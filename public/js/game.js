/* =====================================================================
 * TradeSmart Operations Quiz Arena – game screen controller
 * Requires: GSAP, Howler.js, canvas-confetti, SweetAlert2, Bootstrap 5
 *
 * The server decides every selection (question + participant). This file
 * only animates TOWARDS the result it receives, so the wheel always
 * lands on exactly the participant recorded in the database.
 * ===================================================================== */
(() => {
    'use strict';

    const Q = window.QUIZ;
    if (window.gsap) gsap.config({ nullTargetWarn: false });
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const sleep = ms => new Promise(r => setTimeout(r, ms));
    const rand = arr => arr[Math.floor(Math.random() * arr.length)];
    const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const get = (obj, path) => path.split('.').reduce((o, k) => (o == null ? o : o[k]), obj);

    // ---------------------------------------------------------------- state
    const S = {
        session: null, config: null, participants: [], teams: [], leaderboard: [], teamBoard: [],
        round: null, history: [], canUndo: false, reactions: { correct: [], incorrect: [] }, audio: null,
        phase: 'idle',      // idle | spinning | picked (step 1 done) | reveal | scored (step 2)
        busy: false, answerShown: false, started: false, prevRanks: {},
    };

    // ================================================================ API
    async function api(method, path, body) {
        const opts = { method, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': Q.csrf, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' };
        if (body !== undefined) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
        let res;
        try {
            res = await fetch(Q.api + path, opts);
        } catch (e) {
            toast('Connection problem – the action was not saved. Check the network and try again.', 'danger');
            throw e;
        }
        if (res.status === 419 || res.status === 401) {
            await Swal.fire({ icon: 'warning', title: 'Session expired', text: 'Please sign in again. Your game is saved.', confirmButtonColor: '#4EAADF' });
            location.reload();
            throw new Error('expired');
        }
        let data = {};
        try { data = await res.json(); } catch (_) { /* empty */ }
        if (!res.ok) {
            const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Something went wrong.');
            toast(msg, res.status >= 500 ? 'danger' : 'warning');
            const err = new Error(msg); err.status = res.status; throw err;
        }
        return data;
    }

    function toast(msg, type = 'info') {
        const el = document.createElement('div');
        el.className = 'toast align-items-center show mb-2';
        el.setAttribute('role', 'alert');
        const icon = { info: 'info-circle', success: 'check-circle', warning: 'exclamation-triangle', danger: 'x-octagon' }[type] || 'info-circle';
        el.innerHTML = `<div class="d-flex"><div class="toast-body"><i class="bi bi-${icon} me-2"></i>${esc(msg)}</div><button type="button" class="btn-close me-2 m-auto"></button></div>`;
        el.querySelector('button').onclick = () => el.remove();
        $('#toasts').appendChild(el);
        setTimeout(() => el.remove(), 5000);
    }

    // ================================================================ AUDIO
    const Audio = {
        howls: {}, failed: new Set(), music: null, ctx: null, unlocked: false, loops: {},
        cfg() { return S.config?.audio || { enabled: true, sfx_enabled: true, music_enabled: false, sfx_volume: .8, music_volume: .25, theme: 'gameshow' }; },
        unlock() {
            this.unlocked = true;
            try { if (Howler.ctx && Howler.ctx.state === 'suspended') Howler.ctx.resume(); } catch (_) { }
            this.preload();
            this.updateMusic();
        },
        url(key, theme) { return `${Q.sounds}/${theme || this.cfg().theme}/${key}.mp3`; },
        preload() {
            if (!S.audio) return;
            Object.keys(S.audio.catalogue).forEach(k => this.howl(k));
        },
        howl(key, theme) {
            theme = theme || this.cfg().theme;
            const id = theme + ':' + key;
            if (this.howls[id]) return this.howls[id];
            try {
                const h = new Howl({
                    src: [this.url(key, theme)], preload: true, html5: false,
                    loop: key === 'music_lobby' || key === 'slot_spin' || key === 'wheel_spin',
                    onloaderror: () => this.failed.add(id),
                    onplayerror: () => { h.once('unlock', () => { }); },
                });
                this.howls[id] = h;
                return h;
            } catch (e) { this.failed.add(id); return null; }
        },
        enabled(isMusic = false) {
            const c = this.cfg();
            return this.unlocked && c.enabled && (isMusic ? c.music_enabled : c.sfx_enabled);
        },
        /** Play a specific sound key. Never throws. */
        playKey(key, { volume = 1, rate = 1, force = false } = {}) {
            try {
                if (!force && !this.enabled()) return null;
                if (S.audio?.disabled?.includes(key)) return null;
                const id = this.cfg().theme + ':' + key;
                if (this.failed.has(id)) { this.synth(key); return null; }
                const h = this.howl(key);
                if (!h) { this.synth(key); return null; }
                const sid = h.play();
                h.volume(clamp(this.cfg().sfx_volume * volume, 0, 1), sid);
                if (rate !== 1) h.rate(rate, sid);
                return { h, sid };
            } catch (e) { return null; }
        },
        /** Play the sound(s) mapped to a game event. */
        play(event, opts) {
            const keys = S.audio?.events?.[event];
            if (!keys || !keys.length) return null;
            return this.playKey(rand(keys), opts);
        },
        loop(event, on) {
            try {
                if (on) {
                    if (!this.enabled()) return;
                    const keys = S.audio?.events?.[event]; if (!keys?.length) return;
                    const key = keys[0]; if (S.audio.disabled?.includes(key)) return;
                    const h = this.howl(key); if (!h) return;
                    const sid = h.play(); h.volume(this.cfg().sfx_volume * .55, sid);
                    this.loops[event] = { h, sid };
                } else if (this.loops[event]) {
                    const { h, sid } = this.loops[event];
                    h.fade(h.volume(sid), 0, 250, sid);
                    setTimeout(() => { try { h.stop(sid); } catch (_) { } }, 260);
                    delete this.loops[event];
                }
            } catch (_) { }
        },
        updateMusic() {
            try {
                const want = this.enabled(true) && !S.audio?.disabled?.includes('music_lobby');
                if (want) {
                    if (!this.music || this.music._theme !== this.cfg().theme) {
                        if (this.music) this.music.stop();
                        this.music = this.howl('music_lobby'); if (!this.music) return;
                        this.music._theme = this.cfg().theme;
                        this.music.loop(true);
                        this.music.play();
                    }
                    if (!this.music.playing()) this.music.play();
                    this.music.volume(this.cfg().music_volume);
                } else if (this.music) {
                    this.music.pause();
                }
            } catch (_) { }
        },
        duck(on) { try { if (this.music) this.music.fade(this.music.volume(), on ? this.cfg().music_volume * .3 : this.cfg().music_volume, 400); } catch (_) { } },
        /** Fallback beep when a sound file is missing – gameplay must never break. */
        synth(key) {
            try {
                const ctx = this.ctx || (this.ctx = new (window.AudioContext || window.webkitAudioContext)());
                const o = ctx.createOscillator(), g = ctx.createGain();
                const good = /correct|applause|victory|jackpot|level|streak|winner|champ|celebr|confetti|reveal|select|start/.test(key);
                const bad = /wrong|buzzer|times_up|danger|fail|trombone|disappoint/.test(key);
                o.type = bad ? 'sawtooth' : 'triangle';
                o.frequency.value = bad ? 140 : good ? 660 : 880;
                g.gain.value = .08 * this.cfg().sfx_volume;
                o.connect(g).connect(ctx.destination);
                o.start(); g.gain.exponentialRampToValueAtTime(.0001, ctx.currentTime + (key.includes('tick') ? .05 : .35));
                o.stop(ctx.currentTime + .4);
            } catch (_) { }
        },
    };

    // ================================================================ PARTICLES (subtle background)
    const Particles = {
        c: null, ctx: null, dots: [], run: false,
        init() { this.c = $('#particles'); this.ctx = this.c.getContext('2d'); window.addEventListener('resize', () => this.size()); this.size(); },
        size() { this.c.width = innerWidth; this.c.height = innerHeight; },
        configure() {
            const reduce = S.config?.animation?.reduce_motion || matchMedia('(prefers-reduced-motion: reduce)').matches;
            const n = reduce ? 0 : ({ low: 25, normal: 55, high: 110 }[S.config?.animation?.intensity] ?? 55);
            this.dots = Array.from({ length: n }, () => this.dot(true));
            if (n && !this.run) { this.run = true; requestAnimationFrame(t => this.tick(t)); }
            if (!n) { this.run = false; this.ctx.clearRect(0, 0, this.c.width, this.c.height); }
        },
        dot(initial) {
            return { x: Math.random() * innerWidth, y: initial ? Math.random() * innerHeight : innerHeight + 10, r: Math.random() * 2 + .5,
                vy: -(Math.random() * .25 + .05), vx: (Math.random() - .5) * .1, a: Math.random() * .5 + .15, col: Math.random() > .5 ? '78,170,223' : '134,196,64' };
        },
        tick() {
            if (!this.run) return;
            const { ctx } = this; ctx.clearRect(0, 0, this.c.width, this.c.height);
            for (const d of this.dots) {
                d.x += d.vx; d.y += d.vy;
                if (d.y < -10) Object.assign(d, this.dot(false));
                ctx.beginPath(); ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(${d.col},${d.a})`; ctx.fill();
            }
            requestAnimationFrame(() => this.tick());
        },
    };

    // ================================================================ WHEEL
    const PALETTE = ['#3E9FD8', '#76B82A', '#1F6FA3', '#5E9A1F', '#7D858D', '#5DB4E3', '#8FC74F', '#2D4F73'];  // TradeSmart blues, greens & greys
    const Wheel = {
        canvas: null, ctx: null, list: [], rotation: 0, highlight: -1, glow: 0, idleTween: null, size: 0, lastIdx: -1, lastTick: 0,
        init() {
            this.canvas = $('#wheelCanvas'); this.ctx = this.canvas.getContext('2d');
            new ResizeObserver(() => this.resize()).observe($('#wheelBox'));
        },
        setList(people) {
            const ids = people.map(p => p.id).join(',');
            if (ids === this.list.map(p => p.id).join(',')) { this.list = people; this.draw(); return; }
            this.list = people; this.highlight = -1; this.draw();
        },
        resize() {
            const box = $('#wheelBox').getBoundingClientRect();
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            this.size = Math.max(50, box.width);
            this.canvas.width = this.size * dpr; this.canvas.height = this.size * dpr;
            this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            this.draw();
        },
        colorFor(i, n) {
            let c = PALETTE[i % PALETTE.length];
            if (i === n - 1 && n > 1 && c === PALETTE[0]) c = PALETTE[(i + 3) % PALETTE.length]; // avoid same colour touching the first segment
            return c;
        },
        label(p, n, R) {
            const maxW = R * .66;
            const arc = 2 * Math.PI * (R * .62) / Math.max(n, 1);
            let text = p.name || '?';
            let fs = Math.min(R * .12, arc * .62);
            const fit = t => R * .62 / (Math.max(t.length, 3) * .58);
            if (fs > fit(text)) fs = fit(text);
            if (fs < R * .045) {                     // too small: try first word, then initials
                text = text.split(' ')[0];
                fs = Math.min(R * .1, arc * .62, fit(text));
                if (fs < R * .04) { text = p.initials || text.slice(0, 2); fs = Math.min(R * .09, arc * .66, fit(text)); }
            }
            return { text, fs, maxW, show: fs >= R * .025 };
        },
        draw() {
            const { ctx } = this; const n = this.list.length; const sz = this.size; if (!sz) return;
            const cx = sz / 2, cy = sz / 2, R = sz / 2 - 4;
            ctx.clearRect(0, 0, sz, sz);
            // white rim
            ctx.beginPath(); ctx.arc(cx, cy, R, 0, Math.PI * 2); ctx.fillStyle = '#ffffff'; ctx.fill();
            ctx.lineWidth = Math.max(1, R * .008); ctx.strokeStyle = '#E2E8EF'; ctx.stroke();
            if (!n) {
                ctx.fillStyle = '#66717E'; ctx.font = `700 ${R * .08}px Montserrat, sans-serif`; ctx.textAlign = 'center';
                ctx.fillText('Add participants', cx, cy + R * .4); return;
            }
            const seg = Math.PI * 2 / n, r = R * .93;
            for (let i = 0; i < n; i++) {
                const a0 = -Math.PI / 2 + i * seg, a1 = a0 + seg;
                ctx.beginPath(); ctx.moveTo(cx, cy); ctx.arc(cx, cy, r, a0, a1); ctx.closePath();
                const col = this.list[i].team_color && S.config?.modes?.team_mode ? this.list[i].team_color : this.colorFor(i, n);
                const g = ctx.createRadialGradient(cx, cy, r * .15, cx, cy, r);
                g.addColorStop(0, shade(col, .12)); g.addColorStop(1, col);
                const dim = this.highlight >= 0 && i !== this.highlight;
                ctx.globalAlpha = dim ? .35 + .3 * (1 - this.glow) : 1;
                ctx.fillStyle = g; ctx.fill();
                ctx.globalAlpha = 1;
                ctx.lineWidth = i === this.highlight ? 3 + 3 * this.glow : (n > 40 ? 1 : 2.5); ctx.strokeStyle = '#ffffff'; ctx.stroke();
                // label
                const L = this.label(this.list[i], n, r);
                if (L.show) {
                    ctx.save(); ctx.translate(cx, cy); ctx.rotate(a0 + seg / 2);
                    ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
                    ctx.font = `800 ${L.fs}px Montserrat, sans-serif`;
                    ctx.globalAlpha = this.highlight >= 0 && i !== this.highlight ? .6 : 1;
                    ctx.fillStyle = '#fff'; ctx.shadowColor = 'rgba(0,0,0,.25)'; ctx.shadowBlur = 3;
                    ctx.fillText(L.text, r * .92, 0, L.maxW);
                    ctx.restore();
                }
            }
            // small tick marks on the rim between segments
            if (n <= 60) for (let i = 0; i < n; i++) {
                const a = -Math.PI / 2 + i * seg;
                ctx.beginPath(); ctx.arc(cx + Math.cos(a) * (R * .965), cy + Math.sin(a) * (R * .965), R * .011, 0, Math.PI * 2);
                ctx.fillStyle = '#A7A9AC'; ctx.fill();
            }
        },
        indexAt(rot) {
            const n = this.list.length; if (!n) return -1;
            const seg = 360 / n; const a = ((-rot % 360) + 360) % 360;
            return Math.floor(a / seg) % n;
        },
        apply(rot) {
            this.rotation = rot;
            gsap.set(this.canvas, { rotation: rot, force3D: true });
            const idx = this.indexAt(rot);
            if (idx !== this.lastIdx) {
                this.lastIdx = idx;
                const now = performance.now();
                if (S.phase === 'spinning' && now - this.lastTick > 45) {
                    this.lastTick = now;
                    Audio.play('wheel_tick', { volume: .5 });
                    gsap.fromTo('#wheelPointer', { rotation: -18 }, { rotation: 0, duration: .18, ease: 'power2.out', overwrite: true });
                }
            }
        },
        startIdle() {
            this.stopIdle();
            if (S.config?.animation?.reduce_motion) return;
            const p = { r: this.rotation };
            this.idleTween = gsap.to(p, { r: this.rotation + 360, duration: 90, ease: 'none', repeat: -1, onUpdate: () => this.apply(p.r) });
        },
        stopIdle() { if (this.idleTween) { this.idleTween.kill(); this.idleTween = null; } },
        /** Spin so that the participant at index `target` ends under the pointer. */
        spinTo(target, t) {
            this.stopIdle(); this.highlight = -1; this.draw();
            const n = this.list.length; const seg = 360 / n;
            const jitter = (Math.random() - .5) * seg * .6;
            const finalMod = ((-(target + .5) * seg + jitter) % 360 + 360) % 360;
            const factor = t.accel / 3 + t.fast + t.slow / 4;   // distance = v * factor (velocity-matched phases)
            const desiredV = 760;                               // deg/s at full speed
            const start = this.rotation;
            let dist = desiredV * factor;
            const startMod = ((start % 360) + 360) % 360;
            let delta = finalMod - startMod; if (delta < 0) delta += 360;
            dist = Math.max(2, Math.round((dist - delta) / 360)) * 360 + delta;
            const v = dist / factor;
            const d1 = v * t.accel / 3, d2 = v * t.fast, d3 = dist - d1 - d2;
            const p = { r: start };
            return new Promise(resolve => {
                gsap.timeline({ onComplete: resolve })
                    .to(p, { r: start + d1, duration: t.accel, ease: 'power2.in', onUpdate: () => this.apply(p.r) })
                    .to(p, { r: start + d1 + d2, duration: t.fast, ease: 'none', onUpdate: () => this.apply(p.r) })
                    .add(() => Audio.playKey('wheel_slow', { volume: .6 }))
                    .to(p, { r: start + dist, duration: t.slow, ease: 'power3.out', onUpdate: () => this.apply(p.r) });
            });
        },
        celebrate(index) {
            this.highlight = index;
            const g = { v: 0 };
            gsap.to(g, { v: 1, duration: .35, repeat: 5, yoyo: true, ease: 'sine.inOut', onUpdate: () => { this.glow = g.v; this.draw(); } });
        },
    };

    function shade(hex, amt) {
        const n = parseInt(hex.slice(1), 16);
        const r = clamp((n >> 16) + 255 * amt, 0, 255), g = clamp(((n >> 8) & 255) + 255 * amt, 0, 255), b = clamp((n & 255) + 255 * amt, 0, 255);
        return `rgb(${r | 0},${g | 0},${b | 0})`;
    }

    // ================================================================ TIMER
    const Timer = {
        total: 30000, remaining: 30000, running: false, disabled: false, raf: 0, lastSec: null, lastHalf: null, last: 0, roundId: null, expired: false,
        C: 2 * Math.PI * 52,
        el() { return $('#timer'); },
        setup(round) {
            this.stop();
            this.roundId = round?.id || null;
            const secs = round?.timer;
            this.disabled = !secs || !S.config.timer.enabled;
            this.total = (secs || S.config.timer.default_seconds) * 1000;
            this.remaining = this.total; this.expired = false; this.lastSec = null;
            const saved = this.load();
            if (saved && !this.disabled) {
                this.remaining = saved.remaining; this.disabled = saved.disabled; this.expired = saved.expired;
                if (saved.running) { this.remaining -= (Date.now() - saved.savedAt); if (this.remaining > 0) this.start(true); else { this.remaining = 0; this.expired = true; } }
            }
            this.render();
        },
        key() { return `qa_timer_${Q.sessionId}_${this.roundId}`; },
        save() { try { if (this.roundId) localStorage.setItem(this.key(), JSON.stringify({ remaining: this.remaining, running: this.running, disabled: this.disabled, expired: this.expired, savedAt: Date.now() })); } catch (_) { } },
        load() { try { return JSON.parse(localStorage.getItem(this.key()) || 'null'); } catch (_) { return null; } },
        clear() { try { localStorage.removeItem(this.key()); } catch (_) { } },
        start(silent) {
            if (this.disabled || this.running || this.remaining <= 0) return;
            this.running = true; this.last = performance.now();
            if (!silent) Audio.play('timer_start', { volume: .7 });
            const loop = t => {
                if (!this.running) return;
                this.remaining -= (t - this.last); this.last = t;
                if (this.remaining <= 0) { this.remaining = 0; this.running = false; this.render(); this.timeUp(); this.save(); return; }
                this.render(); this.raf = requestAnimationFrame(loop);
            };
            this.raf = requestAnimationFrame(loop); this.save(); updateControls();
        },
        pause() { if (!this.running) return; this.running = false; cancelAnimationFrame(this.raf); this.render(); this.save(); updateControls(); },
        toggle() { this.running ? this.pause() : this.start(); },
        stop() { this.running = false; cancelAnimationFrame(this.raf); },
        reset() { this.stop(); this.remaining = this.total; this.expired = false; this.lastSec = null; this.el().classList.remove('done'); this.render(); this.save(); updateControls(); },
        add(s) { if (this.disabled) return; this.remaining += s * 1000; this.total = Math.max(this.total, this.remaining); this.expired = false; this.render(); this.save(); },
        disable() { this.stop(); this.disabled = true; this.render(); this.save(); updateControls(); },
        render() {
            const el = this.el(); const sec = Math.ceil(this.remaining / 1000);
            const warn = S.config.timer.warning_at, crit = S.config.timer.critical_at;
            el.classList.toggle('off', this.disabled);
            el.classList.toggle('warn', !this.disabled && sec <= warn && sec > crit);
            el.classList.toggle('crit', !this.disabled && sec <= crit && this.remaining > 0);
            el.classList.toggle('done', !this.disabled && this.remaining <= 0);
            $('#timerNum').textContent = this.disabled ? '∞' : sec;
            $('#timerLabel').textContent = this.disabled ? 'NO TIMER' : (this.running ? 'SECONDS' : (this.remaining <= 0 ? "TIME'S UP" : (this.remaining >= this.total ? 'READY' : 'PAUSED')));
            $('#timerBar').style.strokeDashoffset = this.disabled ? 0 : this.C * (1 - this.remaining / this.total);
            // ticking: once per second in warning zone, twice per second in critical zone
            if (this.running && S.config.timer.ticking) {
                const half = Math.ceil(this.remaining / 500);
                if (sec !== this.lastSec && sec <= warn && sec > 0) { Audio.play('timer_tick', { volume: sec <= crit ? 1 : .6 }); if (sec % 1 === 0) this.save(); }
                else if (sec <= crit && half !== this.lastHalf && half % 2 === 1) Audio.play('timer_tick', { volume: .5, rate: 1.3 });
                this.lastHalf = half;
            }
            this.lastSec = sec;
        },
        async timeUp() {
            if (this.expired) return; this.expired = true;
            Audio.play('times_up');
            banner("TIME'S UP!", '', '#FF4D6D', 1400);
            document.body.classList.add('flash-red'); setTimeout(() => document.body.classList.remove('flash-red'), 700);
            if (!reduceMotion()) gsap.fromTo('#revealCard', { x: -12 }, { x: 0, duration: .5, ease: 'elastic.out(1, .25)' });
            updateControls();
            if (S.config.timer.auto_timeout && S.phase === 'reveal') { await sleep(1500); if (S.phase === 'reveal') score('timed_out'); }
        },
        secondsRemaining() { return this.disabled ? null : Math.ceil(this.remaining / 1000); },
    };

    // ================================================================ OVERLAYS
    function reduceMotion() { return !!S.config?.animation?.reduce_motion; }

    function banner(text, sub = '', color = '#4EAADF', ms = 1300) {
        const o = $('#bannerOverlay');
        $('#bannerText').textContent = text; $('#bannerSub').textContent = sub;
        o.style.setProperty('--bc', color); o.classList.add('show');
        gsap.killTweensOf('#bannerText');
        const tl = gsap.timeline({ onComplete: () => o.classList.remove('show') });
        if (reduceMotion()) tl.fromTo('#bannerText', { opacity: 0 }, { opacity: 1, duration: .2 }).to('#bannerText', { opacity: 0, duration: .3, delay: ms / 1000 });
        else tl.fromTo('#bannerText', { scale: 3, opacity: 0, rotation: -6 }, { scale: 1, opacity: 1, rotation: 0, duration: .45, ease: 'back.out(2)' })
            .fromTo('#bannerSub', { y: 20, opacity: 0 }, { y: 0, opacity: 1, duration: .3 }, '-=.15')
            .to(['#bannerText', '#bannerSub'], { scale: .6, opacity: 0, duration: .35, ease: 'power2.in', delay: ms / 1000 });
        return sleep(ms + 500);
    }

    function confettiBurst(kind = 'small') {
        if (reduceMotion() || typeof confetti !== 'function') return;
        const mult = { low: .4, normal: 1, high: 1.8 }[S.config?.animation?.intensity] ?? 1;
        const colors = ['#4EAADF', '#86C440', '#A7A9AC', '#1F7FB8', '#5E9A1F', '#F2B705'];
        try {
            if (kind === 'big') {
                const end = Date.now() + 2500;
                (function frame() {
                    confetti({ particleCount: Math.round(6 * mult), angle: 60, spread: 70, origin: { x: 0, y: .7 }, colors });
                    confetti({ particleCount: Math.round(6 * mult), angle: 120, spread: 70, origin: { x: 1, y: .7 }, colors });
                    if (Date.now() < end) requestAnimationFrame(frame);
                })();
                confetti({ particleCount: Math.round(200 * mult), spread: 120, origin: { y: .45 }, colors, scalar: 1.2 });
            } else {
                confetti({ particleCount: Math.round(90 * mult), spread: 80, origin: { y: .6 }, colors });
            }
            Audio.play('confetti', { volume: .6 });
        } catch (_) { }
    }

    async function reaction(result, points, streak) {
        const o = $('#reactionOverlay');
        const good = result === 'correct' || (result === 'partial' && points > 0);
        let text, color;
        if (result === 'correct') { text = rand(S.reactions.correct); color = '#86C440'; }
        else if (result === 'partial') { text = 'HALF MARKS!'; color = '#4EAADF'; }
        else if (result === 'passed') { text = 'PASSED!'; color = '#A7A9AC'; }
        else if (result === 'timed_out') { text = "TIME'S UP!"; color = '#FF9F1C'; }
        else { text = rand(S.reactions.incorrect); color = '#FF4D6D'; }
        $('#reactionText').textContent = text;
        $('#reactionPoints').textContent = points > 0 ? `+${points}` : (points < 0 ? `${points}` : '');
        o.style.setProperty('--rc', color); o.classList.add('show');

        if (result === 'correct') {
            Audio.play('correct'); setTimeout(() => Audio.play('correct_extra', { volume: .8 }), 350);
            confettiBurst(points >= 20 ? 'big' : 'small');
            if (streak >= (S.config.scoring.streak_threshold || 3)) setTimeout(() => { Audio.play('streak'); toast(`🔥 ${S.round?.participant?.name} is on a ${streak}-answer streak!`, 'success'); }, 900);
        } else if (result === 'incorrect' || result === 'timed_out') {
            Audio.play('incorrect'); setTimeout(() => Audio.play('incorrect_funny', { volume: .9 }), 450);
            document.body.classList.add('flash-red'); setTimeout(() => document.body.classList.remove('flash-red'), 700);
        } else if (result === 'passed') Audio.play('pass');
        else Audio.play('correct', { volume: .7 });

        const tl = gsap.timeline({ onComplete: () => o.classList.remove('show') });
        if (reduceMotion()) {
            tl.fromTo([...o.children], { opacity: 0 }, { opacity: 1, duration: .2 }).to([...o.children], { opacity: 0, duration: .3, delay: 1.3 });
        } else if (good) {
            tl.fromTo('#reactionText', { scale: .2, opacity: 0, rotation: -10 }, { scale: 1, opacity: 1, rotation: 0, duration: .55, ease: 'back.out(2.5)' })
                .fromTo('#reactionPoints', { y: 40, opacity: 0 }, { y: 0, opacity: 1, duration: .35, ease: 'power2.out' }, '-=.2')
                .to('#reactionText', { scale: 1.08, duration: .25, yoyo: true, repeat: 1 })
                .to([...o.children], { y: -60, opacity: 0, duration: .4, ease: 'power2.in', delay: .5 });
        } else {
            tl.fromTo('#reactionText', { y: -200, opacity: 0 }, { y: 0, opacity: 1, duration: .6, ease: 'bounce.out' })
                .fromTo('#reactionText', { x: -14 }, { x: 0, duration: .5, ease: 'elastic.out(1, .2)' })
                .fromTo('#reactionPoints', { opacity: 0 }, { opacity: 1, duration: .2 }, '<')
                .to([...o.children], { opacity: 0, scale: .8, duration: .35, delay: .6 });
        }
        return tl;
    }

    // ================================================================ RENDERING
    function eligible() { return S.participants.filter(p => p.active && !p.excluded); }

    function renderHeader() {
        const s = S.session; if (!s) return;
        $('#hdrRound').textContent = s.current_round;
        $('#hdrTotal').textContent = s.total_rounds;
        $('#hdrWorkshop').textContent = s.workshop_title || s.title;
        $('#hdrDate').textContent = s.session_date || '';
        const chip = $('#modeChip');
        const queued = s.hot_seat?.remaining ? `🔥 HOT SEAT ×${s.hot_seat.remaining}` : s.speed_remaining ? `⚡ SPEED ×${s.speed_remaining}` :
            s.pending_mode ? ({ bonus: '⭐ BONUS NEXT', danger: '⚠️ DANGER NEXT', mystery: '🎁 MYSTERY NEXT', hot_seat: '🔥 HOT SEAT NEXT' }[s.pending_mode] || '') : '';
        chip.textContent = queued; chip.classList.toggle('d-none', !queued);
        const d = S.config.display;
        $$('[data-display=logo]').forEach(e => e.classList.toggle('d-none', !d.show_logo));
        $$('[data-display=date]').forEach(e => e.classList.toggle('d-none', !d.show_date || !s.session_date));
        $('#miniBoard').classList.toggle('d-none', !d.show_leaderboard);
        $('#tabTeams').classList.toggle('d-none', !S.teamBoard.length);
        $('#btnSound').classList.toggle('off', !S.config.audio.enabled);
        $('#btnSound i').className = S.config.audio.enabled ? 'bi bi-volume-up' : 'bi bi-volume-mute';
        document.body.classList.toggle('reduce-motion', reduceMotion());
        renderMiniBoard();
    }

    function renderMiniBoard() {
        const top = S.leaderboard.slice(0, 5);
        $('#miniBoard').innerHTML = '<div class="mb-title">TOP 5</div>' + (top.length ? top.map(r =>
            `<div class="mb-row"><span>${r.rank}.</span><span>${esc(r.name)}</span><b>${r.score}</b></div>`).join('') : '<div class="mb-row text-muted">No scores yet</div>');
    }

    function avatarHtml(p, cls = '') {
        if (p?.photo && S.config.display.show_photos) return `<img src="${esc(p.photo)}" alt="" class="${cls}">`;
        return esc(p?.initials || '?');
    }

    function renderReveal(round) {
        const q = round.question;
        const card = $('#revealCard');
        card.style.setProperty('--cat', q?.category_color || (round.type === 'danger' ? '#FF4D6D' : '#FFD23F'));
        $('#rcCategory').innerHTML = q ? `<i class="bi bi-${esc(q.category_icon || 'tag')}"></i> ${esc(q.category || '')}` : '🎭 CHALLENGE';
        $('#rcMeta').textContent = q ? `${q.type_label} · ${q.difficulty}` : (round.mystery_label || '');
        renderPoints(round);
        // special banner inside card
        const sp = $('#rcSpecial'); sp.className = 'rc-special'; let spText = '';
        if (round.type === 'bonus') spText = `⭐ BONUS ROUND · ×${round.multiplier} POINTS`;
        if (round.type === 'danger') { spText = `⚠️ DANGER ROUND · ×${round.multiplier}${S.config.scoring.danger_negative ? ' · WRONG ANSWERS LOSE POINTS' : ''}`; sp.classList.add('danger'); }
        if (round.type === 'mystery') spText = round.mystery_effect === 'mystery_prize' && round.challenge_text ? `🎁 ${round.challenge_text}` : `🎁 MYSTERY: ${round.mystery_label || ''}`;
        if (round.type === 'hot_seat') spText = `🔥 HOT SEAT · QUESTION ${round.hot_seat_index} OF ${S.config.modes.hot_seat_questions}`;
        if (round.type === 'speed') spText = '⚡ SPEED ROUND';
        if (S.session.current_round >= S.session.total_rounds) spText = (spText ? spText + ' · ' : '') + '🏁 FINAL ROUND';
        sp.textContent = spText; sp.classList.toggle('d-none', !spText);
        // question text
        const text = q ? q.text : (round.challenge_text || 'Mystery challenge!');
        const qe = $('#rcQuestion'); qe.textContent = text;
        qe.classList.toggle('long', text.length > 120 && text.length <= 200);
        qe.classList.toggle('xlong', text.length > 200);
        // options
        const opts = q?.options || [];
        $('#rcOptions').innerHTML = opts.map(o => `<div class="rc-opt" data-correct="${o.correct ? 1 : 0}"><b>${esc(o.label)}</b><span>${esc(o.text)}</span></div>`).join('');
        $('#rcOptions').style.gridTemplateColumns = opts.length === 2 ? '1fr 1fr' : (opts.length > 4 ? '1fr 1fr' : '1fr 1fr');
        // answer
        $('#rcAnswerText').textContent = q?.correct_answer || (q ? '' : 'Facilitator decides!');
        $('#rcExplanation').textContent = q?.explanation || '';
        showAnswer(round.status === 'answered');
        // spotlight
        const p = round.participant || {};
        const fullP = S.participants.find(x => x.id === p.id) || {};
        $('#spAvatar').innerHTML = avatarHtml(p);
        $('#spAvatar').style.background = round.team?.color ? `linear-gradient(135deg, ${round.team.color}, ${shade(round.team.color, -.15)})` : '';
        $('#spName').textContent = p.name || '';
        $('#spRole').textContent = [p.job_title, p.department].filter(Boolean).join(' · ');
        const badges = [];
        if (round.team) badges.push(`<span class="team" style="--tc:${esc(round.team.color)}">${esc(round.team.name)}</span>`);
        if (fullP.streak >= 2) badges.push(`<span class="streak">🔥 ${fullP.streak} streak</span>`);
        if (fullP.score !== undefined) badges.push(`<span>${fullP.score} pts</span>`);
        if (round.double_or_nothing) badges.push('<span class="double">🎲 DOUBLE OR NOTHING</span>');
        $('#spBadges').innerHTML = badges.join('');
    }

    function renderPoints(round) {
        const pts = round.double_or_nothing ? round.points * 2 : round.points;
        $('#rcPoints').innerHTML = `${pts}<small>PTS${round.double_or_nothing ? ' OR 0' : ''}</small>`;
    }

    function showAnswer(on) {
        S.answerShown = on;
        $('#revealCard').classList.toggle('answered', on);
        $('#rcAnswer').classList.toggle('show', on && (!!$('#rcAnswerText').textContent || !!$('#rcExplanation').textContent));
        $$('#rcOptions .rc-opt').forEach(o => { o.classList.toggle('correct', on && o.dataset.correct === '1'); o.classList.toggle('dim', on && o.dataset.correct !== '1'); });
        if (on && !reduceMotion()) gsap.fromTo('#rcAnswer', { y: 20, opacity: 0 }, { y: 0, opacity: 1, duration: .4 });
    }

    function setPhase(phase) {
        S.phase = phase;
        const arena = $('#arena');
        const step2 = phase === 'reveal' || phase === 'scored';
        arena.classList.toggle('phase-reveal', step2);
        arena.classList.toggle('phase-idle', !step2);
        arena.classList.toggle('phase-picked', phase === 'picked');
        $$('.steps .step').forEach(el => {
            const n = +el.dataset.step;
            el.classList.toggle('active', (n === 1 && !step2) || (n === 2 && step2));
            el.classList.toggle('done', (n === 1 && (phase === 'picked' || step2)) || (n === 2 && phase === 'scored'));
        });
        updateControls();
    }

    function updateControls() {
        const open = S.phase === 'reveal';
        const scored = S.phase === 'scored';
        const paused = S.session?.status === 'paused';
        const finished = !!S.session?.finished;
        const completed = S.session?.status === 'completed';
        $('#btnSpin').disabled = S.busy || paused || completed || S.phase !== 'idle';
        $('#btnShowQ').disabled = S.busy || S.phase !== 'picked';
        $$('#cbScore .cb-btn').forEach(b => b.disabled = !open || S.busy);
        $$('#cbTimer .cb-btn').forEach(b => b.disabled = !open || Timer.disabled);
        const tBtn = $('[data-act=timer-toggle]');
        tBtn.querySelector('i').className = Timer.running ? 'bi bi-pause-fill' : 'bi bi-play-fill';
        tBtn.querySelector('span').textContent = Timer.running ? 'Pause' : (Timer.remaining < Timer.total && Timer.remaining > 0 ? 'Resume' : 'Start timer');
        $('[data-act=reveal]').disabled = !(open || scored);
        const dbl = $('[data-act=double]');
        dbl.disabled = !open || !S.config?.modes?.double_or_nothing_enabled || S.busy;
        dbl.classList.toggle('on', !!S.round?.double_or_nothing && open);
        $$('[data-act=undo]').forEach(b => b.disabled = !S.canUndo || S.busy || S.phase === 'spinning' || S.phase === 'picked' || open || completed);
        const next = $('[data-act=next]');
        next.disabled = !scored || S.busy;
        next.querySelector('span').textContent = finished ? 'CROWN CHAMPION' : 'NEXT ROUND';
        next.querySelector('i').className = finished ? 'bi bi-trophy-fill' : 'bi bi-arrow-right-circle-fill';
        if (S.phase === 'idle' || S.phase === 'spinning') {
            $('#stepTitle').textContent = finished ? 'All rounds played!' : S.phase === 'spinning' ? 'Spinning…' : 'Who’s answering next?';
            $('#centreStatus').innerHTML = completed ? 'This session is completed.' : paused ? 'Session paused' : finished ? 'Time to crown the champion 🏆' :
                S.session?.selection_mode === 'facilitator' ? 'Press <kbd>Space</kbd> and choose who answers' : `Press <kbd>Space</kbd> to spin<br><small>${S.session?.remaining_questions ?? '–'} questions left</small>`;
        }
        if (finished && S.phase === 'idle') { $('#btnSpin .spin-text').textContent = 'CROWN THE CHAMPION'; $('#btnSpin .spin-icon').className = 'bi bi-trophy-fill spin-icon'; $('#btnSpin').disabled = S.busy; }
        else { $('#btnSpin .spin-text').textContent = 'SPIN THE WHEEL'; $('#btnSpin .spin-icon').className = 'bi bi-arrow-repeat spin-icon'; }
    }

    function applyScores(d) {
        if (d.session) S.session = d.session;
        if (d.participants) S.participants = d.participants;
        if (d.leaderboard) S.leaderboard = d.leaderboard;
        if (d.team_leaderboard) S.teamBoard = d.team_leaderboard;
        if (d.teams) S.teams = d.teams;
        if (d.history) S.history = d.history;
        if ('can_undo' in d) S.canUndo = d.can_undo;
        if (d.round !== undefined) S.round = d.round;
        renderHeader();
        if ($('#boardOverlay').classList.contains('show')) renderBoard();
        if ($('#settingsPanel').classList.contains('show')) renderPeople();
        Wheel.setList(eligible());
        updateControls();
    }

    // ================================================================ GAME FLOW
    // Step 1: the wheel picks who answers.  Step 2: "Question for <name>" with timer + scoring controls.
    function timings(round) {
        const a = S.config.animation;
        let f = reduceMotion() ? .45 : 1;
        if (round?.type === 'speed' || (S.session?.speed_remaining > 0)) f *= .6;
        const t = { accel: a.accel * f, fast: a.fast * f, slow: a.wheel_slow * f, pause: a.pause * f };
        if (round?.type === 'hot_seat' && round.hot_seat_index > 1) { t.fast = .1; t.slow = 1.2 * f; }
        return { wheel: { accel: t.accel, fast: t.fast, slow: t.slow }, pause: t.pause };
    }

    const TYPE_BANNERS = round => ({
        bonus: ['BONUS ROUND!', `×${round.multiplier} points`, '#F2B705', 'bonus'],
        danger: ['DANGER ROUND!', S.config.scoring.danger_negative ? 'Wrong answers lose points' : 'High stakes!', '#E5484D', 'danger'],
        mystery: ['MYSTERY ROUND!', 'What will it be…?', '#8B5CF6', 'mystery'],
        hot_seat: ['HOT SEAT!', `Question ${round.hot_seat_index} of ${S.config.modes.hot_seat_questions}`, '#F28C28', 'danger'],
        speed: ['SPEED ROUND!', 'Think fast!', '#1F7FB8', 'spin'],
    }[round.type]);

    function showPicked(round) {
        const p = round.participant || {};
        const full = S.participants.find(x => x.id === p.id) || {};
        $('#pkAvatar').innerHTML = avatarHtml(p);
        $('#pkAvatar').style.background = round.team?.color ? `linear-gradient(135deg, ${round.team.color}, ${shade(round.team.color, -.15)})` : '';
        $('#pkName').textContent = p.name || '';
        $('#pkRole').textContent = [p.job_title, p.department].filter(Boolean).join(' · ') || (full.score !== undefined ? `${full.score} points so far` : '');
        const team = $('#pkTeam');
        team.classList.toggle('d-none', !round.team);
        if (round.team) { team.textContent = `Team ${round.team.name}`; team.style.setProperty('--tc', round.team.color); }
        $('#stepTitle').textContent = 'The wheel has chosen…';
    }

    async function spin(participantId = null) {
        if (S.busy || S.phase !== 'idle') return;
        if (S.session.status === 'paused') { showPaused(true); return; }
        if (S.session.status === 'completed') { toast('This session is completed.', 'info'); return; }
        if (S.session.finished) { return crownChampion(); }
        if (!eligible().length) { toast('No eligible participants – add or include people in Settings.', 'warning'); return; }
        if (S.session.selection_mode === 'facilitator' && !participantId) { openPicker(); return; }

        S.busy = true; updateControls();
        Audio.play('spin'); Audio.duck(true);
        const isFinal = S.session.current_round + 1 >= S.session.total_rounds;
        let data;
        try {
            data = await api('POST', '/spin', { round_number: S.session.current_round + 1, participant_id: participantId });
        } catch (e) {
            S.busy = false; Audio.duck(false); updateControls(); return;
        }
        const round = data.round;
        S.session = data.session; S.round = round;
        S.participants = data.wheel || S.participants;
        Wheel.setList(eligible());
        let idx = Wheel.list.findIndex(p => p.id === round.participant?.id);
        if (idx < 0) { Wheel.setList([...Wheel.list, S.participants.find(p => p.id === round.participant.id) || { id: round.participant.id, name: round.participant.name, initials: round.participant.initials }]); idx = Wheel.list.length - 1; }

        setPhase('spinning'); renderHeader();
        const tb = TYPE_BANNERS(round);
        const badge = $('#roundTypeBadge');
        badge.classList.toggle('d-none', !tb && !isFinal);
        if (tb || isFinal) { badge.textContent = isFinal ? '🏁 FINAL ROUND' + (tb ? ' · ' + tb[0] : '') : tb[0]; badge.style.background = isFinal ? '#1E2B3A' : tb[2]; }
        if (isFinal) { Audio.play('final_round'); banner('FINAL ROUND!', 'Winner takes it all', '#1E2B3A', 1100); }
        else if (tb) { Audio.play(tb[3]); banner(tb[0], tb[1], tb[2], 1000); }

        const t = timings(round);
        await Wheel.spinTo(idx, t.wheel);

        Audio.play('wheel_stop'); Wheel.celebrate(idx);
        confettiBurst('small');
        Audio.play('suspense', { volume: .6 });
        showPicked(round);
        setPhase('picked');
        if (!reduceMotion()) {
            gsap.fromTo('#pickedCard', { y: 30, opacity: 0, scale: .95 }, { y: 0, opacity: 1, scale: 1, duration: .5, ease: 'back.out(1.6)' });
            gsap.fromTo('#pkAvatar', { scale: 0, rotation: -60 }, { scale: 1, rotation: 0, duration: .6, ease: 'back.out(2)', delay: .1 });
            gsap.fromTo('#pkName', { y: 15, opacity: 0 }, { y: 0, opacity: 1, duration: .4, delay: .2 });
        }
        S.busy = false; Audio.duck(false); updateControls();
        $('#btnShowQ').focus({ preventScroll: true });
    }

    /** Step 2: show "Question for <name>" with the timer and scoring controls. */
    async function showQuestion() {
        if (S.phase !== 'picked' || S.busy || !S.round) return;
        const round = S.round;
        Timer.setup(round);
        renderReveal(round);
        setPhase('reveal');
        Audio.play('reveal');
        if (!reduceMotion()) {
            gsap.fromTo('#spotlight', { y: -30, opacity: 0 }, { y: 0, opacity: 1, duration: .45, ease: 'power3.out' });
            gsap.fromTo('#revealCard', { y: 40, opacity: 0 }, { y: 0, opacity: 1, duration: .55, ease: 'power3.out', delay: .1 });
            gsap.fromTo('.timer-card', { x: 40, opacity: 0 }, { x: 0, opacity: 1, duration: .5, ease: 'power3.out', delay: .2 });
            gsap.fromTo('.rc-opt', { y: 20, opacity: 0 }, { y: 0, opacity: 1, duration: .35, stagger: .07, delay: .35 });
            gsap.fromTo('#controlBar', { y: 60 }, { y: 0, duration: .45, ease: 'power3.out', clearProps: 'transform' });
        } else gsap.set(['#revealCard', '#spotlight', '.timer-card'], { opacity: 1, x: 0, y: 0 });
        if (round.type === 'mystery' && round.mystery_label) setTimeout(() => banner(round.mystery_label.toUpperCase(), round.challenge_text || '', '#8B5CF6', 1600), 500);
        updateControls();
        if (S.config.timer.auto_start && !Timer.disabled) setTimeout(() => Timer.start(), 700);
    }

    async function score(result, extra = {}) {
        if (S.phase !== 'reveal' || S.busy) return;
        S.busy = true; Timer.pause(); updateControls();
        try {
            const d = await api('POST', '/score', { result, round_id: S.round?.id, seconds_remaining: Timer.secondsRemaining(), ...extra });
            Timer.clear();
            applyScores(d);
            setPhase('scored');
            showAnswer(true);
            reaction(result, d.answer.points, d.streak);
            renderReveal(S.round); showAnswer(true);
        } catch (e) { /* toast already shown */ }
        S.busy = false; updateControls();
    }

    async function next() {
        if (S.phase !== 'scored' || S.busy) return;
        if (S.session.finished) return crownChampion();
        backToIdle();
    }

    function backToIdle() {
        Timer.stop();
        Wheel.highlight = -1; Wheel.draw();
        $('#roundTypeBadge').classList.add('d-none');
        const done = () => { setPhase('idle'); gsap.set(['#revealCard', '#spotlight', '.timer-card', '#pickedCard'], { clearProps: 'all' }); Wheel.startIdle(); };
        if (reduceMotion()) return done();
        gsap.to('#step2', { opacity: 0, duration: .25, onComplete: () => {
            done(); gsap.set('#step2', { clearProps: 'all' });
            gsap.fromTo(['#wheelWrap', '#stepPanel'], { opacity: 0, y: 25 }, { opacity: 1, y: 0, duration: .4, stagger: .08, ease: 'power2.out', clearProps: 'all' });
        } });
    }

    async function undo() {
        if (S.busy || !S.canUndo) return;
        const ok = await confirmBox('Undo the last score?', 'Points and history will be restored and that round re-opened.');
        if (!ok) return;
        S.busy = true;
        try {
            const d = await api('POST', '/undo');
            Audio.play('undo');
            applyScores(d);
            if (S.round && S.round.status === 'revealed') {
                Timer.setup(S.round); renderReveal(S.round); setPhase('reveal');
                gsap.set(['#revealCard', '#spotlight', '.timer-card'], { clearProps: 'all' });
            }
            toast('Last score undone.', 'success');
        } catch (e) { }
        S.busy = false; updateControls();
    }

    async function voidRound() {
        if ((S.phase !== 'reveal' && S.phase !== 'picked') || S.busy) return;
        const ok = await confirmBox('Cancel this round?', 'The question and player are put back and you can spin again. No points are recorded.');
        if (!ok) return;
        S.busy = true;
        try { const d = await api('POST', '/void'); Timer.clear(); applyScores(d); backToIdle(); toast('Round cancelled – spin again.', 'info'); } catch (e) { }
        S.busy = false; updateControls();
    }

    async function toggleDouble() {
        if (S.phase !== 'reveal' || S.busy) return;
        const on = !S.round.double_or_nothing;
        try {
            const d = await api('POST', '/double', { on });
            S.round = d.round; renderPoints(S.round); renderReveal(S.round); updateControls();
            if (on) { Audio.play('danger'); banner('DOUBLE OR NOTHING!', `${S.round.points * 2} points… or lose ${S.round.points}!`, '#9B6BDF', 1300); }
        } catch (e) { }
    }

    async function queueMode(mode) {
        try {
            const d = await api('POST', '/mode', { mode });
            S.session = d.session; renderHeader(); updateControls();
            const label = { bonus: 'Bonus round', danger: 'Danger round', mystery: 'Mystery round', hot_seat: 'Hot seat', speed: 'Speed round', cancel: 'Queued modes cleared' }[mode];
            toast(mode === 'cancel' ? label : `${label} will apply to the next spin.`, 'success');
        } catch (e) { }
    }

    async function setStatus(action) {
        try { const d = await api('POST', '/status', { action }); applyScores(d); return true; } catch (e) { return false; }
    }

    function showPaused(on) { $('#pausedOverlay').classList.toggle('show', on); if (on) Timer.pause(); }

    async function pauseSession() {
        if (await setStatus('pause')) { showPaused(true); Audio.duck(true); }
    }

    async function crownChampion(confirmFirst = false) {
        if (confirmFirst) {
            const ok = await confirmBox('End the quiz now?', 'Show the winner celebration. Any unscored round will be cancelled when you save.');
            if (!ok) return;
        }
        let d;
        try { d = await api('GET', '/final'); } catch (e) { return; }
        Timer.stop();
        const lb = d.leaderboard.filter((r, i) => i === 0 || r.score > 0 || r.answered > 0);
        const podium = [lb[1], lb[0], lb[2]].map((r, i) => {
            if (!r) return '<div class="pod" style="visibility:hidden"></div>';
            const place = [2, 1, 3][i];
            return `<div class="pod p${place}"><div class="pod-av">${avatarHtml(r)}</div><div class="pod-name">${esc(r.name)}</div><div class="pod-score">${r.score} pts · ${r.correct} correct</div><div class="pod-block">${place}</div></div>`;
        }).join('');
        $('#podium').innerHTML = podium;
        const st = d.stats;
        const item = (l, v) => v ? `<div><b>${esc(v)}</b>${esc(l)}</div>` : '';
        $('#winnerStats').innerHTML = item('Questions played', st.rounds_played) + item('Correct answers', st.total_correct) + item('Average score', st.average_score) +
            (st.most_correct ? item('Most correct', `${st.most_correct.name} (${st.most_correct.value})`) : '') +
            (st.highest_accuracy ? item('Highest accuracy', `${st.highest_accuracy.name} ${st.highest_accuracy.value}`) : '') +
            (st.longest_streak ? item('Longest streak', `${st.longest_streak.name} (${st.longest_streak.value})`) : '') +
            (st.most_active ? item('Most active', `${st.most_active.name}`) : '');
        if (d.team_leaderboard?.length) $('#winnerStats').innerHTML += item('Winning team', `${d.team_leaderboard[0].name} (${d.team_leaderboard[0].score})`);
        const completed = S.session.status === 'completed';
        $('#btnSaveEnd').classList.toggle('d-none', completed);
        $('#btnResults').classList.toggle('d-none', !completed);
        const o = $('#winnerOverlay'); o.classList.add('show');
        Audio.duck(true); Audio.play('winner'); setTimeout(() => Audio.play('confetti'), 400);
        confettiBurst('big'); setTimeout(() => confettiBurst('big'), 2600);
        if (!reduceMotion()) {
            gsap.fromTo('.winner-kicker', { scale: 0, rotation: -8 }, { scale: 1, rotation: 0, duration: .8, ease: 'back.out(2)' });
            gsap.fromTo('.pod-block', { scaleY: 0 }, { scaleY: 1, duration: 1, ease: 'power3.out', stagger: .25, delay: .3 });
            gsap.fromTo('.pod-av, .pod-name, .pod-score', { opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: .5, stagger: .08, delay: .9 });
        }
    }

    async function saveAndEnd() {
        if (await setStatus('complete')) {
            toast('Results saved. Session completed.', 'success');
            $('#btnSaveEnd').classList.add('d-none'); $('#btnResults').classList.remove('d-none');
            Audio.playKey('final_celebration');
            setPhase('idle'); updateControls();
        }
    }

    function confirmBox(title, text) {
        return Swal.fire({ title, text, icon: 'question', showCancelButton: true, confirmButtonText: 'Yes', cancelButtonText: 'No',
            confirmButtonColor: '#86C440', cancelButtonColor: '#A7A9AC' }).then(r => r.isConfirmed);
    }

    // ---------------------------------------------------------------- participant picker (facilitator mode)
    function openPicker() {
        const list = eligible();
        $('#pickGrid').innerHTML = list.map(p => `<button data-pid="${p.id}">${esc(p.name)}<small>${p.score} pts · ${p.times_selected}× picked</small></button>`).join('');
        const modal = bootstrap.Modal.getOrCreateInstance('#pickModal'); modal.show();
        $$('#pickGrid button').forEach(b => b.onclick = () => { modal.hide(); spin(+b.dataset.pid); });
    }

    // ================================================================ LEADERBOARD
    let boardMode = 'people';
    function renderBoard() {
        const list = $('#boardList');
        const before = {};
        $$('.board-row', list).forEach(r => before[r.dataset.key] = r.getBoundingClientRect().top);
        const rows = boardMode === 'teams' ? S.teamBoard.map(t => ({ key: 't' + t.id, rank: t.rank, name: `<span class="team-dot" style="--tc:${esc(t.color)}"></span> ${esc(t.name)} <small class="text-muted">(${t.members})</small>`, correct: t.correct, answered: t.answered, accuracy: t.accuracy, score: t.score }))
            : S.leaderboard.map(r => ({ key: 'p' + r.id, rank: r.rank, name: `${r.photo && S.config.display.show_photos ? `<img class="mini-av" src="${esc(r.photo)}" alt="">` : ''}${esc(r.name)}${r.team ? ` <span class="team-dot" style="--tc:${esc(r.team_color)}"></span>` : ''}`, correct: r.correct, answered: r.answered, accuracy: r.accuracy, score: r.score }));
        const medal = r => ({ 1: '🥇', 2: '🥈', 3: '🥉' }[r] || r);
        const showMedal = r => r.rank <= 3 && r.score > 0;
        list.innerHTML = rows.map(r => `<div class="board-row ${r.score > 0 ? 'r' + r.rank : ''}" data-key="${r.key}"><span class="br-rank">${showMedal(r) ? `<span class="medal">${medal(r.rank)}</span>` : r.rank}</span><span class="br-name">${r.name}</span><span>${r.correct}</span><span>${r.answered}</span><span>${r.accuracy}%</span><span class="br-score" data-score="${r.score}">${r.score}</span></div>`).join('')
            || '<div class="text-center text-muted p-4">No scores yet – spin to play!</div>';
        if (reduceMotion()) return;
        $$('.board-row', list).forEach((r, i) => {
            const prev = before[r.dataset.key]; const now = r.getBoundingClientRect().top;
            if (prev !== undefined && prev !== now) gsap.fromTo(r, { y: prev - now }, { y: 0, duration: .8, ease: 'power3.out' });
            else if (prev === undefined) gsap.fromTo(r, { x: -40, opacity: 0 }, { x: 0, opacity: 1, duration: .4, delay: i * .04 });
            const key = r.dataset.key, sc = +r.querySelector('.br-score').dataset.score;
            if (S.prevRanks[key] !== undefined && S.prevRanks[key] !== sc) {
                const o = { v: S.prevRanks[key] }; gsap.to(o, { v: sc, duration: 1, onUpdate: () => r.querySelector('.br-score').textContent = Math.round(o.v) });
            }
            S.prevRanks[key] = sc;
        });
    }
    function toggleBoard(force) {
        const o = $('#boardOverlay'); const show = force ?? !o.classList.contains('show');
        o.classList.toggle('show', show);
        if (show) { renderBoard(); if (!reduceMotion()) gsap.fromTo('.board-panel', { scale: .9, opacity: 0 }, { scale: 1, opacity: 1, duration: .35, ease: 'back.out(1.6)' }); Audio.play('reveal', { volume: .5 }); }
    }

    // ================================================================ SETTINGS PANEL
    async function saveSettings(payload) {
        try {
            const d = await api('POST', '/settings', payload);
            S.config = d.config; S.session = d.session;
            renderHeader(); updateControls(); Audio.updateMusic(); Particles.configure();
            Wheel.draw();
            return true;
        } catch (e) { fillSettings(); return false; }
    }

    function fillSettings() {
        $$('[data-cfg]').forEach(el => {
            const v = get(S.config, el.dataset.cfg);
            if (el.type === 'checkbox') el.checked = !!v; else el.value = v ?? '';
        });
        $$('[data-field]').forEach(el => { const v = S.session[el.dataset.field] ?? S.sessionFields?.[el.dataset.field]; el.value = v ?? ''; });
        $$('[data-diff]').forEach(el => el.checked = (S.config.modes.difficulties || []).includes(el.dataset.diff));
        renderPeople(); loadQuestions(); renderSoundGrid();
    }

    function bindSettings() {
        $$('[data-cfg]').forEach(el => el.addEventListener('change', () => {
            const [g, k] = el.dataset.cfg.split('.');
            let v = el.type === 'checkbox' ? el.checked : (el.type === 'number' || el.type === 'range' ? parseFloat(el.value) : el.value);
            if (el.type === 'number' && isNaN(v)) return;
            saveSettings({ settings: { [g]: { [k]: v } } }).then(ok => {
                if (ok && g === 'timer' && S.phase === 'reveal') Timer.render();
                if (ok && g === 'audio' && k === 'theme') { Audio.preload(); Audio.updateMusic(); renderSoundGrid(); }
            });
        }));
        $$('[data-field]').forEach(el => el.addEventListener('change', () => {
            const v = el.type === 'number' ? (el.value === '' ? null : parseInt(el.value, 10)) : el.value;
            saveSettings({ [el.dataset.field]: v });
        }));
        $$('[data-diff]').forEach(el => el.addEventListener('change', () => {
            saveSettings({ settings: { modes: { difficulties: $$('[data-diff]').filter(x => x.checked).map(x => x.dataset.diff) } } });
        }));
        $$('#timerPresets [data-seconds]').forEach(b => b.addEventListener('click', () => {
            $('[data-cfg="timer.default_seconds"]').value = b.dataset.seconds;
            saveSettings({ settings: { timer: { default_seconds: +b.dataset.seconds } } });
        }));
        $('#formAddPerson').addEventListener('submit', async e => {
            e.preventDefault(); const f = e.target;
            try { const d = await api('POST', '/participants', { full_name: f.full_name.value }); S.participants = d.participants; f.reset(); renderPeople(); Wheel.setList(eligible()); toast('Added to the game.', 'success'); } catch (_) { }
        });
        $('#btnMakeTeams').addEventListener('click', async () => {
            const count = parseInt($('#teamCount').value, 10) || 2;
            if (S.teams.length && !(await confirmBox('Create new random teams?', 'Existing teams will be replaced (individual scores are kept).'))) return;
            try { const d = await api('POST', '/teams/generate', { count }); S.teams = d.teams; S.participants = d.participants; S.teamBoard = d.team_leaderboard; S.config = d.config; renderPeople(); renderHeader(); Wheel.setList(eligible()); fillSettings(); toast('Teams created – team battle mode is on!', 'success'); } catch (_) { }
        });
        $('#formAddCat').addEventListener('submit', async e => {
            e.preventDefault(); const f = e.target;
            try { const d = await api('POST', '/categories', { name: f.name.value, color: f.color.value }); f.reset(); renderQuestions(d); } catch (_) { }
        });
        $('#formAddQuestion').addEventListener('submit', async e => {
            e.preventDefault(); const f = e.target;
            const body = Object.fromEntries(new FormData(f)); body.points = parseInt(body.points, 10) || 10;
            try { const d = await api('POST', '/questions', body); f.reset(); renderQuestions(d); toast('Question added.', 'success'); } catch (_) { }
        });
        let qt; $('#qSearch').addEventListener('input', () => { clearTimeout(qt); qt = setTimeout(loadQuestions, 300); });
        $('#settingsPanel').addEventListener('show.bs.offcanvas', fillSettings);
    }

    function renderPeople() {
        const teamOpts = t => `<option value="">No team</option>` + S.teams.map(x => `<option value="${x.id}" ${x.id === t ? 'selected' : ''}>${esc(x.name)}</option>`).join('');
        $('#peopleList').innerHTML = S.participants.map(p => `
            <div class="person-row ${p.excluded || !p.active ? 'excluded' : ''}" data-pid="${p.id}">
                <div class="form-check form-switch mb-0" title="Include in selection"><input class="form-check-input" type="checkbox" data-in ${!p.excluded ? 'checked' : ''}></div>
                <input type="text" value="${esc(p.name)}" maxlength="60" data-name title="${esc(p.full_name)}">
                ${S.teams.length ? `<select class="form-select form-select-sm" style="width:130px" data-team>${teamOpts(p.team_id)}</select>` : ''}
                <span class="stats">${p.score} pts · ${p.answered} ans</span>
                <button class="btn btn-sm btn-link text-danger p-0" data-remove title="Remove from this session"><i class="bi bi-x-circle"></i></button>
            </div>`).join('');
        $$('#peopleList .person-row').forEach(row => {
            const pid = row.dataset.pid;
            const upd = async body => { try { const d = await api('PATCH', `/participants/${pid}`, body); S.participants = d.participants; S.teams = d.teams; renderPeople(); Wheel.setList(eligible()); renderHeader(); } catch (_) { renderPeople(); } };
            row.querySelector('[data-in]').onchange = e => upd({ is_excluded: !e.target.checked });
            row.querySelector('[data-name]').onchange = e => e.target.value.trim() && upd({ display_name: e.target.value.trim() });
            const ts = row.querySelector('[data-team]'); if (ts) ts.onchange = e => upd({ team_id: e.target.value ? +e.target.value : null });
            row.querySelector('[data-remove]').onclick = async () => {
                if (!(await confirmBox('Remove from this session?', 'If they already played they are excluded instead, so history is kept.'))) return;
                try { const d = await api('DELETE', `/participants/${pid}`); S.participants = d.participants; renderPeople(); Wheel.setList(eligible()); } catch (_) { }
            };
        });
        $('#teamList').innerHTML = S.teams.map(t => `<div class="d-flex gap-2 mb-1" data-tid="${t.id}"><input type="color" class="form-control form-control-sm form-control-color" value="${esc(t.color)}" data-tcolor><input class="form-control form-control-sm" value="${esc(t.name)}" data-tname maxlength="80"><span class="small text-muted align-self-center text-nowrap">${t.members} members</span></div>`).join('');
        $$('#teamList [data-tid]').forEach(row => {
            const save = async body => { try { const d = await api('PATCH', `/teams/${row.dataset.tid}`, body); S.teams = d.teams; S.participants = d.participants; Wheel.setList(eligible()); } catch (_) { } };
            row.querySelector('[data-tcolor]').onchange = e => save({ color: e.target.value });
            row.querySelector('[data-tname]').onchange = e => save({ name: e.target.value });
        });
    }

    async function loadQuestions() {
        try { const d = await api('GET', '/questions?q=' + encodeURIComponent($('#qSearch').value || '')); renderQuestions(d); } catch (_) { }
    }
    function renderQuestions(d) {
        $('#qRemaining').textContent = d.remaining;
        const sel = S.session.category_ids || [];
        $('#catChecks').innerHTML = d.categories.map(c => `<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" data-cat="${c.id}" id="cc${c.id}" ${sel.includes(c.id) ? 'checked' : ''} ${c.is_active ? '' : 'disabled'}><label class="form-check-label small" for="cc${c.id}" style="color:${esc(c.color)}">${esc(c.name)}${c.is_active ? '' : ' (off)'}</label></div>`).join('')
            + '<div class="small text-muted">None ticked = all active categories.</div>';
        $$('#catChecks [data-cat]').forEach(cb => cb.onchange = async () => {
            const ids = $$('#catChecks [data-cat]').filter(x => x.checked).map(x => +x.dataset.cat);
            if (await saveSettings({ category_ids: ids })) loadQuestions();
        });
        $('#qCat').innerHTML = d.categories.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
        $('#questionList').innerHTML = d.questions.map(q => `<div class="q-row ${q.active ? '' : 'inactive'}" data-qid="${q.id}">
            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" data-qon ${q.active ? 'checked' : ''} title="Enabled"></div>
            <div class="q-text">${esc(q.text)}<div class="text-muted small">${esc(q.category)} · ${esc(q.difficulty)} · ${q.points} pts</div></div>
            <button class="btn btn-sm btn-link text-danger p-0" data-qdel title="Delete"><i class="bi bi-trash"></i></button></div>`).join('');
        $$('#questionList [data-qid]').forEach(row => {
            const id = row.dataset.qid;
            row.querySelector('[data-qon]').onchange = async e => { try { renderQuestions(await api('PATCH', `/questions/${id}?q=${encodeURIComponent($('#qSearch').value)}`, { is_active: e.target.checked })); } catch (_) { } };
            row.querySelector('[data-qdel]').onclick = async () => { if (await confirmBox('Delete this question?', 'It will be removed from future games (history is kept).')) { try { renderQuestions(await api('DELETE', `/questions/${id}?q=${encodeURIComponent($('#qSearch').value)}`)); } catch (_) { } } };
        });
    }

    function renderSoundGrid() {
        if (!S.audio) return;
        $('#soundGrid').innerHTML = Object.entries(S.audio.catalogue).map(([k, [label]]) => `<button class="btn btn-sm btn-outline-secondary" data-snd="${k}"><i class="bi bi-play-fill"></i> ${esc(label)}</button>`).join('');
        $$('#soundGrid [data-snd]').forEach(b => b.onclick = () => {
            Audio.unlocked = true;
            if (b.dataset.snd === 'music_lobby') { const h = Audio.howl('music_lobby'); if (h) { const id = h.play(); setTimeout(() => h.stop(id), 5000); } return; }
            Audio.playKey(b.dataset.snd, { force: true });
        });
    }

    // ================================================================ FULLSCREEN / PROJECTOR
    function toggleFullscreen() {
        try {
            if (!document.fullscreenElement) document.documentElement.requestFullscreen?.();
            else document.exitFullscreen?.();
        } catch (_) { toast('Fullscreen is not available in this browser.', 'warning'); }
    }
    function setProjector(on) {
        document.body.classList.toggle('projector', on);
        try { localStorage.setItem('qa_projector', on ? '1' : '0'); } catch (_) { }
        if (on) toast('Projector mode: controls hide automatically – move the mouse to the bottom edge or use keyboard shortcuts.', 'info');
    }
    let peekTimer;
    document.addEventListener('mousemove', e => {
        if (!document.body.classList.contains('projector')) return;
        if (e.clientY > innerHeight - 110) { $('#controlBar').classList.add('peek'); clearTimeout(peekTimer); peekTimer = setTimeout(() => $('#controlBar').classList.remove('peek'), 3000); }
    });

    // ================================================================ KEYBOARD
    function typing(e) {
        const t = e.target;
        return t && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName)) && t.type !== 'checkbox' && t.type !== 'range';
    }
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if ($('#boardOverlay').classList.contains('show')) { toggleBoard(false); return; }
            if ($('#winnerOverlay').classList.contains('show')) { $('#winnerOverlay').classList.remove('show'); Audio.duck(false); return; }
            return; // let the browser exit fullscreen / bootstrap close modals
        }
        if (typing(e) || e.ctrlKey || e.metaKey || e.altKey) return;
        if (document.querySelector('.modal.show, .offcanvas.show, .swal2-container')) return;
        if ($('#introOverlay').classList.contains('show')) { if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); $('#btnStart').click(); } return; }
        const k = e.key.toLowerCase();
        const map = {
            ' ': () => S.phase === 'scored' ? next() : S.phase === 'picked' ? showQuestion() : spin(),
            'enter': () => S.phase === 'picked' ? showQuestion() : S.phase === 'reveal' && Timer.toggle(),
            'c': () => score('correct'), 'x': () => score('incorrect'), 'p': () => score('passed'), 'h': () => score('partial'),
            'b': () => score('correct', { star: true }), 'u': undo, 'n': next, 'a': () => (S.phase === 'reveal' || S.phase === 'scored') && showAnswer(!S.answerShown),
            'd': toggleDouble, 'l': () => toggleBoard(), 'f': toggleFullscreen, 'm': toggleMute, 'v': () => setProjector(!document.body.classList.contains('projector')),
            's': () => bootstrap.Offcanvas.getOrCreateInstance('#settingsPanel').show(),
        };
        if (map[k]) { e.preventDefault(); map[k](); }
    });

    function toggleMute() {
        S.config.audio.enabled = !S.config.audio.enabled;
        saveSettings({ settings: { audio: { enabled: S.config.audio.enabled } } });
        renderHeader(); Audio.updateMusic();
        toast(S.config.audio.enabled ? 'Sound on' : 'Sound muted', 'info');
    }

    // ================================================================ WIRING
    function bindUi() {
        $('#btnSpin').addEventListener('click', () => S.session?.finished ? crownChampion() : spin());
        $('#btnLeaderboard').onclick = () => toggleBoard();
        $('#btnSound').onclick = toggleMute;
        $('#btnFullscreen').onclick = toggleFullscreen; $('#btnFs2').onclick = toggleFullscreen;
        $('#btnProjector').onclick = () => setProjector(!document.body.classList.contains('projector'));
        $('#btnProj2').onclick = $('#btnProjector').onclick;
        $('#btnSettings').onclick = () => bootstrap.Offcanvas.getOrCreateInstance('#settingsPanel').show();
        $$('[data-close]').forEach(b => b.onclick = () => $('#' + b.dataset.close).classList.remove('show'));
        $$('.board-tabs button').forEach(b => b.onclick = () => { boardMode = b.dataset.board; $$('.board-tabs button').forEach(x => x.classList.toggle('active', x === b)); S.prevRanks = {}; $('#boardList').innerHTML = ''; renderBoard(); });
        document.addEventListener('fullscreenchange', () => { $('#btnFullscreen i').className = document.fullscreenElement ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen'; });

        const acts = {
            'timer-toggle': () => Timer.toggle(), 'timer-add': () => Timer.add(5), 'timer-reset': () => Timer.reset(), 'timer-off': () => Timer.disable(),
            correct: () => score('correct'), incorrect: () => score('incorrect'), partial: () => score('partial'), passed: () => score('passed'), star: () => score('correct', { star: true }),
            override: () => { if (S.phase === 'reveal') bootstrap.Modal.getOrCreateInstance('#overrideModal').show(); },
            reveal: () => showAnswer(!S.answerShown), double: toggleDouble, undo, next, void: voidRound,
            showq: showQuestion, board: () => toggleBoard(),
            pick: () => { if (S.phase !== 'idle') { toast('Finish the current round first.', 'warning'); return; } openPicker(); },
            pause: () => { bootstrap.Offcanvas.getInstance('#settingsPanel')?.hide(); pauseSession(); },
            finish: () => { bootstrap.Offcanvas.getInstance('#settingsPanel')?.hide(); crownChampion(true); },
        };
        document.addEventListener('click', e => {
            const b = e.target.closest('[data-act]'); if (b && acts[b.dataset.act]) { e.preventDefault(); acts[b.dataset.act](); }
            const m = e.target.closest('[data-mode]'); if (m) { e.preventDefault(); queueMode(m.dataset.mode); }
        });
        $('#ovSave').onclick = () => {
            bootstrap.Modal.getInstance('#overrideModal')?.hide();
            score($('#ovResult').value, { override_points: parseInt($('#ovPoints').value, 10) || 0 });
        };
        $('#btnResume').onclick = async () => { if (await setStatus('resume')) { showPaused(false); Audio.duck(false); } };
        $('#btnSaveEnd').onclick = saveAndEnd;
        $('#btnWinnerBoard').onclick = () => toggleBoard(true);
        $('#btnWinnerClose').onclick = () => { $('#winnerOverlay').classList.remove('show'); Audio.duck(false); };
        $('#btnStart').onclick = startGame;
        bindSettings();
        window.addEventListener('beforeunload', () => Timer.save());
    }

    async function startGame() {
        Audio.unlock();
        Audio.play('intro');
        const o = $('#introOverlay');
        if (!reduceMotion()) await gsap.to('.intro-inner', { scale: 1.15, opacity: 0, duration: .5, ease: 'power2.in' });
        o.classList.remove('show');
        S.started = true;
        if (['draft', 'ready'].includes(S.session.status)) { await setStatus('start'); }
        if (S.session.status === 'paused') showPaused(true);
        if (S.session.status === 'completed') crownChampion();
        if (!reduceMotion()) gsap.from(['.arena-header', '#wheelWrap', '#stepPanel'], { y: 40, opacity: 0, duration: .6, stagger: .1, ease: 'power3.out', clearProps: 'all' });
        Wheel.resize();
    }

    // ================================================================ BOOT
    async function boot() {
        Particles.init(); Wheel.init(); bindUi();
        let d;
        try { d = await api('GET', '/state'); } catch (e) { $('#introTitle').innerHTML = 'CONNECTION<br><span>PROBLEM</span>'; $('#btnStartText').textContent = 'RELOAD'; $('#btnStart').onclick = () => location.reload(); return; }
        S.session = d.session; S.config = d.config; S.participants = d.participants; S.teams = d.teams; S.leaderboard = d.leaderboard; S.teamBoard = d.team_leaderboard;
        S.round = d.round; S.history = d.history; S.canUndo = d.can_undo; S.reactions = d.reactions; S.audio = d.audio;
        S.leaderboard.forEach(r => S.prevRanks['p' + r.id] = r.score);
        try { if (localStorage.getItem('qa_projector') === '1' || (localStorage.getItem('qa_projector') === null && S.config.display.projector_mode)) document.body.classList.add('projector'); } catch (_) { }
        Particles.configure();
        Wheel.setList(eligible()); Wheel.resize();
        renderHeader();
        // Restore the game exactly where it was (refresh / lost connection safe)
        if (S.round && S.round.status === 'revealed') {
            Timer.setup(S.round); renderReveal(S.round); setPhase('reveal');
        } else if (S.round && S.round.status === 'answered' && !S.session.finished && S.history.length) {
            setPhase('idle'); Wheel.startIdle();
        } else { setPhase('idle'); Wheel.startIdle(); }
        if (S.session.current_round > 0 && S.session.status !== 'completed') {
            $('#introTitle').innerHTML = 'WELCOME BACK,<br><span>OPERATIONS!</span>'; $('#btnStartText').textContent = `RESUME · ROUND ${S.session.current_round} OF ${S.session.total_rounds}`;
        }
        if (S.session.status === 'completed') { $('#introTitle').innerHTML = 'QUIZ<br><span>COMPLETE!</span>'; $('#btnStartText').textContent = 'SEE THE CHAMPION'; }
        if (!reduceMotion()) {
            gsap.from('.intro-logo', { y: -40, opacity: 0, duration: .8, ease: 'power3.out' });
            gsap.from('.intro-title', { scale: .6, opacity: 0, duration: .9, ease: 'back.out(1.8)', delay: .2 });
            gsap.from('#btnStart', { y: 30, opacity: 0, duration: .6, delay: .6 });
        }
        updateControls();
    }

    // Expose a small hook for automated browser tests
    window.QuizArena = { state: S, Wheel, Timer, spin, showQuestion, score, next, undo };
    boot();
})();

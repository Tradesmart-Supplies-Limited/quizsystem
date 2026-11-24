"""Synthesise the original, royalty-free sound library for the Quiz Arena.
Every sound is generated from scratch (oscillators + filtered noise), so the
company owns them outright. Usage: python3 make_sounds.py <output_dir>"""
import numpy as np, sys, os, subprocess, tempfile
from scipy.signal import butter, sosfilt
from scipy.io import wavfile

SR = 44100
OUT = sys.argv[1]
rng = np.random.default_rng(7)

def t_(d): return np.arange(int(SR * d)) / SR
def env(n, a=.01, r=.2, curve=3):
    e = np.ones(n); A = max(1, int(a * SR)); R = max(1, min(n - A, int(r * SR)))
    e[:A] = np.linspace(0, 1, A); e[n - R:] *= np.linspace(1, 0, R) ** curve * 1.0
    return e
def osc(freq, d, wave):
    t = t_(d)
    f = np.broadcast_to(freq, t.shape) if np.ndim(freq) else np.full(t.shape, float(freq))
    ph = 2 * np.pi * np.cumsum(f) / SR
    if wave == 'sine': return np.sin(ph)
    if wave == 'tri': return 2 / np.pi * np.arcsin(np.sin(ph))
    if wave == 'square': return np.sign(np.sin(ph)) * .6
    if wave == 'saw': return 2 * ((ph / (2 * np.pi)) % 1) - 1
    if wave == 'rich':  # organ/brass-like additive tone
        return (np.sin(ph) + .5 * np.sin(2 * ph) + .25 * np.sin(3 * ph) + .12 * np.sin(4 * ph)) / 1.9
    if wave == 'bell':
        return (np.sin(ph) + .6 * np.sin(2.76 * ph) * np.exp(-t * 6) + .4 * np.sin(5.4 * ph) * np.exp(-t * 10)) / 1.7
    raise ValueError(wave)
def note(freq, d, wave, a=.005, r=None, vol=1):
    x = osc(freq, d, wave); return x * env(len(x), a, r if r is not None else d * .7) * vol
def noise(d): return rng.uniform(-1, 1, int(SR * d))
def filt(x, lo=None, hi=None, order=2):
    if lo and hi: sos = butter(order, [lo, hi], 'bandpass', fs=SR, output='sos')
    elif lo: sos = butter(order, lo, 'highpass', fs=SR, output='sos')
    else: sos = butter(order, hi, 'lowpass', fs=SR, output='sos')
    return sosfilt(sos, x)
def mix(length, *parts):
    """parts: (start_seconds, signal)"""
    out = np.zeros(int(SR * length))
    for st, sig in parts:
        i = int(st * SR); j = min(len(out), i + len(sig))
        if j > i: out[i:j] += sig[:j - i]
    return out
def reverb(x, amt=.25, delays=(.029, .037, .051, .071, .093)):
    y = x.copy()
    for k, d in enumerate(delays):
        n = int(d * SR); g = amt * (.8 ** k)
        for rep in range(1, 5):
            off = n * rep
            if off < len(x): y[off:] += x[:-off] * (g ** rep)
    return y
def midi(m): return 440 * 2 ** ((m - 69) / 12)
def crush(x, bits=6, hold=3):
    q = 2 ** bits; y = np.round(x * q) / q
    return np.repeat(y[::hold], hold)[:len(x)]

class Theme:
    def __init__(self, name):
        self.name = name; self.arc = name == 'arcade'
    def w(self, kind='lead'):
        if self.arc: return {'lead': 'square', 'pad': 'tri', 'bell': 'square', 'bass': 'tri', 'brass': 'square'}[kind]
        return {'lead': 'tri', 'pad': 'rich', 'bell': 'bell', 'bass': 'sine', 'brass': 'rich'}[kind]
    def fin(self, x, rv=.22):
        if self.arc: x = crush(x, 7, 2)
        else: x = reverb(x, rv)
        return x

def arp(th, notes, step, d, kind='lead', vol=.5, start=0):
    return [(start + i * step, note(midi(n), d, th.w(kind), vol=vol)) for i, n in enumerate(notes)]

def build(th):
    S = {}
    W = th.w
    # ---------------- start
    S['game_start'] = mix(2.4, *arp(th, [60, 64, 67, 72], .12, .5, 'brass', .45),
                          (0.5, note(midi(60), 1.8, W('pad'), .02, 1.2, .3)), (0.5, note(midi(64), 1.8, W('pad'), .02, 1.2, .25)),
                          (0.5, note(midi(67), 1.8, W('pad'), .02, 1.2, .25)), (0.5, note(midi(72), 1.8, W('brass'), .02, 1.2, .3)))
    sw = osc(np.geomspace(180, 1400, int(SR * .7)), .7, W('lead')) * env(int(SR * .7), .02, .2) * .35
    S['spin_start'] = mix(.8, (0, sw), (0, filt(noise(.7), 800, 6000) * env(int(SR * .7), .3, .3) * .25))
    n = int(SR * 2.2); tt = t_(2.2)
    roll = filt(noise(2.2), 150, 3000) * (0.5 + 0.5 * np.sign(np.sin(2 * np.pi * 22 * tt))) * np.linspace(.15, .7, n)
    hit = filt(noise(.9), 3000, 12000) * env(int(SR * .9), .002, .85, 2) * .5
    S['drumroll'] = mix(3.0, (0, roll), (2.15, hit), (2.15, note(60, .5, 'sine', .002, .45, .8)))
    tt = t_(1.8)
    S['suspense'] = (osc(np.linspace(110, 180, len(tt)), 1.8, W('pad')) * (0.6 + 0.4 * np.sin(2 * np.pi * 7 * tt)) * env(len(tt), .4, .4) * .4)
    S['countdown_start'] = mix(1.4, (0, note(880, .12, W('lead'), vol=.4)), (.35, note(880, .12, W('lead'), vol=.4)), (.7, note(1320, .45, W('lead'), vol=.45)))
    # ---------------- wheel
    tt = t_(1.0)
    S['wheel_spin'] = filt(noise(1.0), 300, 2500) * (.6 + .4 * np.sin(2 * np.pi * 4 * tt)) * .25
    click = filt(noise(.02), 1500, 9000) * env(int(SR * .02), .0005, .018, 2)
    S['wheel_tick'] = mix(.04, (0, click * .8), (0, note(2200 if not th.arc else 1800, .02, 'square' if th.arc else 'sine', .0005, .018, .25)))
    S['wheel_slow'] = osc(np.geomspace(700, 160, int(SR * 1.3)), 1.3, W('pad')) * env(int(SR * 1.3), .05, .6) * .25
    thunk = note(70, .35, 'sine', .002, .3, .9) + filt(noise(.35), 60, 400) * env(int(SR * .35), .001, .3) * .5
    S['wheel_stop'] = mix(1.4, (0, thunk), (.08, note(midi(84), 1.2, W('bell'), .003, 1.1, .45)), (.08, note(midi(88), 1.2, W('bell'), .003, 1.1, .3)))
    # ---------------- question
    clicks = mix(1.0, *[(i / 18, click * .9) for i in range(18)], *[(i / 18, note(1200 + (i % 3) * 200, .015, 'square', .0005, .012, .15)) for i in range(18)])
    S['slot_spin'] = clicks + filt(noise(1.0), 400, 1800) * .06
    S['question_reveal'] = mix(1.6, *arp(th, [72, 76, 79, 84, 88], .06, .9, 'bell', .35), (.3, filt(noise(1.0), 5000, 14000) * env(int(SR), .3, .6) * .12))
    S['question_select'] = mix(1.0, (0, note(midi(84), .8, W('bell'), .002, .75, .5)), (.13, note(midi(91), .8, W('bell'), .002, .75, .45)))
    coins = [(i * .07 + rng.uniform(0, .02), note(midi(96 + (i % 4) * 2), .25, W('bell'), .001, .24, .3)) for i in range(16)]
    S['bonus_reveal'] = mix(1.8, *coins, *arp(th, [72, 76, 79, 84], .09, .7, 'brass', .3, start=.1))
    # ---------------- success
    S['correct'] = mix(1.0, (0, note(midi(84), .5, W('bell'), .002, .45, .55)), (.11, note(midi(91), .8, W('bell'), .002, .75, .55)),
                       (.11, note(midi(79), .8, W('pad'), .01, .7, .2)))
    ap = np.zeros(int(SR * 2.8))
    for i in range(420):
        st = rng.uniform(0, 2.4); c = filt(noise(.03), rng.uniform(700, 1600), 6000) * env(int(SR * .03), .001, .028, 2) * rng.uniform(.2, .6)
        k = int(st * SR); ap[k:k + len(c)] += c[:len(ap) - k]
    ap *= np.concatenate([np.linspace(.3, 1, int(SR * .4)), np.ones(int(SR * 1.6)), np.linspace(1, 0, len(ap) - int(SR * 2.0))])
    S['applause'] = ap * .6
    S['victory'] = mix(2.6, *arp(th, [67, 72, 76], .14, .35, 'brass', .45), (.45, note(midi(79), 1.1, W('brass'), .01, .8, .5)),
                       (.45, note(midi(76), 1.1, W('pad'), .01, .8, .3)), (.45, note(midi(72), 1.1, W('pad'), .01, .8, .3)), (.45, note(midi(48), 1.1, W('bass'), .01, .8, .5)))
    S['jackpot'] = mix(2.4, *[(i * .045, note(midi(88 + (i * 5) % 12), .3, W('bell'), .001, .28, .28)) for i in range(28)],
                       *arp(th, [72, 76, 79, 84, 88, 91], .07, .6, 'lead', .35, start=1.2))
    S['level_up'] = mix(1.2, *arp(th, [60, 64, 67, 72, 76, 79, 84], .055, .25, 'lead', .4), (.4, note(midi(84), .7, W('bell'), .002, .65, .4)))
    sw = osc(np.geomspace(300, 2000, int(SR * .6)), .6, W('lead')) * env(int(SR * .6), .01, .2) * .3
    S['streak'] = mix(1.4, (0, sw), *[(.55 + i * .06, note(midi(96 - i), .3, W('bell'), .001, .28, .25)) for i in range(8)])
    # ---------------- danger
    S['wrong'] = mix(.9, (0, note(midi(55), .25, 'saw' if not th.arc else 'square', .005, .1, .35)), (.25, note(midi(49), .55, 'saw' if not th.arc else 'square', .005, .4, .35)))
    S['wrong'] = filt(S['wrong'], hi=2500)
    bz = osc(120, .8, 'square') + osc(127, .8, 'saw') * .5
    S['buzzer'] = filt(bz, hi=3000) * env(len(bz), .005, .1) * .35
    wb = note(1000, .06, 'sine', .0005, .055, .6) + filt(noise(.06), 1500, 4000) * env(int(SR * .06), .0005, .05) * .3
    S['time_low'] = wb
    al = mix(1.6, *[(i * .2, note(880 if i % 2 == 0 else 660, .18, 'square', .002, .05, .3)) for i in range(5)], (1.0, S['buzzer'][:int(SR * .6)]))
    S['times_up'] = al
    tt = t_(2.2); siren = osc(300 + 150 * np.sin(2 * np.pi * 1.2 * tt - np.pi / 2), 2.2, 'saw' if not th.arc else 'square')
    S['danger_round'] = mix(2.6, (0, filt(siren, hi=2500) * env(len(tt), .05, .4) * .3), (0, note(55, 2.2, 'sine', .01, 1.5, .5)))
    dd = lambda f, d: note(f, d, W('brass'), .01, d * .8, .5) + note(f / 2, d, W('bass'), .01, d * .8, .4)
    S['dramatic'] = mix(2.4, (0, dd(midi(50), .25)), (.35, dd(midi(50), .25)), (.7, dd(midi(46), 1.6)))
    # ---------------- funny
    S['comedic_fail'] = osc(np.geomspace(1400, 250, int(SR * 1.0)) * (1 + .02 * np.sin(2 * np.pi * 9 * t_(1.0))), 1.0, 'sine') * env(int(SR), .01, .2) * .45
    tt = t_(.8); S['boing'] = osc(180 * (1 + .45 * np.exp(-tt * 5) * np.sin(2 * np.pi * 14 * tt)), .8, 'sine') * env(len(tt), .002, .6) * .6
    tt = t_(.55); scr = filt(noise(.55), 300, 4000) * (np.abs(np.sin(2 * np.pi * 7 * tt)) ** 2)
    S['record_scratch'] = (scr + osc(400 + 350 * np.sin(2 * np.pi * 7 * tt), .55, 'saw') * .15) * env(len(tt), .005, .1) * .5
    honk = lambda d: filt(osc(300, d, 'saw') + osc(303, d, 'square') * .5, 400, 2500) * env(int(SR * d), .01, .05) * .45
    S['funny_horn'] = mix(1.0, (0, honk(.22)), (.32, honk(.45)))
    def wah(m, d, vib=False):
        tt = t_(d); f = midi(m) * (1 + (.03 * np.sin(2 * np.pi * 6 * tt) if vib else 0))
        x = osc(f, d, 'saw') if not th.arc else osc(f, d, 'square')
        cutoff_env = filt(x, 300, 1800); return cutoff_env * env(len(tt), .03, .15) * .5
    S['sad_trombone'] = mix(2.8, (0, wah(58, .45)), (.5, wah(57, .45)), (1.0, wah(56, .45)), (1.5, wah(55, 1.2, True)))
    S['surprise'] = mix(1.0, (0, osc(np.geomspace(300, 1600, int(SR * .5)), .5, 'sine') * env(int(SR * .5), .01, .1) * .4),
                        (.5, filt(noise(.08), 800, 6000) * env(int(SR * .08), .001, .07) * .8), (.52, note(midi(88), .4, W('bell'), .001, .38, .4)))
    def ha(f0, d):
        tt = t_(d); src = osc(f0 * (1 - .15 * tt / d), d, 'saw')
        v = filt(src, 600, 1300, 2) * 1.0 + filt(src, 1000, 2000, 2) * .6 + filt(noise(d), 1500, 5000) * .15
        return v * env(len(tt), .01, d * .6)
    S['laugh'] = mix(1.6, *[(i * .2, ha(230 - i * 10, .14) * .55) for i in range(7)])
    tt = t_(1.2); src = osc(260 * np.linspace(1, .7, len(tt)), 1.2, 'saw')
    S['disappointment'] = (filt(src, 500, 1100, 2) + filt(src, 900, 1600, 2) * .5) * env(len(tt), .08, .5) * .6
    # ---------------- celebration
    pop = filt(noise(.06), 500, 8000) * env(int(SR * .06), .0005, .055, 2)
    S['confetti'] = mix(1.0, (0, pop * .9), *[(.05 + rng.uniform(0, .6), note(rng.uniform(3000, 7000), .1, 'sine', .001, .09, .12)) for _ in range(18)])
    drum = lambda: note(60, .4, 'sine', .002, .35, .9) + filt(noise(.4), 60, 300) * env(int(SR * .4), .001, .3) * .5
    S['winner'] = mix(3.4, (0, drum()), (.25, drum()), *arp(th, [60, 64, 67, 72], .14, .4, 'brass', .4, start=.5),
                      (1.1, note(midi(76), 2.0, W('brass'), .01, 1.5, .45)), (1.1, note(midi(72), 2.0, W('pad'), .01, 1.5, .3)), (1.1, note(midi(67), 2.0, W('pad'), .01, 1.5, .3)),
                      (1.1, note(midi(48), 2.0, W('bass'), .01, 1.5, .5)), (1.1, filt(noise(1.2), 4000, 12000) * env(int(SR * 1.2), .002, 1.1) * .25))
    prog = [(0, [60, 64, 67]), (.6, [65, 69, 72]), (1.2, [67, 71, 74]), (1.8, [72, 76, 79, 84])]
    parts = []
    for st, ch in prog:
        d = 2.2 if st == 1.8 else .55
        for m in ch: parts.append((st, note(midi(m), d, W('brass'), .01, d * .8, .22)))
        parts.append((st, note(midi(ch[0] - 24), d, W('bass'), .01, d * .8, .45)))
        parts.append((st, drum() * .7))
    S['championship'] = mix(4.4, *parts, (1.8, S['applause'] * .5))
    S['final_celebration'] = mix(3.8, *arp(th, [72, 76, 79, 84, 79, 84, 88, 91], .1, .35, 'bell', .35), (0, S['applause'] * .6), (.9, pop * .8), (1.4, pop * .8))
    # ---------------- music loop (16 beats @ 112 bpm, I–vi–IV–V)
    bpm = 112; beat = 60 / bpm; bars = 8; L = bars * 4 * beat
    chords = [[60, 64, 67], [57, 60, 64], [53, 57, 60], [55, 59, 62]] * 2
    parts = []
    for b, ch in enumerate(chords):
        st = b * 4 * beat
        parts.append((st, note(midi(ch[0] - 12), 4 * beat * .95, W('bass'), .01, .3, .32)))
        for k in range(8):
            parts.append((st + k * beat / 2, note(midi(ch[k % 3] + 12), beat * .45, W('lead'), .005, beat * .3, .09)))
        for k in range(4):
            parts.append((st + k * beat, filt(noise(.05), 6000, 14000) * env(int(SR * .05), .001, .045) * (.12 if k % 2 else .06)))
            if k % 2 == 0: parts.append((st + k * beat, note(55, .18, 'sine', .002, .16, .35)))
    m = mix(L, *parts)
    S['music_lobby'] = m
    return S

def write(x, path, loop=False):
    x = np.asarray(x, dtype=float)
    peak = np.max(np.abs(x)) or 1
    x = x / peak * (.6 if os.path.basename(path).startswith('music') else .89)
    if not loop:
        fade = int(SR * .01); x[-fade:] *= np.linspace(1, 0, fade)
    with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as tmp:
        wavfile.write(tmp.name, SR, (x * 32767).astype(np.int16))
        br = '64k' if not os.path.basename(path).startswith('music') else '96k'
        subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', tmp.name, '-ac', '1', '-ar', '44100', '-b:a', br, path], check=True)
        os.unlink(tmp.name)

for theme in ['gameshow', 'arcade']:
    th = Theme(theme); S = build(th)
    d = os.path.join(OUT, theme); os.makedirs(d, exist_ok=True)
    for k, x in S.items():
        if not th.arc and k in ('wheel_tick', 'time_low', 'slot_spin', 'wheel_spin', 'music_lobby'):
            y = x
        else:
            y = th.fin(x, .18) if k not in ('wheel_tick', 'time_low', 'slot_spin', 'wheel_spin', 'music_lobby') else (crush(x, 7, 2) if th.arc else x)
        write(y, os.path.join(d, k + '.mp3'), loop=k in ('slot_spin', 'wheel_spin', 'music_lobby'))
    print(theme, len(S))

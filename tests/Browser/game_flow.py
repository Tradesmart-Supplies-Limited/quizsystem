"""
Frontend (browser) test for the Operations Quiz Arena game screen.

Plays a complete quiz in a real Chromium browser and checks:
  * SPIN & PLAY cannot be triggered twice while the animation runs (one round per spin)
  * the wheel stops on exactly the participant the server selected
  * step 1 shows the picked person, step 2 shows "Question for <name>" with the
    server's question, the timer and the scoring controls
  * nothing overflows the screen and the timer runs
  * scoring, the leaderboard and undo work
  * refreshing the browser mid-round resumes the same round
  * the quiz can be completed, the winner celebration appears and results are saved
  * no JavaScript errors (including audio errors) occur

Requirements:  pip install playwright  &&  python -m playwright install chromium
Usage (app running, e.g. `php artisan serve`, with a session that has unused rounds):
  python tests/Browser/game_flow.py --base http://127.0.0.1:8000 --session 1 \
         --email admin@tradesmart.local --password 'QuizArena@2026' [--size 1366x768] [--shots DIR]
Exit code 0 = all checks passed.
"""
import argparse, asyncio, json, os, sys
from playwright.async_api import async_playwright

CHECK_JS = """() => {
  const A = window.QuizArena, S = A.state, W = A.Wheel;
  const landed = W.list[W.indexAt(W.rotation)];
  const btns = [...document.querySelectorAll('.control-bar button, .arena-header .icon-btn')].filter(b => b.offsetParent);
  const av = document.querySelector('#spAvatar').getBoundingClientRect();
  return { phase: S.phase, round: S.round?.id, roundNo: S.session.current_round,
           pid: S.round?.participant?.id, landedPid: landed?.id, landedName: landed?.name,
           spName: document.querySelector('#spName').textContent, pname: S.round?.participant?.name,
           qText: S.round?.question?.text || S.round?.challenge_text || '',
           pkName: document.querySelector('#pkName').textContent,
           barVisible: !!document.querySelector('#controlBar').offsetParent,
           card: document.querySelector('#rcQuestion').textContent,
           overflow: Math.max(...btns.map(b => b.getBoundingClientRect().right)) > innerWidth + 1
                     || document.documentElement.scrollWidth > innerWidth,
           roundAvatar: Math.abs(av.width - av.height) < 2,
           finished: !!S.session.finished, status: S.session.status };
}"""


class Flow:
    def __init__(self, a):
        self.a, self.problems, self.errors = a, [], []

    def check(self, ok, msg):
        print(('  ok  ' if ok else '  FAIL ') + msg)
        if not ok: self.problems.append(msg)

    async def shot(self, pg, name):
        if self.a.shots:
            os.makedirs(self.a.shots, exist_ok=True)
            await pg.screenshot(path=os.path.join(self.a.shots, f'{self.a.size}_{name}.png'))

    async def state(self, pg):
        return await pg.evaluate(CHECK_JS)

    async def wait_phase(self, pg, phase, timeout=25000):
        await pg.wait_for_function(f"window.QuizArena && window.QuizArena.state.phase === '{phase}'", timeout=timeout)

    async def start(self, pg):
        if await pg.is_visible('#btnStart'):
            await pg.click('#btnStart')
            await pg.wait_for_timeout(1800)

    async def run(self):
        w, h = map(int, self.a.size.split('x'))
        async with async_playwright() as p:
            kw = {'executable_path': self.a.chrome} if self.a.chrome else {}
            b = await p.chromium.launch(**kw)
            pg = await (await b.new_context(viewport={'width': w, 'height': h})).new_page()
            pg.on('pageerror', lambda e: self.errors.append(str(e)))
            pg.on('console', lambda m: m.type == 'error' and 'favicon' not in m.text and self.errors.append(m.text))
            spins = []
            pg.on('request', lambda r: r.method == 'POST' and r.url.endswith('/api/spin') and spins.append(r.url))

            await pg.goto(self.a.base + '/login')
            await pg.fill('#email', self.a.email); await pg.fill('#password', self.a.password)
            await pg.click('form button.btn-brand, form button[type=submit]'); await pg.wait_for_load_state('networkidle')
            await pg.goto(f'{self.a.base}/play/{self.a.session}'); await pg.wait_for_timeout(700)
            await self.shot(pg, '0-intro')
            await self.start(pg)

            s = await self.state(pg)
            if s['status'] in ('draft', 'completed'):
                print('Session is', s['status'], '- use a ready/active session'); await b.close(); return 2
            if s['phase'] == 'reveal':          # a round was left open - finish it first
                await pg.keyboard.press('p'); await pg.wait_for_timeout(2500); await pg.keyboard.press('n'); await pg.wait_for_timeout(1500)
            elif s['phase'] == 'scored':
                await pg.keyboard.press('n'); await pg.wait_for_timeout(1500)
            await self.shot(pg, '1-idle')

            played = 0
            while True:
                s = await self.state(pg)
                if s['finished'] or played >= self.a.rounds: break
                before = len(spins)
                # hammer the spin button: only one round may be created
                await pg.keyboard.press('Space'); await pg.wait_for_timeout(120)
                await pg.keyboard.press('Space'); await pg.click('#btnSpin', force=True, no_wait_after=True, timeout=2000)
                await pg.wait_for_timeout(1500)
                if played == 0: await self.shot(pg, '2-spinning')
                await self.wait_phase(pg, 'picked')
                await pg.wait_for_timeout(900)
                s = await self.state(pg)
                n = s['roundNo']
                self.check(len(spins) - before == 1, f'round {n}: exactly one spin request ({len(spins) - before})')
                self.check(s['pid'] == s['landedPid'], f'round {n}: wheel stopped on {s["landedName"]} = server pick {s["pname"]}')
                self.check(s['pkName'] == s['pname'], f'round {n}: step 1 shows {s["pkName"]}')
                self.check(not s['barVisible'], f'round {n}: scoring controls hidden on step 1')
                if played == 0: await self.shot(pg, '2b-picked')
                await pg.keyboard.press('Space')
                await self.wait_phase(pg, 'reveal'); await pg.wait_for_timeout(900)
                s = await self.state(pg)
                q = s['qText']
                self.check(s['spName'] == s['pname'], f'round {n}: "Question for" shows {s["spName"]}')
                self.check(s['barVisible'], f'round {n}: scoring controls shown on step 2')
                self.check(s['card'] == q, f'round {n}: question card matches')
                self.check(not s['overflow'], f'round {n}: no horizontal overflow')
                self.check(s['roundAvatar'], f'round {n}: avatar is round')
                if played == 0:
                    await self.shot(pg, '3-reveal')
                    # timer runs
                    await pg.keyboard.press('Enter'); await pg.wait_for_timeout(2300)
                    t = await pg.evaluate('window.QuizArena.Timer.remaining')
                    total = await pg.evaluate('window.QuizArena.Timer.total')
                    disabled = await pg.evaluate('window.QuizArena.Timer.disabled')
                    self.check(disabled or t < total - 1500, 'timer counts down')
                    # refresh mid-round resumes the same round
                    rid = s['round']
                    await pg.reload(); await pg.wait_for_timeout(1200); await self.start(pg)
                    s2 = await self.state(pg)
                    self.check(s2['round'] == rid and s2['phase'] == 'reveal', 'refresh resumes the open round')
                # score: alternate correct / wrong
                before_score = await pg.evaluate("(()=>{const S=window.QuizArena.state;return S.participants.find(p=>p.id===S.round.participant.id)?.score??0})()")
                key = 'c' if played % 2 == 0 else 'x'
                await pg.keyboard.press(key); await pg.wait_for_timeout(700)
                if played == 0: await self.shot(pg, '4-reaction')
                await self.wait_phase(pg, 'scored'); await pg.wait_for_timeout(1800)
                after = await pg.evaluate("(()=>{const S=window.QuizArena.state;return S.participants.find(p=>p.id===S.round.participant.id)?.score??0})()")
                if key == 'c': self.check(after > before_score, f'round {n}: correct answer added points ({before_score}→{after})')
                if played == 1:
                    # undo the wrong answer -> round re-opens, score restored
                    await pg.keyboard.press('u'); await pg.wait_for_timeout(500)
                    if await pg.is_visible('.swal2-confirm'): await pg.click('.swal2-confirm')
                    await pg.wait_for_timeout(1200)
                    s3 = await self.state(pg)
                    restored = await pg.evaluate("(()=>{const S=window.QuizArena.state;return S.participants.find(p=>p.id===S.round.participant.id)?.score??0})()")
                    self.check(s3['phase'] == 'reveal' and restored == before_score, 'undo re-opens round and restores score')
                    await pg.keyboard.press('c'); await self.wait_phase(pg, 'scored'); await pg.wait_for_timeout(1800)
                if played == 0:
                    await self.shot(pg, '5-scored')
                    await pg.keyboard.press('l'); await pg.wait_for_timeout(800)
                    self.check(await pg.is_visible('#boardOverlay'), 'leaderboard opens (L)')
                    await self.shot(pg, '6-leaderboard')
                    await pg.keyboard.press('Escape'); await pg.wait_for_timeout(400)
                await pg.keyboard.press('n'); await pg.wait_for_timeout(1300)
                played += 1

            s = await self.state(pg)
            if s['finished']:
                await pg.keyboard.press('Space'); await pg.wait_for_timeout(800)
                if await pg.is_visible('.swal2-confirm'): await pg.click('.swal2-confirm')
                await pg.wait_for_timeout(2500)
                self.check(await pg.is_visible('#winnerOverlay'), 'winner celebration shown')
                await self.shot(pg, '7-winner')
                await pg.click('[data-act=save-end], #btnSaveEnd', timeout=3000)
                await pg.wait_for_timeout(800)
                if await pg.is_visible('.swal2-confirm'): await pg.click('.swal2-confirm')
                await pg.wait_for_timeout(2500)
                await self.shot(pg, '8-saved')
                self.check('/admin/sessions/' in pg.url or (await self.state(pg))['status'] == 'completed', 'results saved, session completed')
            self.check(not self.errors, f'no JavaScript errors {self.errors[:3]}')
            await b.close()
        print('\nRESULT:', 'PASS' if not self.problems else f'{len(self.problems)} FAILED')
        return 0 if not self.problems else 1


if __name__ == '__main__':
    ap = argparse.ArgumentParser()
    ap.add_argument('--base', default='http://127.0.0.1:8000')
    ap.add_argument('--session', default='1')
    ap.add_argument('--email', default=os.environ.get('QUIZ_EMAIL', 'admin@tradesmart.local'))
    ap.add_argument('--password', default=os.environ.get('QUIZ_PASSWORD', 'QuizArena@2026'))
    ap.add_argument('--size', default='1920x1080')
    ap.add_argument('--rounds', type=int, default=99, help='max rounds to play')
    ap.add_argument('--shots', default='')
    ap.add_argument('--chrome', default='', help='path to a Chrome binary (optional)')
    sys.exit(asyncio.run(Flow(ap.parse_args()).run()))

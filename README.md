# TradeSmart Supplies Limited – Operations Quiz Arena

A game-show style quiz application for Operations workshops, training sessions and team meetings.
Put it on the boardroom projector and play each round in two steps: **1 · Spin the wheel** to pick the team
member, then **2 · Question for <name>** with the timer and scoring controls – in a clean, light TradeSmart
look with sound effects, reactions, a live leaderboard, special rounds and a winner celebration.

Built with **Laravel 12 · PHP 8.2+ · MySQL/MariaDB · Bootstrap 5 · GSAP · Howler.js · Canvas Confetti ·
SweetAlert2 · Chart.js**. Every library, font and sound ships inside the project, so the game screen keeps
working without an internet connection once the page has loaded.

---

## 1. Quick start on XAMPP (Windows)

The project lives in `C:\xampp\htdocs\quizsystem`.

1. **XAMPP** – use XAMPP with **PHP 8.2 or newer** (XAMPP 8.2.x / 8.3.x). Start **Apache** and **MySQL**.
2. **Database** – open <http://localhost/phpmyadmin>, create an empty database named **`quizsystem`**
   (collation `utf8mb4_unicode_ci`).
3. **PHP libraries** – either
   * extract **`vendor.zip`** into the project folder (it creates the `vendor` folder – no Composer needed), or
   * run `composer install` in the project folder.
4. **Run `install.bat`** (double-click). It creates `.env`, generates the app key, builds the tables and
   loads the sample data. Or do it by hand – see section 2.
5. Open **<http://localhost/quizsystem>** and sign in with the demo administrator:

   | Email | Password |
   |---|---|
   | `admin@tradesmart.local` | `QuizArena@2026` |

   **Change this password straight away** (Users › edit) or create your own administrator with
   `php artisan quiz:create-admin` and delete the demo account.

A sample session (“Operations Quiz – Sample Session”) with 18 team members and 61 questions is ready to play
from the Overview page.

---

## 2. Manual installation (any server)

```bash
cd C:\xampp\htdocs\quizsystem            # or your project directory
composer install                         # skip if you extracted vendor.zip
copy .env.example .env                   # Linux/macOS: cp .env.example .env
php artisan key:generate
# edit .env → DB_DATABASE / DB_USERNAME / DB_PASSWORD / APP_URL / APP_TIMEZONE
php artisan migrate --seed               # tables + sample questions, participants, demo admin
php artisan quiz:create-admin            # create your own administrator (prompts for name, email, password)
```

On XAMPP the PHP executable is `C:\xampp\php\php.exe` – use it instead of `php` if PHP is not on your PATH.

**Without Apache** (quick test on a laptop): `php artisan serve` then open <http://127.0.0.1:8000>.

### Environment settings (`.env`)

| Key | Meaning |
|---|---|
| `APP_URL` | URL you open in the browser, e.g. `http://localhost/quizsystem` |
| `APP_TIMEZONE` | e.g. `Africa/Nairobi`, `Europe/London` |
| `DB_*` | MySQL/MariaDB connection (XAMPP default: user `root`, empty password) |
| `SESSION_LIFETIME` | facilitator login lifetime in minutes (default 480 = a full workshop day) |
| `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` | demo admin created by `db:seed` (non-production only) |

No production credentials are stored in the code. In `APP_ENV=production` the seeder does **not** create the
demo administrator – use `php artisan quiz:create-admin`.

### Frontend assets

There is **no build step**. Bootstrap, GSAP, Howler.js, SweetAlert2, Canvas Confetti, Chart.js, Bootstrap
Icons and the fonts are already in `public/vendor/`; the app's own CSS/JS are in `public/css` and `public/js`.
To update a library, replace its file in `public/vendor/`.

---

## 3. Running a quiz (facilitator guide)

1. **Quiz Sessions › New session** – name, workshop, number of questions, participants, categories, timer,
   scoring, special modes, selection mode. Click **Save & open game screen**.
2. On the projector click **LET’S PLAY!** (this also unlocks sound in the browser).
3. **Step 1** – press **Space** (or click SPIN THE WHEEL). The wheel lands on the player (and shows their team in
   team mode). Press **Space** / **Enter** (or *Show the question*) to go to **Step 2** – “Question for <name>”
   with the question, points, timer and scoring controls. *Cancel this round & re-spin* (Modes menu) puts the
   player and question back.
4. Press **Enter** to start the countdown. When they answer, press **C** (correct), **X** (wrong), **H** (partial),
   **P** (pass) or **B** (correct + bonus). Use **A** to reveal the answer and **U** to undo a mistake.
5. **N** / Space for the next round. After the last round the button becomes **CROWN CHAMPION** → podium,
   statistics, confetti → **Save results & end session**.

### Keyboard shortcuts

| Key | Action | Key | Action |
|---|---|---|---|
| Space | Spin & Play / next round | L | Leaderboard |
| Enter | Start / pause timer | F | Fullscreen |
| C | Correct | M | Mute / unmute |
| X | Wrong | V | Projector mode (auto-hide controls) |
| H | Partially correct | S | Settings panel |
| P | Pass | A | Show / hide answer |
| B | Correct + ⭐ bonus | D | Double or Nothing |
| U | Undo last score | N | Next round |
| Esc | Close overlay / exit fullscreen | | |

Shortcuts are ignored while typing in a text box.

### Special modes (Modes ✨ menu or automatic random chance)

* **Bonus round** – ×2 or ×3 points, jackpot sound.
* **Danger round** – higher points, optional negative points for wrong answers, red alarm.
* **Mystery round** – random effect: normal, bonus, practical task, team challenge, double points, funny
  challenge, no points or mystery prize (edit challenge/prize lists in settings).
* **Hot seat** – one player answers N questions in a row.
* **Speed round** – short timer and faster spins for N rounds.
* **Double or Nothing** – before scoring, the player can risk the round: correct = 2×, wrong = lose the points.
* **Team battle** – Settings › People › Random teams (or assign manually); team leaderboard; team questions
  can award points to the whole team.
* **Streak bonus** and optional **time bonus**.

### Participant selection modes

| Mode | Behaviour |
|---|---|
| Completely random | everyone equal each round |
| Fair rotation | people who have answered least go first; resets once everyone has answered |
| Random among remaining | nobody is picked twice until everyone has been picked |
| Facilitator selection | you choose the player; the wheel still spins to them |
| Team selection | random team first, then a member of that team |

“Allow repeat selections” controls whether the same person may be picked twice in a row. Any participant can be
temporarily excluded from the game screen settings (People tab).

### Reliability

* The **server** chooses every question and participant (cryptographically secure `random_int`), records it,
  and the animations land on that exact result.
* Spins are idempotent and protected by row locks – double-clicks or two open tabs cannot create duplicate rounds.
* Everything is saved after every action. **Refreshing the browser or losing the network resumes the game exactly
  where it was** (open question, player, scores, used questions, even the running timer).
* Sound problems never stop the game – missing files fall back to a simple beep.

---

## 4. Administration

| Area | What you can do |
|---|---|
| Overview | totals, running sessions, recent sessions, most used questions, top scorers |
| Questions | add/edit/delete, enable/disable, 9 question types, difficulty, points, time limit, options, answer, explanation, bonus/danger flags, search & filters, usage history, CSV import/export |
| Categories | create, colour, icon, order, enable/disable |
| Team Members | add/edit/remove, display name, department, job title, photo, enable/disable, CSV import/export, history & scores |
| Quiz Sessions | create, edit, start/pause/resume/complete/re-open, duplicate, results, round history, session log, CSV export, printable / PDF report, delete (admins) |
| Reports | date-filtered summary, outcomes chart, category performance, participant performance, CSV exports |
| Reactions | edit / disable the on-screen “CORRECT!” / “OOPS!” messages |
| Defaults & Sounds *(admin)* | default game configuration, branding, sound library preview, enable/disable sounds, map sounds to events |
| Users *(admin)* | administrators and facilitators |

**Excel:** import/export uses CSV, which Excel opens directly. To import from Excel, use *File › Save As ›
CSV UTF-8*. Download the template from the Import/Export menu.

**PDF:** open the session's *Printable / PDF report* and choose *Save as PDF* in the print dialog.

### Roles

* **Administrator** – everything, including users, default settings and deleting sessions.
* **Facilitator** – questions, categories, team members, sessions, reports, running games.
* Team members never need an account.

---

## 5. Sounds

All 38 sounds × 2 themes (*Game Show* and *Arcade*) in `public/sounds/` were **synthesised from scratch for this
project** (`tools/make_sounds.py`), so they are original and royalty-free. To use your own licensed audio, replace a
file with an MP3 of the same name (e.g. `public/sounds/gameshow/applause.mp3`). Browsers block audio until the
first click – the start screen takes care of that.

---

## 6. Tests

```bash
php vendor/bin/phpunit          # or: composer test
```

56 feature tests cover authentication & permissions, question/participant CRUD and CSV import/export, question
selection (active only, categories, difficulty, no repeats), participant selection (all five modes, exclusions,
repeat rule), special modes, timer settings, scoring (all results, streaks, danger, double-or-nothing, time bonus,
team awards), exact undo, session isolation, persistence/resume, pause/resume/complete, exports, every admin page,
and that every sound file exists. Tests use an in-memory SQLite database and never touch your data.
To run them against MySQL/MariaDB instead, point them at an **empty test database**:
`DB_CONNECTION=mysql DB_DATABASE=quiz_test php vendor/bin/phpunit` (the suite passes on both).

### Browser (frontend) test

`tests/Browser/game_flow.py` plays a whole quiz in a real Chromium browser and checks that SPIN & PLAY
creates only one round however often it is pressed, that the wheel stops on exactly the participant the
server picked, that step 1 and step 2 (“Question for …”) show that person and the server’s question, nothing overflows the screen, the timer
runs, scoring/leaderboard/undo work, a browser refresh resumes the open round, the winner celebration appears and
the session is saved as completed – with no JavaScript or audio errors.

```bash
pip install playwright && python -m playwright install chromium
php artisan migrate:fresh --seed          # test copy only – this wipes the database!
php artisan serve
python tests/Browser/game_flow.py --session 1 --size 1366x768 --shots storage/app/shots
```

---

## 7. Deployment to a production server

```bash
git pull                                   # or copy the files
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate   # first time only
# .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://quiz.yourdomain, DB_*
php artisan migrate --force
php artisan quiz:create-admin
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

* Point the web server's document root at the **`public/`** folder. (On XAMPP the included root `.htaccess`
  forwards `/quizsystem` to `public/` and blocks `.env`, `composer.json` etc.)
* Make `storage/`, `bootstrap/cache/` and `public/uploads/` writable by the web server.
* Use HTTPS on shared networks.
* After code updates: `php artisan optimize:clear` then re-run the cache commands above.

## 8. Backups

* **Database** – phpMyAdmin › `quizsystem` › Export, or
  `C:\xampp\mysql\bin\mysqldump -u root quizsystem > quizsystem-%DATE:~-4%%DATE:~3,2%%DATE:~0,2%.sql`
* **Files** – also back up `public/uploads/` (participant photos) and your `.env`.
* **Restore** – create the database, then `mysql -u root quizsystem < backup.sql`.
* Session results can also be exported as CSV from each session page.

---

## 9. Project structure

```
app/Services/GameEngine.php       spin logic: round types, hot seat, speed, mystery, void, status
app/Services/QuestionPicker.php   question selection rules
app/Services/ParticipantPicker.php participant selection modes
app/Services/ScoringService.php   scoring, streaks, double-or-nothing, undo (transactions + row locks)
app/Services/GameState.php        JSON state for the game screen, leaderboards, final statistics
app/Support/GameDefaults.php      every configurable option, default values, sound catalogue
app/Http/Controllers/GameController.php   game screen API
app/Http/Controllers/Admin/*      administration
resources/views/game/play.blade.php       projector game screen
public/js/game.js, public/css/game.css    two-step game screen: wheel, question, timer, sounds
database/migrations, database/seeders     schema + 61 questions, 18 participants, 11 categories
tests/Feature                              automated tests
```

### Database design notes

`users`, `participants`, `question_categories`, `questions`, `question_options`, `quiz_sessions`,
`quiz_session_categories`, `quiz_session_questions` (optional fixed pool), `teams` (per session),
`quiz_session_participants` (membership + exclusions + per-session score aggregates – replaces separate
`team_members` / `participant_scores` tables), `quiz_rounds`, `quiz_answers` (scoring ledger with undo snapshots),
`session_events` (audit log), `app_settings` (key/value defaults incl. sound settings), `reaction_messages`.
Foreign keys, indexes, unique constraints (one round number per session, one entry per participant per session),
soft deletes for questions/participants/categories/sessions, and transactions with row locks for every score change.

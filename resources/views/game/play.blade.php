<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $session->title }} · Operations Quiz Arena</title>
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/game.css') }}?v={{ filemtime(public_path('css/game.css')) }}">
</head>
<body class="arena-body">
@php $branding = \App\Models\AppSetting::get('branding', ['company' => 'TradeSmart Supplies Limited', 'tagline' => 'Together Values']); @endphp
<canvas id="particles" aria-hidden="true"></canvas>

<div id="arena" class="phase-idle">
    {{-- ================================================================== HEADER --}}
    <header class="arena-header">
        <div class="brand-pill" data-display="logo"><img src="{{ asset('images/logo-mark-light.png') }}" alt="TradeSmart Supplies Limited"></div>
        <div class="title-block">
            <div class="arena-title">TradeSmart <span>Operations Quiz Arena</span></div>
            <div class="arena-sub"><span id="hdrWorkshop">{{ $session->workshop_title ?: $session->title }}</span><span class="dot" data-display="date">•</span><span id="hdrDate" data-display="date">{{ $session->session_date?->format('j M Y') }}</span></div>
        </div>
        <nav class="steps" aria-label="Round steps">
            <div class="step" data-step="1"><b>1</b><span>Spin for a player</span></div>
            <i class="bi bi-chevron-right step-sep"></i>
            <div class="step" data-step="2"><b>2</b><span>Answer the question</span></div>
        </nav>
        <div class="mode-chip d-none" id="modeChip"></div>
        <div class="round-chip"><small>ROUND</small><strong><span id="hdrRound">0</span><em>/</em><span id="hdrTotal">{{ $session->total_rounds }}</span></strong></div>
        <div class="hdr-actions">
            <button class="icon-btn" id="btnLeaderboard" title="Leaderboard (L)"><i class="bi bi-trophy"></i></button>
            <button class="icon-btn" id="btnSound" title="Sound on/off (M)"><i class="bi bi-volume-up"></i></button>
            <button class="icon-btn" id="btnFullscreen" title="Fullscreen (F)"><i class="bi bi-arrows-fullscreen"></i></button>
            <button class="icon-btn" id="btnProjector" title="Projector mode – hide controls (V)"><i class="bi bi-projector"></i></button>
            <button class="icon-btn" id="btnSettings" title="Settings (S)"><i class="bi bi-gear"></i></button>
            <a class="icon-btn" href="{{ route('admin.sessions.show', $session) }}" id="btnExit" title="Back to admin"><i class="bi bi-box-arrow-left"></i></a>
        </div>
    </header>

    @php
        $modesMenu = <<<'HTML'
            <li><h6 class="dropdown-header">Apply to the NEXT spin</h6></li>
            <li><button class="dropdown-item" data-mode="bonus">⭐ Bonus round</button></li>
            <li><button class="dropdown-item" data-mode="danger">⚠️ Danger round</button></li>
            <li><button class="dropdown-item" data-mode="mystery">🎁 Mystery round</button></li>
            <li><button class="dropdown-item" data-mode="hot_seat">🔥 Hot seat</button></li>
            <li><button class="dropdown-item" data-mode="speed">⚡ Speed round</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item" data-act="pick">👆 Choose the player myself</button></li>
            <li><button class="dropdown-item" data-act="void">🔄 Cancel this round &amp; re-spin</button></li>
            <li><button class="dropdown-item" data-mode="cancel">✖ Clear queued modes</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item" data-act="pause">⏸ Pause session</button></li>
            <li><button class="dropdown-item" data-act="finish">🏆 End quiz &amp; crown champion</button></li>
        HTML;
    @endphp

    {{-- ================================================================== STAGE --}}
    <main class="arena-stage">
        {{-- STEP 1: the wheel picks who answers --}}
        <section class="step1" id="step1">
            <div class="wheel-wrap" id="wheelWrap">
                <div class="wheel-box" id="wheelBox">
                    <div class="wheel-pointer" id="wheelPointer"></div>
                    <canvas id="wheelCanvas"></canvas>
                    <div class="wheel-hub"><img src="{{ asset('images/logo-mark-light.png') }}" alt=""></div>
                </div>
            </div>

            <aside class="step-panel" id="stepPanel">
                <div class="step-kicker"><span class="step-no">1</span> Step 1 of 2</div>
                <h2 class="step-title" id="stepTitle">Who’s answering next?</h2>
                <div class="round-type-badge d-none" id="roundTypeBadge"></div>

                <div class="pick-idle" id="pickIdle">
                    <button class="spin-btn" id="btnSpin" type="button">
                        <i class="bi bi-arrow-repeat spin-icon"></i>
                        <span class="spin-text">SPIN THE WHEEL</span>
                        <small>Space</small>
                    </button>
                    <div class="centre-status" id="centreStatus">Press <kbd>Space</kbd> to spin</div>
                </div>

                <div class="picked-card" id="pickedCard" aria-live="polite">
                    <div class="pk-label">It’s your turn!</div>
                    <div class="pk-avatar" id="pkAvatar"></div>
                    <div class="pk-name" id="pkName"></div>
                    <div class="pk-role" id="pkRole"></div>
                    <div class="pk-team d-none" id="pkTeam"></div>
                    <button class="go-btn" id="btnShowQ" data-act="showq" type="button">Show the question <i class="bi bi-arrow-right"></i><small>Space</small></button>
                </div>

                <div class="panel-tools">
                    <button class="tool-btn" data-act="board"><i class="bi bi-trophy"></i> Leaderboard</button>
                    <button class="tool-btn" data-act="undo"><i class="bi bi-arrow-return-left"></i> Undo</button>
                    <div class="dropup">
                        <button class="tool-btn" data-bs-toggle="dropdown"><i class="bi bi-magic"></i> Modes</button>
                        <ul class="dropdown-menu dropdown-menu-end">{!! $modesMenu !!}</ul>
                    </div>
                </div>

                <div class="mini-board" id="miniBoard" data-display="leaderboard"></div>
            </aside>
        </section>

        {{-- STEP 2: the question for the selected person --}}
        <section class="step2" id="step2">
            <div class="qfor" id="spotlight">
                <div class="sp-avatar" id="spAvatar"></div>
                <div class="qfor-text">
                    <div class="qfor-label"><span class="step-no">2</span> Question for</div>
                    <div class="sp-name" id="spName"></div>
                    <div class="sp-role" id="spRole"></div>
                </div>
                <div class="sp-badges" id="spBadges"></div>
                <div class="rc-points" id="rcPoints"></div>
            </div>

            <div class="q-body">
                <section class="reveal-card" id="revealCard" aria-live="polite">
                    <div class="rc-top">
                        <span class="rc-cat" id="rcCategory"></span>
                        <span class="rc-meta" id="rcMeta"></span>
                    </div>
                    <div class="rc-special d-none" id="rcSpecial"></div>
                    <div class="rc-question" id="rcQuestion"></div>
                    <div class="rc-options" id="rcOptions"></div>
                    <div class="rc-answer" id="rcAnswer"><div class="rc-answer-label"><i class="bi bi-check2-circle"></i> ANSWER</div><div id="rcAnswerText"></div><div class="rc-expl" id="rcExplanation"></div></div>
                </section>

                <aside class="timer-card">
                    <div class="tc-label"><i class="bi bi-stopwatch"></i> Timer</div>
                    <div class="timer" id="timer">
                        <svg viewBox="0 0 120 120"><circle class="t-track" cx="60" cy="60" r="52"/><circle class="t-bar" id="timerBar" cx="60" cy="60" r="52"/></svg>
                        <div class="t-num" id="timerNum">30</div>
                        <div class="t-label" id="timerLabel">SECONDS</div>
                    </div>
                    <div class="timer-btns" id="cbTimer">
                        <button class="cb-btn main" data-act="timer-toggle" title="Start / pause timer (Enter)"><i class="bi bi-play-fill"></i><span>Start</span></button>
                        <button class="cb-btn sm" data-act="timer-add" title="Add 5 seconds"><i class="bi bi-plus-circle"></i><span>+5s</span></button>
                        <button class="cb-btn sm" data-act="timer-reset" title="Reset timer"><i class="bi bi-arrow-counterclockwise"></i><span>Reset</span></button>
                        <button class="cb-btn sm" data-act="timer-off" title="Disable timer for this question"><i class="bi bi-slash-circle"></i><span>Off</span></button>
                    </div>
                </aside>
            </div>
        </section>
    </main>

    {{-- ================================================================== FACILITATOR CONTROLS (step 2) --}}
    <footer class="control-bar" id="controlBar">
        <div class="cb-group cb-score" id="cbScore">
            <button class="cb-btn ok" data-act="correct" title="Correct (C)"><i class="bi bi-check-lg"></i><span>CORRECT</span></button>
            <button class="cb-btn bad" data-act="incorrect" title="Wrong (X)"><i class="bi bi-x-lg"></i><span>WRONG</span></button>
            <button class="cb-btn half" data-act="partial" title="Partially correct (H)"><i class="bi bi-circle-half"></i><span>PARTIAL</span></button>
            <button class="cb-btn pass" data-act="passed" title="Pass (P)"><i class="bi bi-skip-forward-fill"></i><span>PASS</span></button>
            <button class="cb-btn star" data-act="star" title="Correct + bonus (B)"><i class="bi bi-star-fill"></i><span>BONUS</span></button>
            <button class="cb-btn sm" data-act="override" title="Award custom points"><i class="bi bi-123"></i><span>Points</span></button>
        </div>
        <div class="cb-group">
            <button class="cb-btn sm" data-act="reveal" title="Reveal answer (A)"><i class="bi bi-eye"></i><span>Answer</span></button>
            <button class="cb-btn sm don" data-act="double" title="Double or nothing (D)"><i class="bi bi-dice-5"></i><span>2× or 0</span></button>
            <button class="cb-btn sm" data-act="undo" title="Undo last score (U)"><i class="bi bi-arrow-return-left"></i><span>Undo</span></button>
            <div class="dropup">
                <button class="cb-btn sm" data-bs-toggle="dropdown" title="Special modes"><i class="bi bi-magic"></i><span>Modes</span></button>
                <ul class="dropdown-menu dropdown-menu-end">{!! $modesMenu !!}</ul>
            </div>
        </div>
        <div class="cb-group cb-next">
            <button class="cb-btn next" data-act="next" title="Next round (N / Space)"><i class="bi bi-arrow-right-circle-fill"></i><span>NEXT ROUND</span></button>
        </div>
    </footer>
</div>

{{-- ====================================================================== OVERLAYS --}}
<div class="overlay intro-overlay show" id="introOverlay">
    <div class="intro-inner">
        <img src="{{ asset('images/logo-light.png') }}" class="intro-logo" alt="{{ $branding['company'] ?? 'TradeSmart Supplies Limited' }}">
        <div class="intro-kicker" id="introKicker">{{ $session->workshop_title ?: 'OPERATIONS QUIZ ARENA' }}</div>
        <h1 class="intro-title" id="introTitle">ARE YOU READY,<br><span>OPERATIONS?</span></h1>
        <button class="btn-glow" id="btnStart"><i class="bi bi-lightning-charge-fill"></i> <span id="btnStartText">LET’S PLAY!</span></button>
        <div class="intro-hint">Click to start – this also enables sound in the browser.</div>
    </div>
</div>
<div class="overlay banner-overlay" id="bannerOverlay"><div class="banner-text" id="bannerText"></div><div class="banner-sub" id="bannerSub"></div></div>
<div class="overlay reaction-overlay" id="reactionOverlay"><div class="reaction-text" id="reactionText"></div><div class="reaction-points" id="reactionPoints"></div></div>
<div class="overlay paused-overlay" id="pausedOverlay"><div><i class="bi bi-pause-circle"></i><h2>SESSION PAUSED</h2><button class="btn-glow" id="btnResume">RESUME</button></div></div>

<div class="overlay board-overlay" id="boardOverlay">
    <div class="board-panel">
        <div class="board-head"><h2><i class="bi bi-trophy-fill"></i> LEADERBOARD</h2>
            <div class="board-tabs"><button class="active" data-board="people">Players</button><button data-board="teams" id="tabTeams">Teams</button></div>
            <button class="icon-btn" data-close="boardOverlay"><i class="bi bi-x-lg"></i></button></div>
        <div class="board-cols"><span>#</span><span>NAME</span><span>CORRECT</span><span>ANSWERED</span><span>ACCURACY</span><span>SCORE</span></div>
        <div class="board-list" id="boardList"></div>
    </div>
</div>

<div class="overlay winner-overlay" id="winnerOverlay">
    <div class="winner-inner">
        <div class="winner-kicker">OPERATIONS QUIZ CHAMPION!</div>
        <div class="podium" id="podium"></div>
        <div class="winner-stats" id="winnerStats"></div>
        <div class="winner-actions">
            <button class="btn-glow" id="btnSaveEnd"><i class="bi bi-save"></i> SAVE RESULTS &amp; END SESSION</button>
            <button class="btn-ghost" id="btnWinnerBoard"><i class="bi bi-list-ol"></i> Full leaderboard</button>
            <button class="btn-ghost" id="btnWinnerClose"><i class="bi bi-arrow-left"></i> Back to game</button>
            <a class="btn-ghost d-none" id="btnResults" href="{{ route('admin.sessions.show', $session) }}"><i class="bi bi-bar-chart"></i> View results &amp; export</a>
        </div>
    </div>
</div>

{{-- ====================================================================== MODALS --}}
<div class="modal fade" id="pickModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content arena-modal">
    <div class="modal-header"><h5 class="modal-title">Who is answering?</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="pick-grid" id="pickGrid"></div></div>
</div></div></div>

<div class="modal fade" id="overrideModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content arena-modal">
    <div class="modal-header"><h5 class="modal-title">Award custom points</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <label class="form-label">Result</label>
        <select class="form-select mb-3" id="ovResult"><option value="correct">Correct</option><option value="partial">Partially correct</option><option value="incorrect">Incorrect</option><option value="passed">Passed</option></select>
        <label class="form-label">Points (negative to deduct)</label>
        <input type="number" class="form-control form-control-lg" id="ovPoints" value="5" min="-1000" max="1000">
    </div>
    <div class="modal-footer"><button class="btn btn-success" id="ovSave">Award points</button></div>
</div></div></div>

{{-- ====================================================================== SETTINGS PANEL --}}
<div class="offcanvas offcanvas-end arena-settings" tabindex="-1" id="settingsPanel">
    <div class="offcanvas-header"><h5 class="offcanvas-title"><i class="bi bi-gear"></i> Game settings</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
    <div class="offcanvas-body">
        <p class="small text-muted">Changes apply immediately and never reset scores or the current question.</p>
        <ul class="nav nav-pills nav-fill mb-3 small" role="tablist">
            @foreach (['sPeople' => 'People', 'sQuestions' => 'Questions', 'sTimer' => 'Timer', 'sGame' => 'Game', 'sAudio' => 'Audio', 'sDisplay' => 'Display', 'sSession' => 'Session'] as $id => $label)
                <li class="nav-item"><button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#{{ $id }}" type="button">{{ $label }}</button></li>
            @endforeach
        </ul>
        <div class="tab-content">
            {{-- People --}}
            <div class="tab-pane fade show active" id="sPeople">
                <form class="d-flex gap-2 mb-3" id="formAddPerson"><input class="form-control" name="full_name" placeholder="Add a name…" required maxlength="150"><button class="btn btn-success"><i class="bi bi-person-plus"></i></button></form>
                <div class="small text-muted mb-2">Toggle <b>In</b> to temporarily exclude someone from selection. Rename by editing the name.</div>
                <div id="peopleList" class="people-list"></div>
                <hr>
                <h6>Teams</h6>
                <div class="d-flex gap-2 align-items-center mb-2"><label class="small">Number of teams</label><input type="number" min="2" max="12" value="2" id="teamCount" class="form-control form-control-sm" style="width:80px"><button class="btn btn-sm btn-outline-secondary" id="btnMakeTeams"><i class="bi bi-shuffle"></i> Random teams</button></div>
                <div id="teamList"></div>
            </div>
            {{-- Questions --}}
            <div class="tab-pane fade" id="sQuestions">
                <div class="small mb-2"><span id="qRemaining">–</span> unused questions available for this session.</div>
                <h6>Categories in play</h6>
                <div id="catChecks" class="mb-3"></div>
                <form class="d-flex gap-2 mb-3" id="formAddCat"><input class="form-control form-control-sm" name="name" placeholder="New category…" required maxlength="100"><input type="color" name="color" value="#4EAADF" class="form-control form-control-sm form-control-color"><button class="btn btn-sm btn-outline-secondary">Add</button></form>
                <details class="mb-3"><summary class="fw-semibold">➕ Add a question</summary>
                    <form id="formAddQuestion" class="mt-2">
                        <textarea class="form-control mb-2" name="question_text" rows="2" placeholder="Question" required></textarea>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><select class="form-select form-select-sm" name="question_category_id" id="qCat"></select></div>
                            <div class="col-6"><select class="form-select form-select-sm" name="type">@foreach (\App\Models\Question::TYPES as $k => $v)<option value="{{ $k }}" @selected($k === 'short_answer')>{{ $v }}</option>@endforeach</select></div>
                            <div class="col-6"><select class="form-select form-select-sm" name="difficulty">@foreach (\App\Models\Question::DIFFICULTIES as $k => $v)<option value="{{ $k }}" @selected($k === 'medium')>{{ $v }}</option>@endforeach</select></div>
                            <div class="col-6"><input type="number" class="form-control form-control-sm" name="points" value="10" min="0" max="1000"></div>
                        </div>
                        <input class="form-control form-control-sm mb-2" name="correct_answer" placeholder="Correct answer">
                        <button class="btn btn-sm btn-success w-100">Save question</button>
                    </form>
                </details>
                <input class="form-control form-control-sm mb-2" id="qSearch" placeholder="Search questions…">
                <div id="questionList" class="question-list"></div>
            </div>
            {{-- Timer --}}
            <div class="tab-pane fade" id="sTimer">
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" data-cfg="timer.enabled" id="cTimerOn"><label class="form-check-label" for="cTimerOn">Timer enabled</label></div>
                <label class="form-label">Default seconds</label>
                <div class="btn-group w-100 mb-2" id="timerPresets">@foreach ([10, 15, 20, 30, 45, 60] as $s)<button class="btn btn-outline-secondary btn-sm" data-seconds="{{ $s }}">{{ $s }}</button>@endforeach</div>
                <input type="number" class="form-control mb-3" data-cfg="timer.default_seconds" min="5" max="600">
                <div class="row g-2 mb-2"><div class="col"><label class="form-label small">Warning at (s)</label><input type="number" class="form-control" data-cfg="timer.warning_at" min="1" max="120"></div>
                    <div class="col"><label class="form-label small">Critical at (s)</label><input type="number" class="form-control" data-cfg="timer.critical_at" min="1" max="60"></div></div>
                @foreach (['ticking' => 'Ticking sound in final seconds', 'auto_start' => 'Auto-start timer after reveal', 'auto_timeout' => 'Auto-record “Timed out” at zero'] as $k => $l)
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-cfg="timer.{{ $k }}" id="ct{{ $k }}"><label class="form-check-label" for="ct{{ $k }}">{{ $l }}</label></div>
                @endforeach
                <p class="small text-muted mt-2">New timer values apply from the next question.</p>
            </div>
            {{-- Game --}}
            <div class="tab-pane fade" id="sGame">
                <label class="form-label">Participant selection</label>
                <select class="form-select mb-3" data-field="selection_mode">@foreach (\App\Models\QuizSession::SELECTION_MODES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                @foreach (['modes.allow_repeat_participant' => 'Allow repeat selections (same person twice in a row)', 'modes.allow_repeat_questions' => 'Allow questions to repeat', 'modes.bonus_enabled' => 'Random bonus rounds', 'modes.danger_enabled' => 'Random danger rounds', 'modes.mystery_enabled' => 'Random mystery rounds', 'modes.double_or_nothing_enabled' => 'Double or Nothing', 'modes.team_mode' => 'Team battle mode', 'scoring.streak_enabled' => 'Streak bonus', 'scoring.time_bonus_enabled' => 'Time bonus', 'scoring.danger_negative' => 'Danger rounds deduct points'] as $k => $l)
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-cfg="{{ $k }}" id="g{{ $loop->index }}"><label class="form-check-label" for="g{{ $loop->index }}">{{ $l }}</label></div>
                @endforeach
                <div class="row g-2 mt-2">
                    @foreach (['modes.bonus_chance' => 'Bonus %', 'modes.danger_chance' => 'Danger %', 'modes.mystery_chance' => 'Mystery %', 'scoring.bonus_multiplier' => 'Bonus ×', 'scoring.danger_multiplier' => 'Danger ×', 'scoring.streak_bonus' => 'Streak pts', 'scoring.streak_threshold' => 'Streak after', 'scoring.star_bonus_points' => '⭐ pts', 'scoring.partial_percent' => 'Partial %', 'modes.hot_seat_questions' => 'Hot seat Qs', 'modes.speed_rounds' => 'Speed rounds', 'modes.speed_timer' => 'Speed timer'] as $k => $l)
                        <div class="col-4"><label class="form-label small mb-0">{{ $l }}</label><input type="number" class="form-control form-control-sm" data-cfg="{{ $k }}" min="0" max="1000"></div>
                    @endforeach
                </div>
                <label class="form-label mt-3">Difficulty in play</label>
                <div>@foreach (\App\Models\Question::DIFFICULTIES as $k => $v)<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" data-diff="{{ $k }}" id="df{{ $k }}"><label class="form-check-label" for="df{{ $k }}">{{ $v }}</label></div>@endforeach</div>
            </div>
            {{-- Audio --}}
            <div class="tab-pane fade" id="sAudio">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-cfg="audio.enabled" id="aOn"><label class="form-check-label" for="aOn">All sounds</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-cfg="audio.sfx_enabled" id="aSfx"><label class="form-check-label" for="aSfx">Sound effects</label></div>
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" data-cfg="audio.music_enabled" id="aMus"><label class="form-check-label" for="aMus">Background music</label></div>
                <label class="form-label small">Effects volume</label><input type="range" class="form-range" min="0" max="1" step="0.05" data-cfg="audio.sfx_volume">
                <label class="form-label small">Music volume</label><input type="range" class="form-range" min="0" max="1" step="0.05" data-cfg="audio.music_volume">
                <label class="form-label small">Sound theme</label>
                <select class="form-select mb-3" data-cfg="audio.theme">@foreach (\App\Support\GameDefaults::soundThemes() as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                <h6>Preview</h6>
                <div class="sound-grid" id="soundGrid"></div>
            </div>
            {{-- Display --}}
            <div class="tab-pane fade" id="sDisplay">
                @foreach (['display.show_leaderboard' => 'Show live leaderboard on screen', 'display.show_photos' => 'Show participant photos', 'display.show_logo' => 'Show company logo', 'display.show_date' => 'Show session date', 'animation.reduce_motion' => 'Reduce motion (shorter, calmer animations)'] as $k => $l)
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-cfg="{{ $k }}" id="d{{ $loop->index }}"><label class="form-check-label" for="d{{ $loop->index }}">{{ $l }}</label></div>
                @endforeach
                <label class="form-label mt-3">Animation intensity</label>
                <select class="form-select mb-3" data-cfg="animation.intensity"><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option></select>
                <div class="row g-2">
                    @foreach (['animation.accel' => 'Accel (s)', 'animation.fast' => 'Fast spin (s)', 'animation.question_slow' => 'Question slow (s)', 'animation.wheel_slow' => 'Wheel slow (s)', 'animation.pause' => 'Pause (s)'] as $k => $l)
                        <div class="col-4"><label class="form-label small mb-0">{{ $l }}</label><input type="number" step="0.1" class="form-control form-control-sm" data-cfg="{{ $k }}" min="0" max="10"></div>
                    @endforeach
                </div>
                <div class="d-grid gap-2 mt-3">
                    <button class="btn btn-outline-secondary" id="btnFs2"><i class="bi bi-arrows-fullscreen"></i> Toggle fullscreen</button>
                    <button class="btn btn-outline-secondary" id="btnProj2"><i class="bi bi-projector"></i> Toggle projector mode</button>
                </div>
                <div class="small text-muted mt-3"><b>Shortcuts:</b> Space spin/next · Enter timer · C correct · X wrong · H partial · P pass · B bonus · A answer · D double · U undo · L leaderboard · F fullscreen · M mute · V projector · S settings · Esc close</div>
            </div>
            {{-- Session --}}
            <div class="tab-pane fade" id="sSession">
                <label class="form-label">Session name</label><input class="form-control mb-2" data-field="title" maxlength="150">
                <label class="form-label">Workshop title</label><input class="form-control mb-2" data-field="workshop_title" maxlength="150">
                <div class="row g-2 mb-3"><div class="col"><label class="form-label">Number of questions</label><input type="number" class="form-control" data-field="total_rounds" min="1" max="500"></div>
                    <div class="col"><label class="form-label">Duration (min)</label><input type="number" class="form-control" data-field="duration_minutes" min="1" max="600"></div></div>
                <div class="small text-muted mb-3">The game is saved automatically after every action. You can close the browser and resume later from Quiz Sessions.</div>
                <div class="d-grid gap-2">
                    <button class="btn btn-warning" data-act="pause"><i class="bi bi-pause-fill"></i> Pause session</button>
                    <button class="btn btn-success" data-act="finish"><i class="bi bi-trophy"></i> End quiz &amp; crown champion</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.sessions.show', $session) }}"><i class="bi bi-bar-chart"></i> Results page</a>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.sessions.export', $session) }}"><i class="bi bi-download"></i> Export results (CSV)</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 start-50 translate-middle-x p-3" id="toasts"></div>

<script>
    window.QUIZ = {
        base: @json(url('/')),
        api: @json(url('play/'.$session->id.'/api')),
        sounds: @json(asset('sounds')),
        csrf: document.querySelector('meta[name=csrf-token]').content,
        sessionId: {{ $session->id }},
    };
</script>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/gsap.min.js') }}"></script>
<script src="{{ asset('vendor/howler.min.js') }}"></script>
<script src="{{ asset('vendor/confetti.browser.js') }}"></script>
<script src="{{ asset('vendor/sweetalert2.all.min.js') }}"></script>
<script src="{{ asset('js/game.js') }}?v={{ filemtime(public_path('js/game.js')) }}"></script>
</body>
</html>

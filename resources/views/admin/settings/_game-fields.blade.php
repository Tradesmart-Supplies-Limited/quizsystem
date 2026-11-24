{{-- Shared game configuration fields. Expects $config (merged config array). Inputs are named settings[group][key]. --}}
@php
    $c = $config;
    $num = fn ($g, $k, $label, $min = 0, $max = 1000, $step = 1, $help = null) => compact('g', 'k', 'label', 'min', 'max', 'step', 'help');
@endphp
<div class="accordion" id="gameCfg">
    {{-- Timer --}}
    <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#cfgTimer"><i class="bi bi-stopwatch me-2"></i> Timer</button></h2>
        <div id="cfgTimer" class="accordion-collapse collapse show"><div class="accordion-body row g-3">
            <div class="col-md-4"><label class="form-label">Default answer time</label>
                <div class="input-group"><input type="number" class="form-control" name="settings[timer][default_seconds]" min="5" max="600" list="timerPresets" value="{{ $c['timer']['default_seconds'] }}"><span class="input-group-text">seconds</span></div>
                <datalist id="timerPresets">@foreach ([10, 15, 20, 30, 45, 60] as $s)<option value="{{ $s }}">@endforeach</datalist>
                <div class="d-flex flex-wrap gap-1 mt-2">@foreach ([10, 15, 20, 30, 45, 60] as $s)<button type="button" class="btn btn-sm btn-outline-secondary" onclick="this.closest('.col-md-4').querySelector('input').value={{ $s }}">{{ $s }}s</button>@endforeach</div>
            </div>
            <div class="col-md-4"><label class="form-label">Warning (yellow + ticking) at</label><div class="input-group"><input type="number" class="form-control" name="settings[timer][warning_at]" min="1" max="120" value="{{ $c['timer']['warning_at'] }}"><span class="input-group-text">s</span></div></div>
            <div class="col-md-4"><label class="form-label">Critical (red + pulse) at</label><div class="input-group"><input type="number" class="form-control" name="settings[timer][critical_at]" min="1" max="60" value="{{ $c['timer']['critical_at'] }}"><span class="input-group-text">s</span></div></div>
            @foreach (['enabled' => 'Timer enabled', 'ticking' => 'Ticking sound in final seconds', 'auto_start' => 'Start timer automatically after reveal', 'auto_timeout' => 'Automatically record “Timed out” at zero'] as $k => $l)
                <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="settings[timer][{{ $k }}]" value="1" id="t{{ $k }}" @checked($c['timer'][$k])><label class="form-check-label" for="t{{ $k }}">{{ $l }}</label></div></div>
            @endforeach
        </div></div>
    </div>
    {{-- Scoring --}}
    <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cfgScore"><i class="bi bi-trophy me-2"></i> Scoring</button></h2>
        <div id="cfgScore" class="accordion-collapse collapse"><div class="accordion-body row g-3">
            <p class="small text-muted mb-0">Correct answers earn the question’s own points (default 10).</p>
            @foreach ([
                $num('scoring', 'incorrect_points', 'Points for a wrong answer', -100, 100),
                $num('scoring', 'partial_percent', 'Partially correct (% of points)', 0, 100),
                $num('scoring', 'bonus_multiplier', 'Bonus round multiplier (×)', 1, 5),
                $num('scoring', 'danger_multiplier', 'Danger round multiplier (×)', 1, 5),
                $num('scoring', 'danger_penalty_percent', 'Danger wrong-answer penalty (% of points)', 0, 200),
                $num('scoring', 'streak_threshold', 'Streak bonus after N correct in a row', 2, 20),
                $num('scoring', 'streak_bonus', 'Streak bonus points', 0, 100),
                $num('scoring', 'time_bonus_max', 'Max time bonus points', 0, 100),
                $num('scoring', 'star_bonus_points', '⭐ Bonus button extra points', 0, 100),
            ] as $f)
                <div class="col-md-4"><label class="form-label small">{{ $f['label'] }}</label><input type="number" class="form-control" name="settings[{{ $f['g'] }}][{{ $f['k'] }}]" min="{{ $f['min'] }}" max="{{ $f['max'] }}" value="{{ $c[$f['g']][$f['k']] }}"></div>
            @endforeach
            <div class="col-md-4"><label class="form-label small">Team questions award points to</label>
                <select class="form-select" name="settings[scoring][team_question_award]"><option value="team" @selected($c['scoring']['team_question_award'] === 'team')>The team</option><option value="participant" @selected($c['scoring']['team_question_award'] === 'participant')>The selected participant</option></select></div>
            @foreach (['danger_negative' => 'Danger rounds can deduct points', 'streak_enabled' => 'Streak bonus enabled', 'time_bonus_enabled' => 'Time bonus for fast correct answers'] as $k => $l)
                <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="settings[scoring][{{ $k }}]" value="1" id="s{{ $k }}" @checked($c['scoring'][$k])><label class="form-check-label" for="s{{ $k }}">{{ $l }}</label></div></div>
            @endforeach
        </div></div>
    </div>
    {{-- Modes --}}
    <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cfgModes"><i class="bi bi-stars me-2"></i> Game modes &amp; question filters</button></h2>
        <div id="cfgModes" class="accordion-collapse collapse"><div class="accordion-body row g-3">
            @foreach (['bonus' => 'Bonus rounds', 'danger' => 'Danger rounds', 'mystery' => 'Mystery rounds'] as $m => $l)
                <div class="col-md-4"><div class="border rounded p-2">
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="settings[modes][{{ $m }}_enabled]" value="1" id="m{{ $m }}" @checked($c['modes'][$m.'_enabled'])><label class="form-check-label fw-semibold" for="m{{ $m }}">{{ $l }}</label></div>
                    <label class="form-label small mb-0">Random chance per spin</label><div class="input-group input-group-sm"><input type="number" class="form-control" name="settings[modes][{{ $m }}_chance]" min="0" max="100" value="{{ $c['modes'][$m.'_chance'] }}"><span class="input-group-text">%</span></div>
                </div></div>
            @endforeach
            <div class="col-md-3"><label class="form-label small">Hot seat: questions in a row</label><input type="number" class="form-control" name="settings[modes][hot_seat_questions]" min="2" max="10" value="{{ $c['modes']['hot_seat_questions'] }}"></div>
            <div class="col-md-3"><label class="form-label small">Speed round: number of rounds</label><input type="number" class="form-control" name="settings[modes][speed_rounds]" min="1" max="20" value="{{ $c['modes']['speed_rounds'] }}"></div>
            <div class="col-md-3"><label class="form-label small">Speed round timer (s)</label><input type="number" class="form-control" name="settings[modes][speed_timer]" min="5" max="60" value="{{ $c['modes']['speed_timer'] }}"></div>
            <div class="col-md-3 d-flex flex-column justify-content-end">
                @foreach (['double_or_nothing_enabled' => 'Double or Nothing', 'team_mode' => 'Team battle mode', 'allow_repeat_participant' => 'Allow same person twice in a row', 'allow_repeat_questions' => 'Allow questions to repeat'] as $k => $l)
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="settings[modes][{{ $k }}]" value="1" id="m{{ $k }}" @checked($c['modes'][$k])><label class="form-check-label small" for="m{{ $k }}">{{ $l }}</label></div>
                @endforeach
            </div>
            <div class="col-md-4"><label class="form-label small">Difficulty levels</label><input type="hidden" name="settings[modes][difficulties][]" value="">
                @foreach (\App\Models\Question::DIFFICULTIES as $k => $l)
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="settings[modes][difficulties][]" value="{{ $k }}" id="d{{ $k }}" @checked(in_array($k, $c['modes']['difficulties']))><label class="form-check-label" for="d{{ $k }}">{{ $l }}</label></div>
                @endforeach
            </div>
            <div class="col-md-8"><label class="form-label small">Question types <small class="text-muted">(none ticked = all types)</small></label><input type="hidden" name="settings[modes][types][]" value=""><div>
                @foreach (\App\Models\Question::TYPES as $k => $l)
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="settings[modes][types][]" value="{{ $k }}" id="ty{{ $k }}" @checked(in_array($k, $c['modes']['types']))><label class="form-check-label small" for="ty{{ $k }}">{{ $l }}</label></div>
                @endforeach
            </div></div>
            <div class="col-md-6"><label class="form-label small">Funny challenges (Mystery round) – one per line</label><textarea class="form-control" rows="4" name="settings[funny_challenges]">{{ implode("\n", $c['funny_challenges']) }}</textarea></div>
            <div class="col-md-6"><label class="form-label small">Mystery prizes – one per line</label><textarea class="form-control" rows="4" name="settings[mystery_prizes]">{{ implode("\n", $c['mystery_prizes']) }}</textarea></div>
        </div></div>
    </div>
    {{-- Animation & display --}}
    <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cfgAnim"><i class="bi bi-film me-2"></i> Animation, display &amp; audio</button></h2>
        <div id="cfgAnim" class="accordion-collapse collapse"><div class="accordion-body row g-3">
            @foreach ([['accel', 'Initial acceleration (s)', 0.1, 3], ['fast', 'Fast spin phase (s)', 0.3, 8], ['question_slow', 'Question slowing (s)', 0.5, 8], ['wheel_slow', 'Wheel slowing (s)', 1, 10], ['pause', 'Dramatic pause (s)', 0, 5]] as [$k, $l, $min, $max])
                <div class="col"><label class="form-label small">{{ $l }}</label><input type="number" step="0.1" class="form-control" name="settings[animation][{{ $k }}]" min="{{ $min }}" max="{{ $max }}" value="{{ $c['animation'][$k] }}"></div>
            @endforeach
            <div class="col-md-4"><label class="form-label small">Animation intensity</label><select class="form-select" name="settings[animation][intensity]">@foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'] as $k => $l)<option value="{{ $k }}" @selected($c['animation']['intensity'] === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label small">Sound theme</label><select class="form-select" name="settings[audio][theme]">@foreach (\App\Support\GameDefaults::soundThemes() as $k => $l)<option value="{{ $k }}" @selected($c['audio']['theme'] === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label small">Effects volume</label><input type="range" class="form-range" min="0" max="1" step="0.05" name="settings[audio][sfx_volume]" value="{{ $c['audio']['sfx_volume'] }}"></div>
            <div class="col-md-2"><label class="form-label small">Music volume</label><input type="range" class="form-range" min="0" max="1" step="0.05" name="settings[audio][music_volume]" value="{{ $c['audio']['music_volume'] }}"></div>
            @foreach ([['animation', 'reduce_motion', 'Reduce motion'], ['display', 'show_leaderboard', 'Show live leaderboard panel'], ['display', 'show_photos', 'Show participant photos'], ['display', 'show_logo', 'Show company logo'], ['display', 'show_date', 'Show session date'], ['display', 'projector_mode', 'Start in projector mode'], ['audio', 'enabled', 'Sound enabled'], ['audio', 'sfx_enabled', 'Sound effects'], ['audio', 'music_enabled', 'Background music']] as [$g, $k, $l])
                <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="settings[{{ $g }}][{{ $k }}]" value="1" id="x{{ $g }}{{ $k }}" @checked($c[$g][$k])><label class="form-check-label small" for="x{{ $g }}{{ $k }}">{{ $l }}</label></div></div>
            @endforeach
        </div></div>
    </div>
</div>

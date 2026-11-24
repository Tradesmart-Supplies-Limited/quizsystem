@extends('layouts.admin')
@section('title', $session->title)
@section('actions')
    @if ($session->isPlayable() || $session->status === 'draft')
        <a href="{{ route('play', $session) }}" class="btn btn-success"><i class="bi bi-play-circle-fill"></i> {{ $session->current_round ? 'Resume game' : 'Open game screen' }}</a>
    @endif
    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">More</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('admin.sessions.edit', $session) }}"><i class="bi bi-pencil"></i> Edit session</a></li>
            <li><a class="dropdown-item" href="{{ route('admin.sessions.export', $session) }}"><i class="bi bi-filetype-csv"></i> Export results (CSV)</a></li>
            <li><a class="dropdown-item" href="{{ route('admin.sessions.report', $session) }}" target="_blank"><i class="bi bi-filetype-pdf"></i> Printable / PDF report</a></li>
            <li><form method="POST" action="{{ route('admin.sessions.duplicate', $session) }}">@csrf<button class="dropdown-item"><i class="bi bi-copy"></i> Duplicate session</button></form></li>
            <li><hr class="dropdown-divider"></li>
            @foreach (['start' => ['draft', 'ready'], 'pause' => ['active'], 'resume' => ['paused'], 'complete' => ['active', 'paused', 'ready'], 'reopen' => ['completed']] as $act => $from)
                @if (in_array($session->status, $from))
                    <li><form method="POST" action="{{ route('admin.sessions.status', [$session, $act]) }}" @if ($act === 'complete') data-confirm="End this session now?" @endif>@csrf<button class="dropdown-item text-capitalize"><i class="bi bi-arrow-right-circle"></i> {{ $act }} session</button></form></li>
                @endif
            @endforeach
            @can('admin')
                <li><form method="POST" action="{{ route('admin.sessions.destroy', $session) }}" data-confirm="Delete this session and its results?">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="bi bi-trash"></i> Delete session</button></form></li>
            @endcan
        </ul>
    </div>
@endsection
@section('content')
<div class="d-flex flex-wrap gap-3 align-items-center mb-3 small text-muted">
    @include('partials.status-badge', ['status' => $session->status])
    <span><i class="bi bi-easel"></i> {{ $session->workshop_title ?: '—' }}</span>
    <span><i class="bi bi-calendar"></i> {{ $session->session_date?->format('j M Y') }}</span>
    <span><i class="bi bi-person"></i> {{ $session->facilitator?->name }}</span>
    <span><i class="bi bi-shuffle"></i> {{ \App\Models\QuizSession::SELECTION_MODES[$session->selection_mode] ?? $session->selection_mode }}</span>
    <span><i class="bi bi-clock-history"></i> {{ $session->started_at?->format('H:i') }}{{ $session->ended_at ? ' – '.$session->ended_at->format('H:i') : '' }}</span>
    <span><i class="bi bi-tags"></i> {{ $session->categories->pluck('name')->join(', ') ?: 'All categories' }}</span>
</div>
<div class="row g-3 mb-4">
    @foreach ([['Rounds played', $stats['rounds_played'].' / '.$session->total_rounds], ['Correct answers', $stats['total_correct']], ['Wrong / timed out', $stats['total_incorrect']], ['Average score', $stats['average_score']], ['Points awarded', $stats['total_points']]] as [$l, $v])
        <div class="col"><div class="card stat-card h-100"><div class="card-body"><div class="stat-value">{{ $v }}</div><div class="stat-label">{{ $l }}</div></div></div></div>
    @endforeach
</div>
<div class="row g-4">
    <div class="col-xl-6">
        <div class="card mb-4"><div class="card-header"><strong><i class="bi bi-trophy-fill text-warning"></i> Leaderboard</strong></div>
            <table class="table align-middle mb-0"><thead><tr><th>#</th><th>Name</th><th>Score</th><th>Correct</th><th>Answered</th><th>Accuracy</th><th>Streak</th></tr></thead><tbody>
            @foreach ($leaderboard as $r)
                <tr><td><span class="rank-dot rank-{{ $r['rank'] }}">{{ $r['rank'] }}</span></td><td>{{ $r['full_name'] }} @if ($r['team'])<span class="badge" style="background: {{ $r['team_color'] }}">{{ $r['team'] }}</span>@endif</td>
                    <td class="fw-bold">{{ $r['score'] }}</td><td>{{ $r['correct'] }}</td><td>{{ $r['answered'] }}</td><td>{{ $r['accuracy'] }}%</td><td>{{ $r['best_streak'] }}</td></tr>
            @endforeach
            </tbody></table></div>
        @if ($teamLeaderboard)
            <div class="card mb-4"><div class="card-header"><strong>Team leaderboard</strong></div>
                <table class="table mb-0"><tbody>@foreach ($teamLeaderboard as $t)<tr><td>{{ $t['rank'] }}</td><td><span class="badge" style="background: {{ $t['color'] }}">&nbsp;</span> {{ $t['name'] }}</td><td>{{ $t['members'] }} members</td><td class="fw-bold">{{ $t['score'] }}</td></tr>@endforeach</tbody></table></div>
        @endif
        <div class="card mb-4"><div class="card-header"><strong>Highlights</strong></div><ul class="list-group list-group-flush">
            @foreach (['most_correct' => 'Most correct answers', 'highest_accuracy' => 'Highest accuracy', 'longest_streak' => 'Longest streak', 'most_active' => 'Most active participant'] as $k => $l)
                <li class="list-group-item d-flex"><span>{{ $l }}</span><strong class="ms-auto">{{ $stats[$k]['name'] ?? '—' }} {{ isset($stats[$k]) ? '('.$stats[$k]['value'].')' : '' }}</strong></li>
            @endforeach
        </ul></div>
        <div class="card"><div class="card-header"><strong>Category performance</strong></div><div class="card-body"><canvas id="catChart" height="160"></canvas></div></div>
    </div>
    <div class="col-xl-6">
        <div class="card mb-4"><div class="card-header"><strong>Round history</strong></div>
            <div class="table-responsive" style="max-height: 640px"><table class="table table-sm align-middle mb-0"><thead class="sticky-top bg-white"><tr><th>#</th><th>Participant</th><th>Question</th><th>Type</th><th>Result</th><th>Pts</th></tr></thead><tbody>
            @forelse ($rounds as $r)
                <tr><td>{{ $r->round_number }}</td><td class="small">{{ $r->participant?->display_name }}</td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($r->question?->question_text ?? $r->challenge_text, 70) }}<div class="text-muted">{{ $r->question?->category?->name }}</div></td>
                    <td class="small text-capitalize">{{ str_replace('_', ' ', $r->round_type) }}{{ $r->double_or_nothing ? ' · 2×/0' : '' }}</td>
                    <td>@include('admin.sessions._result', ['result' => $r->result])</td><td>{{ $r->points_awarded }}</td></tr>
            @empty <tr><td colspan="6" class="text-muted text-center py-4">No rounds played yet.</td></tr> @endforelse
            </tbody></table></div></div>
        <div class="card"><div class="card-header"><strong>Session log</strong></div>
            <ul class="list-group list-group-flush small" style="max-height: 300px; overflow:auto">
                @foreach ($events as $e)
                    <li class="list-group-item d-flex"><span class="text-muted me-2">{{ $e->created_at?->format('H:i:s') }}</span><span>{{ str_replace('_', ' ', $e->type) }}</span><span class="ms-auto text-muted">{{ $e->user?->name }}</span></li>
                @endforeach
            </ul></div>
    </div>
</div>
@push('scripts')
<script src="{{ asset('vendor/chart.umd.js') }}"></script>
<script>
const perf = @json($categoryPerf);
const labels = Object.keys(perf);
if (labels.length) new Chart(document.getElementById('catChart'), {
    type: 'bar',
    data: { labels, datasets: [
        { label: 'Correct', data: labels.map(l => perf[l].correct), backgroundColor: '#86C440' },
        { label: 'Not correct', data: labels.map(l => perf[l].asked - perf[l].correct), backgroundColor: '#E94B6A' },
    ]},
    options: { indexAxis: 'y', scales: { x: { stacked: true, ticks: { precision: 0 } }, y: { stacked: true } }, plugins: { legend: { position: 'bottom' } } }
});
</script>
@endpush
@endsection

@extends('layouts.admin')
@section('title', 'Overview')
@section('actions')
    <a href="{{ route('admin.sessions.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> New quiz session</a>
@endsection
@section('content')
    @if ($liveSessions->isNotEmpty())
        <div class="card mb-4 border-0 hero-card">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <div class="display-6"><i class="bi bi-broadcast"></i></div>
                <div class="flex-grow-1">
                    <div class="fw-bold">Ready to play</div>
                    <div class="small opacity-75">Open a session on the projector and press <kbd>Space</kbd> to Spin &amp; Play.</div>
                </div>
                @foreach ($liveSessions as $s)
                    <a href="{{ route('play', $s) }}" class="btn btn-light"><i class="bi bi-play-circle-fill text-success"></i> {{ \Illuminate\Support\Str::limit($s->title, 34) }} <small class="text-muted">({{ $s->status }})</small></a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([
            ['Questions', $stats['questions'], 'patch-question', 'primary', $stats['active_questions'].' active'],
            ['Team members', $stats['participants'], 'people', 'success', ''],
            ['Quiz sessions', $stats['sessions'], 'controller', 'warning', $stats['live'].' running'],
            ['Active categories', $stats['categories'], 'tags', 'info', ''],
        ] as [$label, $value, $icon, $color, $sub])
            <div class="col-6 col-xl-3">
                <div class="card stat-card h-100"><div class="card-body">
                    <div class="stat-icon text-bg-{{ $color }}"><i class="bi bi-{{ $icon }}"></i></div>
                    <div class="stat-value">{{ number_format($value) }}</div>
                    <div class="stat-label">{{ $label }} <span class="text-muted small">{{ $sub }}</span></div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex"><strong>Recent sessions</strong><a href="{{ route('admin.sessions.index') }}" class="ms-auto small">All sessions</a></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Session</th><th>Date</th><th>Status</th><th>Rounds</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($recentSessions as $s)
                            <tr>
                                <td><a href="{{ route('admin.sessions.show', $s) }}" class="fw-semibold">{{ $s->title }}</a><div class="small text-muted">{{ $s->workshop_title }}</div></td>
                                <td class="small">{{ $s->session_date?->format('j M Y') }}</td>
                                <td>@include('partials.status-badge', ['status' => $s->status])</td>
                                <td>{{ $s->current_round }}/{{ $s->total_rounds }}</td>
                                <td class="text-end">@if ($s->isPlayable())<a class="btn btn-sm btn-success" href="{{ route('play', $s) }}"><i class="bi bi-play-fill"></i> Play</a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No sessions yet. <a href="{{ route('admin.sessions.create') }}">Create the first one</a>.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card mb-4">
                <div class="card-header"><strong>Highest scoring team members</strong> <span class="small text-muted">(all sessions)</span></div>
                <ul class="list-group list-group-flush">
                    @forelse ($topParticipants as $i => $row)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <span class="rank-dot rank-{{ $i + 1 }}">{{ $i + 1 }}</span>
                            <span class="flex-grow-1">{{ $row->participant?->full_name ?? 'Removed' }}</span>
                            <span class="small text-muted">{{ $row->total_correct }}/{{ $row->total_answered }}</span>
                            <strong>{{ $row->total_score }}</strong>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No scores yet.</li>
                    @endforelse
                </ul>
            </div>
            <div class="card">
                <div class="card-header"><strong>Most frequently used questions</strong></div>
                <ul class="list-group list-group-flush">
                    @forelse ($topQuestions as $q)
                        <li class="list-group-item d-flex gap-2">
                            <span class="badge rounded-pill" style="background: {{ $q->category?->color }}">{{ $q->times_used }}×</span>
                            <a href="{{ route('admin.questions.edit', $q) }}" class="small text-body">{{ \Illuminate\Support\Str::limit($q->question_text, 80) }}</a>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No questions played yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection

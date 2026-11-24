@extends('layouts.admin')
@section('title', 'Quiz sessions')
@section('actions')
    <a href="{{ route('admin.sessions.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> New session</a>
@endsection
@section('content')
<form class="card card-body mb-3 row g-2 flex-row" method="GET">
    <div class="col-md-7"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search session or workshop…"></div>
    <div class="col-md-4"><select class="form-select" name="status"><option value="">All statuses</option>@foreach (\App\Models\QuizSession::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-md-1 d-grid"><button class="btn btn-primary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Session</th><th>Date</th><th>Facilitator</th><th>Players</th><th>Progress</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($sessions as $s)
                <tr>
                    <td><a class="fw-semibold" href="{{ route('admin.sessions.show', $s) }}">{{ $s->title }}</a><div class="small text-muted">{{ $s->workshop_title }}</div></td>
                    <td class="small">{{ $s->session_date?->format('j M Y') }}</td>
                    <td class="small">{{ $s->facilitator?->name }}</td>
                    <td>{{ $s->entries_count }}</td>
                    <td style="min-width:120px"><div class="progress" style="height:8px"><div class="progress-bar bg-success" style="width: {{ $s->total_rounds ? min(100, $s->current_round / $s->total_rounds * 100) : 0 }}%"></div></div><small>{{ $s->current_round }}/{{ $s->total_rounds }}</small></td>
                    <td>@include('partials.status-badge', ['status' => $s->status])</td>
                    <td class="text-end text-nowrap">
                        @if ($s->isPlayable() || $s->status === 'draft')<a class="btn btn-sm btn-success" href="{{ route('play', $s) }}"><i class="bi bi-play-fill"></i> {{ $s->current_round ? 'Resume' : 'Play' }}</a>@endif
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.sessions.show', $s) }}"><i class="bi bi-bar-chart"></i></a>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.sessions.edit', $s) }}"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-5">No sessions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $sessions->links() }}</div>
</div>
@endsection

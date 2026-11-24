@extends('layouts.admin')
@section('title', $participant->full_name)
@section('actions')
    <a href="{{ route('admin.participants.edit', $participant) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
@endsection
@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card h-100"><div class="card-body d-flex gap-3 align-items-center">
        @if ($participant->photo_url)<img src="{{ $participant->photo_url }}" class="avatar avatar-xl" alt="">@else<span class="avatar avatar-xl avatar-initials">{{ $participant->initials }}</span>@endif
        <div><div class="h5 mb-0">{{ $participant->full_name }}</div><div class="text-muted">{{ $participant->job_title }}</div><div class="small">{{ $participant->department }}</div></div>
    </div></div></div>
    @foreach ([['Total score', $stats['score']], ['Sessions', $stats['sessions']], ['Answered', $stats['answered']], ['Correct', $stats['correct']], ['Incorrect', $stats['incorrect']]] as [$l, $v])
        <div class="col"><div class="card stat-card h-100"><div class="card-body"><div class="stat-value">{{ $v }}</div><div class="stat-label">{{ $l }}</div></div></div></div>
    @endforeach
</div>
<div class="row g-4">
    <div class="col-lg-5"><div class="card"><div class="card-header"><strong>Sessions</strong></div>
        <table class="table mb-0"><thead><tr><th>Session</th><th>Score</th><th>Correct</th><th>Best streak</th></tr></thead><tbody>
        @forelse ($entries as $e)
            <tr><td><a href="{{ route('admin.sessions.show', $e->quiz_session_id) }}">{{ $e->session?->title }}</a></td><td class="fw-bold">{{ $e->score }}</td><td>{{ $e->correct }}/{{ $e->answered }}</td><td>{{ $e->best_streak }}</td></tr>
        @empty <tr><td colspan="4" class="text-muted">Not yet played.</td></tr> @endforelse
        </tbody></table></div></div>
    <div class="col-lg-7"><div class="card"><div class="card-header"><strong>Participation history</strong> <span class="small text-muted">(last 50 rounds)</span></div>
        <table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Session</th><th>Question</th><th>Result</th><th>Pts</th></tr></thead><tbody>
        @forelse ($rounds as $r)
            <tr><td class="small text-nowrap">{{ $r->created_at->format('j M') }}</td><td class="small">{{ $r->session?->title }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($r->question?->question_text ?? $r->challenge_text, 70) }}</td>
                <td>@include('admin.sessions._result', ['result' => $r->result])</td><td>{{ $r->points_awarded }}</td></tr>
        @empty <tr><td colspan="5" class="text-muted">No rounds yet.</td></tr> @endforelse
        </tbody></table></div></div>
</div>
@endsection

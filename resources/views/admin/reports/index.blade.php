@extends('layouts.admin')
@section('title', 'Reports & analytics')
@section('actions')
    <a href="{{ route('admin.reports.participants') }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Participant performance CSV</a>
    <a href="{{ route('admin.reports.questions') }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Question statistics CSV</a>
@endsection
@section('content')
<form class="card card-body mb-3 row g-2 flex-row align-items-end" method="GET">
    <div class="col-md-3"><label class="form-label small">From</label><input type="date" class="form-control" name="from" value="{{ $from?->format('Y-m-d') }}"></div>
    <div class="col-md-3"><label class="form-label small">To</label><input type="date" class="form-control" name="to" value="{{ $to?->format('Y-m-d') }}"></div>
    <div class="col-md-2 d-grid"><button class="btn btn-primary">Apply</button></div>
</form>
<div class="row g-3 mb-4">
    @foreach ([['Sessions', $summary['sessions']], ['Completed', $summary['completed']], ['Questions answered', $summary['rounds']], ['Correct', $summary['correct']], ['Incorrect', $summary['incorrect']], ['Average score', $summary['avg_score']], ['Participants', $summary['participants']]] as [$l, $v])
        <div class="col"><div class="card stat-card h-100"><div class="card-body"><div class="stat-value">{{ $v }}</div><div class="stat-label">{{ $l }}</div></div></div></div>
    @endforeach
</div>
<div class="row g-4 mb-4">
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><strong>Answer outcomes</strong></div><div class="card-body"><canvas id="outcomeChart"></canvas></div></div></div>
    <div class="col-lg-8"><div class="card h-100"><div class="card-header"><strong>Category performance (% correct)</strong></div><div class="card-body"><canvas id="catChart" height="120"></canvas></div></div></div>
</div>
<div class="row g-4">
    <div class="col-xl-7"><div class="card"><div class="card-header"><strong>Participant performance</strong></div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Name</th><th>Sessions</th><th>Score</th><th>Answered</th><th>Correct</th><th>Wrong</th><th>Accuracy</th><th>Best streak</th></tr></thead><tbody>
        @forelse ($participants as $p)
            <tr><td><a href="{{ route('admin.participants.show', $p) }}">{{ $p->full_name }}</a></td><td>{{ $p->sessions }}</td><td class="fw-bold">{{ $p->score }}</td><td>{{ $p->answered }}</td><td>{{ $p->correct }}</td><td>{{ $p->incorrect }}</td><td>{{ $p->answered ? round($p->correct / $p->answered * 100) : 0 }}%</td><td>{{ $p->best_streak }}</td></tr>
        @empty <tr><td colspan="8" class="text-muted">No data for this period.</td></tr> @endforelse
        </tbody></table></div></div></div>
    <div class="col-xl-5">
        <div class="card mb-4"><div class="card-header"><strong>Sessions</strong></div><ul class="list-group list-group-flush">
            @forelse ($sessions as $s)<li class="list-group-item d-flex"><a href="{{ route('admin.sessions.show', $s) }}">{{ $s->title }}</a><span class="ms-auto small text-muted">{{ $s->session_date?->format('j M Y') }} · {{ $s->rounds_count }} rounds</span></li>
            @empty <li class="list-group-item text-muted">None.</li> @endforelse</ul></div>
        <div class="card"><div class="card-header"><strong>Most used questions</strong></div><ul class="list-group list-group-flush">
            @foreach ($topQuestions as $q)<li class="list-group-item small d-flex gap-2"><span class="badge text-bg-primary">{{ $q->times_used }}</span>{{ \Illuminate\Support\Str::limit($q->question_text, 80) }}</li>@endforeach</ul></div>
    </div>
</div>
@push('scripts')
<script src="{{ asset('vendor/chart.umd.js') }}"></script>
<script>
const s = @json($summary), cats = @json($categoryPerf);
new Chart(document.getElementById('outcomeChart'), { type: 'doughnut', data: { labels: ['Correct', 'Incorrect / timed out', 'Partial', 'Passed'],
    datasets: [{ data: [s.correct, s.incorrect, s.partial, s.passed], backgroundColor: ['#86C440', '#E94B6A', '#4EAADF', '#A7A9AC'] }] }, options: { plugins: { legend: { position: 'bottom' } } } });
new Chart(document.getElementById('catChart'), { type: 'bar', data: { labels: cats.map(c => c.name),
    datasets: [{ label: '% correct', data: cats.map(c => c.asked ? Math.round(c.correct / c.asked * 100) : 0), backgroundColor: cats.map(c => c.color) }] },
    options: { scales: { y: { max: 100, beginAtZero: true } }, plugins: { legend: { display: false }, tooltip: { callbacks: { afterLabel: ctx => `${cats[ctx.dataIndex].correct}/${cats[ctx.dataIndex].asked} answered correctly` } } } } });
</script>
@endpush
@endsection

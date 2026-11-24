@extends('layouts.admin')
@section('title', $session->exists ? 'Edit session' : 'Create quiz session')
@section('content')
<form method="POST" action="{{ $session->exists ? route('admin.sessions.update', $session) : route('admin.sessions.store') }}">
    @csrf @if ($session->exists) @method('PUT') @endif
    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card mb-4">
                <div class="card-header"><strong><span class="step">1</span> Session details</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-7"><label class="form-label">Session name</label><input class="form-control" name="title" value="{{ old('title', $session->title) }}" required maxlength="150"></div>
                    <div class="col-md-5"><label class="form-label">Date</label><input type="date" class="form-control" name="session_date" value="{{ old('session_date', $session->session_date?->format('Y-m-d')) }}"></div>
                    <div class="col-md-7"><label class="form-label">Workshop / training title</label><input class="form-control" name="workshop_title" value="{{ old('workshop_title', $session->workshop_title) }}" maxlength="150" placeholder="e.g. Warehouse Safety Refresher"></div>
                    <div class="col-md-5"><label class="form-label">Department</label><input class="form-control" name="department" value="{{ old('department', $session->department) }}" maxlength="100"></div>
                    <div class="col-md-4"><label class="form-label">Number of questions</label><input type="number" class="form-control" name="total_rounds" min="1" max="500" value="{{ old('total_rounds', $session->total_rounds) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Planned duration (min)</label><input type="number" class="form-control" name="duration_minutes" min="1" max="600" value="{{ old('duration_minutes', $session->duration_minutes) }}"></div>
                    <div class="col-md-4"><label class="form-label">Participant selection</label>
                        <select class="form-select" name="selection_mode">@foreach (\App\Models\QuizSession::SELECTION_MODES as $k => $v)<option value="{{ $k }}" @selected(old('selection_mode', $session->selection_mode) === $k)>{{ $v }}</option>@endforeach</select></div>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header"><strong><span class="step">2</span> Game configuration</strong></div>
                <div class="card-body p-0">@include('admin.settings._game-fields', ['config' => $config])</div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center"><strong><span class="step">3</span> Team members</strong>
                    <span class="ms-auto small"><a href="#" data-check="participant_ids" data-val="1">All</a> · <a href="#" data-check="participant_ids" data-val="0">None</a></span></div>
                <div class="card-body pick-list">
                    <input class="form-control form-control-sm mb-2" placeholder="Filter…" data-filter="#plist">
                    <div id="plist">
                    @foreach ($participants as $p)
                        <label class="pick-item"><input type="checkbox" class="form-check-input" name="participant_ids[]" value="{{ $p->id }}" @checked(in_array($p->id, old('participant_ids', $selectedParticipants)))>
                            <span>{{ $p->full_name }}</span><small class="text-muted ms-auto">{{ $p->job_title }}</small></label>
                    @endforeach
                    </div>
                    <a href="{{ route('admin.participants.create') }}" class="small" target="_blank"><i class="bi bi-person-plus"></i> Add a new team member</a>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header d-flex"><strong><span class="step">4</span> Question categories</strong><span class="ms-auto small text-muted">none ticked = all</span></div>
                <div class="card-body">
                    @foreach ($categories as $c)
                        <label class="pick-item"><input type="checkbox" class="form-check-input" name="category_ids[]" value="{{ $c->id }}" @checked(in_array($c->id, old('category_ids', $selectedCategories)))>
                            <span class="cat-chip" style="--c: {{ $c->color }}">{{ $c->name }}</span><small class="ms-auto text-muted">{{ $c->questions_count }} questions</small></label>
                    @endforeach
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header"><a data-bs-toggle="collapse" href="#qpool" class="text-body"><strong><span class="step">5</span> Specific questions</strong> <small class="text-muted">(optional – leave empty to use every question in the chosen categories)</small></a></div>
                <div id="qpool" class="collapse {{ count(old('question_ids', $selectedQuestions)) ? 'show' : '' }}"><div class="card-body pick-list">
                    <input class="form-control form-control-sm mb-2" placeholder="Filter…" data-filter="#qlist">
                    <div id="qlist">
                    @foreach ($questions as $q)
                        <label class="pick-item small"><input type="checkbox" class="form-check-input" name="question_ids[]" value="{{ $q->id }}" @checked(in_array($q->id, old('question_ids', $selectedQuestions)))>
                            <span>{{ \Illuminate\Support\Str::limit($q->question_text, 90) }}</span><small class="ms-auto text-muted text-nowrap">{{ $q->category?->name }}</small></label>
                    @endforeach
                    </div>
                </div></div>
            </div>
            <div class="card sticky-save"><div class="card-body d-grid gap-2">
                @unless ($session->exists)
                    <button class="btn btn-success btn-lg" name="after" value="play"><i class="bi bi-play-circle-fill"></i> Save &amp; open game screen</button>
                @endunless
                <button class="btn btn-brand" name="after" value="show"><i class="bi bi-save"></i> Save session</button>
            </div></div>
        </div>
    </div>
</form>
@push('scripts')
<script>
document.querySelectorAll('[data-check]').forEach(a => a.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll(`input[name="${a.dataset.check}[]"]`).forEach(cb => { if (cb.closest('label').style.display !== 'none') cb.checked = a.dataset.val === '1'; });
}));
document.querySelectorAll('[data-filter]').forEach(inp => inp.addEventListener('input', () => {
    const t = inp.value.toLowerCase();
    document.querySelectorAll(inp.dataset.filter + ' label').forEach(l => l.style.display = l.textContent.toLowerCase().includes(t) ? '' : 'none');
}));
</script>
@endpush
@endsection

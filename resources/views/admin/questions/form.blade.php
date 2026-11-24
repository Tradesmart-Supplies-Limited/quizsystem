@extends('layouts.admin')
@section('title', $question->exists ? 'Edit question' : 'New question')
@section('content')
@php
    $opts = old('options', $question->exists ? $question->options->map(fn ($o) => ['text' => $o->option_text])->all() : []);
    $opts = array_pad($opts, 4, ['text' => '']);
    $correctIdx = old('correct_option', $question->exists ? $question->options->search(fn ($o) => $o->is_correct) : null);
@endphp
<div class="row g-4">
<div class="col-xl-8">
<form method="POST" action="{{ $question->exists ? route('admin.questions.update', $question) : route('admin.questions.store') }}" class="card">
    @csrf @if ($question->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-12"><label class="form-label fw-semibold">Question</label>
            <textarea class="form-control form-control-lg" name="question_text" rows="3" required maxlength="2000">{{ old('question_text', $question->question_text) }}</textarea></div>
        <div class="col-md-4"><label class="form-label">Category</label>
            <select class="form-select" name="question_category_id" required>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('question_category_id', $question->question_category_id) == $c->id)>{{ $c->name }}{{ $c->is_active ? '' : ' (disabled)' }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Type</label>
            <select class="form-select" name="type" id="qtype">@foreach (\App\Models\Question::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('type', $question->type) === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Difficulty</label>
            <select class="form-select" name="difficulty">@foreach (\App\Models\Question::DIFFICULTIES as $k => $v)<option value="{{ $k }}" @selected(old('difficulty', $question->difficulty) === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Points</label><input type="number" class="form-control" name="points" min="0" max="1000" value="{{ old('points', $question->points) }}" required></div>
        <div class="col-md-4"><label class="form-label">Answer time (seconds)</label><input type="number" class="form-control" name="time_limit" min="5" max="600" value="{{ old('time_limit', $question->time_limit) }}" placeholder="Session default"></div>
        <div class="col-md-4 d-flex flex-column justify-content-end gap-1">
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="a" @checked(old('is_active', $question->is_active))><label class="form-check-label" for="a">Enabled</label></div>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_bonus" value="1" id="b" @checked(old('is_bonus', $question->is_bonus))><label class="form-check-label" for="b">Use in bonus rounds</label></div>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_danger" value="1" id="d" @checked(old('is_danger', $question->is_danger))><label class="form-check-label" for="d">Use in danger rounds</label></div>
        </div>
        <div class="col-12" id="optionsBox">
            <label class="form-label fw-semibold">Multiple-choice options <small class="text-muted">(select the correct one; leave blank for non-MCQ types)</small></label>
            <div id="optRows">
            @foreach ($opts as $i => $o)
                <div class="input-group mb-2 opt-row">
                    <span class="input-group-text"><input class="form-check-input mt-0" type="radio" name="correct_option" value="{{ $i }}" @checked((string) $correctIdx === (string) $i)></span>
                    <span class="input-group-text fw-bold">{{ chr(65 + $i) }}</span>
                    <input class="form-control" name="options[{{ $i }}][text]" value="{{ $o['text'] ?? '' }}" maxlength="500">
                </div>
            @endforeach
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="addOpt"><i class="bi bi-plus"></i> Add option</button>
        </div>
        <div class="col-12"><label class="form-label fw-semibold">Correct answer</label>
            <textarea class="form-control" name="correct_answer" rows="2" maxlength="2000" placeholder="Shown to the facilitator / revealed after answering">{{ old('correct_answer', $question->correct_answer) }}</textarea></div>
        <div class="col-12"><label class="form-label">Explanation <small class="text-muted">(optional)</small></label>
            <textarea class="form-control" name="explanation" rows="2" maxlength="2000">{{ old('explanation', $question->explanation) }}</textarea></div>
    </div>
    <div class="card-footer d-flex gap-2"><button class="btn btn-brand"><i class="bi bi-save"></i> Save question</button><a href="{{ route('admin.questions.index') }}" class="btn btn-light">Cancel</a>
        @if ($question->exists)<span class="ms-auto small text-muted align-self-center">Created {{ $question->created_at?->format('j M Y') }} · Modified {{ $question->updated_at?->diffForHumans() }}</span>@endif</div>
</form>
</div>
@if ($question->exists)
<div class="col-xl-4">
    <div class="card">
        <div class="card-header"><strong>Usage history</strong> <span class="badge text-bg-primary">{{ $question->times_used }} uses</span></div>
        <ul class="list-group list-group-flush">
            @forelse ($usage ?? [] as $r)
                <li class="list-group-item small">
                    <div class="d-flex"><span>{{ $r->session?->title }}</span><span class="ms-auto text-muted">R{{ $r->round_number }}</span></div>
                    <div class="text-muted">{{ $r->participant?->display_name }} · {{ $r->result ?? 'not scored' }} · {{ $r->created_at->format('j M Y') }}</div>
                </li>
            @empty
                <li class="list-group-item text-muted small">Never used yet.</li>
            @endforelse
        </ul>
    </div>
</div>
@endif
</div>
@push('scripts')
<script>
document.getElementById('addOpt').addEventListener('click', () => {
    const rows = document.querySelectorAll('.opt-row');
    if (rows.length >= 8) return;
    const i = rows.length, el = rows[0].cloneNode(true);
    el.querySelector('input[type=radio]').value = i; el.querySelector('input[type=radio]').checked = false;
    el.querySelectorAll('.input-group-text')[1].textContent = String.fromCharCode(65 + i);
    const t = el.querySelector('input.form-control'); t.name = `options[${i}][text]`; t.value = '';
    document.getElementById('optRows').appendChild(el);
});
</script>
@endpush
@endsection

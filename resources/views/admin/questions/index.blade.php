@extends('layouts.admin')
@section('title', 'Questions')
@section('actions')
    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-arrow-down-up"></i> Import / Export</button>
        <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 320px">
            <a class="dropdown-item px-0" href="{{ route('admin.questions.export') }}"><i class="bi bi-download"></i> Export all questions (CSV / Excel)</a>
            <a class="dropdown-item px-0" href="{{ route('admin.questions.template') }}"><i class="bi bi-file-earmark-spreadsheet"></i> Download import template</a>
            <hr>
            <form method="POST" action="{{ route('admin.questions.import') }}" enctype="multipart/form-data">@csrf
                <label class="form-label small">Import CSV <span class="text-muted">(save Excel files as “CSV UTF-8”)</span></label>
                <input type="file" name="file" accept=".csv,text/csv" class="form-control form-control-sm mb-2" required>
                <button class="btn btn-sm btn-brand w-100"><i class="bi bi-upload"></i> Import</button>
                <div class="form-text">Rows with an existing <code>id</code> are updated; others are added.</div>
            </form>
        </div>
    </div>
    <a href="{{ route('admin.questions.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Add question</a>
@endsection
@section('content')
<form class="card card-body mb-3 row g-2 flex-row" method="GET">
    <div class="col-md-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search questions or answers…"></div>
    <div class="col-md-2"><select class="form-select" name="category"><option value="">All categories</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><select class="form-select" name="difficulty"><option value="">Any difficulty</option>@foreach (\App\Models\Question::DIFFICULTIES as $k => $v)<option value="{{ $k }}" @selected(request('difficulty') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-md-2"><select class="form-select" name="type"><option value="">Any type</option>@foreach (\App\Models\Question::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-md-1"><select class="form-select" name="status"><option value="">All</option><option value="active" @selected(request('status') === 'active')>Enabled</option><option value="inactive" @selected(request('status') === 'inactive')>Disabled</option></select></div>
    <div class="col-md-1 d-grid"><button class="btn btn-primary"><i class="bi bi-funnel"></i></button></div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th style="width:45%">Question</th><th>Category</th><th>Type</th><th>Diff.</th><th>Pts</th><th><a href="{{ request()->fullUrlWithQuery(['sort' => 'times_used']) }}">Used</a></th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($questions as $q)
                <tr class="{{ $q->is_active ? '' : 'opacity-50' }}">
                    <td>
                        <div class="fw-semibold">{{ \Illuminate\Support\Str::limit($q->question_text, 140) }}</div>
                        <div class="small text-muted"><i class="bi bi-check2-circle"></i> {{ \Illuminate\Support\Str::limit($q->correct_answer, 90) }}</div>
                        @if ($q->is_bonus)<span class="badge text-bg-warning">BONUS</span>@endif
                        @if ($q->is_danger)<span class="badge text-bg-danger">DANGER</span>@endif
                    </td>
                    <td><span class="cat-chip" style="--c: {{ $q->category?->color }}">{{ $q->category?->name }}</span></td>
                    <td class="small">{{ $q->type_label }}</td>
                    <td class="small text-capitalize">{{ $q->difficulty }}</td>
                    <td>{{ $q->points }}</td>
                    <td>{{ $q->times_used }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.questions.toggle', $q) }}">@csrf @method('PATCH')
                            <button class="btn btn-sm {{ $q->is_active ? 'btn-success' : 'btn-outline-secondary' }}" title="Enable / disable">{{ $q->is_active ? 'On' : 'Off' }}</button></form>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.questions.edit', $q) }}"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="POST" action="{{ route('admin.questions.destroy', $q) }}" data-confirm="Delete this question? Its history in past sessions is kept.">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No questions match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex align-items-center"><span class="small text-muted">{{ $questions->total() }} questions</span><div class="ms-auto">{{ $questions->links() }}</div></div>
</div>
@endsection

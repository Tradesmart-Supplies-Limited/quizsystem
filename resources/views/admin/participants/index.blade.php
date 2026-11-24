@extends('layouts.admin')
@section('title', 'Team members')
@section('actions')
    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-arrow-down-up"></i> Import / Export</button>
        <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 320px">
            <a class="dropdown-item px-0" href="{{ route('admin.participants.export') }}"><i class="bi bi-download"></i> Export list (CSV / Excel)</a>
            <a class="dropdown-item px-0" href="{{ route('admin.participants.template') }}"><i class="bi bi-file-earmark-spreadsheet"></i> Download import template</a>
            <hr>
            <form method="POST" action="{{ route('admin.participants.import') }}" enctype="multipart/form-data">@csrf
                <label class="form-label small">Import CSV (columns: full_name, display_name, department, job_title)</label>
                <input type="file" name="file" accept=".csv,text/csv" class="form-control form-control-sm mb-2" required>
                <button class="btn btn-sm btn-brand w-100"><i class="bi bi-upload"></i> Import</button>
            </form>
        </div>
    </div>
    <a href="{{ route('admin.participants.create') }}" class="btn btn-brand"><i class="bi bi-person-plus"></i> Add team member</a>
@endsection
@section('content')
<form class="card card-body mb-3 row g-2 flex-row" method="GET">
    <div class="col-md-6"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search names, departments, job titles…"></div>
    <div class="col-md-3"><select class="form-select" name="department"><option value="">All departments</option>@foreach ($departments as $d)<option @selected(request('department') === $d)>{{ $d }}</option>@endforeach</select></div>
    <div class="col-md-2"><select class="form-select" name="status"><option value="">All</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select></div>
    <div class="col-md-1 d-grid"><button class="btn btn-primary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Name</th><th>Department / role</th><th>Sessions</th><th>Total score</th><th>Answered</th><th>Correct</th><th>Wrong</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($participants as $p)
                <tr class="{{ $p->is_active ? '' : 'opacity-50' }}">
                    <td><div class="d-flex align-items-center gap-2">
                        @if ($p->photo_url)<img src="{{ $p->photo_url }}" class="avatar" alt="">@else<span class="avatar avatar-initials">{{ $p->initials }}</span>@endif
                        <div><a href="{{ route('admin.participants.show', $p) }}" class="fw-semibold">{{ $p->full_name }}</a><div class="small text-muted">“{{ $p->display_name }}”</div></div></div></td>
                    <td class="small">{{ $p->department }}<div class="text-muted">{{ $p->job_title }}</div></td>
                    <td>{{ $p->sessions_count }}</td>
                    <td class="fw-bold">{{ (int) $p->total_score }}</td>
                    <td>{{ (int) $p->total_answered }}</td>
                    <td class="text-success">{{ (int) $p->total_correct }}</td>
                    <td class="text-danger">{{ (int) $p->total_incorrect }}</td>
                    <td><form method="POST" action="{{ route('admin.participants.toggle', $p) }}">@csrf @method('PATCH')
                        <button class="btn btn-sm {{ $p->is_active ? 'btn-success' : 'btn-outline-secondary' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</button></form></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.participants.edit', $p) }}"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="POST" action="{{ route('admin.participants.destroy', $p) }}" data-confirm="Remove {{ $p->full_name }}? Past results are kept in reports.">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No team members found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex"><span class="small text-muted">{{ $participants->total() }} team members</span><div class="ms-auto">{{ $participants->links() }}</div></div>
</div>
@endsection

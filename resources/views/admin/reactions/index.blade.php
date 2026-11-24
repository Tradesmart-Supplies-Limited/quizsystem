@extends('layouts.admin')
@section('title', 'Reaction messages')
@section('content')
<p class="text-muted">These playful messages flash up on the big screen after each answer. Keep them friendly – never humiliating.</p>
<div class="row g-4">
@foreach (['correct' => ['Correct answer reactions', 'success'], 'incorrect' => ['Wrong answer reactions', 'danger']] as $type => [$label, $color])
    <div class="col-lg-6"><div class="card">
        <div class="card-header text-bg-{{ $color }}"><strong>{{ $label }}</strong></div>
        <ul class="list-group list-group-flush">
            @foreach ($reactions->where('type', $type) as $r)
                <li class="list-group-item">
                    <form method="POST" action="{{ route('admin.reactions.update', $r) }}" class="d-flex gap-2 align-items-center">@csrf @method('PUT')
                        <input class="form-control form-control-sm fw-bold" name="message" value="{{ $r->message }}" maxlength="120">
                        <div class="form-check form-switch mb-0" title="Enabled"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($r->is_active) onchange="this.form.submit()"></div>
                        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-save"></i></button>
                    </form>
                    <form method="POST" action="{{ route('admin.reactions.destroy', $r) }}" class="d-inline" data-confirm="Delete this reaction?">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0">delete</button></form>
                </li>
            @endforeach
            <li class="list-group-item">
                <form method="POST" action="{{ route('admin.reactions.store') }}" class="d-flex gap-2">@csrf<input type="hidden" name="type" value="{{ $type }}">
                    <input class="form-control form-control-sm" name="message" placeholder="New reaction…" maxlength="120" required><button class="btn btn-sm btn-brand">Add</button></form>
            </li>
        </ul>
    </div></div>
@endforeach
</div>
@endsection

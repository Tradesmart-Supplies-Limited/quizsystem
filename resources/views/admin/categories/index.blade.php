@extends('layouts.admin')
@section('title', 'Question categories')
@section('actions')
    <a href="{{ route('admin.categories.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Add category</a>
@endsection
@section('content')
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Category</th><th>Questions</th><th>Order</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @foreach ($categories as $c)
                <tr>
                    <td><span class="cat-chip" style="--c: {{ $c->color }}"><i class="bi bi-{{ $c->icon ?: 'tag' }}"></i> {{ $c->name }}</span>
                        @if ($c->description)<div class="small text-muted">{{ $c->description }}</div>@endif</td>
                    <td><a href="{{ route('admin.questions.index', ['category' => $c->id]) }}">{{ $c->questions_count }}</a></td>
                    <td>{{ $c->sort_order }}</td>
                    <td>{!! $c->is_active ? '<span class="badge text-bg-success">Enabled</span>' : '<span class="badge text-bg-secondary">Disabled</span>' !!}</td>
                    <td class="text-end text-nowrap">
                        <form class="d-inline" method="POST" action="{{ route('admin.categories.toggle', $c) }}">@csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-secondary">{{ $c->is_active ? 'Disable' : 'Enable' }}</button></form>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.categories.edit', $c) }}"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="POST" action="{{ route('admin.categories.destroy', $c) }}" data-confirm="Delete this category?">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

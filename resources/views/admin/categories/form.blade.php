@extends('layouts.admin')
@section('title', $category->exists ? 'Edit category' : 'New category')
@section('content')
<form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="card" style="max-width: 720px">
    @csrf @if ($category->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-8"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $category->name) }}" required maxlength="100"></div>
        <div class="col-md-4"><label class="form-label">Colour</label><input type="color" class="form-control form-control-color w-100" name="color" value="{{ old('color', $category->color) }}"></div>
        <div class="col-md-8"><label class="form-label">Icon <small class="text-muted">(Bootstrap Icons name, e.g. <code>truck</code>, <code>box-seam</code>)</small></label><input class="form-control" name="icon" value="{{ old('icon', $category->icon) }}" maxlength="50"></div>
        <div class="col-md-4"><label class="form-label">Sort order</label><input type="number" class="form-control" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0" max="999"></div>
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2" maxlength="1000">{{ old('description', $category->description) }}</textarea></div>
        <div class="col-12 form-check form-switch ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $category->is_active))><label class="form-check-label" for="act">Enabled (questions in this category can be selected)</label></div>
    </div>
    <div class="card-footer d-flex gap-2"><button class="btn btn-brand">Save</button><a href="{{ route('admin.categories.index') }}" class="btn btn-light">Cancel</a></div>
</form>
@endsection

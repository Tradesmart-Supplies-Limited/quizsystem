@extends('layouts.admin')
@section('title', $user->exists ? 'Edit user' : 'New user')
@section('content')
<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card" style="max-width: 640px">
    @csrf @if ($user->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $user->name) }}" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="{{ old('email', $user->email) }}" required autocomplete="off"></div>
        <div class="col-md-6"><label class="form-label">Role</label><select class="form-select" name="role">@foreach (\App\Models\User::ROLES as $k => $l)<option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ua" @checked(old('is_active', $user->is_active))><label class="form-check-label" for="ua">Account active</label></div></div>
        <div class="col-md-6"><label class="form-label">Password {{ $user->exists ? '(leave blank to keep)' : '' }}</label><input type="password" class="form-control" name="password" autocomplete="new-password" {{ $user->exists ? '' : 'required' }}></div>
        <div class="col-md-6"><label class="form-label">Confirm password</label><input type="password" class="form-control" name="password_confirmation" autocomplete="new-password"></div>
    </div>
    <div class="card-footer d-flex gap-2"><button class="btn btn-brand">Save</button><a href="{{ route('admin.users.index') }}" class="btn btn-light">Cancel</a></div>
</form>
@endsection

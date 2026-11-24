@extends('layouts.admin')
@section('title', $participant->exists ? 'Edit team member' : 'New team member')
@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $participant->exists ? route('admin.participants.update', $participant) : route('admin.participants.store') }}" class="card" style="max-width: 760px">
    @csrf @if ($participant->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-7"><label class="form-label">Full name</label><input class="form-control" name="full_name" value="{{ old('full_name', $participant->full_name) }}" required maxlength="150"></div>
        <div class="col-md-5"><label class="form-label">Display name <small class="text-muted">(shown on the wheel)</small></label><input class="form-control" name="display_name" value="{{ old('display_name', $participant->display_name) }}" maxlength="60" placeholder="First name by default"></div>
        <div class="col-md-6"><label class="form-label">Department</label><input class="form-control" name="department" value="{{ old('department', $participant->department) }}" maxlength="100"></div>
        <div class="col-md-6"><label class="form-label">Job title</label><input class="form-control" name="job_title" value="{{ old('job_title', $participant->job_title) }}" maxlength="100"></div>
        <div class="col-md-6"><label class="form-label">Email <small class="text-muted">(optional, never required to play)</small></label><input type="email" class="form-control" name="email" value="{{ old('email', $participant->email) }}" maxlength="150"></div>
        <div class="col-md-6"><label class="form-label">Profile photo <small class="text-muted">(JPG/PNG/WebP, max 2&nbsp;MB)</small></label>
            <input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/webp">
            @if ($participant->photo_url)
                <div class="d-flex align-items-center gap-2 mt-2"><img src="{{ $participant->photo_url }}" class="avatar avatar-lg" alt="">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="rp"><label class="form-check-label" for="rp">Remove photo</label></div></div>
            @endif
        </div>
        <div class="col-12 form-check form-switch ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $participant->is_active))><label class="form-check-label" for="act">Active (eligible for selection)</label></div>
    </div>
    <div class="card-footer d-flex gap-2"><button class="btn btn-brand">Save</button><a href="{{ route('admin.participants.index') }}" class="btn btn-light">Cancel</a></div>
</form>
@endsection

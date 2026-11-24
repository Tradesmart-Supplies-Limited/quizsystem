@extends('layouts.admin')
@section('title', 'Users')
@section('actions')<a href="{{ route('admin.users.create') }}" class="btn btn-brand"><i class="bi bi-person-plus"></i> Add user</a>@endsection
@section('content')
<p class="text-muted small"><strong>Administrators</strong> can manage users, default settings and delete sessions. <strong>Facilitators</strong> can manage questions, team members and run quiz sessions. Team members never need an account to play.</p>
<div class="card"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead><tbody>
@foreach ($users as $u)
    <tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td><span class="badge text-bg-{{ $u->isAdmin() ? 'primary' : 'secondary' }}">{{ \App\Models\User::ROLES[$u->role] ?? $u->role }}</span></td>
        <td>{!! $u->is_active ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Disabled</span>' !!}</td>
        <td class="small">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
        <td class="text-end"><a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
            <form class="d-inline" method="POST" action="{{ route('admin.users.destroy', $u) }}" data-confirm="Delete {{ $u->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
@endforeach
</tbody></table></div>
@endsection

@extends('layouts.app')
@section('title', 'Manage Roles')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('manage-team.index') }}">Team</a><span class="sep">/</span>
    <span class="current">Roles & Permissions</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-shield-lock" style="color:var(--green-600);opacity:.6"></i> Roles & Permissions</h4>
        <div class="heading-subtitle">Define roles and assign granular permissions to team members</div>
    </div>
    <div class="page-heading-actions">
        <a href="{{ route('manage-team.create-role') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create Role</a>
    </div>
</div>

<div class="card">
    <div class="card-header"><span>All Roles</span></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Role</th><th>Permissions</th><th></th></tr></thead>
        <tbody>
            @foreach($roles as $role)
            <tr>
                <td class="fw-semibold">{{ $role->name }}</td>
                <td><span class="badge badge-green badge-pill">{{ $role->permissions->count() }} permissions</span></td>
                <td><a href="{{ route('manage-team.get-role', $role->id) }}" class="btn btn-outline-primary btn-action" data-bs-toggle="tooltip" title="Edit role"><i class="bi bi-pencil"></i></a></td>
            </tr>
            @endforeach
        </tbody>
    </table></div></div>
    @if($roles->hasPages())<div class="card-footer">{{ $roles->links() }}</div>@endif
</div>
@endsection

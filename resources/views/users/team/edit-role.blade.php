@extends('layouts.app')
@section('title', 'Edit Role: ' . $role->name)
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('manage-team.index') }}">Team</a><span class="sep">/</span>
    <a href="{{ route('manage-team.manage-roles') }}">Roles</a><span class="sep">/</span>
    <span class="current">{{ $role->name }}</span>
@endsection

@section('content')
<a href="{{ route('manage-team.manage-roles') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Roles</a>

<div class="card">
    <div class="card-header"><span><i class="bi bi-pencil me-2"></i> Edit Role: {{ $role->name }}</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('manage-team.update-role') }}">@csrf @method('PUT')
            <input type="hidden" name="role_id" value="{{ $role->id }}">
            <div class="mb-4">
                <label class="form-label">Role Name</label>
                <input type="text" name="name" class="form-control form-control-sm" value="{{ $role->name }}" required style="max-width:340px">
            </div>
            <div class="mb-4">
                <label class="form-label">Permissions</label>
                @foreach($permissions as $category => $perms)
                <div class="mb-3 p-3 rounded-3" style="background:#faf8f2;border:1px solid var(--border-light)">
                    <h6 style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:10px">{{ $category }}</h6>
                    <div class="row g-2">
                        @foreach($perms as $perm)
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->id }}" id="perm_{{ $perm->id }}" {{ in_array($perm->id, $rolePermissions) ? 'checked' : '' }}>
                                <label class="form-check-label" for="perm_{{ $perm->id }}">{{ $perm->name }}</label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Update Role</button>
        </form>
    </div>
</div>
@endsection

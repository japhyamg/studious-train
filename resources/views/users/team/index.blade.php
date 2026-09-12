@extends('layouts.app')
@section('title', 'Team Management')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">Team</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-people" style="color:var(--green-600);opacity:.6"></i> Team Management</h4>
        <div class="heading-subtitle">Manage your team members and their roles</div>
    </div>
    <div class="page-heading-actions">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMemberModal"><i class="bi bi-person-plus me-1"></i>Add Team Member</button>
    </div>
</div>

{{-- Team List (full width) --}}
<div class="card">
    <div class="card-header">
        <span><i class="bi bi-people me-2"></i> Team Members ({{ count($team) }})</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($team as $user)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-circle" style="width:30px;height:30px;font-size:10px">{{ $user->initials }}</div>
                            <span class="fw-medium">{{ $user->name }}</span>
                        </div>
                    </td>
                    <td style="font-size:12px">{{ $user->email }}</td>
                    <td>
                        @foreach($user->roles as $role)
                        <span class="badge badge-green">{{ ucfirst($role->name) }}</span>
                        @endforeach
                    </td>
                    <td style="font-size:12px;color:var(--text-muted)">{{ $user->created_at->format('M d, Y') }}</td>
                    <td>
                        @if($user->email_verified_at || true)
                            <span class="badge bg-success" style="font-size:10px">Active</span>
                        @else
                            <span class="badge bg-secondary" style="font-size:10px">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            {{-- Edit --}}
                            <button class="btn btn-outline-primary btn-action" data-bs-toggle="modal" data-bs-target="#editMemberModal"
                                data-id="{{ $user->id }}" data-name="{{ $user->name }}" data-email="{{ $user->email }}"
                                data-roles="{{ $user->roles->pluck('name')->implode(',') }}" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            {{-- Delete --}}
                            @if($user->id !== auth()->id())
                            <button class="btn btn-outline-danger btn-action" onclick="deleteMember({{ $user->id }}, '{{ $user->name }}')" title="Remove">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-people fs-3 d-block mb-2"></i>No team members yet.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
</div>

{{-- Add Member Modal --}}
<div class="modal fade" id="addMemberModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('manage-team.add') }}">
        @csrf
        <div class="modal-header"><h6 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Team Member</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. John Doe">
            </div>
            <div class="mb-3">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control form-control-sm" required placeholder="e.g. john@company.com">
            </div>
            <div class="mb-3">
                <label class="form-label">Role(s) *</label>
                @foreach($roles as $role)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role }}" id="role_{{ $role }}">
                    <label class="form-check-label" for="role_{{ $role }}" style="font-size:12.5px">{{ ucfirst($role) }}</label>
                </div>
                @endforeach
            </div>
            <p class="text-muted" style="font-size:11px"><i class="bi bi-info-circle me-1"></i>Default password: <code>password</code>. User should change on first login.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-person-plus me-1"></i>Add Member</button>
        </div>
    </form>
</div></div></div>

{{-- Edit Member Modal --}}
<div class="modal fade" id="editMemberModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('manage-team.update') }}">@csrf @method('PUT')
        <div class="modal-header"><h6 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Team Member</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="mb-3">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" id="edit_user_name" class="form-control form-control-sm" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="email" id="edit_user_email" class="form-control form-control-sm" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Role(s) *</label>
                @foreach($roles as $role)
                <div class="form-check">
                    <input class="form-check-input edit-role-check" type="checkbox" name="roles[]" value="{{ $role }}" id="edit_role_{{ $role }}">
                    <label class="form-check-label" for="edit_role_{{ $role }}" style="font-size:12.5px">{{ ucfirst($role) }}</label>
                </div>
                @endforeach
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-primary">Update Member</button>
        </div>
    </form>
</div></div></div>
@endsection

@push('scripts')
<script>
// Edit modal population
document.getElementById('editMemberModal')?.addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('edit_user_id').value = b.dataset.id;
    document.getElementById('edit_user_name').value = b.dataset.name;
    document.getElementById('edit_user_email').value = b.dataset.email;

    const userRoles = (b.dataset.roles || '').split(',');
    document.querySelectorAll('.edit-role-check').forEach(cb => {
        cb.checked = userRoles.includes(cb.value);
    });
});

// Delete member
function deleteMember(id, name) {
    if (!confirm('Remove ' + name + ' from the team?')) return;
    fetch('{{ route("manage-team.delete") }}', {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    }).then(r => r.json()).then(d => {
        if (d.status === 'success') location.reload();
        else alert(d.message || 'Failed to remove member.');
    });
}
</script>
@endpush

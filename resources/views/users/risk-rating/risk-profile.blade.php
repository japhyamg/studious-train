@extends('layouts.app')
@section('title', 'Risk Profiles')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('risk-rating.index') }}">Customer Risk Rating</a><span class="sep">/</span>
    <span class="current">Risk Profiles</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-file-earmark-bar-graph" style="color:var(--green-600);opacity:.6"></i> Risk Profiles</h4>
        <div class="heading-subtitle">Define data point profiles used to generate customer risk scores</div>
    </div>
    <div class="page-heading-actions">
        @can('risk-profile-create')
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i> Add Profile</button>
        @endcan
    </div>
</div>

@include('users.risk-rating.partials.nav')

{{-- Profile Cards --}}
<div class="row g-3">
    @forelse($riskProfiles as $profile)
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-bold">{{ $profile->name }}</span>
                <div class="d-flex align-items-center gap-1">
                    @if($profile->isDefault)
                    <span class="badge badge-green badge-pill" style="font-size:9px">Default</span>
                    @endif
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light btn-icon" data-bs-toggle="dropdown" style="width:28px;height:28px"><i class="bi bi-three-dots-vertical" style="font-size:12px"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @can('risk-profile-update')
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="editProfile('{{ $profile->id }}')"><i class="bi bi-pencil me-2" style="opacity:.5"></i>Edit</a></li>
                            @endcan
                            @can('risk-profile-delete')
                            <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteProfile('{{ $profile->id }}')"><i class="bi bi-trash me-2" style="opacity:.5"></i>Delete</a></li>
                            @endcan
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body">
                {{-- Data Points --}}
                <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Data Points</div>
                <div class="mb-3">
                    @foreach($profile->data_points ?? [] as $dp)
                    <span class="badge" style="background:var(--green-50);color:var(--green-800);font-size:10px;border:1px solid var(--green-100);margin:1px">{{ ucwords(str_replace('_',' ',$dp)) }}</span>
                    @endforeach
                </div>

                {{-- Status badges --}}
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span style="font-size:11px;color:var(--text-muted);font-weight:500">Template:</span>
                        @if($profile->template_uploaded)
                        <span class="badge" style="background:#f0fdf4;color:#16a34a;font-size:9px;border:1px solid #bbf7d0">Ready</span>
                        @else
                        <span class="badge" style="background:#fef3c7;color:#b45309;font-size:9px;border:1px solid #fde68a">Pending</span>
                        @endif
                    </div>
                    <div>
                        <span style="font-size:11px;color:var(--text-muted);font-weight:500">Status:</span>
                        @if($profile->status)
                        <span class="status-dot active" style="font-size:10px">Active</span>
                        @else
                        <span class="status-dot muted" style="font-size:10px">Inactive</span>
                        @endif
                    </div>
                </div>

                <div class="divider" style="margin:12px 0"></div>

                {{-- Template Actions --}}
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex gap-2">
                        <a href="{{ route('risk-rating.manage-risk-profile.download-template', $profile->id) }}" class="btn btn-outline-primary btn-sm flex-fill">
                            <i class="bi bi-download me-1"></i> Download Template
                        </a>
                        @can('risk-profile-view')
                        <a href="{{ route('risk-rating.manage-risk-profile.show', $profile->id) }}" class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip" title="View details">
                            <i class="bi bi-eye"></i>
                        </a>
                        @endcan
                    </div>

                    {{-- Upload Template --}}
                    <form method="POST" action="{{ route('risk-rating.manage-risk-profile.import-template') }}" enctype="multipart/form-data" class="d-flex gap-2">
                        @csrf
                        <input type="hidden" name="profile_id" value="{{ $profile->id }}">
                        <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.xls,.csv" required style="font-size:11px">
                        <button type="submit" class="btn btn-primary btn-sm" style="white-space:nowrap">
                            <i class="bi bi-upload me-1"></i> Upload
                        </button>
                    </form>

                    @if($profile->template_uploaded)
                    <button class="btn btn-outline-danger btn-sm" onclick="deleteTemplate('{{ $profile->id }}')" style="font-size:11px">
                        <i class="bi bi-trash me-1"></i> Remove Template Data
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-file-earmark-bar-graph"></i></div>
                    <div class="empty-state-title">No risk profiles</div>
                    <div class="empty-state-text">Create a risk profile to define data points for customer risk scoring.</div>
                </div>
            </div>
        </div>
    </div>
    @endforelse
</div>

@if($riskProfiles->hasPages())
<div class="mt-3">{{ $riskProfiles->links() }}</div>
@endif

{{-- Add Modal --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <form action="{{ route('risk-rating.manage-risk-profile.store') }}" method="POST">
        @csrf
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Create Risk Profile</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Profile Name *</label>
                    <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. Standard Customer Profile">
                </div>
                <div class="mb-3">
                    <label class="form-label">Select Data Points *</label>
                    <div class="p-3 rounded-3" style="background:#faf8f2;border:1px solid var(--border-light);max-height:300px;overflow-y:auto">
                        @foreach($datapoints as $dp)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="data_points[]" value="{{ $dp }}" id="dp_{{ $dp }}">
                            <label class="form-check-label" for="dp_{{ $dp }}">{{ ucwords(str_replace('_',' ',$dp)) }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Create Profile</button>
            </div>
        </div></div>
    </form>
</div>

{{-- Edit Modal --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <form action="{{ route('risk-rating.manage-risk-profile.update') }}" method="POST">
        @method('PUT') @csrf
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Risk Profile</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_profile_id" name="profile_id">
                <div class="mb-3">
                    <label class="form-label">Profile Name *</label>
                    <input type="text" id="edit_name" name="name" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Select Data Points *</label>
                    <div class="p-3 rounded-3" style="background:#faf8f2;border:1px solid var(--border-light);max-height:300px;overflow-y:auto">
                        @foreach($datapoints as $dp)
                        <div class="form-check mb-2">
                            <input class="form-check-input edit-dp-check" type="checkbox" name="data_points[]" value="{{ $dp }}" id="edit_dp_{{ $dp }}">
                            <label class="form-check-label" for="edit_dp_{{ $dp }}">{{ ucwords(str_replace('_',' ',$dp)) }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Update Profile</button>
            </div>
        </div></div>
    </form>
</div>
@endsection

@push('scripts')
<script>
const ajaxUrl = "{{ route('risk-rating.manage-risk-profile.ajax') }}";
const deleteUrl = "{{ route('risk-rating.manage-risk-profile.destroy') }}";
const deleteTemplateUrl = "{{ route('risk-rating.manage-risk-profile.delete-template') }}";
const csrfToken = "{{ csrf_token() }}";

function editProfile(id) {
    $.get(ajaxUrl, { id: id }, function(response) {
        if (response.status === 'success') {
            const profile = response.data;
            $('#edit_profile_id').val(profile.id);
            $('#edit_name').val(profile.name);
            // Check matching data points
            $('.edit-dp-check').prop('checked', false);
            if (profile.data_points) {
                const points = typeof profile.data_points === 'string' ? JSON.parse(profile.data_points) : profile.data_points;
                points.forEach(function(dp) {
                    $('#edit_dp_' + dp).prop('checked', true);
                });
            }
            $('#editModal').modal('show');
        } else {
            alert('Failed to load profile data.');
        }
    });
}

function deleteProfile(id) {
    if (!confirm('Delete this risk profile and all its data?')) return;
    $.ajax({
        url: deleteUrl,
        method: 'DELETE',
        data: { _token: csrfToken, id: id },
        success: function(response) {
            if (response.status === 'success') location.reload();
            else alert('Failed to delete.');
        }
    });
}

function deleteTemplate(id) {
    if (!confirm('Remove all template data for this profile? The profile will become inactive.')) return;
    $.ajax({
        url: deleteTemplateUrl,
        method: 'DELETE',
        data: { _token: csrfToken, id: id },
        success: function(response) {
            if (response.status === 'success') location.reload();
            else alert('Failed to delete template data.');
        }
    });
}
</script>
@endpush

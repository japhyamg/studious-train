@extends('layouts.app')
@section('title', 'Risk Levels')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('risk-rating.index') }}">Customer Risk Rating</a><span class="sep">/</span>
    <span class="current">Risk Levels</span>
@endsection
@section('page-title', 'Risk Levels')
@section('content')
@include('users.risk-rating.partials.nav')
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Add Risk Level</div>
            <div class="card-body">
                <form method="POST" action="{{ route('risk-rating.manage-risk-level.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Label *</label>
                        <input type="text" name="label" class="form-control form-control-sm" required placeholder="e.g. Low, Medium, High">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Min Score *</label>
                            <input type="number" name="min_score" class="form-control form-control-sm" required step="0.01" placeholder="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Max Score *</label>
                            <input type="number" name="max_score" class="form-control form-control-sm" required step="0.01" placeholder="33">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Diligence Type *</label>
                        <select name="diligence_type" class="form-select form-select-sm" required>
                            <option value="CDD">CDD — Customer Due Diligence</option>
                            <option value="EDD">EDD — Enhanced Due Diligence</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">CDD/EDD Review Schedule (days) *</label>
                        <input type="number" name="review_schedule_days" class="form-control form-control-sm" required min="1" placeholder="e.g. 30, 90, 180">
                        <div class="form-text" style="font-size:11px;color:var(--text-muted)">
                            How many days after onboarding (or last review) should this customer's profile be triggered for review.
                            <br>Example: <strong>30</strong> = reviewed every 30 days. A customer onboarded Jan 15 with this level will be due for review on Feb 14.
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-check me-1"></i>Add Risk Level</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <span>Configured Risk Levels</span>
                <div class="d-flex gap-2">
                    <a href="{{ route('risk-rating.risk-level-changes.export') }}" class="btn btn-sm btn-outline-success" style="font-size:11px"><i class="bi bi-download me-1"></i>Risk Level Changes</a>
                    <a href="{{ route('risk-rating.reviews-due') }}" class="btn btn-sm btn-outline-primary" style="font-size:11px"><i class="bi bi-calendar-check me-1"></i>View Reviews Due</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive"><table class="table table-hover mb-0">
                    <thead><tr><th>Label</th><th>Score Range</th><th>Diligence</th><th>Review Cycle</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse($levels as $l)
                        <tr>
                            <td>
                                <span class="fw-medium">{{ $l->label }}</span>
                            </td>
                            <td>{{ $l->min_score }} — {{ $l->max_score }}</td>
                            <td>
                                <span class="badge {{ $l->getDiligenceLabel() == 'EDD' ? 'bg-warning text-dark' : 'bg-success bg-opacity-75 text-white' }}" style="font-size:10px">
                                    {{ $l->getDiligenceLabel() }}
                                </span>
                            </td>
                            <td>
                                @if($l->review_schedule_days)
                                    <span style="font-size:12px">Every <strong>{{ $l->review_schedule_days }}</strong> days</span>
                                @else
                                    <span class="text-muted" style="font-size:12px">Not set</span>
                                @endif
                            </td>
                            <td>
                                <button class="btn btn-outline-primary btn-action" data-bs-toggle="modal" data-bs-target="#editLevelModal"
                                    data-id="{{ $l->id }}" data-label="{{ $l->label }}" data-min="{{ $l->min_score }}"
                                    data-max="{{ $l->max_score }}" data-days="{{ $l->review_schedule_days }}"
                                    data-diligence="{{ $l->diligence_type }}"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-outline-danger btn-action" onclick="deleteLevel({{ $l->id }})"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center py-4 text-muted">No risk levels configured yet.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</div>

{{-- Edit Modal --}}
<div class="modal fade" id="editLevelModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('risk-rating.manage-risk-level.update') }}">@csrf @method('PUT')
        <div class="modal-header"><h6 class="modal-title">Edit Risk Level</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="label_id" id="edit_level_id">
            <div class="mb-3"><label class="form-label">Label</label><input type="text" name="label" id="edit_level_label" class="form-control form-control-sm" required></div>
            <div class="row g-2 mb-3">
                <div class="col-6"><label class="form-label">Min Score</label><input type="number" name="min_score" id="edit_level_min" class="form-control form-control-sm" required step="0.01"></div>
                <div class="col-6"><label class="form-label">Max Score</label><input type="number" name="max_score" id="edit_level_max" class="form-control form-control-sm" required step="0.01"></div>
            </div>
            <div class="mb-3"><label class="form-label">Diligence Type</label>
                <select name="diligence_type" id="edit_level_diligence" class="form-select form-select-sm" required>
                    <option value="CDD">CDD</option><option value="EDD">EDD</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">CDD/EDD Review Schedule (days)</label>
                <input type="number" name="review_schedule_days" id="edit_level_days" class="form-control form-control-sm" min="1" placeholder="e.g. 30">
                <div class="form-text" style="font-size:11px">Number of days between reviews for customers at this risk level.</div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary">Update</button></div>
    </form>
</div></div></div>
@endsection

@push('scripts')
<script>
document.getElementById('editLevelModal')?.addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('edit_level_id').value = b.dataset.id;
    document.getElementById('edit_level_label').value = b.dataset.label;
    document.getElementById('edit_level_min').value = b.dataset.min;
    document.getElementById('edit_level_max').value = b.dataset.max;
    document.getElementById('edit_level_days').value = b.dataset.days || '';
    document.getElementById('edit_level_diligence').value = b.dataset.diligence || 'CDD';
});
function deleteLevel(id){if(!confirm('Delete this risk level?'))return;fetch('{{ route("risk-rating.manage-risk-level.destroy") }}',{method:'DELETE',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'},body:JSON.stringify({id:id})}).then(r=>r.json()).then(d=>{if(d.status==='success')location.reload();});}
</script>
@endpush

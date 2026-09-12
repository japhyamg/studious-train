@extends('layouts.app')
@section('title', 'Internal Watchlist')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">Internal Watchlist</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-exclamation-diamond" style="color:var(--green-600);opacity:.6"></i> Internal Watchlist</h4>
        <div class="heading-subtitle">Manage your institution's internal watch list of flagged individuals</div>
    </div>
</div>

<div class="row g-4">
    {{-- Add / Upload --}}
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><span><i class="bi bi-plus-circle me-2"></i> Add to Watchlist</span></div>
            <div class="card-body">
                <form method="POST" action="{{ route('watch-list.internal.add') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">First Name *</label><input type="text" name="customer_first_name" class="form-control form-control-sm" required></div>
                    <div class="mb-3"><label class="form-label">Middle Name</label><input type="text" name="customer_middle_name" class="form-control form-control-sm"></div>
                    <div class="mb-3"><label class="form-label">Last Name *</label><input type="text" name="customer_last_name" class="form-control form-control-sm" required></div>
                    <div class="mb-3"><label class="form-label">Account No *</label><input type="text" name="customer_account_no" class="form-control form-control-sm" required></div>
                    <div class="mb-3"><label class="form-label">BVN *</label><input type="text" name="customer_bvn" class="form-control form-control-sm" required maxlength="11"></div>
                    <div class="mb-3"><label class="form-label">NIN *</label><input type="text" name="customer_nin" class="form-control form-control-sm" required maxlength="11"></div>
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Add Entry</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span><i class="bi bi-upload me-2"></i> Import CSV/Excel</span></div>
            <div class="card-body">
                <form method="POST" action="{{ route('watch-list.internal.upload') ?? '#' }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="type" value="watchlist">
                    <div class="mb-3"><input type="file" name="file" class="form-control form-control-sm" accept=".csv,.xlsx,.xls" required></div>
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-upload me-1"></i>Upload</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Listing --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><span>Watchlist Entries ({{ $watchlist->total() }})</span></div>
            <div class="card-body p-0">
                <div class="table-responsive"><table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Account No</th><th>BVN</th><th>NIN</th><th>Added</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse($watchlist as $w)
                        <tr>
                            <td class="fw-medium">{{ $w->name }}</td>
                            <td class="font-monospace" style="font-size:12px">{{ $w->account_no }}</td>
                            <td style="font-size:12px;color:var(--text-muted)">{{ $w->bvn }}</td>
                            <td style="font-size:12px;color:var(--text-muted)">{{ $w->nin }}</td>
                            <td style="font-size:12px;color:var(--text-muted)">{{ $w->created_at->format('M d, Y') }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-outline-primary btn-action" data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="{{ $w->id }}" data-fn="{{ $w->first_name }}" data-mn="{{ $w->middle_name }}" data-ln="{{ $w->last_name }}"
                                        data-acc="{{ $w->account_no }}" data-bvn="{{ $w->bvn }}" data-nin="{{ $w->nin }}"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-outline-danger btn-action" onclick="deleteItem({{ $w->id }})"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="bi bi-exclamation-diamond"></i></div>
                                    <div class="empty-state-title">No entries</div>
                                    <div class="empty-state-text">Add individuals to the internal watchlist using the form or import a file.</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
            @if($watchlist->hasPages())<div class="card-footer">{{ $watchlist->links() }}</div>@endif
        </div>
    </div>
</div>

{{-- Edit Modal --}}
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('watch-list.internal.update') }}">@csrf @method('PUT')
        <div class="modal-header"><h6 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Entry</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="customer_id" id="edit_id">
            <div class="mb-3"><label class="form-label">First Name</label><input type="text" name="customer_first_name" id="edit_fn" class="form-control form-control-sm" required></div>
            <div class="mb-3"><label class="form-label">Middle Name</label><input type="text" name="customer_middle_name" id="edit_mn" class="form-control form-control-sm"></div>
            <div class="mb-3"><label class="form-label">Last Name</label><input type="text" name="customer_last_name" id="edit_ln" class="form-control form-control-sm" required></div>
            <div class="mb-3"><label class="form-label">Account No</label><input type="text" name="customer_account_no" id="edit_acc" class="form-control form-control-sm" required></div>
            <div class="mb-3"><label class="form-label">BVN</label><input type="text" name="customer_bvn" id="edit_bvn" class="form-control form-control-sm" required></div>
            <div class="mb-3"><label class="form-label">NIN</label><input type="text" name="customer_nin" id="edit_nin" class="form-control form-control-sm" required></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary">Update</button></div>
    </form>
</div></div></div>
@endsection

@push('scripts')
<script>
document.getElementById('editModal')?.addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('edit_id').value = b.dataset.id;
    document.getElementById('edit_fn').value = b.dataset.fn;
    document.getElementById('edit_mn').value = b.dataset.mn;
    document.getElementById('edit_ln').value = b.dataset.ln;
    document.getElementById('edit_acc').value = b.dataset.acc;
    document.getElementById('edit_bvn').value = b.dataset.bvn;
    document.getElementById('edit_nin').value = b.dataset.nin;
});
function deleteItem(id) {
    if (!confirm('Delete this entry?')) return;
    fetch('{{ route("watch-list.internal.destory") }}', {method:'DELETE', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'}, body:JSON.stringify({id:id})})
    .then(r=>r.json()).then(d=>{if(d.status==='success')location.reload();});
}
</script>
@endpush

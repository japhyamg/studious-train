@extends('layouts.app')
@section('title', 'NIBSS Watchlist')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">NIBSS Watchlist</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-shield-exclamation" style="color:var(--green-600);opacity:.6"></i> NIBSS Watchlist</h4>
        <div class="heading-subtitle">Nigeria Inter-Bank Settlement System flagged identities</div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><span><i class="bi bi-plus-circle me-2"></i> Add to NIBSS Watchlist</span></div>
            <div class="card-body">
                <form method="POST" action="{{ route('watch-list.nibss.add') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">BVN *</label><input type="text" name="customer_bvn" class="form-control form-control-sm" required maxlength="11"></div>
                    <div class="mb-3"><label class="form-label">First Name *</label><input type="text" name="customer_first_name" class="form-control form-control-sm" required></div>
                    <div class="mb-3"><label class="form-label">Middle Name</label><input type="text" name="customer_middle_name" class="form-control form-control-sm"></div>
                    <div class="mb-3"><label class="form-label">Last Name *</label><input type="text" name="customer_last_name" class="form-control form-control-sm" required></div>
                    <div class="mb-3"><label class="form-label">Category *</label>
                        <select name="customer_category" class="form-select form-select-sm" required>
                            <option value="">Select...</option><option value="1">Category 1 - Fraud</option><option value="2">Category 2 - Forgery</option><option value="3">Category 3 - Theft</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Reason *</label><textarea name="customer_reason" class="form-control form-control-sm" rows="2" required></textarea></div>
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Add Entry</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span><i class="bi bi-upload me-2"></i> Import NIBSS File</span></div>
            <div class="card-body">
                <form method="POST" action="{{ route('watch-list.nibss.upload') ?? '#' }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="type" value="watchlist">
                    <div class="mb-3"><input type="file" name="file" class="form-control form-control-sm" accept=".csv,.xlsx,.xls" required></div>
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-upload me-1"></i>Upload</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><span>NIBSS Watchlist ({{ $watchlist->total() }})</span></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0">
                <thead><tr><th>Name</th><th>BVN</th><th>Category</th><th>Reason</th><th>Bank</th><th>Date</th><th></th></tr></thead>
                <tbody>
                    @forelse($watchlist as $w)
                    <tr>
                        <td class="fw-medium">{{ $w->name }}</td>
                        <td class="font-monospace" style="font-size:12px">{{ $w->bvn }}</td>
                        <td><span class="badge" style="background:#fef2f2;color:#dc2626;font-size:10px;border:1px solid #fecaca">{{ $w->category }}</span></td>
                        <td style="font-size:12px;color:var(--text-muted);max-width:200px" class="text-truncate">{{ $w->reason }}</td>
                        <td style="font-size:12px">{{ $w->requesting_bank ?? '---' }}</td>
                        <td style="font-size:12px;color:var(--text-muted)">{{ $w->watchlisted_date ?? $w->created_at->format('M d, Y') }}</td>
                        <td><button class="btn btn-outline-danger btn-action" onclick="deleteNibss({{ $w->id }})"><i class="bi bi-trash"></i></button></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="bi bi-shield-exclamation"></i></div>
                                <div class="empty-state-title">No entries</div>
                                <div class="empty-state-text">NIBSS watchlist entries can be added manually or imported from a file.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table></div></div>
            @if($watchlist->hasPages())<div class="card-footer">{{ $watchlist->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
function deleteNibss(id){if(!confirm('Delete?'))return;fetch('{{ route("watch-list.nibss.destroy") }}',{method:'DELETE',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'},body:JSON.stringify({id:id})}).then(r=>r.json()).then(d=>{if(d.status==='success')location.reload();});}
</script>
@endpush

@extends('layouts.app')
@section('title', 'Transaction Rules')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">Transaction Rules</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-shield-check" style="color:var(--green-600);opacity:.6"></i> Transaction Rules</h4>
        <div class="heading-subtitle">Configure detection rules for automated transaction monitoring</div>
    </div>
    <div class="page-heading-actions">
        @can('rule-create')
        <a href="{{ route('transaction-rules.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create New Rule</a>
        @endcan
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span>All Rules ({{ $rules->count() }})</span>
    </div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Name</th><th>Description</th><th>Conditions</th><th>Run Time</th><th>Report</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($rules as $rule)
            @php $conditions = is_array($rule->search_attributes) ? $rule->search_attributes : json_decode($rule->search_attributes, true); @endphp
            <tr>
                <td class="fw-semibold">{{ $rule->name }}</td>
                <td style="font-size:12px;max-width:250px;color:var(--text-muted)">{{ \Illuminate\Support\Str::limit($rule->description, 80) }}</td>
                <td>
                    @if($conditions)
                        @foreach($conditions as $c)
                        <span class="badge" style="background:#faf8f2;color:var(--text-secondary);font-size:9.5px;margin:1px 0;display:inline-block;border:1px solid var(--border-light)">
                            {{ $c['attribute'] ?? '?' }} {{ $c['action'] ?? '' }} {{ is_array($c['value'] ?? '') ? json_encode($c['value']) : ($c['value'] ?? '') }}
                        </span><br>
                        @endforeach
                    @else
                        <span style="font-size:11px;color:var(--text-muted)">No conditions</span>
                    @endif
                </td>
                <td>
                    @if($rule->run_time == 'Instantly')
                    <span class="badge" style="background:#f0fdf4;color:#16a34a;font-size:10px;border:1px solid #bbf7d0">Instantly</span>
                    @else
                    <span class="badge" style="background:#fef3c7;color:#b45309;font-size:10px;border:1px solid #fde68a">{{ $rule->run_time }}</span>
                    @endif
                </td>
                <td><span class="badge badge-green" style="font-size:10px">{{ $rule->report_type ?? 'STR' }}</span></td>
                <td>
                    @if($rule->status)
                    <span class="status-dot active" style="font-size:11px">Active</span>
                    @else
                    <span class="status-dot muted" style="font-size:11px">Inactive</span>
                    @endif
                </td>
                <td>
                    <div class="d-flex gap-1">
                        @can('rule-edit-value')<a href="{{ route('transaction-rules.edit', $rule->id) }}" class="btn btn-outline-primary btn-action" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>@endcan
                        @can('rule-delete')<button class="btn btn-outline-danger btn-action" data-bs-toggle="tooltip" title="Delete" onclick="deleteRule({{ $rule->id }})"><i class="bi bi-trash"></i></button>@endcan
                        <button class="btn btn-action {{ $rule->status ? 'btn-outline-warning' : 'btn-outline-success' }}" data-bs-toggle="tooltip" title="{{ $rule->status ? 'Deactivate' : 'Activate' }}" onclick="toggleRule({{ $rule->id }}, '{{ $rule->status ? 'deactivate' : 'activate' }}')">
                            <i class="bi bi-{{ $rule->status ? 'pause' : 'play' }}"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-shield-check"></i></div>
                        <div class="empty-state-title">No rules configured</div>
                        <div class="empty-state-text">Create transaction rules to start monitoring for suspicious activity.</div>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table></div></div>
</div>
@endsection

@push('scripts')
<script>
function deleteRule(id){if(!confirm('Delete this rule?'))return;fetch('/transaction-rules/'+id,{method:'DELETE',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}).then(r=>r.json()).then(d=>{if(d.status==='success')location.reload();});}
function toggleRule(id,action){fetch('/transaction-rules/'+id+'/'+action,{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}).then(r=>r.json()).then(d=>{if(d.status==='success')location.reload();});}
</script>
@endpush

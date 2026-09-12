@extends('layouts.app')
@section('title', 'Peer Group Analysis')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">Peer Groups</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-diagram-3" style="color:var(--green-600);opacity:.6"></i> Peer Group Analysis</h4>
        <div class="heading-subtitle">IQR-based statistical outlier detection across customer peer groups</div>
    </div>
    <div class="page-heading-actions">
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#configModal"><i class="bi bi-gear me-1"></i>Configuration</button>
        <form method="POST" action="{{ route('peer-grouping.recompute') }}" onsubmit="return startRecompute(this)">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-calculator me-1"></i>Recompute Thresholds</button>
        </form>
    </div>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Flagged Outliers</div>
            <div class="kpi-value">{{ number_format($stats['flagged']) }}</div>
            <div class="kpi-sub">transactions above their peer-group limit</div>
            <div class="kpi-icon" style="background:#fef2f2;color:#dc2626"><i class="bi bi-exclamation-triangle"></i></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Peer Group Fields</div>
            <div class="kpi-value">{{ $stats['fields'] }}</div>
            <div class="kpi-sub">{{ implode(', ', array_map('ucwords', array_map(fn($f) => str_replace('_', ' ', $f), $selectedFields))) ?: 'none selected' }}</div>
            <div class="kpi-icon" style="background:var(--green-50);color:var(--green-600)"><i class="bi bi-sliders"></i></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Distinct Groups</div>
            <div class="kpi-value">{{ number_format($stats['groups']) }}</div>
            <div class="kpi-sub">group values seen across outliers</div>
            <div class="kpi-icon" style="background:#eff6ff;color:#2563eb"><i class="bi bi-diagram-3"></i></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Last Recompute</div>
            <div style="font-size:21px;font-weight:800;letter-spacing:-.5px;color:var(--text-primary);line-height:1.1">{{ $stats['last_recompute'] ? \Carbon\Carbon::parse($stats['last_recompute'])->diffForHumans() : 'Never' }}</div>
            <div class="kpi-sub">{{ $stats['last_recompute'] ? \Carbon\Carbon::parse($stats['last_recompute'])->format('M d, Y · H:i') : 'recompute thresholds to begin' }}</div>
            <div class="kpi-icon" style="background:#fffbeb;color:#b45309"><i class="bi bi-clock-history"></i></div>
        </div>
    </div>
</div>

{{-- How it works (accordion) --}}
<div class="accordion mb-4" id="howItWorksAccordion">
    <div class="accordion-item" style="border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;background:var(--card-bg);box-shadow:var(--shadow-sm)">
        <h2 class="accordion-header" id="howItWorksHeading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#howItWorksCollapse" aria-expanded="false" aria-controls="howItWorksCollapse" style="background:transparent;font-weight:600;font-size:14px;color:var(--text-primary);box-shadow:none">
                <i class="bi bi-info-circle me-2" style="color:var(--green-700)"></i> How Peer Grouping Works
            </button>
        </h2>
        <div id="howItWorksCollapse" class="accordion-collapse collapse" aria-labelledby="howItWorksHeading" data-bs-parent="#howItWorksAccordion">
            <div class="accordion-body" style="padding:18px 22px;border-top:1px solid var(--border-light)">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div style="font-size:13px;color:var(--text-secondary);line-height:1.7">
                            <p class="mb-2">Customers are segmented into <strong>peer groups</strong> using the fields you select
                            (for example <em>gender</em>, <em>customer type</em> or <em>tier level</em>). For each group, the system
                            studies the <strong>transaction amounts</strong> that group normally produces and learns a
                            <strong>typical range</strong>.</p>
                            <p class="mb-0">A transaction is flagged as an <strong>outlier</strong> when its amount sits far above
                            that range — i.e. above the group's <strong>upper threshold</strong>.</p>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="p-3 rounded-3" style="background:#faf8f2;border:1px solid var(--border-light);font-size:12px;color:var(--text-secondary)">
                            <div style="font-weight:700;color:var(--text-primary);margin-bottom:8px"><i class="bi bi-braces me-1"></i>Threshold formula (Tukey's fences)</div>
                            <div class="font-monospace" style="font-size:12px;line-height:1.8">
                                Q1 = 25th percentile of amounts<br>
                                Q3 = 75th percentile of amounts<br>
                                IQR = Q3 − Q1<br>
                                Upper threshold = Q3 + 1.5 × IQR
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Thresholds --}}
<div class="card mb-4">
    <div class="card-header">
        <span><i class="bi bi-braces me-2"></i> Current Thresholds</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Field</th><th>Group</th><th>Sample</th><th>Q1</th><th>Q3</th><th>IQR</th><th>Upper Threshold</th><th>Computed</th></tr></thead>
            <tbody>
                @php $rows = []; @endphp
                @foreach($thresholds as $field => $t)
                    @if($t && is_array($t->thresholds))
                        @foreach($t->thresholds as $group => $detail)
                            @php
                                $d = is_array($detail) ? $detail : ['upper' => $detail, 'q1' => null, 'q3' => null, 'iqr' => null, 'sample' => null];
                                $rows[] = ['field' => $field, 'group' => $group, 'd' => $d, 'computed' => $t->created_at];
                            @endphp
                        @endforeach
                    @endif
                @endforeach
                @forelse($rows as $r)
                <tr>
                    <td><span class="badge badge-green">{{ ucwords(str_replace('_',' ',$r['field'])) }}</span></td>
                    <td class="fw-medium">{{ $r['group'] ?: '—' }}</td>
                    <td class="font-monospace" style="font-size:12px">{{ $r['d']['sample'] ?? '—' }}</td>
                    <td class="font-monospace" style="font-size:12px">{{ isset($r['d']['q1']) ? moneyFormat($r['d']['q1']) : '—' }}</td>
                    <td class="font-monospace" style="font-size:12px">{{ isset($r['d']['q3']) ? moneyFormat($r['d']['q3']) : '—' }}</td>
                    <td class="font-monospace" style="font-size:12px">{{ isset($r['d']['iqr']) ? moneyFormat($r['d']['iqr']) : '—' }}</td>
                    <td><span class="badge" style="background:var(--green-700);color:#fff">{{ isset($r['d']['upper']) ? moneyFormat($r['d']['upper']) : '—' }}</span></td>
                    <td style="font-size:12px;color:var(--text-muted)">{{ \Carbon\Carbon::parse($r['computed'])->format('M d, Y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="8">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-braces"></i></div>
                        <div class="empty-state-title">No thresholds computed</div>
                        <div class="empty-state-text">Select peer-group fields above, save, then click "Recompute Thresholds" — or wait for the next transaction to trigger automatic computation.</div>
                    </div>
                </td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
</div>

{{-- Outliers --}}
<div class="card">
    <div class="card-header">
        <span><i class="bi bi-exclamation-triangle me-2"></i> Outliers</span>
        <span class="badge badge-pill" style="background:#faf8f2;color:var(--text-muted);border:1px solid var(--border-light)">{{ $outliers->total() }} records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Customer</th><th>Peer Group Field</th><th>Group</th><th>Amount</th><th>Threshold</th><th>Exceeded By</th><th>Result</th><th>Date</th></tr></thead>
            <tbody>
                @forelse($outliers as $o)
                <tr>
                    <td>
                        <div class="fw-medium" style="font-size:12.5px">{{ $o->customer?->name ?? '—' }}</div>
                        <div class="font-monospace" style="font-size:11px;color:var(--text-muted)">{{ $o->customer?->account_number ?? '—' }}</div>
                    </td>
                    <td><span class="badge badge-green">{{ ucwords(str_replace('_',' ',$o->peer_group)) }}</span></td>
                    <td class="fw-medium" style="font-size:12px">{{ $o->customer_group ?: '—' }}</td>
                    <td class="font-monospace" style="font-size:12px">{{ $o->transaction ? moneyFormat($o->transaction->amount) : '—' }}</td>
                    <td class="font-monospace" style="font-size:12px;color:var(--text-muted)">{{ moneyFormat($o->threshold) }}</td>
                    <td class="font-monospace" style="font-size:12px;{{ $o->is_flagged ? 'color:#dc2626;' : '' }}">{{ $o->exceeded_by > 0 ? moneyFormat($o->exceeded_by) : '—' }}</td>
                    <td>
                        @if($o->is_flagged)
                        <span class="badge badge-dot" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca">Outlier</span>
                        @else
                        <span class="badge badge-dot" style="background:var(--green-50);color:var(--green-700);border:1px solid var(--green-100)">Within Range</span>
                        @endif
                    </td>
                    <td style="font-size:12px;color:var(--text-muted)">{{ $o->created_at->format('M d, Y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="8">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-diagram-3"></i></div>
                        <div class="empty-state-title">No outliers yet</div>
                        <div class="empty-state-text">Outliers appear here as transactions are screened against their peer group's upper threshold.</div>
                    </div>
                </td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
    @if($outliers->hasPages())<div class="card-footer">{{ $outliers->links() }}</div>@endif
</div>

{{-- Configuration Modal --}}
<div class="modal fade" id="configModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" action="{{ route('peer-grouping.update-settings') }}">@csrf
        <div class="modal-header">
            <h6 class="modal-title"><i class="bi bi-gear me-2"></i>Peer Group Configuration</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Recompute Interval (days)</label>
                    <input type="number" name="pg_recompute_interval" class="form-control form-control-sm" style="max-width:200px" value="{{ settings('pg_recompute_interval', 7) }}">
                    <div class="form-text" style="font-size:10px">Thresholds are refreshed automatically after this many days</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Peer Group Fields</label>
                    <div class="d-flex flex-wrap gap-3 p-3 rounded-3" style="background:#faf8f2;border:1px solid var(--border-light)">
                        @forelse($availableFields as $field)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="selected_groups[]" value="{{ $field }}" id="pg_{{ $field }}" {{ in_array($field, $selectedFields) ? 'checked' : '' }}>
                            <label class="form-check-label" for="pg_{{ $field }}">{{ ucwords(str_replace('_',' ',$field)) }}</label>
                        </div>
                        @empty
                        <span style="font-size:12px;color:var(--text-muted)">No available customer fields found.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
        </div>
    </form>
</div></div></div>
@endsection

@push('scripts')
<script>
function startRecompute(form) {
    const btn = form.querySelector('button[type="submit"]');
    if (!btn) return true;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Recomputing…';
    return true;
}
</script>
@endpush

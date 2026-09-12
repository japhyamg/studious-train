@extends('layouts.app')
@section('title', 'AI Alerts')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">AI Alerts</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-cpu" style="color:#7c3aed;opacity:.7"></i> AI Anomaly Alerts</h4>
        <div class="heading-subtitle">Machine learning-detected anomalous transactions</div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span>Anomaly Alerts ({{ $alerts->total() }})</span></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Transaction</th>
                <th>Account</th>
                <th>Side</th>
                <th>Score</th>
                <th>Severity</th>
                <th>Reason</th>
                <th>Date</th>
                <th>Case</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($alerts as $a)
            @php
                // Check if a case already exists for this alert
                $existingCase = \App\Models\FlaggedCase::where('transaction_id', $a->transaction_id)
                    ->where('trigger_source', 'ai_anomaly')
                    ->first();
            @endphp
            <tr>
                <td>
                    @if($a->transaction)
                    <a href="{{ route('transactions.detail', $a->transaction_id) }}" class="fw-semibold text-decoration-none font-monospace" style="font-size:12px">
                        {{ \Illuminate\Support\Str::limit($a->transaction->transaction_ref ?? $a->transaction->ref ?? 'TXN#'.$a->transaction_id, 18) }}
                    </a>
                    @else
                    <span class="font-monospace" style="font-size:12px;color:var(--text-muted)">---</span>
                    @endif
                </td>
                <td class="font-monospace" style="font-size:12px">{{ $a->account_number }}</td>
                <td>
                    @if($a->transaction_side)
                    <span class="badge" style="background:#faf8f2;color:var(--text-secondary);font-size:9px;border:1px solid var(--border-light)">{{ ucfirst($a->transaction_side) }}</span>
                    @endif
                </td>
                <td>
                    <span class="fw-bold" style="color:{{ $a->anomaly_score > 0.8 ? '#dc2626' : ($a->anomaly_score > 0.5 ? '#b45309' : '#2563eb') }}">
                        {{ number_format($a->anomaly_score, 3) }}
                    </span>
                    @if($a->model_version)
                    <div style="font-size:10px;color:var(--text-muted)">v{{ $a->model_version }}</div>
                    @endif
                </td>
                <td>
                    @php $sevColors = ['high'=>['#fef2f2','#dc2626','#fecaca'],'medium'=>['#fef3c7','#b45309','#fde68a'],'low'=>['#eff6ff','#2563eb','#bfdbfe']]; $sc = $sevColors[strtolower($a->severity ?? 'low')] ?? $sevColors['low']; @endphp
                    <span class="badge" style="background:{{ $sc[0] }};color:{{ $sc[1] }};font-size:10px;border:1px solid {{ $sc[2] }}">{{ ucfirst($a->severity ?? 'N/A') }}</span>
                </td>
                <td style="font-size:12px;color:var(--text-muted);max-width:220px" class="text-truncate" title="{{ $a->anomaly_reason }}">{{ $a->anomaly_reason }}</td>
                <td style="font-size:12px;color:var(--text-muted);white-space:nowrap">{{ $a->created_at->format('M d, H:i') }}</td>
                <td>
                    @if($existingCase)
                    <a href="{{ route('case-management.show', $existingCase->slug) }}" class="badge badge-status-{{ $existingCase->status }}" style="font-size:9px;text-decoration:none">
                        {{ $existingCase->slug }}
                    </a>
                    @else
                    <span style="font-size:11px;color:var(--text-muted)">—</span>
                    @endif
                </td>
                <td>
                    <div class="d-flex gap-1">
                        @if($a->transaction)
                        <a href="{{ route('transactions.detail', $a->transaction_id) }}" class="btn btn-outline-primary btn-action" data-bs-toggle="tooltip" title="View transaction"><i class="bi bi-eye"></i></a>
                        @endif

                        @if(!$existingCase)
                        <form method="POST" action="{{ route('ai-alerts.flag', $a->id) }}" class="d-inline" onsubmit="return confirm('Create a case from this AI alert?')">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-action" data-bs-toggle="tooltip" title="Flag as case">
                                <i class="bi bi-flag"></i>
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9">
                    <div class="empty-state">
                        <div class="empty-state-icon" style="background:#faf5ff"><i class="bi bi-cpu" style="color:#7c3aed"></i></div>
                        <div class="empty-state-title">No AI alerts detected</div>
                        <div class="empty-state-text">AI anomaly detection alerts will appear here when the ML server identifies suspicious patterns.</div>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table></div></div>
    @if($alerts->hasPages())<div class="card-footer">{{ $alerts->links() }}</div>@endif
</div>
@endsection

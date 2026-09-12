@extends('layouts.app')
@section('title', 'Transaction Detail')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('transactions.index') }}">Transactions</a><span class="sep">/</span>
    <span class="current">{{ $transaction->transaction_ref ?? '#'.$transaction->id }}</span>
@endsection

@section('content')
<a href="{{ route('transactions.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Transactions</a>

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Transaction Info --}}
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-arrow-left-right me-2"></i> Transaction Details</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-sm-4"><div style="font-size:10px;color:var(--text-muted);font-weight:600">REFERENCE</div><div class="font-monospace" style="font-size:12px">{{ $transaction->transaction_ref ?? '—' }}</div></div>
                    <div class="col-sm-4"><div style="font-size:10px;color:var(--text-muted);font-weight:600">AMOUNT</div><div class="fw-bold" style="font-size:18px;color:var(--green-700)">{{ moneyFormat($transaction->amount) }}</div></div>
                    <div class="col-sm-4"><div style="font-size:10px;color:var(--text-muted);font-weight:600">TYPE / CHANNEL</div>
                        <span class="badge {{ strtolower($transaction->transaction_type)=='debit'?'bg-danger':'bg-success' }} bg-opacity-10" style="color:{{ strtolower($transaction->transaction_type)=='debit'?'#dc2626':'var(--green-700)' }};font-size:11px">{{ ucfirst($transaction->transaction_type) }}</span>
                        <span class="badge bg-light" style="color:var(--text-secondary);font-size:10px">{{ ucfirst($transaction->channel) }}</span>
                    </div>
                </div>

                <div class="row g-0 rounded-3 overflow-hidden" style="border:1px solid var(--border)">
                    <div class="col-md-6 p-3" style="background:#faf8f2">
                        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:6px"><i class="bi bi-arrow-up-right me-1"></i>SENDER @if(is_customer($transaction->sender_account_no))<span class="badge bg-success" style="font-size:8px">Customer</span>@endif</div>
                        <div class="fw-medium">{{ $transaction->sender_name }}</div>
                        <div class="font-monospace" style="font-size:11px;color:var(--text-muted)">{{ $transaction->sender_account_no }}</div>
                        @if($transaction->sender_bvn)<div style="font-size:10px;color:var(--text-muted)">BVN: {{ $transaction->sender_bvn }}</div>@endif
                        @if($transaction->sender_nin)<div style="font-size:10px;color:var(--text-muted)">NIN: {{ $transaction->sender_nin }}</div>@endif
                    </div>
                    <div class="col-md-6 p-3" style="background:#faf8f2;border-left:1px solid var(--border)">
                        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:6px"><i class="bi bi-arrow-down-left me-1"></i>BENEFICIARY @if(is_customer($transaction->beneficiary_account_no))<span class="badge bg-success" style="font-size:8px">Customer</span>@endif</div>
                        <div class="fw-medium">{{ $transaction->beneficiary_name }}</div>
                        <div class="font-monospace" style="font-size:11px;color:var(--text-muted)">{{ $transaction->beneficiary_account_no }}</div>
                        @if($transaction->beneficiary_bvn)<div style="font-size:10px;color:var(--text-muted)">BVN: {{ $transaction->beneficiary_bvn }}</div>@endif
                        @if($transaction->beneficiary_nin)<div style="font-size:10px;color:var(--text-muted)">NIN: {{ $transaction->beneficiary_nin }}</div>@endif
                    </div>
                </div>

                @if($transaction->narration)<div class="mt-3" style="font-size:12px"><strong>Narration:</strong> {{ $transaction->narration }}</div>@endif
                @if($transaction->location)<div style="font-size:12px"><strong>Location:</strong> {{ $transaction->location }}</div>@endif
                <div class="mt-2" style="font-size:11px;color:var(--text-muted)"><i class="bi bi-clock me-1"></i>{{ $transaction->transaction_datetime->format('M d, Y H:i:s') }}</div>
            </div>
        </div>

        {{-- Risk Scores --}}
        @if($transaction->risk_scores->count() > 0)
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-graph-up-arrow me-2"></i> Transaction Risk Scores</div>
            <div class="card-body p-0">
                @foreach($transaction->risk_scores as $score)
                <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="fw-medium" style="font-size:13px">{{ $score->customer?->name ?? 'Unknown' }}</span>
                            <span class="font-monospace ms-2" style="font-size:11px;color:var(--text-muted)">{{ $score->customer?->account_number }}</span>
                        </div>
                        <span class="badge {{ $score->risk_level === 'EXCEEDED THRESHOLD' ? 'bg-danger' : 'badge-green' }}" style="font-size:10px">{{ $score->risk_level }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div style="font-size:11px;color:var(--text-muted)">Score: <strong style="font-size:16px;color:var(--text-primary)">{{ $score->total_score }}</strong></div>
                        <div style="font-size:11px;color:var(--text-muted)">Threshold: <strong>{{ settings('risk_scoring_threshold', 100) }}</strong></div>
                    </div>
                    @if($score->meta && isset($score->meta['score_breakdown']))
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($score->meta['score_breakdown'] as $factorName => $detail)
                        <span class="badge bg-light" style="color:var(--text-secondary);font-size:9px">
                            {{ is_array($detail) ? ($detail['factor'] ?? $factorName) : $factorName }}: +{{ is_array($detail) ? ($detail['score'] ?? '?') : $detail }}
                        </span>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- AI Scores --}}
        @if($transaction->aiScores->count() > 0)
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-cpu me-2"></i> AI Anomaly Detection</div>
            <div class="card-body p-0">
                @foreach($transaction->aiScores as $ai)
                <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="font-monospace" style="font-size:12px">{{ $ai->account_number }}</span>
                            <span class="badge bg-light ms-2" style="font-size:9px;color:var(--text-muted)">{{ ucfirst($ai->transaction_side ?? '') }}</span>
                        </div>
                        <span class="badge {{ $ai->is_anomaly ? 'bg-danger' : 'bg-success' }}" style="font-size:10px">{{ $ai->is_anomaly ? 'ANOMALY' : 'NORMAL' }}</span>
                    </div>
                    <div class="mt-1" style="font-size:12px">Score: <strong>{{ number_format($ai->anomaly_score, 3) }}</strong> · Severity: <strong>{{ ucfirst($ai->severity ?? '—') }}</strong></div>
                    @if($ai->anomaly_reason)<div style="font-size:11px;color:var(--text-muted)">{{ $ai->anomaly_reason }}</div>@endif
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        {{-- Flagged Cases for this transaction --}}
        @if($transaction->flaggedCases->count() > 0)
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-exclamation-triangle me-2" style="color:#b45309"></i> Flagged Cases ({{ $transaction->flaggedCases->count() }})</div>
            <div class="card-body p-0">
                @foreach($transaction->flaggedCases as $fc)
                <a href="{{ route('case-management.show', $fc->slug) }}" class="d-flex justify-content-between align-items-center p-3 text-decoration-none {{ !$loop->last ? 'border-bottom' : '' }}" style="color:var(--text-primary)">
                    <div>
                        <div class="fw-medium" style="font-size:12.5px">{{ $fc->slug }}</div>
                        <div style="font-size:10.5px;color:var(--text-muted)">{{ $fc->transaction_rule?->name ?? $fc->trigger_label }}</div>
                    </div>
                    @php $sc = ['open'=>'primary','escalated'=>'warning','closed_filed'=>'success','closed_not_filed'=>'secondary']; @endphp
                    <span class="badge bg-{{ $sc[$fc->status] ?? 'dark' }}" style="font-size:9px">{{ \Illuminate\Support\Str::headline($fc->status) }}</span>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Quick Info --}}
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i> Quick Info</div>
            <div class="card-body">
                <table class="table table-sm mb-0" style="font-size:12px">
                    <tr><td style="color:var(--text-muted)">ID</td><td class="fw-medium">#{{ $transaction->id }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Amount</td><td class="fw-bold">{{ moneyFormat($transaction->amount) }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Type</td><td>{{ ucfirst($transaction->transaction_type) }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Channel</td><td>{{ ucfirst($transaction->channel) }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Risk Scores</td><td>{{ $transaction->risk_scores->count() }}</td></tr>
                    <tr><td style="color:var(--text-muted)">AI Alerts</td><td>{{ $transaction->aiScores->where('is_anomaly', true)->count() }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Cases</td><td>{{ $transaction->flaggedCases->count() }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Created</td><td>{{ $transaction->created_at->format('M d, Y H:i') }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

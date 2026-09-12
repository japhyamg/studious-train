@extends('layouts.app')
@section('title', 'Case Management')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">Cases</span>
@endsection

@php
    $sourceIcons = [
        'rule' => 'bi-shield-check', 'watchlist' => 'bi-exclamation-diamond',
        'risk_score' => 'bi-graph-up-arrow', 'ai_anomaly' => 'bi-cpu',
        'peer_group' => 'bi-diagram-3', 'pas' => 'bi-globe2', 'preemptive' => 'bi-lightning-charge',
        'ctr' => 'bi-cash-stack', 'manual' => 'bi-person',
    ];
    $sourceColors = [
        'rule' => '#145234', 'watchlist' => '#b45309', 'risk_score' => '#dc2626',
        'ai_anomaly' => '#7c3aed', 'peer_group' => '#0284c7', 'pas' => '#0d9488', 'preemptive' => '#d97706',
        'ctr' => '#0ea5e9', 'manual' => '#64748b',
    ];
@endphp

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-folder2-open" style="color:var(--green-600);opacity:.6"></i> Case Management</h4>
        <div class="heading-subtitle">Monitor, investigate, and resolve flagged transactions</div>
    </div>
</div>

{{-- Status Filter Pills --}}
<div class="filter-bar">
    @foreach(['all' => 'All', 'open' => 'Open', 'escalated' => 'Escalated', 'closed_filed' => 'Closed (Filed)', 'closed_not_filed' => 'Closed (Not Filed)'] as $key => $label)
    <a href="{{ route('case-management.index', array_merge(request()->except('page'), ['status' => $key])) }}"
       class="filter-pill {{ ($status ?? 'all') == $key ? 'active' : '' }}">
        {{ $label }}
        <span class="count">
            @if(auth()->user()->hasRole('reviewer'))
                {{ $counts['reviewer_'.$key] ?? $counts[$key] ?? 0 }}
            @else
                {{ $counts[$key] ?? 0 }}
            @endif
        </span>
    </a>
    @endforeach
</div>

{{-- Trigger Source Filter --}}
@if(!empty($triggerSourceCounts))
<div class="filter-bar" style="margin-bottom:16px">
    <span style="font-size:11px;color:var(--text-muted);align-self:center;font-weight:600">Trigger:</span>
    <a href="{{ route('case-management.index', array_merge(request()->except(['trigger_source','page']))) }}"
       class="filter-pill {{ empty($trigger_source) ? 'active' : '' }}" style="font-size:11px;padding:4px 12px">
        All
    </a>
    @foreach($triggerSourceCounts as $src => $cnt)
    <a href="{{ route('case-management.index', array_merge(request()->except('page'), ['trigger_source' => $src])) }}"
       class="filter-pill {{ ($trigger_source ?? '') == $src ? 'active' : '' }}" style="font-size:11px;padding:4px 12px">
        <i class="bi {{ $sourceIcons[$src] ?? 'bi-flag' }}"></i>
        {{ \Illuminate\Support\Str::headline($src) }}
        <span class="count">{{ $cnt }}</span>
    </a>
    @endforeach
</div>
@endif

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <div class="input-group">
                    <span class="input-group-text" style="background:#faf8f2;border-color:var(--border);border-radius:10px 0 0 10px;font-size:14px;color:var(--text-muted)"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Case ID, account, customer..." value="{{ $search }}" style="border-radius:0 10px 10px 0 !important">
                </div>
            </div>
            @if(!auth()->user()->hasRole('reviewer'))
            <div class="col-md-2">
                <label class="form-label">Reviewer</label>
                <select name="reviewer" class="form-select form-select-sm">
                    <option value="all">All Reviewers</option>
                    @foreach($reviewers as $r)<option value="{{ $r->id }}" {{ ($reviewer ?? '') == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>@endforeach
                </select>
            </div>
            @endif
            <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}"></div>
            <input type="hidden" name="status" value="{{ $status }}">
            @if($trigger_source)<input type="hidden" name="trigger_source" value="{{ $trigger_source }}">@endif
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="{{ route('case-management.index') }}" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Clear all"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Cases Table --}}
<div class="card">
    <div class="card-header">
        <span>Cases ({{ $paginator->total() }})</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Case ID</th>
                        <th>Trigger</th>
                        <th>Rule / Source</th>
                        <th>Account</th>
                        <th>Customer</th>
                        <th>Side</th>
                        <th>Status</th>
                        <th>SLA</th>
                        <th>Classification</th>
                        <th>Reviewer</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($paginator->items() as $case)
                    @php $src = $case['trigger_source'] ?? 'rule'; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('case-management.show', $case['case_id']) }}" class="fw-semibold text-decoration-none" style="font-size:12.5px">{{ $case['case_id'] }}</a>
                        </td>
                        <td>
                            <span class="badge" style="background:{{ $sourceColors[$src] ?? '#64748b' }}10;color:{{ $sourceColors[$src] ?? '#64748b' }};font-size:10px;border:1px solid {{ $sourceColors[$src] ?? '#64748b' }}25">
                                <i class="bi {{ $sourceIcons[$src] ?? 'bi-flag' }} me-1"></i>{{ $case['trigger_label'] ?? \Illuminate\Support\Str::headline($src) }}
                            </span>
                        </td>
                        <td style="font-size:12px;max-width:160px" class="text-truncate">{{ $case['transaction_rule'] }}</td>
                        <td class="font-monospace" style="font-size:11px;color:var(--text-muted)">{{ $case['account_no'] }}</td>
                        <td style="font-size:12.5px;font-weight:500">{{ $case['customer_name'] ?? '—' }}</td>
                        <td>
                            @if($case['flagged_side'])
                            <span class="badge" style="background:#faf8f2;color:var(--text-secondary);font-size:9px;border:1px solid var(--border-light)">{{ ucfirst($case['flagged_side']) }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-status-{{ $case['status'] }}">{{ \Illuminate\Support\Str::headline($case['status']) }}</span>
                            @if(!empty($case['pending_disposition']))
                            <span class="badge" style="background:#fef3c7;color:#b45309;font-size:8px;border:1px solid #fde68a" data-bs-toggle="tooltip" title="Disposition proposed: {{ \Illuminate\Support\Str::headline($case['proposed_status']) }}">Pending</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $sla = $case['sla'] ?? ['status' => 'not_applicable', 'remaining' => '—'];
                                $slaColors = ['on_track' => ['#f0fdf4', '#16a34a'], 'at_risk' => ['#fef3c7', '#b45309'], 'breached' => ['#fef2f2', '#dc2626'], 'not_applicable' => ['#faf8f2', '#94a3b8']];
                                [$sbg, $sclr] = $slaColors[$sla['status']] ?? $slaColors['not_applicable'];
                            @endphp
                            <span class="badge" style="background:{{ $sbg }};color:{{ $sclr }};font-size:9px;border:1px solid {{ $sclr }}33" data-bs-toggle="tooltip" title="SLA deadline">
                                <i class="bi {{ $sla['status'] === 'breached' ? 'bi-exclamation-triangle' : 'bi-stopwatch' }} me-1"></i>{{ $sla['remaining'] }}
                            </span>
                        </td>
                        <td>
                            @if($case['classification'] == 'false_positive')
                            <span class="badge" style="background:#f1f0ee;color:#6b6860;font-size:9px;border:1px solid #e4e3e0">False Positive</span>
                            @else
                            <span class="badge badge-green" style="font-size:9px">{{ \Illuminate\Support\Str::headline($case['classification']) }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle" style="width:22px;height:22px;font-size:8px;border-radius:6px">{{ collect(explode(' ', $case['reviewer']))->map(fn($w)=>strtoupper($w[0]??''))->take(2)->join('') }}</div>
                                <span style="font-size:12px">{{ $case['reviewer'] }}</span>
                            </div>
                        </td>
                        <td style="font-size:11px;color:var(--text-muted);white-space:nowrap">{{ \Carbon\Carbon::parse($case['created_at'])->format('M d, Y') }}</td>
                        <td>
                            <a href="{{ route('case-management.show', $case['case_id']) }}" class="btn btn-outline-primary btn-action" data-bs-toggle="tooltip" title="View case"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="bi bi-folder2-open"></i></div>
                                <div class="empty-state-title">No cases found</div>
                                <div class="empty-state-text">Cases are created automatically when transactions trigger rules or detection thresholds.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($paginator->hasPages())
    <div class="card-footer">{{ $paginator->links() }}</div>
    @endif
</div>
@endsection

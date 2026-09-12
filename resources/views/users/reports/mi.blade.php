@extends('layouts.app')
@section('title', 'MI Reports')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">MI Reports</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-file-earmark-bar-graph" style="color:var(--green-600);opacity:.6"></i> Management Information</h4>
        <div class="heading-subtitle">CCO / Board AML-CFT report pack (CBN 5.8(a)(ii))</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('mi-reports.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="{{ route('mi-reports.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
    </div>
</div>

{{-- Period filter --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}"></div>
            <div class="col-auto"><button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i> Filter</button></div>
            <div class="col-auto ms-auto text-muted" style="font-size:11px">
                Period: <strong>{{ $data['period']['from'] ?? 'all time' }}</strong> → <strong>{{ $data['period']['to'] ?? 'now' }}</strong>
                · Generated {{ $data['period']['generated_at'] }}
            </div>
        </form>
    </div>
</div>

{{-- Customers --}}
<div class="row g-3 mb-2">
    @php
        $cards = [
            ['Customers', $data['customers']['total'], 'bi-people', '#eff6ff', '#2563eb'],
            ['PEP', $data['customers']['pep'], 'bi-person-exclamation', '#fef2f2', '#dc2626'],
            ['High Risk', $data['customers']['high'], 'bi-graph-up-arrow', '#fef2f2', '#dc2626'],
            ['Medium Risk', $data['customers']['medium'], 'bi-graph-up', '#fef3c7', '#b45309'],
            ['Low Risk', $data['customers']['low'], 'bi-check-circle', '#f0fdf4', '#16a34a'],
            ['Unrated', $data['customers']['unrated'], 'bi-question-circle', '#faf8f2', '#94a3b8'],
        ];
    @endphp
    @foreach($cards as [$label, $value, $icon, $bg, $clr])
    <div class="col-6 col-md-2">
        <div class="card text-center h-100" style="border:1px solid {{ $bg }};background:{{ $bg }}55">
            <div class="card-body py-3">
                <i class="bi {{ $icon }}" style="font-size:18px;color:{{ $clr }}"></i>
                <div class="fw-bold" style="font-size:22px;color:{{ $clr }}">{{ $value }}</div>
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);font-weight:700">{{ $label }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Cases --}}
<div class="card mb-4">
    <div class="card-header"><span><i class="bi bi-folder2-open me-2"></i> Case Volumes & Outcomes</span></div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            @foreach([
                ['Total Cases', $data['cases']['total']], ['Open', $data['cases']['open']],
                ['Escalated', $data['cases']['escalated']], ['Closed (Filed)', $data['cases']['closed_filed']],
                ['Closed (Not Filed)', $data['cases']['closed_not_filed']], ['STR', $data['cases']['str']],
                ['CTR', $data['cases']['ctr']], ['False Positives', $data['cases']['false_positives'] . ' (' . $data['cases']['false_positive_rate'] . '%)'],
            ] as [$lbl, $val])
            <div class="col-6 col-md-3">
                <div class="p-2 rounded-3" style="background:#faf8f2;border:1px solid var(--border-light)">
                    <div style="font-size:9px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;font-weight:700">{{ $lbl }}</div>
                    <div class="fw-bold" style="font-size:17px">{{ $val }}</div>
                </div>
            </div>
            @endforeach
        </div>

        @if(!empty($data['cases']['by_source']))
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:8px">Cases by Trigger Source</div>
        <div class="d-flex flex-wrap gap-1 mb-2">
            @foreach($data['cases']['by_source'] as $src => $cnt)
            <span class="badge" style="background:#faf8f2;color:var(--text-secondary);font-size:10px;border:1px solid var(--border-light)">
                {{ \Illuminate\Support\Str::headline($src) }} · {{ $cnt }}
            </span>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- Filings + SLA + Screening --}}
<div class="row g-4 mb-2">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><span><i class="bi bi-send me-2"></i> STR Filings</span></div>
            <div class="card-body">
                @foreach([['Total STR', $data['filings']['str_total']], ['Filed', $data['filings']['str_filed']], ['Unfiled', $data['filings']['str_unfiled']]] as [$lbl, $val])
                <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--border-light)">
                    <span style="font-size:12.5px;color:var(--text-secondary)">{{ $lbl }}</span>
                    <span class="fw-bold" style="font-size:13px">{{ $val }}</span>
                </div>
                @endforeach
                <div class="mt-3 p-2 rounded-2" style="background:{{ $data['filings']['str_overdue'] > 0 ? '#fef2f2' : '#f0fdf4' }};border:1px solid {{ $data['filings']['str_overdue'] > 0 ? '#fecaca' : '#bbf7d0' }}">
                    <i class="bi {{ $data['filings']['str_overdue'] > 0 ? 'bi-exclamation-triangle' : 'bi-check-circle' }} me-1" style="color:{{ $data['filings']['str_overdue'] > 0 ? '#dc2626' : '#16a34a' }}"></i>
                    <span style="font-size:12px;font-weight:600">{{ $data['filings']['str_overdue'] }} overdue STR filing{{ $data['filings']['str_overdue'] == 1 ? '' : 's' }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><span><i class="bi bi-stopwatch me-2"></i> SLA / TAT Compliance</span></div>
            <div class="card-body text-center">
                <div class="display-6 fw-bold" style="color:{{ $data['sla']['compliance_rate'] >= 90 ? '#16a34a' : ($data['sla']['compliance_rate'] >= 70 ? '#b45309' : '#dc2626') }}">
                    {{ $data['sla']['compliance_rate'] }}%
                </div>
                <div style="font-size:11px;color:var(--text-muted)">within SLA</div>
                <div class="row g-2 mt-3">
                    <div class="col-6"><div class="p-2 rounded-2" style="background:#faf8f2"><div class="fw-bold">{{ $data['sla']['within'] }}</div><div style="font-size:9px;text-transform:uppercase;color:var(--text-muted)">Within</div></div></div>
                    <div class="col-6"><div class="p-2 rounded-2" style="background:#faf8f2"><div class="fw-bold" style="color:#dc2626">{{ $data['sla']['breached'] }}</div><div style="font-size:9px;text-transform:uppercase;color:var(--text-muted)">Breached</div></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><span><i class="bi bi-radar me-2"></i> Screening & Detection</span></div>
            <div class="card-body">
                @foreach([['Watchlist Hits', $data['screening']['watchlist_hits']], ['PAS Cases', $data['screening']['pas_cases']], ['Pre-emptive Alerts', $data['screening']['preemptive_alerts']], ['Peer-Group Outliers', $data['screening']['peer_group_outliers']], ['AI Anomalies', $data['screening']['ai_anomalies']]] as [$lbl, $val])
                <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--border-light)">
                    <span style="font-size:12.5px;color:var(--text-secondary)">{{ $lbl }}</span>
                    <span class="fw-bold" style="font-size:13px">{{ $val }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- Trend --}}
<div class="card">
    <div class="card-header"><span><i class="bi bi-graph-up me-2"></i> STR / CTR Filing Trend (12 months)</span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Month</th><th>STR</th><th>CTR</th><th>Total</th></tr></thead>
                <tbody>
                    @foreach($data['trend'] as $m)
                    <tr>
                        <td style="font-size:12.5px;font-weight:500">{{ $m['label'] }}</td>
                        <td><span class="badge badge-status-open">{{ $m['STR'] }}</span></td>
                        <td><span class="badge badge-green">{{ $m['CTR'] }}</span></td>
                        <td class="fw-bold" style="font-size:12.5px">{{ $m['STR'] + $m['CTR'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

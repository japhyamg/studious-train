@extends('layouts.app')
@section('title', 'False Positive Dashboard')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('case-management.index') }}">Cases</a><span class="sep">/</span>
    <span class="current">False Positives</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-pie-chart" style="color:var(--green-600);opacity:.6"></i> False Positive Dashboard</h4>
        <div class="heading-subtitle">Monitor false positive rates and set alerting thresholds</div>
    </div>
    <div class="page-heading-actions">
        <a href="{{ route('case-management.false-positive-dashboard.export', ['format' => 'csv']) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="{{ route('case-management.false-positive-dashboard.export', ['format' => 'pdf']) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <span><i class="bi bi-pie-chart me-2"></i> False Positive Analysis</span>
        <form method="POST" action="{{ route('case-management.set-false-positive-threshold') }}" class="d-flex gap-2 align-items-center">
            @csrf
            <label class="form-label mb-0" style="font-size:12px;white-space:nowrap">Threshold:</label>
            <input type="number" name="threshold" class="form-control form-control-sm" style="width:80px" placeholder="%" value="{{ settings('false_positive_threshold', 30) }}">
            <button class="btn btn-primary btn-sm">Set</button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Month</th><th>Total Cases</th><th>True Positives</th><th>False Positives</th><th>FP Rate</th><th>Threshold</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($data as $row)
                <tr>
                    <td style="font-size:12px;color:var(--text-muted)">{{ $row['index'] }}</td>
                    <td class="fw-medium">{{ $row['month'] }}</td>
                    <td>{{ $row['total_cases'] }}</td>
                    <td>{{ $row['true_positives'] }}</td>
                    <td>{{ $row['false_positives'] }}</td>
                    <td class="fw-bold" style="color:{{ $row['status']=='above' ? '#dc2626' : 'var(--green-700)' }}">{{ $row['false_positive_rate'] }}%</td>
                    <td style="font-size:12px;color:var(--text-muted)">{{ $row['threshold'] }}%</td>
                    <td>
                        @if($row['status'] == 'above')
                        <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:10px;border:1px solid #fecaca">Above</span>
                        @else
                        <span class="badge" style="background:#f0fdf4;color:#16a34a;font-size:10px;border:1px solid #bbf7d0">Within</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="bi bi-pie-chart"></i></div>
                            <div class="empty-state-title">No data available</div>
                            <div class="empty-state-text">False positive analysis data will appear once cases are classified.</div>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection

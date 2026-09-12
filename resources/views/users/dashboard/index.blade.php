@extends('layouts.app')
@section('title', 'Dashboard')
@section('breadcrumb')
    <span class="current"><i class="bi bi-grid-1x2-fill me-1"></i> Dashboard</span>
@endsection

@section('content')
<div class="page-greeting">
    <h4>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }} 👋</h4>
    <p>{{ now()->format('l, d F Y') }} · MoniSurv Transaction Monitoring</p>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--green-50);color:var(--green-700)">
                <i class="bi bi-arrow-left-right"></i>
            </div>
            <div class="kpi-label">Total Transactions</div>
            <div class="kpi-value">{{ number_format($totalTransactions) }}</div>
            <div class="kpi-sub">processed all time</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#eff6ff;color:#2563eb">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="kpi-label">Customers</div>
            <div class="kpi-value">{{ number_format($totalCustomers) }}</div>
            <div class="kpi-sub">registered profiles</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef3c7;color:#b45309">
                <i class="bi bi-flag-fill"></i>
            </div>
            <div class="kpi-label">Flagged Cases</div>
            <div class="kpi-value" style="color:#b45309">{{ number_format($flaggedTransactions) }}</div>
            <div class="kpi-sub">
                <span class="status-dot warning" style="font-size:11px">{{ $caseCounts['open'] }} open</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#faf5ff;color:#7c3aed">
                <i class="bi bi-activity"></i>
            </div>
            <div class="kpi-label">Credit / Debit</div>
            <div class="kpi-value" style="font-size:22px">
                <span style="color:var(--green-700)">{{ number_format($totalCredit) }}</span>
                <span style="color:var(--text-muted);font-size:16px;margin:0 4px">/</span>
                <span style="color:#dc2626">{{ number_format($totalDebit) }}</span>
            </div>
            <div class="kpi-sub">credit vs debit count</div>
        </div>
    </div>
</div>

{{-- Case Status + Quick Actions --}}
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-folder2-open me-2"></i>Case Status Overview</span>
                <a href="{{ route('case-management.index') }}" class="btn btn-sm btn-outline-primary">View all <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @php
                        $statusCards = [
                            ['Open', $caseCounts['open'], 'var(--green-700)', 'bg-success', 'bi-folder2-open'],
                            ['Closed (Filed)', $caseCounts['closed_filed'], 'var(--green-800)', 'bg-success', 'bi-check-circle'],
                            ['Closed (Not Filed)', $caseCounts['closed_not_filed'], '#6b7280', 'bg-secondary', 'bi-x-circle'],
                            ['Escalated', $caseCounts['escalated'], '#b45309', 'bg-warning', 'bi-arrow-up-circle'],
                        ];
                    @endphp
                    @foreach($statusCards as [$label, $count, $color, $bgClass, $icon])
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-3 text-center" style="background:#faf8f2;border:1px solid var(--border-light)">
                            <div style="margin-bottom:6px">
                                <i class="bi {{ $icon }}" style="font-size:20px;color:{{ $color }};opacity:.6"></i>
                            </div>
                            <div style="font-size:24px;font-weight:800;color:{{ $color }};letter-spacing:-.5px">{{ $count }}</div>
                            <div style="font-size:10.5px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.3px;margin-top:4px">{{ $label }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><span><i class="bi bi-lightning me-2"></i>Quick Actions</span></div>
            <div class="card-body py-2">
                <a href="{{ route('case-management.index') }}" class="quick-action">
                    <i class="bi bi-folder2-open"></i>
                    <span>Review open cases</span>
                    <i class="bi bi-chevron-right quick-action-arrow"></i>
                </a>
                <a href="{{ route('transactions.index') }}" class="quick-action">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>View transactions</span>
                    <i class="bi bi-chevron-right quick-action-arrow"></i>
                </a>
                <a href="{{ route('transaction-rules.index') }}" class="quick-action">
                    <i class="bi bi-shield-check"></i>
                    <span>Manage rules</span>
                    <i class="bi bi-chevron-right quick-action-arrow"></i>
                </a>
                <a href="{{ route('customers.all') }}" class="quick-action">
                    <i class="bi bi-people"></i>
                    <span>View customers</span>
                    <i class="bi bi-chevron-right quick-action-arrow"></i>
                </a>
                <a href="{{ route('risk-rating.index') }}" class="quick-action">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Run risk rating</span>
                    <i class="bi bi-chevron-right quick-action-arrow"></i>
                </a>
                <a href="{{ route('pas.index') }}" class="quick-action">
                    <i class="bi bi-search"></i>
                    <span>P.A.S Screening</span>
                    <i class="bi bi-chevron-right quick-action-arrow"></i>
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Charts --}}
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-bar-chart-fill me-2"></i>Top Flagged Rules</span>
            </div>
            <div class="card-body">
                <div class="chart-container"><canvas id="flaggedRulesChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-pie-chart-fill me-2"></i>Channel Distribution</span>
            </div>
            <div class="card-body">
                <div class="chart-container"><canvas id="channelChart"></canvas></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const greens = ['#0d3b26','#145234','#1a6b42','#228554','#2ea86a','#6bc492','#a0dbb8'];
Chart.defaults.font.family = 'Inter, sans-serif';
Chart.defaults.font.size = 11;
Chart.defaults.color = '#8896a4';

@if(!empty($top5FlaggedChart) && count($top5FlaggedChart) >= 2)
new Chart(document.getElementById('flaggedRulesChart'), {
    type: 'bar',
    data: {
        labels: @json($top5FlaggedChart[0] ?? []),
        datasets: [{
            label: 'Cases',
            data: @json($top5FlaggedChart[1] ?? []),
            backgroundColor: 'rgba(20,82,52,0.85)',
            borderRadius: 6,
            barThickness: 20,
            hoverBackgroundColor: '#0d3b26',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0d3b26',
                titleFont: { size: 11, weight: '600' },
                bodyFont: { size: 11 },
                padding: 10,
                cornerRadius: 8,
                displayColors: false,
            }
        },
        scales: {
            x: {
                grid: { color: '#f0f0f0', drawBorder: false },
                ticks: { font: { size: 10 } }
            },
            y: {
                grid: { display: false, drawBorder: false },
                ticks: { font: { size: 11, weight: '500' } }
            }
        }
    }
});
@endif

@php $channels = $channelSummary ?? collect(); @endphp
@if($channels->count() > 0)
new Chart(document.getElementById('channelChart'), {
    type: 'doughnut',
    data: {
        labels: @json($channels->pluck('channel')),
        datasets: [{
            data: @json($channels->pluck('total')),
            backgroundColor: greens,
            borderWidth: 0,
            hoverOffset: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 16, usePointStyle: true, pointStyleWidth: 8, font: { size: 11 } }
            },
            tooltip: {
                backgroundColor: '#0d3b26',
                padding: 10,
                cornerRadius: 8,
            }
        }
    }
});
@endif
</script>
@endpush

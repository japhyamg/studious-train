@extends('layouts.app')
@section('title', 'Case Performance')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('case-management.index') }}">Cases</a><span class="sep">/</span>
    <span class="current">Performance</span>
@endsection

@push('styles')
<link href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
<style>
    .dt-buttons .btn { font-size: 12px !important; padding: 5px 14px !important; border-radius: 8px !important; font-weight: 500 !important; }
    .dataTables_filter input { border-radius: 8px !important; border: 1.5px solid var(--border) !important; font-size: 12.5px !important; padding: 6px 12px !important; }
    .dataTables_filter input:focus { border-color: var(--green-500) !important; box-shadow: 0 0 0 3px rgba(46,168,106,.1) !important; outline: none !important; }
    .dataTables_length select { border-radius: 8px !important; border: 1.5px solid var(--border) !important; font-size: 12.5px !important; }
    .dataTables_info { font-size: 12px !important; color: var(--text-muted) !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { border-radius: 6px !important; font-size: 12px !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--green-700) !important; border-color: var(--green-700) !important; color: #fff !important; }
    table.dataTable thead th { font-size: 10.5px !important; text-transform: uppercase !important; letter-spacing: .8px !important; color: var(--text-muted) !important; font-weight: 700 !important; }
    table.dataTable tbody td { font-size: 13px !important; vertical-align: middle !important; }
</style>
@endpush

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-speedometer2" style="color:var(--green-600);opacity:.6"></i> Reviewer Performance</h4>
        <div class="heading-subtitle">Track case resolution metrics across team members</div>
    </div>
    <div class="page-heading-actions">
        <a href="{{ route('case-management.performance.export', ['format' => 'csv']) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="{{ route('case-management.performance.export', ['format' => 'pdf']) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    @php $kpis = [
        ['Total Cases', $totalCasesCount, 'var(--green-700)', 'bi-folder2-open', 'var(--green-50)'],
        ['Open', $openCasesCount, '#2563eb', 'bi-folder2', '#eff6ff'],
        ['Closed', $closedCasesCount, '#16a34a', 'bi-check-circle', '#f0fdf4'],
        ['Escalated', $escalatedCasesCount, '#b45309', 'bi-arrow-up-circle', '#fef3c7'],
        ['Reviewers', $reviewersCount, 'var(--text-primary)', 'bi-people', '#faf8f2'],
    ]; @endphp
    @foreach($kpis as [$label, $count, $color, $icon, $bg])
    <div class="col">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:{{ $bg }};color:{{ $color }}"><i class="bi {{ $icon }}"></i></div>
            <div class="kpi-label">{{ $label }}</div>
            <div class="kpi-value" style="color:{{ $color }}">{{ $count }}</div>
        </div>
    </div>
    @endforeach
</div>

{{-- Reviewer Breakdown Table with Export --}}
<div class="card mb-4">
    <div class="card-header"><span><i class="bi bi-people me-2"></i> Reviewer Breakdown</span></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="perfTable" style="width:100%">
                <thead>
                    <tr>
                        <th>Reviewer</th>
                        <th>Total</th>
                        <th>Open</th>
                        <th>Closed (Filed)</th>
                        <th>Closed (Not Filed)</th>
                        <th>Escalated</th>
                        <th>SLA Breached</th>
                        <th>Avg Resolution</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($performance as $p)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle" style="width:26px;height:26px;font-size:9px;border-radius:7px">{{ collect(explode(' ',$p['name']))->map(fn($w)=>strtoupper($w[0]??''))->take(2)->join('') }}</div>
                                <span class="fw-medium">{{ $p['name'] }}</span>
                            </div>
                        </td>
                        <td class="fw-bold">{{ $p['total'] }}</td>
                        <td>{{ $p['open'] }}</td>
                        <td>{{ $p['closed_filed'] }}</td>
                        <td>{{ $p['closed_not_filed'] }}</td>
                        <td>{{ $p['escalated'] }}</td>
                        <td>
                            @if(($p['sla_breached'] ?? 0) > 0)
                            <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:10px;border:1px solid #fecaca">{{ $p['sla_breached'] }}</span>
                            @else
                            <span class="badge badge-green" style="font-size:10px">0</span>
                            @endif
                        </td>
                        <td><span class="badge badge-green">{{ $p['avg_resolution_hours'] }}h</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Performance Chart --}}
<div class="card">
    <div class="card-header"><span><i class="bi bi-bar-chart-fill me-2"></i> Performance Chart</span></div>
    <div class="card-body"><div class="chart-container"><canvas id="perfChart"></canvas></div></div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script>
$(function() {
    // DataTable with export buttons
    $('#perfTable').DataTable({
        paging: false,
        info: false,
        searching: false,
        ordering: true,
        order: [[1, 'desc']],
        dom: '<"d-flex justify-content-between align-items-center mb-3"<"dt-title">B>t',
        buttons: [
            {
                extend: 'csvHtml5',
                text: '<i class="bi bi-filetype-csv me-1"></i> CSV',
                className: 'btn btn-outline-primary btn-sm',
                title: 'MoniSurv_Reviewer_Performance',
            },
            {
                extend: 'excelHtml5',
                text: '<i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel',
                className: 'btn btn-outline-primary btn-sm',
                title: 'MoniSurv_Reviewer_Performance',
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="bi bi-file-earmark-pdf me-1"></i> PDF',
                className: 'btn btn-outline-primary btn-sm',
                title: 'MoniSurv - Reviewer Performance Report',
            },
            {
                extend: 'print',
                text: '<i class="bi bi-printer me-1"></i> Print',
                className: 'btn btn-outline-secondary btn-sm',
                title: 'MoniSurv - Reviewer Performance Report',
            },
        ],
    });

    // Chart.js
    Chart.defaults.font.family = 'Inter, sans-serif';
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#8896a4';

    new Chart(document.getElementById('perfChart'), {
        type: 'bar',
        data: {
            labels: @json($chartData['labels'] ?? []),
            datasets: [
                { label: 'Open', data: @json($chartData['open'] ?? []), backgroundColor: '#2563eb', borderRadius: 4 },
                { label: 'Closed (Filed)', data: @json($chartData['closed_filed'] ?? []), backgroundColor: '#145234', borderRadius: 4 },
                { label: 'Escalated', data: @json($chartData['escalated'] ?? []), backgroundColor: '#f59e0b', borderRadius: 4 },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 14, usePointStyle: true, font: { size: 11 } }
                },
                tooltip: { backgroundColor: '#0d3b26', cornerRadius: 8, padding: 10 }
            }
        }
    });
});
</script>
@endpush

@extends('layouts.app')
@section('title', 'Case Aging & Resolution')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('case-management.index') }}">Cases</a><span class="sep">/</span>
    <span class="current">Case Aging & Resolution</span>
@endsection

@push('styles')
<link href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.5.1/css/responsive.bootstrap5.min.css" rel="stylesheet">
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

@php
    // Normalize chartData — support both old format (flat counts) and new format (labels+series)
    $chartLabels = $chartData['labels'] ?? [];
    $chartSeries = $chartData['series'] ?? [];

    // If old format: ['open' => 5, 'closed_filed' => 10, ...]
    if (empty($chartLabels) && !isset($chartData['labels'])) {
        $chartLabels = [];
        $chartSeries = [];
        foreach ($chartData as $status => $count) {
            $chartLabels[] = \Illuminate\Support\Str::headline($status);
            $chartSeries[] = $count;
        }
    }
@endphp

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-clock-history" style="color:var(--green-600);opacity:.6"></i> Case Aging and Resolution Dashboard</h4>
        <div class="heading-subtitle">Track case lifecycle — from creation to resolution with aging metrics</div>
    </div>
    <div class="page-heading-actions">
        <a href="{{ route('case-management.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back to Cases</a>
    </div>
</div>

{{-- DataTable Card --}}
<div class="card mb-4">
    <div class="card-header">
        <span><i class="bi bi-table me-2"></i> Case Aging Report</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="carrdTable" style="width:100%">
                <thead>
                    <tr>
                        <th></th>
                        <th>Case ID</th>
                        <th>Generated On</th>
                        <th>Last Action Date</th>
                        <th>Days Open</th>
                        <th>Status</th>
                        <th>Closed By</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Chart Card --}}
<div class="card">
    <div class="card-header"><span><i class="bi bi-pie-chart-fill me-2"></i> Case Status Distribution</span></div>
    <div class="card-body">
        <div class="chart-container" style="max-width:500px;margin:0 auto"><canvas id="carrdChart"></canvas></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.1/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script>
$(function() {
    // Status badge renderer
    function renderStatus(status) {
        const colors = {
            open: ['#dbeafe', '#1e40af', '#bfdbfe'],
            escalated: ['#fef3c7', '#92600a', '#fde68a'],
            closed_filed: ['#e6f2ec', '#145234', '#b0f0cc'],
            closed_not_filed: ['#f1f0ee', '#6b6860', '#e4e3e0'],
        };
        const c = colors[status] || ['#faf8f2', '#64748b', '#e4e8f0'];
        const label = status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        return '<span class="badge" style="background:'+c[0]+';color:'+c[1]+';border:1px solid '+c[2]+';font-size:10px">'+label+'</span>';
    }

    // Days open color coding
    function renderDays(days) {
        let color = '#16a34a';
        if (days > 30) color = '#dc2626';
        else if (days > 14) color = '#b45309';
        else if (days > 7) color = '#2563eb';
        return '<span class="fw-bold" style="color:'+color+'">'+days+'</span>';
    }

    // Init DataTable
    $('#carrdTable').DataTable({
        processing: true,
        autoWidth: false,
        ajax: {
            url: "{{ route('case-management.carrd.data') }}",
            dataSrc: 'data'
        },
        columns: [
            { data: 'id', visible: false },
            {
                data: 'caseid',
                render: function(data) {
                    return '<a href="/case-management/'+data+'" class="fw-semibold text-decoration-none" style="font-size:12.5px">'+data+'</a>';
                }
            },
            { data: 'generated_on' },
            { data: 'last_action_date' },
            {
                data: 'days_open',
                render: function(data) { return renderDays(data); }
            },
            {
                data: 'status',
                render: function(data) { return renderStatus(data); }
            },
            { data: 'closed_by' },
        ],
        order: [[0, 'desc']],
        columnDefs: [
            { className: 'control', orderable: false, targets: 0, searchable: false },
        ],
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        dom: '<"row align-items-center mb-3"<"col-sm-6 col-md-4"l><"col-sm-6 col-md-4 text-center"B><"col-md-4"f>>rtip',
        buttons: [
            {
                extend: 'csvHtml5',
                text: '<i class="bi bi-filetype-csv me-1"></i> CSV',
                className: 'btn btn-outline-primary btn-sm',
                title: 'MoniSurv_Case_Aging_Report',
                exportOptions: { columns: [1, 2, 3, 4, 5, 6] }
            },
            {
                extend: 'excelHtml5',
                text: '<i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel',
                className: 'btn btn-outline-primary btn-sm',
                title: 'MoniSurv_Case_Aging_Report',
                exportOptions: { columns: [1, 2, 3, 4, 5, 6] }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="bi bi-file-earmark-pdf me-1"></i> PDF',
                className: 'btn btn-outline-primary btn-sm',
                title: 'MoniSurv - Case Aging and Resolution Report',
                exportOptions: { columns: [1, 2, 3, 4, 5, 6] }
            },
            {
                extend: 'print',
                text: '<i class="bi bi-printer me-1"></i> Print',
                className: 'btn btn-outline-secondary btn-sm',
                title: 'MoniSurv - Case Aging and Resolution Report',
                exportOptions: { columns: [1, 2, 3, 4, 5, 6] }
            },
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search cases...',
            lengthMenu: 'Show _MENU_',
            info: 'Showing _START_ to _END_ of _TOTAL_ cases',
            paginate: {
                next: '<i class="bi bi-chevron-right"></i>',
                previous: '<i class="bi bi-chevron-left"></i>',
            },
        },
    });

    // Chart.js Pie Chart
    Chart.defaults.font.family = 'Inter, sans-serif';
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#8896a4';

    new Chart(document.getElementById('carrdChart'), {
        type: 'pie',
        data: {
            labels: @json($chartLabels),
            datasets: [{
                data: @json($chartSeries),
                backgroundColor: ['#dc2626', '#145234', '#94a3b8', '#f59e0b'],
                borderWidth: 0,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        pointStyleWidth: 10,
                        font: { size: 12, weight: '500' }
                    }
                },
                tooltip: {
                    backgroundColor: '#0d3b26',
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        label: function(ctx) {
                            var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                            var pct = total > 0 ? Math.round((ctx.raw / total) * 100) : 0;
                            return ' ' + ctx.label + ': ' + ctx.raw + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
});
</script>
@endpush

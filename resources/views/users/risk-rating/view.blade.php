@extends('layouts.app')
@section('title', 'Rating Results')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('risk-rating.index') }}">Customer Risk Rating</a><span class="sep">/</span>
    <span class="current">Rating Results</span>
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
@include('users.risk-rating.partials.nav')

<a href="{{ route('risk-rating.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Risk Ratings</a>

<div class="page-heading">
    <div>
        <h4><i class="bi bi-graph-up-arrow" style="color:var(--green-600);opacity:.6"></i> Risk Rating Results</h4>
        <div class="heading-subtitle">Profile: {{ $riskrating->risk_profile?->name ?? '—' }} · Generated {{ $riskrating->created_at->format('M d, Y H:i') }}</div>
    </div>
    <div class="page-heading-actions">
        <div class="dropdown">
            <button class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-download me-1"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('risk-rating.export', [$riskrating->id, 'csv']) }}"><i class="bi bi-filetype-csv me-2" style="opacity:.5"></i>CSV</a></li>
                <li><a class="dropdown-item" href="{{ route('risk-rating.export', [$riskrating->id, 'excel']) }}"><i class="bi bi-file-earmark-spreadsheet me-2" style="opacity:.5"></i>Excel</a></li>
                <li><a class="dropdown-item" href="{{ route('risk-rating.export', [$riskrating->id, 'pdf']) }}"><i class="bi bi-file-earmark-pdf me-2" style="opacity:.5"></i>PDF</a></li>
            </ul>
        </div>
    </div>
</div>

{{-- Summary KPIs --}}
<div class="row g-3 mb-4">
    @php
        $levelColors = [
            'Low' => ['var(--green-50)', 'var(--green-700)', 'var(--green-100)'],
            'Medium' => ['#fef3c7', '#b45309', '#fde68a'],
            'High' => ['#fef2f2', '#dc2626', '#fecaca'],
        ];
    @endphp
    @foreach($chartData as $level => $count)
    @php $lc = $levelColors[$level] ?? ['#faf8f2', 'var(--text-primary)', 'var(--border-light)']; @endphp
    <div class="col">
        <div class="kpi-card" style="border-left:4px solid {{ $lc[1] }}">
            <div class="kpi-label">{{ $level }}</div>
            <div class="kpi-value" style="color:{{ $lc[1] }}">{{ $count }}</div>
        </div>
    </div>
    @endforeach
</div>

{{-- Results Table --}}
<div class="card">
    <div class="card-header">
        <span><i class="bi bi-table me-2"></i> Customer Results ({{ $results->total() }})</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="ratingResultsTable" style="width:100%">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Account No</th>
                        <th>Type</th>
                        <th>State</th>
                        <th>PEP</th>
                        <th>Score</th>
                        <th>Risk Level</th>
                        <th>Diligence</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($results as $r)
                    @php $level = mapScoreToRiskLevel($r->score); @endphp
                    <tr>
                        <td>
                            @if($r->customer)
                            <a href="{{ route('customers.show', $r->customer->id) }}" class="fw-semibold text-decoration-none">{{ $r->customer->name }}</a>
                            @else
                            <span style="color:var(--text-muted)">—</span>
                            @endif
                        </td>
                        <td class="font-monospace" style="font-size:12px">{{ $r->customer?->account_number ?? '—' }}</td>
                        <td><span class="badge" style="background:#faf8f2;color:var(--text-secondary);font-size:9px;border:1px solid var(--border-light)">{{ ucfirst($r->customer?->customer_type ?? '—') }}</span></td>
                        <td style="font-size:12px">{{ ucfirst($r->customer?->state_of_residence ?? '—') }}</td>
                        <td>
                            @if(($r->customer?->isPep === 'yes') || ($r->customer?->isPep == 1))
                            <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:9px;border:1px solid #fecaca">PEP</span>
                            @else
                            <span style="font-size:11px;color:var(--text-muted)">No</span>
                            @endif
                        </td>
                        <td class="fw-bold">{{ $r->score }}</td>
                        <td>
                            @if($level)
                            @php
                                $badgeColors = [
                                    'Low' => ['#f0fdf4', '#16a34a', '#bbf7d0'],
                                    'Medium' => ['#fef3c7', '#b45309', '#fde68a'],
                                    'High' => ['#fef2f2', '#dc2626', '#fecaca'],
                                ];
                                $bc = $badgeColors[$level->label] ?? ['#faf8f2', '#94a3b8', '#e4e8f0'];
                            @endphp
                            <span class="badge" style="background:{{ $bc[0] }};color:{{ $bc[1] }};font-size:10px;border:1px solid {{ $bc[2] }}">{{ $level->label }}</span>
                            @else
                            <span style="font-size:11px;color:var(--text-muted)">N/A</span>
                            @endif
                        </td>
                        <td>
                            @if($level)
                            <span class="badge {{ $level->diligence_type === 'EDD' ? '' : 'badge-green' }}" style="{{ $level->diligence_type === 'EDD' ? 'background:#fef3c7;color:#b45309;font-size:10px;border:1px solid #fde68a' : 'font-size:10px' }}">
                                {{ $level->diligence_type ?? 'CDD' }}
                            </span>
                            @else
                            CDD
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @if($results->hasPages())
    <div class="card-footer">{{ $results->links() }}</div>
    @endif
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
    $('#ratingResultsTable').DataTable({
        paging: false,
        info: false,
        ordering: true,
        order: [[5, 'desc']],
        dom: '<"d-flex justify-content-between align-items-center mb-3"fB>t',
        buttons: [
            {
                extend: 'csvHtml5',
                text: '<i class="bi bi-filetype-csv me-1"></i> CSV',
                className: 'btn btn-outline-primary btn-sm',
                title: 'MoniSurv_RiskRating_Results',
            },
            {
                extend: 'excelHtml5',
                text: '<i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel',
                className: 'btn btn-outline-primary btn-sm',
                title: 'MoniSurv_RiskRating_Results',
            },
            {
                extend: 'print',
                text: '<i class="bi bi-printer me-1"></i> Print',
                className: 'btn btn-outline-secondary btn-sm',
                title: 'MoniSurv - Risk Rating Results',
            },
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search results...',
        },
    });
});
</script>
@endpush

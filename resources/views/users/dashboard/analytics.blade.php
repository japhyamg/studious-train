@extends('layouts.app')
@section('title', 'Analytics')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">Analytics</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-bar-chart-line" style="color:var(--green-600);opacity:.6"></i> Analytics</h4>
        <div class="heading-subtitle">Customer demographics and transaction distribution insights</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><span><i class="bi bi-people me-2"></i> Gender Distribution</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-4">
                        <div class="p-3 rounded-3 text-center" style="background:#faf8f2;border:1px solid var(--border-light)">
                            <div style="font-size:28px;font-weight:800">{{ $genderDist['Total'] ?? 0 }}</div>
                            <div style="font-size:10.5px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px">Total</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 rounded-3 text-center" style="background:#eff6ff;border:1px solid #bfdbfe">
                            <div style="font-size:28px;font-weight:800;color:#2563eb">{{ $genderDist['Male'] ?? 0 }}</div>
                            <div style="font-size:10.5px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px">Male</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 rounded-3 text-center" style="background:#fdf2f8;border:1px solid #fbcfe8">
                            <div style="font-size:28px;font-weight:800;color:#db2777">{{ $genderDist['Female'] ?? 0 }}</div>
                            <div style="font-size:10.5px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px">Female</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><span><i class="bi bi-bar-chart me-2"></i> Customer Type Distribution</span></div>
            <div class="card-body"><div class="chart-container" style="min-height:200px"><canvas id="custTypeChart"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><span><i class="bi bi-layers me-2"></i> Tier Distribution</span></div>
            <div class="card-body"><div class="chart-container" style="min-height:220px"><canvas id="tierChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><span><i class="bi bi-map me-2"></i> Regional Transaction Volume</span></div>
            <div class="card-body"><div class="chart-container" style="min-height:220px"><canvas id="regionChart"></canvas></div></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
Chart.defaults.font.family = 'Inter, sans-serif';
Chart.defaults.font.size = 11;
Chart.defaults.color = '#8896a4';
const chartOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { padding: 14, usePointStyle: true, font: { size: 11 } } }, tooltip: { backgroundColor: '#0d3b26', cornerRadius: 8, padding: 10 } } };

@php $td = $tierDist['data'] ?? ['labels'=>[],'series'=>[]]; @endphp
new Chart(document.getElementById('tierChart'),{type:'doughnut',data:{labels:@json($td['labels']),datasets:[{data:@json($td['series']),backgroundColor:['#145234','#228554','#f59e0b','#dc2626'],borderWidth:0,hoverOffset:6}]},options:{...chartOpts,cutout:'65%'}});

@php $ct = $customerTypeDist ?? []; unset($ct['data']); @endphp
new Chart(document.getElementById('custTypeChart'),{type:'bar',data:{labels:@json(array_keys($ct)),datasets:[{data:@json(array_values($ct)),backgroundColor:'#145234',borderRadius:6,barThickness:24}]},options:{...chartOpts,plugins:{legend:{display:false},tooltip:chartOpts.plugins.tooltip}}});

@php $rc = $regionChartData ?? []; @endphp
@if(!empty($rc))
new Chart(document.getElementById('regionChart'),{type:'bar',data:{labels:['NW','NE','NC','SW','SE','SS'],datasets:[{label:'Credit',data:@json($rc['credit_volume'] ?? []),backgroundColor:'#145234',borderRadius:4},{label:'Debit',data:@json($rc['debit_volume'] ?? []),backgroundColor:'#dc2626',borderRadius:4}]},options:chartOpts});
@endif
</script>
@endpush

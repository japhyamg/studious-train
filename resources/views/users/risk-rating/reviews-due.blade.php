@extends('layouts.app')
@section('title', 'CDD/EDD Reviews Due')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('risk-rating.index') }}">Customer Risk Rating</a><span class="sep">/</span>
    <span class="current">Reviews Due</span>
@endsection
@section('page-title', 'CDD/EDD Reviews Due')
@section('content')
@include('users.risk-rating.partials.nav')

{{-- Summary KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="border-left:3px solid #dc2626">
            <div class="kpi-label">Overdue</div>
            <div class="kpi-value" style="color:#dc2626">{{ $overdue->count() }}</div>
            <div class="kpi-sub">past review date</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="border-left:3px solid #b45309">
            <div class="kpi-label">Due Today</div>
            <div class="kpi-value" style="color:#b45309">{{ $dueToday->count() }}</div>
            <div class="kpi-sub">requires review</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="border-left:3px solid #6b8fce">
            <div class="kpi-label">Upcoming (7 days)</div>
            <div class="kpi-value" style="color:#6b8fce">{{ $upcoming->count() }}</div>
            <div class="kpi-sub">within next week</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="border-left:3px solid var(--green-700)">
            <div class="kpi-label">Completed</div>
            <div class="kpi-value" style="color:var(--green-700)">{{ $completed->count() }}</div>
            <div class="kpi-sub">reviewed this month</div>
        </div>
    </div>
</div>

{{-- Overdue + Due Customers --}}
<div class="card mb-4">
    <div class="card-header">
        <span><i class="bi bi-exclamation-triangle me-1" style="color:#dc2626"></i> Reviews Overdue & Due</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead>
                <tr><th>Customer</th><th>Account</th><th>Risk Level</th><th>Diligence</th><th>Review Due</th><th>Days</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                @forelse($allDue as $customer)
                @php
                    $daysLeft = $customer->days_until_review;
                    $statusColor = $daysLeft < 0 ? '#dc2626' : ($daysLeft == 0 ? '#b45309' : '#6b8fce');
                    $statusLabel = $daysLeft < 0 ? 'Overdue' : ($daysLeft == 0 ? 'Due Today' : "In {$daysLeft}d");
                @endphp
                <tr>
                    <td class="fw-medium">{{ $customer->name }}</td>
                    <td class="font-monospace" style="font-size:12px">{{ $customer->account_number }}</td>
                    <td>
                        @php $levelColors = ['low'=>'var(--green-700)','medium'=>'#b45309','high'=>'#dc2626']; @endphp
                        <span class="badge" style="background:{{ $levelColors[strtolower($customer->current_risk_level ?? '')] ?? '#8a8680' }};color:#fff;font-size:10px">
                            {{ ucfirst($customer->current_risk_level ?? 'N/A') }}
                        </span>
                    </td>
                    <td>
                        @php $riskLevel = \App\Models\RiskLevel::where('label', $customer->current_risk_level)->first(); @endphp
                        <span class="badge {{ ($riskLevel?->getDiligenceLabel() ?? 'CDD') == 'EDD' ? 'bg-warning text-dark' : 'bg-success bg-opacity-75 text-white' }}" style="font-size:10px">
                            {{ $riskLevel?->getDiligenceLabel() ?? 'CDD' }}
                        </span>
                    </td>
                    <td style="font-size:12px">{{ $customer->next_review_date?->format('M d, Y') ?? '---' }}</td>
                    <td>
                        <span style="color:{{ $statusColor }};font-weight:600;font-size:12px">{{ $statusLabel }}</span>
                    </td>
                    <td>
                        <span class="badge" style="background:{{ $statusColor }};color:#fff;font-size:10px">
                            {{ $daysLeft < 0 ? 'OVERDUE' : ($daysLeft == 0 ? 'DUE' : 'UPCOMING') }}
                        </span>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('risk-rating.reviews-due') }}?mark_reviewed={{ $customer->id }}" style="display:inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary" style="font-size:11px;padding:2px 8px">
                                <i class="bi bi-check2 me-1"></i>Mark Reviewed
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center py-4 text-muted"><i class="bi bi-check-circle fs-4 d-block mb-2" style="color:var(--green-700)"></i>No reviews due. All customers are up to date!</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection

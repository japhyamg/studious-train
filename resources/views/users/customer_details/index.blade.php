@extends('layouts.app')
@section('title', 'Customers')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">Customers</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-people" style="color:var(--green-600);opacity:.6"></i> Customer Management</h4>
        <div class="heading-subtitle">View and manage all registered customer profiles</div>
    </div>
    <div class="page-heading-actions">
        @can('customer-create')
        <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Add Customer</a>
        @endcan
    </div>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <div class="input-group">
                    <span class="input-group-text" style="background:#faf8f2;border-color:var(--border);border-radius:10px 0 0 10px;font-size:14px;color:var(--text-muted)"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, account, BVN..." value="{{ request('search') }}" style="border-radius:0 10px 10px 0 !important">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="customerType" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="individual" {{ request('customerType')=='individual'?'selected':'' }}>Individual</option>
                    <option value="corporate" {{ request('customerType')=='corporate'?'selected':'' }}>Corporate</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select form-select-sm">
                    <option value="">All Genders</option>
                    <option value="male" {{ request('gender')=='male'?'selected':'' }}>Male</option>
                    <option value="female" {{ request('gender')=='female'?'selected':'' }}>Female</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Risk Level</label>
                <select name="risk_level" class="form-select form-select-sm">
                    <option value="">All Levels</option>
                    <option value="low" {{ request('risk_level')=='low'?'selected':'' }}>Low</option>
                    <option value="medium" {{ request('risk_level')=='medium'?'selected':'' }}>Medium</option>
                    <option value="high" {{ request('risk_level')=='high'?'selected':'' }}>High</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
                <a href="{{ route('customers.all') }}" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Clear filters"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Customers Table --}}
<div class="card">
    <div class="card-header">
        <span>All Customers ({{ $customers->total() }})</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Account No</th>
                        <th>BVN</th>
                        <th>Type</th>
                        <th>Tier</th>
                        <th>State</th>
                        <th>PEP</th>
                        <th>Risk Level</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $c)
                    <tr>
                        <td>
                            <a href="{{ route('customers.show', $c->id) }}" class="text-decoration-none">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle" style="width:30px;height:30px;font-size:10px;border-radius:8px">{{ strtoupper(substr($c->first_name ?? $c->name ?? '?', 0, 1)) }}{{ strtoupper(substr($c->last_name ?? '', 0, 1)) }}</div>
                                    <span class="fw-medium">{{ $c->name }}</span>
                                </div>
                            </a>
                        </td>
                        <td class="font-monospace" style="font-size:12px">{{ $c->account_number }}</td>
                        <td style="font-size:12px;color:var(--text-muted)">{{ $c->bvn }}</td>
                        <td><span class="badge" style="background:#faf8f2;color:var(--text-secondary);border:1px solid var(--border-light)">{{ ucfirst($c->customer_type) }}</span></td>
                        <td style="font-size:12.5px">{{ $c->tier_level }}</td>
                        <td style="font-size:12.5px">{{ ucfirst($c->state_of_residence) }}</td>
                        <td>
                            @if($c->isPep == 'yes')
                            <span class="badge" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca">PEP</span>
                            @else
                            <span class="badge" style="background:#faf8f2;color:#94a3b8;border:1px solid var(--border-light)">No</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $riskColors = [
                                    'low' => ['bg' => '#f0fdf4', 'color' => '#16a34a', 'border' => '#bbf7d0'],
                                    'medium' => ['bg' => '#fef3c7', 'color' => '#b45309', 'border' => '#fde68a'],
                                    'high' => ['bg' => '#fef2f2', 'color' => '#dc2626', 'border' => '#fecaca'],
                                ];
                                $level = strtolower($c->current_risk_level ?? '');
                                $rc = $riskColors[$level] ?? ['bg' => '#faf8f2', 'color' => '#94a3b8', 'border' => '#e4e8f0'];
                            @endphp
                            <span class="badge" style="background:{{ $rc['bg'] }};color:{{ $rc['color'] }};border:1px solid {{ $rc['border'] }}">{{ ucfirst($c->current_risk_level ?? 'Unrated') }}</span>
                        </td>
                        <td>
                            <a href="{{ route('customers.show', $c->id) }}" class="btn btn-outline-primary btn-action" data-bs-toggle="tooltip" title="View profile"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                                <div class="empty-state-title">No customers found</div>
                                <div class="empty-state-text">Customers will appear here once they are created or imported.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($customers->hasPages())
    <div class="card-footer">{{ $customers->links() }}</div>
    @endif
</div>
@endsection

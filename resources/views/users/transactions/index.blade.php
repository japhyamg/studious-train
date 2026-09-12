@extends('layouts.app')
@section('title', 'Transactions')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">Transactions</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-arrow-left-right" style="color:var(--green-600);opacity:.6"></i> Transactions</h4>
        <div class="heading-subtitle">All financial transactions processed through the system</div>
    </div>
</div>

{{-- KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--green-50);color:var(--green-700)">
                <i class="bi bi-arrow-left-right"></i>
            </div>
            <div class="kpi-label">Total</div>
            <div class="kpi-value">{{ number_format($total) }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#f0fdf4;color:#16a34a">
                <i class="bi bi-arrow-down-left"></i>
            </div>
            <div class="kpi-label">Credit</div>
            <div class="kpi-value text-success">{{ number_format($credit) }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef2f2;color:#dc2626">
                <i class="bi bi-arrow-up-right"></i>
            </div>
            <div class="kpi-label">Debit</div>
            <div class="kpi-value text-danger">{{ number_format($debit) }}</div>
        </div>
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
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Ref, name, amount..." value="{{ request('search') }}" style="border-radius:0 10px 10px 0 !important">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="transactionType" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="credit" {{ request('transactionType')=='credit'?'selected':'' }}>Credit</option>
                    <option value="debit" {{ request('transactionType')=='debit'?'selected':'' }}>Debit</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Channel</label>
                <select name="channel" class="form-select form-select-sm">
                    <option value="">All Channels</option>
                    @foreach(['bank','mobile','atm','pos','online'] as $ch)
                    <option value="{{ $ch }}" {{ request('channel')==$ch?'selected':'' }}>{{ ucfirst($ch) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
                <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Clear filters"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Transactions Table --}}
<div class="card">
    <div class="card-header">
        <span>Transactions ({{ $transactions->total() }})</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Sender</th>
                        <th>Beneficiary</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Channel</th>
                        <th>Date & Time</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                    <tr>
                        <td>
                            <a href="{{ route('transactions.detail', $t->id) }}" class="fw-medium text-decoration-none font-monospace" style="font-size:12px">{{ $t->ref }}</a>
                        </td>
                        <td>
                            <div class="fw-medium" style="font-size:12.5px">{{ $t->sender_name }}</div>
                            <div class="font-monospace" style="font-size:10.5px;color:var(--text-muted)">{{ $t->sender_account_no }}</div>
                        </td>
                        <td>
                            <div class="fw-medium" style="font-size:12.5px">{{ $t->beneficiary_name }}</div>
                            <div class="font-monospace" style="font-size:10.5px;color:var(--text-muted)">{{ $t->beneficiary_account_no }}</div>
                        </td>
                        <td class="fw-bold" style="font-size:13px">{{ moneyFormat($t->amount) }}</td>
                        <td>
                            @if($t->transaction_type == 'credit')
                            <span class="badge" style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0"><i class="bi bi-arrow-down-left me-1" style="font-size:9px"></i>Credit</span>
                            @else
                            <span class="badge" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca"><i class="bi bi-arrow-up-right me-1" style="font-size:9px"></i>Debit</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-light" style="color:var(--text-secondary);font-size:10px">{{ ucfirst($t->channel) }}</span>
                        </td>
                        <td style="font-size:12px;color:var(--text-muted);white-space:nowrap">{{ $t->transaction_datetime->format('M d, Y · H:i') }}</td>
                        <td>
                            <a href="{{ route('transactions.detail', $t->id) }}" class="btn btn-outline-primary btn-action" data-bs-toggle="tooltip" title="View details"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
                                <div class="empty-state-title">No transactions found</div>
                                <div class="empty-state-text">Transactions will appear here once they are ingested via the API.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
</div>
@endsection

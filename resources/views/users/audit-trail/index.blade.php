@extends('layouts.app')
@section('title', 'Audit Trail')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">Audit Trail</span>
@endsection

@php
    // Fallback: if controller didn't pass $users, fetch them here
    if (!isset($users)) {
        $users = \App\Models\User::orderBy('name')->get(['id', 'name']);
    }
@endphp

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-journal-text" style="color:var(--green-600);opacity:.6"></i> Audit Trail</h4>
        <div class="heading-subtitle">Track all user actions and system events</div>
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
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search actions..." value="{{ request('search') }}" style="border-radius:0 10px 10px 0 !important">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">User</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="all">All Users</option>
                    @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
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
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="{{ route('audit-trail.index') }}" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Clear all"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Activity Log Table --}}
<div class="card">
    <div class="card-header">
        <span>Activity Log ({{ $activities->total() }})</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Subject</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activities as $a)
                    <tr>
                        <td style="font-size:12px;color:var(--text-muted);white-space:nowrap">
                            <i class="bi bi-clock me-1" style="opacity:.4"></i>{{ $a->created_at->format('M d, Y H:i:s') }}
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle" style="width:26px;height:26px;font-size:9px;border-radius:7px">{{ collect(explode(' ', $a->causer?->name ?? 'SY'))->map(fn($w) => strtoupper($w[0] ?? ''))->take(2)->join('') }}</div>
                                <span style="font-size:12.5px;font-weight:500">{{ $a->causer?->name ?? 'System' }}</span>
                            </div>
                        </td>
                        <td style="font-size:13px">{{ $a->description }}</td>
                        <td style="font-size:12px;color:var(--text-muted)">
                            @if($a->subject_type)
                                {{ class_basename($a->subject_type) }} #{{ $a->subject_id }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="bi bi-journal-text"></i></div>
                                <div class="empty-state-title">No activity logs</div>
                                <div class="empty-state-text">System activity will appear here as users interact with MoniSurv.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($activities->hasPages())
    <div class="card-footer">{{ $activities->links() }}</div>
    @endif
</div>
@endsection

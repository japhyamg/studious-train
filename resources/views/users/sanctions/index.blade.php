@extends('layouts.app')
@section('title', 'Sanction Lists')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">Sanction Lists</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-globe2" style="color:var(--green-600);opacity:.6"></i> Sanction Lists</h4>
        <div class="heading-subtitle">OFAC, UN and Nigerian sanctions sources with a full refresh audit trail</div>
    </div>
    <div class="page-heading-actions">
        <form method="POST" action="{{ route('sanctions.sync') }}" onsubmit="return startSync(this)">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Sync All Sources</button>
        </form>
    </div>
</div>

{{-- KPI row --}}
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Synced Entries</div>
            <div class="kpi-value">{{ number_format($totalRecords) }}</div>
            <div class="kpi-sub">Across all active sources</div>
            <div class="kpi-icon" style="background:var(--green-50);color:var(--green-600)"><i class="bi bi-database"></i></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Last Sync</div>
            <div style="font-size:22px;font-weight:800;letter-spacing:-.5px;color:var(--text-primary);line-height:1.1">{{ $lastSyncAt ? \Carbon\Carbon::parse($lastSyncAt)->diffForHumans() : 'Never' }}</div>
            <div class="kpi-sub">{{ $lastSyncAt ? \Carbon\Carbon::parse($lastSyncAt)->format('M d, Y · H:i') : 'Run your first sync' }}</div>
            <div class="kpi-icon" style="background:#eff6ff;color:#2563eb"><i class="bi bi-clock-history"></i></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Active Sources</div>
            <div class="kpi-value">{{ $activeSources }}<span style="font-size:16px;color:var(--text-muted);font-weight:600"> / {{ count($sources) }}</span></div>
            <div class="kpi-sub">Enabled &amp; configured</div>
            <div class="kpi-icon" style="background:#fffbeb;color:#b45309"><i class="bi bi-rss"></i></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Failed Today</div>
            <div style="font-size:30px;font-weight:800;letter-spacing:-.8px;line-height:1;color:{{ $todayFailed > 0 ? '#dc2626' : 'var(--text-primary)' }}">{{ $todayFailed }}</div>
            <div class="kpi-sub">{{ $todayFailed > 0 ? 'Check the sync log below' : 'All sources healthy' }}</div>
            <div class="kpi-icon" style="background:{{ $todayFailed > 0 ? '#fef2f2' : '#faf8f2' }};color:{{ $todayFailed > 0 ? '#dc2626' : '#8896a4' }}"><i class="bi {{ $todayFailed > 0 ? 'bi-exclamation-triangle' : 'bi-check-circle' }}"></i></div>
        </div>
    </div>
</div>

{{-- Sources --}}
<div class="row g-3 mb-4">
    @foreach($sources as $key => $source)
        @php
            $enabled = !empty($source['enabled']) && !empty($source['url']);
            $last = $latest[$key] ?? null;
            $icon  = match ($key) { 'ofac_consolidated' => 'bi-bank2', 'un_consolidated' => 'bi-globe-americas', default => 'bi-flag' };
            $short = match ($key) { 'ofac_consolidated' => 'OFAC', 'un_consolidated' => 'UN', default => 'Nigeria' };
        @endphp
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 {{ $enabled ? '' : 'card-no-hover' }}" style="{{ $enabled ? '' : 'opacity:.72' }}">
                <div class="card-header">
                    <span class="d-flex align-items-center gap-2">
                        <span class="avatar-circle"><i class="bi {{ $icon }}"></i></span>
                        <span>{{ $source['name'] }}</span>
                    </span>
                    @if($enabled && $last && $last->status === 'success')
                        <span class="badge badge-dot" style="background:var(--green-50);color:var(--green-700);border:1px solid var(--green-100)">Synced</span>
                    @elseif($enabled && $last && $last->status === 'failed')
                        <span class="badge badge-dot" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca">Failed</span>
                    @elseif($enabled)
                        <span class="badge badge-dot" style="background:#fffbeb;color:#b45309;border:1px solid #fde68a">Not synced</span>
                    @else
                        <span class="badge" style="background:#f1f0ee;color:#6b6860;border:1px solid #e4e3e0">Disabled</span>
                    @endif
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="font-monospace text-truncate mb-3" style="font-size:11px;color:var(--text-muted)" title="{{ $source['url'] ?? '' }}">
                        <i class="bi bi-link-45deg me-1"></i>{{ $source['url'] ?: 'No URL configured' }}
                    </div>

                    <div class="row g-2 text-center mb-3">
                        <div class="col-4">
                            <div class="p-2 rounded-3 h-100" style="background:#faf8f2;border:1px solid var(--border-light)">
                                <div class="kpi-label" style="margin-bottom:4px;font-size:9px">Records</div>
                                <div class="fw-bold" style="font-size:18px">{{ number_format($counts[$key] ?? 0) }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3 h-100" style="background:#faf8f2;border:1px solid var(--border-light)">
                                <div class="kpi-label" style="margin-bottom:4px;font-size:9px">Last Sync</div>
                                <div style="font-size:12px">{{ $last ? \Carbon\Carbon::parse($last->synced_at)->diffForHumans() : 'Never' }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3 h-100" style="background:#faf8f2;border:1px solid var(--border-light)">
                                <div class="kpi-label" style="margin-bottom:4px;font-size:9px">Version</div>
                                <div class="text-truncate" style="font-size:11px" title="{{ $last->version ?? '' }}">{{ $last->version ?? '—' }}</div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('sanctions.sync') }}" onsubmit="return startSync(this)" class="mt-auto">
                        @csrf
                        <input type="hidden" name="source" value="{{ $key }}">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100" {{ $enabled ? '' : 'disabled' }}>
                            <i class="bi bi-download me-1"></i>Refresh {{ $short }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Sync log --}}
<div class="card">
    <div class="card-header">
        <span><i class="bi bi-journal-text me-2"></i> Sync Log</span>
        <span class="badge badge-pill" style="background:#faf8f2;color:var(--text-muted);border:1px solid var(--border-light)">{{ $logs->total() }} entries</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Source</th><th>Status</th><th>Records</th><th>Version</th><th>Data Date</th><th>Synced At</th><th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @php $logIcon = match ($log->source) { 'ofac_consolidated' => 'bi-bank2', 'un_consolidated' => 'bi-globe-americas', default => 'bi-flag' }; @endphp
                                <i class="bi {{ $logIcon }}" style="color:var(--green-600);font-size:14px"></i>
                                <span class="fw-medium">{{ $log->source_label }}</span>
                            </div>
                        </td>
                        <td>
                            @if($log->status === 'success')
                                <span class="badge badge-dot" style="background:var(--green-50);color:var(--green-700);border:1px solid var(--green-100)">Success</span>
                            @else
                                <span class="badge badge-dot" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca">Failed</span>
                            @endif
                        </td>
                        <td class="font-monospace" style="font-size:12px">{{ number_format($log->record_count) }}</td>
                        <td class="text-truncate" style="font-size:11.5px;color:var(--text-muted);max-width:150px" title="{{ $log->version ?? '' }}">{{ $log->version ?? '—' }}</td>
                        <td style="font-size:12px;color:var(--text-muted)">{{ $log->last_updated?->format('M d, Y') ?? '—' }}</td>
                        <td style="font-size:12px;color:var(--text-muted)">{{ $log->synced_at?->format('M d, Y H:i') ?? '—' }}</td>
                        <td class="text-truncate" style="font-size:11.5px;color:var(--text-muted);max-width:220px" title="{{ $log->message ?? '' }}">{{ $log->message ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="bi bi-globe2"></i></div>
                                <div class="empty-state-title">No syncs yet</div>
                                <div class="empty-state-text">Click "Sync All Sources" to download the sanction lists and record the first audit entry.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
    <div class="card-footer">{{ $logs->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function startSync(form) {
    const btn = form.querySelector('button[type="submit"]');
    if (!btn) return true;
    btn.disabled = true;
    btn.dataset.original = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing…';
    return true;
}
</script>
@endpush

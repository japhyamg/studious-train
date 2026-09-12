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
        <div class="heading-subtitle">OFAC, UN Consolidated and Nigerian sanctions sources with refresh audit trail</div>
    </div>
    <div class="ms-auto">
        <form method="POST" action="{{ route('sanctions.sync') }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Sync All Sources</button>
        </form>
    </div>
</div>

<div class="row g-3">
    @foreach($sources as $key => $source)
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <div class="fw-bold" style="font-size:14px">{{ $source['name'] }}</div>
                    <div style="font-size:11px;color:var(--text-muted)" class="font-monospace text-truncate" title="{{ $source['url'] ?? 'No URL' }}">{{ $source['url'] ?: 'No URL configured' }}</div>
                </div>
                @if(!empty($source['enabled']) && !empty($source['url']))
                    <span class="badge bg-success" style="font-size:10px">Enabled</span>
                @else
                    <span class="badge bg-secondary" style="font-size:10px">Disabled</span>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3 text-center">
                    <div class="col-4"><div class="p-2 rounded-3" style="background:#faf8f2;border:1px solid var(--border-light)">
                        <div style="font-size:9px;color:var(--text-muted);text-transform:uppercase;font-weight:700">Records</div>
                        <div class="fw-bold" style="font-size:16px">{{ number_format($counts[$key] ?? 0) }}</div>
                    </div></div>
                    <div class="col-4"><div class="p-2 rounded-3" style="background:#faf8f2;border:1px solid var(--border-light)">
                        <div style="font-size:9px;color:var(--text-muted);text-transform:uppercase;font-weight:700">Last Sync</div>
                        <div style="font-size:12px">{{ isset($latest[$key]) ? \Carbon\Carbon::parse($latest[$key]->synced_at)->diffForHumans() : 'Never' }}</div>
                    </div></div>
                    <div class="col-4"><div class="p-2 rounded-3" style="background:#faf8f2;border:1px solid var(--border-light)">
                        <div style="font-size:9px;color:var(--text-muted);text-transform:uppercase;font-weight:700">Version</div>
                        <div class="text-truncate" style="font-size:11px" title="{{ $latest[$key]->version ?? '' }}">{{ $latest[$key]->version ?? '—' }}</div>
                    </div></div>
                </div>
                @php
                    $shortName = match ($key) {
                        'ofac_consolidated' => 'OFAC',
                        'un_consolidated' => 'UN',
                        default => 'Nigerian',
                    };
                @endphp
                <form method="POST" action="{{ route('sanctions.sync') }}">
                    @csrf
                    <input type="hidden" name="source" value="{{ $key }}">
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100" @if(empty($source['enabled']) || empty($source['url'])) disabled @endif>
                        <i class="bi bi-download me-1"></i>Refresh {{ $shortName }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card mt-3">
    <div class="card-header">
        <span><i class="bi bi-journal-text me-1"></i> Sync Log</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Source</th><th>Status</th><th>Records</th><th>Version</th><th>Last Updated</th><th>Synced At</th><th>Message</th></tr></thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td class="fw-medium">{{ $log->source_label }}</td>
                    <td>
                        @if($log->status === 'success')<span class="badge bg-success" style="font-size:10px">Success</span>
                        @else<span class="badge bg-danger" style="font-size:10px">Failed</span>@endif
                    </td>
                    <td class="font-monospace" style="font-size:12px">{{ number_format($log->record_count) }}</td>
                    <td class="text-truncate" style="font-size:11px;color:var(--text-muted);max-width:140px">{{ $log->version ?? '—' }}</td>
                    <td style="font-size:12px;color:var(--text-muted)">{{ $log->last_updated?->format('M d, Y') ?? '—' }}</td>
                    <td style="font-size:12px;color:var(--text-muted)">{{ $log->synced_at?->format('M d, Y H:i') ?? '—' }}</td>
                    <td class="text-truncate" style="font-size:11px;color:var(--text-muted);max-width:220px">{{ $log->message ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="7">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-globe2"></i></div>
                        <div class="empty-state-title">No syncs yet</div>
                        <div class="empty-state-text">Click "Sync All Sources" to download the sanction lists and record the first audit entry.</div>
                    </div>
                </td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
    @if($logs->hasPages())<div class="card-footer">{{ $logs->links() }}</div>@endif
</div>
@endsection

@extends('layouts.app')
@section('title', 'Cron Jobs')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">Cron Jobs</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-arrow-repeat" style="color:var(--green-600);opacity:.6"></i> Cron Job Manager</h4>
        <div class="heading-subtitle">Monitor and manually trigger scheduled background tasks</div>
    </div>
</div>

<div class="row g-3">
    @foreach($data as $key => $job)
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="fw-bold" style="font-size:14px">{{ $job['name'] }}</div>
                    <div style="font-size:11.5px;color:var(--text-muted)">{{ $job['description'] }}</div>
                </div>
                <span class="badge" style="background:#faf8f2;color:var(--text-secondary);font-size:10px;border:1px solid var(--border-light)"><i class="bi bi-clock me-1"></i>{{ $job['frequency'] }}</span>
            </div>
            <div class="card-body">
                {{-- Stats --}}
                @if(!empty($job['stats']))
                <div class="row g-2 mb-3">
                    @foreach($job['stats'] as $statLabel => $statValue)
                    <div class="col">
                        <div class="p-2 rounded-3 text-center" style="background:#faf8f2;border:1px solid var(--border-light)">
                            <div style="font-size:9px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;font-weight:700">{{ ucwords(str_replace('_', ' ', $statLabel)) }}</div>
                            <div class="fw-bold" style="font-size:18px;color:var(--text-primary)">{{ $statValue }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Run Button --}}
                <div class="d-flex gap-2 align-items-center">
                    <button class="btn btn-primary btn-sm" onclick="runCron('{{ $job['url'] }}', this)">
                        <i class="bi bi-play-fill me-1"></i>Run Now
                    </button>
                    <span class="cron-result" style="font-size:11px;color:var(--text-muted)"></span>
                </div>

                {{-- Crontab hint --}}
                <div class="mt-3 p-2 rounded-2 font-mono" style="background:#faf8f2;font-size:10.5px;color:var(--text-muted);border:1px solid var(--border-light)">
                    <i class="bi bi-terminal me-1" style="opacity:.5"></i>
                    @if($job['frequency'] === 'Daily')
                    0 1 * * * curl -s {{ $job['url'] }}
                    @elseif($job['frequency'] === 'Weekly')
                    0 2 * * 0 curl -s {{ $job['url'] }}
                    @else
                    */30 * * * * curl -s {{ $job['url'] }}
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- How CDD/EDD Reviews Work --}}
<div class="card mt-4">
    <div class="card-header"><span><i class="bi bi-info-circle me-2"></i> How CDD/EDD Review Scheduling Works</span></div>
    <div class="card-body" style="font-size:13px;line-height:1.8">
        <ol style="padding-left:18px">
            <li class="mb-2"><strong>Admin configures Risk Levels</strong> with a <code>Review Schedule (days)</code> value.
                <br><span style="color:var(--text-muted)">Example: Low = 365 days (CDD), Medium = 90 days (EDD), High = 30 days (EDD)</span></li>
            <li class="mb-2"><strong>Customers get risk-rated</strong> — either via the Risk Rating page or automatically for new customers via cron.
                <br><span style="color:var(--text-muted)">Each customer gets a <code>current_risk_level</code> and <code>next_review_date</code> calculated from their onboarding date.</span></li>
            <li class="mb-2"><strong>Daily cron job runs the Review Scheduler</strong> — it checks all customers:
                <ul class="mt-1">
                    <li>Past <code>next_review_date</code> → marked <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:9px;border:1px solid #fecaca">OVERDUE</span></li>
                    <li>Today = <code>next_review_date</code> → marked <span class="badge" style="background:#fef3c7;color:#b45309;font-size:9px;border:1px solid #fde68a">DUE</span></li>
                    <li>Newly rated with no schedule → calculates and sets <code>next_review_date</code></li>
                </ul>
            </li>
            <li class="mb-2"><strong>Compliance team reviews the "Reviews Due" dashboard</strong> and marks customers as reviewed.
                <br><span style="color:var(--text-muted)">On mark → <code>last_reviewed_at</code> = today, <code>next_review_date</code> recalculated for next cycle.</span></li>
        </ol>
        <div class="p-3 rounded-3" style="background:var(--green-50);border:1px solid var(--green-100);font-size:12.5px">
            <strong style="color:var(--green-800)">Example:</strong><br>
            Customer A onboarded <strong>Jan 1, 2026</strong>, risk level = <strong>Low</strong> (365-day cycle) → next review: <strong>Jan 1, 2027</strong><br>
            Customer B onboarded <strong>Jan 15, 2026</strong>, risk level = <strong>High</strong> (30-day cycle) → next review: <strong>Feb 14, 2026</strong><br>
            Customer B gets reviewed on Feb 14 → next review recalculated: <strong>Mar 16, 2026</strong> (30 days later)
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function runCron(url, btn) {
    const resultSpan = btn.closest('.d-flex').querySelector('.cron-result');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Running...';
    resultSpan.textContent = '';

    fetch(url)
        .then(r => r.json())
        .then(d => {
            resultSpan.innerHTML = '<i class="bi bi-check-circle-fill me-1" style="color:var(--green-600)"></i>' + (d.message || 'Done');
            resultSpan.style.color = 'var(--green-700)';
            setTimeout(() => location.reload(), 2000);
        })
        .catch(e => {
            resultSpan.innerHTML = '<i class="bi bi-x-circle-fill me-1" style="color:#dc2626"></i>Failed: ' + e.message;
            resultSpan.style.color = '#dc2626';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill me-1"></i>Run Now';
        });
}
</script>
@endpush

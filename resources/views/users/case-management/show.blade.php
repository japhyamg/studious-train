@extends('layouts.app')
@section('title', $case->slug)
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('case-management.index') }}">Cases</a><span class="sep">/</span>
    <span class="current">{{ $case->slug }}</span>
@endsection

@php
    $sourceColors = ['rule'=>'#145234','watchlist'=>'#b45309','risk_score'=>'#dc2626','ai_anomaly'=>'#7c3aed','peer_group'=>'#0284c7','manual'=>'#64748b'];
    $sourceIcons = ['rule'=>'bi-shield-check','watchlist'=>'bi-exclamation-diamond','risk_score'=>'bi-graph-up-arrow','ai_anomaly'=>'bi-cpu','peer_group'=>'bi-diagram-3','manual'=>'bi-person'];
    $src = $case->trigger_source ?? 'rule';
    $srcColor = $sourceColors[$src] ?? '#64748b';
    $statusColors = ['open'=>'primary','escalated'=>'warning','closed_filed'=>'success','closed_not_filed'=>'secondary'];
@endphp

@section('content')
<a href="{{ route('case-management.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Cases</a>

<div class="row g-4">
    {{-- ═══════════ MAIN COLUMN ═══════════ --}}
    <div class="col-lg-8">
        {{-- Case Header --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <h5 class="fw-bold mb-1" style="font-size:18px;letter-spacing:-.2px">{{ $case->slug }}</h5>
                        <p class="text-muted mb-0" style="font-size:12.5px">{{ $case->transaction_rule?->description ?? 'No description' }}</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge bg-{{ $statusColors[$case->status] ?? 'dark' }}" style="font-size:11px;padding:6px 14px;border-radius:8px">{{ \Illuminate\Support\Str::headline($case->status) }}</span>
                        <span class="badge" style="background:{{ $srcColor }}12;color:{{ $srcColor }};font-size:10.5px;border:1px solid {{ $srcColor }}25;padding:6px 12px;border-radius:8px">
                            <i class="bi {{ $sourceIcons[$src] ?? 'bi-flag' }} me-1"></i>{{ $case->trigger_label }}
                        </span>
                    </div>
                </div>
                <div class="row g-3">
                    @foreach([['Rule', $case->transaction_rule?->name ?? '—', 'bi-shield-check'], ['Account', $case->account_no, 'bi-credit-card-2-back'], ['Flagged Side', ucfirst($case->flagged_side ?? 'Transaction'), 'bi-arrow-left-right'], ['Report Type', $case->report_type, 'bi-file-earmark-text']] as [$label, $value, $icon])
                    <div class="col-sm-3">
                        <div style="font-size:10px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</div>
                        <div class="fw-medium {{ $label=='Account' ? 'font-monospace' : '' }}" style="font-size:13px">@if($label=='Report Type')<span class="badge badge-green">{{ $value }}</span>@else {{ $value }} @endif</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Trigger Details --}}
        @if($case->trigger_details)
        <div class="card mb-4">
            <div class="card-header"><span><i class="bi {{ $sourceIcons[$src] ?? 'bi-flag' }} me-2" style="color:{{ $srcColor }}"></i> Trigger Details</span></div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($case->trigger_details as $key => $val)
                        @if(!is_array($val))
                        <div class="col-sm-4">
                            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:3px">{{ ucwords(str_replace('_', ' ', $key)) }}</div>
                            <div style="font-size:13px;font-weight:500">{{ $val }}</div>
                        </div>
                        @endif
                    @endforeach
                </div>
                @if(isset($case->trigger_details['breakdown']))
                <div class="divider"></div>
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:10px">Score Breakdown</div>
                @foreach($case->trigger_details['breakdown'] as $factor => $detail)
                <div class="d-flex justify-content-between align-items-center py-2" style="font-size:12.5px;border-bottom:1px solid var(--border-light)">
                    <span>{{ is_array($detail) ? ($detail['factor'] ?? $factor) : $factor }}</span>
                    <span class="badge badge-green" style="font-size:10px">+{{ is_array($detail) ? ($detail['score'] ?? '—') : $detail }}</span>
                </div>
                @endforeach
                @endif
            </div>
        </div>
        @endif

        {{-- Transaction Details --}}
        @if($transaction)
        <div class="card mb-4">
            <div class="card-header"><span><i class="bi bi-arrow-left-right me-2"></i> Transaction Details</span></div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-sm-4">
                        <div style="font-size:10px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.5px">Reference</div>
                        <div class="font-monospace" style="font-size:12px">{{ $transaction->transaction_ref ?? $transaction->ref ?? '—' }}</div>
                    </div>
                    <div class="col-sm-4">
                        <div style="font-size:10px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.5px">Amount</div>
                        <div class="fw-bold" style="font-size:20px;color:var(--green-700)">{{ moneyFormat($transaction->amount) }}</div>
                    </div>
                    <div class="col-sm-4">
                        <div style="font-size:10px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.5px">Type / Channel</div>
                        <div style="font-size:13px">{{ ucfirst($transaction->transaction_type) }} / {{ ucfirst($transaction->channel) }}</div>
                    </div>
                </div>

                {{-- Sender / Beneficiary side-by-side --}}
                <div class="row g-0 rounded-3 overflow-hidden" style="border:1.5px solid var(--border)">
                    @foreach(['sender' => ['arrow-up-right', $transaction->sender_name, $transaction->sender_account_no, $transaction->sender_bvn ?? null], 'beneficiary' => ['arrow-down-left', $transaction->beneficiary_name, $transaction->beneficiary_account_no, $transaction->beneficiary_bvn ?? null]] as $side => [$arrow, $name, $acct, $bvn])
                    <div class="col-md-6 p-3" style="background:{{ ($case->flagged_side ?? '') === $side ? '#fef2f2' : '#faf8f2' }};{{ $side === 'beneficiary' ? 'border-left:1.5px solid var(--border)' : '' }}">
                        <div class="d-flex align-items-center gap-2 mb-2" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:{{ ($case->flagged_side ?? '') === $side ? '#dc2626' : 'var(--text-muted)' }}">
                            <i class="bi bi-{{ $arrow }}"></i>{{ strtoupper($side) }}
                            @if(($case->flagged_side ?? '') === $side)<span class="badge bg-danger" style="font-size:8px;padding:2px 6px">FLAGGED</span>@endif
                            @if(isset($customerContexts[$side]))<span class="badge" style="background:#f0fdf4;color:#16a34a;font-size:8px;padding:2px 6px;border:1px solid #bbf7d0">Customer</span>@endif
                        </div>
                        <div class="fw-semibold" style="font-size:14px">{{ $name }}</div>
                        <div class="font-monospace" style="font-size:11.5px;color:var(--text-muted)">{{ $acct }}</div>
                        @if($bvn)<div style="font-size:10.5px;color:var(--text-muted);margin-top:2px">BVN: {{ $bvn }}</div>@endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- ═══════════ CUSTOMER INTELLIGENCE (summary) ═══════════ --}}
        @if(!empty($customerContexts))
        <div class="card mb-4">
            <div class="card-header">
                <span><i class="bi bi-person-badge me-2"></i> Customer Intelligence</span>
                <span class="badge badge-green badge-pill" style="font-size:10px">{{ count($customerContexts) }} customer{{ count($customerContexts) > 1 ? 's' : '' }}</span>
            </div>
            <div class="card-body">
                {{-- Tabs if both sides are customers --}}
                @if(count($customerContexts) > 1)
                <ul class="nav nav-tabs mb-3" role="tablist">
                    @foreach($customerContexts as $side => $ctx)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#intel-{{ $side }}" type="button" role="tab">
                            <i class="bi bi-{{ $side === 'sender' ? 'arrow-up-right' : 'arrow-down-left' }} me-1"></i>
                            {{ ucfirst($side) }} — {{ $ctx['customer']->name }}
                            @if($ctx['is_flagged'])<span class="badge bg-danger ms-1" style="font-size:8px">FLAGGED</span>@endif
                        </button>
                    </li>
                    @endforeach
                </ul>
                @endif

                <div class="tab-content">
                @foreach($customerContexts as $side => $ctx)
                @php
                    $cust = $ctx['customer'];
                    $stats = $ctx['txn_stats'];
                    $rl = $cust->current_risk_level;
                    $rlColors = ['low'=>['#f0fdf4','#16a34a','#bbf7d0'],'medium'=>['#fef3c7','#b45309','#fde68a'],'high'=>['#fef2f2','#dc2626','#fecaca']];
                    $rlc = $rlColors[strtolower($rl ?? '')] ?? ['#faf8f2','#94a3b8','#e4e8f0'];
                @endphp
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="intel-{{ $side }}" role="tabpanel">
                    {{-- Customer header with link --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-circle" style="width:36px;height:36px;font-size:12px;border-radius:10px">{{ strtoupper(substr($cust->first_name,0,1).substr($cust->last_name,0,1)) }}</div>
                            <div>
                                <div class="fw-bold" style="font-size:14px">{{ $cust->name }}</div>
                                <div class="d-flex gap-2 align-items-center" style="font-size:11px;color:var(--text-muted)">
                                    <span class="font-monospace">{{ $cust->account_number }}</span>
                                    <span>·</span>
                                    <span>{{ ucfirst($cust->customer_type ?? '—') }}</span>
                                    <span>·</span>
                                    <span>{{ ucfirst($cust->state_of_residence ?? '—') }}</span>
                                    @if($cust->isPep === 'yes' || $cust->isPep == 1)
                                    <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:8px;border:1px solid #fecaca">PEP</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('customers.show', $cust->id) }}" class="btn btn-sm btn-outline-primary" style="font-size:11px"><i class="bi bi-eye me-1"></i>Full Profile</a>
                    </div>

                    {{-- Summary Stats --}}
                    <div class="row g-2 mb-3">
                        @foreach([
                            ['Credit', $stats['credit_count'], '₦'.number_format($stats['credit_value'],0), 'var(--green-50)', 'var(--green-700)', 'var(--green-100)'],
                            ['Debit', $stats['debit_count'], '₦'.number_format($stats['debit_value'],0), '#fef2f2', '#dc2626', '#fecaca'],
                            ['Txns', $stats['total_count'], '', '#eff6ff', '#2563eb', '#bfdbfe'],
                            ['STR', $ctx['str_count'], '', '#fef3c7', '#b45309', '#fde68a'],
                            ['CTR', $ctx['ctr_count'], '', '#faf5ff', '#7c3aed', '#e9d5ff'],
                            ['Risk', ucfirst($rl ?? 'N/A'), $cust->current_risk_score ?? '—', $rlc[0], $rlc[1], $rlc[2]],
                        ] as [$lbl, $val, $sub, $bg, $clr, $bdr])
                        <div class="col-4 col-md-2">
                            <div class="p-2 rounded-3 text-center" style="background:{{ $bg }};border:1px solid {{ $bdr }}">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted)">{{ $lbl }}</div>
                                <div style="font-size:14px;font-weight:800;color:{{ $clr }}">{{ $val }}</div>
                                @if($sub)<div style="font-size:9px;color:var(--text-muted)">{{ $sub }}</div>@endif
                            </div>
                        </div>
                        @endforeach
                    </div>

                    {{-- Prior Cases (compact) --}}
                    @if($ctx['prior_cases']->count() > 0)
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:6px"><i class="bi bi-clock-history me-1"></i>Prior Cases ({{ $ctx['total_cases'] - 1 }})</div>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        @foreach($ctx['prior_cases'] as $pc)
                        <a href="{{ route('case-management.show', $pc->slug) }}" class="badge text-decoration-none badge-status-{{ $pc->status }}" style="font-size:9px" data-bs-toggle="tooltip" title="{{ $pc->transaction_rule?->name ?? '' }} · {{ \Illuminate\Support\Str::headline($pc->classification) }}">{{ $pc->slug }}</a>
                        @endforeach
                    </div>
                    @else
                    <div style="font-size:11px;color:var(--text-muted)"><i class="bi bi-check-circle me-1" style="color:var(--green-600)"></i>No prior cases for this customer.</div>
                    @endif
                </div>
                @endforeach
                </div>
            </div>
        </div>
        @endif


        {{-- Comments & Activity --}}
        <div class="card">
            <div class="card-header"><span><i class="bi bi-chat-text me-2"></i> Comments & Activity</span></div>
            <div class="card-body">
                @forelse($case->comments as $comment)
                <div class="d-flex gap-3 mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="avatar-circle" style="width:32px;height:32px;font-size:10px;flex-shrink:0;border-radius:10px">{{ $comment->user?->initials ?? '?' }}</div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold" style="font-size:13px">{{ $comment->user?->name ?? 'System' }}</span>
                            <span style="font-size:10.5px;color:var(--text-muted)">{{ $comment->created_at->diffForHumans() }}</span>
                        </div>
                        <div style="font-size:13px;margin-top:3px;color:var(--text-secondary);line-height:1.6">{{ $comment->comment }}</div>
                    </div>
                </div>
                @empty
                <div class="text-center py-3">
                    <i class="bi bi-chat-text" style="font-size:28px;color:#d0d4dc;display:block;margin-bottom:8px"></i>
                    <span style="color:var(--text-muted);font-size:12.5px">No comments yet.</span>
                </div>
                @endforelse

                @if(!in_array($case->status, ['closed_filed', 'closed_not_filed']))
                <div class="divider"></div>
                <form method="POST" action="{{ route('case-management.update', $case->slug) }}">
                    @csrf @method('PUT')
                    <div class="mb-3"><textarea name="comment" class="form-control" rows="3" required placeholder="Add a comment or note..."></textarea></div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" name="action" value="open" class="btn btn-sm btn-primary"><i class="bi bi-chat me-1"></i>Comment</button>
                        @can('case-close')
                        <button type="submit" name="action" value="escalated" class="btn btn-sm btn-warning"><i class="bi bi-arrow-up me-1"></i>Escalate</button>
                        <button type="submit" name="action" value="closed_filed" class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i>Close (Filed)</button>
                        <button type="submit" name="action" value="closed_not_filed" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Close (Not Filed)</button>
                        @endcan
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════════ RIGHT SIDEBAR ═══════════ --}}
    <div class="col-lg-4">
        {{-- Export --}}
        @if(in_array($case->status, ['escalated', 'closed_filed']))
        <div class="card mb-4">
            <div class="card-header"><span><i class="bi bi-download me-2"></i> Export Report</span></div>
            <div class="card-body">
                <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px">Generate report for filing with NFIU.</p>
                <form method="POST" action="{{ route('case-management.export') }}">@csrf
                    <input type="hidden" name="selected_cases" value="{{ $case->id }}">
                    <div class="d-flex flex-column gap-2">
                        <button type="submit" name="format" value="XML" class="btn btn-sm btn-primary w-100"><i class="bi bi-file-earmark-code me-1"></i> Export XML (goAML)</button>
                        <button type="submit" name="format" value="CSV" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-filetype-csv me-1"></i> Export CSV</button>
                        <button type="submit" name="format" value="Excel" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Excel</button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- Classification --}}
        <div class="card mb-4">
            <div class="card-header"><span><i class="bi bi-toggle-on me-2"></i> Classification</span></div>
            <div class="card-body text-center">
                <div class="p-3 rounded-3 mb-3" style="background:{{ $case->classification == 'false_positive' ? '#f8f8f6' : 'var(--green-50)' }};border:1px solid {{ $case->classification == 'false_positive' ? '#e4e3e0' : 'var(--green-100)' }}">
                    <span style="font-size:14px;font-weight:700;color:{{ $case->classification == 'false_positive' ? '#6b6860' : 'var(--green-800)' }}">{{ \Illuminate\Support\Str::headline($case->classification) }}</span>
                </div>
                <button class="btn btn-sm btn-outline-secondary" id="toggleClassBtn"><i class="bi bi-arrow-repeat me-1"></i> Toggle</button>
            </div>
        </div>

        {{-- Account Interdiction --}}
        <div class="card mb-4">
            <div class="card-header"><span><i class="bi bi-lock me-2"></i> Account Interdiction</span></div>
            <div class="card-body text-center">
                @php $frozen = $case->interdiction_status === \App\Models\FlaggedCase::INTERDICTION_FROZEN; @endphp
                <div class="p-3 rounded-3 mb-3" style="background:{{ $frozen ? '#fef2f2' : '#f8f8f6' }};border:1px solid {{ $frozen ? '#fecaca' : '#e4e3e0' }}">
                    <div style="font-size:13px;font-weight:700;color:{{ $frozen ? '#dc2626' : '#6b6860' }}">{{ $frozen ? 'Account Frozen' : 'No Interdiction' }}</div>
                    @if($case->interdicted_at)
                    <div style="font-size:10.5px;color:var(--text-muted);margin-top:4px">{{ $case->interdicted_at->format('M d, Y H:i') }} · {{ $case->interdictingUser?->name ?? 'System' }}</div>
                    @endif
                </div>
                <button class="btn btn-sm {{ $frozen ? 'btn-outline-secondary' : 'btn-outline-danger' }}" id="interdictBtn">
                    <i class="bi {{ $frozen ? 'bi-unlock' : 'bi-lock' }} me-1"></i>{{ $frozen ? 'Lift Freeze' : 'Freeze Account' }}
                </button>
                <p style="font-size:10px;color:var(--text-muted);margin:10px 0 0">Post-facto flag — no real-time core-banking integration yet (CBN 5.3(a)(viii)).</p>
            </div>
        </div>

        {{-- NFIU Indicator --}}
        <div class="card mb-4">
            <div class="card-header"><span><i class="bi bi-flag me-2"></i> NFIU Indicator</span></div>
            <div class="card-body">
                @if($case->nfiu_indicator)
                <div class="p-2 rounded-2 mb-2" style="background:var(--green-50);border:1px solid var(--green-100)">
                    <span class="badge badge-green" style="font-size:10.5px;padding:4px 10px">{{ $case->nfiu_indicator->code }} — {{ \Illuminate\Support\Str::limit($case->nfiu_indicator->description, 80) }}</span>
                </div>
                @else
                <p style="color:var(--text-muted);font-size:12px;margin-bottom:10px">No indicator assigned</p>
                @endif
                <select class="form-select form-select-sm" id="nfiuSelect">
                    <option value="">Select indicator...</option>
                    @foreach($nfiu_indicators as $ind)<option value="{{ $ind->id }}" {{ $case->indicator_id == $ind->id ? 'selected' : '' }}>{{ $ind->code }} — {{ \Illuminate\Support\Str::limit($ind->description, 60) }}</option>@endforeach
                </select>
            </div>
        </div>

        {{-- Timeline --}}
        <div class="card mb-4">
            <div class="card-header"><span><i class="bi bi-clock-history me-2"></i> Timeline</span></div>
            <div class="card-body">
                <div class="timeline-item">
                    <div class="timeline-dot"><i class="bi bi-plus"></i></div>
                    <div class="timeline-content"><div class="timeline-title">Case Created</div><div class="timeline-time">{{ $case->created_at->format('M d, Y · H:i') }}</div></div>
                </div>
                @if($case->updated_at != $case->created_at)
                <div class="timeline-item">
                    <div class="timeline-dot" style="background:#eff6ff;color:#2563eb;border-color:#bfdbfe"><i class="bi bi-pencil"></i></div>
                    <div class="timeline-content"><div class="timeline-title">Last Updated</div><div class="timeline-time">{{ $case->updated_at->format('M d, Y · H:i') }}</div></div>
                </div>
                @endif
            </div>
        </div>

        {{-- Reviewer --}}
        <div class="card">
            <div class="card-header"><span><i class="bi bi-person-check me-2"></i> Assigned Reviewer</span></div>
            <div class="card-body">
                @if($case->reviewer)
                <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:#faf8f2;border:1px solid var(--border-light)">
                    <div class="avatar-circle" style="width:36px;height:36px;font-size:12px;border-radius:10px">{{ $case->reviewer->initials }}</div>
                    <div><div style="font-size:13px;font-weight:600">{{ $case->reviewer->name }}</div><div style="font-size:11px;color:var(--text-muted)">{{ $case->reviewer->email }}</div></div>
                </div>
                @else
                <div class="text-center py-3"><i class="bi bi-person-x" style="font-size:24px;color:#d0d4dc;display:block;margin-bottom:6px"></i><span style="color:var(--text-muted);font-size:12px">Unassigned</span></div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('toggleClassBtn')?.addEventListener('click', () => {
    fetch('{{ route("case-management.toogle-class", $case->slug) }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'}})
    .then(r => r.json()).then(d => { if (d.status === 'success') location.reload(); });
});
document.getElementById('nfiuSelect')?.addEventListener('change', function() {
    if (!this.value) return;
    fetch('{{ route("case-management.set-nfiu-indicator", $case->slug) }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'}, body:JSON.stringify({indicator_id: this.value})})
    .then(r => r.json()).then(d => { if (d.status === 'success') location.reload(); });
});
document.getElementById('interdictBtn')?.addEventListener('click', () => {
    if (!confirm('Toggle account interdiction for this case?')) return;
    fetch('{{ route("case-management.interdict", $case->slug) }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'}})
    .then(r => r.json()).then(d => { if (d.status === 'success') location.reload(); });
});
</script>
@endpush

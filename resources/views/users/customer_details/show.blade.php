@extends('layouts.app')
@section('title', $customer->name)
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('customers.all') }}">Customers</a><span class="sep">/</span>
    <span class="current">{{ $customer->name }}</span>
@endsection

@php
    $rl = $customer->current_risk_level;
    $riskColors = [
        'low' => ['#f0fdf4', '#16a34a', '#bbf7d0'],
        'medium' => ['#fef3c7', '#b45309', '#fde68a'],
        'high' => ['#fef2f2', '#dc2626', '#fecaca'],
    ];
    $rc = $riskColors[strtolower($rl ?? '')] ?? ['#faf8f2', '#94a3b8', '#e4e8f0'];
@endphp

@section('content')
<a href="{{ route('customers.all') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Customers</a>

{{-- Global customer search + export (CBN 5.9(a)(vi)) --}}
<div class="card mb-3">
    <div class="card-body py-3 d-flex flex-wrap gap-3 align-items-center justify-content-between">
        <form method="GET" action="{{ route('customers.search') }}" class="d-flex gap-2" style="flex:1;min-width:260px">
            <div class="input-group input-group-sm">
                <span class="input-group-text" style="background:#faf8f2;border-color:var(--border)"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search any customer — name, account, BVN, NIN…" required>
            </div>
            <button class="btn btn-primary btn-sm">Find</button>
        </form>
        <div class="d-flex gap-2">
            <a href="{{ route('customers.show.export', ['id' => $customer->id, 'format' => 'csv']) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
            <a href="{{ route('customers.show.export', ['id' => $customer->id, 'format' => 'pdf']) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
        </div>
    </div>
</div>

{{-- Customer Header --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="avatar-circle" style="width:60px;height:60px;font-size:20px;border-radius:16px">{{ strtoupper(substr($customer->first_name,0,1) . substr($customer->last_name,0,1)) }}</div>
            <div class="flex-grow-1">
                <h5 class="fw-bold mb-1" style="font-size:18px;letter-spacing:-.2px">{{ $customer->name }}</h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="font-monospace" style="font-size:12px;color:var(--text-muted)">{{ $customer->account_number }}</span>
                    <span class="badge" style="background:{{ $rc[0] }};color:{{ $rc[1] }};font-size:10px;border:1px solid {{ $rc[2] }}">{{ ucfirst($rl ?? 'Not Rated') }}</span>
                    @if($customer->isPep === 'yes' || $customer->isPep == 1)
                    <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:10px;border:1px solid #fecaca">PEP</span>
                    @endif
                    <span class="badge" style="background:#faf8f2;color:var(--text-secondary);font-size:10px;border:1px solid var(--border-light)">{{ ucfirst($customer->customer_type ?? '—') }}</span>
                    {{-- Watchlist standing (CBN 5.3(a)(v)) --}}
                    @if(!empty($watchlist['internal']))
                    <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:10px;border:1px solid #fecaca"><i class="bi bi-shield-exclamation me-1"></i>Internal Watchlist</span>
                    @endif
                    @if(!empty($watchlist['nibss']))
                        @if($watchlist['nibss'] === 'delisted')
                        <span class="badge" style="background:#f1f0ee;color:#6b6860;font-size:10px;border:1px solid #e4e3e0"><i class="bi bi-shield-check me-1"></i>NIBSS Delisted</span>
                        @else
                        <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:10px;border:1px solid #fecaca"><i class="bi bi-shield-exclamation me-1"></i>NIBSS Watchlisted</span>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Quick Stats Row --}}
        <div class="row g-3">
            <div class="col-6 col-md-2">
                <div class="p-3 rounded-3 text-center" style="background:var(--green-50);border:1px solid var(--green-100)">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted)">Credit Vol</div>
                    <div style="font-size:18px;font-weight:800;color:var(--green-700)">{{ number_format($txnStats['credit_count']) }}</div>
                    <div style="font-size:10px;color:var(--text-muted)">₦{{ number_format($txnStats['credit_value'], 2) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-3 rounded-3 text-center" style="background:#fef2f2;border:1px solid #fecaca">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted)">Debit Vol</div>
                    <div style="font-size:18px;font-weight:800;color:#dc2626">{{ number_format($txnStats['debit_count']) }}</div>
                    <div style="font-size:10px;color:var(--text-muted)">₦{{ number_format($txnStats['debit_value'], 2) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-3 rounded-3 text-center" style="background:#eff6ff;border:1px solid #bfdbfe">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted)">Total Txns</div>
                    <div style="font-size:18px;font-weight:800;color:#2563eb">{{ number_format($txnStats['total_count']) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-3 rounded-3 text-center" style="background:#fef3c7;border:1px solid #fde68a">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted)">STR Filed</div>
                    <div style="font-size:18px;font-weight:800;color:#b45309">{{ $strCount }}</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-3 rounded-3 text-center" style="background:#faf5ff;border:1px solid #e9d5ff">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted)">CTR Filed</div>
                    <div style="font-size:18px;font-weight:800;color:#7c3aed">{{ $ctrCount }}</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-3 rounded-3 text-center" style="background:{{ $rc[0] }};border:1px solid {{ $rc[2] }}">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted)">Risk Score</div>
                    <div style="font-size:18px;font-weight:800;color:{{ $rc[1] }}">{{ $customer->current_risk_score ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Left Column: Profile + KYC --}}
    <div class="col-lg-4">
        {{-- Personal Information --}}
        <div class="card mb-4">
            <div class="card-header"><span><i class="bi bi-person me-2"></i> Personal Information</span></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" style="font-size:12.5px">
                    <tr><td style="color:var(--text-muted);width:40%;padding:10px 16px">Full Name</td><td style="padding:10px 16px" class="fw-medium">{{ $customer->name }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Gender</td><td style="padding:10px 16px">{{ ucfirst($customer->gender ?? '—') }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Date of Birth</td><td style="padding:10px 16px">{{ $customer->date_of_birth?->format('M d, Y') ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Phone</td><td style="padding:10px 16px">{{ $customer->phone_number ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Email</td><td style="padding:10px 16px">{{ $customer->email ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Address</td><td style="padding:10px 16px">{{ $customer->address ?? '—' }}</td></tr>
                </table>
            </div>
        </div>

        {{-- KYC / KYB Information --}}
        <div class="card mb-4">
            <div class="card-header"><span><i class="bi bi-shield-check me-2"></i> KYC / KYB Information</span></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" style="font-size:12.5px">
                    <tr><td style="color:var(--text-muted);width:40%;padding:10px 16px">BVN</td><td style="padding:10px 16px" class="font-monospace">{{ $customer->bvn ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">NIN</td><td style="padding:10px 16px" class="font-monospace">{{ $customer->nin ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Account Type</td><td style="padding:10px 16px">{{ ucfirst($customer->account_type ?? '—') }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Customer Type</td><td style="padding:10px 16px">{{ ucfirst($customer->customer_type ?? '—') }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Tier Level</td><td style="padding:10px 16px">{{ $customer->tier_level ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Occupation</td><td style="padding:10px 16px">{{ ucfirst($customer->occupation ?? '—') }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Employer</td><td style="padding:10px 16px">{{ $customer->employer_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Source of Funds</td><td style="padding:10px 16px">{{ ucfirst($customer->source_of_funds ?? '—') }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Income Range</td><td style="padding:10px 16px">{{ $customer->income_range ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Business Activity</td><td style="padding:10px 16px">{{ ucfirst($customer->business_activity ?? '—') }}</td></tr>
                    <tr>
                        <td style="color:var(--text-muted);padding:10px 16px">PEP Status</td>
                        <td style="padding:10px 16px">
                            @if($customer->isPep === 'yes' || $customer->isPep == 1)
                            <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:10px;border:1px solid #fecaca">Yes — Politically Exposed</span>
                            @else
                            <span class="badge" style="background:#f0fdf4;color:#16a34a;font-size:10px;border:1px solid #bbf7d0">No</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- Geography --}}
        <div class="card mb-4">
            <div class="card-header"><span><i class="bi bi-geo-alt me-2"></i> Geography</span></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" style="font-size:12.5px">
                    <tr><td style="color:var(--text-muted);width:40%;padding:10px 16px">State</td><td style="padding:10px 16px">{{ ucfirst($customer->state_of_residence ?? '—') }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">LGA</td><td style="padding:10px 16px">{{ ucfirst($customer->local_govt_area ?? '—') }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:10px 16px">Onboarded</td><td style="padding:10px 16px">{{ $customer->date_onboarded?->format('M d, Y') ?? '—' }}</td></tr>
                </table>
            </div>
        </div>

        {{-- Risk & Compliance --}}
        <div class="card">
            <div class="card-header"><span><i class="bi bi-graph-up-arrow me-2"></i> Risk & Compliance</span></div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3 text-center" style="background:{{ $rc[0] }};border:1px solid {{ $rc[2] }}">
                            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:4px">Risk Level</div>
                            <span class="badge" style="background:{{ $rc[0] }};color:{{ $rc[1] }};border:1px solid {{ $rc[2] }};font-size:12px">{{ ucfirst($rl ?? 'Not Rated') }}</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 text-center" style="background:#faf8f2;border:1px solid var(--border-light)">
                            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:4px">Risk Score</div>
                            <div class="fw-bold" style="font-size:20px">{{ $customer->current_risk_score ?? '—' }}</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--border-light);font-size:12.5px">
                    <span style="color:var(--text-muted)">STR Reports</span>
                    <span class="fw-bold" style="color:#b45309">{{ $strCount }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--border-light);font-size:12.5px">
                    <span style="color:var(--text-muted)">CTR Reports</span>
                    <span class="fw-bold" style="color:#7c3aed">{{ $ctrCount }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--border-light);font-size:12.5px">
                    <span style="color:var(--text-muted)">Total Cases</span>
                    <span class="fw-bold">{{ $totalCases }}</span>
                </div>

                {{-- Risk Level Change History (CBN 5.4(a)(v)) --}}
                @if($riskChanges->isNotEmpty())
                <div class="mt-3">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:6px">Risk Level Changes</div>
                    @foreach($riskChanges as $change)
                    <div class="d-flex align-items-center gap-2 py-1" style="font-size:12px;border-bottom:1px dashed var(--border-light)">
                        <span class="badge" style="background:#faf8f2;color:var(--text-secondary);border:1px solid var(--border-light);font-size:10px">{{ ucfirst($change->from_level ?? '—') }}</span>
                        <i class="bi bi-arrow-right" style="font-size:10px;color:var(--text-muted)"></i>
                        <span class="badge" style="background:{{ $riskColors[strtolower($change->to_level ?? '')][0] ?? '#faf8f2' }};color:{{ $riskColors[strtolower($change->to_level ?? '')][1] ?? '#94a3b8' }};border:1px solid {{ $riskColors[strtolower($change->to_level ?? '')][2] ?? '#e4e8f0' }};font-size:10px">{{ ucfirst($change->to_level ?? '—') }}</span>
                        <span style="color:var(--text-muted);font-size:10px">{{ $change->created_at?->format('M d, Y') }} · score {{ $change->score }} · {{ str_replace('_', ' ', $change->driver) }}</span>
                    </div>
                    @endforeach
                </div>
                @endif

                @if($customer->next_review_date)
                <div class="mt-3 p-3 rounded-3 d-flex align-items-center gap-2" style="background:{{ $customer->review_status === 'overdue' ? '#fef2f2' : 'var(--green-50)' }};border:1px solid {{ $customer->review_status === 'overdue' ? '#fecaca' : 'var(--green-100)' }};font-size:12px">
                    <i class="bi bi-calendar-check" style="color:{{ $customer->review_status === 'overdue' ? '#dc2626' : 'var(--green-700)' }}"></i>
                    <div>
                        Next review: <strong>{{ $customer->next_review_date->format('M d, Y') }}</strong>
                        @if($customer->review_status === 'overdue')
                        <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:9px;border:1px solid #fecaca;margin-left:4px">OVERDUE</span>
                        @elseif($customer->review_status === 'due')
                        <span class="badge" style="background:#fef3c7;color:#b45309;font-size:9px;border:1px solid #fde68a;margin-left:4px">DUE</span>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Right Column: Transactions + Cases --}}
    <div class="col-lg-8">
        {{-- Transaction History --}}
        <div class="card mb-4">
            <div class="card-header">
                <span><i class="bi bi-arrow-left-right me-2"></i> Transaction History</span>
                <span class="badge badge-green badge-pill">{{ $txns->total() }} transactions</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive"><table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Ref</th><th>Direction</th><th>Amount</th><th>Counterparty</th><th>Channel</th><th>Date</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($txns as $t)
                        @php
                            $isSender = $t->sender_account_no === $customer->account_number;
                            $counterparty = $isSender ? $t->beneficiary_name : $t->sender_name;
                            $counterpartyAcct = $isSender ? $t->beneficiary_account_no : $t->sender_account_no;
                        @endphp
                        <tr>
                            <td class="font-monospace" style="font-size:11px">
                                <a href="{{ route('transactions.detail', $t->id) }}" class="text-decoration-none">{{ \Illuminate\Support\Str::limit($t->transaction_ref ?? $t->ref, 18) }}</a>
                            </td>
                            <td>
                                @if($isSender)
                                <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:10px;border:1px solid #fecaca"><i class="bi bi-arrow-up-right me-1" style="font-size:9px"></i>Outgoing</span>
                                @else
                                <span class="badge" style="background:#f0fdf4;color:#16a34a;font-size:10px;border:1px solid #bbf7d0"><i class="bi bi-arrow-down-left me-1" style="font-size:9px"></i>Incoming</span>
                                @endif
                            </td>
                            <td class="fw-bold" style="font-size:13px">{{ moneyFormat($t->amount) }}</td>
                            <td>
                                <div style="font-size:12.5px;font-weight:500">{{ $counterparty }}</div>
                                <div class="font-monospace" style="font-size:10px;color:var(--text-muted)">{{ $counterpartyAcct }}</div>
                            </td>
                            <td><span class="badge bg-light" style="color:var(--text-secondary);font-size:10px">{{ ucfirst($t->channel) }}</span></td>
                            <td style="font-size:11px;color:var(--text-muted);white-space:nowrap">{{ $t->transaction_datetime->format('M d, Y H:i') }}</td>
                            <td><a href="{{ route('transactions.detail', $t->id) }}" class="btn btn-outline-primary btn-action"><i class="bi bi-eye"></i></a></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state" style="padding:32px">
                                    <div class="empty-state-icon"><i class="bi bi-arrow-left-right"></i></div>
                                    <div class="empty-state-title">No transactions</div>
                                    <div class="empty-state-text">No transactions found for this customer.</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
            @if($txns->hasPages())<div class="card-footer">{{ $txns->links() }}</div>@endif
        </div>

        {{-- Flagged Cases (via transaction relationship) --}}
        @if($cases->total() > 0)
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-exclamation-triangle me-2" style="color:#b45309"></i> Flagged Cases</span>
                <div class="d-flex gap-2">
                    <span class="badge" style="background:#fef3c7;color:#b45309;font-size:9px;border:1px solid #fde68a">STR: {{ $strCount }}</span>
                    <span class="badge" style="background:#faf5ff;color:#7c3aed;font-size:9px;border:1px solid #e9d5ff">CTR: {{ $ctrCount }}</span>
                    <span class="badge badge-green badge-pill" style="font-size:9px">Total: {{ $cases->total() }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive"><table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Case ID</th><th>Rule</th><th>Report</th><th>Status</th><th>Classification</th><th>Date</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($cases as $c)
                        <tr>
                            <td><a href="{{ route('case-management.show', $c->slug) }}" class="fw-semibold text-decoration-none" style="font-size:12.5px">{{ $c->slug }}</a></td>
                            <td style="font-size:12px;color:var(--text-muted)">{{ $c->transaction_rule?->name ?? '—' }}</td>
                            <td>
                                <span class="badge" style="background:{{ $c->report_type === 'CTR' ? '#faf5ff' : '#fef3c7' }};color:{{ $c->report_type === 'CTR' ? '#7c3aed' : '#b45309' }};font-size:9px;border:1px solid {{ $c->report_type === 'CTR' ? '#e9d5ff' : '#fde68a' }}">{{ $c->report_type }}</span>
                            </td>
                            <td><span class="badge badge-status-{{ $c->status }}">{{ \Illuminate\Support\Str::headline($c->status) }}</span></td>
                            <td>
                                @if($c->classification == 'false_positive')
                                <span class="badge" style="background:#f1f0ee;color:#6b6860;font-size:10px;border:1px solid #e4e3e0">False Positive</span>
                                @else
                                <span class="badge badge-green" style="font-size:10px">{{ \Illuminate\Support\Str::headline($c->classification) }}</span>
                                @endif
                            </td>
                            <td style="font-size:11px;color:var(--text-muted);white-space:nowrap">{{ $c->created_at->format('M d, Y') }}</td>
                            <td><a href="{{ route('case-management.show', $c->slug) }}" class="btn btn-outline-primary btn-action"><i class="bi bi-eye"></i></a></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table></div>
            </div>
            @if($cases->hasPages())<div class="card-footer">{{ $cases->links() }}</div>@endif
        </div>
        @endif
    </div>
</div>
@endsection

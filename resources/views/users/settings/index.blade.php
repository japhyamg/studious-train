@extends('layouts.app')
@section('title', 'System Settings')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">System Settings</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-gear" style="color:var(--green-600);opacity:.6"></i> System Settings</h4>
        <div class="heading-subtitle">Configure system-wide preferences, API credentials, and data sync</div>
    </div>
</div>

<div class="row g-4">
    {{-- General Settings --}}
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><span><i class="bi bi-gear me-2"></i> General Settings</span></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-3" style="border-bottom:1px solid var(--border-light)">
                    <div>
                        <div class="fw-semibold" style="font-size:13px">Case Notification</div>
                        <div style="font-size:11.5px;color:var(--text-muted)">Send notifications when cases are created</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" {{ ($case_notification ?? '') == 'true' ? 'checked' : '' }} onchange="toggleSetting('case_notification', this.checked ? 'true' : 'false')">
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-3" style="border-bottom:1px solid var(--border-light)">
                    <div>
                        <div class="fw-semibold" style="font-size:13px">Two-Step Verification</div>
                        <div style="font-size:11.5px;color:var(--text-muted)">Require email OTP on login</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" {{ ($two_step_verification ?? '') == 'true' ? 'checked' : '' }} onchange="toggleSetting('two_step_verification', this.checked ? 'true' : 'false')">
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label">Case Prefix</label>
                    <div class="input-group" style="max-width:280px">
                        <input type="text" class="form-control form-control-sm" value="{{ $case_prefix ?? 'CASE' }}" id="casePrefix" placeholder="CASE">
                        <button class="btn btn-primary btn-sm" onclick="window.location='{{ route('settings') }}?case_prefix='+document.getElementById('casePrefix').value"><i class="bi bi-check-lg"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- API Keys --}}
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><span><i class="bi bi-key me-2"></i> API Keys</span></div>
            <div class="card-body">
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:16px">Generate API credentials for external integrations.</p>
                <div class="mb-3">
                    <label class="form-label">Client ID</label>
                    <input type="text" class="form-control form-control-sm" value="{{ getBusinessDetails('client_id') ?? 'Not generated' }}" readonly style="background:#faf8f2">
                </div>
                <div class="mb-3">
                    <label class="form-label">Client Secret</label>
                    <input type="text" class="form-control form-control-sm" value="{{ getBusinessDetails('client_secret') ? '••••••••••••••••' : 'Not generated' }}" readonly style="background:#faf8f2">
                </div>
                <button class="btn btn-warning btn-sm" onclick="if(confirm('Generate new API keys? This will invalidate existing keys.'))fetch('{{ route('generate-api-keys') }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}).then(()=>location.reload())">
                    <i class="bi bi-arrow-repeat me-1"></i>Generate New Keys
                </button>
            </div>
        </div>
    </div>

    {{-- Customer Data Sync --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-arrow-repeat me-2"></i> Customer Data Synchronisation</span>
                @php $lastSync = settings('customer_sync_last_run'); @endphp
                @if($lastSync)
                <span class="badge badge-green badge-pill" style="font-size:10px">Last sync: {{ \Carbon\Carbon::parse($lastSync)->diffForHumans() }}</span>
                @else
                <span class="badge" style="background:#fef3c7;color:#b45309;font-size:10px;border:1px solid #fde68a">Never synced</span>
                @endif
            </div>
            <div class="card-body">
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:20px">
                    Connect to a secondary database (core banking system, KYC platform) to periodically sync customer data.
                    The sync runs as a cron job at the configured interval and will create new customers or update existing ones based on account number matching.
                </p>

                <form method="POST" action="{{ route('settings.customer-sync') }}">
                    @csrf
                    <div class="row g-3">
                        {{-- Connection --}}
                        <div class="col-12"><h6 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:0">Database Connection</h6></div>
                        <div class="col-md-4">
                            <label class="form-label">Host</label>
                            <input type="text" name="customer_sync_host" class="form-control form-control-sm" value="{{ settings('customer_sync_host', env('SECONDARY_DB_HOST', '127.0.0.1')) }}" placeholder="127.0.0.1">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Port</label>
                            <input type="text" name="customer_sync_port" class="form-control form-control-sm" value="{{ settings('customer_sync_port', env('SECONDARY_DB_PORT', '3306')) }}" placeholder="3306">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Database Name</label>
                            <input type="text" name="customer_sync_database" class="form-control form-control-sm" value="{{ settings('customer_sync_database', env('SECONDARY_DB_DATABASE', '')) }}" placeholder="core_banking_db">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="customer_sync_username" class="form-control form-control-sm" value="{{ settings('customer_sync_username', env('SECONDARY_DB_USERNAME', 'root')) }}" placeholder="root">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="customer_sync_password" class="form-control form-control-sm" value="{{ settings('customer_sync_password', '') }}" placeholder="••••••••">
                        </div>

                        {{-- Table Mapping --}}
                        <div class="col-12 mt-3"><h6 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:0">Source Table Configuration</h6></div>
                        <div class="col-md-3">
                            <label class="form-label">Source Table Name</label>
                            <input type="text" name="customer_sync_table" class="form-control form-control-sm" value="{{ settings('customer_sync_table', 'customers') }}" placeholder="customers">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Account Number Field</label>
                            <input type="text" name="customer_sync_account_field" class="form-control form-control-sm" value="{{ settings('customer_sync_account_field', 'account_number') }}" placeholder="account_number">
                            <div class="form-text">Column used to match/link customers</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Updated At Field</label>
                            <input type="text" name="customer_sync_updated_at_field" class="form-control form-control-sm" value="{{ settings('customer_sync_updated_at_field', 'updated_at') }}" placeholder="updated_at">
                            <div class="form-text">For incremental sync (leave blank for full sync)</div>
                        </div>

                        {{-- Schedule --}}
                        <div class="col-12 mt-3"><h6 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:0">Sync Schedule</h6></div>
                        <div class="col-md-3">
                            <label class="form-label">Sync Interval (hours)</label>
                            <select name="customer_sync_interval_hours" class="form-select form-select-sm">
                                @foreach([1, 2, 4, 6, 8, 12, 24, 48, 72] as $h)
                                <option value="{{ $h }}" {{ settings('customer_sync_interval_hours', 24) == $h ? 'selected' : '' }}>Every {{ $h }} {{ $h === 1 ? 'hour' : 'hours' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Batch Size</label>
                            <input type="number" name="customer_sync_batch_size" class="form-control form-control-sm" value="{{ settings('customer_sync_batch_size', 500) }}" placeholder="500" min="50" max="5000">
                            <div class="form-text">Records per batch (50–5000)</div>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i> Save Sync Settings</button>
                        <a href="{{ route('cron-job.customer-sync') }}" class="btn btn-outline-primary btn-sm" onclick="return confirm('Run customer sync now?')">
                            <i class="bi bi-play-fill me-1"></i> Run Sync Now
                        </a>
                    </div>
                </form>

                {{-- Column Mapping Reference --}}
                <div class="mt-4 p-3 rounded-3" style="background:#faf8f2;border:1px solid var(--border-light)">
                    <h6 style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:10px"><i class="bi bi-info-circle me-1"></i> Default Column Mapping</h6>
                    <p style="font-size:11.5px;color:var(--text-muted);margin-bottom:8px">
                        The sync expects these column names in the source table. If your source uses different names, the mapping can be customised via the <code>customer_sync_column_mapping</code> setting (JSON).
                    </p>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach(['first_name','middle_name','last_name','account_number','date_of_birth','bvn','nin','gender','customer_type','account_type','tier_level','state_of_residence','local_govt_area','date_onboarded','occupation','source_of_funds','income_range','business_activity','employer_name','phone_number','email','address'] as $col)
                        <span class="badge" style="background:#fff;color:var(--text-secondary);font-size:10px;border:1px solid var(--border)">{{ $col }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Governance (CTR / Filing SLA / Retention / Maker-Checker) --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header"><span><i class="bi bi-shield-lock me-2"></i> Governance & Reporting</span></div>
            <div class="card-body">
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:20px">
                    STR filing SLA, audit retention, and the maker-checker disposition flow (CBN 5.7 / 5.8 / 5.9).
                    CTR amount thresholds are managed on the <a href="{{ route('transactions.risk-scoring-config') }}">Risk Scoring</a> page
                    (<code>TRANSACTION_AMOUNT</code> for individuals, <code>TRANSACTION_AMOUNT_CORPORATE</code> for corporates).
                </p>
                <form method="POST" action="{{ route('settings.governance') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">STR Filing SLA (days)</label>
                            <input type="number" name="str_filing_sla_days" class="form-control form-control-sm" min="1" value="{{ settings('str_filing_sla_days', config('governance.filing.str_sla_days', 5)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Audit Retention (days)</label>
                            <input type="number" name="audit_retention_days" class="form-control form-control-sm" min="1" value="{{ settings('audit_retention_days', config('governance.audit.retention_days', 1825)) }}">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="d-flex align-items-center gap-2 pb-2">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="maker_checker_enabled" value="1" {{ settingBool('maker_checker_enabled', config('governance.maker_checker.enabled', true)) ? 'checked' : '' }}>
                                </div>
                                <span style="font-size:12.5px">Maker-checker</span>
                            </div>
                        </div>
                    </div>
                    <div class="divider"></div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i> Save Governance Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleSetting(a, v) {
    fetch('{{ route("settings-toggle-update") }}', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json'},
        body: JSON.stringify({action: a, value: v})
    });
}
</script>
@endpush

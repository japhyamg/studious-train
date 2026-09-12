@extends('layouts.app')
@section('title', 'P.A.S Screening')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">P.A.S Screening</span>
@endsection

@push('styles')
<style>
    .pas-left{background:#0d3b26;min-height:100%;border-radius:10px 0 0 10px}
    .pas-brand-dot{width:7px;height:7px;border-radius:50%;background:#3ecf8e;display:inline-block}
    .pas-entity-btn{background:rgba(0,0,0,.2);border:1.5px solid rgba(255,255,255,.1);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;border-radius:7px;cursor:pointer;transition:.15s;font-family:'Inter',sans-serif;padding:8px;text-align:center;flex:1}
    .pas-entity-btn:hover{border-color:rgba(255,255,255,.2);color:rgba(255,255,255,.7)}
    .pas-entity-btn.active{border-color:#3ecf8e;background:rgba(62,207,142,.08);color:#3ecf8e}
    .pas-inp{background:rgba(0,0,0,.2)!important;border:1.5px solid rgba(255,255,255,.1)!important;color:#e2e8f0!important;border-radius:7px!important;font-size:13px}
    .pas-inp:focus{border-color:#3ecf8e!important;box-shadow:none!important}
    .pas-inp::placeholder{color:rgba(255,255,255,.3)!important}
    .pas-field-label{font-size:10px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:rgba(255,255,255,.5)}
    .pas-preview{background:rgba(0,0,0,.2);border:1px dashed rgba(255,255,255,.1)!important;border-radius:7px;font-size:12px;color:rgba(255,255,255,.4);padding:8px 12px}
    .pas-submit{background:#3ecf8e;border:none;border-radius:8px;font-size:13px;font-weight:600;color:#0d3b26;cursor:pointer;width:100%;padding:11px;display:flex;align-items:center;justify-content:center;gap:7px;transition:.15s}
    .pas-submit:hover{background:#2db87a}
    .pas-submit:disabled{background:rgba(255,255,255,.1);color:rgba(255,255,255,.3);cursor:not-allowed}
    .pas-spin{width:14px;height:14px;border:2px solid rgba(255,255,255,.25);border-top-color:#0d3b26;border-radius:50%;animation:_spin .7s linear infinite;display:none;flex-shrink:0}
    @keyframes _spin{to{transform:rotate(360deg)}}
    .pas-right{background:#f8fafc}
    .pas-hidden-radio{position:absolute;opacity:0;pointer-events:none}
    /* Report card */
    .pas-rhead{background:#0d3b26;position:relative;overflow:hidden}
    .pas-reyebrow{font-size:9px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;color:#3ecf8e;display:flex;align-items:center;gap:6px}
    .pas-reyebrow::before{content:'';width:14px;height:1px;background:#3ecf8e}
    .pas-rname{font-size:20px;font-weight:700;color:#f1f5f9}
    .pas-rslug{font-size:10px;color:rgba(255,255,255,.4);font-family:monospace}
    .pas-rmeta-chip{display:inline-flex;align-items:center;gap:4px;font-size:10px;padding:3px 8px;border-radius:5px;background:rgba(255,255,255,.08);color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.1)}
    .pas-risk-row{display:flex;align-items:center;justify-content:space-between;padding:11px 22px;border-bottom:1px solid #dee2e6}
    .pas-risk-lbl{font-size:9px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;margin-bottom:3px}
    .pas-risk-val{font-size:18px;font-weight:700;display:flex;align-items:center;gap:7px}
    .pas-rdot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
    .pas-sum-cell{border-right:1px solid #dee2e6}.pas-sum-cell:last-child{border-right:0}
    .pas-sum-lbl{font-size:9px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:#94a3b8;margin-bottom:4px}
    .pas-sum-num{font-size:22px;font-weight:700;line-height:1}
    .pas-sec{border:1px solid #dee2e6;border-radius:9px;overflow:hidden}
    .pas-sec-head{background:#f8f9fa;cursor:pointer;user-select:none;transition:background .12s;border-bottom:1px solid #dee2e6;display:flex;align-items:center;justify-content:space-between;padding:12px}
    .pas-sec-head:hover{background:#f1f3f5}
    .pas-si{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
    .pas-si-pep{background:#eff6ff;color:#3b82f6}.pas-si-san{background:#fff1f2;color:#f43f5e}.pas-si-med{background:#fffbeb;color:#f59e0b}
    .pas-sec-title{font-size:13px;font-weight:600;color:#1e293b}.pas-sec-sub{font-size:11px;color:#94a3b8}
    .pas-badge-hit{background:#fee2e2;color:#dc2626;border:1px solid #fecaca;font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;padding:3px 9px;border-radius:5px}
    .pas-badge-ok{background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0;font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;padding:3px 9px;border-radius:5px}
    .pas-pep-card{border-bottom:1px solid #f1f5f9;padding:8px 0}.pas-pep-card:last-child{border-bottom:0}.pas-pep-card:hover{background:#f8fafc}
    .pas-pep-link{font-size:13px;font-weight:500;color:#3b82f6;text-decoration:none}.pas-pep-link:hover{text-decoration:underline}
    .pas-pep-snip{font-size:11px;color:#64748b;line-height:1.6}
    .pas-kw{font-size:10px;padding:2px 6px;border-radius:4px;background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe}
    .pas-cf{font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px}
    .pas-cf.HIGH{background:#fee2e2;color:#dc2626}.pas-cf.MEDIUM{background:#fef3c7;color:#d97706}.pas-cf.LOW{background:#f1f5f9;color:#64748b}
    .pas-san-name{font-size:13px;font-weight:600;color:#1e293b}
    .pas-san-prog{font-size:10px;padding:2px 6px;border-radius:4px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca}
    .pas-med-link{font-size:12px;font-weight:500;color:#1e293b;text-decoration:none}.pas-med-link:hover{color:#3b82f6}
    .pas-sec-empty i{font-size:20px;display:block}
    .pas-rfooter{background:#f8fafc;border-top:1px solid #dee2e6}
    .pas-rfooter span{font-size:10px;color:#94a3b8;font-family:monospace}
    .pas-print-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;background:#fff;border:1.5px solid #dee2e6;border-radius:7px;font-size:12px;font-weight:500;color:#6c757d;cursor:pointer;transition:.15s}
    .pas-print-btn:hover{border-color:#3ecf8e;color:#145234}
</style>
@endpush

@section('content')
<div class="card border-0 shadow-sm overflow-hidden" id="pas-screening-section">
    <div class="row g-0 align-items-start">
        {{-- Left panel (dark) --}}
        <div class="col-12 col-lg-4" style="position:sticky;top:54px;align-self:flex-start">
            <div class="card-body pas-left p-4 d-flex flex-column" style="min-height:600px">
                <div class="d-flex align-items-center gap-2 mb-4">
                    <span class="pas-brand-dot"></span>
                    <div>
                        <div class="fw-bold text-white" style="font-size:14px;letter-spacing:.3px">P.A.S Screening</div>
                        <div style="font-size:9px;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.4)">Compliance Intelligence</div>
                    </div>
                </div>

                <div class="fw-bold text-white lh-sm mb-1" style="font-size:20px">Entity<br>Screening</div>
                <p style="font-size:12px;color:rgba(255,255,255,.4);line-height:1.6" class="mb-4">Search for PEP, sanctions exposure and adverse media across global sources.</p>

                {{-- Entity type toggle --}}
                <div class="d-flex gap-2 mb-3">
                    <input type="radio" name="entity_type" id="pas-ind" value="individual" onchange="pasSetEntity(this, 'individual')" class="pas-hidden-radio" checked>
                    <label for="pas-ind" id="pas-btn-ind" class="pas-entity-btn active"><i class="bi bi-person me-1"></i> Individual</label>
                    <input type="radio" name="entity_type" id="pas-co" value="company" onchange="pasSetEntity(this, 'company')" class="pas-hidden-radio">
                    <label for="pas-co" id="pas-btn-co" class="pas-entity-btn"><i class="bi bi-building me-1"></i> Company</label>
                </div>

                {{-- Individual fields --}}
                <div id="pas-name-fields">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="pas-field-label mb-1">First name</div>
                            <input class="pas-inp form-control" id="first_name" placeholder="e.g. John" oninput="pasSync()">
                        </div>
                        <div class="col-6">
                            <div class="pas-field-label mb-1">Last name</div>
                            <input class="pas-inp form-control" id="last_name" placeholder="e.g. Doe" oninput="pasSync()">
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="pas-field-label">Middle name</span>
                            <span style="font-size:10px;color:rgba(255,255,255,.3)">optional</span>
                        </div>
                        <input class="pas-inp form-control" id="middle_name" placeholder="e.g. Collin" oninput="pasSync()">
                    </div>
                </div>

                {{-- Company field --}}
                <div id="pas-company-field" class="mb-2 d-none">
                    <div class="pas-field-label mb-1">Company name</div>
                    <input class="pas-inp form-control" id="company_name" placeholder="e.g. Acme Holdings Ltd">
                </div>

                {{-- Preview --}}
                <div class="pas-preview mb-1">
                    <i class="bi bi-search me-1" style="font-size:11px"></i>
                    <span id="pas-preview-text">Enter a name above...</span>
                </div>

                {{-- Advanced toggle --}}
                <div class="d-flex align-items-center gap-2 mt-3 mb-0" style="cursor:pointer;user-select:none" onclick="pasToggleAdv()">
                    <div style="height:1px;background:rgba(255,255,255,.1);flex:1"></div>
                    <span style="font-size:11px;color:rgba(255,255,255,.4);white-space:nowrap">Advanced filters</span>
                    <i class="bi bi-chevron-down" id="pas-adv-chev" style="font-size:11px;color:rgba(255,255,255,.4);transition:transform .2s"></i>
                    <div style="height:1px;background:rgba(255,255,255,.1);flex:1"></div>
                </div>

                {{-- Advanced panel --}}
                <div class="collapse" id="pas-adv-panel">
                    <div class="row g-2 mt-1">
                        <div class="col-6"><div class="pas-field-label mb-1">Gender</div><select class="pas-inp form-select" id="gender"><option value="">Any</option><option>Male</option><option>Female</option></select></div>
                        <div class="col-6"><div class="pas-field-label mb-1">Date of birth</div><input type="date" class="pas-inp form-control" id="birthdate"></div>
                        <div class="col-6"><div class="pas-field-label mb-1">Country</div><select class="pas-inp form-select" id="country"><option value="">Any</option><option>Nigeria</option><option>Ghana</option><option>Kenya</option><option>South Africa</option><option>United States</option><option>United Kingdom</option></select></div>
                        <div class="col-6"><div class="pas-field-label mb-1">RC Number</div><input class="pas-inp form-control" id="rc_number" placeholder="Optional"></div>
                    </div>
                </div>

                {{-- Submit --}}
                <button class="pas-submit mt-3" id="pas-submit-btn" onclick="submitSearch()">
                    <div class="pas-spin" id="pas-spin"></div>
                    <i class="bi bi-shield-check" id="pas-submit-icon" style="color:#0d3b26"></i>
                    <span id="pas-submit-text">Run Screening</span>
                </button>
            </div>
        </div>

        {{-- Right panel (light) --}}
        <div class="col-12 col-lg-8 pas-right p-4 d-flex flex-column" style="min-height:600px;max-height:90vh;overflow-y:auto">
            {{-- Empty state --}}
            <div class="flex-fill d-flex flex-column align-items-center justify-content-center text-center opacity-50" id="pas-empty">
                <i class="bi bi-shield" style="font-size:40px;color:#cbd5e1;margin-bottom:12px"></i>
                <h6 style="font-size:15px;color:#94a3b8">No screening performed</h6>
                <p style="font-size:12px;color:#94a3b8;margin:0">Enter an entity name on the left<br>and run a screening to see results.</p>
            </div>

            {{-- Report (populated by JS) --}}
            <div id="pas-report" class="d-none">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="d-flex align-items-center gap-2" style="font-size:12px;color:#94a3b8"><i class="bi bi-file-earmark-text"></i> Screening Report</span>
                    <button class="pas-print-btn" onclick="pasPrint()"><i class="bi bi-printer"></i> Print / PDF</button>
                </div>
                <div class="card border" id="pas-rcard" style="border-radius:12px;overflow:hidden">
                    {{-- populated by renderScreeningResult() --}}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Hidden iframe for printing --}}
<iframe id="pas-print-frame" style="display:none;position:absolute;width:0;height:0;border:0"></iframe>
@endsection

@push('scripts')
<script>
    let screenUrl = "{{ route('pas.screen') }}";
    let csrfToken = "{{ csrf_token() }}";
    let resultData;
</script>
<script src="{{ asset('utils/users/pas.js') }}"></script>
@endpush

@extends('layouts.app')
@section('title', 'Transaction Risk Scoring')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('transactions.index') }}">Transactions</a><span class="sep">/</span>
    <span class="current">Risk Scoring Config</span>
@endsection
@section('page-title', 'Transaction Risk Scoring Configuration')

@php $threshold = settings('risk_scoring_threshold', 100); @endphp

@section('content')

{{-- Threshold + Summary --}}
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="kpi-card" style="border-left:3px solid var(--green-700)">
            <div class="kpi-label">Alert Threshold</div>
            <div class="kpi-value">{{ $threshold }}</div>
            <div class="kpi-sub">Transactions scoring ≥ this value will trigger an alert.</div>
            <form method="POST" action="{{ route('transactions.risk-scoring-config.update-threshold') }}" class="mt-3 d-flex gap-2">
                @csrf
                <input type="number" name="threshold" class="form-control form-control-sm" style="width:100px" value="{{ $threshold }}" min="1" required>
                <button type="submit" class="btn btn-primary btn-sm">Update</button>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="kpi-card">
            <div class="kpi-label">Total Weight (Active)</div>
            <div class="kpi-value">{{ $total_threshold }}</div>
            <div class="kpi-sub">Sum of all active factor weights</div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="kpi-card">
            <div class="kpi-label">Active Factors</div>
            <div class="kpi-value">{{ $data->where('is_active', true)->count() }} <span style="font-size:14px;color:var(--text-muted)">/ {{ $data->count() }}</span></div>
            <div class="kpi-sub">factors enabled</div>
        </div>
    </div>
</div>

{{-- Factors Table (full width) --}}
<div class="card">
    <div class="card-header">
        <span><i class="bi bi-sliders me-1"></i> Risk Scoring Factors</span>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addConfigModal"><i class="bi bi-plus-lg me-1"></i>Add Risk Factor</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Factor</th><th>Checks Against</th><th>Conditions</th><th>Weight</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($data as $config)
                @php
                    $cond = is_array($config->conditions) ? $config->conditions : json_decode($config->conditions, true);
                    $cond = is_array($cond) ? $cond : [];
                    $logic = strtoupper($cond['logic'] ?? 'AND');
                    $condList = (isset($cond['conditions']) && is_array($cond['conditions'])) ? $cond['conditions'] : [$cond];
                    $checkTypes = array_unique(array_map(fn($c) => ($c['check_type'] ?? 'transaction'), $condList));
                @endphp
                <tr>
                    <td>
                        <div class="fw-medium" style="font-size:11px;font-family:monospace">{{ $config->factor_name }}</div>
                        <div style="font-size:11px;color:var(--text-muted)">{{ $config->factor_description }}</div>
                    </td>
                    <td>
                        @foreach($checkTypes as $ct)
                        <span class="badge {{ $ct == 'customer' ? 'bg-info' : ($ct == 'behaviour' ? 'bg-warning text-dark' : 'bg-primary') }} bg-opacity-15" style="color:{{ $ct == 'customer' ? '#0891b2' : ($ct == 'behaviour' ? '#b45309' : 'var(--green-700)') }};font-size:10px">{{ ucfirst($ct) }}</span>
                        @endforeach
                    </td>
                    <td style="font-size:11px">
                        @foreach($condList as $i => $c)
                            @if($i > 0)<div style="font-size:9px;color:var(--text-muted);font-weight:700;margin:2px 0">{{ $logic }}</div>@endif
                            <div>
                                <span class="text-muted">{{ $c['check_type'] ?? 'transaction' }}:</span>
                                {{ $c['field'] ?? '—' }} {{ $c['operator'] ?? '—' }} {{ $c['value'] ?? '' }}
                                @if(!empty($c['window_days']))<span class="text-muted" style="font-size:10px">(last {{ $c['window_days'] }}d)</span>@endif
                            </div>
                        @endforeach
                    </td>
                    <td><span class="badge" style="background:var(--green-700);color:#fff">{{ $config->weight }}</span></td>
                    <td><span class="badge {{ $config->is_active ? 'bg-success' : 'bg-secondary' }}" style="font-size:10px">{{ $config->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td>
                        <button class="btn btn-outline-primary btn-action" data-bs-toggle="modal" data-bs-target="#editConfigModal"
                            data-id="{{ $config->id }}"
                            data-name="{{ $config->factor_name }}"
                            data-desc="{{ $config->factor_description }}"
                            data-weight="{{ $config->weight }}"
                            data-active="{{ $config->is_active ? '1' : '0' }}"
                            data-logic="{{ $logic }}"
                            data-conditions="{{ json_encode($condList) }}"
                        ><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-outline-danger btn-action" onclick="deleteConfig({{ $config->id }})"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-4 text-muted">No factors configured. Click "Add Risk Factor" to create one.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
</div>

{{-- Add Risk Factor Modal --}}
<div class="modal fade" id="addConfigModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" action="{{ route('transactions.risk-scoring-config.store') }}" id="addConfigForm">
        @csrf
        <div class="modal-header"><h6 class="modal-title">Add Risk Factor</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Factor Name *</label>
                    <input type="text" name="factor_name" class="form-control form-control-sm" required placeholder="e.g. HIGH_FREQUENCY_TRANSACTION">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description *</label>
                    <input type="text" name="factor_description" class="form-control form-control-sm" required placeholder="e.g. More than 20 transactions in 7 days">
                </div>

                <div class="col-12" style="border-top:1px dashed var(--border-light);padding-top:14px">
                    <div class="fw-semibold" style="font-size:12.5px"><i class="bi bi-funnel me-1" style="color:var(--green-600)"></i>Condition 1</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Checks Against *</label>
                    <select name="check_type" class="form-select form-select-sm check-type-select" required onchange="onCheckTypeChange(this, this.closest('form'))">
                        <option value="">— Select —</option>
                        <option value="transaction">Transaction Data</option>
                        <option value="customer">Customer Data</option>
                        <option value="behaviour">Behaviour / Derived (activity pattern)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Field to Check *</label>
                    <select name="check_field" class="form-select form-select-sm check-field-select" required>
                        <option value="">— Select "Checks Against" first —</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Condition *</label>
                    <select name="condition_operator" class="form-select form-select-sm">
                        <option value="greater_than">Greater Than</option>
                        <option value="equal_to">Equal To</option>
                        <option value="not_equal_to">Not Equal To</option>
                        <option value="less_than">Less Than</option>
                        <option value="greater_than_equal">≥</option>
                        <option value="less_than_equal">≤</option>
                        <option value="contains">Contains</option>
                        <option value="is_true">Is True/Yes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Value *</label>
                    <input type="text" name="condition_value" class="form-control form-control-sm" placeholder="e.g. 20 or 1000000 or high">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Weight (Points) *</label>
                    <input type="number" name="weight" class="form-control form-control-sm" required min="1" placeholder="e.g. 15">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Match Logic</label>
                    <select name="logic" class="form-select form-select-sm">
                        <option value="AND">ALL (AND)</option>
                        <option value="OR">ANY (OR)</option>
                    </select>
                </div>
                <div class="col-md-4 window-days-wrapper d-none">
                    <label class="form-label">Window (days)</label>
                    <input type="number" name="window_days" class="form-control form-control-sm" min="1" max="365" value="7">
                    <div class="form-text" style="font-size:10px">Look back period for behaviour checks</div>
                </div>
                <div class="col-md-8 d-flex align-items-end">
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" name="is_active" id="newIsActive" checked>
                        <label class="form-check-label" for="newIsActive" style="font-size:12px">Active</label>
                    </div>
                </div>

                <div class="col-12" style="border-top:1px dashed var(--border-light);padding-top:14px">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold" style="font-size:12.5px"><i class="bi bi-plus-circle me-1" style="color:var(--green-600)"></i>Additional Conditions <span class="text-muted fw-normal">(optional)</span></span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addConditionRow(document.getElementById('addExtraConditions'))"><i class="bi bi-plus-lg me-1"></i>Add Condition</button>
                    </div>
                    <div id="addExtraConditions"></div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check me-1"></i>Add Factor</button>
        </div>
    </form>
</div></div></div>

{{-- Edit Modal — full form matching create --}}
<div class="modal fade" id="editConfigModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" id="editConfigForm">@csrf @method('PUT')
        <div class="modal-header"><h6 class="modal-title">Edit Risk Factor</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Factor Name</label>
                    <input type="text" id="edit_config_name" class="form-control form-control-sm bg-light" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description *</label>
                    <input type="text" name="factor_description" id="edit_config_desc" class="form-control form-control-sm" required>
                </div>

                <div class="col-12" style="border-top:1px dashed var(--border-light);padding-top:14px">
                    <div class="fw-semibold" style="font-size:12.5px"><i class="bi bi-funnel me-1" style="color:var(--green-600)"></i>Condition 1</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Checks Against *</label>
                    <select name="check_type" id="edit_check_type" class="form-select form-select-sm check-type-select" required onchange="onCheckTypeChange(this, document.getElementById('editConfigForm'))">
                        <option value="">— Select —</option>
                        <option value="transaction">Transaction Data</option>
                        <option value="customer">Customer Data</option>
                        <option value="behaviour">Behaviour / Derived (activity pattern)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Field to Check *</label>
                    <select name="check_field" id="edit_check_field" class="form-select form-select-sm check-field-select" required>
                        <option value="">— Select —</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Condition *</label>
                    <select name="condition_operator" id="edit_operator" class="form-select form-select-sm">
                        <option value="greater_than">Greater Than</option>
                        <option value="equal_to">Equal To</option>
                        <option value="not_equal_to">Not Equal To</option>
                        <option value="less_than">Less Than</option>
                        <option value="greater_than_equal">≥</option>
                        <option value="less_than_equal">≤</option>
                        <option value="contains">Contains</option>
                        <option value="is_true">Is True/Yes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Value *</label>
                    <input type="text" name="condition_value" id="edit_cond_value" class="form-control form-control-sm" placeholder="e.g. 20">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Weight (Points) *</label>
                    <input type="number" name="weight" id="edit_config_weight" class="form-control form-control-sm" required min="1">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Match Logic</label>
                    <select name="logic" id="edit_logic" class="form-select form-select-sm">
                        <option value="AND">ALL (AND)</option>
                        <option value="OR">ANY (OR)</option>
                    </select>
                </div>
                <div class="col-md-4 window-days-wrapper d-none">
                    <label class="form-label">Window (days)</label>
                    <input type="number" name="window_days" id="edit_window_days" class="form-control form-control-sm" min="1" max="365" value="7">
                </div>
                <div class="col-md-8 d-flex align-items-end">
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_config_active">
                        <label class="form-check-label" for="edit_config_active" style="font-size:12px">Active</label>
                    </div>
                </div>

                <div class="col-12" style="border-top:1px dashed var(--border-light);padding-top:14px">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold" style="font-size:12.5px"><i class="bi bi-plus-circle me-1" style="color:var(--green-600)"></i>Additional Conditions <span class="text-muted fw-normal">(optional)</span></span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addConditionRow(document.getElementById('editExtraConditions'))"><i class="bi bi-plus-lg me-1"></i>Add Condition</button>
                    </div>
                    <div id="editExtraConditions"></div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-primary">Update Factor</button>
        </div>
    </form>
</div></div></div>
@endsection

@push('scripts')
<script>
const fieldCatalog = {
    transaction: [
        {value: 'amount', label: 'Amount'},
        {value: 'transaction_type', label: 'Transaction Type'},
        {value: 'channel', label: 'Channel'},
        {value: 'location', label: 'Location'},
        {value: 'narration', label: 'Narration'},
        {value: 'sender_account_no', label: 'Sender Account No'},
        {value: 'beneficiary_account_no', label: 'Beneficiary Account No'},
        {value: 'sender_account_type', label: 'Sender Account Type'},
        {value: 'beneficiary_account_type', label: 'Beneficiary Account Type'},
        {value: 'sender_nin', label: 'Sender NIN'},
        {value: 'sender_bvn', label: 'Sender BVN'},
        {value: 'beneficiary_nin', label: 'Beneficiary NIN'},
        {value: 'beneficiary_bvn', label: 'Beneficiary BVN'},
        {value: 'transaction_ref', label: 'Transaction Reference'},
    ],
    customer: [
        {value: 'isPep', label: 'PEP Status (isPep)'},
        {value: 'customer_type', label: 'Customer Type'},
        {value: 'account_type', label: 'Account Type'},
        {value: 'tier_level', label: 'Tier Level'},
        {value: 'current_risk_level', label: 'Current Risk Level'},
        {value: 'current_risk_score', label: 'Current Risk Score'},
        {value: 'gender', label: 'Gender'},
        {value: 'state_of_residence', label: 'State of Residence'},
        {value: 'local_govt_area', label: 'LGA'},
        {value: 'occupation', label: 'Occupation'},
        {value: 'source_of_funds', label: 'Source of Funds'},
        {value: 'income_range', label: 'Income Range'},
        {value: 'business_activity', label: 'Business Activity'},
        {value: 'employer_name', label: 'Employer Name'},
    ],
    behaviour: [
        {value: 'transaction_count', label: 'Transaction Count (High Frequency)'},
        {value: 'debit_count', label: 'Debit Count (outgoing)'},
        {value: 'credit_count', label: 'Credit Count (incoming)'},
        {value: 'debit_value', label: 'Total Debit Value (₦)'},
        {value: 'credit_value', label: 'Total Credit Value (₦)'},
        {value: 'str_count', label: 'STR Count'},
        {value: 'ctr_count', label: 'CTR Count'},
        {value: 'pep_receipt_count', label: 'PEP-Linked Receipts'},
        {value: 'midnight_transaction_count', label: 'Midnight Transactions (23:00–04:00)'},
        {value: 'same_sender_count', label: 'Repeat Sender (max from one sender)'},
        {value: 'distinct_sender_count', label: 'Distinct Senders'},
    ],
};

const OPERATORS = [
    {value: 'greater_than', label: 'Greater Than'},
    {value: 'equal_to', label: 'Equal To'},
    {value: 'not_equal_to', label: 'Not Equal To'},
    {value: 'less_than', label: 'Less Than'},
    {value: 'greater_than_equal', label: '≥'},
    {value: 'less_than_equal', label: '≤'},
    {value: 'contains', label: 'Contains'},
    {value: 'is_true', label: 'Is True/Yes'},
];

const CHECK_TYPES = [
    {value: 'transaction', label: 'Transaction Data'},
    {value: 'customer', label: 'Customer Data'},
    {value: 'behaviour', label: 'Behaviour / Derived'},
];

function fieldsFor(checkType) {
    return fieldCatalog[checkType] || [];
}

function esc(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function operatorOptionsHTML(selected) {
    return OPERATORS.map(o => `<option value="${o.value}" ${o.value === selected ? 'selected' : ''}>${o.label}</option>`).join('');
}

function checkTypeOptionsHTML(selected) {
    return CHECK_TYPES.map(t => `<option value="${t.value}" ${t.value === selected ? 'selected' : ''}>${t.label}</option>`).join('');
}

/**
 * Update the "Field to Check" dropdown for a given "Checks Against" selection.
 */
function updateFieldOptions(checkTypeSelect, fieldSelect, preselect = null) {
    const fields = fieldsFor(checkTypeSelect.value);

    fieldSelect.innerHTML = '';

    if (fields.length === 0) {
        fieldSelect.innerHTML = '<option value="">— Select "Checks Against" first —</option>';
        return;
    }

    fieldSelect.innerHTML = '<option value="" disabled>Select field...</option>';
    fields.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.value;
        opt.textContent = f.label;
        if (preselect && f.value === preselect) opt.selected = true;
        fieldSelect.appendChild(opt);
    });

    if (!preselect) fieldSelect.selectedIndex = 0;
}

/**
 * Show/hide the "Window (days)" input — only relevant for behaviour checks.
 */
function toggleWindowInput(form, checkType) {
    const wrapper = form.querySelector('.window-days-wrapper');
    if (!wrapper) return;
    wrapper.classList.toggle('d-none', checkType !== 'behaviour');
}

function onCheckTypeChange(checkTypeSelect, form) {
    updateFieldOptions(checkTypeSelect, form.querySelector('.check-field-select'));
    toggleWindowInput(form, checkTypeSelect.value);
}

/**
 * Add an additional condition row to a modal's extra-conditions container.
 */
function addConditionRow(container, data = {}) {
    const wrap = document.createElement('div');
    wrap.className = 'cond-row row g-2 mt-2 align-items-end';
    wrap.innerHTML = `
        <div class="col-md-3">
            <label class="form-label" style="font-size:10px;margin-bottom:2px">Checks Against</label>
            <select class="form-select form-select-sm cond-check-type" name="cond[][check_type]">${checkTypeOptionsHTML(data.check_type || 'transaction')}</select>
        </div>
        <div class="col-md-3">
            <label class="form-label" style="font-size:10px;margin-bottom:2px">Field</label>
            <select class="form-select form-select-sm cond-field" name="cond[][field]"></select>
        </div>
        <div class="col-md-2">
            <label class="form-label" style="font-size:10px;margin-bottom:2px">Condition</label>
            <select class="form-select form-select-sm" name="cond[][operator]">${operatorOptionsHTML(data.operator || 'greater_than')}</select>
        </div>
        <div class="col-md-2">
            <label class="form-label" style="font-size:10px;margin-bottom:2px">Value</label>
            <input class="form-control form-control-sm" name="cond[][value]" value="${esc(data.value)}" placeholder="value">
        </div>
        <div class="col-md-1">
            <label class="form-label" style="font-size:10px;margin-bottom:2px">Days</label>
            <input type="number" class="form-control form-control-sm" name="cond[][window_days]" value="${data.window_days || 7}" min="1" max="365">
        </div>
        <div class="col-md-1">
            <label class="form-label" style="visibility:hidden;margin-bottom:2px">x</label>
            <button type="button" class="btn btn-outline-danger btn-action" onclick="this.closest('.cond-row').remove()"><i class="bi bi-x"></i></button>
        </div>`;

    const ctSelect = wrap.querySelector('.cond-check-type');
    const fieldSelect = wrap.querySelector('.cond-field');
    ctSelect.addEventListener('change', () => updateFieldOptions(ctSelect, fieldSelect));
    updateFieldOptions(ctSelect, fieldSelect, data.field || null);

    container.appendChild(wrap);
}

// Reset the Add modal to a clean state each time it opens.
document.getElementById('addConfigModal')?.addEventListener('show.bs.modal', function() {
    const form = document.getElementById('addConfigForm');
    form.reset();
    document.getElementById('addExtraConditions').innerHTML = '';
    updateFieldOptions(form.querySelector('.check-type-select'), form.querySelector('.check-field-select'));
    toggleWindowInput(form, '');
});

// Edit modal — populate ALL fields (including multi-condition rows) from data attributes.
document.getElementById('editConfigModal')?.addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    const form = document.getElementById('editConfigForm');

    // Set form action
    form.action = '/transactions/risk-scoring-config/' + b.dataset.id;

    // Basic fields
    document.getElementById('edit_config_name').value = b.dataset.name;
    document.getElementById('edit_config_desc').value = b.dataset.desc;
    document.getElementById('edit_config_weight').value = b.dataset.weight;
    document.getElementById('edit_config_active').checked = b.dataset.active === '1';

    // Parse conditions (multi-condition JSON) with single-condition fallback.
    let conditions = [];
    try {
        conditions = JSON.parse(b.dataset.conditions || '[]');
    } catch (err) {
        conditions = [];
    }
    if (!Array.isArray(conditions) || conditions.length === 0) {
        conditions = [{ check_type: b.dataset.checkType, field: b.dataset.field, operator: b.dataset.operator, value: b.dataset.condValue, window_days: b.dataset.windowDays || 7 }];
    }

    const primary = conditions[0] || {};

    // Match logic
    document.getElementById('edit_logic').value = b.dataset.logic || 'AND';

    // Condition 1 (primary)
    const checkTypeSelect = document.getElementById('edit_check_type');
    const fieldSelect = document.getElementById('edit_check_field');
    checkTypeSelect.value = primary.check_type || '';
    document.getElementById('edit_operator').value = primary.operator || 'greater_than';
    document.getElementById('edit_cond_value').value = primary.value ?? '';
    document.getElementById('edit_window_days').value = primary.window_days || 7;

    updateFieldOptions(checkTypeSelect, fieldSelect, primary.field || null);
    toggleWindowInput(form, checkTypeSelect.value);

    // Additional conditions
    const container = document.getElementById('editExtraConditions');
    container.innerHTML = '';
    conditions.slice(1).forEach(c => addConditionRow(container, c));
});

function deleteConfig(id) {
    if (!confirm('Delete this risk factor?')) return;
    fetch('/transactions/risk-scoring-config/' + id, {
        method: 'DELETE', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json'}
    }).then(r => r.json()).then(d => { if (d.status === 'success') location.reload(); });
}
</script>
@endpush

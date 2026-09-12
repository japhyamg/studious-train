@extends('layouts.app')
@section('title', 'Create Transaction Rule')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('transaction-rules.index') }}">Rules</a><span class="sep">/</span>
    <span class="current">Create</span>
@endsection
@section('page-title')
    <a href="{{ route('transaction-rules.index') }}" class="text-decoration-none" style="color:var(--text-muted)">Transaction Rules</a>
    <i class="bi bi-chevron-right mx-1" style="font-size:10px"></i> Create
@endsection

@section('content')
<a href="{{ route('transaction-rules.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Rules</a>
<div class="card">
    <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Create New Transaction Rule</div>
    <div class="card-body">
        <form method="POST" action="{{ route('transaction-rules.store') }}" id="ruleForm">
            @csrf

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Name *</label>
                    <input name="name" type="text" class="form-control form-control-sm" placeholder="Enter rule name" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Run Time *</label>
                    <select name="run_time" class="form-select form-select-sm" required>
                        <option value="Instantly" selected>Instantly</option>
                        <option value="24HrTask">24 Hour Task</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="true" selected>Active</option>
                        <option value="false">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-9">
                    <label class="form-label">Description *</label>
                    <textarea class="form-control form-control-sm" name="description" rows="2" required placeholder="Describe what this rule detects..."></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Report Type *</label>
                    <select name="report_type" class="form-select form-select-sm" required>
                        <option value="STR">STR</option>
                        <option value="CTR">CTR</option>
                    </select>
                </div>
            </div>

            <hr>
            <h6 class="fw-bold mb-3" style="font-size:13px">Conditions</h6>
            <p style="font-size:11px;color:var(--text-muted)">Define the conditions that must match an incoming transaction for this rule to trigger.</p>

            <div id="conditionsContainer">
                <div class="row g-2 mb-3 condition-row align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Attribute</label>
                        <select name="attribute[]" class="form-select form-select-sm" onchange="setAttributeOptions(this)" required>
                            <option selected disabled value="">Select Attribute</option>
                            <optgroup label="Basic (Transaction Fields)">
                                <option value="amount">Amount</option>
                                <option value="narration">Narration</option>
                                <option value="sender_account_type">Sender Account Type</option>
                                <option value="beneficiary_account_type">Beneficiary Account Type</option>
                                <option value="transaction_type">Transaction Type</option>
                                <option value="channel">Channel</option>
                                <option value="location">Location</option>
                                <option value="transaction_ref">Transaction Ref.</option>
                                <option value="transaction_datetime">Transaction Date & Time</option>
                            </optgroup>
                            <optgroup label="Advanced (Aggregated / Account Level)">
                                <option value="sender_account">Sender's Account</option>
                                <option value="beneficiary_account">Beneficiary's Account</option>
                                <option value="total_amount">Total Amount (in period)</option>
                                <option value="total_transactions">Total Transactions (in period)</option>
                                <option value="daily_transaction_limit">Daily Transaction Limit</option>
                                <option value="multiple_sender">Multiple Senders</option>
                                <option value="multiple_beneficiary">Multiple Beneficiaries</option>
                                <option value="self_transfer">Self Transfer</option>
                                <option value="duration">Duration (hours)</option>
                                <option value="in_internal_watchlist">In Internal Watchlist</option>
                                <option value="in_nibss_watchlist">In NIBSS Watchlist</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Action</label>
                        <select name="action[]" class="form-select form-select-sm" required>
                            <option selected disabled value="">Select Action</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Value</label>
                        <input type="text" name="value[]" class="form-control form-control-sm" placeholder="e.g. 400000">
                    </div>
                    <input type="hidden" name="type[]" value="column">
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.condition-row').remove()">
                            <i class="bi bi-x me-1"></i>Remove
                        </button>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-sm btn-outline-secondary mb-4" onclick="addCondition()">
                <i class="bi bi-plus me-1"></i>Add Condition
            </button>

            <hr>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check me-1"></i>Create Rule</button>
                <a href="{{ route('transaction-rules.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const actionOptions = {
    numeric: [
        {value: 'equal_to', label: 'Equal To'},
        {value: 'not_equal_to', label: 'Not Equal To'},
        {value: 'greater_than', label: 'Greater Than'},
        {value: 'less_than', label: 'Less Than'},
        {value: 'equal_to_greater_than', label: 'Greater Than or Equal (≥)'},
        {value: 'equal_to_less_than', label: 'Less Than or Equal (≤)'},
        {value: 'between', label: 'Between (comma-separated)'},
    ],
    text: [
        {value: 'equal_to', label: 'Equal To'},
        {value: 'not_equal_to', label: 'Not Equal To'},
        {value: 'contains', label: 'Contains'},
    ],
    boolean: [
        {value: 'equal_to', label: 'Is'},
    ],
    account: [
        // Account-level attributes are used as grouping, action can be empty
    ],
};

const attrTypeMap = {
    'amount': 'numeric', 'total_amount': 'numeric', 'total_transactions': 'numeric',
    'daily_transaction_limit': 'numeric', 'duration': 'numeric', 'multiple_sender': 'numeric',
    'multiple_beneficiary': 'numeric',
    'narration': 'text', 'transaction_type': 'text', 'channel': 'text', 'location': 'text',
    'sender_account_type': 'text', 'beneficiary_account_type': 'text', 'transaction_ref': 'text',
    'transaction_datetime': 'text',
    'self_transfer': 'boolean', 'in_internal_watchlist': 'boolean', 'in_nibss_watchlist': 'boolean',
    'sender_account': 'account', 'beneficiary_account': 'account',
};

function setAttributeOptions(select) {
    const row = select.closest('.condition-row');
    const actionSelect = row.querySelector('[name="action[]"]');
    const typeInput = row.querySelector('[name="type[]"]');
    const attr = select.value;

    // Determine type
    const type = attrTypeMap[attr] || 'text';
    const options = actionOptions[type] || actionOptions.text;

    // Set type hidden field
    const advancedAttrs = ['total_amount','total_transactions','daily_transaction_limit','multiple_sender','multiple_beneficiary','sender_account','beneficiary_account','duration','self_transfer','in_internal_watchlist','in_nibss_watchlist'];
    typeInput.value = advancedAttrs.includes(attr) ? 'advance' : 'column';

    // Populate actions
    actionSelect.innerHTML = '<option selected disabled value="">Select Action</option>';
    options.forEach(opt => {
        const o = document.createElement('option');
        o.value = opt.value;
        o.textContent = opt.label;
        actionSelect.appendChild(o);
    });
}

let condIdx = 1;
function addCondition() {
    const html = `<div class="row g-2 mb-3 condition-row align-items-end">
        <div class="col-md-3">
            <label class="form-label">Attribute</label>
            <select name="attribute[]" class="form-select form-select-sm" onchange="setAttributeOptions(this)" required>
                <option selected disabled value="">Select Attribute</option>
                <optgroup label="Basic (Transaction Fields)">
                    <option value="amount">Amount</option><option value="narration">Narration</option>
                    <option value="sender_account_type">Sender Account Type</option><option value="beneficiary_account_type">Beneficiary Account Type</option>
                    <option value="transaction_type">Transaction Type</option><option value="channel">Channel</option>
                    <option value="location">Location</option><option value="transaction_ref">Transaction Ref.</option>
                    <option value="transaction_datetime">Transaction Date & Time</option>
                </optgroup>
                <optgroup label="Advanced (Aggregated / Account Level)">
                    <option value="sender_account">Sender's Account</option><option value="beneficiary_account">Beneficiary's Account</option>
                    <option value="total_amount">Total Amount (in period)</option><option value="total_transactions">Total Transactions (in period)</option>
                    <option value="daily_transaction_limit">Daily Transaction Limit</option><option value="multiple_sender">Multiple Senders</option>
                    <option value="multiple_beneficiary">Multiple Beneficiaries</option><option value="self_transfer">Self Transfer</option>
                    <option value="duration">Duration (hours)</option><option value="in_internal_watchlist">In Internal Watchlist</option>
                    <option value="in_nibss_watchlist">In NIBSS Watchlist</option>
                </optgroup>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Action</label><select name="action[]" class="form-select form-select-sm" required><option selected disabled value="">Select Action</option></select></div>
        <div class="col-md-3"><label class="form-label">Value</label><input type="text" name="value[]" class="form-control form-control-sm" placeholder="e.g. 400000"></div>
        <input type="hidden" name="type[]" value="column">
        <div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.condition-row').remove()"><i class="bi bi-x me-1"></i>Remove</button></div>
    </div>`;
    document.getElementById('conditionsContainer').insertAdjacentHTML('beforeend', html);
    condIdx++;
}
</script>
@endpush

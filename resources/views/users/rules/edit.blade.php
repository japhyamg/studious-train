@extends('layouts.app')
@section('title', 'Edit Rule: ' . $rule->name)
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('transaction-rules.index') }}">Rules</a><span class="sep">/</span>
    <span class="current">Edit Rule</span>
@endsection
@section('page-title')
    <a href="{{ route('transaction-rules.index') }}" class="text-decoration-none" style="color:var(--text-muted)">Transaction Rules</a>
    <i class="bi bi-chevron-right mx-1" style="font-size:10px"></i> Edit: {{ $rule->name }}
@endsection

@section('content')
<a href="{{ route('transaction-rules.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Rules</a>
@php $conditions = is_array($rule->search_attributes) ? $rule->search_attributes : json_decode($rule->search_attributes, true); @endphp
<div class="card">
    <div class="card-header"><i class="bi bi-pencil me-1"></i> Edit Transaction Rule</div>
    <div class="card-body">
        <form method="POST" action="{{ route('transaction-rules.update', $rule->id) }}">
            @csrf @method('PUT')

            <div class="row g-3 mb-4">
                <div class="col-md-6"><label class="form-label">Name *</label><input name="name" type="text" class="form-control form-control-sm" value="{{ $rule->name }}" required></div>
                <div class="col-md-3"><label class="form-label">Run Time</label><select name="run_time" class="form-select form-select-sm"><option value="Instantly" {{ $rule->run_time=='Instantly'?'selected':'' }}>Instantly</option><option value="24HrTask" {{ $rule->run_time=='24HrTask'?'selected':'' }}>24 Hour Task</option></select></div>
                <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm"><option value="true" {{ $rule->status?'selected':'' }}>Active</option><option value="false" {{ !$rule->status?'selected':'' }}>Inactive</option></select></div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-9"><label class="form-label">Description</label><textarea class="form-control form-control-sm" name="description" rows="2">{{ $rule->description }}</textarea></div>
                <div class="col-md-3"><label class="form-label">Report Type</label><select name="report_type" class="form-select form-select-sm"><option value="STR" {{ ($rule->report_type??'STR')=='STR'?'selected':'' }}>STR</option><option value="CTR" {{ ($rule->report_type??'')=='CTR'?'selected':'' }}>CTR</option></select></div>
            </div>

            <hr><h6 class="fw-bold mb-3" style="font-size:13px">Conditions</h6>

            <div id="conditionsContainer">
                @if($conditions)
                @foreach($conditions as $i => $c)
                <div class="row g-2 mb-3 condition-row align-items-end">
                    <div class="col-md-3"><label class="form-label">Attribute</label>
                        <select name="attribute[]" class="form-select form-select-sm" onchange="setAttributeOptions(this)">
                            <option disabled value="">Select</option>
                            @foreach(['amount'=>'Amount','narration'=>'Narration','sender_account_type'=>'Sender Acct Type','beneficiary_account_type'=>'Beneficiary Acct Type','transaction_type'=>'Transaction Type','channel'=>'Channel','location'=>'Location','transaction_ref'=>'Transaction Ref','transaction_datetime'=>'Transaction DateTime'] as $val=>$lbl)
                            <option value="{{ $val }}" {{ ($c['attribute']??'')==$val?'selected':'' }}>{{ $lbl }}</option>
                            @endforeach
                            @foreach(['sender_account'=>"Sender's Account",'beneficiary_account'=>"Beneficiary's Account",'total_amount'=>'Total Amount','total_transactions'=>'Total Transactions','daily_transaction_limit'=>'Daily Txn Limit','multiple_sender'=>'Multiple Senders','multiple_beneficiary'=>'Multiple Beneficiaries','self_transfer'=>'Self Transfer','duration'=>'Duration (hrs)','in_internal_watchlist'=>'In Internal Watchlist','in_nibss_watchlist'=>'In NIBSS Watchlist'] as $val=>$lbl)
                            <option value="{{ $val }}" {{ ($c['attribute']??'')==$val?'selected':'' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Action</label>
                        <select name="action[]" class="form-select form-select-sm">
                            @foreach(['equal_to'=>'Equal To','not_equal_to'=>'Not Equal','greater_than'=>'Greater Than','less_than'=>'Less Than','equal_to_greater_than'=>'≥','equal_to_less_than'=>'≤','between'=>'Between','contains'=>'Contains'] as $val=>$lbl)
                            <option value="{{ $val }}" {{ ($c['action']??'')==$val?'selected':'' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Value</label><input type="text" name="value[]" class="form-control form-control-sm" value="{{ is_array($c['value']??'') ? json_encode($c['value']) : ($c['value']??'') }}"></div>
                    <input type="hidden" name="type[]" value="{{ $c['type'] ?? 'column' }}">
                    <div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.condition-row').remove()"><i class="bi bi-x me-1"></i>Remove</button></div>
                </div>
                @endforeach
                @endif
            </div>

            <button type="button" class="btn btn-sm btn-outline-secondary mb-4" onclick="addCondition()"><i class="bi bi-plus me-1"></i>Add Condition</button>
            <hr>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check me-1"></i>Update Rule</button>
                <a href="{{ route('transaction-rules.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function setAttributeOptions(select) { /* same as create page */ }
let condIdx = {{ count($conditions ?? []) }};
function addCondition() {
    const html = `<div class="row g-2 mb-3 condition-row align-items-end">
        <div class="col-md-3"><label class="form-label">Attribute</label><select name="attribute[]" class="form-select form-select-sm" onchange="setAttributeOptions(this)" required><option selected disabled value="">Select</option>
            <option value="amount">Amount</option><option value="narration">Narration</option><option value="transaction_type">Transaction Type</option>
            <option value="channel">Channel</option><option value="location">Location</option><option value="total_amount">Total Amount</option>
            <option value="duration">Duration</option><option value="sender_account">Sender Account</option><option value="beneficiary_account">Beneficiary Account</option>
            <option value="in_internal_watchlist">In Internal Watchlist</option><option value="in_nibss_watchlist">In NIBSS Watchlist</option>
        </select></div>
        <div class="col-md-3"><label class="form-label">Action</label><select name="action[]" class="form-select form-select-sm"><option value="equal_to">Equal To</option><option value="greater_than">Greater Than</option><option value="less_than">Less Than</option><option value="between">Between</option><option value="equal_to_greater_than">≥</option></select></div>
        <div class="col-md-3"><label class="form-label">Value</label><input type="text" name="value[]" class="form-control form-control-sm"></div>
        <input type="hidden" name="type[]" value="column">
        <div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.condition-row').remove()"><i class="bi bi-x me-1"></i>Remove</button></div>
    </div>`;
    document.getElementById('conditionsContainer').insertAdjacentHTML('beforeend', html);
}
</script>
@endpush

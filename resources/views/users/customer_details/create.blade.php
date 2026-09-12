@extends('layouts.app')
@section('title', 'Add Customer')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('customers.all') }}">Customers</a><span class="sep">/</span>
    <span class="current">Add Customer</span>
@endsection

@section('content')
<a href="{{ route('customers.all') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Customers</a>

<div class="card">
    <div class="card-header"><span><i class="bi bi-person-plus me-2"></i> Add New Customer</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('customers.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">First Name *</label><input type="text" name="customer_first_name" class="form-control form-control-sm" required value="{{ old('customer_first_name') }}"></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" name="customer_middle_name" class="form-control form-control-sm" value="{{ old('customer_middle_name') }}"></div>
                <div class="col-md-4"><label class="form-label">Last Name *</label><input type="text" name="customer_last_name" class="form-control form-control-sm" required value="{{ old('customer_last_name') }}"></div>
                <div class="col-md-4"><label class="form-label">Account Number *</label><input type="text" name="customer_account_number" class="form-control form-control-sm" required value="{{ old('customer_account_number') }}"></div>
                <div class="col-md-4"><label class="form-label">BVN *</label><input type="text" name="customer_bvn" class="form-control form-control-sm" required maxlength="11" value="{{ old('customer_bvn') }}"></div>
                <div class="col-md-4"><label class="form-label">NIN</label><input type="text" name="customer_nin" class="form-control form-control-sm" maxlength="11" value="{{ old('customer_nin') }}"></div>
                <div class="col-md-3"><label class="form-label">Gender</label><select name="customer_gender" class="form-select form-select-sm"><option value="">Select...</option><option value="male">Male</option><option value="female">Female</option></select></div>
                <div class="col-md-3"><label class="form-label">Date of Birth</label><input type="date" name="customer_dob" class="form-control form-control-sm" value="{{ old('customer_dob') }}"></div>
                <div class="col-md-3"><label class="form-label">Customer Type</label><select name="customer_type" class="form-select form-select-sm"><option value="individual">Individual</option><option value="corporate">Corporate</option></select></div>
                <div class="col-md-3"><label class="form-label">Tier Level</label><select name="customer_tier_level" class="form-select form-select-sm"><option value="1">Tier 1</option><option value="2">Tier 2</option><option value="3">Tier 3</option></select></div>
                <div class="col-md-4"><label class="form-label">State of Residence</label><input type="text" name="customer_state" class="form-control form-control-sm" value="{{ old('customer_state') }}"></div>
                <div class="col-md-4"><label class="form-label">LGA</label><input type="text" name="customer_lga" class="form-control form-control-sm" value="{{ old('customer_lga') }}"></div>
                <div class="col-md-4"><label class="form-label">PEP</label><select name="customer_is_pep" class="form-select form-select-sm"><option value="no">No</option><option value="yes">Yes</option></select></div>
            </div>
            <div class="divider"></div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create Customer</button>
        </form>
    </div>
</div>
@endsection

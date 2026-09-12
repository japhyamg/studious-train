@extends('layouts.app')
@section('title', 'Edit Rule: ' . $rule->name)
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('transaction-rules.index') }}">Rules</a><span class="sep">/</span>
    <span class="current">Edit Value</span>
@endsection
@section('page-title')
    <a href="{{ route('transaction-rules.index') }}" class="text-decoration-none text-muted">Rules</a>
    <i class="bi bi-chevron-right mx-1 small"></i> {{ $rule->name }}
@endsection
@section('content')
<div class="card">
    <div class="card-header"><i class="bi bi-pencil me-1"></i> Edit Rule Conditions</div>
    <div class="card-body">
        <p class="text-muted small mb-4">{{ $rule->description }}</p>
        <form method="POST" action="{{ route('transaction-rules.store-value', $rule->id) }}">
            @csrf
            @php $conditions = is_array($rule->search_attributes) ? $rule->search_attributes : json_decode($rule->search_attributes, true); @endphp
            @foreach($conditions as $i => $cond)
            <div class="row g-2 mb-3 align-items-center">
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Attribute</label>
                    <input type="text" name="attribute[]" class="form-control form-control-sm bg-light" value="{{ $cond['attribute'] ?? '' }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Value</label>
                    <input type="text" name="value[]" class="form-control form-control-sm" value="{{ $cond['value'] ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Action</label>
                    <input type="text" class="form-control form-control-sm bg-light" value="{{ $cond['action'] ?? '' }}" readonly>
                </div>
            </div>
            @endforeach
            <div class="mb-3">
                <label class="form-label small fw-medium">Status</label>
                <select name="status" class="form-select form-select-sm" style="width:200px">
                    <option value="1" {{ $rule->status ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ !$rule->status ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check me-1"></i> Save Changes</button>
        </form>
    </div>
</div>
@endsection

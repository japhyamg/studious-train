@extends('layouts.app')
@section('title', 'Peer Group Analysis')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">Peer Groups</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-diagram-3" style="color:var(--green-600);opacity:.6"></i> Peer Group Analysis</h4>
        <div class="heading-subtitle">IQR-based statistical outlier detection across customer peer groups</div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span><i class="bi bi-gear me-2"></i> Configuration</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('peer-grouping.update-settings') }}">@csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Recompute Interval (days)</label>
                    <input type="number" name="pg_recompute_interval" class="form-control form-control-sm" value="{{ settings('pg_recompute_interval', 7) }}">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Peer Group Fields</label>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach($availableFields as $field)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="selected_groups[]" value="{{ $field }}" id="pg_{{ $field }}" {{ in_array($field, $selectedField) ? 'checked' : '' }}>
                            <label class="form-check-label" for="pg_{{ $field }}">{{ ucwords(str_replace('_',' ',$field)) }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="mt-3"><button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save Settings</button></div>
        </form>
    </div>
</div>
@endsection

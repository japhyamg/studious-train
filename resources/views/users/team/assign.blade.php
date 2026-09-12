@extends('layouts.app')
@section('title', 'Assign Rules')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('manage-team.index') }}">Team</a><span class="sep">/</span>
    <span class="current">Assign Rules</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-link-45deg" style="color:var(--green-600);opacity:.6"></i> Assign Rules to Reviewers</h4>
        <div class="heading-subtitle">Map transaction rules to specific reviewers for case assignment</div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><span><i class="bi bi-plus-circle me-2"></i> Assign Rule</span></div>
            <div class="card-body">
                <form method="POST" action="{{ route('manage-team.assign-rule.update') }}">@csrf
                    <div class="mb-3">
                        <label class="form-label">Reviewer</label>
                        <select name="reviewer" class="form-select form-select-sm" required>
                            <option value="">Select reviewer...</option>
                            @foreach($reviewers as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rules to Assign</label>
                        <div class="p-3 rounded-3" style="background:#faf8f2;border:1px solid var(--border-light);max-height:300px;overflow-y:auto">
                            @foreach($rules as $rule)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="rules[]" value="{{ $rule->id }}" id="rule_{{ $rule->id }}">
                                <label class="form-check-label" for="rule_{{ $rule->id }}">{{ $rule->name }}</label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-link-45deg me-1"></i>Assign</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><span>Current Assignments</span></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0">
                <thead><tr><th>Rule</th><th>Reviewer</th><th></th></tr></thead>
                <tbody>
                    @forelse($assigned_rules as $ar)
                    <tr>
                        <td class="fw-medium">{{ $ar->transaction_rule?->name }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle" style="width:24px;height:24px;font-size:8px;border-radius:6px">{{ collect(explode(' ',$ar->user?->name ?? '?'))->map(fn($w)=>strtoupper($w[0]??''))->take(2)->join('') }}</div>
                                <span style="font-size:12.5px">{{ $ar->user?->name }}</span>
                            </div>
                        </td>
                        <td><a href="{{ route('manage-team.assign-rule.delete', $ar->id) }}" class="btn btn-outline-danger btn-action" data-bs-toggle="tooltip" title="Remove"><i class="bi bi-trash"></i></a></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3">
                            <div class="empty-state" style="padding:32px">
                                <div class="empty-state-icon"><i class="bi bi-link-45deg"></i></div>
                                <div class="empty-state-title">No assignments yet</div>
                                <div class="empty-state-text">Assign rules to reviewers using the form on the left.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table></div></div>
        </div>
    </div>
</div>
@endsection

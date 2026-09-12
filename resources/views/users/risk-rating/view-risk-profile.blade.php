@extends('layouts.app')
@section('title', $profile->name)
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <a href="{{ route('risk-rating.index') }}">Risk Rating</a><span class="sep">/</span>
    <span class="current">View Profile</span>
@endsection
@section('page-title', 'Profile: ' . $profile->name)
@section('content')
<div class="card"><div class="card-body">
    <h5>{{ $profile->name }}</h5>
    <div class="mb-3">@foreach($profile->data_points ?? [] as $dp)<span class="badge bg-primary bg-opacity-10 text-primary me-1">{{ ucwords(str_replace('_',' ',$dp)) }}</span>@endforeach</div>
    <div class="mb-3"><span class="badge {{ $profile->status ? 'bg-success' : 'bg-secondary' }}">{{ $profile->status ? 'Active' : 'Inactive' }}</span></div>
    @if($profile->template_data->count())
    <h6 class="mt-4">Template Data</h6>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Data Point</th><th>Type</th><th>Value</th></tr></thead><tbody>
        @foreach($profile->template_data as $td)<tr><td>{{ $td->data_point }}</td><td>{{ $td->type }}</td><td>{{ $td->value }}</td></tr>@endforeach
    </tbody></table></div>
    @endif
</div></div>
@endsection

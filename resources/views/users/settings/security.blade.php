@extends('layouts.app')
@section('title', 'My Account')
@section('content')
{{-- Security is now part of Profile page --}}
<script>window.location.href = "{{ route('account-settings-profile') }}";</script>
<p>Redirecting to <a href="{{ route('account-settings-profile') }}">My Account</a>...</p>
@endsection

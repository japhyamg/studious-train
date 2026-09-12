@extends('layouts.app')
@section('title', 'My Account')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">My Account</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-person-circle" style="color:var(--green-600);opacity:.6"></i> My Account</h4>
        <div class="heading-subtitle">Manage your profile, password, and security settings</div>
    </div>
</div>

<div class="row g-4">
    {{-- Profile Card --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('account-settings-profile.update') }}">
                    @csrf @method('PUT')
                    {{-- Avatar & Info --}}
                    <div class="text-center mb-4">
                        <div class="avatar-circle mx-auto mb-3" style="width:72px;height:72px;font-size:22px;border-radius:18px">{{ auth()->user()->initials }}</div>
                        <h6 class="mb-1" style="font-weight:700;font-size:16px">{{ auth()->user()->name }}</h6>
                        <span class="badge badge-green badge-pill" style="font-size:11px">{{ auth()->user()->roles->first()?->name }}</span>
                        <div style="font-size:11.5px;color:var(--text-muted);margin-top:8px">
                            <i class="bi bi-envelope me-1"></i>{{ auth()->user()->email }}
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ auth()->user()->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control form-control-sm" value="{{ auth()->user()->email }}" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-check-lg me-1"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Password Card --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-lock me-2"></i> Change Password</div>
            <div class="card-body">
                <form method="POST" action="{{ route('account-settings-password.update') }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:#faf8f2;border-color:var(--border);font-size:14px;color:var(--text-muted)"><i class="bi bi-lock"></i></span>
                            <input type="password" name="current_password" class="form-control form-control-sm" required placeholder="••••••••">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control form-control-sm" required minlength="8" placeholder="Minimum 8 characters">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="password_confirmation" class="form-control form-control-sm" required placeholder="Re-enter new password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-lock me-1"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Security Column --}}
    <div class="col-lg-4">
        {{-- Email OTP --}}
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-shield-lock me-2"></i> Two-Factor Authentication</div>
            <div class="card-body">
                <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px">
                    When enabled, you'll receive a one-time password via email during login for enhanced security.
                </p>

                @php $otpEnabled = auth()->user()->email_otp_enabled ?? false; @endphp
                <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background:{{ $otpEnabled ? 'var(--green-50)' : '#faf8f2' }};border:1px solid {{ $otpEnabled ? 'var(--green-100)' : 'var(--border-light)' }}">
                    <div>
                        <div style="font-weight:600;font-size:13px;display:flex;align-items:center;gap:6px">
                            <i class="bi bi-envelope-check" style="color:{{ $otpEnabled ? 'var(--green-700)' : 'var(--text-muted)' }}"></i>
                            Email OTP
                        </div>
                        <span class="status-dot {{ $otpEnabled ? 'active' : 'muted' }}" style="font-size:11px;margin-top:4px">{{ $otpEnabled ? 'Enabled' : 'Disabled' }}</span>
                    </div>
                    <form method="POST" action="{{ route('toggle-email-otp') }}">
                        @csrf
                        <input type="hidden" name="action" value="{{ $otpEnabled ? 'deactivate' : 'activate' }}">
                        <button type="submit" class="btn btn-sm {{ $otpEnabled ? 'btn-outline-danger' : 'btn-outline-primary' }}" style="font-size:11px">
                            <i class="bi bi-{{ $otpEnabled ? 'x-circle' : 'check-circle' }} me-1"></i>
                            {{ $otpEnabled ? 'Disable' : 'Enable' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Active Session --}}
        <div class="card">
            <div class="card-header"><i class="bi bi-laptop me-2"></i> Active Session</div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:var(--green-50);border:1px solid var(--green-100)">
                    <div style="width:42px;height:42px;border-radius:12px;background:var(--green-100);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi bi-laptop" style="font-size:18px;color:var(--green-700)"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div style="font-weight:600;font-size:12.5px">Current Session</div>
                        <div style="font-size:11px;color:var(--text-muted)">
                            IP: {{ request()->ip() }} · {{ now()->format('M d, Y H:i') }}
                        </div>
                    </div>
                    <span class="status-dot active" style="font-size:10px;font-weight:600">Active</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

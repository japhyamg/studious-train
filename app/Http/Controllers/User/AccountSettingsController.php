<?php

namespace App\Http\Controllers\User;

use App\Models\BusinessDetails;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AccountSettingsController extends Controller
{
    public function index()
    {
        return view('users.settings.profile');
    }

    public function security()
    {
        return view('users.settings.security');
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update($request->only('name', 'email'));
        activity()->causedBy($user)->log('Profile updated');

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update(['password' => Hash::make($request->password)]);
        activity()->causedBy($user)->log('Password changed');

        return back()->with('success', 'Password changed successfully.');
    }

    public function toggleEmailOtp(Request $request)
    {
        if ($request->has('action') && in_array($request->action, ['activate', 'deactivate'])) {
            $user = Auth::user();
            $user->email_otp_enabled = $request->action == 'activate' ? 1 : 0;
            $user->save();
            activity()->causedBy($user)->log('Email OTP ' . $request->action . 'd');
            return back()->with('success', 'Email OTP updated successfully.');
        }
        return back();
    }

    public function businessProfileUpdate(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'max:255'],
        ]);

        if ($request->has('name')) BusinessDetails::where('name', 'business_name')->update(['value' => $request->name]);
        if ($request->has('phone')) BusinessDetails::where('name', 'phone')->update(['value' => $request->phone]);
        if ($request->has('address')) BusinessDetails::where('name', 'address')->update(['value' => $request->address]);
        if ($request->has('state')) BusinessDetails::where('name', 'state')->update(['value' => $request->state]);

        return redirect(route('settings'))->with('success', 'Business information updated successfully');
    }
}

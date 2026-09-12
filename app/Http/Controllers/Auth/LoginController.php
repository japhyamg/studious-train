<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
                'last_login_device' => $request->userAgent(),
            ])->save();

            activity()->causedBy($user)->withProperties([
                'ip' => $request->ip(),
                'device' => $request->userAgent(),
            ])->log('User logged in');
            return redirect()->intended(route('dashboard'));
        }

        activity()->withProperties([
            'ip' => $request->ip(),
            'device' => $request->userAgent(),
            'email' => $request->input('email'),
        ])->log('Failed login attempt');

        return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        activity()->causedBy(Auth::user())->withProperties([
            'ip' => $request->ip(),
            'device' => $request->userAgent(),
        ])->log('User logged out');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}

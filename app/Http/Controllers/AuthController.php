<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->to($this->homeFor(Auth::user()));
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|min:4',
        ]);

        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            app(\App\Services\AuditTrail::class)->event('auth.login.succeeded', [
                'actor_type' => 'office_user', 'actor_id' => Auth::id(), 'actor_name' => Auth::user()->name,
                'actor_identifier' => Auth::user()->email, 'method' => 'POST', 'route_name' => 'login.post',
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            ]);

            /* Where somebody lands after signing in depends on who they are.
               An employee's home is their own record, not the office dashboard —
               sending them to a page they are about to be turned away from would
               greet every employee with an error message. */
            return redirect()->intended($this->homeFor(Auth::user()));
        }

        app(\App\Services\AuditTrail::class)->event('auth.login.failed', [
            'outcome' => 'failed', 'actor_type' => 'public', 'actor_identifier' => $request->input('email'),
            'method' => 'POST', 'route_name' => 'login.post', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->withInput($request->except('password'));
    }

    /** The landing page for whoever has just signed in. */
    private function homeFor(?\App\Models\User $user): string
    {
        return $user && $user->isEmployee() ? route('my.dashboard') : route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}

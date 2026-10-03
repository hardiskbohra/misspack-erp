<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClientPortalAuthController extends Controller
{
    public function showLogin(Request $request): View
    {
        if ($request->session()->has('client_portal_user_id')) {
            return view('client_portal.auth.redirecting');
        }

        return view('client_portal.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = 'client-portal-login:'.Str::lower(trim($data['login'])).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withInput($request->only('login'))
                ->with('error', 'Too many sign-in attempts. Please try again in '.$seconds.' seconds.');
        }

        $portalUser = ClientPortalUser::with('client')
            ->where('username', $data['login'])
            ->first();

        if (! $portalUser) {
            $emailMatches = ClientPortalUser::with('client')
                ->where('email', $data['login'])
                ->limit(2)
                ->get();
            $portalUser = $emailMatches->count() === 1 ? $emailMatches->first() : null;
        }

        if (! $portalUser || ! Hash::check($data['password'], $portalUser->password) || ! $portalUser->canLogin()) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withInput($request->only('login'))->with('error', 'Sign-in details are invalid or portal access is unavailable.');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->put('client_portal_user_id', $portalUser->id);
        $request->session()->regenerate();

        $portalUser->update(['last_login_at' => now()]);

        if ($portalUser->must_change_password) {
            return redirect()->route('client-portal.password.edit')->with('warning', 'Please change your one-time password.');
        }

        return redirect()->route('client-portal.dashboard')->with('success', 'Welcome to your client portal.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('client-portal.login')->with('success', 'Logged out successfully.');
    }

    public function editPassword(Request $request): View
    {
        $portalUser = ClientPortalUser::with('client')->findOrFail($request->session()->get('client_portal_user_id'));
        view()->share('clientPortalUser', $portalUser);
        view()->share('clientPortalClient', $portalUser->client);

        return view('client_portal.auth.change-password', compact('portalUser'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $portalUser = ClientPortalUser::findOrFail($request->session()->get('client_portal_user_id'));

        $rules = [
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ];

        if (! $portalUser->must_change_password) {
            $rules['current_password'] = ['required', 'string'];
        }

        $data = $request->validate($rules);

        if (! $portalUser->must_change_password && ! Hash::check($data['current_password'], $portalUser->password)) {
            return back()->with('error', 'Current password is incorrect.');
        }

        $portalUser->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
        $request->session()->regenerate();

        return redirect()->route('client-portal.dashboard')->with('success', 'Password changed successfully.');
    }
}

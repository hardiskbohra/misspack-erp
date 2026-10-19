<?php

namespace App\Http\Controllers;

use App\Models\VendorPortalUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VendorPortalAuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('vendor_portal_user_id')) return redirect()->route('vendor-portal.dashboard');
        return view('vendor_portal.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required','string','max:255'], 'password' => ['required','string','max:255']]);
        $key = 'vendor-portal-login:'.Str::lower(trim($data['login'])).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withInput($request->only('login'))->with('error', 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($key).' seconds.');
        }
        $user = VendorPortalUser::with('vendor')->where('username', $data['login'])->first();
        if (! $user) {
            $matches = VendorPortalUser::with('vendor')->where('email', $data['login'])->limit(2)->get();
            $user = $matches->count() === 1 ? $matches->first() : null;
        }
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->canLogin()) {
            RateLimiter::hit($key, 60);
            return back()->withInput($request->only('login'))->with('error', 'Sign-in details are invalid or portal access is unavailable.');
        }
        RateLimiter::clear($key);
        $request->session()->put('vendor_portal_user_id', $user->id);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        return redirect()->route($user->must_change_password ? 'vendor-portal.password.edit' : 'vendor-portal.dashboard')
            ->with($user->must_change_password ? 'warning' : 'success', $user->must_change_password ? 'Please change your one-time password.' : 'Welcome to your vendor workspace.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('vendor_portal_user_id');
        $request->session()->regenerateToken();
        return redirect()->route('vendor-portal.login')->with('success', 'Logged out successfully.');
    }

    public function editPassword(Request $request): View
    {
        return view('vendor_portal.auth.change-password', ['portalUser' => $request->attributes->get('vendorPortalUser')]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->attributes->get('vendorPortalUser');
        $rules = ['password' => ['required','string','min:12','confirmed']];
        if (! $user->must_change_password) $rules['current_password'] = ['required','string'];
        $data = $request->validate($rules);
        if (! $user->must_change_password && ! Hash::check($data['current_password'], $user->password)) return back()->with('error', 'Current password is incorrect.');
        $user->update(['password' => Hash::make($data['password']), 'must_change_password' => false, 'password_changed_at' => now()]);
        $request->session()->regenerate();
        return redirect()->route('vendor-portal.dashboard')->with('success', 'Password changed successfully.');
    }
}

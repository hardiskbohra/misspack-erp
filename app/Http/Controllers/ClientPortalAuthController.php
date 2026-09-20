<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $portalUser = ClientPortalUser::with('client')
            ->where(function ($query) use ($data) {
                $query->where('username', $data['login'])
                    ->orWhere('email', $data['login']);
            })
            ->first();

        if (! $portalUser || ! Hash::check($data['password'], $portalUser->password)) {
            return back()->withInput($request->only('login'))->with('error', 'Invalid username/email or password.');
        }

        if (! $portalUser->canLogin()) {
            return back()->withInput($request->only('login'))->with('error', 'Your client portal access is disabled. Please contact MissPack team.');
        }

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
        $request->session()->forget('client_portal_user_id');
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
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

        return redirect()->route('client-portal.dashboard')->with('success', 'Password changed successfully.');
    }
}

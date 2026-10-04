<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientPortalAccountController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $portalUser = $this->portalUser($request);

        return view('client_portal.account.index', compact('portalUser'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $portalUser = $this->portalUser($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('client_portal_users', 'email')->ignore($portalUser->id),
            ],
            'mobile' => ['nullable', 'string', 'max:40'],
            'current_password' => ['nullable', 'string'],
        ]);

        if (($data['email'] ?? null) !== $portalUser->email) {
            if (empty($data['current_password']) || ! Hash::check($data['current_password'], $portalUser->password)) {
                return back()->withErrors(['current_password' => 'Enter your current password to change the sign-in email.']);
            }
        }

        $portalUser->update(collect($data)->except('current_password')->all());

        return back()->with('success', 'Your profile has been updated.');
    }
}

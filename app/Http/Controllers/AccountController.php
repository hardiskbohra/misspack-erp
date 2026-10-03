<?php

namespace App\Http\Controllers;

use App\Services\EmployeeAccess;
use App\Services\EmployeeProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Your own account, whoever you are.
 *
 * The user menu in the shell used to be a label: for an employee the avatar was
 * a link to `/my/profile`, and for the office account it was a `<div>` that said
 * so out loud — "the office account does not have a page, so it keeps the same
 * look without pretending to be a link". That is a menu with no items. This is
 * the page behind it, reachable by both roles, because both roles have a name,
 * an address, a mobile number and a password.
 *
 * What it deliberately does *not* offer is the whole record. Which fields belong
 * to the person and which to the office is one rule, asked of one place
 * (`EmployeeAccess::ownEditableFields()`), so this page cannot drift from the
 * employee's own page or from the users module: an employee edits the same five
 * fields here as there, and the office edits the rest on the record page this
 * one links to — for themselves as much as for anybody else.
 */
class AccountController extends Controller
{
    public function __construct(private readonly EmployeeAccess $access)
    {
    }

    public function index(EmployeeProfile $profile): View
    {
        $me = Auth::user();

        return view('account.index', [
            'me' => $me,
            'employee' => $me->isEmployee(),
            'record' => $profile->profile($me),
            'editable' => $this->access->ownEditableFields(),
            /* The full record this account belongs to: the employee's workspace
               for staff, the users module for the office. An employee cannot
               reach the module — the middleware turns them around at the door —
               so the link is asked for by role rather than by preference. */
            'recordUrl' => $me->isEmployee()
                ? route('my.dashboard')
                : route('users.show', $me),
        ]);
    }

    /**
     * Save the parts of the record that are the person's own.
     *
     * The field list is filtered, not trusted: the form posts five names and the
     * validator accepts exactly those five, so a hand-made request cannot put a
     * designation or a bank account through the door that was opened for an
     * address.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $fields = $this->access->ownEditableFields();

        $data = array_intersect_key(
            $request->validate($this->access->ownFieldRules()),
            array_flip($fields)
        );

        Auth::user()->update($data);

        return back()->with('success', 'Your details are saved.');
    }

    /**
     * Change your own password.
     *
     * The current password is required even though the person is already signed
     * in: a session left open on a shared machine is exactly the situation a
     * password change is meant to close.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        Auth::user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Your password is changed.');
    }
}

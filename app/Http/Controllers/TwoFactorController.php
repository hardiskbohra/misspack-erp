<?php

namespace App\Http\Controllers;

use App\Services\AuditTrail;
use App\Services\TwoFactorAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The two-factor card on the account page.
 *
 * Four small moves, all about the sign-in of the person holding the session:
 * start enrolment, prove the authenticator got the key, ask for a fresh set of
 * recovery codes, turn it off. The last two ask for the current password again
 * — as the password change does — because a session left open on a shared
 * machine must not be able to quietly weaken its own account.
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorAuthentication $twoFactor)
    {
    }

    /** Begin (or restart) enrolment: a fresh pending key to show on the page. */
    public function store(Request $request): RedirectResponse
    {
        if ($this->twoFactor->hasEnabled($request->user())) {
            return $this->backToCard()->with('error', 'Two-factor authentication is already on. Turn it off before setting it up again.');
        }

        $this->twoFactor->beginEnrollment($request->user());

        return $this->backToCard()->with('success',
            'Add the key below to your authenticator app, then type the six digits it shows to finish.');
    }

    /** Prove the setup: a right code turns two-factor on and makes the recovery codes. */
    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('twoFactor', [
            'code' => ['required', 'string', 'max:12'],
        ]);

        $codes = $this->twoFactor->confirmEnrollment($request->user(), $data['code']);

        if ($codes === null) {
            return $this->backToCard()->withErrors([
                'code' => 'That code did not match. Wait for the app to show the next one and try again.',
            ], 'twoFactor');
        }

        $this->audit($request, 'auth.two_factor.enabled');

        /* The plain codes ride the flash bag exactly once; only their hashes
           reach the database. The page that renders them says so out loud. */
        return $this->backToCard()
            ->with('twoFactorRecoveryCodes', $codes)
            ->with('success', 'Two-factor authentication is on. Save the recovery codes somewhere safe — they are shown only now.');
    }

    /** A fresh set of recovery codes, behind the current password. */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validateWithBag('twoFactor', [
            'current_password' => ['required', 'current_password'],
        ]);

        $codes = $this->twoFactor->regenerateRecoveryCodes($request->user());

        if ($codes === null) {
            return $this->backToCard()->with('error', 'Two-factor authentication is not on yet.');
        }

        $this->audit($request, 'auth.two_factor.recovery.regenerated');

        return $this->backToCard()
            ->with('twoFactorRecoveryCodes', $codes)
            ->with('success', 'Fresh recovery codes — the old set has stopped working. Save these somewhere safe.');
    }

    /** Turn two-factor off, behind the current password. */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($this->twoFactor->hasEnabled($user)) {
            $request->validateWithBag('twoFactor', [
                'current_password' => ['required', 'current_password'],
            ]);
        }

        $this->twoFactor->disable($user);

        $this->audit($request, 'auth.two_factor.disabled');

        return $this->backToCard()->with('success', 'Two-factor authentication is off for this account.');
    }

    private function backToCard(): RedirectResponse
    {
        return redirect()->to(route('account.index').'#two-factor');
    }

    private function audit(Request $request, string $event): void
    {
        $user = $request->user();

        app(AuditTrail::class)->event($event, [
            'actor_type' => 'office_user', 'actor_id' => $user->id, 'actor_name' => $user->name,
            'actor_identifier' => $user->email, 'method' => $request->method(), 'route_name' => $request->route()?->getName(),
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
    }
}

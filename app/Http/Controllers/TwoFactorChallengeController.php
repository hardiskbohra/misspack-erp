<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditTrail;
use App\Services\TwoFactorAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * The second half of signing in.
 *
 * A correct password for a two-factor account does not open the session — it
 * leaves a name in the session and ends here. This page asks for the six
 * digits from that person's phone, or one of their recovery codes, and only a
 * right answer turns the half-visit into a sign-in.
 *
 * It is throttled harder than the password page: six digits is a small space,
 * so five wrong guesses end the challenge and send the visitor back to the
 * front door to start the password over.
 */
class TwoFactorChallengeController extends Controller
{
    private const MAX_CHALLENGE_ATTEMPTS = 5;
    private const CHALLENGE_LOCKOUT_SECONDS = 300;

    public function show(Request $request): View|RedirectResponse
    {
        $userId = $request->session()->get('two_factor.user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        /* A stale or tampered pending id — or an account whose two-factor was
           turned off mid-visit — restarts at the password, not at a dead form. */
        $user = User::find($userId);

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(['two_factor.user_id', 'two_factor.remember']);

            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $userId = $request->session()->get('two_factor.user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(['two_factor.user_id', 'two_factor.remember']);

            return redirect()->route('login');
        }

        $throttleKey = 'two-factor-challenge|'.$user->getKey().'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_CHALLENGE_ATTEMPTS)) {
            /* Too many guesses at a six-digit space: the challenge is done —
               back to the password, which is where a real owner can start over. */
            $request->session()->forget(['two_factor.user_id', 'two_factor.remember']);

            app(AuditTrail::class)->event('auth.two_factor.throttled', [
                'outcome' => 'throttled', 'actor_type' => 'office_user', 'actor_id' => $user->id,
                'actor_name' => $user->name, 'actor_identifier' => $user->email,
                'method' => 'POST', 'route_name' => 'two-factor.challenge.store',
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => 'Too many attempts at the code. Sign in again with your password.',
            ]);
        }

        $data = $request->validate([
            'code'          => ['required_without:recovery_code', 'nullable', 'string', 'max:12'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:20'],
        ]);

        $usedRecovery = blank($data['code'] ?? null);

        if ($twoFactor->verify($user, $data['code'] ?? null, $data['recovery_code'] ?? null)) {
            $remember = (bool) $request->session()->pull('two_factor.remember', false);
            $request->session()->forget('two_factor.user_id');

            Auth::login($user, $remember);
            $request->session()->regenerate();
            RateLimiter::clear($throttleKey);

            app(AuditTrail::class)->event('auth.two_factor.succeeded', [
                'actor_type' => 'office_user', 'actor_id' => $user->id, 'actor_name' => $user->name,
                'actor_identifier' => $user->email, 'method' => 'POST', 'route_name' => 'two-factor.challenge.store',
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
                'context' => ['via' => $usedRecovery ? 'recovery_code' : 'totp'],
            ]);

            /* Same landing rule as the password page: an employee's home is
               their own record, the office's is the dashboard. */
            return redirect()->intended(
                $user->isEmployee() ? route('my.dashboard') : route('dashboard')
            );
        }

        RateLimiter::hit($throttleKey, self::CHALLENGE_LOCKOUT_SECONDS);

        app(AuditTrail::class)->event('auth.two_factor.failed', [
            'outcome' => 'failed', 'actor_type' => 'office_user', 'actor_id' => $user->id,
            'actor_name' => $user->name, 'actor_identifier' => $user->email,
            'method' => 'POST', 'route_name' => 'two-factor.challenge.store',
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            'context' => ['via' => $usedRecovery ? 'recovery_code' : 'totp'],
        ]);

        return back()->withErrors([
            'code' => 'That code did not match. Check the time on your phone and try the next one, or use a recovery code.',
        ]);
    }
}

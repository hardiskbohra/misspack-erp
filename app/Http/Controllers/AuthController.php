<?php

namespace App\Http\Controllers;

use App\Services\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The front door of the office.
 *
 * It is the most privileged door in the building — behind it sits everybody's
 * money, payroll and paperwork — so it is also the most defended one:
 *
 * 1. **Throttled.** Five wrong passwords for an email from one IP locks the
 *    pair for five minutes, counted whether or not the email exists, so the
 *    lockout itself cannot be used to tell accounts apart. A lockout is an
 *    audit event, because five hundred wrong passwords is somebody knocking
 *    the door down, not forgetting theirs.
 * 2. **Honest about length.** The validator no longer repeats a minimum the
 *    policy left behind years ago; what a password must be is said once, in
 *    the default rules, where it is set and changed — not where it is tried.
 * 3. **Two doors for the careful.** An account with two-factor on gets past
 *    the password only as far as a challenge: the sign-in is finished there,
 *    by the code from their phone or a recovery code, never here.
 */
class AuthController extends Controller
{
    /** Wrong passwords a email/IP pair may make before the lockout, and for how long. */
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 300;

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
            'password' => 'required|string',
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            app(AuditTrail::class)->event('auth.login.throttled', [
                'outcome' => 'throttled', 'actor_type' => 'public', 'actor_identifier' => $request->input('email'),
                'method' => 'POST', 'route_name' => 'login.post', 'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(), 'context' => ['retry_after_seconds' => $seconds],
            ]);

            throw ValidationException::withMessages([
                'email' => "Too many sign-in attempts. This email and network are paused — try again in {$seconds} seconds.",
            ]);
        }

        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');
        $user        = Auth::getProvider()->retrieveByCredentials($credentials);

        if (! $user || ! Auth::validate($credentials)) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            app(AuditTrail::class)->event('auth.login.failed', [
                'outcome' => 'failed', 'actor_type' => 'public', 'actor_identifier' => $request->input('email'),
                'method' => 'POST', 'route_name' => 'login.post', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->withInput($request->except('password'));
        }

        /* The password was right, so the lockout counter forgets the evening. */
        RateLimiter::clear($throttleKey);

        /* An account carrying two-factor is only half-way in: the password
           bought a challenge, not a session. The who goes into the session,
           the sign-in finishes on the challenge page. */
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('two_factor.user_id', $user->getKey());
            $request->session()->put('two_factor.remember', $remember);

            app(AuditTrail::class)->event('auth.two_factor.challenged', [
                'actor_type' => 'office_user', 'actor_id' => $user->id, 'actor_name' => $user->name,
                'actor_identifier' => $user->email, 'method' => 'POST', 'route_name' => 'login.post',
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        app(AuditTrail::class)->event('auth.login.succeeded', [
            'actor_type' => 'office_user', 'actor_id' => $user->id, 'actor_name' => $user->name,
            'actor_identifier' => $user->email, 'method' => 'POST', 'route_name' => 'login.post',
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);

        /* Where somebody lands after signing in depends on who they are.
           An employee's home is their own record, not the office dashboard —
           sending them to a page they are about to be turned away from would
           greet every employee with an error message. */
        return redirect()->intended($this->homeFor($user));
    }

    /** The landing page for whoever has just signed in. */
    public function homeFor(?\App\Models\User $user): string
    {
        return $user && $user->isEmployee() ? route('my.dashboard') : route('dashboard');
    }

    /**
     * What the lockout counts: an email from a network. Per email alone would
     * let a stranger lock the owner's account from anywhere; per IP alone
     * would punish an office sharing one connection.
     */
    private function throttleKey(Request $request): string
    {
        return 'office-login|'.Str::lower((string) $request->input('email')).'|'.$request->ip();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The line between the office and the payroll.
 *
 * Everything the ERP has built so far — the ledger, the clients, the shipments,
 * the paperwork — is the office's. An employee account exists to answer a much
 * smaller set of questions: what did I earn, where is my payslip, are my papers
 * on file. This middleware is applied to the office's half of the routes, so an
 * employee who follows a link, an old bookmark or a typed URL to any of it is
 * turned around at the door rather than shown a page they should not read.
 *
 * Deliberately a redirect with a sentence and not a 403: the person holding an
 * employee login is staff, not an attacker, and a wall with no door is a support
 * ticket. The only pages they can reach are their own.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isEmployee()) {
            /* The one exception worth making: an employee opening the dashboard
               by name should land on their own, not on a message telling them
               they are in the wrong place. */
            return redirect()
                ->route('my.dashboard')
                ->with('error', 'That area belongs to the office. This is your own workspace — your profile, your salary, your payslips and your documents.');
        }

        return $next($request);
    }
}

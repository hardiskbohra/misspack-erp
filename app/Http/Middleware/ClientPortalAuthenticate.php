<?php

namespace App\Http\Middleware;

use App\Models\ClientPortalUser;
use Closure;
use Illuminate\Http\Request;

class ClientPortalAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $userId = $request->session()->get('client_portal_user_id');

        if (! $userId) {
            return redirect()
                ->route('client-portal.login')
                ->with('error', 'Please login to access client portal.');
        }

        $portalUser = ClientPortalUser::with('client')->find($userId);

        if (! $portalUser || ! $portalUser->canLogin()) {
            $request->session()->forget('client_portal_user_id');

            return redirect()
                ->route('client-portal.login')
                ->with('error', 'Your client portal access is disabled. Please contact MissPack team.');
        }

        $request->attributes->set('clientPortalUser', $portalUser);

        view()->share('clientPortalUser', $portalUser);
        view()->share('clientPortalClient', $portalUser->client);

        if (
            $portalUser->must_change_password
            && ! $request->routeIs('client-portal.password.*')
            && ! $request->routeIs('client-portal.logout')
        ) {
            return redirect()
                ->route('client-portal.password.edit')
                ->with('warning', 'Please change your one-time password to continue.');
        }

        return $next($request);
    }
}
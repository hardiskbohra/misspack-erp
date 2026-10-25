<?php

namespace App\Http\Middleware;

use App\Models\VendorPortalUser;
use Closure;
use Illuminate\Http\Request;

class VendorPortalAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $userId = $request->session()->get('vendor_portal_user_id');
        if (! $userId) {
            return redirect()->route('vendor-portal.login')->with('error', 'Please sign in to access the vendor workspace.');
        }

        $portalUser = VendorPortalUser::with('vendor')->find($userId);
        if (! $portalUser || ! $portalUser->canLogin()) {
            $request->session()->forget('vendor_portal_user_id');
            return redirect()->route('vendor-portal.login')->with('error', 'Vendor portal access is disabled. Please contact the MissPack team.');
        }

        $request->attributes->set('vendorPortalUser', $portalUser);
        view()->share('vendorPortalUser', $portalUser);
        view()->share('vendorPortalVendor', $portalUser->vendor);

        if ($portalUser->must_change_password && ! $request->routeIs('vendor-portal.password.*', 'vendor-portal.logout')) {
            return redirect()->route('vendor-portal.password.edit')->with('warning', 'Please change your one-time password to continue.');
        }

        return $next($request);
    }
}

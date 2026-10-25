<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\VendorPortalUser;
use Illuminate\Http\Request;

abstract class VendorPortalBaseController extends Controller
{
    protected function portalUser(Request $request): VendorPortalUser
    {
        return $request->attributes->get('vendorPortalUser');
    }

    protected function vendor(Request $request): Vendor
    {
        return $this->portalUser($request)->vendor;
    }
}

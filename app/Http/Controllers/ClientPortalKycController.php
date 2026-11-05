<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPortalKycController extends ClientPortalBaseController
{
    public function show(Request $request): View
    {
        $client = $this->client($request);
        $kycUrl = \App\Services\PublicShareLinks::url($client);

        return view('client_portal.kyc.show', compact('client', 'kycUrl'));
    }
}

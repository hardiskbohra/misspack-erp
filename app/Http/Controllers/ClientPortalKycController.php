<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPortalKycController extends ClientPortalBaseController
{
    public function show(Request $request): View
    {
        $client = $this->client($request);
        $kycUrl = route('clients.publicKyc', $client->public_token);

        return view('client_portal.kyc.show', compact('client', 'kycUrl'));
    }
}

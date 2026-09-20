<?php

namespace App\Services;

use App\Models\ClientPortalUser;
use Illuminate\Http\Request;

class ClientPortalAccess
{
    public function user(Request $request): ?ClientPortalUser
    {
        $user = $request->attributes->get('clientPortalUser');

        return $user instanceof ClientPortalUser ? $user : null;
    }

    public function client(Request $request)
    {
        $user = $this->user($request);

        return $user ? $user->client : null;
    }
}

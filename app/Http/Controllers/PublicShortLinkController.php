<?php

namespace App\Http\Controllers;

use App\Services\PublicShareLinks;
use Illuminate\Http\Response;

class PublicShortLinkController extends Controller
{
    public function show(string $token): Response
    {
        $destination = PublicShareLinks::destination($token);
        $headers = [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($destination === null) {
            return response('Not found', 404, $headers);
        }

        // Keep the Location relative and the body empty: never echo a bearer
        // token into HTML, accept a visitor's destination, or cache the redirect.
        return response('', 302, $headers + ['Location' => $destination]);
    }
}

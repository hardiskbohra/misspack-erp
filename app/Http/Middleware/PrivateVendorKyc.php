<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Cover successful pages/downloads and rendered validation/not-found errors. */
class PrivateVendorKyc
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        foreach (['Cache-Control' => 'private, no-store, max-age=0', 'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow', 'X-Content-Type-Options' => 'nosniff'] as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}

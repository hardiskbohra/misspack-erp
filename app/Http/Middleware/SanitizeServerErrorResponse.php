<?php

namespace App\Http\Middleware;

use App\Support\UnexpectedError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Also cover explicit 500 responses, which Laravel's exception renderer never sees. */
class SanitizeServerErrorResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        return UnexpectedError::response($next($request), null, $request);
    }
}

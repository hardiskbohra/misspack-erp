<?php

namespace App\Http\Middleware;

use App\Services\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuditMutations
{
    public function handle(Request $request, Closure $next)
    {
        $officeUser = auth()->user();
        $officeActor = $officeUser ? [
            'actor_type' => 'office_user', 'actor_id' => $officeUser->id,
            'actor_name' => $officeUser->name, 'actor_identifier' => $officeUser->email,
        ] : [];

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            if (! $request->isMethodSafe()) {
                $status = $exception instanceof ValidationException ? 422
                    : (method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500);
                try { app(AuditTrail::class)->mutation($request, new Response('', $status), $officeActor); } catch (\Throwable $auditException) { report($auditException); }
            }
            throw $exception;
        }

        if (! $request->isMethodSafe() && ! $request->routeIs('theme.toggle', 'login.post', 'client-portal.login.submit', 'vendor-portal.login.submit')) {
            try { app(AuditTrail::class)->mutation($request, $response, $officeActor); } catch (\Throwable $exception) { report($exception); }
        }
        return $response;
    }
}

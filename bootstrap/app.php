<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents(discover: [
        __DIR__.'/../app/Listeners',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\App\Http\Middleware\SanitizeServerErrorResponse::class);
        $middleware->alias([
            'client.portal' => \App\Http\Middleware\ClientPortalAuthenticate::class,
            'vendor.portal' => \App\Http\Middleware\VendorPortalAuthenticate::class,
            /* The office's half of the application. An employee account reaches
               its own workspace and nothing else — see the middleware. */
            'office' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
        $middleware->appendToGroup('web', [\App\Http\Middleware\AuditMutations::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Correlate Laravel's normal exception report with the safe response;
        // no database or session is needed to create the reference.
        $exceptions->context(fn () => app()->bound('request')
            ? ['error_reference' => \App\Support\UnexpectedError::reference(app('request'))]
            : []);
        $exceptions->respond(fn ($response, $exception, $request) =>
            \App\Support\UnexpectedError::response($response, $exception, $request));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                // Inline client creation must receive validation/auth errors, not an HTML redirect.
                || ($request->routeIs('clients.quickStore', 'vendors.publicKyc*', 'vendors.kyc.*') && $request->expectsJson()),
        );
    })->create();

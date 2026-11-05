<?php

namespace App\Support;

use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** A database- and layout-independent last resort for unexpected HTTP failures. */
final class UnexpectedError
{
    private const ATTRIBUTE = '_erp_error_reference';
    private const MESSAGE = 'We could not complete this request. Check the record and any email or payment before retrying; the action may have completed.';

    public static function reference(Request $request): string
    {
        $reference = $request->attributes->get(self::ATTRIBUTE);
        if (! is_string($reference) || ! preg_match('/^[a-f0-9]{24}$/', $reference)) {
            $reference = bin2hex(random_bytes(12));
            $request->attributes->set(self::ATTRIBUTE, $reference);
        }

        return $reference;
    }

    public static function response(Response $original, ?Throwable $exception, Request $request): Response
    {
        if ($original->getStatusCode() !== 500 || (app()->environment('local', 'testing') && config('app.debug'))) {
            return $original;
        }

        $hadReference = $request->attributes->has(self::ATTRIBUTE);
        $reference = self::reference($request);
        if (! $hadReference) {
            // Some response exceptions are deliberately not reported by Laravel.
            // Supply a minimal correlated entry without exposing their contents.
            try {
                app(LoggerInterface::class)->error('HTTP 500 response', [
                    'error_reference' => $reference, 'exception_type' => $exception ? $exception::class : null,
                ]);
            } catch (Throwable) {
                // The fallback must not depend on a functioning log transport.
            }
        }

        $headers = ['X-Error-Reference' => $reference, 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff'];
        if ($request->is('api/*') || $request->expectsJson()
            || str_contains(strtolower((string) $original->headers->get('Content-Type')), 'json')) {
            return new JsonResponse(['message' => self::MESSAGE, 'reference' => $reference], 500, $headers);
        }

        $safeReference = htmlspecialchars($reference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Request could not be completed · MissPack</title>
<style>
:root{color-scheme:light dark;--canvas:#f3f6fb;--card:#fff;--text:#17243c;--muted:#52627a;--border:#d7dfec;--action:#315fb8;--action-text:#fff}
@media(prefers-color-scheme:dark){:root{--canvas:#0f1628;--card:#1a2540;--text:#edf2fa;--muted:#b7c5d9;--border:#34425d;--action:#a7c1ff;--action-text:#102449}}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:var(--canvas);color:var(--text);font:14px/1.5 Inter,system-ui,-apple-system,sans-serif}
main{width:min(100%,540px);padding:24px;background:var(--card);border:1px solid var(--border);border-radius:18px}h1{font-size:22px;line-height:1.3;font-weight:700;margin:0 0 16px}p{margin:0 0 16px}p.secondary{color:var(--muted)}code{overflow-wrap:anywhere;font-size:13px}a{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 16px;border-radius:10px;background:var(--action);color:var(--action-text);font-weight:600;text-decoration:none}a:focus-visible{outline:3px solid var(--action);outline-offset:3px}
</style>
</head>
<body><main>
<h1>We could not complete this request</h1>
<p>Check the record and any email or payment before retrying; the action may have completed.</p>
<p class="secondary">If you need help, contact your administrator and share this reference:<br><code>__REFERENCE__</code></p>
<a href="/">Return to the app</a>
</main></body></html>
HTML;
        return new Response(str_replace('__REFERENCE__', $safeReference, $html), 500, $headers + ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}

<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FeedbackRequest;
use App\Models\Lead;
use App\Models\PartyStatementShare;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Shipment;
use App\Models\Vendor;
use App\Models\VendorShareLink;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * Compact aliases for existing bearer links. The type and database ID are not
 * secrets: authorization is the 128-bit MAC over the CURRENT full token.
 * No destination or URL supplied by a visitor is ever used for the redirect.
 */
class PublicShareLinks
{
    /** type => [model, bearer-token column, canonical public GET route] */
    private const TYPES = [
        'c' => [Client::class, 'public_token', 'clients.publicKyc'],
        'v' => [Vendor::class, 'kyc_public_token', 'vendors.publicKyc'],
        'h' => [Shipment::class, 'public_token', 'shipments.publicTrack'],
        'p' => [Product::class, 'public_token', 'products.public'],
        's' => [SalesInvoice::class, 'public_token', 'sales-invoices.public'],
        'b' => [PurchaseInvoice::class, 'public_token', 'purchase-invoices.public'],
        'l' => [Lead::class, 'public_token', 'leads.public.show'],
        'a' => [PartyStatementShare::class, 'token', 'statements.public'],
        'w' => [VendorShareLink::class, 'token', 'vendor-shares.show'],
        'f' => [FeedbackRequest::class, 'token', 'feedback.public.show'],
    ];

    /** Issue a shareable absolute URL for a record that already has a token. */
    public static function url(Model $record): string
    {
        foreach (self::TYPES as $type => [$class, $column]) {
            if ($record instanceof $class) {
                $id = (string) $record->getKey();
                $token = $record->getAttribute($column);
                if (! $record->exists || ! preg_match('/\A[1-9][0-9]{0,19}\z/', $id) || ! is_string($token) || $token === '') {
                    throw new InvalidArgumentException('A public link requires a saved record and an active token.');
                }
                $key = self::key(config('app.key'));
                if ($key === null) {
                    throw new LogicException('An application key is required for public share links.');
                }
                $alias = $type.$id.'.'.self::signature($type, $id, $token, $key);

                return route('public-links.short', ['token' => $alias]);
            }
        }

        throw new InvalidArgumentException('This record type cannot have a public share link.');
    }

    /** Return the allowlisted, relative canonical path, or null for any invalid alias. */
    public static function destination(string $alias): ?string
    {
        if (! preg_match('/\A([a-z])([1-9][0-9]{0,19})\.([A-Za-z0-9_-]{22})\z/', $alias, $match)) {
            return null;
        }
        [, $type, $id, $signature] = $match;
        if (! isset(self::TYPES[$type])) {
            return null;
        }
        [$class, $column, $route] = self::TYPES[$type];
        $token = $class::query()->whereKey($id)->value($column);
        if (! is_string($token) || $token === '') {
            return null;
        }

        // Compare the canonical encoding, not decoded base64: the latter also
        // accepts noncanonical final characters with the same padding bits.
        foreach ([config('app.key'), ...config('app.previous_keys', [])] as $configuredKey) {
            $key = self::key($configuredKey);
            if ($key !== null && hash_equals(self::signature($type, $id, $token, $key), $signature)) {
                // Laravel's relative route helper removes the request's base
                // prefix. Extract the path from the absolute canonical route
                // instead, so a subdirectory/proxy mount is preserved while
                // the Location cannot become a cross-origin URL.
                $path = parse_url(route($route, ['token' => $token]), PHP_URL_PATH);

                return is_string($path) ? '/'.ltrim($path, '/') : null;
            }
        }

        return null;
    }

    private static function signature(string $type, string $id, string $token, string $key): string
    {
        $mac = hash_hmac('sha256', "misspack:public-short:v1\0{$type}\0{$id}\0{$token}", $key, true);

        return rtrim(strtr(base64_encode(substr($mac, 0, 16)), '+/', '-_'), '=');
    }

    /** Laravel accepts both raw APP_KEY strings and base64:-encoded keys. */
    private static function key(mixed $configured): ?string
    {
        if (! is_string($configured) || $configured === '') {
            return null;
        }
        if (str_starts_with($configured, 'base64:')) {
            $configured = base64_decode(substr($configured, 7), true);
        }

        return is_string($configured) && $configured !== '' ? $configured : null;
    }
}

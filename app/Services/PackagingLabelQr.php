<?php

namespace App\Services;

use App\Models\OfficeSetting;

final class PackagingLabelQr
{
    // The label encoder supports QR versions 1–10. Keep UTF-8 bytes, not just
    // characters, within its medium-error-correction capacity (213 bytes).
    public const MAX_URL_BYTES = 200;

    public static function validUrl(string $url): bool
    {
        return strlen($url) <= self::MAX_URL_BYTES
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null;
    }

    public static function configuredUrl(): string
    {
        $url = OfficeSetting::packaging()['label_qr_url'];

        // Invalid legacy/manual database edits must never become QR payloads.
        return self::validUrl($url) ? $url : '';
    }
}

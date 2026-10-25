<?php

namespace App\Services;

/** Presentation only: preserve the complete issuer address in three compact lines. */
final class StatementPrintHeader
{
    public static function addressLines(array $issuer): array
    {
        $parts = preg_split('/[\r\n]+|,\s*/u', trim((string) ($issuer['seller_address'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        $first = implode(', ', $parts);
        $second = '';
        $best = PHP_INT_MAX;
        for ($i = 1; $i < count($parts); $i++) {
            $left = implode(', ', array_slice($parts, 0, $i));
            $right = implode(', ', array_slice($parts, $i));
            $difference = abs(mb_strlen($left) - mb_strlen($right));
            if ($difference < $best) {
                [$first, $second, $best] = [$left, $right, $difference];
            }
        }
        $stateAndPin = trim(($issuer['seller_state'] ?? '').' '.($issuer['seller_pincode'] ?? ''));
        $locality = implode(', ', array_filter([$issuer['seller_city'] ?? '', $stateAndPin, $issuer['seller_country'] ?? ''], fn ($part) => $part !== ''));
        return [$first, $second, $locality];
    }
}

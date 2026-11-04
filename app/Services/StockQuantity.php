<?php

namespace App\Services;

/** Exact thousandths without floating-point or automatic rounding. */
final class StockQuantity
{
    public const MAX_MILLI = 999999999999999;

    public static function fromInput(mixed $value): int
    {
        if (! is_string($value) && ! is_int($value)) throw new \InvalidArgumentException('Enter a nonnegative quantity with up to three decimal places.');
        $value = trim((string) $value);
        if (! preg_match('/\A([0-9]{1,12})(?:\.([0-9]{1,3}))?\z/', $value, $matches)) {
            throw new \InvalidArgumentException('Enter a nonnegative quantity with up to 12 whole digits and three decimal places.');
        }
        return ((int) $matches[1] * 1000) + (int) str_pad($matches[2] ?? '', 3, '0');
    }

    public static function format(int $milli): string
    {
        $sign = $milli < 0 ? '-' : '';
        $absolute = abs($milli);
        return $sign.intdiv($absolute, 1000).'.'.str_pad((string) ($absolute % 1000), 3, '0', STR_PAD_LEFT);
    }
}

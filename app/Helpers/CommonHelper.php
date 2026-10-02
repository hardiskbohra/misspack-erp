<?php

namespace App\Helpers {

/**
 * Money, written the way it is read in India.
 *
 * One formatter for the whole app: the rupee sign with lakh/crore grouping
 * ("₹1,50,000", never "₹ 150,000.00"), paise only when the amount really has
 * them, and the currency code with thousand grouping for anything that is not
 * rupees. The sales-invoice module has used this class from the start;
 * everything else goes through it too now, so the same figure cannot read
 * differently on two pages.
 */
class CommonHelper
{
    /** The rupee sign — written in exactly one place. */
    public const SYMBOL = '₹';

    /**
     * An amount in rupees.
     *
     * 150000      => ₹1,50,000
     * 6163140     => ₹61,63,140
     * 123456.5    => ₹1,23,456.50
     * -1234567.5  => -₹12,34,567.50
     */
    public static function indianCurrency($amount, $symbol = self::SYMBOL): string
    {
        $value = (float) $amount;               // null and '' are zero
        $negative = $value < 0;

        $paise = (int) round(abs($value) * 100);
        $rupees = intdiv($paise, 100);
        $paise %= 100;

        /* the sign goes in front of the symbol, and a rounded-to-nothing
           negative is not a negative at all */
        $sign = $negative && ($rupees > 0 || $paise > 0) ? '-' : '';

        /* paise are shown only when the amount really has them, so a whole
           amount reads ₹1,50,000 rather than ₹1,50,000.00 */
        return $sign.$symbol.self::groupIndian((string) $rupees)
            .($paise > 0 ? '.'.str_pad((string) $paise, 2, '0', STR_PAD_LEFT) : '');
    }

    /**
     * Money in the currency it is written in: the rupee sign with Indian
     * grouping for rupees, the code with thousand grouping for anything else —
     * a ₹ in front of a dollar figure would state the wrong amount.
     */
    public static function amount($amount, ?string $currency = 'INR'): string
    {
        $code = strtoupper((string) ($currency ?: 'INR'));

        return $code === 'INR'
            ? self::indianCurrency($amount)
            : $code.' '.number_format((float) $amount, 2);
    }

    /**
     * Lakh/crore grouping: the last three digits, then twos all the way up.
     * 150000 => 1,50,000 — 6163140 => 61,63,140
     */
    public static function groupIndian(string $digits): string
    {
        if (strlen($digits) <= 3) {
            return $digits;
        }

        $lastThree = substr($digits, -3);
        $rest = substr($digits, 0, -3);

        return preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$lastThree;
    }
}

}

/* Views and controllers write money as inr($amount) / money($amount, $currency).
   This file is already autoloaded by composer, so the short names need no
   further wiring; braced namespaces let one file hold both the class and the
   helpers. */
namespace {

if (! function_exists('inr')) {
    function inr($amount, $symbol = \App\Helpers\CommonHelper::SYMBOL): string
    {
        return \App\Helpers\CommonHelper::indianCurrency($amount, $symbol);
    }
}

if (! function_exists('money')) {
    function money($amount, ?string $currency = 'INR'): string
    {
        return \App\Helpers\CommonHelper::amount($amount, $currency);
    }
}

}

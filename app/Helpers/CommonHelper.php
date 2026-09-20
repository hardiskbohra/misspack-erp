<?php

namespace App\Helpers;

class CommonHelper
{
    /**
     * Format amount in Indian currency format.
     *
     * Example:
     * 6163140      => ₹ 61,63,140.00
     * 123456.50    => ₹ 1,23,456.50
     */
    public static function indianCurrency($amount, $symbol = '₹')
    {
        if ($amount === null || $amount === '') {
            return $symbol . ' 0.00';
        }

        $amount = number_format((float) $amount, 2, '.', '');

        [$integer, $decimal] = explode('.', $amount);

        $lastThree = substr($integer, -3);
        $remaining = substr($integer, 0, -3);

        if ($remaining !== '') {
            $remaining = preg_replace(
                '/\B(?=(\d{2})+(?!\d))/',
                ',',
                $remaining
            );

            $integer = $remaining . ',' . $lastThree;
        }

        return $symbol . $integer . '.' . $decimal;
    }
}
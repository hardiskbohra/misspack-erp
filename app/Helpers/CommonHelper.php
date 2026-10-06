<?php

namespace App\Helpers;

/**
 * Money, written the way it is read in India.
 *
 * One formatter for the whole app: the rupee sign with lakh/crore grouping
 * ("₹1,50,000", never "₹ 150,000.00"), paise only when the amount really has
 * them, and the currency code with thousand grouping for anything that is not
 * rupees. The sales-invoice module has used this class from the start;
 * everything else goes through it too now, so the same figure cannot read
 * differently on two pages.
 *
 * Views call it by class name — \App\Helpers\CommonHelper::indianCurrency(…) —
 * because a class name is resolved by the autoloader on first use. A bare
 * global helper function only exists once something else has already loaded
 * this file, which is how a page that never mentions the class died with
 * "Call to undefined function".
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
        return self::indianCurrency($amount, self::symbol($currency));
    }

    /** The sign a currency wears: ₹, $, ¥. Unknown codes keep the code itself. */
    public static function symbol(?string $currency = 'INR'): string
    {
        return match (strtoupper((string) ($currency ?: 'INR'))) {
            'INR' => self::SYMBOL,
            'USD' => '$',
            'RMB', 'CNY' => '¥',
            default => strtoupper((string) $currency).' ',
        };
    }

    /**
     * A currency as a *label* — in a chip, a column, a picker — rather than as
     * an amount.
     *
     * Rupees are the sign and nothing else: "INR" is a code the office never
     * writes on a statement. Everything else keeps its code, because that is
     * how a vendor abroad writes it. Money itself always goes through
     * amount()/indianCurrency(); this is only for the word beside it.
     */
    public static function currencyLabel(?string $code): string
    {
        $code = strtoupper((string) ($code ?: 'INR'));

        return self::symbol($code);
    }

    /**
     * An amount in words, in the Indian system — the line every payslip and
     * every cheque carries, because a figure can be misread and words cannot.
     *
     *   95000    => Rupees Ninety Five Thousand Only
     *   150000   => Rupees One Lakh Fifty Thousand Only
     *   6163140  => Rupees Sixty One Lakh Sixty Three Thousand One Hundred And Forty Only
     *
     * Lakh and crore, not million and billion: this is the same decision as the
     * grouping above, said out loud. Paise are named when the amount has them
     * ("… And Fifty Paise Only"), and a negative amount says so in words too.
     */
    public static function inWords($amount, ?string $currency = 'INR'): string
    {
        $value = (float) $amount;
        $negative = $value < 0;

        $paise = (int) round(abs($value) * 100);
        $rupees = intdiv($paise, 100);
        $paise %= 100;

        $words = self::numberInWords($rupees);

        if ($paise > 0) {
            $words .= ' And '.self::numberInWords($paise).' Paise';
        }

        $unit = strtoupper((string) ($currency ?: 'INR')) === 'INR' ? 'Rupees' : strtoupper((string) $currency);

        return ($negative ? 'Minus ' : '').$unit.' '.$words.' Only';
    }

    /**
     * A whole number below a hundred crore, in words. One, two and three digit
     * runs are read as themselves and the Indian groups — crore, lakh,
     * thousand, hundred — are peeled off the front, largest first.
     */
    private static function numberInWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $groups = [
            10000000 => 'Crore',
            100000 => 'Lakh',
            1000 => 'Thousand',
            100 => 'Hundred',
        ];

        $words = [];

        foreach ($groups as $value => $label) {
            if ($number >= $value) {
                $count = intdiv($number, $value);
                $number %= $value;
                /* "One Lakh" reads; "One Hundred Thousand" does not — the same
                   reason the grouping is Indian. */
                $words[] = self::numberInWords($count).' '.$label;
            }
        }

        if ($number === 0) {
            return implode(' ', $words);
        }

        $tail = $number < 20 ? self::SMALL_NUMBERS[$number] : self::tensInWords($number);

        if ($words === []) {
            return $tail;
        }

        /* "One Hundred And Forty", never "One Lakh And Fifty Thousand": the And
           belongs to the last run, the one under a hundred. */
        return implode(' ', $words).($number < 100 ? ' And ' : ' ').$tail;
    }

    private static function tensInWords(int $number): string
    {
        $tens = [
            20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
            60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety',
        ];

        $whole = intdiv($number, 10) * 10;
        $rest = $number % 10;

        return $tens[$whole].($rest > 0 ? ' '.self::SMALL_NUMBERS[$rest] : '');
    }

    /** The numbers that are words of their own. */
    private const SMALL_NUMBERS = [
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
    ];

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

<?php

namespace App\Support;

/**
 * GSTIN — the 15-character Goods and Services Tax Identification Number.
 *
 * A GSTIN is not just a shape (the regex every validator reaches for); the
 * 15th character is a **check digit** computed from the first fourteen with
 * a Luhn-flavoured mod-36 algorithm, and the first two are a state/UT code
 * from a fixed table. A number that passes `15 chars, Z at fourteen` still
 * fails the checksum roughly one time in three when typed by hand — which is
 * precisely the failure this class exists to refuse, because a mistyped GSTIN
 * lands silently on invoices and GSTR-1 files until the notice arrives.
 *
 * The algorithm (as published for the GSTN): map every character of the first
 * fourteen onto base-36 (0-9 → 0-9, A-Z → 10-35); multiply each by a factor
 * that alternates 1, 2, 1, 2 … starting with 1 at the leftmost position; for
 * each product add (product ÷ 36 quotient) + (product mod 36) to a running
 * total; the check character is then `(36 - total mod 36) mod 36` mapped back
 * through the same alphabet.
 *
 * Three helpers ride on the validation, because once a GSTIN is known-good
 * its parts are useful: the state code drives intra/inter-state defaults and
 * the embedded PAN is what e-invoice buyer blocks repeat.
 */
final class Gstin
{
    /** Base-36 alphabet: digits keep their face value, letters follow at 10. */
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /** The structural shape every GSTIN must satisfy before any math runs. */
    private const PATTERN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    /**
     * State and Union Territory codes as allotted for GST (01-38 plus the
     * special registrations). The table is the whole point of keeping the map
     * here: a GSTIN that claims "state 00" is structurally fine and still
     * wrong, and this is the only class that gets to say so.
     *
     * @var array<string, string>
     */
    private const STATES = [
        '01' => 'Jammu & Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab',
        '04' => 'Chandigarh', '05' => 'Uttarakhand', '06' => 'Haryana',
        '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
        '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram',
        '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam',
        '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha',
        '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
        '25' => 'Daman & Diu', '26' => 'Dadra & Nagar Haveli and Daman & Diu',
        '27' => 'Maharashtra', '28' => 'Andhra Pradesh (pre-division)',
        '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep',
        '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry',
        '35' => 'Andaman & Nicobar Islands', '36' => 'Telangana',
        '37' => 'Andhra Pradesh', '38' => 'Ladakh',
        '97' => 'Other Territory', '99' => 'Centre Jurisdiction',
    ];

    private function __construct()
    {
        // Static utility; instantiating it would imply per-instance state.
    }

    /** The allotted GST state/UT codes (`code => name`) — public domain. */
    public static function stateCodes(): array
    {
        return self::STATES;
    }

    /**
     * Normalise user input for storage: trim, uppercase, strip stray spaces.
     * Returns null for an empty value so nullable fields stay nullable.
     */
    public static function normalise(?string $gstin): ?string
    {
        $cleaned = strtoupper(trim(preg_replace('/\s+/', '', (string) $gstin)));

        return $cleaned === '' ? null : $cleaned;
    }

    /** True only when structure, state code and check digit all hold. */
    public static function isValid(?string $gstin): bool
    {
        $normalised = self::normalise($gstin);

        if ($normalised === null) {
            return false;
        }

        if (! preg_match(self::PATTERN, $normalised)) {
            return false;
        }

        // Codes outside the allotted table are rejected even if the math held.
        if (! isset(self::STATES[self::stateCode($normalised)])) {
            return false;
        }

        return $normalised[14] === self::checkDigit(substr($normalised, 0, 14));
    }

    /** The two-digit state/UT code, or null when the shape is impossible. */
    public static function stateCode(?string $gstin): ?string
    {
        $normalised = self::normalise($gstin);

        return $normalised !== null && strlen($normalised) >= 2
            ? substr($normalised, 0, 2)
            : null;
    }

    /** The state's name for the code embedded in the GSTIN, if we know it. */
    public static function stateName(?string $gstin): ?string
    {
        $code = self::stateCode($gstin);

        return $code !== null ? (self::STATES[$code] ?? null) : null;
    }

    /** The PAN the registration belongs to (characters 3-12). */
    public static function pan(?string $gstin): ?string
    {
        $normalised = self::normalise($gstin);

        return $normalised !== null && strlen($normalised) === 15
            ? substr($normalised, 2, 10)
            : null;
    }

    /**
     * Compute the check character for a 14-character prefix. Public because
     * the tests pin it directly — a checksum nobody can compute from outside
     * is a checksum nobody can prove correct.
     */
    public static function checkDigit(string $firstFourteen): string
    {
        $total = 0;
        $factor = 1;

        for ($i = 0; $i < 14; $i++) {
            $codePoint = strpos(self::ALPHABET, strtoupper($firstFourteen[$i] ?? ''));

            // A prefix containing a character outside base-36 cannot checksum.
            if ($codePoint === false) {
                return '';
            }

            $product = $factor * $codePoint;
            $total += intdiv($product, 36) + ($product % 36);
            $factor = $factor === 1 ? 2 : 1;
        }

        return self::ALPHABET[(36 - ($total % 36)) % 36];
    }
}

<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Time-based one-time passwords, RFC 6238, with nothing installed.
 *
 * Two-factor authentication cannot wait for a Composer package to join the
 * vendor directory, and the algorithm it stands on is thirty lines of hashing:
 * a Base32 secret both sides keep, a counter that ticks every thirty seconds,
 * an HMAC-SHA1 of the two, six digits out of the middle of the digest. This is
 * the same TOTP every authenticator app speaks — the enrolment key this class
 * makes can be typed into Google Authenticator, Microsoft Authenticator, 1Password
 * or any of their peers.
 *
 * Everything here is pure and stateless so the unit test can pin it to the
 * RFC's own test vectors. Callers that need "which user is this for" and "what
 * happens when it runs out of codes" belong to `App\Services\TwoFactorAuthentication`.
 */
class Totp
{
    /** Authenticator apps assume these two; changing either would strand them. */
    public const PERIOD = 30;
    public const DIGITS = 6;

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A fresh shared secret, Base32 so it can be typed letter by letter. */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /** The six digits an authenticator would show for a given moment. */
    public static function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), self::PERIOD);

        // 64-bit big-endian counter, as the RFC's HOTP demands.
        $binary = pack('NN', 0, $counter);
        $hash = hash_hmac('sha1', $binary, self::base32Decode($secret), true);

        // Dynamic truncation: the last nibble says where the four bytes start.
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Whether a presented code is right *now*, give or take a clock.
     *
     * The window is authenticators and phones disagreeing by half a minute, not
     * a second door: one step each side, never more, and every comparison is
     * constant-time so the answer leaks nothing about how close a guess was.
     */
    public static function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return false;
        }

        $now = $timestamp ?? time();

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $now + $i * self::PERIOD), $code)) {
                return true;
            }
        }

        return false;
    }

    /** The otpauth:// link an authenticator app can swallow whole. */
    public static function provisioningUri(string $issuer, string $accountName, string $secret): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($accountName)
            .'?'.http_build_query([
                'secret' => $secret,
                'issuer' => $issuer,
                'algorithm' => 'SHA1',
                'digits' => self::DIGITS,
                'period' => self::PERIOD,
            ]);
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    public static function base32Decode(string $encoded): string
    {
        $alphabet = array_flip(str_split(self::BASE32_ALPHABET));

        $bits = '';
        foreach (str_split(strtoupper(rtrim($encoded, '='))) as $char) {
            if (! isset($alphabet[$char])) {
                throw new InvalidArgumentException('The two-factor key is not valid Base32.');
            }
            $bits .= str_pad(decbin($alphabet[$char]), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $decoded .= chr(bindec($byte));
            }
        }

        return $decoded;
    }
}

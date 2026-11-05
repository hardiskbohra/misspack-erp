<?php

namespace Tests\Unit;

use App\Support\Totp;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The TOTP class pinned to RFC 6238's own test vectors, so an update that
 * breaks authenticator compatibility breaks this test instead of sign-ins.
 */
class TotpTest extends TestCase
{
    /* ASCII "12345678901234567890", the RFC's shared secret, in Base32. */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_codes_match_the_rfc_6238_vectors(): void
    {
        // The RFC prints 8-digit values; ours is the same HOTP taken to six.
        $this->assertSame('287082', Totp::code(self::RFC_SECRET, 59));
        $this->assertSame('081804', Totp::code(self::RFC_SECRET, 1111111109));
        $this->assertSame('005924', Totp::code(self::RFC_SECRET, 1234567890));
    }

    public function test_verify_accepts_the_code_around_the_current_step(): void
    {
        // One step each side covers phones and servers disagreeing by half a minute.
        $this->assertTrue(Totp::verify(self::RFC_SECRET, '287082', 1, 59));
        $this->assertTrue(Totp::verify(self::RFC_SECRET, '287082', 1, 31)); // previous tick's edge
        $this->assertFalse(Totp::verify(self::RFC_SECRET, '287082', 1, 120)); // four steps away
        $this->assertFalse(Totp::verify(self::RFC_SECRET, '287083', 1, 59)); // wrong digits
    }

    public function test_verify_rejects_anything_that_is_not_six_digits(): void
    {
        $this->assertFalse(Totp::verify(self::RFC_SECRET, '28708', 1, 59));
        $this->assertFalse(Totp::verify(self::RFC_SECRET, 'abcdef', 1, 59));
        $this->assertFalse(Totp::verify(self::RFC_SECRET, '', 1, 59));
    }

    public function test_base32_round_trips_and_rejects_bad_input(): void
    {
        $secret = Totp::generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertSame($secret, Totp::base32Encode(Totp::base32Decode($secret)));

        $this->expectException(InvalidArgumentException::class);
        Totp::base32Decode('not-valid-base-32-!!!');
    }

    public function test_provisioning_uri_carries_what_an_authenticator_needs(): void
    {
        $uri = Totp::provisioningUri('MissPack ERP', 'office@example.com', self::RFC_SECRET);

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret='.self::RFC_SECRET, $uri);
        $this->assertStringContainsString('issuer=MissPack+ERP', $uri);
        $this->assertStringContainsString('office%40example.com', $uri);
    }
}

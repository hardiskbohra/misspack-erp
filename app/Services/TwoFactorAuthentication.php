<?php

namespace App\Services;

use App\Models\User;
use App\Support\Totp;

/**
 * Two-factor authentication for the people who sign in to the office.
 *
 * The shape follows the way the office talks about it. *Enrolment* is the
 * account page handing somebody a key and asking their authenticator to prove
 * it got it; until that proof lands the secret is *pending* and locks nothing,
 * so a half-finished setup can never lock its owner out. *Sign-in* is the
 * password, then the challenge page asking for the six digits — or one of the
 * recovery codes, which are the spare key under the mat: each works exactly
 * once, each is stored only as a hash, and the plain list is shown exactly
 * once, at the moment it is made.
 *
 * Everything user-facing lives here so the two controllers (the challenge at
 * the door, the management card on the account page) stay thin, and so the
 * rules have one home to be tested against.
 */
class TwoFactorAuthentication
{
    /** The person proved their authenticator holds the key. */
    public function hasEnabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null
            && ! empty($user->two_factor_secret);
    }

    /** A key was made but never proven: setup in progress, sign-in unchanged. */
    public function isPending(User $user): bool
    {
        return $user->two_factor_confirmed_at === null
            && ! empty($user->two_factor_secret);
    }

    /**
     * Start enrolment over. Any earlier pending secret is torn up — starting
     * again is how a mistyped setup key gets fixed — and an already-confirmed
     * setup is never touched by this path (the controller guards that).
     */
    public function beginEnrollment(User $user): string
    {
        $secret = Totp::generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return $secret;
    }

    /**
     * Prove the authenticator got the key. Returns the recovery codes to show
     * once, or null when the code did not match — in which case the secret
     * stays pending and the person simply reads the next code off the app.
     */
    public function confirmEnrollment(User $user, string $code): ?array
    {
        if (! $this->isPending($user)) {
            return null;
        }

        if (! Totp::verify($user->two_factor_secret, $code)) {
            return null;
        }

        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $this->hashed($codes),
        ])->save();

        return $codes;
    }

    /**
     * The challenge at the door: six fresh digits, or one recovery code.
     * A recovery code that matches is spent on the spot — a code that could
     * be replayed would be a second password written on a sticky note.
     */
    public function verify(User $user, ?string $code, ?string $recoveryCode): bool
    {
        if (! $this->hasEnabled($user)) {
            return false;
        }

        if (filled($code) && Totp::verify($user->two_factor_secret, $code)) {
            return true;
        }

        if (filled($recoveryCode)) {
            $stored = collect($user->two_factor_recovery_codes ?? []);
            $hash = $this->hash($recoveryCode);

            if (! $stored->contains($hash)) {
                return false;
            }

            $user->forceFill([
                'two_factor_recovery_codes' => $stored
                    ->reject(fn (string $kept) => $kept === $hash)
                    ->values()
                    ->all(),
            ])->save();

            return true;
        }

        return false;
    }

    /** A fresh set of recovery codes; the old set stops working at once. */
    public function regenerateRecoveryCodes(User $user): ?array
    {
        if (! $this->hasEnabled($user)) {
            return null;
        }

        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $this->hashed($codes),
        ])->save();

        return $codes;
    }

    /** Turn it off entirely — enrolment and recovery codes alike. */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    /** The otpauth:// link for the account's pending or active key. */
    public function provisioningUri(User $user): ?string
    {
        if (empty($user->two_factor_secret)) {
            return null;
        }

        $issuer = (string) (config('app.name') ?: 'MissPack ERP');

        return Totp::provisioningUri($issuer, $user->email, $user->two_factor_secret);
    }

    /**
     * Spare keys: eight one-time codes, pronounced as `abc123-def456` so they
     * can be read over a phone. Only their SHA-256 hashes are ever stored —
     * they are random with 48 bits of entropy behind a throttled challenge, so
     * a plain fast hash is the right one (bcrypt is for keeping slow secrets,
     * not for spending single-use ones).
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = bin2hex(random_bytes(3)).'-'.bin2hex(random_bytes(3));
        }

        return $codes;
    }

    private function hashed(array $codes): array
    {
        return array_map(fn (string $code) => $this->hash($code), $codes);
    }

    private function hash(string $code): string
    {
        return hash('sha256', strtolower(str_replace(' ', '', trim($code))));
    }
}

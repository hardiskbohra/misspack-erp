<?php

namespace Tests\Unit;

use App\Rules\ValidGstin;
use App\Support\Gstin;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * UT-TAX-001 — the GSTIN check digit, the shapes it accepts, and the typos
 * it must refuse.
 *
 * The vectors are pinned, not recomputed: `24AATCM8816E1Z5` is the
 * organisation's own seeded GSTIN (so the value on every demo document is
 * genuinely valid), and `33AAACC1206D1ZN` is the canonical example used by
 * the public validator write-ups. `24ABCDE1234F1Z6` exists because the
 * earlier fixtures used `…Z5` — structurally fine, checksum-wrong; pinning
 * the corrected digit here is what keeps that fixture honest forever.
 */
final class GstinTest extends TestCase
{
    public function test_known_good_gstins_pass_structure_state_and_checksum(): void
    {
        foreach (['24AATCM8816E1Z5', '33AAACC1206D1ZN', '24ABCDE1234F1Z6'] as $gstin) {
            $this->assertTrue(Gstin::isValid($gstin), $gstin);
        }
    }

    public function test_the_check_digit_is_the_published_mod36_algorithm(): void
    {
        $this->assertSame('5', Gstin::checkDigit('24AATCM8816E1Z'));
        $this->assertSame('N', Gstin::checkDigit('33AAACC1206D1Z'));
        $this->assertSame('6', Gstin::checkDigit('24ABCDE1234F1Z'));
    }

    public function test_a_single_character_typo_anywhere_fails(): void
    {
        $valid = '24AATCM8816E1Z5';

        for ($i = 0; $i < 15; $i++) {
            $mutant = $valid;
            $mutant[$i] = $valid[$i] === 'Q' ? 'R' : 'Q'; // any different character
            $this->assertFalse(Gstin::isValid($mutant), "position {$i}");
        }
    }

    public function test_structural_and_state_rejections(): void
    {
        $this->assertFalse(Gstin::isValid('24AATCM8816E1Z'));      // 14 chars
        $this->assertFalse(Gstin::isValid('24AATCM8816E1A5'));     // Z must sit at 14
        $this->assertFalse(Gstin::isValid('24AATCM8816E0Z5'));     // entity 0 not allowed
        $this->assertFalse(Gstin::isValid('0GAATCM8816E1Z5'));     // state code not allotted
        $this->assertFalse(Gstin::isValid('AAAAAAAAAAAAAAA'));      // not a GSTIN at all
        $this->assertFalse(Gstin::isValid(null));
        $this->assertFalse(Gstin::isValid(''));
    }

    public function test_normalise_and_the_parts_it_unlocks(): void
    {
        $this->assertSame('24AATCM8816E1Z5', Gstin::normalise(' 24aatcm8816e1z5 '));
        $this->assertNull(Gstin::normalise('   '));
        $this->assertSame('24', Gstin::stateCode('24AATCM8816E1Z5'));
        $this->assertSame('Gujarat', Gstin::stateName('24AATCM8816E1Z5'));
        $this->assertSame('AATCM8816E', Gstin::pan('24AATCM8816E1Z5'));
        $this->assertTrue(Gstin::isValid('24aatcm8816e1z5')); // lowercase normalises
    }

    public function test_the_validation_rule_carries_one_message_everywhere(): void
    {
        $pass = Validator::make(['gstin' => '24AATCM8816E1Z5'], ['gstin' => ['nullable', new ValidGstin]]);
        $fail = Validator::make(['gstin' => '24AATCM8816E1Z6'], ['gstin' => ['nullable', new ValidGstin]]);
        $null = Validator::make(['gstin' => null], ['gstin' => ['nullable', new ValidGstin]]);

        $this->assertTrue($pass->passes());
        $this->assertTrue($null->passes());
        $this->assertTrue($fail->fails());
        $this->assertStringContainsString('check character', $fail->errors()->first('gstin'));
    }
}

<?php

namespace App\Rules;

use App\Support\Gstin;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Laravel validation rule wrapper for `Gstin::isValid()`.
 *
 * Kept as a rule (rather than a closure repeated in every controller) so the
 * message stays the one sentence a user can act on, and so every form that
 * collects a GST-in number — client, vendor, organisation, invoice snapshot —
 * fails in exactly the same way.
 */
final class ValidGstin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return; // nullable fields are ruled by 'nullable', not by us
        }

        if (! is_string($value) || ! Gstin::isValid($value)) {
            $fail('The :attribute is not a valid GSTIN — check the 15 characters, including the state code and the last check character.');
        }
    }
}

<?php

namespace Tests\Unit;

use App\Models\VendorPaymentEntry;
use App\Services\CashflowVendorPaymentSync;
use PHPUnit\Framework\TestCase;

/**
 * One payment, one owner.
 *
 * A rupee payment to a vendor is written by exactly one screen: the vendor
 * ledger, or the cashflow module. `VendorPaymentEntry::source` names the owner,
 * and the vendor page reads it to decide whether a row can be edited there —
 * editing the derived copy would silently rewrite the bank row it came from.
 *
 * The value is a constant shared with the service that writes it, so the two
 * halves of the rule cannot drift into different spellings.
 */
class CashflowVendorOwnershipTest extends TestCase
{
    public function test_a_row_derived_from_cashflow_names_the_cashflow_as_owner(): void
    {
        $entry = new VendorPaymentEntry(['source' => CashflowVendorPaymentSync::SOURCE]);

        $this->assertTrue($entry->isFromCashflow());
    }

    public function test_a_row_entered_in_the_vendor_ledger_owns_itself(): void
    {
        $entry = new VendorPaymentEntry(['particular' => 'Advance to supplier']);

        $this->assertFalse($entry->isFromCashflow());
    }

    public function test_the_ownership_column_can_be_mass_assigned(): void
    {
        /* The sync fills the row from an array; a source that is not fillable
           would quietly fall back to null and hand the row back to the vendor
           ledger — the exact double-owner bug the column exists to prevent. */
        $entry = new VendorPaymentEntry(['source' => CashflowVendorPaymentSync::SOURCE]);

        $this->assertSame(CashflowVendorPaymentSync::SOURCE, $entry->source);
    }
}

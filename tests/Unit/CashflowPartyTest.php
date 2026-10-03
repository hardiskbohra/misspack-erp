<?php

namespace Tests\Unit;

use App\Models\CashflowEntry;
use PHPUnit\Framework\TestCase;

/**
 * The label follows the link.
 *
 * A party statement is built from the *column* (`PartyStatement` reads
 * `client_id` / `vendor_id`, never `related_party_type`), while the ledger's
 * filter and the report's "Not set" row read the label. When the two disagree,
 * one entry is on a client's statement and invisible to the filter for
 * "Client" — the same rows answering two questions differently, which is the
 * manual reconciliation these screens exist to delete.
 *
 * Quick Entry made that easy to do: the party selector defaults to its first
 * option, so a payment to a vendor filed without touching it was labelled
 * "Client" while linked to the vendor. `alignPartyType()` is the rule that stops
 * it, and it is a static method on the model so it can be proved here, without
 * a database.
 */
class CashflowPartyTest extends TestCase
{
    public function test_a_linked_party_is_the_label(): void
    {
        $data = CashflowEntry::alignPartyType([
            'related_party_type' => 'other',
            'client_id' => 7,
        ]);

        $this->assertSame('client', $data['related_party_type'], 'the link is the fact');
    }

    public function test_the_default_first_option_does_not_overrule_the_link(): void
    {
        /* The selector is left where it opens ("Client") and the office picks a
           vendor: the entry belongs to the vendor. */
        $data = CashflowEntry::alignPartyType([
            'related_party_type' => 'client',
            'vendor_id' => 3,
        ]);

        $this->assertSame('vendor', $data['related_party_type']);
    }

    public function test_a_choice_that_names_one_of_its_own_links_stands(): void
    {
        $data = CashflowEntry::alignPartyType([
            'related_party_type' => 'employee',
            'employee_id' => 12,
        ]);

        $this->assertSame('employee', $data['related_party_type']);
    }

    public function test_nothing_linked_leaves_the_choice_alone(): void
    {
        foreach (['expense', 'owner', 'other'] as $type) {
            $data = CashflowEntry::alignPartyType(['related_party_type' => $type]);

            $this->assertSame($type, $data['related_party_type'], $type.' stands on its own');
        }
    }

    public function test_a_free_text_name_is_not_a_link(): void
    {
        $data = CashflowEntry::alignPartyType([
            'related_party_type' => 'other',
            'related_party_name' => 'Ramesh Transport',
        ]);

        $this->assertSame('other', $data['related_party_type'], 'a typed name links to nothing');
    }

    public function test_the_strongest_link_wins_when_more_than_one_is_set(): void
    {
        $data = CashflowEntry::alignPartyType([
            'related_party_type' => 'owner',
            'client_id' => 1,
            'vendor_id' => 2,
            'employee_id' => 3,
        ]);

        $this->assertSame('client', $data['related_party_type'], 'client, vendor, employee — in that order');
    }

    public function test_the_dialog_opens_on_the_option_the_selector_will_show(): void
    {
        /* The list is the office's, and a `<select>` with nothing marked
           `selected` shows its first option — so the server has to name that
           one. A name hard-coded in the class is how the picker on screen and
           the selector above it start disagreeing. */
        $this->assertSame('vendor', CashflowEntry::partyTypeFor(null, [
            'vendor' => 'Vendor',
            'client' => 'Client',
        ]));
    }

    public function test_a_failed_save_keeps_the_party_it_was_filing(): void
    {
        $this->assertSame('expense', CashflowEntry::partyTypeFor('expense', [
            'client' => 'Client',
            'expense' => 'Cash Expense',
        ]));
    }

    public function test_a_type_the_list_no_longer_offers_cannot_put_a_picker_on_screen(): void
    {
        $this->assertSame('client', CashflowEntry::partyTypeFor('owner', [
            'client' => 'Client',
            'vendor' => 'Vendor',
        ]));
    }

    public function test_a_hand_made_request_cannot_name_the_field(): void
    {
        /* `old()` reads whatever the last request posted — an array included. */
        $this->assertSame('client', CashflowEntry::partyTypeFor(['client' => '1'], [
            'client' => 'Client',
        ]));
    }

    public function test_the_rule_is_the_model_s_own_list_of_columns(): void
    {
        $this->assertSame(
            ['client' => 'client_id', 'vendor' => 'vendor_id', 'employee' => 'employee_id'],
            CashflowEntry::partyLinkColumns(),
            'a fourth link means one line here and one `data-party-for` in the dialog'
        );
    }
}

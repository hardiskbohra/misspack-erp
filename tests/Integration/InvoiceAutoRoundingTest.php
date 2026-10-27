<?php

namespace Tests\Integration;

use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** Real HTTP and full migrated schema; values in these scenarios are not external payments. */
class InvoiceAutoRoundingTest extends TestCase
{
    use IsolatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]));
        $this->actingAs(User::create(['name' => 'Accounts', 'email' => 'accounts@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']));
    }

    private function data(string $type = 'proforma', string $price = '100.49', array $overrides = []): array
    {
        return array_merge([
            'invoice_number' => null, 'invoice_type' => $type, 'status' => 'draft', 'currency' => 'INR', 'gst_type' => 'export',
            'items' => [['product_name' => 'Cartons', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => $price, 'gst_percent' => 0]],
        ], $overrides);
    }

    private function createInvoice(array $data): SalesInvoice
    {
        $this->post(route('sales-invoices.store'), $data)->assertSessionHasNoErrors()->assertStatus(302)->assertSessionHas('success');
        return SalesInvoice::query()->latest('id')->firstOrFail();
    }

    public function test_new_inr_proformas_and_tax_invoices_round_half_up_and_store_the_signed_difference(): void
    {
        foreach (['proforma', 'tax'] as $type) {
            foreach ([['100.49', '100.00', '-0.49'], ['100.50', '101.00', '0.50'], ['100.99', '101.00', '0.01'],
                ['100.00', '100.00', '0.00']] as [$price, $total, $difference]) {
                $invoice = $this->createInvoice($this->data($type, $price));
                $this->assertSame($total, $invoice->total_amount);
                $this->assertSame($difference, $invoice->round_off);
                $this->assertSame($total, $invoice->balance_amount);
                $this->assertTrue((bool) $invoice->auto_round_total);
                $this->assertStringNotContainsString('Paise', $invoice->amount_in_words);
            }
        }
    }

    public function test_half_paise_on_a_fractional_quantity_is_rounded_before_the_whole_rupee_total(): void
    {
        $invoice = $this->createInvoice($this->data('proforma', '633.00', [
            'items' => [['product_name' => 'Cartons', 'quantity' => '0.015', 'unit' => 'pcs', 'unit_price' => '633.00', 'gst_percent' => 0]],
        ]));
        $this->assertSame('9.50', $invoice->items->first()->gross_amount);
        $this->assertSame('0.50', $invoice->round_off);
        $this->assertSame('10.00', $invoice->total_amount);
    }

    public function test_rounding_is_after_discounts_and_charges_and_is_visible_on_print_and_detail(): void
    {
        $invoice = $this->createInvoice($this->data('tax', '100.25', ['discount_type' => 'amount', 'discount_value' => '10.00',
            'freight_amount' => '0.25']));
        $this->assertSame('90.25', $invoice->taxable_amount);
        $this->assertSame('0.50', $invoice->round_off);
        $this->assertSame('91.00', $invoice->total_amount);
        $this->get(route('sales-invoices.show', $invoice))->assertOk()->assertSee('Round off')->assertSee('91.00');
        $this->get(route('sales-invoices.print', $invoice))->assertOk()->assertSee('Round off')->assertSee('₹91');
    }

    public function test_auto_rounding_is_applied_after_gst_invoice_discount_and_charges(): void
    {
        $invoice = $this->createInvoice($this->data('tax', '100.25', [
            'gst_type' => 'intra_state', 'discount_type' => 'amount', 'discount_value' => '10.00', 'freight_amount' => '0.26',
            'items' => [['product_name' => 'Cartons', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => '100.25', 'gst_percent' => 18]],
        ]));
        $this->assertSame('90.25', $invoice->taxable_amount);
        $this->assertSame('8.12', $invoice->cgst_amount);
        $this->assertSame('8.12', $invoice->sgst_amount);
        $this->assertSame('0.25', $invoice->round_off);
        $this->assertSame('107.00', $invoice->total_amount);
        $this->assertSame('107.00', $invoice->balance_amount);
    }

    public function test_explicit_manual_override_is_preserved_and_auto_mode_does_not_trust_submitted_round_off(): void
    {
        $manual = $this->createInvoice($this->data('proforma', '100.49', ['auto_round_total' => '0', 'round_off' => '0.21']));
        $this->assertSame('100.70', $manual->total_amount);
        $this->assertSame('0.21', $manual->round_off);
        $this->assertFalse((bool) $manual->auto_round_total);
        // Legacy API callers that explicitly supply a non-zero round-off also
        // retain their manual amount without knowing about the new flag.
        $legacy = $this->createInvoice($this->data('tax', '100.49', ['round_off' => '0.21']));
        $this->assertSame('100.70', $legacy->total_amount);
        $this->assertFalse((bool) $legacy->auto_round_total);
        $automatic = $this->createInvoice($this->data('tax', '100.49', ['auto_round_total' => '1', 'round_off' => '99']));
        $this->assertSame('100.00', $automatic->total_amount);
        $this->assertSame('-0.49', $automatic->round_off);
    }

    public function test_draft_edits_recompute_auto_mode_and_keep_manual_mode_when_it_is_selected(): void
    {
        $invoice = $this->createInvoice($this->data());
        $this->put(route('sales-invoices.update', $invoice), $this->data('proforma', '120.99', [
            'invoice_number' => $invoice->invoice_number, 'auto_round_total' => '1', 'round_off' => '-0.49',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('sales-invoices.show', $invoice));
        $this->assertSame('121.00', $invoice->fresh()->total_amount);
        $this->assertSame('0.01', $invoice->fresh()->round_off);

        $this->put(route('sales-invoices.update', $invoice), $this->data('proforma', '120.99', [
            'invoice_number' => $invoice->invoice_number, 'auto_round_total' => '0', 'round_off' => '0.23',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('sales-invoices.show', $invoice));
        $this->assertSame('121.22', $invoice->fresh()->total_amount);
        $this->assertSame('0.23', $invoice->fresh()->round_off);
        $this->assertFalse((bool) $invoice->fresh()->auto_round_total);

        // An older caller omitting the new policy flag must not switch it back on.
        $this->put(route('sales-invoices.update', $invoice), $this->data('proforma', '120.99', [
            'invoice_number' => $invoice->invoice_number, 'round_off' => '0.23',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('121.22', $invoice->fresh()->total_amount);

        $this->put(route('sales-invoices.update', $invoice), $this->data('proforma', '120.99', [
            'invoice_number' => $invoice->invoice_number, 'auto_round_total' => '1', 'round_off' => '0.23',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('121.00', $invoice->fresh()->total_amount);
        $this->assertSame('0.01', $invoice->fresh()->round_off);
        $this->assertTrue((bool) $invoice->fresh()->auto_round_total);
    }

    public function test_existing_legacy_invoice_is_not_automatically_changed_on_edit(): void
    {
        $legacy = SalesInvoice::create(['invoice_number' => 'LEGACY-PI', 'invoice_type' => 'proforma', 'status' => 'draft',
            'currency' => 'INR', 'gst_type' => 'export', 'total_amount' => '100.49', 'balance_amount' => '100.49', 'round_off' => 0]);
        $legacy->items()->create(['product_name' => 'Cartons', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => '100.49']);
        $this->put(route('sales-invoices.update', $legacy), $this->data('proforma', '100.49', [
            'invoice_number' => $legacy->invoice_number, 'round_off' => '0',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('sales-invoices.show', $legacy));
        $this->assertSame('100.49', $legacy->fresh()->total_amount);
        $this->assertFalse((bool) $legacy->fresh()->auto_round_total);
    }

    public function test_foreign_currency_and_cash_invoices_are_not_auto_rounded(): void
    {
        foreach ([['proforma', 'USD'], ['tax', 'RMB'], ['cash', 'INR']] as [$type, $currency]) {
            $invoice = $this->createInvoice($this->data($type, '100.49', ['currency' => $currency]));
            $this->assertSame('100.49', $invoice->total_amount);
            $this->assertSame('0.00', $invoice->round_off);
        }
    }

    public function test_switching_an_auto_draft_to_foreign_or_cash_without_a_round_off_does_not_leak_the_old_adjustment(): void
    {
        foreach ([['proforma', 'USD'], ['proforma', 'RMB'], ['cash', 'INR']] as [$type, $currency]) {
            $invoice = $this->createInvoice($this->data('proforma', '100.49', ['status' => 'draft']));
            $this->assertSame('-0.49', $invoice->round_off);
            $this->put(route('sales-invoices.update', $invoice), $this->data($type, '100.49', [
                'invoice_number' => $invoice->invoice_number, 'currency' => $currency, 'status' => 'draft',
            ]))->assertSessionHasNoErrors()->assertRedirect(route('sales-invoices.show', $invoice));
            $this->assertSame('0.00', $invoice->fresh()->round_off);
            $this->assertSame('100.49', $invoice->fresh()->total_amount);
            $this->assertFalse((bool) $invoice->fresh()->auto_round_total);
        }
    }

    public function test_proforma_conversion_preserves_exact_fractional_remainder_and_cash_claim(): void
    {
        $pi = $this->createInvoice($this->data('proforma', '100.49', ['auto_round_total' => '0', 'round_off' => 0]));
        $this->post(route('sales-invoices.convert-cash', $pi), ['conversion_mode' => 'amount', 'conversion_value' => '30.11', 'transfer_received' => 0])
            ->assertSessionHasNoErrors()->assertStatus(302)->assertSessionHas('success');
        $cash = SalesInvoice::query()->where('invoice_type', 'cash')->firstOrFail();
        $this->assertSame('30.11', $cash->total_amount);
        $this->post(route('sales-invoices.convert', $pi->fresh()))->assertSessionHasNoErrors()->assertStatus(302)->assertSessionHas('success');
        $tax = SalesInvoice::query()->where('invoice_type', 'tax')->firstOrFail();
        $this->assertSame('70.38', $tax->total_amount);
        $this->assertSame('70.38', $tax->balance_amount);
        $this->assertSame('100.49', $pi->fresh()->total_amount);
        $this->assertFalse((bool) $tax->auto_round_total);
    }

    public function test_a_rounded_proforma_keeps_the_exact_fractional_remainder_after_a_partial_cash_conversion(): void
    {
        $pi = $this->createInvoice($this->data('proforma', '100.49'));
        $this->assertSame('100.00', $pi->total_amount);
        $this->post(route('sales-invoices.convert-cash', $pi), ['conversion_mode' => 'amount', 'conversion_value' => '30.11', 'transfer_received' => 0])
            ->assertSessionHasNoErrors()->assertStatus(302)->assertSessionHas('success');
        $this->post(route('sales-invoices.convert', $pi->fresh()))->assertSessionHasNoErrors()->assertStatus(302)->assertSessionHas('success');
        $tax = SalesInvoice::query()->where('invoice_type', 'tax')->firstOrFail();
        $this->assertSame('69.89', $tax->total_amount);
        $this->assertFalse((bool) $tax->auto_round_total);
        $this->assertSame('100.00', $pi->fresh()->total_amount);
    }

    public function test_partial_conversion_preserves_fractional_receipts_and_the_outstanding_balance(): void
    {
        $pi = $this->createInvoice($this->data('proforma', '100.49', ['amount_paid' => '20.37']));
        $this->post(route('sales-invoices.convert-cash', $pi), ['conversion_mode' => 'amount', 'conversion_value' => '30.11', 'transfer_received' => '10.09'])
            ->assertSessionHasNoErrors()->assertStatus(302)->assertSessionHas('success');
        $cash = SalesInvoice::query()->where('invoice_type', 'cash')->firstOrFail();
        $this->post(route('sales-invoices.convert', $pi->fresh()))->assertSessionHasNoErrors()->assertStatus(302)->assertSessionHas('success');
        $tax = SalesInvoice::query()->where('invoice_type', 'tax')->firstOrFail();
        $this->assertSame('30.11', $cash->fresh()->total_amount);
        $this->assertSame('10.09', $cash->fresh()->amount_paid);
        $this->assertSame('20.02', $cash->fresh()->balance_amount);
        $this->assertSame('69.89', $tax->total_amount);
        $this->assertSame('10.28', $tax->amount_paid);
        $this->assertSame('59.61', $tax->balance_amount);
        $this->assertSame(79.63, round((float) $cash->fresh()->balance_amount + (float) $tax->balance_amount, 2));
        $this->assertFalse((bool) $cash->fresh()->auto_round_total);
        $this->assertFalse((bool) $tax->auto_round_total);
    }

    public function test_full_conversion_keeps_fractional_total_and_transferred_opening_receipt(): void
    {
        $pi = $this->createInvoice($this->data('proforma', '100.49', ['auto_round_total' => '0', 'amount_paid' => '20.37']));
        $this->post(route('sales-invoices.convert', $pi))->assertSessionHasNoErrors()->assertStatus(302)->assertSessionHas('success');
        $tax = SalesInvoice::query()->where('invoice_type', 'tax')->firstOrFail();
        $this->assertSame('100.49', $tax->total_amount);
        $this->assertSame('20.37', $tax->amount_paid);
        $this->assertSame('80.12', $tax->balance_amount);
        $this->assertSame('0.00', $pi->fresh()->amount_paid);
        $this->assertFalse((bool) $tax->auto_round_total);
    }

    public function test_auto_rounding_is_included_in_the_e_invoice_payload(): void
    {
        $invoice = $this->createInvoice($this->data('tax', '100.49', ['invoice_date' => '2026-10-27', 'place_of_supply' => '24',
            'items' => [['product_name' => 'Cartons', 'hsn_sac' => '4819', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => '100.49', 'gst_percent' => 0]]]));
        $values = (new \App\Services\EInvoice\EInvoicePayload($invoice))->build()['ValDtls'];
        $this->assertSame(-0.49, $values['RndOffAmt']);
        $this->assertSame(100.0, $values['TotInvVal']);
        $this->assertSame(100.0, round($values['AssVal'] + $values['CgstVal'] + $values['SgstVal'] + $values['IgstVal'] + $values['OthChrg'] + $values['RndOffAmt'], 2));
    }

    public function test_duplicate_is_a_new_draft_with_auto_rounding_but_does_not_change_the_source(): void
    {
        $source = $this->createInvoice($this->data('proforma', '100.49', ['auto_round_total' => '0', 'round_off' => '0.10']));
        $this->post(route('sales-invoices.duplicate', $source))->assertSessionHasNoErrors()->assertStatus(302)->assertSessionHas('success');
        $copy = SalesInvoice::query()->latest('id')->firstOrFail();
        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame('100.59', $source->fresh()->total_amount);
        $this->assertSame('100.00', $copy->total_amount);
        $this->assertSame('-0.49', $copy->round_off);
        $this->assertSame('100.00', $copy->balance_amount);
        $this->assertTrue((bool) $copy->auto_round_total);
    }

    public function test_issued_tax_invoice_rejects_switching_rounding_policy_or_changing_the_adjustment(): void
    {
        $invoice = $this->createInvoice($this->data('tax', '100.49', ['status' => 'sent']));
        // A working-field edit by an older client need not post the new flag
        // or round-off; neither omission changes the issued money.
        $this->put(route('sales-invoices.update', $invoice), $this->data('tax', '100.49', [
            'invoice_number' => $invoice->invoice_number, 'status' => 'sent', 'internal_notes' => 'Delivery on Monday',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('sales-invoices.show', $invoice));
        $this->assertSame('Delivery on Monday', $invoice->fresh()->internal_notes);
        $this->put(route('sales-invoices.update', $invoice), $this->data('tax', '100.49', [
            'invoice_number' => $invoice->invoice_number, 'status' => 'sent',
            'auto_round_total' => '0', 'round_off' => $invoice->round_off,
        ]))->assertSessionHasErrors('invoice');
        $this->put(route('sales-invoices.update', $invoice), $this->data('tax', '100.49', [
            'invoice_number' => $invoice->invoice_number, 'status' => 'sent',
            'auto_round_total' => '1', 'round_off' => '0.00',
        ]))->assertSessionHasErrors('invoice');
        $this->assertSame('100.00', $invoice->fresh()->total_amount);
        $this->assertTrue((bool) $invoice->fresh()->auto_round_total);
    }

    public function test_form_exposes_auto_rounding_and_manual_override_with_an_accessible_explanation(): void
    {
        $this->get(route('sales-invoices.create'))->assertOk()->assertSee('id="autoRoundTotal"', false)
            ->assertSee('Automatically round')->assertSee('Uncheck')->assertSee('INR');
    }
}

<?php

namespace Tests\Integration;

use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPaymentEntry;
use App\Services\PartyStatement;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\AnalyticsDatabaseFixture;
use Tests\TestCase;

/** A pre-partial-conversion schema can still classify existing legacy links. */
class PurchaseVisibilityLegacySchemaTest extends TestCase
{
    public function test_legacy_links_hide_without_requiring_partial_conversion_columns(): void
    {
        AnalyticsDatabaseFixture::install();
        $this->assertFalse(Schema::hasColumn('purchase_invoices', 'converted_amount'));
        $this->actingAs(User::create(['name' => 'Legacy Accounts', 'email' => 'legacy-visibility@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']));
        $vendor = Vendor::create(['vendor_number' => 'LEGACY-VIS', 'vendor_name' => 'Legacy Vendor', 'preferred_currency' => 'INR']);
        $bill = PurchaseInvoice::create(['invoice_number' => 'LEGACY-BILL', 'invoice_type' => 'bill', 'status' => 'received',
            'public_token' => Str::random(48), 'vendor_id' => $vendor->id, 'currency' => 'INR', 'total_amount' => 100]);
        $order = PurchaseInvoice::create(['invoice_number' => 'LEGACY-PO', 'invoice_type' => 'order', 'status' => 'billed',
            'public_token' => Str::random(48), 'vendor_id' => $vendor->id, 'currency' => 'INR', 'total_amount' => 100,
            'converted_invoice_id' => $bill->id]);
        foreach ([[$order, 'order'], [$bill, 'bill']] as [$document, $category]) {
            VendorPaymentEntry::create(['vendor_id' => $vendor->id, 'purchase_invoice_id' => $document->id,
                'transaction_date' => '2026-10-01', 'invoice_number' => $document->invoice_number, 'foreign_currency' => 'INR',
                'foreign_amount' => 100, 'transaction_type' => 'credit', 'entry_category' => $category,
                'particular' => $category.' '.$document->invoice_number, 'status' => 'booked']);
        }
        $this->assertSame([$order->id], PurchaseInvoice::fullyConverted()->pluck('id')->all());
        $this->assertSame([$bill->id], PurchaseInvoice::notFullyConverted()->pluck('id')->all());
        $default = $this->get(route('purchase-invoices.index'))->assertOk();
        $this->assertSame(['LEGACY-BILL'], $default->viewData('invoices')->pluck('invoice_number')->all());
        $include = $this->get(route('purchase-invoices.index', ['converted' => 'include']))->assertOk();
        $this->assertCount(2, $include->viewData('invoices'));
        $statement = app(PartyStatement::class)->build('vendor', $vendor->id, null, null, ['currency' => 'INR']);
        $this->assertSame(['LEGACY-BILL'], array_column($statement['rows'], 'reference'));
        $this->assertSame(100.0, $statement['totals']['credit']);
    }
}

<?php

namespace Tests\Integration;

use App\Models\CashflowAccount;
use App\Models\CashflowAllocation;
use App\Models\CashflowEntry;
use App\Models\Client;
use App\Models\PartyStatementShare;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchasePaymentSplit;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPaymentEntry;
use App\Services\CashflowVendorPaymentSync;
use App\Services\PartyStatement;
use App\Services\PurchaseBillLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** Project context on the vendor ledger, using only a disposable full schema. */
class VendorStatementProjectsTest extends TestCase
{
    use IsolatedDatabase;

    private Vendor $vendor;
    private Client $client;
    private CashflowAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]));
        Carbon::setTestNow('2026-11-01 12:00:00');
        $this->actingAs(User::create(['name' => 'Statement Accounts', 'email' => 'project-statements@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']));
        $this->vendor = Vendor::create(['vendor_number' => 'STMT-PROJECT-VENDOR', 'vendor_name' => 'Statement Supplier', 'preferred_currency' => 'INR']);
        $this->client = Client::create(['client_number' => 'STMT-PROJECT-CLIENT', 'company_name' => 'Statement Client', 'preferred_currency' => 'INR']);
        $this->account = CashflowAccount::create(['account_name' => 'Statement Test Bank', 'account_type' => 'current', 'currency' => 'INR']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disableQueryLog();
        parent::tearDown();
    }

    private function project(string $name): Project
    {
        return Project::create(['project_number' => 'PROJECT-'.Str::random(12), 'client_id' => $this->client->id, 'name' => $name]);
    }

    private function bill(string $number, ?Project $project, ?Vendor $vendor = null): PurchaseInvoice
    {
        $vendor ??= $this->vendor;
        return PurchaseInvoice::create(['invoice_number' => $number, 'invoice_type' => 'bill', 'status' => 'received',
            'public_token' => Str::random(48), 'vendor_id' => $vendor->id, 'vendor_company_name' => $vendor->vendor_name,
            'project_id' => $project?->id, 'invoice_date' => '2026-10-10', 'currency' => 'INR', 'total_amount' => 200]);
    }

    private function ledger(string $reference, string $type, float $amount, array $extra = []): VendorPaymentEntry
    {
        return VendorPaymentEntry::create(array_replace([
            'vendor_id' => $this->vendor->id, 'transaction_date' => '2026-10-11', 'invoice_number' => $reference,
            'foreign_currency' => 'INR', 'foreign_amount' => $amount, 'transaction_type' => $type,
            'entry_category' => $type === 'credit' ? 'bill' : 'payment', 'particular' => 'Original '.$reference,
            'status' => $type === 'credit' ? 'booked' : 'paid', 'amount_in_inr' => $amount,
        ], $extra));
    }

    private function cashflow(string $reference, float $amount, array $extra = []): CashflowEntry
    {
        return CashflowEntry::create(array_replace([
            'entry_date' => '2026-10-12', 'particular' => 'Original '.$reference, 'invoice_bill_number' => $reference,
            'transaction_type' => 'debit', 'debit_amount' => $amount, 'credit_amount' => 0,
            'currency' => 'INR', 'account_id' => $this->account->id, 'accounting_status' => 'booked',
            'vendor_id' => $this->vendor->id,
        ], $extra));
    }

    private function statement(): array
    {
        return app(PartyStatement::class)->build('vendor', $this->vendor->id, null, null, ['currency' => 'INR']);
    }

    private function row(array $statement, string $reference): array
    {
        $row = collect($statement['rows'])->firstWhere('reference', $reference);
        $this->assertNotNull($row, 'Statement row '.$reference.' should exist.');
        return $row;
    }

    public function test_ledger_bill_and_archived_project_names_preserve_descriptions_and_balances(): void
    {
        $direct = $this->project('Direct packaging');
        $billProject = $this->project('North clinic cartons');
        $archived = $this->project('Archived labels');
        $bill = $this->bill('BILL-RECON', $billProject);
        $posting = app(PurchaseBillLedger::class)->sync($bill);
        $posting->forceFill(['project_id' => null])->save(); // older posting: document still owns the project
        $this->ledger('DIRECT', 'credit', 100, ['project_id' => $direct->id]);
        $this->ledger('NO-PROJECT', 'debit', 35);
        $this->ledger('ARCHIVED', 'debit', 25, ['project_id' => $archived->id]);
        $archived->delete();
        $before = VendorPaymentEntry::orderBy('id')->get()->toArray();

        $statement = $this->statement();
        $this->assertSame(['Direct packaging'], $this->row($statement, 'DIRECT')['project_names'] ?? []);
        $this->assertSame(['North clinic cartons'], $this->row($statement, 'BILL-RECON')['project_names'] ?? []);
        $this->assertSame(['Archived labels'], $this->row($statement, 'ARCHIVED')['project_names'] ?? []);
        $this->assertSame([], $this->row($statement, 'NO-PROJECT')['project_names'] ?? []);
        $this->assertSame('Original DIRECT', $this->row($statement, 'DIRECT')['particular']);
        $this->assertSame('Purchase bill BILL-RECON', $this->row($statement, 'BILL-RECON')['particular']);
        $this->assertSame(['debit' => 60.0, 'credit' => 300.0, 'closing' => 240.0, 'count' => 4], $statement['totals']);
        $this->assertSame($before, VendorPaymentEntry::orderBy('id')->get()->toArray());
    }

    public function test_cashflow_project_allocations_and_safe_legacy_name_matches_do_not_duplicate_money(): void
    {
        $alpha = $this->project('Alpha cartons');
        $beta = $this->project('Beta labels');
        $gamma = $this->project('Gamma cartons');
        $secret = $this->project('Fuzzy match must not reveal this');
        $bill = $this->bill('B-ALLOC', $beta);
        $allocated = $this->cashflow('SPLIT-PAYMENT', 120);
        foreach ([[$alpha->id, null, 30], [$alpha->id, null, 20], [null, $bill->id, 40]] as [$projectId, $billId, $amount]) {
            CashflowAllocation::create(['cashflow_entry_id' => $allocated->id, 'project_id' => $projectId,
                'purchase_invoice_id' => $billId, 'amount' => $amount, 'currency' => 'INR']);
        }
        app(CashflowVendorPaymentSync::class)->sync($allocated);
        $this->cashflow('DIRECT-CASHFLOW', 10, ['project_id' => $gamma->id]);
        $this->cashflow('LEGACY-EXACT', 5, ['vendor_id' => null, 'related_party_type' => 'vendor',
            'related_party_name' => 'Statement Supplier', 'project_id' => $gamma->id]);
        $this->cashflow('LEGACY-FUZZY', 6, ['vendor_id' => null, 'related_party_type' => 'vendor',
            'related_party_name' => 'Another Statement Supplier Group', 'project_id' => $secret->id]);

        $statement = $this->statement();
        $this->assertSame(['Alpha cartons', 'Beta labels'], $this->row($statement, 'SPLIT-PAYMENT')['project_names'] ?? []);
        $this->assertSame(['Gamma cartons'], $this->row($statement, 'DIRECT-CASHFLOW')['project_names'] ?? []);
        $this->assertSame(['Gamma cartons'], $this->row($statement, 'LEGACY-EXACT')['project_names'] ?? []);
        $this->assertSame([], $this->row($statement, 'LEGACY-FUZZY')['project_names'] ?? []);
        $this->assertSame(['debit' => 141.0, 'credit' => 0.0, 'closing' => -141.0, 'count' => 4], $statement['totals']);
        $this->assertSame(120.0, $this->row($statement, 'SPLIT-PAYMENT')['debit']);
        $this->assertCount(1, VendorPaymentEntry::where('cashflow_entry_id', $allocated->id)->get());
    }

    public function test_partially_converted_purchase_payment_keeps_project_context_after_its_document_link_becomes_splits(): void
    {
        $project = $this->project('Conversion project');
        $this->post(route('purchase-invoices.store'), ['invoice_number' => 'PO-PROJECT-SPLIT', 'invoice_type' => 'order',
            'status' => 'approved', 'vendor_id' => $this->vendor->id, 'project_id' => $project->id, 'currency' => 'INR',
            'gst_type' => 'export', 'invoice_date' => '2026-10-01', 'terms_conditions' => null,
            'items' => [['product_name' => 'Cartons', 'quantity' => 1, 'unit_price' => '1000', 'gst_percent' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $order = PurchaseInvoice::where('invoice_number', 'PO-PROJECT-SPLIT')->firstOrFail();
        $payment = $this->ledger('PAY-PROJECT-SPLIT', 'debit', 600, ['purchase_invoice_id' => $order->id]);
        $this->post(route('purchase-invoices.convert-partial', $order), ['kind' => 'cash', 'mode' => 'amount', 'value' => '400',
            'payments' => [$payment->id => '200']])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($payment->fresh()->purchase_invoice_id);
        $this->assertSame(2, PurchasePaymentSplit::where('vendor_payment_entry_id', $payment->id)->count());

        $statement = $this->statement();
        $this->assertSame(['Conversion project'], $this->row($statement, 'PAY-PROJECT-SPLIT')['project_names'] ?? []);
        $this->assertSame(['debit' => 600.0, 'credit' => 1000.0, 'closing' => 400.0, 'count' => 3], $statement['totals']);
    }

    public function test_other_vendors_purchase_and_cashflow_links_cannot_reveal_projects(): void
    {
        $other = Vendor::create(['vendor_number' => 'OTHER-VENDOR', 'vendor_name' => 'Other Supplier', 'preferred_currency' => 'INR']);
        $secret = $this->project('Other vendor secret');
        $safe = $this->project('Our valid project');
        $wrongBill = $this->bill('OTHER-BILL', $secret, $other);
        $ourBill = $this->bill('OUR-BILL', $safe);
        $wrongCashflow = $this->cashflow('OTHER-CASHFLOW', 8, ['vendor_id' => $other->id, 'project_id' => $secret->id]);
        $wrongLedger = $this->ledger('WRONG-LINKS', 'credit', 50, ['purchase_invoice_id' => $wrongBill->id, 'cashflow_entry_id' => $wrongCashflow->id]);
        PurchasePaymentSplit::create(['vendor_payment_entry_id' => $wrongLedger->id, 'purchase_invoice_id' => $wrongBill->id, 'amount' => 10]);
        $splitPayment = $this->ledger('MIXED-PURCHASE-SPLIT', 'debit', 20);
        PurchasePaymentSplit::create(['vendor_payment_entry_id' => $splitPayment->id, 'purchase_invoice_id' => $wrongBill->id, 'amount' => 10]);
        PurchasePaymentSplit::create(['vendor_payment_entry_id' => $splitPayment->id, 'purchase_invoice_id' => $ourBill->id, 'amount' => 10]);
        // The pre-existing statement search includes this conflicting name match,
        // but it must not expose the other vendor's project.
        $this->cashflow('OTHER-WRONG-NAME', 7, ['vendor_id' => $other->id, 'project_id' => $secret->id,
            'related_party_type' => 'vendor', 'related_party_name' => $this->vendor->vendor_name]);
        $sale = SalesInvoice::create(['invoice_number' => 'PRIVATE-SALE', 'invoice_type' => 'tax', 'status' => 'sent',
            'client_id' => $this->client->id, 'project_id' => $secret->id, 'invoice_date' => '2026-10-11',
            'currency' => 'INR', 'total_amount' => 10]);
        $ours = $this->cashflow('MIXED-ALLOCATION', 40);
        CashflowAllocation::create(['cashflow_entry_id' => $ours->id, 'project_id' => $secret->id,
            'purchase_invoice_id' => $wrongBill->id, 'amount' => 10, 'currency' => 'INR']);
        CashflowAllocation::create(['cashflow_entry_id' => $ours->id, 'project_id' => $safe->id, 'amount' => 10, 'currency' => 'INR']);
        CashflowAllocation::create(['cashflow_entry_id' => $ours->id, 'project_id' => $secret->id,
            'sales_invoice_id' => $sale->id, 'amount' => 10, 'currency' => 'INR']);
        CashflowAllocation::create(['cashflow_entry_id' => $ours->id, 'project_id' => $secret->id,
            'purchase_invoice_id' => $ourBill->id, 'amount' => 10, 'currency' => 'INR']);
        app(CashflowVendorPaymentSync::class)->sync($ours);

        $statement = $this->statement();
        $this->assertSame([], $this->row($statement, 'WRONG-LINKS')['project_names'] ?? []);
        $this->assertSame([], $this->row($statement, 'OTHER-WRONG-NAME')['project_names'] ?? []);
        $this->assertSame(['Our valid project'], $this->row($statement, 'MIXED-ALLOCATION')['project_names'] ?? []);
        $this->assertSame(['Our valid project'], $this->row($statement, 'MIXED-PURCHASE-SPLIT')['project_names'] ?? []);
        $this->get(route('cashflows.statements.show', ['partyType' => 'vendor', 'party' => $this->vendor->id, 'period' => 'all', 'currency' => 'INR']))
            ->assertOk()->assertDontSee('Other vendor secret');
    }

    public function test_vendor_views_and_guest_share_escape_project_names_and_leave_client_statements_unchanged(): void
    {
        $project = $this->project('Tea <script>alert(1)</script>');
        $this->ledger('VIEW-REF', 'credit', 100, ['project_id' => $project->id, 'particular' => 'Supplier invoice']);
        SalesInvoice::create(['invoice_number' => 'CLIENT-REF', 'invoice_type' => 'tax', 'status' => 'sent',
            'client_id' => $this->client->id, 'project_id' => $project->id, 'invoice_date' => '2026-10-11',
            'currency' => 'INR', 'total_amount' => 100]);
        $share = PartyStatementShare::issue(['party_type' => 'vendor', 'party_id' => $this->vendor->id,
            'party_name' => $this->vendor->vendor_name, 'party_currency' => 'INR', 'expires_in_days' => 0,
            'shared_via' => 'link', 'options' => ['currency' => 'INR', 'ageing' => false]]);
        $assertVendorDocument = function ($response) {
            $response->assertOk()->assertSee('Project:')->assertSee('Tea &lt;script&gt;alert(1)&lt;/script&gt;', false)
                ->assertDontSee('<script>alert(1)</script>', false)->assertSee('Supplier invoice')->assertSee('VIEW-REF');
            $doc = new \DOMDocument();
            @$doc->loadHTML($response->getContent());
            $xpath = new \DOMXPath($doc);
            $row = $xpath->query('//table[contains(@class,"stmt-table")]/tbody/tr[td[contains(.,"VIEW-REF")]]')->item(0);
            $this->assertNotNull($row);
            $particulars = $xpath->query('./td[2]', $row)->item(0)->textContent;
            $this->assertStringContainsString('Project: Tea <script>alert(1)</script>', preg_replace('/\s+/', ' ', $particulars));
            $this->assertStringContainsString('Supplier invoice', $particulars);
            $headers = preg_replace('/\s+/', ' ', $xpath->query('//table[contains(@class,"stmt-table")]/thead')->item(0)->textContent);
            $this->assertStringContainsString('Debit (paid)', $headers);
            $this->assertStringContainsString('Credit (billed)', $headers);
            $this->assertSame(['debit' => 0.0, 'credit' => 100.0, 'closing' => 100.0, 'count' => 1], $response->viewData('statement')['totals']);
        };
        foreach (['show', 'print', 'pdf'] as $route) {
            $assertVendorDocument($this->get(route('cashflows.statements.'.$route, [
                'partyType' => 'vendor', 'party' => $this->vendor->id, 'period' => 'all', 'currency' => 'INR', 'ageing' => 0])));
        }
        $this->get(route('cashflows.statements.show', ['partyType' => 'client', 'party' => $this->client->id,
            'period' => 'all', 'currency' => 'INR']))->assertOk()->assertDontSee('Tea &lt;script&gt;alert(1)&lt;/script&gt;', false);
        auth()->logout();
        $assertVendorDocument($this->get(route('statements.public', $share->token)));
        $this->assertSame(1, $share->fresh()->views);
    }

    public function test_project_lookups_are_batched_instead_of_one_query_per_row(): void
    {
        $project = $this->project('Many-row project');
        for ($i = 0; $i < 60; $i++) $this->ledger('BATCH-'.$i, 'credit', 1, ['project_id' => $project->id]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $statement = $this->statement();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $projectQueries = array_filter($queries, fn ($q) => (bool) preg_match('/\bfrom\s+["`]?projects["`]?/i', $q['query']));
        $this->assertCount(1, $projectQueries, 'Resolve all project names in one query, not one per row.');
        $this->assertCount(60, $statement['rows']);
        $this->assertSame(['Many-row project'], $this->row($statement, 'BATCH-0')['project_names'] ?? []);
        $this->assertSame(60.0, $statement['totals']['credit']);
    }

    public function test_missing_optional_project_and_allocation_tables_leave_legacy_particulars_intact(): void
    {
        $this->ledger('LEGACY-WITHOUT-PROJECTS', 'credit', 42, ['project_id' => 12345]);
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('cashflow_allocations');
        Schema::dropIfExists('projects');
        Schema::enableForeignKeyConstraints();
        $statement = $this->statement();
        $row = $this->row($statement, 'LEGACY-WITHOUT-PROJECTS');
        $this->assertSame('Original LEGACY-WITHOUT-PROJECTS', $row['particular']);
        $this->assertSame([], $row['project_names'] ?? []);
        $this->assertSame(42.0, $statement['totals']['closing']);
    }
}

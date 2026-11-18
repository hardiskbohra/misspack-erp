<?php

/** Fictional vendor statement fixtures; only a disposable SQLite database under .work. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CashflowAccount;
use App\Models\CashflowAllocation;
use App\Models\CashflowEntry;
use App\Models\Client;
use App\Models\PartyStatementShare;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchasePaymentSplit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPaymentEntry;
use App\Services\CashflowVendorPaymentSync;
use App\Services\PurchaseBillLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$work = realpath(base_path('.work'));
$database = realpath((string) DB::connection()->getDatabaseName());
$password = getenv('VENDOR_STATEMENT_PROJECTS_TEST_PASSWORD');
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite'
    || ! $work || ! $database || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR)
    || ! is_string($password) || strlen($password) < 12) {
    throw new LogicException('Use a disposable SQLite database under .work and a test-only password.');
}
User::create(['name' => 'Statement Browser', 'email' => 'vendor-statement-projects@example.test',
    'password' => $password, 'role' => 'admin', 'access_profile' => 'full']);
$client = Client::create(['client_number' => 'BROWSER-STMT-CLIENT', 'company_name' => 'Browser Clinic']);
$vendor = Vendor::create(['vendor_number' => 'BROWSER-STMT-VENDOR', 'vendor_name' => 'Browser Supplier', 'preferred_currency' => 'INR']);
$alpha = Project::create(['project_number' => 'BROWSER-PRJ-A', 'client_id' => $client->id, 'name' => 'Refillable Dispenser Kits for North Clinic']);
$longName = 'ExtraLongProjectNameWithoutSpaces1234567890ExtraLongProjectNameWithoutSpaces1234567890ExtraLongProjectNameWithoutSpaces';
$beta = Project::create(['project_number' => 'BROWSER-PRJ-B', 'client_id' => $client->id, 'name' => $longName]);
$bill = PurchaseInvoice::create(['invoice_number' => 'BROWSER-PROJECT-BILL', 'invoice_type' => 'bill', 'status' => 'received',
    'public_token' => Str::random(48), 'vendor_id' => $vendor->id, 'vendor_company_name' => $vendor->vendor_name,
    'project_id' => $alpha->id, 'invoice_date' => '2026-10-10', 'currency' => 'INR', 'total_amount' => '300.500']);
app(PurchaseBillLedger::class)->sync($bill);
$account = CashflowAccount::create(['account_name' => 'Browser Statement Bank', 'account_type' => 'current', 'currency' => 'INR']);
$cashflow = CashflowEntry::create(['entry_date' => '2026-10-12', 'particular' => 'Payment for vendor projects', 'invoice_bill_number' => 'BROWSER-SPLIT',
    'transaction_type' => 'debit', 'debit_amount' => '150.250', 'credit_amount' => 0, 'currency' => 'INR',
    'vendor_id' => $vendor->id, 'account_id' => $account->id, 'accounting_status' => 'booked']);
foreach ([[$alpha->id, '70.125'], [$beta->id, '80.125']] as [$projectId, $amount]) {
    CashflowAllocation::create(['cashflow_entry_id' => $cashflow->id, 'project_id' => $projectId, 'amount' => $amount, 'currency' => 'INR']);
}
app(CashflowVendorPaymentSync::class)->sync($cashflow);
$otherBill = PurchaseInvoice::create(['invoice_number' => 'BROWSER-OTHER-BILL', 'invoice_type' => 'bill', 'status' => 'received',
    'public_token' => Str::random(48), 'vendor_id' => $vendor->id, 'vendor_company_name' => $vendor->vendor_name,
    'project_id' => $beta->id, 'invoice_date' => '2026-10-10', 'currency' => 'INR', 'total_amount' => '100']);
$slicedPayment = VendorPaymentEntry::create(['vendor_id' => $vendor->id, 'transaction_date' => '2026-10-12', 'invoice_number' => 'BROWSER-LEDGER-SLICE',
    'foreign_currency' => 'INR', 'foreign_amount' => '25.125', 'transaction_type' => 'debit', 'entry_category' => 'payment',
    'particular' => 'Advance shared across documents', 'status' => 'paid', 'amount_in_inr' => '25.125']);
foreach ([[$bill->id, '10.125'], [$otherBill->id, '15']] as [$billId, $amount]) {
    PurchasePaymentSplit::create(['vendor_payment_entry_id' => $slicedPayment->id, 'purchase_invoice_id' => $billId, 'amount' => $amount]);
}
VendorPaymentEntry::create(['vendor_id' => $vendor->id, 'transaction_date' => '2026-10-13', 'invoice_number' => 'BROWSER-NO-PROJECT',
    'foreign_currency' => 'INR', 'foreign_amount' => '10', 'transaction_type' => 'debit', 'entry_category' => 'payment',
    'particular' => 'Unallocated vendor payment', 'status' => 'paid', 'amount_in_inr' => '10']);
$alpha->delete(); // A statement must still identify historical projects.
$share = PartyStatementShare::issue(['party_type' => 'vendor', 'party_id' => $vendor->id, 'party_name' => $vendor->vendor_name,
    'party_currency' => 'INR', 'expires_in_days' => 0, 'shared_via' => 'link', 'options' => ['currency' => 'INR', 'ageing' => false]]);
file_put_contents($work.'/vendor-statement-projects-fixture.json', json_encode([
    'vendor_id' => $vendor->id, 'share_token' => $share->token, 'alpha_name' => $alpha->name, 'beta_name' => $longName,
], JSON_THROW_ON_ERROR));
echo "Seeded fictional vendor statement and project data.\n";

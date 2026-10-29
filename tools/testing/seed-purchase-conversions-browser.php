<?php

/** Fictional browser fixtures; requires a disposable SQLite database under .work. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CashflowAccount;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPaymentEntry;
use App\Services\PurchaseBillLedger;
use App\Services\VendorPaymentCashflowSync;
use Illuminate\Support\Facades\DB;

$work = realpath(base_path('.work'));
$database = realpath((string) DB::connection()->getDatabaseName());
$password = getenv('PURCHASE_TEST_PASSWORD');
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite'
    || ! $work || ! $database || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR)
    || ! is_string($password) || strlen($password) < 12) {
    throw new LogicException('Use a disposable SQLite database under .work and a test-only password.');
}
$user = User::create(['name' => 'Purchase Browser', 'email' => 'purchase-browser@example.test',
    'password' => $password, 'role' => 'admin', 'access_profile' => 'full']);
$vendor = Vendor::create(['vendor_number' => 'BROWSER-SUP', 'vendor_name' => 'Example supplier']);
$cash = CashflowAccount::create(['account_name' => 'Browser cash', 'account_type' => 'cash', 'currency' => 'INR', 'opening_balance' => '100000']);
$bank = CashflowAccount::create(['account_name' => 'Browser bank', 'account_type' => 'current', 'currency' => 'INR', 'opening_balance' => '100000']);
$orders = [];
foreach ([1440, 390] as $width) {
    $order = PurchaseInvoice::create(['invoice_number' => 'BROWSER-PO-'.$width, 'invoice_type' => 'order',
        'status' => 'approved', 'public_token' => \Illuminate\Support\Str::random(48), 'vendor_id' => $vendor->id,
        'vendor_company_name' => 'Example supplier', 'invoice_date' => '2026-10-28', 'currency' => 'INR', 'gst_type' => 'export',
        'subtotal' => 10000, 'taxable_amount' => 10000, 'total_amount' => 10000, 'created_by' => $user->id]);
    $order->items()->create(['product_name' => 'Cartons', 'quantity' => 100, 'unit' => 'pcs', 'unit_price' => 100,
        'gross_amount' => 10000, 'taxable_amount' => 10000, 'line_total' => 10000, 'sort_order' => 1]);
    app(PurchaseBillLedger::class)->sync($order);
    foreach ([['Cash advance', 2000, $cash, 'cash'], ['Bank advance', 1000, $bank, 'bank_transfer']] as [$label, $amount, $account, $mode]) {
        $payment = VendorPaymentEntry::create(['vendor_id' => $vendor->id, 'purchase_invoice_id' => $order->id,
            'transaction_date' => '2026-10-28', 'foreign_currency' => 'INR', 'foreign_amount' => $amount,
            'exchange_rate' => 1, 'amount_in_inr' => $amount, 'transaction_type' => 'debit', 'entry_category' => 'payment',
            'particular' => $label, 'status' => 'paid', 'payment_mode' => $mode, 'paid_account_id' => $account->id, 'created_by' => $user->id]);
        app(VendorPaymentCashflowSync::class)->sync($payment, true);
    }
    $orders[(string) $width] = $order->id;
}
echo json_encode($orders, JSON_THROW_ON_ERROR)."\n";

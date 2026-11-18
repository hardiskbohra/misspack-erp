<?php

/** Fictional purchase-list fixtures; only a disposable SQLite database under .work. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$work = realpath(base_path('.work'));
$database = realpath((string) DB::connection()->getDatabaseName());
$password = getenv('PURCHASE_VISIBILITY_TEST_PASSWORD');
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite'
    || ! $work || ! $database || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR)
    || ! is_string($password) || strlen($password) < 12) {
    throw new LogicException('Use a disposable SQLite database under .work and a test-only password.');
}
User::create(['name' => 'Purchase Browser', 'email' => 'purchase-visibility@example.test',
    'password' => $password, 'role' => 'admin', 'access_profile' => 'full']);
$vendor = Vendor::create(['vendor_number' => 'BROWSER-PURCHASE', 'vendor_name' => 'Browser Supplier', 'preferred_currency' => 'INR']);
$make = function (string $number, string $type, array $values = []) use ($vendor): PurchaseInvoice {
    return PurchaseInvoice::create(array_replace(['invoice_number' => $number, 'invoice_type' => $type,
        'status' => $type === 'order' ? 'approved' : 'received', 'public_token' => Str::random(48),
        'vendor_id' => $vendor->id, 'vendor_company_name' => $vendor->vendor_name, 'invoice_date' => '2026-10-28',
        'currency' => 'INR', 'total_amount' => 0], $values));
};
$legacyBill = $make('VIS-BILL-LEGACY', 'bill', ['total_amount' => 1000]);
$make('VIS-PO-LEGACY', 'order', ['status' => 'billed', 'total_amount' => 1000, 'converted_invoice_id' => $legacyBill->id]);
$done = $make('VIS-PO-DONE', 'order', ['converted_amount' => 10000, 'original_total_amount' => 10000]);
$make('VIS-BILL-DONE', 'bill', ['total_amount' => 10000, 'purchase_order_id' => $done->id]);
$partial = $make('VIS-PO-PARTIAL', 'order', ['total_amount' => 7500, 'converted_amount' => 2500, 'original_total_amount' => 10000]);
$make('VIS-BILL-PARTIAL', 'bill', ['total_amount' => 2500, 'purchase_order_id' => $partial->id]);
$make('VIS-PO-OPEN', 'order', ['total_amount' => 10000]);
for ($i = 1; $i <= 27; $i++) {
    $order = $make(sprintf('PAGE-PO-DONE-%02d', $i), 'order', ['invoice_date' => '2026-09-01',
        'converted_amount' => 1, 'original_total_amount' => 1]);
    $make(sprintf('PAGE-BILL-%02d', $i), 'bill', ['invoice_date' => '2026-09-01', 'total_amount' => 1, 'purchase_order_id' => $order->id]);
}
echo "Seeded fictional purchase documents for browser checks.\n";

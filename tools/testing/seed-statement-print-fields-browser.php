<?php

/** Add fictional multi-page client data to the guarded vendor-project browser fixture. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;

$work = realpath(base_path('.work'));
$database = realpath((string) DB::connection()->getDatabaseName());
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite'
    || ! $work || ! $database || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR)) {
    throw new LogicException('Use a disposable SQLite database under .work.');
}
$projectFixture = json_decode(file_get_contents($work.'/vendor-statement-projects-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$client = Client::where('client_number', 'BROWSER-STMT-CLIENT')->firstOrFail();
$client->update(['preferred_currency' => 'INR', 'billing_address' => 'Fictional Clinic Road 42', 'billing_city' => 'Ahmedabad']);
for ($i = 1; $i <= 65; $i++) {
    SalesInvoice::create(['invoice_number' => sprintf('FIELD-ROW-%03d', $i), 'invoice_type' => 'tax', 'status' => 'sent',
        'invoice_date' => '2026-10-10', 'client_id' => $client->id, 'currency' => 'INR', 'total_amount' => '25.125']);
}
file_put_contents($work.'/statement-print-fields-fixture.json', json_encode([
    ...$projectFixture, 'client_id' => $client->id,
], JSON_THROW_ON_ERROR));
echo "Seeded 65 fictional client invoices for multi-page statement print checks.\n";

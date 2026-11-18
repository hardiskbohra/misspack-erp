<?php

/** Isolated optional-PDF adapter test; never connects outside a disposable test fixture. */
if (getenv('APP_ENV') !== 'testing') {
    throw new LogicException('This script requires APP_ENV=testing.');
}
require __DIR__.'/../../vendor/autoload.php';

class StatementPdfTestDouble
{
    public static array $captured = [];

    public static function loadView($name, $data): object
    {
        self::$captured = [$name, $data];

        return new class {
            public function setPaper($paper): self { return $this; }
            public function download($filename) { return response()->view(StatementPdfTestDouble::$captured[0], StatementPdfTestDouble::$captured[1]); }
        };
    }
}

$facade = 'Barryvdh\\DomPDF\\Facade\\Pdf';
if (class_exists($facade, false)) throw new LogicException('The isolated PDF double must be registered before the facade loads.');
class_alias(StatementPdfTestDouble::class, $facade);
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$fixture = Tests\Support\StatementListDatabaseFixture::install();
auth()->setUser($fixture['user']);
$request = Illuminate\Http\Request::create('/cashflows/statements/client/'.$fixture['client']->id.'/pdf', 'GET', [
    'period' => 'all', 'currency' => 'INR', 'ageing' => '0', 'fields_configured' => '1',
    'print_fields' => ['date', 'particulars', 'balance'],
]);
app()->instance('request', $request);
$response = app(App\Http\Controllers\PartyStatementController::class)->pdf($request, 'client', $fixture['client']->id);
[$view, $data] = StatementPdfTestDouble::$captured;
if ($view !== 'cashflows.statement-pdf' || ($data['printFields']['reference'] ?? null) !== false
    || ($data['printFields']['balance'] ?? null) !== true) {
    throw new LogicException('The optional PDF adapter did not receive the chosen field selection.');
}
$html = $response->getContent();
if (str_contains($html, 'STMT-1') || str_contains($html, 'Bank:')) {
    throw new LogicException('The optional PDF document did not apply the selection.');
}
echo "PASS server PDF selection via isolated facade double\n";

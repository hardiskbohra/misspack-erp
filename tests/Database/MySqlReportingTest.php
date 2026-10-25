<?php

namespace Tests\Database;

use App\Http\Controllers\AdminDashboardController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Real MySQL DATE_FORMAT/YEAR aggregation; never silently substitutes SQLite. */
class MySqlReportingTest extends TestCase
{
    private ?string $table = null;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = DB::connection();
        if (! app()->environment('testing') || $connection->getDriverName() !== 'mysql'
            || ! str_ends_with($connection->getDatabaseName(), '_test')) {
            throw new \LogicException('Use a disposable MySQL database with a name ending in _test and APP_ENV=testing.');
        }
        $this->table = 'erp_test_reporting_'.bin2hex(random_bytes(4));
        Schema::create($this->table, function (Blueprint $table) {
            $table->date('entry_date');
            $table->decimal('amount', 12, 2);
        });
    }

    protected function tearDown(): void
    {
        if ($this->table) Schema::dropIfExists($this->table);
        parent::tearDown();
    }

    public static function ranges(): array
    {
        return [['2026-10-10', '2026-10-12', 'day'], ['2026-01-29', '2026-05-06', 'month'], ['2020-11-20', '2026-02-03', 'year']];
    }

    #[DataProvider('ranges')]
    public function test_real_database_aggregation_honours_custom_bounds(string $from, string $to, string $group): void
    {
        $controller = app(AdminDashboardController::class);
        $period = (new \ReflectionMethod($controller, 'periodContext'))->invoke($controller, Request::create('/dashboard', 'GET', [
            'period_type' => 'custom', 'start_date' => $from, 'end_date' => $to,
        ]));
        DB::table($this->table)->insert([
            ['entry_date' => $period['start']->copy()->subDay()->toDateString(), 'amount' => 10000],
            ['entry_date' => $from, 'amount' => 10], ['entry_date' => $to, 'amount' => 20],
            ['entry_date' => $period['end']->copy()->addDay()->toDateString(), 'amount' => 20000],
        ]);
        $values = (new \ReflectionMethod($controller, 'sumExpressionSeries'))->invoke($controller, $this->table, 'entry_date', 'amount', $period);
        $this->assertSame($group, $period['group']);
        $this->assertEqualsWithDelta(30, array_sum($values), .001);
        $this->assertCount(count($period['labels']), $values);
    }
}

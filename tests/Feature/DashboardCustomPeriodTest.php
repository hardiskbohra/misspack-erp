<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminDashboardController;
use App\Services\DashboardPeriod;
use App\Services\ModuleDashboard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardCustomPeriodTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-25 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function period(bool $module, array $query): array
    {
        $request = Request::create('/dashboard', 'GET', $query);
        if ($module) return app(DashboardPeriod::class)->fromRequest($request);

        return (new \ReflectionMethod(AdminDashboardController::class, 'periodContext'))
            ->invoke(app(AdminDashboardController::class), $request);
    }

    public function test_custom_bounds_include_both_entire_days_on_both_dashboards(): void
    {
        foreach ([false, true] as $module) {
            $period = $this->period($module, ['period_type' => 'custom', 'start_date' => '2024-02-28', 'end_date' => '2024-03-01',
                'year' => '2026', 'month' => '10', 'range' => '30']);
            $this->assertSame('custom', $period['type']);
            $this->assertSame('2024-02-28 00:00:00.000000', $period['start']->format('Y-m-d H:i:s.u'));
            $this->assertSame('2024-03-01 23:59:59.999999', $period['end']->format('Y-m-d H:i:s.u'));
            $this->assertSame('28 Feb 2024 – 01 Mar 2024', $period['label']);
        }
        $this->assertSame(['2024-02-28', '2024-02-29', '2024-03-01'], array_keys($this->period(false, [
            'period_type' => 'custom', 'start_date' => '2024-02-28', 'end_date' => '2024-03-01',
        ])['labels']));
    }

    public function test_same_day_is_a_valid_one_day_period(): void
    {
        foreach ([false, true] as $module) {
            $period = $this->period($module, ['period_type' => 'custom', 'start_date' => '2026-10-25', 'end_date' => '2026-10-25']);
            $this->assertSame('2026-10-25', $period['start']->toDateString());
            $this->assertSame('2026-10-25', $period['end']->toDateString());
            if ($module) {
                $this->assertSame('2026-10-24', $period['previousStart']->toDateString());
                $this->assertSame('2026-10-24', $period['previousEnd']->toDateString());
            } else {
                $this->assertCount(1, $period['labels']);
            }
        }
    }

    public function test_module_comparison_uses_the_immediately_preceding_equal_length_window(): void
    {
        $period = $this->period(true, ['period_type' => 'custom', 'start_date' => '2024-02-28', 'end_date' => '2024-03-01']);
        $this->assertSame('2024-02-25 00:00:00', $period['previousStart']->toDateTimeString());
        $this->assertSame('2024-02-27 23:59:59', $period['previousEnd']->toDateTimeString());
        $this->assertSame('25 Feb 2024 – 27 Feb 2024', $period['previousLabel']);
    }

    public function test_overview_groups_long_windows_without_rounding_the_selected_bounds(): void
    {
        $period = $this->period(false, ['period_type' => 'custom', 'start_date' => '2026-01-29', 'end_date' => '2026-05-06']);
        $this->assertSame('month', $period['group']);
        $this->assertSame('2026-01-29', $period['start']->toDateString());
        $this->assertSame('2026-05-06', $period['end']->toDateString());
        $this->assertSame(['2026-01', '2026-02', '2026-03', '2026-04', '2026-05'], array_keys($period['labels']));
        $period = $this->period(false, ['period_type' => 'custom', 'start_date' => '2020-11-20', 'end_date' => '2026-02-03']);
        $this->assertSame('year', $period['group']);
        $this->assertCount(7, $period['labels']);
        $this->assertSame('2020-11-20', $period['start']->toDateString());
        $this->assertSame('2026-02-03', $period['end']->toDateString());
    }

    #[DataProvider('invalidDateRanges')]
    public function test_both_dashboards_reject_invalid_custom_dates(array $dates, string $field): void
    {
        foreach ([false, true] as $module) {
            try {
                $this->period($module, ['period_type' => 'custom'] + $dates);
                $this->fail('Invalid custom dates must not silently use a different period.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
    }

    public static function invalidDateRanges(): array
    {
        return [
            'missing from' => [['end_date' => '2026-10-25'], 'start_date'],
            'missing to' => [['start_date' => '2026-10-25'], 'end_date'],
            'reversed' => [['start_date' => '2026-10-25', 'end_date' => '2026-10-24'], 'end_date'],
            'impossible day' => [['start_date' => '2026-02-30', 'end_date' => '2026-03-01'], 'start_date'],
            'non leap day' => [['start_date' => '2025-02-29', 'end_date' => '2025-03-01'], 'start_date'],
            'wrong format' => [['start_date' => '25/10/2026', 'end_date' => '2026-10-26'], 'start_date'],
            'timestamp is not a date' => [['start_date' => '2026-10-25', 'end_date' => '2026-10-26T12:00:00'], 'end_date'],
            'array to date' => [['start_date' => '2026-10-25', 'end_date' => ['2026-10-26']], 'end_date'],
            'array input' => [['start_date' => ['2026-10-25'], 'end_date' => '2026-10-26'], 'start_date'],
        ];
    }

    public function test_invalid_ranges_return_validation_errors_from_module_route(): void
    {
        $this->withoutMiddleware();
        $this->withSession([]);
        foreach (['/dashboards/finance'] as $url) {
            $this->get($url.'?period_type=custom&start_date=2026-10-25&end_date=2026-10-24')
                ->assertRedirect()->assertSessionHasErrors('end_date');
        }
    }

    public function test_module_links_preserve_both_custom_dates(): void
    {
        $this->app->instance('request', Request::create('/dashboards/finance', 'GET', [
            'period_type' => 'custom', 'start_date' => '2026-10-10', 'end_date' => '2026-10-12',
        ]));
        $user = new \App\Models\User(['role' => 'admin', 'access_profile' => 'full']);
        $user->id = 901;
        $this->actingAs($user);
        preg_match('/<nav class="core-card module-dashboard-nav".*?<\/nav>/s', file_get_contents(resource_path('views/dashboard/module.blade.php')), $matches);
        $html = Blade::render($matches[0], ['modules' => ModuleDashboard::MODULES, 'module' => 'finance']);
        // Six canonical modules preserve the reporting context; no executive overview link.
        $this->assertSame(6, substr_count($html, 'start_date=2026-10-10'));
        $this->assertSame(6, substr_count($html, 'end_date=2026-10-12'));
        $this->assertSame(6, substr_count($html, 'period_type=custom'));
    }

    public function test_totals_and_trends_use_exact_custom_bounds_for_dates_and_timestamps(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('dashboard_period_events', function (Blueprint $table) {
            $table->date('entry_date');
            $table->dateTime('created_at');
            $table->decimal('amount', 12, 2);
        });
        foreach ([['2026-10-09 23:59:59', 10000], ['2026-10-10 00:00:00', 10],
            ['2026-10-11 12:00:00', 20], ['2026-10-12 23:59:59', 30], ['2026-10-13 00:00:00', 20000]] as [$date, $amount]) {
            DB::table('dashboard_period_events')->insert(['entry_date' => substr($date, 0, 10), 'created_at' => $date, 'amount' => $amount]);
        }
        $query = ['period_type' => 'custom', 'start_date' => '2026-10-10', 'end_date' => '2026-10-12'];
        $overview = $this->period(false, $query);
        $module = $this->period(true, $query);
        foreach (['entry_date', 'created_at'] as $column) {
            $values = (new \ReflectionMethod(AdminDashboardController::class, 'sumExpressionSeries'))->invoke(
                app(AdminDashboardController::class), 'dashboard_period_events', $column, 'amount', $overview);
            $this->assertSame([10.0, 20.0, 30.0], $values);
            $totals = new \ReflectionMethod(ModuleDashboard::class, 'sumRange');
            $this->assertSame(60.0, (float) $totals->invoke(app(ModuleDashboard::class), 'dashboard_period_events', $column, 'amount', $module['start'], $module['end']));
            $this->assertSame(10000.0, (float) $totals->invoke(app(ModuleDashboard::class), 'dashboard_period_events', $column, 'amount', $module['previousStart'], $module['previousEnd']));
            $trend = (new \ReflectionMethod(ModuleDashboard::class, 'series'))->invoke(app(ModuleDashboard::class), 'dashboard_period_events', $column, 'amount', $module);
            $this->assertSame(60.0, (float) array_sum(array_column($trend, 'value')));
        }
    }

    public function test_switching_away_from_custom_ignores_stale_date_inputs(): void
    {
        foreach ([false, true] as $module) {
            foreach (['month', 'quarter', 'half', 'year'] as $type) {
                $period = $this->period($module, ['period_type' => $type, 'year' => '2026', 'month' => '10', 'quarter' => '4', 'half' => '2',
                    'start_date' => 'invalid', 'end_date' => 'invalid']);
                $this->assertSame($type, $period['type']);
                $this->assertSame(2026, $period['start']->year);
            }
        }
    }
}

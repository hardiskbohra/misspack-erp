<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\DashboardAnalytics;
use App\Services\ModuleDashboard;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardAnalyticsRulesTest extends TestCase
{
    public static function dueDates(): array
    {
        return [[null, 'No due date'], ['2026-10-26', 'Not due'], ['2026-10-25', 'Not due'],
            ['2026-10-24', '1–30 days'], ['2026-09-25', '1–30 days'], ['2026-09-24', '31–60 days'],
            ['2026-08-26', '31–60 days'], ['2026-08-25', '61–90 days'], ['2026-07-27', '61–90 days'], ['2026-07-26', '90+ days']];
    }

    #[DataProvider('dueDates')]
    public function test_ageing_bands_do_not_mark_due_today_as_overdue(?string $due, string $band): void
    {
        $this->assertSame($band, DashboardAnalytics::ageBucket($due, Carbon::parse('2026-10-25 14:30:00')));
    }

    public function test_dashboard_directory_only_contains_authorised_modules(): void
    {
        $reader = new User(['role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['sales.view', 'operations.manage']]);
        $this->assertSame(['sales', 'operations'], array_keys(ModuleDashboard::availableModules($reader)));
        $this->assertSame([], ModuleDashboard::availableModules(new User(['role' => 'employee'])));
        $this->assertCount(6, ModuleDashboard::availableModules(new User(['role' => 'admin', 'access_profile' => 'full'])));
    }
}

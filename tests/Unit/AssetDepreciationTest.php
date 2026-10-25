<?php

namespace Tests\Unit;

use App\Models\FixedAsset;
use App\Services\AssetDepreciation;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssetDepreciationTest extends TestCase
{
    private function asset(array $overrides = []): FixedAsset
    {
        return (new FixedAsset(array_merge(['cost' => 100000, 'gst_amount' => 18000,
            'purchase_date' => '2024-04-01', 'depreciation_method' => 'straight_line',
            'useful_life_years' => 5, 'residual_percent' => 5, 'depreciate_on_total' => false], $overrides)))
            ->setRelation('category', null);
    }

    #[DataProvider('financialYears')]
    public function test_indian_financial_year_boundaries(string $day, string $key): void
    {
        $service = new AssetDepreciation();
        $year = $service->financialYearOf(Carbon::parse($day));
        $this->assertSame($key, $year['key']);
        $this->assertSame($year['from']->toDateString(), $service->bounds($key)['from']->toDateString());
        $this->assertSame($year['to']->toDateString(), $service->bounds($key)['to']->toDateString());
    }

    public static function financialYears(): array
    {
        return [['2026-03-31', '2025-26'], ['2026-04-01', '2026-27'], ['2024-02-29', '2023-24'], ['2026-12-31', '2026-27']];
    }

    public function test_invalid_financial_year_keys_are_rejected(): void
    {
        foreach (['2026-26', '2026', '../2026', '2026-2027', '2026-99'] as $key) $this->assertNull((new AssetDepreciation())->bounds($key));
    }

    #[DataProvider('recipes')]
    public function test_schedule_conserves_value_and_never_depreciates_below_residual(string $method, float $cost, int $life): void
    {
        $asset = $this->asset(['depreciation_method' => $method, 'cost' => $cost, 'useful_life_years' => $life]);
        $rows = (new AssetDepreciation())->schedule($asset);
        $this->assertNotEmpty($rows);
        $residual = round($cost * .05, 2);
        $opening = $cost;
        foreach ($rows as $row) {
            $this->assertEqualsWithDelta($opening, $row['opening'], .001);
            $this->assertGreaterThanOrEqual(0, $row['charge']);
            $this->assertGreaterThanOrEqual($residual, $row['closing']);
            $this->assertEqualsWithDelta($row['opening'] - $row['charge'], $row['closing'], .011);
            $opening = $row['closing'];
        }
        $this->assertEqualsWithDelta($residual, $opening, .001);
        $this->assertEqualsWithDelta($cost - $residual, array_sum(array_column($rows, 'charge')), .011);
    }

    public static function recipes(): array
    {
        return [['straight_line', 100000.0, 5], ['wdv', 100000.0, 5], ['straight_line', 12345.67, 3], ['wdv', 12345.67, 3]];
    }

    public function test_capitalised_basis_distinguishes_claimed_gst_credit(): void
    {
        $this->assertSame(100000.0, $this->asset()->capitalisedCost());
        $this->assertSame(118000.0, $this->asset(['depreciate_on_total' => true])->capitalisedCost());
    }

    public function test_missing_purchase_date_zero_cost_and_non_depreciating_asset_have_no_schedule(): void
    {
        foreach ([['purchase_date' => null], ['cost' => 0], ['depreciation_method' => 'none']] as $override) {
            $this->assertSame([], (new AssetDepreciation())->schedule($this->asset($override)));
        }
    }
}

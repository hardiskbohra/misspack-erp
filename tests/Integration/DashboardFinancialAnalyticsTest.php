<?php

namespace Tests\Integration;

use App\Models\SalesInvoice;
use App\Services\DashboardAnalytics;
use App\Services\DashboardPeriod;
use App\Services\ModuleDashboard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\AnalyticsDatabaseFixture;
use Tests\TestCase;

class DashboardFinancialAnalyticsTest extends TestCase
{
    private array $fixture;
    private array $period;

    protected function setUp(): void
    {
        parent::setUp();
        AnalyticsDatabaseFixture::install();
        Carbon::setTestNow('2026-10-25 12:00:00');
        $this->fixture = AnalyticsDatabaseFixture::seed();
        $this->actingAs($this->fixture['user']);
        $this->period = app(DashboardPeriod::class)->fromRequest(Request::create('/', 'GET', [
            'period_type'=>'custom','start_date'=>'2026-10-10','end_date'=>'2026-10-12',
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_receivable_ageing_matches_payment_and_allocation_aware_metric_without_writes(): void
    {
        $before = DB::table('sales_invoices')->get()->toArray();
        $data = app(ModuleDashboard::class)->build('sales', $this->period, 'INR');
        $ageing = collect($data['panels'])->firstWhere('title', 'Receivable ageing');
        $this->assertSame(50.0, $ageing['rows'][0]['amount']);
        $this->assertSame('1–30 days', $ageing['rows'][0]['label']);
        $this->assertSame(50.0, collect($data['metrics'])->firstWhere('label', 'Receivables')['current']);
        $this->assertEquals($before, DB::table('sales_invoices')->get()->toArray());
    }

    public function test_selected_currency_agrees_between_kpi_trend_and_concentration(): void
    {
        $data = app(ModuleDashboard::class)->build('sales', $this->period, 'USD');
        $this->assertSame(1000.0, $data['metrics'][0]['current']);
        $this->assertSame(1000.0, array_sum(array_column($data['trend'], 'value')));
        $this->assertSame(1000.0, array_sum(array_column(collect($data['panels'])->firstWhere('title','Client concentration')['rows'], 'amount')));
        $this->get('/dashboards/sales?period_type=custom&start_date=2026-10-10&end_date=2026-10-12&currency=USD')
            ->assertOk()->assertSee('USD only')->assertSee('USD', false);
    }

    public function test_product_line_totals_are_not_multiplied_by_invoice_header_join(): void
    {
        $this->fixture['invoice']->items()->create(['product_name'=>'Other product','quantity'=>1,'unit'=>'kg','line_total'=>7]);
        $panels = app(DashboardAnalytics::class)->panels('sales', $this->period, 'INR');
        $this->assertSame(118.0, collect($panels)->firstWhere('title','Client concentration')['rows'][0]['amount']);
        $products = collect($panels)->firstWhere('title','Product contribution')['rows'];
        $this->assertCount(2, $products);
        $this->assertSame(125.0, array_sum(array_column($products,'amount')));
        $this->assertSame(['pcs','kg'], array_column($products,'unit'));
    }

    public function test_purchase_payable_and_finance_movement_are_currency_specific(): void
    {
        $purchase = app(ModuleDashboard::class)->build('purchase', $this->period, 'INR');
        $this->assertSame(300.0, collect($purchase['panels'])->firstWhere('title','Payable ageing')['rows'][0]['amount']);
        $finance = app(ModuleDashboard::class)->build('finance', $this->period, 'INR');
        $this->assertSame(140.0, $finance['metrics'][0]['current']);
        $accounts = collect($finance['panels'])->firstWhere('title','Account movement');
        $this->assertSame(140.0, $accounts['rows'][0]['inward']);
    }

    public function test_dashboard_prints_supplied_party_text_safely_and_preserves_currency_in_links(): void
    {
        $this->fixture['invoice']->update(['client_company_name'=>'<script>alert(1)</script>']);
        $this->get('/dashboards/sales?currency=INR')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('currency=INR', false);
    }
}

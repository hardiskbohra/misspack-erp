<?php

namespace Tests\Integration;

use App\Models\User;
use App\Services\DashboardAnalytics;
use App\Services\DashboardPeriod;
use App\Services\ModuleDashboard;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

class DashboardConsolidationTest extends TestCase
{
    use IsolatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        Carbon::setTestNow('2026-10-25 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reader(array $permissions = ['dashboard.view', 'sales.view']): User
    {
        $user = new User(['role' => 'admin', 'name' => 'Analyst', 'email' => 'analyst@example.test',
            'access_profile' => 'custom', 'permissions' => $permissions]);
        $user->id = 1001;
        $this->actingAs($user);
        return $user;
    }

    private function period(): array
    {
        return app(DashboardPeriod::class)->fromRequest(Request::create('/', 'GET', [
            'period_type' => 'custom', 'start_date' => '2026-10-10', 'end_date' => '2026-10-12',
        ]));
    }

    private function invoiceRows(string $table = 'sales_invoices'): void
    {
        Schema::create($table, function (Blueprint $t) {
            $t->id(); $t->string('invoice_number')->nullable(); $t->string('invoice_type'); $t->string('status');
            $t->string('currency'); $t->date('invoice_date'); $t->decimal('total_amount', 14, 2);
            $t->string('client_company_name')->nullable(); $t->string('vendor_company_name')->nullable();
        });
        foreach ([['tax', 'sent', 'INR', '2026-10-10', 100], ['cash', 'sent', 'INR', '2026-10-12', 20],
            ['tax', 'draft', 'INR', '2026-10-11', 999], ['tax', 'cancelled', 'INR', '2026-10-11', 999],
            ['proforma', 'sent', 'INR', '2026-10-11', 999], ['tax', 'sent', 'USD', '2026-10-11', 500],
            ['tax', 'sent', 'INR', '2026-10-13', 700]] as $i => $r) {
            DB::table($table)->insert(['invoice_number' => 'INV-'.$i, 'invoice_type' => $r[0], 'status' => $r[1], 'currency' => $r[2],
                'invoice_date' => $r[3], 'total_amount' => $r[4], 'client_company_name' => 'Client <safe>', 'vendor_company_name' => 'Vendor']);
        }
    }

    public function test_overview_has_canonical_module_links_instead_of_duplicate_analytical_tabs(): void
    {
        $this->reader();
        $response = $this->get('/dashboard?period_type=custom&start_date=2026-10-10&end_date=2026-10-12');
        $response->assertOk()->assertSee('Executive overview')->assertSee('Sales')->assertDontSee('dashboardTabSales', false)
            ->assertDontSee('dashboardTabFinance', false)->assertDontSee('dashboardTabOperations', false)
            ->assertSee('start_date=2026-10-10', false)->assertSee('end_date=2026-10-12', false)
            ->assertDontSee('/dashboards/finance', false);
    }

    public function test_rolling_overview_window_reaches_module_as_exact_custom_dates(): void
    {
        $this->reader();
        $this->get('/dashboard?period_type=range&range=7')->assertOk()
            ->assertSee('start_date=2026-10-19', false)->assertSee('end_date=2026-10-25', false);
    }

    public function test_directory_and_module_navigation_hide_unauthorised_modules(): void
    {
        $this->reader();
        $this->get('/dashboards')->assertOk()->assertSee('/dashboards/sales', false)->assertDontSee('/dashboards/finance', false);
        $this->get('/dashboards/sales')->assertOk()->assertSee('Detailed analysis')->assertSee('Client concentration')
            ->assertDontSee('/dashboards/finance', false);
        $this->get('/dashboards/finance')->assertForbidden();
        $this->get('/dashboards/not-a-module')->assertNotFound();
    }

    public function test_sales_concentration_uses_exact_dates_currency_and_real_issued_documents(): void
    {
        $this->invoiceRows();
        $panels = app(DashboardAnalytics::class)->panels('sales', $this->period(), 'INR');
        $panel = collect($panels)->firstWhere('title', 'Client concentration');
        $this->assertSame(120.0, (float) $panel['rows'][0]['amount']);
        $this->assertSame(2, $panel['rows'][0]['count']);
        $this->assertSame('Client <safe>', $panel['rows'][0]['label']);
        $this->assertSame('period', $panel['scope']);
        $usd = collect(app(DashboardAnalytics::class)->panels('sales', $this->period(), 'USD'))->firstWhere('title', 'Client concentration');
        $this->assertSame(500.0, (float) $usd['rows'][0]['amount']);
    }

    public function test_finance_category_analysis_excludes_internal_transfers_and_other_currencies(): void
    {
        Schema::create('cashflow_categories', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('type'); });
        Schema::create('cashflow_entries', function (Blueprint $t) {
            $t->id(); $t->date('entry_date'); $t->string('currency'); $t->unsignedBigInteger('category_id');
            $t->decimal('credit_amount', 14, 2)->default(0); $t->decimal('debit_amount', 14, 2)->default(0);
        });
        DB::table('cashflow_categories')->insert([['id'=>1,'name'=>'Freight','type'=>'expense'], ['id'=>2,'name'=>'Transfer','type'=>'transfer']]);
        DB::table('cashflow_entries')->insert([
            ['entry_date'=>'2026-10-10','currency'=>'INR','category_id'=>1,'debit_amount'=>80],
            ['entry_date'=>'2026-10-12','currency'=>'INR','category_id'=>1,'debit_amount'=>20],
            ['entry_date'=>'2026-10-11','currency'=>'USD','category_id'=>1,'debit_amount'=>500],
            ['entry_date'=>'2026-10-11','currency'=>'INR','category_id'=>2,'debit_amount'=>999],
            ['entry_date'=>'2026-10-13','currency'=>'INR','category_id'=>1,'debit_amount'=>999],
        ]);
        $panel = collect(app(DashboardAnalytics::class)->panels('finance', $this->period(), 'INR'))->firstWhere('title', 'Outward by category');
        $this->assertCount(1, $panel['rows']);
        $this->assertSame('Freight', $panel['rows'][0]['label']);
        $this->assertSame(100.0, (float) $panel['rows'][0]['outward']);
    }

    public function test_each_existing_module_has_a_single_detailed_empty_state_page(): void
    {
        $this->reader(['dashboard.view','sales.view','purchase.view','projects.view','operations.view','finance.view','work.view']);
        foreach (array_keys(ModuleDashboard::MODULES) as $module) {
            $this->get('/dashboards/'.$module)->assertOk()->assertSee('Detailed analysis')->assertSee('No data')
                ->assertSee('Selected period')->assertSee('Live position');
        }
    }

    public function test_unsupported_currency_returns_validation_error_not_mislabelled_money(): void
    {
        $this->reader();
        $this->get('/dashboards/sales?currency=INVALID')->assertRedirect()->assertSessionHasErrors('currency');
    }

    public function test_module_reader_can_use_directory_without_cross_module_overview_permission(): void
    {
        $this->reader(['sales.view']);
        $this->get('/dashboards')->assertOk()->assertSee('/dashboards/sales', false)->assertDontSee('/dashboards/finance', false);
        $this->get('/dashboard')->assertForbidden();
    }

    public function test_module_retains_secondary_activity_trends_in_the_same_workspace(): void
    {
        $period = $this->period();
        foreach (array_keys(ModuleDashboard::MODULES) as $module) {
            $data = app(ModuleDashboard::class)->build($module, $period);
            $this->assertNotEmpty($data['additional_trends']);
        }
    }

    public function test_receipt_quantities_stay_separate_by_unit_and_respect_received_date(): void
    {
        Schema::create('shipment_delivery_receipts', function (Blueprint $t) { $t->id(); $t->dateTime('received_at'); });
        Schema::create('shipment_items', function (Blueprint $t) { $t->id(); $t->string('unit'); });
        Schema::create('shipment_delivery_receipt_lines', function (Blueprint $t) {
            $t->id(); $t->integer('shipment_delivery_receipt_id'); $t->integer('shipment_item_id');
            foreach (['received','accepted','short','damaged','rejected','returned','replacement'] as $key) $t->decimal($key.'_quantity', 14, 3)->default(0);
        });
        DB::table('shipment_delivery_receipts')->insert([['id'=>1,'received_at'=>'2026-10-12 23:59:59'], ['id'=>2,'received_at'=>'2026-10-13 00:00:00']]);
        DB::table('shipment_items')->insert([['id'=>1,'unit'=>'pcs'], ['id'=>2,'unit'=>'kg']]);
        DB::table('shipment_delivery_receipt_lines')->insert([
            ['shipment_delivery_receipt_id'=>1,'shipment_item_id'=>1,'received_quantity'=>100,'accepted_quantity'=>98,'damaged_quantity'=>2],
            ['shipment_delivery_receipt_id'=>1,'shipment_item_id'=>2,'received_quantity'=>5,'accepted_quantity'=>5,'damaged_quantity'=>0],
            ['shipment_delivery_receipt_id'=>2,'shipment_item_id'=>1,'received_quantity'=>900,'accepted_quantity'=>900,'damaged_quantity'=>0],
        ]);
        $panel = collect(app(DashboardAnalytics::class)->panels('operations', $this->period(), 'INR'))->firstWhere('title', 'Delivery reconciliation');
        $rows = collect($panel['rows'])->keyBy('label');
        $this->assertCount(2, $rows);
        $this->assertSame(98.0, $rows['pcs']['accepted']);
        $this->assertSame(2.0, $rows['pcs']['damaged']);
        $this->assertSame(5.0, $rows['kg']['accepted']);
    }

    public function test_completion_timeliness_uses_completion_window_and_end_of_due_day(): void
    {
        Schema::create('tasks', function (Blueprint $t) {
            $t->id(); $t->string('status'); $t->date('due_date')->nullable(); $t->dateTime('completed_at')->nullable();
        });
        DB::table('tasks')->insert([
            ['status'=>'completed','due_date'=>'2026-10-10','completed_at'=>'2026-10-10 23:59:59'],
            ['status'=>'completed','due_date'=>'2026-10-10','completed_at'=>'2026-10-11 00:00:00'],
            ['status'=>'completed','due_date'=>null,'completed_at'=>'2026-10-12 23:59:59'],
            ['status'=>'completed','due_date'=>'2026-10-13','completed_at'=>'2026-10-13 00:00:00'],
        ]);
        $panel = collect(app(DashboardAnalytics::class)->panels('work', $this->period(), 'INR'))->firstWhere('title', 'Completion timeliness');
        $this->assertSame(['On time'=>1,'Late'=>1,'No due date'=>1], collect($panel['rows'])->pluck('count','label')->all());
    }

    public function test_project_dashboard_retains_live_client_value_analysis_without_mixing_currencies(): void
    {
        Schema::create('projects', function (Blueprint $t) {
            $t->id(); $t->integer('client_id'); $t->string('currency'); $t->integer('progress_percent')->default(0); $t->softDeletes();
        });
        Schema::create('clients', function (Blueprint $t) { $t->id(); $t->string('company_name'); });
        $this->invoiceRows();
        Schema::table('sales_invoices', function (Blueprint $t) {
            $t->integer('project_id')->nullable(); $t->integer('converted_invoice_id')->nullable();
            $t->decimal('cash_converted_total_amount', 14, 2)->default(0);
        });
        DB::table('projects')->insert([
            ['id'=>1,'client_id'=>1,'currency'=>'INR','progress_percent'=>50],
            ['id'=>2,'client_id'=>1,'currency'=>'USD','progress_percent'=>20],
        ]);
        DB::table('clients')->insert(['id'=>1,'company_name'=>'Client portfolio']);
        DB::table('sales_invoices')->where('invoice_type','tax')->where('status','sent')->update(['project_id'=>1]);
        $panel = collect(app(DashboardAnalytics::class)->panels('projects', $this->period(), 'INR'))->firstWhere('title', 'Client project value');
        $this->assertNotNull($panel);
        $this->assertSame('live', $panel['scope']);
        $this->assertSame(800.0, $panel['rows'][0]['amount']); // INR100 + INR700 live, not USD500 or period-limited.
        $this->assertSame(1, $panel['rows'][0]['count']);
        $this->assertSame(50.0, $panel['rows'][0]['progress']);
    }
}

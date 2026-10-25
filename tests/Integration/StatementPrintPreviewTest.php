<?php

namespace Tests\Integration;

use App\Models\{CashflowEntry, SalesInvoice, User};
use Carbon\Carbon;
use Tests\Support\StatementListDatabaseFixture;
use Tests\TestCase;

class StatementPrintPreviewTest extends TestCase
{
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = StatementListDatabaseFixture::install();
        $this->actingAs($this->fixture['user']);
        $this->fixture['client']->update(['billing_address'=>'Client industrial road 123','billing_city'=>'Ahmedabad',
            'billing_state'=>'Gujarat','billing_country'=>'India','billing_pincode'=>'380004']);
        $this->fixture['vendor']->update(['address'=>'Vendor factory road 456','city'=>'Shenzhen','country'=>'China']);
        SalesInvoice::create(['invoice_number'=>'PRINT-OLD','invoice_type'=>'tax','status'=>'sent','currency'=>'INR',
            'invoice_date'=>'2025-04-04','client_id'=>$this->fixture['client']->id,'total_amount'=>290280]);
        CashflowEntry::create(['entry_date'=>'2025-11-27','particular'=>'Historical print receipt','currency'=>'INR',
            'client_id'=>$this->fixture['client']->id,'transaction_type'=>'credit','credit_amount'=>89584,
            'account_id'=>\App\Models\CashflowAccount::first()->id,'accounting_status'=>'booked']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function url(string $suffix = ''): string
    {
        return '/cashflows/statements/client/'.$this->fixture['client']->id.$suffix;
    }

    private function xpath(string $html): \DOMXPath
    {
        $doc = new \DOMDocument;
        @$doc->loadHTML($html);
        return new \DOMXPath($doc);
    }

    public function test_statement_page_print_link_opens_new_tab_and_preserves_all_time_currency_and_ageing_off(): void
    {
        $response = $this->get($this->url('?period=all&date_from=2026-10-01&date_to=2026-10-31&currency=INR&ageing=0'))->assertOk();
        $x = $this->xpath($response->getContent());
        $print = $x->query('//*[@id="printStatement"]')->item(0);
        $this->assertSame('a', $print?->nodeName);
        $this->assertSame('_blank', $print->getAttribute('target'));
        $this->assertStringContainsString('noopener', $print->getAttribute('rel'));
        parse_str(parse_url($print->getAttribute('href'), PHP_URL_QUERY) ?: '', $query);
        $this->assertSame('all', $query['period']);
        $this->assertSame('INR', $query['currency']);
        $this->assertSame('0', $query['ageing']);
        $this->assertArrayNotHasKey('date_from', $query);
        $this->assertSame(0, $x->query('//header[contains(@class,"topbar")]//a[contains(text(),"Download PDF")]')->length);
        $this->get($print->getAttribute('href'))->assertOk()->assertSee('2,90,380')->assertSee('89,584');
    }

    public function test_preview_exposes_download_and_filters_with_same_all_time_scope(): void
    {
        $page = $this->get($this->url('/print?period=all&currency=INR&ageing=0'))->assertOk();
        $this->assertSame(3, $page->viewData('statement')['totals']['count']);
        $this->assertNull($page->viewData('statement')['ageing']);
        $x = $this->xpath($page->getContent());
        $download = $x->query('//*[@id="statementDownload"]')->item(0);
        $this->assertNotNull($download);
        parse_str(parse_url($download->getAttribute('href'), PHP_URL_QUERY) ?: '', $query);
        $this->assertSame('all', $query['period']);
        $this->assertSame('0', $query['ageing']);
        $this->assertNotNull($x->query('//*[@data-drawer-open="statementPrintFilters"]')->item(0));
        $this->assertSame(0, $x->query('//body[@onload]')->length);
        $this->get($download->getAttribute('href'))->assertOk()->assertSee('2,90,380')->assertSee('2,00,796');
    }

    public function test_preview_filters_use_shared_form_and_searchable_select_assets_without_external_close_icons(): void
    {
        $page = $this->get($this->url('/print?period=all&currency=INR&ageing=0'))->assertOk();
        foreach (['assets/css/master-form.css', 'assets/vendor/select2/css/select2.min.css',
            'assets/css/select2-theme.css', 'assets/vendor/jquery/jquery.min.js',
            'assets/vendor/select2/js/select2.min.js', 'assets/js/master-selects.js'] as $asset) {
            $page->assertSee($asset);
        }
        $x = $this->xpath($page->getContent());
        $this->assertSame(1, $x->query('//*[@id="statementPrintFilters"]//button[contains(@class,"core-drawer-close")]//*[local-name()="svg"]')->length);
        $this->assertSame(2, $x->query('//*[@id="statementPrintFilters"]//section[contains(@class,"core-drawer-section")]')->length);
        $this->assertSame(1, $x->query('//*[@id="statementPrintFilters"]//label[contains(@class,"master-switch")]/input[@name="ageing"]')->length);
        $this->assertSame(1, $x->query('//*[@id="statementPrintFilters"]//*[contains(@class,"core-drawer-footer")]//button[contains(@class,"master-btn-primary")]')->length);
    }

    public function test_print_custom_dates_do_not_revert_to_current_month(): void
    {
        $page = $this->get($this->url('/print?period=custom&date_from=2025-01-01&date_to=2025-12-31&currency=INR&ageing=0'))->assertOk();
        $s = $page->viewData('statement');
        $page->assertSee('01 Jan 2025 – 31 Dec 2025');
        $this->assertSame(2, $s['totals']['count']);
        $this->assertSame(200696.0, $s['totals']['closing']);
        $this->assertSame('2025-01-01', $s['period']['from']->toDateString());
        $this->assertSame('2025-12-31', $s['period']['to']->toDateString());
    }

    public function test_compact_paper_header_has_logo_party_details_and_no_pan(): void
    {
        $page = $this->get($this->url('/print?period=all&currency=INR'))->assertOk();
        $x = $this->xpath($page->getContent());
        $this->assertSame(1, $x->query('//img[contains(@class,"stmt-logo")]')->length);
        $this->assertSame(3, $x->query('//*[contains(@class,"stmt-issuer-address-line")]')->length);
        $heading = $x->query('//*[contains(@class,"stmt-heading")]')->item(0);
        $this->assertStringContainsString('Historical client', $heading->textContent);
        $this->assertStringContainsString('STMT-CLIENT', $heading->textContent);
        $this->assertStringContainsString('Client industrial road 123', $heading->textContent);
        $this->assertStringContainsString('Ahmedabad, Gujarat, 380004', $heading->textContent);
        $page->assertDontSee('PAN ');
    }

    public function test_monthly_defaults_remain_unchanged_and_an_explicit_foreign_currency_is_retained(): void
    {
        foreach (['', '/print', '/pdf'] as $suffix) {
            $page = $this->get($this->url($suffix.'?currency=INR&ageing=0'))->assertOk();
            $this->assertSame(1, $page->viewData('statement')['totals']['count']);
            $this->assertSame(100.0, $page->viewData('statement')['totals']['debit']);
            $this->assertSame(200696.0, $page->viewData('statement')['opening']);
            $this->assertSame('2026-10-01', $page->viewData('statement')['period']['from']->toDateString());
            $page = $this->get($this->url($suffix.'?period=all&currency=USD&ageing=0'))->assertOk();
            $this->assertSame('USD', $page->viewData('statement')['currency']);
            $this->assertSame(200.0, $page->viewData('statement')['totals']['closing']);
        }
    }

    public function test_vendor_preview_is_a_single_currency_payable_and_finance_boundary_is_retained(): void
    {
        $url = '/cashflows/statements/vendor/'.$this->fixture['vendor']->id.'/print?period=all&currency=RMB&ageing=0';
        $page = $this->get($url)->assertOk();
        $this->assertSame('payable', $page->viewData('statement')['direction']);
        $this->assertStringContainsString('Vendor factory road 456', $this->xpath($page->getContent())->query('//*[contains(@class,"stmt-heading")]')->item(0)->textContent);
        $this->assertSame(70.0, $page->viewData('statement')['totals']['closing']);
        $user = new User(['role'=>'admin','access_profile'=>'custom','permissions'=>['sales.view']]);
        $user->id = 900;
        $this->actingAs($user)->get($url)->assertForbidden();
        $user->role = 'employee';
        $this->actingAs($user)->get($url)->assertRedirect(route('my.dashboard'));
    }
}

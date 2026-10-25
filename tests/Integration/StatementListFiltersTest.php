<?php

namespace Tests\Integration;

use App\Models\Client;
use Carbon\Carbon;
use Tests\TestCase;

class StatementListFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $fixture = \Tests\Support\StatementListDatabaseFixture::install();
        $this->actingAs($fixture['user']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rendered_all_time_link_keeps_the_explicit_preset_and_fetches_both_party_types(): void
    {
        $page = $this->get('/cashflows/statements?currency=all');
        $page->assertOk();
        preg_match('/href="([^"]+)"[^>]*>All time<\/a>/', $page->getContent(), $matches);
        $this->assertNotEmpty($matches, 'Find the actual rendered All time control.');
        $url = html_entity_decode($matches[1]);
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
        $this->assertSame('all', $query['period'] ?? null);
        $this->assertArrayNotHasKey('date_from', $query);
        $this->assertArrayNotHasKey('date_to', $query);
        $all = $this->get($url)->assertOk()->assertViewHas('activeRange','all');
        $this->assertSame(['GBP','INR','RMB','USD'], collect($all->viewData('rows'))->pluck('currency')->sort()->values()->all());
        $this->assertSame(0.0, (float) collect($all->viewData('rows'))->sum('opening'));
    }

    public function test_default_currency_lists_all_accounts_and_totals_are_not_mixed(): void
    {
        $page = $this->get('/cashflows/statements?period=all')->assertOk()->assertViewHas('currency','all');
        $this->assertCount(4, $page->viewData('rows'));
        $totals = $page->viewData('totalsByCurrency');
        $this->assertSame(100.0, $totals['INR']['closing']);
        $this->assertSame(200.0, $totals['USD']['closing']);
        $this->assertSame(70.0, $totals['RMB']['closing']);
        $this->assertSame(-30.0, $totals['GBP']['closing']);
        $this->assertNull($page->viewData('totals')['closing']);
        $this->assertContains('GBP', $page->viewData('currencyOptions'));
        $activity = app(\App\Services\PartyStatement::class)->activity('client',null,null,null,'all');
        $this->assertNull($activity['totals']['closing']);
        $this->assertCount(3, $activity['rows']);
    }

    public function test_currency_quick_filters_preserve_all_time_party_type_and_search(): void
    {
        $page = $this->get('/cashflows/statements?period=all&party_type=client&q=Historical&currency=all')->assertOk();
        preg_match('/<nav[^>]*aria-label="Statement currencies"[^>]*>(.*?)<\/nav>/s', $page->getContent(), $nav);
        $this->assertNotEmpty($nav, 'Currencies are visible quick filters, not drawer-only.');
        preg_match_all('/href="([^"]+)"/', $nav[1], $links);
        $this->assertGreaterThanOrEqual(4, count($links[1]));
        foreach ($links[1] as $url) {
            parse_str(parse_url(html_entity_decode($url), PHP_URL_QUERY) ?: '', $query);
            $this->assertSame('all', $query['period'] ?? null);
            $this->assertSame('client', $query['party_type']);
            $this->assertSame('Historical', $query['q']);
        }
        $usd = $this->get('/cashflows/statements?period=all&party_type=client&q=Historical&currency=USD')->assertOk();
        $this->assertSame(['USD'], array_column($usd->viewData('rows'),'currency'));
        $this->assertSame(200.0, $usd->viewData('totals')['closing']);
        $this->assertStringContainsString('name="period" value="all"', $usd->getContent());
    }

    public function test_bad_currency_is_validated_without_changing_single_statement_currency_defaults(): void
    {
        $this->from('/cashflows/statements')->get('/cashflows/statements?currency[]=USD')
            ->assertRedirect('/cashflows/statements')->assertSessionHasErrors('currency');
        $this->from('/cashflows/statements')->get('/cashflows/statements?currency=NOTREAL')
            ->assertRedirect('/cashflows/statements')->assertSessionHasErrors('currency');
        $client = Client::first();
        $this->get('/cashflows/statements/client/'.$client->id.'?period=all')->assertOk()->assertViewHas('currency','USD');
    }

    public function test_vendor_debit_credit_labels_match_the_vendor_ledger_not_the_client_ledger(): void
    {
        $vendor = $this->get('/cashflows/statements?period=all&party_type=vendor')->assertOk();
        $this->assertSame(70.0, $vendor->viewData('rows')[0]['credit']);
        $vendor->assertSee('Debit <em>(Paid)</em>', false)->assertSee('Credit <em>(Billed)</em>', false);
    }


    public function test_calendar_month_includes_last_day_and_exposes_a_displayable_last_activity_date(): void
    {
        foreach ([['2026-10-31',25], ['2026-11-01',900]] as $i => $row) {
            \App\Models\SalesInvoice::create(['invoice_number'=>'END-'.$i,'invoice_type'=>'tax','status'=>'sent',
                'currency'=>'INR','invoice_date'=>$row[0],'total_amount'=>$row[1],'client_id'=>Client::first()->id]);
        }
        $page = $this->get('/cashflows/statements?period=this_month&currency=INR')->assertOk();
        $this->assertSame(125.0, $page->viewData('totals')['debit']);
        $this->assertSame('2026-10-31', $page->viewData('rows')[0]['last_date']);
        $page->assertSee('31 Oct 2026');
    }

}

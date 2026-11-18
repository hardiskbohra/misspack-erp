<?php

namespace Tests\Integration;

use App\Models\PartyStatementShare;
use App\Models\Project;
use App\Models\User;
use App\Models\VendorPaymentEntry;
use Carbon\Carbon;
use Tests\Support\StatementListDatabaseFixture;
use Tests\TestCase;

class StatementPrintFieldsTest extends TestCase
{
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = StatementListDatabaseFixture::install();
        (require database_path('migrations/2026_10_06_080000_drop_the_public_project_portal.php'))->up();
        $this->actingAs($this->fixture['user']);
        $this->fixture['client']->update(['billing_address' => 'Client address for print fields']);
        $this->fixture['vendor']->update(['address' => 'Vendor address for print fields']);
        $project = Project::create(['project_number' => 'FIELD-PROJECT', 'client_id' => $this->fixture['client']->id,
            'name' => 'Bottles <for> field test']);
        VendorPaymentEntry::create(['vendor_id' => $this->fixture['vendor']->id, 'project_id' => $project->id,
            'transaction_date' => '2026-10-01', 'invoice_number' => 'FIELD-REF-103', 'transaction_type' => 'credit',
            'foreign_currency' => 'RMB', 'foreign_amount' => 103.125, 'particular' => 'Vendor consumables', 'status' => 'booked']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function url(string $type, string $action = 'print', array $extra = []): string
    {
        return route('cashflows.statements.'.$action, ['partyType' => $type, 'party' => $this->fixture[$type]->id,
            'period' => 'all', 'currency' => $type === 'vendor' ? 'RMB' : 'INR', ...$extra]);
    }

    private function xpath(string $html): \DOMXPath
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML($html);

        return new \DOMXPath($doc);
    }

    private function selected(array $fields, array $extra = []): array
    {
        return ['fields_configured' => '1', 'print_fields' => $fields, ...$extra];
    }

    public function test_default_print_fields_are_checked_and_existing_client_and_vendor_documents_remain_complete(): void
    {
        foreach (['client', 'vendor'] as $type) {
            $page = $this->get($this->url($type))->assertOk();
            $x = $this->xpath($page->getContent());
            $this->assertSame(1, $x->query('//link[contains(@href,"statement.css?v=")]')->length, 'Print CSS is cache-busted.');
            $this->assertSame(1, $x->query('//*[@id="statementPrintFilterForm"]//input[@name="fields_configured" and @value="1"]')->length);
            foreach (['date', 'particulars', 'reference', 'debit', 'credit', 'balance', 'summary', 'bank_details',
                'other_currencies', 'notes', 'logo', 'issuer_details', 'party_details'] as $field) {
                $this->assertSame(1, $x->query('//*[@id="statementPrintFilterForm"]//input[@name="print_fields[]" and @value="'.$field.'" and @checked]')->length, $type.' '.$field);
            }
            $this->assertSame($type === 'vendor' ? 1 : 0,
                $x->query('//*[@id="statementPrintFilterForm"]//input[@name="print_fields[]" and @value="project_names" and @checked and @type="checkbox"]')->length);
            $this->assertSame(6, $x->query('(//table[contains(@class,"stmt-table")])[1]/thead/tr/th')->length);
            $this->assertSame(1, $x->query('//*[contains(@class,"stmt-block--summary")]')->length);
            $this->assertSame(1, $x->query('//*[contains(@class,"stmt-logo")]')->length);
            $this->assertStringContainsString('Bank:', $x->query('//article')->item(0)->textContent);
        }
    }

    public function test_selection_is_in_preview_download_and_fallback_without_changing_statement_numbers(): void
    {
        $fields = ['date', 'particulars', 'project_names', 'debit', 'credit', 'balance', 'logo', 'issuer_details'];
        $original = $this->get($this->url('vendor', 'print', ['ageing' => '0']))->assertOk();
        $page = $this->get($this->url('vendor', 'print', $this->selected($fields, ['ageing' => '0'])))->assertOk();
        $this->assertSame($original->viewData('statement')['totals'], $page->viewData('statement')['totals']);
        $this->assertEquals($original->viewData('statement')['rows'], $page->viewData('statement')['rows']);
        $this->assertSame($original->viewData('statement')['opening'], $page->viewData('statement')['opening']);
        $x = $this->xpath($page->getContent());
        $this->assertSame(5, $x->query('(//table[contains(@class,"stmt-table")])[1]/thead/tr/th')->length);
        $this->assertSame(0, $x->query('(//table[contains(@class,"stmt-table")])[1]/thead/tr/th[contains(.,"Reference")]')->length);
        $this->assertSame(0, $x->query('//*[contains(@class,"stmt-block--summary")]')->length);
        $this->assertSame(0, $x->query('//*[contains(@class,"stmt-ageing")]')->length);
        $page->assertDontSee('FIELD-REF-103')->assertDontSee('Bank:')->assertDontSee('Vendor address for print fields');
        $page->assertSee('Vendor consumables')->assertSee('Bottles &lt;for&gt; field test', false)->assertSee('Historical vendor');
        $this->assertSame(1, $x->query('//input[@name="print_fields[]" and @value="date" and @checked]')->length);
        $this->assertSame(1, $x->query('//input[@name="print_fields[]" and @value="reference" and not(@checked)]')->length);
        $this->assertStringContainsString('omits', strtolower($x->query('//article')->item(0)->textContent));

        $download = $x->query('//*[@id="statementDownload"]')->item(0)->getAttribute('href');
        parse_str(parse_url($download, PHP_URL_QUERY) ?: '', $query);
        $this->assertSame('1', $query['fields_configured']);
        $this->assertSame('all', $query['period']);
        $this->assertSame('RMB', $query['currency']);
        $this->assertSame('0', $query['ageing']);
        $this->assertEqualsCanonicalizing($fields, $query['print_fields']);
        $pdf = $this->get($download)->assertOk();
        $pdf->assertDontSee('FIELD-REF-103')->assertDontSee('Bank:')->assertSee('Vendor consumables');
        $this->assertSame($original->viewData('statement')['totals'], $pdf->viewData('statement')['totals']);
    }

    public function test_all_transaction_column_combinations_have_valid_geometry_and_truthful_totals(): void
    {
        $columns = ['date', 'particulars', 'reference', 'debit', 'credit', 'balance'];
        $sections = ['summary', 'logo', 'issuer_details', 'party_details', 'bank_details', 'other_currencies', 'notes'];
        $base = $this->get($this->url('client', 'print', ['ageing' => '0']))->assertOk()->viewData('statement');
        for ($mask = 0; $mask < 64; $mask++) {
            $selected = [];
            foreach ($columns as $index => $column) {
                if ($mask & (1 << $index)) $selected[] = $column;
            }
            $page = $this->get($this->url('client', 'print', $this->selected([...$sections, ...$selected], ['ageing' => '0'])))->assertOk();
            $this->assertSame($base['totals'], $page->viewData('statement')['totals'], 'mask '.$mask);
            $x = $this->xpath($page->getContent());
            $table = $x->query('(//table[contains(@class,"stmt-table")])[1]')->item(0);
            if (! $selected) {
                $this->assertNull($table, 'No columns should omit the transaction table.');
                continue;
            }
            $this->assertNotNull($table, 'mask '.$mask);
            $this->assertSame(count($selected), $x->query('./thead/tr/th', $table)->length, 'header mask '.$mask);
            foreach ($x->query('./tbody/tr|./tfoot/tr', $table) as $row) {
                $cellCount = 0;
                foreach ($x->query('./td', $row) as $cell) {
                    $span = $cell->hasAttribute('colspan') ? (int) $cell->getAttribute('colspan') : 1;
                    $this->assertGreaterThan(0, $span, 'No zero colspan, mask '.$mask);
                    $cellCount += $span;
                }
                $this->assertSame(count($selected), $cellCount, 'row geometry mask '.$mask);
            }
            $this->assertSame(in_array('balance', $selected, true) ? 1 : 0,
                $x->query('./tbody/tr[contains(@class,"stmt-row-opening")]', $table)->length, 'opening mask '.$mask);
            $money = array_intersect(['debit', 'credit', 'balance'], $selected);
            $this->assertSame($money ? 1 : 0, $x->query('./tfoot/tr', $table)->length, 'totals mask '.$mask);
            if ($money && ! in_array('balance', $selected, true)) {
                $this->assertStringContainsString('Period totals', $x->query('./tfoot', $table)->item(0)->textContent);
            }
        }
    }

    public function test_project_names_can_show_without_description_and_remain_escaped(): void
    {
        $page = $this->get($this->url('vendor', 'print', $this->selected(['project_names', 'balance'], ['ageing' => '0'])))->assertOk();
        $x = $this->xpath($page->getContent());
        $headers = preg_replace('/\s+/', ' ', $x->query('(//table[contains(@class,"stmt-table")])[1]/thead')->item(0)->textContent);
        $this->assertStringContainsString('Project', $headers);
        $page->assertSee('Bottles &lt;for&gt; field test', false)->assertDontSee('Bottles <for> field test', false)
            ->assertDontSee('Vendor consumables')->assertDontSee('FIELD-REF-103');
        $this->assertSame(1, $x->query('(//table[contains(@class,"stmt-table")])[1]//span[contains(@class,"stmt-project")]')->length);
    }

    public function test_date_and_reference_choices_also_apply_to_ageing_and_empty_statements(): void
    {
        foreach (['client', 'vendor'] as $type) {
            $fields = ['particulars', 'balance', 'summary'];
            $page = $this->get($this->url($type, 'print', $this->selected($fields, ['ageing' => '1'])))->assertOk();
            $this->assertNotNull($page->viewData('statement')['ageing']);
            $x = $this->xpath($page->getContent());
            $age = $x->query('//section[contains(@class,"stmt-ageing")]//table')->item(0);
            $this->assertNotNull($age);
            $this->assertSame(0, $x->query('./thead/tr/th[normalize-space(.)="Date" or normalize-space(.)="Reference"]', $age)->length);
            $this->assertStringNotContainsString($type === 'vendor' ? 'FIELD-REF-103' : 'STMT-1', $age->textContent);
            $count = $x->query('./thead/tr/th', $age)->length;
            foreach ($x->query('./tbody/tr|./tfoot/tr', $age) as $row) {
                $total = 0;
                foreach ($x->query('./td', $row) as $cell) $total += $cell->hasAttribute('colspan') ? (int) $cell->getAttribute('colspan') : 1;
                $this->assertSame($count, $total);
            }
        }
        $empty = $this->get($this->url('vendor', 'print', $this->selected(['reference', 'credit'],
            ['period' => 'custom', 'date_from' => '2030-01-01', 'date_to' => '2030-01-31', 'ageing' => '0'])))->assertOk();
        $x = $this->xpath($empty->getContent());
        $this->assertSame('2', $x->query('(//table[contains(@class,"stmt-table")])[1]//td[contains(.,"No transactions")]')->item(0)->getAttribute('colspan'));
    }

    public function test_field_selection_is_print_only_and_unknown_choices_do_not_inject_markup(): void
    {
        $choice = $this->selected(['date', '<script>bad()</script>', ['balance']], ['ageing' => '0']);
        $print = $this->get($this->url('vendor', 'print', $choice))->assertOk();
        $print->assertDontSee('<script>bad()</script>', false)->assertDontSee('FIELD-REF-103');
        $office = $this->get($this->url('vendor', 'show', $choice))->assertOk();
        $office->assertSee('FIELD-REF-103')->assertSee('Vendor consumables');
        $share = PartyStatementShare::issue(['party_type' => 'vendor', 'party_id' => $this->fixture['vendor']->id,
            'party_name' => 'Historical vendor', 'party_currency' => 'RMB', 'expires_in_days' => 0,
            'shared_via' => 'link', 'options' => ['currency' => 'RMB', 'ageing' => false]]);
        $this->get('/statement/'.$share->token.'?fields_configured=1')->assertOk()->assertSee('FIELD-REF-103')->assertSee('Vendor consumables');
        $malformed = $this->get($this->url('vendor', 'print', ['fields_configured' => '1', 'print_fields' => 'unexpected', 'ageing' => '0']))->assertOk();
        $this->assertSame(0, $this->xpath($malformed->getContent())->query('//table[contains(@class,"stmt-table")]')->length);
    }

    public function test_sections_can_be_omitted_without_changing_negative_foreign_balances_or_identity(): void
    {
        VendorPaymentEntry::create(['vendor_id' => $this->fixture['vendor']->id, 'transaction_date' => '2026-10-20',
            'invoice_number' => 'FIELD-OVERPAYMENT', 'transaction_type' => 'debit', 'foreign_currency' => 'RMB',
            'foreign_amount' => 500, 'particular' => 'Overpayment', 'status' => 'paid']);
        $base = $this->get($this->url('vendor', 'print', ['ageing' => '0']))->assertOk();
        $this->assertSame(-326.875, $base->viewData('statement')['totals']['closing']);
        $count = VendorPaymentEntry::count();
        $custom = $this->get($this->url('vendor', 'print', $this->selected(['date', 'balance'], ['ageing' => '0'])))->assertOk();
        $this->assertSame($base->viewData('statement')['totals'], $custom->viewData('statement')['totals']);
        $this->assertSame('RMB', $custom->viewData('statement')['currency']);
        $this->assertSame($count, VendorPaymentEntry::count(), 'Print choices never write the ledger.');
        $custom->assertSee('MissPack India Pvt Ltd')->assertSee('Historical vendor')->assertSee('All time')
            ->assertSee(\App\Helpers\CommonHelper::amount(-326.875, 'RMB'))
            ->assertDontSee('Vendor address for print fields')->assertDontSee('Bank:');
        $x = $this->xpath($custom->getContent());
        $this->assertSame(0, $x->query('//article//*[contains(@class,"stmt-logo") or contains(@class,"stmt-issuer-address-line") or contains(@class,"stmt-foot")]')->length);

        $clientBase = $this->get($this->url('client', 'print', ['currency' => 'USD', 'ageing' => '0']))->assertOk();
        $clientBase->assertSee('Also on this account')->assertSee('Built from sales invoices and receipts');
        $client = $this->get($this->url('client', 'print', $this->selected(['date', 'reference', 'balance'],
            ['currency' => 'USD', 'ageing' => '0'])))->assertOk();
        $client->assertDontSee('Also on this account')->assertDontSee('Built from sales invoices and receipts');
        $this->assertSame(200.0, $client->viewData('statement')['totals']['closing']);
        $this->assertSame($clientBase->viewData('statement')['totals'], $client->viewData('statement')['totals']);
    }

    public function test_print_options_do_not_bypass_office_authorization(): void
    {
        $restricted = new User(['role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['sales.view']]);
        $restricted->id = 900;
        $this->actingAs($restricted);
        foreach (['print', 'pdf'] as $action) {
            $this->get($this->url('vendor', $action, $this->selected(['date'])))->assertForbidden();
        }
        auth()->logout();
        foreach (['print', 'pdf'] as $action) {
            $this->get($this->url('vendor', $action, $this->selected(['date'])))->assertRedirect(route('login'));
        }
    }

    public function test_optional_server_pdf_receives_the_same_selection(): void
    {
        // Run the fake facade in another process so it cannot change the fallback
        // behaviour of later tests. In a micro-PHP sandbox PHP_BINARY is empty.
        $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
        $this->assertNotEmpty($php, 'Set TEST_PHP_BINARY for the isolated PDF test.');
        $process = proc_open([$php, base_path('tools/testing/check-statement-server-pdf.php')],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $output);
        $this->assertStringContainsString('PASS server PDF selection', $output);
    }
}

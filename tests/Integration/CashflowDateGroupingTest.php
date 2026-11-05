<?php

namespace Tests\Integration;

use App\Models\CashflowAccount;
use App\Models\CashflowEntry;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** Actual rendered ledger dividers, with a deterministic office clock. */
class CashflowDateGroupingTest extends TestCase
{
    use IsolatedDatabase;

    private CashflowAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]));
        config(['app.business_timezone' => 'Asia/Kolkata']);
        $this->actingAs(User::create(['name' => 'Finance', 'email' => 'cashflow-groups@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']));
        $this->account = CashflowAccount::create(['account_name' => 'Fixture bank', 'currency' => 'INR']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function addEntry(string $day, string $name, string $type = 'credit'): void
    {
        CashflowEntry::create([
            'entry_date' => $day, 'particular' => $name, 'transaction_type' => $type,
            'credit_amount' => $type === 'credit' ? 1 : 0, 'debit_amount' => $type === 'debit' ? 1 : 0,
            'currency' => 'INR', 'account_id' => $this->account->id, 'accounting_status' => 'booked',
        ]);
    }

    /** @return array<string, array{count: int, rows: list<string>}> */
    private function renderedGroups(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " master-table ")]/tbody/tr');
        $groups = [];
        $current = null;
        foreach ($rows as $row) {
            $class = ' '.$row->getAttribute('class').' ';
            if (str_contains($class, ' master-list-group ')) {
                $label = trim($xpath->query('./td/div/span[1]', $row)->item(0)->textContent);
                $count = (int) trim($xpath->query('./td/div/span[2]', $row)->item(0)->textContent);
                $this->assertArrayNotHasKey($label, $groups, 'A date group should never be repeated on the same page.');
                $groups[$label] = ['count' => $count, 'rows' => []];
                $current = $label;
            } elseif (str_contains($class, ' cf-row ')) {
                $this->assertNotNull($current, 'Every ledger row needs a divider.');
                $name = $xpath->query('./td[contains(concat(" ", normalize-space(@class), " "), " cf-particular ")]/a/strong', $row)->item(0);
                $groups[$current]['rows'][] = trim($name->textContent);
            }
        }
        return $groups;
    }

    public function test_listing_groups_rows_in_specificity_order_and_preserves_oldest_sort(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-29 10:00:00', 'Asia/Kolkata'));
        foreach ([
            ['2026-11-15', 'Future far'], ['2026-10-30', 'Future near'],
            ['2026-10-29', 'Today entry'], ['2026-10-28', 'Yesterday entry'], ['2026-10-27', 'Two days entry'],
            ['2026-10-26', 'This week entry'], ['2026-10-25', 'Last week recent'], ['2026-10-19', 'Last week start'],
            ['2026-10-18', 'This month recent'], ['2026-10-01', 'This month start'],
            ['2026-09-30', 'Last month recent'], ['2026-09-01', 'Last month start'],
            ['2026-08-31', 'Older recent'], ['2025-12-31', 'Older past'],
        ] as [$date, $name]) $this->addEntry($date, $name);
        $expected = [
            'Upcoming' => ['count' => 2, 'rows' => ['Future far', 'Future near']],
            'Today' => ['count' => 1, 'rows' => ['Today entry']],
            'Yesterday' => ['count' => 1, 'rows' => ['Yesterday entry']],
            '2 days ago' => ['count' => 1, 'rows' => ['Two days entry']],
            'This week' => ['count' => 1, 'rows' => ['This week entry']],
            'Last week' => ['count' => 2, 'rows' => ['Last week recent', 'Last week start']],
            'This month' => ['count' => 2, 'rows' => ['This month recent', 'This month start']],
            'Last month' => ['count' => 2, 'rows' => ['Last month recent', 'Last month start']],
            'Older entries' => ['count' => 2, 'rows' => ['Older recent', 'Older past']],
        ];
        $response = $this->get(route('cashflows.index'))->assertOk();
        $this->assertSame($expected, $this->renderedGroups($response->getContent()));

        $oldest = $this->get(route('cashflows.index', ['sort' => 'oldest']))->assertOk();
        $ascending = array_reverse($expected, true);
        foreach ($ascending as &$group) $group['rows'] = array_reverse($group['rows']);
        unset($group);
        $this->assertSame($ascending, $this->renderedGroups($oldest->getContent()));
        $oldest->assertSee('Oldest first');
    }

    public function test_week_month_and_year_boundaries_are_disjoint(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-04 09:00:00', 'Asia/Kolkata'));
        foreach ([['2027-01-04', 'Monday'], ['2027-01-03', 'Sunday'], ['2027-01-02', 'Saturday'],
            ['2027-01-01', 'New Year'], ['2026-12-31', 'Year end'], ['2026-12-27', 'December'], ['2026-11-30', 'November']] as [$date, $name]) {
            $this->addEntry($date, $name);
        }
        $this->assertSame([
            'Today' => ['count' => 1, 'rows' => ['Monday']],
            'Yesterday' => ['count' => 1, 'rows' => ['Sunday']],
            '2 days ago' => ['count' => 1, 'rows' => ['Saturday']],
            'Last week' => ['count' => 2, 'rows' => ['New Year', 'Year end']],
            'Last month' => ['count' => 1, 'rows' => ['December']],
            'Older entries' => ['count' => 1, 'rows' => ['November']],
        ], $this->renderedGroups($this->get(route('cashflows.index'))->assertOk()->getContent()));

        CashflowEntry::query()->delete();
        Carbon::setTestNow(Carbon::parse('2026-03-01 09:00:00', 'Asia/Kolkata'));
        foreach ([['2026-03-01', 'March'], ['2026-02-28', 'Yesterday'], ['2026-02-27', 'Two days'],
            ['2026-02-23', 'Week in February'], ['2026-02-22', 'Last week end'], ['2026-02-16', 'Last week start'],
            ['2026-02-15', 'February'], ['2026-01-31', 'January']] as [$date, $name]) {
            $this->addEntry($date, $name);
        }
        $this->assertSame([
            'Today' => ['count' => 1, 'rows' => ['March']],
            'Yesterday' => ['count' => 1, 'rows' => ['Yesterday']],
            '2 days ago' => ['count' => 1, 'rows' => ['Two days']],
            'This week' => ['count' => 1, 'rows' => ['Week in February']],
            'Last week' => ['count' => 2, 'rows' => ['Last week end', 'Last week start']],
            'Last month' => ['count' => 1, 'rows' => ['February']],
            'Older entries' => ['count' => 1, 'rows' => ['January']],
        ], $this->renderedGroups($this->get(route('cashflows.index'))->assertOk()->getContent()));
    }

    public function test_business_timezone_and_filters_control_visible_groups(): void
    {
        // It is still Wednesday in UTC, but already Thursday for the office.
        Carbon::setTestNow(Carbon::parse('2026-10-28 20:00:00', 'UTC'));
        foreach ([['2026-10-29', 'Office today', 'credit'], ['2026-10-28', 'Office yesterday', 'debit'],
            ['2026-10-27', 'Office two days', 'debit'], ['2026-10-26', 'Office week', 'debit'],
            ['2026-10-25', 'Office last week', 'credit']] as [$date, $name, $type]) $this->addEntry($date, $name, $type);
        $this->assertSame([
            'Today' => ['count' => 1, 'rows' => ['Office today']],
            'Yesterday' => ['count' => 1, 'rows' => ['Office yesterday']],
            '2 days ago' => ['count' => 1, 'rows' => ['Office two days']],
            'This week' => ['count' => 1, 'rows' => ['Office week']],
            'Last week' => ['count' => 1, 'rows' => ['Office last week']],
        ], $this->renderedGroups($this->get(route('cashflows.index'))->assertOk()->getContent()));
        $this->assertSame([
            'Yesterday' => ['count' => 1, 'rows' => ['Office yesterday']],
            '2 days ago' => ['count' => 1, 'rows' => ['Office two days']],
            'This week' => ['count' => 1, 'rows' => ['Office week']],
        ], $this->renderedGroups($this->get(route('cashflows.index', ['transaction_type' => 'debit']))->assertOk()->getContent()));
        $this->assertSame([
            'Yesterday' => ['count' => 1, 'rows' => ['Office yesterday']],
            '2 days ago' => ['count' => 1, 'rows' => ['Office two days']],
        ], $this->renderedGroups($this->get(route('cashflows.index', ['date_from' => '2026-10-27', 'date_to' => '2026-10-28']))->assertOk()->getContent()));
    }

    public function test_divider_counts_are_for_the_current_page(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-29 10:00:00', 'Asia/Kolkata'));
        for ($i = 1; $i <= 101; $i++) $this->addEntry('2026-10-29', "Today $i");
        for ($i = 1; $i <= 3; $i++) $this->addEntry('2026-10-28', "Yesterday $i");
        $first = $this->renderedGroups($this->get(route('cashflows.index'))->assertOk()->getContent());
        $this->assertSame(['Today'], array_keys($first));
        $this->assertSame(100, $first['Today']['count']);
        $this->assertCount(100, $first['Today']['rows']);
        $second = $this->renderedGroups($this->get(route('cashflows.index', ['page' => 2]))->assertOk()->getContent());
        $this->assertSame(['Today', 'Yesterday'], array_keys($second));
        $this->assertSame(['count' => 1, 'rows' => ['Today 1']], $second['Today']);
        $this->assertSame(['count' => 3, 'rows' => ['Yesterday 3', 'Yesterday 2', 'Yesterday 1']], $second['Yesterday']);
    }
}

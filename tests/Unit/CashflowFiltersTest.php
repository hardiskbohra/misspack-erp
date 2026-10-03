<?php

namespace Tests\Unit;

use App\Models\CashflowEntry;
use App\Services\CashflowFilters;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * What a filter means when the operator has typed nothing.
 *
 * This is the one file in the project that *runs* the ledger's filters, and it
 * exists because two bugs shipped that a source-reading check could not see:
 *
 *   1. `$request->query($key, self::DEFAULTS[$key] ?? 'all')` reads a
 *      *declared null* as a missing key, so a blank search box became
 *      `search=all`: the ledger listed the entries containing the word "all",
 *      which is none of them, and the page said "No cashflow entries yet".
 *   2. `$filters[$key] ?? $default` in `apply()` then did the same thing in the
 *      other direction — a filter that was present-and-empty was re-defaulted
 *      to the sentinel, so the fix upstream changed nothing on screen.
 *
 * Both are the same question asked in four places: "is this filter set, is it
 * empty, or does it hold a value?" The checks in `tools/checks/*.cjs` read that
 * question out of the source; this test asks it of the running code, which is
 * the only way to know the answer.
 *
 * Run it with:  php artisan test --filter=CashflowFiltersTest
 *
 * No database is touched: the filters are turned into SQL and read back
 * (`toSql()` + `getBindings()`), never executed.
 */
class CashflowFiltersTest extends TestCase
{
    /** The filters a request produces, exactly as the controller asks for them. */
    private function filters(array $query = []): array
    {
        return (new CashflowFilters())->fromRequest(Request::create('/cashflows', 'GET', $query));
    }

    /** The SQL a set of filters turns into — the shape of the query, not its rows. */
    private function query(array $query = []): array
    {
        $builder = (new CashflowFilters())->apply(CashflowEntry::query(), $this->filters($query));

        return [
            'sql' => $builder->toSql(),
            'bindings' => $builder->getBindings(),
        ];
    }

    /**
     * The bug the office saw: open the ledger, see nothing.
     */
    public function test_a_request_with_no_filters_narrows_nothing(): void
    {
        $query = $this->query();

        $this->assertStringNotContainsString('where', $query['sql'], 'a bare ledger must not filter');
        $this->assertSame([], $query['bindings'], 'a bare ledger must not bind a value');
    }

    public function test_the_empty_filters_are_empty_and_the_rest_are_sentinels(): void
    {
        $filters = $this->filters();

        $this->assertNull($filters['search'], 'an empty search box is no search');
        $this->assertNull($filters['dateFrom'], 'an empty range end is no bound');
        $this->assertNull($filters['dateTo'], 'an empty range end is no bound');

        foreach (['accountId', 'categoryId', 'transactionType', 'documents'] as $key) {
            $this->assertSame('all', $filters[$key], $key.' widens with its sentinel');
        }
    }

    /**
     * A range *name* where a day belongs — `?date_from=all` — arrives by link and
     * by saved view. It used to reach Carbon and turn the page into a 500.
     */
    public function test_a_range_name_where_a_day_belongs_is_no_bound(): void
    {
        $query = $this->query(['date_from' => 'all', 'date_to' => 'all']);

        $this->assertStringNotContainsString('where', $query['sql']);
        $this->assertSame([], $query['bindings']);
    }

    public function test_a_real_range_bounds_the_query(): void
    {
        $query = $this->query(['date_from' => '2026-04-01', 'date_to' => '2026-06-30']);

        /* The grammar differs by driver (sqlite wraps the column in strftime,
           MySQL in date()); the bound days are what must be right. */
        $this->assertStringContainsString('entry_date', $query['sql']);
        $this->assertStringContainsString('>=', $query['sql']);
        $this->assertStringContainsString('<=', $query['sql']);
        $this->assertSame(['2026-04-01', '2026-06-30'], $query['bindings']);
    }

    public function test_a_search_searches_for_what_was_typed(): void
    {
        $query = $this->query(['search' => 'Ramesh']);

        $this->assertStringContainsString('like', $query['sql']);
        $this->assertSame(['%Ramesh%'], array_slice($query['bindings'], 0, 1));
    }

    /**
     * Typing the word "all" in the search box is a search for the word "all".
     * Only the *empty* box was ever the bug.
     */
    public function test_the_word_all_typed_by_hand_is_still_a_search(): void
    {
        $query = $this->query(['search' => 'all']);

        $this->assertStringContainsString('like', $query['sql']);
        $this->assertSame(['%all%'], array_slice($query['bindings'], 0, 1));
    }

    /**
     * What travels on to a link, a report cell or a saved view: the filters that
     * are set, and nothing else. `search=all` used to be saved into views and
     * re-applied every time they were opened.
     */
    public function test_nothing_that_is_unset_travels_or_is_chipped(): void
    {
        $service = new CashflowFilters();
        $empty = $service->fromRequest(Request::create('/cashflows', 'GET', ['search' => '']));

        $this->assertSame([], $service->toQuery($empty), 'an empty filter set has no query string');
        $this->assertSame([], $service->applied($empty), 'an empty filter set has no chips');

        $set = $service->fromRequest(Request::create('/cashflows', 'GET', [
            'search' => 'Ramesh',
            'date_from' => '2026-04-01',
            'date_to' => '2026-06-30',
        ]));

        $this->assertSame(
            ['search' => 'Ramesh', 'date_from' => '2026-04-01', 'date_to' => '2026-06-30'],
            $service->toQuery($set),
            'exactly what was asked for travels'
        );
        $this->assertCount(3, $service->applied($set), 'each set filter owns one chip');
    }

    /**
     * The two screens that read the same vocabulary — the archive and the
     * report builder — ask the same question and must get the same answer.
     */
    public function test_every_declared_filter_is_reachable_and_has_a_label(): void
    {
        $this->assertCount(17, CashflowFilters::DEFAULTS);
        $this->assertSame(
            array_keys(CashflowFilters::DEFAULTS),
            array_values(CashflowFilters::VIEW_KEYS),
            'the view vocabulary covers every filter'
        );
        $this->assertSame(
            array_keys(CashflowFilters::VIEW_KEYS),
            array_keys(CashflowFilters::LABELS),
            'every filter can name itself on a chip'
        );
    }
}

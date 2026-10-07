<?php

namespace Tests\Unit;

use App\Models\Note;
use App\Services\NoteFilters;
use App\Services\NoteVocabulary;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The two rules a source-reading check cannot prove.
 *
 * `tools/checks/notes-check.cjs` asserts that every builder in the controller
 * is owned and that the module has one writer. Neither of those is enough on
 * its own:
 *
 *   1. **a note belongs to one login** — a source check can see `ownedBy()` in
 *      the SQL, not what the SQL *says*. The floor under the whole module is
 *      that a missing owner matches no rows rather than every row, and that is
 *      a claim about the query, so it is asked of the query;
 *   2. **a hand-typed filter is the default** — the ledger shipped two bugs of
 *      exactly this shape (a blank search box became `search=all`, and the page
 *      said "No cashflow entries yet"), because "is this filter set, is it
 *      empty, or does it hold a value?" was asked in four places. It is asked
 *      in one place here, and this file asks it of the running code.
 *
 * Run it with:  php artisan test --filter=NotesRulesTest
 *
 * No database is touched: the queries are turned into SQL and read back
 * (`toSql()` + `getBindings()`), never executed, and the model is exercised
 * through attributes that were never saved.
 */
class NotesRulesTest extends TestCase
{
    /** The filters a request produces, exactly as the controller asks for them. */
    private function filters(array $query = []): array
    {
        return (new NoteFilters())->fromRequest(Request::create('/notes', 'GET', $query));
    }

    /**
     * The SQL without its identifier quoting.
     *
     * Which character wraps a column is the grammar's business — `"is_pinned"`
     * on SQLite, `` `is_pinned` `` on MySQL — and a test about what the query
     * *means* should not fail because the office runs MariaDB.
     */
    private function sql($query): string
    {
        return str_replace(['"', '`', '[', ']'], '', $query->toSql());
    }

    /* ------------------------------------------------- the privacy floor */

    /**
     * The bug that would matter: a session with no person in it listing
     * everybody's notes.
     */
    public function test_a_missing_owner_matches_no_rows_at_all(): void
    {
        $this->assertStringContainsString('1 = 0', $this->sql(Note::query()->ownedBy(null)));
        $this->assertStringContainsString('1 = 0', $this->sql(Note::query()->ownedBy(0)));
    }

    public function test_an_owner_narrows_to_one_column_and_one_value(): void
    {
        $query = Note::query()->ownedBy(7);

        $this->assertStringContainsString('user_id = ?', $this->sql($query));
        $this->assertSame([7], $query->getBindings());
    }

    /* ----------------------------------------------------- the filters */

    public function test_nothing_typed_is_the_default_view(): void
    {
        $this->assertSame(NoteFilters::DEFAULTS, $this->filters());
    }

    public function test_a_hand_typed_filter_is_the_default_rather_than_an_empty_list(): void
    {
        $this->assertSame(
            NoteFilters::DEFAULTS,
            $this->filters(['state' => 'garbage', 'colour' => 'orange', 'period' => 'soon', 'sort' => 'nope'])
        );
    }

    public function test_a_real_filter_survives_the_read(): void
    {
        $filters = $this->filters(['q' => '  challan  ', 'state' => 'filed', 'colour' => 'blue', 'period' => 'week', 'sort' => 'title']);

        $this->assertSame('challan', $filters['q']);
        $this->assertSame('filed', $filters['state']);
        $this->assertSame('blue', $filters['colour']);
        $this->assertSame('week', $filters['period']);
        $this->assertSame('title', $filters['sort']);
    }

    /** A chip's count answers "what would I get if I clicked it". */
    public function test_lifting_the_state_chip_lifts_that_one_key_and_no_other(): void
    {
        $filters = $this->filters(['q' => 'ply', 'state' => 'filed', 'colour' => 'pink', 'period' => 'month']);

        $lifted = (new NoteFilters())->withoutState($filters);

        $this->assertSame('everything', $lifted['state']);
        $this->assertSame('ply', $lifted['q']);
        $this->assertSame('pink', $lifted['colour']);
        $this->assertSame('month', $lifted['period']);
    }

    /** The strip names the drawer's criteria only — the chips are already on screen. */
    public function test_the_applied_strip_never_repeats_a_chip(): void
    {
        $filters = (new NoteFilters());

        $this->assertSame([], $filters->applied($this->filters(['state' => 'filed', 'colour' => 'grey', 'q' => 'rajesh'])));

        $applied = $filters->applied($this->filters(['period' => 'today', 'sort' => 'created']));

        $this->assertCount(2, $applied);
        $this->assertSame(['period', 'sort'], array_column($applied, 'key'));
    }

    /** The default view's URL is `/notes`, not `?state=desk&colour=all&period=any`. */
    public function test_the_query_string_the_module_writes_back_carries_only_what_narrows(): void
    {
        $filters = new NoteFilters();

        $this->assertSame([], $filters->toQuery($this->filters()));
        $this->assertSame(['state' => 'filed', 'colour' => 'blue'],
            $filters->toQuery($this->filters(['state' => 'filed', 'colour' => 'blue'])));
    }

    /* --------------------------------------------------- the query it builds */

    public function test_the_state_chip_is_the_desk_or_the_drawer(): void
    {
        $filters = new NoteFilters();

        $desk = $this->sql($filters->apply(Note::query(), $this->filters(['state' => 'desk'])));
        $filed = $this->sql($filters->apply(Note::query(), $this->filters(['state' => 'filed'])));
        $pinned = $this->sql($filters->apply(Note::query(), $this->filters(['state' => 'pinned'])));

        $this->assertStringContainsString('archived_at is null', $desk);
        $this->assertStringContainsString('archived_at is not null', $filed);
        $this->assertStringContainsString('archived_at is null', $pinned);
        $this->assertStringContainsString('is_pinned', $pinned);
    }

    /** A colour the vocabulary does not know must not narrow anything. */
    public function test_a_colour_that_is_not_a_colour_narrows_nothing(): void
    {
        $filters = new NoteFilters();

        $this->assertStringNotContainsString(
            'colour',
            $this->sql($filters->apply(Note::query(), $this->filters(['colour' => 'all'])))
        );
    }

    public function test_every_order_a_reader_can_choose_is_a_scope_the_model_has(): void
    {
        foreach (NoteFilters::SORT_SCOPES as $scope) {
            $this->assertTrue(
                method_exists(Note::class, 'scope'.ucfirst($scope)),
                "Note::scope{$scope}() is missing"
            );
        }

        $this->assertStringContainsString('title', $this->sql((new NoteFilters())->order(Note::query(), ['sort' => 'title'])));
        $this->assertStringContainsString('created_at', $this->sql((new NoteFilters())->order(Note::query(), ['sort' => 'created'])));
        $this->assertStringContainsString('updated_at', $this->sql((new NoteFilters())->order(Note::query(), ['sort' => 'edited'])));
    }

    /** The board is a board whatever the table is sorted by. */
    public function test_the_board_puts_pinned_first(): void
    {
        $this->assertMatchesRegularExpression('/order by is_pinned desc/', $this->sql(Note::query()->deskOrder()));
    }

    /* ----------------------------------------------------- the vocabulary */

    public function test_a_colour_nobody_chose_is_the_default_and_never_an_unknown_key(): void
    {
        $this->assertSame(NoteVocabulary::DEFAULT_COLOUR, NoteVocabulary::normalise(null));
        $this->assertSame(NoteVocabulary::DEFAULT_COLOUR, NoteVocabulary::normalise(''));
        $this->assertSame(NoteVocabulary::DEFAULT_COLOUR, NoteVocabulary::normalise('Orange'));
        $this->assertSame('yellow', NoteVocabulary::normalise('  yellow  '));
        $this->assertSame(NoteVocabulary::DEFAULT_COLOUR, NoteVocabulary::normalise('constructed'));
    }

    public function test_the_vocabulary_agrees_with_itself(): void
    {
        $this->assertArrayHasKey(NoteVocabulary::DEFAULT_COLOUR, NoteVocabulary::colours());

        foreach (NoteVocabulary::colours() as $key => $label) {
            $this->assertTrue(NoteVocabulary::hasColour($key));
            $this->assertSame($label, NoteVocabulary::label($key));
            $this->assertSame($key, strtolower($key), 'a colour key is also a css class suffix');
        }
    }

    /** A note is filed or it is on the desk — one column decides. */
    public function test_filing_is_one_fact(): void
    {
        $this->assertFalse((new Note())->isArchived());
        $this->assertTrue((new Note(['archived_at' => now()]))->isArchived());
    }

    /** The board prints the words; the table prints one line of them. */
    public function test_the_excerpt_folds_whitespace_into_one_line(): void
    {
        $note = new Note(['body' => "Call Rajesh\n\nabout the 6 mm ply"]);

        $this->assertSame('Call Rajesh about the 6 mm ply', $note->excerpt());
        $this->assertSame('', (new Note())->excerpt());
        $this->assertSame('', (new Note(['body' => "   \n  "]))->excerpt());
    }
}

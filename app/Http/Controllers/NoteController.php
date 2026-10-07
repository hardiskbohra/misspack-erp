<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\NoteFigures;
use App\Services\NoteFilters;
use App\Services\NoteIntake;
use App\Services\NoteVocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Notes — the sticky notes that were on your desk, and who may read them.
 *
 * This is the one screen in the application that belongs to the *login* rather
 * than to the office or to a person's record: an administrator keeps their
 * stickies here and so does an employee, and neither can see the other's. That
 * is why the routes live outside the `office` group (which would turn an
 * employee around at the door) and outside `/my` (an employee's own record,
 * where an administrator has no business) — and why the single most important
 * line in this file is `mine()`.
 *
 * The page is one query read twice: a board of sticky cards to think in front
 * of, and a table to work through. The chips decide which notes both draw; the
 * board always puts pinned first; the table takes the order the reader chose.
 */
class NoteController extends Controller
{
    public function __construct(
        private NoteFilters $filters,
        private NoteFigures $figures,
        private NoteIntake $intake,
    ) {
    }

    /* --------------------------------------------------------------- the desk */

    public function index(Request $request): View
    {
        $filters = $this->filters->fromRequest($request);
        $owner = $request->user();

        /* **One query.** The board below, the table under it and the figures
           above them are this builder or a clone of it, so the two readouts
           cannot drift apart — a filter added here reaches all three. */
        $query = $this->filters->apply(Note::query()->ownedBy($owner->id), $filters);

        $board = (clone $query)->deskOrder()->limit(NoteVocabulary::BOARD_LIMIT)->get();

        $figures = $this->figures->summary($query);

        /* The chip counts are the one reading that needs its own builder: they
           are counted with the state chip lifted, or each chip would report on
           the state it is standing in rather than the state it offers. */
        $stateCounts = $this->figures->stateCounts(
            $this->filters->apply(
                Note::query()->ownedBy($owner->id),
                $this->filters->withoutState($filters)
            )
        );

        $notes = $this->filters->order($query, $filters)->paginate(20)->withQueryString();

        $applied = $this->filters->applied($filters);

        return view('notes.index', [
            'board' => $board,
            'notes' => $notes,
            'figures' => $figures,
            'stateCounts' => $stateCounts,
            'stateOptions' => NoteFilters::STATE_LABELS,
            'colourOptions' => NoteVocabulary::colours(),
            'periodOptions' => NoteFilters::PERIOD_LABELS,
            'sortOptions' => NoteFilters::SORT_LABELS,
            'applied' => $applied,
            'filtered' => $applied !== [],
            'boardLimit' => NoteVocabulary::BOARD_LIMIT,
            ...$filters,
        ]);
    }

    /* ------------------------------------------------------------ the writers */

    /** Quick capture: the composer on the desk, one box and a colour. */
    public function store(Request $request): RedirectResponse
    {
        $this->intake->create($request->user(), $this->facts($request));

        return redirect()
            ->route('notes.index')
            ->with('success', 'Note on the desk.');
    }

    /** One note, its words and its colour. */
    public function edit(Request $request, string $note): View
    {
        $note = $this->mine($request, $note);

        return view('notes.edit', [
            'note' => $note,
            'colourOptions' => NoteVocabulary::colours(),
        ]);
    }

    public function update(Request $request, string $note): RedirectResponse
    {
        $this->intake->update($this->mine($request, $note), $this->facts($request));

        return redirect()
            ->route('notes.index')
            ->with('success', 'Note saved.');
    }

    /** Pinned, or back in the pile. The form sends which. */
    public function pin(Request $request, string $note): RedirectResponse
    {
        $note = $this->mine($request, $note);
        $pinned = $request->boolean('pinned');

        $this->intake->pin($note, $pinned);

        return back()->with('success', $pinned ? 'Pinned to the front.' : 'Back in the pile.');
    }

    /**
     * Off the desk, or back onto it.
     *
     * One URL with two directions, because the button the reader pressed says
     * which way it goes and the note's own state has to agree with it. A second
     * route would be a second way to say the same thing, and the two would
     * eventually disagree about a note that is already filed.
     */
    public function archive(Request $request, string $note): RedirectResponse
    {
        $note = $this->mine($request, $note);

        if ($note->isArchived()) {
            $this->intake->restore($note);

            return back()->with('success', 'Back on the desk.');
        }

        $this->intake->fileAway($note);

        return back()->with('success', 'Filed away — look under “Filed away” when you want it again.');
    }

    public function destroy(Request $request, string $note): RedirectResponse
    {
        $this->intake->delete($this->mine($request, $note));

        return redirect()
            ->route('notes.index')
            ->with('success', 'Note deleted.');
    }

    /* ------------------------------------------------------------- the private */

    /**
     * The only way to reach a note: an id, looked up inside the owner's rows.
     *
     * The route hands this method a *string*, never a `Note` — deliberately.
     * With route-model binding, `/{note}` would fetch the row first and the
     * ownership check would be a step somebody can forget to write; here the
     * row does not exist unless it belongs to the person asking. A note that is
     * not yours is a **404**, not a 403: an id that is not yours should not even
     * be confirmed to exist.
     */
    private function mine(Request $request, string $id): Note
    {
        return Note::query()
            ->ownedBy($request->user()->id)
            ->findOrFail($id);
    }

    /** The facts the form carries, checked — and nothing else reaches a note. */
    private function facts(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:'.NoteVocabulary::TITLE_LIMIT],
            'body' => ['nullable', 'string', 'max:'.NoteVocabulary::BODY_LIMIT],
            'colour' => ['nullable', Rule::in(array_keys(NoteVocabulary::colours()))],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        /* An unticked checkbox sends nothing at all, so `validated` would not
           mention it and the writer would leave the note exactly as it was —
           which is how a note stays pinned after you unpin it. The field is
           always present here; its value is what the box actually said. */
        $validated['is_pinned'] = $request->boolean('is_pinned');

        return $validated;
    }
}

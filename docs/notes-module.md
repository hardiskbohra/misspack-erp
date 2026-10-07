# The notes module

**Status: built.** One screen with a board and a table, one note's own page, and
one rule that everything else is arranged around: **a note belongs to the login
that wrote it.** Its guard rails are `tools/checks/notes-check.cjs` (52 checks),
and the two rules a source check cannot prove — that a missing owner matches no
rows, and that a hand-typed filter is the default — are held by
`tests/Unit/NotesRulesTest.php` (`php artisan test --filter=NotesRulesTest`).

It follows the house rule — a vocabulary, a filter, a figures reader and *one
writer per fact* — which for a module this small means four services and a
controller that decides nothing on its own:

| File | Owns |
| --- | --- |
| `app/Services/NoteVocabulary.php` | the six colours, the default, the three limits |
| `app/Services/NoteFilters.php` | every way the page can be narrowed, and the one query |
| `app/Services/NoteFigures.php` | the four figures and the chip counts, each one row of sums |
| `app/Services/NoteIntake.php` | the only writer: create, update, pin, file away, restore, delete |
| `app/Http/Controllers/NoteController.php` | what a request may ask for, and who is asking |

---

## 1. Why this is not a notes app

The ask was a desk covered in paper: sticky notes, a diary page, a phone number
written on the back of a delivery challan. The alternatives were considered and
are worth writing down, because they explain the shape:

- **A general notes app** (Keep, Notion, a Notes tab in the browser) is better at
  writing notes and worse at the one thing this needs: it is *outside* the ERP.
  The note that says "call Rajesh about the 6 mm ply shortfall before the PPS
  run" belongs beside the project it is about, in the tool the person already has
  open, behind the login they already used this morning.
- **An office-wide noticeboard** was rejected outright. Half of what lands on a
  desk is not for the office: a salary question, a candidate's number, a
  reminder to complain to a vendor. A shared board would quietly become a
  screen nobody writes on.
- **Reusing Tasks** was rejected for the same reason in reverse. A task is
  office work with an owner and a status, and it lives in the office's half of
  the application. A note is private, has no status, and belongs to whoever is
  signed in — an employee included, who cannot open the Tasks module at all.

So: one module, its own table, private per login, and deliberately smaller than
it could be. What was *not* built is listed in §8.

## 2. The one rule

A note is private to the login that wrote it — the office does not read an
employee's notes and an employee does not read the office's. There is no
"administrator can see everything", because that would make the promise on the
screen ("nobody else, not even the office, reads your notes") a lie.

Three things enforce it, and the check reads all three:

1. **One scope.** `Note::scopeOwnedBy($userId)` is the first clause of every
   query in the module, and it is written once, in the model. A `null` owner — a
   session that has gone, a console command with no actor — matches **no** rows
   (`1 = 0`) rather than all of them, which is what a bare
   `where('user_id', null)` would quietly become.
2. **One row door.** `NoteController::mine()` resolves an id *inside* the
   owner's rows and 404s otherwise. The route hands the controller a **string**,
   never a `Note`: with route-model binding the row would be fetched first and
   the ownership check would be a step somebody could forget.
3. **No user ids in URLs.** Every route reads the session. There is nothing to
   guess at and nothing to change in the address bar.

A note that is not yours is a **404, not a 403**: an id that is not yours should
not even be confirmed to exist.

## 3. The data model

`notes` — one table, created by
`database/migrations/2026_10_07_120000_create_notes_table.php`.

| Column | Why it exists |
| --- | --- |
| `user_id` | **who may read it.** Non-null, foreign key to `users`, `cascadeOnDelete`. Not "who wrote it": a note with no owner is a note with nobody to be private to, so the row goes when the person does |
| `title` | 160 characters, required — a desk of untitled stickies is a desk you cannot search |
| `body` | the words. Nullable, and an empty body is stored as `null` rather than a string of spaces, so "has this note got anything in it?" is answered the same way by the card, the table and the search |
| `colour` | one of the vocabulary's keys. Nullable, and **with no database default**: the vocabulary is the app's, and a default in the schema would be a second, silent answer to "what colour is a note nobody was asked about" |
| `is_pinned` | stuck to the front of the board |
| `archived_at` | filed away. Nullable, rather than a boolean: the *moment* a note left the desk is a fact worth keeping, and "is it filed" is `archived_at !== null` — one fact, one column |
| `created_at`, `updated_at` | written once and edited last, which is what the board's order and the table's "Edited" column read |

Two indexes, one per question the page asks: `[user_id, is_pinned, updated_at]`
for the board's order, `[user_id, archived_at]` for the chips.

There is **no status column and no state machine**. A note is on the desk or it
is filed away; a note you are done with is deleted.

## 4. The vocabulary

`NoteVocabulary` owns the words:

- **the six colours** — yellow, blue, green, pink, purple, grey — as
  `key => label`, plus `DEFAULT_COLOUR`. The key is written in three places at
  once (`notes.colour`, the label on the select, and the CSS class
  `.nt-note--<key>`), which is exactly why it is one list here and the stylesheet
  has a rule for every key in it. `NoteVocabulary::normalise()` is what the
  writer calls, so an unknown key can never reach the column;
- **the three limits** — `TITLE_LIMIT`, `BODY_LIMIT`, `EXCERPT_LIMIT` — read by
  the form, by the validation and by the table cell;
- **`BOARD_LIMIT`** — how many notes the board draws. A desk with ninety notes
  on it is a wall, so the board draws the first 24 and **says so** ("Showing the
  first 24 — the list below holds all 90"). A capped list that does not say it is
  capped is a lie about the pile.

The colours are the only literal colours in `public/assets/css/notes.css`, and
each one declares `--nt-accent` twice: once for the light theme, once for the
dark. A note colour that cannot be seen on the dark surface is not a colour, and
the check fails the sheet if a key is missing either value.

## 5. The writer

`NoteIntake` is the only thing in the application that saves a note. Six methods
— `create`, `update`, `pin`, `fileAway`, `restore`, `delete` — and the
controller never assigns a field, never calls `save()`, and never decides what a
colour is. Four rules live in the class rather than in the controller, so they
hold however the method is called:

- **the owner is written in exactly one line**, `$note->user_id = $owner->id;`,
  inside `create()`. `$owner` is a `User` and not an id on purpose: the caller
  has to have the person, so "whose note is this" is never a number out of a
  URL. `update()` has no owner in its field list, which is what "a note never
  changes hands" means in practice;
- **a colour the vocabulary does not know becomes the default colour**, not an
  unknown string in a column a stylesheet then cannot paint;
- **an empty body is no body**;
- **`array_key_exists`, not `??`** — a field the form sent as empty is a field
  the person cleared, and "cleared" and "not mentioned" are different things.
  This is also the rule behind the two toggles: an unticked pin box sends
  *nothing*, so `pin` takes an explicit boolean, and the controller reads the
  box with `$request->boolean('is_pinned')` rather than trusting `validated` to
  mention it. Without that line a note stays pinned after you unpin it — a bug
  that leaves no trace anywhere.

`fileAway()` and `restore()` are the same column (`now()` and `null`), so
restoring is exact rather than approximate. Filing is never deleting: a filed
note is still readable, still searchable, and one click from being back.

## 6. The readers

### The filters

`NoteFilters` owns the keys, the words and the defaults — and it is handed an
**already-owned** builder: this class narrows what a person may see, it never
decides who that is. A hand-typed `?state=garbage` is the default state, not a
query that quietly returns nothing.

Two criteria live on the chip bar — the note's state (On the desk, Pinned, Filed
away, Everything) and its colour — and two in the drawer: the period it was
edited in (Any time, Today, This week, This month) and the order the table is
read in. **Nothing is asked twice:** a criterion with a chip of its own is not
printed again in the drawer, and the "Filtered by" strip names only the drawer's
criteria, because what is visible on the page is not repeated underneath it.

The drawer's two chips also carry a rule worth naming: the **chip counts are
counted with the chip's own filter lifted** (`NoteFilters::withoutState()`).
Otherwise "Filed away" reads 0 while you are standing on it, and "Pinned" reads
the number of pinned notes that are filed away — the one number nobody asked
for. A chip's count is the answer to "what would I get if I clicked this".

Every order a reader can choose (`edited`, `created`, `title`) is a scope the
model owns, mapped in `NoteFilters::SORT_SCOPES`; the check reads both ends, so
a select that changes nothing cannot be shipped.

### The figures

`NoteFigures` returns four numbers — how many notes the filters leave, how many
are pinned, how many are on the desk, how many moved this week — out of **one
grouped query**, not one query per number. Four separate counts are four chances
to count a different set; a single row of sums cannot disagree with itself. The
chip counts come out of the same row.

### One query, two readouts

The page builds **one** builder and reads it twice:

```php
$query = $this->filters->apply(Note::query()->ownedBy($owner->id), $filters);

$board = (clone $query)->deskOrder()->limit(NoteVocabulary::BOARD_LIMIT)->get();
$notes = $this->filters->order($query, $filters)->paginate(20)->withQueryString();
```

The board and the table draw **the same rows**; the chips decide which rows, and
the readouts differ in shape, not in content — the board is the sticky you think
in front of, the table is the pile you work through. The board always keeps its
own order (pinned first, then last edited) because that is what a board *is*;
the table takes the order the reader chose. The one asymmetry is deliberate and
stated on the page: the board is capped at 24 and says so, the table is
paginated and shows its range.

## 7. The page

`resources/views/notes/index.blade.php`:

1. **four figures** (the shared `.master-stats`);
2. **the composer** — title, colour, the words, a pin box, one button. Quick
   capture is the first thing on the page because that is the moment a note is
   worth writing;
3. **search and filter** — the chips, the search box, the filter drawer, the
   applied strip. The chips are links, so the search form carries the chips it
   does not own as hidden fields: without them, typing a search silently drops
   the state and the colour the reader had chosen;
4. **the board** — sticky cards, 240px minimum, auto-filled. Each card is one
   link to the note's page and holds **no control**: a button inside a link is
   two controls for one click, and the board is the place you *read* your desk.
   The card prints the body with its line breaks kept (`nl2br` after `e()`), the
   way the invoice record prints a line's description;
5. **the table** — the same rows with the colour, the state, when it was last
   edited, and the working controls: Open, Pin/Unpin, File away/Restore. Rows
   become labelled cards on a phone (`.ui-mobile-cards` + `data-label`).

Both `pin` and `archive` are **one URL with two directions**: the button's label
and the note's own state decide which way it goes, so there is nothing to keep in
step. Deleting is not on the page at all — it is on the note's own page, where
the button is not a mis-click away from a sticky you were only reading.

`resources/views/notes/edit.blade.php` is the note's page: the words in a form,
a facts card (colour, state, written, last edited) and the three things that can
happen to it — pin, file away, delete (behind `data-confirm`).

## 8. What was deliberately not built

- **Sharing, mentions, assignments.** A note that can be handed to somebody is a
  task, and the office already has tasks.
- **Attachments.** There is a document archive; a sticky note that grows files is
  a different module.
- **Reminders and notifications.** A note is a reminder. The office's briefings
  and the task list are where a *time* is attached to work.
- **Tags, folders, nesting, checklists.** Six colours and a search box are the
  whole filing system, and the search reads both the title and the body.
- **An office view of everybody's notes.** Considered and rejected — see §2.
- **Rich text.** Plain words, line breaks kept. A note that can hold a table is a
  note that takes a minute to write, and the point was the ten-second one.

## 9. Where it sits in the shell

The routes are the only screen in the application that is neither the office's
nor a person's record, so they sit in `routes/web.php` inside `auth` and
**outside** both groups:

- outside `office`, because that middleware turns an employee around at the door
  and an employee keeps notes too;
- outside `/my`, because `/my` is one person's record — salary, documents — and
  this is whoever is signed in, an administrator included.

The sidebar carries it twice: in the employee's own menu (`My Workspace`) and in
the office's, under a `Personal` section. `layouts/app.blade.php` also maps
`notes.*` to `data-ui-module="notes"`, like every other module.

## 10. The guard rails

`tools/checks/notes-check.cjs` — 52 checks, dependency-free, run with
`node tools/checks/notes-check.cjs`. It reads source with comments stripped, so
the prose in these files can explain a rule without answering a check. What it
holds:

- the reader: the cascading owner column, the `1 = 0` branch, every builder in
  the controller owned, the single row door, five call sites, no `Note` type-hint,
  the notes group outside the office group, no `{user}` route, no view that names
  an owner;
- the writer: one saver, the owner written once from a `User`, the colour
  normalised, the empty body, the unticked pin box, `array_key_exists`;
- the vocabulary: a `.nt-note--<key>` rule, a `.nt-dot--<key>` rule and a dark
  value for every colour, the default in the list, no view re-listing colours;
- one query twice: the clone, the board limit and the sentence that admits it,
  the figures cloned and de-ordered, the lifted chip counts, the sort map against
  the model's scopes, the state words against the query, the date fallbacks;
- the page: the shared shell classes, the shared drawer, a criterion asked once,
  the chips carried through a search, a form for every write, the card with no
  control inside it, `nl2br(e(…))`, the empty state's way out, the mobile cards;
- the shell and the paperwork: both menus, the module mapping, all seven routes,
  the drawer contract list, this document, and the README.

`tests/Unit/NotesRulesTest.php` is the other half, and it is the half that
answers the two questions source reading cannot: `Note::query()->ownedBy(null)`
turns into `1 = 0` (a session with no person in it lists nothing, not
everything), and a hand-typed `?state=garbage&colour=orange` is the default view
rather than an empty list or a 500. It reads queries as SQL (`toSql()` +
`getBindings()`) and never touches the database, like
`tests/Unit/CashflowFiltersTest.php`.

Also updated by this module: `tools/checks/ui-components-check.cjs` (the notes
index joins its shared-drawer contract list) and
`docs/employee-workspace.md` (the employee's menu is no longer four items).

## 11. Where it lives

```
database/migrations/2026_10_07_120000_create_notes_table.php
app/Models/Note.php
app/Services/NoteVocabulary.php
app/Services/NoteFilters.php
app/Services/NoteFigures.php
app/Services/NoteIntake.php
app/Http/Controllers/NoteController.php
resources/views/notes/index.blade.php
resources/views/notes/edit.blade.php
resources/views/notes/partials/note-card.blade.php
public/assets/css/notes.css
tools/checks/notes-check.cjs
tests/Unit/NotesRulesTest.php
```

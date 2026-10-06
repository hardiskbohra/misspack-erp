# The projects module

A project is the office's record of an order being made: the client it belongs
to, the products and milestones it is built from, the money that moves against
it, and the feedback that closes it. This file is the design of record for the
screens that list and open it. `tools/checks/projects-check.cjs` is its guard,
run with `node tools/checks/projects-check.cjs` before any commit that touches
`resources/views/projects/`, `public/assets/css/projects.css`,
`public/assets/js/projects.js`, or the controller's `index()` and `show()`.

## The list is the shared master-list

`resources/views/projects/index.blade.php` used to be the last list in the ERP
carrying its own composition: a `.projects-page` wrapper, a gradient
`.projects-hero`, four `.projects-stat-card` tiles, its own
`.projects-filter-card`, its own `.projects-modal`, its own money colours. That
copy is why the module never looked like the ledger, the vendors or the clients
next door, and it is gone. The page is now:

```
.project.project-index.master-list
├── .master-stats                 four .master-stat--flat tiles
├── section.master-card           the chips, the search, the drawer  (aria-label="Search and filter projects")
└── section.master-card.master-table-card   the records             (aria-label="Project records")
```

- **The rhythm is the shell's.** The 24px between the two cards is
  `.master-list > .master-card + .master-card` in `master-list.css`. The module
  sheet declares no margin, padding or surface for `.master-card`,
  `.master-list`, `.master-stat`, `.master-table`, `.master-list-chip` or
  `.master-modal` — a second definition of the gap is how the two drift.
- **The criteria live in one place.** Status and health are quick chips in the
  card's bar; the search sits in the GET form; everything else (status, health,
  client) is in the `projectFiltersDrawer`, opened by the shared
  `<x-filter-trigger>`. A chip changes only what it owns and carries the rest
  of the query with it, so the header's `Applied filters` strip can remove any
  one criterion without dropping the others.
- **One row is one project.** `Project` cell (name, number, a priority chip
  when the priority is worth saying), client, stage with its progress bar,
  owner, target date (red once it is late and the project is still open),
  value with what is outstanding under it, status and health badges, and one
  `Action` menu — open, quick details, edit, delete. The rows are
  `.project-row.is-clickable` with `data-href`, so the row is the door and the
  inner links keep their own click.
- **The table is the shared table.** `data-table-settings data-table-key="projects"`
  opts it into the column chooser, the density group is the shell's
  `.master-list-density-btn` markup, and `MasterList.density({ root: '.project-index' })`
  binds it to `misspack.projects.density`.
- **The dialog is the shared dialog.** Quick create is one `.master-modal`
  opened through `window.MasterModal`; the module's own `projects-modal`
  wiring is deleted. Close, backdrop, Escape and the scroll lock are the
  shared layer's job.

## The record page

`resources/views/projects/show.blade.php` used to be a copy of the sheet too: a
`.pd-page` with a gradient hero, a `pd-status-card` whose status form was always
open, a five-box `pd-metrics` row, and a **button strip** of ten tabs whose panels
were all rendered on every request — the active one was a `localStorage` value, so
no panel could be linked to, opened in a second window, or reached by the back
button. It is now the shared record page:

```
.project.project-show.master-list
├── header.master-card.master-header.project-record-header   identity, state, four actions
├── .project-attention            only when the target date has passed
├── .master-stats                 four .master-stat--flat figures
└── .master-tabs-card
    ├── nav.master-tabs            ten links, each ?tab=key, each with its count
    └── .master-tabs-panels        one panel per request
```

- **The tabs are links.** `SHOW_TABS` in `ProjectController` is the one list —
  the view draws the strip from it and the controller validates `?tab=` against
  it (an unknown tab lands on the overview). Because every sub-action redirects
  `back()`, adding a product, a milestone, a payment or a comment returns to the
  tab it was posted from; and a panel can be shared, bookmarked, or opened in a
  new window. The old `initTabs` handler and the ten-panel render are gone.
- **The panels are the shell's.** Each is a `section.master-tab-panel` holding
  `master-card master-card--flat` cards laid out by `.project-blocks` (the
  guideline's 16px between cards), and each panel is *well formed*: the wrapper
  is its only child, and the checks walk the tag stack because the framework
  never reads the markup — a wrapper closed one line early puts every card after
  it outside the grid and nothing complains. Facts are `master-facts`/`master-info`,
  empty blocks are `master-empty-state`, tables are `master-table` inside
  `master-table-wrap` with scoped headers, and dialogs are the shared
  `.master-modal`, closed through `[data-close-modal]`.
- **The status form is the shared drawer.** `<x-drawer id="projectStatusDrawer">`
  carries the four fields (`status`, `stage`, `health`, `progress_percent`) that
  `updateStatus()` validates and posts to `projects.status.update` — the header
  keeps one action instead of a permanently open form.
- **Update URLs come from the server.** Each edit dialog carries
  `data-update-url="{{ route(...) }}"` with an `__ID__` placeholder and
  `projects.js` binds it through `bindUpdateUrl()`: a hand-built
  `/project-products/12` misses an install served from a sub-path. A generic
  `[data-modal-open="id"]` binder opens the same dialog from a second door (an
  empty state), so an empty table is never a dead end.
- **One colour vocabulary.** The `.status-*` and `.health-*` tones are scoped to
  `.project` and shared by both pages, so a status reads the same in the list, in
  the record header, in a milestone badge and in the log.
- **The feedback tab is a record panel, not a copy of the feedback module.** The
  ask is issued from here (one live link per kind, refused while it is open), the
  answers are read here, and every door leads into `feedback.*` — that is where the
  queue lives. Its chrome is this page's vocabulary like the other nine panels:
  `feedback.css` (`.fb-*`) is only loaded by the feedback pages, so a class from
  that sheet landing here would arrive with no rules at all.

The milestones tab keeps its own `pmile-*` stepper — a genuinely bespoke timeline
— but its chrome is the shell's now: the five-box stat row is a `master-stats`
row, its action bar is a `master-card`, its badges are `master-badge` tones, and
its empty block is the shared one.

## How the sheet is scoped

`.project-index` on the list and `.project-show` on the record are the two
scopes; `.project` carries the one thing they must agree on — the status, health
and band tones. Nothing else in the sheet is a page scope, which is why the
colour vocabulary is the only rule group whose selector is not the page's.

The form is the third: its root is `.project-form-page`, the name the sheet
scopes the form's own stack (18px between its cards), its required marker and
its help text to. A root that spells another name leaves those rules unread —
the form's cards sit flush and the asterisks go grey — so the root class is a
fact the check reads, not a decoration.

## One writer per fact

| Fact | Written by | Read by |
| --- | --- | --- |
| how many projects are in each status / health | `ProjectController::index()`'s two grouped queries | the left "figures" row, the status/health chips, the filter drawer |
| what a project is worth and what has been received | `Project::paymentTotals()` (`payments` + `cashflowEntries`) | the Value cell, the quick-details drawer, the project page |
| what a row's relations are | the list's `with([...])` | the row, the drawer — `paymentTotals()` reuses the eager load instead of querying per project |

The four figures are derived from the grouped counts (`total` is their sum,
`waiting` is client + vendor), so a chip's tally and a tile's number can never
disagree, and the list pays for two grouped queries instead of four
`COUNT(*)` round trips. The list paginates at 25 with `withQueryString()`, the
module's size, so a chip click keeps its page context.

## What the module sheet owns

`public/assets/css/projects.css` is loaded by the office list, the project form,
the tabbed project page and the milestones tab. It owns:

- the domain's colour — `.status-*`, `.health-*` and `.band-*` tones, the
  priority chip, the late date, and their `:root[data-theme="dark"]`
  counterparts. The state table is one row per meaning, so a word is the same
  colour wherever the record prints it: the band words come from
  `FeedbackVocabulary`, the follow-up badge borrows the table's own *somebody
  else has to act* (amber) and *stopped* (red) rows rather than inventing two;
- the list's own cells (`.project-table-name`, `-meta`, `-stage`, `-progress`,
  `-date`, `.project-col-actions`), every one of them scoped under
  `.project-index`;
- the client portal's project cards (`.projects-project-card`, `-mini-grid`,
  `-progress`, `-chip*`, `-top-bar`, `-number`, `-meta-row`, `-footer`), which
  `resources/views/client_portal/projects/index.blade.php` still renders and
  which must not be deleted with the office list.
- the record page's own compositions — the header, the two-column grid, the
  thread rows, the progress track, and the feedback tab's
  `.project-feedback-*` (the link row, the score line, the verbatim note, the
  issue form), every one of them built out of shell classes;
- **nothing else.** A rule that names a `master-*` / `core-*` class must also
  name one of this module's scopes — `.project`, `.project-index`,
  `.project-show`, `.project-form-page`, `.pd-page`, a `pmile-*`/`pf-*` class,
  or the milestone modal's id. A bare `.master-field input` (`padding: 10px
  11px`) used to beat the shell's `.master-input` (`padding: 10px 14px`) on
  every projects screen, so the same control was a different size here than
  everywhere else; the legacy page's field geometry and the form's label
  colours are scoped to their own pages now, and the checks refuse a rule that
  places a shell class on its own.

Two screens are still outside this file's scope: the form
(`projects/form.blade.php`, `pf-*`) and the standalone client link
(`projects/public.blade.php`, which is the client portal's branding, not the
office's shell). Everything the record page renders is in scope — all ten
panels are a `master-tab-panel` of shell cards — and the milestones sheet section keeps
the stepper's own vocabulary (`pmile-step-*`, `pmile-current-*`,
`pmile-product-head`, `pmile-head-actions`): a timeline drawn as a strip of
connected steps is something no shared class describes. Those rules stay: the
client portal's copy of the same block
(`client_portal/projects/partials/milestone-product-block.blade.php`), the
portal's own sheet, and the static preview under `public/_preview/` still render
`pmile-*`, so nothing in that section is deleted with the office page.

## Checks

`tools/checks/projects-check.cjs` (52 checks) pins the composition above: the
root is the master-list, the two cards sit in that order, the gap is not
declared in the module's sheet, every chip carries its tally, the columns are
named in the order the table draws them, the figures come from the grouped
rows, the row's relations are eager-loaded and `paymentTotals()` reuses them,
the row says it is a link, the module sheet declares no shell class and keeps
every portal class, the dialog is the shared modal opened through
`MasterModal`, and every route the screen links to is registered — and the
record page's half: the shared shell and tab strip, `SHOW_TABS` as the one list,
one panel per request, the drawer form, the server-emitted update URLs, the
shared facts, tables and empty states, a tone for every state the models offer
in both themes (the bands come from `FeedbackVocabulary`, the initials from the
client pages' idiom rather than a `Str::of()` chain), and the feedback tab as a
record panel that owns its own wrapper and wears no class from another sheet.
The last check is the vocabulary itself: every class a module screen names is
defined — by a rule in one of the app's sheets or by another screen — so a tag's
private vocabulary cannot come back without a rule to render it.

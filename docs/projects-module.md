# The projects module

A project is the office's record of an order being made: the client it belongs
to, the products and milestones it is built from, the money that moves against
it, and the feedback that closes it. This file is the design of record for the
screens that list and open it. `tools/checks/projects-check.cjs` is its guard,
run with `node tools/checks/projects-check.cjs` before any commit that touches
`resources/views/projects/`, `public/assets/css/projects.css`,
`public/assets/js/projects.js` or `ProjectController::index()`.

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

- the domain's colour — `.status-*` and `.health-*` tones, the priority chip,
  the late date, and their `:root[data-theme="dark"]` counterparts;
- the list's own cells (`.project-table-name`, `-meta`, `-stage`, `-progress`,
  `-date`, `.project-col-actions`), every one of them scoped under
  `.project-index`;
- the client portal's project cards (`.projects-project-card`, `-mini-grid`,
  `-progress`, `-chip*`, `-top-bar`, `-number`, `-meta-row`, `-footer`), which
  `resources/views/client_portal/projects/index.blade.php` still renders and
  which must not be deleted with the office list.

The module's other screens are outside this file's scope: the form
(`projects/form.blade.php`, `pf-*`), the tabbed record page
(`projects/show.blade.php`, `pd-*` and the milestones tab, `pmile-*`), and the
standalone client link (`projects/public.blade.php`).

## Checks

`tools/checks/projects-check.cjs` (29 checks) pins the composition above: the
root is the master-list, the two cards sit in that order, the gap is not
declared in the module's sheet, every chip carries its tally, the columns are
named in the order the table draws them, the figures come from the grouped
rows, the row's relations are eager-loaded and `paymentTotals()` reuses them,
the row says it is a link, the module sheet declares no shell class and keeps
every portal class, the dialog is the shared modal opened through
`MasterModal`, and every route the screen links to is registered.

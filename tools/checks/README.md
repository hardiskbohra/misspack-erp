# Design checks

Small, dependency-free checks for the things that are easy to break and hard to
see: CSS that lands in the wrong order, a rule that flattens a badge, a control
that grows into empty space, a label that cannot fit its stock.

They are **not** a test suite for business logic — they are the guard rails for
the shared UI (`master-*.css`, the record pages, the shipping mark). Run them
after touching a shared stylesheet, a record view, or a print document.

```bash
node tools/checks/design-check.cjs    # stylesheets, cascade, design rules
node tools/checks/blade-check.cjs     # templates parse: directives, includes, components
node tools/checks/mark-check.cjs      # shipping-mark geometry + QR payloads
node tools/checks/list-check.cjs      # one index page: chips, columns, toolbar, row actions,
                                      # applied filters, density, the pinned grid
node tools/checks/ui-components-check.cjs # shared Drawer/DataTable API, persistence, a11y, and inventory
node tools/checks/status-check.cjs    # the rules a shipment status change keeps: the delivery
                                      # date it fills in, the hold/delay reason it still refuses
node tools/checks/cost-check.cjs      # a cost head's exchange rate: the field is always on
                                      # screen, and the INR value is amount × rate, never a guess
node tools/checks/php-check.cjs       # the PHP files the module owns: parsed with php-parser when
                                      # it is reachable, plus name/import guards that a parser
                                      # cannot answer. PHP_PARSER_PATH=<dir> points at a node_modules
                                      # that holds php-parser when it is not installed here.
node tools/checks/docs-check.cjs      # the paperwork behind an entry: one table, the archive route
                                      # before the resource route, one definition of "missing"
node tools/checks/statement-check.cjs # a party statement: opening + debit − credit = closing, the
                                      # ageing adds up to the closing, and an expiring link is real
node tools/checks/pdf-documents-check.cjs # shared PDF paper/actions, A4 typography, currency/tax
                                          # labels, reports, shipment docs, and label geometry
node tools/checks/report-check.cjs    # the report builder: a cell sums the same rows it opens,
                                      # a bucket is dates and not a dialect, a comparison is the
                                      # axis shifted, and "not set" is a row you can drill
node tools/checks/feedback-check.cjs  # the feedback module: the link is the whole of the
                                      # authentication, one ask has one answer, the band is
                                      # computed rather than stored, a detractor is never a dead
                                      # end, consent is checked where the words are used, a score
                                      # gates nothing, and the screen and the CSV are one query
node tools/checks/projects-check.cjs  # the projects list is the shared master-list: two flat
                                      # cards with the shell's own gap, chips and a drawer for
                                      # the criteria, one grouped query behind the figures and
                                      # the chip tallies, the row's relations eager-loaded, and
                                      # the module sheet owning colour and columns — not the
                                      # shell — while the client portal's classes stay put — and
                                      # the record page: the shared shell and tab links, one list
                                      # of tab names behind both the strip and the URL, one panel
                                      # per request, the status form in the shared drawer, update
                                      # URLs the server writes, the shell's facts, tables and empty
                                      # states, well-formed panels, a tone for every state the
                                      # models offer — light and dark, the feedback tab drawn as
                                      # a record panel instead of borrowing another sheet's
                                      # classes, and the record mark's initials in the house idiom
node tools/checks/employees-check.cjs # the employee side of the user module: the office door is
                                      # on the whole admin group, no personal route takes a user id,
                                      # a file is ownership-checked before it is served, a draft
                                      # payslip is invisible and a verified paper is not removable
node tools/checks/invoices-check.cjs  # the invoice module: its sheet defines no shared class, one conversion per proforma, sales and potential revenue apart, one
                                      # rule for the money and one for how late it is, the figures
                                      # are one aggregate, a receipt is a ledger line, the vocabulary
                                      # routes come before the resource route — and the collections
                                      # half: a reminder is a log (never a counter beside it), the
                                      # sweep only deletes drafts, and the GST file is the screen's
                                      # rows grouped by HSN and rate — and the record
                                      # page: the shared composition, five flat
                                      # figures, a sentence where a card is empty
```

Every check is dependency-free. `mark-check.cjs` additionally decodes the QR
payloads with [`jsqr`](https://www.npmjs.com/package/jsqr) when it is installed
(`npm i jsqr`); without it, the geometry checks still run and the decode checks
report as skipped.

The files use the `.cjs` extension because `package.json` declares
`"type": "module"` for the Vite front end.

All of them exit non-zero on failure, so they can be wired into CI or a
pre-push hook:

```bash
for c in design blade php mark list ui-components status cost docs statement pdf-documents report projects employees invoices; do
  node "tools/checks/$c-check.cjs" || exit 1
done
```

### What the checkers cannot see

These files *read* the code. Two bugs shipped that a reader could not catch — an
empty search box that searched the ledger for the word `all`, and the same
sentinel re-applied on the way into the query — because both were about what the
code *does* when it runs. That question is answered by the test suite, where a
PHP runtime exists:

```bash
php artisan test --filter=CashflowFiltersTest   # what an unfiltered ledger does
```

There is no CI in this repository and no PHP runtime in the sandbox, so the
checkers are the gate: they are run by hand before every commit, and each one is
paired with a mutator (a plausible regression written into the tree) to prove the
guards actually fail when the behaviour they describe is broken.

## What they cover

| Check | Why it exists |
| --- | --- |
| Stylesheet wiring + load order | `master-detail.css` has to load after `master-form.css` and before `master-flat.css`, or the cascade silently wins the wrong way |
| Cascade resolution | a rule that ends up *before* the one it is meant to replace is a bug that looks like nothing happened |
| Card titles, facts, empty states | one record-page vocabulary instead of per-module restyling |
| One primary action per card | a delete button should never be the loudest thing on a page |
| Numeric columns aligned | money reads wrong when the digits do not line up |
| Labelled controls | every text control on a record page or modal has a label or `aria-label` |
| Balanced blade directives | an unclosed `@if` in one partial takes the page down at render |
| Directives that actually compile | Blade finds a directive with `/\B@(…)`, so `outstanding@if (…)` stays literal text while its `@endif` compiles — an `endif` with no `if`, and a 500 at render. The balance rule cannot see it: the directives are balanced, they just are not compiled |
| A directive keeps its `(…)` on the same line | only spaces and tabs may sit between the name and its argument list, so `@if` with the condition on the next line compiles as a bare `if:` |
| Resolvable includes/components | a renamed partial or component breaks silently at render time |
| Dark-theme pairs | every semantic hue needs a counterpart on the dark panel |
| No length `flex-basis` on a control in a column flex | that is how the file input became 240px tall |
| No negative margin on a title/hint pair | that is how the paperwork hint overlapped its heading |
| Shipping-mark geometry | adds up the millimetre sizes and fails if the sticker cannot fit the 85 × 130 mm label |
| Address fitting | re-derives the character budget per line from the font and the 77 mm line, reads the threshold numbers out of the partial, and fails if a boundary address would be cut — the last line carries the pin code and country |
| Rule order | the address size-step rules must come after the per-party rules they override, or they silently do nothing (same specificity) |
| QR payload decode | a code that does not decode is not printable |
| Index-page vocabulary | a list row and a list toolbar have one shape across modules: `scope="col"` headers, money in an `.is-num` cell that lines up with the totals, one primary action on the page, every status/type/label chip with a light **and** dark tone |
| A list that keeps its context | the header of a long list stays in view with the totals row, and the reader decides how many rows fit — both are checked, not just styled |
| Row linking | the row opens the record and the inner links/buttons keep their own click; the guard lives in JS and is checked, because a half-linked row is worse than none |
| Toolbar honesty | a Reset appears only when a filter is set, the empty state offers a way out, and the closed block says how many rows it holds |
| Applied filters | one removable chip per active filter, each removing only its own key — a filter you cannot see is a filter you cannot undo |
| Row density | the list remembers how tight the rows are, and both densities are checked to really change the row geometry |
| A pinned grid | above 1200px the header and the totals row stay put while the rows scroll, and the borders are separated so a sticky header keeps its hairline |
| Stacked-row labels | on a phone the row becomes a card and every value keeps the column name it had |
| One row rhythm | every first line and every second line shares a line box, so a row reads as a row instead of a pile of boxes |
| A spanning table cell | a cell turned into a flex or grid box leaves the table layout, and `colspan` quietly stops spanning — the closed-divider row and the users empty state both shipped that way. Outside a card breakpoint no cell may be flexed at all; inside one it has to exclude `[colspan]` |
| A row menu inside a scrolling table | a panel in a table cell paints in that row's pass (so the rows after it draw over it), is clipped by the scroll box above it, and is trapped by any ancestor that creates a stacking context — a sticky cell, an opacity group — whatever z-index it asks for. The open panel is therefore moved into `<body>`, placed against the viewport, kept opaque, and put back where it came from on close; no sheet may re-declare the menu after `master-index.css` owns it |
| Translucent rows | `opacity` on a row or a cell makes a stacking context and swallows the row's own menu — muting a finished row belongs in colour, not in alpha |
| One row-menu item shape | every item in a row menu is an icon slot plus a label from the same left edge, and the panel owns that shape — a `.master-btn` dropped inside a menu wears its own background, centres itself and lines up with nothing (the shipments menu shipped a centred, icon-less "Public Link" next to six left-aligned siblings). No template may put button styling in a row menu, and every item must carry an icon |
| Themed row menu | the shared kebab menu is drawn on the card colour, so it must not paint fixed light-theme values — on the dark card they are invisible |
| Delivered means dated | a shipment marked delivered gets a delivery date — today when none was given — and the date is filled, never overwritten, on every path that writes a status: the edit form, the tracking history form, a shipment created delivered, and a status derived from a history edit. The office's timezone (not UTC) decides what "today" is |
| Still refused, on purpose | a hold or a delay with no reason, and a delivery date that precedes its pickup — both are data errors, not empty fields, so they keep their validation error |
| A date the form shows | the history form offers the delivery date only when the status can use it, prefilled with today and cleared again only if the script filled it — a value already on the shipment is never touched |
| A field that is always there | the exchange rate on a cost head used to appear and disappear with the currency select, which is how it went missing exactly when a foreign bill was being entered — and the operator was then refused with nowhere to type the number. It is always on screen and always editable now: it starts from the rate the currency was last billed at (1 on the base currency) and the operator types the rate this bill was raised at, with the INR value it will freeze shown while they type |
| Money converted once | the INR value of a cost is amount × rate, computed in one place; a foreign row without a rate is refused rather than quietly converted at 1:1, and a rupee row is stored at 1 so a rate left over from another currency can never multiply it |
| Rupees are written in ₹ | the reader sees money as `₹ 1,20,000.00`, never as the code `INR` in front of a figure — not in a view, not in a script, not in a placeholder or a rate hint. `INR` may still *name* the currency (a select option, a comparison, a column saying which currency a figure is in), but the symbol itself lives in exactly one place and every rupee figure goes through it. A non-rupee amount keeps its own code, because a ₹ in front of a dollar figure would state the wrong amount |
| Totals in one currency | a spend total is the sum of the rupee values the ledger already recorded at the rate of the day (never a re-conversion, never two currencies added as bare figures), so the list's Spent card and its footer both read as one `₹` figure; a shipment still on the old single figure counts once it is billed in rupees |
| One money format | every amount is written by one formatter: the rupee sign with lakh/crore grouping (`₹1,50,000`, never `₹ 150,000.00`), paise only when the amount really has them, and the code with thousand grouping for anything that is not rupees (`USD 2,400.00` — a ₹ in front of a dollar figure would state the wrong amount). The grouping rule is the same expression in the server helper and in the browser formatter, and the browser's half is run against fixtures |
| A figure and its drill are one definition | a report cell is summed over a window and opened over the same window, through the same filter vocabulary — if the two ever disagree, the accountant re-adds the report by hand, which is the manual job the report exists to delete |
| Buckets are dates, not a dialect | the dashboard's `GROUP BY DATE_FORMAT(...)` is MySQL-only; the report computes its buckets in PHP because each cell's drill link needs that bucket's exact first and last day anyway |
| A comparison is the axis shifted | comparing April with March inside an April–June report counts March twice — once in its own column and once as April's comparison |
| "Not set" is a row you can open | the entries nobody was named against are the ones people ask about, and a row that cannot be drilled is a row nobody trusts |
| A mixed range says so | rupees, dollars and yuan are never dressed as one currency: the figures lose their sign and the page names the currencies it holds |
| The money is one rule | an invoice's received figure is the opening amount plus the ledger lines linked to it, expressed once (`SalesInvoice::RECEIVED_SQL` / `receivedAmount()`) for the row, the chips, the figures, the filter and the CSV. A view that adds money up is a second definition of it, and the client's statement is printed from the first |
| One definition of "late" | `ageingBuckets()` is the model's list and the filter's arms are the same buckets, so a chip saying *31-60 days late* opens the rows that are in that bucket — a second spelling of "overdue" is how the list and the statement started disagreeing |
| An aggregate alias is read off the row | `Builder::value('received')` replaces the select list with the alias, which is a column that does not exist — the figures and the ledger sum are fetched with `first()?->received` |
| A receipt is a ledger line | recording a payment writes a credit `CashflowEntry` linked to the invoice and re-derives the stored balance from it; the ledger is where the bank line is reconciled, and the invoice list, the client statement and the payment filter all read the money from there |
| The module's sheet defines no shared class | the invoice stylesheet redefined twenty-six `master-*` classes with module-tuned values, which is why the module never matched the ledger next door — a `master-*` rule in a module sheet overrides the design system for every page that loads it |
| A statement adds up | opening + debit − credit = closing, the ageing buckets sum to the closing, and an expiring public link is a real route — not a paragraph promising one |
| Money never hand-built | a template may not format money itself, and may never echo a currency and then a rupee figure (`USD ₹1,200`) — the currency belongs with the amount it is in, through the formatter. Only an exchange rate keeps its own decimals in a template |
| The office door | an employee account is turned around at the door of every screen the office owns — by a middleware on the whole admin group, not by a check on one page — and lands on its own workspace with a sentence instead of a 403 |
| No user id in a personal URL | `/my/...` reads the person from the session and never from the address bar, so there is no number to change and no record to reach for; the two routes that do take a row id are ownership-checked before a byte is served |
| A draft is not a payslip yet | an office closing a month drafts a slip before the money moves, and the employee cannot see it until it is issued; the figures are the record and the PDF is optional, because the office that pays by transfer has none |
| A verified paper stays put | once the office has marked a document as seen, the person who uploaded it can no longer remove or quietly replace it — that difference is the whole value of the file at an audit |
| Two owners for one profile | the employee keeps their mobile, address, date of birth and emergency contact current; designation, joining date, pay and the salary bank account are the office's record, and the list of who may edit what is written once in `EmployeeAccess` |
| Roles read as "not an employee" | any unknown or missing role keeps the account able to work, because an office locked out of its own ledger by a missing value is the worse failure — and the last administrator cannot be demoted or deleted |
| One payroll list | the ledger's Employee field offers employees first and office accounts underneath, from one method on the model — entries filed before roles existed still point at people the picker contains |
| A tab is a URL | the project record rendered all ten panels on every request and kept the active one in `localStorage`, so no panel could be linked, bookmarked, opened in a second window, or reached with the back button. One panel is rendered per request now, `?tab=` is validated against the controller's own list of names, and a form posted from a tab returns to that tab because every sub-action redirects `back()` |
| The server writes the URL | each edit dialog carries `data-update-url="{{ route(...) }}"` with an `__ID__` placeholder, bound by one script helper: a hand-built `/project-products/12` misses an install served from a sub-path, and a renamed route is a 404 in whichever of the two places was forgotten |
| One vocabulary, two pages | the status and health tones are scoped to `.project` and shared by the list, the record header, a milestone badge and the log — so a status cannot mean one thing on the list and another on the page it opens |
| The record page speaks the shell | the record page's sheet may place the shell's classes and no more: it composes the header, the two-column grid and the thread rows, while the cards, facts, tables, badges, empty states and dialogs come from the shared sheets. A second card design on the detail page is how the module stopped matching the list next door |
| A panel closes as it opens | the tag stack is the only reader a Blade panel has: a wrapper closed one line early moves every card after it out of the grid, and the browser silently recovers — so the checks walk the panel's tags instead of trusting the eye |
| One writer for a control | a module sheet may place a shared class only inside that module's own scopes: a bare `.master-field input` (`padding: 10px 11px`) beat the shell's `.master-input` (`10px 14px`) on every projects screen, so the same control was a different size on one module's pages — the legacy page's field rules and the form's label colours are scoped to their own pages, and the check refuses a new rule that places a shell class on its own |
| A string is not a list | `Str::of()` hands back a Stringable — a string that answers to string methods and to nothing else — so `->map()`, `->filter()` or `->each()` on that chain compiles, passes every check that reads a template, and throws `BadMethodCallException` the first time a browser opens the page it is on. The record mark's initials did exactly that. The house idiom is to collect the parts (`collect(explode(...))->map(...)->implode('')`), and no `Str::of()` chain may call a collection method |
| A string is not an object | the mirror rule: every other `Str::*` static returns a string, and so do `str_*`, `mb_*`, `trim`, `explode`, `implode`, `number_format` and friends — a `->method()` chained onto one of those is a fatal error the moment the page renders, not a clever way to compose text |
| A panel another sheet owns | the feedback tab renders inside the project record, which loads `projects.css` and never `feedback.css`: a class from another module's sheet is a class with no rules at all, so the tab is built from the record's vocabulary, owns its own `master-tab-panel` wrapper, and reads its band tones from the same state table as every other badge on the page |

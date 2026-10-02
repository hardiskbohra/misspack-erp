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
node tools/checks/status-check.cjs    # the rules a shipment status change keeps: the delivery
                                      # date it fills in, the hold/delay reason it still refuses
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
node tools/checks/design-check.cjs && node tools/checks/blade-check.cjs \
  && node tools/checks/mark-check.cjs && node tools/checks/list-check.cjs \
  && node tools/checks/status-check.cjs
```

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
| A row menu inside a scrolling table | a panel in a table cell paints in its row's pass, so the rows after it cover it: the open row is raised, the panel is capped or flipped, and no sheet may re-declare the menu after `master-index.css` owns it |
| Translucent rows | `opacity` on a row or a cell makes a stacking context and swallows the row's own menu — muting a finished row belongs in colour, not in alpha |
| Themed row menu | the shared kebab menu is drawn on the card colour, so it must not paint fixed light-theme values — on the dark card they are invisible |
| Delivered means dated | a shipment marked delivered gets a delivery date — today when none was given — and the date is filled, never overwritten, on every path that writes a status: the edit form, the tracking history form, a shipment created delivered, and a status derived from a history edit. The office's timezone (not UTC) decides what "today" is |
| Still refused, on purpose | a hold or a delay with no reason, and a delivery date that precedes its pickup — both are data errors, not empty fields, so they keep their validation error |
| A date the form shows | the history form offers the delivery date only when the status can use it, prefilled with today and cleared again only if the script filled it — a value already on the shipment is never touched |

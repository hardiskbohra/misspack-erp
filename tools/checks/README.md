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
node tools/checks/list-check.cjs      # one index page: chips, columns, toolbar, row actions
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
  && node tools/checks/mark-check.cjs && node tools/checks/list-check.cjs
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
| Row linking | the row opens the record and the inner links/buttons keep their own click; the guard lives in JS and is checked, because a half-linked row is worse than none |
| Toolbar honesty | a Reset appears only when a filter is set, the empty state offers a way out, and the closed block says how many rows it holds |

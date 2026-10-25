# Searchable Select2 and consistent statement print filters

Acceptance — REQ-UI / REQ-STATEMENT (25 October 2026):
- Every ERP Select2, regardless of option count, exposes working search. Keep one
  shared initializer and existing values, validation, change events, multiple
  selections, opt-outs and dynamic controls intact. Do not edit vendor sources.
- Search works with mouse/keyboard on ordinary forms, modals and right drawers;
  dropdowns remain above overlays, search receives focus, close/Escape/backdrop
  do not leave floating options or lose return focus.
- Statement print filters use shared form/Select2 styling, labelled grouped
  sections, consistent spacing/actions, an actual visible close icon and the
  standard blue ageing switch. No external icon-font dependency for closing.
- Selecting a preset through Select2 clears stale dates; editing dates visibly
  selects Custom. Preserve dates/currency/ageing on GET and Download. Keep print
  controls hidden and A4 figures/layout unchanged.
- Red → Green evidence, full Unit/Integration/Feature, contracts and actual browser
  tests required. Native PHP/MySQL CI checked after commit/push.


## Evidence and scope

- **Test-first Red:** the actual initializer VM passes for0/1/2/8 options but
  fails for9/50 (search configured as Infinity). Actual Chromium also fails on
  the30-option dropdown: no search. New HTTP render test fails on absent shared
  form assets. A later entry-point audit catches the unversioned public-lead
  script before its cache-busting fix. Browser regressions also caught the
  undersized38px close target and a duplicated Select2 wrapper border before
  their fixes.
- **Green:**8 Node unit/policy tests and7 focused statement HTTP tests/79assertions.
  Full portable PHP8.4.25/WASM **492tests/15,387assertions PASS**, including Unit,
  Feature and Integration. Node tests now run in the default contract/CI gate.
- **Real Chromium153:** ordinary and multiple selects, short/30-option lists,
  dynamically injected selects, disabled/native opt-outs, idempotent init, one
  change event, mouse/keyboard selection, search focus, empty results and modal
  cleanup at1440/900/390px in both light/dark. Drawer Tab stays inside; first
  Escape closes only the dropdown; closing the drawer removes body-portaled
  options before restoring opener focus. No browser exceptions.
- **Real statement preview:** shared40–44px controls,1px borders,7px label gap,
  desktop24px/mobile16px drawer inset, visible SVG close with40px target, grouped period/options,
  standard blue/white ageing switch and shared print-action footer at all three
  widths. Select2 presets clear stale dates; manual date edits visibly update
  Custom; currency changes work with Enter; submitted exact dates/currency/
  ageing off survive. Browser assertions wait for rendered results/transitions,
  not arbitrary sleeps. Search typing uses actual key events, not a synthetic
  input-only fill followed by Enter (which omits Select2's keydown lifecycle).
- Also reran actual directory + six dashboard pages (1440/900/390, light/dark,
  real Month/Custom Select2 date controls), statement list chip/search/date
  journeys, and statement print popup/download/filters. All PASS.
- **Actual A4 PDFs:** all-time/custom/vendor one page;73-row long-name/large-amount
  case3pages. Complete figures, issuer logo, three visual address lines, repeated
  ledger headings, once-only final total and every word within12mm margins PASS.
- Source gates: statements75, PDF20, design79, UI24, Notes67, sidebar10. No
  schema, SQL, money calculation, permission or vendor-library changes.
- Native PHP/MySQL CI is checked independently after push and reported with the
  commit. Local PHP execution is not presented as native/MySQL verification.

## Changes kept central

Only `master-selects.js` initializes Select2 across the application. Removed the
option-count condition; retained body dropdown placement, class mirroring,
mutation-based initialization and existing event semantics. Opening focuses the
search; drawer close emits a generic lifecycle event before restoring focus.
Native/public selects without the vendor library remain native. Intentional
`data-no-select2` and disabled controls are not forcibly enhanced.

Preview now loads the existing shared form/Select2 assets and the canonical
standalone design-system partial. Canonical form borders no longer depend on
legacy shell tokens or decorate the Select2 wrapper as a second control; the existing switch gains standalone token fallbacks and
its decorative slider cannot intercept the actual checkbox. Shared drawer close
uses SVG, so an unavailable icon CDN no longer produces an empty close button.
Date preset listeners explicitly support Select2's jQuery change events; date
edits refresh only the selection display, without clearing those dates.

Catalogue UT-UI-004 / IT-UI-005:223designs (97unit/126integration),67PHP-method-linked
designs,50PHPtest files. Node tests are separately executed and not conflated
with those PHP method counts or measured coverage.

## Reproduce

```sh
node --test tools/testing/select2-policy.test.cjs
APP_ENV=testing php tools/testing/render-statement-print.php .work/statement-print
CHROMIUM_EXECUTABLE_PATH=/path/to/chromium node tools/checks/searchable-selects-browser.cjs .work/statement-print
CHROMIUM_EXECUTABLE_PATH=/path/to/chromium node tools/checks/statement-print-browser.cjs .work/statement-print
python3 tools/checks/statement-print-pdf.py .work/statement-print  # PyMuPDF
vendor/bin/phpunit -c phpunit.xml
npm run test:contracts
```

Sandbox: PHP command replaced by`PHP=8.4 /tmp/erp-test-tools/node_modules/.bin/php-wasm-cli`,
Chromium`/tmp/chromium` plus`LD_LIBRARY_PATH=/tmp/al2023/lib`, Playwright via
`NODE_PATH=/tmp/erp-test-tools/node_modules`, PyMuPDF via`PYTHONPATH=/tmp/erp-pdf-tools`.
Generated PDFs/screenshots/HTML/JUnit/logs remain ignored under`.work/`.

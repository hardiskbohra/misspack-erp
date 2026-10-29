# One-point increase for thermal carton labels

## Acceptance criteria — IT-PACK-003 and IT-UI-003

- The packaging-list carton label on **both 100 × 140 mm and 100 × 150 mm**
  stock has one additional typographic point on every existing text size,
  relative to the previous thermal design. This includes the sender heading,
  barcode caption, identity and party keys/values, item headings and values,
  small SKU/serial details, weights and handling text. Party labels, names and
  addresses retain their earlier extra 2 pt; this request adds another point.
  A point is 1/72 inch, not one pixel or one relative `em`.
- Preserve A4/A5 typography and paper geometry; do not change thermal page
  dimensions, padding, sender branding, QR size, borders, physical carton
  numbering, or the shared single/batch label markup and print workflow.
- For realistic single-item labels, including the MissPack logo, QR, two-line
  addresses, all handling marks and a short handling note, the visible text,
  weights and marks must fit the stock. Multi-item labels retain the existing
  deliberate item-band overflow clipping; the larger type may leave fewer item
  lines visible. Arbitrarily long addresses or notes cannot be guaranteed to fit
  fixed-size stock and must not be claimed as exhaustively verified.
- Verify the real rendered Blade with Chromium's print layout and PDFs for both
  sizes and for single/batch output. Check exact computed point sizes against
  the previous design, unchanged A4/A5 typography, physical PDF dimensions and
  page count, QR/logo presence, representative visible text, and absence of
  unexpected clipping in the normal fixture. Use only fake fixture data.

The catalogue IDs above are broader designs with partial PHP method links;
these checks exercise the thermal-label subset and do not claim their entire
coverage.

## Reproducing the print check

`tools/checks/packaging-label-typography-browser.cjs` renders the actual Blade
with the fake models in `tools/testing/packaging-label-fixture.php`, serves only
local assets, prepares the real QR/logo/print script, and inspects computed
sizes, line-box containment, page PDFs and sampled outer borders. It needs the
Composer dependencies, `playwright-core`, Chromium and Python with PyMuPDF.
Set `LABEL_TEST_PHP` if `php` is not on PATH, `CHROMIUM_EXECUTABLE_PATH` to the
browser, and `NODE_PATH`/`PYTHONPATH` if dependencies are in temporary folders.
With no extra font it tests the system fallback. To test Inter without contacting
Google, install `@fontsource/inter` separately and set `LABEL_TEST_INTER_DIR` to
its `files` directory; the script verifies regular and bold actually loaded.
Run:

```sh
node tools/checks/packaging-label-typography-browser.cjs
```

The smaller source-level typography guard runs in `npm run test:contracts`;
it is not a replacement for the browser/PDF test. The Blade fixture uses
in-memory fake data and makes no external requests or production writes.

## Verification — 27 October 2026

- **Red:** before the CSS change, Chromium reported the thermal base as 5.6 pt
  rather than the expected 6.6 pt. The portable guard also fails against both
  the old stylesheet and a naïve base-only 6.6 pt change: `em` descendants
  would not all gain exactly one point.
- PHP 8.4.26: `APP_ENV=testing APP_RUNNING_IN_CONSOLE=true php
  vendor/bin/phpunit -c phpunit.xml --no-progress --display-phpunit-notices`
  passed **642 tests / 16,828 assertions** in 54.1 seconds, with the existing
  single PHPUnit notice about an email transport mock without expectations.
- `npm run test:contracts` passed in 1.7 seconds (291 designed cases, 136
  partially or fully method-linked; 77 PHP test files). Packaging list, label
  batch and print-setting source checks passed **117 / 10 / 9** checks; route
  inventory matched **483** routes. Inventories do not measure runtime coverage.
- Chromium 153 plus PyMuPDF: the real-Blade check passed with both the fallback
  font and offline Inter 5.2.8 (13.2 seconds each, run in parallel). Each run
  covered ten PDF scenarios and 14 pages, plus a base-only typography negative
  control: both thermal stocks, single/group batch output,
  MissPack and vendor branding, a deliberately overflowing item band, and
  unchanged A4/A5 typography and geometry. Normal thermal fixtures include a
  logo, QR, two-line addresses, all five handling symbols and a short note;
  text fits its cells, item details remain visible, and sampled borders print.
  The 30-item fixtures verify that excess rows remain clipped while weights and
  handling survive. A4/A5 are not claimed to fit every possible document.
- **Pre-existing A5 limitation:** the same dense fixture clips the A5 item band
  even with the original stylesheet from before this change. The A5 check
  compares every rendered element's geometry and computed styles with thermal
  rules removed; it does not falsely claim those A5 item lines print. Addressing
  that separate format is outside this thermal-only change.
- The existing A4 packaging-sheet border PDF check also passed portrait and
  landscape with three and 100 rows, including its broken-width negative
  control (4.4 seconds). The normal thermal label screenshots were visually
  inspected in addition to the automated PDF and line-box checks.
- No physical thermal printer was available; output was checked with Chromium
  print PDFs, not a printer/driver. Native MySQL was not rerun locally because
  this change is CSS and test code only. No customer data, network font service
  or production database was used.

# The packaging list

**Status: built.** One document per packing run — `MP/PL/26-27/NNN` — with a
screen, a printed sheet, per-carton labels and structured automatic checks. Its
guard rails are `tools/checks/packaging-lists-check.cjs`; the section arithmetic
below is held by `tests/Unit/PackagingSectionsTest.php`
(`php artisan test --filter=PackagingSectionsTest`).

The data is three tables, because a carton is a real object with a size and a
weight, and the thing inside it is not:

| Table | Holds |
| --- | --- |
| `packaging_lists` | the document: number, date, parties, linked records, totals |
| `packaging_list_cartons` | one **carton block**: a count of cartons, a size, a weight per carton, a heading, handling |
| `packaging_list_items` | what sits in that block: product, quantity per carton, batch, serials |

Everything derived — total quantity, net and gross weight, CBM, chargeable
weight — is computed from those two children, never stored as a second fact.

---

## 1. Two rules the sheet is built on

### A section is a heading, not a product

The sheet that comes back from the supplier is read in sections:

```
        50ml Empty Spray Bottle
 1  50ml Spray Bottle   15   336   5,050   50×34×39   ...
 2  50ml Spray Cap      13   392   5,000   50×34×39   ...

        Silicone Ring Case
 3  Silicone Ring        1  5,000   5,000   42×40×40   ...
```

Bottles and caps are different products under one heading, because the heading
describes **what was packed together**, not what the product is.

The heading is `packaging_list_cartons.packing_group` — typed on the carton
block in the form as **Packing group**. Blocks that carry the same heading print
under one band, in the order they were entered. A block with no heading does not
merge with anything: it is its own section, titled by its carton label and then
by its first item — exactly the sheet it printed before the column existed, so
no list had to be re-entered.

`PackagingList::sections()` is that read. It is **derived on read** — there is no
section table and no `product_group` column to keep in step with the rows.

### A carton can hold two products

A 60 × 40 × 40 box carrying 600 bottles and 5,000 hooks is **one carton** with
**one weight**. It is stored as one carton block with two item lines, so:

- its size, weights and CBM are printed **once and span** its item lines
  (`rowspan`), which is why the sheet shows 14.40 kg beside a merged cell rather
  than beside each product;
- only Qty/Carton and Total Qty repeat per line — those are the per-product
  figures;
- the carton is counted **once** in Cartons and in every weight total.

This is the only honest reading: splitting one carton's weight between the
products inside it would be a guess.

### List navigation and spacing

The list grid owns a single standard 16 px vertical gap between its statistics
and table card; inherited card/stat margins are reset rather than added to the
grid gap. Click a row's non-interactive area to open that packaging list. The
shared `MasterList.rowNavigation` helper leaves links, menus, form controls and
text selection alone. The packaging-number link remains the keyboard-accessible
route to the record. The index loads the module script, which initializes list
behaviour before its editor-only guard.

### Editing many carton groups

The editor keeps one carton group on screen at a time, behind a strip of summary
tabs — a group's packing heading, its carton count and its dimensions, so the
right tab is recognisable without opening it. A list can hold a dozen groups,
which is more tabs than any window shows, so **the strip scrolls inside its
card**: it never wraps (a wrapped strip moves the fields down every time a group
is added) and never widens the page. That second part is not free — the form is
a grid, and a grid item defaults to `min-width: auto`, its min-content width, so
eighteen 210 px tabs used to stretch the card and the whole page past the window
instead of scrolling. `minmax(0, 1fr)` on the form column and `min-width: 0` on
its sections is what lets the strip be narrower than the tabs it holds.

Opening a group — by click, arrow key, or "Add carton group" — scrolls its tab
into view, because a selected tab that sits off-screen is a tab the user cannot
find again.

### Per-list print columns

Create/edit has a **Print columns** section with twelve checkboxes in document
order: Sr. no., Item description, Carton, Qty / carton, Total qty, Carton size
(cm), N.W. / carton, Net weight, G.W. / carton, Gross weight, CBM/carton, Total
CBM. Select at least one and save. This setting belongs to the packaging list,
not to the user or browser; duplication copies it. Existing records (null JSON)
and new records default to the original eleven columns, excluding CBM/carton.

The print view emits only selected headers, cells and totals. Column weights
are normalized to 100% of the selected A4 orientation; section bands span the selected count.
Carton-level values still span all item lines within a shared carton. CBM/carton
is calculated from dimensions, not entered again. Only carton count, total qty,
net weight, gross weight and total CBM are summed in the foot; per-carton values
are never summed. Packing calculations, workflow checks, labels and the packing
summary below the table are unaffected. All twelve columns can be selected,
though fewer columns make a more readable portrait sheet.

Deployment: run `php artisan migrate` to add the nullable `print_columns` JSON
column. Omitted settings in older integrations preserve the existing choice;
an explicitly empty or unknown selection is rejected.

### Continuous right-edge borders in print/PDF

The printed `.pdf-sheet.pl-sheet` is one CSS pixel narrower than the printable
width (`calc(100% - 1px)`). Chromium can round an auto-width sheet onto the
printable-area clip and lose the entire right border: the status badge, facts,
party cards, items and summary all ended at that same clipped coordinate.
This print-only safety inset follows either orientation, preserves the flush
section seams, and does not change other documents or screen layout. It must
override the shared print shell's `width: auto !important`. Regression coverage
should inspect actual PDF output, not just screen computed styles: the borders
were present in computed CSS even when absent from the exported PDF.

`node tools/checks/packaging-print-borders-browser.cjs` is an optional real
Chromium/PDF raster regression (requires `playwright-core`, a Chromium executable
via `CHROMIUM_EXECUTABLE_PATH`, and Python `pymupdf`; temporary installs can use
`NODE_PATH`, `PYTHONPATH`, and any platform library paths). It checks right-edge
pixels at the status, facts, ship-to, item rows, summary and signature in both
orientations, on single- and multi-page documents. A negative control restores
the old auto width and must fail. Generated PDFs are temporary and removed.

### Printing or downloading every carton label

On the packaging-list detail page, **Carton labels → All carton labels** has a
page-type selector (**A4**, **A5**, **100 × 140 mm label**) and one **Print /
download all labels** button. A4/A5 use the existing landscape label designs;
100 × 140 mm uses the existing portrait thermal design. An optional reprint
reason applies to the whole run. Individual group printing remains available.

The CSRF-protected POST opens a single document with one page per **physical
carton**, in stored group order. For groups of 2 and 3 cartons it produces five
labels, numbered 1/5 through 5/5, carrying each group's own contents, weights,
handling marks and QR destination. It does not print just one label per group.
The batch and individual paths share the same label partial and paper rules.

The print dialog opens automatically after QR generation and fonts are ready.
Choose a printer or **Save as PDF** to download one PDF for the entire list;
there is no server-side PDF renderer. A **Print / save PDF** button remains if
the browser blocks automatic printing or the dialog is cancelled. QR failure
prevents automatic printing and displays a visible error. Disable browser
headers/footers and use the selected paper size at 100% scale when printing.

Each group records an audit row with its selected format, physical carton count,
reason, actor and time, in one transaction. These record requested output, not
proof that a printer finished or a PDF was saved. Validation and batch assembly
happen before audit writes. A run is limited to 10,000 physical cartons to bound
the HTML/QR workload; larger lists must use the existing per-group action.
Empty lists and invalid counts are rejected, never silently omitted. No new
migration is needed for batch printing.

### Print-page layout settings

Only `/packaging-lists/{id}/print` offers **Orientation** (A4 portrait or
landscape), **Header text**, **Footer text** and **Show page numbers**. These are
not packing fields and do not appear in the create/edit form. Changes preview
immediately; **Save settings** persists them on that list via a CSRF-protected
PATCH route requiring Operations manage permission. Printing without saving
uses the current preview. Duplicating a list copies its saved print settings.

Existing lists default to portrait, the original footer note, blank header and
page numbers hidden. Header/footer can be cleared deliberately. The print view
places the document's own `@page` rule last in its head; it switches both the
screen sheet and the actual paper orientation, with 12 mm side margins and
16 mm top/bottom margins reserved for running text. Page-margin boxes repeat
the header and footer on every printed page, and optionally print **Page N of
M**, using real `page` / `pages` counters rather than a fixed “Page 1”. The
screen preview shows sample numbering, not a pagination estimate. Running text
is plain text, CSS-escaped server-side and client-side; it cannot inject HTML
or CSS. The body preview copies are suppressed in print to avoid duplicates.

**Browser support:** repeating margin text and counters require a recent Chrome
or Edge with CSS page-margin-box support (Chromium 131+). Firefox/older browsers
may omit them. Disable the browser's own **Headers and footers** in its print
dialog to avoid added URLs/dates. The app cannot control that browser preference.
No other ERP document's paper or page margins are changed.

Deployment also requires `php artisan migrate` for the nullable `print_settings`
JSON column. This presentation-only endpoint does not recreate carton rows,
change status, or bypass packing checks; even frozen packing can change its
print presentation without modifying shipment allocations.

## 2. What the two surfaces show

- **Print** (`packaging_lists/print`) is the sheet the supplier and the customer
  read: Sr. No., Item Description, Carton, Qty/Carton, Total Qty, Carton Size,
  NW/Carton, Net Weight, GW/Carton, Gross Weight, optional CBM/carton, Total CBM — up to twelve columns.
  Volume is shown as a packing-run total by default; per-carton CBM can be
  enabled in Print columns. Item lines are numbered
  continuously across sections, and the foot is the document's own totals — so a
  section can never disagree with the total.
- **It is a document like the invoice, not a separate design.** The sheet loads
  `document-print.css` for the paper, actions, print reset and Inter stack, and
  `packaging-list-print.css` for content only. It repeats the invoice's
  hierarchy in the same order — letterhead, facts strip, party cards, line
  table, summary, sign-off, footer note — with the same 12 mm margins, 8 px
  card radius, 9.5 px uppercase field labels and 12 px table values, all driven
  off the shared `--pdf-*` tokens. A customer receiving an invoice and a packing
  list sees one house style, not two printouts from two systems.
- **It is black and white, and dark enough to print.** Every word on the sheet
  is pure `#000` — headings, labels, values and the footnote alike — and the
  rules are `#6b7280` (card and table outlines) and `#b6bcc7` (row dividers)
  instead of the screen's near-invisible `#dfe5ef`. An office laser renders the
  screen values as nothing, so hierarchy is carried by size, weight,
  letter-spacing and case rather than by grey: a 9.5 px uppercase label reads as
  secondary next to a 12 px value even in the same black. The only light fills
  left are the band backgrounds, and nothing stops being readable if a printer
  drops them. Dimensions print as `50 × 34 × 30` rather than
  `50.00 × 34.00 × 30.00`.
- **A4 portrait by default, with landscape selectable on the print page.**
  A final document-local page rule overrides the shared default only for this
  printout. Side margins stay 12 mm (186 mm portrait / 273 mm landscape);
  top/bottom margins reserve 16 mm for running text and page numbers. The
  eleven-column default retains its original relative weights: Carton Size
  14%, per-carton weights 7.5% each, and description 21.5%. Selecting different
  columns normalizes their weights to the available width. Table padding is
  6 px vertically / 5 px horizontally and body text remains 12 px.
- **The letterhead stacks downwards:** the mark on top, the issuer name and
  address beneath it. Side by side, a long registered address broke mid-street
  against the logo; stacked, both read in one column and the document identity
  stays top-right.
- **Column headings wrap, figures do not.** Only `td` values are held on one
  line; a heading that cannot wrap prints straight out of a narrow column, so the
  header cells are `white-space: normal` and set a shade tighter (9.5 px, 1.25
  line-height) — `N.W. / CARTON` takes two short lines and no column ever spills
  into its neighbour. A portrait A4 is why there is no slack for a heading that
  refuses to wrap.
- **It reads as one ruled form, not a set of cards.** The facts strip, the three
  party cards, the table and the declaration/summary/sign-off blocks butt
  together: no gaps, no margins, neighbouring borders collapsed with a −1 px
  margin so two boxes share one rule instead of drawing two. Corners are square
  throughout (`border-radius: 0`), including the paper itself on screen — the
  shared shell rounds `.pdf-sheet`, so the packaging sheet re-states the square
  corner at matching specificity.
- **The logo is the issuer's.** `PackagingList::issuedByUs()` compares the From
  name with the organisation's legal and trade names (with "Pvt Ltd" against
  "Private Limited" ignored), so our own export prints on MissPack letterhead
  while a list drawn from a vendor's bill prints without our logo. With no
  sender typed, a list that links a vendor is the vendor's and one that does
  not is ours.
- **Carton labels** (`packaging_lists/label`) are A4, A5 landscape or
  100 × 140 mm thermal, laid out on the paper in millimetres and set in points.
  The label is a flex column that fills its page: the item table takes whatever
  height the identity, parties, weights and handling bands leave, so a carton
  holding one product does not print half a page of white. A5 is set at 11 pt
  (body, quantity and item text) with 15.6 pt packing group and 17.6 pt
  sender heading — two points up on the old 9 pt — and carries the carton's
  barcode under the QR it scans to. Labels use the app's Inter stack too: a
  carton label is part of the same paperwork as the invoice and the packing
  list, so it is set in the same type — and in the same pure black, with solid
  black rules rather than `#111`, because a label is read at a metre under
  warehouse light.
- **A label is one ruled form.** The identity, party, item and weight bands sit
  edge to edge in a column with no flex gap, and every seam between them is
  drawn exactly once — by the bottom rule of the band above. So each band
  between two others drops only its *top* edge; dropping both neighbours' edges
  is how a row ends up with no bottom border, which is what a printed label
  showed when the carton-number strip was left open-ended. The item table fills
  its band and its own cells draw every edge (a border on the wrapper would sit
  beside theirs and double the vertical rules), so its header row loses only its
  top border, which the party band already draws. Cells pad 3.5 × 4 mm on A4, 3 × 3.5 mm on A5 and
  1.5 × 1.8 mm on the thermal roll, so even a two-line address does not touch a
  rule. Only the handling pictograms stand apart, on their own 3 mm margin,
  because they are marks rather than rows of the form.
- **Show** (`packaging_lists/show`) is the working screen: the same sections and
  the same spanning, plus chargeable weight per carton and the `#carton-N`
  anchor each carton's printed QR points at.

## 3. Preparing a list like the supplier's

1. **One carton block per packing configuration.** A block is "10 cartons of
   50 × 34 × 39 cm". Three sizes of the same product are three blocks.
2. **Type the heading** in *Packing group* on each block that belongs together
   ("50ml Empty Spray Bottle"). Blocks with the same heading print together.
3. **Add item lines inside the block** for a carton that holds more than one
   product — *Add item* under the block's Items, one line per product with its
   own Qty/Carton. Do not create a second block for the same carton: a second
   block would be counted as extra cartons.
4. **Weight is per carton, not per item.** Enter the weight of the packed carton
   in *Weight information*; the item weights in the item modal are traceability
   figures and do not roll up.
5. **Total Qty is calculated** (Qty/Carton × Carton count) and cannot be typed —
   that is why a line of "1,000 per carton, 3 cartons" can never print 3,000 by
   hand.
6. **Print** — the sheet, the sections and the spanning are generated from the
   blocks, so the PDF matches the form without any layout work.

The three automatic checks that matter here: *carton dimensions and weights*
(every block has a size and gross ≥ net), *item descriptions and quantities*
(every line has a description and a positive quantity) and *source quantity
allocation* (nothing is packed beyond what the linked invoice or shipment
carried).

### Carton-label sender branding and 100 × 140 mm stock

Thermal labels use **100 × 140 mm** in both the CSS page size and the label frame;
single-group and all-carton selectors use that same size. A4/A5 paper sizes are unchanged.
The heading names the **sender**, not the shipping mark or recipient. MissPack-origin
labels use the organisation’s print logo above the sender heading; vendor-origin labels
never carry our logo. FROM and SHIP TO labels, names and addresses are each **2 pt larger**
than their previous format-specific size. The item band absorbs the changed height so
weights, handling marks and continuous borders stay on the label. Batch printing waits
for the logo image as well as fonts and QR codes before opening the print dialog.

### Global carton-label QR destination

Open **Settings → Packaging lists**, enter the **QR code link → Destination URL**,
and save. Use a complete `https://` (or `http://`) URL, up to 200 UTF-8 bytes, without
embedded usernames/passwords. A short link is best for the small thermal QR code.
The configured URL is encoded exactly, including query parameters and fragments;
no carton identifiers or tracking values are appended. It applies to all senders,
A4/A5/100 × 140 mm stock, and both single-group and all-carton batch output.

Clear the field and save to restore the existing ERP link to the relevant carton
on its packaging-list record (login required). Settings are read once per generated
label page, not once per physical copy. Changes affect newly generated/reprinted
labels; existing physical labels and saved PDFs keep their original payload.
Barcode text, numbering, label geometry and print auditing are unchanged. No new
database migration is required; the setting uses the existing `office_settings`
table. Missing settings tables and invalid manually stored URLs safely fall back
to the existing ERP link.

`php artisan test --filter=PackagingQrSettingsTest` exercises settings persistence,
permissions, URL safety/length, default reset, all label formats/senders, batch
copies and the once-per-page lookup.

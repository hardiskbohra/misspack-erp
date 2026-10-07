# Fixed assets — the register, the custody and the year

> *"In organisation, there are multiple fixed assets that a private limited
> company has to maintain and manage properly for the compliance purpose. Also
> maintain the asset allocation and maintenance and repairs history of each
> asset. The company should have an understanding of total fixed assets and the
> yearly depreciation should also be calculated as per the useful life of the
> asset and the depreciation method."*

A private limited company has to keep a **fixed asset register** — what it owns,
what it paid, who has it, what has been spent keeping it running, and what it is
worth at the end of the year. Most offices keep that in a spreadsheet, which is
where it goes wrong: the book value column is a formula somebody overwrote, the
hand-over history is a second sheet nobody updates, and the depreciation for the
year is worked out by hand the week the auditor calls.

This module is that register, with the arithmetic done once and in one place.

---

## 1. What the module is, and what it deliberately is not

It is a **module** — `/fixed-assets`, a sidebar door under **Accounts**, right
after Cashflow — and not a settings area. The boundary rule in
`docs/settings-module.md` decides this, and the register is its sharpest test:

| The register (`/fixed-assets`) | The classes (`/settings/assets`) |
| --- | --- |
| one row per thing the company owns | one row per *kind* of thing |
| the chair, with its purchase date, its custodian and its repairs | "furniture & fixtures: 10 years, straight line, 5% residual" |
| a **record**: it has states, history, a person who answers for it | a **rule**: it changes how every asset in it is written down |
| 200 rows and growing | a dozen, changed once a year |

So the asset register is a module, and only the class master data is a setting.
The two screens link to each other: the register's header has an **Asset classes**
button, and the classes page has a **Back to the register** button. Neither is a
dead end.

### The three surfaces

| Screen | Address | What it is |
| --- | --- | --- |
| **The register** | `/fixed-assets` | the company's totals, the state chips, the filter drawer, and the list — plus the register as a CSV in the office's own column order |
| **One asset** | `/fixed-assets/{asset}?tab=…` | the record: **Overview**, **Allocation**, **Maintenance**, **Depreciation** — four panels, four URLs |
| **The year** | `/fixed-assets/depreciation?year=…` | the company's depreciation schedule, class by class, with its own CSV |

---

## 2. The columns — sixty of them, in four blocks

The office's spreadsheet had forty columns. They are all in the module, grouped
the way the spreadsheet grouped them, and the extra ones are the figures the
spreadsheet could not keep right.

**Identity** — asset ID (unique), name, class, description/specifications, make,
model, serial/IMEI/identification number.

**Purchase** — purchase date, supplier (a vendor on file, or a typed name),
invoice number, invoice date, purchase cost, GST, the GST treatment, total cost,
capitalised cost.

**Custody** — location, department, custodian, status, warranty end date,
insurance details, insurance expiry, last physical verification date, who
verified it, condition, disposal date, disposal value, remarks, who registered
it and when.

**The recipe** — useful life (years), depreciation method, residual value (%).

Plus the two figures that are **not** columns, on purpose: **accumulated
depreciation** and **net book value** are computed, always, from the recipe.

### Why two of those are not columns

A stored `net_book_value` is a second answer to a question the arithmetic already
answers, and it is wrong from the moment somebody corrects a purchase cost. An
auditor's question is *"what was it worth on 31 March?"*, and the only honest way
to answer it is to compute it from the asset's own dates and recipe. So:

- the asset stores a **recipe** (`useful_life_years`, `depreciation_method`,
  `residual_percent`) — and each of the three is **nullable**, where null means
  *"follow the class"*, which is the answer for almost every asset;
- `total cost` is `cost + gst_amount`, a SQL expression where it is needed;
- `capitalised cost` is `cost + gst_amount` **when the GST was capitalised** and
  `cost` alone when the company claimed input credit — one switch
  (`depreciate_on_total`), read in one place, so the register, the record and the
  report cannot mean different things by "the cost".

There is deliberately **no "sort by net book value"** in the drawer. Newest,
oldest, name and invoice value are columns and SQL can order by them; net book
value is a curve, and a sort that had to compute every asset before it could
order one page is a register that dies at a thousand assets. The register's own
figures answer that question instead.

---

## 3. `AssetDepreciation` — one calculator

Every money figure in this module comes out of
`App\Services\AssetDepreciation`. The register's totals, the record's four
figures, the per-asset schedule, the company's year, both exports — one class,
called with the asset's own effective recipe. `FixedAsset::schedule()`,
`accumulatedDepreciation()`, `netBookValue()` and `chargeForYear()` are one-line
delegations to it; nothing re-implements the arithmetic, because a second
implementation of a depreciation schedule is a second answer to an auditor.

### The year

The financial year is **1 April – 31 March**, stated once, in
`AssetVocabulary::FINANCIAL_YEAR_START_MONTH`. The schedule is cut into those
years, and each year's charge is **pro-rated by the days the asset was on the
books in that year** — so the year an asset was bought charges from the purchase
date, and the year it was sold charges up to the disposal date. Nothing is
charged before the asset existed or after it left.

### Straight line (SLM)

`(capitalised cost − residual) ÷ life`, per day, for the days of the year in
which the asset was ours. A ₹1,00,000 machine over 10 years with a 5% residual
carries ₹9,500 in a full year, and about ₹4,760 for 183 days of one.

### Written down value (WDV)

The rate is **solved from the residual**, not typed in:

```
rate = 1 − (residual ÷ capitalised cost) ^ (1 ÷ life)
```

Charged on the year's opening book value, pro-rated by the same day count. This
is why WDV takes the bigger bite in the first year (₹25,893 against SLM's ₹9,500
on the machine above) and still lands exactly on the residual at the end of the
life — a flat 20% would leave ₹10,737 and quietly break the promise the recipe
made.

### The last year, and the rounding

Whichever method, **the last row's charge is `opening − residual`, exactly**. The
earlier rows are the method's arithmetic; this line is the rule that the curve
ends where the office said it would. Without it a schedule ends a few rupees away
from its own residual value, and the register's net book value with it. The
charge is also clamped (`max(0, min(charge, opening − residual))`) so a mistaken
recipe cannot depreciate an asset past its residual.

### A disposal stops the curve

From the day after the disposal the asset is not on the books: the curve ends
there, `net book value` reads zero afterwards, and
`FixedAsset::bookValueOnDisposal()` is the figure the sale is measured against —
the one the disposal dialog shows before the office types anything. A disposal
also **closes any open hand-over** and appends the reason to the remarks, because
a disposed asset is not in anybody's custody.

### Not depreciated

`depreciation_method = none` is a real answer — for land, for a fully written
down item kept in service, for something bought for a rupee. Such an asset sits
on the register at cost and produces **no schedule rows at all**, rather than a
row that says nothing was charged.

---

## 4. `AssetIntake` — one writer

Every door in the module — register it, change it, hand it over, take it back,
log a repair, verify it, dispose of it, delete it — posts to
`App\Services\AssetIntake`. The controller validates and produces the sentence;
the writer writes. That is what keeps the two histories and the asset's own
columns from drifting apart, and it is checked.

The eight doors, and what each one promises:

| Door | Writer | What it keeps true |
| --- | --- | --- |
| register | `create()` | the code is unique, the recipe is the class's until overridden, the registering person is recorded |
| change | `update()` | the open hand-over is kept in step with a changed custodian, location or department — and **never invented** if none is open |
| hand over | `allocate()` | a new open row, the previous one closed, and the asset's custodian/location/department moved with it |
| take back | `returnAsset()` | the open row closed with the date and the condition it came back in, the condition copied onto the asset (it is the latest news about it) |
| log a repair | `maintain()` | the repair history grows; the asset's status moves only if the office said so |
| verify | `verify()` | who looked (the session, not a typed name) and when |
| dispose | `dispose()` | the disposal recorded, custody closed, reason appended to remarks, the curve stopped |
| delete | `delete()` | the asset and **its own history** removed in one transaction. Nothing else is ever deleted |

### The cupboard rule, and the one direction it moves by itself

An asset's status is what the office says it is. There is exactly one case where
the writer moves it without being asked, because leaving it alone would be a lie
on the register:

- an asset in store (`spare`) that is handed to somebody **becomes `in_use`** —
  it is not in the store room any more;
- an asset that comes back from a hand-over **becomes `spare`** — it is nobody's
  now.

Everything else (under repair, back in use, disposed) is the office's own word,
typed on the door that did it. A machine repaired over a weekend never stopped
being in use, so the maintenance dialog's status field is optional and defaults
to *leave it as it is*.

### One open hand-over

`fixed_asset_allocations` has no "is this the open one" flag and no partial unique
index (a partial index is not portable across the databases this ERP could be
installed on). The invariant is the **writer's**: `allocate()` closes whatever is
open before it opens anything, `returnAsset()` closes the open row and refuses
when there is none. `FixedAsset::openAllocation()` is therefore the asset's
custody, and the register's *held by* column, the record's custody block and the
allocation tab all read it rather than a copy of it.

---

## 5. The register screen

**The figures above the table are the table.** Ten statistic cards and four
attention cards are computed from the *same builder* the list is read from — so
standing in the Ahmedabad view, every number on the page describes Ahmedabad.
The chip counts are the one exception, and for the obvious reason: they are
counted with the state chip lifted, or each chip would report on the state it is
standing in rather than the state it offers.

The ten figures are the two groups the office asked for:

- **what we own** — assets on the register, total fixed assets (capitalised),
  net book value today, depreciation to date, the year's charge, and what
  disposals realised across all years;
- **what needs doing** — warranty running out, insurance expiring, verification
  overdue, service due. Each one's count is **the same query its link opens**:
  the card reads four, the list shows four, because both are the same scope.

**The list** shows the columns a person reads on a screen: asset (name, code,
make/model, serial), class, purchase and age, cost and capitalised value, book
value and what has been written off, where it is and who holds it, state and
condition, warranty, and the row menu. The other columns are one click away on
the record, and all of them are in the export.

**The filter drawer** carries the class, the place (location, department,
custodian), and the compliance readings (warranty, physical verification, service
diary), plus the order. Every filter a request can carry is checked against what
the screen offers — a hand-typed `?warranty=garbage` is the default view, not an
empty list and not a 500.

**The row menu** offers the doors: open, hand over, take back (only when
something is out), log a repair, verified today, dispose of it (not on a disposed
asset), and delete — behind the shell's own confirm, with a sentence that says
what goes with it.

---

## 6. The record — four panels, four URLs

The tabs are **links with their own address**, the same rule the project and
recurring records follow, so a tab can be shared, bookmarked, opened in a window,
reached with the back button — and so a form posted from a tab returns to that
tab. An unknown `?tab=` is the overview, not an error.

- **Overview** — the identity, purchase, custody and compliance blocks; the
  recipe block with the figure it lands on; and the doors. Nothing is hidden
  behind an edit form: the page is the record.
- **Allocation** — every hand-over, newest first, with the open one marked and
  counted to today, the place, the days held, the condition it came back in and
  who recorded it.
- **Maintenance** — every service, repair, AMC payment, calibration and upgrade,
  with what it cost, how long the asset was down, when the next one is due, and
  the total spent keeping it running. These are running costs: the tab says so,
  because a reader arriving from the purchase block will wonder.
- **Depreciation** — the recipe it is actually using (and whether that came from
  the class or from the asset), then the whole life, financial year by financial
  year: opening value, the year's charge with its day count, and the closing
  value. Where the method is WDV the page says why the first year is the biggest;
  where it is SLM it says why the first and last rows are the smallest.

---

## 7. The year — the company's depreciation schedule

`/fixed-assets/depreciation?year=2026-27`, with the year as the one control it
carries. It answers the question a CA asks, so it is **company-wide by design**:
the page says out loud that it ignores the register's filters, because reading a
year through a location filter answers a question nobody asked.

Five figures, then the schedule **class by class**:

| | |
| --- | --- |
| opening book value | the day before the year began |
| additions | capitalised value of everything bought during the year |
| depreciation for the year | the figure that goes to the profit and loss account |
| profit or loss on disposals | what sold fetched, against what it was carried at |
| closing book value | opening + additions − charge − disposals at book value |

The rows are the assets the year **touched** — still being written down, bought
during it, or sold during it. A fully depreciated asset contributes nothing and
is not a row, and the page says that too.

Every class has a subtotal, and the company's own row sits under them all. That
total is **accumulated from the very rows printed above it** — the report's
totals are the sum of its own table, not a second query that could disagree with
the page it sits on.

Both the register and the schedule export as CSV. The register's export is the
office's own column order, including the two figures the spreadsheet could never
keep right, computed as at the day the file was taken.

---

## 8. Settings — the classes

`/settings/assets` is the sixth area of the settings module, and it holds one
list: the classes. Each class carries a code, a name, a useful life, a method, a
residual percentage, a sort order, notes and an active flag — and the number of
assets in it.

- a class **cannot be deleted while it is holding assets**. The register would be
  left with assets whose class has stopped existing, and the recipe they inherit
  would be a hole. The refusal names the count and says what to do instead;
- changing a class's recipe changes how **every asset that follows the class** is
  written down from now on. Assets with their own recipe are untouched, and the
  page says so before the office presses save.

The list itself is a table — a class per row, its actions in the last cell —
because a class is a record with nine fields, and nine inputs in a grid of eight
columns is not a row of a list, it is a form that happens to look like one. It
clipped its own Notes and Save off the right edge of the card, and the corner it
had was measured in a `notes` input that could never fit. It follows the same
shape as every other master list in the settings module: `.master-card` holding a
toolbar, a `.master-table-wrap` (which is also where the horizontal scroll lives
below the desktop breakpoint) and a `.master-table`, with `data-label` on every
cell so the same rows re-flow into labelled cards on a phone.

**One dialog, two doors, and every door carries what the write needs.** *Add*
opens the dialog pointed at the collection; *Change* opens the same dialog pointed
at that row, and hands it the row's own values — including the `code`, which was
the actual cause of the validation error ("The code field is required.") that
appeared when a class was edited. The validator was right: `code` is required, and
unique ignoring the record being edited. The row was simply never telling the
form what its code was, so the form sent nothing. The fix is on the form side —
the door passes a `data-payload` and the script fills the fields from it; the rule
was not weakened to `sometimes`, and the dialog now carries exactly the keys
`AssetSettingController::validatedData()` reads, so a dialog that stops offering a
field its validator requires fails the check instead of the office.

A failed save comes back to **that row's** dialog: the form remembers the row it
was about in `_record`, and the reopen happens through the row's own door, so the
typing survives and the second attempt is aimed at the same class rather than at
whatever the add door meant. For that to work the marker that names the dialog
(`_dialog`), the word the door carries (`data-open-asset-modal`) and the key the
form itself answers to (`data-asset-form`) have to be **the same word** —
`register`, `edit`, `allocate`, `return`, `maintenance`, `verify`, `dispose` for
the register's dialogs, `category` for this one. They were not: every dialog
answered to its modal's *id* (`assetCategoryModal`) while every door spoke the
*action*, so `form[data-asset-form="assetCategoryModal"]` matched nothing and no
failed save ever reopened anything. A reader cannot see that; `tools/checks/assets-dialog-check.cjs` runs it.

---

## 9. What is checked

`tools/checks/assets-check.cjs` — every promise above, in the order it would be
broken: the recipe is not the answer and the arithmetic lives in one class; the
writer is one writer and the state follows the cupboard; the financial year, the
pro-rating, the last-row rule and the anchored WDV rate; the register's figures,
chips, drawer, tabs, dialogs and exports; and the boundary between the register
and the classes. The two formulas are additionally **ported out of PHP and
executed** in the check, because that is the one part of this module that can be
proved without a PHP runtime.

The dialog behaviour is checked too — a marker names the form, the form owns the
dialog, a shared dialog clears itself before it is filled again, and a failed
save reopens the dialog it came from with the typing kept.

`tools/checks/assets-dialog-check.cjs` goes further and **runs the dialogs**: it
puts the module's own `assets.js` on a real DOM (jsdom), renders the doors and the
forms from the views' own vocabulary, and clicks them. It promises that a change
door opens addressed at its own row with the row's values in the fields and the
right verb in `_method`; that an add door posts to the collection again, clears
the typing and empties `_record`; that a retired class comes back with its
checkbox clear and the hidden "no" intact; and that a save which failed reopens
the dialog it came from, still addressed at the same row, with the typing kept.
The fixtures are not invented: the check re-reads each fixture's field names and
payload keys out of the views it claims to describe, so a form that stops
offering a field fails even if the fixture was updated to match. This is the only
check that can catch the class of bug the reopen marker was — a lookup that
returns `null` reads exactly like a lookup that works.

Two promises exist because there is **no PHP runtime where this was built**, and a
page that reads a name that does not exist fails in the reader's face rather than
in a build:

- **every view resolves — per render, not per repository.** Each `$name` a view
  reads has to be one **its own render will have**: passed by the controller
  that renders that page, bound by the file itself, handed down by the
  `@include` that reached it, or inherited from a loop the include sits inside.
  The walk starts from everything the module knows and **narrows to the
  intersection of every includer**, because a partial rendered by two pages may
  only count on what both of them pass. A name in common across the module is
  not enough, and believing it was is how a dialog reached a browser reading a
  list only the other page sent — the constant lists the dialogs are filled from
  therefore live in `FixedAssetController::sharedData()`, not on one screen;
  each `$asset->…()`, `$category->…()`, `AssetVocabulary::…` and
  `$figures[…]/$totals[…]/$row[…]` a view names exists on the model, the
  vocabulary, or in the service that builds the array. This is the honest
  substitute for rendering the page once, and it is the one that can name the
  method, the key and the file;
- **every class resolves.** Each `ast-…` class a view writes has a rule in
  `assets.css` (the module's own namespace, scoped under `.fixed-assets`), and
  each badge tone, stat-card colour and notice-box modifier is a rule in the
  shell's sheets — a class nobody defines renders as nothing, and nothing about
  the markup would say so.

---

## 10. What is not built

Said plainly, so the next round does not have to guess:

- **no component accounting.** An asset is one row with one recipe. Splitting a
  machine into "shell" and "electronics" with different lives needs a child
  table, and nobody has asked for it;
- **no revaluation or impairment.** The register writes down; it does not write
  up. A revaluation would need its own history and its own decision about whether
  it disturbs the curve;
- **no document attachments.** The purchase invoice is a number and a date on the
  asset; the file itself belongs in the cashflow document archive that already
  exists, and a second attachment store would be a second archive;
- **no asset write-back to the ledger.** The year's depreciation is a report —
  the office posts it, because posting depreciation is a decision about which
  account and which date, and the cashflow ledger deliberately has no
  non-cash entries;
- **no barcode or QR labels.** The asset code is the sticker; printing labels is
  a separate job with a separate printer;
- **no depreciation for a part-year on a monthly convention.** Days, not months,
  because days can be explained to an auditor and half-months cannot.

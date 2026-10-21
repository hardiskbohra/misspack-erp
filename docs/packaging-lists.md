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

## 2. What the two surfaces show

- **Print** (`packaging_lists/print`) is the sheet the supplier and the customer
  read: Sr. No., Item Description, Carton, Qty/Carton, Total Qty, Carton Size,
  NW/Carton, Net Weight, GW/Carton, Gross Weight, Total CBM — eleven columns.
  Volume is carried as a total for the packing run only; the per-carton CBM was
  a twelfth column nobody priced freight from. Item lines are numbered
  continuously across sections, and the foot is the document's own totals — so a
  section can never disagree with the total.
- **The sheet is black and white.** Every rule, band and fill is grey or black,
  body text is black on white, and no colour carries meaning, so it prints the
  same on an office laser as on screen — including with "background graphics"
  switched off. Table body text is 12 px, not the old 10 px, and dimensions
  print as `50 × 34 × 30` rather than `50.00 × 34.00 × 30.00`.
- **It is laid out to the page, not to the screen.** A4 landscape, 10 mm
  margins, the document box is 277 mm wide (297 − 2 × 10) and print type is set
  in points, so the browser sends the page to the printer at scale 100% instead
  of shrinking a 1120 px design. The Carton Size column is 9% — wide enough for
  `120 × 80 × 65` on one line, narrow enough to give the description its space.
- **The logo is the issuer's.** `PackagingList::issuedByUs()` compares the From
  name with the organisation's legal and trade names (with "Pvt Ltd" against
  "Private Limited" ignored), so our own export prints on MissPack letterhead
  while a list drawn from a vendor's bill prints without our logo. With no
  sender typed, a list that links a vendor is the vendor's and one that does
  not is ours.
- **Carton labels** (`packaging_lists/label`) are A4, A5 landscape or
  100 × 150 mm thermal, laid out on the paper in millimetres and set in points.
  The label is a flex column that fills its page: the item table takes whatever
  height the identity, parties, weights and handling bands leave, so a carton
  holding one product does not print half a page of white. A5 is set at 11 pt
  (body, quantity and item text) with 15.6 pt packing group and 17.6 pt
  destination — two points up on the old 9 pt — and carries the carton's
  barcode under the QR it scans to.
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

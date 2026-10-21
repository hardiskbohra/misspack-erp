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
  read, in the supplier's own column order: Sr. No., Item Description, Carton,
  Qty/Carton, Total Qty, Carton Size, NW/Carton, Net Weight, GW/Carton, Gross
  Weight, CBM/Carton, Total CBM. Item lines are numbered continuously across
  sections, and the foot is the document's own totals — so a section can never
  disagree with the total.
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

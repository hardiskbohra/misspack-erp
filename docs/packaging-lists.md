# The packaging list

**Status: built.** One document per packing run — `MP/PL/26-27/NNN` — with a
screen, a printed sheet, per-carton labels and structured automatic checks. Its
guard rails are `tools/checks/packaging-lists-check.cjs`; the grouping arithmetic
below is held by `tests/Unit/PackagingProductGroupsTest.php`
(`php artisan test --filter=PackagingProductGroupsTest`).

The data is three tables, because a carton is a real object with a size and a
weight, and the thing inside it is not:

| Table | Holds |
| --- | --- |
| `packaging_lists` | the document: number, date, parties, linked records, totals |
| `packaging_list_cartons` | one **packing configuration**: count of cartons, size, weight per carton, handling |
| `packaging_list_items` | what sits in that configuration: product, quantity per carton, batch, serials |

Everything derived — total quantity, net and gross weight, CBM, chargeable
weight — is computed from those two children, never stored as a second fact.

---

## 1. A product is not a carton

A supplier packs one product in whatever cartons are to hand. The sheet that
comes back with the goods says, for one bottle:

| Cartons | Qty / carton | Total qty |
| --- | --- | --- |
| 10 | 336 | 3,360 |
| 2 | 340 | 680 |
| 3 | 300 | 900 |

Three carton groups, one product, and the receiving side reads **15 cartons and
4,940 pieces** with one weight and one CBM. Read carton by carton, the product
appears three times and nothing adds it up, which is exactly what the screen and
the printed sheet used to do.

`PackagingList::productGroups()` is the read that fixes it. It groups the stored
carton rows by product — the `product_id` when the line is linked, the
normalised description otherwise, never across units — and totals each group.
It is **derived on read**: there is no `product_group` column to keep in step
with the rows, and nothing to backfill when the grouping rule changes.

Grouped, the same list prints as:

```
Group(50ml Spray Bottle)                                   3 carton sizes
1  50ml Spray Bottle  10  336  3,360  50×34×39  6.90  69.00  7.36  73.60 ...
2  50ml Spray Bottle   2  340    680  50×34×39  6.90  13.80  7.36  14.72 ...
3  50ml Spray Bottle   3  300    900  50×34×39  6.90  20.70  7.36  22.08 ...
Group total · 15 cartons            4,940               103.50       110.40
```

The serial number restarts inside each group, the group total is the row the
office checks, and the grand total at the foot is still the document's own
(`total_cartons`, `total_net_weight`, …) so the two can never disagree.

## 2. A carton that holds two products

A carton is occasionally shared. It is listed under both products — nothing
packed should be invisible — but its weight and CBM are not split between them,
because any split would be a guess. Such a row is marked `shared`, the group
counts its pieces and not its cartons, and both the sheet and the screen say so
in a footnote. The document total counts that carton once, as it should.

## 3. What the two surfaces show

- **Print** (`packaging_lists/print`) is the sheet the supplier and the customer
  read: product groups, per-carton rows, per-group totals, then the grand total.
  Each row also names the physical cartons it covers (`Cartons 1–10`), so a
  group heading still tells a packer which boxes to open.
- **Show** (`packaging_lists/show`) is the working screen: the same grouping in
  the shared table, with chargeable weight per carton and the label anchor each
  carton's printed QR points at.

Neither surface lists a product twice.

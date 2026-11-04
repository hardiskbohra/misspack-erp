# Stock reconciliation

> **Implementation status:** this first release has been verified locally with disposable data but has not been deployed. See [the stock test plan](testing/stock-reconciliation.md) for results and remaining limitations before relying on it for live inventory.

## What this release covers

The stock register maintains **company-owned quantities at one location**. A stock item is one existing product plus a descriptive variant and, where applicable, a batch/lot and expiry date. Every item has one fixed unit. For example, `Bottle / amber 100 ml / lot B-01 / pcs` and `Bottle / clear 100 ml / lot B-02 / pcs` are separate balances. A batch may be left unspecified, but an expiry date requires a named batch. Expiry warnings use the application's configured time zone (currently UTC); they do not block an issue.

The stock ledger starts with a deliberately entered opening count and records manual receipts, issues and approved count adjustments. It does **not** infer historical balances or automatically post purchase invoices, converted bills, shipments, delivery receipts, returns or packaging lists. The catalogue's `ready_stock_available` field is a separate yes/no flag and does not establish a stock balance. References typed into a movement are notes, not links or automatic duplicate checks against the referenced document. Posting the same real transaction twice from two separate forms will record it twice.

This version does not provide multiple locations, transfers, unit conversions, reservations, owner/consignment accounting, costing or inventory valuation, general-ledger postings, import, automatic reorder alerts or a public/client/vendor view. Do not treat its quantity totals as an accounting valuation or available-to-promise balance.

## Access and responsibilities

- Office `operations.view`: view the register, item histories and physical counts.
- Office `operations.manage`: open stock items, record manual receipts/issues, start and complete a count, review another person's submitted count, or cancel an open count.
- A count's creator is its sole counter. They cannot approve or reject their own submission. A **different** office user with `operations.manage` must independently review and give a reason. If the counter's user account has been deleted, the count cannot be independently verified; an operations manager should cancel it and start a new count.
- Posted history is retained. There is no edit/delete button for stock movements or completed counts. Products referenced by stock history cannot be deleted; mark them inactive instead.

## Start a stock item

1. From **Stock reconciliation → Add stock item**, choose an existing product. Specify the variant, batch/lot and expiry if relevant, and the stock unit.
2. Enter a physically verified opening quantity, including an explicit `0` only if the actual quantity is known to be zero. Add the reason and, optionally, the source or count-sheet reference.
3. Save. The opening is immediately recorded as the first ledger entry. The product + normalized variant + normalized batch identity must be unique. Matching ignores case and repeated whitespace, but cannot recognize differently worded descriptions of the same variant; use a consistent naming convention. Identity, unit and expiry are retained as history; a different batch or variant needs its own item.
4. Repeat for every independently tracked variant and batch. Do not add different units together or assume a carton equals a particular number of pieces; no conversion is performed.

There is no automatic migration of old paperwork into opening stock. Plan a cutover: agree the start time, physically verify the opening counts, enter them once and record subsequent movements. Until the opening and subsequent movements are complete, the on-hand figure is not a reliable physical balance.

## Record receipts and issues

On the stock item's ledger page, choose **Receipt** to add quantity or **Issue** to remove it. Enter a positive quantity in that item's unit, a reason and an optional reference. Quantities accept up to 12 whole digits and three decimal places; they are stored as integer thousandths and are not rounded. An issue larger than the current balance is rejected. Each entry records its actor, time, signed change and running balance. The same request retried with its original request key is not posted twice, but separate submissions with different keys are separate movements.

Correct a mistake with a later, clearly explained counter-movement or an independently approved physical count; do not delete history. If the cached balance/revision no longer matches the latest ledger entry, new postings and count snapshots are blocked. An administrator should investigate against a consistent backup and the full ledger rather than changing the cache blindly. The built-in comparison with the latest row is not a full audit of every historical movement.

## Count and independently reconcile

1. Choose **Physical counts → Start physical count**. Give the count a meaningful name and select 1–100 precise product/variant/batch rows, including any zero-balance items that should be checked. The system snapshots their current book quantities and ledger revisions when the count is created. Count the stock against that snapshot; a count only covers the selected rows.
2. The creator enters the physical quantity for each row and may save a partial draft. **Blank means not counted; it is not zero.** A difference from book stock requires a line-specific reason, such as damage, shortage or an overlooked receipt.
3. Submit the count for review. Submission saves the currently entered values. The submitted quantities, snapshots and reasons are retained in the count history.
4. A different operations manager reviews the evidence and either approves with a reason or returns the count with a reason. Approval posts all nonzero adjustments together; matching rows do not create artificial zero movements. The decision and actor remain in the history. If another action changes the count while a form is open, reload and review the current version; an older form cannot approve or overwrite it.
5. If any selected item's ledger changes after the snapshot, even if later movements bring the balance back to the same number, posting is blocked. For a submitted count, the reviewer must return it first. The counter then selects **Refresh book balances**, which snapshots current balances and clears **all** entered physical quantities and difference reasons, and physically recounts before resubmitting. Previous submissions remain visible in history.
6. If the count is no longer needed, an operations manager may cancel an open draft or submission with a reason. Cancellation changes no stock and retains its history.

Example: opening 100 pcs + receipt 25 pcs − issue 10 pcs = book 115 pcs. A verified physical count of 112 pcs has a variance of −3 pcs. After another manager approves, the ledger records a −3 pcs count adjustment and the new book balance is 112 pcs. If any movement was posted between the snapshot and approval, the original count cannot be posted and must be refreshed/recounted.

## Deployment and rollback precautions

Back up the database before applying the migration; deploy the migration before exposing the new stock routes. Existing product, purchase, sales and shipment records are not modified or backfilled by it. The migration refuses normal rollback when stock history exists. Intentional removal or recovery requires a verified consistent backup, explicit data migration/removal and a maintenance window with writers stopped; MySQL DDL auto-commits. Never run `migrate:fresh` on a live database.

Before use, review the permissions, verify the configured application time zone, agree the physical cutover and reconciliation procedure, and review the verification and limitations in the test plan. Local test results do not replace a deployment rehearsal, consistent backups or a verified physical opening count.

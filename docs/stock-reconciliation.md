# Stock reconciliation

> **Implementation status:** the manual-ledger and subsequent edit/archive changes have local verification using disposable data but have not been deployed. See [edit/archive verification and limits](testing/stock-register-edit-delete.md) and [the original stock verification](testing/stock-reconciliation.md) before relying on them for live inventory.

## What this release covers

The stock register maintains **company-owned quantities at one location**. A stock item is one existing product plus a descriptive variant and, where applicable, a batch/lot and expiry date. Every item has one explicit stock unit. For example, `Bottle / amber 100 ml / lot B-01 / pcs` and `Bottle / clear 100 ml / lot B-02 / pcs` are separate balances. A batch may be left unspecified, but an expiry date requires a named batch. Expiry warnings use the application's configured time zone (currently UTC); they do not block an issue. Correcting an incorrectly entered unit does not convert quantities.

The stock ledger starts with a deliberately entered opening count and records manual receipts, issues and approved count adjustments. It does **not** infer historical balances or automatically post purchase invoices, converted bills, shipments, delivery receipts, returns or packaging lists. The catalogue's `ready_stock_available` field is a separate yes/no flag and does not establish a stock balance. References typed into a movement are notes, not links or automatic duplicate checks against the referenced document. Posting the same real transaction twice from two separate forms will record it twice.

This version does not provide multiple locations, transfers, unit conversions, reservations, owner/consignment accounting, costing or inventory valuation, general-ledger postings, import, automatic reorder alerts or a public/client/vendor view. Do not treat its quantity totals as an accounting valuation or available-to-promise balance.

## Access and responsibilities

- Office `operations.view`: view the register, item histories and physical counts.
- Office `operations.manage`: open and correct stock items, request balance corrections, archive or restore items, record manual receipts/issues, start and complete a count, review another person's submitted count, or cancel an open count.
- A count's creator is its sole counter. They cannot approve or reject their own submission. A **different** office user with `operations.manage` must independently review and give a reason. If the counter's user account has been deleted, the count cannot be independently verified; an operations manager should cancel it and start a new count.
- Posted history is retained. There is no edit/delete button for stock movements or completed counts. Products referenced by current or historical stock identities, including archived items, cannot be deleted; mark them inactive instead.

## Start a stock item

1. From **Stock reconciliation → Add stock item**, choose an existing product. Specify the variant, batch/lot and expiry if relevant, and the stock unit.
2. Enter a physically verified opening quantity, including an explicit `0` only if the actual quantity is known to be zero. Add the reason and, optionally, the source or count-sheet reference.
3. Save. The opening is immediately recorded as the first ledger entry. The product + normalized variant + normalized batch identity must be unique, including archived items. Matching ignores case and repeated whitespace, but cannot recognize differently worded descriptions of the same variant; use a consistent naming convention. Identity, unit and expiry are retained as history; a genuinely different batch or variant needs its own item, not a correction of this one.
4. Repeat for every independently tracked variant and batch. Do not add different units together or assume a carton equals a particular number of pieces; no conversion is performed.

There is no automatic migration of old paperwork into opening stock. Plan a cutover: agree the start time, physically verify the opening counts, enter them once and record subsequent movements. Until the opening and subsequent movements are complete, the on-hand figure is not a reliable physical balance.

## Record receipts and issues

On the stock item's ledger page, choose **Receipt** to add quantity or **Issue** to remove it. Enter a positive quantity in that item's unit, a reason and an optional reference. Quantities accept up to 12 whole digits and three decimal places; they are stored as integer thousandths and are not rounded. An issue larger than the current balance is rejected. Each entry records its actor, time, signed change and running balance. The same request retried with its original request key is not posted twice, but separate submissions with different keys are separate movements. If the item is corrected or archived/restored while a movement form is open, the old form is rejected; review the latest identity and preserved unsaved entries before re-entering it. Older forms or integrations must reload and include the current record revision.

Correct a mistake with a later, clearly explained counter-movement or an independently approved physical count; do not delete history. If the cached balance/revision no longer matches the latest ledger entry, new postings and count snapshots are blocked. An administrator should investigate against a consistent backup and the full ledger rather than changing the cache blindly. The built-in comparison with the latest row is not a full audit of every historical movement.

## Correct details or request a balance correction

On an active item's ledger, **Edit item** corrects an incorrectly recorded product, variant, batch, expiry or unit. Give a reason. The correction changes the identity of this same ledger and is not a transfer, split, new batch or unit conversion; open a separate item for genuinely different stock. The item history records the actor, time, reason, before/after identity and book balance. Open counts become stale when identity changes and must be returned, refreshed and recounted.

The edit form shows the current balance. Leave the requested quantity unchanged for a details-only correction. Entering a different physical quantity and a reason submits a one-item count to the normal independent-review queue; **the balance does not change until a different operations manager approves**. Details are saved immediately even while that quantity request awaits review. Movement or identity changes after the request make the count stale. Rejected requests may be refreshed/recounted or cancelled using the usual count workflow. Retry of an old edit form is rejected; review current values and any unsaved entries before trying again.

New ledger movements store the identity/unit at posting. Movements predating this feature have no original movement-time identity snapshot: they show the current item identity until the first identity correction, when the then-current identity is captured and labelled as an older record. Earlier unrecorded changes, including product-name edits before capture, cannot be reconstructed. Count snapshots and later item-history entries remain separately visible.

## Archive and restore an item

**Delete** opens an archive decision, not a hard delete. Provide a reason; an item with stock on hand also requires explicit acknowledgement that its balance will disappear from the active register but remain in the archive. Finish or cancel any open counts for the item first. Archiving preserves its balance, ledger, counts and item history; it prevents new movements or counts, and its product/variant/batch identity remains reserved. The active register warns if archived items still have stock. Use the **Archived** filter to inspect those balances and **Restore** with a reason to return the same record and balance to the active register. A stale archive/restore form must be reviewed against the latest item and balance before retrying.

## Count and independently reconcile

1. Choose **Physical counts → Start physical count**. Give the count a meaningful name and select 1–100 precise product/variant/batch rows, including any zero-balance items that should be checked. The system snapshots their current book quantities and ledger revisions when the count is created. Count the stock against that snapshot; a count only covers the selected rows.
2. The creator enters the physical quantity for each row and may save a partial draft. **Blank means not counted; it is not zero.** A difference from book stock requires a line-specific reason, such as damage, shortage or an overlooked receipt.
3. Submit the count for review. Submission saves the currently entered values. The submitted quantities, snapshots and reasons are retained in the count history.
4. A different operations manager reviews the evidence and either approves with a reason or returns the count with a reason. Approval posts all nonzero adjustments together; matching rows do not create artificial zero movements. The decision and actor remain in the history. If another action changes the count while a form is open, reload and review the current version; an older form cannot approve or overwrite it.
5. If any selected item's ledger or identity changes after the snapshot, even if later movements bring the balance back to the same number, posting is blocked. For a submitted count, the reviewer must return it first. The counter then selects **Refresh book balances**, which snapshots current identities and balances and clears **all** entered physical quantities and difference reasons, and physically recounts before resubmitting. Previous submissions remain visible in history.
6. If the count is no longer needed, an operations manager may cancel an open draft or submission with a reason. Cancellation changes no stock and retains its history.

Example: opening 100 pcs + receipt 25 pcs − issue 10 pcs = book 115 pcs. A verified physical count of 112 pcs has a variance of −3 pcs. After another manager approves, the ledger records a −3 pcs count adjustment and the new book balance is 112 pcs. If any movement was posted between the snapshot and approval, the original count cannot be posted and must be refreshed/recounted.

## Deployment and rollback precautions

Back up the database and stop stock/product writers before applying the stock migrations; deploy the migration before exposing the corresponding routes. The edit/archive migration initializes product-history references from existing stock items and adds identity, audit and archive metadata; it does not infer stock from product, purchase, sales or shipment documents. Existing movements are snapshotted lazily before their first identity correction. The migrations refuse normal rollback when protected history would be discarded. Intentional removal or recovery requires a verified consistent backup, explicit data migration/removal and a maintenance window with writers stopped; MySQL DDL auto-commits, so a partial failed migration needs deliberate recovery. Never run `migrate:fresh` on a live database.

Before use, review the permissions, verify the configured application time zone, agree the physical cutover and reconciliation procedure, and review the verification and limitations in the test plan. Local test results do not replace a deployment rehearsal, consistent backups or a verified physical opening count.

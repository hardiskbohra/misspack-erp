# System integration / end-to-end journeys

These are **designed release journeys**, not a claim that a complete browser suite has executed them. Run on disposable data with authoritative DB assertions and browser evidence. Use the detailed UT/IT catalogue IDs to implement the smaller boundaries first.

## SYS-01 — Client → project → PI → cash → tax remainder (P0)

**Fixtures:** C1, P1/A100/B200, INR PI300000, cash100000, bank50000.

1. Approve client/KYC and create project/product snapshots and commercial PI.
2. Record the two receipts through the supported receipt flow; assert total received150000 and no duplicated source entries.
3. Convert100000 to a cash invoice with chosen transfer100000.
4. Verify cash document contains no GST claim, PI remains open, cash-first100000 transfers and bank50000 remains with PI.
5. Convert remaining amount to tax invoice; inspect each original item, proportional rate, discount, GST and rounding.
6. Open client profile, PI/invoice summary, cashflow allocation picker, statement and workflow gates.

**Expected:** one reconciled economic claim across canonical documents; no duplicated PI receivable or payments; currency buckets agree; physical project quantities not duplicated by amount-summary invoices. Read-only summaries do not mutate money. **Related:** SALES, CLIENT, CASH, STATEMENT, WORKFLOW.

## SYS-02 — Reversal and failure recovery (P0)

1. Create partially converted PI with split receipts and an auditable cash child.
2. Snapshot source/child/allocations/opening credit/ledger account balances.
3. Inject failure independently at audit insert, receipt reassignment and child deletion.
4. Assert each rejected attempt leaves the original graph unchanged.
5. Reverse successfully, verify original balance and receipt ownership, then repeat a stale request.

**Expected:** single atomic restoration, traceable reversal evidence, no invoice-number reuse and no double credit. Tax-remainder dependencies continue to block unsafe reversal. **Related:** IT-SALES-003 and reversal cases004 onward; existing CashInvoiceDeletionTest.

## SYS-03 — Purchase settlement → mirrored cashflow → cancellation (P0)

1. Create V1 bill100000; record active payment25000 and a separate opening-paid amount.
2. Verify vendor payment, mirrored ledger row, bill status, stored balance and allocation picker agree.
3. Cancel through both supported vendor-money and cashflow paths in separate fixtures.
4. Repeat cancellation and inject one failed operation during bulk reversal.

**Expected:** no duplicate mirror; only active economic payment counted; unrelated payments/opening amounts retained; cancellation reopens correct balance; bulk failure rolls back every selected reversal. Include FX source/document amounts. **Related:** VENDOR, PURCHASE, CASH.

## SYS-04 — Independent product execution and partial shipment (P0)

1. Start standard workflow twice for A100/B200; verify no duplicate steps.
2. Complete A procurement/artwork/PPS/production, recording optional sample-lot QC evidence.
3. Keep B unfinished. Confirm A packing and automatic product comparison.
4. Pay full required dispatch balance or record an authorised, evidenced override.
5. Dispatch/deliver A40 then A60 via two shipments. Record delivery receipt without attachment once.
6. Recalculate workflow and reopen queue/UI.

**Expected:** A reaches100 delivered exactly; B remains uncompleted; no over-allocation; project not fully delivered/closed while B open; no unnecessary navigation outside Action Centre. Packing/QC/financial blockers stay actionable. **Related:** PROJECT, WORKFLOW, QC, PACK, SHIP.

## SYS-05 — Upstream change invalidates stale readiness (P0)

1. Approve current artwork, passing QC, packing and dispatch balance.
2. Change a material upstream item/approved version or reverse relevant receipt.
3. Attempt dispatch using the previously opened browser/route model.
4. Re-evaluate, resolve the changed prerequisite, and retry.

**Expected:** previous approval/evidence does not silently authorise changed content; rejection is atomic and explains next unblocking action; only authorised sensitive override can bypass the appropriate gate. **Related:** WORKFLOW/QC/PACK/SHIP. Full stale-evidence system journey remains planned.

## SYS-06 — Client portal isolation and revocation (P0)

1. Login CP1 and CP2 in separate browser contexts.
2. As CP1 substitute C2 IDs in invoices, projects, shipments, documents, statements, support and print/download URLs.
3. Repeat with parent ID from C1 and child ID from C2.
4. Disable CP1 or its client after login; test next request and cached/private responses.
5. Exercise one-time password and expired/invalid public token paths.

**Expected:** no foreign content, filenames, financial figures, search snippets or write effects; disabled sessions cleared; restricted password-change surface enforced. Unit canLogin tests alone do not complete this journey.

## SYS-07 — Vendor portal/share isolation (P0)

Repeat SYS-06 with VP1/VP2 and shared purchase/project documents; narrow scope to one share target; revoke an already-used token. Attempt portal/share administration with purchase.manage but no portals.manage.

**Expected:** vendor/target scope and revocation effective for HTML and downloads; sensitive portal grant enforced; raw tokens not logged. Native/session/browser journey remains planned.

## SYS-08 — Employee privacy and private notes (P0)

1. Office creates payslips/documents for E and B; E signs in.
2. Access own record, attempt B's documents/payslips and every office index.
3. Submit own contact edit plus forged bank/role/permissions/pay fields.
4. E creates note with forged A owner; A attempts its edit/update/pin/archive/delete.

**Expected:** own permitted reads only; no salary or banking change through contact form; office routes redirect employee to own workspace; even A cannot access E's private note; note ownership immutable. Office boundary/note portions are automated; full payroll HTTP/download matrix remains planned.

## SYS-09 — Recurrence and transaction contention (P0)

1. Approve monthly31January rule; verify leap-February clamp and restoration of31March with1-based sequence IDs.
2. Post the same occurrence from two native DB connections/processes, synchronized before write.
3. Pause/resume across a gap; reverse one posted occurrence; retry a stale posting.

**Expected:** one active occurrence ledger effect; no accidental catch-up postings; anchor and date/count ceilings correct; reversal traceable. Calendar unit tests are not evidence of concurrency safety.

## SYS-10 — Reporting, currency and exports (P1)

1. Place financial rows immediately before, at both boundaries and after custom10..12October.
2. Use overview and all six module dashboards; switch custom/month/quarter/half/year without first submitting.
3. Verify chosen dates survive module navigation; Apply once; compare prior7..9October.
4. Run native day/month/year aggregation and reconcile statement/client/invoice views under declared respective accounting scopes.
5. Print/export; verify currency labels and cancelled/transfer handling.

**Expected:** exact inclusive date range; no hidden date input blocking another view; comparable period length; no mixed-currency invented total. Current-balance cards may intentionally be point-in-time rather than period movements—test each label's contract, not a blanket filter assumption.

## SYS-11 — Evidence, print and mobile execution (P1)

1. Upload valid and invalid batches directly on shipment show; inspect project document ownership and visibility opt-in.
2. Print third-party and MissPack sender carton labels at100×140 and100×150mm, plus A5 and configured A4 orientation/columns.
3. Open workflow/filter drawers and horizontally bounded carton/advance tab rails at320/390px and keyboard-only desktop.
4. Close/reopen with Escape/backdrop and verify focus/Select2 cleanup.

**Expected:** no partially stored invalid batch; no file path/foreign evidence leak; sender not recipient at label top; branding only for MissPack; configured paper rules honoured; every record/action accessible without page overflow.

## SYS-12 — Asset custody → depreciation → disposal → report (P0 accounting review)

1. Add two assets with SLM/WDV, explicit residual and GST-credit choices; allocate/return/reassign one.
2. Read midyear NBV and financial-year report; sum row values to totals.
3. Dispose the SLM asset early; recompute disposal charge, pre-disposal book value, sale gain/loss and next-day NBV.
4. Reconcile screen, export and any financial summary consuming these figures.

**Expected:** one current custodian; conserved cost/residual for normal life; disposal-day prorating follows approved policy; thereafter asset leaves books. **Open defect:** manual probe currently charges95000 rather than the documented9526.03 for the fixture in the TDD playbook. Do not sign off this journey on the green regression count alone.

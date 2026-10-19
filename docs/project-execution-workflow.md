# Project execution workflow

The workflow coordinates the ERP's operational records; it does not duplicate them. A purchase order remains a purchase document, a receipt remains cashflow, a verified pack remains a Packaging List, and delivery remains a Shipment event. The project workflow reads those records, identifies the next work and applies auditable gates.

## Initial policy

Version 1 has one `standard_execution` workflow. New full and quick projects receive it automatically; an existing project can start it from its Workflow tab. The project coordinator is the workflow owner.

The path is:

1. project setup;
2. commercial baseline and production advance;
3. procurement per product;
4. current artwork approval per product;
5. PPS approval or approved not-applicable decision per product;
6. production per product;
7. quality control per product;
8. verified packing per product;
9. complete pre-dispatch balance and dispatch approval;
10. delivery reconciliation;
11. commercial closure;
12. feedback and final closure.

Products progress independently so partial production, packing and shipment remain possible.

## Calculated and reported state

`project_workflows` stores calculated stage/progress and coordinator-reported stage/progress separately. Weighted required steps produce the calculated value. A coordinator report requires a reason and cannot satisfy or bypass a gate. The command centre displays the variance.

## Commercial source

The coordinator explicitly selects the project's baseline Proforma Invoice. Printable `payment_terms` remain, while structured Proforma fields make policy enforceable:

- whether an advance is required;
- advance percentage;
- whether complete balance is required before dispatch;
- payment tolerance.

A project-level advance percentage overrides the baseline Proforma percentage. Receipts will be read from booked/reconciled Cashflow allocations. Complete pre-dispatch balance applies before the first shipment and is not prorated for a partial shipment.

## Gates and overrides

Gate evaluations are immutable snapshots with a content fingerprint. Material changes make an earlier result stale. A manager may override a blocked gate; the override records the risk snapshot, reason, approver, timestamp and optional expected resolution date. Revocation is additive and does not delete history.

Initial application permissions treat administrators as managers. Fine-grained override capabilities can be added without changing gate history.

## Client approvals

Artwork and PPS will use versioned portal approval requests. Requests remain open after their due date, show warnings and accept reminders until approved, rejected, revoked or superseded. An approval applies only to the submitted version; a new version makes it stale. A manager can override a missing approval without changing the client's actual decision.

## Closure

Managers may close an operationally complete project with receivables or payables outstanding. Such a project is `closed with outstanding financials`, not financially complete. Invoice, bill, cashflow and statement balances remain active.

## Foundation delivered

The first foundation slice contains workflow instances, product/project steps, gate evaluation and override audit tables, standard-path synchronisation, weighted calculation, structured Proforma payment terms, project-scoped workflow actions and a Workflow command-centre tab.

## Financial gate slice delivered

Commercial baseline, production advance and complete pre-dispatch balance are calculated from the selected Proforma and finalized receipts. An unchanged set of facts reuses its immutable SHA-256 evaluation; changed terms, totals or receipts create a new current evaluation and leave earlier decisions in history. Moving a product into production enforces the current advance gate. Moving a shipment to Picked up enforces the complete-balance gate.

Administrators can record or revoke manager overrides from the command centre. Each override remains attached to one exact evaluation, preserves its risk snapshot, reason, approver, time and expected resolution date, and makes the related workflow step explicitly `skipped` rather than pretending the underlying gate passed. When financial facts change, the prior override is stale and cannot release the new evaluation.

## Versioned client approvals delivered

Artwork and PPS approval requests are product-specific and available in both the internal Workflow command centre and the authenticated client portal. Every submitted file is hashed with SHA-256 and stored as an immutable numbered version. Approve, reject and request-changes decisions record the portal user, time and comment against that exact version.

Uploading a revision marks the previous decision superseded, reopens the request and moves its workflow step back to `waiting_client`. A passed decision completes the corresponding artwork/PPS step; rejection or requested changes blocks it. Response deadlines produce warnings but never close the request automatically.

Administrators can proceed under a version-scoped manager override. Overrides and revocations are normalized audit rows rather than fields overwritten by the next decision. A later client decision retires the active override without deleting it. Approval files cannot be deleted while referenced by this history.

## Product operational readiness delivered

The four operational steps are source-controlled and evaluated separately for every active project product:

- **Procurement** requires an assigned vendor and enough directly linked lines on sent, approved or billed Purchase Orders. Draft and cancelled documents never release the gate.
- **Production** reads the quantity completed plus a required operational evidence reference. Partial production remains visible and does not complete the product step.
- **Quality** is an append-only series of structured inspection attempts. Each attempt reconciles inspected quantity into passed, rejected and rework quantities, stores the checklist, inspector, time, evidence file SHA-256 and record fingerprint, and cannot pass more than the ordered product quantity cumulatively.
- **Packing** sums only Packaging List lines carrying the direct `project_product_id` whose list has a latest successful verification matching the list's current fingerprint. Editing packing content invalidates that evidence until verification runs again.

Invoice, purchase and shipment source lines retain project-product identity when copied into a Packaging List. This avoids guessing by product name or a generic catalogue product. QC and verified packing expose required, covered and remaining quantities in the Workflow command centre, so a partial quantity cannot falsely complete the whole product.

Every readiness refresh creates a new immutable gate evaluation only when its evidence changes. Existing manager override and revocation controls apply to these product-scoped failures, while the underlying failed quantities and evidence remain visible. Coordinators cannot manually mark procurement, production, quality or packing complete.

## Partial shipment and delivery synchronization delivered

Shipment lines linked to a project must retain a direct `project_product_id`; shipment quantities are never guessed by product name. The standard path now contains product-scoped dispatch and delivery steps plus a project-level delivery reconciliation step.

A product's dispatched quantity is the sum of its directly linked lines on picked-up, in-transit, held, delayed, out-for-delivery and delivered shipments. Its delivered quantity counts only delivered shipments. Planning and cancelled shipments contribute nothing. Each immutable evaluation records shipment line identity, shipment number, status, quantity, pickup date and delivery date. Partial quantities remain blocked with an explicit balance; excess quantities remain visible as warnings rather than being silently discarded.

Shipment creation, edits, tracking-status changes and deletion immediately recalculate readiness. Existing workflows are upgraded in place to version 2 of the same standard template, preserving their history while adding the two product steps. The project delivery reconciliation passes only when every active product is fully delivered or has a current manager override. Complete pre-dispatch financial balance remains mandatory before a project shipment can move from planning into any dispatched state.

## Dispatch allocation and authorization delivered

A project shipment must first be saved in Planning. Every shipment line is then allocated to one or more exact Packaging List item lines through normalized `shipment_packing_allocations`. Allocations may split one shipment line across several packs, and one verified pack may be consumed by several partial shipments. Planning allocations reserve quantity; cancelled shipments release it; once dispatch starts, shipment lines and allocations are frozen.

Dispatch authorization verifies the exact current payload against direct project-product identity, current successful Packaging List fingerprints, unconsumed packed quantity, cumulative QC-passed quantity, artwork/PPS workflow state and mandatory shipment paperwork. The dedicated Packaging List allocation satisfies the packing-list paperwork requirement without requiring a duplicate shipment-owned packing document. Database row locks serialize the final availability check so concurrent shipments cannot consume the same packed quantity.

Every authorization is an immutable `dispatch_approved` gate evaluation. A changed allocation, packing verification, document state or quantity produces a new fingerprint and makes an older manager override stale. Failed checks remain visible in the Workflow Command Centre and can use the existing reasoned manager override and revocation history. Planning packing edits remove affected reservations and immediately reopen authorization; packing already consumed by a dispatched shipment is protected from edit or deletion.

## Line-level delivery receipt and discrepancy reconciliation delivered

Shipment status and accepted delivery quantity are separate facts. Marking a shipment delivered no longer completes its product quantities by itself. Immutable delivery receipts record recipient, receipt time, POD evidence and SHA-256 fingerprint, with line-level received, accepted, short, damaged, rejected, returned and replacement quantities.

Each receipt reconciles `received = accepted + damaged + rejected`; replacement quantity is explicitly identified within accepted quantity. Shortage, damage, rejection and return require a reason. Returns cannot exceed cumulative accepted quantity. Cumulative net accepted quantity (`accepted - returned`) drives product delivery and project reconciliation gates. Replacement receipts can subsequently close an earlier discrepancy without deleting its history. Accepted quantity above the dispatched line remains recordable but produces a visible warning and blocked/excess evidence rather than silently rewriting dispatch.

Receipt writes lock the shipment and shipment lines, preserve evidence and actor identity, and immediately recalculate product and project delivery gates. The shipment page shows dispatched, net accepted and remaining quantity per line, open discrepancies, receipt history and evidence hashes. A receipt evidence file satisfies the shipment POD checklist. Shipments with delivery receipts cannot be deleted, preserving the operational audit trail.

## Financial closure and outstanding-financial exception delivered

Financial closure is calculated from source documents and ledger applications, never from a manually typed project balance. The immutable snapshot keeps six categories separate and groups each by document currency: client receivables, vendor payables, client advances, vendor advances, project-attributable unapplied inward cash and project-attributable unapplied outward cash. These categories are not netted against one another. Converted Proformas are excluded through the normal project-counting predicate, and Purchase Orders are excluded because only vendor bills create payables.

Every source row preserves document identity, state, total, applied amount, open amount, excess advance, update time and its exact direct-ledger/allocation sources. Unapplied cash rows preserve their allocation composition. The complete payload receives a SHA-256 fingerprint. Changes to invoices, bills, receipts, payments or allocation rows therefore produce a new current evaluation without rewriting an earlier closure decision.

Ordinary closure requires every other workflow step to be completed or formally overridden and every financial category to be reconciled. It records a `financially_complete` decision and applies the distinct `Financially Closed` project status. A coordinator cannot manually complete the commercial-closure step or assign either financial-closure status from a generic project form.

An administrator acting as manager may instead choose **Closed with Outstanding Financials**. The exception requires a substantive reason/follow-up statement, explicit acceptance that the balances remain live, and an expected resolution date. The decision records closer identity and time, exact risk snapshot and fingerprint, while the blocked workflow gate is represented by a manager override rather than being falsely marked passed. Invoice, bill and cashflow balances are never settled, cancelled or hidden by project closure.

The Workflow Command Centre displays all six categories, exact source detail, current fingerprint, stale-snapshot warnings and immutable decision history. Later settlement or allocation changes visibly stale the saved decision. A manager can review and resolve an exceptional closure as financially complete, or explicitly reopen either kind of closure; reopening adds another decision and project audit event rather than deleting prior history.

## Coordinator work queue delivered

The Coordinator Queue turns the standard workflow into a focused office worklist rather than repeating every incomplete workflow step. It selects one current action from each active execution lane. Project-level controls remain sequential; every product lane advances independently after shared project prerequisites; and a later project control is held back until all earlier product work is resolved. Completed, skipped and cancelled steps, terminal projects and merely future blocked gates stay out of the queue.

The default **My projects** scope uses the project's assigned coordinator, preserving that role as the central owner. Managers can switch to the team queue or filter one coordinator. Attention filters cover overdue, blocked, externally waiting, unassigned and ready work; schedule filters cover overdue, today, the next seven days and unscheduled work. Stage and full-text filters can narrow by project, client, product or action.

Each queue row shows project and client context, exact product lane, current source-controlled reason, coordinator, action owner and planned dates. Planning can update the central project coordinator, delegated action owner, planned start/completion and coordinator notes without pretending the workflow action itself passed. Every planning change writes a private project audit event with its before/after values. The action opens the Workflow Command Centre for the actual gate, approval or manual completion decision.

## Exceptions and escalation delivered

The exception register materializes actionable failures from the same source-controlled workflow rather than asking coordinators to type a second description of the problem. It detects current blocked or overdue workflow lanes, projects without a coordinator, current manager gate overrides, current client-approval overrides, and projects closed with outstanding financials. Stable source keys prevent duplicates; SHA-256 source fingerprints make a changed gate, deadline, owner or balance snapshot reopen the response because the previous plan is stale.

Each exception has a current operational state and a separate append-only event stream. Detection, source changes, acknowledgment, follow-up changes, automatic or manager escalation, reopening and automatic resolution retain actor, time, severity, escalation level and the exact source snapshot. Events cannot be edited or deleted. The current row may carry an owner, response plan and review deadline, but it never changes the invoice, gate, approval, delivery record or workflow evidence that created the exception.

Acknowledgment requires a follow-up owner, substantive response plan/evidence and future review time. Passing the initial response deadline escalates to level one and critical severity. Passing an acknowledged level-one review escalates to level two. Managers can explicitly escalate with a reason and next management review. Escalation also raises a critical Operations office alert linking to the exact exception.

There is deliberately no manual Resolve button. An exception resolves automatically only when its underlying source condition disappears—for example the blocked gate passes, an override is revoked, a coordinator is assigned, or an outstanding-financial closure is completed. If the same source becomes active again, the existing exception reopens and keeps its full history. The register provides active, unacknowledged, follow-up, resolved and all-history views with severity, category, owner and search filters.

## Richer Workflow Command Centre matrix delivered

The Workflow Command Centre now opens with an execution-control matrix that keeps the project control lane and every active product lane separate. Columns follow the complete standard path from setup and commercial readiness through procurement, approvals, production, QC, packing, dispatch, delivery and closure. Product rows therefore advance independently without collapsing the project into one misleading status.

Every matrix cell is derived from the actual workflow step plus its latest gate evaluation or current approval version. It distinguishes completed, manager-overridden, blocked, waiting, in-progress, ready, not-yet-reached and not-applicable states. An override is never painted as a pass. Current lane actions come from the same dependency-aware selection used by the Coordinator Queue, and active source exceptions are linked from the affected control.

Operational cells show covered versus required quantity and a progress meter for purchase coverage, production, QC-passed quantity, verified packing, dispatch and net accepted delivery. Commercial cells show received versus required money in the project currency. Project delivery reconciliation shows delivered versus active product count. Approval cells expose the current version, effective decision, due date and overdue warning. Evaluated controls retain their check time and abbreviated source fingerprint in the matrix.

Stage headers show cleared/applicable lane counts (with override cells still visibly distinct from passes), while each row shows its coordinator and lane completion percentage. Blocked or waiting lanes are visibly marked as risk. The matrix is intentionally horizontally scrollable, with the execution-lane identity pinned, so the complete workflow remains comparable on desktop and usable on smaller screens without hiding stages.

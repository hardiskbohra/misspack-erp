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

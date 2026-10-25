# ERP test strategy

## 1. Purpose and contracts

Protect money, permission boundaries, product execution and historical evidence across the ERP. Derive expectations from the application services/controllers, the workflow/UI documentation and the owner's explicit business decisions. Proposed tests are reviewed acceptance contracts, not claims that all desired behaviour already exists.

- **P0:** financial correctness, cross-user/client/vendor privacy, destructive operations, authoritative workflow gates, secure files and audit.
- **P1:** normal operational CRUD, reporting, filters, configuration, document consistency and accessibility.
- **P2:** cosmetic variations and lower-risk compatibility, once P0/P1 are stable.

A severity is not a test result. A planned P0 case is an uncovered release risk even if all currently implemented tests pass.

## 2. Test layers

| Layer | Boundary | Real dependencies | Must prove |
|---|---|---|---|
| Pure unit | One deterministic rule/calculator | None; unsaved models/value objects acceptable | Exact amounts, dates, booleans, transitions and boundary errors |
| Component unit | A service/model rule requiring Laravel boot | Container/validation/casts; no persisted business data unless explicitly stated | Application contract and input normalization |
| Integration | HTTP → middleware → controller/service → persistence, or interacting services | Actual disposable DB, routes, fake filesystem; only external delivery mocked | Permissions, DB rows, rollbacks, ownership, idempotence and derived values |
| Native database | Production SQL dialect/schema | MySQL 8 service; real transactions/constraints | DATE_FORMAT/YEAR, migrations, decimal constraints and locking |
| Browser/system | Actual Blade/Select2/drawers/print and multiple workflows | Browser + isolated application | Interactive state, keyboard access, print geometry and user-visible reconciliation |

The current `tests/Unit` directory includes framework-bootstrapped component tests. A test in `Feature` can verify service integration; file names do not transform it into a pure unit test. Source-reading Node checks validate declarations/structure and cannot prove transactional correctness. Existing browser checks from earlier tasks are historical evidence, not new executions in this task.

## 3. Financial invariants

1. Each receipt is counted once, even if represented by allocations, an opening-paid field and a derived vendor mirror.
2. Allocation cannot consume more than the active source-currency remainder. FX source and document amounts are distinct, explicit and conserved.
3. INR, USD and RMB amounts are never silently added as one currency total.
4. Cash invoices exclude GST from the actual claim, not merely its printed columns.
5. Partial PI conversion leaves the PI open. The owner chooses transferred advance/receipt amount; transfers are capped by the child claim and availability, cash-first where configured.
6. For PI ₹3,00,000, cash receipt ₹1,00,000 and bank receipt ₹50,000, a ₹1,00,000 cash invoice receives at most ₹1,00,000; ₹50,000 remains with the PI.
7. The remaining tax invoice preserves original item identity/quantity/metadata with proportional rates and reconciled discount/tax/rounding, without rebilling cash claims.
8. Reversal is atomic and auditable. Failed audit/receipt/model updates leave the original graph unchanged. Retrying must not restore credit twice or reuse a historical document number.
9. A cancelled/superseded document cannot remain a second receivable, while genuine client credit/refunds remain traceable.
10. Cash/bank transfers conserve funds across both legs and are excluded from income/expense classification.

## 4. Operational invariants

- One standard execution workflow; project status, stage and progress are authoritative workflow caches, not ordinary form-editable fields.
- Product lanes progress independently and support partial shipments. Starting/syncing workflow is idempotent and preserves completed work.
- Packing verification is automatic. Identity discrepancies show both actual project product and linked packing product; invalid linkage is never silently approved.
- Dispatch needs the full pre-dispatch financial balance plus relevant QC, approvals, packing and documents. Manager overrides require the sensitive grant and recorded reason/evidence.
- Dispatch documents belong to the Project; shipment show supports direct upload. Receipt/POD evidence may be optional while receipt quantities remain valid.
- Financial/cancellation terminal decisions survive workflow recalculation.
- Closure, exception and feedback gates use current evidence; changing upstream facts must not leave stale approvals silently effective.

## 5. Security boundaries

Test every role/surface with at least: guest, full office, custom reader, custom manager, employee owner/non-owner, client owner/non-owner, vendor owner/non-owner, disabled portal account and revoked/expired public token. Hiding a button is not authorization.

For each sensitive record class test read, list/search, edit, mutation, download, print and export; vary both parent and child IDs. Assert the absence of foreign bytes, snippets and rows, not just a response status. Private notes remain private even from another office administrator. Payroll self-service cannot write role, permissions, pay, bank or designation fields.

Use the generated route inventory to identify endpoints, but verify policy through HTTP and ownership fixtures. Middleware registration alone does not prove model-binding order, record isolation or correct policy decisions. Test unsafe methods with CSRF middleware **explicitly enabled** when verifying CSRF; Laravel's test defaults bypass that check. Existing cash reversal and shipment-upload regressions already exercise that boundary.

Files require both the shared extension/size policy and controller MIME/content handling. An extension-only helper test is not proof that renamed executable content is safe. Validate download ownership, missing-file behaviour, storage path safety and failed-transaction cleanup.

## 6. Data and environment

Use [fixtures.md](fixtures.md), fake storage/queues/notifications and deterministic clocks. Use real transactions for internal writes; do not mock the service whose arithmetic or transaction is being tested. Never send live email, SMS, banking calls or portal invitations. Use reserved `example.test` contacts.

The default fixture trait migrates **only specified tables** in memory. This is faster and intentional, but does not establish that all migrations work together. The MySQL CI job supplies a fresh schema check separately. Shared-DB parallelisation is prohibited until each process has its own database/storage namespace; no parallel-test speed claim is made here.

Concurrency tests use two distinct native DB connections/processes and a barrier. Two sequential HTTP calls are only retry/stale-model tests, not evidence of row-lock safety. Verify receipt allocation caps, invoice numbering, duplicate conversion/posting and settings conflicts under real overlap.

## 7. Non-functional design gates (not executed in this hand-off)

- **Performance:** measure query growth with 10 versus 100/1000 documents, plus representative production-shaped data. Preserve existing bounded-query summary tests; set latency budgets only after measuring a named environment. Do not invent a p95 SLA.
- **Accessibility:** keyboard-only drawer/Select2/modal/confirmation flows; focus restoration; labelled validation errors; meaningful badges and contrasts in light/dark themes.
- **Responsive/print:** 1440/1024/768/390/320 widths; local scrolling; A4 portrait/landscape; A5 and both requested thermal sizes; no recipient/sender identity mix-up.
- **Resilience:** missing evidence files, DB deadlock/constraint failure, audit failure, aborted requests, repeated posting and stale browser/route models.
- **Upgrade:** fresh install plus upgrade from a representative previous schema, backup/restore rehearsal and historical financial reconciliation. Fresh migration success is not upgrade/rollback safety.
- **External integrations:** fake notifications locally; verify provider contracts only against approved sandboxes if/when a provider is configured.

## 8. Entry and exit criteria

**Entry:** accepted business rule; linked case ID; reviewed deterministic fixtures; disposable environment; reproduction of defect or new requirement; no live secrets.

**PR exit:** a meaningful red test was observed for new behaviour (or tests explicitly labelled retrospective characterization); focused and neighbouring suites green; no skipped financial/security assertion hidden; exact expected values and persisted negative-side-effect checks; source/docs exports regenerated; code review; commit and push.

**Release exit:** all implemented gates green including native DB; every P0 planned case either automated/executed or explicitly risk-accepted by the owner; native concurrency and core system journeys executed; no unapproved financial defect; restore and permissions review complete. No blanket coverage percentage is asserted until real runtime coverage is collected and reviewed.

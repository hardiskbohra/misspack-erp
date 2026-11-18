# Shipment status: advisory Proforma balance

## Decision and acceptance criteria

The original decision made a missing, unusable or unpaid pre-dispatch Proforma balance non-blocking for an otherwise valid shipment status or tracking-history change. The updated screenshot request also made missing packing, QC, Artwork/PPS approvals and documents advisory; see `shipment-readiness-warning.md`. Product identity and quantity, as well as permissions, stale-list protection, date checks and hold/delay remarks, remain independent controls.

- On an otherwise permitted transition from Planning to a dispatched status, evaluate the current project balance using the existing workflow service. Save the shipment/history normally and show an accessible, non-blocking payment warning after the redirect when the balance is outstanding or cannot be verified. Do not require acknowledgement or a manager payment override.
- Identify the shipment and warn when a balance is outstanding. Only users with sales or project viewing permission see the known amount and currency; operations-only users see a generic warning directing them to Accounts. Financial-view access is checked both when the detail is recorded and when the redirect renders. If no usable commercial baseline exists, say it could not be verified rather than claiming money is due. A passed balance produces no payment warning. An existing manager override does not make an unpaid balance disappear.
- Preserve the underlying blocked financial evaluation, invoice balances, payment records and any real overrides. Never mark a balance paid or create an override solely because a shipment status was saved. Other project steps and financial closure continue to use the actual financial facts.
- Cover the list modal, tracking detail and shipment edit routes. History edits/deletions that derive a dispatch status retain the independent identity and valid-quantity controls, while also warning for missing packing, QC, artwork/PPS approvals or documents.
- Keep success and payment warnings distinct so an existing delivery-paperwork warning is not lost. Warning text must be escaped and available without JavaScript.

## Regression design

- **IT-SHIP-015:** cover unpaid and fully paid balance, missing/cancelled baseline, manager override, list/detail/edit status changes, existing notification/history behaviour, reader authorization, and unpaid amounts that remain on the invoice.
- Verify an unpaid balance alone does not require a payment override. Invalid project-product identity and quantity still require their independent requirements or a valid administrator override; history edit/delete paths must not silently circumvent them. Missing packing, QC, artwork/PPS approvals and documents are now warnings, and approvals remain unresolved.
- Cover hold/delay and date validation, stale list submissions, shipment targeting and unchanged status behaviour. `tools/checks/shipment-payment-warning-browser.cjs` and its guarded fictional seeder describe a visible warning after saving at four desktop/mobile widths and with JavaScript disabled, without a blocking overlay.
- `MySqlShipmentPaymentWarningTest` and its guarded worker describe one ordered race in which an invoice update commits while dispatch waits for the shared project lock; the warning must use those committed facts. This is not exhaustive concurrency coverage: some receipt writers post separately before reevaluating the workflow, so the warning is a point-in-time snapshot rather than a reservation or a guarantee against every concurrent payment.

## Verification status

The original payment-only change was submitted without local runtime checks, as recorded at the time. The user subsequently authorized verification of the readiness-warning follow-up: the revised payment cases passed in the full portable and native MySQL suites, and the guarded Chromium payment-warning check passed at desktop/mobile widths and without JavaScript. See [shipment-readiness-warning.md](shipment-readiness-warning.md) for commands, counts and limitations. These are retrospective results, not a test-first Red/Green or deployment claim.

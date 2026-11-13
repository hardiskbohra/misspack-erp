# Shipment status: advisory Proforma balance

## Decision and acceptance criteria

The user selected **Proforma balance only**: a missing, unusable or unpaid pre-dispatch Proforma balance must not itself prevent an otherwise valid shipment status or tracking-history change. Packing, quantity, QC, document and approval authorization, as well as permissions, stale-list protection, date checks and hold/delay remarks, remain enforced.

- On an otherwise permitted transition from Planning to a dispatched status, evaluate the current project balance using the existing workflow service. Save the shipment/history normally and show an accessible, non-blocking payment warning after the redirect when the balance is outstanding or cannot be verified. Do not require acknowledgement or a manager payment override.
- Identify the shipment and warn when a balance is outstanding. Only users with sales or project viewing permission see the known amount and currency; operations-only users see a generic warning directing them to Accounts. Financial-view access is checked both when the detail is recorded and when the redirect renders. If no usable commercial baseline exists, say it could not be verified rather than claiming money is due. A passed balance produces no payment warning. An existing manager override does not make an unpaid balance disappear.
- Preserve the underlying blocked financial evaluation, invoice balances, payment records and any real overrides. Never mark a balance paid or create an override solely because a shipment status was saved. Other project steps and financial closure continue to use the actual financial facts.
- Cover the list modal, tracking detail and shipment edit routes. History edits/deletions that derive a dispatch status must retain dispatch-authorization safeguards, rather than using the advisory payment check to bypass packing, QC, documents or approvals.
- Keep success and payment warnings distinct so an existing delivery-paperwork warning is not lost. Warning text must be escaped and available without JavaScript.

## Regression design

- **IT-SHIP-015:** cover unpaid and fully paid balance, missing/cancelled baseline, manager override, list/detail/edit status changes, existing notification/history behaviour, reader authorization, and unpaid amounts that remain on the invoice.
- Verify an unpaid balance alone does not require a payment override, while unrelated dispatch authorization is still refused until its independent requirements or a valid administrator override are met. History edit/delete paths must not silently circumvent that authorization.
- Cover hold/delay and date validation, stale list submissions, shipment targeting and unchanged status behaviour. `tools/checks/shipment-payment-warning-browser.cjs` and its guarded fictional seeder describe a visible warning after saving at four desktop/mobile widths and with JavaScript disabled, without a blocking overlay.
- `MySqlShipmentPaymentWarningTest` and its guarded worker describe one ordered race in which an invoice update commits while dispatch waits for the shared project lock; the warning must use those committed facts. This is not exhaustive concurrency coverage: some receipt writers post separately before reevaluating the workflow, so the warning is a point-in-time snapshot rather than a reservation or a guarantee against every concurrent payment.

## Verification status

The user requested a commit and push after being told that tests had not been run, without requesting local test execution. New/updated regression cases remain **unexecuted locally**; no test-first Red, Green, browser, native-MySQL or deployment claim is made. The modified PHP files and browser seeder were syntax-checked using PHP 8.4.21, the modified JavaScript was syntax-checked with Node, and the catalogue was regenerated (generation is not test execution). The repository's full portable, contract, native-database and browser verification remains necessary before treating the change as locally verified.

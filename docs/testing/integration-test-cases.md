# Integration test cases

Generated from [catalogue.json](catalogue.json); edit the JSON and run `npm run test:catalogue:build`.

**Implementation links are not a passing result.** Unlinked cases are planned. Partial evidence does not cover every step. Unit targets linked only to component evidence remain unclaimed as isolated unit coverage.

## Authentication and office permissions (`AUTH`)

Sources: `app/Services/PermissionMatrix.php`, `app/Http/Controllers/AuthController.php`, `app/Http/Middleware/EnsureUserIsAdmin.php`.

### IT-AUTH-001 — Guests cannot enter office modules

**Priority:** P0 · **Requirement:** REQ-AUTH · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY; empty disposable database for guest/employee boundary checks
- **Data:** Named module index URLs; no authenticated session
1. GET every URL in officePages provider

**Expected:** Redirect to login before controller/database business queries run.

**Evidence:** `tests/Integration/OfficeAccessBoundaryTest.php#test_guest_cannot_read_office_modules`

### IT-AUTH-002 — Employee is contained in own workspace

**Priority:** P0 · **Requirement:** REQ-AUTH · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY; empty disposable database for guest/employee boundary checks
- **Data:** Employee E; office module index URLs
1. Sign in E
2. GET each office page

**Expected:** Redirect to my.dashboard; no office data disclosed.

**Evidence:** `tests/Integration/OfficeAccessBoundaryTest.php#test_employee_is_redirected_before_an_office_controller_can_run`

### IT-AUTH-003 — Reader cannot cross finance or write sales

**Priority:** P0 · **Requirement:** REQ-AUTH · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY; empty disposable database for guest/employee boundary checks
- **Data:** Custom user R with sales.view
1. GET cashflows.index
2. POST clients.store

**Expected:** Both return 403; no client created.

**Evidence:** `tests/Integration/OfficeAccessBoundaryTest.php#test_sales_reader_cannot_read_finance_or_write_sales`

## People, payroll and employee documents (`PEOPLE`)

Sources: `app/Services/EmployeeAccess.php`, `app/Services/EmployeeProfile.php`, `app/Services/PayslipDocument.php`.

### IT-PEOPLE-001 — Office creates and employee reads own payslip

**Priority:** P0 · **Requirement:** REQ-PEOPLE · **Implementation:** planned

- **Preconditions:** F-IDENTITY; employee E, other employee B, office A; fake private document storage
- **Data:** Payslip for E; actor E then B
1. Create salary/payslip
2. Download as E
3. Attempt as B

**Expected:** E receives correct slip; B gets denial/redirect without salary leakage.

### IT-PEOPLE-002 — Forged profile fields cannot escalate

**Priority:** P0 · **Requirement:** REQ-PEOPLE · **Implementation:** planned

- **Preconditions:** F-IDENTITY; employee E, other employee B, office A; fake private document storage
- **Data:** E posts mobile plus role=admin and bank_account_number
1. Update self-service profile
2. Reload record

**Expected:** Permitted contact field changes; role and bank remain unchanged.

### IT-PEOPLE-003 — Document verification is office-only

**Priority:** P0 · **Requirement:** REQ-PEOPLE · **Implementation:** planned

- **Preconditions:** F-IDENTITY; employee E, other employee B, office A; fake private document storage
- **Data:** E document; E and office A sessions
1. Upload as E
2. Attempt verify as E
3. Verify as A

**Expected:** Upload belongs to E; verification requires office authority and records verifier.

## Client master, KYC and account profile (`CLIENT`)

Sources: `app/Http/Controllers/ClientController.php`, `app/Services/ClientAccountSummary.php`, `app/Models/Client.php`.

### IT-CLIENT-001 — KYC approval uses correct client token

**Priority:** P1 · **Requirement:** REQ-CLIENT · **Implementation:** planned

- **Preconditions:** F-IDENTITY and F-MONEY; two separate clients C1 and C2
- **Data:** C1 pending KYC; valid and invalid public tokens
1. Submit C1 KYC
2. Approve as office
3. Try invalid token

**Expected:** Correct client updated; invalid token rejected; review actor/time retained.

### IT-CLIENT-002 — Payment summary isolates client and is read-only

**Priority:** P1 · **Requirement:** REQ-CLIENT · **Implementation:** partial evidence

- **Preconditions:** F-IDENTITY and F-MONEY; two separate clients C1 and C2
- **Data:** C1 and C2 invoices/receipts; sales reader
1. GET C1 payment-summary
2. Compare persisted money rows

**Expected:** C1-only totals; private/no-store response; no allocation or invoice mutation.

**Evidence:** `tests/Feature/ClientAccountSummaryTest.php#test_endpoint_and_profile_use_same_escaped_summary_with_no_store_response`

### IT-CLIENT-003 — Projects tab respects detail permission

**Priority:** P1 · **Requirement:** REQ-CLIENT · **Implementation:** planned

- **Preconditions:** F-IDENTITY and F-MONEY; two separate clients C1 and C2
- **Data:** C1 projects; sales.view without projects.view
1. GET client profile and Projects tab

**Expected:** Aggregate count allowed; project details/links protected; C2 projects excluded.

### IT-CLIENT-004 — Unlinked refund reduces on-account advance

**Priority:** P0 · **Requirement:** REQ-CLIENT · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY and F-MONEY; two separate clients C1 and C2
- **Data:** Client advance1000; refund300 without invoice link
1. Build summary

**Expected:** Advance700; no fabricated invoice receipt.

**Evidence:** `tests/Feature/ClientAccountSummaryTest.php#test_on_account_advance_is_reduced_by_unlinked_client_refund`

### IT-CLIENT-005 — Cancelled invoice receipts remain client credit

**Priority:** P0 · **Requirement:** REQ-CLIENT · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY and F-MONEY; two separate clients C1 and C2
- **Data:** Cancelled invoice with received cash
1. Build summary

**Expected:** No cancelled invoice claim; receipt retained as client credit.

**Evidence:** `tests/Feature/ClientAccountSummaryTest.php#test_payments_on_cancelled_invoice_remain_client_credit_but_not_an_invoice_claim`

### IT-CLIENT-006 — Summary query count remains bounded

**Priority:** P0 · **Requirement:** REQ-CLIENT · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY and F-MONEY; two separate clients C1 and C2
- **Data:** Same client with small and large invoice sets
1. Measure queries for both snapshots

**Expected:** No per-invoice query growth.

**Evidence:** `tests/Feature/ClientAccountSummaryTest.php#test_summary_query_count_does_not_grow_with_invoice_count`

### IT-CLIENT-007 — FX advance residue remains in bank currency

**Priority:** P0 · **Requirement:** REQ-CLIENT · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY and F-MONEY; two separate clients C1 and C2
- **Data:** FX allocation partly applied to document currency
1. Build currency buckets

**Expected:** Document amount applied in document currency; only source remainder in bank currency.

**Evidence:** `tests/Feature/ClientAccountSummaryTest.php#test_fx_allocations_use_document_currency_and_leave_only_remainder_in_bank_currency`

## Vendor master and payable profile (`VENDOR`)

Sources: `app/Http/Controllers/VendorController.php`, `app/Models/Vendor.php`, `app/Services/VendorPaymentCashflowSync.php`.

### IT-VENDOR-001 — Vendor payment links exact bill and party

**Priority:** P1 · **Requirement:** REQ-VENDOR · **Implementation:** planned

- **Preconditions:** F-MONEY; vendors V1 and V2 with separate bills and payments
- **Data:** V1 bill; payment posted to V1
1. Save payment
2. Inspect bill and linked cashflow

**Expected:** One active payment and one consistent ledger effect; no V2 change.

### IT-VENDOR-002 — Cancellation reverses payable state

**Priority:** P1 · **Requirement:** REQ-VENDOR · **Implementation:** partial evidence

- **Preconditions:** F-MONEY; vendors V1 and V2 with separate bills and payments
- **Data:** V1 paid bill and linked ledger receipt
1. Cancel vendor payment
2. Reload bill and cashflow

**Expected:** Ledger effect reversed once; bill balance reopens correctly.

**Evidence:** `tests/Feature/VendorCashflowDeletionTest.php#test_deleting_mirror_cancels_payment_and_reopens_bill_with_audit_and_bank_balance`

### IT-VENDOR-003 — Vendor records require purchase permissions

**Priority:** P1 · **Requirement:** REQ-VENDOR · **Implementation:** planned

- **Preconditions:** F-MONEY; vendors V1 and V2 with separate bills and payments
- **Data:** Sales-only office user
1. GET vendor
2. Attempt vendor update

**Expected:** Permission denied; vendor fields unchanged.

## Products and price calculator (`PRODUCT`)

Sources: `app/Http/Controllers/ProductController.php`, `app/Http/Controllers/PriceCalculatorController.php`.

### IT-PRODUCT-001 — Product creation and update preserve identity

**Priority:** P1 · **Requirement:** REQ-PRODUCT · **Implementation:** planned

- **Preconditions:** Two products with distinct IDs/SKUs, units, INR/USD prices and optional media
- **Data:** Unique SKU-A and SKU-B
1. Create product
2. Edit SKU-A
3. Reload references

**Expected:** One intended master changes; references remain attached to correct ID.

### IT-PRODUCT-002 — Price calculator applies chosen currency and unit

**Priority:** P1 · **Requirement:** REQ-PRODUCT · **Implementation:** planned

- **Preconditions:** Two products with distinct IDs/SKUs, units, INR/USD prices and optional media
- **Data:** INR and USD product variants
1. Calculate each separately
2. Compare persisted products

**Expected:** Correct unit/currency displayed; calculator does not silently alter master prices.

### IT-PRODUCT-003 — Product deletion does not corrupt historical documents

**Priority:** P1 · **Requirement:** REQ-PRODUCT · **Implementation:** planned

- **Preconditions:** Two products with distinct IDs/SKUs, units, INR/USD prices and optional media
- **Data:** Product referenced by PI/project/packing item
1. Attempt deletion
2. Reopen historical documents

**Expected:** Reference policy enforced; history remains readable.

## Leads and sales follow-up (`LEAD`)

Sources: `app/Http/Controllers/LeadController.php`, `app/Http/Controllers/LeadCommentController.php`, `app/Http/Controllers/PublicLeadController.php`.

### IT-LEAD-001 — Public lead submission validates required details

**Priority:** P1 · **Requirement:** REQ-LEAD · **Implementation:** planned

- **Preconditions:** Two leads with different owners, stages and next follow-up dates
- **Data:** Valid enquiry plus empty/invalid-contact variants
1. Submit public enquiry
2. Read office queue

**Expected:** Valid lead created once; invalid input returns field errors without partial record.

### IT-LEAD-002 — Follow-up comment records actor and lead

**Priority:** P1 · **Requirement:** REQ-LEAD · **Implementation:** planned

- **Preconditions:** Two leads with different owners, stages and next follow-up dates
- **Data:** Lead L1; office sales manager
1. Add comment
2. Reload timeline

**Expected:** Correct lead and author; unrelated L2 unchanged.

### IT-LEAD-003 — Sales readers cannot change lead outcome

**Priority:** P1 · **Requirement:** REQ-LEAD · **Implementation:** planned

- **Preconditions:** Two leads with different owners, stages and next follow-up dates
- **Data:** sales.view only
1. Read lead
2. POST update/stage change

**Expected:** Read permitted; write denied and original lead state retained.

## Project master and product lanes (`PROJECT`)

Sources: `app/Http/Controllers/ProjectController.php`, `app/Services/ProjectProducts.php`, `app/Models/Project.php`.

### IT-PROJECT-001 — Project product edits preserve snapshot identity

**Priority:** P1 · **Requirement:** REQ-PROJECT · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; client C1; project P1 with product A and B
- **Data:** P1/A and P1/B with different product IDs
1. Edit A description
2. Reopen product lanes

**Expected:** Only intended lane changes; B and existing allocations unchanged.

### IT-PROJECT-002 — Project attachment belongs to project

**Priority:** P1 · **Requirement:** REQ-PROJECT · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; client C1; project P1 with product A and B
- **Data:** Project P1; fake shipping bill
1. Upload dispatch document
2. Reopen project/shipment checklist

**Expected:** File attached to P1, not orphaned on shipment; metadata retained.

### IT-PROJECT-003 — Deleting project follows archival safeguards

**Priority:** P1 · **Requirement:** REQ-PROJECT · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; client C1; project P1 with product A and B
- **Data:** Project with linked invoices and shipment history
1. Request delete
2. Inspect linked records

**Expected:** History retained according to archival policy; no dangling document ownership.

## Execution, approvals, gates and financial closure (`WORKFLOW`)

Sources: `app/Services/ProjectWorkflowExecution.php`, `app/Services/ProjectWorkflowGates.php`, `app/Services/ProjectFinancialClosure.php`.

### IT-WORKFLOW-001 — Starting workflow twice is idempotent

**Priority:** P0 · **Requirement:** REQ-WORKFLOW · **Implementation:** linked executable evidence

- **Preconditions:** F-PRODUCTS and F-MONEY; workflow standard_execution; approver A and non-override reader R
- **Data:** One active product and one cancelled product
1. Start
2. Start again
3. Count lanes/steps

**Expected:** One workflow; sixteen steps for one active product; no lane for cancelled product.

**Evidence:** `tests/Integration/ProjectWorkflowExecutionTest.php#test_start_is_idempotent_and_cancelled_products_do_not_get_new_lanes`

### IT-WORKFLOW-002 — Product execution is independent and survives resync

**Priority:** P0 · **Requirement:** REQ-WORKFLOW · **Implementation:** linked executable evidence

- **Preconditions:** F-PRODUCTS and F-MONEY; workflow standard_execution; approver A and non-override reader R
- **Data:** Two product lanes A and B
1. Complete A production
2. Start/sync workflow again

**Expected:** A completion/notes retained; B production remains incomplete; progress between 0 and 100.

**Evidence:** `tests/Integration/ProjectWorkflowExecutionTest.php#test_product_lanes_are_independent_and_repeated_sync_preserves_completed_work`

### IT-WORKFLOW-003 — Terminal financial/cancellation decision survives recalculation

**Priority:** P0 · **Requirement:** REQ-WORKFLOW · **Implementation:** linked executable evidence

- **Preconditions:** F-PRODUCTS and F-MONEY; workflow standard_execution; approver A and non-override reader R
- **Data:** Existing cancelled project in closure stage
1. Recalculate workflow

**Expected:** Cancelled status and closure stage preserved, not reopened by step calculation.

**Evidence:** `tests/Integration/ProjectWorkflowExecutionTest.php#test_financial_terminal_decision_is_not_overwritten_by_workflow_recalculation`

### IT-WORKFLOW-004 — Dispatch gate totals every cash child

**Priority:** P0 · **Requirement:** REQ-WORKFLOW · **Implementation:** linked executable evidence

- **Preconditions:** F-PRODUCTS and F-MONEY; workflow standard_execution; approver A and non-override reader R
- **Data:** PI with multiple partial cash children and receipts
1. Evaluate workflow financial gates

**Expected:** Gate sees entire settlement family, not just last converted pointer.

**Evidence:** `tests/Feature/CashInvoiceConversionTest.php#test_workflow_gates_include_every_cash_child_not_just_the_last_converted_invoice`

### IT-WORKFLOW-005 — Cash reversal restores baseline and re-evaluates dispatch

**Priority:** P0 · **Requirement:** REQ-WORKFLOW · **Implementation:** linked executable evidence

- **Preconditions:** F-PRODUCTS and F-MONEY; workflow standard_execution; approver A and non-override reader R
- **Data:** Workflow baseline points to cash child
1. Reverse child
2. Re-read commercial baseline and gate

**Expected:** PI baseline restored; dispatch balance recalculated.

**Evidence:** `tests/Feature/CashInvoiceDeletionTest.php#test_workflow_cash_baseline_is_restored_to_the_pi_and_dispatch_balance_is_rechecked`

## Quality inspection and sampling (`QC`)

Sources: `app/Http/Controllers/ProjectQualityInspectionController.php`, `app/Services/ProjectOperationalReadiness.php`.

### IT-QC-001 — QC recorded from Action Centre attaches to correct lane

**Priority:** P0 · **Requirement:** REQ-QC · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; A quantity 100 and B quantity 200; optional fake QC attachment
- **Data:** P1/A sample 10 of 100
1. Open workflow QC action
2. Record result
3. Inspect lane B

**Expected:** Inspection belongs to A and updates only relevant readiness; B unchanged.

### IT-QC-002 — Failed lot blocks dispatch until resolved

**Priority:** P0 · **Requirement:** REQ-QC · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; A quantity 100 and B quantity 200; optional fake QC attachment
- **Data:** A failed inspection then corrected passing inspection
1. Attempt dispatch before and after valid resolution

**Expected:** Before blocked; after current accepted inspection and other gates permit progression.

### IT-QC-003 — Non-manager cannot bypass failed QC

**Priority:** P0 · **Requirement:** REQ-QC · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; A quantity 100 and B quantity 200; optional fake QC attachment
- **Data:** R without projects.override
1. Submit override request

**Expected:** Denied; original inspection and gate evidence unchanged.

## Packaging lists, verification and print labels (`PACK`)

Sources: `app/Http/Controllers/PackagingListController.php`, `app/Services/PackagingListVerifier.php`, `app/Services/PackagingListProjectComparison.php`.

### IT-PACK-001 — Confirmation runs automatic verification

**Priority:** P0 · **Requirement:** REQ-PACK · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; packing groups and carton sizes; sender MissPack or third party
- **Data:** Draft list tied to P1; valid product quantities
1. Confirm packing list
2. Read verification and workflow

**Expected:** Automatic checks run; workflow uses confirmed verified quantities; no manual verification step required.

### IT-PACK-002 — Packing mismatch is actionable rather than opaque

**Priority:** P0 · **Requirement:** REQ-PACK · **Implementation:** partial evidence

- **Preconditions:** F-PRODUCTS; packing groups and carton sizes; sender MissPack or third party
- **Data:** P1/A versus list/B
1. Open comparison and attempt confirmation

**Expected:** Both actual identities visible; invalid allocation not silently approved.

**Evidence:** `tests/Feature/PackagingProjectComparisonTest.php#test_comparison_shows_the_actual_packing_catalog_and_linked_project_product`

### IT-PACK-003 — Print options remain per list and independent

**Priority:** P0 · **Requirement:** REQ-PACK · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; packing groups and carton sizes; sender MissPack or third party
- **Data:** 100x140 and 100x150 thermal sizes; A4 orientation; custom columns
1. Save print settings
2. Render each print mode

**Expected:** Both thermal options available; selected orientation/columns/header/footer/page numbers respected.

### IT-PACK-004 — Foreign project mapping is refused on save

**Priority:** P0 · **Requirement:** REQ-PACK · **Implementation:** linked executable evidence

- **Preconditions:** F-PRODUCTS; packing groups and carton sizes; sender MissPack or third party
- **Data:** Packing row with product belonging to another project
1. Submit row mapping
2. Inspect persisted list

**Expected:** Validation error; no invalid cross-project product linkage.

**Evidence:** `tests/Feature/PackagingProjectComparisonTest.php#test_cancelled_or_foreign_project_selections_are_rejected_by_the_save_endpoint`

### IT-PACK-005 — Comparison escapes names and does not mutate

**Priority:** P0 · **Requirement:** REQ-PACK · **Implementation:** linked executable evidence

- **Preconditions:** F-PRODUCTS; packing groups and carton sizes; sender MissPack or third party
- **Data:** Names containing markup; matching comparison
1. Render comparison
2. Inspect HTML and DB

**Expected:** Names escaped; comparison is read-only.

**Evidence:** `tests/Feature/PackagingProjectComparisonTest.php#test_matched_identical_names_pass_without_review_and_comparison_does_not_change_records`; `tests/Feature/PackagingProjectComparisonTest.php#test_names_are_escaped_and_lists_without_a_project_do_not_display_a_false_check`

## Shipments, dispatch and delivery (`SHIP`)

Sources: `app/Http/Controllers/ShipmentController.php`, `app/Services/ShipmentDispatchAuthorization.php`, `app/Services/ShipmentDocuments.php`.

### IT-SHIP-001 — Shipment view uploads directly to project

**Priority:** P0 · **Requirement:** REQ-SHIP · **Implementation:** planned

- **Preconditions:** F-PRODUCTS and F-MONEY; partial shipments S1/S2; fake public upload disk
- **Data:** S1 linked to P1; fake shipping bill
1. Upload from shipment show
2. Open project documents

**Expected:** No Edit detour; document attached to P1 and visible in checklist.

### IT-SHIP-002 — Partial shipments reconcile independently

**Priority:** P0 · **Requirement:** REQ-SHIP · **Implementation:** planned

- **Preconditions:** F-PRODUCTS and F-MONEY; partial shipments S1/S2; fake public upload disk
- **Data:** A100; dispatch/deliver 40 then 60
1. Record S1 and S2
2. Recalculate product/project delivery

**Expected:** No over-allocation; A reaches 100 once; unrelated B remains open.

### IT-SHIP-003 — Dispatch denied when packing/QC/balance missing

**Priority:** P0 · **Requirement:** REQ-SHIP · **Implementation:** planned

- **Preconditions:** F-PRODUCTS and F-MONEY; partial shipments S1/S2; fake public upload disk
- **Data:** Confirmed list missing or failed QC or amount due
1. Attempt dispatch for each failed prerequisite

**Expected:** No dispatch timestamp/stock event; actionable missing-gate details returned.

### IT-SHIP-004 — Invalid upload batch stores no partial files

**Priority:** P0 · **Requirement:** REQ-SHIP · **Implementation:** linked executable evidence

- **Preconditions:** F-PRODUCTS and F-MONEY; partial shipments S1/S2; fake public upload disk
- **Data:** One valid PDF plus invalid/oversized file
1. Submit entire shipment upload batch
2. Inspect disk

**Expected:** Validation rejects batch; no files stored.

**Evidence:** `tests/Feature/ShipmentAttachmentUploadTest.php#test_invalid_or_oversized_batches_do_not_store_any_files`

### IT-SHIP-005 — Read-only operator cannot upload evidence

**Priority:** P0 · **Requirement:** REQ-SHIP · **Implementation:** linked executable evidence

- **Preconditions:** F-PRODUCTS and F-MONEY; partial shipments S1/S2; fake public upload disk
- **Data:** operations.view without operations.manage
1. Open shipment
2. Attempt upload

**Expected:** Read permitted; upload denied without file creation.

**Evidence:** `tests/Feature/ShipmentAttachmentUploadTest.php#test_read_only_users_see_attachments_but_cannot_upload`

## Proformas, cash invoices and tax invoices (`SALES`)

Sources: `app/Services/CashInvoiceConversion.php`, `app/Services/CashInvoiceDeletion.php`, `app/Http/Controllers/SalesInvoiceController.php`.

### IT-SALES-001 — Partial cash conversion leaves PI open

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** partial evidence

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** F-MONEY selected amount/percentage and transfer
1. Convert once
2. Inspect original, cash invoice, receipts

**Expected:** PI remains open and linked; only chosen receipt amount moves; no duplicate allocation.

**Evidence:** `tests/Feature/CashInvoiceConversionTest.php#test_three_lakh_pi_keeps_the_bank_receipt_and_prorates_items_on_the_tax_remainder`

### IT-SALES-002 — Remainder converts to tax invoice without double payment

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** partial evidence

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** PI after partial cash conversion
1. Convert remaining balance
2. Inspect chain and totals

**Expected:** Canonical receivable documents reconcile; payments not counted on both superseded PI and invoice.

**Evidence:** `tests/Feature/CashInvoiceConversionTest.php#test_remainder_can_be_tax_invoiced_without_rebilling_cash_or_moving_cash_receipts`

### IT-SALES-003 — Cash cancellation/deletion restores source receipts

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** partial evidence

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** Cash invoice linked to original PI with transferred receipts
1. Cancel/delete cash invoice
2. Recalculate source balances

**Expected:** Original PI and available receipt amounts restored according to reversal policy exactly once.

**Evidence:** `tests/Feature/CashInvoiceDeletionTest.php#test_split_receipts_and_opening_credit_return_without_duplicating_bank_money`

### IT-SALES-004 — Excess transfer is rejected atomically

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** PI with enough receipts; cash invoice100000; transfer150000
1. Attempt conversion
2. Compare invoice,receipt and allocation rows

**Expected:** No child or money movement committed.

**Evidence:** `tests/Feature/CashInvoiceConversionTest.php#test_excess_transfer_is_rejected_atomically_even_when_the_pi_has_enough_receipts`

### IT-SALES-005 — FX split preserves source and document amounts

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** One FX allocation split across PI and cash child
1. Convert partially
2. Reconcile native and document values

**Expected:** Both currencies conserved without revaluation.

**Evidence:** `tests/Feature/CashInvoiceConversionTest.php#test_fx_allocation_splits_both_native_and_document_amounts_without_revaluation`

### IT-SALES-006 — Unsupported FX transfer rolls back

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** Direct receipt with unsupported FX transfer
1. Attempt conversion
2. Read source and child rows

**Expected:** Entire conversion rejected; source unchanged.

**Evidence:** `tests/Feature/CashInvoiceConversionTest.php#test_unsupported_direct_fx_transfer_rolls_back_the_entire_conversion`

### IT-SALES-007 — Refunds restrict net transferable receipts

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** PI receipt plus refund reducing available money
1. Convert with transfer
2. Inspect refund and residual

**Expected:** Refund intact; only net positive receipt capacity transfers.

**Evidence:** `tests/Feature/CashInvoiceConversionTest.php#test_refunds_stay_intact_and_only_net_receipts_are_transferable`

### IT-SALES-008 — Stale PI route model cannot overwrite converted state

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** Two snapshots of same PI; first conversion committed
1. Submit update using stale route instance

**Expected:** Converted money/linkage not overwritten.

**Evidence:** `tests/Feature/CashInvoiceConversionTest.php#test_stale_route_models_cannot_overwrite_a_pi_after_another_request_converts_it`

### IT-SALES-009 — Audit failure rolls back reversal

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** Cash child with transferred receipts; force audit insertion failure
1. Delete child
2. Inspect original and receipts

**Expected:** No partial reversal; source,child and allocations remain consistent.

**Evidence:** `tests/Feature/CashInvoiceDeletionTest.php#test_audit_failure_rolls_back_the_invoice_credit_receipt_links_and_pi_totals`

### IT-SALES-010 — Repeated deletion cannot duplicate restored advance

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** Deleted cash child; same stale deletion request again
1. Delete
2. Repeat deletion

**Expected:** Restoration occurs once; no duplicated credit.

**Evidence:** `tests/Feature/CashInvoiceDeletionTest.php#test_repeating_a_delete_never_restores_credit_twice`

### IT-SALES-011 — Deleted invoice numbers are never reused

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** Full conversion reversed; create another cash document
1. Delete full child
2. Create next invoice

**Expected:** Sequence remains monotonic/unique; original source reopened.

**Evidence:** `tests/Feature/CashInvoiceDeletionTest.php#test_full_conversion_is_reopened_and_deleted_document_numbers_are_never_reused`

### IT-SALES-012 — Subcent rounding reconciles multiple children

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** Discounted PI with subcent values and three partial conversions
1. Convert three times
2. Reconcile remaining lines

**Expected:** Converted plus remaining money equals original net claim exactly at money precision.

**Evidence:** `tests/Feature/CashInvoiceConversionTest.php#test_rounding_charges_and_discounted_values_reconcile_after_three_partial_conversions`

## Purchase documents and vendor settlement (`PURCHASE`)

Sources: `app/Services/PurchaseBillLedger.php`, `app/Services/PurchaseInvoiceMoney.php`, `app/Http/Controllers/PurchaseInvoiceController.php`.

### IT-PURCHASE-001 — Posting vendor payment and ledger is consistent

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** planned

- **Preconditions:** F-MONEY; V1 bill100000; active payments25000; cancelled payment10000
- **Data:** V1 bill and cashflow account
1. Create vendor payment
2. Inspect ledger link and bill

**Expected:** One active economic effect and matching payable change.

### IT-PURCHASE-002 — Payment cancellation reopens bill once

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** partial evidence

- **Preconditions:** F-MONEY; V1 bill100000; active payments25000; cancelled payment10000
- **Data:** Fully paid bill with synced cashflow
1. Cancel payment
2. Repeat/call stale action

**Expected:** No duplicate reversal; payable matches remaining active payments.

**Evidence:** `tests/Feature/VendorCashflowDeletionTest.php#test_deleting_from_vendor_money_refreshes_bill_too`

### IT-PURCHASE-003 — Purchase reader cannot post payments

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** planned

- **Preconditions:** F-MONEY; V1 bill100000; active payments25000; cancelled payment10000
- **Data:** purchase.view user without manage
1. Read bill
2. Submit payment mutation

**Expected:** Read allowed; payment denied and balances unchanged.

## Cashflow, allocations and account transfers (`CASH`)

Sources: `app/Services/CashflowAllocations.php`, `app/Services/CashflowLedger.php`, `app/Http/Controllers/CashflowController.php`.

### IT-CASH-001 — Allocate receipt without inventing an invoice payment

**Priority:** P0 · **Requirement:** REQ-CASH · **Implementation:** planned

- **Preconditions:** F-MONEY; cash and bank accounts with explicit source currencies; transfer category
- **Data:** Receipt1000; invoice due700
1. Allocate700
2. Read document balance and residual

**Expected:** Invoice balance reflects700 once; residual300; no duplicate receipt record.

### IT-CASH-002 — Account transfer is atomic

**Priority:** P0 · **Requirement:** REQ-CASH · **Implementation:** planned

- **Preconditions:** F-MONEY; cash and bank accounts with explicit source currencies; transfer category
- **Data:** Source cash10000; target bank0; inject failure after first leg
1. Post transfer
2. Force rollback
3. Inspect both accounts

**Expected:** Either both legs persist or neither; totals conserved; no orphan leg.

### IT-CASH-003 — Concurrent allocation cannot overspend receipt

**Priority:** P0 · **Requirement:** REQ-CASH · **Implementation:** planned

- **Preconditions:** F-MONEY; cash and bank accounts with explicit source currencies; transfer category
- **Data:** Two workers each allocate700 from same1000
1. Execute overlapping transactions on production database

**Expected:** At most1000 committed; losing request conflicts/retries without partial allocation.

### IT-CASH-004 — Overpaid document does not show negative allocatable balance

**Priority:** P0 · **Requirement:** REQ-CASH · **Implementation:** linked executable evidence

- **Preconditions:** F-MONEY; cash and bank accounts with explicit source currencies; transfer category
- **Data:** Paid and overpaid sales/purchase documents
1. Open allocation picker positions

**Expected:** Balance and allocation capacity clamped safely; overpayment retained as credit.

**Evidence:** `tests/Feature/CashflowDocumentBalanceTest.php#test_fully_paid_and_overpaid_documents_never_show_negative_balance`

### IT-CASH-005 — Editing existing allocation counts it once

**Priority:** P0 · **Requirement:** REQ-CASH · **Implementation:** linked executable evidence

- **Preconditions:** F-MONEY; cash and bank accounts with explicit source currencies; transfer category
- **Data:** Saved allocation included in editing context
1. Build payment position for edit

**Expected:** Current allocation counted once; available amount not double reduced.

**Evidence:** `tests/Feature/CashflowDocumentBalanceTest.php#test_saved_editing_allocation_is_counted_once_and_legacy_amount_is_supported`

### IT-CASH-006 — Bulk reversal failure is fully atomic

**Priority:** P0 · **Requirement:** REQ-CASH · **Implementation:** linked executable evidence

- **Preconditions:** F-MONEY; cash and bank accounts with explicit source currencies; transfer category
- **Data:** Two vendor mirrored receipts; forced failure on one
1. Bulk delete
2. Inspect all payments,bills and cashflows

**Expected:** All selected reversals rolled back on failure.

**Evidence:** `tests/Feature/VendorCashflowDeletionTest.php#test_bulk_failure_rolls_back_all_selected_entries_and_reversals`

## Recurring cashflow planning and posting (`RECUR`)

Sources: `app/Services/RecurrenceSchedule.php`, `app/Services/RecurrencePosting.php`, `app/Services/RecurrencePlan.php`.

### IT-RECUR-001 — Posting the same occurrence is idempotent

**Priority:** P1 · **Requirement:** REQ-RECUR · **Implementation:** planned

- **Preconditions:** Monthly rule anchored31January; daily/weekly variants; approved and paused rules
- **Data:** Approved occurrence with due date today
1. Post
2. Repeat same occurrence from second request

**Expected:** Exactly one active ledger effect and stable occurrence reference.

### IT-RECUR-002 — Pause does not create unauthorised backdated payments

**Priority:** P1 · **Requirement:** REQ-RECUR · **Implementation:** planned

- **Preconditions:** Monthly rule anchored31January; daily/weekly variants; approved and paused rules
- **Data:** Paused rule resumes after three months
1. Resume and build next planning window

**Expected:** Future authorised occurrences follow anchor; no automatic catch-up ledger entries.

### IT-RECUR-003 — Occurrence reversal is reflected in plan and ledger

**Priority:** P1 · **Requirement:** REQ-RECUR · **Implementation:** planned

- **Preconditions:** Monthly rule anchored31January; daily/weekly variants; approved and paused rules
- **Data:** Posted occurrence with linked entry
1. Reverse
2. Inspect occurrence and cashflow

**Expected:** Reversal traceable; balances restored and no duplicate active posting.

## Party statements and reconciliation (`STATEMENT`)

Sources: `app/Services/PartyStatement.php`, `app/Http/Controllers/PartyStatementController.php`.

### IT-STATEMENT-001 — Statement reconciles to source documents

**Priority:** P1 · **Requirement:** REQ-STATEMENT · **Implementation:** planned

- **Preconditions:** C1/V1 opening balances and dated receipts/documents in INR and USD
- **Data:** C1 invoices/receipts and V1 unrelated rows
1. Request C1 statement and export

**Expected:** Only C1 records; opening plus rows equals closing; export agrees with screen.

### IT-STATEMENT-002 — Date filters include full last day

**Priority:** P1 · **Requirement:** REQ-STATEMENT · **Implementation:** planned

- **Preconditions:** C1/V1 opening balances and dated receipts/documents in INR and USD
- **Data:** Receipt12Oct23:59:59; end date12Oct
1. Request statement through12Oct

**Expected:** Receipt included;13Oct00:00 excluded.

### IT-STATEMENT-003 — Read access cannot change receipt allocation

**Priority:** P1 · **Requirement:** REQ-STATEMENT · **Implementation:** planned

- **Preconditions:** C1/V1 opening balances and dated receipts/documents in INR and USD
- **Data:** Statement reader session
1. GET/print/export statement
2. Compare money tables

**Expected:** No mutation and same allocation ownership before/after.

## Asset register, allocation and depreciation (`ASSET`)

Sources: `app/Services/AssetDepreciation.php`, `app/Http/Controllers/FixedAssetController.php`.

### IT-ASSET-001 — Asset custody has one open allocation

**Priority:** P1 · **Requirement:** REQ-ASSET · **Implementation:** planned

- **Preconditions:** Asset cost100000 GST18000 useful life5 residual5 percent; SLM/WDV
- **Data:** Asset assigned to E then returned and assigned B
1. Allocate
2. Take back
3. Reallocate

**Expected:** Exactly one current custodian; complete dated custody history.

### IT-ASSET-002 — Disposal stops future depreciation without consuming remaining life

**Priority:** P1 · **Requirement:** REQ-ASSET · **Implementation:** planned

- **Preconditions:** Asset cost100000 GST18000 useful life5 residual5 percent; SLM/WDV
- **Data:** Asset disposed midyear before useful life ends
1. Dispose
2. Run year report and NBV

**Expected:** Charge prorated to disposal; thereafter NBV zero; gain/loss based on value at disposal; check residual write-down defect risk.

**Note:** Manual probe on 2026-10-25 reproduced actual charge95000 versus prorated9526.03 described by the service. Open business-rule/implementation defect; not covered by the passing baseline. See execution-baseline.md and tdd-playbook.md.

### IT-ASSET-003 — Depreciation report total equals printed asset rows

**Priority:** P1 · **Requirement:** REQ-ASSET · **Implementation:** planned

- **Preconditions:** Asset cost100000 GST18000 useful life5 residual5 percent; SLM/WDV
- **Data:** Two assets with different methods
1. Generate yearly report and export

**Expected:** Opening/additions/disposals/charge/closing reconcile to row sums.

## Organisation, addresses and banking identity (`ORG`)

Sources: `app/Http/Controllers/OrganisationController.php`, `app/Models/Organisation.php`.

### IT-ORG-001 — Organisation settings update correct document identity

**Priority:** P1 · **Requirement:** REQ-ORG · **Implementation:** planned

- **Preconditions:** MissPack and third-party organisations; two addresses and bank accounts each
- **Data:** Change MissPack dispatch address
1. Save settings
2. Print new invoice/label

**Expected:** New documents use selected identity; historical snapshots follow snapshot contract.

### IT-ORG-002 — Non-settings user cannot alter organisation bank details

**Priority:** P1 · **Requirement:** REQ-ORG · **Implementation:** planned

- **Preconditions:** MissPack and third-party organisations; two addresses and bank accounts each
- **Data:** Office reader without settings.manage
1. Submit organisation update

**Expected:** Denied; account details unchanged.

### IT-ORG-003 — Third-party sender does not receive MissPack branding

**Priority:** P1 · **Requirement:** REQ-ORG · **Implementation:** planned

- **Preconditions:** MissPack and third-party organisations; two addresses and bank accounts each
- **Data:** Packaging sender third party
1. Render carton label and packing print

**Expected:** Correct third-party name; no MissPack logo.

## Office services and linked spending (`SERVICE`)

Sources: `app/Http/Controllers/OfficeServiceController.php`, `app/Models/OfficeService.php`.

### IT-SERVICE-001 — Service bill and cashflow reference same provider

**Priority:** P1 · **Requirement:** REQ-SERVICE · **Implementation:** planned

- **Preconditions:** Service provider subscription and related invoice/payment/cashflow rows
- **Data:** Create service expense for provider S1
1. Post expense
2. Open service financial history

**Expected:** Correct service linkage; one ledger effect; unrelated S2 excluded.

### IT-SERVICE-002 — Service attachment lifecycle preserves evidence

**Priority:** P1 · **Requirement:** REQ-SERVICE · **Implementation:** planned

- **Preconditions:** Service provider subscription and related invoice/payment/cashflow rows
- **Data:** Service invoice PDF on fake storage
1. Upload
2. Replace under allowed policy
3. Download

**Expected:** Correct metadata; authorised download; missing file handled safely.

### IT-SERVICE-003 — Purchase reader cannot change service configuration

**Priority:** P1 · **Requirement:** REQ-SERVICE · **Implementation:** planned

- **Preconditions:** Service provider subscription and related invoice/payment/cashflow rows
- **Data:** purchase.view only
1. GET service
2. Submit configuration update

**Expected:** Read allowed; write denied.

## Tasks and coordinator queues (`TASK`)

Sources: `app/Http/Controllers/TaskController.php`, `app/Services/ProjectCoordinatorQueue.php`.

### IT-TASK-001 — Task assignment and completion are persisted

**Priority:** P1 · **Requirement:** REQ-TASK · **Implementation:** planned

- **Preconditions:** Assigned/unassigned tasks; overdue/today/future deadlines; two coordinators
- **Data:** Office work manager; task assigned E
1. Create
2. Assign
3. Complete
4. Reload queue

**Expected:** Correct assignee/status/time; completed task removed from active overdue queue.

### IT-TASK-002 — Coordinator action stays within project workflow

**Priority:** P1 · **Requirement:** REQ-TASK · **Implementation:** planned

- **Preconditions:** Assigned/unassigned tasks; overdue/today/future deadlines; two coordinators
- **Data:** Project blocked on receipt
1. Open queue action
2. Record receipt in shared modal

**Expected:** No forced navigation detour; gate/queue updates after one successful receipt.

### IT-TASK-003 — Work reader cannot reassign or complete others work

**Priority:** P1 · **Requirement:** REQ-TASK · **Implementation:** planned

- **Preconditions:** Assigned/unassigned tasks; overdue/today/future deadlines; two coordinators
- **Data:** work.view only
1. Read queue
2. Attempt task mutation

**Expected:** Mutation denied; task unchanged.

## Private notes (`NOTE`)

Sources: `app/Services/NoteIntake.php`, `app/Http/Controllers/NoteController.php`, `app/Models/Note.php`.

### IT-NOTE-001 — Creation ignores forged owner

**Priority:** P1 · **Requirement:** REQ-NOTE · **Implementation:** linked executable evidence

- **Preconditions:** Employee E owns note N1; office A owns note N2; independent sessions
- **Data:** Authenticated E posts user_id=A
1. POST notes.store

**Expected:** New note belongs to E, never A.

**Evidence:** `tests/Integration/NotesOwnershipTest.php#test_create_ignores_forged_owner_and_persists_authenticated_owner`

### IT-NOTE-002 — Even office admin cannot access another login note

**Priority:** P1 · **Requirement:** REQ-NOTE · **Implementation:** linked executable evidence

- **Preconditions:** Employee E owns note N1; office A owns note N2; independent sessions
- **Data:** A targets E note via edit/update/pin/archive/delete
1. Run every foreign action

**Expected:** 404 for every action; original note unchanged.

**Evidence:** `tests/Integration/NotesOwnershipTest.php#test_even_an_office_admin_cannot_access_somebody_elses_note`

### IT-NOTE-003 — Owner can complete lifecycle without ownership transfer

**Priority:** P1 · **Requirement:** REQ-NOTE · **Implementation:** linked executable evidence

- **Preconditions:** Employee E owns note N1; office A owns note N2; independent sessions
- **Data:** E note with forged owner in update
1. Update
2. Pin
3. Archive
4. Restore
5. Delete

**Expected:** Expected states persisted; owner immutable; deleted row absent.

**Evidence:** `tests/Integration/NotesOwnershipTest.php#test_owner_can_update_pin_archive_restore_and_delete_without_transferring_ownership`

## Feedback scores and closure (`FEEDBACK`)

Sources: `app/Services/FeedbackVocabulary.php`, `app/Services/FeedbackIntake.php`, `app/Services/ProjectFeedbackClosure.php`.

### IT-FEEDBACK-001 — Valid feedback token writes correct request once

**Priority:** P1 · **Requirement:** REQ-FEEDBACK · **Implementation:** planned

- **Preconditions:** Scores1..5, recommendation0..10, nullable dimensions and secure request token
- **Data:** C1 active request; one-time/expiry policy configured
1. Submit feedback
2. Repeat or use invalid token

**Expected:** Correct request/client linked; duplicate and expired-token policy enforced.

### IT-FEEDBACK-002 — Open detractor prevents unattended final closure

**Priority:** P1 · **Requirement:** REQ-FEEDBACK · **Implementation:** planned

- **Preconditions:** Scores1..5, recommendation0..10, nullable dimensions and secure request token
- **Data:** Unresolved low-score response for P1
1. Attempt feedback closure
2. Resolve follow-up
3. Retry

**Expected:** Initial blocker visible; authorised resolution recorded before closure.

### IT-FEEDBACK-003 — Feedback results remain client/project isolated

**Priority:** P1 · **Requirement:** REQ-FEEDBACK · **Implementation:** planned

- **Preconditions:** Scores1..5, recommendation0..10, nullable dimensions and secure request token
- **Data:** C1 and C2 requests
1. Use C1 portal
2. Attempt C2 feedback request

**Expected:** No cross-client response content or write access.

## Office briefings, alerts and watchers (`BRIEF`)

Sources: `app/Services/OfficeBriefing.php`, `app/Http/Controllers/OfficeAlertController.php`, `app/Models/OfficeSetting.php`.

### IT-BRIEF-001 — Briefing action opens correct project action

**Priority:** P1 · **Requirement:** REQ-BRIEF · **Implementation:** planned

- **Preconditions:** Sales,operations and director watchers; unscoped office briefing; critical deadline
- **Data:** Exception for P1/A
1. Click next-action link
2. Complete authoritative workflow action

**Expected:** Correct context; alert refresh follows source, no duplicate execution path.

### IT-BRIEF-002 — Unpermitted desk cannot read private team briefing

**Priority:** P1 · **Requirement:** REQ-BRIEF · **Implementation:** planned

- **Preconditions:** Sales,operations and director watchers; unscoped office briefing; critical deadline
- **Data:** Custom reader without work access
1. Request briefing route/details

**Expected:** Denied without leaking title/body.

### IT-BRIEF-003 — Watcher configuration is settings-only

**Priority:** P1 · **Requirement:** REQ-BRIEF · **Implementation:** planned

- **Preconditions:** Sales,operations and director watchers; unscoped office briefing; critical deadline
- **Data:** Reader and settings manager
1. Attempt watcher update as each

**Expected:** Only settings-authorised mutation persists.

## Global search and navigation (`SEARCH`)

Sources: `app/Services/GlobalSearch.php`, `app/Http/Controllers/GlobalSearchController.php`.

### IT-SEARCH-001 — Cross-module results respect access profile

**Priority:** P1 · **Requirement:** REQ-SEARCH · **Implementation:** planned

- **Preconditions:** Records across sales,finance,projects and private notes; sales-only reader
- **Data:** Sales and finance matches share reference token
1. Search as sales reader

**Expected:** Only authorised results/snippets/URLs exposed.

### IT-SEARCH-002 — Deep link respects normal permission boundary

**Priority:** P1 · **Requirement:** REQ-SEARCH · **Implementation:** planned

- **Preconditions:** Records across sales,finance,projects and private notes; sales-only reader
- **Data:** Known finance result URL; sales-only actor
1. Navigate directly to URL

**Expected:** Finance controller remains protected despite bypassing search UI.

### IT-SEARCH-003 — Quick menu is role-aware and keyboard usable

**Priority:** P1 · **Requirement:** REQ-SEARCH · **Implementation:** planned

- **Preconditions:** Records across sales,finance,projects and private notes; sales-only reader
- **Data:** Desktop/mobile; full office versus employee
1. Open quick menu
2. Activate authorised item

**Expected:** Relevant role-safe destinations; no inaccessible create links; focus remains operable.

## Settings, workflow policy and master options (`SETTINGS`)

Sources: `app/Http/Controllers/SettingsController.php`, `app/Http/Controllers/WorkflowSettingController.php`, `app/Services/SettingsDirectory.php`.

### IT-SETTINGS-001 — Non-manager cannot save shared settings

**Priority:** P1 · **Requirement:** REQ-SETTINGS · **Implementation:** planned

- **Preconditions:** Settings manager and reader; master options referenced by existing records
- **Data:** Reader without settings.manage
1. POST/PUT organisation or workflow settings

**Expected:** 403; stored policy unchanged.

### IT-SETTINGS-002 — Policy changes affect next gate evaluation

**Priority:** P1 · **Requirement:** REQ-SETTINGS · **Implementation:** planned

- **Preconditions:** Settings manager and reader; master options referenced by existing records
- **Data:** Authorised advance/document requirement change
1. Save
2. Re-evaluate affected workflow

**Expected:** New policy applied with appropriate invalidation; stale approval not silently trusted.

### IT-SETTINGS-003 — Concurrent settings edit does not silently lose protected changes

**Priority:** P1 · **Requirement:** REQ-SETTINGS · **Implementation:** planned

- **Preconditions:** Settings manager and reader; master options referenced by existing records
- **Data:** Two managers edit same record
1. Save stale version after newer change

**Expected:** Conflict/locking policy verified on native DB; no silent critical-policy loss.

## Client portal authentication and data isolation (`CPORTAL`)

Sources: `app/Http/Middleware/ClientPortalAuthenticate.php`, `app/Services/ClientPortalAccess.php`, `app/Http/Controllers/ClientPortalInvoiceController.php`.

### IT-CPORTAL-001 — Client cannot download another client document

**Priority:** P0 · **Requirement:** REQ-CPORTAL · **Implementation:** planned

- **Preconditions:** F-IDENTITY; CP1 for C1 and CP2 for C2; disabled/must-change-password accounts
- **Data:** CP1 session; C2 invoice/attachment/project IDs
1. Request detail/download/print for each C2 record

**Expected:** Denied/404 with no C2 bytes or existence leak per endpoint policy.

### IT-CPORTAL-002 — Disabled portal session is invalidated

**Priority:** P0 · **Requirement:** REQ-CPORTAL · **Implementation:** planned

- **Preconditions:** F-IDENTITY; CP1 for C1 and CP2 for C2; disabled/must-change-password accounts
- **Data:** CP1 already signed in; disable account or client
1. Request next protected portal page

**Expected:** Portal session cleared; redirected to client login.

### IT-CPORTAL-003 — Portal receipt does not bypass office settlement controls

**Priority:** P0 · **Requirement:** REQ-CPORTAL · **Implementation:** planned

- **Preconditions:** F-IDENTITY; CP1 for C1 and CP2 for C2; disabled/must-change-password accounts
- **Data:** CP1 receipt evidence pending review
1. Submit evidence
2. Inspect invoice paid balance

**Expected:** Evidence stored under C1; no unapproved financial mutation or foreign allocation.

## Vendor portal and secure share links (`VPORTAL`)

Sources: `app/Http/Controllers/VendorPortalController.php`, `app/Http/Controllers/VendorShareController.php`, `app/Models/VendorPortalUser.php`.

### IT-VPORTAL-001 — Vendor cannot fetch another vendor document

**Priority:** P0 · **Requirement:** REQ-VPORTAL · **Implementation:** planned

- **Preconditions:** VP1 for V1 and VP2 for V2; valid,revoked,expired share tokens
- **Data:** VP1 session; V2 bill/project/share reference
1. Request detail/download/print

**Expected:** Denied without V2 content.

### IT-VPORTAL-002 — Revoking existing share takes effect immediately

**Priority:** P0 · **Requirement:** REQ-VPORTAL · **Implementation:** planned

- **Preconditions:** VP1 for V1 and VP2 for V2; valid,revoked,expired share tokens
- **Data:** Previously valid link open; office revokes
1. GET link after revocation

**Expected:** No cached authenticated content returned.

### IT-VPORTAL-003 — Portal/share administration requires sensitive portal grant

**Priority:** P0 · **Requirement:** REQ-VPORTAL · **Implementation:** planned

- **Preconditions:** VP1 for V1 and VP2 for V2; valid,revoked,expired share tokens
- **Data:** purchase.manage without portals.manage
1. Create/reset/revoke portal or share link

**Expected:** Denied; no new token or changed credentials.

## Overview and module dashboard reporting (`DASH`)

Sources: `app/Services/DashboardPeriod.php`, `app/Services/ModuleDashboard.php`, `app/Http/Controllers/AdminDashboardController.php`.

### IT-DASH-001 — Native database grouping respects exact boundaries

**Priority:** P1 · **Requirement:** REQ-DASH · **Implementation:** linked executable evidence

- **Preconditions:** Frozen25Oct2026; custom10..12Oct; receipts before,within,after and two currencies
- **Data:** Day,month and year groups; outside-window high-value rows
1. Run MySQL DATE,DATE_FORMAT,YEAR aggregation

**Expected:** Only in-window total30; label count and group match period.

**Evidence:** `tests/Database/MySqlReportingTest.php#test_real_database_aggregation_honours_custom_bounds`

### IT-DASH-002 — Custom selection survives module navigation

**Priority:** P1 · **Requirement:** REQ-DASH · **Implementation:** linked executable evidence

- **Preconditions:** Frozen25Oct2026; custom10..12Oct; receipts before,within,after and two currencies
- **Data:** finance custom10..12Oct; six module links
1. Render navigation
2. Inspect query parameters

**Expected:** All six links retain type,start_date,end_date.

**Evidence:** `tests/Feature/DashboardCustomPeriodTest.php#test_module_links_preserve_both_custom_dates`

### IT-DASH-003 — Select2 fields update before Apply

**Priority:** P1 · **Requirement:** REQ-DASH · **Implementation:** planned

- **Preconditions:** Frozen25Oct2026; custom10..12Oct; receipts before,within,after and two currencies
- **Data:** Desktop1440 and mobile390; all period types
1. Change View
2. Edit dates
3. Switch away/back
4. Apply once

**Expected:** Relevant fields immediately visible; hidden dates disabled; choices retained; one explicit submission.

## Audit trail and sensitive operations (`AUDIT`)

Sources: `app/Services/AuditTrail.php`, `app/Http/Middleware/AuditMutations.php`, `app/Http/Controllers/AuditLogController.php`.

### IT-AUDIT-001 — Successful mutation records actor and target

**Priority:** P0 · **Requirement:** REQ-AUDIT · **Implementation:** planned

- **Preconditions:** Office,portal and anonymous actors; sensitive settings mutation and secret URL token
- **Data:** Office A changes one protected record
1. Submit mutation
2. Read audit entry

**Expected:** Correct actor,target,route,status and timestamp with no leaked secret.

### IT-AUDIT-002 — Denied permission is auditable without changing target

**Priority:** P0 · **Requirement:** REQ-AUDIT · **Implementation:** planned

- **Preconditions:** Office,portal and anonymous actors; sensitive settings mutation and secret URL token
- **Data:** Restricted R attempts finance mutation
1. Attempt request
2. Inspect audit and target

**Expected:** 403 evidence recorded if audit storage available; financial target unchanged.

### IT-AUDIT-003 — Audit register is itself access controlled

**Priority:** P0 · **Requirement:** REQ-AUDIT · **Implementation:** planned

- **Preconditions:** Office,portal and anonymous actors; sensitive settings mutation and secret URL token
- **Data:** Reader without audit.view then auditor
1. GET/export audit

**Expected:** Reader denied; authorised auditor sees scoped event data.

## Shared file policy and storage lifecycle (`FILES`)

Sources: `app/Services/DocumentUpload.php`, `app/Http/Controllers/ShipmentController.php`.

### IT-FILES-001 — Upload validates content as well as suffix

**Priority:** P0 · **Requirement:** REQ-FILES · **Implementation:** planned

- **Preconditions:** Fake local public/private disks; safe PDF,disallowed executable,20MB and20MB+1KB files
- **Data:** PHP/HTML bytes renamed invoice.pdf; path traversal filename
1. Submit real controller upload

**Expected:** MIME/content and filename controls reject unsafe content where required; storage path cannot escape intended disk. Policy helper alone is not security proof.

### IT-FILES-002 — Failed parent save does not leak orphaned evidence

**Priority:** P0 · **Requirement:** REQ-FILES · **Implementation:** planned

- **Preconditions:** Fake local public/private disks; safe PDF,disallowed executable,20MB and20MB+1KB files
- **Data:** Inject failure after upload before transaction commit
1. Submit upload
2. Inspect database and disk

**Expected:** Rollback/cleanup policy verified; no dangling DB file link or unmanaged file.

### IT-FILES-003 — Missing stored file returns safe response

**Priority:** P0 · **Requirement:** REQ-FILES · **Implementation:** planned

- **Preconditions:** Fake local public/private disks; safe PDF,disallowed executable,20MB and20MB+1KB files
- **Data:** Attachment metadata whose file has been removed
1. Download as authorised owner

**Expected:** Controlled404/absent result, not filesystem path disclosure or500.

## Shared UI, printing and accessible interactions (`UI`)

Sources: `resources/views/components/drawer.blade.php`, `public/assets/js/master-drawer.js`, `docs/ui-design-guidelines.md`.

### IT-UI-001 — Drawers support keyboard lifecycle

**Priority:** P1 · **Requirement:** REQ-UI · **Implementation:** planned

- **Preconditions:** Desktop1440,tablet768,mobile390 and320; light/dark; keyboard-only input
- **Data:** Closed drawer; trigger focused
1. Open
2. Tab/ShiftTab
3. Escape
4. Reopen

**Expected:** Focus contained; Escape closes; focus restored to trigger; no stranded dropdown.

### IT-UI-002 — Dense financial UI stays within mobile viewport

**Priority:** P1 · **Requirement:** REQ-UI · **Implementation:** planned

- **Preconditions:** Desktop1440,tablet768,mobile390 and320; light/dark; keyboard-only input
- **Data:** Eight advance cards; long names; mixed currencies
1. Render320 and390px
2. Scroll card rail
3. Activate last card

**Expected:** Local horizontal scroll only; bounded page height; every allocation accessible.

### IT-UI-003 — Document prints use requested paper and identity

**Priority:** P1 · **Requirement:** REQ-UI · **Implementation:** planned

- **Preconditions:** Desktop1440,tablet768,mobile390 and320; light/dark; keyboard-only input
- **Data:** A4 portrait/landscape;100x140 and100x150 labels
1. Render each print page and inspect final print rules

**Expected:** Correct paper geometry; black document text; sender branding and configured columns respected.

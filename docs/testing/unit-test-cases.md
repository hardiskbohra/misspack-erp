# Unit test cases

Generated from [catalogue.json](catalogue.json); edit the JSON and run `npm run test:catalogue:build`.

**Implementation links are not a passing result.** Unlinked cases are planned. Partial evidence does not cover every step. Unit targets linked only to component evidence remain unclaimed as isolated unit coverage.

## Authentication and office permissions (`AUTH`)

Sources: `app/Services/PermissionMatrix.php`, `app/Http/Controllers/AuthController.php`, `app/Http/Middleware/EnsureUserIsAdmin.php`.

### UT-AUTH-001 — Read permission cannot mutate

**Priority:** P0 · **Requirement:** REQ-AUTH · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY; empty disposable database for guest/employee boundary checks
- **Data:** Custom office profile with sales.view only
1. Resolve clients.store for POST
2. Evaluate sales.manage

**Expected:** sales.manage is required and denied; sales.view remains allowed.

**Evidence:** `tests/Unit/PermissionMatrixTest.php#test_manage_implies_view_but_view_does_not_imply_manage_or_sensitive_access`; `tests/Unit/PermissionMatrixTest.php#test_route_requires_the_correct_permission`

### UT-AUTH-002 — Management does not imply sensitive override

**Priority:** P0 · **Requirement:** REQ-AUTH · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY; empty disposable database for guest/employee boundary checks
- **Data:** projects.manage without projects.override
1. Evaluate projects.view then projects.override

**Expected:** View is allowed; override is denied.

**Evidence:** `tests/Unit/PermissionMatrixTest.php#test_manage_implies_view_but_view_does_not_imply_manage_or_sensitive_access`

### UT-AUTH-003 — Employee cannot inherit office access

**Priority:** P0 · **Requirement:** REQ-AUTH · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY; empty disposable database for guest/employee boundary checks
- **Data:** Employee with full profile and explicit finance.manage
1. Evaluate every permission option

**Expected:** Every office permission is denied.

**Evidence:** `tests/Unit/PermissionMatrixTest.php#test_employee_never_gains_office_access_even_with_full_profile_or_explicit_grants`

## People, payroll and employee documents (`PEOPLE`)

Sources: `app/Services/EmployeeAccess.php`, `app/Services/EmployeeProfile.php`, `app/Services/PayslipDocument.php`.

### UT-PEOPLE-001 — Self-service read ownership

**Priority:** P0 · **Requirement:** REQ-PEOPLE · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY; employee E, other employee B, office A; fake private document storage
- **Data:** Employee E as actor; E and B as owners
1. Evaluate canView for E and B

**Expected:** Own record allowed; other employee denied.

**Evidence:** `tests/Unit/EmployeeAccessTest.php#test_employee_can_read_self_but_not_another_employee_or_office_record`

### UT-PEOPLE-002 — Contact edit allowlist excludes pay and bank

**Priority:** P0 · **Requirement:** REQ-PEOPLE · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY; employee E, other employee B, office A; fake private document storage
- **Data:** Role, permissions, salary and bank fields in payload
1. Compare editable fields with office-only fields

**Expected:** No sensitive field is self-editable; field-rule keys match allowlist.

**Evidence:** `tests/Unit/EmployeeAccessTest.php#test_self_service_allowlist_never_contains_salary_bank_or_role_fields`

### UT-PEOPLE-003 — Salary net reconciles to lines

**Priority:** P0 · **Requirement:** REQ-PEOPLE · **Implementation:** planned

- **Preconditions:** F-IDENTITY; employee E, other employee B, office A; fake private document storage
- **Data:** Earnings 25000+5000; deductions 2000+500
1. Calculate payroll net

**Expected:** Net 27500; negative deductions/earnings follow validation policy.

## Client master, KYC and account profile (`CLIENT`)

Sources: `app/Http/Controllers/ClientController.php`, `app/Services/ClientAccountSummary.php`, `app/Models/Client.php`.

### UT-CLIENT-001 — Currencies remain separate

**Priority:** P1 · **Requirement:** REQ-CLIENT · **Implementation:** planned

- **Preconditions:** F-IDENTITY and F-MONEY; two separate clients C1 and C2
- **Data:** INR invoice 1000; USD invoice 100
1. Build client account summary buckets

**Expected:** Two currency buckets; no fabricated converted grand total.

### UT-CLIENT-002 — Residual advance is not double-counted

**Priority:** P1 · **Requirement:** REQ-CLIENT · **Implementation:** planned

- **Preconditions:** F-IDENTITY and F-MONEY; two separate clients C1 and C2
- **Data:** Receipt 1000; active allocations 600
1. Calculate residual and payments

**Expected:** Residual 400; receipt counted once in total payments.

### UT-CLIENT-003 — Cancelled document is not current receivable

**Priority:** P1 · **Requirement:** REQ-CLIENT · **Implementation:** planned

- **Preconditions:** F-IDENTITY and F-MONEY; two separate clients C1 and C2
- **Data:** Current invoice 1000; cancelled invoice 500
1. Resolve canonical client documents

**Expected:** Only current non-cancelled document contributes receivable.

## Vendor master and payable profile (`VENDOR`)

Sources: `app/Http/Controllers/VendorController.php`, `app/Models/Vendor.php`, `app/Services/VendorPaymentCashflowSync.php`.

### UT-VENDOR-001 — Outstanding is vendor-specific

**Priority:** P1 · **Requirement:** REQ-VENDOR · **Implementation:** planned

- **Preconditions:** F-MONEY; vendors V1 and V2 with separate bills and payments
- **Data:** V1 bills 10000; active payments 3000
1. Calculate V1 payable

**Expected:** Payable 7000; V2 activity excluded.

### UT-VENDOR-002 — Cancelled payment is inactive

**Priority:** P1 · **Requirement:** REQ-VENDOR · **Implementation:** planned

- **Preconditions:** F-MONEY; vendors V1 and V2 with separate bills and payments
- **Data:** Bill 10000; cancelled payment 3000
1. Resolve payment total

**Expected:** Cancelled amount contributes zero.

### UT-VENDOR-003 — Search input cannot become SQL syntax

**Priority:** P1 · **Requirement:** REQ-VENDOR · **Implementation:** planned

- **Preconditions:** F-MONEY; vendors V1 and V2 with separate bills and payments
- **Data:** Vendor search containing quote and percent characters
1. Build filtered query and inspect bindings

**Expected:** Input remains bound data; no raw injected predicate.

## Products and price calculator (`PRODUCT`)

Sources: `app/Http/Controllers/ProductController.php`, `app/Http/Controllers/PriceCalculatorController.php`.

### UT-PRODUCT-001 — Extended price preserves decimal precision

**Priority:** P1 · **Requirement:** REQ-PRODUCT · **Implementation:** planned

- **Preconditions:** Two products with distinct IDs/SKUs, units, INR/USD prices and optional media
- **Data:** Quantity 2.5; unit price 123.45
1. Calculate line extension using domain rounding

**Expected:** Amount 308.63 at two-decimal money boundary.

### UT-PRODUCT-002 — Invalid product quantities are rejected

**Priority:** P1 · **Requirement:** REQ-PRODUCT · **Implementation:** planned

- **Preconditions:** Two products with distinct IDs/SKUs, units, INR/USD prices and optional media
- **Data:** Quantity -1, zero where prohibited and nonnumeric input
1. Validate product/pricing input

**Expected:** Invalid values rejected rather than silently generating a total.

### UT-PRODUCT-003 — Product snapshot is independent of later master edit

**Priority:** P1 · **Requirement:** REQ-PRODUCT · **Implementation:** planned

- **Preconditions:** Two products with distinct IDs/SKUs, units, INR/USD prices and optional media
- **Data:** Project product copied from SKU-A
1. Change master name/rate
2. Read stored snapshot

**Expected:** Historical project/document snapshot does not silently change.

## Leads and sales follow-up (`LEAD`)

Sources: `app/Http/Controllers/LeadController.php`, `app/Http/Controllers/LeadCommentController.php`, `app/Http/Controllers/PublicLeadController.php`.

### UT-LEAD-001 — Stage and source values use configured vocabulary

**Priority:** P1 · **Requirement:** REQ-LEAD · **Implementation:** planned

- **Preconditions:** Two leads with different owners, stages and next follow-up dates
- **Data:** Known stage and arbitrary invalid stage
1. Validate intake/filter values

**Expected:** Known value accepted; invalid value rejected or documented default applied.

### UT-LEAD-002 — Overdue follow-up respects calendar boundary

**Priority:** P1 · **Requirement:** REQ-LEAD · **Implementation:** planned

- **Preconditions:** Two leads with different owners, stages and next follow-up dates
- **Data:** Follow-up yesterday, today and tomorrow
1. Evaluate due/overdue classification

**Expected:** Past due differs from due today and future; null date not overdue.

### UT-LEAD-003 — Lead counts exclude unrelated filters correctly

**Priority:** P1 · **Requirement:** REQ-LEAD · **Implementation:** planned

- **Preconditions:** Two leads with different owners, stages and next follow-up dates
- **Data:** Owned and unowned leads in multiple stages
1. Apply stage/search filters
2. Compute badge counts

**Expected:** Counts reflect defined scope rather than current page size.

## Project master and product lanes (`PROJECT`)

Sources: `app/Http/Controllers/ProjectController.php`, `app/Services/ProjectProducts.php`, `app/Models/Project.php`.

### UT-PROJECT-001 — Execution status fields are not ordinary form fields

**Priority:** P1 · **Requirement:** REQ-PROJECT · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; client C1; project P1 with product A and B
- **Data:** status=completed, stage=completed, progress_percent=100
1. Mass assign ordinary project input

**Expected:** Workflow-owned fields are not changed through normal fill.

### UT-PROJECT-002 — Product delivery balances are independent

**Priority:** P1 · **Requirement:** REQ-PROJECT · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; client C1; project P1 with product A and B
- **Data:** A required 100 delivered 40; B required 200 delivered 0
1. Calculate remaining per product

**Expected:** A remaining 60; B remaining 200; no cross-product subtraction.

### UT-PROJECT-003 — Archived project is absent from active scope

**Priority:** P1 · **Requirement:** REQ-PROJECT · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; client C1; project P1 with product A and B
- **Data:** Active and soft-deleted projects
1. Build active project query

**Expected:** Archived record excluded while historical financial ownership remains recoverable.

## Execution, approvals, gates and financial closure (`WORKFLOW`)

Sources: `app/Services/ProjectWorkflowExecution.php`, `app/Services/ProjectWorkflowGates.php`, `app/Services/ProjectFinancialClosure.php`.

### UT-WORKFLOW-001 — Only one standard execution vocabulary exists

**Priority:** P0 · **Requirement:** REQ-WORKFLOW · **Implementation:** planned

- **Preconditions:** F-PRODUCTS and F-MONEY; workflow standard_execution; approver A and non-override reader R
- **Data:** Standard stage keys including QC, packing, dispatch and closure
1. Inspect stage options and template identity

**Expected:** One ordered workflow; no alternative template selected implicitly.

### UT-WORKFLOW-002 — Progress uses required active weights

**Priority:** P0 · **Requirement:** REQ-WORKFLOW · **Implementation:** planned

- **Preconditions:** F-PRODUCTS and F-MONEY; workflow standard_execution; approver A and non-override reader R
- **Data:** Required steps weights 4 and 6; first complete; optional/cancelled steps present
1. Calculate eligible completion percentage

**Expected:** 40 percent from eligible required weights; optional/cancelled work does not inflate denominator.

### UT-WORKFLOW-003 — Full dispatch balance cannot be mistaken for advance

**Priority:** P0 · **Requirement:** REQ-WORKFLOW · **Implementation:** planned

- **Preconditions:** F-PRODUCTS and F-MONEY; workflow standard_execution; approver A and non-override reader R
- **Data:** PI 300000; received 150000
1. Evaluate advance versus dispatch balance conditions

**Expected:** Production advance may pass; pre-dispatch balance remains blocked.

## Quality inspection and sampling (`QC`)

Sources: `app/Http/Controllers/ProjectQualityInspectionController.php`, `app/Services/ProjectOperationalReadiness.php`.

### UT-QC-001 — Sample cannot exceed relevant lot

**Priority:** P0 · **Requirement:** REQ-QC · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; A quantity 100 and B quantity 200; optional fake QC attachment
- **Data:** Lot 100; sample 10 and 101
1. Validate QC quantities

**Expected:** 10 accepted; 101 rejected; no sample attributed to other product.

### UT-QC-002 — Failed inspection cannot mark quality passed

**Priority:** P0 · **Requirement:** REQ-QC · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; A quantity 100 and B quantity 200; optional fake QC attachment
- **Data:** Failed result with unresolved defects
1. Evaluate quality gate

**Expected:** Quality gate remains blocked with actionable failure reason.

### UT-QC-003 — Optional evidence is not mandatory

**Priority:** P0 · **Requirement:** REQ-QC · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; A quantity 100 and B quantity 200; optional fake QC attachment
- **Data:** Valid QC result without attachment
1. Validate inspection payload

**Expected:** Inspection allowed without file; result/inspector remain required.

## Packaging lists, verification and print labels (`PACK`)

Sources: `app/Http/Controllers/PackagingListController.php`, `app/Services/PackagingListVerifier.php`, `app/Services/PackagingListProjectComparison.php`.

### UT-PACK-001 — Carton CBM uses centimetres consistently

**Priority:** P0 · **Requirement:** REQ-PACK · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; packing groups and carton sizes; sender MissPack or third party
- **Data:** One carton 60 x 40 x 40 cm
1. Calculate volume

**Expected:** 0.096 cubic metres per carton; totals multiply carton count once.

### UT-PACK-002 — Mixed-product carton does not double its weight

**Priority:** P0 · **Requirement:** REQ-PACK · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; packing groups and carton sizes; sender MissPack or third party
- **Data:** One carton net 14.4kg with two item rows
1. Calculate packing section totals

**Expected:** Carton weight counted once; both item quantities retained.

### UT-PACK-003 — Product identity mismatch is explicit

**Priority:** P0 · **Requirement:** REQ-PACK · **Implementation:** planned

- **Preconditions:** F-PRODUCTS; packing groups and carton sizes; sender MissPack or third party
- **Data:** Project product A; packing item linked to B
1. Compare project and packing identities

**Expected:** Result includes actual A and linked B with mismatch highlighted.

## Shipments, dispatch and delivery (`SHIP`)

Sources: `app/Http/Controllers/ShipmentController.php`, `app/Services/ShipmentDispatchAuthorization.php`, `app/Services/ShipmentDocuments.php`.

### UT-SHIP-001 — Dispatch gate requires full financial balance

**Priority:** P0 · **Requirement:** REQ-SHIP · **Implementation:** planned

- **Preconditions:** F-PRODUCTS and F-MONEY; partial shipments S1/S2; fake public upload disk
- **Data:** Invoice due 1 versus due 0
1. Evaluate dispatch authorization

**Expected:** Outstanding balance blocks dispatch absent authorised recorded override.

### UT-SHIP-002 — Partial delivery cannot exceed shipped balance

**Priority:** P0 · **Requirement:** REQ-SHIP · **Implementation:** planned

- **Preconditions:** F-PRODUCTS and F-MONEY; partial shipments S1/S2; fake public upload disk
- **Data:** Shipped 40; prior delivered 10; new delivery 31
1. Validate delivery quantity

**Expected:** 31 rejected; maximum remaining 30.

### UT-SHIP-003 — Delivery receipt can omit evidence file

**Priority:** P0 · **Requirement:** REQ-SHIP · **Implementation:** planned

- **Preconditions:** F-PRODUCTS and F-MONEY; partial shipments S1/S2; fake public upload disk
- **Data:** Valid receipt quantities; no POD file
1. Validate receipt request

**Expected:** No file-required validation; quantities and receipt fact preserved.

### UT-SHIP-004 — Delivery receipt letterhead follows the shipper and preserves its address snapshot

**Priority:** P1 · **Requirement:** REQ-SHIP · **Implementation:** linked executable evidence

- **Preconditions:** In-memory shipment and MissPack organisation; default shipping and billing addresses; fake public logo disk
- **Data:** MissPack spelling variants; vendor or empty shipper with MissPack receiver; current and historical addresses; configured print logo
1. Resolve issuer for each sender without querying shipment records
2. Compare snapshot address and own-company-only fallbacks
3. Select configured print logo, never the screen logo

**Expected:** Only a named own-company shipper gets our logo/contact/address fallback; shipment origin wins; receiver and cost payer do not affect issuer.

**Evidence:** `tests/Unit/ShipmentPrintIssuerTest.php#test_letterhead_follows_normalised_shipper_identity_not_receiver_or_cost_payer`; `tests/Unit/ShipmentPrintIssuerTest.php#test_stored_origin_and_contacts_take_precedence_over_current_organisation_settings`; `tests/Unit/ShipmentPrintIssuerTest.php#test_missing_own_origin_falls_back_to_default_shipping_then_billing_only`; `tests/Unit/ShipmentPrintIssuerTest.php#test_partial_shipment_address_is_not_silently_replaced_with_current_office`; `tests/Unit/ShipmentPrintIssuerTest.php#test_configured_print_logo_is_used_without_screen_logo`; `tests/Unit/ShipmentPrintIssuerTest.php#test_address_preserves_non_latin_city_state_and_country`

## Proformas, cash invoices and tax invoices (`SALES`)

Sources: `app/Services/CashInvoiceConversion.php`, `app/Services/CashInvoiceDeletion.php`, `app/Http/Controllers/SalesInvoiceController.php`.

### UT-SALES-001 — Cash invoice excludes GST from its amount

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** planned

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** Tax-inclusive PI lines; cash conversion selected
1. Calculate cash document rates and totals

**Expected:** Cash invoice has no GST contribution; no tax-inclusive total hidden behind missing tax rows.

### UT-SALES-002 — Conversion transfer is capped cash-first

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** planned

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** Cash100000 plus bank50000; cash document100000
1. Choose transfer100000
2. Evaluate receipt distribution

**Expected:** Only100000 transfers; remaining50000 stays with PI.

### UT-SALES-003 — Remaining tax invoice preserves proportional original items

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** planned

- **Preconditions:** F-MONEY: PI INR300000; cash100000 plus bank50000; selected cash conversion100000
- **Data:** Original PI multiple quantities/rates; partial cash conversion
1. Calculate remainder rates and line totals

**Expected:** Original items preserved; proportionally adjusted rates reproduce remaining amount within rounding tolerance.

## Purchase documents and vendor settlement (`PURCHASE`)

Sources: `app/Services/PurchaseBillLedger.php`, `app/Services/PurchaseInvoiceMoney.php`, `app/Http/Controllers/PurchaseInvoiceController.php`.

### UT-PURCHASE-001 — Active payments determine bill balance

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** planned

- **Preconditions:** F-MONEY; V1 bill100000; active payments25000; cancelled payment10000
- **Data:** Bill100000; active25000; cancelled10000
1. Calculate paid and remaining

**Expected:** Paid25000 and balance75000; cancelled payment excluded.

### UT-PURCHASE-002 — Cancelled and draft statuses are preserved

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** planned

- **Preconditions:** F-MONEY; V1 bill100000; active payments25000; cancelled payment10000
- **Data:** Draft/cancelled bill plus refresh request
1. Refresh payment-derived money

**Expected:** Draft/cancelled state is not changed to received/paid.

### UT-PURCHASE-003 — Payment status can move backwards after reversal

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** planned

- **Preconditions:** F-MONEY; V1 bill100000; active payments25000; cancelled payment10000
- **Data:** Previously paid bill; all active payments removed
1. Refresh money state

**Expected:** Balance reopens; bill returns to unpaid/received state, not stale paid.

## Cashflow, allocations and account transfers (`CASH`)

Sources: `app/Services/CashflowAllocations.php`, `app/Services/CashflowLedger.php`, `app/Http/Controllers/CashflowController.php`.

### UT-CASH-001 — Allocation cannot exceed available source amount

**Priority:** P0 · **Requirement:** REQ-CASH · **Implementation:** planned

- **Preconditions:** F-MONEY; cash and bank accounts with explicit source currencies; transfer category
- **Data:** Receipt1000 allocated600; requested500
1. Calculate remaining and validate allocation

**Expected:** Only400 available;500 rejected; no negative residual.

### UT-CASH-002 — Party label follows canonical linked party

**Priority:** P0 · **Requirement:** REQ-CASH · **Implementation:** planned

- **Preconditions:** F-MONEY; cash and bank accounts with explicit source currencies; transfer category
- **Data:** Selector client; vendor_id=V1; no client_id
1. Align party type

**Expected:** Vendor label selected; unrelated employee/account linkage retained where meaningful.

### UT-CASH-003 — Internal transfers are not income or expense

**Priority:** P0 · **Requirement:** REQ-CASH · **Implementation:** planned

- **Preconditions:** F-MONEY; cash and bank accounts with explicit source currencies; transfer category
- **Data:** Cash-to-bank contra pair10000
1. Calculate P&L movement

**Expected:** Transfer excluded from income/expense while account balances show both legs.

## Recurring cashflow planning and posting (`RECUR`)

Sources: `app/Services/RecurrenceSchedule.php`, `app/Services/RecurrencePosting.php`, `app/Services/RecurrencePlan.php`.

### UT-RECUR-001 — Month-end anchor is restored

**Priority:** P1 · **Requirement:** REQ-RECUR · **Implementation:** planned

- **Preconditions:** Monthly rule anchored31January; daily/weekly variants; approved and paused rules
- **Data:** 31Jan2028 monthly five dates
1. Calculate dates using original anchor

**Expected:** 31Jan,29Feb,31Mar,30Apr,31May; sequence IDs begin at1.

### UT-RECUR-002 — Date/count/cap are independent ceilings

**Priority:** P1 · **Requirement:** REQ-RECUR · **Implementation:** planned

- **Preconditions:** Monthly rule anchored31January; daily/weekly variants; approved and paused rules
- **Data:** 5Oct start; until1Dec; limit10; cap24
1. Calculate schedule

**Expected:** Only5Oct and5Nov; fifth-day December occurrence is after end date.

### UT-RECUR-003 — Invalid frequency and sequence are rejected

**Priority:** P1 · **Requirement:** REQ-RECUR · **Implementation:** planned

- **Preconditions:** Monthly rule anchored31January; daily/weekly variants; approved and paused rules
- **Data:** Unknown rhythm or sequence0
1. Call calendar calculator

**Expected:** Explicit exception; no silently invented date.

## Party statements and reconciliation (`STATEMENT`)

Sources: `app/Services/PartyStatement.php`, `app/Http/Controllers/PartyStatementController.php`.

### UT-STATEMENT-001 — Running balance respects debit/credit direction

**Priority:** P1 · **Requirement:** REQ-STATEMENT · **Implementation:** planned

- **Preconditions:** C1/V1 opening balances and dated receipts/documents in INR and USD
- **Data:** Opening1000; debit500; credit300
1. Calculate chronological rows

**Expected:** Closing1200 under declared statement convention.

### UT-STATEMENT-002 — Currencies are not implicitly added

**Priority:** P1 · **Requirement:** REQ-STATEMENT · **Implementation:** planned

- **Preconditions:** C1/V1 opening balances and dated receipts/documents in INR and USD
- **Data:** INR1000 and USD100
1. Assemble statement totals

**Expected:** Separate currency totals or explicit FX basis; no meaningless1100 mixed total.

### UT-STATEMENT-003 — Opening balance includes only pre-window activity

**Priority:** P1 · **Requirement:** REQ-STATEMENT · **Implementation:** planned

- **Preconditions:** C1/V1 opening balances and dated receipts/documents in INR and USD
- **Data:** Window10Oct onward; entries9Oct and10Oct
1. Split opening and in-period rows

**Expected:** 9Oct in opening once;10Oct in rows once.

### UT-STATEMENT-004 — Balance issuer address into three compact print lines without truncation

**Priority:** P1 · **Requirement:** REQ-STATEMENT · **Implementation:** linked executable evidence

- **Preconditions:** Pure presentation helper; no database or live issuer data
- **Data:** Six comma-separated street parts plus city/state/pin/country; multiline address; missing locality
1. Balance complete street parts across two lines
2. Keep locality third
3. Preserve missing values rather than inventing address text

**Expected:** Complete street retained; default issuer has exactly three visual lines in the real A4 PDF; website remains independent.

**Evidence:** `tests/Unit/StatementPrintHeaderTest.php#test_long_issuer_street_is_balanced_into_two_lines_with_locality_third`; `tests/Unit/StatementPrintHeaderTest.php#test_multiline_addresses_and_missing_fields_are_not_invented_or_truncated`

## Asset register, allocation and depreciation (`ASSET`)

Sources: `app/Services/AssetDepreciation.php`, `app/Http/Controllers/FixedAssetController.php`.

### UT-ASSET-001 — Indian financial year changes on1April

**Priority:** P1 · **Requirement:** REQ-ASSET · **Implementation:** linked executable evidence

- **Preconditions:** Asset cost100000 GST18000 useful life5 residual5 percent; SLM/WDV
- **Data:** 31Mar2026 and1Apr2026; leap day
1. Resolve financial year and inverse bounds

**Expected:** 2025-26 and2026-27; consistent1April to31March bounds.

**Evidence:** `tests/Unit/AssetDepreciationTest.php#test_indian_financial_year_boundaries`

### UT-ASSET-002 — Depreciation conserves cost minus residual

**Priority:** P1 · **Requirement:** REQ-ASSET · **Implementation:** linked executable evidence

- **Preconditions:** Asset cost100000 GST18000 useful life5 residual5 percent; SLM/WDV
- **Data:** SLM/WDV; cost100000 and12345.67; life3/5
1. Build schedule
2. Sum charges and check every closing

**Expected:** Charges nonnegative; final residual exact; no closing below residual.

**Evidence:** `tests/Unit/AssetDepreciationTest.php#test_schedule_conserves_value_and_never_depreciates_below_residual`

### UT-ASSET-003 — GST credit changes capitalised basis

**Priority:** P1 · **Requirement:** REQ-ASSET · **Implementation:** linked executable evidence

- **Preconditions:** Asset cost100000 GST18000 useful life5 residual5 percent; SLM/WDV
- **Data:** Cost100000 GST18000; credit claimed versus capitalised
1. Calculate capitalised cost

**Expected:** 100000 when credit claimed;118000 when total capitalised.

**Evidence:** `tests/Unit/AssetDepreciationTest.php#test_capitalised_basis_distinguishes_claimed_gst_credit`

## Organisation, addresses and banking identity (`ORG`)

Sources: `app/Http/Controllers/OrganisationController.php`, `app/Models/Organisation.php`.

### UT-ORG-001 — Sender and recipient are distinct identities

**Priority:** P1 · **Requirement:** REQ-ORG · **Implementation:** planned

- **Preconditions:** MissPack and third-party organisations; two addresses and bank accounts each
- **Data:** Sender MissPack; recipient C1
1. Resolve invoice/label sender details

**Expected:** Sender is never replaced by recipient name.

### UT-ORG-002 — Optional organisation fields do not invent data

**Priority:** P1 · **Requirement:** REQ-ORG · **Implementation:** planned

- **Preconditions:** MissPack and third-party organisations; two addresses and bank accounts each
- **Data:** No optional tax/bank field
1. Build document identity

**Expected:** Missing values omitted or explicitly blank; no another organisation fallback.

### UT-ORG-003 — Default address choice is deterministic

**Priority:** P1 · **Requirement:** REQ-ORG · **Implementation:** planned

- **Preconditions:** MissPack and third-party organisations; two addresses and bank accounts each
- **Data:** One marked default and another address
1. Resolve billing/dispatch address

**Expected:** Appropriate default selected by type; no arbitrary first unrelated address.

## Office services and linked spending (`SERVICE`)

Sources: `app/Http/Controllers/OfficeServiceController.php`, `app/Models/OfficeService.php`.

### UT-SERVICE-001 — Office service party link is recognised

**Priority:** P1 · **Requirement:** REQ-SERVICE · **Implementation:** planned

- **Preconditions:** Service provider subscription and related invoice/payment/cashflow rows
- **Data:** office_service_id present; selector other
1. Resolve party ownership and label

**Expected:** Office service included as canonical supported link.

### UT-SERVICE-002 — Service active/expired boundary uses due date

**Priority:** P1 · **Requirement:** REQ-SERVICE · **Implementation:** planned

- **Preconditions:** Service provider subscription and related invoice/payment/cashflow rows
- **Data:** Expiry yesterday,today,tomorrow
1. Classify current service

**Expected:** Date semantics consistent with displayed status and filter.

### UT-SERVICE-003 — Recurring service expense retains source currency

**Priority:** P1 · **Requirement:** REQ-SERVICE · **Implementation:** planned

- **Preconditions:** Service provider subscription and related invoice/payment/cashflow rows
- **Data:** USD subscription and INR account
1. Validate money input

**Expected:** No implicit currency merge; conversion requires explicit supported basis.

## Tasks and coordinator queues (`TASK`)

Sources: `app/Http/Controllers/TaskController.php`, `app/Services/ProjectCoordinatorQueue.php`.

### UT-TASK-001 — Completed task is not overdue

**Priority:** P1 · **Requirement:** REQ-TASK · **Implementation:** planned

- **Preconditions:** Assigned/unassigned tasks; overdue/today/future deadlines; two coordinators
- **Data:** Completed task with past due date
1. Calculate overdue bucket

**Expected:** Excluded from overdue active workload.

### UT-TASK-002 — Coordinator queue picks next actionable step

**Priority:** P1 · **Requirement:** REQ-TASK · **Implementation:** planned

- **Preconditions:** Assigned/unassigned tasks; overdue/today/future deadlines; two coordinators
- **Data:** Earlier completed step; next blocked and ready candidates
1. Compute current step ordering

**Expected:** Next unresolved action shown with blocker, not already completed task.

### UT-TASK-003 — Workload counts use full filtered scope

**Priority:** P1 · **Requirement:** REQ-TASK · **Implementation:** planned

- **Preconditions:** Assigned/unassigned tasks; overdue/today/future deadlines; two coordinators
- **Data:** 27 assigned tasks;20-per-page list
1. Calculate badges

**Expected:** Badge27 not current page20.

## Private notes (`NOTE`)

Sources: `app/Services/NoteIntake.php`, `app/Http/Controllers/NoteController.php`, `app/Models/Note.php`.

### UT-NOTE-001 — No owner means no rows

**Priority:** P1 · **Requirement:** REQ-NOTE · **Implementation:** planned

- **Preconditions:** Employee E owns note N1; office A owns note N2; independent sessions
- **Data:** ownedBy(null) and ownedBy(0)
1. Build note scope

**Expected:** Fail-closed query1=0; never all notes.

### UT-NOTE-002 — Unknown colour normalises to default

**Priority:** P1 · **Requirement:** REQ-NOTE · **Implementation:** planned

- **Preconditions:** Employee E owns note N1; office A owns note N2; independent sessions
- **Data:** Blank/invalid colour and valid yellow
1. Normalise vocabulary

**Expected:** Only declared colour keys reach writer/UI.

### UT-NOTE-003 — Excerpt folds whitespace without changing body

**Priority:** P1 · **Requirement:** REQ-NOTE · **Implementation:** planned

- **Preconditions:** Employee E owns note N1; office A owns note N2; independent sessions
- **Data:** Body with newlines and repeated spaces
1. Calculate excerpt

**Expected:** One readable line; blank body returns empty excerpt.

## Feedback scores and closure (`FEEDBACK`)

Sources: `app/Services/FeedbackVocabulary.php`, `app/Services/FeedbackIntake.php`, `app/Services/ProjectFeedbackClosure.php`.

### UT-FEEDBACK-001 — One low dimension makes detractor

**Priority:** P1 · **Requirement:** REQ-FEEDBACK · **Implementation:** planned

- **Preconditions:** Scores1..5, recommendation0..10, nullable dimensions and secure request token
- **Data:** Overall5; recommendation10; dimensions5,2,5
1. Classify response

**Expected:** Detractor, not promoter from generous headline scores.

### UT-FEEDBACK-002 — NPS formula uses promoters minus detractors

**Priority:** P1 · **Requirement:** REQ-FEEDBACK · **Implementation:** planned

- **Preconditions:** Scores1..5, recommendation0..10, nullable dimensions and secure request token
- **Data:** Recommendations10,8 then10,5 then none
1. Compute NPS

**Expected:** 50,0,null respectively; no answers is not zero satisfaction.

### UT-FEEDBACK-003 — Blank dimension is not score zero

**Priority:** P1 · **Requirement:** REQ-FEEDBACK · **Implementation:** planned

- **Preconditions:** Scores1..5, recommendation0..10, nullable dimensions and secure request token
- **Data:** Dimensions5,null,5
1. Classify and average

**Expected:** Null ignored in scoring; published empty-dimension policy reviewed separately.

## Office briefings, alerts and watchers (`BRIEF`)

Sources: `app/Services/OfficeBriefing.php`, `app/Http/Controllers/OfficeAlertController.php`, `app/Models/OfficeSetting.php`.

### UT-BRIEF-001 — Team watcher sees only its desk and office-wide notices

**Priority:** P1 · **Requirement:** REQ-BRIEF · **Implementation:** planned

- **Preconditions:** Sales,operations and director watchers; unscoped office briefing; critical deadline
- **Data:** Sales watcher; operations-specific notice
1. Evaluate watchesTeam

**Expected:** Operations-only hidden from sales; office-wide visible per policy.

### UT-BRIEF-002 — Critical and routine alerts remain distinct

**Priority:** P1 · **Requirement:** REQ-BRIEF · **Implementation:** planned

- **Preconditions:** Sales,operations and director watchers; unscoped office briefing; critical deadline
- **Data:** Overdue mandatory gate versus ordinary reminder
1. Classify tone and urgency

**Expected:** Critical alert red/actionable; routine item not mislabelled as blocker.

### UT-BRIEF-003 — Dismissal does not erase source exception

**Priority:** P1 · **Requirement:** REQ-BRIEF · **Implementation:** planned

- **Preconditions:** Sales,operations and director watchers; unscoped office briefing; critical deadline
- **Data:** Alert linked to unresolved project exception
1. Compute source and display state

**Expected:** Dismissal is reader state; exception remains unresolved.

## Global search and navigation (`SEARCH`)

Sources: `app/Services/GlobalSearch.php`, `app/Http/Controllers/GlobalSearchController.php`.

### UT-SEARCH-001 — Search uses permission-aware module scopes

**Priority:** P1 · **Requirement:** REQ-SEARCH · **Implementation:** planned

- **Preconditions:** Records across sales,finance,projects and private notes; sales-only reader
- **Data:** Reader has sales.view only
1. Select searchable sources

**Expected:** Finance/payroll/private other-login sources omitted.

### UT-SEARCH-002 — Search term remains bound data

**Priority:** P1 · **Requirement:** REQ-SEARCH · **Implementation:** planned

- **Preconditions:** Records across sales,finance,projects and private notes; sales-only reader
- **Data:** Quote/SQL-like text and Unicode query
1. Build search predicates

**Expected:** No executable SQL injection; Unicode searchable safely.

### UT-SEARCH-003 — Empty query has bounded predictable result

**Priority:** P1 · **Requirement:** REQ-SEARCH · **Implementation:** planned

- **Preconditions:** Records across sales,finance,projects and private notes; sales-only reader
- **Data:** Blank query; high record count
1. Resolve search request

**Expected:** No unbounded entire-database enumeration.

## Settings, workflow policy and master options (`SETTINGS`)

Sources: `app/Http/Controllers/SettingsController.php`, `app/Http/Controllers/WorkflowSettingController.php`, `app/Services/SettingsDirectory.php`.

### UT-SETTINGS-001 — Settings directory maps unique stable destinations

**Priority:** P1 · **Requirement:** REQ-SETTINGS · **Implementation:** planned

- **Preconditions:** Settings manager and reader; master options referenced by existing records
- **Data:** Configured settings sections
1. Enumerate keys and routes

**Expected:** No duplicate keys or dead section destinations.

### UT-SETTINGS-002 — Disabled master option remains readable historically

**Priority:** P1 · **Requirement:** REQ-SETTINGS · **Implementation:** planned

- **Preconditions:** Settings manager and reader; master options referenced by existing records
- **Data:** Existing document uses now-disabled option
1. Resolve label versus new-entry options

**Expected:** History readable; inactive option unavailable for new selection.

### UT-SETTINGS-003 — Workflow boolean inputs normalise safely

**Priority:** P1 · **Requirement:** REQ-SETTINGS · **Implementation:** planned

- **Preconditions:** Settings manager and reader; master options referenced by existing records
- **Data:** Missing checkbox,0,1 and invalid array
1. Validate workflow configuration

**Expected:** Boolean semantics explicit; invalid structure rejected.

## Client portal authentication and data isolation (`CPORTAL`)

Sources: `app/Http/Middleware/ClientPortalAuthenticate.php`, `app/Services/ClientPortalAccess.php`, `app/Http/Controllers/ClientPortalInvoiceController.php`.

### UT-CPORTAL-001 — All portal enablement flags are required

**Priority:** P0 · **Requirement:** REQ-CPORTAL · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY; CP1 for C1 and CP2 for C2; disabled/must-change-password accounts
- **Data:** User portal_enabled,is_active and client portal_enabled combinations
1. Evaluate canLogin across all8 combinations

**Expected:** Only all-enabled combination may log in.

**Evidence:** `tests/Unit/ClientPortalLoginPolicyTest.php#test_every_enabled_flag_is_required`

### UT-CPORTAL-002 — Missing linked client and wrong request actor fail closed

**Priority:** P0 · **Requirement:** REQ-CPORTAL · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY; CP1 for C1 and CP2 for C2; disabled/must-change-password accounts
- **Data:** Portal user without client; stdClass request attribute
1. Resolve portal user/client

**Expected:** Cannot log in; access helper returns null.

**Evidence:** `tests/Unit/ClientPortalLoginPolicyTest.php#test_missing_client_or_wrong_request_attribute_never_resolves_portal_access`

### UT-CPORTAL-003 — One-time password route restrictions are explicit

**Priority:** P0 · **Requirement:** REQ-CPORTAL · **Implementation:** planned

- **Preconditions:** F-IDENTITY; CP1 for C1 and CP2 for C2; disabled/must-change-password accounts
- **Data:** must_change_password account
1. Resolve allowed destination

**Expected:** Password-change and logout only until change; no report/document content.

## Vendor portal and secure share links (`VPORTAL`)

Sources: `app/Http/Controllers/VendorPortalController.php`, `app/Http/Controllers/VendorShareController.php`, `app/Models/VendorPortalUser.php`.

### UT-VPORTAL-001 — Revoked or expired share is unusable

**Priority:** P0 · **Requirement:** REQ-VPORTAL · **Implementation:** planned

- **Preconditions:** VP1 for V1 and VP2 for V2; valid,revoked,expired share tokens
- **Data:** Token valid until past date or revoked_at set
1. Evaluate share validity

**Expected:** Revoked/expired token cannot authorise access.

### UT-VPORTAL-002 — Share scope does not expand to adjacent documents

**Priority:** P0 · **Requirement:** REQ-VPORTAL · **Implementation:** planned

- **Preconditions:** VP1 for V1 and VP2 for V2; valid,revoked,expired share tokens
- **Data:** Share for single purchase document
1. Resolve allowed document IDs

**Expected:** Only explicit share scope, not all vendor or project records.

### UT-VPORTAL-003 — Disabled vendor portal account cannot authenticate

**Priority:** P0 · **Requirement:** REQ-VPORTAL · **Implementation:** planned

- **Preconditions:** VP1 for V1 and VP2 for V2; valid,revoked,expired share tokens
- **Data:** Inactive/disabled vendor login
1. Evaluate login predicate

**Expected:** Access denied regardless of password correctness.

## Overview and module dashboard reporting (`DASH`)

Sources: `app/Services/DashboardPeriod.php`, `app/Services/ModuleDashboard.php`, `app/Http/Controllers/AdminDashboardController.php`, `app/Services/DashboardAnalytics.php`.

### UT-DASH-001 — Custom range is inclusive and same-day valid

**Priority:** P1 · **Requirement:** REQ-DASH · **Implementation:** planned

- **Preconditions:** Frozen25Oct2026; custom10..12Oct; receipts before,within,after and two currencies
- **Data:** 10Oct..12Oct and25Oct..25Oct
1. Parse custom bounds

**Expected:** Start00:00:00; end23:59:59.999999; same day retained.

**Note:** Unit target; related component evidence is in DashboardCustomPeriodTest. Not mapped as isolated unit coverage.

### UT-DASH-002 — Invalid custom dates are rejected

**Priority:** P1 · **Requirement:** REQ-DASH · **Implementation:** planned

- **Preconditions:** Frozen25Oct2026; custom10..12Oct; receipts before,within,after and two currencies
- **Data:** Reversed,missing,impossible date, timestamp and array inputs
1. Validate both overview and module period contexts

**Expected:** Field validation errors; no silently substituted reporting period.

**Note:** Unit target; related component evidence is in DashboardCustomPeriodTest. Not mapped as isolated unit coverage.

### UT-DASH-003 — Prior comparison has same day count

**Priority:** P1 · **Requirement:** REQ-DASH · **Implementation:** planned

- **Preconditions:** Frozen25Oct2026; custom10..12Oct; receipts before,within,after and two currencies
- **Data:** 28Feb2024..1Mar2024
1. Compute prior bounds

**Expected:** 25Feb..27Feb; leap day included in current period.

**Note:** Unit target; related component evidence is in DashboardCustomPeriodTest. Not mapped as isolated unit coverage.

### UT-DASH-004 — Consolidated dashboard ageing and module visibility rules

**Priority:** P1 · **Requirement:** REQ-DASH · **Implementation:** linked executable evidence

- **Preconditions:** Fixed25Oct2026; office users with full, sales-only and employee permissions
- **Data:** Undated/today/future/30/60/90+ due dates; sales.view and operations.manage
1. Classify due-date boundaries
2. Resolve only modules authorised by existing permission matrix

**Expected:** Due today is not overdue; stable ageing bands; employees get no office modules and manage implies module view.

**Evidence:** `tests/Unit/DashboardAnalyticsRulesTest.php#test_ageing_bands_do_not_mark_due_today_as_overdue`; `tests/Unit/DashboardAnalyticsRulesTest.php#test_dashboard_directory_only_contains_authorised_modules`

## Audit trail and sensitive operations (`AUDIT`)

Sources: `app/Services/AuditTrail.php`, `app/Http/Middleware/AuditMutations.php`, `app/Http/Controllers/AuditLogController.php`.

### UT-AUDIT-001 — Public tokens are redacted from logged URL

**Priority:** P0 · **Requirement:** REQ-AUDIT · **Implementation:** planned

- **Preconditions:** Office,portal and anonymous actors; sensitive settings mutation and secret URL token
- **Data:** Request path includes secure token
1. Build audit URL

**Expected:** Token replaced with redacted marker rather than stored plaintext.

### UT-AUDIT-002 — Sensitive request bodies are not logged wholesale

**Priority:** P0 · **Requirement:** REQ-AUDIT · **Implementation:** planned

- **Preconditions:** Office,portal and anonymous actors; sensitive settings mutation and secret URL token
- **Data:** Password,bank account and attachment bytes
1. Build audit event facts

**Expected:** No raw password/token/upload contents in audit payload.

### UT-AUDIT-003 — Denied action is distinct from successful mutation

**Priority:** P0 · **Requirement:** REQ-AUDIT · **Implementation:** planned

- **Preconditions:** Office,portal and anonymous actors; sensitive settings mutation and secret URL token
- **Data:** Permission failure versus successful update
1. Classify event and response status

**Expected:** Denial retains required permission and actor; not labelled success.

## Shared file policy and storage lifecycle (`FILES`)

Sources: `app/Services/DocumentUpload.php`, `app/Http/Controllers/ShipmentController.php`.

### UT-FILES-001 — Allowed extensions are case insensitive

**Priority:** P0 · **Requirement:** REQ-FILES · **Implementation:** linked executable evidence

- **Preconditions:** Fake local public/private disks; safe PDF,disallowed executable,20MB and20MB+1KB files
- **Data:** Every supported extension in uppercase
1. Evaluate extension and allowlist

**Expected:** Normalised lowercase accepted; original name retained only as metadata.

**Evidence:** `tests/Unit/DocumentUploadPolicyTest.php#test_supported_extensions_are_case_insensitive`

### UT-FILES-002 — Executable and unlisted extensions are rejected

**Priority:** P0 · **Requirement:** REQ-FILES · **Implementation:** linked executable evidence

- **Preconditions:** Fake local public/private disks; safe PDF,disallowed executable,20MB and20MB+1KB files
- **Data:** payload.php,image.jpg.php,exe,html,sh and no extension
1. assertAllowed with field=evidence

**Expected:** Validation error belongs to evidence; no storage performed.

**Evidence:** `tests/Unit/DocumentUploadPolicyTest.php#test_prohibited_extensions_are_rejected_with_the_correct_field`

### UT-FILES-003 — File size limit is inclusive

**Priority:** P0 · **Requirement:** REQ-FILES · **Implementation:** linked executable evidence

- **Preconditions:** Fake local public/private disks; safe PDF,disallowed executable,20MB and20MB+1KB files
- **Data:** Exactly20480KB and20481KB
1. Validate files with same allowed suffix

**Expected:** 20MB accepted;1KB over rejected; required/optional controls share policy.

**Evidence:** `tests/Unit/DocumentUploadPolicyTest.php#test_size_limit_includes_exactly_twenty_megabytes_but_rejects_one_kilobyte_more`; `tests/Unit/DocumentUploadPolicyTest.php#test_optional_and_required_upload_controls_share_the_same_policy`

## Shared UI, printing and accessible interactions (`UI`)

Sources: `resources/views/components/drawer.blade.php`, `public/assets/js/master-drawer.js`, `docs/ui-design-guidelines.md`.

### UT-UI-001 — Confirmation label matches operation

**Priority:** P1 · **Requirement:** REQ-UI · **Implementation:** planned

- **Preconditions:** Desktop1440,tablet768,mobile390 and320; light/dark; keyboard-only input
- **Data:** Non-delete conversion prompt and destructive delete
1. Resolve confirmation configuration

**Expected:** Conversion says Yes/Convert; only deletion uses Delete.

### UT-UI-002 — Print options retain explicit booleans

**Priority:** P1 · **Requirement:** REQ-UI · **Implementation:** planned

- **Preconditions:** Desktop1440,tablet768,mobile390 and320; light/dark; keyboard-only input
- **Data:** Page-number false; portrait default; landscape selection
1. Normalise stored print settings

**Expected:** False remains false; valid orientation retained; invalid options default safely.

### UT-UI-003 — Tab state matches authorised destination

**Priority:** P1 · **Requirement:** REQ-UI · **Implementation:** planned

- **Preconditions:** Desktop1440,tablet768,mobile390 and320; light/dark; keyboard-only input
- **Data:** Requested inaccessible tab versus authorised overview
1. Resolve active tab

**Expected:** Safe authorised fallback; no hidden data fetch from inaccessible tab.

### UT-UI-004 — One searchable Select2 policy for every option count and entry point

**Priority:** P1 · **Requirement:** REQ-UI · **Implementation:** planned

- **Preconditions:** Node builtin test runner; shared initializer executed in isolated VM; application/vendor sources separate
- **Data:** 0,1,2,8,9 and50 options; office, client portal, public lead and print entry points
1. Execute the real initializer and inspect search settings for every count
2. Reject competing initializers and per-control search hiding
3. Require cache-busted shared initializer in every entry point

**Expected:** minimumResultsForSearch is always0; selection class/width remain intact; no separate policy hides search; entry points receive updated script.

**Note:** Executed Node unit regressions: tools/testing/select2-policy.test.cjs (8 tests), now part of npm run test:contracts. Catalogue exact-method links count PHP methods only; this is not an unexecuted case.

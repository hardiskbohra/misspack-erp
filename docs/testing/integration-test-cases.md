# Integration test cases

Generated from [catalogue.json](catalogue.json); edit the JSON and run `npm run test:catalogue:build`.

**Implementation links are not a passing result.** Unlinked cases are planned. Partial evidence does not cover every step. Unit targets linked only to component evidence remain unclaimed as isolated unit coverage.

## Authentication and office permissions (`AUTH`)

Sources: `app/Services/PermissionMatrix.php`, `app/Http/Controllers/AuthController.php`, `app/Http/Middleware/EnsureUserIsAdmin.php`, `app/Support/Totp.php`, `app/Services/TwoFactorAuthentication.php`, `app/Http/Controllers/TwoFactorController.php`, `app/Http/Controllers/TwoFactorChallengeController.php`.

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

### IT-AUTH-004 — Office login locks out after five wrong passwords

**Priority:** P0 · **Requirement:** REQ-AUTH · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY fixture tables (users, audit logs) on disposable SQLite.
- **Data:** One office user with a strong password; five wrong attempts then one right one from the same client.
1. POST /login five times with a wrong password
2. POST /login with the right password during the lockout
3. Check the lockout is an audit event
4. Repeat with failures below the limit and confirm a success clears the counter

**Expected:** The sixth attempt is refused with a retry-after message even with the right password; a successful sign-in clears earlier failures; auth.login.throttled is audited.

**Evidence:** `tests/Feature/LoginSecurityTest.php#test_five_wrong_passwords_lock_the_email_and_network_out`; `tests/Feature/LoginSecurityTest.php#test_a_successful_sign_in_forgets_earlier_failures`

### IT-AUTH-005 — Two-factor accounts pass the password only to a challenge

**Priority:** P0 · **Requirement:** REQ-AUTH · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY fixture; user with two-factor confirmed via the service.
- **Data:** Strong password; live TOTP codes from the stored secret; the eight recovery codes from enrolment.
1. POST /login with the right password and assert no session is created, only a challenge redirect
2. Finish at /two-factor-challenge with a current TOTP code and assert the session opens
3. Sign in with a recovery code, then replay the same code after logout, expecting refusal
4. Make six wrong code guesses and assert the challenge is torn up and audited

**Expected:** Password alone never opens a session for a two-factor account; recovery codes are single-use; repeated guessing ends the challenge with an auth.two_factor.throttled audit event.

**Evidence:** `tests/Feature/LoginSecurityTest.php#test_a_right_password_only_buys_a_challenge_when_two_factor_is_on`; `tests/Feature/LoginSecurityTest.php#test_recovery_codes_work_exactly_once`; `tests/Feature/LoginSecurityTest.php#test_challenge_gives_up_after_five_wrong_codes`

### IT-AUTH-006 — One password policy everywhere passwords are set

**Priority:** P1 · **Requirement:** REQ-AUTH · **Implementation:** linked executable evidence

- **Preconditions:** F-IDENTITY fixture; signed-in office user.
- **Data:** A weak candidate (lowercaseonly1x) and a strong one (Str0nger-Passw0rd); enrolment confirm codes.
1. PUT /account/password with the weak candidate and expect a validation error on password
2. PUT /account/password with the strong candidate and expect the stored hash to change
3. Walk the account-page enrolment: start, wrong confirm code, right confirm code, then disable with the current password

**Expected:** The default strength rule rejects the weak password and accepts the strong one; pending enrolment never locks sign-in; confirm turns two-factor on and disable turns it off again.

**Evidence:** `tests/Feature/LoginSecurityTest.php#test_password_change_enforces_the_default_strength_rule`; `tests/Feature/LoginSecurityTest.php#test_the_account_page_enrols_and_disables_two_factor`

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

### IT-PEOPLE-004 — Photo-first identity is consistent through listing record account and edit upload lifecycle

**Priority:** P1 · **Requirement:** REQ-PEOPLE; REQ-FILES; REQ-UI · **Implementation:** linked executable evidence

- **Preconditions:** Analytics fixture plus employee document/payslip schema, office session, fake public photo; Chromium HTTP captures
- **Data:** Saved PNG, replacement with stale remove flag, invalid upload and explicit removal;1440/900/390px light/dark
1. Render real list, record, account and both shared shell menus
2. Read edit JSON and replace saved photo with stale remove flag
3. Reject invalid upload and remove photo explicitly
4. Browser checks saved/replacement previews, removal, failed-image initials and no HTML or javascript URL injection

**Expected:** Current photos preferred everywhere; initials when absent or unloadable; valid replacement wins over remove; invalid upload preserves original; no raw HTML interpolation.

**Evidence:** `tests/Integration/PublicMediaAvatarTest.php#test_user_listing_record_account_and_edit_json_use_the_current_photo`; `tests/Integration/PublicMediaAvatarTest.php#test_replacement_photo_wins_over_a_stale_remove_flag_and_is_servable`; `tests/Integration/PublicMediaAvatarTest.php#test_photo_removal_and_invalid_uploads_preserve_the_upload_contract`

### IT-PEOPLE-005 — Edit a user photo directly from any detail tab without changing account fields

**Priority:** P1 · **Requirement:** REQ-PEOPLE; REQ-UI; REQ-FILES; REQ-AUTH · **Implementation:** linked executable evidence

- **Preconditions:** Disposable Analytics fixture plus employee document/payslip schema; people.manage and people.view office users, employee and guest; fake public disk; captured HTTP views in Chromium
- **Data:** Saved photo, replacement with stale remove flag, explicit remove, no photo, empty/HTML/oversize upload, simulated failed storage;1440/900/390px light/dark
1. Render all six detail tabs with a visible Edit photo action for managers only
2. Open photo-only modal, preview saved image, replace/remove/cancel and inspect native multipart submission
3. Save via people.manage endpoint and return to same user/tab/year
4. Reject invalid or empty uploads with a dedicated validation bag and automatically reopen detail modal
5. Preserve old photo on failed storage and prevent edits to role/password/name/bank fields
6. Deny guest/employee/read-only mutations; verify focus trap/restoration and bounded mobile layout

**Expected:** Photo editing stays on detail page; original remains until save; only avatar is mutable; shared renderer/picker and validation policy remain consistent with listing edit; permissions, file safety, tab/year context and keyboard lifecycle preserved.

**Evidence:** `tests/Integration/UserDetailPhotoTest.php#test_detail_header_exposes_a_scoped_photo_editor_on_any_tab_only_to_managers`; `tests/Integration/UserDetailPhotoTest.php#test_photo_only_upload_preserves_all_other_fields_and_returns_to_the_same_record_context`; `tests/Integration/UserDetailPhotoTest.php#test_photo_removal_only_happens_on_save_and_displays_initials`; `tests/Integration/UserDetailPhotoTest.php#test_invalid_empty_and_oversize_uploads_reopen_the_photo_modal_without_losing_the_existing_photo`; `tests/Integration/UserDetailPhotoTest.php#test_storage_failure_does_not_delete_the_current_photo_or_change_the_record`; `tests/Integration/UserDetailPhotoTest.php#test_guest_employee_and_read_only_accounts_cannot_change_photos`

## Client master, KYC and account profile (`CLIENT`)

Sources: `app/Http/Controllers/ClientController.php`, `app/Services/ClientAccountSummary.php`, `app/Models/Client.php`, `app/Services/PublicShareLinks.php`.

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

### IT-CLIENT-008 — Short public bearer links retain the original security and legacy routes

**Priority:** P0 · **Requirement:** REQ-CLIENT; REQ-VENDOR; REQ-AUTH; REQ-AUDIT · **Implementation:** linked executable evidence

- **Preconditions:** Full disposable schema, private fake vendor evidence, testing-only application keys, guest and office sessions
- **Data:** Client and vendor KYC, other public types, altered signature/type/id, rotated token and previous app key; mounted subdirectory
1. Copy and open short KYC links; submit through the canonical forms
2. Rotate and revoke vendor tokens; try altered and noncanonical aliases
3. Verify previous-key handling, same-origin paths and isolated throttling
4. Check document ownership, privacy headers and audit redaction

**Expected:** Short links resolve only to allowlisted current tokens; old full links still work; tampered, deleted and rotated-token aliases fail; original KYC security remains authoritative.

**Note:** Real Chromium clipboard and guest navigation are a separate check; web-server access logs still need operational redaction. See public-short-links.md.

**Evidence:** `tests/Integration/PublicShortLinksTest.php#test_client_kyc_copy_field_contains_a_short_working_link_and_the_old_link_still_works`; `tests/Integration/PublicShortLinksTest.php#test_vendor_kyc_link_can_be_copied_opened_submitted_rotated_and_revoked`; `tests/Integration/PublicShortLinksTest.php#test_invalid_tampered_rotated_and_deleted_links_fail_without_redirecting_or_disclosing_secrets`; `tests/Integration/PublicShortLinksTest.php#test_old_signing_key_works_only_while_retained_and_never_invalidates_the_legacy_url`; `tests/Integration/PublicShortLinksTest.php#test_short_link_throttle_is_isolated_from_the_existing_vendor_kyc_limit`; `tests/Integration/PublicShortLinksTest.php#test_redirect_keeps_the_application_mount_prefix_without_becoming_an_external_url`; `tests/Integration/PublicShortLinksTest.php#test_authority_like_mount_prefixes_cannot_make_an_external_redirect`; `tests/Integration/PublicShortLinksTest.php#test_audit_redacts_the_alias_as_a_bearer_token`

## Vendor master and payable profile (`VENDOR`)

Sources: `app/Http/Controllers/VendorController.php`, `app/Models/Vendor.php`, `app/Services/VendorPaymentCashflowSync.php`, `app/Services/VendorKyc.php`, `app/Http/Controllers/VendorKycController.php`.

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

### IT-VENDOR-004 — Draft and submission stage details until authorised approval

**Priority:** P1 · **Requirement:** REQ-VENDOR; REQ-AUTH; REQ-UI; REQ-FILES · **Implementation:** linked executable evidence

- **Preconditions:** Disposable migrated database; VendorKycFixture; private storage and mail fakes; see vendor-kyc.md
- **Data:** On-hold RMB supplier; distinct maker/checker/reader; pending bank change; India/overseas vendor types
1. Save draft and submit malicious internal controls
2. Allow creator approval; deny read-only and stale approval
3. Approve current version and inspect reviewer attribution

**Expected:** Live identity and bank change only on authorised approval (creator allowed); commercial currency and operational status unaffected.

**Evidence:** `tests/Integration/VendorKycWorkflowTest.php#test_draft_and_final_stage_details_without_mutating_live_vendor_or_internal_controls`; `tests/Integration/VendorKycWorkflowTest.php#test_manager_applies_submission_atomically_but_reader_and_stale_reviews_cannot`; `tests/Integration/VendorKycWorkflowTest.php#test_vendor_creator_can_review_their_own_submission_and_is_recorded_as_reviewer`

### IT-VENDOR-005 — Revision, rejection and completed-KYC update cycles

**Priority:** P1 · **Requirement:** REQ-VENDOR; REQ-AUTH; REQ-UI; REQ-FILES · **Implementation:** linked executable evidence

- **Preconditions:** Disposable migrated database; VendorKycFixture; private storage and mail fakes; see vendor-kyc.md
- **Data:** On-hold RMB supplier; distinct maker/checker/reader; pending bank change; India/overseas vendor types
1. Require review reasons
2. Resubmit revision
3. Reject and reopen completed KYC with reason

**Expected:** Locked closed states; explicit update request permits new public submission and fresh review without changing existing bank prematurely.

**Evidence:** `tests/Integration/VendorKycWorkflowTest.php#test_revision_requires_reason_reopens_for_resubmission_and_rejection_locks`; `tests/Integration/VendorKycWorkflowTest.php#test_completed_kyc_can_be_reopened_with_a_reason_for_a_new_review_cycle`

### IT-VENDOR-006 — Scoped, revocable manual sharing without stale mark-sent conflicts

**Priority:** P1 · **Requirement:** REQ-VENDOR; REQ-AUTH; REQ-UI; REQ-FILES · **Implementation:** linked executable evidence

- **Preconditions:** Disposable migrated database; VendorKycFixture; private storage and mail fakes; see vendor-kyc.md
- **Data:** On-hold RMB supplier; distinct maker/checker/reader; pending bank change; India/overseas vendor types
1. Issue/rotate/revoke links
2. Try old/stale links
3. Mark a live link as sent

**Expected:** 48-character secure token; old link access revoked; mark-sent does not invalidate an open form or claim automatic email delivery.

**Evidence:** `tests/Integration/VendorKycWorkflowTest.php#test_rotated_revoked_and_stale_links_cannot_save_or_download_other_vendor_evidence`; `tests/Integration/VendorKycWorkflowTest.php#test_mark_as_sent_does_not_stale_an_open_vendor_form_or_claim_email_delivery`

### IT-VENDOR-007 — Private evidence and public request boundaries

**Priority:** P1 · **Requirement:** REQ-VENDOR; REQ-AUTH; REQ-UI; REQ-FILES · **Implementation:** linked executable evidence

- **Preconditions:** Disposable migrated database; VendorKycFixture; private storage and mail fakes; see vendor-kyc.md
- **Data:** On-hold RMB supplier; distinct maker/checker/reader; pending bank change; India/overseas vendor types
1. Upload allowed and forbidden files
2. Try cross-vendor and unauthorised office downloads
3. Exercise CSRF, cache/error headers and throttling

**Expected:** Evidence is private; type/size/count restricted; cross-vendor access denied, missing CSRF rejected, excess submissions throttled and errors no-store/noindex.

**Evidence:** `tests/Integration/VendorKycWorkflowTest.php#test_evidence_is_private_validated_and_downloads_are_vendor_scoped`; `tests/Integration/VendorKycWorkflowTest.php#test_private_evidence_limits_and_office_download_permissions_are_enforced`; `tests/Integration/VendorKycWorkflowTest.php#test_validation_and_document_errors_have_private_response_headers`; `tests/Integration/VendorKycWorkflowTest.php#test_public_forms_keep_csrf_authentication_and_rate_limiting_boundaries`

### IT-VENDOR-008 — Office edits, audit and briefing preserve reviewed truth

**Priority:** P1 · **Requirement:** REQ-VENDOR; REQ-AUTH; REQ-UI; REQ-FILES · **Implementation:** linked executable evidence

- **Preconditions:** Disposable migrated database; VendorKycFixture; private storage and mail fakes; see vendor-kyc.md
- **Data:** On-hold RMB supplier; distinct maker/checker/reader; pending bank change; India/overseas vendor types
1. Change approved identity using fresh/stale edit versions
2. Try stale edit with replacement image
3. Inspect submission notification and audit

**Expected:** Identity edits reset KYC; stale edits cannot delete image; office briefing points to KYC; general audit excludes token and bank values.

**Evidence:** `tests/Integration/VendorKycWorkflowTest.php#test_office_profile_edits_require_fresh_version_and_invalidate_approved_identity`; `tests/Integration/VendorKycWorkflowTest.php#test_stale_office_form_cannot_delete_the_existing_vendor_image`; `tests/Integration/VendorKycWorkflowTest.php#test_notifications_audit_and_documents_do_not_leak_bearer_tokens_or_unapproved_values`

### IT-VENDOR-009 — Client-style responsive vendor wizard and review tab

**Priority:** P1 · **Requirement:** REQ-VENDOR; REQ-AUTH; REQ-UI; REQ-FILES · **Implementation:** linked executable evidence

- **Preconditions:** Disposable migrated database; VendorKycFixture; private storage and mail fakes; see vendor-kyc.md
- **Data:** On-hold RMB supplier; distinct maker/checker/reader; pending bank change; India/overseas vendor types
1. Render genuine HTTP states
2. Exercise shared JS in Chromium at 1440/390px
3. Confirm draft/submit/cancel, navigation visibility, errors and locked states

**Expected:** One visible step; correct validation/navigation; private bank data never stored in localStorage; creator/other managers can review; reader cannot review; approved client wizard still locked.

**Evidence:** `tests/Feature/VendorKycPresentationTest.php#test_actual_http_public_and_office_states_are_accessible_and_use_shared_components`

### IT-VENDOR-010 — KYC migration, native JSON and competing review locks

**Priority:** P1 · **Requirement:** REQ-VENDOR; REQ-AUTH; REQ-UI; REQ-FILES · **Implementation:** linked executable evidence

- **Preconditions:** Disposable migrated database; VendorKycFixture; private storage and mail fakes; see vendor-kyc.md
- **Data:** On-hold RMB supplier; distinct maker/checker/reader; pending bank change; India/overseas vendor types
1. Migrate existing vendor and roll back
2. Use a second MySQL connection against locked vendor
3. Approve once then replay stale review

**Expected:** Migration preserves existing accounts; native InnoDB competing approval times out; approval JSON applies atomically once; stale replay cannot overwrite.

**Evidence:** `tests/Integration/VendorKycWorkflowTest.php#test_kyc_schema_migrates_existing_vendors_and_rolls_back_cleanly`; `tests/Database/MySqlVendorKycTest.php#test_native_json_schema_locking_and_stale_review_protection`

### IT-VENDOR-011 — Indian submissions require bank/tax identifiers and private classified GST/PAN evidence

**Priority:** P1 · **Requirement:** REQ-VENDOR; REQ-FILES · **Implementation:** linked executable evidence

- **Preconditions:** Disposable migrated VendorKycFixture; fake private storage; real public/office HTTP requests
- **Data:** Indian and overseas profiles; named GST Certificate/PAN, generic/foreign-owned/missing files; drafts and legacy under-review submissions
1. Reject incomplete Indian final submissions without mutation
2. Save incomplete draft and typed evidence
3. Resubmit without re-uploading existing owned evidence
4. Reject generic, cross-vendor, missing, invalid and excess uploads
5. Check legacy approval cannot bypass compliance

**Expected:** Indian final submission/approval needs valid GSTIN, account number, IFSC and both private certificate categories. Drafts and overseas vendors exempt. Existing valid owned evidence is reused; combined five-file/20-total limits and private downloads retained.

**Evidence:** `tests/Integration/IndianVendorKycTest.php#test_indian_final_submission_requires_numbers_and_both_classified_documents`; `tests/Integration/IndianVendorKycTest.php#test_indian_draft_can_be_incomplete_and_saved_evidence_is_reused_on_submission_and_revision`; `tests/Integration/IndianVendorKycTest.php#test_generic_or_another_vendors_documents_do_not_satisfy_indian_compliance`; `tests/Integration/IndianVendorKycTest.php#test_named_evidence_rejects_invalid_files_and_counts_towards_the_combined_upload_limit`; `tests/Integration/IndianVendorKycTest.php#test_foreign_vendors_are_exempt_but_indian_country_aliases_are_not`; `tests/Integration/IndianVendorKycTest.php#test_legacy_incomplete_indian_submissions_cannot_be_approved`; `tests/Integration/IndianVendorKycTest.php#test_missing_private_file_does_not_count_as_valid_indian_evidence`

### IT-VENDOR-012 — Bank and beneficiary addresses stage, review, approve and edit as multiline text

**Priority:** P1 · **Requirement:** REQ-VENDOR; REQ-UI · **Implementation:** linked executable evidence

- **Preconditions:** Migrated vendor KYC with two new nullable text columns; private classified documents
- **Data:** Multiline bank/beneficiary addresses; bank address longer than255 characters on native MySQL
1. Submit addresses without altering live vendor
2. Display comparison and classified document labels
3. Approve and inspect live address fields
4. Edit addresses from office and reset KYC
5. Run responsive browser country-switch validation and native persistence/locking

**Expected:** Both optional address fields persist without truncation, appear in public/office forms and record details, retain line breaks, and participate in KYC invalidation. Browser requirements switch with country; native schema supports text and typed evidence.

**Evidence:** `tests/Integration/IndianVendorKycTest.php#test_typed_uploads_are_private_and_addresses_are_staged_then_applied_on_approval`; `tests/Integration/IndianVendorKycTest.php#test_office_addresses_are_editable_and_invalidate_previously_approved_kyc`; `tests/Database/MySqlVendorKycTest.php#test_native_json_schema_locking_and_stale_review_protection`

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

### IT-PROJECT-004 — Normal planning save persists and recalculates a long readiness evaluation

**Priority:** P0 · **Requirement:** REQ-PROJECT · **Implementation:** linked executable evidence

- **Preconditions:** Fresh full schema; authenticated office user; project and shipment with paid commercial baseline on disposable SQLite or strict MySQL
- **Data:** Save a normal project planning field while packing, QC and document prerequisites produce a long evaluation
1. Post a normal planning change
2. Read project and latest gate evaluation

**Expected:** Save succeeds and the complete evaluation is retained; no database exception or truncation.

**Evidence:** `tests/Integration/FullSchemaReliabilityTest.php#test_full_schema_normal_project_planning_save_succeeds_and_evaluates_readiness`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_full_schema_normal_project_planning_save_succeeds_and_evaluates_readiness`

### IT-PROJECT-005 — Preserve three-decimal product snapshot from an invoice

**Priority:** P0 · **Requirement:** REQ-PROJECT · **Implementation:** linked executable evidence

- **Preconditions:** Project with editable product sourced from a PI
- **Data:** Unit price 0.574
1. Save PI against project
2. Reload the project product snapshot

**Expected:** The source rate and derived amount retain their thousandths.

**Evidence:** `tests/Integration/ThreeDecimalPrecisionTest.php#test_invoice_product_snapshot_preserves_the_three_decimal_rate`

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

### IT-WORKFLOW-006 — Fresh schema and message-widening migrations preserve data and refuse lossy rollback

**Priority:** P0 · **Requirement:** REQ-WORKFLOW · **Implementation:** partial evidence

- **Preconditions:** Disposable SQLite and strict MySQL; CREATE/DROP DATABASE privileges for isolated MySQL upgrade schemas
- **Data:** Existing short values, all widened fields, and values exceeding their old column limits
1. Run fresh SQLite migration chain and legacy-data upgrade
2. Apply both widenings on SQLite and MySQL
3. Attempt unsafe rollback and verify all values remain; shorten data and roll back

**Expected:** Existing values survive upgrade; rollback is blocked before narrowing if any value would be lost; safe short values can roll back.

**Note:** Full fresh MySQL migrations are a separate CI/local setup command. Rollback must be run with writers stopped; these checks do not prove atomicity under concurrent writes.

**Evidence:** `tests/Integration/FreshSchemaTest.php#test_all_migrations_apply_to_a_disposable_fresh_sqlite_database`; `tests/Integration/FreshSchemaTest.php#test_progress_migration_copies_matching_workflow_and_preserves_unmatched_project`; `tests/Integration/WideningMigrationSafetyTest.php#test_gate_summary_migration_preserves_existing_values_and_refuses_lossy_rollback`; `tests/Database/MySqlWideningMigrationSafetyTest.php#test_gate_summary_migration_preserves_existing_values_and_refuses_lossy_rollback`; `tests/Integration/WideningMigrationSafetyTest.php#test_message_widening_preserves_old_values_and_checks_every_column_before_rollback`; `tests/Database/MySqlWideningMigrationSafetyTest.php#test_message_widening_preserves_old_values_and_checks_every_column_before_rollback`

### IT-WORKFLOW-007 — A thousandth outstanding prevents ordinary financial closure

**Priority:** P0 · **Requirement:** REQ-WORKFLOW · **Implementation:** linked executable evidence

- **Preconditions:** Project with a linked PI and a client
- **Data:** PI 0.574 with 0.573 received
1. Take a financial closure snapshot
2. Inspect client summary and closure flags

**Expected:** The INR 0.001 receivable remains visible and financial closure is not incorrectly marked complete.

**Evidence:** `tests/Integration/ThreeDecimalPrecisionTest.php#test_financial_closure_detects_a_thousandth_balance_and_client_summary_preserves_it`

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

**Priority:** P0 · **Requirement:** REQ-PACK · **Implementation:** partial evidence

- **Preconditions:** F-PRODUCTS; packing groups and carton sizes; sender MissPack or third party
- **Data:** 100x140 and 100x150 thermal sizes; A4 orientation; custom columns
1. Save print settings
2. Render each print mode

**Expected:** Both thermal options available; selected orientation/columns/header/footer/page numbers respected.

**Note:** Method links cover selected label, print-setting and column cases, not every configuration. Thermal typography and actual PDF checks are separately documented in thermal-label-typography.md.

**Evidence:** `tests/Feature/PackagingAllLabelsTest.php#test_all_formats_render_each_physical_carton_with_correct_data_and_audit_counts`; `tests/Feature/PackagingPrintSettingsTest.php#test_saving_only_changes_print_settings_and_returns_to_the_print_page`; `tests/Feature/PackagingPrintColumnsViewTest.php#test_print_headers_cells_spans_and_totals_follow_the_saved_selection`

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

Sources: `app/Http/Controllers/ShipmentController.php`, `app/Services/ShipmentDispatchAuthorization.php`, `app/Services/ShipmentDocuments.php`, `app/Services/PublicShareLinks.php`.

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

**Priority:** P0 · **Requirement:** REQ-SHIP · **Implementation:** partial evidence

- **Preconditions:** F-PRODUCTS and F-MONEY; partial shipments S1/S2; fake public upload disk
- **Data:** Confirmed list missing or failed QC or amount due
1. Attempt dispatch for each failed prerequisite

**Expected:** No dispatch timestamp/stock event; actionable missing-gate details returned.

**Note:** Both real HTTP dispatch paths are covered with multiple simultaneous blockers and a long summary, on SQLite and strict MySQL. Individual prerequisite permutations and concurrent dispatch are not established by these links.

**Evidence:** `tests/Integration/FullSchemaReliabilityTest.php#test_full_schema_dispatch_rejects_with_a_complete_long_evaluation_on_both_http_paths`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_full_schema_dispatch_rejects_with_a_complete_long_evaluation_on_both_http_paths`

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

### IT-SHIP-006 — Shared delivery receipt hides audit metadata without altering stored integrity

**Priority:** P1 · **Requirement:** REQ-SHIP · **Implementation:** linked executable evidence

- **Preconditions:** Disposable SQLite receipt fixture; operations reader; content hash and optional evidence already stored
- **Data:** Own-company and vendor senders; with and without supporting POD file
1. GET authenticated delivery receipt print route
2. Inspect shared HTML for receiver details and conditional letterhead
3. Reload receipt, lines and shipment from database

**Expected:** Receiver-facing print has no hashes, internal recorder or evidence filename; quantities, receipt and shipment attributes remain unchanged; optional evidence remains optional.

**Evidence:** `tests/Integration/DeliveryReceiptPrintTest.php#test_authenticated_print_is_receiver_ready_without_mutating_stored_hashes_or_evidence`; `tests/Integration/DeliveryReceiptPrintTest.php#test_optional_evidence_and_vendor_shipper_remain_printable_without_our_logo`; `tests/Feature/DeliveryReceiptPrintViewTest.php#test_shared_print_has_no_internal_integrity_or_evidence_metadata`; `tests/Feature/DeliveryReceiptPrintViewTest.php#test_own_shipper_has_print_logo_and_origin_address_above_document_title`; `tests/Feature/DeliveryReceiptPrintViewTest.php#test_third_party_or_empty_shipper_does_not_get_our_letterhead_even_when_receiver_is_misspack`

### IT-SHIP-007 — Delivery receipt provides safe receiver sign and company stamp areas

**Priority:** P1 · **Requirement:** REQ-SHIP · **Implementation:** linked executable evidence

- **Preconditions:** Real Blade view with in-memory accepted and discrepant receipt lines; optional Chromium and PDF inspection
- **Data:** Accepted100 or accepted98/damaged2; supplied HTML in party names, addresses, recipient, notes and reasons; 60-line browser fixture
1. Render normal and discrepant receipt views
2. Check receiver acknowledgement, signature, designation, date/time and stamp fields
3. Run optional Chromium PDF check for A4 clipping, logo, long-table pagination and intact signing block

**Expected:** Escaped supplied text; discrepancy-aware acknowledgement; usable signing and stamp spaces; repeated long-table headers, no lost rows or horizontal clipping.

**Note:** PHP methods cover rendering and escaping; the actual optional Chromium/PyMuPDF run is recorded in delivery-receipt-print.md, not inferred from these method links.

**Evidence:** `tests/Feature/DeliveryReceiptPrintViewTest.php#test_receiver_can_sign_date_and_stamp_without_accepting_undisclosed_damage`; `tests/Feature/DeliveryReceiptPrintViewTest.php#test_shared_print_escapes_recipient_notes_and_reasons`; `tests/Feature/DeliveryReceiptPrintViewTest.php#test_party_names_and_addresses_remain_plain_text`

### IT-SHIP-008 — Delivery receipt print retains authentication permission and shipment ownership boundaries

**Priority:** P1 · **Requirement:** REQ-SHIP · **Implementation:** linked executable evidence

- **Preconditions:** Disposable SQLite receipt and two shipments; guest and office user without operations access
- **Data:** Foreign receipt/shipment pair; unauthenticated request; finance-only reader
1. Request receipt under another shipment
2. Request print without login
3. Request print with no operations permission

**Expected:** 404 for mismatched shipment; login redirect for guest; 403 for unauthorised office user; no public access endpoint introduced.

**Evidence:** `tests/Integration/DeliveryReceiptPrintTest.php#test_receipt_cannot_be_printed_under_another_shipment`; `tests/Integration/DeliveryReceiptPrintTest.php#test_print_retains_login_and_operations_permission_boundaries`

### IT-SHIP-009 — Reject malformed lines and storage-incompatible shipment numbers without data loss

**Priority:** P0 · **Requirement:** REQ-SHIP · **Implementation:** linked executable evidence

- **Preconditions:** Fresh full schema; authenticated operations user; existing shipment and line on disposable SQLite or strict MySQL
- **Data:** Null/malformed/non-array and incomplete rows; omitted and explicitly cleared rows; overflowing and excess-precision decimals/integers; valid boundary values; blank INR template row under a USD header
1. Submit malformed or out-of-range edits
2. Check errors and re-read shipment and items
3. Submit boundary values and a blank template row
4. Omit lines and then explicitly clear them; evaluate dispatch with omitted lines

**Expected:** Invalid edits produce field errors and preserve existing rows; valid limits and a blank template row save; omitted lines are preserved and evaluated from storage, while explicit clearing removes them.

**Evidence:** `tests/Integration/FullSchemaReliabilityTest.php#test_malformed_or_incomplete_shipment_lines_cannot_silently_delete_existing_items`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_malformed_or_incomplete_shipment_lines_cannot_silently_delete_existing_items`; `tests/Integration/FullSchemaReliabilityTest.php#test_out_of_range_shipment_values_are_rejected_without_mutating_the_shipment_or_items`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_out_of_range_shipment_values_are_rejected_without_mutating_the_shipment_or_items`; `tests/Integration/FullSchemaReliabilityTest.php#test_values_with_excess_precision_are_not_silently_rounded`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_values_with_excess_precision_are_not_silently_rounded`; `tests/Integration/FullSchemaReliabilityTest.php#test_database_boundary_values_and_an_empty_template_row_are_accepted`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_database_boundary_values_and_an_empty_template_row_are_accepted`; `tests/Integration/FullSchemaReliabilityTest.php#test_missing_shipment_lines_are_preserved_but_explicitly_cleared_lines_are_removed`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_missing_shipment_lines_are_preserved_but_explicitly_cleared_lines_are_removed`; `tests/Integration/FullSchemaReliabilityTest.php#test_dispatch_with_omitted_lines_evaluates_the_stored_lines`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_dispatch_with_omitted_lines_evaluates_the_stored_lines`

### IT-SHIP-010 — Short tracking link is consistent across copy, notifications and shipping marks

**Priority:** P1 · **Requirement:** REQ-SHIP; REQ-UI · **Implementation:** linked executable evidence

- **Preconditions:** Full disposable schema, guest tracking page and visible client notification
- **Data:** A live shipment with a 48-character original token
1. Read office tracking copy controls, shipment sticker and public share controls
2. Open the short URL as a guest
3. Notify the client of a status change and rotate the token

**Expected:** All recipients receive the same short link; it redirects to the existing tracking page, and the old short alias stops working on token rotation.

**Note:** Chromium additionally copies the URL on desktop/mobile and decodes the actual rendered QR. See public-short-links.md.

**Evidence:** `tests/Integration/PublicShortLinksTest.php#test_shipment_share_fields_sticker_qr_and_tracking_link_use_the_same_short_url`; `tests/Integration/FullSchemaReliabilityTest.php#test_long_shipment_title_and_link_are_saved_to_both_notification_channels`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_long_shipment_title_and_link_are_saved_to_both_notification_channels`

## Proformas, cash invoices and tax invoices (`SALES`)

Sources: `app/Services/CashInvoiceConversion.php`, `app/Services/CashInvoiceDeletion.php`, `app/Http/Controllers/SalesInvoiceController.php`, `app/Services/SalesInvoiceAdjustment.php`, `app/Models/SalesInvoiceNote.php`, `app/Http/Controllers/SalesInvoiceNoteController.php`, `tests/Feature/InvoiceAdjustmentTest.php`.

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

### IT-SALES-013 — Invoice can be emailed to the client and the send is provable

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Office admin; draft tax invoice with a client snapshot e-mail
- **Data:** To accounts+ops, cc director; mark-sent ticked
1. Open the compose dialog from the record page
2. Send

**Expected:** Mail carries the posted recipients/subject/message, the invoice numbers and the portal link; a sales_invoice_sends row snapshots to/cc/subject/message/sender; the draft flips to sent+portal-visible; the record page shows the send.

**Evidence:** `tests/Feature/SalesInvoiceEmailTest.php#test_the_invoice_goes_out_and_the_send_lands_in_the_log`

### IT-SALES-014 — A re-send of an issued invoice leaves the books alone

**Priority:** P1 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Issued invoice, portal flag off
- **Data:** mark_sent omitted
1. Send with the box unticked

**Expected:** Status/sent_at/portal flag untouched; send logged; the mail omits the View-invoice button because the link would 404.

**Evidence:** `tests/Feature/SalesInvoiceEmailTest.php#test_a_resend_of_an_issued_invoice_leaves_the_books_alone`

### IT-SALES-015 — The composer refuses bad input and off-limits invoices

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Cancelled invoice; malformed To address
- **Data:** to=not-an-email and to=valid on a cancelled invoice
1. Attempt both sends

**Expected:** Validation error / refusal flash; nothing mailed; zero send rows.

**Evidence:** `tests/Feature/SalesInvoiceEmailTest.php#test_the_composer_refuses_an_address_that_is_none`; `tests/Feature/SalesInvoiceEmailTest.php#test_a_cancelled_invoice_is_never_emailed`

### IT-SALES-016 — A mailer refusal is logged and briefs the accounts desk

**Priority:** P1 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Throwing transport; briefings enabled with e-mails off in fixture
- **Data:** SMTP connection refused
1. Attempt a send

**Expected:** Flash error; send row status=failed with the transport's reason; invoice stays draft; office alert invoice.email_failed (ack-required, accounts desk) raised.

**Evidence:** `tests/Feature/SalesInvoiceEmailTest.php#test_a_refused_send_is_logged_and_briefs_the_desk`

### IT-SALES-017 — Without a configured sender the office is told before any attempt

**Priority:** P1 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** mail.from.address empty
- **Data:** Valid compose post
1. Attempt a send

**Expected:** Flash points at Organisation settings; nothing mailed; no send row — a configuration gap is not a send attempt.

**Evidence:** `tests/Feature/SalesInvoiceEmailTest.php#test_without_a_configured_sender_the_office_is_told_first`

### IT-SALES-018 — Guests and payroll cannot email invoices

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Guest session; employee session
- **Data:** POST to the email endpoint
1. Send unauthenticated
2. Send as an employee

**Expected:** Guest → login; employee → their own dashboard; zero sends.

**Evidence:** `tests/Feature/SalesInvoiceEmailTest.php#test_guests_and_employees_are_held_at_the_wall`

### IT-SALES-022 — An issued tax invoice refuses money and identity changes

**Priority:** P1 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Tax invoice MP/INV/26-27/001, status sent, one line 10 PCS at 100, total 1180
- **Data:** PUT the full edit payload with items.quantity 20; then PUT with a changed client name
1. PUT /sales-invoices/{id} with the whole document unchanged except items quantity 10 to 20
2. Observe; then repeat with client_company_name changed only

**Expected:** Both saves are refused with an invoice-level error that names the changed figures and tells the user to issue a credit or debit note; the stored row is untouched.

**Evidence:** `tests/Feature/InvoiceAdjustmentTest.php#test_a_sent_tax_invoice_refuses_money_and_identity_changes`

### IT-SALES-023 — Terms and office notes stay editable on an issued invoice

**Priority:** P1 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Same issued invoice
- **Data:** PUT with internal_notes and dispatch_terms changed, money untouched
1. PUT /sales-invoices/{id} changing internal_notes and dispatch_terms only

**Expected:** The save succeeds; totals and identity are byte-identical.

**Evidence:** `tests/Feature/InvoiceAdjustmentTest.php#test_an_issued_invoice_still_edits_its_working_fields`

### IT-SALES-024 — An issued tax invoice cannot be deleted; a draft can

**Priority:** P1 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Issued invoice plus a draft twin
- **Data:** DELETE the issued invoice; DELETE the draft
1. Send DELETE to the issued invoice
2. Send DELETE to the draft

**Expected:** The issued invoice refuses with a guiding error and survives; the draft deletes cleanly.

**Evidence:** `tests/Feature/InvoiceAdjustmentTest.php#test_an_issued_tax_invoice_cannot_be_deleted_but_a_draft_can`

### IT-SALES-025 — A credit note lowers what is owed and caps at the document's worth

**Priority:** P1 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Issued invoice for 1180 (1000 + 90 CGST + 90 SGST)
- **Data:** Issue credit note of 500 (+tax); then another of 500; then attempt 100 more
1. POST /sales-invoices/{id}/notes kind=credit subtotal=500 with a reason
2. Read payableTotal, balanceDue, stored balance_amount
3. Issue a second 500; then attempt a third of 100

**Expected:** The note is numbered MP/CN/<FY>/001 with tax pro-rated to the invoice split (590); payable and the stored balance drop to 590, then to 0 with paymentState paid; the third attempt is refused because it exceeds what the invoice is still worth.

**Evidence:** `tests/Feature/InvoiceAdjustmentTest.php#test_a_credit_note_lowers_what_is_owed_and_stops_when_full`

### IT-SALES-026 — A debit note raises what is owed

**Priority:** P1 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Same issued invoice
- **Data:** Issue debit note of 200 (+tax)
1. POST /sales-invoices/{id}/notes kind=debit subtotal=200 with a reason

**Expected:** The MP/DN note totals 236 and payable/balance rise from 1180 to 1416.

**Evidence:** `tests/Feature/InvoiceAdjustmentTest.php#test_a_debit_note_raises_what_is_owed`

### IT-SALES-027 — A return posts its restock and credit in one flow, capped by what is outstanding per line

**Priority:** P1 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Issued invoice, one line of 10 PCS at 100
- **Data:** Return 4 PCS with restock; then try returning 7 more
1. POST /sales-invoices/{id}/returns with lines[item_id,qty 4], restock ticked, reason given
2. Attempt a second return of 7

**Expected:** A return flag = restock-carrying credit note of 472 (4*100 + tax) is created; the second attempt over the remaining 6 is refused and no extra note exists.

**Evidence:** `tests/Feature/InvoiceAdjustmentTest.php#test_a_return_restock_posts_its_credit_and_caps_quantities`

### IT-SALES-028 — Cancelling a note needs a reason and restores the money

**Priority:** P1 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Invoice with a live credit note of 590
- **Data:** Cancel without a reason; then with one; then again
1. PATCH /sales-invoice-notes/{id}/cancel with empty cancel_reason
2. Then with a reason
3. Then again

**Expected:** The empty cancel is refused; with a reason the note flips to cancelled, keeps the reason, payable returns to 1180, and the note cannot be cancelled twice.

**Evidence:** `tests/Feature/InvoiceAdjustmentTest.php#test_a_cancelled_note_stops_applying_and_keeps_its_reason`

### IT-SALES-029 — Corrections refuse drafts, proformas and superseded documents

**Priority:** P1 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** A draft invoice and a proforma
- **Data:** POST a credit note against each
1. POST notes to the draft
2. POST notes to the proforma

**Expected:** Both are refused 422; no note rows exist afterwards.

**Evidence:** `tests/Feature/InvoiceAdjustmentTest.php#test_corrections_refuse_drafts_proformas_and_superseded_documents`

### IT-SALES-030 — Notes change what is owed but never what was received

**Priority:** P1 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Issued invoice with 500 recorded as received
- **Data:** Issue a credit note of 500 (+tax = 590) afterwards
1. Force amount_paid 500 to emulate a receipt
2. Issue the credit note
3. Read receivedAmount, payableTotal, balanceDue, paymentState

**Expected:** Received stays 500; payable drops to 590; balance due is 90; the state reads partial — the receipt is never eaten by the correction.

**Evidence:** `tests/Feature/InvoiceAdjustmentTest.php#test_notes_change_what_the_listing_counts_but_never_the_receipts`

### IT-SALES-032 — New eligible INR invoice totals round half-up after tax and charges

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Full migrated schema; authorised office user; new INR proforma or tax invoice
- **Data:** Final totals 100.49, 100.50, 100.99; fractional quantities; discounts, GST and charges
1. Create eligible invoices through HTTP
2. Inspect signed round-off, total, balance and words
3. Inspect detail, print and e-invoice payload

**Expected:** The final total rounds to the nearest whole rupee with half-up boundaries; signed adjustment, balance, documents and payload reconcile.

**Evidence:** `tests/Integration/InvoiceAutoRoundingTest.php#test_new_inr_proformas_and_tax_invoices_round_half_up_and_store_the_signed_difference`; `tests/Integration/InvoiceAutoRoundingTest.php#test_fractional_quantity_uses_the_documents_precision_before_whole_rupee_rounding`; `tests/Integration/InvoiceAutoRoundingTest.php#test_rounding_is_after_discounts_and_charges_and_is_visible_on_print_and_detail`; `tests/Integration/InvoiceAutoRoundingTest.php#test_auto_rounding_is_applied_after_gst_invoice_discount_and_charges`; `tests/Integration/InvoiceAutoRoundingTest.php#test_auto_rounding_is_included_in_the_e_invoice_payload`; `tests/Database/MySqlInvoiceAutoRoundingTest.php#test_auto_manual_and_ineligible_amounts_are_stored_at_mysql_decimal_precision`

### IT-SALES-033 — Staff can override automatic rounding without changing issued money

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Eligible draft and issued tax invoice; authorised office user
- **Data:** Manual signed adjustment; tampered auto preview; draft edit; policy and adjustment change on sent tax invoice
1. Choose manual mode and save an adjustment
2. Enable auto mode and submit a forged adjustment
3. Edit the draft and then try changing the issued invoice

**Expected:** Manual values persist; the server recomputes auto values; issued monetary fields and rounding mode are locked while working fields remain editable.

**Evidence:** `tests/Integration/InvoiceAutoRoundingTest.php#test_explicit_manual_override_is_preserved_and_auto_mode_does_not_trust_submitted_round_off`; `tests/Integration/InvoiceAutoRoundingTest.php#test_draft_edits_recompute_auto_mode_and_keep_manual_mode_when_it_is_selected`; `tests/Integration/InvoiceAutoRoundingTest.php#test_issued_tax_invoice_rejects_switching_rounding_policy_or_changing_the_adjustment`

### IT-SALES-034 — Legacy, foreign-currency and cash invoices are not automatically re-rounded

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Existing manual invoice and new USD, RMB and cash documents; migrated schema
- **Data:** Legacy fractional total; USD and RMB at 100.49; auto INR draft switched to foreign or cash without an adjustment
1. Migrate and read existing invoice values
2. Edit a legacy invoice
3. Create foreign/cash invoices and switch an auto draft to an ineligible type

**Expected:** Existing money is unchanged, ineligible invoices do not auto-round, and a prior computed INR adjustment does not leak into an ineligible document. Rollback refuses to discard active policies.

**Evidence:** `tests/Integration/InvoiceAutoRoundingMigrationTest.php#test_existing_invoice_money_survives_migration_and_active_policy_cannot_be_dropped`; `tests/Database/MySqlWideningMigrationSafetyTest.php#test_existing_invoice_money_survives_migration_and_active_policy_cannot_be_dropped`; `tests/Integration/InvoiceAutoRoundingTest.php#test_existing_legacy_invoice_is_not_automatically_changed_on_edit`; `tests/Integration/InvoiceAutoRoundingTest.php#test_foreign_currency_and_cash_invoices_are_not_auto_rounded`; `tests/Integration/InvoiceAutoRoundingTest.php#test_switching_an_auto_draft_to_foreign_or_cash_without_a_round_off_does_not_leak_the_old_adjustment`; `tests/Database/MySqlInvoiceAutoRoundingTest.php#test_auto_manual_and_ineligible_amounts_are_stored_at_mysql_decimal_precision`

### IT-SALES-035 — Conversions preserve exact claims and duplicate drafts round afresh

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Existing proforma with a fractional outstanding claim or partial cash conversion
- **Data:** Full transfer of opening receipts; partial cash conversion leaving a fractional tax remainder; duplicate with manual round-off
1. Convert proforma in full and in part
2. Compare source and child totals and transferred receipts
3. Duplicate an eligible invoice

**Expected:** Tax and cash continuations preserve the exact outstanding claim including paise and never re-round; an eligible duplicate is a new automatically rounded draft and leaves its source untouched.

**Evidence:** `tests/Integration/InvoiceAutoRoundingTest.php#test_proforma_conversion_preserves_exact_fractional_remainder_and_cash_claim`; `tests/Integration/InvoiceAutoRoundingTest.php#test_a_rounded_proforma_keeps_the_exact_fractional_remainder_after_a_partial_cash_conversion`; `tests/Integration/InvoiceAutoRoundingTest.php#test_partial_conversion_preserves_fractional_receipts_and_the_outstanding_balance`; `tests/Integration/InvoiceAutoRoundingTest.php#test_full_conversion_keeps_fractional_total_and_transferred_opening_receipt`; `tests/Integration/InvoiceAutoRoundingTest.php#test_duplicate_is_a_new_draft_with_auto_rounding_but_does_not_change_the_source`; `tests/Database/MySqlInvoiceAutoRoundingTest.php#test_partial_conversion_keeps_the_fractional_tax_remainder_on_mysql`

### IT-SALES-031 — Invoice mail audit remains honest before, during and after delivery attempt

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Fresh full schema; authenticated sales manager; disposable SQLite or strict MySQL; fake mail or an in-process failing transport
- **Data:** Normalised To/CC longer than raw input; initial audit failure; post-send update failure; 70,000-character transport error
1. Submit each email attempt
2. Inspect transport calls, HTTP result, invoice state, alert and stored send record
3. Read the unconfirmed attempt in the invoice view

**Expected:** The initial audit failure prevents sending; one successful request creates one complete audit record; uncertain outcomes are never reported as confirmed success and advise checking delivery; long failures do not overflow the alert.

**Note:** One mail invocation per request is asserted; this is not an idempotency guarantee across repeated requests, nor a real external-provider delivery test.

**Evidence:** `tests/Integration/FullSchemaReliabilityTest.php#test_invoice_email_with_long_normalised_to_and_cc_is_sent_once_and_fully_recorded`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_invoice_email_with_long_normalised_to_and_cc_is_sent_once_and_fully_recorded`; `tests/Integration/FullSchemaReliabilityTest.php#test_long_mail_transport_error_cannot_overflow_alert_or_response`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_long_mail_transport_error_cannot_overflow_alert_or_response`; `tests/Integration/FullSchemaReliabilityTest.php#test_invoice_email_is_not_sent_when_its_initial_audit_write_fails`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_invoice_email_is_not_sent_when_its_initial_audit_write_fails`; `tests/Integration/FullSchemaReliabilityTest.php#test_invoice_email_result_write_failure_leaves_an_unconfirmed_attempt_not_a_false_success`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_invoice_email_result_write_failure_leaves_an_unconfirmed_attempt_not_a_false_success`

### IT-SALES-036 — Three-decimal prices with document-specific monetary precision

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Full migrated schema and authorised office user
- **Data:** Rate 0.574, quantities 1 and 5000; PI and tax invoice
1. Save both invoice types through HTTP
2. Inspect unit rate, totals, print, and tax JSON

**Expected:** PI keeps 0.574; tax money remains in paise; 5000 × 0.574 is 2870; no rate truncation.

**Evidence:** `tests/Integration/ThreeDecimalPrecisionTest.php#test_proforma_keeps_three_decimal_unit_price_total_print_and_words`; `tests/Integration/ThreeDecimalPrecisionTest.php#test_tax_invoice_keeps_the_rate_but_calculates_monetary_amounts_at_two_decimals`; `tests/Integration/ThreeDecimalPrecisionTest.php#test_quantity_5000_times_0574_is_2870_and_the_tax_payload_keeps_the_rate`; `tests/Database/MySqlThreeDecimalPrecisionTest.php#test_sql_columns_and_http_writers_preserve_three_place_values`

### IT-SALES-037 — Conversions and reversals conserve fractional claims and receipts

**Priority:** P0 · **Requirement:** REQ-SALES · **Implementation:** linked executable evidence

- **Preconditions:** Full migrated schema; PI with a fractional rate and receipts
- **Data:** Cash conversion, exact tax conversion, unrepresentable remainders and a one-thousandth rate edit
1. Convert and reverse a cash invoice
2. Convert a paise-representable PI
3. Attempt unsafe conversions and an issued rate edit

**Expected:** Exact claims and receipts survive; a tax conversion that cannot represent the remaining claim or line is refused atomically; issued money is locked.

**Evidence:** `tests/Integration/ThreeDecimalPrecisionTest.php#test_cash_conversion_and_reversal_preserve_fractional_receipts_and_claims`; `tests/Integration/ThreeDecimalPrecisionTest.php#test_exact_claim_conversion_recalculates_tax_components_and_keeps_fractional_receipts`; `tests/Integration/ThreeDecimalPrecisionTest.php#test_unrepresentable_proforma_to_tax_conversion_is_blocked_without_changing_the_claim`; `tests/Integration/ThreeDecimalPrecisionTest.php#test_partial_conversion_refuses_an_unrepresentable_tax_line_without_changing_the_claim`; `tests/Integration/ThreeDecimalPrecisionTest.php#test_issued_tax_invoice_rejects_a_one_thousandth_rate_change`; `tests/Feature/CashInvoiceConversionTest.php#test_unrepresentable_fx_allocation_split_rolls_back_without_zero_value_slices`; `tests/Database/MySqlThreeDecimalPrecisionTest.php#test_unsafe_partial_tax_conversion_is_atomic_on_native_mysql`

### IT-SALES-038 — Fractional cash conversion of a whole-paise PI preserves its exact remainder

**Priority:** P0 · **Requirement:** REQ-SALES-PI · **Implementation:** linked executable evidence

- **Preconditions:** A PI has an exact 1.00 claim and a recorded 0.001 advance
- **Data:** Convert 0.001 to cash and transfer the 0.001 advance
1. Create the PI
2. Create the cash conversion
3. Compare the consumed claim, cash invoice, transferred receipt and remaining PI
4. Attempt tax conversion of the fractional remainder

**Expected:** Cash and consumed claim are 0.001, PI remains 0.999, receipt transfers unchanged, and two-decimal tax conversion is blocked atomically.

**Evidence:** `tests/Integration/ThreeDecimalPrecisionTest.php#test_fractional_cash_conversion_of_a_paise_pi_preserves_the_exact_claim`; `tests/Database/MySqlThreeDecimalPrecisionTest.php#test_fractional_cash_conversion_of_a_paise_claim_keeps_the_exact_remainder_on_mysql`

## Purchase documents and vendor settlement (`PURCHASE`)

Sources: `app/Services/PurchaseBillLedger.php`, `app/Services/PurchaseInvoiceMoney.php`, `app/Http/Controllers/PurchaseInvoiceController.php`, `app/Services/PurchasePartialConversion.php`, `app/Models/PurchasePaymentSplit.php`.

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

### IT-PURCHASE-004 — Three-decimal purchase totals, posting and carried advances

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** linked executable evidence

- **Preconditions:** Full migrated schema; authorised office user and vendor
- **Data:** PO and bill at 0.574; PO advance 0.001
1. Save PO and bill
2. Post to vendor ledger
3. Convert an approved PO to a bill

**Expected:** Three-decimal totals, vendor posting and carried advance remain exact.

**Evidence:** `tests/Integration/ThreeDecimalPrecisionTest.php#test_purchase_order_and_bill_store_three_decimal_unit_price_and_total`; `tests/Integration/ThreeDecimalPrecisionTest.php#test_purchase_order_conversion_and_vendor_posting_retain_fractional_values`; `tests/Integration/ThreeDecimalPrecisionTest.php#test_vendor_payment_mirror_keeps_three_decimal_bank_and_ledger_amounts`; `tests/Database/MySqlThreeDecimalPrecisionTest.php#test_sql_columns_and_http_writers_preserve_three_place_values`

### IT-PURCHASE-005 — Partial purchase example preserves independent advance provenance

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** linked executable evidence

- **Preconditions:** Full disposable migrated schema; authorised office user, approved PO and separate advances
- **Data:** INR, RMB and USD; PO 10000, advances 2000 and 1000; bill 2500
1. Create advances and choose 2000 cash plus 500 bank
2. Create cash purchase bill and compare PO, bill, vendor and bank

**Expected:** PO 7500, paid 500, open 7000; bill paid 2500; one unchanged movement per payment.

**Evidence:** `tests/Integration/PurchasePartialConversionsTest.php#test_user_example_preserves_payment_provenance_in_all_three_currencies`

### IT-PURCHASE-006 — Multiple partial purchase bills apportion original GST

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** linked executable evidence

- **Preconditions:** Full disposable migrated schema; authorised office user, approved PO and separate advances
- **Data:** GST-inclusive PO and multiple cash and purchase bills
1. Convert gross slices
2. Compare item rates, tax components and remaining claim

**Expected:** Cash carries no GST; multiple bills and remainder reconcile to original gross.

**Evidence:** `tests/Integration/PurchasePartialConversionsTest.php#test_multiple_partial_bills_and_cash_reduce_the_gross_and_apportion_gst`; `tests/Integration/PurchasePartialConversionsTest.php#test_price_rounding_is_visible_while_unrepresentable_fx_splits_are_atomic`

### IT-PURCHASE-007 — Manual source selection preserves FX and opening advances

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** linked executable evidence

- **Preconditions:** Full disposable migrated schema; authorised office user, approved PO and separate advances
- **Data:** Opening advance; individual vendor payment slices; foreign-currency cashflow allocation
1. Select source amounts including a partial split
2. Repeat on the remaining source; reverse and try an impossible FX split

**Expected:** Only selected amounts move; source/native/document currencies, balances and bank entries are conserved.

**Evidence:** `tests/Integration/PurchasePartialConversionsTest.php#test_opening_and_fx_allocation_split_and_reverse_without_changing_the_bank_entry`; `tests/Integration/PurchasePartialConversionsTest.php#test_repeated_manual_splits_and_full_transferred_payment_are_protected`; `tests/Integration/PurchasePartialConversionsTest.php#test_foreign_or_excess_payment_selections_fail_atomically_and_unselected_advance_stays`

### IT-PURCHASE-008 — Linked purchase reversal and reporting preserve accounting history

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** linked executable evidence

- **Preconditions:** Full disposable migrated schema; authorised office user, approved PO and separate advances
- **Data:** Linked bills, cashflow and vendor ledger, project, downstream packaging
1. Try out-of-band mutations and reverse in last-in-first-out order
2. Review project, vendor provenance and document number reuse

**Expected:** Unsafe changes are blocked; reversal restores payments and PO without bank movement; numbers remain reserved.

**Evidence:** `tests/Integration/PurchasePartialConversionsTest.php#test_latest_conversion_reversal_restores_claim_payments_and_bank_without_duplication`; `tests/Integration/PurchasePartialConversionsTest.php#test_reversal_refuses_downstream_packaging_and_manual_credits`; `tests/Integration/PurchasePartialConversionsTest.php#test_split_payment_is_not_unallocated_and_closure_retains_its_provenance`; `tests/Integration/PurchasePartialConversionsTest.php#test_reversed_bill_number_is_reserved_against_automatic_and_manual_reuse`; `tests/Integration/PurchasePartialConversionsTest.php#test_linked_documents_and_split_payment_cannot_be_mutated_or_deleted_out_of_band`; `tests/Integration/PurchasePartialConversionsTest.php#test_reversal_refuses_vendor_collaboration_and_share_links`

### IT-PURCHASE-009 — Purchase conversion permissions, stale state and native concurrency

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** linked executable evidence

- **Preconditions:** Full disposable migrated schema; authorised office user, approved PO and separate advances
- **Data:** Guest, purchase reader, stale PO model and two native MySQL connections
1. Attempt unauthorized and stale mutations
2. Run overlapping conversions and guarded migration rollback

**Expected:** Unauthorized or stale actions cannot mutate claims; overlapping conversions cannot consume the same remainder.

**Evidence:** `tests/Integration/PurchasePartialConversionsTest.php#test_purchase_reader_and_guest_cannot_create_or_change_conversions`; `tests/Integration/PurchasePartialConversionsTest.php#test_stale_controller_models_cannot_cancel_or_delete_a_newly_split_order`; `tests/Integration/PurchasePartialConversionsTest.php#test_stale_order_cannot_record_a_payment_after_full_conversion`; `tests/Database/MySqlPurchasePartialConversionTest.php#test_native_money_slices_and_lossy_rollback_guard`; `tests/Database/MySqlPurchasePartialConversionTest.php#test_two_overlapping_conversions_serialize_and_cannot_consume_the_same_remainder`; `tests/Database/MySqlPurchasePartialConversionTest.php#test_cross_order_bill_number_collision_retries_without_losing_either_conversion`

### IT-PURCHASE-010 — Scaled purchase rates use decimal rounding with an explicit residual

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** linked executable evidence

- **Preconditions:** Approved itemised PO; cash and purchase bill conversions in INR or RMB
- **Data:** RMB 21,000 → 9,000 cash and 12,000 bill; positive and negative rounding; half-up boundary; GST, discounts and freight
1. Create cash and purchase bills and compare full quantities and scaled prices
2. Check line arithmetic, GST, exact totals, visible round-off, payments and reversal
3. Attempt inconsistent sources, negative taxable values and unrepresentable FX allocations

**Expected:** RMB bills retain 10,000 units at rates 0.9 and 1.2. Three-decimal price residuals, even beyond 0.005, are visible; exact selected totals and payments are conserved. Invalid sources and FX slices fail atomically.

**Evidence:** `tests/Integration/PurchasePartialConversionsTest.php#test_rmb_remainder_bill_scales_rate_and_preserves_payments`; `tests/Integration/PurchasePartialConversionsTest.php#test_rate_rounding_uses_decimal_half_up_and_preserves_amount`; `tests/Integration/PurchasePartialConversionsTest.php#test_scaled_prices_recalculate_gst_and_reconcile_bill_total`; `tests/Integration/PurchasePartialConversionsTest.php#test_scaled_prices_preserve_discounts_gst_and_freight`; `tests/Integration/PurchasePartialConversionsTest.php#test_rate_rounding_allows_cancelling_lines_and_tiny_slices_with_exact_totals`; `tests/Integration/PurchasePartialConversionsTest.php#test_rounding_cannot_make_line_or_invoice_taxable_amount_negative`; `tests/Database/MySqlPurchasePartialConversionTest.php#test_native_rmb_remainder_saves_full_quantity_and_scaled_rate`; `tests/Database/MySqlPurchasePartialConversionTest.php#test_native_scaled_price_rounding_keeps_three_decimal_amounts_on_both_bills`

### IT-PURCHASE-011 — Purchase cash and tax bills retain full PO quantities with proportionally scaled prices

**Priority:** P0 · **Requirement:** REQ-PURCHASE · **Implementation:** linked executable evidence

- **Preconditions:** Approved itemised PO; new linked cash and purchase bill conversions
- **Data:** Three 1,000-quantity items at INR 1/2/3, INR 3,000 bill; GST/discount/charges; positive and negative price rounding above 0.005; stale or invalid sources
1. Convert selected cash and purchase portions, explicitly select payment sources, and inspect bill detail/print
2. Check arithmetic, PO and payment conservation, reversal, invalid-source atomicity and packaging prefill

**Expected:** Both bills retain all original items and quantities, scale prices to the selected amount, show any unavoidable price-rounding adjustment, retain truthful GST on purchase bills, absorb GST/discounts/charges into cash prices, and never treat repeated financial quantities as physical packing quantities.

**Evidence:** `tests/Integration/PurchasePartialConversionsTest.php#test_scaled_prices_keep_all_original_quantities_on_cash_and_purchase_bills`; `tests/Integration/PurchasePartialConversionsTest.php#test_cash_prices_include_gst_discounts_and_charges_without_separate_components`; `tests/Integration/PurchasePartialConversionsTest.php#test_visible_price_rounding_preserves_exact_totals_and_never_fabricates_a_price`; `tests/Integration/PurchasePartialConversionsTest.php#test_inconsistent_purchase_source_is_not_hidden_as_round_off`; `tests/Integration/PurchasePartialConversionsTest.php#test_converted_bill_packing_requires_actual_physical_quantities`; `tests/Integration/PurchasePartialConversionsTest.php#test_intra_state_percentage_discount_and_original_manual_round_off_are_preserved`; `tests/Integration/PurchasePartialConversionsTest.php#test_existing_legacy_split_is_not_rewritten_when_a_new_bill_is_created`; `tests/Integration/PurchasePartialConversionsTest.php#test_packing_verification_does_not_claim_linked_financial_quantities_are_physical`; `tests/Integration/PurchasePartialConversionsTest.php#test_manual_packing_quantities_are_saved_without_inventing_a_physical_bill_balance`; `tests/Database/MySqlPurchasePartialConversionTest.php#test_native_scaled_price_rounding_keeps_three_decimal_amounts_on_both_bills`; `tests/Integration/PurchasePartialConversionsTest.php#test_purchase_bill_recalculates_mixed_gst_rates_after_invoice_discount`; `tests/Integration/PurchasePartialConversionsTest.php#test_packaging_from_a_real_shipment_is_not_misclassified_by_an_associated_financial_bill`; `tests/Integration/PurchasePartialConversionsTest.php#test_valid_legacy_float_halfway_percentages_are_accepted_without_accepting_arbitrary_errors`; `tests/Integration/PurchasePartialConversionsTest.php#test_valid_legacy_float_halfway_item_gross_is_accepted`

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

### IT-CASH-007 — Three-decimal entry, balance, allocation and account edit

**Priority:** P0 · **Requirement:** REQ-CASH · **Implementation:** linked executable evidence

- **Preconditions:** Full migrated schema; active bank account and invoice
- **Data:** Credit 0.574, debit 0.426, allocation and over-allocation by 0.001
1. Save credit/debit and allocate
2. Edit account opening balance
3. Attempt over-allocation or a fourth decimal

**Expected:** Balances and linked document amounts retain thousandths; invalid or excessive entries are rejected without changes.

**Evidence:** `tests/Integration/ThreeDecimalPrecisionTest.php#test_cashflow_entries_and_running_balances_keep_three_decimal_places`; `tests/Integration/ThreeDecimalPrecisionTest.php#test_editing_an_account_recalculates_running_balances_without_losing_thousandths`; `tests/Integration/ThreeDecimalPrecisionTest.php#test_three_decimal_cashflow_allocation_settles_the_exact_document_balance`; `tests/Integration/ThreeDecimalPrecisionTest.php#test_over_allocating_by_one_thousandth_is_rejected_atomically`; `tests/Integration/ThreeDecimalPrecisionTest.php#test_prices_and_cashflow_amounts_over_three_decimal_places_are_refused`; `tests/Database/MySqlThreeDecimalPrecisionTest.php#test_sql_columns_and_http_writers_preserve_three_place_values`; `tests/Integration/ThreeDecimalPrecisionTest.php#test_statement_and_report_csv_preserve_the_third_decimal`

### IT-CASH-008 — Decimal schema widening preserves values and guards lossy rollback

**Priority:** P0 · **Requirement:** REQ-CASH · **Implementation:** linked executable evidence

- **Preconditions:** Disposable strict MySQL test schema; stop writers before DDL
- **Data:** Existing cent values and one nonzero third-decimal value
1. Migrate up
2. Attempt rollback with fractional data
3. Clear fractional data, rollback and reapply

**Expected:** Legacy data and defaults survive; the complete preflight refuses rollback before any table changes when a thousandth would be lost.

**Note:** DDL tests use a unique disposable database because MySQL commits schema changes independently.

**Evidence:** `tests/Database/MySqlWideningMigrationSafetyTest.php#test_precision_migration_preserves_old_decimals_and_preflights_every_table_before_rollback`

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

Sources: `app/Services/PartyStatement.php`, `app/Http/Controllers/PartyStatementController.php`, `app/Services/PublicShareLinks.php`.

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

### IT-STATEMENT-004 — All-time and currency quick filters survive rendered navigation and search

**Priority:** P1 · **Requirement:** REQ-STATEMENT · **Implementation:** linked executable evidence

- **Preconditions:** Disposable financial migrations; finance-only office reader; historical client/vendor activity in USD/RMB/GBP and current INR invoice
- **Data:** USD200 invoice in2025; RMB70 vendor bill in2024; GBP30 receipt in2023; INR100 current month
1. Follow the actual rendered All time href
2. Select currency and party quick filters, then search
3. Open list with no currency filter and inspect per-currency rows and totals
4. Reject malformed/unsupported currency without changing single-statement default

**Expected:** All time retains period=all and includes historical movement for both party types. Default all currencies; explicit currency narrows only that dimension. Other query context survives. Cashflow-only currency codes are selectable. Currency totals stay separate; cross-currency monetary totals are null, not a fabricated sum.

**Note:** Real browser drawer/date input coverage and native MySQL reporting evidence are separate; see statement-list-filters.md.

**Evidence:** `tests/Integration/StatementListFiltersTest.php#test_rendered_all_time_link_keeps_the_explicit_preset_and_fetches_both_party_types`; `tests/Integration/StatementListFiltersTest.php#test_default_currency_lists_all_accounts_and_totals_are_not_mixed`; `tests/Integration/StatementListFiltersTest.php#test_currency_quick_filters_preserve_all_time_party_type_and_search`; `tests/Integration/StatementListFiltersTest.php#test_bad_currency_is_validated_without_changing_single_statement_currency_defaults`

### IT-STATEMENT-005 — Statement date boundaries and vendor debit/credit labels remain ledger-correct

**Priority:** P1 · **Requirement:** REQ-STATEMENT · **Implementation:** linked executable evidence

- **Preconditions:** Disposable statements fixture; frozen25Oct2026; date-cast Eloquent records
- **Data:** INR100 on10Oct, INR25 on31Oct, INR900 on1Nov; vendor bill RMB70
1. Select October/INR and inspect totals and last activity
2. Select vendors and inspect debit/credit columns

**Expected:** October includes31Oct, excludes1Nov and reports debit125 with displayable31Oct2026 last activity. Vendor payments are debit/Paid and bills credit/Billed; combined parties have neutral debit/credit headers.

**Evidence:** `tests/Integration/StatementListFiltersTest.php#test_calendar_month_includes_last_day_and_exposes_a_displayable_last_activity_date`; `tests/Integration/StatementListFiltersTest.php#test_vendor_debit_credit_labels_match_the_vendor_ledger_not_the_client_ledger`

### IT-STATEMENT-006 — New-tab print preview and download retain selected period, currency and ageing

**Priority:** P1 · **Requirement:** REQ-STATEMENT · **Implementation:** linked executable evidence

- **Preconditions:** Disposable financial migrations; finance reader and denied office/employee readers; frozen 25 October 2026
- **Data:** Historical INR290280 invoice and89584 receipt, current INR100 invoice; USD200 and vendor RMB70; stale month dates with period=all
1. Follow actual Print href into the protected preview
2. Inspect Download URL and Filters
3. Change exact dates and retain selected currency/ageing off
4. Render compact issuer/party header and reject unauthorised readers
5. Retain current-month defaults and pre-period opening balance

**Expected:** One Print action opens a new tab. Preview and download show selected all-time/custom ledger, not an accidental current month. No currency mixing or balance reset. Issuer logo and address left; party/address/dates right; no issuer PAN. Controls print-hidden and amounts complete. Print masthead title/filters align with logo above equal-sized, top-aligned issuer/party names. Header chips absent; currency on amounts and summary direction retained. Balance heading right aligned.

**Note:** Real Chromium and PyMuPDF geometry/text/page checks are separate executed gates; see statement-print-preview.md. Download uses browser Save as PDF when optional Dompdf is not installed.

**Evidence:** `tests/Integration/StatementPrintPreviewTest.php#test_statement_page_print_link_opens_new_tab_and_preserves_all_time_currency_and_ageing_off`; `tests/Integration/StatementPrintPreviewTest.php#test_preview_exposes_download_and_filters_with_same_all_time_scope`; `tests/Integration/StatementPrintPreviewTest.php#test_print_custom_dates_do_not_revert_to_current_month`; `tests/Integration/StatementPrintPreviewTest.php#test_compact_paper_header_has_logo_party_details_and_no_pan`; `tests/Integration/StatementPrintPreviewTest.php#test_monthly_defaults_remain_unchanged_and_an_explicit_foreign_currency_is_retained`; `tests/Integration/StatementPrintPreviewTest.php#test_vendor_preview_is_a_single_currency_payable_and_finance_boundary_is_retained`; `tests/Integration/StatementPrintPreviewTest.php#test_print_masthead_separates_title_filters_from_aligned_party_names_and_removes_only_header_badges`

### IT-STATEMENT-007 — Shared client statements work with empty receipts and do not double count overlapping receipts

**Priority:** P0 · **Requirement:** REQ-STATEMENT; REQ-CPORTAL · **Implementation:** linked executable evidence

- **Preconditions:** Full disposable schema and guest statement link; both no-project and published-project cases
- **Data:** Empty project receipts; one INR100 invoice and one INR40 receipt linked to both project and invoice
1. Open the short statement as a guest with no published project receipts
2. Open a statement where the same booked receipt appears through both sources
3. Revoke or expire the shared link and inspect view counts

**Expected:** Empty statements open instead of failing; the receipt appears once with INR60 closing balance; expiry/revocation and view counts remain authoritative at the original handler.

**Note:** The empty-receipts failure predated short links; both SQLite and strict MySQL exercise its fix.

**Evidence:** `tests/Integration/PublicShortLinksTest.php#test_canonical_handlers_still_enforce_share_expiry_revocation_permissions_and_view_counts`; `tests/Integration/PublicShortLinksTest.php#test_client_statement_with_an_overlapping_project_receipt_counts_it_only_once`; `tests/Database/MySqlPublicShortLinksTest.php#test_guest_links_use_current_tokens_and_the_original_handlers_on_mysql`

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

### IT-BRIEF-004 — Notification centre lists open briefings with their actions

**Priority:** P1 · **Requirement:** REQ-BRIEF · **Implementation:** linked executable evidence

- **Preconditions:** Office admin; open critical ops briefing and attention sales briefing
- **Data:** Two raised alerts
1. Open /office-alerts/centre

**Expected:** 200 with both items, critical sorted first, tray/desk/severity chips present.

**Evidence:** `tests/Feature/OfficeAlertCentreTest.php#test_the_centre_is_locked_to_guests_and_the_payroll`; `tests/Feature/OfficeAlertCentreTest.php#test_the_centre_lists_open_briefings_with_their_actions`

### IT-BRIEF-005 — Centre filters narrow by desk and severity without mutating state

**Priority:** P1 · **Requirement:** REQ-BRIEF · **Implementation:** linked executable evidence

- **Preconditions:** Alerts on two desks and severities
- **Data:** OPS-CASE critical, SALES-CASE info
1. Filter team=sales
2. then severity=critical
3. submit nonsense state value

**Expected:** Only matching desks/severities show; invalid values 422; reader state untouched; chips preserve sibling filters.

**Evidence:** `tests/Feature/OfficeAlertCentreTest.php#test_filters_narrow_the_tray_and_keep_the_other_filters`

### IT-BRIEF-006 — Acknowledging from the centre files a resolvable trail

**Priority:** P1 · **Requirement:** REQ-BRIEF · **Implementation:** linked executable evidence

- **Preconditions:** Open shared-ack briefing
- **Data:** KYC-TEAM-LEAD alert
1. PATCH ack from a plain form (no XHR)
2. re-open open tray
3. open resolved tray

**Expected:** Redirect with flash (not JSON); acked_at/by stamped; open tray empties; resolved tray names who acted.

**Evidence:** `tests/Feature/OfficeAlertCentreTest.php#test_ack_from_the_centre_redirects_and_files_the_resolved_trail`

### IT-BRIEF-007 — Snoozing from the centre moves the card to the snoozed tray

**Priority:** P2 · **Requirement:** REQ-BRIEF · **Implementation:** linked executable evidence

- **Preconditions:** Open briefing
- **Data:** until=1h form post
1. Snooze 1h from the centre
2. view open tray
3. view snoozed tray

**Expected:** State row stamped; open tray hides it; snoozed tray shows it with wake time.

**Evidence:** `tests/Feature/OfficeAlertCentreTest.php#test_snooze_moves_the_card_out_of_open_and_back`

### IT-BRIEF-008 — Centre respects the desk watcher scope

**Priority:** P1 · **Requirement:** REQ-BRIEF · **Implementation:** linked executable evidence

- **Preconditions:** Sales-desk watcher; ops, sales and office-wide alerts open
- **Data:** Three alerts across desks
1. Open centre
2. filter to operations desk

**Expected:** Only sales + office-wide visible; filtering to another desk returns the empty state, not the data.

**Evidence:** `tests/Feature/OfficeAlertCentreTest.php#test_a_desk_watcher_sees_only_their_desks_briefings`

### IT-BRIEF-009 — Composed notification titles and links retain their complete text

**Priority:** P0 · **Requirement:** REQ-BRIEF · **Implementation:** linked executable evidence

- **Preconditions:** Fresh full schema; authenticated office user; real services and HTTP shipment change on disposable SQLite or strict MySQL
- **Data:** Long shipment name and carrier, portal token/link, project and workflow-step names
1. Create a changed shipment and inspect office and client messages
2. Register a project exception and read it back

**Expected:** Composed titles and action URLs preserve their complete values without a MySQL length failure.

**Evidence:** `tests/Integration/FullSchemaReliabilityTest.php#test_long_shipment_title_and_link_are_saved_to_both_notification_channels`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_long_shipment_title_and_link_are_saved_to_both_notification_channels`; `tests/Integration/FullSchemaReliabilityTest.php#test_project_exception_keeps_the_full_composed_project_and_step_title`; `tests/Database/MySqlFullSchemaReliabilityTest.php#test_project_exception_keeps_the_full_composed_project_and_step_title`

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

**Priority:** P0 · **Requirement:** REQ-VPORTAL · **Implementation:** partial evidence

- **Preconditions:** VP1 for V1 and VP2 for V2; valid,revoked,expired share tokens
- **Data:** Previously valid link open; office revokes
1. GET link after revocation

**Expected:** No cached authenticated content returned.

**Note:** Exercises short redirect plus the original expired/revoked share response and view count; not all portal cache layers.

**Evidence:** `tests/Integration/PublicShortLinksTest.php#test_canonical_handlers_still_enforce_share_expiry_revocation_permissions_and_view_counts`

### IT-VPORTAL-003 — Portal/share administration requires sensitive portal grant

**Priority:** P0 · **Requirement:** REQ-VPORTAL · **Implementation:** planned

- **Preconditions:** VP1 for V1 and VP2 for V2; valid,revoked,expired share tokens
- **Data:** purchase.manage without portals.manage
1. Create/reset/revoke portal or share link

**Expected:** Denied; no new token or changed credentials.

## Overview and module dashboard reporting (`DASH`)

Sources: `app/Services/DashboardPeriod.php`, `app/Services/ModuleDashboard.php`, `app/Http/Controllers/AdminDashboardController.php`, `app/Services/DashboardAnalytics.php`.

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

### IT-DASH-006 — Module-only dashboard landing and canonical analytical workspaces

**Priority:** P1 · **Requirement:** REQ-DASH · **Implementation:** linked executable evidence

- **Preconditions:** Disposable SQLite; fixed25Oct2026; six stable module routes
- **Data:** Custom10..12Oct; rolling7days; allowed sales versus forbidden finance
1. Visit retired /dashboard compatibility entry and permission-filtered directory
2. Follow canonical module links with dates and currency
3. Render six module workspaces without executive UI

**Expected:** Legacy entry redirects to directory with allowed reporting context; no executive page/navigation, duplicate summary projection or unnecessary generic dashboard privilege. All detailed metrics, trends, scoped tables and attention remain in their module.

**Evidence:** `tests/Integration/DashboardConsolidationTest.php#test_legacy_dashboard_redirects_to_module_directory_with_custom_dates`; `tests/Integration/DashboardConsolidationTest.php#test_retired_overview_drops_unsupported_rolling_day_count`; `tests/Integration/DashboardConsolidationTest.php#test_each_existing_module_has_a_single_detailed_empty_state_page`; `tests/Integration/DashboardConsolidationTest.php#test_module_retains_secondary_activity_trends_in_the_same_workspace`; `tests/Integration/DashboardConsolidationTest.php#test_retired_executive_dashboard_redirects_module_readers_to_directory_with_context`; `tests/Integration/DashboardConsolidationTest.php#test_module_workspaces_and_directory_have_no_executive_dashboard_navigation`

### IT-DASH-007 — Detailed module analytics reconcile dates currency units and canonical balances

**Priority:** P1 · **Requirement:** REQ-DASH · **Implementation:** linked executable evidence

- **Preconditions:** Disposable schema fixtures plus actual financial migrations; real direct receipts and cashflow allocations
- **Data:** INR and USD; draft/cancelled/proforma/out-of-window documents; multiple product lines; pcs/kg receipts; on-time/late tasks; live project portfolio
1. Compare selected-currency KPI, trend and party concentration
2. Read ageing from canonical payment/allocation-aware balances
3. Check unit-separated receipts, completion timeliness, client portfolio and HTML escaping

**Expected:** No cross-currency or cross-unit sums, invoice-header multiplication, fabricated profit or dropped final-day entries. Period flows and live positions are explicit; read-only balances reconcile to existing models.

**Note:** Additional native MySQL method test_native_module_concentration_and_trend_keep_last_day_and_currency_consistent and optional browser check exercise the real engine and layout separately; consult dashboard-consolidation.md.

**Evidence:** `tests/Integration/DashboardConsolidationTest.php#test_sales_concentration_uses_exact_dates_currency_and_real_issued_documents`; `tests/Integration/DashboardConsolidationTest.php#test_finance_category_analysis_excludes_internal_transfers_and_other_currencies`; `tests/Integration/DashboardConsolidationTest.php#test_receipt_quantities_stay_separate_by_unit_and_respect_received_date`; `tests/Integration/DashboardConsolidationTest.php#test_completion_timeliness_uses_completion_window_and_end_of_due_day`; `tests/Integration/DashboardConsolidationTest.php#test_project_dashboard_retains_live_client_value_analysis_without_mixing_currencies`; `tests/Integration/DashboardFinancialAnalyticsTest.php#test_receivable_ageing_matches_payment_and_allocation_aware_metric_without_writes`; `tests/Integration/DashboardFinancialAnalyticsTest.php#test_selected_currency_agrees_between_kpi_trend_and_concentration`; `tests/Integration/DashboardFinancialAnalyticsTest.php#test_product_line_totals_are_not_multiplied_by_invoice_header_join`; `tests/Integration/DashboardFinancialAnalyticsTest.php#test_purchase_payable_and_finance_movement_are_currency_specific`; `tests/Integration/DashboardFinancialAnalyticsTest.php#test_dashboard_prints_supplied_party_text_safely_and_preserves_currency_in_links`

### IT-DASH-008 — Consolidated analytics retain permission validation and empty-state boundaries

**Priority:** P1 · **Requirement:** REQ-DASH · **Implementation:** linked executable evidence

- **Preconditions:** Office users with selected domain grants; empty in-memory tables
- **Data:** Sales-only reader without dashboard.view; unknown module; invalid currency
1. Read permission-filtered directory and compatibility redirect
2. Request forbidden finance domain
3. Submit unsupported currency and unknown module; open directory with no module grants

**Expected:** Directory/compatibility entry permit office module readers without exposing forbidden domain data; forbidden domain403, unknown404; invalid currency validation error; empty permission set has an explanatory state without module links.

**Evidence:** `tests/Integration/DashboardConsolidationTest.php#test_directory_and_module_navigation_hide_unauthorised_modules`; `tests/Integration/DashboardConsolidationTest.php#test_unsupported_currency_returns_validation_error_not_mislabelled_money`; `tests/Integration/DashboardConsolidationTest.php#test_module_reader_can_use_directory_and_legacy_entry_without_dashboard_permission`; `tests/Integration/DashboardConsolidationTest.php#test_directory_explains_when_no_module_is_authorised_without_exposing_links`

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

### IT-AUDIT-004 — Audit register uses shared pagination and accessible readonly evidence modal

**Priority:** P1 · **Requirement:** REQ-AUDIT; REQ-UI; REQ-AUTH · **Implementation:** linked executable evidence

- **Preconditions:** Disposable AnalyticsDatabaseFixture, authorised audit reader,35 filtered records, current-page evidence templates; real Chromium
- **Data:** Filtered30+5 records, long JSON, hostile HTML text, empty results;1440/900/390px light/dark
1. Render actual audit HTTP view and shared pagination
2. Navigate page2 with all filters retained
3. Open distinct rows in one modal outside table
4. Close with control, footer, Escape or backdrop; trap/restore focus
5. Check mobile filters, searched Select2, long and hostile content
6. Deny non-audit readers and retain immutable log count

**Expected:** Shared ERP table/cards, pager, drawer and modal; current-page scoped escaped evidence; no clipping, horizontal overflow, stale data or log mutations. Filters preserved and accessible on phones; permission boundary unchanged.

**Evidence:** `tests/Integration/AuditRegisterPresentationTest.php#test_shared_pagination_search_and_single_readonly_evidence_modal_replace_inline_popovers`; `tests/Integration/AuditRegisterPresentationTest.php#test_pagination_keeps_scope_and_only_current_page_evidence_is_rendered`; `tests/Integration/AuditRegisterPresentationTest.php#test_evidence_is_escaped_and_empty_results_keep_the_shared_readonly_shell`; `tests/Integration/AuditRegisterPresentationTest.php#test_non_audit_office_users_cannot_read_the_register_or_evidence`

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

### IT-FILES-004 — Public media fallback cannot expose private files or execute active content

**Priority:** P0 · **Requirement:** REQ-FILES; REQ-AUTH; REQ-UI · **Implementation:** linked executable evidence

- **Preconditions:** Disposable database, isolated public/private fake disks; no public storage symlink
- **Data:** PNG, HTML, missing file, hidden file, traversal/control/backslash paths, escaping symlink and private KYC proof
1. GET saved public photo and inspect MIME/nosniff
2. Reject missing, hidden, traversal and symlink escape paths
3. Force active content to attachment with sandbox CSP
4. Confirm distinct private endpoint denies unsigned access but accepts a valid signature

**Expected:** Public images load without symlink; only real files inside public root are served; private disk requires signature and stays inaccessible via /storage.

**Evidence:** `tests/Integration/PublicMediaAvatarTest.php#test_public_images_load_without_a_symlink_and_private_or_traversal_paths_do_not`; `tests/Integration/PublicMediaAvatarTest.php#test_symlinks_cannot_escape_the_public_disk`; `tests/Integration/PublicMediaAvatarTest.php#test_private_storage_keeps_a_distinct_signed_only_endpoint`

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

**Priority:** P1 · **Requirement:** REQ-UI · **Implementation:** partial evidence

- **Preconditions:** Desktop1440,tablet768,mobile390 and320; light/dark; keyboard-only input
- **Data:** A4 portrait/landscape;100x140 and100x150 labels
1. Render each print page and inspect final print rules

**Expected:** Correct paper geometry; black document text; sender branding and configured columns respected.

**Note:** Method links cover carton-label Blade paper and branding only. Thermal PDF geometry, typography and overflow limitations are separately documented in thermal-label-typography.md.

**Evidence:** `tests/Feature/PackagingCartonLabelViewTest.php#test_thermal_label_uses_100_by_140_mm_stock`; `tests/Feature/PackagingCartonLabelViewTest.php#test_100_by_150_is_an_additional_thermal_format_without_changing_140`; `tests/Feature/PackagingCartonLabelViewTest.php#test_misspack_sender_is_the_heading_and_has_our_print_logo_in_every_format`; `tests/Feature/PackagingCartonLabelViewTest.php#test_vendor_sender_has_its_own_heading_and_never_our_logo`

### IT-UI-004 — Compact topbar preserves private utilities and reporting context

**Priority:** P1 · **Requirement:** REQ-UI · **Implementation:** linked executable evidence

- **Preconditions:** Office, custom-access, employee and guest identities; real module Blade
- **Data:** Custom10..12Oct2026 in USD; full or settings-only permissions
1. Render icon-only Quick Menu and reporting trigger
2. Locate Notes in Quick Menu and theme control only in profile menu
3. Read selected reporting dates/currency in page content
4. Retain authorised operational shortcuts and employee privacy

**Expected:** No duplicate standalone theme or Notes controls and no visible launcher/filter text. Accessible names/custom tooltips remain. Exact reporting selection appears in content; mobile filter remains reachable. Notes available privately to both roles without office privilege; guests see no launcher.

**Note:** Actual responsive browser verifies40px triggers, keyboard Quick Menu Notes/End/Escape, mobile drawer opening and profile theme reachability; source/HTTP results alone do not prove browser layout.

**Evidence:** `tests/Feature/TopbarQuickMenuTest.php#test_launcher_is_icon_only_but_has_an_accessible_name`; `tests/Feature/TopbarQuickMenuTest.php#test_private_notes_moves_into_quick_menu_for_both_roles_without_office_privilege`; `tests/Feature/TopbarQuickMenuTest.php#test_employee_receives_private_notes_only_even_with_full_profile`; `tests/Feature/TopbarQuickMenuTest.php#test_custom_access_only_shows_permitted_modules_including_manage_implies_view`; `tests/Integration/DashboardConsolidationTest.php#test_compact_topbar_keeps_reporting_context_in_content_and_profile_theme_in_menu`

### IT-UI-005 — Standalone statement filters use the shared searchable form/drawer design

**Priority:** P1 · **Requirement:** REQ-UI · **Implementation:** linked executable evidence

- **Preconditions:** Disposable statement fixture and authorised finance reader; actual Blade/Select2/assets; Chromium desktop/tablet/mobile
- **Data:** All-time/custom INR statement; USD switch; explicit ageing off; shared modal/drawer controls with30choices
1. Render preview and verify shared form/Select2 assets and grouped filter sections
2. Search with mouse and keyboard in ordinary, multiple, dynamic, modal and drawer controls
3. Choose All time to clear dates, then edit dates to display Custom
4. Close overlays and retain opener focus
5. Submit exact scope and verify actual A4 PDF unchanged

**Expected:** Consistent control borders/spacing, self-contained visible close icon, blue ageing switch and shared footer actions. Search always available, no duplicate initialization/change events, no stranded popup. Exact selected dates/currency/ageing retained.

**Note:** PHP link establishes rendered design dependencies. Actual interactive/visual outcomes separately executed in searchable-selects-browser.cjs at1440/900/390px and light/dark; A4 proof in statement-print-pdf.py. See searchable-selects.md.

**Evidence:** `tests/Integration/StatementPrintPreviewTest.php#test_preview_filters_use_shared_form_and_searchable_select_assets_without_external_close_icons`

### IT-UI-006 — Vendor KYC multiline fields share base focus and error field design

**Priority:** P1 · **Requirement:** REQ-UI; REQ-VENDOR · **Implementation:** linked executable evidence

- **Preconditions:** VendorKycFixture public form; actual HTTP view captures in Chromium
- **Data:** Address, Bank Address and Beneficiary Address; multiline values, required error;1440/900/390px
1. Render public KYC with both optional address fields
2. Check textarea shared base/focus/error selectors
3. Browse steps and compare actual typography, geometry, focus ring, error border, vertical resize and multiline values

**Expected:** Inputs and multiline fields share font/radius/padding policy and focus/error tokens; textarea has96px minimum height, auto height and vertical-only resize without mobile overflow.

**Evidence:** `tests/Integration/KycTextareaPresentationTest.php#test_public_multiline_fields_share_input_base_focus_and_error_styles`

### IT-UI-007 — Delivery-receipt modal is a full-screen sheet with card lines on phones

**Priority:** P1 · **Requirement:** REQ-UI-RESPONSIVE · **Implementation:** planned

- **Preconditions:** Dispatched shipment with line items; ≤767px viewport
- **Data:** One shipment, two lines
1. Open Record delivery receipt on a phone-width viewport

**Expected:** Modal fills the screen; each line is a labelled card (data-label), inputs full-width, footer respects the safe area; no horizontal page scroll.

**Note:** Pinned by tools/checks/responsive-check.cjs rule 6 (no layout floor past 320px inside mobile blocks) plus the full-sheet/card rules in shipments.css's mobile pass; a device pass confirms the modal fills the screen and lines stack.

### IT-UI-008 — No mobile media block pins a layout past 320px

**Priority:** P1 · **Requirement:** REQ-UI-RESPONSIVE · **Implementation:** planned

- **Preconditions:** Effective ≤767px rules across module sheets
- **Data:** Contract scan
1. Run responsive check

**Expected:** No min-width/width ≥321px on non-table selectors inside max-width media blocks; the trend-chart exception is named with its reason.

**Note:** Contract-only case: the check itself is the executable evidence (npm run test:contracts → responsive rule 6).

### IT-UI-009 — Unexpected production HTTP 500s have a safe correlated response

**Priority:** P0 · **Requirement:** REQ-UI · **Implementation:** linked executable evidence

- **Preconditions:** Production environment with debug mistakenly enabled; fake in-process routes and loggers
- **Data:** Thrown exception; explicit HTML/API 500; logging outage; validation/auth/403; local debug
1. Request each failure path
2. Compare status, response type, reference and logged context
3. Check known 4xx and local diagnostics

**Expected:** 500s stay 500, suppress details, guide safe retry and use the appropriate HTML/JSON contract; correlation is recorded when the logger works; 4xx and local debug remain intact.

**Note:** A logger outage cannot guarantee a corresponding log entry. Browser evidence is recorded separately, not inferred from PHPUnit.

**Evidence:** `tests/Feature/UnexpectedErrorHandlingTest.php#test_production_html_error_is_safe_even_with_debug_enabled_and_matches_the_reported_reference`; `tests/Feature/UnexpectedErrorHandlingTest.php#test_production_api_error_is_json_and_contains_no_debug_details`; `tests/Feature/UnexpectedErrorHandlingTest.php#test_explicit_500_response_exception_does_not_bypass_the_safe_fallback`; `tests/Feature/UnexpectedErrorHandlingTest.php#test_explicit_api_500_uses_json_even_if_the_original_response_was_html`; `tests/Feature/UnexpectedErrorHandlingTest.php#test_failure_of_the_fallback_log_transport_does_not_expose_the_response_or_cause_another_failure`; `tests/Feature/UnexpectedErrorHandlingTest.php#test_validation_authentication_and_other_client_errors_keep_their_status_and_contract`; `tests/Feature/UnexpectedErrorHandlingTest.php#test_local_debug_mode_retains_the_diagnostic_exception_output`

## GST: GSTIN checksum, HSN/SAC master and e-invoice (IRN/QR) (`TAX`)

Sources: `app/Support/Gstin.php`, `app/Rules/ValidGstin.php`, `app/Models/HsnSacCode.php`, `app/Models/SalesInvoiceEInvoice.php`, `app/Services/EInvoice/EInvoicePayload.php`, `app/Services/EInvoice/EInvoiceRegistration.php`, `app/Services/EInvoice/HttpGateway.php`, `app/Services/EInvoice/ManualGateway.php`, `app/Http/Controllers/TaxSettingController.php`, `app/Http/Controllers/SalesInvoiceEInvoiceController.php`.

### IT-TAX-001 — A proforma invoice cannot be e-registered

**Priority:** P0 · **Requirement:** REQ-TAX-EINV · **Implementation:** linked executable evidence

- **Preconditions:** Office user; invoice with invoice_type proforma.
- **Data:** Fully populated invoice body that would pass pre-flight if it were a tax invoice.
1. POST the generate route for the proforma
2. Verify the redirect carries a refusal flash and no e-invoice row exists

**Expected:** No record is even parked; the error names the invoice type as the reason.

**Evidence:** `tests/Feature/EInvoiceFlowTest.php#test_a_proforma_is_refused_before_any_record_exists`

### IT-TAX-002 — A tax invoice line without HSN/SAC is named in the refusal

**Priority:** P0 · **Requirement:** REQ-TAX-EINV · **Implementation:** linked executable evidence

- **Preconditions:** Office user; tax invoice whose single line has hsn_sac null.
- **Data:** One line of 1000 taxable at 18%.
1. POST generate
2. Read the refusal and confirm it names HSN and no record was created

**Expected:** The e-invoice law's 4-digit minimum is enforced at pre-flight, not at the portal.

**Evidence:** `tests/Feature/EInvoiceFlowTest.php#test_a_line_without_hsn_is_named_in_the_refusal`

### IT-TAX-003 — Generate parks the NIC-schema payload pending acknowledgement

**Priority:** P0 · **Requirement:** REQ-TAX-EINV · **Implementation:** linked executable evidence

- **Preconditions:** Office user; fully valid intra-state tax invoice with valid seller and buyer GSTINs.
- **Data:** 100 pcs cartons HSN 4819 at 18% GST.
1. POST generate
2. Verify record is pending, provider manual
3. Verify payload Version/TranDtls/DocDtls/SellerDtls/BuyerDtls/ItemList/ValDtls contents incl. dd/mm/yyyy date and HsnCd/GstRt

**Expected:** The stored JSON matches the portal schema for a B2B intra-state invoice.

**Evidence:** `tests/Feature/EInvoiceFlowTest.php#test_generate_builds_the_portal_schema_and_parks_it_pending`

### IT-TAX-004 — Manual acknowledgement accepts only a 64-hex IRN and records registration

**Priority:** P0 · **Requirement:** REQ-TAX-EINV · **Implementation:** linked executable evidence

- **Preconditions:** Pending e-invoice record for an office user.
- **Data:** Bogus IRN 'NOT-AN-IRN'; then sha256-derived valid IRN with ack number/date and signed QR.
1. POST record with bogus IRN and verify the 64-hex refusal
2. POST record with the valid set and verify registered status, ack fields, registrar and audit event

**Expected:** Only portal-real IRNs register; registration writes a sales_invoice.einvoice.registered audit row.

**Evidence:** `tests/Feature/EInvoiceFlowTest.php#test_the_acknowledgement_accepts_only_a_real_irn_and_marks_registration`

### IT-TAX-005 — Cancellation honours the portal's 24-hour window

**Priority:** P0 · **Requirement:** REQ-TAX-EINV · **Implementation:** linked executable evidence

- **Preconditions:** Registered record whose ack_date is 30 hours ago, then 2 hours ago.
- **Data:** Cancel reason code 2 (data entry mistake).
1. Attempt cancel past the window and verify refusal with the credit-note guidance
2. Move ack inside the window and cancel; verify status becomes cancelled

**Expected:** The window belongs to the portal, not the ERP's preference.

**Evidence:** `tests/Feature/EInvoiceFlowTest.php#test_the_cancel_window_is_the_portals_twenty_four_hours_not_ours`

### IT-TAX-006 — The HTTP gateway contract: bearer, schema Version in, artefacts out

**Priority:** P0 · **Requirement:** REQ-TAX-EINV · **Implementation:** linked executable evidence

- **Preconditions:** config services.einvoice.provider=http with faked endpoint answering Irn/AckNo/AckDt/SignedQRCode.
- **Data:** GSP-style JSON acknowledgement.
1. POST generate with Http::fake
2. Verify the record registers directly with provider http and stores the signed QR
3. Assert the request carried bearer auth and Version 1.1 with SupTyp B2B

**Expected:** A configured GSP registers without any UI acknowledgement step.

**Evidence:** `tests/Feature/EInvoiceFlowTest.php#test_the_http_gateway_registers_from_the_endpoints_answer`

### IT-TAX-007 — The rate master ships packaging defaults and the area lists them

**Priority:** P1 · **Requirement:** REQ-TAX-HSN · **Implementation:** linked executable evidence

- **Preconditions:** Migrated database incl. hsn_sac_codes; office user.
- **Data:** Seeded goods/services codes (3923 at 18% et al.).
1. Count seeded rows
2. GET /settings/taxes and verify area chrome and a seeded code render

**Expected:** A fresh install is usable immediately and the settings page is a directory area.

**Evidence:** `tests/Feature/TaxMasterTest.php#test_the_migration_seeds_packaging_defaults_and_the_area_lists_them`

### IT-TAX-008 — Add, edit and remove a code through the settings door

**Priority:** P1 · **Requirement:** REQ-TAX-HSN · **Implementation:** linked executable evidence

- **Preconditions:** Office user; migrated HSN table.
- **Data:** New code 39232100 then a rate edit and removal.
1. POST store
2. PUT update flipping rate and inactive
3. DELETE and verify the row is gone

**Expected:** The office maintains its own master; an unchecked active box means false, not unchanged.

**Evidence:** `tests/Feature/TaxMasterTest.php#test_add_edit_and_remove_a_code_through_the_settings_door`

### IT-TAX-009 — Master validation refuses letters-as-codes, duplicates and impossible rates

**Priority:** P1 · **Requirement:** REQ-TAX-HSN · **Implementation:** linked executable evidence

- **Preconditions:** Office user.
- **Data:** Code 39A3; duplicate of seeded 3923; rate 450.
1. Attempt each of the three invalid stores and verify the right field errors

**Expected:** Codes are 4-8 digits, unique; rates live inside 0-99.99.

**Evidence:** `tests/Feature/TaxMasterTest.php#test_the_rules_a_typo_breaks_against_all_fire`

### IT-TAX-010 — Guests cannot read or write the tax master

**Priority:** P0 · **Requirement:** REQ-TAX-HSN · **Implementation:** linked executable evidence

- **Preconditions:** No session.
- **Data:** Store attempt with a valid-looking payload.
1. GET the area and POST a store without auth
2. Verify redirects and an untouched table

**Expected:** The master sits behind the office door like every settings area.

**Evidence:** `tests/Feature/TaxMasterTest.php#test_guests_cannot_touch_the_master`

### IT-TAX-011 — A client GSTIN that fails its check digit is refused at the invoice door

**Priority:** P0 · **Requirement:** REQ-TAX-GSTIN · **Implementation:** linked executable evidence

- **Preconditions:** Office user; inline client creation from the invoice form.
- **Data:** 24ABCDE1234F1Z5 — structurally valid, checksum-wrong (correct digit is 6).
1. POST clients.quickStore with the defective GSTIN
2. Verify 422 on gstin and no client row

**Expected:** The checksum rule fires on the historical quickStore path, not only on the full client form.

**Evidence:** `tests/Feature/InvoiceClientCreationTest.php#test_a_gstin_that_fails_its_check_digit_is_refused`

### IT-TAX-012 — Reject inconsistent saved legal figures before e-registration

**Priority:** P0 · **Requirement:** REQ-TAX-EINV · **Implementation:** linked executable evidence

- **Preconditions:** Tax invoice and saved item values
- **Data:** Quantity 100, rate 666.667, allocated gross 66666.67
1. Try generating JSON with inconsistent gross
2. Try with mismatched header totals

**Expected:** The payload is refused with a repair message instead of silently exporting different legal figures.

**Evidence:** `tests/Integration/ThreeDecimalPrecisionTest.php#test_einvoice_refuses_inconsistent_saved_line_and_header_figures`

### IT-TAX-013 — Allocate an invoice-wide discount once in the legal payload

**Priority:** P0 · **Requirement:** REQ-TAX-EINV · **Implementation:** linked executable evidence

- **Preconditions:** Tax invoice with two GST-rated items and a pre-tax document-wide discount
- **Data:** INR 10 at 18%, INR 20 at 5%, invoice-wide INR 3 discount
1. Create and save the tax invoice
2. Build the portal JSON
3. Compare line discounts, taxable values, GST and header total

**Expected:** The allocated line discounts total INR 3, item taxable and tax values sum to the saved header, and the discount is not subtracted again at invoice level.

**Evidence:** `tests/Integration/ThreeDecimalPrecisionTest.php#test_einvoice_allocates_an_invoice_wide_discount_without_double_counting`; `tests/Database/MySqlThreeDecimalPrecisionTest.php#test_discounted_tax_payload_reconciles_saved_mysql_totals`

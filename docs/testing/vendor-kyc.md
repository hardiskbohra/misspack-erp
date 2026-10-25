## Review access change — 25 October 2026

User-requested policy revision (UT-VENDOR-005, IT-VENDOR-004/009): remove the
maker/creator separation restriction for every authorised purchase manager, not
only full-access Admin. Creator and current maker may approve, request revision
or reject their own vendor submissions. Purchase manage permission, employee /
guest / read-only boundaries, pending-state checks, version locks, reason
requirements and audit reviewer attribution remain unchanged. Earlier independent
review evidence below describes the original implementation, superseded here.

TDD: updated unit, real HTTP creator-approval and rendered creator-control tests
failed for the intended old restriction (3 failures). After removing only the
identity restriction, full Unit + Integration + Feature passes **517 tests /
15,656 assertions**; actual Chromium creator/other-manager controls and read-only
exclusion pass at 1440/390px, alongside existing wizard regressions. Contract,
catalogue and route checks pass. Native PHP/MySQL CI is checked after push.
Evidence: `.work/vendor-kyc-open-review-{red,full,contracts,browser}.log`.

# Vendor KYC — acceptance and test-first plan

REQ-VENDOR / REQ-UI / REQ-FILES / REQ-AUTH

- Same four-step public form, save-for-later, validation/error navigation, locked
  submission/review banners and office review styling as client KYC. Reuse shared
  KYC CSS/JS and ERP components; searchable Select2 where enhanced.
- Vendor types include packaging suppliers, manufacturers, traders, distributors,
  service providers, consultants, forwarders and other vendors. India/overseas
  tax and bank details supported; do not impose Indian IDs on foreign vendors.
- Separate `kyc_status`: draft → under_review → approved / revision / rejected.
  Operational Active/Inactive/On Hold/Blacklisted and existing procurement are
  unchanged; no automatic KYC block on historic vendors or payments.
- Submitted identity/contact/address/tax/bank data is staged, not live. Public
  allowlist excludes prices, ratings, internal notes, commercial terms, balances,
  operational status, users, permissions and all review/link metadata.
- Only purchase managers issue/rotate/revoke/mark-sent links and review. The
  maker/record creator may also review their own file. Read-only office access may
  inspect KYC but not mutate it. Reasons required for revision/rejection.
- Review only a current submitted version. Row locks and version checks reject
  stale drafts/reviews and duplicate submissions. Approval atomically applies
  staged details. Office profile changes invalidate an existing KYC review.
- Unpredictable independently revocable token; no guest enumeration or unrelated
  vendor data. CSRF, submission throttling, private no-store/noindex/referrer
  response policy. Sharing is manual (like client KYC), not a claim of email sent.
- Optional PDF/image evidence private, type/size/count restricted. Token/office
  scoped downloads; never expose existing vendor financial attachments or paths.
- Review/link/submission audit history is office-only. Public feedback includes
  only explicit revision/rejection reasons. No client KYC behaviour regression.
- Unit + real HTTP/database integration + native MySQL + real responsive browser
  gates before task completion; full suites/contracts before commit and push.

## Implemented workflow and operating notes

Open **Vendors → vendor → KYC → Create KYC link**. Copy/share it, optionally
mark it as sent, and preview the same four-step design as client KYC. Bank
values already on the office record are not disclosed when first issuing a
link. Drafts/submissions are staged; only an authorised purchase manager's
approval updates the live vendor. Commercial currency/terms, balances, ratings,
internal notes and operational status are never public-writable.

`purchase.view` permits inspection; `purchase.manage` permits link administration,
review and requesting updated KYC. Creator/current maker may also review. A
completed approved/rejected file can be reopened with a reason for another
submission/review cycle. Office identity/bank edits reset KYC to draft and
record the maker; ordinary commercial/operational edits do not reset it.

The public form saves to the server, not localStorage (especially bank data).
Uploads use private `local` storage and forced, scoped downloads: at most five
PDF/JPG/PNG/WebP files per request, 5 MB each, twenty per vendor. Evidence is
retained for review; replacing a document means uploading the corrected version,
not deleting audit evidence. Existing general/financial vendor attachments are
not exposed through the KYC link. No public endpoint enumerates vendors.

Deploy with the normal **`php artisan migrate --force`** migration step. The
additive migration backfills existing vendors as draft without creating links,
changing operational status or blocking existing procurement/payments. There is
no automatic email of the invitation. A submitted-KYC office briefing is raised
using the existing briefing settings; a briefing outage does not roll back KYC.

## TDD and verification evidence

- Initial Red: 11 tests, 2 missing-behaviour errors + 9 failures (new policy,
  categories and HTTP routes absent), `.work/vendor-kyc-red.log`.
- Subsequent genuine Reds: reopen route absent; browser bank data persisted;
  invalid phone advanced steps; hidden navigation buttons remained visible;
  validation responses lacked no-store; SQLite rollback needed explicit index
  removal; marking sent incorrectly staled an open form; model serialization
  exposed private staging; stale image edits deleted the old image; malformed
  phone arrays produced 500 instead of validation errors. Retained regressions
  pass after fixes. Final visual inspection also caught missing mobile comparison
  labels; a browser Red proved current/submitted values were indistinguishable,
  then shared `data-label` mobile captions fixed it. No expected business values were weakened to turn tests green.
- Full portable PHP 8.4.25 (WASM) Unit + Integration + Feature: **516 tests,
  15,649 assertions PASS**. This is real PHP/HTTP/SQLite evidence, not native
  MySQL/concurrency evidence. `.work/vendor-kyc-full.{log,xml}`.
- Actual Blade/HTTP fixtures and real Chromium at 1440/390px: four steps,
  field/error navigation, correct Back/Continue/Submit visibility, draft,
  optional files, cancel/confirm submission, zero persisted bank data, locked
  states, independent checker/maker/read-only UI, no page overflow/JS exceptions;
  approved client KYC regression also passes. `.work/vendor-kyc-browser.log` and
  `.work/vendor-kyc/*.png`. Native PHP endpoints are separately tested; the
  browser runner serves captured HTTP-rendered pages/assets and intercepts
  submission to verify intent. It does **not** claim live browser→PHP end-to-end.
- `npm run test:contracts`: catalogue, Node search regressions, dashboard and
  248-template Blade contracts PASS. 462 declared routes; **234 designs**
  (101 unit / 133 integration), **78 method-linked designs**, 54 PHP test files.
- Native MySQL regression `MySqlVendorKycTest` requires a disposable `*_test`
  database, committed fixture rows, and two real connections. It verifies JSON
  staging, InnoDB lock contention, one successful approval, stale replay rejection
  and independent operational/currency preservation. Executed by the existing
  CI MySQL job after push; CI status is reported with delivery, not inferred from
  SQLite. The sandbox itself has no native PHP/MySQL service.

Reproduce (native PHP):

```sh
vendor/bin/phpunit -c phpunit.xml
VENDOR_KYC_BROWSER_FIXTURES=1 vendor/bin/phpunit --filter VendorKycPresentationTest
node tools/checks/vendor-kyc-browser.cjs # playwright-core + Chromium executable
vendor/bin/phpunit -c phpunit.mysql.xml # disposable migrated MySQL *_test only
npm run test:contracts
php tools/testing/export-routes.php --check
```

Catalogue coverage: UT-VENDOR-004–007 and IT-VENDOR-004–010. Native concurrency,
portable HTTP and browser UI outcomes are distinct evidence, not interchangeably
claimed as complete system/line coverage.

## Indian vendor requirements — acceptance (25 October 2026)

Test-first change: Indian final submissions require a valid GSTIN, bank account
number and IFSC, plus separately classified GST Certificate and PAN uploads.
Drafts may be incomplete; overseas submissions remain exempt. Recognise India,
IN, IND, Bharat and भारत case-insensitively with surrounding whitespace ignored.
Existing generic documents must not silently count as tax certificates. Previously
saved classified documents belonging to this vendor can satisfy resubmission;
foreign-vendor evidence or missing storage files cannot. Check pending legacy
Indian submissions at approval too; request revision rather than approve an
incomplete file. Do not retroactively change approved or operational statuses.
Bank Address and Beneficiary Address are optional multiline fields in public
KYC, submitted comparisons, approved vendor data, and office forms/details.
Keep document privacy, size/count limits, version/locking, review permissions,
server draft saving, and full/browser/native regression gates unchanged.

### Indian requirements — verification

- Genuine initial Red: seven targeted tests exposed absent required identifiers /
  classified evidence, absent staging fields and approval checks. One additional
  fixture-only failure (stale in-memory version) was corrected without changing
  the expected rejection. No Indian requirement was relaxed to turn tests green.
- Green full portable Unit + Integration + Feature: **527 tests / 15,765
  assertions PASS** on PHP8.4.25 WASM. Focused vendor suite:34 tests/355 assertions.
  `IndianVendorKycTest` covers nine real HTTP/database cases; country policy has
  an isolated unit regression. Legacy broad vendor fixtures now explicitly use
  an overseas vendor, while Indian fixtures exercise all new mandatory rules.
- Actual Chromium at1440/390px: India switches on all five requirements (GSTIN,
  account, IFSC, GST Certificate and PAN), missing fields prevent confirmation,
  overseas switching removes those requirements, both address inputs work, and
  existing draft/confirm/locked/creator-review/client regression checks PASS.
- PDF/image privacy, combined five-file-per-save and20-file-total limits remain.
  Saved evidence can be reused, but generic files, other-vendor files and missing
  private storage files cannot satisfy the mandatory certificate requirements.
- Native MySQL test now submits typed Indian proofs, checks category persistence,
  approves a multiline beneficiary address and a bank address longer than255
  characters, and repeats real two-connection locking/replay assertions. It runs
  in the existing disposable MySQL CI job; CI is verified after push, not inferred
  from SQLite. No production data or external delivery was used.
- Deploy using `php artisan migrate --force`. The new additive migration adds
  two nullable text fields and classifies pre-existing documents as Supporting
  document; it does not guess their tax category, change approved vendors, or
  alter procurement/payment status. Already-pending incomplete Indian submissions
  must be sent back for revision before they can be approved.
- Catalogue: UT-VENDOR-008 / IT-VENDOR-011–012;237 designs (102 unit/135
  integration),81 exact-method linked cases,55 PHP test files,462 declared routes.
  Node/Blade/contracts PASS; source declarations are not coverage percentages.
  Evidence: `.work/indian-vendor-{red,focused,full,browser,contracts}.log` and
  `.work/indian-vendor-full.xml`.

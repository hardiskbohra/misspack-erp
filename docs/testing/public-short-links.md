# Short public sharing links

## Acceptance criteria and boundaries

Relevant designs: **IT-CLIENT-008**, **IT-SHIP-010**, **IT-STATEMENT-007**,
**IT-VENDOR-007**, **IT-VPORTAL-002**, **IT-SALES-013**, and **UT-AUDIT-001**.
Some of these are broader cases; the work here covers only their sharing boundaries.

- On the existing domain, provide materially shorter links for client KYC,
  vendor KYC, shipment tracking and the other existing token-based public share
  surfaces: products, sales/purchase documents, public lead records, party
  statements, vendor secure shares and feedback. Use the same short link in
  copy buttons, previews, messages, emails, QR/stickers and recipient-facing
  share controls, where those surfaces exist.
- Make the general enquiry link and the client/vendor portal login addresses
  shorter when presented for sharing. Login is still required for portal data;
  private files, authenticated record URLs and arbitrary external addresses are
  not made public or turned into general-purpose redirects.
- Keep all previously issued full-length links, POST/form actions, attachment
  permissions, public visibility, expiry, revocation, ownership, view counts,
  audit attribution, rate limits and KYC privacy controls working. A short link
  redirects to the existing destination, which continues to enforce its rules;
  the browser address bar therefore shows the original URL after opening it.
- No third-party shortener, existing-token replacement or production data
  mutation. A compact, stateless 128-bit authenticated signature binds the
  resource type, its identifier and its current long token. The identifier is
  not a secret or an authorization check. Changing/removing the long token
  invalidates its short link; tampered, unknown, cross-type, deleted or missing
  records fail closed. Only an explicit destination allowlist can redirect, and
  redirect responses are temporary, private/no-store and no-referrer.
- The signature uses the application's key and recognizes Laravel's configured
  previous keys. Previously shared short links require the former key to remain
  in `APP_PREVIOUS_KEYS` during key rotation; legacy long URLs do not depend on
  the new short-link signature. No migration or alias database is required.
- Verify the actual guest HTTP path and original destinations, client/vendor
  KYC submit and document privacy, shipment and other share surfaces, old links,
  altered signatures/types/IDs, token rotation, expiry/revocation, visibility,
  view counting, key rotation, no open redirect, and user-facing copy/QR flows.
  Test rate limits and printable/interactive changes in real browsers where
  appropriate, using disposable data and fake external services.

## Operational notes

- A token-based short link is itself a **bearer credential** even though it no
  longer contains the original token. Anyone holding it can reach the same
  public resource, subject to its existing rules. Treat it like the original;
  do not publish it or include it in analytics. The type and numerical record ID
  are visible and are not secrets.
- Laravel's application audit trail redacts the `/s/{token}` route parameter.
  Reverse-proxy, web-server, hosting and third-party access logs may still log
  the short URL or the destination; operators must configure their own
  redaction/retention as they do for the original bearer URLs.
- Use the same `APP_KEY` on every application instance. When rotating it,
  retain the former key in `APP_PREVIOUS_KEYS` (Laravel's comma-separated key
  list) for as long as previously issued short links must remain usable. Removing
  a previous key invalidates those aliases but does not invalidate the original
  long links. Changing or revoking a resource's long token immediately
  invalidates its alias, regardless of previous keys. Expired/revoked statement,
  feedback and vendor shares still land on their existing unavailable page;
  the redirect itself does not count a view.
- Resource links are temporary redirects, not branded landing pages: after
  opening one, the browser shows the original full URL. Enquiry and login
  aliases serve the existing public form directly; form submissions continue
  to use the original routes and their authentication/CSRF/rate limits.
  The resolver retains a mounted application path and never redirects to an
  arbitrary host.
- No database migration is introduced by this feature. Deployments upgrading
  from before the earlier ERP resilience/invoice work still need the existing
  backup and `php artisan migrate --force` procedures for those migrations.

## Reproducing the browser check

`tools/checks/public-short-links-browser.cjs` requires a local Chromium,
`playwright-core`, `jsqr`, and `pngjs` (they can be installed in an ignored
`.work` or external tools directory). It refuses non-loopback URLs and blocks
external requests. The fixture seeder refuses non-testing environments and any
database outside `.work`. Create a **fresh disposable** SQLite database, for
example:

```sh
mkdir -p .work
rm -f .work/public-short-links-browser.sqlite
touch .work/public-short-links-browser.sqlite
export APP_ENV=testing APP_KEY='base64:dHR0dHR0dHR0dHR0dHR0dHR0dHR0dHR0dHR0dHR0dHQ='
export APP_URL=http://127.0.0.1:8136 DB_CONNECTION=sqlite
export DB_DATABASE="$PWD/.work/public-short-links-browser.sqlite"
export CACHE_STORE=file SESSION_DRIVER=file MAIL_MAILER=array QUEUE_CONNECTION=sync
export SHORT_LINK_TEST_PASSWORD='browser-only-password-2026'
php artisan migrate --force
php tools/testing/seed-public-short-links-browser.php > .work/public-short-links-fixture.json
# In a separate terminal/process, with the same database and key. Use local,
# not testing, to exercise Laravel's normal request-forgery middleware:
APP_ENV=local APP_DEBUG=false php artisan serve --host=0.0.0.0 --port=8136
# Install browser-only test packages outside the application dependency tree:
npm install --prefix .work/short-link-browser-deps --no-save --no-package-lock playwright-core jsqr pngjs
export NODE_PATH="$PWD/.work/short-link-browser-deps/node_modules"
# Set CHROMIUM_EXECUTABLE_PATH to a working local Chromium binary first.
node tools/checks/public-short-links-browser.cjs
```

The test logs in with fictional users, copies client/vendor KYC and shipment
links at desktop and mobile widths, opens them as a guest, saves client and vendor KYC drafts through canonical
form actions, and checks that tokenless cross-site POSTs are rejected. It also
checks tracking/WhatsApp sharing, decodes the actual rendered shipping mark QR,
checks static forms and an authenticated client-portal KYC copy. No production
data, email or external QR service is used. This is a Chromium screen/QR decode,
not a physical phone scan or printer test.

## Verification results (2026-10-28)

- Red/green: the initial client sharing test failed on the original long URL;
  the mounted-path test failed on a missing `/office` prefix; and an unsaved
  record could initially mint a link. Each now has a passing regression. Testing
  the new statement redirect also exposed a pre-existing failure when an empty
  project-receipt result was a plain Support collection rather than an Eloquent
  collection. `PartyStatement` now uses collection-compatible ID extraction;
  empty currency discovery and an overlapping invoice/project receipt are
  regression-tested so a shared receipt is counted only once.
- `APP_ENV=testing APP_RUNNING_IN_CONSOLE=true php vendor/bin/phpunit -c
  phpunit.xml --no-progress --display-phpunit-notices`: portable PHP 8.4.26 /
  SQLite full suite, **657 tests, 17,294 assertions passed** in approximately 70–74 seconds,
  with one existing PHPUnit mock-without-expectations notice in
  `SalesInvoiceEmailTest`. The same command with `--filter PublicShortLinksTest`
  ran **15 tests, 460 assertions** in 12 seconds.
- `php vendor/bin/phpunit -c phpunit.mysql.xml --no-progress` with a socket-only
  disposable MySQL **8.0.14** `misspack_erp_test` database and strict SQL mode:
  full MySQL suite **26 tests, 286 assertions** in 6 seconds, including a
  full-schema public-alias HTTP test and long shipment-notification URLs. No
  production database was touched.
- The browser command above, Chromium 153 against the disposable SQLite
  instance, passed in 12 seconds: desktop/mobile sharing, guest redirects,
  KYC draft POST and cross-site forgery rejection, actual QR decoding, static
  forms, login gating and portal copy. No physical phone or printer was tested.
  `node tools/checks/{statement,invoices,mark,vendor-portal,feedback}-check.cjs`
  (run separately; `mark` also needs `jsqr`) passed **75**, **82**, **48**,
  **21** and **47** assertions respectively.
- `npm run test:contracts` and `php tools/testing/export-routes.php --check`
  passed: **32 domains, 294 test designs, 141 method-linked cases, 79
  inventoried executable test files and 487 routes**. These counts are not
  runtime coverage claims. All timings were from a shared sandbox.
- An additional, non-contract `node tools/checks/php-check.cjs` run reported
  **8 passed, 2 failed**. The same two findings reproduce on the unchanged
  pre-feature HEAD: it does not recognize `preg_match_all`'s output argument
  in `OfficeAlertCentreTest`, and its bracket counter does not understand the
  nowdoc in `UnexpectedError`. Its parser check passes all PHP files and Blade
  islands, and `php -l` passes both flagged files. These unrelated checker
  limitations were not suppressed or changed.

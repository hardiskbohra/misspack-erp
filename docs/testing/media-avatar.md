# Public media, user avatars and KYC textareas — acceptance/TDD

25 October 2026. REQ-UI / REQ-PEOPLE / REQ-FILES.
- KYC multiline fields inherit shared input typography, borders, padding, focus
  and error appearance, with auto height/minimum lines and vertical resize.
- Public uploaded images work without a storage symlink and with an APP_URL
  pointing to a different host/port; default URLs remain same-origin. Optional
  explicit public storage/CDN URL remains configurable. No private disk exposure.
- Restrict fallback serving to real files under the public disk root; reject
  traversal, hidden paths and symlink escapes; unsafe active content downloads
  rather than executing. Missing public files return404, never a broken HTML200.
- One photo-first user avatar component for listing, record, account and both
  shell menus; missing or failed images show initials. Modal existing-photo,
  new-upload, removal and initial fallback use the same safe rendering policy.
- Preserve upload types/2MB, permissions and private KYC/employee document access.
  A newly chosen replacement photo wins over a stale remove flag.
- Genuine unit/HTTP Red first, full Unit+Integration+Feature and contracts,
  responsive real browser images/textarea/photo-edit checks, then commit/push/CI.

## Findings and implementation

The local checkout has no `public/storage` or `public_html/storage` link. That
alone is not evidence of the user's deployment filesystem, but HTTP Red also
proved that GET `/storage/avatars/example.png` was routed to Laravel's **private**
local disk handler (403 for unsigned access), not the public disk. Private disk
serving previously defaulted to the same `/storage` prefix used by public URLs.

- Public fallback `media.public` serves only a real file contained within the
  public disk root, including realpath checks against symlink escapes. Hidden,
  traversal/backslash/control paths and missing files return404. Raster images
  and PDFs are inline with correct MIME/nosniff/sandbox headers; HTML/SVG/other
  active types download as attachments. It does not search other disks/roots.
- Public disk URLs now default to `/storage` (same origin, scheme and port), with
  `PUBLIC_STORAGE_URL` available for an explicit CDN. User edit JSON uses the
  authoritative `User::avatar_url` accessor instead of constructing another URL.
- Laravel's private signed disk endpoint moves to `/private-storage`. Its
  signature gate remains intact; the ordinary KYC and employee permissioned
  download controllers remain unchanged. Existing generic private temporary
  links should be regenerated after deployment; the ERP has no callers of
  `temporaryUrl` today. Never make the private disk public to fix images.
- `<x-user-avatar>` covers user list/record/account, sidebar/topbar triggers and
  both menu headers, plus vendor comment authors. Contact/party identity marks
  are not user accounts and are intentionally unchanged. Global capture handlers
  handle early cached image failures as well as dynamic edit previews, hiding
  broken icons and exposing initials. Initials handle Unicode/extra whitespace.
- Modal rendering uses DOM methods, not interpolated image/name HTML. Safe URL
  schemes, failed-image fallback and a FileReader stale-read token protect
  replacement/removal. A valid upload wins over a stale remove flag on save.
- KYC textareas use the existing public field tokens and matching base/focus/error
  rules, auto height,96px minimum, multiline padding/line-height and vertical resize.

## Actual results

- Genuine pre-production Red: **7 tests/7 intended failures**, covering URL and
  initials policy, absent textarea selectors, image403, conflicting private
  prefix, initials-only list, and replacement being overwritten by remove flag.
- Focused Green and expanded safety cases: **10 tests/61 assertions PASS**. The
  record/account fixture needed the existing employee document/payslip migrations
  after the intended list assertion passed; no application behavior was changed
  to hide that fixture issue.
- Full portable Unit + Integration + Feature: **541 tests/15,866 assertions PASS**,
  PHP8.4.25 WASM. Native PHP/MySQL are unavailable locally; native CI checked
  after push. No SQL, schema or financial changes.
- Actual Chromium:1440/900/390px light/dark user surfaces, saved edit preview,
  replacement, remove and repick, failed images across list/record/account/shell,
  URL/name injection defense PASS. KYC base/font/radius,96px geometry, focus ring,
  live error border/aria-invalid, multiline values and vertical resize PASS at
  all three widths. Screenshots visually inspected. HTTP views/JSON/image bytes
  are captured from real application tests and routed into the browser; this is
  not claimed as a live PHP browser/database E2E run.
- `npm run test:contracts` PASS (249 Blade templates), permission-audit21/0,
  route inventory463 and diff checks PASS.242 designs (103 unit/139 integration),
  86 linked cases,59 PHP test files; no line/branch coverage claim.
- Optional legacy `employees-check.cjs`:132 pass/1 existing source-inventory
  failure (aggregate_count, packing_description, item_id in unrelated services).
  Replayed HEAD versions of changed sources and obtained **byte-identical output**;
  no existing check regressed, and no assertion was weakened or skipped.
- Raw Red/Green/full/browser/contracts logs, JUnit, captures and screenshots live
  under ignored `.work/media-avatar/`. Links: UT-PEOPLE-004, IT-PEOPLE-004,
  IT-FILES-004 and IT-UI-006 in the generated catalogue.

## Reproduce

Native: `vendor/bin/phpunit -c phpunit.xml`, `npm run test:contracts`,
`APP_ENV=testing php tools/testing/export-routes.php --check`.

Browser capture and execution:

```sh
MEDIA_BROWSER_FIXTURES=1 APP_ENV=testing vendor/bin/phpunit \
  --filter '(UserAvatarUrl|PublicMediaAvatar|KycTextareaPresentation)'
CHROMIUM_EXECUTABLE_PATH=/path/to/chromium node tools/checks/media-avatar-browser.cjs
```

The sandbox equivalents use `/tmp/erp-test-tools/node_modules/.bin/php-wasm-cli`,
`NODE_PATH=/tmp/erp-test-tools/node_modules`, `LD_LIBRARY_PATH=/tmp/al2023/lib`
and `CHROMIUM_EXECUTABLE_PATH=/tmp/chromium` as in earlier testing entries.

## Deployment

Run `php artisan optimize:clear` so old filesystem config/routes do not survive
this change, then rebuild deployment caches per the normal release procedure.
`php artisan storage:link` is still recommended where supported (static files are
more efficient); the fallback handles hosts where symlinks are unavailable. Keep
`storage/app/public` writable and preserve existing uploads. A custom Nginx/Apache
`/storage` location must either point at that directory or forward missing files
to Laravel rather than returning its own403/404 before the application runs.
Missing/deleted source photos cannot be recovered by this fix: they show initials
until reuploaded. Do not expose `storage/app/private`. No migration is needed.

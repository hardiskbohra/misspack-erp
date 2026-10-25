# User detail photo editing — acceptance / TDD

25 October 2026 · REQ-PEOPLE / REQ-FILES / REQ-UI / REQ-AUTH.

- A visible **Edit photo** action beside the identity on every user detail tab,
  for people.manage users only; no journey back to the listing.
- A shared-style photo-only modal with current image/initials, upload/drop,
  preview, remove, cancel and save. Use existing avatar renderer and picker rules.
- Dedicated people.manage-authorised mutation validates JPG/PNG/GIF/WEBP up to2MB,
  changes only avatar, and returns to the same user/tab/year. No role, password,
  salary, bank, name or contact edits through the photo-only endpoint.
- Save a replacement before deleting the old image; remove only on explicit save.
  Reject invalid/empty submissions and preserve original photo on failed storage.
- Dedicated validation errors reopen the photo modal on the detail page.
- Guests, employees and people.view-only accounts cannot mutate photos; readers
  see avatars without an edit action. Keyboard focus, Escape/cancel/backdrop and
  focus restoration work at1440/900/390px light/dark, without overflow.
- Genuine HTTP Red before production; run focused/full Unit+Integration+Feature,
  portable contracts and real Chromium before commit/push; then native CI.

## Implementation and safety

The detail identity card now has an explicit camera/Edit photo action for
`people.manage`. The dialog is rendered once, outside the tab panels, on all six
tabs and not rendered at all for readers. The same shared modal, avatar renderer,
upload/drop band and size/type policy are reused; pending replacement/removal is
reset when reopened. Save is disabled until a valid change is selected.

`PATCH users/{user}/photo` uses the existing office/people.manage route boundary.
Its controller whitelists only photo/removal and safe tab/year navigation. A
photo-only request cannot change other account/profile fields even if supplied.
The existing user edit and new photo edit share persistence: store the replacement
first, update the user, then delete the previous file. Failed storage retains the
old record/file; a thrown persistence exception cleans up the newly stored file.

Errors are named `userPhoto` and flashed with a record-specific reopen flag, so
validation returns to the same detail context instead of the listing. HTTP tests
carry the actual session cookie through the error redirect before checking the
rendered alert; this mirrors a browser rather than creating a fresh test session.

## Actual evidence

- **Red:**6 intended HTTP failures before production changes (no action/modal or
  photo endpoint). The unconsumed storage mock was risky while the route was
  absent; it is consumed in Green.
- **Green:** new detail + existing public media integration **12 tests/131
  assertions PASS**. Existing URL/avatar/KYC focused suite10/61 PASS.
- Complete portable **548 tests/15,953 assertions PASS** (Unit + Integration +
  Feature), including the added `users.photo.update → people.manage` unit case.
  PHP8.4.25 WASM locally; native PHP/MySQL checked through CI after push.
- Actual Chromium1440/900/390px light/dark PASS: visible action, current image,
  replacement/removal/repick, cancellation/reset, invalid file rejection, no-photo
  initials, read-only hiding, focus trap/restoration, Escape/header/footer/backdrop,
  validation auto-reopen/alert, native multipart fields and viewport/scroll bounds.
  Desktop light and mobile dark screenshots visually inspected. Existing media
  and KYC browser checks also PASS.
- Views/images are captures from real HTTP tests routed into Chromium, not a
  claimed live PHP/database browser E2E. Real HTTP tests separately prove
  persistence, authorisation and same-tab/year redirect. The browser checks
  multipart emission without depending on a mocked redirect/network chain.
- Portable contracts PASS (250 templates), permission audit21/0, route inventory464,
  diff check clean. IT-PEOPLE-005 links6 exact methods.243 designs/87 linked cases/
  60 PHP test files inventoried; no line/branch coverage claim.
- One initial focused run overlapped a full run and collided on Laravel's shared
  fake public disk. Both suites were rerun **serially**, without changing or
  weakening assertions; the full gate is also serial. Do not run independent
  PHPUnit processes concurrently against the same fake disk path.

Artifacts: ignored `.work/detail-photo/{red,focused,full,browser,contracts}.log`,
`full.xml`, HTTP captures and screenshots. Reproduce natively:

```sh
vendor/bin/phpunit -c phpunit.xml
npm run test:contracts
APP_ENV=testing php tools/testing/export-routes.php --check
DETAIL_PHOTO_FIXTURES=1 vendor/bin/phpunit --filter UserDetailPhotoTest
CHROMIUM_EXECUTABLE_PATH=/path/to/chromium node tools/checks/detail-photo-browser.cjs
```

The sandbox uses the same PHP WASM and Chromium paths documented in
[media-avatar.md](media-avatar.md). No migration is required; clear/rebuild cached
routes on deployment so the new photo endpoint is registered.

The optional legacy employee source checker retains its pre-existing132 pass/
1 unrelated column-inventory failure (aggregate_count, packing_description,
item_id), as documented in the preceding media task. No additional failure and
no weakened assertion; portable mandatory gates pass.

# Audit register UI — acceptance / TDD (25 October 2026)

REQ-AUDIT / REQ-UI / REQ-AUTH. Use shared ERP pagination, table/cards, status
chips, filter drawer and modal surfaces. Primary search remains visible.
Replace clipped inline evidence popovers with one read-only accessible modal
outside the table. Show record identity, actor/event/subject/outcome/status,
changed field names, escaped context, URL, IP and user agent. No audit mutations.
Modal must support close control/footer, backdrop, Escape, trapped focus and
return focus to its originating row; switching rows must not retain stale data.
Long evidence scrolls inside the modal without overflowing desktop/mobile.
Shared pagination preserves all active filters; evidence is current-page scoped.
Empty/single-page lists, permission boundaries, malicious/long content, dark mode,
1440/900/390px, searchable drawer selects and readonly model remain covered.
Page-local failed/denied counts must be labelled honestly; do not change queries,
permissions, sensitive-value redaction or immutable audit storage in a UI task.

## Executed evidence

- Initial genuine Red: four HTTP/database tests, three expected failures for
  missing shared pagination/modal/current-page templates (not broken fixtures).
- Green:4 focused tests/40 assertions; full portable PHP8.4.25 WASM Unit +
  Integration + Feature **531 tests/15,805 assertions PASS**. Tests inspect
  actual rendered HTML, pagination query strings, escaped hostile values,
  permission denial and unchanged immutable log count. No SQL/schema changed.
- Actual Chromium at1440/900/390px in light/dark passes modal geometry, wrapped
  scrolling evidence, close/header/footer/backdrop/Escape, Tab/Shift+Tab trapping,
  row focus restoration, row-content replacement, single GET form scope,
  filtered page2 navigation, searched drawer Select2, hostile text and empty view.
  Browser testing caught a real mobile issue: the shell hides page actions on
  phones; the Audit filters trigger now sits beside primary search, available at
  every breakpoint. Screenshots inspected, including mobile dark and desktop.
- Use `AUDIT_BROWSER_FIXTURES=1 vendor/bin/phpunit --filter AuditRegisterPresentationTest`
  then `node tools/checks/audit-register-browser.cjs` (Playwright + Chromium).
  Browser serves captured real HTTP Blade pages and production local assets;
  PHP HTTP/database behavior is separately exercised, not claimed as live browser
  PHP E2E. No external service or production/customer data is used.
- `npm run test:contracts`, `permission-audit-check.cjs` (21 checks), route export
  (462 unchanged routes) and whitespace checks PASS. Native PHP/MySQL CI is
  inspected after push, not inferred from SQLite or source checks.
- IT-AUDIT-004 links4 exact methods. Inventory:238 designed cases (102 unit/136
  integration),82 method-linked designs,56 PHP test files. These are not line/
  branch coverage percentages or a claim that every scenario is automated.
- Runtime evidence: `.work/audit-register/{red,focused,full,browser,contracts}.log`,
  `full.xml` and light/dark desktop/tablet/mobile screenshots.

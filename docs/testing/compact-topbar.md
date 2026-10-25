# Compact topbar (25 October 2026)

Acceptance: remove the standalone theme switch, retaining it inside the profile
menu; move the standalone private Notes action into Quick Menu. Quick Menu and
module reporting-filter triggers are icon-only with accessible names, custom ERP
tooltips and40px touch targets. Selected period, exact dates and currency appear
in page content, not the topbar. Preserve drawer interaction, keyboard focus,
per-module permissions, private Notes access for employees, search and briefings.
No database/reporting arithmetic changes. Test-first HTTP/Blade regressions,
full portable suites/contracts and actual responsive browser checks precede push.


Evidence (IT-UI-004):
- Actual test-first red:3 tests/6 assertions/3 failures reproduced standalone theme
  button, visible Quick Menu label and missing Notes shortcut. Focused green:
  25 tests/197 assertions (launcher, role/permission and dashboard integration).
- Complete Unit+Integration+Feature suite: **483 tests,15,304 assertions PASS**,
  PHP8.4.25 WASM, independently checked JUnit; no skipped/error/failure cases.
- Existing menu-count expectations deliberately change8→9 for a full office user,
  and0→1 for employees/settings-only readers: the added entry is private Notes,
  not an unauthorised module. Operational permission assertions are retained.
- Real Chromium153: directory + six modules at1440/900/390px, light/dark surfaces,
  no document overflow or JS exceptions, icon-only accessible launchers, exact
  dates in content,40px targets and mobile reporting access. Work dashboard also
  exercises Quick Menu keyboard End→Notes/Escape/focus restoration, profile theme
  reachability and reporting drawer open/close at all three sizes. Existing actual
  Select2 month/custom switching and date preservation still pass.
- Shared icon-only filter variant is opt-in; other list/drawer buttons keep text.
  Canonical resources/css component and generated static fallback are both updated
  through `npm run build:design-system`. Shared tooltip system supplies tooltips.
- Source checks: notes67, sidebar/navigation10, design79 and shared UI24; default
  catalogue/period/dashboard/Blade contracts regenerated and checked. Previous
  standalone Notes source expectations are replaced by explicit Quick Menu and
  both-role access assertions, not simply deleted.
- No SQL, schema or financial arithmetic changes. Native PHP/MySQL CI remains the
  post-push independent gate. No live data or delivery services accessed.

Reproduction: use existing `render-dashboard-analytics.php` and
`dashboard-consolidation-browser.cjs` commands from dashboard-consolidation.md.
HTML/screenshots/JUnit stay ignored under `.work/`.

The sidebar source contract also removes stale executive/Overview requirements
from the earlier retirement: it now explicitly requires Analytics/module directory
and rejects the retired executive route. No executive UI was restored.

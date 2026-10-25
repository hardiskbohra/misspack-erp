# MissPack ERP — testing and TDD hand-off

Prepared **25 October 2026** from this repository, not a generic ERP template. Here **TDD means Test-Driven Development**.

## Deliverables

| Deliverable | Contents |
|---|---|
| [Unit test cases](unit-test-cases.md) · [spreadsheet CSV](unit-test-cases.csv) | 95 unit-test designs with IDs, priority, preconditions, inputs, steps and expected results |
| [Integration test cases](integration-test-cases.md) · [spreadsheet CSV](integration-test-cases.csv) | 121 integration designs, including cross-module money, privacy and workflow scenarios |
| [Test strategy](test-strategy.md) | Boundaries, risks, environments, acceptance criteria and release gates |
| [TDD playbook](tdd-playbook.md) | Concrete red–green–refactor workflow, regression discipline and a real next-test example |
| [Fixture specification](fixtures.md) | Deterministic people, currencies, documents, product lanes, dates and files |
| [System journeys](system-journeys.md) | End-to-end integration sequences with quantitative reconciliation points |
| [Traceability matrix](traceability.md) | All 31 domains and links between requirements, designs and executable evidence |
| [Execution baseline](execution-baseline.md) | Actual results, corrected legacy-test contracts, unexecuted checks and open defects |
| [Source inventory](source-inventory.json) | Every discovered controller, service, model, test file/method and source check |
| [Route inventory](route-inventory.json) | 454 declared routes with methods, names, actions and middleware |

**216 designed cases are not 216 automated or passing scenarios.** The catalogue links 61 cases to specific executable methods; some links are deliberately labelled partial. There are more existing regression methods than catalogue links. The source inventory is broader than the curated catalogue, and neither is a code-coverage report. The entire system is **not** exhaustively automated or certified by this hand-off.

### Coverage scope

Authentication/RBAC; people/payroll; clients/KYC; vendors; products/pricing; leads; projects/product lanes; execution/approvals/financial closure; QC; Packaging Lists and labels; shipments/delivery; PI/cash/tax invoices; purchase documents; cashflow/allocations/transfers; recurrence; statements; fixed assets; organisation; office services; tasks/coordinator queues; notes; feedback; briefings; global search; settings; client portal; vendor portal/shares; dashboards; audit; files; shared UI/print.

**Latest portable run:** 474 PHPUnit tests and15,216 assertions passed, including the receiver-facing delivery receipt and consolidated dashboard regressions. The original testing-pack baseline was427 tests/14,986 assertions (134 added in that task), with passing native PHP/Composer and MySQL CI jobs. See [dashboard-consolidation.md](dashboard-consolidation.md) for current analytics verification and [delivery-receipt-print.md](delivery-receipt-print.md) for print/PDF verification; complete system journeys and real concurrency remain separate follow-up gates.

## Executable foundation

The new tests cover permission resolution and implication, employee privacy and field allowlists, depreciation arithmetic, file policy boundaries, client-portal enablement, real HTTP office-access boundaries, note ownership/CRUD, workflow idempotence and independent product lanes, and route-boundary registration. Existing financial, packaging, shipment and dashboard regressions are retained. `tests/Support/IsolatedDatabase.php` provides opt-in disposable fixtures without touching an installation database.

`phpunit.xml` now discovers **Unit**, **Feature** and **Integration** suites. Production-dialect reporting tests live separately in `tests/Database`, invoked through `phpunit.mysql.xml`.

## Run locally

Prerequisites: PHP 8.4+, Composer, Node 22+, SQLite PDO, mbstring, DOM/XML, bcmath, GD and ZIP. Use the repository's locked Composer dependencies. Do not use customer data, a live `.env`, or production database credentials for testing.

```sh
composer install --no-interaction --prefer-dist
vendor/bin/phpunit                         # complete portable suite
npm run test:integration                   # real HTTP/database integration subset
npm run test:contracts                     # catalogue + dashboard behaviour + Blade checks
APP_ENV=testing npm run test:routes         # refresh-detection for declared routes

# Focused TDD loop:
vendor/bin/phpunit --filter PermissionMatrixTest
vendor/bin/phpunit --filter ProjectWorkflowExecutionTest
vendor/bin/phpunit --filter CashInvoiceConversionTest
```

The default XML uses in-memory SQLite, array sessions/cache, and a **test-only**, non-secret encryption key. Integration fixtures replace their connection explicitly. Never run a destructive migration or concurrency test against an existing installation.

For native SQL verification, provision a **disposable** MySQL database named `misspack_erp_test`, then supply test-only host/user/password values through environment variables:

```sh
APP_ENV=testing DB_HOST=127.0.0.1 DB_USERNAME=YOUR_LOCAL_TEST_USER \
  DB_PASSWORD=YOUR_LOCAL_TEST_PASSWORD npm run test:mysql
```

The MySQL XML forces the test database name and the test class rejects any database whose name does not end in `_test`. It creates/drops only randomly named test tables. MySQL CI also runs the **whole fresh migration chain** against its isolated service, separately from SQLite fixtures. Those MySQL checks were **not run in the local sandbox**, but the fresh migration and native aggregation CI job passed in [run40176652907](https://github.com/hardiskbohra/misspack-erp/actions/runs/40176652907).

## Update this pack

Edit `catalogue.json`, not the generated Markdown/CSV or matrix:

```sh
npm run test:catalogue:build
npm run test:catalogue
APP_ENV=testing php tools/testing/export-routes.php
APP_ENV=testing npm run test:routes
```

The catalogue checker rejects duplicate IDs, missing case fields, missing source files, nonexistent linked methods, and stale generated exports. It does not execute those PHP methods. Add an exact `tests/...Test.php#test_method` reference only after implementation, and use `evidence_scope: partial` when it proves only part of the design. Record execution separately, not as a guessed pass flag in JSON.

## Release caveat

A passing portable suite is necessary but not sufficient. The asset-disposal probe found a documented prorating discrepancy; native MySQL migrations/aggregation now pass in CI, while real concurrent writers, cross-tenant portal downloads and full browser journeys remain explicit follow-up gates. See the baseline and prioritised TDD backlog before treating the ERP as release-certified.

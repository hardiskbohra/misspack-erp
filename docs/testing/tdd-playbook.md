# Test-Driven Development playbook

## What this hand-off does and does not mean

The ERP already existed. New tests in this task are primarily **retrospective characterization and regression tests**; writing them now does not make the historical ERP “built with TDD”. This playbook establishes the process for future work and a risk-ranked backlog for the uncovered catalogue.

## Mandatory standing rule

All future features, behaviour changes and bug fixes must follow test-first Red → Green → Refactor. Before **every commit and push**, run the complete Unit, Integration and Feature suites using `vendor/bin/phpunit -c phpunit.xml`, plus `npm run test:contracts`. Focused checks supplement, not replace, this gate. Report failures or blocked execution honestly and obtain explicit approval before submitting anything unverified. Documentation-only changes need no artificial red business test, but still require unit/integration verification. See the repository's `AGENTS.md` for the persistent development rules.

## The loop for every change

1. **Specify:** choose a case ID and requirement. Identify the authoritative service and all affected modules. Write concrete amounts/dates/actors and rejection semantics; resolve ambiguous business policy before implementation.
2. **Red:** add the smallest executable assertion for the missing rule or reproduced defect. Run it alone. Confirm failure is the missing behaviour, not a nonexistent route/table, wrong role, missing key, null fixture identity or array-order accident.
3. **Green:** make the minimum production change in the existing service. Do not introduce a second calculator/ledger/workflow to satisfy the test. Use real money precision and transaction boundaries.
4. **Refactor:** remove duplication while keeping the test red if the rule is removed. Prefer data providers for boundary partitions and factories/traits for fixtures, not generalized mocks of the method being tested.
5. **Integrate:** add HTTP/persistence/rollback/ownership checks where the rule crosses boundaries. Add a native-DB test when SQLite cannot establish SQL or locking correctness. Add browser evidence for event/focus/print changes.
6. **Record:** link the case to the exact method, mark partial evidence honestly, rebuild the catalogue and route inventory if relevant, and record the actual test commands/results.
7. **Commit and push:** after passing the affected checks, commit the code/tests/docs and push the fixed session branch. Never stage unrelated snapshot/history changes without comparing to the remote tree.

### Everyday commands

```sh
# RED: write a single failing business assertion first.
vendor/bin/phpunit --filter AssetDepreciationTest

# GREEN: test the related integration boundary, then neighbours.
vendor/bin/phpunit --filter ProjectWorkflowExecutionTest
vendor/bin/phpunit --filter 'CashInvoiceConversionTest|CashInvoiceDeletionTest|CashflowDocumentBalanceTest'

# REFACTOR/hand-off: retain full suite and declared-route checks.
vendor/bin/phpunit
npm run test:contracts
APP_ENV=testing npm run test:routes
```

For a new feature, keep the first red assertion in the same eventual PR but do not push a deliberately failing default suite as if it were a passing release. Record the red command/output in the PR or approved CI artifact. A `markTestSkipped`, broad exception catch, wildcard status assertion or deleted assertion is not a green implementation.

## Concrete next cycle: asset disposal prorating (IT-ASSET-002)

**Finding, not fixed here:** a manual probe of the current service with cost100000, residual5%, life5 years, bought1Apr2026 and disposed30Sep2026 returned a year charge of **95000**. The service documentation says disposal truncates/pro-rates the charge; that yields **9526.03 = 19000 × 183 / 365**. The `schedule()` final-row adjustment currently absorbs the entire remaining depreciable amount even on early disposal. This needs accounting-policy confirmation and a focused production fix; the existing green baseline does not cover it.

After confirming the intended accounting rule, add this test in `AssetDepreciationTest` (its existing `asset()` helper is available):

```php
public function test_disposal_prorates_the_charge_instead_of_writing_down_the_entire_remaining_life(): void
{
    $asset = $this->asset([
        'purchase_date' => '2026-04-01',
        'disposal_date' => '2026-09-30',
        'cost' => 100000,
        'useful_life_years' => 5,
        'residual_percent' => 5,
    ]);

    $charge = (new AssetDepreciation())->chargeForYear($asset, '2026-27');
    $this->assertEqualsWithDelta(9526.03, $charge, 0.01);
}
```

- **Red:** run only this method; verify actual95000 versus expected9526.03, not fixture failure.
- **Green:** distinguish disposal from natural useful-life exhaustion in the one existing schedule calculator. Do not change every depreciation policy or store an independent accumulated amount.
- **Refactor:** share prorating/residual guards; retain SLM/WDV conservation tests for naturally exhausted assets.
- **Integration:** disposal-day book value, next-day zero NBV, sale gain/loss, partial/leap financial year, yearly totals and printed/export reconciliation. Add multiple-disposal and before-purchase validation cases if the accepted policy requires them.
- **Evidence:** replace the planned/known-gap status only after the regression and actual report integration pass. The example is intentionally a documented next test, not a silently skipped passing assertion.

## Backlog, in order

| Wave | Cases | Required evidence |
|---|---|---|
| 0: accounting defect | IT-ASSET-002 and dependent asset reports | Finance signoff; real red/green; correct disposal/prorating and gain/loss |
| 1: money under contention | IT-CASH-002/003, recurrence duplicate posting, invoice sequences/conversion | Two concurrent native connections/processes; deterministic barrier; persisted conservation and rollback assertions |
| 2: portal ownership | IT-CPORTAL-001/002/003, IT-VPORTAL-001/002/003 | Cross-client/vendor read, list, download, print and mutation matrix; disabled/stolen/revoked token paths |
| 3: full dispatch/closure | QC + PACK + SHIP + WORKFLOW system journeys | Real gates/approvals/overrides, stale-evidence invalidation, independent quantities and final financial closure |
| 4: master data and communications | LEAD, PRODUCT, ORG, SERVICE, SETTINGS, BRIEF, SEARCH | Validation, reference integrity, permission-filtered snippets, fake notification delivery and recipient isolation |
| 5: browser/release | UI and system journeys | Keyboard, mobile, print, native migrations, upgrade/restore and performance measurements |

Each wave adds both positive and rejection/rollback tests. “Create/read/update/delete works” without authorization and financial side-effect assertions does not complete a module.

## Review checklist

- The failing expectation matches approved business behaviour, not merely current code.
- Cases include zero, exact boundary, just over boundary, null/array/invalid input and stale data where relevant.
- Assertions inspect exact domain outcomes and unchanged unrelated rows, not just HTTP200/302.
- No test depends on execution order, production records, network email or another test's static cache.
- A fixed defect has a regression which would fail if the fix were removed.
- SQL dialect/concurrency, browser behaviour and source checks are reported as different forms of evidence.

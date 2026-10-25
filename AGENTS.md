# Repository development rules

## Mandatory test-driven development

For every new feature, behaviour change or bug fix:

1. Define the acceptance criteria, affected business rules and relevant catalogue IDs in `docs/testing/`.
2. Write the unit/regression test **before changing production behaviour**. Run it and verify that it fails for the intended missing behaviour, not a broken fixture or environment (**Red**).
3. Implement the smallest production change in the existing authoritative service (**Green**).
4. Refactor while preserving the passing assertions. Add integration tests for affected HTTP, permission, persistence, transaction, ownership and cross-module boundaries.
5. For a defect, retain a regression test that would fail if the fix were removed. Do not weaken assertions, skip failures or change an expected business result merely to make the suite green.

Use `docs/testing/tdd-playbook.md` and `docs/testing/test-strategy.md`. Distinguish true test-first development from retrospective characterization. Documentation-only changes do not need an artificial failing business test, but still require the pre-submission test gate below.

## Mandatory verification before repository submission

Before **every commit and push**, including documentation/configuration changes:

- Run the complete portable PHPUnit suite (`vendor/bin/phpunit -c phpunit.xml`). This must include **Unit**, **Integration** and **Feature**; a focused test or source check alone is not sufficient.
- Run `npm run test:contracts` and check the diff for accidental changes and whitespace errors.
- Add/run native MySQL checks for SQL/schema/locking-sensitive changes and browser checks for interactive UI/print changes. Never claim SQLite or source checks establish native concurrency or browser behaviour.
- Use only disposable test databases and fake external delivery/storage. Never test against production data or credentials.
- Record the actual commands, runtime, results and any unexecuted checks. If dependencies or infrastructure block verification, explain the blocker and obtain explicit approval before submitting an unverified change; never describe it as passed.
- Update test-case links and generated inventories when relevant, without confusing designed cases, linked tests and measured runtime coverage.

After successful local verification, commit and push changes to the branch assigned to the active session. Check the remote/PR state and relevant CI results, and report any CI failure accurately. Preserve unrelated user changes and never stage a reconstructed snapshot wholesale without comparing it to the remote baseline.

# Deterministic fixtures and isolation

These fixture IDs describe reproducible **test data**, not real ERP IDs or customer data. Tests should create actual IDs and pass model instances rather than assume an existing row numbered 1.

| Fixture | Construction and purpose |
|---|---|
| F-IDENTITY | A=full office administrator; R=custom office user with sales.view only; M=custom domain manager; E=employee owner; B=other employee; C1/C2=unrelated clients; V1/V2=unrelated vendors; CP1/CP2 and VP1/VP2=their portal accounts. Add disabled, expired and must-change-password variants. |
| F-MONEY | PI ₹3,00,000 with original multi-item lines; cash receipt ₹1,00,000; bank receipt ₹50,000; requested cash invoice ₹1,00,000. Add opening credit, refund, tentative/cancelled receipt, split allocation and overpayment variants individually rather than mixing unrelated edge cases in one fixture. |
| F-FX | USD document with INR source receipt and stored explicit source/document amounts; partially allocated remainder. Add RMB separately. Assert both amount domains and never infer an exchange rate from mixed totals. |
| F-PRODUCTS | Project P1 belongs to C1; product A required100 and B required200; P2 belongs to C2. Add one cancelled lane, draft/confirmed/mismatched packing lists and split shipments40+60 for A. |
| F-DATES | Freeze to25Oct2026 in a declared timezone; inclusive custom10..12Oct; preceding7..9Oct. Insert rows9Oct23:59:59,10Oct00:00:00,12Oct23:59:59 and13Oct00:00:00. Include29Feb2024,31Jan2028 and1April financial-year boundaries. |
| F-FILES | Fake disks and uploaded files: valid PDF/image, uppercase extension, disallowed executable/double extension, MIME mismatch, exactly20480KB and20481KB, removed stored file, malicious original filename. Do not use real client documents. |
| F-ASSET | Cost100000, GST18000, useful life5, residual5%; SLM/WDV and claimed/capitalised GST variants. Separate disposal fixture bought1Apr2026 and disposed30Sep2026 exposes the open prorating discrepancy. |

## Rules for executable fixtures

1. Tests run only with `APP_ENV=testing`. `IsolatedDatabase` refuses another environment, forces `:memory:` SQLite, purges the previous connection, and uses array session/cache stores.
2. Scope migrations explicitly for fast integration tests; document stub tables. The workflow tests create only an invoice identity table because they test lane execution, not invoice settlement. They must not be cited as coverage of invoice money or all workflow gates.
3. Fixture models which are meant to represent stored rows need distinct IDs. Null IDs made unrelated unheaded cartons collapse into a single section in the old test fixture.
4. Use `forceFill` only to construct internal test fixtures for guarded attributes, never to imply the ordinary HTTP client may write those fields. The portal login unit fixture sets client.portal_enabled deliberately, while project ownership tests ensure normal `fill` cannot set workflow-owned fields.
5. Preload in-memory relations when a document-only test has no repository. In particular payslip issuer addresses/banks and carton items must not depend on another test's database state. Compare date values, not Carbon object identity.
6. The organisation accessor has process-static caching. The current suite's issuer fixtures pin its relation values; add cache-invalidation/reconfiguration integration tests before assuming behaviour across multiple organisations or a long-running worker.
7. Restore Carbon clocks and fake resources in teardown. Do not assume fixed `now()` across different test processes. Leave generated runtime files and JUnit/coverage artifacts under ignored `.work/` or external CI storage.
8. For native concurrency, use independent connections to a disposable MySQL database, not SQLite in-memory handles. Never clear/migrate an existing installation to make a test pass.

## Persisted negative assertions

Every sensitive rejected action should assert that source document totals, receipt ownership, allocation sums, target state and evidence metadata remain unchanged. For rollback tests snapshot only relevant rows/fields; avoid comparing dynamic timestamps as business values. Use stored `original_name` only as metadata, never as a trusted filesystem path.

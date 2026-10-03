# The employee side of the user module

> An employee **is** a user of this ERP with a smaller set of doors. There is one
> `users` table, one login form, and one column — `role` — that decides which
> application the person sees after signing in. Nothing is stored twice about
> anybody.

Written for whoever maintains this next: it is the map of *who may do what*, in
the order the questions actually get asked.

---

## 1. The two roles

| | Administrator (the office) | Employee (the payroll) |
| --- | --- | --- |
| Sees | every screen built so far: ledger, clients, vendors, shipments, projects, paperwork, users | their own workspace and nothing else |
| Lands on after login | `/dashboard` | `/my` |
| Menu | the full sidebar | four items: Dashboard, My Salary, My Documents, My Profile |
| Created from | Users → Add user → role "Administrator" | Users → Add user → role "Employee" |
| Required to create | name, email, password | + designation, joining date, mobile |

`role` is `admin` by default, and `User::isAdmin()` is written as *"not an
employee"* rather than *"is an admin"* — so any row that predates the column, or
carries a value a later version does not know, can still work. Locking an office
out of its own ledger is the worse failure.

The two guard rails on this side:

- **the last administrator cannot be demoted** and cannot delete themselves
  (`UserController::isLastAdmin()`), because the users page, the payroll and the
  ledger all live behind that account;
- **an employee code belongs to one person** — blank means "give them the next
  one in the `EMP-0001` series" (`EmployeeProfile::nextEmployeeCode()`).

## 2. Where the two halves are separated

```
routes/web.php
  Route::middleware('auth')
    ├─ Route::middleware('office')   ← EnsureUserIsAdmin
    │    the whole ERP as it was before: cashflows, clients, vendors, shipments,
    │    projects, tasks, sales invoices, documents, statements, users…
    │
    └─ Route::prefix('my')           ← the employee's own record
         /            dashboard
         /profile     GET, PUT      — and POST /password
         /salary      GET           — the ledger months *and* the payslips
         /payslips    GET           — a redirect to /salary?focus=payslips#payslips
         /payslips/{payslip}/file | /pdf
         /documents   GET, POST     — and /documents/{document}, /{document}/file
```

Two properties of that list are load-bearing:

1. **no personal route takes a user id.** The person is the session. There is no
   number to change, so there is no record to reach for.
2. The routes that *do* take a row id — a payslip's file or document, and a
   document's file — ask `App\Services\EmployeeAccess::canView($actor, $owner)`
   before a byte is served, and the office side re-checks that the row belongs to the person in
   its own URL (`abort_unless($document->user_id === $user->id, 404)`). A wrong
   id is a 404, never somebody else's salary.

An employee who follows an old bookmark into the office's half is **redirected
to their own workspace with a sentence**, not shown a 403 — the person holding
an employee login is staff, not an attacker.

## 3. Managing one person's profile

`Users → the folder icon` — `/users/{user}` — is the record: identity, contact,
employment, bank, and then a tab per question — Overview, Details, **Salary**
(the ledger months and the payslips, together), Documents, Work. `Users` list has
role chips (Everyone / Office / Employees) with counts.

There is **no separate payslips tab**. A payslip *is* a month of the salary, and
a tab of its own listed the same months one click away from the entries that paid
them — two places to read one month, which is two places to disagree about it. A
tab is a URL, so `?tab=payslips` is not dead either: `UserController::TAB_ALIASES`
resolves it to the salary tab rather than dropping the reader on Overview.

Ownership of every field is declared **once**, in `EmployeeAccess`:

| Only the employee may edit | Only the office may edit |
| --- | --- |
| mobile, address, date of birth, emergency contact name and number | name, email, role, department, designation, employee code, joining date, employment type and status, PAN, bank name / account name / number / IFSC |

The employee's profile page is built from `ownEditableFields()`, so the form and
the rule cannot drift: an employee who posts `bank_account_number` is ignored by
the controller, which validates only the fields in that list.

## 4. Pay, payslips and papers

**Salary** is not a second ledger. An employee's salary is **every cashflow
entry filed against them** (`employee_id = them`), read through
`EmployeeProfile::salaryQuery()` — and the *direction is read off the entry*
rather than assumed:

- a **debit** is money that left the company, which is what paying somebody
  looks like on a cash book — and it is the ledger's own default;
- a **credit** filed against a person is money that came back (an advance
  repaid, a recovery), so it is subtracted rather than added.

`CashflowEntry::isMoneyOut()`, `amountMoved()` and `signedAmount()` decide that
once; `EmployeeProfile::payFrom()` and `monthsFrom()` do the arithmetic over
rows in hand, so `tests/Unit/EmployeePayTest.php` can prove it without a
database. Both the office's record page and the employee's own pages are built
from those two methods — the figures cannot disagree.

> This was wrong the first time, in a way no source-reading check could see:
> the record counted *credits* against a person while the office files a salary
> as a **debit**, so a ₹1,00,000 salary read as ₹0 on the person's page. The
> direction had been assumed, not read, and every line was spelled correctly.

**Payslips** (`employee_payslips`) are one row per person per month:

- the **row is the record**, and the slip is *rendered from it every time* — it
  is never stored, because a stored copy of the figures is a copy that can be
  older than the record it came from, and the first person to notice would be
  the employee holding the paper. An optional **signed scan** can be attached;
  that is a file, not the slip;
- `draft` is the office's working copy and is **invisible to the employee** —
  in the list (`issuedPayslips()`), as a file, **and** as a document: both doors
  into a slip pass `EmployeeWorkspaceController::guardPayslip()`, so a draft that
  is invisible as a list row cannot be read as paper instead;
- when a salary payment exists for that month, the slip links to it
  (`cashflow_entry_id`), so the slip and the ledger can be read against each
  other instead of argued about.

### The payslip document

`App\Services\PayslipDocument::build($payslip, 'app'|'pdf')` is the **one
definition of what a payslip says**. Three surfaces read it — the office's
record page, the employee's own salary page, and paper — so the figure on the
screen and the figure on the printed sheet cannot drift apart. It carries:

- the month, the slip number (`EMP-0007/2026-08`, derived from the person's code
  and the period — never stored, so a correction cannot leave it wrong);
- the person: code, designation, department, employment type and status, joining
  date, PAN, and the bank line with the **account number masked** exactly where
  the record masks it;
- attendance: working days, paid days and the loss of pay between them;
- **earnings and deductions as lines**, with the totals *summed from those lines*
  — `EmployeePayslipController::figures()` derives the gross and the deductions
  when the form sends a breakdown, so a slip whose parts do not add up to its
  whole cannot be saved. The lines arrive under their own field names
  (`earning_lines`, `deduction_lines`) while the totals keep theirs
  (`gross_amount`, `deductions`, `net_amount`): the one-button issue /
  back-to-draft form posts the totals it read off the row, and a single key
  cannot be both a number and a list — when it was, PHP kept the last rule it
  saw and that button failed validation;
- the net **in words** (`CommonHelper::inWords()`), Indian grouping
  ("Rupees One Lakh Fifty Thousand Only") and paise when there are any;
- how the money moved: paid-on date, mode, bank reference, and the ledger entry
  it matches;
- the office's own identity — name, address, GSTIN, PAN, bank — read from
  `SalesInvoice::defaultSellerDetails()`, the same source the invoices use.

`PayslipDocument::download()` returns a **dompdf** download when
`barryvdh/laravel-dompdf` is installed, and otherwise the same document as a
standalone printable page (`employees/payslip-pdf.blade.php`, A4 `@media print`,
opening its own print dialog) — the convention every other document in this
application already follows. There is no PDF library in `composer.json` today,
so the printable page is the live path; installing dompdf upgrades it to a file
without touching a line of the slip.

**Handing it over.** An issued slip carries a WhatsApp draft (`whatsappUrl()`,
ten-digit mobiles addressed as Indian) and an email draft (`mailUrl()`) for the
employee's own mobile and address, and both buttons sit on the office's list.
The message says which month and where to look — `shareMessage()` deliberately
carries **no breakdown**, because the figures belong behind the login and a chat
message is not a private place.

**Where they are read.** One list,
`resources/views/employees/partials/payslip-table.blade.php`, is drawn on both
sides with `context="office"|"employee"`: the office sees the issue state and the
corrections on each row, the employee sees the months and the print buttons.
Everything else — the month, the figures, the slip number, the print link — is
the same list.

**Documents** (`employee_documents`) are the person's own file, separate from
`cashflow_attachments` (which are the bills behind the ledger). Required:
ID proof, address proof, resume. Optional: experience certificate, education,
PAN card, bank proof, offer letter, other.

- the employee uploads their own, and may remove one they uploaded **while
  nobody has verified it**;
- the office can file one on their behalf, and marks each as seen
  (`verified_by` + `verified_at`) — the difference between "they uploaded
  something" and "we looked at it" is the whole reason the checklist exists;
- both sides read the same checklist (`EmployeeProfile::documentChecklist()`),
  so "2 of 3 required papers" means the same thing on both pages.

Uploads go through `App\Services\DocumentUpload` — one allowlist (17 extensions),
one 20 MB ceiling, one metadata shape — instead of a copy per controller.

## 5. After pulling this change

```bash
php artisan migrate
```

Existing accounts become administrators (the column defaults to `admin`), which
keeps every current login working exactly as it does today. To put somebody on
the payroll: Users → Add user → role **Employee** (or edit an existing person
and change the role). `DatabaseSeeder` now seeds one administrator
(`admin@misspack.com`) and four employees so a fresh install has both sides to
look at.

## 6. What is verified where

`node tools/checks/employees-check.cjs` — 80 source-level guards, run with the
other ten gates: the middleware is on the office group, no personal route takes
a user id, ownership is checked before serving a file, a draft is invisible, a
verified paper cannot be removed by its uploader, the two role lists agree, the
stylesheet has a dark value for every token, one salary door, one payslip
document read by three surfaces, totals summed from their own lines, a slip
sheet that loads its own stylesheet, and a draft that cannot be printed.

Two arithmetic facts are asserted by tests that **run**, not by reading the
source — `php artisan test --filter=EmployeePayTest` (which way the money went)
and `php artisan test --filter=EmployeePayslipTest` (a slip's totals are its own
lines, the money in words, the page and the paper are one document). Neither
needs a database.

And `python3 /tmp/2a/mutate.py` proves the guards bite: each hand mutation under
`/tmp/2a/hand.json` breaks exactly one rule and is caught by the guard that
names it.

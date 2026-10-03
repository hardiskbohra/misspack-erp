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
| Menu | the full sidebar | five items: Dashboard, My Salary, My Payslips, My Documents, My Profile |
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
         /salary      GET
         /payslips    GET           — and /payslips/{payslip}/file
         /documents   GET, POST     — and /documents/{document}, /{document}/file
```

Two properties of that list are load-bearing:

1. **no personal route takes a user id.** The person is the session. There is no
   number to change, so there is no record to reach for.
2. The two routes that *do* take a row id — a payslip file and a document file —
   ask `App\Services\EmployeeAccess::canView($actor, $owner)` before a byte is
   served, and the office side re-checks that the row belongs to the person in
   its own URL (`abort_unless($document->user_id === $user->id, 404)`). A wrong
   id is a 404, never somebody else's salary.

An employee who follows an old bookmark into the office's half is **redirected
to their own workspace with a sentence**, not shown a 403 — the person holding
an employee login is staff, not an attacker.

## 3. Managing one person's profile

`Users → the folder icon` — `/users/{user}` — is the record: identity, contact,
employment, bank, plus payslips, pay history and the document file. `Users` list
has role chips (Everyone / Office / Employees) with counts.

Ownership of every field is declared **once**, in `EmployeeAccess`:

| Only the employee may edit | Only the office may edit |
| --- | --- |
| mobile, address, date of birth, emergency contact name and number | name, email, role, department, designation, employee code, joining date, employment type and status, PAN, bank name / account name / number / IFSC |

The employee's profile page is built from `ownEditableFields()`, so the form and
the rule cannot drift: an employee who posts `bank_account_number` is ignored by
the controller, which validates only the fields in that list.

## 4. Pay, payslips and papers

**Salary** is not a second ledger. An employee's salary is the cashflow entry
**credited** to them (`employee_id = them`, `transaction_type = credit`), read
throug `EmployeeProfile::salaryQuery()`. A debit filed against somebody is a
recovery, not pay, and counting the two together would overstate what they
earned. Both the office's record page and the employee's own pages are built
from that one query — the figures cannot disagree.

**Payslips** (`employee_payslips`) are one row per person per month:

- the **figures are the record**; the PDF is optional, because an office that
  pays by bank transfer often has none;
- `draft` is the office's working copy and is **invisible to the employee**
  (`issuedPayslips()`); `issued` is what they see;
- when a salary credit exists for that month, the slip links to it
  (`cashflow_entry_id`), so the slip and the ledger can be read against each
  other instead of argued about.

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

`node tools/checks/employees-check.cjs` — 38 source-level guards, run with the
other ten gates: the middleware is on the office group, no personal route takes
a user id, ownership is checked before serving a file, a draft is invisible, a
verified paper cannot be removed by its uploader, the two role lists agree, the
stylesheet has a dark value for every token. And `python3 /tmp/2a/mutate.py`
proves those guards bite: mutations `M57`–`M69` break exactly these rules and
are each caught.

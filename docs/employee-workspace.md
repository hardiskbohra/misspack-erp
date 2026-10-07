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
| Menu | the full sidebar | five items: Dashboard, My Salary, My Documents, My Profile, Notes |
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
    ├─ Route::prefix('my')           ← the employee's own record
    │    /            dashboard
    │    /profile     GET, PUT      — and POST /password
    │    /salary      GET           — the ledger months *and* the payslips
    │    /payslips    GET           — a redirect to /salary?focus=payslips#payslips
    │    /payslips/{payslip}/file | /pdf
    │    /documents   GET, POST     — and /documents/{document}, /{document}/file
    │
    └─ Route::prefix('notes')        ← neither half: whoever is signed in
         /                 GET, POST — the desk, and the composer
         /{note}/edit      GET
         /{note}           PUT, DELETE
         /{note}/pin       PATCH
         /{note}/archive   PATCH      — two directions, from the note's own state
```

Notes is the one screen that belongs to the **login** rather than to a role. It
sits outside `office` because an employee keeps notes too, and outside `/my`
because `/my` is one person's record — the same routes serve both menus. Its own
rule is in `docs/notes-module.md`: a note is read through its owner scope and
nowhere else, so the employee's menu can name it without naming an office screen.

Three properties of that list are load-bearing:

1. **no personal route takes a user id.** The person is the session. There is no
   number to change, so there is no record to reach for.
2. The routes that *do* take a row id — a payslip's file or document, and a
   document's file — ask `App\Services\EmployeeAccess::canView($actor, $owner)`
   before a byte is served, and the office side re-checks that the row belongs to the person in
   its own URL (`abort_unless($document->user_id === $user->id, 404)`). A wrong
   id is a 404, never somebody else's salary.
3. The notes routes are the same rule for the one screen both halves share: the
   id in `/notes/{note}` is resolved inside the caller's own notes
   (`NoteController::mine()`), so the row does not exist unless it is theirs.

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

### One table, and the forms in dialogs

The salary tab is **one table**: `EmployeeProfile::payRows()` walks the ledger
entries and the slips together, so a row carries the month, the money that moved
and the payslip of that month. Two tables listing the same months — the entries
in one card, the slips in the next — meant the reader matched them by eye, and a
payslip is not a second answer to "what did September cost", it is the same
month written on paper.

The merge rules, all of them in that one method:

- **one row per salary entry**, newest first;
- a month that paid twice draws its slip **once**, on its newest row, and the row
  below it links up to it instead of saying "no payslip";
- a slip whose month has **no entry** behind it still gets a row, marked *No
  ledger entry*: hiding a document because its month is missing from the ledger
  is how a payslip goes missing;
- a row is **generatable** only where money actually went out — a recovery is not
  a month to print a slip for.

`resources/views/employees/partials/pay-table.blade.php` draws it on both sides
with `context="office"|"employee"`: the office sees the issue state, the
corrections and the share buttons; the employee sees the month, the figures, the
slip number and Print. Everything else is the same table.

> A view array is evaluated **top to bottom**: `return view('x', [...])` reads
> each value as the array is built, so a variable first assigned *inside* that
> array is an undefined variable at the line that reads it. The salary tab died
> exactly there — `'payRows' => $profile->payRows($year, $salaryEntries,
> $payslips)` sat above the `'salaryEntries' => …` that created it — which is why
> `UserController::show()` reads the year's entries **before** the array, and why
> `employees-check` sweeps every controller's view arrays for the same shape.

**The two forms are dialogs** (`employees/partials/payslip-form.blade.php`,
included once per mode). Recording a month and correcting one are the same
fourteen fields, so they are written once; they open from the table — *Record a
month* in the toolbar, **Generate payslip** on a row (which arrives with the
month, the amount the ledger moved and the day it moved on already filled),
**Modify** on a slip's row — with the month or the slip in the URL, so the server
renders the dialog already filled and already open. A validation failure reopens
the same dialog with what was typed: `UserController::payslipDialog()` reads the
error bag, and a form that loses the office's work is worse than no dialog.

Both are the shared `.master-modal > .master-modal-card > .master-modal-body`
sheet, so the scroll, the Escape key, the backdrop and the body lock come from
the one implementation (task 42's lesson). The dialog totals the lines as they
are typed, in `misspackFormat` — the browser half of `CommonHelper`, so the
figure in the dialog and the figure on the printed slip cannot be grouped
differently.

> Two shared classes the dialogs had been naming since they were written were
> defined by no sheet at all: `.master-form-grid` existed only inside
> `price-calculator.css`, page-scoped, so `.master-field.full` and `.two` were
> no-ops and every dialog — users, profile, payslip — was a single column of
> fields. It is now defined once in `master-form.css` (two columns, one on a
> phone); `.master-section-label` and `.master-info-box` are defined beside it,
> and the record page loads `users.css` with `employees.css`, which is where its
> own cells live.

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

## 6. The record's shape: facts to read, one form to write

The details tab shows the same twenty-one fields twice — once as the record you
read, once as the form you edit — so both halves are now dense on purpose.

**Read: the facts grid.** `.master-detail-list` was named by three record pages
and defined by no sheet, so every field fell back to `.master-info`, the bordered
box, and each one took a full-width row: nine fields of "The record, in short"
were nine boxes, and the details tab was a wall of them. The lists now use
`.master-facts` — the shared label-over-value grid the shipment, project and
client records already use: two columns, a hairline between rows, no box per
field, because a record is not a form.

One trap lives in that change. `.master-card--flat .master-info` re-surfaces
every fact as a panel row, and it is the same specificity as `.master-facts >
.master-info` in a later file, so the facts rule is written twice, with the card
in front of it. Without that, a flat card wins the tie and the boxes come back.

**Write: one form, five groups, three to a row.** Twenty-one fields in two
columns is a thirteen-row scroll with no landmarks; they are now five labelled
groups — Identity, Employment, Bank, Contact, Access — each a
`master-form-grid.is-three`: three columns at 1200px and up (a third column
below that is narrower than its own label), two above 768, one on a phone. The
save button rides the foot of the viewport while the card is in view
(`.master-actions.is-sticky`), because the office types in row two and then
hunts for the action at row nine.

The field list is held by a guard, not by review: `employees-check` asserts the
form still posts all twenty-one names, so a regrouping cannot quietly drop one.

## 7. The two dialogs, in detail

Add user and Edit user are one form twice, and their two bottom blocks are the
same two questions: **a photo** and **a password**.

**The photo band.** A circle, a sentence and the controls, on one line: the
circle is the avatar the list and the record already use (photo when there is
one, initials when there is not, an icon when neither), the sentence says what
is accepted, and the picker is a label styled as a button because a file input
cannot be themed (task 22). The band was three stacked lines until now: both
dialogs named `master-avatar-row`, `-preview`, `-info`, `-actions` and
`master-file-name`, and **no sheet defined any of them** — the same fault as
`.master-form-grid` and `.master-detail-list`, found in the same sweep.

**The password fields.** Each field's eye is absolutely positioned, so it needs
`master-password-wrap` to be `position: relative`; that class was named and not
defined either, and the eye resolved against the *dialog card* — one floated
over the form in a corner while the other fields had no toggle at all. The
wrapper is now defined, the field is padded so a long password never runs under
the icon, and the edit dialog shows the rule's error the way the add dialog
does (it reopened with no reason given before).

Both blocks now read the same in both dialogs — the same heading words, the
same 2 MB sentence, an error under the field in both — and the edit dialog's
link to the person's page moved from the end of the form to the footer line,
left of Cancel and Save, where a way out of a dialog belongs.

## 8. The band is the control, and a failure comes back to its dialog

**The photo band is a control, not a caption.** A file input cannot be themed and
cannot be dropped on, so the band around it does the work:

- the **circle is a second label** for the same input, with a camera badge on its
  corner, so clicking the photo opens the picker — and the input is *clipped*
  rather than `hidden`, because `hidden` takes it out of the tab order and would
  leave the dialog with no keyboard route to the picker at all;
- the row takes a **dropped photo**: the file is put into the input through a
  `DataTransfer`, so what is shown and what is submitted are the same file. If
  the browser refuses, the band says so instead of drawing a preview of something
  that would never upload;
- a photo that will not be taken **says why** in the slot the file name would
  have used — the four types and the 2 MB limit are the server's own rule
  (`mimes:jpg,jpeg,png,gif,webp`, `max:2048`), and `employees-check` holds the
  two copies equal, so changing one without the other fails the gate;
- the button's meaning comes from the row: `data-avatar-remove="clear"` forgets a
  file that was just chosen (the add dialog), `"delete"` files the photo on
  record for removal (the edit dialog, which posts `remove_avatar`).

**A failed save reopens the dialog it came from.** It did not. `update()` threw
the validation failure into `back()` — the list — and the script ran
`openAddModal()`: the office corrected a person, submitted, and got a *New user*
form wearing their errors, with the edit fields empty (they were only ever filled
by the fetch). Now:

- `UserController::update()` catches the failure and redirects to
  `users.index?edit={id}` with the input kept;
- the page reads that id (`$reopenUserId`) and says which dialog to open
  (`data-open-dialog="edit|add"`), and the script opens **that** one — for the
  edit dialog without refetching, because the fields already hold `old()`;
- every edit field carries `old()`, and the form's action is server-rendered when
  the page came back with errors, so the reopened dialog posts to the person it
  was editing.

## 9. The user menu, and the account page behind it

The shell shows the person twice — a chip in the top bar and a card at the foot
of the sidebar — and both were **labels**: for an employee the avatar was a link
to `/my/profile`, and for the office account it was a `<div>` carrying a comment
explaining that the office has no page, "so it keeps the same look without
pretending to be a link". A menu that does nothing is not a menu, and the person
most likely to want one is the office.

One partial (`layouts/partials/user-menu.blade.php`) renders both surfaces, and
both open the **shared** `.master-dropdown` panel — the one `app-layout.js`
portals to `<body>`, measures against the trigger, flips upwards when the sidebar
has no room below, and closes on Escape or an outside click. Its styles are
written for the portaled panel (`.master-dropdown-menu.user-menu-panel`, never
`.user-menu .user-menu-panel`, which stops matching once it has moved).

What the menu offers, for both roles:

| Item | Where it goes |
| --- | --- |
| **My account** | `/account` — this page |
| **My record** | Employee: their workspace. Office: their own record in the users module. A role ask, not a preference — an employee following the office's link is turned around at the door |
| **Salary & payslips**, **My documents** | Employee only, inside the employee branch and nowhere else |
| **Change password** | `/account#password` |
| **Switch to dark / light** | The same route as the top bar's button — which is hidden on a phone, so this is the only switch there |
| **Sign out** | The card's separate logout icon is gone: one control per action |

**`/account`** is the page behind "My account". It sits **outside** the `office`
middleware group, because both roles have a name, an address, a mobile number and
a password. It offers the five fields that belong to the person — read from
`EmployeeAccess::ownEditableFields()`, validated with
`EmployeeAccess::ownFieldRules()` and then filtered through the same list, so a
hand-made request cannot put a designation or a bank account through the door
that was opened for an address — plus the password form, the theme switch, and
doors to the rest.

The two pages that edit those fields (this one and the employee's own profile)
render **one form each** (`users/partials/own-details.blade.php`,
`own-password.blade.php`) and both controllers filter through one service list.
Two copies of a field list is how two pages start asking different questions.

### The shape of `/account`

The page is a form and an aside, and the aside is two cards. The details card is
the tallest thing on it — five fields, one of them a textarea — so a third card
beside it left that column ending some three hundred pixels above the bottom of
the grid. The switch card moved into the tall column, where it is a **row**: a
button under its own sentence is a card-shaped hole, and beside it the card is as
tall as the button it holds.

One thing was missing at the shell's level, and it belongs there: a `.master-grid`
that directly follows a card had **no margin at all** — a grid owns the gutter
*between* its columns and never the space above itself. The identity strip
therefore shared an edge with the columns under it, and the employee's record
page did the same behind its "still to be filled in" note. `master-list.css` now
names `.master-card + .master-grid` in the same one rule that spaces every other
pair of blocks (the margins collapse with a card's own `margin-bottom`, so a block
that already carries one is not spaced twice) — and the rhythm guard asks that
rule for its *body* instead of for the selector that used to end it: a guard built
on "this selector is followed by a brace" fails the moment the list gains a
member, which is the day the sheet got more correct.

**And one bug this uncovered.** `theme.toggle` sat inside the `office` group
while its own comment said "for anybody signed in — an employee has a theme too".
An employee pressing the top bar's **Dark** button was turned around at the door
with an error, and the switch was a dead control on every page of their
workspace. It is registered with the account routes now, and a guard holds it
there.

## 10. What is verified where

`node tools/checks/employees-check.cjs` — the module's own guards, run with the
other ten gates: the middleware is on the office group, no personal route takes
a user id, ownership is checked before serving a file, a draft is invisible, a
verified paper cannot be removed by its uploader, the two role lists agree, the
stylesheet has a dark value for every token, one salary door, one payslip
document read by three surfaces, totals summed from their own lines, a slip
sheet that loads its own stylesheet, a draft that cannot be printed, and the
account page's own shape — two columns that end together, the theme switch beside
its sentence, the page's blocks spaced by the shell and not by the page.

Two arithmetic facts are asserted by tests that **run**, not by reading the
source — `php artisan test --filter=EmployeePayTest` (which way the money went)
and `php artisan test --filter=EmployeePayslipTest` (a slip's totals are its own
lines, the money in words, the page and the paper are one document). Neither
needs a database.

And `python3 .dev-rig/mutate.py` proves the guards bite: each hand mutation under
`.dev-rig/hand.json` (62 hand mutations) breaks exactly one rule and is caught by the guard that
names it.

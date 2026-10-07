# Recurring cashflow — the standing payments, and the office's yes

Salary, rent, the internet bill, a monthly supplier, a quarterly advance: money
this office pays (or receives) again and again, on a rhythm, whether or not
anybody is looking at a ledger at the time.

The module exists for that last clause. A payment that happens every month is
the payment nobody double-checks, and the failure mode is not a crash — it is a
salary paid twice, or not paid, or paid from the wrong account, discovered on a
bank statement three weeks later.

**The whole design is one sentence:** *a rule is not a payment.* A rule is a
standing instruction, written as a draft, argued about, approved once — and from
then on it **asks**, one date at a time, for the money to actually move.

---

## 1. The ask, on the effective date

The office's words for this feature were *"build the recurring creation system in
draft state, and ask the approval on the effective date"*, and the reading here
is deliberately literal:

- **a rule is born a draft.** A draft posts nothing, plans nothing and notifies
  nobody. It is a sentence waiting to be agreed with;
- **a rule is approved once**, from an ask: somebody sends it for approval, the
  office answers. The ask is not theatre — it puts the rule in a queue, and it
  means every active rule has an answer to "who wanted this?";
- **every date then asks for itself, on the day it is due.** The plan writes the
  dates down when the rule is approved; on the morning of an effective date the
  office is notified that *this* payment needs an answer, and only then can it be
  approved.

There is exactly one consequence worth stating out loud, because it is the rule
this module is built around:

> **A payment is approved on or after its effective date.**

Approving early is not a convenience — it is a payment approved in a different
world from the one it lands in. The whole point of the notify-on-the-day design
is that the answer belongs to the day the money is due. The module refuses and
says so in a sentence.

**Skipping is the mirror image, and it is allowed at any time.** A skip moves no
money; it is a decision about the plan ("this month's salary already went out by
cheque"), and knowing that a date is not going to be paid *in advance* is
useful, not dangerous. The rule keeps its anchor: skipping the 5th of November
does not move December's payment to the 6th.

---

## 2. Who decides

The application has two roles — the office and the employee — and this module
lives in the office's half of the routes. So approval is *any office login*,
and the record says precisely who did what:

| fact | where it lives |
| --- | --- |
| who wrote the rule | `created_by` |
| who asked for approval | `requested_at`, `requested_by` |
| who answered, and what they said | `decided_at`, `decided_by`, `decision_note` |
| who posted one occurrence | `cashflow_recurrence_occurrences.decided_by` |

Maker and checker are the same *role* here, and the module does not pretend
otherwise: when one person writes, asks and approves, the record reads that way
in three columns rather than hiding it behind a status. What it never does is
approve a rule nobody asked about.

---

## 3. The calendar

`app/Services/RecurrenceSchedule.php` is the **only** place a date is computed.

Three things read it — the planner, the draft's preview, and the test — and they
agree by construction: there is one `step()`, and everything else is that step
repeated. (If the preview were computed in the browser — the obvious place for
"show me the next few dates while I type" — the office would be shown one
calendar and receive another, and nothing on the server would ever fail.)

The rhythms are the six the office asked for, plus the two ways the window
closes:

| Frequency | The dates it makes |
| --- | --- |
| Daily | every day |
| Weekly | every week, on the same weekday |
| Monthly | every month, on the anchor day |
| Quarterly | every three months |
| Every six months | every six months |
| Yearly | every year, on the anchor day |

**The anchor is the effective date's day, and it survives the months that do not
have one.** A rule starting 31 January pays 28 February (29 in a leap year),
then **31 March, 30 April, 31 May** — the step moves to the *first* of the target
month and sets the anchor day into it, so it never reads a clamped date back.
Stepping "one month" from 28 February is the classic way a rent rule slides to
the 28th of every month for ever; this arithmetic cannot do it. A yearly rule on
29 February keeps the 29th in every leap year. `tests/Unit/RecurrenceScheduleTest.php`
holds exactly those cases, because the failure is money and not an exception.

The window closes by **a number of occurrences**, by **a date**, by both, or by
neither — "until we say stop" is a real answer. Whichever comes first wins.

---

## 4. The plan is a window, not the whole promise

"Every day until 2028" is a thousand rows nobody will read. So when a rule is
approved, the **plan** — one row per date — holds `PLAN_WINDOW` (24) undecided
dates and is topped up as the office works through them:

- a daily rule and a yearly rule cost the same, and the tail of a very long rule
  is not a table that grows for ever;
- the page says so in words, so the reader is never looking at a truncated plan
  and wondering where the rest went;
- **re-planning is a reconciliation, never a rebuild.** The rows that are still
  undecided keep their dates, their ids and their notifications; the ones the
  new plan no longer contains are *withdrawn* (cancelled, never deleted); the
  dates the plan adds are appended. Sequences continue from the highest ever
  used, so a re-planned row can never collide with its own history.

Waking up is the same arithmetic: a rule paused for three months and resumed owes
nobody three months of back-dated asks, so its ladder restarts on **the rule's own
anchor day on or after today**. A rule approved long after its effective date
starts the same way — the effective date is where a plan *begins*, not a debt of
missed payments.

---

## 5. One writer per fact

The module is small, and most of its size is this table:

| fact | the one writer | everyone else |
| --- | --- | --- |
| a rule's fields and state | `RecurrenceIntake` | the controller validates and hands over facts |
| the dates, and the decisions about them | `RecurrencePlan` | the pages read them |
| the ledger entry an approval posts | `RecurrencePosting` | nothing else creates an entry for a rule |
| a balance column | `CashflowLedger` (already existed) | the posting calls it |
| the office alert | `OfficeBriefing` (already existed) | the plan asks it to raise one |
| the ask was made | `RecurrencePlan::notifyDue()` — `notified_at` | the notifier cannot claim it |

That shape is what makes the two loud claims true:

- **one entry per occurrence** — `RecurrencePosting::post()` returns the entry a
  row already has instead of writing a second one, and the decision is written in
  the same transaction as the entry, so a double-click, a retried request or a
  re-run of the morning sweep cannot pay a salary twice;
- **one notification per date** — `notified_at` is the plan's fact and the alert's
  fingerprint is the occurrence's id, so "the office was told once" is true even
  if two doors run the sweep.

And two things are deliberately **absent**: there is no counter of released
occurrences, no stored next date, and no amount kept on an occurrence. The
occurrences are the log; a count beside a log is a second answer that drifts, and
the copy that drifts is always the one nobody is looking at. `isDue()`,
`isOverdue()` and "3 days late" are readings of two dates and today — a stored
`overdue` is wrong by tomorrow morning.

---

## 6. The notification

The office asked to *"notify at the time of approval required"*, which is a push,
not a page somebody remembers to open. So:

- **`cashflows:recurring`** runs at 08:45 — before the 09:15 office briefing, so
  the day's approvals are already waiting in the list it sends. It tops every
  running rule's plan up, raises the asks that have reached their effective date,
  and ends the rules whose last date has been decided;
- **the module's own page runs the same sweep.** A cron is the one thing an
  office cannot rely on having, and the sweep is idempotent — `notified_at` is
  its marker — so the page shows the truth whether it ran or not;
- **the alert goes through the office briefing system**: one `OfficeAlert` per
  occurrence, requiring acknowledgement, on the accounts desk, **emailed** to
  whoever watches that desk, pointing straight at the rule;
- **it can be switched off**, like every other briefing source, in
  *Briefings → sources* (`recurring_due`). And if it is switched off, the module
  does **not** stamp the date as told — the ask would otherwise be swallowed for
  ever. Switched back on, the next sweep speaks.

Severity is `attention`, not `critical`: money falling due is the system working,
and a desk that treats every standing payment as an emergency learns to ignore
the colour that means emergency.

---

## 7. The two screens

`cashflows/recurring/index.blade.php` — the desk:

1. **four figures** cut from one query — how many rules are running, what is
   waiting for an answer, which dates are asking today, and what the next thirty
   days hold;
2. **Waiting on your approval** — every date that has reached its effective date,
   with **Approve** and **Skip this date** right there. A queue you cannot answer
   from is a queue you have to go and find. It obeys the filters below it, like
   everything else on the page;
3. **search, chips and the shared drawer** — the chips carry the rule's state
   (including the two piles that matter: *Waiting for approval* and *Drafts*);
   the drawer carries the rhythm, the plan's window and the order;
4. **the rules** — five columns: the rule (with its ledger line and party), the
   amount, the rhythm, where its plan has got to, and the state; every action in
   the shared row menu, and the whole row opens the rule.

`cashflows/recurring/show.blade.php` — one rule: the record head with the next
date, four figures, the **decision panel** (who wrote it, who asked, who
answered, and what they said), the **plan** — every date, its state, its decider
and a link to the entry it posted — and the rule's facts.

A **draft** shows its plan as a *preview*: the same table, the same calculator,
marked "not written yet", so approving a draft does not change the shape of the
page and nobody approves a calendar they have not seen.

The rule form is one partial under both screens (`partials/rule-form.blade.php`),
opened as a dialog from the list and from the record. A rejected save reopens the
dialog it came from with the typing kept (`_dialog` + `old()`), exactly as the
notes composer does.

---

## 8. What was deliberately not built

- **No editing of an approved rule.** Approving fixes the recipe — the plan is
  built from it, and the entries it posts are its consequences. Changing a live
  rule means ending it and writing the next one, which keeps every posted entry's
  story intact: you approved *this* rule, not one that has since changed.
- **No "approve all".** Every date is its own decision, which is the entire point
  of the ask. A bulk approve is a button that turns a loop back into an
  auto-payment.
- **No auto-posting on the effective date**, however tempting: the office asked
  for an approval, not for a robot.
- **No un-approving.** A decision is a fact; a posted entry is corrected in the
  ledger, where corrections already happen.
- **No notifications settings of its own** — the briefing source list is the one
  place the office switches notifications off, and two switchboards is one too
  many.

---

## 9. Verification

`tools/checks/recurring-check.cjs` — **77 checks**, dependency-free, run with
`node tools/checks/recurring-check.cjs`. It reads source with comments stripped,
so the prose in these files can explain a rule without answering a check. What it
holds:

- **the calendar** — one calculator, the plan and the preview both read it, no
  view or script does date arithmetic, the clamp is `startOfMonth()` + `min(day,
  daysInMonth)`, the anchor is carried rather than read back, every rhythm in the
  vocabulary is a case in the step, and an unknown rhythm throws instead of
  defaulting;
- **the state machine** — draft is the column's default, the intake is the only
  thing that saves a rule, the controller hands over every state change, approval
  requires the ask, approving writes the plan in the same call, only a draft is
  editable, pausing and ending withdraw the tail, and a rule that decided
  something is ended rather than deleted;
- **the plan** — one writer, a window rather than the whole promise, sequences
  that continue instead of being reused, withdrawals that cancel rather than
  delete, no counter, no stored next date, no amount copied onto a date, and
  "due"/"overdue"/"late" computed from today;
- **the ask** — approval on or after the effective date, skipping allowed any
  time, one notifier with an occurrence fingerprint, `notified_at` stamped only
  when the alert exists, the source toggle present in both `OfficeSetting` lists,
  one sweep implementation behind two doors, scheduled before the briefings;
- **the posting** — one entry per occurrence, written by the module's own mirror,
  balance through `CashflowLedger`, an account required, the narration naming the
  rule, and the entry page able to point back at it;
- **the page** — the shared master-list and drawer, a criterion asked once, chips
  carried through a search, a form for every write, the shared row menu, the
  whole row opening the rule, the day's approvals answerable from the list, both
  empty states with a way out, the labelled mobile cards, the guideline inset on
  this module's own cards (and *not* on the two table cards), the module tables
  dropping the ledger's 1280px floor, money right-aligned and tabular, the shared
  `core-badge` with tones that exist in `core.css`, no class borrowed from
  another module, one form partial under both dialogs, the shared modal
  vocabulary, and the `_dialog` reopen with `old()` kept;
- **the reading** — one row of sums per figure set, the plan-side figures cut
  from the same rules the page shows, chip counts with the chip lifted, filters
  through the model's scopes, every order choice a real scope, and every
  hand-typed filter falling back to a default;
- **the shell and the paperwork** — the routes declared before the ledger's
  resource route, every door registered, an occurrence reached inside its rule, a
  sidebar item the ledger item steps aside for, a door from the ledger, this
  document, the roadmap's 1E marked shipped, the check in `tools/checks/README.md`,
  the drawer page on the shared-drawer contract list, and the calendar held by
  `tests/Unit/RecurrenceScheduleTest.php`.

`tests/Unit/RecurrenceScheduleTest.php` is the half a source check cannot read:
it asks the calculator itself about the 31st of January, 29 February, a window
closed by a count, by a date, by both and by neither, and the day after a pause.
(No `php` runtime is available in every environment this is developed in, so it
is parsed by `tools/checks/php-check.cjs` and run wherever a test runner exists.)

Also touched by this module: `app/Services/OfficeBriefing.php` (the module's one
notifier), `app/Models/OfficeSetting.php` (the `recurring_due` source),
`app/Services/CashflowPickers.php` (new — the lists both cashflow forms are
filled from, extracted so the entry form and the rule form cannot offer different
accounts), `routes/console.php` (the 08:45 sweep), `resources/views/layouts/app.blade.php`
(the sidebar item), `resources/views/cashflows/index.blade.php` (the door),
`resources/views/cashflows/show.blade.php` and `app/Models/CashflowEntry.php`
(an entry that came from a rule says so), and `tools/checks/ui-components-check.cjs`.

---

## 10. Where it lives

```
app/Console/Commands/ReleaseRecurringCashflows.php
app/Http/Controllers/CashflowRecurrenceController.php
app/Models/CashflowRecurrenceOccurrence.php
app/Models/CashflowRecurrenceRule.php
app/Services/CashflowPickers.php
app/Services/RecurrenceFigures.php
app/Services/RecurrenceFilters.php
app/Services/RecurrenceIntake.php
app/Services/RecurrencePlan.php
app/Services/RecurrencePosting.php
app/Services/RecurrenceSchedule.php
app/Services/RecurrenceVocabulary.php
database/migrations/2026_10_07_130000_create_cashflow_recurrence_rules_table.php
database/migrations/2026_10_07_130100_create_cashflow_recurrence_occurrences_table.php
docs/recurring-cashflow.md
public/assets/css/cashflow-recurring.css
public/assets/js/cashflow-recurring.js
resources/views/cashflows/recurring/index.blade.php
resources/views/cashflows/recurring/show.blade.php
resources/views/cashflows/recurring/partials/rule-form.blade.php
tests/Unit/RecurrenceScheduleTest.php
tools/checks/recurring-check.cjs
```

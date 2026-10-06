# Feedback module — the last mile of a project

**Status: built.** All four parts below shipped together — the ask, the surface,
the loop and the mid-project pulse. The decisions in §11 are locked and the
paragraphs that follow describe what the code does, in the present tense. The
module's guard rails are `tools/checks/feedback-check.cjs` (47 checks) and its
rules are held by `tests/Unit/FeedbackRulesTest.php`.

It follows the house rule that a module is a *vocabulary plus one writer per
fact*, and it borrows its link model from [statement shares](cashflow-ux-roadmap.md)
rather than inventing a second one.

Read this with `app/Models/PartyStatementShare.php`, `app/Http/Controllers/PartyStatementController.php` and
`tools/checks/statement-check.cjs` open — three quarters of the machinery below is
those three files in a different suit.

---

## 1. The honest case for building it

A feedback form collects stars. Stars in a table change nothing, and a Google Form
is cheaper. The module is worth building only if it does three jobs a form cannot:

| Job | What it changes | Why a form can't |
| --- | --- | --- |
| **Catch the client who is about to leave quietly** | A detractor is called by a named person inside 48 hours, while the relationship is still worth saving | Nobody reads a spreadsheet on the day it matters |
| **Harvest proof that sells** | A consented testimonial, attributed and quotable, ready for the website, a quote or a deck | Consent, attribution and permission-to-publish are module state, not a paragraph in an email |
| **Turn recurring complaints into a fix list** | Complaints become owned actions with a resolution, and a resolved action can be shown back to the client | A form has no owner, no status, and no memory |

The rule that falls out of this: **the score is not the deliverable — the follow-up
is.** If we cannot say who owns a 2-star response and what happened next, we should
not build this at all.

A second, subtler job is worth naming because it decides the timing:

- **The close-out ask** ("how did the project go?") is for the record, the
  testimonial and the account's health. It arrives after the money is settled and
  the pressure is off — which is exactly when people answer honestly.
- **The mid-project pulse** (at PPS or dispatch-ready) is where money is actually
  saved: a print or fitment problem caught before the full run is worth more than
  every post-mortem after it. Same table, different `kind`. Phase 3 — see §9.

## 2. What already exists to build on

Do not build a second version of anything in this list.

| Need | Existing machinery |
| --- | --- |
| A link that *is* the authentication | `PartyStatementShare` — long token, `expires_at`, `revoked_at`, view counting, `state()`/`stateLabel()`, `whatsappUrl()`, `mailUrl()` |
| A public page with no app chrome | `resources/views/statements/public.blade.php`, `statements/expired.blade.php`, `public/assets/css/statement.css` + `document-print.css` |
| A project screen the client already reads | the login portal's project record (`client_portal/projects/show.blade.php`) — the feedback ask is issued from the office's copy of that record, and the portal is where the client meets it |
| A logged-in client | the `client.portal` middleware, `ClientPortalProjectController`, `ClientPortalNotifier` |
| An audit trail the client can see | `ProjectLog` (`actor_type = client`, `is_public`) and the `logs` tab on the project |
| Work with an owner | the `tasks` module, `assigned_to` on `Project` |
| Modules house style | model — service — controller — screen — **check**; `CommonHelper` for every figure; `core-*` CSS in `resources/css/pages/` |

## 3. Two doors, one answer

The response is the same row whichever way it arrives, so the office cannot have a
"portal NPS" and a "link NPS" that disagree.

1. **The link** — `/feedback/{token}`, named `feedback.public.show`. Outside auth.
   The token is the whole of the authentication: 48 characters, expiry by default,
   revocable, every open counted. It shows one project, one client, nothing else.
2. **The portal** — `/client-portal/feedback`, inside `client.portal`. A completed
   project in the portal grows a *Rate this project* action; the notification is
   written by `ClientPortalNotifier` so it lands in the bell the client already has.

A portal response is filed against the same `feedback_requests` row as the link — the
office issues the request once, and the client may open it either way.

## 4. What the client sees

Five short steps, one page, no login, works with JavaScript off:

1. **Who is answering** — name (required), designation and email (optional),
   prefilled from the project's client contacts. *"Who should we call about this?"*
2. **The scorecard** — six dimensions, 1–5 each, plus one 0–10 question: *"how likely
   are you to recommend MissPack?"* (the NPS question, asked verbatim — changing its
   wording changes the number and the benchmark with it).
3. **The verbatims** — *what went well* and *what could be better*. One of the two is
   required, because a score with no sentence is not actionable.
4. **The branch, which is where the module earns its keep:**
   - any dimension ≤ 2, overall ≤ 2, or NPS ≤ 6 → *"What went wrong, and may we
     call you?"* + optional photo upload (a picture of the defect is the whole
     complaint, often) → raises a **follow-up action** and notifies the project owner.
   - overall ≥ 4 and NPS ≥ 9 → *"May we quote you?"* — separate consent per use
     (website / social / sales deck / case study), plus an optional Google-review link.
5. **Thank you** — states what happens next in one sentence and names the person who
   will read it. No confetti.

**Attribution is a real choice, so it is asked, not assumed.** The default is a named
response — the loop needs someone to ring. But the form offers *"keep my name out of
marketing"*, recorded as `attribution_consent`, and the office can mark any response
`is_anonymous` internally when the client asks for it after the fact. Anonymous
responses still count in the score; they simply cannot be quoted.

## 5. The data model

Five tables, one of them a vocabulary.

### `feedback_requests` — the ask, and the link
Mirrors `party_statement_shares` deliberately: `token`, `project_id`, `client_id`,
`kind` (`close_out` | `pulse`), `issued_by`, `expires_at`, `revoked_at`, `shared_via`,
`views`, `first_viewed_at`, `last_viewed_at`, `last_viewed_ip`, `reminder_count`,
`last_reminded_at`, `note`. Unique index on `token`. `FeedbackRequest::issue()` owns
token generation and the default 45-day expiry — one place answers *what makes a
token unique*, and it retries rather than 500s on a birthday collision.

### `feedback_responses` — one answer, immutable
`feedback_request_id` (**unique** — one answer per ask), `project_id`, `client_id`,
`respondent_name`, `respondent_email`, `respondent_designation`, `overall_rating`,
`nps_score`, `would_order_again`, `went_well`, `could_improve`, `testimonial`,
`attribution_consent`, `publish_website`, `publish_social`, `publish_sales`,
`publish_case_study`, `is_anonymous`, `submitted_at`, `ip`, `user_agent`,
`source` (`link` | `portal`).

**A submitted response is never edited.** The office may add an internal note and may
change consent flags at the client's request; the scores are write-once. If a client
wants to revise an answer, the office revokes and reissues — and the old row stays,
because a number that can be quietly edited afterwards is not evidence of anything.

### `feedback_answers` — the scorecard, as rows
`feedback_response_id`, `dimension` (key), `score` (1–5), `comment`. One row per
dimension rather than six columns: a new dimension is a vocabulary entry, not a
migration, and *"we lost on delivery this quarter but won on print"* is a `GROUP BY`
instead of a hand-built report.

### `feedback_actions` — the loop
`feedback_response_id`, `type` (`fix` | `acknowledge` | `referral` | `win_back`),
`severity`, `owner_id`, `due_on`, `status` (`open` | `in_progress` | `resolved` |
`dismissed`), `task_id` (nullable link to `tasks`), `resolution_note`, `resolved_at`,
`client_notified_at`. This is the table that makes §1 true, and the one a check should
guard hardest.

### `feedback_master_options` — the vocabulary
Same shape as `lead_master_options`: `group`, `key`, `label`, `sort_order`, `is_active`.
Groups: `dimension` and `action_type`. `App\Services\FeedbackVocabulary` is the one
place the dimensions and their labels are defined — the controller, the form, the
report and the check all read it, so a renamed dimension cannot mean two things.
(Built-in fallback list, so the table being empty is never a broken page.)

## 6. The office side

One surface, in the *Management* section of the sidebar as **Feedback**:

- **The figures** — responses, response rate, average score, NPS, and the promoter /
  passive / detractor split. NPS is computed as `%promoters − %detractors` and is
  shown **with its denominator** ("NPS 62 · 13 of 21 asked"). A one-decimal NPS from
  four answers is a lie with a decimal point in it, and the screen should not tell it.
- **Needs attention** — every detractor response that has no `resolved` action,
  oldest first, with the owner's name. This is the first thing the page shows,
  because it is the only row on the page that is still costing money.
- **Testimonial bank** — responses with publish consent, filtered by the channel they
  consented to, each with a copy-ready quote block and a link to the project it
  speaks about.
- **The list** — every request with its state (`live` / `opened` / `responded` /
  `expired` / `revoked`), exactly the badges `PartyStatementShare` already uses; the
  row menu offers *Copy link*, *WhatsApp*, *Email*, *Send reminder* (max two, and
  never after a response), *Revoke*.
- **CSV** — the same rows under the same filters, as the other modules do.

Integrations, all one line each because the relations already exist:

- **Project page** — a *Feedback* tab: the request's state, the response, and the
  actions. When a project moves to `completed`, the status log offers **Share feedback
  link** as the next step (offer, never automatic: a project can be marked complete
  before the client has the goods in hand).
- **Client page** — a *Feedback* panel: every response for that client, the score
  trend, and whether the account is a renewal risk. This is the panel sales will
  actually open before quoting the next order.
- **Vendor performance** — a complaint that names a vendor (`fix` action, severity
  high) can be filed against the vendor's existing performance panel, which is where
  the same problem is fixed for every other client.
- **Dashboard** — one stat: *Responses awaiting action*. Not the average score — a
  number nobody can act on belongs on the module page, not on the dashboard.

## 7. The rules a check should hold

`tools/checks/feedback-check.cjs`, dependency-free, in the house style. Each of these
is a rule a copy-paste would break silently:

1. **The link is the whole of the authentication** — the public controller never
   accepts a project or client id from the request, only the token; a revoked or
   expired token renders the *expired* page, not the form, on both GET and POST.
2. **One answer per ask** — unique index, and the POST refuses a second submission
   naming the response that already exists.
3. **The score is computed, never typed** — NPS, averages and bands come from
   `FeedbackVocabulary` / the service; no view and no script does its own arithmetic.
4. **Feed-back cannot gate anything** — no route, no view and no controller may block
   a payment, a document or a project close on a feedback request. Feedback asked for
   under pressure is not feedback.
5. **A detractor is never a dead end** — every response with a band of `detractor`
   has exactly one open action within 48 hours of submission (an observer/job, tested).
6. **Consent is checked at the point of use** — a testimonial can only be published via
   a channel the response consented to; the office screen offers no other route.
7. **Nothing about one client appears on another's page or link** — no query joins
   across clients on a public surface; the token page loads by token only.
8. **The figures on the office page and the CSV are the same query** — one service
   method, two renderings, as the other modules keep it.
9. **The office list is a list like the ERP's other lists** — the order hint and the
   toolbar's right-hand group stand where the shared sheet expects them (and the density
   presets are bound to the shared toolkit), the counts wear the shared pill, the figures
   wear the module's own icon vocabulary, the action column is named, and the filter row
   and the records are two cards — the shell's own 24px is what stands between them, never
   a margin the module wrote for itself.

## 8. Anti-abuse and privacy, briefly

- Honeypot field, exactly as `PublicLeadController` does it, plus `throttle` on the
  POST. A token is not a password and will be forwarded; rate limiting is what stops
  a forwarded link being a spam hole.
- The public page is `noindex, nofollow`, like the statement.
- The response stores an IP and user agent for the office's own record; the page says
  so in one line, because collecting it quietly is how a feedback form becomes a
  trust problem.
- Deleting a project does not silently delete its responses — the response is the
  client's words about us, not the project's property. `project_id` nulls; the
  response stays and the report still counts it. (This is a deliberate difference from
  `Project::destroy()`, which deletes child rows.)
- Nothing new leaves the server: no third-party form, no analytics pixel on the page.

## 9. Phasing

Each part ships whole — model, service, controller, screens, check — the way the
purchase module was built.

| Part | What ships | Value it delivers on its own |
| --- | --- | --- |
| **1. The ask** | `feedback_requests` + `feedback_responses` + `feedback_answers`, vocabulary service, public form at `/feedback/{token}`, expired page, Share/WhatsApp/Email/Revoke from the project page | The link exists and the answers land somewhere only this office can read |
| **2. The surface** | Feedback list + figures + needs-attention queue + testimonial bank + CSV, project tab, client panel | The office can see response rates and read replies |
| **3. The loop** | `feedback_actions`, detractor auto-action + owner notification, reminder ladder, portal route + notification, resolved-action note back to the client | Something actually happens after the score — the part that makes it worth it |
| **4. The pulse** | `kind = pulse` requests at PPS / dispatch-ready, mid-project branch | Problems caught before the run, not after |

## 10. Non-goals for v1

- **No incentive and no gate.** No payment held, no next order delayed, no discount
  for a review. It buys a number that means nothing.
- **No public wall of praise.** The testimonial bank is internal; publishing stays a
  human decision on a consented quote.
- **No email marketing machinery.** The module shares a link and reminds twice.
- **No question builder.** A configurable form is a product of its own; the six
  dimensions live in the vocabulary table and change when the business does.
- **No anonymous-only mode.** Anonymous feedback cannot be followed up, and the
  follow-up is the point.

## 11. The decisions, as taken

1. **The dimensions are editable in the app.** `feedback_master_options` holds them,
   `feedback.settings` edits them, `FeedbackVocabulary` reads them, and the built-in
   six are the fallback so an unseeded table still renders a working form. Retiring
   a line is safe — the answers that scored it keep their numbers and wear the new
   name; deleting is only offered while nothing has ever been scored on it.
2. **The client is always named.** There is no anonymous channel: the follow-up needs
   someone to ring, and an anonymous answer cannot be followed up. The *marketing*
   consent boxes are separate, one per channel, and the office can only ever publish
   where a box was ticked.
3. **A detractor raises its own follow-up.** Inside the same transaction that stores
   the answer: a `FeedbackAction` with an owner (the project's owner, then its
   creator, then the ask's issuer), a two-day clock, and a `Task` in that owner's own
   list carrying the client's words. Nothing waits for somebody to open a page.
4. **The ask is an offer, never automatic.** Marking a project complete says where the
   link is — *"Ask for feedback while it is fresh"* — and the Feedback tab issues it
   on one click. A project can be closed before the client has the goods in hand, and
   a form that arrives early reads as a chase.

## 12. Where it lives

| Layer | Files |
| --- | --- |
| Schema | `database/migrations/2026_10_06_0100*_feedback_*.php` (ask, answer, scorecard, follow-up, vocabulary, seed) |
| Models | `FeedbackRequest`, `FeedbackResponse`, `FeedbackAnswer`, `FeedbackAction`, `FeedbackMasterOption` |
| Services | `FeedbackVocabulary` (the words and the rules), `FeedbackFormRules` (one validation for both doors), `FeedbackIntake` (the one writer), `FeedbackFilters`, `FeedbackFigures` (one query for screen and CSV) |
| Controllers | `FeedbackController` (office), `PublicFeedbackController` (the link), `ClientPortalFeedbackController` (the portal) |
| Routes | `/feedback/{token}` and `/feedback/{token}/thanks` public; `feedback.*` in the office; `client-portal.feedback.*` |
| Screens | `resources/views/feedback/*` (list, ask, settings, public form, thank-you, expired), the project tab, the client panel, the portal pages |
| Asset | `public/assets/css/feedback.css` (module-local `fb-*`, shared tokens), `public/assets/js/feedback.js` (copy only — the module works with JavaScript off) |
| Guard | `tools/checks/feedback-check.cjs` — run it after touching any of the above |

The sheet follows `docs/ui-design-guidelines.md` rather than a look of its own: a card
the module owns is padded 20–24px (`.fb-card`), a card holding a table is flush with its
bar inset at 16px (`.fb-card--flush`), controls and buttons are 40–44px, and the space
between cards is the shell's own — 24px between page blocks (the `.master-list.css`
rhythm, mirrored on `.fb-show` and `.fb-settings`), 16px inside a panel, a tab or the
side rail, and the guideline's own 16px between the five cards of the form itself.
Nothing here spaces its own page, and no shared control is redrawn.

The form is that shared API rather than a look beside it: a section is a `core-card` (the
module adds only `.fb-card`'s padding and `.fb-step`'s rhythm), a field is a `core-label`
over a `core-text-input` or `core-textarea` with its `core-field-error` under it, the
error summary is a `core-alert`, the submit is a `core-button core-button-primary`, and
the two pick-one-of-few questions — work with us again, and which uses of a quote you
consent to — are the app's `.master-choice-chip` group. The standalone copy of the form
loads `core.css` and `master-form.css` for those components (the same two sheets the
office and the portal already load), and re-states the two border longhands the legacy
`--mc-*` tokens would have carried, because a public page has no app shell to define
them. Four checks pin the result: the page rhythm, the card padding, the shared control,
and the form's own vocabulary.

The list is the same rule on the other surface. It is a list like the ERP's other lists,
so it wears the shared chrome in the shared places: the order hint on the left, the export
and the three density presets grouped in the toolbar's right-hand end, the counts in the
shared count pill, the module's own Font Awesome icons on the four figures, and a named
action column. Declaring that right-hand group is what keeps the controls in the toolbar
rather than on a line of their own above the table — and the group needs
`MasterList.density`, because the shared script binds only the controls it created itself.
It is two cards as well, the search and filter one and then the records one, because the
shared sheet puts the shell's 24px between two cards and nothing between two blocks inside
one: as a single card, the search row ran straight into the table. Two more checks pin it:
the chrome in the shared places, and the two cards with the shell's rhythm between them.

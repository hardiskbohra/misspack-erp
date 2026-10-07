# Settings — the one place a rule is changed

> *"Make one central settings module. Move all the settings of all the modules
> in that section. This is the one place where all the module's settings can be
> found. Module-wise we can have submenu, so that we can have a better way of
> managing all the settings."*

Before this round, the settings of this ERP were where they had grown: the
**organisation profile** was a sidebar item, the **briefing rules** were
another one, the **cashflow accounts, categories and option lists** were a
button on the ledger, the **lead dropdowns** were a link on the lead list, and
the **feedback scorecard** was a third page reachable only from the feedback
list. Five screens, five ideas of what a settings page looks like, and nobody —
including the office that uses it every day — able to answer *"where do I change
that?"* without trying two or three of them.

The module is the answer to that question. It is **one** screen with a rail, one
hub that says what is inside each area, and one rule about what belongs in it.

---

## 1. The boundary rule — what a *setting* is

This is the sentence the whole module is built on, and the one that will be
asked again every time a new screen is designed:

> **A setting is a rule or a master list that changes how a module behaves for
> everybody. A module's own records are not settings, however editable they
> look.**

The test is not *"is it editable?"* — everything is editable. The test is
**who changes, and for whom**:

| It is a setting | It is not a setting |
| --- | --- |
| the GSTIN and logo every invoice prints from | a client, a vendor, a contact |
| the option lists every lead form is filled from | the lead that was filled in |
| the accounts and categories the ledger is kept in | the cashflow entry that was posted |
| the lines a client scores | the scorecard a client returned |
| which briefings raise, and who is emailed | the alert that was raised, and the snooze on it |
| a user's role and desk | a user's own mobile number |

The right-hand column keeps the list pages it already has — clients, projects,
products, users, notes, office services. They are *work*: a record per thing,
owned by the person who made it, with its own states, its own history and its
own page. The left-hand column is *configuration*: one answer for the whole
company, changed rarely, read by every screen. Moving the right-hand column into
Settings would have made the module a second copy of the application.

The rule is enforced by where the screens live, not by a paragraph: this module
has no models of its own, and `SettingsController` writes nothing.

---

## 2. The five areas

| Area | What it owns | Where the writes already lived |
| --- | --- | --- |
| **Organisation** | the company record every document prints from — name, GSTIN, PAN, logo, letterhead, addresses and branches, contacts, social links, bank accounts | `OrganisationController` |
| **Briefings** | what raises an alert for the whole office, whether it pops up, who is emailed, and which desk each watcher holds | `OfficeBriefingSettingController` |
| **Leads** | the dropdown master data a lead is filed with — status, source, priority, finish, printing, currency, incoterm, capacity unit | `LeadSettingController` |
| **Feedback** | the scorecard: the lines a client scores, which are also the list the report groups by and the "needs attention" queue scores against | `FeedbackController` |
| **Cashflow** | the accounts money moves through, the categories rows are filed under, and the option lists the entry forms are filled from | `CashflowSettingController` |

Each area's controller is unchanged in what it does: it validates, it writes, it
redirects. What changed is the **view** it renders and the **route name** it
sends the reader back to. A settings module that took over the writes would have
been a second writer for five modules' rules, and the first one to drift.

---

## 3. One list — `App\Services\SettingsDirectory`

The rail (in `settings/partials/nav.blade.php`) and the hub (in
`settings/index.blade.php`) draw from the same array:

```php
$areas = app(SettingsDirectory::class)->areas();
```

So an area is defined **once** — key, label, icon, blurb, what it holds, its
route, its search keywords, and how to count it — and it can never be in the
menu and missing from the hub, renamed in one and not the other, or link to a
route that does not exist. Neither view names an area by hand; that is a check.

Two things the directory deliberately does **not** do:

- **it owns no setting.** It knows the areas exist and what they are called. It
  does not know what a valid GSTIN is, or which frequencies a recurrence offers.
  Each module's controller keeps its own validation — the directory is a
  directory, not a second writer;
- **it does not decide who may look.** The routes stay inside the office's half
  of the application, exactly as they were, and the module adds no permission
  model of its own. When roles grow a "may change the ledger's accounts" flag,
  that flag belongs on the route, not on a card.

### The numbers on the cards

`counts()` returns one number per heading — how many addresses are on file, how
many option rows an area holds, how many briefing sources are switched on. Two
rules make them safe:

- **a count may not be the reason a settings screen does not open.** Each one is
  wrapped in its own `try/catch`, and each checks `Schema::hasTable()` first: a
  half-migrated install shows the settings with one fewer badge instead of a 500;
- **`null` is not zero.** A table that is not there yet returns `null`, and the
  card **omits** that badge. A zero would be a statement about the office's data;
  `null` is a statement about the database.

---

## 4. The rail, and the way in

**The rail is the module-wise submenu.** It is the same five links on every
settings screen — so from Organisation a reader can reach Briefings without
going back to the hub — and it is rendered from the directory, so it is the same
five links everywhere by construction. On a wide screen it is a sticky column;
on a phone it is a horizontal strip that scrolls its own current item into view
(`settings.js`), because a menu that opens three items along looks like it
starts at Organisation.

**The sidebar has one door, not a tree.** Settings is a single item; the
module-wise menu lives inside the module. Five sub-items in the sidebar would be
the same five links in two places, kept in step by hand, and the day the two
disagree is the day one of them is wrong. The same is true of the account menu,
which used to carry *Organisation* and *Briefing settings* as separate items.

**Both the hub and the rail are one component's job.** `.set` is the two-column
layout, `.set-nav` is the rail, `.set-head` is the page head every area wears
(mark, name, one sentence), and the hub's `.set-grid` is the cards. Nothing in
`settings.css` defines a card, a button, a field, a tab, a badge or a table: the
surfaces are `.master-card`, the buttons are `.master-btn`, the area's inner tabs
are the shared `.master-tabs` with a URL per tab, and every colour is a `--ui-*`
token — which is why the sheet has no dark-theme block. It has none of its own
literals, and that is checked.

---

## 5. URLs, and what happened to the old ones

| Area | URL | Old URL (still open) |
| --- | --- | --- |
| the hub | `/settings` | — |
| Organisation | `/settings/organisation` | `/organisation` |
| Briefings | `/settings/briefings` | `/office-alerts/settings` |
| Leads | `/settings/leads` | `/leads/settings` |
| Feedback | `/settings/feedback` | `/feedback/settings` |
| Cashflow | `/settings/cashflow` | `/cashflows/settings` |

Three rules, and all three are checked:

1. **Every old URL redirects** (301) to the page that replaced it. A bookmark is
   somebody's link to work, and a module move is not their problem;
2. **the redirect carries the query string.** On these screens the tab *is* the
   address: `/cashflows/settings?tab=categories` is a link to the categories, and
   a redirect that drops it lands the reader on Accounts — an old link that feels
   broken is worse than a 404 that says so. The `masters`/`group` spellings the
   cashflow page accepted before are carried through the same way, and its
   controller still reads them;
3. **the old route names still exist** (`organisation.settings`,
   `office-alerts.settings`, `leads.settings.index`, `feedback.settings`,
   `cashflows.settings.index`), pointing at the redirect. Route names are used
   by checks, docs and modules that link across; a rename is a 404 in whichever
   of those places was forgotten.

The **tab is a URL** on every area page — `?tab=…` is validated by the area's own
controller, so a settings page is shareable, bookmarkable and reachable with the
back button. That is the shared `.master-tabs` rule from
`docs/ui-design-guidelines.md`, and the two moved pages that had their own tab
strip (cashflow's `cf-tab`, leads' `ls-tab`) now wear it.

---

## 6. Finding a setting

The hub answers *"what is in this module?"*. The global search box answers the
harder question — *"where is that setting?"* — which is the one people actually
have when they are three screens deep in a shipment.

`SettingsDirectory::searchHits()` matches an area's label, blurb, headings and
keywords (nobody types *briefings* when they want the emails switched off; they
type *alert*, *email*, *popup*), and prefers a **heading** over the area that
contains it: searching *bank* opens the bank accounts, not the organisation page
four clicks away. `GlobalSearch` appends the matches as a `Settings` group, and
the group only appears when it has something to say.

---

## 7. Recommended next — the honest list

What is deliberately **not** in this round, in the order it would pay off:

1. **A "changed by" trail on the settings themselves.** The office can see *what*
   a setting is and never touched *who* set it. Two columns (`updated_by`,
   `updated_at`) and one line on each card — "GSTIN changed by Hardik, 3 days
   ago" — would turn every settings argument from memory into a fact. This is
   the single highest-value addition, and it belongs to each module's own table,
   not to a central log;
2. **Roles on the areas.** Today the office door is all-or-nothing: everybody
   with an office login may change the ledger's accounts, the GSTIN and who
   receives briefings. When roles grow, the flag belongs on the route group
   (`settings.organisation.*` etc.), and the rail can grey an area the reader
   cannot open rather than hiding it;
3. **A "recently changed" strip on the hub.** Four lines, drawn from the trail
   in (1). It answers the question people actually ask after a change ("what did
   that do?") and it is the reason the hub would be worth visiting on a normal
   day, not only when something is being changed;
4. **Per-area "reset to default"** for the areas that have defaults today —
   the briefing sources, the lead option lists, the feedback scorecard. The
   defaults exist in code (`OfficeSetting::sourceLabels()`,
   `LeadMasterOption::groupOptions()`, `FeedbackVocabulary::DEFAULT_DIMENSIONS`);
   the screen just does not offer them.

None of these change the boundary rule or the shape of the module. They are the
next four increments of it.

---

## 8. What is checked

`node tools/checks/settings-check.cjs` (registered in `tools/checks/README.md`)
holds the parts that are easy to lose:

- the areas are defined once, in the directory, and neither the hub nor the rail
  names one by hand;
- the page owns no setting — `SettingsController` has no model, no `save`, no
  `create`;
- every area screen includes the same rail, with its own key, and marks exactly
  one item;
- the sheet defines module chrome only (no `master-*`, `cf-*`, `ls-*`, `fb-*`,
  `ob-*` rule), spends `--ui-*` tokens and no literals, and never hides the rail
  on a phone;
- the five old URLs redirect, the redirect carries the query string, and the old
  route names survive;
- the five pages that used to *be* the settings are gone, no controller still
  renders them, and the two module sheets no longer style them;
- a setting is findable by the words people use, and the search box says so;
- the boundary rule is written down — this file.

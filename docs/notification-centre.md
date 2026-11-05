# Notification centre

The office already had the *bell*: a dropdown of open briefings in the topbar,
with popups for the critical ones and email for the desks that opt in
(`office_alerts` + `office_alert_states`, raised by `App\Services\OfficeBriefing`,
configured under **Settings → Briefings**).

The bell answers *"what needs me now?"* and deliberately forgets the rest —
an acknowledged alert leaves the dropdown. The notification centre
(**/office-alerts/centre**, header-chrome link from the bell's footer) is the same desk
as a page, and it answers what the dropdown cannot: *"what happened while I was
on the floor, and who dealt with it?"*

## Surfaces

| Surface | What it holds | Lifetime |
| --- | --- | --- |
| Bell dropdown | Open items (≤80), snoozed dimmed at the bottom, badge = open count | Leaves when acked/seen |
| **Centre page** | Open + snoozed + resolved, with resolver names and timestamps | Keeps the last 200 of your desk |
| Popup modal | The newest critical ack-required alert, once | Until acked/snoozed |
| Email | Critical raises + optional hourly chase of unacked | — |

## State machine (per alert, per user)

```
raised ──● open ──[Mark read / Got it]──▶ resolved (keeps who & when)
              │
              └──────[Snooze 1h/4h/tomorrow 9:00]────▶ snoozed ──(time passes)──▶ open
```

- **Shared acknowledgements** (`requires_ack`, e.g. a KYC to review): one person's
  ack resolves the alert for the whole office — the alert row stamps
  `acked_at`/`acked_by`, and the resolved trail says *who covered every desk*.
- **Personal items** (`FYI` severity): your own "Got it" (seen) resolves it for
  you only; other desks keep their copy until they act.

The centre filters by **tray** (open / snoozed / resolved), **desk**
(office-wide / sales / operations / accounts) and **severity** (needs action /
follow up / FYI). Filter chips are links — every filter state is a shareable URL,
and changing one filter never drops the other two.

## Scope rules that already existed (unchanged)

- Only non-payroll users (`EnsureUserIsAdmin`) reach the centre; the bell never
  rendered for employees and the page doesn't either.
- Desk watchers see their own desk plus office-wide briefings
  (`User::watchesTeam`). A director (no department set) sees everything.
- PoP-gating stays in `OfficeAlert::payload()`; the centre returns the honest
  empty state when briefings are disabled in settings.

## Wiring

- `OfficeBriefing::centre($user, $filters)` — maps alerts through the dropdown's
  `present()` and adds `state`/`resolved_at`/`resolved_by`; stats are computed
  pre-filter so the strip always shows the true desk.
- `OfficeAlertController::centre` (+ `GET /office-alerts/centre`,
  `office-alerts.centre`) — validates the three filters.
- The three action endpoints (`seen` / `ack` / `snooze`) now answer a plain
  form POST with `302 + flash` instead of JSON — the centre works without
  JavaScript; the dropdown's fetch flow is untouched.

## Out of scope on purpose

- **No push channels added** (SMS/WhatsApp): the email chase is the loud lane;
  anything more links out from a real alert and is a settings decision.
- **No auto-resolve on read of the target page** — a brief is resolved by an
  explicit ack, because "opened the KYC tab" does not mean "reviewed the KYC".
- **No per-user preferences page** — desks are organisation-level
  (Settings → Briefings) by design; personal snoozing is per-alert, per-user.

## Tests & pins

`tests/Feature/OfficeAlertCentreTest.php` (6 cases, in-memory sqlite):
guest/employee walls, listing order (critical first), filter narrowing with
invalid-value 422, ack-from-centre filing the resolved trail with the resolver's
name, snooze moving cards between trays, and desk-watcher scoping. Catalogue:
IT-BRIEF-004…008.

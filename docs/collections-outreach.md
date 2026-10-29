# Collections outreach — slice 1: e-mail the invoice, and keep the evidence

Part of the audit's P0 collections line. The office already *logs* chases
(`SalesInvoiceReminder` — the WhatsApp call that happened outside the system).
This slice is the first that makes the system itself *send*: the invoice goes
to the client's inbox from MissPack, and every attempt is provable afterwards.

## What ships

- **"Email to client"** on the invoice record page (actions menu + inbox card)
  and in the list's row menu. One shared compose dialog — the trigger carries
  the invoice and the defaults; `sales-invoices.js` fills the template action.
- **Defaults the model writes** (`SalesInvoice::emailDefaults()`): To is the
  e-mail snapshot on the invoice, else the client card's accounts-desk address;
  subject is `Invoice INV-0001 from {seller}`; message is a polite first send,
  not a chase (a chase body is `reminderMessage()`, one click away).
- **Send-event tracking** (`sales_invoice_sends`): recipients, cc, subject,
  message, sender, timestamp, status (`sent` | `failed`) with the transport's
  reason. A log, not a flag — the same invoice goes out several times and
  which attempt said what is the next conversation's ammunition.
- **The send is the mark-sent move** when the box is ticked (default): a draft
  becomes `sent`, `sent_at` is stamped, the client portal copy opens — same
  transition as the old *Mark sent* item. Unticked (re-sends) changes nothing
  on the invoice, and the mail omits a "View invoice" button that would 404.
- **Failures are loud, not silent**: the flash says why, the log row keeps the
  transport's reason, and `invoice.email_failed` raises an ack-required,
  accounts-desk briefing in the notification centre (`workflowNotice`).
- **Misconfiguration fails fast**: with no sender address configured the
  office is told to fix Organisation settings *before* any attempt — and no
  fake send row is written for a mail that was never attempted.

## Deliberate limits of the slice

- **No PDF attachment yet.** The mail carries the numbers and the portal link;
  the print view is the document. A PDF attach needs the print pipeline in a
  downloadable form — separate slice.
- **No open/click tracking.** The send log is office-side truth (what/who/when
  /accepted-or-refused), not pixel tracking; public-copy views are a later slice.
- **No dunning schedule.** Reminders stay manual-but-logged until the dunning
  engine lands (Next, P0).
- The recipients are free text (comma/space-separated), not a contact picker —
  the card gives you the sensible default and gets out of the way.

## Wiring

| Piece | Where |
| --- | --- |
| Endpoint | `POST /sales-invoices/{invoice}/email` → `SalesInvoiceController@emailClient` (`web`,`auth`,`office`) |
| Mail | `App\Mail\SalesInvoiceMail` → `mail.sales-invoice` markdown view |
| Log model | `App\Models\SalesInvoiceSend` ← `SalesInvoice::sends()` |
| Compose dialog | `sales_invoices/partials/email-modal.blade.php`, opened by `[data-open-email]` in `sales-invoices.js` |
| History card | "In the client's inbox" on the record page (empty state explains the button) |
| Failed-send brief | `invoice.email_failed` via `OfficeBriefing::workflowNotice` |

## Tests

`tests/Feature/SalesInvoiceEmailTest.php` — walls, the full happy path (mail
contents + send row + status flip + page shows it), the no-op re-send, bad
address, cancelled invoice, missing sender config, and the refused transport
(failed row + desk alert). Catalogue: IT-SALES-013…018.

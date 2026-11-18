# E-invoice (IRN/QR), GSTIN checksum and the HSN/SAC rate master

Three connected pieces of GST plumbing, one read. The roadmap item they close
is *"E-invoice (IRN/QR) schema + integration; GSTIN checksum + HSN/rate
master"*.

---

## 1. GSTIN, validated for real — `App\Support\Gstin`, `App\Rules\ValidGstin`

Every GSTIN the office types now passes three gates, not one:

1. **Structure** — `SS PPPPPPPPPP C Z X` (state code, embedded PAN, entity
   counter, the reserved `Z`, the check character).
2. **State code** — characters 1–2 must be an allotted code (01–38, 97, 99;
   the whole map is public and lives in `Gstin::stateCodes()`).
3. **Check digit** — the official mod-36 (Luhn-family) checksum over the first
   fourteen characters. This is the gate that catches the typo, which is the
   discrepancy that otherwise surfaces months later as a GSTR-1 mismatch.

Helpers ride on the validation because a known-good GSTIN is useful:
`stateCode()`, `stateName()`, `pan()`.

Wired at every collection point: client store & quick-store (public KYC too),
vendor store, the vendor KYC portal (`VendorKyc`), the organisation profile,
and both invoice headers' GSTIN snapshots. Nullable stays nullable; empty is
not a GST problem.

> Fixture note: the old test GSTIN `24ABCDE1234F1Z5` was structurally fine but
> checksum-wrong (the correct digit is `6`). Fixtures were corrected — the unit
> test pins the true digits so the fixture cannot drift back.

## 2. The HSN/SAC rate master — Settings → Taxes & HSN

- Table `hsn_sac_codes`: `code` (4–8 digits, unique), `kind` (goods/service),
  `description`, `gst_rate`, `is_active`, `sort_order`. Seeded with the codes
  a packaging manufacturer actually uses (39xx plastics/paper packaging, 998x
  job-work services) at the rates current at seed time; each row carries a
  description so a notification review can audit them quickly.
- CRUD lives under **Settings → Taxes & HSN** (`TaxSettingController`), a new
  area in the SettingsDirectory — the area is *"a master list that changes how
  invoices behave for everybody"*, which is exactly the boundary rule.
- **Forms**: `public/assets/js/hsn-autofill.js` turns the codes into a native
  `<datalist>` on every HSN input of the sales/purchase invoice line grids;
  choosing a mastered code fills the line's GST rate advisory-style (editable
  after). The data arrives as `data-hsn-codes` on the items `<tbody>`, same
  pattern as product options.
- **Deliberate**: invoice lines keep their own copy of HSN and rate. Editing
  the master never re-prices history; documents freeze what was charged. The
  master changes what forms *offer from now on*, no more and no less.

## 3. E-invoice (IRN/QR) — schema, lifecycle, providers

### The law-in-code summary

A registered tax invoice carries an **IRN** (64-hex-char hash identity on the
GST network), an **acknowledgement**, and a **signed QR** for offline
verification. Cancellation is possible only within **24 hours of the ack**;
after that it's a credit note, and the ERP says so rather than offering a
button that the portal would refuse.

### Storage — `sales_invoice_e_invoices`

One row per sales invoice (unique FK; IRN unique). Alongside status it keeps:
`irn`, `ack_no`, `ack_date`, `signed_invoice`, `signed_qr_code`, the exact
**request** JSON we generated, the gateway's raw **response**, last `error`,
who registered, cancel metadata. When the portal and the ERP ever disagree,
this row — not either party's memory — settles it.

### Lifecycle (one writer: `App\Services\EInvoice\EInvoiceRegistration`)

```
(no record) ──generate──▶ pending ──record ack (manual)──▶ registered ──▶ cancelled (≤24h)
                       └──▶ or registered directly (http gateway)
                       └──▶ failed (gateway refused; retry = a fresh generate)
```

- **Pre-flight** (`EInvoicePayload::issues()` + `build()`) — refuses before any
  byte moves: proforma/cancelled/no-date, seller GSTIN invalid, buyer GSTIN
  invalid where the supply type needs it (B2B always; B2CL only above the
  ₹2.5L inter-state line), unplaceable place-of-supply, and **every line needs
  a 4–8 digit HSN/SAC**. The invoice show page lists these blockers instead of
  a dead-end button.
- **UI** — an "E-invoice (IRN/QR)" card on the sales-invoice record page:
  generate → copy the NIC-schema JSON / register direct → record IRN/ack/QR →
  IRN, ack and QR displayed, cancel with portal reason codes 1–4 while the
  window is open. The **print document carries the IRN and a QR** drawn locally
  by `qr.js` (extended to version 20 offline, tables cross-checked against the
  reference implementation — pinned by `tools/testing/qr-code.test.cjs`).
- **Audit** — `sales_invoice.einvoice.generated/registered/cancelled` events in
  the audit log with the actor; only the IRN tail is logged, like all secrets.

### Providers — `config/services.php → einvoice`

| Provider | What happens on *Generate* |
|---|---|
| `manual` (default) | Payload parked as pending with a copy-friendly JSON block; the office registers on the portal/GSP and records IRN/Ack/QR back. Suits current SME practice and needs zero new dependencies. |
| `http` | POSTs the payload JSON (bearer token optional) to `EINVOICE_ENDPOINT`; expects the artefacts back as `{Irn, AckNo, AckDt, SignedInvoice, SignedQRCode}` (snake_case accepted); `{endpoint}/cancel` on cancel. |

Switching providers is `.env` (`EINVOICE_PROVIDER`), never code. HTTP errors
and "2xx without an IRN" become a `failed` record with the reason on the card.

### What deliberately does not exist

- No retry loop on a timed-out HTTP submission (duplicate submits can mint
  duplicate IRNs on some bridges — a human re-fires deliberately).
- No automatic "e-invoice required" turnover check (the ₹5Cr aggregate rule
  is a business fact, not a per-invoice one); the card is available on every
  tax invoice and refuses nicely what the law would refuse anyway.
- No e-way bill integration — that is a separate portal transaction.

## 4. Tests

| Coverage | Where |
|---|---|
| GSTIN check digit, mutations, rule message | `tests/Unit/GstinTest.php` (UT-TAX-001) |
| Registration refusal (proforma, missing HSN), payload schema, manual ack flow, cancel window, HTTP gateway contract | `tests/Feature/EInvoiceFlowTest.php` (IT-TAX-001…006) |
| Settings area CRUD, validation, auth wall | `tests/Feature/TaxMasterTest.php` (IT-TAX-007…010) |
| QR matrices pinned vs. reference-verified goldens incl. v12–v20 payloads | `tools/testing/qr-code.test.cjs` (contract chain) |
| Checksum-negative client creation | `tests/Feature/InvoiceClientCreationTest.php` |

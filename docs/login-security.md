# Sign-in security: lockout, password policy, two-factor

How the office's front door is defended, what each defence is for, and where
the knobs live. Written when the door was hardened; keep it next to
[permission-and-audit-hardening.md](permission-and-audit-hardening.md), which
covers what happens *after* somebody gets in.

---

## 1. The three defences, in the order an attacker meets them

### 1.1 The login throttle — `AuthController`

The most privileged account in the business signs in at `/login`, and that
route used to accept unlimited guesses at a `min:4` password. Now:

- **Five wrong passwords** for one email from one IP lock the pair for **five
  minutes** (`MAX_LOGIN_ATTEMPTS`, `LOCKOUT_SECONDS`). The counter is hit on
  *every* failed check — including emails that do not exist — so the lockout
  cannot be used to discover which emails are accounts.
- A **right** password during lockout is refused too; the lockout ends guesses,
  not just failures. The message says how many seconds to wait and nothing
  about which half is locked.
- A successful sign-in **clears** the counter: an office that fat-fingers twice
  a day is never punished for the evening before.
- Lockouts are audit events (`auth.login.throttled`) so a brute-force run is
  visible in the register next morning, beside the existing
  `auth.login.failed` / `auth.login.succeeded` trail.
- A blunt `throttle:30,1` route backstop stops the door being *knocked* on
  at machine speed even below the lockout threshold.

Why per email+IP and not per email: an attacker anywhere could otherwise keep
the legitimate owner's account permanently locked (a denial-of-service against
people, not servers). Why not per IP alone: a whole office behind one
connection would share one attacker's quota.

### 1.2 The password policy — `Password::defaults()`

The rule used to drift: `min:4` at login, `min:6` in the user editor, `min:8`
on the account page. It is now said **once**, in `AppServiceProvider::boot()`:

> at least 12 characters, with upper and lower case letters and a number.

Everywhere a password is *set* — the account page's own change, the office's
user create/edit — validates against `Password::defaults()`. Login itself no
longer repeats a length rule: where a password is *tried* is the wrong place
to say what it must be.

Deliberately **not** `->uncompromised()`: that rule phones a breach-list
service at validation time, and the office network is not promised to reach it.
Existing short passwords keep working — the policy bites at the next change,
which is the migration path for an installed base (see §4).

### 1.3 Two-factor authentication — TOTP, no packages

Accounts can opt into a second factor from **My Account → Two-factor
authentication**. The implementation is RFC 6238 TOTP
(`App\Support\Totp`) written into the codebase, because the defence could not
wait for a Composer package: HMAC-SHA1 over a 30-second counter, six digits —
the same thing every authenticator app already speaks.

**Enrolment** (`TwoFactorController` + `TwoFactorAuthentication`):

1. *Start* creates a **pending** secret. Nothing about sign-in changes yet —
   a half-finished setup can never lock its owner out.
2. The page shows the **setup key** (Base32, grouped in fours for typing) and
   an `otpauth://` link for phones. There is intentionally no QR image: no QR
   library is installed, and every authenticator app accepts a typed key —
   this is the documented trade-off, not an oversight.
3. *Confirm* asks for the six digits the app shows. A right code flips the
   account on and generates **eight recovery codes** — shown in the page
   **once**, stored only as SHA-256 hashes (48-bit random, single-use,
   throttled: a fast hash is the right hash for spend-once secrets).

**Sign-in** (`AuthController` → `TwoFactorChallengeController`): a right
password for a two-factor account buys *a challenge, not a session*. The
session carries only a pending user id to `/two-factor-challenge`, which
trades a six-digit code — or one recovery code — for the real session.
Recovery codes are **consumed on use**. Five wrong guesses at the six-digit
space tear the challenge up and send the visitor back to the password
(`auth.two_factor.throttled` audited); the route is also `throttle:15,1`.

**Management**: disabling two-factor and regenerating recovery codes both ask
for the **current password** again — a session left open on a shared machine
must not be able to quietly weaken (or quietly capture) its own account. All
of it lands in the audit register: `auth.two_factor.challenged`, `.succeeded`,
`.failed`, `.enabled`, `.disabled`, `.recovery.regenerated`.

Secrets are **encrypted at rest** (Eloquent `encrypted` casts), so a database
dump alone is not a keyring; the columns are also `$hidden` from any
serialization.

---

## 2. Where everything lives

| Piece | File |
|---|---|
| Login + lockout + challenge handoff | `app/Http/Controllers/AuthController.php` |
| The challenge page | `app/Http/Controllers/TwoFactorChallengeController.php`, `resources/views/auth/two-factor-challenge.blade.php` |
| Enrolment / disable / recovery codes | `app/Http/Controllers/TwoFactorController.php`, `resources/views/account/partials/two-factor.blade.php` |
| The 2FA rules | `app/Services/TwoFactorAuthentication.php` |
| RFC 6238 TOTP | `app/Support/Totp.php` |
| Password policy default | `app/Providers/AppServiceProvider.php` (`Password::defaults()`) |
| Columns | `database/migrations/2026_10_25_220000_add_two_factor_authentication_to_users_table.php` |
| Tests | `tests/Unit/TotpTest.php`, `tests/Feature/LoginSecurityTest.php` |

## 3. Tests

`tests/Unit/TotpTest.php` pins the algorithm to the RFC's own vectors (so a
refactor breaks the build, not sign-ins). `tests/Feature/LoginSecurityTest.php`
covers: five-failure lockout (with the right password refused during it),
success clearing the counter, the strength rule rejecting a weak candidate and
accepting a strong one, password→challenge→session for a two-factor account,
single-use recovery codes, challenge exhaustion, and the account-page
enrol/disable walk. Traceability entries UT-AUTH-004 and IT-AUTH-004…006 in
`docs/testing/catalogue.json` link each case to its methods.

## 4. What is deliberately left for later

- **Enforcement.** Two-factor is opt-in today so the deploy cannot strand the
  one administrator who never reads release notes. Next step: an office
  setting making it mandatory for `admin` role accounts, with a grace period
  banner (the permission/audit scaffolding in
  `permission-and-audit-hardening.md` is where the report of who hasn't
  enrolled belongs).
- **QR enrolment image.** Only when a QR library legitimately joins
  `composer.json`; the typed key is the fallback every app already supports.
- **Legacy password rotation.** No forced reset was shipped; if a credential
  leak is ever suspected, force-reset specific users from the user editor
  rather than shipping a global flag day.
- **Portal logins** (client/vendor) keep their existing `throttle:10,1`; giving
  portal users TOTP is a separate product decision, not a stop-gap.

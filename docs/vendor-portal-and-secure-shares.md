# Vendor portal and secure share links

## Two access paths

The vendor collaboration surface deliberately supports two different access needs:

1. **Authenticated vendor workspace** at `/vendor-portal` for recurring supplier contacts. Each person has a named account, a one-time password, mandatory password replacement and a recorded last sign-in.
2. **Expiring secure share links** at `/vendor-share/{token}` for a recipient who needs one vendor-scoped record without an account. A link can expose the workspace summary, one purchase document, one shipment or one Packaging List.

Both paths are managed from the vendor record’s **Portal & sharing** tab.

## Portal controls

Office users can enable or disable the vendor’s portal independently of individual users. A portal user can sign in only when:

- the vendor portal is enabled;
- the user is active and portal-enabled; and
- the vendor itself is active.

The office can create named users, deactivate/reactivate them and issue a new one-time password. Plain passwords are shown only in the response that creates or resets them; only a hash is stored.

The authenticated workspace shows published purchase orders, vendor bills and authoritative open balances, vendor-linked shipments, Packaging Lists, and a shared update timeline. Draft purchase documents are never exposed. Every purchase route verifies that the document belongs to the signed-in vendor.

## Collaboration updates

Office users and portal users can submit structured updates against a vendor-owned purchase document. Office messages appear in the authenticated workspace and any matching secure-document timeline. Available states include acknowledgement, clarification, production, dispatch readiness, planned dispatch and document submission. The update stores its author, message, expected date and source.

Secure purchase-document links may optionally permit the same updates. Such updates are attributed to the exact share link rather than to an invented user. The vendor record provides the office collaboration inbox and records when vendor-originated updates are read.

Portal updates do not silently mutate the internal purchase-document status. They are supplier evidence for the procurement team to review.

## Secure link lifecycle

Every share link has:

- a cryptographically random 64-character token;
- one vendor and optional vendor-owned resource;
- an explicit permission array;
- a required office-selected expiry of 1–90 days;
- revocation identity and time;
- first/last view times, view count and last-view IP.

Resource ownership is checked when a link is created and again whenever it is opened. A revoked or expired link renders a clear unavailable page rather than leaking the record. Public update submission is both permission-gated and rate-limited.

The office register shows live/opened/expired/revoked state and allows immediate revocation.

## Security boundaries

- Portal and office sessions use different session keys and middleware.
- Vendor IDs are derived from the authenticated portal user or share link, never trusted from a form.
- Public tokens are route-constrained and looked up directly.
- Account and share update endpoints retain CSRF protection.
- Public update endpoints are throttled.
- No internal notes or draft procurement documents are rendered in vendor surfaces.
- Secure links are vendor-specific and access is logged.

## Verification

Run `node tools/checks/vendor-portal-check.cjs` for focused coverage. Repository-wide PHP, Blade, design, SQL and module suites plus the production asset build must remain green.

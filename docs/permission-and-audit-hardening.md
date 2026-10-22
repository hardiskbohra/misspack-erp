# Permission and audit hardening

## Access profiles

Office accounts retain the existing administrator/employee identity boundary. Administrators now have one of two access profiles:

- **Full office access** preserves the existing behaviour and automatically includes future modules.
- **Custom office access** starts deny-by-default and grants explicit view, change and sensitive-control capabilities.

Employees remain confined to self-service regardless of stored permission data.

`App\Services\PermissionMatrix` is the canonical permission catalogue and route-to-permission map. The office route group applies `office.permission` after the existing employee boundary, so direct URLs and write requests are protected even when navigation is bypassed. A manage capability implies the matching view capability.

Sensitive permissions are separate from ordinary module changes:

- manager project overrides and financial closure;
- organisation settings;
- audit-register access;
- access-profile administration.

The **Access** tab on each user record shows the current boundary, who last changed it, and the complete grouped permission matrix. Users cannot remove their own access-management capability while switching themselves to a custom profile. Sidebar navigation and global search results are filtered using the same permission service; hidden navigation is not treated as the security boundary.

Existing office accounts migrate to full access to prevent deployment lockout.

## Audit register

`audit_logs` is an append-only evidence table. Its Eloquent model rejects update and delete operations. The register records:

- event and success/failed/denied outcome;
- actor type, immutable actor name/identifier snapshot and actor ID;
- subject model and ID where route binding supplies one;
- named route, HTTP method, response status and redacted URL;
- IP address and user agent;
- changed field names, never submitted secret values;
- route context and occurrence time.

The web audit middleware records every non-safe request across office, client portal, vendor portal and public secure-share actions. It also records validation/exception failures. Permission denials are written immediately with the required capability. Login success and failure receive explicit security events.

Passwords, confirmation fields, CSRF values, account numbers and tokens are excluded. Token-bearing public URLs replace the token with `[redacted-token]` before persistence.

The **Audit register** supports event, actor, outcome, search and date filters. Its evidence disclosure shows field names and request context without exposing submitted values.

## Operational rules

- Full access should be limited to trusted administrators.
- Module managers should receive custom view/manage permissions plus only the sensitive controls they actually need.
- `permissions.manage` should remain held by at least one trusted full-access account.
- Audit records must never be edited or deleted through application workflows.
- New office route families must be added to `PermissionMatrix::requiredForRoute()` before release; custom profiles deny new mapped modules until explicitly granted.

## Verification

Run `node tools/checks/permission-audit-check.cjs`. The full repository static suite and production asset build must also remain green.

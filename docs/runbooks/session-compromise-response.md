# Session compromise response

**Audience:** technical administrator / on-call operator  
**Scope:** Laravel database sessions (`config('session.table')` on `config('session.connection')`). No parallel session store.  
**Product constraints:** OTP + staff MFA remain authoritative. Owner has no clinical-document access. Do not weaken production auth.

## Symptoms

- User reports unknown device activity or unexpected OTP challenges.
- Audit events `session.revoke_*` or `session.force_revoke_all` appear without a matching user action.
- Multiple concurrent sessions from distant networks for a single account.

## Immediate containment (user self-service)

1. User opens **Profile → Active sessions** (`/{locale}/panel/profile`).
2. Prefer **Revoke other sessions** (keeps the current browser).
3. If the current device is also untrusted: **Sign out everywhere**, then re-authenticate with OTP (+ staff TOTP when required).
4. API equivalents (CSRF + session cookie):
   - `GET /api/v1/me/sessions`
   - `DELETE /api/v1/me/sessions/{id}`
   - `POST /api/v1/me/sessions/revoke-others`
   - `POST /api/v1/me/sessions/revoke-all`

Self-service actions write audited events: `session.revoke_one`, `session.revoke_others`, `session.revoke_all`.

## Operator force-logout

Use application code path `SessionInventoryService::forceRevokeAll($subject, $actor, $reason)` from a controlled console or future tech-admin UI only. It:

1. Deletes every row in the configured session table for `user_id = subject`.
2. Writes `session.force_revoke_all` plus the underlying `session.revoke_all` audit rows.
3. Does **not** change phone, TOTP secret, or role by itself — those are separate privileged flows.

Example (staging / break-glass shell only):

```php
$subject = App\Models\User::query()->findOrFail($id);
$actor = App\Models\User::query()->where('role', 'tech_admin')->firstOrFail();
app(App\Domain\Identity\Services\SessionInventoryService::class)
    ->forceRevokeAll($subject, $actor, 'compromise_response');
```

Never run destructive session deletes against production without ticket + dual control.

## Session store connection and audit consistency

Inventory and revocation query **only** the Laravel session store:

- connection: `config('session.connection')` (null = default DB connection)
- table: `config('session.table', 'sessions')`

Do not assume sessions live on the application default connection. If `SESSION_CONNECTION` points at a dedicated database, default-connection `DELETE FROM sessions` will not log anyone out.

### Same connection as `audit_events`

When the session store shares the AuditEvent connection, each revoke wraps the row delete and the corresponding `AuditEvent` insert in **one transaction**. If encrypted `reason`/`context` persist fails, the session row is rolled back and the HTTP request errors. Operators must retry; the session is still live.

`forceRevokeAll` keeps both `session.revoke_all` and `session.force_revoke_all` inside that same transaction.

### Split store (`SESSION_CONNECTION` ≠ audit connection)

Laravel cannot XA two database connections. Containment wins:

1. Delete the session row(s) on the session connection first.
2. Best-effort audit write on the application connection.
3. If the audit/encryption write fails, the exception is reported and **not** rethrown. The HTTP/API response still reports success because the sessions are already gone.
4. Treat a missing `session.revoke_*` row after a successful revoke as an audit-gap incident, not as “sessions still active”. Re-run is idempotent (delete count 0).

Never wrap the two connections in a pretend transaction.

**Production policy:** `royadarman:preflight` **fails** when `APP_ENV=production` and the resolved session connection is not the AuditEvent/default connection. Split-store is a non-production / test degraded mode only. A durable outbox is not implemented; do not invent XA.

### Staff identity / MFA changes

`royadarman:staff:provision` invalidates existing sessions on role change and TOTP/recovery replacement. New identities have no sessions.

Order, inside the user/audit connection transaction:

1. Revoke all sessions for the subject (`staff_role_change`, `staff_mfa_replaced`, or `staff_role_change_and_mfa`).
2. Persist the role/MFA attributes.

A same-connection audit failure therefore rolls back **both** the session delete and the identity update. Prior sessions cannot remain active on an already-elevated or already-rotated MFA identity.

## After containment

1. Confirm the subject can complete a fresh OTP (+ MFA) login.
2. Review `audit_events` for the subject around the incident window. On a split session store, also confirm the session table itself is empty for that `user_id`.
3. If phone number or TOTP enrollment is suspected compromised, rotate those secrets through existing identity services (do not invent a parallel auth path).
4. Document correlation id(s) on the incident ticket.
5. Do **not** disable session encryption or CSRF for troubleshooting.

## What not to do

- Do not truncate the global `sessions` table.
- Do not push session-related hotfixes to `main` without the presentation-rescue / staging review path.
- Do not expose clinical documents while investigating identity incidents.
- Do not add Sanctum personal-access tokens as a silent replacement for web session/CSRF semantics.
- Do not inventory or revoke against the default DB connection when `SESSION_CONNECTION` is set.

## Related code

- `backend/app/Domain/Identity/Services/SessionInventoryService.php`
- `backend/app/Console/Commands/ProvisionStaff.php`
- `backend/app/Console/Commands/Preflight.php`
- `backend/app/Http/Controllers/Web/ProfileWorkspaceController.php`
- `backend/app/Http/Controllers/Api/V1/SessionController.php`
- `backend/config/session.php` (`driver=database`, production `SESSION_ENCRYPT=true`)
- `backend/tests/Feature/SessionInventoryTest.php`
- `backend/tests/Feature/StaffProvisioningTest.php`

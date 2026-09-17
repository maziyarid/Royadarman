# Session compromise response

**Audience:** technical administrator / on-call operator  
**Scope:** Laravel database sessions (`sessions` table). No parallel session store.  
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

1. Deletes every row in `sessions` for `user_id = subject`.
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

## After containment

1. Confirm the subject can complete a fresh OTP (+ MFA) login.
2. Review `audit_events` for the subject around the incident window.
3. If phone number or TOTP enrollment is suspected compromised, rotate those secrets through existing identity services (do not invent a parallel auth path).
4. Document correlation id(s) on the incident ticket.
5. Do **not** disable session encryption or CSRF for troubleshooting.

## What not to do

- Do not truncate the global `sessions` table.
- Do not push session-related hotfixes to `main` without the presentation-rescue / staging review path.
- Do not expose clinical documents while investigating identity incidents.
- Do not add Sanctum personal-access tokens as a silent replacement for web session/CSRF semantics.

## Related code

- `backend/app/Domain/Identity/Services/SessionInventoryService.php`
- `backend/app/Http/Controllers/Web/ProfileWorkspaceController.php`
- `backend/app/Http/Controllers/Api/V1/SessionController.php`
- `backend/config/session.php` (`driver=database`, production `SESSION_ENCRYPT=true`)

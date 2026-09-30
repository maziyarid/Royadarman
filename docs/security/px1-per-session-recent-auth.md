# PX.1 per-session recent-authentication assurance

Status: implemented on branch `security/per-session-recent-auth`; tests NOT RUN when authored; not deployed.
Base: main f99210e05c1fef2e186d76e625164cc4ea101d8a.

## Finding (from source, not from a runtime)

`EnsureRecentAuthentication` compared the shared `users.last_authenticated_at` with a 30-minute window.
That column is written by password login, OTP verification, the passkey listener and demo access.
A login on device B therefore let device A's old session pass sensitive routes: policy publish, launch-readiness acknowledge, credential change, TOTP start/confirm/cancel/disable and passkey management.

## Change

- `SessionAssurance` stores `recent_auth_at` (integer Unix time) and `recent_auth_method` (`password`, `otp`, `passkey`) in the session only.
- Password and OTP login stamp the session before regeneration. Regeneration keeps the data; logout clears it and invalidates the session.
- A passkey listener (`App\Listeners\MarkPasskeySessionAssurance`) stamps the session. It relies on Laravel listener auto-discovery so the shared provider is untouched.
- The middleware reads only the session. Missing, non-integer, future-dated or older-than-30-minute values return 423. Inactive users and demo sessions stay 403.
- `users.last_authenticated_at` is still written for audit and compatibility. It is no longer proof for any session.

## Semantics for other clients

- Browser and cookie-session API paths: covered by this change.
- Native Android/iOS: the current API is cookie-session based and no refresh endpoint exists or is invented here. A future token or device credential must carry its own assurance timestamp bound to that credential (PX.5). It must never reuse the shared user column.
- No global admin bypass: no role skips the check.

## Rollout effect

Existing sessions have no stamp. After deployment their next sensitive action returns 423 until the user logs in again. Normal non-sensitive use is unaffected. This is intended and not an owner lockout, since ordinary login still works.

## Known gaps (NOT covered)

- Passkey listener discovery and event/login ordering are unverified against the `laravel/passkeys` package.
- Revoked-session behaviour via the sessions table is not tested here; the array session driver is used in tests.
- There is no in-session step-up endpoint; the 423 message asks for re-authentication.
- All tests are NOT RUN. Run `php artisan test --filter RecentAuthenticationSessionTest` in an isolated checkout and report real output.

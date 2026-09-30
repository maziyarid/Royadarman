# PX.5 mobile authentication, device, push and upload contract (Android and iOS)

Status: DRAFT contract. Not implemented, not reviewed, no mobile app is delivered by this slice. Base main f99210e05c1fef2e186d76e625164cc4ea101d8a. One backend and one permission model serve Android and iOS; a PWA does not satisfy either.

## 1. Verified current API (from routes/api.php, packet 3)

Client routes below are under `/api/v1` with the `web` middleware group, so today's client authentication is a cookie session with CSRF, not a native token. The provider notification callback is defined outside that group and verifies its own signature. Endpoint paths in the table are relative to `/api/v1`.

| Area | Existing endpoints |
| --- | --- |
| Login | `POST /auth/otp/challenge`, `POST /auth/otp/verify`, `POST /auth/password` (throttled) |
| Session | `POST /auth/logout`, `GET /me`, `PATCH /me/preferences` |
| Device list and revocation | `GET /me/sessions`, `DELETE /me/sessions/{session}`, `POST /me/sessions/revoke-others`, `POST /me/sessions/revoke-all` |
| Documents | `POST /cases/{case}/documents`, `GET /cases/{case}/documents/{document}`, `GET .../content` |
| Notifications | `POST /notifications/callback` (provider callback, HMAC) |

Not present: a refresh token or renewal endpoint, device registration, push token storage, API versioned error contract for mobile, resumable upload. Their absence is verified only for the files provided.

## 2. Proposed additions (all PROPOSED, need an ADR under RPH-78 M1)

1. Device credential: a per-device, revocable credential created after a successful login, bound to a device record (id, platform, app version, created, last seen, revoked). No shared or global token. Stored in Keychain (iOS) or encrypted app storage protected by Android Keystore keys. Keystore holds cryptographic keys rather than arbitrary credential strings.
2. Short-lived access plus rotating refresh credential with reuse detection: reuse of a rotated refresh credential revokes that device.
3. Recent-authentication assurance per device credential: a timestamp set only by a fresh password, OTP or passkey on that device, never read from `users.last_authenticated_at` (see PR #17).
4. `POST /devices`, `DELETE /devices/{id}`, `POST /devices/revoke-all`: list and revoke reuse the session inventory concept.
5. Push registration `PUT /devices/{id}/push`: stores a provider token only; consent and notification preferences are separate and default off.
6. Private upload: `POST /cases/{case}/documents/uploads` creates an upload with size, hash and expiry; `PUT` chunks with offset checks; `POST .../complete` verifies the declared hash then runs the existing quarantine and scan pipeline. Idempotency key required.
7. Versioned error envelope and minimum-app-version response so old clients can be refused or forced to update.

## 3. Security requirements

- Tenant, consent, assignment and credential checks stay on the server for every platform; a support identity must not gain clinical records, OPG access, diagnoses or invoices from support membership. Preserve explicitly permitted support conversations and operational metadata; do not misrepresent those existing views as clinical access.
- No PHI in push payloads, lock-screen text, analytics, app logs, clipboard or OS backups. Push carries an opaque identifier; the app fetches content after authentication.
- Staff accounts follow whatever MFA policy the owner decides (PX.2). Mobile must not offer a weaker path than the web.
- Logout and revoke-all invalidate the server credential. The app purges private cached content when logout or revocation is observed and before restoring an authorised session. An offline device cannot immediately observe server revocation, so immediate remote erasure must not be promised. Private offline caching remains prohibited until an approved expiry/encryption and revocation design exists.
- Rate limits per device and per account on login, refresh, upload and push registration.
- Deep links carry no identifiers that grant access on their own.

## 4. Negative test matrix (to implement with the endpoints)

- Login: wrong code, expired code, reused code, locked challenge (see PR #18), inactive account, staff without required MFA, patient on staff routes.
- Device credential: revoked, expired, other user's device id, replayed rotated refresh credential, refresh after logout-all, clock skew.
- Assurance: old device stays stale after another device logs in; sensitive action returns 423 until that device re-authenticates.
- Push: token registered for another user's device, token reuse after revoke, payload contains no PHI.
- Upload: wrong tenant or case, consent revoked mid-upload, oversize, hash mismatch, duplicate chunk, out-of-order offset, resume after expiry, unscanned or quarantined file never downloadable, idempotent completion.
- Authorisation parity: each staff endpoint returns the same 403/404 for the same identity on web and mobile.

## 5. Open decisions

Mobile stack and ADR, supported OS matrix, push provider and consent copy, whether any staff workflow is mobile-restricted, token lifetimes, offline data policy, signing and store accounts. None is assumed here.

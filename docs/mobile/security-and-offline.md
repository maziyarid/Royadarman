# Mobile security and offline-data requirements (PROPOSED)

Baseline: OWASP MASVS (L1 minimum for any app handling user data; L2 is appropriate here because clinical images and identity are involved). OWASP MAS names Android Keystore and iOS Keychain as examples of platform key storage.

## Credentials and storage
- Device credential and refresh credential only in iOS Keychain, or in app storage protected by an Android Keystore key. Keystore stores keys, not arbitrary secrets.
- No secrets, signing keys or API keys in the app binary. Only public values ship in the client.
- TLS 1.2 or later everywhere. Certificate pinning is a separate owner decision (rotation risk).
- Biometric unlock may gate local access to the stored credential; it never replaces server-side recent-authentication for sensitive actions.

## Session and revocation
- One revocable credential per device; no shared token. Logout and revoke-all invalidate the server record.
- Recent-authentication assurance is held per device credential and set only by a fresh password, OTP or passkey on that device (see PR #17 for the web equivalent).
- An offline device cannot learn about revocation immediately. The app must not promise instant remote erasure. It purges private cached content when it observes logout, revocation or a 401 or 403 from the server, and again at next launch before showing anything.

## Offline data lifecycle
- Default: no offline cache of clinical or financial data. Private offline caching stays prohibited until an approved expiry, encryption and revocation design exists.
- Allowed offline: neutral shell, localized strings, public clinic directory, queued upload metadata that contains no PHI.
- Cached images and thumbnails of radiographs are private data; never in shared storage, photo library or OS backups.

## Push
- Payload carries an opaque identifier only: no names, dates of treatment, diagnoses, amounts or reference numbers.
- Content is fetched after authentication. Lock-screen text is generic.
- Push consent and preferences are separate, default off, and per device.

## Uploads
- Camera and gallery access requested just in time with a clear reason.
- Strip location and unneeded metadata before upload; compute SHA-256 locally and let the server verify.
- Resumable with server-side offsets; idempotent completion; never retried in a way that creates duplicates.
- Quarantined or unscanned files are never downloadable. Radiographs are never mirrored in RTL layouts.

## Logging and telemetry
- No PHI, phone numbers, tokens or file names in logs, crash reports, analytics or clipboard.
- Redact request and response bodies in debug builds.

## Accessibility and locale
- Persian RTL first, then Arabic and English; screen-reader labels; large text; low-bandwidth behaviour; Jalali dates with Tehran time.

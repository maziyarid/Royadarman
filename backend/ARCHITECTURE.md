# Royadarman Architecture

## Decision

Keep AlmaLinux 9, cPanel, and Apache as the managed host, but replace WordPress as the case-management backend with Laravel 13 on PHP 8.3 and MariaDB 10.11. WordPress is not part of the clinical or patient trust boundary. Public pages are server-rendered Blade views; Node is not required at runtime.

## Product boundary

Royadarman provides 24/7 dental guidance and coordination, Tehran-only home dentistry, preliminary OPG review by an assigned licensed clinician, and referrals across a clinic network based on location and stated budget. It does not diagnose autonomously, sell treatment, process payments, operate a marketplace, or represent the owner as a clinician.

## Runtime topology

- Apache public root: `/home/royadarman/public_html` containing only the front controller and public assets.
- Application: `/home/royadarman/apps/royadarman-backend` containing Laravel, vendor packages, `.env`, queues, logs, and private storage.
- Database: dedicated MariaDB database/user with least-privilege application grants.
- Queue: Laravel database queue with a supervised worker; events use an outbox and idempotency key.
- Scheduler: one cron entry invokes `artisan schedule:run` every minute.
- Uploads: OPG images enter a private quarantine disk, are content-validated and hashed, scanned, then promoted to a separate private approved disk. Downloads are server-streamed after authorization and audited; no public or signed object URLs.

## Domain modules

| Module | Responsibilities |
|---|---|
| Identity | Patient OTP, staff OTP + TOTP/recovery, minimal profile, locale |
| Provider | Clinician/clinic records, licensing and expiry checks |
| Intake | Draft/submit workflow, optimistic version checks, idempotency |
| Consent | Versioned locale-specific policy text and immutable acceptance event |
| Documents | JPEG/PNG OPG validation, quarantine, scanning, retention, access audit |
| Coordination | Assignment, explicit status transitions, referral proposals/grants |
| Clinical | Append-only review revisions; publish only by assigned licensed clinician |
| Operations | Transactional outbox, locale-aware notifications, signed callbacks, purge jobs |

## Authorization invariants

- Roles: patient, coordinator, clinician, clinic representative, owner, technical administrator.
- The owner has operational visibility only and no implicit access to patient cases, OPG files, or clinical reviews.
- Patients can access only their own cases and documents.
- Coordinators can operate only within explicit workflow permissions.
- A clinician needs a current credential and an active assignment to access or publish a case review.
- Clinic representatives receive only the minimum referral grant after patient acceptance.

## Multilingual contract

Persian is default (`fa`, RTL); Arabic is RTL; English is LTR. Locale is captured with consent and queued notifications. Missing policy translation fails closed with HTTP 503. User-entered content is stored in its source language; interface strings are key-based, not machine-translated at request time.

## Security and privacy

- Secure, HTTP-only, SameSite=Lax session cookie; session ID regenerated after authentication.
- OTPs are short-lived, hashed, single-use, rate-limited, and have bounded attempts.
- Stable error codes and request IDs are returned without leaking internal 5xx messages.
- Audit and outbox records exclude document bytes, OTPs, notification bodies, and unnecessary PII.
- Retention deletes private objects and records the outcome without indefinite retry.
- Intake remains disabled until SMS delivery, ClamAV, queue supervision, approved consent copy, retention period, and operator staffing are confirmed.

## Deployment and rollback

Each release requires a source/database/public-root backup, formatter and test pass, fresh MariaDB migration, queue restart, cache rebuild, locale and security-header smoke tests, and a browser review. Rollback restores the previous public root and code release; database rollback uses the pre-deploy dump when migrations are not safely reversible. Detailed commands are maintained in `DEPLOYMENT.md`.

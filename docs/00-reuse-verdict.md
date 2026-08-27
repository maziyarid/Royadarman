# ADR-001 — Do not fork Medical-CRM

Status: **Accepted** (2026-08-27)

Repository reviewed: https://github.com/maziyarid/Medical-CRM @ c451f27

Question: May we extend Medical-CRM instead of starting from scratch?

Answer: **No.**

## What Medical-CRM is

A multi-host clinic platform for one practice: Dr. Shahin Bastaninejad.

- WordPress site: marketing + booking form
- dashboard.drbastaninejad.com: PHP 8.1 custom MVC JSON API on MySQL
- app.drbastaninejad.com/Frontend: static HTML staff CRM + patient portal

The dashboard README locks the stack: no Composer frameworks, no Laravel, no Symfony, MySQL, EMR, Google Sheets dual-write, Jalali at the presentation layer, Iranian SMS chain (Kavenegar, Ghasedak, FarazSMS, TSMS).

That is a legitimate clinic product. It is the wrong product for Royadarman.

## Why forking it would be a mistake

| Lens | Medical-CRM | Royadarman |
| --- | --- | --- |
| Product | Single-clinic ENT CRM + EMR + marketing site | Multi-clinic dental marketplace |
| Database | MySQL | PostgreSQL + PostGIS |
| Money | Clinic billing notes; no hosted PSP ledger | Orders, holds, captured payments, immutable ledger, payouts |
| Matching | None — the patient already chose this doctor | Eligibility, travel time, availability, fairness |
| Tenancy | One practice | Many clinics and hostile-tenant isolation |
| Clinical data | EMR is in-scope | EMR is explicitly out of MVP |
| Geo | None | PostGIS + routing adapter |
| Ops | Staff CRM for one clinic | Platform operations console |
| Size / hygiene | ~332 MB, WordPress core, zip backups in git | New, small, secrets never committed |

Royadarman owns request, match, hold, pay, support, and settlement. Medical-CRM is the clinic side of that sentence. Putting the marketplace inside the clinic CRM inverts the design.

WordPress in the payment and appointment path also fails the reliability bar (holds, webhooks, unique slot constraints, append-only financial history).

## Security finding (act on Medical-CRM independently)

The following appear in the public tree:

- dashboard.drbastaninejad.com/.env
- wp-config.php (README says keep it out of git)
- Multiple zip backups of the running app

Do not treat those files as documentation. Rotate every credential they may contain, make the repository private or purge history, and stop committing runtime archives. This is not optional and is not part of Royadarman.

## What we harvest (knowledge, not code)

Keep as adapter and validator knowledge:

- Mobile OTP with per-mobile, per-IP, and global rate limits
- SMS provider chain for Iran
- UTC/Gregorian storage; Jalali only at the presentation layer
- Iranian mobile normalisation and national-id checksum
- Separate patient vs staff auth audiences
- Idempotency on public intake submissions

Leave behind:

- WordPress, MU-plugins, Google Sheets as a write path
- Custom PHP MVC without a framework, MySQL schema, EMR
- SHA-256 bearer tokens as the long-term auth design
- cPanel / LiteSpeed deployment as the platform model
- clinic_id as a decorative column on a single-practice product

## Correct future relationship

Medical-CRM may remain Dr. Bastaninejad's clinic system. A later HIS/calendar adapter can speak to it, the same way it will speak to any other clinic system. That is integration, not a fork.

## Decision

Royadarman is a new private repository. Phase 0 artefacts live here. Application code starts here after Phase 0. Medical-CRM is not a parent, template, or subtree.

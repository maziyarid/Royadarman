# Phase 0 — before marketplace code

> **SUPERSEDED — historical Phase-0 plan.** This document planned work for the
> originally-proposed marketplace. The implemented product is a guidance and
> coordination service, not a marketplace. See `backend/ARCHITECTURE.md` and
> `DOCUMENTATION.md` for the current system. Retained for historical provenance.

Duration: 2-4 weeks.

Writing application code during Phase 0 freezes the wrong payment role, taxonomy, and booking mix into migrations.

## Checklist

- [ ] Confirm operating country and first city (recommended: Iran, Tehran).
- [ ] Counsel: who legally sells the dental visit.
- [ ] Counsel: who issues the invoice; who carries refund and chargeback liability.
- [ ] Counsel: whether healthcare referral commissions are permitted.
- [ ] Counsel: what identity and medical data Royadarman may retain; minors and guardians.
- [ ] Patient terms and clinic contracts, versioned.
- [ ] Cancellation and refund matrix.
- [ ] Narrow, clinically reviewed dental-service taxonomy.
- [ ] Urgency and red-flag questions with a clinical adviser. Explicitly not a diagnosis.
- [ ] Clinic credentialing procedure.
- [ ] Interview 8-12 clinics on real scheduling.
- [ ] Choose one Iranian hosted IPG with webhooks.
- [ ] Clickable patient and clinic prototypes.
- [ ] Data residency and retention schedule.
- [ ] Rotate secrets currently exposed in the public Medical-CRM repository.

## Exit criteria

Phase 1 may start when country, settlement, and commission legality are accepted or explicitly deferred with a safe implementation (visit-fee, no wallet, no escrow); taxonomy and red-flag copy exist; at least eight clinic interviews are written up; one PSP and one SMS provider are named; patient, clinic, and ops clickable flows exist.

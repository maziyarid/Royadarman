# Architecture decision records

Convention: accepted ADRs do not change in place. A reversal is a new ADR that names the cost.

> **Note on superseded ADRs.** ADR-004 (PostgreSQL/PostGIS), ADR-006 (payment
> capture), ADR-007 (double-entry ledger), ADR-014 (booking modes), and ADR-015
> (settlement) describe the originally-proposed marketplace/payment architecture.
> The implemented system is a guidance/coordination service on Laravel 13 /
> MariaDB with no payments, no marketplace, and no booking holds. These ADRs are
> retained as historical record; the implemented system superseded them. See
> `backend/ARCHITECTURE.md` for the current architecture.

## Accepted (2026-08-27)

### ADR-001 — Do not fork Medical-CRM

Royadarman is a new repository. See docs/00-reuse-verdict.md.

### ADR-002 — Marketplace, not a directory

Royadarman owns request, matching, hold, payment, support, and settlement. Clinics own diagnosis, treatment, and clinical records.

### ADR-003 — Modular monolith plus asynchronous workers

One domain application, background workers, module boundaries in code. No microservices until a real scaling or ownership reason appears.

### ADR-004 — PostgreSQL / PostGIS is source of truth

Bookings, money, and eligibility live in Postgres. Redis may cache and time holds. Redis never wins a booking dispute.

### ADR-005 — Instant slots and manual acceptance in MVP

Small clinics must join without calendar software. Manual acceptance uses a short SLA (default five minutes). Patient pays only after a viable reservation.

### ADR-006 — No money without a viable hold

Never capture payment until a clinic reservation exists. Hold expiry before a verified webhook means rematch or refund. Browser redirects never prove payment.

### ADR-007 — Immutable double-entry ledger

Completed financial rows are never edited. Refunds are reversing entries. Daily reconciliation against the gateway is mandatory.

### ADR-008 — No EMR in MVP

Referral and transactional data only. Diagnoses, imaging, prescriptions, and treatment notes stay in the clinic system.

### ADR-009 — Operations console is first-class

Credentialing, unmatched requests, payment exceptions, refunds, and settlements are product features. Staff never edit database rows by hand.

### ADR-010 — Provider adapters

Maps, payments, SMS, and clinic calendars sit behind adapters. Vendor lock is a defect.

## Proposed (need product-owner lock)

### ADR-011 — Operating country: Iran, Tehran first

Inferred from brand, Jalali/SMS/national-id work, and existing dental clinic sites. Locks PSP, maps, SMS, RTL, data residency, and health-advertising law.

### ADR-012 — MVP collects the visit fee only

Treatment-plan payments, installments, insurance, and wallets wait.

### ADR-013 — One recommendation plus two alternatives

Patient chooses. Auto-assign is rejected for MVP unless explicitly locked otherwise.

### ADR-014 — Both booking modes

Instant slots and manual acceptance both ship in the pilot.

### ADR-015 — Legal settlement model

Preferred: marketplace agent + split settlement if the Iranian PSP and counsel allow it. Until counsel signs, do not implement a wallet and do not call held funds escrow.

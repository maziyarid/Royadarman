# Royadarman architecture

Working specification for Phase 1.

## Business loop

1. Patient enters dental need and location.
2. Platform finds eligible nearby clinics.
3. A slot or acceptance hold is created.
4. Patient pays the visit fee to Royadarman (hosted checkout).
5. Signed webhook confirms; appointment is confirmed.
6. Clinic checks in and completes the visit.
7. Ledger allocates clinic payable, platform revenue, tax.
8. Clinic is settled. Refunds, cancellations, disputes are central.

Royadarman is not a clinic directory.

## Shape

Patient PWA, clinic portal, ops console, and partner API sit behind CDN / WAF / API gateway / BFF.

The modular domain app owns geo/matching/availability, orders/payments/ledger, and an outbox plus workers.

PostgreSQL/PostGIS is source of truth. Redis caches. Adapters wrap maps, PSP, SMS, and clinic calendar/HIS.

No microservices in the first release.

## Matching

Hard filters first, then PostGIS ST_DWithin, take 10-20 candidates, then routing adapter for travel time.

Default weights: 35% travel time, 30% time-to-slot, 15% service/preference fit, 10% acceptance/reliability, 10% fair distribution.

Never take payment without a viable appointment.

## Booking

Three modes: instant slots, capacity windows, manual acceptance.

A hold is a database transaction with a unique constraint, row lock, 7-10 minute expiry, server-issued token, automatic release, and idempotency.

Browser redirects never prove payment.

## Money

Hosted IPG page. Unique order before payment. Idempotency keys. Store signed webhook then process. Daily reconciliation. No edits to completed financial rows. No unlicensed wallet.

## Stack

TypeScript web apps. TypeScript modular monolith, or Laravel if the team cannot take on TypeScript. PostgreSQL + PostGIS. Redis. Real queue with retry and DLQ. Encrypted object storage. WordPress is not in the transactional path.

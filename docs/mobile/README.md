# Royadarman mobile programme (Android AND iOS) - PX.5 design package

Status: DESIGN ONLY. No mobile app, native endpoint, device credential, push provider, build, emulator run, real-device test, signed artifact or deployment exists. Authored by Perplexity from repository source read through issue #15 packets; base main f99210e05c1fef2e186d76e625164cc4ea101d8a. Nothing here was executed.

Android and iOS are two separate required deliverables on one backend and one permission model. A PWA or responsive site does not satisfy either (runbook, M0-M8).

## Contents
- `ADR-0001-mobile-stack.md`: options and a provisional recommendation (owner decision needed).
- `openapi-mobile-v1.yaml`: existing endpoints (verified from routes/api.php) and PROPOSED additions, each tagged `x-status`.
- `screens-and-denials.md`: role to screen catalogue for M3/M4 and the server-side denial rules.
- `security-and-offline.md`: app-local security, offline-data lifecycle, push, upload requirements.
- `delivery-sequence.md`: M0-M8 sequencing, store/toolchain gates with sources, and the negative contract-test plan.

## What is verified versus proposed
Verified: the current routes, validation rules and error envelope read from `routes/api.php`, `AuthController`, `bootstrap/app.php` and `SessionController`. Proposed: everything tagged `x-status: proposed` and every client-side design choice.

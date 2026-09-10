# Royadarman

Production Laravel 13 application for Royadarman's multilingual dental guidance,
coordination, support, and referral service.

## SUPERSEDED ARCHITECTURE NOTICE

> **The product description below this notice reflects the *current canonical*
> architecture and supersedes an earlier marketplace/payment design that
> previously lived in this root README and in `docs/`.**
>
> An earlier generation of this repository described Royadarman as a
> "Managed dental marketplace" that "owns patient request, matching,
> appointment hold, payment, support, and clinic settlement", with a
> PostgreSQL/PostGIS data layer, hosted IPG checkout, an immutable
> double-entry ledger, slot holds, and clinic payouts. **That design is
> superseded.** It must not be used as the current implementation
> specification, and no code should be written to restore it.
>
> The historical marketplace/PostgreSQL/payment ADRs are preserved in
> `docs/` for context, but each now carries a `SUPERSEDED` header pointing
> to the current architecture.
>
> **Current canonical architecture:** `backend/ARCHITECTURE.md`
> (Laravel 13, PHP 8.3, MariaDB 10.11, AlmaLinux/cPanel/Apache, no live
> payments, no marketplace, Tehran-only home dentistry, licensed-clinician
> OPG review, consent-driven referral).

## Current canonical product scope

Royadarman is a 24/7 dental **guidance, coordination, support, and referral
platform** — not a doctor, not a single clinic, and not a marketplace.

- 24/7 dental guidance and support centre (support/coordination availability,
  distinguished from clinician response turnaround).
- Tehran-only home-dentistry coordination (request is not confirmed service).
- Preliminary OPG review by an **assigned, licensed, verified dentist** —
  preliminary, not autonomous, not AI-authored, not a guaranteed diagnosis.
- Location- and budget-aware referral to a partner clinic network, with
  **patient consent** before any minimum-necessary data is shared.

Royadarman is **not** represented as a doctor or a single clinic. The business
owner is not automatically a clinician. The application does **not**:

- provide autonomous or AI diagnosis;
- process payments, refunds, or settlements (MVP exclusion);
- operate a marketplace, bidding, or automatic treatment purchasing;
- guarantee treatment outcomes, prices, or availability;
- expose clinical files publicly.

## Current canonical stack

| Layer | Choice |
| --- | --- |
| Framework | Laravel 13 (modular monolith) |
| Runtime | PHP 8.3 |
| Database | MariaDB 10.11 (InnoDB, utf8mb4) |
| Host | AlmaLinux 9, cPanel/WHM, Apache |
| Queue | Laravel database queue, systemd-supervised worker |
| Scheduler | `artisan schedule:run` every minute |
| Scanner | ClamAV (fail-closed) |
| Storage | Private quarantine + private approved disks; server-streamed downloads |
| Frontend | Server-rendered Blade, progressive enhancement |
| Languages | Persian (`fa`, RTL, default), Arabic (`ar`, RTL), English (`en`, LTR) |

Live patient intake remains **server-side disabled** (`INTAKE_ENABLED=false`)
until the activation gates (legal consent text, SMS, retention/backup, named
staffing/clinical lead, scanner drills) are met and explicit activation
approval is given.

## Read in this order (current, authoritative first)

1. `backend/AGENTS.md` — agent setup for the Laravel app
2. `backend/ARCHITECTURE.md` — **current canonical architecture**
3. `backend/CHECKLIST.md` — delivery checklist + open activation gates
4. `backend/DESIGN.md` — approved visual identity and accessibility contract
5. `backend/DEPLOYMENT.md` — deployment and rollback procedure
6. `backend/README.md` — backend overview
7. `Royadarman-Architecture-Review-and-Backend-Plan.md` — v3 architecture review
8. `docs/` — **historical, superseded** marketplace-era documentation

## Relationship to other repositories

Royadarman is a separate codebase from Canopy, Teznevise, Teznevisan,
BlueThesis, Kanoon Research, Medical-CRM, Dr Bastaninejad CRM, Morihub, and
any thesis-site content automation. Do not import their scope into this
repository. Royadarman may eventually be *monitored by* Canopy, but Canopy
functionality belongs in the Canopy repository.

## License

Proprietary. All rights reserved.

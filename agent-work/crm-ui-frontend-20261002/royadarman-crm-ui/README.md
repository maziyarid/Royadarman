# Roya Darman CRM UI

Frontend implementation of the Roya Darman / Smart Teb Medical CRM, built from the 128-image **CRM UI Design** reference set (Google Drive folder `1eVa8Hj5tzrwLU2ZsfTojALZMuepLYNur`) plus the existing R01–R20 registry on RPH-WU-1.

**Status: design-implementation frontend only — no backend wiring, all data is synthetic and labelled.** This tree is pending work, not accepted production code.

## Stack

- **Next.js 15** (App Router, SSG) + **React 19** + **TypeScript**
- **Tailwind CSS v4** with a design-token theme (blue/teal Persian-first system)
- **next-intl**: Persian (fa, default, RTL) · English (en, LTR) · Arabic (ar, RTL)
- **lucide-react** icons, CSS keyframe animations (reduced-motion safe), dark mode
- Build: `npm run build` · Dev: `npm run dev` · Lint: `npm run lint`

## Locales

Every screen is fully translated in all three languages (`messages/{fa,en,ar}.json`). RTL is applied via `<html dir>` with logical CSS properties (`ps-`, `me-`, `start-`), so layouts mirror correctly without mirroring dental anatomy. Language switcher lives in the topbar.

## Modules (20 routes)

| Route | Module |
| --- | --- |
| `/[locale]/dashboard` | Overview, today's schedule, quick actions |
| `/[locale]/patients` | Patient records table |
| `/[locale]/appointments` | Scheduling & capacity |
| `/[locale]/dental-chart` | Adult/child FDI tooth chart with provenance states (healthy/caries/missing/restored/implant/unknown) |
| `/[locale]/opg-review` | OPG quarantine→scan→assign→draft→sign→release pipeline |
| `/[locale]/treatments` | Treatment plans, stages, consent |
| `/[locale]/lab` | Lab orders & results |
| `/[locale]/leads` | Lead funnel & follow-ups |
| `/[locale]/invoices` | Invoices, instalments, cheques |
| `/[locale]/messages` | Unified inbox |
| `/[locale]/teleconsult` | Video consultation sessions |
| `/[locale]/staff` | Shifts, onboarding, training |
| `/[locale]/inventory` | Stock, expiry, suppliers, maintenance |
| `/[locale]/clinics` | Branches, services, capacity |
| `/[locale]/reports` | Operational metrics |
| `/[locale]/tasks` | Operational tasks |
| `/[locale]/notifications` | Notification events & preferences |
| `/[locale]/audit-security` | Audit events & privileged access |
| `/[locale]/settings` | Profile, security, integrations |
| `/[locale]/design-reference` | Gallery of all 128 design images mapped to modules |

## Design registry

`src/data/design-registry.ts` maps every Drive image (D001–D128) to its module, with thumbnail + source links. `/[locale]/design-reference` renders them grouped by module.

## Conventions

- Design tokens in `src/styles/globals.css` (`--primary` teal, `--accent` sky) — no hardcoded colors in components.
- UI primitives in `src/components/ui/` (Button, Card, Badge, Input, StatCard, DataTable, PageHeader/Reveal).
- Shared module screen pattern via `src/components/module-workbench.tsx` (stats + list + actions).
- Synthetic demo data in `src/data/mock.ts` — Persian sample records, always labelled `syntheticData`.

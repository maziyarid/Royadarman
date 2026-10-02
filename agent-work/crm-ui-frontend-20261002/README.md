# CRM UI frontend — 128-image design implementation (pending work)

Date: 2026-10-02 · Branch: `vibe/crm-ui-frontend-d9f3c2` · Agiflow: RPH-98 (design system), project Medical Websites — Operations & Growth.

## What this is

A complete frontend design implementation of the Medical CRM from the **CRM UI Design** Google Drive folder (128 webp images, folder id `1eVa8Hj5tzrwLU2ZsfTojALZMuepLYNur`). Per `AGENTS.md`, this lives under `agent-work/` as **pending, explicitly undeployed work** — it is not accepted application code and must not be served as production.

## Scope

- **Stack:** Next.js 15 App Router + React 19 + TypeScript + Tailwind v4 + next-intl + lucide-react. No backend; all data synthetic and labelled.
- **Trilingual:** Persian (default, RTL), English (LTR), Arabic (RTL). Full message catalogs for all 22 UI sections in `messages/{fa,en,ar}.json`.
- **20 module routes** (see app README): dashboard, patients, appointments, dental chart (FDI, provenance states incl. "not assessed"), OPG review pipeline (quarantine→…→released + needs-image), treatments, lab, leads/funnel, invoices/instalments/cheques, messages, teleconsult, staff, inventory, clinics, reports, tasks, notifications, audit/security, settings, and a design-reference gallery.
- **Design registry:** `src/data/design-registry.ts` maps all 128 Drive images (D001–D128, by file id) to modules; the `/design-reference` page renders them grouped by module with links to the originals.
- **Design system:** blue/teal tokens in `src/styles/globals.css` (light + dark), shared primitives in `src/components/ui/`, logical CSS properties throughout for correct RTL mirroring (dental anatomy is never mirrored), reduced-motion-safe animations.

## Verification (2026-10-02, sandbox)

- `npm run build`: compiled successfully, 66/66 static pages generated, lint clean.
- Runtime smoke (`next start`, loopback only): all 20 routes × fa/en/ar = 60/60 HTTP 200; `dir="rtl"` on fa/ar, `dir="ltr"` on en; localized titles confirmed; design-reference page renders 128 entries.

## Known limits / next steps

- Visual parity per image was implemented by module pattern, not pixel-matched: Drive's tooling cannot extract descriptions of the webp images, so the 128 images are grouped into the 19 CRM module patterns (each image is registered, embedded, and linked in the design-reference page). A human review pass against the originals should refine individual screens.
- No real API binding, auth, or persistence — every button is presentational pending the backend vertical slices.
- Two duplicate images exist in the Drive folder (identical hashes uploaded twice with "(1)" suffix); both are retained in the registry to preserve the 128 count.

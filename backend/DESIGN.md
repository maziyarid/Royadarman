# Royadarman Design System

## North Star

Royadarman is a calm, capable dental coordination centre—not a clinic and not a doctor. The interface should feel like a clear human handoff during an anxious moment: composed, legible, and specific about what happens next.

## Brand register

- Audience: patients and families seeking 24/7 guidance, Tehran home dentistry, OPG pre-review, or a budget-aware clinic referral.
- Voice: reassuring, plain, non-diagnostic, and never promotional about medical outcomes.
- Anti-references: generic hospital cyan, toothpaste green, glossy medical stock photography, AI-diagnosis language, and crowded super-app dashboards.

## Original identity

The original mark is a tooth held by two offset support arcs. It communicates dental care plus coordination without using a medical cross or copying a clinic emblem. Source of truth: `public/assets/brand-mark.svg`; favicon: `public/assets/favicon.svg`.

The service icons are an original monoline family in `public/assets/icon-*.svg`, sharing round caps, 1.8-unit strokes, and the same optical weight.

## Color tokens

Runtime source of truth: CSS custom properties in `public/assets/site.css`.

| Token | Value | Use |
|---|---:|---|
| `--lapis-700` | `#2947A3` | Primary actions, links, brand mark |
| `--lapis-900` | `#162B70` | Headings and high-emphasis states |
| `--apricot-500` | `#F2A566` | Human warmth, small highlights only |
| `--mineral-50` | `#F6F4EE` | Page canvas |
| `--ink-900` | `#18233C` | Body text |

No color is the sole carrier of meaning. Focus uses a visible lapis outline with sufficient separation from both white and mineral surfaces.

## Type and script

Use the system font stack already declared in `site.css`, prioritising native Persian/Arabic-capable UI fonts. Body text starts at 16px with generous line height. Persian and Arabic are RTL; English is LTR. Do not mirror the brand mark, numerals, or media; directional layout and chevrons may mirror.

## Layout and motion

- Reading width is bounded; service cards use asymmetric editorial spacing rather than a generic equal-card grid.
- Interactive targets are at least 44px where space permits, with visible hover, active, and keyboard focus states.
- Motion is short and functional. `prefers-reduced-motion: reduce` removes non-essential transitions.
- Media and icon dimensions are reserved to prevent layout shift.

## Content boundaries

- Always identify Royadarman as a coordination/support centre.
- OPG feedback is preliminary and must be performed by a licensed clinician; it is not a final diagnosis.
- Home dentistry is currently limited to Tehran.
- Do not imply the owner is a dentist or that a referral guarantees outcome, price, or availability.
- Intake remains visibly and server-side disabled until staffing, SMS, malware scanning, consent, and retention controls are operational.

## Localization contract

Canonical public routes are `/fa/`, `/ar/`, and `/en/`; `/` redirects to `/fa/`. Every route sets `lang`, `dir`, localized metadata, canonical URL, and complete hreflang alternates. Translation keys remain structurally identical across all three locale files. Locale switching changes route, not client-only text.

## Accessibility and stability

Target WCAG 2.2 AA. Use semantic headings, links, buttons, and lists; include a skip link; never hide focus; keep scrollbar styling usable; and verify 200% zoom, 320px width, keyboard order, and reduced motion. Decorative SVGs are hidden from assistive technology; meaningful icons have adjacent visible labels.

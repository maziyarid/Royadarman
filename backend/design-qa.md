# Royadarman production design QA

Date: 2026-09-08

## Automated evidence

- `audit_project.py ... --mode strict`: 0 errors, warnings, or violations.
- Laravel Pint: 95 files passed.
- PHPUnit: 25 tests, 81 assertions passed.
- Anti-pattern search found no browser `alert`, `confirm`, `prompt`, inline click handlers, JavaScript URLs, or removed focus outlines. The one intentional `overflow:hidden` is scoped to the decorative hero, not a shared page shell.

## Live browser evidence

- Persian: `lang=fa`, `dir=rtl`, localized title/H1/boundaries, complete SVG assets, no horizontal overflow at 1363×936.
- Arabic: `lang=ar`, `dir=rtl`, localized title/H1/intake state, three localized FAQs, no horizontal overflow.
- English: `lang=en`, `dir=ltr`, localized title/H1/scope tags and medical boundary, no horizontal overflow.
- Language menu navigates between real locale URLs; FAQ disclosure opens and reveals localized content.
- First Tab target is the visible skip link with a solid focus outline.
- Root scrollbar uses the documented lapis/soft-lapis tokens.
- Console showed no application-origin warnings/errors; browser-extension metadata errors were external to the site.

## Responsive and resilience review

- CSS provides explicit ≤900px and ≤600px transformations, a 320px minimum supported canvas, full-width mobile CTAs, single-column content, reserved image sizes, forced-colors handling, and reduced-motion handling.
- The available cloud browser viewport was desktop-sized, so narrow/mobile behavior is source-verified but should also be included in the first physical-device acceptance pass.
- Intake remains an honest status panel rather than a false working form.

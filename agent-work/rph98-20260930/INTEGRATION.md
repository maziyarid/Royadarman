# RPH-98 Worker B integration package

Status: implemented and tested in isolation. Not deployed. RPH-98 stays Planning.

Worker B, 2026-09-30. Host `host_d3b5541db7acae2a` / `server.royadarman.com`.
Proposed production integrator: Grok. No Agiflow comment was found in which Grok accepted that role. Do not deploy this package concurrently with other workers. Immediately before integration, re-hash the live files below. If they differ from the baseline, reconcile. Do not force-overwrite.

## Slice

Presentation-only redesign of the existing coordinator operations calendar (`GET /{locale}/panel/calendar`, route `panel.calendar.index`). This is not clinic slot scheduling and does not implement RPH-65.

The view still renders only the controller payload: coordination-task due dates, home-service schedule times, and referral expiry dates already scoped to the signed-in coordinator. No new query, route, role check, or migration.

## Live baseline SHA-256 (2026-09-30T16:52Z)

| File | SHA-256 |
|---|---|
| `apps/royadarman-backend/resources/views/panel/calendar/index.blade.php` | `60abda7000349f6b473beb0cc1117761f3de16752ee48e38168a68ae3e318bcb` |
| `apps/royadarman-backend/resources/views/panel/layout.blade.php` | `837f8beaec62a184bfa6c484020a89813e7764f53c781cee4d39621d647ebcee` |
| `apps/royadarman-backend/resources/views/panel/support/index.blade.php` | `3209d6b655fbc2f2767110826631ee4cd8213a5f9fa6533014e7c4d3457b3b16` |
| `apps/royadarman-backend/app/Http/Controllers/Web/OperationsCalendarController.php` | `e6ef3d8189150346bc07cb790424f1a8092ecece1ced50ac6f28bae752434add` |
| `apps/royadarman-backend/app/Support/JalaliCalendar.php` | `f8f5aa8cc755c2dc83e20ce521646023b1cae26140a1c5fc23be32d9811fd3a2` |
| `public_html/assets/workspace.css` | `12a65d5cde491c447941b1d2542126c9130d353670ac2fd8c104f54d1ce32de7` |

`public_html/assets/workspace.css` is the file the browser loads. `apps/royadarman-backend/public/assets/workspace.css` is a different, older file (`1853bdd35aa7f512027dab6f6e371c423de045631b68a119b5a8c67746cbd95f`). Do not treat them as the same asset.

## Files to add

| Destination | Package path | SHA-256 |
|---|---|---|
| `apps/royadarman-backend/resources/views/components/rph98/stat.blade.php` | `resources/views/components/rph98/stat.blade.php` | `b3c7c0779e61580b3ec0b227b7211757e062f97cdbbe14236ca4a6b35f3ac0c0` |
| `.../empty-state.blade.php` | same relative path | `3e493aed14aabf6e6c9c4afe7552a54139bf36c6f9b4aa6e6c2674912da88057` |
| `.../filter-chips.blade.php` | same | `09ac84c21ca21aeb704bf0df45e2ce808efb266b11a01a9005b159e06ef6710e` |
| `.../month-toolbar.blade.php` | same | `4e1c3ba0bdaae5d8a3776187fb6699e6fbce02c444f02acde2aa0f3fa77f0f25` |
| `.../operations-month.blade.php` | same | `0db9dcc923578fc7bdfd06afdc6037ceaead7576196cc1a5e5594e275b79127a` |
| `.../operations-agenda.blade.php` | same | `6764b08e8014722b34fc7c80db1d16bd364bdc07ee1080d8c5dcb2946576944c` |
| `public_html/assets/rph98-presentation.css` | `public_html/assets/rph98-presentation.css` | `3c6872315ba8fdde49c418d28c349a31c6cac5043f08341258575e56f36a4b22` |

New CSS mode should be `644`, owner `royadarman`. Do not edit `workspace.css`. Layout is unchanged: the calendar view emits its own stylesheet link because `panel/layout.blade.php` has `@stack('scripts')` and no style stack.

## File to replace

`apps/royadarman-backend/resources/views/panel/calendar/index.blade.php`

New SHA-256: `007b827fb75c8a358d895cd285175d3034883f0c4d28956d9fee5b64635080cd`

Unified diff: `calendar-index.diff`.

Keep the existing mode. The live file is `600`.

## Explicitly not in this patch

- `OperationsCalendarController.php`, `JalaliCalendar.php`
- layout, routes, lang files, `workspace.css`, `workspace.js`, Vite inputs
- users, memberships, policies, migrations
- support index and other panel screens

No Vite build is required. The panel does not load `resources/css/app.css`.

## Behaviour preserved

- Coordinator-only, non-demo access stays in the controller.
- Persian `jmonth` and other locales' `month` query keys are unchanged.
- Month bounds still come from `JalaliCalendar::toGregorian` of day 1 and next month day 1, then convert to UTC. Display still uses Asia/Tehran.
- Saturday-first leading blanks stay `(dayOfWeek + 1) % 7` for Jalali. The old stylesheet hid weekday labels and leading cells below 980px and collapsed the grid to two then one column. This slice keeps seven columns at tablet and mobile widths.
- Event `url`, `kind`, `title`, and `meta` are still the controller values. Task links remain the status-filtered task index. Home-service and referral links remain the case page.
- Counts are the existing summary. Persian digits are applied only in `fa` display.

## Visible changes

- Blue/teal cards, 44px month controls, kind chips that filter the events already on the page. With JavaScript off, every event stays visible.
- `امروز` links to the same calendar route without a month argument, which already means the current Tehran month.
- Agenda heading uses the existing string `panel.calendar.total` (`رویدادهای ماه`). The previous heading called missing key `panel.calendar.agenda`.
- Kind badge uses `panel.calendar.types.*`. The previous badge called missing key `panel.calendar.kinds.{kind}`. `referral_expiry` maps to existing `types.referral_deadline`.
- Empty month uses the existing empty string. There is no spinner and no retry button: invalid months never reach this view (controller returns 422) and there is no separate retry command.
- No booking, rating, revenue, clinician, holiday, or health-score control was added.

## Jalali defect, not fixed here

Verified on a verbatim copy of production `JalaliCalendar.php` (`f8f5aa8c…`), PHP 8.3.33, no database. Inside years 1398–1408 only these seams disagree:

- `1403-12`: `monthLength` is 29 and the last converted day is 2025-03-19, but `1404-01-01` converts to 2025-03-21. Gregorian 2025-03-20 is in neither month.
- `1404-12`: `monthLength` is 30, but day 30 converts to 2026-03-21 and round-trips as `1405-01-01`. That is the same instant as Farvardin 1, 1405. The controller's exclusive end for Esfand 1404 is that instant, so the extra cell does not pull in a second copy of stored events, but the cell label is wrong.

Do not paper over this with a display-only conversion. Fixing it means changing `JalaliCalendar` and the controller's month window together, with fixtures for both seams. That file is outside this slice.

Farvardin 1405 itself is consistent: 2026-03-21, Saturday, leading blanks 0, 31 days, end exclusive 2026-04-21 00:00 +03:30 (`2026-03-20T20:30:00Z` through `2026-04-20T20:30:00Z`).

## Notification correlation

Not closed. Task events still link to `panel.tasks.index` by status, not by task id or notification id. Home-service and referral events link to `panel.case`. This slice does not read the notification or delivery tables. The previously reported delivery-to-notification gap remains.

## Tests actually run

1. `python3 tests/presentation_contract.py` in the isolated package. Passed, including contrast pairs at or above 4.5:1 and a source scan that the Blade/CSS files do not mention patient, OPG, invoice, phone, diagnosis, or new queries.
2. PHP 8.3.33 on the host, class copy SHA-256 `f8f5aa8c…`, `tests/jalali_boundary_test.php` and `tests/jalali_gap_probe.php`. Synthetic only. The first boundary run failed one assertion, which is the `1404-12-30` defect above. The probe then confirmed the two seams and no others in 1398–1408. The test file was then changed to expect that known defect. Re-run it from this package before integration.
3. Chromium via Playwright on `evidence/calendar-fixture.html` (synthetic Persian labels and `SYN-1405-*` references, not production records) at 1280×800, 768×1024, and 390×844. Seven weekday labels, six leading cells on a Friday-start month, no horizontal page overflow, no page errors, visible focus, and the home-service chip hid the other agenda rows. This was not an authenticated production session.

Not run: PHPUnit (production has no `tests/`), authenticated coordinator walkthrough, SMS, payments.

## Rollback

Restore `calendar/index.blade.php` from the baseline hash above and delete:

- `resources/views/components/rph98/`
- `public_html/assets/rph98-presentation.css`

No database rollback. Clear compiled views only if Laravel has cached the old calendar view (`php artisan view:clear` as `royadarman`). Do not delete unrelated caches as root.

## Still required before RPH-98 can be accepted

Role/tenant proofs, the other screens, real backend bindings for references R02/R11/R18, the Jalali seam fix, and a live authenticated check after deploy. This package does not close those.

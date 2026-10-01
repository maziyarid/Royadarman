# Jalali calendar seam fix — 30 September 2026 (Mistral, G1.1)

Worker: Mistral (Vibe Code) · Lane: G1.1 (GitHub issue #13 / RPH-94 / RPH-65)
Base: f99210e (main, PR #11 merge)

## Requirement

The 30 Sep runbook (G1.1) requires correcting the Jalali 1403-12 / 1404-12 seam
defects with an independently justified calendar algorithm and expected
fixtures, including actual 1403-12-30 versus invalid 1404-12-30, conversion
round trips and leap bounds — without changing tests to expect a known defect.

## Hypothesis

`JalaliCalendar` mixes two different leap-year algorithms:

- `fromGregorian` / `toGregorian` embed a 33-year cycle.
- `monthLength` used the 2820-year Birashk cycle.

These two cycles disagree at the 1403/1404 seam: the 33-year algorithm (and
astronomical fact — Nowruz 1404 fell on 2025-03-21) makes 1403 leap and 1404
normal, while Birashk makes 1404 leap and 1403 normal.

## Observed failure (before fix)

- `JalaliCalendar::isValid(1404, 12, 30)` returned true (invalid date accepted).
- `JalaliCalendar::isValid(1403, 12, 30)` returned false (real date rejected).
- Round trip 1404-12-30 → 2026-03-21 → 1405-01-01 (day skipped/duplicated at seam).
- `NotificationDeliveryController` (line 149) validated delivery dates with the
  wrong cycle: it accepted 1404-12-30 and rejected the real 1403-12-30.
- `OperationsCalendarController` (line 51) rendered month grids from the wrong
  month length, producing a missing/extra day cell at leap-month seams.

## Disproof method

Probed both algorithms against independently established fixtures (Nowruz dates:
1403=2024-03-20, 1404=2025-03-21, 1405=2026-03-21, 1407=2028-03-20,
1408=2029-03-20) rather than values derived from the implementation itself.
The conversion pair matched all fixtures; `monthLength` did not. Winning
hypothesis: the conversion 33-year cycle is correct and `monthLength` is the
defect. Derived the algorithm's own leap set (1391, 1395, 1399, 1403, 1408,
1412, 1416, 1420, 1424 — ≡ {1,5,9,13,17,22,26,30} mod 33) and confirmed it is
constant across the whole practical range.

## Change

`backend/app/Support/JalaliCalendar.php`

- `monthLength` now derives Esfand length from the same 33-year leap cycle the
  conversion functions embed (new `isLeap()` helper), so validity, month grids
  and conversions can no longer disagree.
- No route, schema, provider or controller changes.

`backend/tests/Unit/JalaliCalendarTest.php` (new, 10 tests / 30,031 assertions)

- Independently justified Nowruz/seam fixtures, both conversion directions.
- 1403 leap / 1404 normal classification and rejection of invalid 1404-12-30.
- Leap-year set equals the cycle embedded in the conversions (1390–1425).
- Exhaustive bijection: every Jalali day 1390–1430 round-trips to exactly one
  Gregorian day and back, with no duplicates or gaps (14,975 days).
- Month-length rules, validation, invalid-input rejection, keys/labels/digits.

## Verification (actually observed)

- `vendor/bin/phpunit tests/Unit/JalaliCalendarTest.php` → OK (10 tests,
  30,031 assertions).
- Full suite before change (PHP 8.4.26, sqlite, GD absent): 406 tests,
  5 errors (missing php-gd only) + 3 failures.
- Full suite after change: same 5+3 pre-existing failures, zero new failures
  (fix introduces no regressions).
- After installing php-gd, the 5 environment errors cleared: 416 tests,
  31396 assertions, only 3 pre-existing failures remain.

## Pre-existing failures NOT touched (separate owners)

1. `DemoPanelAccessTest::test_demo_session_..._cannot_mutate` — expects 403,
   got 200 (pre-existing on main; support/demo mutation policy, not calendar).
2. `PatientRequestPageTest::test_..._cannot_submit_when_intake_disabled` —
   expects `id="request-form"`, view differs (Codex UI lane).
3. `PatientRequestPageTest::test_enabled_request_page_contains_fail_closed_consent_workflow`
   — expects no inline `<style>`; deployed view still inlines CSS (Codex UI lane).

## Remaining within G1 (next slices)

- G1.2: persisted midnight/month-boundary event window tests with the corrected
  calendar (controller + grid share it, but persisted event fixtures are not yet
  covered by tests).
- G1.3: outbox/delivery correlation tests.

## Limits / not done

- No production deployment (VPS inaccessible from this sandbox; SentinelX
  host-slot limitation). This is a Git-verified fix, not a deployed release.
- MariaDB-family tests (`phpunit.mariadb.xml`) not run here; no MariaDB server
  available in sandbox. SQLite suite is the executed evidence.
- No `time.ir` runtime dependency introduced or assumed; algorithm is
  self-contained per runbook rules.

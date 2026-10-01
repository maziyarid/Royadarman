# P01 identity/tenancy worklog — vibe agent (2026-10-01)

Status: in progress on branch `vibe/p01-identity-tenancy-f264e5`, PR #28 (draft).
Lane: P01 Smart Teb shared core — identity, tenancy, permissions (RPH-58/RPH-96 direction).
This file documents every step taken, with evidence, for later reference and review.

## Environment notes

- Sandbox had no PHP; installed PHP 8.4 CLI + extensions and Composer 2.8.8 via apt.
  Live target per handoff is PHP 8.3.33 / Laravel 13.29.0 / MariaDB 10.11.19; local tests
  run on SQLite in-memory (phpunit.xml), which the roadmap explicitly calls insufficient
  for final release gates (P13 requires production-family DB tests). No MariaDB runs here.
- `composer install` from the existing lock file succeeded after creating
  `bootstrap/cache` and `storage/framework/*` (gitignored runtime dirs).
- Baseline before any change: `php artisan test` → 403 passed / 3 failed.
  Pre-existing failures (untouched, present on main before my commits):
  1. `DemoPanelAccessTest::demo session can read support…` (expects 403, gets 200 at line ~420)
  2. `PatientRequestPageTest::patient can review new req…`
  3. `PatientRequestPageTest::enabled request page conta…`
  backend/AGENTS.md already states repository tests were not fully reconciled with the
  production snapshot; these three are not caused by my changes and are out of my lane.

## Commit 1 — Granular staff roles + capability map (a00f19d)

Hypothesis: the six-role `UserRole` enum is the P01 bottleneck; adding cases naively
would crash every exhaustive `match` on role.

Evidence found before editing:
- Exhaustive `match ($user->role)` sites: `PanelController`, `DashboardService`,
  `Web/DashboardController` (renders `dashboard.{$role}` view), `WorkspaceView`
  (`panelKey` match) — all would throw `UnhandledMatchError` → HTTP 500 for new roles.
- `HomeService*` controllers already use `default => false` arms (safe).
- Policies (`PatientCasePolicy`, `ClinicalDocumentPolicy`, `SupportConversationPolicy`,
  CMS policies) deny by default for unlisted roles (safe).
- `users.role` is a plain `string(32)` column with default `patient` — no migration
  needed for new enum values (migration 2026_09_08_000300, line 15).

Changes:
1. `app/Domain/Identity/Enums/UserRole.php` — added cases: `superadmin`, `developer`,
   `supervisor`, `receptionist`, `accountant`, `customer_support`, `clinic_manager`.
   Added helpers: `isClinicalSigner()` (only Clinician), `isPlatformAdministrator()`
   (Owner+Superadmin), `isPrivileged()` (Owner/Superadmin/TechnicalAdministrator/Developer),
   `dashboardFamily()` (maps granular roles onto an existing dashboard family:
   ClinicManager/Receptionist/Superadmin→ClinicRepresentative, Accountant→Owner,
   CustomerSupport/Supervisor→Coordinator, Developer→TechnicalAdministrator).
2. `app/Domain/Identity/Services/StaffCapabilities.php` (new) — central capability map.
   Capabilities: `support.view`, `support.reply`, `support.assign`,
   `support.internal_note`, `support.change_status`, `coordination.assign`,
   `network.manage`, `cms.manage`, `integration.manage`, `finance.view`,
   `reception.schedule`, `diagnostics.view`. Unknown capability denies. Patient holds none.
3. All four exhaustive matches now dispatch on `dashboardFamily()`:
   `PanelController`, `DashboardService`, `Web/DashboardController`, `WorkspaceView`.
   `DashboardService::build` sets `payload['role'] = $user->role->value` AFTER dispatch
   so the API reports the true role (keeps `DashboardTest::every role…` passing),
   while `Web/DashboardController` re-overrides with the family before choosing the view.
4. `SupportConversationPolicy` — view/reply/addInternalNote/changePriority/changeStatus/
   assign consult `StaffCapabilities`; Owner/Coordinator/Patient original behaviour kept.
5. `PatientCasePolicy::view` — `ClinicManager` joins `ClinicRepresentative` in the
   referral-grant arm (roadmap §2: clinic manager has own-tenant authority). Grant path
   still requires: active clinic + active membership + non-revoked, non-expired grant +
   accepted, non-revoked consent event.
6. `AdministratorController` — new roles added to ROLES const (owner can now grant them),
   `FIELD()` ordering list extended, role labels added to `lang/{fa,ar,en}/administrators.php`.
7. Tests: `tests/Unit/StaffCapabilitiesTest.php` (6), `tests/Feature/GranularRoleBoundaryTest.php` (8).

Bugs caught during this work (evidence the crash-risk analysis was right):
- First test run hit a real `UnhandledMatchError` in `WorkspaceView.php:28` for superadmin
  (found via `storage/logs/laravel.log`), fixed by the family dispatch.
- `DashboardTest::every role returns a dashboard payload` iterates all enum cases and
  asserts `data.role === role->value` — fixed by overriding payload role in `build()`.

Result: 417 passed / same 3 pre-existing failures. Zero regressions.

## Commit 2 — Grant lifecycle isolation + support index alignment (6da7ca0)

Hypothesis: policy code checks grant/membership/consent validity per request, but no
test proves cross-clinic isolation or that revocation is immediate (P01 exit gate).

Changes:
1. `tests/Feature/ReferralGrantLifecycleTest.php` (new, 7 tests): two synthetic clinics.
   - Clinic A member with grant → case view OK (with `Cache-Control: no-store, private`).
   - Clinic B member (same city, active membership, no grant) → 404. Cross-clinic IDOR closed.
   - Dual membership (A+B): access OK, then grant revoked → 404 immediately.
   - Grant expired → 404. Consent event revoked (with grant still active) → 404.
   - Membership expired → 404. User deactivated → 403 (EnsureActiveUser middleware denies
     before the policy; assertion adjusted from 404 to 403 after observing actual behavior).
2. Found a real inconsistency introduced by commit 1: `SupportController@index` (API)
   still 403'd roles whose `show` policy now allows them. Fixed: index gates on
   `StaffCapabilities::can(role, 'support.view')`; coordinator/patient scoping unchanged.
   Added boundary test: customer support can list; receptionist 403.
3. Verified `ConsentService::revoke…` (lines 100–121) already cascades consent revocation
   to referral grants inside one transaction with `lockForUpdate` — no code change needed;
   the lifecycle test proves it end-to-end.

Result: 425 passed / same 3 pre-existing failures.

## Commit 3 — Workspace search + web support workspace capability alignment

Hypothesis: the `support.view` capability is granted to customer support/superadmin,
but panel surfaces other than the API index still use hardcoded role lists, making the
role inconsistent between entry points.

Evidence found:
- `WorkspaceSearchController`: navigation items offered "support" nav only to
  Patient/Coordinator/Owner/TechnicalAdministrator; `supportResults()` only searched
  for Patient/Coordinator. Case results deny-by-default for granular roles (correct).
- `Web/SupportWorkspaceController@index` had the same hardcoded four-role list.

Changes:
1. `WorkspaceSearchController` — support nav + support search now allow
   Patient/Coordinator plus any role holding `support.view`. Case search untouched
   (granular roles still get no case results — deny-by-default preserved).
2. `SupportWorkspaceController@index` — gate is now Patient/Coordinator OR
   `support.view` capability. Listing scoping for Patient/Coordinator unchanged;
   capability holders see the full authorised list, consistent with the API index.
3. New boundary test: customer support opens `/fa/panel/support` OK; receptionist 403.
   `GranularRoleBoundaryTest` now 10 tests.

Result: 426 passed / same 3 pre-existing failures. `php -l` clean on all touched files.

## Verified-not-broken (checked, no change needed)

- `EnsureStaffAccess` middleware: all new roles are staff → panel access allowed;
  per-page authorization remains in controllers/policies (deny by default).
- `CaseQueueController`, `OperationsAnalyticsController`, `CoordinationTaskController`:
  explicit role checks (Coordinator/Clinician/Owner), new roles denied by default. OK.
- `ScanClinicalDocument` / `ProcessOutboxEvent` jobs: do not depend on referral grants
  or memberships; scan job re-checks document status + file hash on every attempt.
- No `Cache::remember`/result caching anywhere in controllers/domain — every request
  revalidates grants/memberships fresh from DB. No stale-access window found.
- `OtpService` staff-MFA branch: new roles are `isStaff()` → MFA required when
  configured; unchanged behavior.

## Open items in this lane (not yet done)

1. Explicit multi-membership workspace/branch chooser (roadmap §2 requires a user to
   choose an authorised workspace). Today access is the union of active memberships;
   there is no persistent "current workspace" state to constrain queries further.
   The referral-grant policy already constrains clinic reps/managers per grant, so the
   isolation requirement is met at the data level; the chooser is a UX/product decision
   needing RPH-96 ERD decisions (branch table does not exist yet — only `clinics`).
2. Reauthentication coverage for new privileged roles (superadmin/developer) on
   sensitive actions — belongs with RPH-97 audit lifecycle work.
3. Branch-level scoping: schema has no `branches` table yet; multi-branch isolation
   tests cannot be written until RPH-96 lands the ERD/migrations.
4. The 3 pre-existing test failures should be triaged by whoever owns the
   demo-panel/request-page surfaces (not identity lane).

## Reproduction commands

```bash
cd backend
composer install            # once
cp .env.example .env        # if absent
php artisan key:generate --force
php artisan test            # 426 passed / 3 pre-existing failures at time of writing
```

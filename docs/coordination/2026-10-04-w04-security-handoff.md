# W04 security handoff — 4 October 2026

## Status and ownership

Role W04; RPH-59, security acceptance under RPH-57 and continuity issue #15.
Base reviewed: `9a16918997dfa064364fda47c57b18b4f5e40618` (main/PR59).
W01 G01 grant: issue #61 comment `5977948680`. This slice writes ONLY the new
`backend/tests/Feature/W04AuthenticationAcceptanceTest.php` and this handoff.
Both paths were absent at the pinned base. The earlier proposed test filename
`W04RecoveryCodeConsumptionTest.php` is superseded by the granted name.

This is a **TESTS-ONLY / NOT_RUN application-regression handoff**. Production auth
source is unchanged. The proposed service patch below is NOT applied, not an
ownership grant, and not independently approved. W01 alone integrates/deploys.
No production data, environment, providers, schema, or MFA activation was changed.

Startup ACK: #61 comment `5977903459`. Finding: #15 comment `5977968219`.
Actual worker: foreground ChatGPT with GitHub/Agiflow and a local PHP container.
No detached runner, lease or background heartbeat is claimed.

## F-W04-20261004-01 — stale recovery inventory

Proposed severity: **HIGH**, subject to independent route/MariaDB confirmation.
Source-level counterexample reproduced; no production exploitation established.

Path/action: `backend/app/Domain/Identity/Services/StaffMfaService.php`,
`verifyAndConsume()`. Git blob: `59c38912dffaab6bce940a1bb238f7ba2d7b4204`.
Exact copied source SHA256:
`aea49e34ed507e975877160e966fa7caea2e0a2288a93aaaa9df1b2e8a592e54`.
The local `git hash-object` matched the GitHub blob before execution.

The service verifies the already-hydrated caller's encrypted-array inventory and
writes back the whole remaining array without rereading/locking the shared user
row. The password controller loads a User before this call. OTP verification
locks its challenge, not the shared user recovery inventory. The password route
still requires the primary password; this is not passwordless remote takeover.

Minimal synthetic reproduction: load User snapshots A and B before consumption.
A consumes X; B then consumes X, or B consumes Y from the original X/Y list.

| Invariant | Original exact-source harness | Proposed-source harness |
| --- | --- | --- |
| First valid use succeeds | PASS | PASS |
| Same code cannot succeed through a stale snapshot | FAIL: true twice | PASS |
| Different valid codes both remain consumed | FAIL: spent X reappears | PASS |
| Restored spent code stays unusable | FAIL: X works again | PASS |
| Cleared persisted codes invalidate stale snapshots | FAIL: stale code accepted | PASS |
| Unknown code denied and inventory preserved | PASS | PASS |

Impact: one-use and recovery-code revocation semantics can be defeated by stale
requests; a competing write can resurrect an already-spent factor. Confidence is
high for the demonstrated PHP stale-object behavior, not yet for measured live
exploitability or production-family lock behavior.

## Evidence actually executed

Local PHP CLI **8.4.23**, not the reported current Roya PHP 8.3.35.
No Laravel vendor, Composer, database driver or HTTP application runtime was
available. The owner-correct SentinelX command was platform-blocked, not retried
or bypassed. Public clone failed DNS; no complete local checkout was obtained.

The standalone harness executes the exact service source with EXPLICIT
model/facade doubles that simulate independent hydration and last-write-wins
persistence. The transaction/lock doubles do not implement real database locks.
These results are NOT Laravel HTTP, MariaDB race, CSRF, browser or device proof.

```text
2026-10-04T08:06:58Z / 11:36:58 Asia/Tehran
php reproduce_stale_recovery.php source/StaffMfaService.php
exit 1; 9 assertions; 4 failed security expectations

2026-10-04T08:10:39Z / 11:40:39 Asia/Tehran
php reproduce_stale_recovery.php proposed/backend/app/Domain/Identity/Services/StaffMfaService.php
exit 0; 9 assertions; 0 failures
```

Harness JSON, original hash-verified source and the complete harness are retained
in the downloadable W04 evidence bundle. They are not extra executable files in
this branch. Application tests below remain NOT_RUN. PHP syntax checking alone
is not a framework or behavioral test, and no Pint/full-suite pass is claimed.

## Application regressions supplied — NOT_RUN

`W04AuthenticationAcceptanceTest.php` contains 12 synthetic tests. They exercise
real Eloquent persistence and existing password/OTP routes when independently
executed. There is no mocked auth handler or recovery verifier. A one-shot
`retrieved` event deterministically places a competing code consumption after
outer User hydration, with dispatcher restoration in `finally`.

Coverage: same-code replay; different-code resurrection; replaced/cleared
inventory; stale active-account state; wrong recovery code; successful password
recovery and later replay; password route interleavings; wrong primary password;
configured-staff MFA denial plus legitimate patient password login; OTP
challenge/verification denial after interleaved consumption, persisted attempt
count, and successful retry using the remaining valid recovery factor.

The event seam is NOT a second database connection or a real lock-contention
proof. OTP delivery uses an in-process capture, and stray HTTP is prevented.
The normal framework test CSRF behavior is not browser CSRF acceptance.

## Proposed service repair — NOT APPLIED

Requested exact source grant: only
`backend/app/Domain/Identity/Services/StaffMfaService.php`, import and recovery
branch of `verifyAndConsume()`. Preserve TOTP behavior and configured-MFA policy;
no routes, controllers, User/UserRole, dependencies, schema or global UI changes.
Reread recovery inventory under a user-row lock in a transaction; reject missing
or inactive current rows; consume only current codes. The caller's stale model
is not saved by this recovery branch. Review nested OTP transaction/lock order.

Proposed file SHA256:
`35ce968964bbacc0ce7c2f220a51c10b1df98b6492d90add464ef9eeab6762b0`.
Laravel 13 documentation: `laravel/docs`, branch `13.x`, `queries.md` pessimistic
locking and `eloquent-mutators.md` encrypted casting; retrieved through Context7.
Encrypted arrays cannot be safely patched with plaintext SQL JSON operations.

```diff
--- a/backend/app/Domain/Identity/Services/StaffMfaService.php
+++ b/backend/app/Domain/Identity/Services/StaffMfaService.php
@@ -4,6 +4,7 @@
 
 use App\Models\User;
 use App\Support\DigitNormalizer;
+use Illuminate\Support\Facades\DB;
 use Illuminate\Support\Facades\Hash;
 use Illuminate\Support\Str;
 
@@ -23,21 +24,31 @@
             return true;
         }
 
-        if (! $recoveryCode || ! is_array($user->mfa_recovery_codes)) {
+        if (! $recoveryCode) {
             return false;
         }
 
-        foreach ($user->mfa_recovery_codes as $index => $hash) {
-            if (is_string($hash) && Hash::check($recoveryCode, $hash)) {
-                $codes = $user->mfa_recovery_codes;
-                unset($codes[$index]);
-                $user->update(['mfa_recovery_codes' => array_values($codes)]);
+        return DB::transaction(function () use ($user, $recoveryCode): bool {
+            // Requests can hold independently hydrated User instances. Serialize
+            // recovery consumption on the shared user row, not the OTP challenge,
+            // and never write an inventory copied from a stale caller snapshot.
+            $current = User::query()->lockForUpdate()->find($user->getKey());
+            if (! $current || ! $current->is_active || ! is_array($current->mfa_recovery_codes)) {
+                return false;
+            }
 
-                return true;
+            foreach ($current->mfa_recovery_codes as $index => $hash) {
+                if (is_string($hash) && Hash::check($recoveryCode, $hash)) {
+                    $codes = $current->mfa_recovery_codes;
+                    unset($codes[$index]);
+                    $current->update(['mfa_recovery_codes' => array_values($codes)]);
+
+                    return true;
+                }
             }
-        }
 
-        return false;
+            return false;
+        });
     }
 
     public function verifySecret(string $secret, string $code): bool
```

## Independent execution and integration gates

W01 or another independent reviewer must first verify base/source hashes and
own the isolated worktree/database. Obtain W01's single heavy-job turn before
MariaDB/full-suite work. Do not copy a production `.env`, reuse production
credentials/database, widen permissions or bypass a blocked command.

On the tests-only candidate, run the new class and record intended baseline
failures. After an explicit source grant and application of the reviewed patch,
run it again. Record actual SHA, PHP/DB versions, commands, exit and counts.
Use production-family MariaDB for the real persistence/concurrency gate.

```sh
# ONLY from an independently provisioned, synthetic Laravel backend checkout.
# Pin the installed project dependencies and isolated test environment first.
php artisan test --compact tests/Feature/W04AuthenticationAcceptanceTest.php
php artisan test --compact tests/Feature/StaffMfaLoginPathsCharacterizationTest.php tests/Feature/StaffMfaPolicyCharacterizationTest.php tests/Feature/RecentAuthenticationSessionTest.php tests/Feature/OtpAttemptCounterTest.php tests/Feature/ProfileSecurityUiTest.php tests/Feature/AdministratorRecentAuthenticationTest.php
```

Separately reproduce with TWO independent MariaDB connections/processes and
committed synthetic fixtures: hydrate both users before releasing a bounded
barrier, use the same code (exactly one success) and different codes (both
consumed, none restored), then a reset/rotation interleaving. Exercise the actual
password and OTP routes too. Preserve fake transports and current MFA policy.
Record process exits and final persisted state; no unbounded stress/load loop.

Before release: independent review of W04's patch, focused and combined suite,
CSRF/session/regeneration/returning-credential browser journeys, source/candidate
reconciliation and W01-only serial deployment under the existing release gates.
No schema migration is proposed. Source rollback must not restore a database or
resurrect consumed codes; there are no fixture writes to production.

## Current disposition and remaining acceptance

- Historical PX.1 shared-timestamp issue: source disposition RESOLVED by current
  SessionAssurance per-session timestamp and existing RecentAuthenticationSession
  regressions. No reason to reapply September patches; not newly runtime-tested.
- Reported live OTP internal error: NOT_VERIFIED in this run. Current source has
  delivery-unavailable handling and committed failed-attempt logic; that is not
  proof of a successful live OTP journey or a diagnosis of the reported error.
- Successful first credential setup / returning password browser flow: still
  NOT_VERIFIED here. New route tests supplement, not replace, browser acceptance.
- Staff MFA enrolment/reset, exhaustion of recovery-only configuration, last-owner
  safety and stale TOTP/reset races: separate policy/revocation review remains.
  This proposed patch does not silently enable mandatory MFA or remove it.
- PR28 capability-policy findings: review comments read; W02 owns current-head
  reproduction/remediation. No transfer/merge of PR28/46 and no new clinical grant.
- Cross-lane tenant/IDOR/export/clinical/payment/outbox/PWA acceptance: pending
  pinned implementation candidates. No blanket security certification follows
  from this bounded recovery test slice.

## Exact next action / sync

W01: consume the two-path tests-only handoff, independently reproduce its failing
route/persistence cases, then grant/review the single service hunk if confirmed.
W04 must not self-approve. Existing RPH-59 and RPH-57 receive the pinned PR and
state distinction; no duplicate tasks or hourly automation are created.

States: regressions AUTHORED + SYNTAX_CHECKED / application tests NOT_RUN;
proposed repair SOURCE_HARNESS_TESTED only / REVIEW_PENDING / NOT_INTEGRATED /
NOT_DEPLOYED / NOT_ACCEPTED. Repository publication is evidenced by its actual
commit/PR checkpoint, not by this pre-publication document.

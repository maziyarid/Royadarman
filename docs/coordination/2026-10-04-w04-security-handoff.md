# W04 security handoff — 4 October 2026

## Current revision: repair published; SQLite and MariaDB evidence reconciled

Role W04; RPH-59 and RPH-57; continuity issue #15 and delivery issue #61.
Base: `9a16918997dfa064364fda47c57b18b4f5e40618`.
Branch: `security/w04-auth-verification-20261004`; PR63.
Repair commit: `9da7afcc60d5f60e35e160e99d1d9fb0d74b7b57`.

This revision supersedes both the initial TESTS-ONLY status and the intermediate statement that all MariaDB proof was pending. W01's completed T02 evidence was subsequently recovered from RPH57 and verified directly in #61 comment `5978135683`. Its scoped checks must be reused on matching content, not rerun merely because this session resumed. The original complete proposal/source-harness report remains in commit `10431a10bb85a771ce229721ea9db2c9a5bf3158`; the intermediate report remains in `fdfdd9415be9b28a96906512311f657687f1eb0b`.

Authority: W01 G01 (#61 comment `5977948680`) granted the two new test/handoff files. W01 G03 (`5978095937`) granted only the recovery-code fresh-state/atomic-consumption repair in StaffMfaService. Exactly three paths differ from base:
- `backend/app/Domain/Identity/Services/StaffMfaService.php`
- `backend/tests/Feature/W04AuthenticationAcceptanceTest.php`
- `docs/coordination/2026-10-04-w04-security-handoff.md`

States: IMPLEMENTED + PUBLISHED; matching service content independently TESTED_ISOLATED on SQLite and MariaDB and source-reviewed by W01. Final published-head/combined-candidate reconciliation and production release remain W01's responsibility. PR remains draft, NOT_MERGED / NOT_DEPLOYED / NOT_ACCEPTED. W04 does not self-approve and never deploys. No production data, .env, provider, schema, MFA policy/activation or additional automation was changed.

## F-W04-20261004-01 — stale recovery inventory

Severity: HIGH for the independently reproduced application defect; no production exploitation is established. Finding #15 comment `5977968219`.

Original verifyAndConsume() checked the caller's already-hydrated encrypted-array inventory and saved the whole remaining array without rereading/locking the shared User row. Password login hydrates a User before this call; OTP locks its challenge, not the shared inventory. Two stale snapshots can reuse a code, resurrect a different consumed code, or accept a cleared/replaced inventory. Password login still requires the primary password; this is not passwordless remote takeover.

The repair rereads the current User using lockForUpdate() in a transaction, rejects missing/inactive current rows or absent inventory, and consumes only a matching hash from the locked current inventory. It does not save the stale caller. TOTP, primary-factor requirements, configured-MFA and exhaustion policy remain unchanged. No User/UserRole/controllers/routes/SessionAssurance/dependencies or migration changes.

### Content identity

| Content | Git blob | SHA256 |
| --- | --- | --- |
| Original service | `59c38912dffaab6bce940a1bb238f7ba2d7b4204` | `aea49e34ed507e975877160e966fa7caea2e0a2288a93aaaa9df1b2e8a592e54` |
| Repaired service | `fc19080617df523e77c60e87cf641994fe0e0ac2` | `35ce968964bbacc0ce7c2f220a51c10b1df98b6492d90add464ef9eeab6762b0` |
| Unchanged W04 tests | `25e5143a0d2299d720d9ec25040854c333e943b0` | `bfd1b489dd3e1808e00aed305f72029e8d300e93c47e92218e9d355b620c512d` |

The published service matches the exact proposal independently executed by W01. No expectations were weakened. Matching-content proof is not a claim that CI executed on this publication commit or that the current divergent live source passed.

## Evidence with executor and limits

### Earlier W04 source harness

PHP8.4.23; exact service source with explicitly labelled User/facade/transaction doubles. Original 08:06:58UTC /11:36:58Tehran:9assertions,4failed,exit1. Proposed 08:10:39UTC /11:40:39Tehran:9assertions,0failed,exit0. Doubles simulate hydration/last-write-wins, NOT real locks, Laravel HTTP or a browser. Full source/harness/JSON remain in the earlier conversation packet and the original handoff history, not new executable files in this PR.

### W01 independent SQLite application evidence

Sources: #61 G03 `5978095937`; PR63 review `5405052451`. Isolated PR62-based review copy; PHP8.3.35/PHPUnit12.5.34; actual Laravel routes/Eloquent; synthetic SQLite; no production .env/data and blocked outbound network.

| Scope | Result |
| --- | --- |
| Exact W04 tests on original service | 12 tests /57 assertions /8 failures, exit1 |
| Same tests on the exact repair | 12 tests /96 assertions, exit0 |
| Six existing auth/session/MFA classes | 70 tests /296 assertions, exit0 |
| Targeted service Pint | 1 file, exit0 |

The six classes are StaffMfaLoginPathsCharacterizationTest, StaffMfaPolicyCharacterizationTest, RecentAuthenticationSessionTest, OtpAttemptCounterTest, ProfileSecurityUiTest and AdministratorRecentAuthenticationTest. Failures on the original cover same/different-code stale snapshots, clearing/replacement/inactive state, and real password/OTP handlers accepting an interleaved spent factor (200 instead of422). W01 private JUnit includes `.w01-evidence/pr63-auth-red-junit.xml`. These are W01 results, not fresh W04 execution.

### W01 T02 — completed production-family concurrency proof

Direct source: #61 comment `5978135683`, posted08:32:14UTC. Execution08:31:27–08:31:46UTC /12:01:27–12:01:46Tehran. Same repaired-service SHA256 above; W01's isolated review copy, not live production.

Private MariaDB10.11.19, Unix socket only/skip_networking=1,64MiB InnoDB pool, synthetic database as cPanel owner. Actual W04 password/OTP/application class: **12 tests /96 assertions, exit0**,7.495s.

Separate PHP/PDO processes with bounded barriers verified five persisted-state scenarios:
1. Same recovery code: exactly one accepted.
2. Different valid codes: both consumed, none resurrected.
3. Cleared inventory: stale code rejected.
4. Replaced inventory: old code rejected and replacement preserved.
5. Deactivated account: rejected without code consumption.

Same-code and different-code scenarios each observed two actual InnoDB LOCK WAIT transactions behind an owned test-row lock before release. These were real processes/locks, not the earlier doubles. All five scenarios passed. Entire turn18.943s,exit0; created children exited, private MariaDB exit0, unique synthetic datadir/socket removed. No production DB/source/config/provider changes.

Receipt `.w01-evidence/t02-mariadb-result.json` SHA256 `eac965bf28983458aa3f26c368f5bb8272182fc8bd84200d35790cde0e335297`; JUnit SHA256 `f0b4eee31911f1a6ab4d5545aac7093bfad4152824e549002abf25a6c9d96f83`; probe SHA256 `38d1f215ba8499846aaace2b62277759dd031f7a20ebc54d041de88426290df4`.

W04 has read W01's exact content-bound execution record, not independently downloaded/re-executed these private receipts. Reuse this bounded proof; it does not prove every possible nested lock order, live browser journey or the latest combined release.

### This resumed W04 session

Recovered the newer checkpoint through GitHub/Agiflow instead of restarting solved work. Consumed G03; reconstructed/hash-checked the exact proposal locally, matched W01's tested service digest, passed PHP8.4.23 syntax (exit0), published the authorised service fix and verified readback blob. Updated the handoff/PR status and independently reviewed PR62.

No new Laravel/MariaDB/browser suite ran in this resumed W04 session. Earlier owner-correct VPS execution was platform-blocked and was not retried/split/bypassed. Local full-source retrieval failed DNS; Laravel/vendor runtime was unavailable. Successful later authorised GitHub publication does not imply the blocked VPS command executed.

## Regression coverage

The12 unchanged tests exercise real Eloquent and existing password/OTP routes, not dummy handlers. A one-shot retrieved event interleaves consumption after User hydration, restoring the dispatcher in finally. This event seam is deterministic application evidence, not itself a second connection. OTP transport is captured in-process; stray HTTP prevented. T02 independently supplies actual database/process contention.

Coverage: first valid use, replay, different-code no-resurrection, clear/replace, inactive current account, wrong code, legitimate password recovery and replay, wrong primary password, configured-staff MFA denial, legitimate patient password login, OTP interleaving denial, persisted attempt count and valid remaining-code retry. Framework test CSRF handling is not browser CSRF acceptance.

## Remaining release and broader acceptance

W01 must reconcile the final published head and exact service/test digests with its independently tested combined candidate. Preserve already-deployed work and inspect fresh source drift; source parity from an older run does not certify current production. Reuse unaffected SQLite/T02 proof; rerun only checks invalidated by changed dependencies/environment/contracts or final integration scope. Additional nested transaction/lock-order cases remain open where not represented by the five recorded scenarios.

Complete applicable credential-setup/returning-login/session-regeneration/CSRF browser journeys and review the final candidate independently. A successful login-page GET is not that proof. W01 alone merges/deploys using fresh source/ownership/schema preconditions, private exact backups and the shared deployment lock. Source rollback must not restore the database or resurrect consumed codes; no migration is proposed. Do not bypass blocked actions, copy production credentials or start an ungranted heavy runtime.

Known isolated commands, only when relevant proof has been invalidated:

```sh
php artisan test --compact tests/Feature/W04AuthenticationAcceptanceTest.php
php artisan test --compact tests/Feature/StaffMfaLoginPathsCharacterizationTest.php tests/Feature/StaffMfaPolicyCharacterizationTest.php tests/Feature/RecentAuthenticationSessionTest.php tests/Feature/OtpAttemptCounterTest.php tests/Feature/ProfileSecurityUiTest.php tests/Feature/AdministratorRecentAuthenticationTest.php
```

Retain these open scope items:
- Historical PX.1 remains resolved by current SessionAssurance; do not reapply September patches.
- Reported live OTP internal error and first credential-setup/returning-password browser journey remain NOT_VERIFIED as live journeys here; synthetic passing cases are not incident diagnosis.
- MFA enrolment/reset, recovery-only exhaustion, last-owner concurrency and stale TOTP/reset races are separate work; no policy activation is implied.
- PR28 capability/policy remediation belongs to W02; no clinical access expansion or bulk import of old stacks.
- Tenant/IDOR/export, quarantine/clinical release, payments, recipient reauthorisation/deduplication and PWA private-cache acceptance require their own pinned candidates. No full-security or whole-product acceptance.

## Independent PR62 review completed

W03 PR62 head `9d646acdbed56f3bdf4696800d40fb0a6b260552`: W04 COMMENT review `5406067458`, scoped PASS/no blocking finding. Complete changed controller and15 new cases read. Active-coordinator/non-demo guard precedes parsing; new selected-query string checks prevent array conversion without altering valid/default/other-locale selectors, case/assignee/referral filters, timezone windows or isLeap. No role/routes/schema/rendering changes.

W01 review `5405041165` supplies matching candidate39tests/30,262assertions and targeted Pint2files exit0. Those are reused W01 results, not new W04 tests. This is independent of W04's own PR63, not self-approval, appointment/capacity/holiday/browser proof, integration or deployment.

## Continuity and sync

StartupACK #61 `5977903459`; finding #15 `5977968219`; G01 `5977948680`; G03 `5978095937`; W01 PR63 review `5405052451`; completed T02 `5978135683`; W04 PR62 review `5406067458`.

Earlier archive W04-security-evidence-20261004.zip SHA256 `e62ccd31f755946bc27800e699fbf5aee4ea90f94f990d4f344483021ffd9dd4` was a conversation attachment. Its Agiflow upload failed UNREGISTERED_FILE_REFERENCE and remains separately SYNC_PENDING; no upload success is invented. Source/tests/current handoff are readable in PR63. Existing RPH59/RPH57 receive text checkpoints with actual write results; no task/acceptance promotion or duplicate automation.

Exact next captain action: match published PR63 repair and PR62 review to the current combined candidate, reconcile live drift without overwriting other workers, complete invalidated integration/browser/release checks, then deploy only reviewed paths under the existing controlled release process. W04 continues independent review of other ready security-sensitive candidates; no background execution is claimed.

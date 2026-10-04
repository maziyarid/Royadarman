# W04 security handoff — 4 October 2026

## Current revision: G03 repair published, release gates open

Role W04; RPH-59, security acceptance under RPH-57, continuity issue #15 and delivery issue #61. This revision supersedes the old TESTS-ONLY / NOT APPLIED status; the historical report and original proposed diff remain in this document at commit `10431a10bb85a771ce229721ea9db2c9a5bf3158`.

Base: `9a16918997dfa064364fda47c57b18b4f5e40618`.
Branch: `security/w04-auth-verification-20261004`; PR63.
Repair commit: `9da7afcc60d5f60e35e160e99d1d9fb0d74b7b57`.

Authority: W01 G01, issue #61 comment `5977948680`, granted the two new test/handoff files. W01 G03, comment `5978095937`, explicitly granted only the recovery-code fresh-state/atomic-consumption repair in `backend/app/Domain/Identity/Services/StaffMfaService.php`. No new paths or broader authentication policy are taken by this revision.

Exactly three paths differ from the base:
- `backend/app/Domain/Identity/Services/StaffMfaService.php`
- `backend/tests/Feature/W04AuthenticationAcceptanceTest.php`
- `docs/coordination/2026-10-04-w04-security-handoff.md`

States: IMPLEMENTED + PUBLISHED; matching service-content digest independently TESTED_ISOLATED and source-reviewed by W01; final published-candidate integration/concurrency/release review remains pending. NOT_INTEGRATED / NOT_DEPLOYED / NOT_ACCEPTED. PR remains draft. W04 does not self-approve; W01 alone integrates/deploys. No production data, environment, providers, schema or MFA activation changed. No detached worker or additional automation was started.

## F-W04-20261004-01 — stale recovery inventory

Severity: HIGH for the independently reproduced application defect; production exploitation is not established. Finding record: #15 comment `5977968219`.

The original `StaffMfaService::verifyAndConsume()` verifies the caller's already-hydrated encrypted-array inventory and writes the whole remaining array without rereading/locking the shared User row. Password login hydrates a User before this call. OTP verification locks its challenge, not that shared inventory. Two snapshots A/B can reuse the same code, restore a different already-consumed code, or accept codes cleared/replaced after hydration. Password login still requires the primary password: this is not passwordless remote takeover.

The repair retains the primary-factor and TOTP paths. For recovery-code verification it rereads the current User under `lockForUpdate()` inside a transaction, rejects missing/inactive rows or absent current inventory, and removes a matching hash only from that locked current inventory. The stale caller model is not saved. Configured-MFA and recovery-exhaustion policies are not changed. No User/UserRole/controller/routes/SessionAssurance/dependency or migration change.

### Exact content identity

| Content | Git blob | SHA256 |
| --- | --- | --- |
| Original service | `59c38912dffaab6bce940a1bb238f7ba2d7b4204` | `aea49e34ed507e975877160e966fa7caea2e0a2288a93aaaa9df1b2e8a592e54` |
| Published repaired service | `fc19080617df523e77c60e87cf641994fe0e0ac2` | `35ce968964bbacc0ce7c2f220a51c10b1df98b6492d90add464ef9eeab6762b0` |
| Existing W04 application tests | `25e5143a0d2299d720d9ec25040854c333e943b0` | `bfd1b489dd3e1808e00aed305f72029e8d300e93c47e92218e9d355b620c512d` |

The repaired service matches the exact W04 proposal independently executed by W01. No test expectations were weakened. The tests remain unchanged. Digest equivalence permits reuse of that focused evidence; it is not a claim that CI ran on the new publication commit or that a final combined candidate passed.

## Execution evidence and attribution

### Historical W04 source harness — not database proof

PHP8.4.23, exact source with explicitly labelled User/facade/transaction doubles:
- 08:06:58 UTC / 11:36:58 Tehran: original service, 9 assertions, 4 failed security expectations, exit1.
- 08:10:39 UTC / 11:40:39 Tehran: proposed service, 9 assertions, 0 failures, exit0.

The harness simulates independent hydration and last-write-wins persistence; its transaction/lock doubles do not implement real locks. These are not Laravel HTTP, MariaDB concurrency, browser, CSRF or device tests. The original source, harness JSON and full harness remain in the earlier owner conversation evidence bundle, not executable files added to this branch.

### W01 independent application reproduction and repair verification

Sources: issue #61 G03 comment `5978095937`; PR63 review `5405052451`. W01 used its isolated review copy based on the PR62 candidate, not W04's branch or production. PHP8.3.35 / PHPUnit12.5.34; actual Laravel routes and Eloquent; synthetic SQLite; no production .env/data; outbound network functions blocked.

| W01 command scope | Result |
| --- | --- |
| Exact W04AuthenticationAcceptanceTest on original service | 12 tests / 57 assertions / 8 failures, exit1 |
| Same tests on the exact proposed repaired-service digest | 12 tests / 96 assertions, exit0 |
| StaffMfaLoginPathsCharacterizationTest, StaffMfaPolicyCharacterizationTest, RecentAuthenticationSessionTest, OtpAttemptCounterTest, ProfileSecurityUiTest, AdministratorRecentAuthenticationTest | 70 tests / 296 assertions, exit0 |
| Targeted StaffMfaService Pint check | 1 file, exit0 |

The failing cases reproduced same-code replay, different-code resurrection, cleared/replaced/inactive state and actual password/OTP handlers accepting an interleaved spent factor (200 rather than422). Legitimate factor and wrong-primary-password cases were retained. W01's receipts remain in its private `.w01-evidence`, including `pr63-auth-red-junit.xml`. These results belong to W01's run; they are not new W04 execution or MariaDB contention evidence.

### This resumed W04 session

Recovered the newer PR63 checkpoint from live GitHub/Agiflow instead of restarting solved work. Consumed G03; reconstructed the exact proposal locally, checked the original blob/hash, matched the candidate hash against W01's tested digest, and passed PHP8.4.23 syntax checking (exit0). Published the single authorised service repair and read it back at the exact repair commit; GitHub returned blob `fc19080617df523e77c60e87cf641994fe0e0ac2`.

No Laravel/HTTP/MariaDB/browser suite was executed by this resumed W04 session. Earlier owner-correct remote execution was platform-blocked and was not retried, split or bypassed. Local full-source retrieval failed DNS and no Laravel/vendor runtime was available. GitHub source publication under the later G03 grant does not claim that the blocked VPS command executed.

## Test coverage and limits

The 12 existing regressions exercise real Eloquent persistence and existing password/OTP routes. A one-shot `retrieved` event deliberately interleaves a competing consumption after outer User hydration; dispatcher restoration is in `finally`. No test-only auth handler or mocked recovery verifier is used. OTP delivery is captured in-process; stray HTTP is prevented.

Coverage includes first valid use, same/different-code stale snapshots, cleared/replaced inventory, inactive current account, wrong recovery code, legitimate password recovery followed by replay, wrong primary password, configured-staff MFA denial, legitimate patient password login, OTP interleaving rejection, persisted attempt count and retry with the remaining valid recovery factor.

The deterministic event seam is not a second connection, actual lock contention, browser CSRF or physical-device proof. W01 application GREEN does not close these gates.

## Remaining independent release gates

W01 owns the next runtime/integration action; no new blanket execution grant is implied. Preserve the one-heavy-job rule and exact isolated ownership.

1. Pin the final PR63 head and combine with other reviewed changes in a separate candidate. Verify that the service and test digests above remain exact; rerun changed dependency/integration scope rather than repeating unchanged proof merely for counts.
2. Run bounded TWO-process/TWO-connection production-family MariaDB tests with committed synthetic fixtures: hydrate both users before a barrier, same code yields one success, different valid codes stay consumed with none restored, and reset/rotation/revocation interleavings invalidate stale inventory. Exercise the real password/OTP paths and examine nested OTP transaction/lock ordering. Record process exits/final state and lock/deadlock handling. No unbounded load test or production database.
3. Complete applicable CSRF/session-regeneration/credential-setup/returning-password browser checks. A successful login-page GET is not that proof.
4. W01 or another independent reviewer reviews the final published candidate; W04 must not approve its own fix. Only W01 merges/deploys under fresh live hashes, private exact backups and the shared deployment lock. No source rollback may restore the database or resurrect consumed codes. No migration is proposed.

Known commands for an already provisioned isolated synthetic checkout:

```sh
php artisan test --compact tests/Feature/W04AuthenticationAcceptanceTest.php
php artisan test --compact tests/Feature/StaffMfaLoginPathsCharacterizationTest.php tests/Feature/StaffMfaPolicyCharacterizationTest.php tests/Feature/RecentAuthenticationSessionTest.php tests/Feature/OtpAttemptCounterTest.php tests/Feature/ProfileSecurityUiTest.php tests/Feature/AdministratorRecentAuthenticationTest.php
```

The original expected-failure evidence should be retained, not confused with an unresolved failure on the repaired digest. Obtain the legitimate runtime turn before any full-suite/MariaDB job; do not copy a production .env or credentials, widen permissions, or retry a platform-blocked action without a genuine authorised boundary change.

## Other W04 acceptance remains visible

- Historical PX.1 shared-timestamp issue remains resolved by current SessionAssurance and existing tests; no September patch replay.
- Reported live OTP internal error remains NOT_VERIFIED as a live incident here. Source handling and synthetic route tests are not a diagnosis of that reported production error.
- First credential setup and returning-password browser journey remain unaccepted.
- Staff MFA enrolment/reset, recovery-only exhaustion, last-owner concurrency and stale TOTP/reset races are separate policy/revocation work, not silently fixed or activated by this patch.
- PR28 capability/policy remediation belongs to W02; no broad clinical access or import of old PR28/46 stacks.
- Tenant/IDOR/exports, quarantine/clinical release, payments, notification eligibility/deduplication and PWA private-cache acceptance remain tied to their respective pinned candidates; no blanket security certification.

## Independent cross-lane review completed this session

PR62, head `9d646acdbed56f3bdf4696800d40fb0a6b260552`: W04 source/diff security review recorded as COMMENT review `5406067458`, scoped PASS/no blocking finding. The active-coordinator/demo guard precedes input parsing; the new selected-query string guards prevent array conversion without changing case/assignee/referral filters, timezone windows or valid selectors. All15 new test cases were read. W01 review `5405041165` independently provides matching candidate39tests/30,262assertions/Pint2files exit0. These are reused W01 results, not fresh W04 tests. This review does not certify appointment capacity, holiday-source accuracy or browser acceptance, and performs no merge/deployment.

## Durable records and next action

Startup ACK #61 `5977903459`; finding #15 `5977968219`; G01 `5977948680`; G03 `5978095937`; W01 PR63 review `5405052451`; W04 PR62 review `5406067458`. Preserve the initial tests-only commit and all failed-hypothesis receipts in history.

The prior archive `W04-security-evidence-20261004.zip`, SHA256 `e62ccd31f755946bc27800e699fbf5aee4ea90f94f990d4f344483021ffd9dd4`, was a conversation attachment; its Agiflow attachment failed UNREGISTERED_FILE_REFERENCE and was not silently uploaded. Current source/tests/handoff are directly readable in PR63. Text checkpoints are mirrored to the existing RPH-59/RPH-57; write success is recorded separately, with no status/acceptance promotion.

Exact next action: W01 verifies the final published candidate and performs the bounded independent MariaDB/combined release gate. W04 retains security review of other ready lane candidates. No claim of uninterrupted background execution, a completed production fix, or owner acceptance.

# W01 independent verification of the W04 recovery-code repair

Recorded 4 October 2026. This is a source-publication and independent verification record, not a production release or whole-project acceptance.

## Source, authorship and scope

W04 authored the exact recovery-branch proposal in PR63 at `10431a10bb85a771ce229721ea9db2c9a5bf3158`. W01 independently reproduced, reviewed and tested it under G03, issue61 comment5978095937. This integration branch preserves W04's original tests and handoff and publishes that exact proposed service rather than leaving the fix only inside a Markdown diff. W04's own branch is not modified.

Original main: `9a16918997dfa064364fda47c57b18b4f5e40618`.
Original service SHA256: `aea49e34ed507e975877160e966fa7caea2e0a2288a93aaaa9df1b2e8a592e54`.
Published repair service SHA256: `35ce968964bbacc0ce7c2f220a51c10b1df98b6492d90add464ef9eeab6762b0`.
W04 regression SHA256: `bfd1b489dd3e1808e00aed305f72029e8d300e93c47e92218e9d355b620c512d`.
Composer lock SHA256: `72127e7d217d9a2e9672704a25e760450f528ca9a8bd264a1df8f4af39bf1b0b`.

The only application change is in `StaffMfaService::verifyAndConsume`: reread the current encrypted recovery inventory under a user-row lock and transaction, reject missing/inactive rows and consume from the current row instead of saving a stale caller snapshot. TOTP behaviour, configured-MFA policy, routes, global roles, schema and primary-factor requirements are unchanged.

## Executed evidence

W01 ran these checks in its separate cPanel-owned development checkout with no `.env`, synthetic records, a locked development dependency graph and blocked external transports. Runtime: PHP8.3.35, Laravel13.29.0, PHPUnit12.5.34.

| Check | Source/environment | Actual result |
| --- | --- | --- |
| W04's 12 application regressions before repair | Original service, in-memory SQLite, actual password/OTP routes | 12 tests, 57 assertions, 8 expected failures, exit1 |
| Same regressions after the exact repair | Isolated SQLite | 12 tests, 96 assertions, exit0 |
| Existing configured-MFA/session/OTP/profile/administrator regressions | Same repaired candidate, isolated SQLite | 70 tests, 296 assertions, exit0 |
| Targeted formatter | Repaired service | Pint1file, exit0 |
| W04's 12 application regressions on MariaDB | Private MariaDB10.11.19, Unix socket, networking disabled | 12 tests, 96 assertions, exit0 |
| Two-process recovery and revocation probe | Same repair, physical MariaDB, independent PHP/PDO processes | Five scenarios PASS; same-code and different-code each observed two actual InnoDB lock waits |

The five MariaDB scenarios were: one successful use of a shared code; two different codes remaining consumed without resurrection; cleared inventory refusing an old snapshot; replacement inventory invalidating the old code without deleting the new one; and inactive accounts refusing consumption. Fixtures were committed before independent processes hydrated them. Bounded barriers and an owned row-lock holder exposed real lock contention, not transaction doubles.

MariaDB run: 2026-10-04T08:31:27Z to 08:31:46Z, 18.943 seconds total. All test children exited, the created server exited0 and its unique synthetic data directory and socket were removed. Production data, users, configuration and queues were not touched.

Commands used PHP at `/opt/cpanel/ea-php83/root/usr/bin/php`, PHPUnit `--configuration phpunit.xml --do-not-cache-result --colors=never --fail-on-warning`; the new class was executed directly from W01's private evidence directory. Existing regression filter:

```text
StaffMfaLoginPathsCharacterizationTest|StaffMfaPolicyCharacterizationTest|RecentAuthenticationSessionTest|OtpAttemptCounterTest|ProfileSecurityUiTest|AdministratorRecentAuthenticationTest
```

MariaDB used a separately generated test configuration pointing exclusively to the new private socket. A working copy of the standard MariaDB configuration must never be pointed at the production database.

## Evidence custody

Private receipts remain in W01's `.w01-evidence/`; no SQL, application secrets, real identifiers or private logs are published in this branch.

| Receipt | SHA256 |
| --- | --- |
| `pr63-auth-proposal-junit.xml` | `dea34f7147dbcf59664cb4fd6d247e7095d502ca1d5da390dec4cc590bddf934` |
| `pr63-auth-regression-junit.xml` | `542956c7d9862fee0589939f2fe4743f4c87e5347752ff844318d525d810a92c` |
| `t02-mariadb-result.json` | `eac965bf28983458aa3f26c368f5bb8272182fc8bd84200d35790cde0e335297` |
| `t02-mariadb-auth-junit.xml` | `f0b4eee31911f1a6ab4d5545aac7093bfad4152824e549002abf25a6c9d96f83` |
| `t02-mfa-race.php` | `38d1f215ba8499846aaace2b62277759dd031f7a20ebc54d041de88426290df4` |

W01 re-read the repaired file and T02 receipt on resumption at 12:13Z. Their hashes still matched. This is integrity verification and reuse of the earlier dated results, not a new test run. Review5405052451 on PR63 and issue61 comment5978135683 record the independent outcomes.

## Integration and remaining gates

The original PR63 handoff's NOT_RUN statements describe W04's authoring environment; the table above supplies subsequent independent execution without rewriting historical evidence. This publication does not assert that a test-only PR was a production fix.

A separate owner-started delivery-finalise session owns the current production rollout. Its candidate86e7785f47acc569b557b59b9276e6768d70e71f includes a separately hashed version of this repair with changed comment wording. Preserve that candidate and its exact combined verification; reconcile source equivalence before selecting either version. Do not create a competing deployment or overwrite urgent live network/presentation changes. Issue61 comments5979803773 and5979814409 record that boundary.

Production status at this publication: NOT DEPLOYED BY THIS W01 SESSION. Integration requires a frozen current candidate, applicable independent review, fresh live hashes, private source backup and serial release under the established deployment lock. Rollback is source-only and must never restore the database or resurrect spent recovery codes.

Authenticated production-browser/physical-device recovery, first-credential setup, the reported live OTP error, stale TOTP/reset races and recovery-only exhaustion policy are not certified by this bounded repair. Twelve scoped workspaces, clinic scheduling, finance, in-app release notifications and full web/PWA acceptance remain separate open requirements. No new clinical, merchant, provider or MFA-policy activation is authorised.

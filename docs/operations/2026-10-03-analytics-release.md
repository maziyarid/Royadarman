# Owner analytics delivery — 3 October 2026

Base: `4a87ceb70b08f298fe7144ec229130d156392018` (PR52). This is a bounded
real Laravel/MariaDB dashboard slice, not the full owner/supervisor/accountant
roadmap. Publication and deployment identity belong to the final checkpoint.

## Reproduced behaviour and disposition

| Hypothesis | Before-change evidence | Disposition |
| --- | --- | --- |
| Open support enum comparison loses active conversations | Persisted open/in-progress/awaiting-patient/reopened records reported zero | Fixed with fixed enum-value SQL buckets and derived counts |
| Home/case enum grouping also fails | Independent positive baseline passed1 test/2 assertions | Refuted; correct positive path retained |
| Future or exact-end records enter report cohorts | Old queries have only a lower bound; persisted endpoint regressions fail | Fixed with one exclusive end/inclusive start window across all five cohorts |
| Invalid range can yield an incoherent selector or500 | Array query reproduced500; unknown strings echo the original selector | Fixed with a scalar allowlist and canonical30d fallback |
| Invalid first-response timestamps distort the average | Negative/future/unobserved records fail expected-value tests | Count only nonnegative recorded responses before the report end; unanswered/invalid-only remainsnull |
| Weekly charts use the wrong day/zone and omit empty weeks | Old Monday/UTC grouping and absent zero bins fail persisted fixtures | Named Tehran Saturday bins, zero weeks and clipped edge cohorts |
| Security policy blocks chart widths | Actual live CSP has style-src self; old Blade emits inline width styles | Native labelled progress elements and dedicated same-origin CSS; CSP unchanged |
| Unrestricted hydration creates unnecessary memory/privacy risk | Old implementation loads whole cohorts as Eloquent collections | Fixed bounded SQL count groups, date-only500-row paging, fixed unknown category |
| Referral status string omits actual withdrawal and recorded SLA evidence | Actual overrideWithdraw/surfaceExpiry persist flags/events while status remains proposed; two lifecycle regressions failed | Read-only effective categories: withdrawn flag, then still-proposed recorded silent-loss/expired evidence before the report end, then actual proposed/accepted/declined decision; unsupported raw text is unknown |
| Different queries can observe different committed states | Source-level multi-query concern; actual live isolation observed REPEATABLE-READ | One read transaction; separate-connection proof below, with explicit isolation precondition |

Backend baseline14 regression cases:12 failed/2 passed. A separate home/case
positive path passed before changes; a suspected defect was not blindly changed.
Original UI baseline8 cases:7 failed/1 passed. Two persisted lifecycle regressions
failed before correction. Final referral UI fixtures use actual withdrawal,
view/expiry and patient-decision records, and assert unchanged event counts on GET. Combined source resolves the
actual backend failure instead of mocking its result.

## Scope and architecture

The existing active, non-demo OWNER-ONLY endpoint remains unchanged in authority.
No accountant, supervisor, clinical or global capability is introduced. Rejected
roles execute no cohort queries. All report data is counts, fixed categorical
keys and dates; patient identity, phone, reference, clinical documents, support
subjects, messages and operational notes are not projected. Malformed status
text becomes a fixed unknown category instead of a display label. The response
is private/no-store. No schema, migration, seed, provider, credential or role
change is part of this slice.

A selected30/90/365 rolling LOCAL-day window retains Tehran wall time across
historical DST changes; SQL uses UTC second-precision [since,until) bounds.
Saturday labels are explicitly Gregorian. Weekly edge bins count only the
clipped reporting cohort. Counts show current state of records created/opened/
proposed within that range; they do not reconstruct historical status or show
all-time open inventory. Overdue means currently open/in-progress and due before
the observation instant. Response mean excludes unanswered conversations.
Unknown response is not zero. Current connection repeatable-read isolation is a
precondition for a consistent multi-query MVCC snapshot, not an assumption that
all deployments use that isolation.

Referral SLA categories use existing append-only events correlated to the proposal
and its immutable case. Current clinic is deliberately not required: reassignment
does not reset the existing proposal-wide SLA. Accepted/declined decisions
supersede historical SLA flags; withdrawal has priority. Opening the report does
not surface expiry, create lifecycle events, or infer a new deadline or cancellation.
Grant expiry is a separate access-control concept. Only event timestamps strictly
before the fixed report end count as recorded evidence. No unverified legacy
status or appointment-capacity rule is activated.

Grouped summaries derive from the same small status buckets. Trends and response
dates page500 rows at a time, with no Eloquent clinical model hydration. Paging
bounds memory, not total database work or request latency; production-scale query
budgets, indexing and load tests remain separate work.

## Real interface

Six summary cards, six status/service distributions, two weekly series and four
secondary operational counts bind to the actual endpoint. Range links preserve
the locale and expose one current selection. One page heading, section links,
visible values, labelled native charts and logical responsive CSS support
Persian/Arabic RTL and English LTR. Empty groups, zero-filled weeks, unknown
response and malformed projection states are distinct. There are no invented
revenue, treatment-progress, dental-health scores, drilldowns or export controls.

RPH98/RPH99 trace to the existing R11/R12 reference registry patterns. The original
screenshots were not freshly retrieved/reviewed in this slice; no pixel-match or
authenticated production browser claim follows from source/render tests.

## Executed verification

Local PHP8.3.6: full689 tests/34,588 assertions;
backend Pint passes. A wider repository-root Pint invocation also found existing
formatting differences in historical agent-work/check tools; those unrelated files
were not changed. The new verification harness was formatted independently. Independent final combined source review found no blocker; independent backend
review18/102 passes; two additional
temporary probes2/16 pass for1,051-row paging, all enums, weighted responses and
zero cohort queries for rejected roles. Positive/negative persisted boundary,
historical2022 Tehran DST, zero-week, private/no-store and redaction checks are
included. Source diff and exact asset-mirror checks pass.

VPS PHP8.3.33 job_c58c1efa0aeb passed at15:32:36Z: full689 tests/34,588
assertions; backend Pint379 files; isolated MariaDB33 tests/271 assertions and
the controlled repeatable-read snapshot probe passed. The actual job timestamps
are retained with its evidence; this text does not infer production browser or
provider delivery. Disposable MariaDB used a separate physical datadir/socket,
networking disabled, and was removed finally. Production DB was untouched.
A subsequent format-only verification harness check has its own checkpoint. The isolated interleaving command is
`php tools/verification/analytics-snapshot-probe.php ISOLATED_BACKEND PRIVATE_SOCKET`.
It refuses environment files/cached config/non-private sockets, forces testing
configuration and provider fakes, verifies the actual socket/database/isolation,
and uses a different PDO connection to commit a synthetic in-range case between
report queries. Required result: first summary/trend1/1, next report2/2. This
controlled interleaving is not broad concurrency, power-loss or load proof.

## Direct-root release and rollback

Capture/compare the fresh453-source baseline under the shared deployment lock.
Use the reviewed merged checkout and
`tools/deploy_source_release.py --source CHECKOUT --baseline
docs/operations/2026-10-03-analytics-baseline.json --apply` as the cPanel account.
The release must back up existing paths and track additions, clear caches, check
guest login/protected panel/analytics redirects and served assets, and verify all
after hashes. Keep the exact before identity privately; refresh release identity
atomically at0600 with the verified merge SHA. This timestamp records metadata
writing, not CI/build execution. An analytics-only change needs no heartbeat
activation, retirement or queue restart; do not disturb existing probes.

Source rollback restores exact existing paths, removes new files, restores the
recorded release identity and clears caches. No DB restore is needed for this
read-only source slice. The earlier SQL/site backup and restore rehearsal retain
their dated scope; this source backup is not a fresh SQL archive.

## Remaining roadmap and limits

RPH49/57/98/99 broad acceptance remains open. Twelve workspace/branch persistence,
capacity-safe booking, clinician dental finding/sign/release, patient dental status,
ledger/gateway/instalment/cheque rules and native Android AND iOS remain required.
MFA enrolment/recovery, clinical signers/retention, finance and app distribution
decisions are explicit activation gates. Authenticated browser/physical-device
walkthroughs, real provider delivery, alternate DB isolation, production-scale
performance and all possible future failures are not inferred from these tests.
Every current identified issue stays in the roadmap/evidence register; no overall
completion percentage or full-project Done claim is made.

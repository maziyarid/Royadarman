# Clinician saved draft preview — 3 October 2026

Pre-release baseline: PR54 merge `2b6dee2c9d4041ebacd2ac1de74509a4c0c495c9`.
All 464 source hashes matched the live app and canonical repository at
15:55:02Z under the shared deployment lock. The final checkpoint records the
later tested candidate, merge, private backup and live identity.

## Scope and boundary

Assigned, currently verified clinicians can read their own stored unsigned
review narratives before using the existing publication action. Only approved,
non-deleted, accepted and non-revoked-consent source documents in this case may
supply a preview. Existing case policy runs first. Explicit selected encrypted
fields are decrypted only after source filtering; metadata rows do not authorise
narrative or source-name disclosure. Foreign clinicians, foreign cases and
signed rows stay excluded. Support and clinic-representative projections retain
their existing clinical-content boundary.

The original narrative language, revision and actual creation date are retained;
translated labels do not translate the clinical record. Escaped text, external
CSP-safe styles and real existing create/publish bindings replace a revision-number-only
preview. An unavailable source has no preview and no publish control. Demo
sessions remain unable to mutate records. Patient and coordinator sections must
remain byte-for-byte identical to the PR54 baseline.

A source review found that the original createReview and publishReview approved
source queries omitted deleted_at. Reproduce the approved-plus-deleted invariant
counterexample in synthetic actual endpoint tests and require deleted_at IS NULL
at both entry points. This is not evidence that such a record occurred in production.
Direct document status/content endpoints also lacked this guard. Synthetic stored-byte
requests must prove deletion denial before access-event insertion or streaming.
The bounded endpoint guards retain existing record metadata and do not implement
a full deletion/retention lifecycle. No other signing, consent, credential,
assignment, role, schema or notification policy changes follow. A displayed preview is not a new legal signature or
complete dental status workflow.

## Evidence and deployment

Executed local PHP8.3.6 full suite: 738 tests / 35,191 assertions pass; backend
Pint passes. Independent final review and focused 67 tests / 599 assertions pass.
Original UI 9-case baseline: 2 pass / 7 red. Backend actual create/reload baseline:
1 pass / 6 red before the additional revocation-before-decryption regression.
Mutation baseline: 3 cases, 1 pass / 2 red (deleted-source create201 and publish200).
Direct access baseline: 9 cases, 5 pass / 4 red (own patient and assigned verified
clinician status200 and actual stored bytes streamed200 with success access event).
Final deleted mutation and read requests are denied before the corresponding
clinical/publication/audit changes or stream; clean sources retain positive paths.
Patient/coordinator Blade blocks and shared JS are byte-identical to PR54.
Isolated VPS PHP8.3.33 also passes the full 738 tests / 35,191 assertions; backend
Pint passes 391 files. Focused private MariaDB passes 85 tests / 810 assertions,
job_f8eb78e0202f finished 16:04:13Z. The separate physical datadir/socket has
networking disabled and is removed finally; production DB is untouched. Production
DB, patient contents and provider delivery are excluded. Authenticated production
browser and physical-device walkthroughs remain unproven.

Publish an expected-head reviewed merge and deploy directly to the root as
already authorised. Compare the complete fresh source baseline under the shared
lock, create a private source backup with old hashes and new-path tracking, clear
caches and verify all resulting source hashes and guest HTTP/static probes.
Back up exact release identity bytes and mode before its atomic private update.
This slice needs no migration, live DB writes, queue restart or probe activation.
The earlier dated SQL/site backup and private restore retain their own scope;
this source archive is not a fresh SQL dump.

RPH107 broad acceptance remains open. Tooth findings, signer attribution,
amendment/retraction/history, clinical release notifications (F-2026-10-03-07),
clinic ledgers, capacity booking, scoped workspaces and native Android/iOS remain
separately tracked unfinished scope. No new activation decision is invented.

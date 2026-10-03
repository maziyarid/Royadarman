# Patient document progress and existing released reports — 3 October 2026

Pre-release source baseline: PR53 merge
`a00407c5856d94cedd9ea94ca16705cd6c7a1c19`, freshly compared against459 live
application/asset hashes at15:37:28Z. The final checkpoint owns later merge,
deployment and backup identities; this baseline is not a permanent main pin.

## Scope

This bounded slice improves the already-existing patient case page. It does not
implement the complete tooth-level dental status/workbench milestone. Clinical
taxonomy, dentist/signature attribution, amendments/retraction, history,
retention and patient acknowledgement still have separately tracked decisions.
No diagnosis, numeric health score, tooth chart, clinic attribution or new mobile
API is inferred from an uploaded file or existing narrative.

The original backend already restricts patient reviews to their case, signed
records, at least one published event and no superseding revision. Preserve that
gate and existing case/document/consent policies. Only current released narrative
fields and actually stored signing/publication metadata may be projected. The
first recorded published-event date is provenance of this existing record, not
a historical credential or a cryptographic/legal signature attestation.

An approved document denotes the existing file-security processing status; it
does not prove dental health, completeness of examination or a clinical review.
No document, processing, rejected/failed processing and no released review need
clear separate copy. Missing clinical evidence stays unknown. Original narrative
language is retained; translated labels do not translate clinical conclusions.

The existing upload form, secure file links, referral decision bindings and demo
mutation restrictions remain bound to their real endpoints. Signed clinical
records are never modified by presentation. Existing referral-view tracking on
patient case reads is preserved, so the entire route must not be described as
a completely write-free operation.

## Reproduced findings and limits

Persisted baseline8-case tests reproduced five failures/errors: authorised
patient/coordinator/demo responses were no-cache/private instead of no-store,
released reviews lacked their first published-event date, and withdrawn proposals
lacked an effective withdrawal projection. Three positive cases already passed.
Case policy still runs before projection; no stronger authority follows from
adding metadata or response cache protection.

Actual referral withdrawal leaves statusproposed and setswithdrawn_at. The old
patient projection omitted that flag and offered stale decision controls. The
decision endpoint already refuses withdrawn proposals. Project the flag and
effective withdrawal display, preserving real proposed positives and existing
decision policy; no new deadline, capacity or cancellation rule follows.

Publication and scan state/provenance fixtures must be synthetic. No production
patient content, document bodies, scanner references, storage keys or session
credentials belong in logs, Git or Agiflow. Existing support/coordinator/clinic
representative projections must remain free of clinical narrative. Native Android
and iOS, authenticated browser/physical-device walkthroughs and real scanner or
notification delivery are not proven by source/render tests.

## Verification and release

Executed verification: local PHP8.3.6 and isolated VPS PHP8.3.33 full709 tests/
34,818 assertions pass; backend Pint384 files; private synthetic MariaDB50 tests/
418 assertions pass (job_b78a7f4e5768, finished15:47:37Z). The temporary physical
datadir/socket had networking disabled and was removed finally; production DB
was untouched. Independent combined patient/UI/source review found no blocker;
independent25 tests/256 assertions cover persisted projection/supersession and UI.
Original10 UI regression cases reproduced7 failures/3 positives before corrections.
A separate actual API journey creates an approved-consented document review as
a currently verified assigned clinician, proves patient text withheld before
publication, publishes through the real endpoint, verifies all five fields and
recorded metadata on the patient page, and denies a foreign patient. These are
synthetic automated HTTP/render tests, not a production human-signature or
authenticated browser/real scanner/recipient delivery demonstration. Use isolated local/VPS PHP and a separate
physical synthetic MariaDB socket with networking disabled; never the live DB.
Prove own patient access, foreign/inactive/demo denial, draft/signed-only/
superseded exclusion, source-faithful dates/states, escaped narrative, three
locales, approved-only secure links, unchanged clinical/publication rows and
preserved referral-view behavior. Verify role/tenant negatives against actual
policies, rather than mocking them away.

Preflight found only the existing Http/Controllers parent non-writable as the
cPanel account. Under the shared lock, all459 source hashes were compared,
that single directory owner changed971:970→1001:1002 nonrecursively, and mode0755
plus all source bytes were preserved. Private0600 cPanel-owned ownership record:
/home/royadarman/royadarman-source-backups/codex-patient-controller-parent-20261003.json,
SHA2563b335561f7582e94501c1ca16b1d54561b7d0b937610e524141d78f35b1eac89.
No broad ownership/permission change or database privilege expansion occurred.

Direct-root release uses the fresh459-source baseline under the shared deploy
lock, verified expected-head merge, current private source backup, cache clear,
HTTP probes and all resulting hashes. Preserve exact release-identity bytes/mode
before an atomic0600 metadata update. This source-only slice needs no migration,
DB restore, queue restart or heartbeat activation. Source rollback restores old
paths and removes tracked additions. The14:18Z SQL/site archive and successful
isolated restore retain their dated scope; a source backup is not a new SQL dump.

Source-verified notification gap F-2026-10-03-07 is recorded on RPH85/RPH109:
publishReview has no outbox event, review template or review-recipient binding.
The page makes no notification or turnaround promise. A separate PHI-free
committed-release/idempotency/recipient/preferences/retry contract is required.

RPH108 broad acceptance remains open: this is the factual existing released
narrative projection, not completed clinical dental status. RPH109 and workbench
source ownership remain separate; no new signer/role/schema authority is granted.

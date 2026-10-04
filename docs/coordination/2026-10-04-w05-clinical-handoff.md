# W05 clinical journey validation — 4 October 2026

## Ownership and source

RPH-50 / RPH-109, RD-DELIVERY-20261004-v2, canonical issue61. W01 G01 comment5977948680 grants only the new W05ClinicalReleaseJourneyTest.php and this handoff. W02 clinical handoff5977956669 received. Existing DocumentController::status source grant requested in comment5978086358, not received at this checkpoint. No existing application path edited.

Base9a16918997dfa064364fda47c57b18b4f5e40618; branch w05/clinical-release-validation-20261004; isolated worktree /home/royadarman/apps/royadarman-repo/agent-workspaces/w05-clinical-20261004, owned by royadarman. The accompanying GitHub checkpoint pins the candidate commit. W02 retains identity/guardian/tenant invariants, W08 notification sender/outbox, W07 shared views, W01 integration/deployment.

## Actual tests and reproducibility

Original baseline at2026-10-04T08:13:53Z /11:43:53 Tehran: 19 tests /190 assertions PASS, exit0, filter PatientCaseIntakeTest|ClinicalDocumentPipelineTest|PatientCaseReviewWorkspaceTest|ReviewSupersessionTest.

Final new test at2026-10-04T08:29:20Z /11:59:20 Tehran: **16 tests /317 assertions, 11PASS and5FAIL, exit1**. The five failures are all status polling: expected200, actual404. Pint on the single test and PHP syntax PASS. Test SHA256 f746fb5b7cad20967f140d25e658530f3fdb1ddd37fb37dee8c31fceaa2304e9.

PHP8.3.35 /Laravel13.29.0 /PHPUnit12.5.34; synthetic SQLite :memory: and fake private/quarantine storage.127 exact-lock packages, unchanged composer.lock SHA25672127e7d217d9a2e9672704a25e760450f528ca9a8bd264a1df8f4af39bf1b0b. No production .env or cached configuration. Queue/mail fakes, HTTP stray requests blocked, fake scanner injected into real scan job. No live patient data or provider sends.

Reproduce from the backend in the isolated worktree, with exact-lock dependencies/runtime directories present. Strip inherited environment, use own HOME/PATH, and run:

    /opt/cpanel/ea-php83/root/usr/bin/php -d memory_limit=256M vendor/bin/phpunit tests/Feature/W05ClinicalReleaseJourneyTest.php --colors=never --fail-on-warning

Ignored coverage/w05-evidence contains baseline-runtime-ready.xml, w05-journey-red-fixed-inputs.xml, w05-revocation-baseline.xml and w05-final-red.xml. Earlier missing bootstrap/cache and invalid synthetic API input failures were corrected against actual source; they are not application defects. No assertion was weakened to conceal a failure.

## Passing journey and limitations

Actual draft/submit endpoints persist one no-OPG request, consent and coordinator assignment. Same-key submit replay is idempotent. Existing authenticated patient session revisits the own case page; this is not an OTP-delivery/login test.

Connected OPG journey: actual consent endpoint -> upload -> quarantine -> actual scanning job with fake scanner and hash-checked private promotion -> actual coordinator assignment -> authorized clinician source stream -> persisted draft -> clinical publication -> own-patient released report visible after repeated server-rendered page requests. Draft hidden from patient; foreign patient/owner/technical administrator denied; duplicate publication rejected without a second publication row. These are existing behaviours verified, not newly implemented clinical features.

Actual upload rejects mislabeled data. Simulated scanner outage retains quarantine and never exposes approved bytes. Actual consent revocation after draft blocks signing. New negatives preserve released-assignment, expired/unverified-credential, deleted-source and wrong-case boundaries. Some revocations are persisted administrative fixtures, not proof that administrative UI exists.

## W05-F01 reproduced defect and proposed fix — NOT APPLIED

backend/app/Http/Controllers/Api/V1/DocumentController.php::status currently uses the view policy, which demands Approved bytes. This returns404 for the owning patient polling quarantined/scanning/rejected/scan_failed, and for the assigned verified clinician polling quarantined. The existing patient case view already shows the correct persisted state.

The existing ClinicalDocumentPolicy::learnStatus checks current participation without demanding approved bytes. Proposed exact change: use learnStatus ONLY in DocumentController::status, with a brief invariant comment. Preserve same-case/deleted-source guards, minimal private/no-store metadata, content byte guards, policy, routes and role enforcement. This is not a permission grant to administrators or unscanned files. W01 exact source grant and W04 independent review required. There is no green proof of this proposed fix yet.

## Remaining gates and next action

F07 is still open with W08: signed revision + publication event does not create recipient-bound in-app notification. W08 checkpoint5978053293 confirms generic outbox scalar payload can reach SMS parameters. Do not place private recipient/eligibility/clinical metadata there. The PHI-free versioned release event needs a transactional producer and bound W08 handler, separated parameters, deduplication and current recipient/consent/source checks. Availability, unread and delivery remain distinct. No second sender or event-only delivery claim.

No invented case.clinic_id or clinical signer authority from the new W02 workspace interface. Existing signed/published/unsuperseded patient projection and source-deletion guards preserved.

NOT_RUN: MariaDB lock/race proof; real scanner/provider; actual browser/physical-device/PWA journey; interrupted upload and storage-outage recovery; integrated cross-module candidate. No-OPG intake tested, no-OPG signed clinical report not delivered. Tooth/surface findings, clinical/SOAP/history, treatment plans, follow-up and profile integration remain required later slices. Clinical taxonomy, real signers, retention/guardian and advanced scoring/AI/3D activation require evidenced owner decisions, not invented defaults.

Test artifact IMPLEMENTED and TESTED_ISOLATED with red cases. Application fix NOT_IMPLEMENTED. Independent REVIEWED /INTEGRATED /DEPLOYED /ACCEPTED: NO. No migration, production backup/rollback action, clinical release or new scheduler.

Next: obtain W01 status-method grant; apply only that hunk; rerun the same sixteen tests and affected existing document/consent/deletion/projection tests; obtain W04 review; provide pinned green candidate to W01. Do not merge this red validation packet as a completed feature. Reuse matching evidence rather than repeating setup.

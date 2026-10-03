# Calendar, clinic isolation and personal-profile release

Base: main `53ce7428df9a9c50c7033a6043d5f3429d441b79`. Owner authorisation
covers continued development, merging and backed-up root deployment. Issue #13,
#14 and RPH-49 contain the bounded integration and file claims. Publication and
deployment identifiers belong in their subsequent checkpoint comments.

## Behaviour and evidence

- Fixed a clinic dashboard relation predicate whose ungrouped `OR` could include
  another user's future-expiring membership. The projection now requires the
  current user's started, unexpired membership in an active clinic, and a live
  referral grant with accepted, non-revoked consent. Inactive/future memberships,
  expired grants and revoked consent disappear on the next request. Pending
  logistics remain count-only. Removed the unused patient-name eager load.
- Integrated the month-window helper from Grok 1's pinned PR47 head `6cb0596`.
  Current JalaliCalendar/public leap API remains intact; alternate PR45 algorithm,
  week projections and scheduling proposals are not imported wholesale. The
  Persian grid and persisted queries share the same half-open UTC bounds. The
  HTML declares the bounds/timezone; clients must not recalculate them.
- Stored-event fixtures cover leap Esfand, Farvardin 1405 and Tehran's historical
  daylight-saving changes. Tasks, home services and referral-expiry entries are
  checked immediately outside/inside each window, without patient names/notes.
  Reassignment and role denials are tested against actual routes.
- Fixed accepted-referral outbox recipient resolution: the event aggregates a
  ReferralProposal, not a PatientCase. Only an accepted, non-withdrawn persisted
  proposal supplies its own case/patient. Payload recipient overrides never
  choose a destination. A real synthetic acceptance correlates the grant/calendar
  entry and fake delivery; duplicate decisions/jobs and provider retries retain
  one delivery/idempotency key. Invalid aggregates do not call a provider.
  The existing template acknowledges recorded acceptance; no new reminder or
  consent-suppression policy is silently introduced.
- Personal profile API/web projections use the signed-in account and fresh own
  active clinic affiliations/credential status. Explicit allowlists exclude
  licence numbers, credential secrets, phones and clinical records. Responses
  are private/no-store. Membership display is not an access grant.
- Name/language changes write a redacted attributable audit in the same
  transaction. An audit failure rolls back the update. Privilege/phone/credential
  writes are not accepted by this preferences path.
- Redesigned the real profile workspace: responsive section navigation, visible
  linked validation errors, actual affiliations/verification status, CSP-safe
  secret selection/copy with denied-permission fallback, external confirmations,
  submit progress/duplicate prevention and browser-back recovery. Existing eleven
  route bindings and CSRF/method tokens are retained. Persian/Arabic/English copy
  is provided; Gregorian account timestamps explicitly identify Tehran time.

## Verification and limits

Local PHP8.3.6 full suite: 568 tests /33,607 assertions. VPS PHP8.3.33 isolated
SQLite full suite: same result; global Pint passes all360 PHP files. Independent
focused review/tests: 29 tests /343 assertions. JS syntax and DOM-adapter behaviour
checks pass. Synthetic Blade render tests cover populated/empty/error states.

Private MariaDB on the VPS, synthetic fixtures only, private socket with networking
disabled: 52 tests /475 assertions pass across clinic isolation, calendar stored
events, profile UI/API/audit, referral notification and callback races. Initial
seven errors were an overlong public-reference fixture, corrected to the existing
schema width; no migration or schema relaxation. The temporary database process,
directory and config were removed. This is a single-process DB-family proof, not
a concurrent booking/capacity race proof or production-patient exercise.

Browser executable is absent locally. No screenshots, authenticated production
browser journey, physical mobile-device or real-SMS results are claimed. Previously
attached screenshot paths are unavailable in this workspace; the dated visual
reference registry remains historical evidence.

Read-only clock check: chronyd active, preferred ntp.time.ir resolves to selected
185.192.112.101; stratum5, normal leap status and sub-millisecond RMS offset at
the observed sample. No clock configuration change, external calendar dependency
or unversioned holiday dataset is added.

## Release and rollback

Fresh baseline manifest captures433 live app/assets/Studio hashes at released
main53ce7428. Use the reviewed checkout and explicit new baseline:

```
python3 tools/deploy_source_release.py --source CHECKOUT \
  --baseline docs/operations/2026-10-03-calendar-profile-baseline.json
```

Apply only after a successful dry run, as cPanel UID1001, adding `--apply`.
Existing script locking, private before-hash backups, atomic source replacement,
HTTP checks and verified rollback remain mandatory. Existing modes are preserved;
no environment/vendor/storage/database restore, migration or seed. If a parent
directory is still Sentinel-owned, record its exact ownership before changing
only that directory to cPanel ownership; do not recursively alter the tree.
Queue-worker service state and source reload require a separate observed check
because this release changes a job class. Provider delivery is never tested with
live patient destinations.

## Remaining delivery, without completion inflation

| Scope / task | Next executable work | Remaining gate |
| --- | --- | --- |
| RPH-94 calendar | Authenticated coordinator walkthrough; real persisted event correlation with operator fixtures; week presentation after its contract | Browser/runtime access and independent week bounds |
| RPH-65/66 appointments | Capacity, holds, idempotent booking/reschedule/cancel, concurrency and tenant denials, then real receptionist UI | Branch/resource schema and approved reminder lifecycle |
| RPH-96 / role dashboards | Scoped workspace persistence/authorisation before role-specific projections | PR28 accountant-to-owner reuse and global grants are unsafe; positive/negative tenant proofs needed |
| RPH-99–109 personal/role profiles | Real profile bindings landed here; team, branch, invitation, credential and finance-limit lifecycles remain | New role persistence and explicit capabilities |
| RPH-85 decisions | Assign clinical publication/signature, retention, finance and staff MFA recovery/enrolment choices to accountable owners | No silent clinical or money-policy activation |
| RPH-49/57 recovery | Rehearse the current full backup restore with appropriate separate restore authority | Source rollback is not a full database restore |
| Clinical OPG/status | Clinician findings/report versions, review/sign/release, patient-safe published projection | No image-only diagnosis or invented dental health score |
| Clinic finance | Tenant ledger/invoices, payments/instalments/cheques, reconciliation/refunds, audited limits | Merchant and finance-policy decisions, production-family constraints/races |
| Android/iOS | Backend device/refresh/upload contract tests and endpoints; chosen-stack shells; signed builds/device tests | ADR remains proposed; signing/build custody and push consent unresolved |
| Notifications | Observed worker liveness/heartbeat and controlled provider exercise | Fake sender proofs do not establish real delivery |

No broad task is marked Done by this slice. PR28/46 grants and notification
proposals, PR45 alternate calendar, unmerged PR47 files, Studio samples and mobile
contracts remain distinguishable from deployed backend features.

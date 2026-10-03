# Public location and truthful dashboard proof — 3 October 2026

Parent main/live PR58 `97f08947f41380b9147ef58d9a74a36fd57f4214`.
Private integration-lock capture16:54:59Z verified496 bounded source hashes;
`2026-10-03-discovery-policy-baseline.json` records them. This document describes
the reviewed candidate. Final issue15/RPH49/57/85/108 checkpoints record the
executed subsequent tests, exact merge, private backup and direct-root identity.

## Public location controls

Modern document.permissionsPolicy and legacy featurePolicy introspection detect
explicit geolocation denial. Known-denied controls remain hidden without a GPS
request. Unknown/missing/throwing introspection preserves the existing explicit
click request and ordinary denial fallback; it does not assert permission.
Policy is rechecked on click and callback. Synchronous API failures preserve
neighbourhood directions. A volatile per-root request serial invalidates older
callbacks after clear or a newer request, preventing cancelled coordinates from
restoring navigation/markers/status. No automatic/real GPS reads, persistence,
header weakening, payload/API changes or new third-party destination is added.
The existing directions action can still hand off a user-chosen location to the
existing navigation provider; this patch does not introduce that behavior.

Source inspection also found shared site.css button display rules can override
native hidden on the referrals page. Narrow mirrored discovery control CSS
protects hidden request/clear controls, with a fresh stylesheet version. JS-only
location controls are omitted when enhancement is unavailable. Global CSS and
layout/header policies remain untouched.

Controlled actual-source regression: initial14cases5pass/9red, final15/15.
Existing copy11/11 and map7/7 remain green. These fixtures do not prove physical
device permission/GPS, WebGL, real tiles or provider behavior. Primary policy
reference: [W3C Permissions Policy](https://www.w3.org/TR/2026/WD-permissions-policy-1-20260922/).
The site continues serving geolocation=(); this is truthful UI consistency,
not enabling geolocation or completing real-device map acceptance.

## Patient availability and readiness staffing

DashboardService formerly set has_published_review for any publication event,
even unsigned or superseded-only revisions. The bounded patient metadata query
now uses EXISTS with the same signed/published/unsuperseded conditions as the
existing own-case page; it avoids loading encrypted clinical revisions.
Own-case/demo filters and every other role projection remain. Availability is
not an unread notice, clinical health score, new signing policy or delivery event.

Launch readiness previously counted reserved synthetic PanelDemoRegistry staff
as real staffing. Only those exact identities are excluded in grouped filters;
genuine null-email/other-email active coordinators and currently verified
clinicians preserve eligibility. Count-only/no-store/viewer/demo guards remain.
This corrects evidence and may make an incorrect staffing gate fail; it neither
activates intake nor certifies staffing suitability or emergency coverage.

Valid synthetic fixtures reproduced5failures across14cases, no runtime errors:
unsigned and superseded availability, clinical SELECT* hydration, demo-only and
mixed staffing counts. Backend independent candidate full785/35,710 and focused
44/366 passed before root's additional rendered control assertions. Final
integrated local/VPS, Pint, private MariaDB and independent cross-review results
are recorded after execution in the final checkpoint. No real patient reads,
live database mutation, provider send, schema, permission or queue activation.

## Build, release and continuing requirements

The pinned targeted discovery graph in2026-10-03-discovery-policy-build.json
records actual source/lock/builder/manifest/artifacts. Preserve unrelated entries
and immutable old assets. Large bundle warning remains without a benchmark.
Root deploys only after clean exact head/fresh496hash baseline/private backup,
then verifies all hashes, served asset bytes, guest responses, source identity
and actual homepage/referral control visibility. Current rollback tests prove
induced exception recovery, not process-kill/power-loss recovery.

F07 remains open: Outbox::record dispatches ProcessOutboxEvent, whose implemented
channel is SMS. There is no current in-app store/feed/ack or notification
preference contract. Reusing it would activate provider delivery. Plan a genuine
in-app recipient/current-visibility/idempotency/read/withdrawal contract and
isolated migration/recovery tests before activation; do not relabel availability
as delivered notification. No new clinical signer or sensitive notification
policy is inferred.

Twelve scoped workspaces/branches, capacity booking, dental findings/report
workflow, finance, verified social targets, authenticated device/recovery
walkthroughs and native Android/iOS remain active requirements. Mobile vendor
upload rules were rechecked from primary sources; stack/device/signing/push
choices and actual native builds remain open. Missing research/screenshots are
not replaced by invented inputs. The full18decision registry remains intact.

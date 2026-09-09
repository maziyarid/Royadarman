# Royadarman UX Contract

This contract defines what the interface promises and how it behaves. It applies to the corrected public frontend and to all production patient/staff workflows built from it.

## 1. Actors and permissions

| Actor | Sees | Can do | Cannot do |
| --- | --- | --- | --- |
| Visitor | Public service scope, limits, FAQs and contact entry | Start a request, call support | View cases or upload without a bound intake session |
| Patient | Own requests, consents, approved review output and support | Submit/update own permitted data, upload/remove before lock, withdraw consent/request deletion | View another patient, assign a clinician, alter signed review |
| Coordinator | Authorised queue/cases, operational data, approved minimum document access | Contact, assign, record next action, refer with consent, resolve | Diagnose, write/edit dentist assessment, browse all documents |
| Dentist reviewer | Only assigned clinical cases and approved documents | Request clarification, write and sign preliminary review, supersede own review | Access unassigned cases, edit after signature, promise final diagnosis from OPG alone |
| Partner user | Referrals assigned to their organisation/branch | Accept/decline, propose visit/contact, update operational outcome | Search global patients, see other partners, access unrelated documents |
| Administrator | Governance, credential, access and audit functions required by role | Verify/suspend, manage policy versions, exceptional reassignment | Gain automatic clinical authority or use unrestricted document access |

## 2. Public actions

### `Start request`

- Entry: service card, hero primary CTA or mobile sticky CTA.
- Result: opens the service-choice step with no sensitive data in the URL.
- If JavaScript is unavailable: a standard link reaches a server-rendered request page in production.
- Analytics: record only generic service-entry source, never entered health data.

### `Call 24-hour support`

- Entry: header/hero/footer and urgent-information panel.
- Result: a real `tel:` link using the owner-approved number.
- Until a number and staffed SLA are confirmed: display “شماره در حال راه‌اندازی” and do not render a fake phone action.

### `View archived demo`

- Entry: secondary footer/about link, not a primary CTA.
- Result: `/demo/` with an “archived marketplace concept” notice.
- It must not be indexed as the current product and must not accept real information.

## 3. Patient intake state

Persist only in session/local draft for the static prototype. Production persistence is server-side after OTP/session binding.

```text
service -> contact -> document (conditional) -> preferences -> consent/review -> receipt
```

Navigation rules:

- Back preserves completed input in the active session.
- Forward validates only fields required for the current step.
- Refresh restores a server-side draft in production; the static prototype clearly says it does not submit.
- Browser back/forward renders the correct step without duplicate submissions.
- Step labels are descriptive; progress is exposed through `aria-current="step"`.

Conditional rules:

- `opg-review` requires the document step before consent/review.
- `home-service` requires Tehran area and displays current coverage limits.
- `guidance-referral` does not require a radiograph; a coordinator may later request one through a new upload session.
- A red-flag response exits the normal sequence to urgent guidance. It does not show a confirmation suggesting emergency care is booked.

## 4. Form validation contract

All forms use `novalidate` and controlled validation.

| Field | Client rule | Error behaviour | Server rule |
| --- | --- | --- | --- |
| Mobile | Iranian mobile shape after digit normalisation | Inline error; focus field | Normalise, validate, rate-limit and verify OTP |
| Name | Optional unless policy requires it; length limit | Inline error | Trim, length and allowed-character validation |
| Area | Required for home service; configured values | Inline error | Validate against active service-area IDs |
| Contact time | Optional preference, never a promise | No blocking error for omission | Validate enum/time window |
| Reason | Short plain text with visible character limit | Inline remaining/error | Length, encoding and abuse controls |
| Budget | Approved bands or “telephone discussion” | Inline selection error only when required | Validate enum; never use as sole clinical filter |
| Consent | Explicit, unchecked by default | Inline error and focus checkbox | Record purpose, policy version, subject, timestamp and channel |

On multi-error submit:

1. render an error summary with links to fields;
2. focus the summary;
3. mark each invalid control with `aria-invalid="true"` and described error;
4. preserve all entered values;
5. remove the error as soon as the corrected value is revalidated.

Never display internal exception, database, scanner or provider messages to the patient.

## 5. OPG upload contract

### States

| UI state | Message/action |
| --- | --- |
| Empty | Formats, maximum size and “انتخاب تصویر OPG” |
| Drag active | Clear drop affordance; no layout jump |
| Local validating | Check type/size; do not claim upload |
| Local rejected | Specific safe reason; selection remains replaceable |
| Ready | Name/size, remove and continue |
| Uploading | Real byte progress if available; cancel only if supported |
| Persisted/quarantined | “دریافت شد؛ در حال بررسی امنیتی” |
| Scanning | Pending state; user may leave and return |
| Approved | “برای بررسی دندانپزشک آماده است” |
| Rejected by scanner | Generic safety failure, remove/retry/support |
| Network interrupted | Preserve session, retry idempotently |
| Expired session | Ask to reauthenticate; never silently restart upload |
| Deleted | Confirm deletion outcome and what audit evidence remains |

Prototype constraints: JPEG/PNG/PDF, max 15 MB. Production values come from server configuration and are repeated in client hints, never enforced only in JavaScript.

## 6. Submission contract

### Before submit

- Show service, contact preference, area, OPG state, budget preference and consent text.
- Distinguish “request received” from “visit confirmed.”
- Show clinician limitation adjacent to the final action.
- Primary label: `ثبت درخواست و دریافت کد پیگیری`.

### Pending

- Disable duplicate submit while request is in flight.
- Keep button width stable and show text plus progress indicator.
- If response is uncertain after a network failure, check idempotency/status before allowing a new submission.

### Success

- Display a stable, copyable case reference.
- State the next human action and only an owner-approved SLA.
- Provide own-case status and support links.
- Repeat: final diagnosis/treatment requires appropriate examination by a licensed dentist.

### Failure

- Keep entered values and uploaded-document reference if safely persisted.
- Explain whether retry is safe.
- Offer support only through a real configured channel.
- Do not create duplicate cases on retry.

## 7. Case timeline contract

Patient labels are simpler than internal states:

| Internal state | Patient label |
| --- | --- |
| `submitted` | درخواست ثبت شد |
| `awaiting_contact` | در صف تماس کارشناسان |
| `in_coordination` | در حال هماهنگی |
| `clinician_review` | در حال بررسی توسط دندانپزشک |
| `referral_proposed` | گزینه درمانی آماده هماهنگی |
| `home_visit_proposed` | زمان پیشنهادی خدمت در منزل |
| `referred` | اطلاعات مرکز معرفی‌شده آماده است |
| `visit_scheduled` | مراجعه هماهنگ شد |
| `resolved` | پیگیری انجام شد |
| `closed` | پرونده پیگیری بسته شد |
| `cancelled` | درخواست لغو شد |

Never reveal another organisation's internal notes or an unverified clinician opinion on the patient timeline.

## 8. Coordinator actions

| Action | Preconditions | Success | Failure/recovery |
| --- | --- | --- | --- |
| Claim case | Case unassigned or claim expired | Assign, audit, show next-action control | Refresh owner/state; no silent override |
| Log contact | Assigned and permitted | Append attempt; update next action | Preserve draft note locally in memory; retry once |
| Assign reviewer | Verified active dentist, approved document if required | Create scoped assignment and notify | Explain inactive credential/conflict |
| Refer partner | Active partner, minimum disclosure, consent basis | Create referral and disclose only selected fields | No partial disclosure; retry through outbox |
| Propose home visit | Covered area, approved service/provider | Create proposal, notify patient | Return to provider/coverage state |
| Resolve | Required outcome/reason | Append transition and timeline | Inline missing field errors |
| Exceptional access | Elevated permission + reason + recent auth | Time-bounded access and security event | Deny by default, escalation path |

Coordinator clinical-note fields are read-only. Operational notes cannot be relabelled as a dentist assessment.

## 9. Dentist review contract

- Opening a document rechecks assignment, credential, document state and grant expiry.
- Viewing/downloading is audited before content is returned.
- The assessment is structured: image adequacy, observations, limitations, options, recommended next step and indicative budget band if appropriate.
- “Sign review” requires recent authentication and an explicit acknowledgement.
- After signing, fields are immutable. “Correct” creates a superseding version and preserves both records.
- Patient release is a distinct action/policy; signing alone does not necessarily publish.

## 10. Async and resilience

| Operation | UI expectation | Reliability rule |
| --- | --- | --- |
| OTP send | Countdown and resend state | Rate-limited, generic account response |
| Case submit | Blocking button progress | Idempotency key and transactional persistence |
| OPG scan | Non-blocking status; return later | Durable queue, retry with limit, fail closed |
| SMS | Timeline says queued/sent only from provider state | Transactional outbox and deduped callback |
| Partner disclosure | Pending until outbox dispatch succeeds | No UI success before durable referral/grant |
| Deletion | Request received, then verified/executed states | Retention exceptions recorded; object and derived previews handled |

Offline behaviour:

- Public content remains readable if cached.
- Do not queue sensitive form submission in a service worker for silent later sending.
- If connectivity drops, preserve unsent text only in memory/session where reasonable and clearly ask the user to retry.
- Never cache OPG responses or staff pages in a public/shared browser cache.

## 11. Feedback and confirmations

- Use inline status and toast for reversible, low-risk actions.
- Use a labelled confirmation dialog for destructive actions such as removing a persisted upload, withdrawing consent that affects an active review, closing a case, or revoking access.
- Do not use `alert()`, `confirm()` or `prompt()`.
- Confirmation copy names the object and consequence; primary/destructive labels are verbs, not “OK.”

## 12. Privacy requests

- A patient can ask to withdraw sharing consent or request deletion.
- The UI explains that withdrawal stops future sharing but may not erase a disclosure already lawfully made.
- Identity verification is required before export/deletion.
- The request receives a reference and status; it is not shown as instantly complete unless every relevant store is actually processed.
- Legal/clinical retention exceptions are shown in plain language after local approval, not invented by engineering.

## 13. Acceptance scenarios

1. Keyboard-only Persian user completes each service path at 320px without horizontal scrolling.
2. OPG user selects an unsupported executable renamed as `.jpg`; server rejects it and no clinical user can open it.
3. Upload succeeds but scanner is unavailable; state remains quarantined/pending and access is denied.
4. Coordinator cannot edit the dentist's assessment and cannot open an unassigned document.
5. Dentist with an expired credential loses access immediately, including existing sessions/grants.
6. Partner A cannot enumerate or access Partner B's referral by changing an identifier.
7. Double-click/retry creates one case and one consent record.
8. Home-service user outside Tehran receives an accurate limitation and an optional referral path, not false booking success.
9. Red-flag answer exits normal intake and does not imply that Royadarman provides emergency care.
10. Signed assessment correction creates a second version with clear authorship/history.
11. Consent withdrawal stops a queued disclosure before dispatch when policy allows.
12. Reduced-motion mode communicates every state without animation.

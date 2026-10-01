# Mobile screens, roles and server-side denials (PROPOSED)

Status: design. The server stays authoritative for tenant, branch, assignment, consent and credential checks; the app only hides what the server would refuse. Current code has six `users.role` values (patient, coordinator, clinician, clinic_rep, owner, tech_admin). The runbook's twelve workspaces (owner, developer, superadmin, supervisor, receptionist, accountant, support, specialist, clinic manager, dentist, clinical staff, patient or guardian) depend on Grok 2's additive membership grants, which are not published; no mapping is assumed here.

## M3 patient and guardian (first mobile slice)
| Screen | Backing API | Notes and denials |
| --- | --- | --- |
| Sign in (OTP, then password for returning accounts) | existing `/auth/*` | Staff accounts must follow the owner's MFA decision (PX.2); the app must never offer a weaker path than the web. |
| Clinic selection | public discovery (existing, not read in detail) | No patient GPS stored; neighbourhood centroid only. |
| New request with OPG or "no OPG" | existing case draft and submit; documents | Intake can be closed: show a neutral paused state, never a dead end. Uploading an OPG alone creates no diagnosis or health score. |
| Upload status and resume | proposed resumable upload | Server checks case ownership, consent and size on every chunk. |
| Appointments | not yet built (RPH-65) | Show nothing invented. |
| Released report and treatment plan | not yet built | Only after a clinician explicitly releases it. |
| Messages and support | existing `/support` | Patient sees only own conversations. |
| Payments | not yet built | A gateway return is not proof of payment; the app shows the server's state. |
| Devices and sessions | `/me/sessions` now, `/devices` proposed | Revoke one, revoke others, revoke all. |

## M4 staff (after M3 and policy)
| Workspace | Mobile scope proposal | Must be denied |
| --- | --- | --- |
| Support | Own queue and thread view | Any patient clinical file, OPG, diagnosis or invoice, regardless of navigation or role change. |
| Specialist | Assigned cases, staged notes, referral actions | Unassigned cases; revoked referrals; cross-clinic data. |
| Receptionist | Calendar and arrival | Clinical content. |
| Dentist and clinical staff | Assigned review, explicit OPG assessment, release | Cases without assignment, credential and consent; signing by title alone. |
| Accountant | Invoices and ledger of own clinic | Clinical records; other clinics. |
| Clinic manager, supervisor, owner | Scoped reports | Drill-down into sensitive records beyond scope. |
| Developer and superadmin | Operations health only | No clinical signing, no blanket data access. |

Whether any staff workflow is desktop-only is an open decision (runbook M4).

## Cross-cutting denial cases for tests
Wrong tenant or branch, revoked grant, expired credential, inactive account, demo session, unassigned case, consent withdrawn, role changed while a session is open (session must be revoked or re-evaluated), device revoked while offline.

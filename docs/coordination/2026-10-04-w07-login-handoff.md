# W07 G04 — login concurrency and recovery

4 October 2026. Task RPH-60, related D06/P01 and W04 authentication acceptance.
Base `9a16918997dfa064364fda47c57b18b4f5e40618`.
Branch `w07/login-concurrency-20261004`.
Ownership G04: issue61 comment5979892099; ACK5979921871.
The publication checkpoint records the exact commit containing this handoff.

## Scope and state

IMPLEMENTED + TESTED_ISOLATED; not independently accepted, integrated or deployed.
This is the active login slice, separate from review-ready PWA PR64. No second
PR/competing deployment is created by this publication. The release captain
controls review ordering and shared integration. Both login JavaScript mirrors,
the new W07 test and this handoff are the only four changed paths.

The existing served-webroot snapshot included password and OTP handlers; the
backend/public mirror was older OTP-only. The candidate deliberately uses the
newer served behaviour as its starting point and publishes identical repaired
bytes to both paths. It does not copy the old mirror over the served source.
The Blade, stylesheet, translations, passkey module, auth backend, permissions,
MFA policy, API routes, database and PWA worker are unchanged by this branch.

## Behaviour

Each password, OTP-challenge and OTP-verification request belongs to one current
view generation and operation. A handler-level guard ignores duplicate submits,
including programmatic events that disabled controls alone do not prevent.
Pending submit controls show disabled/aria-busy and recover after a current
failure. Stale request cleanup cannot unlock a newer request.

Changing login method aborts the prior request and invalidates its result. Even
if abort races completion, an old success/error cannot navigate, revive OTP
verification, overwrite current feedback or move focus. Hidden verification
cannot send an obsolete challenge. Successful login remains fenced while its
navigation is pending. There is no automatic request retry.

WebOTP is scoped to the exact controller, generation and challenge. Late
credentials cannot populate another challenge or a hidden input. Denial or
failure retains manual OTP entry. A new challenge clears obsolete challenge
codes/MFA values. Password input remains during an ordinary request error.

A malformed/non-JSON or followed-redirect response is not accepted as login
success. Existing JSON error messages are rendered as text. The inspected
AuthController returns JSON, challenge HTTP202 and password/verification HTTP200;
request methods, same-origin credentials, CSRF/locale headers and MFA payloads
remain unchanged. No client role selector or new privilege is introduced.

On pagehide, pending work and WebOTP are invalidated, the default method and
controls are restored, and transient username/mobile/password/code/recovery
inputs are cleared. This avoids keeping a login document stuck in redirecting
state after history restoration. It does not force a reload or add browser
storage. The independent passkey script is not modified or replaced.

## Executed tests

```sh
node --test backend/tests/Browser/W07LoginConcurrency.spec.js
node --check deployment/webroot/assets/auth-login.js
```

Node22.16.0, synthetic DOM/fetch/WebOTP adapters. All request values are labelled
synthetic; no real login, SMS, credential, provider or backend transaction.
Final identical29-check spec: original served source10PASS/19FAIL/exit1;
candidate29PASS/0FAIL/0skipped/exit0; syntax0. Failed checks are not19 distinct
bugs. Initial23/26-test intermediate results are superseded by this final run.

Checks cover fa/ar/en request/destination preservation, duplicate submits for
all three forms, stale success/error, busy-state ownership, explicit retry,
HTTP419/network/malformed responses, real challenge202 shape, MFA binding,
WebOTP cancellation and pagehide/restored-document handler state. Fake fetch
ignores abort deliberately so result-generation checks are tested independently.
Storage adapters throw on access. Real browser history, cookie ordering,
assistive technology, passkey device and server authentication are NOT tested.

## Source identity

| File | SHA256 |
|---|---|
| Both repaired auth-login.js files | `9160358f67b70f2bd70fde513460ed3ea6a2b46a3ddb233183abcdb88371aab4` |
| W07LoginConcurrency.spec.js | `65662c70b560fb90287e9c3be70fdb14fb15a784c9bff5880ab9b50f719ae150` |

Git blob IDs were calculated from tested bytes and match published blob results:
script3eea0db83c152e6164a1b10f0fea552836508e2a;
testcc3723b1883f5293671f5abce09e30d091f00b36.

Base served JS acd8762db6a41c2e80ad9cb3bbab9886c3b788501b6a97378b138b4df866a0ff;
base backend mirror ae7562cffab945de9ad097cc3546c49abbbcf330a07839d51868f88e28cb4156.
Unchanged base login Blade9d1ae96401c0b729203839da1f53f05ec75a830bea783568fb99bca1c3bd7608;
passkey source0aa20f5226a3277b0da3dd4278d5aa5746e7980c784e5047a2f525dae5eb2034.

## Review and release conditions

W04/original W01 independently reviews these exact bytes. The active
W01/delivery-finalise owner alone changes login script URL versioning and
integrates/releases after fresh live drift checks, backup and rollback proof.
The old URL may be cached: source replacement without coordinated URL/version
and served-byte verification is not delivery. Shared layouts/brand work remains
with its named owner, not this branch.

Browser cancellation cannot undo an OTP already issued or an authentication
already committed by the server. This patch prevents duplicate client events
and stale UI effects; it is NOT proof of server-side exactly-once delivery,
session-race prevention, immediate revocation or successful production login.
The server remains the authority; W04 owns those separate regression gates.

Current local Chromium policy blocks all navigation. No browser-policy change,
alternative-policy bypass, screenshot, physical-device or real install is
claimed. Validate actual password/passkey/OTP/expired-session/method-switch/
back-forward flows in the authorised browser environment before acceptance.

No source merge, production/DB/schema/provider action, new scheduler, paid
purchase or secret handling. Preserve current data on source rollback. Rolling
back this patch reintroduces its known client races; a reviewed forward fix is
preferable. Existing PWA PR64 has a separate CodeRabbit performance finding
about non-allowlisted static HTTP caching; this login branch does not claim to
close that finding or the public immutable-asset retention gate.

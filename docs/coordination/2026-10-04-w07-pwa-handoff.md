# W07 — PWA privacy and offline recovery handoff

Date: 2026-10-04T08:19:29.886065+00:00 / 2026-10-04T11:49:29.886065+03:30
Role/task: W07 / RPH-79; RPH-60/98 login presentation findings remain open.
Base: `9a16918997dfa064364fda47c57b18b4f5e40618`.
Branch: `w07/pwa-mobile-20261004`.
Worktree: `/home/royadarman/apps/royadarman-repo/agent-workspaces/w07-pwa-mobile-20261004`.
Grant: #61 comment5977948680 (G01), acknowledged comment5978008773.

## Scope and evidence levels

This is a narrow PWA patch, not completion of W07, all twelve workspaces or web/PWA delivery.
IMPLEMENTED: candidate service worker and offline document.
TESTED_ISOLATED: the exact candidate handler source, Node22.16.0, synthetic Web API adapters.
REVIEWED / INTEGRATED / DEPLOYED / ACCEPTED: NOT YET. W04 independent review and W01 release are required.
The commit containing this document is the candidate; its exact Git SHA is posted with the PR/checkpoint, not guessed inside this self-referencing file.

Granted paths only:
- `backend/resources/pwa/sw.js`
- `backend/resources/pwa/offline.html`
- `backend/tests/Browser/W07PwaPrivacy.spec.js`
- this handoff document

No manifest, build, release identity, stylesheets, brand asset, routes, backend auth, database or provider changes. No real patients, clinical files, secrets, messages, charges or refunds were used. No heavy Roya job or new scheduler was started. Charming docs and app listing were read; the current account returned no apps. No prototype, migration or alternative CRM was created.

## Reproduced baseline and change

Current source matched the inspected repository SHA and recorded SHA256s. A separate baseline auth/PWA handler harness executed 15 checks: 9 PASS, 6 FAIL, exit1. This used synthetic DOM, CacheStorage and fetch implementations, not a browser or Laravel.

The old worker admitted arbitrary `/assets/` responses, including a synthetic private/no-store response with a token query; a synthetic account switch replayed the first marker. It deleted an unrelated same-origin cache and called skipWaiting unconditionally. These are handler-level reproductions, NOT evidence that a real patient endpoint exists under `/assets/` or that actual data leaked.

The candidate replaces wildcard runtime admission with exactly three versioned public resources: the neutral offline HTML, existing workspace CSS, and existing brand mark. URLs contain SHA256 versions and fetch carries matching browser SRI metadata, credentials=omit, redirect=error and cache=reload. Non-200, non-basic, redirected, wrong-MIME, private/no-store and Cookie/Authorization-varying responses are rejected. All three responses are obtained and checked before cache writes begin. The runtime does not append arbitrary assets. Only known Royadarman cache namespaces are purged at activation; other same-origin caches are preserved. Matching is restricted to the current cache, not global CacheStorage.

No skipWaiting, clients.claim or reload message is sent. Existing tabs retain their worker until the browser's normal lifecycle permits activation; a new worker is not advertised as active merely because registration succeeded. Navigations and other unallowlisted same-origin GETs use fresh network requests and are never stored. Non-GET and cross-origin requests are not intercepted. Offline private APIs reject rather than return a success envelope. Failed navigation receives the neutral offline page, or a data-free no-store HTTP503 text response if its cache is missing.

The offline document has no inline styles, scripts, event handlers or forms. It reuses the current external workspace CSS and brand, with Persian/Arabic/English sections and real locale sign-in links. It does not promise a booking, upload or payment succeeded. Existing CSS font imports stay network-only; system fallback fonts are expected offline. No font binaries are included in this handoff.

## Executed tests

Command from repository root:

```sh
node --test backend/tests/Browser/W07PwaPrivacy.spec.js
```

Despite the granted Browser directory name, this is explicitly a Node handler specification, not browser execution. It requires only Node built-ins; no package install or production service is used.

Final identical 24-test specification:
- Original baseline source: 5 PASS, 19 FAIL, exit1 (expected product regressions, not harness error).
- Candidate source: 24 PASS, 0 FAIL, 0 skipped, exit0.
- Candidate JavaScript syntax check: exit0.

Checks include fixed cache size, token-extended URL refusal, synthetic account change, API/admin/three-locale panel exclusions, mutation/cross-origin bypass, neutral offline recovery, eviction fallback, legacy and unrelated cache handling, no forced activation, credential-free integrity metadata, changed-response rejection, locale recovery links and no inline executable content. The synthetic fetch adapter asserts SRI request metadata and induced rejection paths; it does NOT demonstrate a browser cryptographically enforcing SRI. Source byte pins must also match the exact release artifacts.

The local browser attempt launched Chromium144.0.7559.96 but its first loopback navigation failed `net::ERR_BLOCKED_BY_ADMINISTRATOR`. Exit2, ZERO browser checks/screenshots/install tests. The policy was not bypassed or retried. A separate remote public-network diagnostic was platform-blocked and supplies no HTTP equivalence proof. The prepared Python browser harness remains BLOCKED/UNVALIDATED and is not an acceptance runner until independently checked; its initial selectors assumed some baseline failures.

## Source receipts

| Path | Candidate SHA256 |
|---|---|
| `backend/resources/pwa/sw.js` | `7d2cc2e11b1856aa2aa9acfeedfa84fa0fd67074668b686bc9b6a94e215cf666` |
| `backend/resources/pwa/offline.html` | `32774791de7d5e1ee072e6bf8d8fd19d4eb19a5c6336f4f7f3768b5a42ee24eb` |
| `backend/tests/Browser/W07PwaPrivacy.spec.js` | `6790dc83005dbe99541fc371327b7758d1eb7021370cde82ae10e37ebd92657a` |

Pinned existing served-webroot source (unchanged):
- workspace.css: `12a65d5cde491c447941b1d2542126c9130d353670ac2fd8c104f54d1ce32de7`
- brand-mark.svg: `491dcbb2cac8e72d4d3380449733c91db36664ca9c776addc83fabff57dded8b`

Source-only baseline observations:
- Existing `.htaccess` disallows inline script/style; old offline style/onclick conflicts with that policy. Browser enforcement is NOT_RUN.
- `deployment/webroot/assets/auth-login.js` is password+OTP (`acd8762db6a41c2e80ad9cb3bbab9886c3b788501b6a97378b138b4df866a0ff`); `backend/public/assets/auth-login.js` is older OTP-only (`ae7562cffab945de9ad097cc3546c49abbbcf330a07839d51868f88e28cb4156`). Do not overwrite the served snapshot with the older mirror.

## Remaining release gates and operational limits

W04: independent source/security review, followed by real browser tests of scoped cache admission, logout/account-switch/back-forward recovery, denied sessions, offline APIs, old caches, integrity rejection and slow/error connections. Runtime handler mocks are insufficient for these claims.

W01: compare actual served responses at candidate deployment with all source pins and current CSP; run supported Chromium installability and platform-specific Safari/iOS guidance, standalone launch, narrow/mobile/tablet/desktop screenshots, keyboard/focus/contrast and offline retry. Name emulated versus physical devices. No authenticated journey, physical device, real install, CSP rendering, Qalam tool pass, full locale catalogue audit, twelve-workspace completeness, push permission or provider delivery has been verified here.

Legacy-cache cleanup occurs only when the new worker ACTIVATES. Old open tabs can still run the old worker and keep old caches while the update waits. Do not call that migration complete before testing close/reopen and logout/account-switch. Do not force activation during payment/upload to hide this limitation. Shared client logout/bfcache coordination is outside G01 and remains an explicit follow-up gate.

Version-query URLs are pinned to exact bytes; SRI fails closed if a later deployment changes the file behind an old URL. W01 must preserve compatible old public asset bytes/URLs while clients need them, or grant a follow-on immutable-file retention change. CSS/brand edits require regenerating their pins, then the offline document pin/cache version, and rerunning tests. No build or immutable-asset manifest rewrite is included in G01. A changed pin alone is not a new review.

Two login defects remain unmodified: a delayed OTP challenge response after switching to password revives verification and steals focus; overlapping submit events issue two challenge requests. Server rate limiting/delivery was not tested and no real SMS was sent. W01 must grant the exact login paths before fixes; W04 owns auth policies and independent review.

## Integration, rollback and next action

No migration or database recovery is required by this source-only patch. No production backup or deployment was performed. W01 alone integrates/releases with its fresh live/source preconditions, backup and deploy lock. Preserve current data and older immutable artifacts. Do not reset a database or force-reload active clients to roll back. Reverting to the old unsafe wildcard worker is not a privacy-approved rollback; W04/W01 should prefer a reviewed forward fix and verify the browser lifecycle.

Next action: independent W04 review of the pinned candidate, W01 browser/served-byte validation, then the separately granted login slice. Do not mark RPH-79 or WEB_PWA_ACCEPTED complete from these tests. GitHub #61 and the existing RPH-79 task receive the exact candidate/checkpoint and attachment; failed sync remains explicitly pending.

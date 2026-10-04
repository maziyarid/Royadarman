# W07 PWA privacy and recovery handoff — resumed 4 October 2026

Updated 2026-10-04 at approximately 12:20 UTC / 15:50 Asia/Tehran.
Role/task: W07 / RPH-79. Branch `w07/pwa-mobile-20261004`, draft PR64.
Parent candidate `5ece494290dfb2243f5c38634056306349408ef1`.
Original main base `9a16918997dfa064364fda47c57b18b4f5e40618`.
Ownership: G01 #61 comment5977948680; resumption comment5979818898.

## Result and scope

IMPLEMENTED + TESTED_ISOLATED. Independent review, integration, deployment and
owner acceptance remain OPEN. This is not completion of all W07 requirements,
twelve dashboards, native apps or WEB_PWA_ACCEPTED. The publishing checkpoint
records the exact commit containing this document; no self-referencing SHA is invented.

Only three existing G01-granted paths change in this resumption: sw.js,
W07PwaPrivacy.spec.js and this handoff. The fourth file, offline.html, is unchanged.
No shared layout, login, brand, stylesheet, manifest, route, dependency, database,
provider or hosting changes. Preserve the concurrent delivery-finalise writer;
W01's named single release session alone deploys. This worktree is not production.

## Preserved first-slice behaviour

PR64 admits exactly three versioned, integrity-pinned public resources: neutral
offline HTML, existing workspace CSS and existing brand mark. Static fetches omit
credentials and reject unsafe status/type/MIME, redirects, private/no-store and
sensitive Vary responses. Runtime fetches cannot expand this cache. Reads use the
current named cache only; activation removes known Royadarman legacy namespaces,
not other same-origin caches. No skipWaiting, clients.claim, forced reload or
mutation queue is used. Private APIs fail rather than return fake success.
Navigation fallback is a neutral fa/ar/en page or no-store text HTTP503 after
cache eviction. The offline document has no inline script/style/event handlers.

Historical parent evidence:24/24 handler checks; original main source5pass/19fail.
Those counts are not19 independent bugs or evidence of an actual patient leak.
The original detailed handoff remains in Git at parent5ece494.

## New gap and repair

A Fetch promise that stays pending never reaches the old catch-only navigation
fallback. A stalled pre-cache request also leaves installation pending. New tests
hold synthetic Fetch promises open and advance virtual deadlines against actual
worker source, not replacement production handlers.

The worker now applies a15,000ms response deadline to public-shell fetches and
navigation response headers only. Expiry aborts that request; failed navigation
uses the existing neutral recovery response. Stalled installation rejects without
purging the previously active cache. Completed/rejected requests clear timers.
Caller cancellation still reaches the returned body after headers: premature
listener cleanup was caught and fixed before publication.

This is a configured foreground-response budget, not a guaranteed wall-clock
deadline on suspended/throttled devices or a timeout for an already-streaming
navigation body. Private API timing/retry policy is unchanged. Non-GET and
cross-origin requests still bypass the worker. No payment/booking/upload replay.
Server401/403/429/500 responses retain their status, not an offline success.
No public-byte pin or cache-generation change is needed for this response-only fix.

## Executed verification

From repository root:

```sh
node --test backend/tests/Browser/W07PwaPrivacy.spec.js
node --check backend/resources/pwa/sw.js
```

Node22.16.0; synthetic Web API adapters and virtual timers; no real network,
database, providers, credentials or patient records. Identical final35-check spec:
- Parent5ece494:32passed,3failed,0skipped, exit1.
- Revised source:35passed,0failed,0skipped, exit0.
- Revised JavaScript syntax: exit0.

Eleven additional checks cover hanging navigation/installation, timer cleanup,
cancellation before/after headers, deadline value, preserved401/403/429/500 and
caller-owned APIs. An early harness drain was insufficient for its nested
asynchronous chain; it was corrected and BOTH versions were tested with the
identical final specification. These counts supersede intermediate runs.

Despite the G01-granted Browser path, these are NOT browser tests, real
CacheStorage lifecycle or cryptographic browser SRI proof. No new navigation
attempt: read-only current Chromium policy inspection confirms URLBlocklist=[*].
The earlier ERR_BLOCKED_BY_ADMINISTRATOR remains a validation boundary; no browser
policy changed or bypassed. No new screenshots, install or real-device evidence.

## Byte identity

| File | SHA256 |
|---|---|
| sw.js | `940ec2126f242dd5de9db97246b400dd1472e343ac3544b1af2102bf10fcd5b9` |
| W07PwaPrivacy.spec.js | `6d675626fbe558d8c8f597014497664e8433e187b2003ed7f2ddf655b42217cc` |
| offline.html, unchanged | `32774791de7d5e1ee072e6bf8d8fd19d4eb19a5c6336f4f7f3768b5a42ee24eb` |

Unchanged dependency pins: workspace.css12a65d5cde491c447941b1d2542126c9130d353670ac2fd8c104f54d1ce32de7;
brand-mark.svg491dcbb2cac8e72d4d3380449733c91db36664ca9c776addc83fabff57dded8b.
At12:15:13Z the cPanel-owned W07 tree was clean at5ece494 and checked hashes
matched the saved packet. Canonical HEAD remained9a169189. This is source identity,
not live served-file equality or an authenticated journey. Later read-back state
belongs in the publication checkpoint.

## Open gates and coordination

W04 independently reviews the new PR head. W01 validates exact served bytes/CSP,
real browser installation/standalone, logout/account switch/back-forward,
slow networks and responsive/accessibility journeys. Emulation is not a physical
device. Bot credit/trial/draft-skip notices do not constitute review.

Legacy cleanup occurs on activation; old tabs may retain their old worker/cache.
Do not force takeover over unsaved input. Shared-client logout/history work and
real-browser privacy acceptance remain open outside this slice.

Query-versioned URLs are not retained immutable files. Replacing bytes at an old
URL correctly fails SRI; the release owner must preserve compatible resources or
grant a separate immutable-file change. New approved brand/preview inputs are
acknowledged, not reverted. CSS/brand changes also require recalculating the
offline-document pin/cache version and running affected tests.

Login stale-response and duplicate-submit findings remain unmodified pending the
exact source/test/doc grant requested in5979818898. Shared layout/script-version
wiring remains with the release owner. Do not overwrite the newer password+OTP
served-webroot script with the older OTP-only backend/public mirror.

W08 notification view plus fa/en/ar module catalogues are delegated in5979818898;
W06's two invoice views remain delegated in5978097380. This only resolves ownership,
not feature acceptance. Global route/layout wiring stays with its named owner.

No migration, production backup, live source write, provider send, charge/refund,
clinical activation, new hosting or scheduler. Source rollback must not reset a
database or erase transactions; the unsafe wildcard worker is not an approved
privacy rollback. Prefer a reviewed forward fix. GitHub and RPH-79 receive the
pinned checkpoint; no owner ZIP installation is required.

## Primary API references checked

- https://developer.mozilla.org/en-US/docs/Web/API/AbortController
- https://developer.mozilla.org/en-US/docs/Web/API/Request/signal
- https://developer.mozilla.org/en-US/docs/Web/API/ExtendableEvent/waitUntil

These support cancellation and failed-install semantics, not browser acceptance
or a claim that production is deployed.

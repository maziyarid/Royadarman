# Discovery map runtime and direct artifact release — 3 October 2026

Base PR56 merge `af24b5e62469d3c19d4fdc4baa3c741416ba09f0`, tree
`271fbe486d7c4a3505abaaf9d3ee7da89eca9d71`. Under the shared deployment lock at
16:27:18Z, all490 baseline hashes matched live and canonical source. The scope
now also captures the map source, served .htaccess and all14 served build files,
in addition to the prior474 source entries. This is an expanded verification
scope, not16 newly implemented features. No environment, patient data or runtime
secret is in this baseline.

## Observed failures and resolution

After PR56, the real guest browser rendered the new homepage and exercised
Vanak→Tajrish filtering with accurate empty results. Its map container had zero
children and no visible fallback. The complete cause of that particular browser
failure was not established by the browser console. Independent source/runtime
checks reproduced separate defects: imported MapLibre Map shadowed the native
marker collection constructor; refilter cleanup invoked remove() on a wrapper
instead of its marker; failed construction/error paths did not show a fallback
and could leave a partly constructed map. All7 controlled Node regression cases
failed on the original source and pass after the repair.

MapLibreMap is now an explicit alias; native Map stores marker wrappers. Error
cleanup removes markers/user marker/map and guards future updates to a disposed
map. The translated status fallback sits outside the map canvas; the clinic list,
filters, selected cards and navigation remain usable. Missing WebGL, missing
tile configuration and construction/error failures show the existing factual
map-unavailable copy. This harness uses a controlled fake MapLibre6.10.0 API/DOM;
it is not real GPU, tile delivery, geographic correctness or physical-device proof.

The actual live root HTTP200 response enforced connect-src self and no worker-src,
incompatible with the default external OpenStreetMap raster origin/blob worker.
The served policy adds only worker-src self blob: and the exact HTTPS
tile.openstreetmap.org connect origin. Script/style restrictions, form origins,
frame restrictions and Permissions-Policy remain unchanged; no unsafe-inline,
unsafe-eval or wildcard host is allowed. A differently configured tile origin
requires a separately reviewed allowance; arbitrary origins are not trusted.

## Build and release safeguards

Existing cPanel Node22.23.2/npm10.9.8 were observed by absolute path; no global
toolchain was installed or upgraded. A private detached checkout outside the app
and webroot used npm ci --ignore-scripts --no-audit --no-fund,122 packages, with
the unchanged lock SHA recorded in the build evidence. The targeted builder loads
Vite8.3.0/MapLibre6.10.0 from those installed pinned dependencies, disables project
config/plugins/public copying/environment loading, and builds only the discovery
entry. Source, builder and lock hashes are checked before/after the build. No
production .env or database was copied.

Exact source/build/lock/merged-manifest/artifact hashes are in
2026-10-03-discovery-map-build.json. New JS assets/discovery-map-DHxMAkVx.js is
1,039,534 bytes; the existing map CSS is unchanged. The build reports a >500kB
chunk warning; no performance/device/capacity benchmark is inferred. Other
manifest entries and fonts/app/passkeys artifacts remain byte-for-byte unchanged.
Old map assets remain available for already loaded pages.

The direct deployer permits this exact JS source and served .htaccess plus only
tracked, mirrored, self-contained map JS/CSS references and manifests. It refuses
symlinks, missing artifacts, traversal/unrelated paths, shared imports, mismatched
mirrors, unrelated manifest edits, changed bytes under existing immutable asset
names, unrecorded destinations and live hash drift. Assets precede both manifest
switches. Sixteen synthetic filesystem tests exercise validation plus real atomic
file writes/backups: success ordering/retained old assets, induced HTTP failure
restoring every previous hash/removing new files, and live drift refusing before
any backup/write. These are isolated fault-injection tests, not production rollback.

Local isolated PHP8.3.6 full764 tests /35,520 assertions pass; backend Pint passes.
Independent focused49 /430 and Node7 /7 pass. Isolated VPS job7259a88deeb2 completed
at16:34:20Z: PHP8.3.33 full764 /35,520, Pint397 files, Node7 /7 and Python16 /16
pass. Private physical synthetic MariaDB63 /567 passes with networking disabled;
the temporary datadir/process/config were removed. Candidate source/build/lock/
mirrored manifest/artifact hashes all match. Independent final release-tool review
found no remaining bounded blocker. Power-loss/process-kill recovery remains
unverified; fault-injected exception rollback does not prove it. The actual
merge/direct-root/backup/served identity/browser results belong to the release
checkpoint; live deployment is not inferred from tests. Direct root remains
authorised after the fresh baseline,
private source backup, expected-head merge and final after-hash/HTTP checks. No
schema, migration, live database write, queue restart or probe activation is needed.

## Explicit adjacent gaps and wider coverage

F-2026-10-03-09: actual Permissions-Policy disables geolocation although the map
offers an opt-in GPS control. This slice does not enable GPS or obtain location.
A separate bounded same-origin permission/consent/ephemeral-state/browser review
is needed. User location must not enter discovery API queries, logs or storage.
F-2026-10-03-10: isolated source execution reproduced clipboard rejection plus
execCommand returning false while the unchanged fallback announces copied.
This inherited adjacent issue needs truthful failure/manual-copy feedback and a
separate regression; it is not fixed or dismissed by this map release.

Browser tile/worker/marker acceptance remains separate from synthetic checks.
No fake clinic was seeded into live data to demonstrate markers. Empty eligible
results are legitimate, not evidence of broken filtering. The current scope is
filtered Tehran discovery, not Mashhad/north/all-district coverage or capacity-safe
booking. Public provider continuity/licensing/capacity and the large bundle's
measured impact remain assessment items, not tested guarantees.

RPH49/57/85 broad criteria remain open. Twelve scoped dashboards/workspace/branch
persistence, booking, dental findings/taxonomy/signers/amendments/history, clinical
release notifications, ledgers/online/offline/installment/cheque payment rules,
authenticated role/recovery/browser verification and native Android AND iOS remain
required. Source-based web improvements do not complete those backend/native
milestones. The original screenshots/research files and verified social handles
remain unavailable; no visual-reference or social-destination content is invented.

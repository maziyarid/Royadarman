# Public destination copy recovery — 3 October 2026

## Source and scope

Parent main/live PR57 `d99eae35664346924781b0adbb2f2b230f9a7796`.
Private integration-lock capture at16:42:31Z verified492 source hashes;
`2026-10-03-discovery-copy-baseline.json` records that bounded scope.
Root owns serial publication/deployment; latest issue15/RPH49/57/85 checkpoint
records the actual subsequent merge, backup, live hashes and browser evidence.
This document does not declare an unexecuted deployment.

The existing public clinic destination copy action used to report success after
`execCommand('copy')` returnedfalse. Clipboard denial or unavailable browser APIs
now report failure truthfully and expose a labelled, readonly, selectable LTR
manual destination field. Persian, Arabic and English feedback/help are bound.
Success hides/clears the previous manual field. Transient fallback buffers are
removed even on exceptions, and previous focus is restored. A volatile per-root
WeakMap prevents an older asynchronous attempt from replacing the newer UI
feedback, value or focus. Already-issued OS clipboard writes cannot be cancelled;
this patch establishes latest-attempt UI ordering, not physical clipboard ordering.

Only public clinic destination coordinates enter that manual field. No GPS,
patient data, network, storage, provider, endpoint, clinic eligibility, booking,
clinical/support permission, schema or worker lifecycle changes are introduced.
The hidden field is enhancement-only. External scoped CSS preserves native
hidden behavior and keyboard focus. Application and served CSS copies match.

## Build and executed evidence

Node22.23.2, Vite8.3.0 and MapLibre6.10.0 from the existing pinned lock.
`2026-10-03-discovery-copy-build.json` identifies source/builder/lock/manifest
and generated artifacts. The targeted isolated build loads no environment,
fonts or application plugins. Only the discovery manifest entry changes;
old immutable assets and every unrelated entry remain. Large bundle warning
persists; no performance benchmark is inferred.

Local PHP8.3.6:771 tests/35,612 assertions; focused UI7/92.
Independent review:56 PHP tests/522 assertions; no bounded blocker.
Controlled actual-source Node checks:11/11 copy and7/7 existing map checks.
Copy baseline1/9 passes,8red; after first repair9/9. Two additional deferred
promise cases reproduce stale-success/stale-failure defects9pass/2red, then11/11.
Some baseline cases enforce the manual UI/no-inline-authoring contract; they
are not independent evidence of physical-browser CSP rejection. Python release
checks16/16 exercise real temporary atomic writes and induced rollback faults.
No physical-browser clipboard success or real GPU/tile/provider proof follows.
Final isolated VPS/full Pint/private MariaDB results are recorded in the final
release checkpoint after execution, rather than inferred from these local tests.

## Release and remaining gaps

Recheck the492 baseline hashes and clean exact canonical head before integration.
Back up changed source privately, deploy reviewed paths directly to the authorised
cPanel root with the existing narrow helper, assets before manifests, then verify
all hashes, served JS/CSS/entry, guest responses and private release identity.
Queue/probe activation remains unchanged. Use the recorded rollback archive if
a live verification fails. Existing rollback tests do not prove power-loss recovery.

F-2026-10-03-10 is resolved only for the tested copy/UI defects. F09 remains:
the existing GPS control meets a served `Permissions-Policy: geolocation=()`.
F07 clinical-release notifications, authenticated role walkthroughs, region and
capacity booking, twelve dedicated workspaces, dental findings/signers,
accounting, verified social targets and Android/iOS delivery remain open.
No clinical or financial policy decision is enabled by this patch.

# Mobile delivery sequence, gates and contract-test plan (PROPOSED)

Dates are not assumed. Each milestone needs evidence before it is called done.

## Toolchain and store gates (checked 2026-10-01)
- Google Play: from 31 August 2026, new apps and updates must target Android 16 (API level 36). Source: Android Developers target-API requirements page.
- Apple: since 28 April 2026, uploads to App Store Connect must be built with Xcode 26 or later using the iOS 26 SDK; App Store Connect added Xcode 27 / iOS 27 SDK uploads on 14 September 2026. Source: Apple Developer upcoming requirements and App Store Connect release notes.
- iOS builds need macOS with Xcode and Apple signing. If no Mac, account or device exists, M2/M6/M7 for iOS stay open with the exact owner and action. Never mark iOS done from Android work.
- Re-verify both policies at release time; they change yearly.

## Milestones
| M | Deliverable | Evidence required |
| --- | --- | --- |
| M0 | ADR-0001 accepted, OS/device matrix, accounts and signing custody, push provider decision | Owner sign-off recorded; no purchases made by agents |
| M1 | Versioned contract (this package) approved; backend device, refresh, push and upload endpoints built behind tests | Security review; negative tests below passing on a real runtime |
| M2 | Buildable Android and iOS shells, RTL, theme, navigation, neutral offline state | Android build; iOS build on a Mac; accessibility smoke |
| M3 | Patient journey end to end on persisted APIs | Interruption, retry, expiry, permissions; report only after clinician release |
| M4 | Staff workspaces by role | Denial tests per role, tenant, assignment, credential, consent |
| M5 | Camera, push consent, deep links, secure storage, revoke | No-PHI checks on lock screen, logs, clipboard, backups |
| M6 | Automated contract and UI tests; real-device journeys | Emulator and simulator results labelled separately from real devices |
| M7 | Signed internal builds and tester distribution | Key custody verified; no keys in repo or chat |
| M8 | Store submission and rollout as the owner authorises | Privacy declarations match behaviour; reproducible release |

## Backend work the apps depend on (not built)
Device registry, credential issue and rotation with reuse detection, per-device assurance, push registration, resumable upload, versioned error envelope with minimum-app-version, membership grants for the twelve workspaces (Grok 2), scheduling and finance APIs for later screens.

## Negative contract tests to write with each endpoint
- Login: wrong, expired, reused and locked codes (PR #18 fixes the attempt counter), inactive account, staff without required MFA, patient calling staff routes.
- Devices: revoked, expired, another user's device id, replayed rotated refresh credential, refresh after revoke-all, clock skew.
- Assurance: an old device stays stale after another device logs in; sensitive actions return 423 until that device re-authenticates.
- Push: token for another user's device, reuse after revoke, payload contains no PHI.
- Uploads: wrong tenant or case, consent withdrawn mid-upload, oversize, hash mismatch, duplicate chunk, out-of-order offset, resume after expiry, unscanned file never downloadable, idempotent completion.
- Parity: the same identity gets the same 403 or 404 on web and mobile for every staff endpoint.

## Implementation order once gates open
1. Contract tests against a fake server generated from the OpenAPI file.
2. Backend device and refresh endpoints with the tests above (new PR, not auth shortcuts).
3. App shell for the chosen stack with login and device list.
4. Upload client with resume against the staging contract.
5. Patient journey screens bound to real APIs as they land; no permanent fake-success buttons.

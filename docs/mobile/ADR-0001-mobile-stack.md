# ADR-0001 (PROPOSED): mobile stack for Royadarman Android and iOS

Status: proposed, not accepted. The owner must decide; no team skills, budget, build host or timeline were provided to me.

## Constraints from the runbook and source
- One backend, one permission model, versioned API. No separate auth service.
- Persian first with RTL, then Arabic and English.
- Camera and gallery capture of radiographs, large private uploads with resume, non-mirrored radiographs.
- Secure token storage in platform stores, no PHI in push, logs, analytics or backups.
- iOS needs a macOS build host and Apple signing. Linux-only editing is not an iOS build.
- The repository's web frontend is JavaScript (Vite); the backend is Laravel 13 / PHP 8.3.

## Options
| Option | Reuse and fit | Main risks |
| --- | --- | --- |
| Native Kotlin + Swift | Best platform security APIs, camera, background upload, accessibility. | Two codebases and two skill sets; slowest for a small team. |
| React Native (TypeScript) | Closest to the existing JavaScript skills; mature RTL; native modules for Keychain/Keystore, camera, background upload. | Native-module upkeep; large background uploads and push handling still need platform code. |
| Flutter (Dart) | One UI codebase, strong RTL and custom rendering. | New language for the team; plugins needed for secure storage, camera, background upload. |

## Provisional recommendation (my judgement, unverified against your team)
React Native with TypeScript, using platform secure-storage modules and thin native modules for background upload and push. If the owner has native engineers available or wants the strongest background-upload and accessibility behaviour, choose native instead. This recommendation is an inference from the JavaScript web code and the single-codebase goal, not evidence about the team.

## Decision gates before any app code
1. Owner chooses the stack and names the build/maintenance owner.
2. Supported OS and device matrix (minimums, tablets, low-end Android).
3. Apple Developer and Google Play accounts, signing custody, tester distribution.
4. Push provider and consent copy.
5. Backend device credential, refresh, push and upload endpoints approved (see `openapi-mobile-v1.yaml`).

# Shared workspace frontend delivery — 30 September 2026

Parent: approved PR #11, main f99210e05c1fef2e186d76e625164cc4ea101d8a.
Frontend branch: frontend/20260930. RPH-60 / RPH-98.

## Delivered to live root

Seventeen presentation files deployed using baseline SHA-256 checks and
flock on /home/royadarman/apps/.royadarman-deploy.lock. Backup:
/home/royadarman/frontend-backup-20260930T1900Z (0700), manifest.json (0600)
records destinations, original/new hashes and modes. Existing modes retained;
new files owned by royadarman, 0644. Cleared compiled views as app user.
No controller, query, policy, route, schema, integration or support payload changed.

- Shared workspace: refined blue palette, spacing, sidebar, cards, metrics,
  tables, forms, visible keyboard focus, reduced-motion support and mobile layout.
- Current role dashboards: shortcuts to existing authorized profile/support,
  coordinator tasks/calendar and owner reports. No new role permissions.
- Sidebar calendar coordinator-only, analytics owner-only, matching controllers.
- Mobile navigation: expanded state, controlled sidebar, closed sidebar inert,
  Escape and backdrop dismissal, focus restoration and desktop reset.
- Worker B calendar integrated with external filtering script under existing CSP.
  All controller events remain visible with scripts disabled. Saturday-start
  months now have zero leading blanks; Friday-start months have six.
- Existing webroot workspace.css retained because app/public copy is older.
  New refinement is additive. Updated workspace.js query version to prevent
  clients retaining the old navigation handler.

## Evidence and limits

Actual Blade rendering in an isolated checkout with synthetic payloads:
18 dashboards (six current roles × fa/en/ar), role-scoped calendar/report links,
one page h1, populated/empty calendar events, 0/6 leading blanks and no inline
calendar script. DB queries forbidden by listener; no production .env copied.
Vendor symlink supplies installed dependencies read-only. This is presentation
verification, not controller authorization, tenant/consent or live-auth proof.

Node syntax checks and tools/frontend/interaction-smoke.cjs passed mobile
closed/open/Escape/backdrop/desktop and calendar filter checks through a minimal
DOM adapter. git diff --check passed. No browser-layout claim: Chromium absent,
installation failed with invalid archives. Authenticated live walkthrough pending.
Live home/login and new CSS/calendar JS returned HTTP 200 after deployment.

Open: other role-specific dashboards/backends and mobile apps, Jalali seams at
1403-12/1404-12, stored-event/notification correlation, clinic scheduling and
backend security hypotheses documented on RPH-85. RPH-98 remains unaccepted.
Historical production-source manifest describes the pre-frontend baseline;
new deployed hashes are in the frontend backup manifest, not that old manifest.

## Rollback

Acquire the same lock; compare each live file against manifest new_sha256.
If a hash differs, reconcile later-agent edits before restoration. Restore
existed files from manifest backup paths/modes; remove only newly added paths
still matching this deployment. Clear compiled views as royadarman and verify
home/login and authenticated dashboard. No database rollback required.

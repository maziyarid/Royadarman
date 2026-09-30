# Source synchronisation — 30 September 2026

## Purpose and source identity

Owner-authorised reconciliation of live Roya Darman with maziyarid/Royadarman.
This release imports production into Git; it does not deploy Git onto production
or apply a schema change.

Parent main: 88dcfeec4a09badc21c094a00f317351d9e6cfe3, preserved as
archive/main-before-live-sync-20260930.
Application: /home/royadarman/apps/royadarman-backend.
Webroot: /home/royadarman/public_html.
SentinelX target: host_d3b5541db7acae2a.

production-source-manifest-20260930.json records 445 captured file hashes,
capture time, paths and exclusions. Every included file was checked against
its captured hash after transfer. backend/public/assets and
deployment/webroot/assets intentionally remain separate: they differ on live.
Never replace served assets with the older application-public copy.

Production inspection reported PHP 8.3.33, Laravel 13.29.0, MariaDB 10.11.19,
27 ran migrations and 61 InnoDB tables. Runtime, .env and database remain private.
No .git or tests directory was added to the live app.

## Exclusions and retained repository content

Excluded: production .env/credentials, private uploads, storage contents,
databases/dumps, vendor/node_modules, caches, sessions, logs/error_log, cPanel INI,
MCP configuration, old-security assets and staging trees.
This is complete source synchronisation for the documented application/webroot
scope, not replication of private operational data or the VPS filesystem.

Existing repository tests, CI, docs and archived frontend were retained.
Their presence does not prove they match or were deployed with the snapshot.
The newer roadmap supersedes historical architecture proposals.

## Agent review and pending work

Grok produced a backup and live-schema inventory. See
docs/architecture/2026-09-30-live-domain-contract.md.
Source hash: 63f53752095e4d0ed914e4a6454389a2ceaba10aa6bc48b7c9c62c34f7be5171.
It is a baseline inventory, not completed target architecture or permissions.

Backup hashes/permissions and archive readability were verified; full recovery
remains unproven because the application DB user cannot CREATE DATABASE.
Preserve least privilege; use a separately provisioned isolated restore target.
A transactional dump does not guarantee filesystem/DB point-in-time consistency
or protection against concurrent DDL.

Worker B's exact pending files are preserved in agent-work/rph98-20260930.
They are not installed in backend or webroot. Read INTEGRATION.md and REVIEW.md.
Passing diagnostics document known Jalali defects, not their correction.

The older dirty VPS checkout remains untouched at
/home/royadarman/apps/royadarman-dev-20260928. Its six tracked edits are preserved
as agent-work/legacy-discovery-20260928/uncommitted.patch. Not applied or accepted.

## Development and publication

Canonical new VPS checkout: /home/royadarman/apps/royadarman-repo.
Use backend/ inside it. Create a separate branch/worktree per agent.
Check Git status and latest Agiflow ownership notes. Preserve others' edits.
Fetch/reconcile before pushing; no force push.

Do not use deployment/webroot/index.php for a development server: it points at
the live application. Use backend/public with a private synthetic environment.
Do not expose a public staging site or copy production environment values.

Connector repository access differs from Git push authentication on the VPS.
The final Agiflow handover records tested access and any auth limitation.
A successful public clone does not prove push access.

Direct-root deployment after backup is authorised, but this is not an automatic
bidirectional mirror. Publish agent branches, review/test, acquire a shared
integration lock, compare live hashes, back up, integrate reviewed paths and
verify live behaviour. Never blindly pull/reset production to main. Record Git
SHA and deployment state separately in Agiflow RPH-49/RPH-57.

## Verification performed

- 445 captured file hashes verified after transfer.
- PHP 8.3 lint of 228 captured app/config/migration/route/bootstrap files passed.
  This is not PHPUnit or an authenticated workflow test.
- Pending RPH-98 static source/contrast checks rerun on VPS: passed, limited scope.
- Jalali diagnostic reproduced seams in 1403-12 and 1404-12. No fix applied.
- Pattern scan of exported source/pending package found no recognised credential
  patterns. A bounded scan, not a guarantee against secrets.
- No production application/schema/queue change made by the sync.

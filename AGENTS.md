# Roya Darman agent operations

Read docs/operations/2026-09-30-source-sync-handoff.md before development.
The product contract is docs/roadmap/2026-09-30-royadarman-roadmap.md and
Agiflow RPH-WU-1. Latest Agiflow comments may supersede dated handovers.

Use one branch/worktree per agent. Never reset another agent's dirty checkout.
Production is not a development checkout; do not automatically pull into it.
Review/test a bounded patch and serialise production integration.
Never commit credentials, production environment files, patient records,
uploads, SQL dumps, logs, sessions or caches. Use synthetic data.
Preserve server-side role/tenant/consent controls and support-workspace privacy.

backend/ is sanitised production source. deployment/webroot/ is the separately
served webroot snapshot; its index points at production. Do not serve that
snapshot as an isolated development application. agent-work/ is pending,
explicitly undeployed work, not accepted application code.

Continuing worker coordination: read docs/coordination/CONTINUING-DELIVERY-RUNBOOK.md
and your lane prompt/issue when present. GitHub issues #13/#14/#15 carry worker
claims/checkpoints; missing Agiflow access does not block the GitHub-only lane.
Preserve file ownership, evidence gates and Android/iOS scope. These instructions
select work on resumed sessions; they do not schedule agents or grant new access.

# GitHub Actions empty-step failures

**Committing this file does not enable GitHub Actions runners
and is not PHPUnit evidence.**

A red X on this repository's `ci` workflow is often **not** an
application-test result. Diagnose duration and job steps before treating
the run as PHPUnit fail, PHPUnit pass, or a merge signal.

## Confirmed cause (2026-09-19, HEAD `5ab77f4`)

GitHub check-run annotations, not `ci.yml`, refuse to start the job:

> The job was not started because your account is locked due to a billing issue.

Read via `GET /repos/maziyarid/Royadarman/check-runs/{job_id}/annotations`.
The same annotation is on:

| Where | Run | Job | `runner_id` | steps | billable |
|---|---|---|---|---|---|
| PR #9 HEAD `5ab77f4` | [35430807126](https://github.com/maziyarid/Royadarman/actions/runs/35430807126) | all 4 (`ubuntu-latest`) | 0 | 0 | 0 ms |
| `main` `2f9763a` | 35009844651 | Locale render parity | 0 | 0 | — |
| `main` first `ci` `f0c30ae` | 34694610015 | Backend (PHP 8.3) | 0 | 0 | — |
| `maziyarid/AdaAI` | 35430640282 | pytest | 0 | 0 | — |
| `maziyarid/Medical-CRM` | 35077300719 | lint | 0 | 0 | — |

Repository Actions **are enabled**. REST
`GET /repos/maziyarid/Royadarman/actions/permissions` returned
`enabled=true`, `allowed_actions=all`, `sha_pinning_required=false`.
Workflow `ci` (`355515620`) state is `active`. Self-hosted runner count is 0
(jobs request `ubuntu-latest`, so that is expected). Zero successful
workflow runs exist in this repository (397 completed: 305 `startup_failure`,
90 `failure`, 2 `cancelled`).

This is an **account-level** GitHub billing lock on `maziyarid`, not a
workflow-YAML defect and not a repository Actions-disable. Rewriting
`.github/workflows/ci.yml` will not assign a runner.

Billing **dollar amounts / invoices / payment methods** are not readable
from this integration (`GET /users/maziyarid/settings/billing/actions`
→ 403 Resource not accessible by integration). Do not invent the
underlying invoice.

## How to recognise the empty-step pattern

Typical signals (all of these together):

- The workflow run completes in about 1–4 seconds.
- Named jobs exist (`Backend (PHP 8.3)`, `Backend (PHP 8.4)`,
  `Frontend (backend Vite)`, `Locale render parity (fa/ar/en)`).
- Each job's `started_at` and `completed_at` are 1–2 seconds apart.
- Job `steps` are empty; checkout / setup-php / composer never start.
- Job `runner_id` is `0` and `runner_name` is empty.
- Job log download returns 404 / `BlobNotFound`.
- Usage `billable.UBUNTU.total_ms` is `0`.
- Check-run annotations include the billing-lock sentence above.
- The same pattern appears on `main` and on other `maziyarid/*` repos.

If those hold, the failure happened **before step 1**. The workflow YAML
still contains checkout, `setup-php`, `composer install`, PHPUnit, Pint,
and `npm ci` — they did not run.

## What this is not

- **Not an application-test failure.** PHPUnit / Pint / Vite did not execute.
- **Not a pass.** Do not merge PR #9 because the red X "isn't our code."
- **Not a reason to weaken `composer audit`.** Leave `|| true` until a
  green baseline exists on a real runner (RPH-9 AC3).
- **Not Greptile evidence.** Bot review is review only; RPH-10 is the merge gate.
- **Not a reason to rewrite `ci.yml`.** Repo Actions are already enabled.

## Representative runs (age; re-check HEAD)

| Run | SHA | Wall clock | Pattern |
|---|---|---|---|
| 35430807126 | `5ab77f4` (this branch) | 08:00:03Z–08:00:06Z | four jobs, runner_id=0, billing-lock annotation |
| 35429263687 | `1d01b93` | 07:25:04Z–07:25:08Z | same |
| 35137478318 | `88dcfee` (`main`) | ~3s | same on default branch |

Treat a later run as the same billing lock unless a job log exists, a
runner is assigned (`runner_id` ≠ 0), and a step actually started.

## What would count as a real CI result

A job that:

1. Receives a runner (`runner_id` ≠ 0) and records steps, and
2. Uploads a log blob, and
3. Reaches `composer install` / `php artisan test` / `npm ci`

Then the conclusion is application evidence. Until then, use regime B in
[`clean-checkout.md`](clean-checkout.md) on a PHP 8.3 + Composer machine.

## What the operator must check (do not guess)

GitHub's public docs for this exact lock:
[Unlocking a locked account](https://docs.github.com/en/billing/how-tos/troubleshooting/locked-account).

Signed in as `maziyarid`:

1. Profile picture → **Settings** → **Billing & Licensing**
   (`https://github.com/settings/billing`).
2. Copy the exact banner / outstanding-invoice / failed-payment text
   (or a screenshot). Do not randomly change Actions settings.
3. **Payment information** — update or replace a declined method only if
   the banner says payment failed / past due.
4. After GitHub unlocks the account, **re-run** workflow run
   [35430807126](https://github.com/maziyarid/Royadarman/actions/runs/35430807126)
   (or push any later commit) **without** editing `ci.yml`.
5. Confirm one job has `runner_id` ≠ 0, non-empty `steps`, and a
   downloadable log. Only then is AC2 in play.

Do **not** change these (already verified via REST):

- Repository **Settings → Actions → General**: Actions are allowed;
  all actions and reusable workflows are allowed.
- Do not add a self-hosted runner on the memory-critical production VPS.
- Do not rewrite `.github/workflows/ci.yml` to "test" unlock.

## Repair

RPH-9 AC2/AC3 are blocked on the `maziyarid` billing lock. Unlock is an
account-owner action. Do not rewrite `.github/workflows/ci.yml` merely
to create activity, and do not apply host systemd/cron from this file.

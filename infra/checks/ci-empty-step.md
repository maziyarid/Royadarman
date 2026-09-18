# GitHub Actions empty-step failures

**Committing this file does not enable GitHub Actions runners
and is not PHPUnit evidence.**

A red X on this repository's `ci` workflow is often **not** an
application-test result. Diagnose duration and job steps before treating
the run as PHPUnit fail, PHPUnit pass, or a merge signal.

## How to recognise the empty-step pattern

Typical signals (all of these together):

- The workflow run completes in about 1–4 seconds.
- Named jobs exist (`Backend (PHP 8.3)`, `Backend (PHP 8.4)`,
  `Frontend (backend Vite)`, `Locale render parity (fa/ar/en)`).
- Each job's `started_at` and `completed_at` are 1–2 seconds apart.
- Job `steps` are empty; checkout / setup-php / composer never start.
- Job log download returns 404 / `BlobNotFound`.
- The same pattern appears on `main`, not only on this branch.

If those hold, the failure happened **before step 1**. The workflow YAML
still contains checkout, `setup-php`, `composer install`, PHPUnit, Pint,
and `npm ci` — they did not run.

## What this is not

- **Not an application-test failure.** PHPUnit / Pint / Vite did not execute.
- **Not a pass.** Do not merge PR #9 because the red X "isn't our code."
- **Not a reason to weaken `composer audit`.** Leave `|| true` until a
  green baseline exists on a real runner (RPH-9).
- **Not Greptile evidence.** Bot 5/5 is review only; RPH-10 is the merge gate.

## Representative runs (age; re-check HEAD)

| Run | SHA | Wall clock | Pattern |
|---|---|---|---|
| 35341730638 | `760ed0e` (this branch) | 11:51:54Z–11:51:58Z | four jobs, ~1–2s each |
| 35326711695 | `9c1b35b` | 08:53:50Z–08:53:52Z | same |
| 35137478318 | `88dcfee` (`main`) | ~3s | same on default branch |

Treat a later run as the same host/Actions pattern unless a job log
exists and a step actually started.

## What would count as a real CI result

A job that:

1. Receives a runner and records steps, and
2. Uploads a log blob, and
3. Reaches `composer install` / `php artisan test` / `npm ci`

Then the conclusion is application evidence. Until then, use regime B in
[`clean-checkout.md`](clean-checkout.md) on a PHP 8.3 + Composer machine.

## Repair

RPH-9 owns runner assignment / org Actions / billing. Do not rewrite
`.github/workflows/ci.yml` merely to create activity, and do not apply
host systemd/cron from this file.

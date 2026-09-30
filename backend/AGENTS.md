# Laravel development rules

Read root AGENTS.md and the source-sync handover first.
Use the existing Composer lock and PHP >=8.3. Observed live versions:
PHP 8.3.33 / Laravel 13.29.0 / MariaDB 10.11.19; verify when needed.
Do not install Laravel Boost or upgrade dependencies merely to sync source.

Install development dependencies in an isolated checkout. Never copy production
.env or database. Tests use synthetic data and disabled external delivery.
Repository tests were retained from the earlier source and have not all been
reconciled with this snapshot; report actual failures.
No tests directory existed on production. Repository tests are not production
workflow proof. Use the cPanel account for authorised application commands;
do not create root-owned application caches.

Read RPH-49/57/85 and the relevant task before edits. RPH-58/96 permissions must
preserve users.role readers and clinical boundaries. RPH-98 UI remains pending
under agent-work, with CSP and Jalali defects. Fixture tests are not acceptance.

# Royadarman deployment and rollback

## Release gate

- [ ] `composer.lock` is present and `composer install --no-dev --prefer-dist --optimize-autoloader` succeeds.
- [ ] `.env` is not in the source archive; production uses `APP_ENV=production` and `APP_DEBUG=false`.
- [ ] `INTAKE_ENABLED=false` remains set until legal consent text, licensed clinical lead, SMS delivery, staffing, scanner, encrypted backup and restore rehearsal are approved.
- [ ] `ROYADARMAN_PHONE_HASH_KEY` is an independent secret and the SMS callback secret is configured.
- [ ] MariaDB uses InnoDB, `utf8mb4_unicode_ci`, UTC and strict SQL mode.
- [ ] `/home/royadarman/private_uploads/{quarantine,approved}` is outside `public_html`, mode `0750`, and not reachable over HTTP.
- [ ] ClamAV is installed, current, and a real clean/EICAR/timeout test passes before intake activation.
- [ ] Apache routes only through the Laravel front controller; `.env`, application files, storage and vendor are not web-accessible.
- [ ] Queue supervisors run `otp`, `scanning`, `notifications`, and `maintenance` queues with bounded retries.
- [ ] Scheduler runs once per minute and retention has an approved configured duration; no duration is invented by code.
- [ ] Encrypted application/database/file backups have a successful restore rehearsal.
- [ ] `php artisan test`, Pint, route listing, locale smoke tests and production HTTP/security-header checks pass.

## Database-safe release

1. Take encrypted database, application and public-root backups and record hashes.
2. Put the application in maintenance mode only for the short schema switch if an online expand step is impossible.
3. Deploy additive code and additive migrations first. Do not rename or drop live columns in the same release that stops writing them.
4. Run `php artisan migrate --force`, warm caches, start workers, then smoke-test with intake still disabled.
5. Remove maintenance mode and monitor HTTP 5xx, queue failures, scanner failures and authentication throttles.

## Rollback

If application code fails, restore the previous release/front controller and restart workers. Keep additive schema in place; old code must tolerate it. If a migration fails, stop at the failed migration, preserve the database snapshot and inspect the exact partial state. Never run a blind rollback that drops a column or table already used by live records. Repair forward with a new expand/contract migration. Destructive contraction happens only in a later release after old code and data use have been verified absent and a fresh restore-tested backup exists.

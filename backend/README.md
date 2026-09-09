# Royadarman

Production Laravel 13 application for Royadarman’s multilingual dental guidance and coordination service.

## Product scope

- 24/7 dental guidance and support centre;
- Tehran-only home-dentistry coordination;
- preliminary OPG review by an assigned licensed dentist;
- location- and budget-aware referral to partner dentists and clinics.

Royadarman is not represented as a doctor or a single clinic. The application does not provide autonomous diagnosis, guarantee treatment outcomes, process payments, or expose clinical files publicly.

## Stack

- PHP 8.3 / Laravel 13
- MariaDB 10.11
- Apache/cPanel with Blade SSR
- database queues and scheduler
- ClamAV-backed private OPG quarantine
- Persian (`fa`, default RTL), Arabic (`ar`, RTL), and English (`en`, LTR)

## Local setup

```bash
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan test
```

The public site is available at `/fa/`, `/ar/`, and `/en/`; `/` redirects to Persian.

## Production safety

`INTAKE_ENABLED=false` is the fail-safe default. Do not enable intake until the legal consent/privacy translations, SMS provider, retention duration, licensed clinical lead, coordinator coverage, encrypted backup restore drill, and end-to-end scanner tests are approved.

See `ARCHITECTURE.md`, `DESIGN.md`, `DEPLOYMENT.md`, `CHECKLIST.md`, and `design-qa.md` for the complete contracts and verification evidence.

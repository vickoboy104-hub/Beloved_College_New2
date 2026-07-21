# Beloved College School Management Platform

A Laravel-first rebuild of the Beloved College public website and school management system.

This repository is a new application codebase. It preserves the approved database records, uploaded files, business rules, calculations and operational functions from `vickoboy104-hub/belovedcollege_new` while replacing the previous presentation architecture.

## Technology foundation

- Laravel 13
- PHP 8.4 target
- Livewire 4
- Tailwind CSS 4
- Vite 8
- PHPUnit 12
- GitHub Actions continuous integration

## Application surfaces

| Surface | Production host | Purpose |
|---|---|---|
| Public website | `belovedcollege.com` | School information, admissions, contact, news, gallery, result checker and portal entry |
| Full web portal | `web.belovedcollege.com` | Complete desktop-oriented administration, teaching, finance and portal workspaces |
| Mobile portal | `app.belovedcollege.com` | Mobile-first installable portal using the same Laravel backend and database |

The three surfaces are not separate school systems. They share one authentication system, one authorization model, one database, one file store and one set of domain services.

## Implemented platform areas

- audience-aware authentication, password recovery, email verification and database-session security
- role and permission boundaries
- Students, Parents, Staff and reversible archival
- teacher class-subject access
- academic sessions, terms, classes, subjects and promotions
- lessons, assignments, attendance and grading
- Student and Parent portals
- reports, report cards and public result checking
- complete objective and theory CBT workflow
- fee catalogue, invoices, collections and receipts
- verified Paystack, Flutterwave and Monnify settlement
- responsive Classic and Dark interfaces
- public website, CMS and semantic theme administration
- targeted announcements, notification inboxes and Parent absence alerts
- audit search, queue controls, system health and encrypted SMTP administration
- permanent account security events and Admin-controlled identity policy
- read-only migration inventory, finance reconciliation, file checksums and deployment preflight on the current release branch

See [`docs/migration/IMPLEMENTATION_STATUS.md`](docs/migration/IMPLEMENTATION_STATUS.md) for the current release-by-release status.

## Migration readiness commands

```bash
php artisan migration:inventory --connection=legacy
php artisan migration:reconcile --source=legacy --target=mysql --strict
php artisan migration:files --connection=mysql --disk=local --disk=public --strict
php artisan deployment:preflight --strict
```

These commands generate confidential JSON reports and do not perform the production data transfer.

## Non-negotiable requirements

1. **Functional parity before replacement** — no approved legacy function may disappear silently.
2. **Existing data preservation** — production records are never used as an experimental migration target.
3. **Uploaded-file preservation** — legacy storage paths are inventoried and mapped before cutover.
4. **Laravel ownership of business logic** — controllers remain thin; domain operations live in actions and services.
5. **Policy-based authorization** — interface visibility never replaces server-side authorization.
6. **Exactly two visual themes** — Classic and Dark.
7. **Flat interface hierarchy** — avoid nested cards and cascading boxes; use direct tables, lists, forms, tabs and drawers.
8. **Mobile-first behavior** — every workflow must remain usable on a phone even when the full web portal is selected.
9. **Test-backed migration** — parity tests are required before each legacy module is retired.
10. **Reversible deployment** — backups, reconciliation and rollback are required before production cutover.

## Documentation

- [`docs/architecture/PLATFORM_ARCHITECTURE.md`](docs/architecture/PLATFORM_ARCHITECTURE.md)
- [`docs/migration/FUNCTIONAL_PARITY_REGISTER.md`](docs/migration/FUNCTIONAL_PARITY_REGISTER.md)
- [`docs/migration/IMPLEMENTATION_STATUS.md`](docs/migration/IMPLEMENTATION_STATUS.md)
- [`docs/migration/DATA_AND_FILE_PRESERVATION.md`](docs/migration/DATA_AND_FILE_PRESERVATION.md)
- [`docs/migration/MIGRATION_DEPLOYMENT_READINESS.md`](docs/migration/MIGRATION_DEPLOYMENT_READINESS.md)
- [`docs/migration/PRODUCTION_CUTOVER_RUNBOOK.md`](docs/migration/PRODUCTION_CUTOVER_RUNBOOK.md)
- [`docs/migration/PRODUCTION_ROLLBACK_RUNBOOK.md`](docs/migration/PRODUCTION_ROLLBACK_RUNBOOK.md)
- [`docs/ui/INTERFACE_PRINCIPLES.md`](docs/ui/INTERFACE_PRINCIPLES.md)
- [`docs/workflows/LEARNING_RESULTS_CBT.md`](docs/workflows/LEARNING_RESULTS_CBT.md)
- [`docs/workflows/FINANCE_AND_PAYMENTS.md`](docs/workflows/FINANCE_AND_PAYMENTS.md)
- [`docs/workflows/PUBLIC_CMS_THEME_MANAGER.md`](docs/workflows/PUBLIC_CMS_THEME_MANAGER.md)
- [`docs/workflows/COMMUNICATION_SYSTEM_ADMINISTRATION.md`](docs/workflows/COMMUNICATION_SYSTEM_ADMINISTRATION.md)
- [`docs/workflows/IDENTITY_HARDENING.md`](docs/workflows/IDENTITY_HARDENING.md)

## Local setup

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
npm run build
php artisan test
```

The default local database is SQLite. Production database credentials and secrets must remain outside source control.

## Development workflow

- `main` contains reviewed, deployable work.
- Feature work is developed on dedicated branches.
- Pull requests must pass formatting, frontend build and automated tests.
- Destructive production migrations are prohibited until a verified backup and migration rehearsal exist.

## Migration status

The core school workflows, public website, payments, communications, operational administration and identity hardening are implemented and test-backed. The current release adds read-only migration and deployment evidence tooling. Production data and uploaded files have not been migrated or modified; staging rehearsal, reconciliation, role acceptance and formal cutover approval remain required.

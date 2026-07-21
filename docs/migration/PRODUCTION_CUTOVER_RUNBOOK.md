# Production Cutover Runbook

This runbook begins only after a successful staging rehearsal and written approval. Replace placeholders with the actual hosting commands before the change window.

## Required approvals

- technical owner
- school operations owner
- finance owner
- data-migration owner
- hosting administrator
- rollback decision owner

## T minus 7 days

1. Confirm public, `web.` and `app.` DNS records and TLS certificates.
2. Confirm production PHP 8.4 and required extensions.
3. Confirm database, storage, queue worker, scheduler and SMTP capacity.
4. Confirm payment-provider production callback URLs and secrets outside source control.
5. Confirm the original APP_KEY fingerprint.
6. Complete a full staging rehearsal using a recent database and upload copy.
7. Archive all staging reports and sign-off evidence.
8. Announce the maintenance window and business freeze.

## T minus 24 hours

1. Run the final legacy inventory.
2. Run the final legacy file manifest.
3. Record current row counts and financial totals.
4. Verify backup destination capacity and restore credentials.
5. Verify rollback environment and previous application release.
6. Verify the queue supervisor and scheduler cron commands.
7. Reduce nonessential production changes.

## Start of change window

1. Put the old system into maintenance or read-only mode.
2. Block new payment initialization while allowing already-started provider callbacks to settle according to the approved freeze procedure.
3. Stop legacy scheduled jobs and queue workers.
4. Record the exact freeze timestamp in UTC and local school time.
5. Take a final database backup.
6. Take a final uploaded-file backup.
7. Verify backup completion and checksums before continuing.

## Prepare New2 release

1. Deploy the approved `main` commit.
2. Install production dependencies:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
```

3. Install the approved `.env` without exposing it in deployment logs.
4. Confirm:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://belovedcollege.com
PUBLIC_HOST=belovedcollege.com
WEB_PORTAL_HOST=web.belovedcollege.com
APP_PORTAL_HOST=app.belovedcollege.com
SESSION_DRIVER=database
SESSION_DOMAIN=.belovedcollege.com
QUEUE_CONNECTION=database
CACHE_STORE=database
```

5. Confirm the original APP_KEY and approved fingerprint.
6. Run:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
```

## Transfer data and files

The approved migration procedure must:

1. import the frozen database snapshot or transform records through reviewed scripts
2. preserve primary keys and foreign keys unless an approved mapping says otherwise
3. preserve password hashes
4. preserve encrypted values with the original APP_KEY
5. copy uploaded files before changing database paths
6. verify file size and SHA-256 after copy
7. never delete source files during cutover

## Reconcile before opening traffic

Run:

```bash
php artisan migration:inventory --connection=<target>
php artisan migration:reconcile --source=<frozen-source> --target=<target> --strict
php artisan migration:files --connection=<target> --disk=local --disk=public --strict
php artisan system:heartbeat scheduler
php artisan deployment:preflight --strict
```

The system remains closed when any command returns failure.

## Start runtime services

1. Start queue workers or the configured process supervisor.
2. Confirm the `notifications` queue is consumed.
3. Confirm cron runs:

```text
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

4. Confirm scheduler heartbeat is current.
5. Send a synchronous SMTP test.
6. Verify payment webhooks with provider-approved non-destructive tests.

## Role acceptance checks

### Public visitor

- homepage, About, Admissions, Contact, News and Gallery
- result checker
- correct HTTPS and trusted hosts

### Student

- login with approved identifier
- dashboard, lessons, assignments and results
- notification inbox
- Account Security

### Parent

- login and child switching
- invoices, receipts and balances
- result viewing
- absence notification visibility

### Teacher

- authorized classes and subjects only
- lessons, assignments, attendance, result entry and CBT
- file access boundaries

### Accountant

- invoices, debtors, collections and receipts
- no unauthorized academic/system controls

### Principal

- academic/report/communication authority
- no SMTP, queue or system-secret access

### Admin and Super Admin

- people, academics, finance, website, communication, themes and system health
- audit and security ledgers
- SMTP and queue administration

## Open traffic

Traffic may open only after:

- strict reconciliation passes
- strict file manifest passes
- strict preflight passes
- all role checks pass
- finance owner approves totals
- rollback owner confirms rollback remains available

Then:

1. remove New2 maintenance mode
2. update or enable final DNS routing
3. monitor HTTP errors, queue failures, scheduler heartbeat, payment callbacks and SMTP
4. retain the old environment and backups unchanged for the approved rollback period

## First 24 hours

- run reconciliation at 1 hour, 6 hours and 24 hours
- inspect failed jobs and audit logs
- compare payment totals with providers
- verify new uploads and private downloads
- verify notifications and mail delivery
- record every production intervention

## Closure

Cutover closes only after:

- 24-hour reconciliation is approved
- no unresolved critical incident exists
- finance settlement matches
- backups and reports are archived
- operational ownership is handed over

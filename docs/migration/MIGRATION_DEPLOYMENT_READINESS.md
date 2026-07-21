# Migration and Deployment Readiness

This release provides read-only tooling for staging rehearsals and production cutover approval. The commands inspect data and files but do not insert, update, delete, rename or move production records.

## Safety boundary

By default, readiness commands are blocked when `APP_ENV=production`.

Run them against:

- a recent read-only copy of the legacy database
- a staging New2 database
- a staging copy of uploaded files

Production inspection requires an explicit temporary setting:

```dotenv
MIGRATION_READINESS_ALLOW_PRODUCTION=true
```

That setting does not grant database write access. It only removes the application-level environment guard. Database credentials used for the legacy source should still be read-only at the database-server level.

## Configuration

Configure the source database without replacing the normal New2 connection:

```dotenv
LEGACY_DB_CONNECTION=legacy
LEGACY_DB_DRIVER=mysql
LEGACY_DB_HOST=127.0.0.1
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=beloved_college_legacy_copy
LEGACY_DB_USERNAME=legacy_read_only
LEGACY_DB_PASSWORD=

MIGRATION_TARGET_CONNECTION=mysql
MIGRATION_REPORT_DISK=local
MIGRATION_REPORT_DIRECTORY=migration-reports
MIGRATION_READINESS_ALLOW_PRODUCTION=false
```

Do not commit real credentials.

## APP_KEY continuity

Legacy encrypted settings and any encrypted database values require the original application key.

Record only its SHA-256 fingerprint:

```bash
php -r 'echo hash("sha256", getenv("APP_KEY")), PHP_EOL;'
```

Place that fingerprint in the staging or deployment environment:

```dotenv
MIGRATION_EXPECTED_APP_KEY_FINGERPRINT=<64-character-sha256>
```

The preflight report compares fingerprints without exposing the key.

## Database inventory

```bash
php artisan migration:inventory --connection=legacy
php artisan migration:inventory --connection=mysql
```

The report includes:

- driver and database name
- table and total row counts
- columns, indexes and foreign keys
- duplicate groups for configured identity fields
- foreign-key orphan counts
- exact invoice and payment totals
- warnings when a table cannot be inspected

Use `--no-schema` for a smaller row/integrity report.

## Source-to-target reconciliation

```bash
php artisan migration:reconcile \
  --source=legacy \
  --target=mysql \
  --strict
```

The command compares:

- every source and target table row count
- invoice count
- total billed
- invoice amount paid
- outstanding balance
- overpayments
- payment count
- all payment amounts
- successful payment count and amount
- successful payments not attached to an invoice

Financial values are represented in minor units. For NGN, `125000.50` is reported as `12500050` kobo, avoiding floating-point rounding.

Any mismatch appears in `critical_findings`. `--strict` returns a failure exit code when a mismatch exists.

## File manifest

```bash
php artisan migration:files \
  --connection=mysql \
  --disk=local \
  --disk=public \
  --strict
```

The command discovers database columns whose names indicate file paths and records:

- source table and record ID
- source column
- stored and normalized path
- disk
- found, missing, external, unsafe or error state
- byte size
- MIME type
- SHA-256 checksum

External URLs are recorded but not downloaded. Paths containing traversal segments are marked unsafe.

For trial runs, limit the number of references:

```bash
php artisan migration:files --max-files=500
```

A limited report is marked `stopped_early` and cannot be treated as final migration evidence.

## Deployment preflight

```bash
php artisan deployment:preflight --strict
```

Checks include:

- APP_KEY and optional approved fingerprint
- production debug state
- HTTPS application URL
- trusted public, web and app hosts
- PHP version and extensions
- database connectivity and required tables
- pending migrations
- private storage write/read/delete
- `storage` and `bootstrap/cache` permissions
- persistent queue configuration
- scheduler heartbeat
- shared database sessions
- minimum mail configuration

The console summary is accompanied by a complete JSON report.

## Report storage

Default report location:

```text
storage/app/private/migration-reports/
```

Each report contains:

- report type
- generation time
- environment
- application name
- Laravel version
- command-specific findings

Reports may contain table names, record identifiers, stored paths, counts and financial totals. Treat them as confidential operational documents.

## Approval criteria

A staging rehearsal is eligible for cutover approval only when:

1. source and target reconciliation returns `pass`
2. invoice equation mismatches equal zero
3. all required file references are found and checksummed
4. no unsafe file paths exist
5. deployment preflight has no critical finding
6. queue and scheduler are observed running
7. role-based acceptance tests pass
8. backup and rollback rehearsal evidence exists
9. the approved original APP_KEY fingerprint matches
10. the final change window is approved

No command in this release performs the production data transfer. Import/copy execution remains a separately reviewed operation.

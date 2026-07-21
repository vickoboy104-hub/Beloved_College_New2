# Data and File Preservation Plan

## Purpose

This document defines the controls required to move the existing Beloved College application to New2 without losing records, encrypted settings, uploaded files or historical relationships.

## 1. Production safety rules

- Development never runs against the live production database.
- Production credentials are never committed to GitHub.
- No destructive migration is run before a verified backup and staging rehearsal.
- No legacy table or column is dropped during the functional-parity phase.
- No legacy repository is overwritten.
- A rollback path is prepared before production cutover.

## 2. Required source assets

The migration source consists of more than the GitHub repository.

Required assets:

1. Full database dump.
2. Original Laravel `.env` values needed for compatibility.
3. Original `APP_KEY`.
4. `storage/app` contents.
5. `storage/app/private` contents where present.
6. `storage/app/public` contents.
7. Existing `public/uploads` contents.
8. Web server configuration relevant to storage links and upload limits.
9. Scheduled-task and queue-worker configuration.
10. Payment-provider callback and webhook configuration.

## 3. Why the original APP_KEY is required

The legacy application encrypts sensitive settings using Laravel encryption. The original `APP_KEY` is required to decrypt existing values such as payment secrets and mail credentials.

The key is transferred through the deployment secret manager, never through Git history or documentation.

Changing the key before encrypted values are safely re-encrypted would make those values unreadable.

## 4. Password compatibility

Existing user passwords are preserved as hashes in the database. The rebuild must continue using Laravel-compatible password hashing so users are not forced to reset passwords solely because the interface changed.

Temporary-password behavior and forced first-password change are tested separately.

## 5. Database inventory

Before module migration, capture:

- Database engine and exact version.
- Table list.
- Column names and types.
- Primary keys.
- Foreign keys.
- Unique indexes.
- Enum or status values.
- Row counts.
- Nullability and defaults.
- Orphaned records.
- Duplicate business identifiers.
- Largest tables.
- Tables containing encrypted or JSON values.

The inventory is stored as a dated migration artifact and compared with the New2 schema plan.

## 6. Migration classes

Schema changes are classified as:

### Compatible additions

Examples:

- Nullable column.
- New table.
- New index after duplicate review.

These may be introduced during parity work.

### Staged transformations

Examples:

- Renaming a column.
- Normalizing a status value.
- Moving a file path.
- Replacing a role field with role and permission tables.

These require:

1. Add the new structure.
2. Backfill safely.
3. Read from both during transition where necessary.
4. Verify totals and behavior.
5. Switch writes.
6. Remove the old structure only in a later release.

### Destructive changes

Examples:

- Dropping tables or columns.
- Changing primary keys.
- Deleting historical records.

These are prohibited until the old system has been retired, backups have been tested and explicit approval is recorded.

## 7. File inventory

For every database field containing a file path, record:

- Model and record ID.
- Database field.
- Stored path.
- Expected storage disk.
- Physical source file.
- File size.
- MIME type.
- SHA-256 checksum.
- Target path.
- Access classification: public or private.
- Migration result.

Missing source files are reported; they are not silently removed from the database.

## 8. File migration behavior

- Copy first; do not move or delete the source.
- Verify the copied file checksum.
- Preserve stable URLs where practical.
- Use authorized download routes for private files.
- Use a public disk or object storage for intentionally public media.
- Update database paths only after file verification.
- Keep a path-translation map during transition.

## 9. Staging rehearsal

A migration rehearsal uses:

- A recent sanitized or access-controlled database copy.
- A copy of uploaded media.
- The same database engine as production where possible.
- Production-like queue, cache and filesystem configuration.

The rehearsal runs the complete migration and reconciliation process without modifying production.

## 10. Reconciliation checks

At minimum compare old and new values for:

- Users by role and status.
- Students by class and status.
- Parent-child links.
- Staff profiles.
- Teacher class-subject assignments.
- Academic sessions, terms, classes and subjects.
- Lessons and assignments.
- Submissions and grading records.
- Attendance entries.
- Assessments and results.
- CBT questions, attempts and answers.
- Term reports and publication settings.
- Fee items and invoices.
- Payments by provider and status.
- Total billed, collected and outstanding amounts.
- Promotion records.
- Announcements and contact messages.
- Settings.
- File counts and checksums.

Financial totals must reconcile exactly to the smallest stored currency unit.

## 11. Cutover sequence

1. Confirm staging sign-off.
2. Confirm current database and media backups.
3. Put the legacy application into maintenance or read-only mode.
4. Capture the final database dump and file delta.
5. Run approved additive migrations.
6. Copy and verify final media changes.
7. Run reconciliation commands.
8. Warm caches and build production assets.
9. Start queue workers and scheduler.
10. Switch traffic to New2.
11. Run smoke tests for every role and critical workflow.
12. Monitor logs, queues and payments.

## 12. Rollback

Rollback is triggered when critical authentication, finance, result, file or permission checks fail.

Rollback actions:

- Route traffic back to the legacy deployment.
- Restore the pre-cutover database only when New2 has written incompatible data.
- Preserve New2 logs and failure evidence.
- Do not delete the failed deployment until the incident is understood.

## 13. Required automated commands

New2 will include read-only Artisan commands for:

- Legacy schema inspection.
- Row-count snapshots.
- Financial reconciliation.
- File-path inventory.
- File checksum verification.
- Orphan detection.
- Migration readiness reporting.

Commands that can modify production data require an explicit `--apply` option and confirmation guard.

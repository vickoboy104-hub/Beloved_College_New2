# Protected Staging Inputs

This checklist defines the minimum protected inputs required before the Beloved College staging rehearsal can be executed truthfully.

The files, credentials and secrets described here must never be committed to GitHub, pasted into issues, or shared through ordinary chat messages.

## Gate status

A staging rehearsal is blocked until every required input has:

- a named owner
- an acquisition date
- a source-system reference
- a storage location
- a checksum or fingerprint where applicable
- a custody and access record
- an explicit approval for staging use

## 1. Legacy database snapshot

Required evidence:

- database engine and version
- backup filename or snapshot identifier
- backup start and completion timestamps
- source production database identifier
- backup size in bytes
- SHA-256 checksum
- encrypted storage location
- person who created the backup
- person who verified restoration

Rules:

- restore only into an isolated staging database
- never point New2 development or tests at production
- create a dedicated read-only legacy database account for inventory and comparison
- retain the untouched backup used for the rehearsal
- document any repair performed after restoration

Suggested environment fields:

```dotenv
LEGACY_DB_CONNECTION=legacy
LEGACY_DB_DRIVER=mysql
LEGACY_DB_HOST=
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=
LEGACY_DB_USERNAME=
LEGACY_DB_PASSWORD=
```

## 2. Legacy uploaded files

Provide two isolated roots where the old system used both private and publicly served files:

```dotenv
LEGACY_PRIVATE_FILES_ROOT=
LEGACY_PUBLIC_FILES_ROOT=
```

For each root record:

- source server and source directory
- copy start and completion timestamps
- file count
- total bytes
- checksum manifest
- unreadable or skipped files
- symbolic links
- path normalization decisions
- person who performed the copy
- person who verified the copy

Rules:

- mount or copy the roots read-only during the first rehearsal
- preserve original relative paths
- do not rename, resize or recompress files before checksum comparison
- quarantine unsafe paths instead of silently rewriting them
- retain the original copied roots until production acceptance is complete

## 3. Application encryption key continuity

The original production `APP_KEY` is required when legacy encrypted values must remain readable.

Preferred handling:

1. store the key in the approved secret manager or protected environment configuration
2. calculate its SHA-256 fingerprint separately
3. configure the expected fingerprint for preflight comparison
4. never place the plaintext key in reports, GitHub or acceptance evidence

```dotenv
MIGRATION_EXPECTED_APP_KEY_FINGERPRINT=
```

The report may contain only:

- whether a key is configured
- its SHA-256 fingerprint
- whether the fingerprint matches the approved value

## 4. Rehearsal application build

Record:

- exact Git commit SHA
- deployment artifact or release identifier
- Composer lock-file checksum
- package-lock checksum
- production asset manifest checksum
- PHP version and extensions
- database driver version

```dotenv
APP_COMMIT_SHA=
```

A rehearsal is invalid when the tested commit cannot be identified exactly.

## 5. Staging infrastructure

Provide and verify:

- public staging hostname
- full-web staging hostname
- mobile-portal staging hostname
- valid TLS certificates
- isolated target database
- private storage path
- public storage path
- database queue worker
- scheduler cron invoking `schedule:run` every minute
- SMTP test transport
- payment-provider sandbox credentials and callback URLs
- log and monitoring access

Do not use live payment credentials during rehearsal.

## 6. Named operators and approvers

The rehearsal evidence must name:

- migration operator
- technical owner
- migration/data owner
- operations/hosting owner
- finance reconciliation owner
- rollback owner

Each approval must include:

- full name
- role
- status
- timestamp
- notes or evidence reference

## 7. Role acceptance testers

Assign a real tester for each role:

- Public Visitor
- Student
- Parent
- Teacher
- Accountant
- Principal
- Admin
- Super Admin

Each tester must provide:

- name
- date/time
- pass or fail status
- tested workflows
- screenshots, report identifiers or other evidence references
- defects found
- retest outcome where applicable

## 8. Rehearsal command

After all protected inputs are available, run from the approved New2 commit:

```bash
php artisan deployment:rehearse <rehearsal-id> \
  --source=legacy \
  --target=<staging-target-connection> \
  --source-snapshot=<backup-identifier> \
  --operator="<named-operator>" \
  --commit="$APP_COMMIT_SHA" \
  --source-disk=legacy_private \
  --source-disk=legacy_public \
  --target-disk=local \
  --target-disk=public \
  --strict
```

The first run creates the acceptance template. After role testing and approvals, rerun with:

```bash
php artisan deployment:rehearse <rehearsal-id> \
  --source=legacy \
  --target=<staging-target-connection> \
  --source-snapshot=<backup-identifier> \
  --operator="<named-operator>" \
  --commit="$APP_COMMIT_SHA" \
  --source-disk=legacy_private \
  --source-disk=legacy_public \
  --target-disk=local \
  --target-disk=public \
  --acceptance=<acceptance-json-path> \
  --strict \
  --require-acceptance
```

## 9. Absolute stop conditions

Do not proceed to production when any of the following is true:

- database backup cannot be restored independently
- original `APP_KEY` continuity is unresolved
- source and target are the same database
- file manifests contain unexplained missing files
- financial reconciliation differs
- critical orphan or duplicate findings remain
- scheduler, queue, mail or storage preflight is critical
- any required role test failed or remains pending
- any required owner rejected or has not approved
- rollback restoration has not been rehearsed
- the evidence package does not report `cutover_eligible: true`

## 10. Evidence custody

Store generated rehearsal packages in protected storage with:

- read-only retention after approval
- access logging
- package-level SHA-256 manifest
- immutable copy of the acceptance file
- reference to the exact legacy backup and file-root manifests
- reference to the exact application commit

The evidence package proves what was tested. It must not contain plaintext credentials, `APP_KEY`, payment secrets, reset tokens or personally unnecessary data.

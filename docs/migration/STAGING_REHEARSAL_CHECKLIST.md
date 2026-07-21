# Staging Migration Rehearsal Checklist

Complete this checklist for every rehearsal. Keep reports and approval records together under one rehearsal identifier.

## Rehearsal identity

- rehearsal ID
- date and time
- source backup timestamp
- legacy source connection name
- New2 target connection name
- application commit SHA
- operator
- reviewers

## Protected inputs

- [ ] legacy database restored to an isolated staging database
- [ ] legacy database user is read-only
- [ ] legacy uploaded files copied to isolated staging storage
- [ ] original APP_KEY supplied through protected environment configuration
- [ ] approved APP_KEY SHA-256 fingerprint configured
- [ ] no production DNS points to staging
- [ ] payment providers use sandbox credentials or remain disabled
- [ ] outbound email uses a safe test recipient or non-delivery transport

## Baseline evidence

Run and archive:

```bash
php artisan migration:inventory --connection=legacy
php artisan migration:files --connection=legacy --disk=local --disk=public
```

Record:

- source table count
- source total rows
- duplicate identity groups
- orphan counts
- source invoice totals
- source payment totals
- source file reference count
- missing source files

## Target migration

- [ ] deploy approved commit
- [ ] install dependencies and build assets
- [ ] apply staging environment configuration
- [ ] clear caches
- [ ] run migrations
- [ ] execute only reviewed data/file transfer procedures
- [ ] preserve source IDs and mappings
- [ ] preserve password hashes
- [ ] preserve encrypted values
- [ ] copy files before changing stored paths

## Target evidence

Run and archive:

```bash
php artisan migration:inventory --connection=<target>
php artisan migration:reconcile --source=legacy --target=<target> --strict
php artisan migration:files --connection=<target> --disk=local --disk=public --strict
php artisan system:heartbeat scheduler
php artisan deployment:preflight --strict
```

- [ ] reconciliation status is pass
- [ ] financial differences are zero
- [ ] invoice equation mismatches are zero
- [ ] required files are found
- [ ] no unsafe paths exist
- [ ] checksums are recorded
- [ ] preflight has no critical or strict warning result

## Role acceptance

- [ ] public visitor
- [ ] Student
- [ ] Parent with multiple children
- [ ] Teacher with exact class-subject scope
- [ ] Accountant
- [ ] Principal
- [ ] Admin
- [ ] Super Admin

For every role, test authentication, authorization, one read workflow and one approved write workflow.

## Operational acceptance

- [ ] queue worker processes notifications
- [ ] scheduler heartbeat remains current
- [ ] SMTP test succeeds in the safe staging configuration
- [ ] password reset link uses the correct host
- [ ] verification link uses the correct host
- [ ] public and private file access boundaries hold
- [ ] payment sandbox verification is idempotent
- [ ] audit and security events are recorded
- [ ] Classic and Dark themes render correctly

## Rollback rehearsal

- [ ] record rollback start time
- [ ] preserve target database and files
- [ ] restore the pre-rehearsal target snapshot
- [ ] verify the restored target
- [ ] document elapsed time
- [ ] document manual steps
- [ ] document defects and corrective actions

## Approval

The rehearsal passes only with written approval from:

- technical owner
- data migration owner
- school operations owner
- finance owner
- rollback owner

A failed or incomplete rehearsal cannot support production cutover approval.

# Production Rollback Runbook

Use this procedure when the approved New2 cutover cannot continue safely.

## Rollback triggers

Stop the cutover when any of these conditions occurs:

- database reconciliation does not pass
- financial totals do not match
- required files are missing or checksums differ
- the approved APP_KEY fingerprint does not match
- migrations remain pending
- trusted-host, HTTPS, session, queue or scheduler checks are critical
- a required user role cannot complete acceptance testing
- payment references are duplicated or incorrectly allocated
- private records or files are accessible to unauthorized users

## Preserve evidence

Before restoring the previous system:

1. Record the rollback decision time and responsible owner.
2. Record the deployed commit and configuration version.
3. Save the generated inventory, reconciliation, file and preflight reports.
4. Save relevant application logs and failed-job records.
5. Back up the current New2 database.
6. Back up files created after the cutover began.
7. Keep the original frozen legacy backups unchanged.

## Before public traffic opens

When New2 has not accepted production writes:

1. Keep New2 in maintenance mode.
2. Stop New2 queue workers and scheduler.
3. Restore the previous application routing.
4. Confirm the legacy database and files match the frozen backup.
5. Start the legacy runtime services.
6. Complete legacy smoke tests before reopening access.

## After public traffic opens

New2 may contain new payments, messages, attendance, results, submissions, uploads and security events. Preserve and export all records created after the cutover timestamp before restoring writes to the previous system.

Required sequence:

1. Put New2 into maintenance mode.
2. Stop New2 queue workers and scheduler.
3. Pause new payment initialization.
4. Take emergency New2 database and file backups.
5. Export records created after the cutover timestamp.
6. Restore the previous application and approved legacy backup.
7. Reconcile post-cutover records through a separately reviewed correction process.
8. Start legacy queue and scheduler services.
9. Run acceptance checks before reopening traffic.

## Payment reconciliation

For each payment provider:

1. Export transactions after the cutover timestamp.
2. Compare reference, gateway reference, amount, currency and status.
3. Identify transactions present in New2 but absent from the restored system.
4. Prevent the same provider callback from being applied twice.
5. Record every correction with operator, reason and supporting evidence.
6. Obtain finance-owner approval.

## File reconciliation

For each file uploaded after cutover:

1. Preserve the New2 file and owning record metadata.
2. Calculate SHA-256.
3. Map the file to its model and record.
4. Copy it only after the owning record is reconciled.
5. Verify byte size and checksum after copy.
6. Retain the emergency New2 backup.

## Validation after rollback

Confirm:

- public pages respond
- all login audiences work
- Student, Parent, Teacher, Accountant, Principal and Admin authorization is correct
- results and reports are available
- invoices, balances, payments and receipts reconcile
- payment callbacks are processed exactly once
- uploaded and private files work
- queue and scheduler services run
- essential email delivery works

## Closure

Rollback closes only after:

- the restored system is stable
- all records created during the failed window are accounted for
- finance approves payment reconciliation
- all new files are preserved
- the root cause is documented
- a corrected staging rehearsal is approved

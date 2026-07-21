# Communication and System Administration

This release adds the operational communication layer and the administration tools required to run the Beloved College platform safely.

## Notification centre

Every authenticated user receives a notification inbox on both portal surfaces.

Users can:

- review all notifications
- filter unread notifications
- open a notification and mark it read
- mark every notification read
- remove their own notification

A user cannot read, modify or delete another user's notification. Unread counts appear in desktop and mobile navigation.

## Targeted announcements

Users with `communication.manage_announcements` can create announcements for:

- every active user
- selected roles
- selected classes
- selected individual users
- a union of selected roles, classes and users

Class targeting includes the Students in the selected classes and their linked Parent accounts.

Announcements support:

- title, summary and full content
- category
- low, normal, high or urgent priority
- portal notification delivery
- optional queued email delivery
- optional public News publication
- start date/time
- expiry date/time
- draft, scheduled, dispatched, expired and cancelled states
- recipient and channel delivery history

The `announcement_deliveries` ledger has a unique announcement/user constraint, so repeated callbacks, button presses or scheduler runs cannot create duplicate recipient deliveries.

## Scheduling

The Laravel scheduler runs:

- `system:heartbeat scheduler` every minute
- `communications:dispatch-scheduled` every minute
- failed-job pruning every day for failures older than seven days

Production cron must execute `php artisan schedule:run` every minute. The System Health workspace flags a stale or missing scheduler heartbeat.

## Delivery channels

Portal notifications use Laravel's database notification channel synchronously, so the inbox updates immediately.

Email notifications use the configured queue and the `notifications` queue name. Production requires a continuously running queue worker. Email delivery is optional and is skipped when a recipient has no email address.

The delivery ledger records:

- intended channels
- queued time
- delivery time
- failure time
- delivered, partial or failed state
- failure summary

## Parent absence alerts

When a teacher saves bulk attendance and a Student is marked absent:

1. the existing attendance transaction completes
2. the linked Parent receives a portal notification
3. optional email delivery is queued
4. `absence_notified_at` prevents duplicate alerts for the same attendance record

The alert is skipped when:

- the status is not absent
- no Parent account is linked
- absence notifications are disabled
- the attendance record was already notified

## System Health

Admin and Super Admin can inspect:

- database connectivity and latency
- private storage write/read/delete access
- pending queue jobs
- failed jobs
- oldest queued-job age
- scheduler heartbeat freshness
- mail transport configuration state
- Laravel and PHP versions
- environment and debug state

Queue and scheduler warning thresholds are configurable.

## Audit logs

The searchable audit viewer supports filters for:

- user
- HTTP method
- action
- route
- status code
- date range

Audit metadata remains sanitized by the existing audit middleware; passwords, PINs, tokens and payment secrets are not displayed.

## Queue administration

The System workspace displays pending and failed jobs. Authorized administrators can:

- inspect a truncated exception
- retry a failed job
- delete a failed-job record

Retry should occur only after the underlying data or configuration issue is corrected.

## SMTP administration

Admin and Super Admin can configure:

- SMTP, log or array mailer
- host and port
- SMTP scheme
- username
- encrypted password
- timeout
- sender address and sender name

Blank password submissions preserve the existing encrypted password. The test-delivery action sends synchronously so the interface can report an immediate transport error.

Queued announcement, absence and public-enquiry notifications apply the database mail configuration when the worker processes them.

## Permissions

- Super Admin: full communication and system access
- Admin: full communication and system access
- Principal: announcement and communication access; no SMTP, queue or system settings
- Accountant, Teacher, Parent and Student: personal notification inbox only by default

## Data preservation

The release adds:

- `notifications`
- `announcement_deliveries`
- `system_heartbeats`
- targeted delivery fields on `announcements`
- `absence_notified_at` on `attendance_records`

Existing announcements, audit logs, settings, queue tables and failed-job history remain in place. Rollback intentionally does not drop operational history.

## Automated verification

The release tests:

- class targeting to Student and linked Parent
- exclusion of unrelated users
- delivery idempotency
- scheduled role dispatch
- Parent absence alert idempotency
- inbox rendering, reading and deletion
- cross-user notification ownership denial
- Admin, Principal and System permission boundaries
- scheduler heartbeat and health rendering
- encrypted SMTP password storage
- blank password preservation
- test mail availability
- audit-log filtering
- failed-job deletion
- notifications, delivery, heartbeat and absence schema

The formatted branch must pass the repository's standard read-only Composer, migration, production asset, Pint and Laravel test pipeline before merge.

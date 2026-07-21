# Identity Hardening

This release completes framework-native password recovery, email verification, database-session control and permanent account security history across the full-web and mobile portal surfaces.

## Password recovery

Both portal hosts provide:

- forgot-password form
- generic, non-enumerating submission response
- expiring email reset link
- reset form
- password policy validation
- surface-aware return to the correct portal login

Recovery is email-only. Admission numbers, student IDs and employee numbers remain valid sign-in identifiers but cannot receive email links.

Reset links are issued only when the matching account is active and not archived. The same public confirmation is returned for unknown, inactive and active email addresses.

A completed reset:

- requires a valid password-broker token
- requires at least 10 characters, uppercase and lowercase letters and a number
- clears the temporary-password requirement
- records `password_changed_at`
- rotates the remember token
- revokes every existing database session
- records a critical security event
- creates an account-security notification

Inactive or archived accounts cannot complete a reset even when an older token still exists.

## Email verification

The User model implements Laravel's email-verification contract. Both portal surfaces provide:

- verification notice
- throttled resend
- signed, expiring verification URL
- surface-aware redirect after verification
- permanent security event and account notification

Verification enforcement is disabled by default for legacy safety.

When Admin enables enforcement:

- unverified users with an email are redirected to the verification notice
- verified users continue normally
- users without an email are not locked out
- temporary-password replacement occurs before verification enforcement
- verification, Security, Notifications and Sign-out remain accessible

## Account Security workspace

Every authenticated user can review:

- assigned email address and verification state
- password-change date
- last successful sign-in and IP address
- current verification policy
- active database sessions
- device and browser summary
- recent permanent security events

Users can:

- change their password after confirming the current password
- revoke an owned non-current session
- revoke every other session after confirming the current password
- request email verification

A user cannot inspect or revoke another user's session. The current session cannot be deleted through the single-session action; the normal Sign out action is used instead.

## Password changes

Self-service password changes require:

- current password
- at least 10 characters
- uppercase and lowercase letters
- a number
- confirmation

The existing mandatory temporary-password replacement remains compatible with its established minimum requirement. Both ordinary and mandatory password changes:

- rotate the remember token
- record the password-change time
- revoke other sessions
- create a permanent security event
- create an account-security alert

## Login history

Every successful sign-in records:

- user
- event type
- time
- IP address
- user agent
- portal host
- previous successful-login IP

The User record also retains the latest successful-login time and IP address.

Optional successful-login alerts may be enabled by Admin. They are disabled by default to avoid notification noise.

## Security alerts

Security notifications always use the portal database channel. Optional email delivery uses the existing encrypted runtime mail configuration and notification queue.

Events include:

- successful login
- initial password replacement
- password change
- password reset
- email verification
- individual session revocation
- bulk session revocation

Session identifiers are never written to security-event metadata in plaintext. When a session identifier is needed for correlation, only a SHA-256 digest is stored.

## Administrator policy

Admin and Super Admin can review migration-readiness counts:

- total users
- users with email
- verified emails
- unverified emails
- users without email
- active database sessions

They can control:

- whether verified email is required for users who have an email address
- whether security alerts are emailed
- whether every successful sign-in creates an alert

The System workspace also displays the recent security ledger. Principal and all lower roles cannot change identity policy.

## Data preservation

The release reuses the existing:

- `users`
- `password_reset_tokens`
- `sessions`
- `notifications`
- `settings`

It adds only:

- `users.password_changed_at`
- `users.last_login_at`
- `users.last_login_ip`
- `security_events`

Rollback intentionally preserves account security history and timestamps.

## Automated verification

The release tests:

- private non-enumerating reset requests
- surface-aware reset notifications
- valid reset completion
- invalid token rejection
- complete session revocation after reset
- password and remember-token updates
- signed email verification
- invalid-signature rejection
- legacy-safe verification policy
- temporary-password precedence
- session visibility and ownership
- single-session revocation
- cross-user revocation denial
- password-driven session revocation
- successful-login history
- Admin-only policy changes
- identity readiness counts and security ledger
- identity, reset-token, session and security-event schema

Multi-factor authentication is intentionally not enabled in this release. Enrollment, recovery-code custody, lost-device support and emergency administrator recovery require explicit product and operational approval before implementation.

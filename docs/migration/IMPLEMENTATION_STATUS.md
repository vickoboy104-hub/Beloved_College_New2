# Current Implementation Status

This document summarizes completed New2 releases without replacing the detailed legacy-function inventory in `FUNCTIONAL_PARITY_REGISTER.md`.

## Complete and merged into `main`

### Platform foundation

- one Laravel application across public, full-web and mobile surfaces
- host-based presentation routing
- Classic and Dark theme contract
- CI requirements and preservation controls

### Identity and authorization

- email, admission-number, student-ID and employee-number login
- Student, Parent and Staff entry points
- temporary credentials and mandatory first password replacement
- active-account enforcement
- Super Admin, Admin, Principal, Accountant, Teacher, Parent and Student roles
- permission defaults and user overrides
- Principal/Admin authority separation

### Legacy-compatible data model

- academic, learning, assessment, CBT, report, promotion, finance, communication, setting and audit tables
- existing student and staff identity fields
- existing file-path compatibility
- encrypted sensitive settings

### People and academics

- student and parent lifecycle
- staff lifecycle
- reversible archive and restore
- private profile media
- teacher class-subject access
- sessions, terms, classes and subjects
- promotion preview, override, history and invoice generation

### Learning, results and CBT

- lessons and protected learning media
- assignments, configurable submissions and grading
- bulk attendance
- ordinary assessments and result entry
- Student and Parent portal
- report compilation, class position, remarks and publication
- public PIN result checker
- objective and theory CBT lifecycle

### Finance and verified payments

- fee catalogue
- individual and class invoices
- partial payments and overpayments
- manual office payment records
- permanent receipts
- family payment portal
- verified Paystack, Flutterwave and Monnify checkout
- signed idempotent webhooks
- safe PalmPay unavailability until merchant verification is supplied

### Public website, CMS and themes

- complete responsive public website
- Home, About, Admissions, Contact, News and Gallery
- public CMS and protected public media
- testimonials and newsletter consent
- stored and queued contact enquiries
- school identity and contact settings
- semantic Classic and Dark theme tokens
- preview, draft, publish, revision history and rollback
- Admin-controlled user theme selection

### Communication and system administration

- targeted role, class and individual announcements
- portal notification inboxes and unread counters
- scheduled dispatch and expiry
- idempotent delivery history
- Parent absence alerts
- searchable audit-log viewer
- system-health dashboard
- scheduler heartbeat
- queue and failed-job controls
- encrypted SMTP administration
- synchronous mail diagnostics
- operational thresholds and retention settings

## Current release branch

`workflows/identity-hardening`

The branch adds:

- private email-based password recovery
- active-account reset eligibility
- signed surface-aware email verification
- legacy-safe verification enforcement
- Account Security workspace on both portal surfaces
- database-session visibility and revocation
- password-driven revocation of other sessions
- complete session revocation after password reset
- successful-login history
- permanent security-event ledger
- portal and optional email security alerts
- Admin identity readiness counts and policy controls

This release is complete only after its pull request passes Composer validation, migration setup, production frontend build, Laravel Pint and the full Laravel test suite.

## Remaining after identity hardening

### Explicit product-decision modules

These require detailed workflow approval before implementation:

- staged online admissions application
- timetable
- transcript requests
- spreadsheet import with validation preview
- payroll disbursement
- hosting-specific backup dashboard and restore workflow
- privileged-role multi-factor authentication enrollment and recovery

### Production migration and deployment

- production database backup
- uploaded-file inventory and copy rehearsal
- record-count and financial reconciliation
- APP_KEY and encrypted-setting continuity
- production queue, mail and scheduler setup
- DNS and TLS for public, `web.` and `app.` hosts
- deployment rehearsal
- acceptance testing by every role
- rollback rehearsal
- final cutover approval

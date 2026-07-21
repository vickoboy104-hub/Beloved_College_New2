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
- private password recovery for active accounts
- signed email verification with legacy-safe enforcement
- active database-session review and revocation
- successful-login and security-event history
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

`workflows/migration-deployment-readiness`

The branch adds:

- production-blocked read-only migration tooling
- complete database schema and row inventory
- duplicate identity detection
- foreign-key orphan detection
- exact invoice and payment reconciliation in minor units
- source-to-target row and finance comparison
- database-referenced file manifests
- file size, MIME type and SHA-256 verification
- missing, external and unsafe-path classification
- APP_KEY fingerprint continuity checks
- explicit trusted-host enforcement
- database, migration, storage, queue, scheduler, session and mail preflight
- timestamped machine-readable JSON reports
- staging approval criteria
- production cutover runbook
- production rollback runbook

This release is complete only after its pull request passes Composer validation, migration setup, production frontend build, Laravel Pint and the full Laravel test suite.

## Remaining after migration/deployment readiness

### Staging rehearsal and production cutover

These require actual protected infrastructure and approved copies of legacy assets:

- create a read-only legacy database user
- restore a recent legacy database copy into staging
- copy all legacy uploads into staging
- supply the original APP_KEY through protected environment configuration
- run inventory, reconciliation, file manifest and preflight reports
- correct every critical mismatch
- complete role-based acceptance tests
- rehearse backup and rollback
- configure production DNS, TLS, queue, scheduler, SMTP and payment callbacks
- obtain finance, operations and technical sign-off
- execute the approved production cutover

### Explicit product-decision modules

These require detailed workflow approval before implementation:

- staged online admissions application
- timetable
- transcript requests
- spreadsheet import with validation preview
- payroll disbursement
- hosting-specific backup dashboard and restore workflow
- privileged-role multi-factor authentication enrollment and recovery

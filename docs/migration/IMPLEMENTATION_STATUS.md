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

### Migration and deployment readiness

- production-blocked read-only migration tooling
- complete schema, row, duplicate and orphan inventory
- exact invoice and payment reconciliation in minor units
- source-to-target row and finance comparison
- database-referenced file manifests
- file size, MIME type and SHA-256 verification
- missing, external and unsafe-path classification
- APP_KEY fingerprint continuity checks
- exact trusted-host enforcement
- database, migration, storage, queue, scheduler, session and mail preflight
- timestamped JSON reports
- staging checklist, production cutover and rollback runbooks

### Staging rehearsal orchestration and evidence gating

- one command for the complete staging rehearsal
- isolated legacy private/public file roots
- source-to-target checksum and byte-size comparison by owning record
- technical rehearsal pass, warning and critical status
- role acceptance template for Public Visitor, Student, Parent, Teacher, Accountant, Principal, Admin and Super Admin
- named approvals for technical, migration, operations, finance and rollback owners
- strict cutover-eligibility gating
- versioned evidence packages
- consolidated JSON report
- human-readable Markdown summary
- evidence manifest with SHA-256 and byte size for every package file
- preservation of pending or failed rehearsal evidence

## Current delivery state

The software implementation is complete through staging-rehearsal orchestration. The repository is not blocked by missing application code; it is blocked by protected external inputs and human acceptance evidence.

No claim of a completed staging rehearsal or production migration is made until a real evidence package is generated from approved staging assets.

## External-input gate before staging rehearsal

The following must be supplied outside source control:

- a recent legacy database backup restored to an isolated staging database
- a read-only legacy database user
- complete legacy private uploads copied to an isolated staging root
- complete legacy public uploads copied to an isolated staging root
- the original production `APP_KEY` or its approved SHA-256 fingerprint
- the exact New2 commit SHA selected for rehearsal
- a named migration operator
- the legacy backup/snapshot identifier and timestamp
- staging SMTP, queue, scheduler and payment-sandbox configuration
- named role testers for Public Visitor, Student, Parent, Teacher, Accountant, Principal, Admin and Super Admin
- named technical, migration, operations, finance and rollback approvers

The exact acquisition and custody checklist is documented in `PROTECTED_STAGING_INPUTS.md`.

## Next executable stage

Once the external-input gate is satisfied:

1. restore the approved legacy database copy in staging
2. mount the approved private and public legacy file roots
3. configure the original `APP_KEY` through protected environment settings
4. run `deployment:rehearse` against separate source and target connections
5. correct every critical mismatch
6. complete all role evidence
7. obtain all named owner approvals
8. rehearse backup and rollback
9. configure production DNS, TLS, queue, scheduler, SMTP and payment callbacks
10. execute production cutover only when the evidence package reports `cutover_eligible: true`

## Explicit product-decision modules

These remain outside the approved implementation scope until detailed workflow requirements are supplied:

- staged online admissions application
- timetable
- transcript requests
- spreadsheet import with validation preview
- payroll disbursement
- hosting-specific backup dashboard and restore workflow
- privileged-role multi-factor authentication enrollment and recovery

# Platform Architecture

## 1. Architectural objective

Beloved College New2 is a modular Laravel monolith. The application exposes several presentation surfaces while retaining one authoritative backend.

The architecture must preserve the existing school-management behavior before introducing new features or changing the production schema.

## 2. Application surfaces

### Public surface

Default production host: `belovedcollege.com`

Responsibilities:

- Public school pages
- Published announcements and news
- Admission information and future admission application flow
- Contact enquiries
- Public result checker
- Student and staff portal entry

### Full web portal

Default production host: `web.belovedcollege.com`

Responsibilities:

- Full administration workspace
- Principal workspace with explicit delegated permissions
- Accountant and finance workspace
- Teacher learning workspace
- Student and parent portal
- Wide data tables, reports and complex forms

### Mobile portal

Default production host: `app.belovedcollege.com`

Responsibilities:

- Mobile-first navigation and workflows
- Installable PWA shell
- Student, parent and teacher daily tasks
- Essential administration and finance actions
- Same authorization and domain services as the full web portal

The mobile portal is not a second backend and does not own separate database tables.

## 3. Domain configuration

Hosts are environment-driven and must not be hard-coded:

```env
PUBLIC_HOST=belovedcollege.com
WEB_PORTAL_HOST=web.belovedcollege.com
APP_PORTAL_HOST=app.belovedcollege.com
SESSION_DOMAIN=.belovedcollege.com
```

Local development may use one host with path-based fallbacks. Production enables explicit domain route groups.

## 4. Backend structure

Business logic is grouped by domain rather than by interface page:

```text
app/
├── Actions/
│   ├── Academics/
│   ├── Admissions/
│   ├── Finance/
│   ├── Identity/
│   ├── Learning/
│   ├── Payments/
│   ├── Reports/
│   └── Students/
├── Data/
├── Enums/
├── Events/
├── Http/
│   ├── Controllers/
│   │   ├── PublicSite/
│   │   ├── WebPortal/
│   │   └── AppPortal/
│   ├── Middleware/
│   └── Requests/
├── Jobs/
├── Livewire/
├── Models/
├── Notifications/
├── Policies/
├── Services/
└── Support/
```

Controllers and Livewire pages coordinate requests. Domain actions and services own state changes, calculations and integration behavior.

## 5. Authorization model

The rebuilt system uses two layers:

1. Roles define a user's broad responsibility.
2. Permissions and policies define exact actions and record scope.

Planned roles:

- Super Admin
- Admin
- Principal
- Accountant
- Teacher
- Parent
- Student

The legacy role values remain readable during migration. Super Admin is added safely without changing existing role meanings.

Authorization rules are enforced in policies, middleware and form requests. Hiding a button is never treated as authorization.

Teacher access remains scoped to exact teacher, class and subject assignments.

## 6. Data compatibility strategy

The first implementation target is compatibility with the existing production tables and relationships.

Rules:

- Existing primary keys remain authoritative.
- Existing foreign keys and status values are mapped before changes.
- New columns are additive and nullable until backfilled.
- Renames use staged migrations instead of immediate destructive changes.
- No table is dropped during the parity phase.
- Old and new record totals are reconciled before cutover.
- The legacy Laravel `APP_KEY` is preserved for encrypted settings.

## 7. File-storage strategy

Legacy files may exist in both Laravel storage and public upload directories. The migration process inventories each database path and physical file.

New private files use Laravel filesystem disks and authorized download routes. Public website media uses a public disk or object storage with stable URLs.

No stored path is rewritten until the target file exists and its checksum has been verified.

## 8. Payment strategy

Payment operations use provider adapters behind a shared contract.

Required providers:

- Paystack
- Flutterwave
- Monnify
- PalmPay, only after authoritative verification is implemented and tested

Every successful payment must be verified server-side. Settlement, invoice allocation and receipt generation run inside database transactions with idempotency protections.

## 9. Themes

Only two themes are supported:

- `classic`
- `dark`

Theme tokens are semantic rather than page-specific. Examples:

- page background
- surface
- elevated surface
- primary
- accent
- text
- muted text
- border
- success
- warning
- danger

Admin may edit allowed tokens for each theme. Contrast validation prevents unreadable combinations. Individual component overrides are exceptional and tracked.

## 10. Interface composition

Pages use a flat hierarchy:

```text
Application shell
├── Page heading and actions
├── Optional summary metrics
├── Filters or tabs
└── Direct working content
    ├── Table
    ├── List
    ├── Form
    ├── Timeline
    └── Detail panel or drawer
```

A card must not contain another decorative card unless the inner element is an independent interactive object.

## 11. Queues and notifications

Slow or bulk operations are queued:

- Email delivery
- Notifications
- Bulk invoice generation
- Report generation
- Large imports
- Promotion processing
- Media processing

Database notifications provide the first in-app channel. Email is added where configured. External channels are added behind explicit integrations.

## 12. Audit and observability

Sensitive changes produce audit records including actor, action, subject, before/after context, request metadata and outcome.

The Admin audit viewer is part of the required parity and security work.

Health checks cover:

- Application boot
- Database connectivity
- Queue health
- Storage availability
- Mail configuration
- Payment-provider configuration
- Backup freshness

## 13. Test strategy

Most tests are feature tests covering complete workflows. Unit tests cover pure calculations and provider mapping.

Required parity suites include:

- Authentication identifiers and role audiences
- First-password change
- Parent-child access
- Teacher class-subject authorization
- Assignment submission and grading
- Attendance
- Assessment and report calculations
- CBT timing, attempts and grading
- Invoice generation and balance synchronization
- Online payment verification and grouped allocation
- Result publication and checker PINs
- Promotion and mandatory invoice generation
- File-access authorization
- Theme and permission administration

No legacy module is declared migrated until its parity tests pass.

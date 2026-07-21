# Verified Legacy Model Inventory

Source repository: `vickoboy104-hub/belovedcollege_new`

This inventory records the legacy models whose fields, casts, relationships and embedded calculations have been mapped into New2. It does not represent production row counts; those require a database backup or read-only production connection during the migration rehearsal.

## Identity and people

- `User`
- `Student`
- `StaffProfile`
- `UserPermissionOverride` — New2 additive authorization structure

## Academic structure

- `AcademicSession`
- `Term`
- `SchoolClass`
- `Subject`
- `TeacherSubjectAssignment`

## Learning and attendance

- `Lesson`
- `Assignment`
- `AssignmentSubmission`
- `Assessment`
- `AssessmentResult`
- `AttendanceRecord`

## CBT

- `CbtQuestion`
- `CbtQuestionOption`
- `CbtAttempt`
- `CbtAnswer`

Preserved calculations:

- CBT question point total synchronization
- Objective and theory score separation
- Submitted-versus-graded status
- Assessment result synchronization

## Reports and promotion

- `StudentTermReport`
- `StudentPromotion`

Preserved report fields include attendance totals, character traits, practical skills, remarks, overall results, class position, private portal publication, public checker publication and checker PIN hash.

## Finance

- `FeeItem`
- `FeeInvoice`
- `Payment`

Preserved calculations:

- Unpaid, part-paid and paid invoice states
- Paid-payment balance synchronization
- Grouped payment allocation by due date
- Allocation audit data inside the payment payload

## Website, communication and operations

- `Announcement`
- `ContactMessage`
- `Setting`
- `AuditLog`

Preserved security behavior:

- Sensitive settings encrypted using Laravel encryption
- Existing `encrypted:` storage prefix
- Public setting filtering
- Blank secret form values preserve configured secrets
- Audit logs exclude request input, passwords, medical data, payment secrets and uploaded contents

## Compatibility rules

1. Migrations create a table only when it does not already exist.
2. Existing student columns are only supplemented when missing.
3. Compatibility migration rollback does not delete historical academic or financial data.
4. Existing role, assessment, attendance, payment provider and payment status string values remain unchanged.
5. Production `APP_KEY` must be preserved to decrypt existing sensitive settings.
6. Functional workflow controllers will be ported only after these schema and calculation tests pass.

## Automated verification

The current test suite verifies:

- Presence of all mapped legacy tables
- Presence of complete student, finance and report columns
- Session, term, class, subject and teacher-access relationships
- Invoice balance state transitions
- Grouped payment allocation
- CBT objective/theory synchronization
- Encrypted setting storage and public filtering
- Privacy-safe audit logging

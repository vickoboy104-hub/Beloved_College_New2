# People and Academics Workflows

This release ports the verified student, parent, staff, teacher-access, academic-setup and promotion behavior from the previous system into service-based Laravel workflows.

## Student lifecycle

Student registration is transactional. The operation creates or links the parent account, creates the student user, creates the complete student profile, stores the passport privately, assigns the current academic session and generates matching mandatory invoices.

A failed step rolls back the complete operation.

Temporary credentials are returned once to the authorized administrator and are not stored as plaintext. The student and any newly created parent must replace the temporary password on first login.

Student records support:

- complete identity and contact information
- parent and guardian information
- class and session placement
- boarding and house details
- previous-school information
- medical, physical and doctor information
- mandatory invoice synchronization
- temporary-password reset
- reversible archive and restore

Archival preserves academic, payment, attendance, report, CBT and promotion history.

## Parent linking

Parent accounts are reused by normalized email address. A parent may remain linked to multiple children.

A non-parent account email cannot be silently converted into a parent account. This prevents staff or student identity collisions.

## Staff lifecycle

Staff creation and updates are transactional and preserve employee number, department, designation, qualification, hire date, salary and private passport media.

Role assignment boundaries:

- Super Admin may assign every role.
- Admin may assign Admin, Principal, Accountant and Teacher.
- Principal may assign Accountant and Teacher.
- Only Super Admin may manage a Super Admin account.
- Principal cannot manage an Admin account.

Staff records support one-time credentials, temporary-password reset and reversible archive/restore.

## Teacher access

Teacher permissions remain exact combinations of:

- teacher
- class
- subject

The workflow supports single assignment, bulk assignment, revocation and restoration. Existing combinations are restored rather than duplicated.

Teacher-side queries can be scoped to permitted class-subject pairs. Admin, Super Admin and Principal retain privileged academic visibility.

## Academic setup

The academic setup workflow supports:

- academic sessions
- current session selection
- session closure and final promotion pass mark
- terms and current term selection
- classes and sections
- one class-teacher allocation per teacher
- subjects

Academic mutations are protected by explicit Laravel permissions.

## Promotion processing

Promotion preview preserves the previous calculation behavior:

1. Group assessments by subject.
2. Calculate the student's score percentage in each subject.
3. Average the subject percentages.
4. Compare the average with the closed session's promotion threshold.
5. Recommend promotion or repetition.
6. Infer the next class where the class name contains a numeric level.

The final workflow allows reviewed decision and target-class overrides. Processing:

- requires a closed source session
- requires a current target session
- records permanent promotion history
- moves the student into the target session and class
- generates target-session mandatory invoices

## Administrative workspaces

The flat responsive interface includes:

### Student Office

- directory
- new students
- inactive students
- archived records
- sibling families
- debtors
- class billing
- full registration and profile editing
- finance, report and promotion history

### Staff Office

- directory
- department filtering
- payroll summary
- class allocation
- archived records
- full profile editing

### Teacher Access

- active assignments
- single and bulk grants
- revocation history
- restoration

### Academic Setup

- sessions
- terms
- classes
- subjects
- promotion review

The workspaces use direct tables, forms, tabs, disclosures and record rows. Decorative card nesting from the previous interface is not carried over.

## Private profile media

Profile photographs are stored on Laravel's private local disk. Access is allowed only to:

- the account owner
- a linked parent viewing their child
- an administrator with student-management permission for a student
- an administrator with staff-management permission for staff

Teachers do not automatically receive access to private student photographs.

## Automated verification

Tests cover:

- student and parent creation
- mandatory invoice generation
- absence of plaintext temporary-password storage
- student archive and restore
- staff role ceilings
- staff archive and restore
- teacher assignment, revocation and restoration
- promotion recommendation and processing
- workspace permission boundaries
- HTTP student registration
- HTTP prevention of Principal-to-Admin elevation
- private-media access controls

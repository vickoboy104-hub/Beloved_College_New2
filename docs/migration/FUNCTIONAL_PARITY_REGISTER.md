# Functional Parity Register

This register is the control document for migrating `vickoboy104-hub/belovedcollege_new` into this repository.

## Status definitions

- **Verified legacy** — confirmed in the previous repository.
- **Planned** — required in New2 but not implemented yet.
- **Improve** — preserve the behavior and correct a known limitation.
- **Decision required** — implementation depends on an explicit product decision.
- **Excluded** — removed only after written approval.

No item may move to **Complete** without automated tests and data reconciliation where applicable.

## Public website

| Function | Legacy status | New2 target | Migration status |
|---|---|---|---|
| Public homepage | Verified legacy | Preserve dynamic homepage content | Planned |
| About page | Verified legacy | Preserve and restructure | Planned |
| Admissions information page | Verified legacy | Preserve | Planned |
| Online admissions application | Not present | Add staged application workflow | Decision required |
| Contact form database storage | Verified legacy | Preserve | Planned |
| Contact email delivery | Verified legacy | Preserve with queued mail | Improve |
| Public result checker | Verified legacy | Preserve admission number, term and PIN lookup | Planned |
| Published announcements on homepage | Verified legacy | Preserve | Planned |
| Hero images and videos | Verified legacy | Preserve files and settings | Planned |
| Homepage gallery | Verified legacy | Preserve files and settings | Planned |
| Testimonials | Verified legacy | Preserve editable content | Planned |
| Newsletter form | Browser-only in legacy | Store subscribers and consent state | Improve |
| WhatsApp and telephone links | Verified legacy | Preserve | Planned |

## Authentication and account lifecycle

| Function | Legacy status | New2 target | Migration status |
|---|---|---|---|
| Email login | Verified legacy | Preserve | Planned |
| Admission-number login | Verified legacy | Preserve | Planned |
| Student-ID login | Verified legacy | Preserve | Planned |
| Employee-number login | Verified legacy | Preserve | Planned |
| Separate student and staff entry points | Verified legacy | Preserve as audience-aware login | Planned |
| Parent login through portal entry | Verified legacy | Preserve | Planned |
| Temporary credentials | Verified legacy | Preserve | Planned |
| Forced first password change | Verified legacy | Preserve | Planned |
| Password reset | Verified legacy | Preserve | Planned |
| Account activation and deactivation | Verified legacy | Preserve | Planned |
| Inactive-account rejection | Verified legacy | Preserve | Planned |
| Email verification | Verified legacy | Preserve where email exists | Planned |
| Shared session across `web.` and `app.` | Not present | Add | Planned |
| Two-factor authentication | Not present | Require for privileged roles | Decision required |

## Roles, permissions and security

| Function | Legacy status | New2 target | Migration status |
|---|---|---|---|
| Admin role | Verified legacy | Preserve | Planned |
| Principal role | Verified legacy | Preserve with delegated permissions | Improve |
| Accountant role | Verified legacy | Preserve | Planned |
| Teacher role | Verified legacy | Preserve | Planned |
| Parent role | Verified legacy | Preserve | Planned |
| Student role | Verified legacy | Preserve | Planned |
| Super Admin | Not present | Add as highest authority | Planned |
| Custom permissions | Limited role checks | Add policy-backed permission matrix | Improve |
| Teacher class-subject scope | Verified legacy | Preserve exactly | Planned |
| Teacher assignment grant | Verified legacy | Preserve | Planned |
| Teacher assignment revoke and restore | Verified legacy | Preserve | Planned |
| Bulk teacher assignment | Verified legacy | Preserve | Planned |
| Private profile media | Verified legacy | Preserve authorization rules | Planned |
| Sensitive setting encryption | Verified legacy | Preserve original `APP_KEY` compatibility | Planned |
| Request rate limiting | Verified legacy | Preserve and review limits | Planned |
| Audit record creation | Verified legacy | Preserve | Planned |
| Admin audit viewer | Not routed in legacy | Add searchable viewer | Improve |
| Soft deletion and restore | Not present consistently | Add for critical records | Improve |

## Students and parents

| Function | Legacy status | New2 target | Migration status |
|---|---|---|---|
| Full student registration | Verified legacy | Preserve all fields | Planned |
| Generated admission number | Verified legacy | Preserve format compatibility | Planned |
| Student ID number | Verified legacy | Preserve | Planned |
| Student passport | Verified legacy | Preserve file | Planned |
| Guardian and parent information | Verified legacy | Preserve | Planned |
| Medical and doctor information | Verified legacy | Preserve with restricted permission | Planned |
| Previous school information | Verified legacy | Preserve | Planned |
| Parent account creation or reuse | Verified legacy | Preserve | Planned |
| Parent-child linking | Verified legacy | Preserve | Planned |
| Multiple children per parent | Verified legacy | Preserve | Planned |
| Parent child-switching | Verified legacy | Preserve in both portal surfaces | Planned |
| Student directory | Verified legacy | Preserve | Planned |
| New-student filter | Verified legacy | Preserve with explicit definition | Improve |
| Inactive-student view | Verified legacy | Preserve | Planned |
| Sibling-family view | Verified legacy | Preserve | Planned |
| Debtor-student view | Verified legacy | Preserve | Planned |
| Class-billing view | Verified legacy | Preserve | Planned |
| Student record dossier | Verified legacy | Preserve | Planned |
| Student update | Verified legacy | Preserve | Planned |
| Student archive and restore | Hard delete in legacy | Add | Improve |
| Spreadsheet import | Not present | Add preview and validation | Decision required |

## Staff

| Function | Legacy status | New2 target | Migration status |
|---|---|---|---|
| Staff registration | Verified legacy | Preserve | Planned |
| Generated employee number | Verified legacy | Preserve format compatibility | Planned |
| Department and designation | Verified legacy | Preserve | Planned |
| Qualification and hire date | Verified legacy | Preserve | Planned |
| Salary field | Verified legacy | Preserve securely | Planned |
| Staff directory and department filter | Verified legacy | Preserve | Planned |
| Class-teacher allocation | Verified legacy | Preserve | Planned |
| Payroll summary | Verified legacy | Preserve summary | Planned |
| Payroll disbursement | Not present | Add only after requirements | Decision required |
| Staff archive and restore | Hard delete in legacy | Add | Improve |

## Academic structure and promotion

| Function | Legacy status | New2 target | Migration status |
|---|---|---|---|
| Academic sessions | Verified legacy | Preserve | Planned |
| Terms | Verified legacy | Preserve | Planned |
| Current session and term | Verified legacy | Preserve | Planned |
| Session closure | Verified legacy | Preserve | Planned |
| Classes and sections | Verified legacy | Preserve | Planned |
| Subjects | Verified legacy | Preserve | Planned |
| Class teacher | Verified legacy | Preserve | Planned |
| Promotion pass mark | Verified legacy | Preserve | Planned |
| Promotion preview | Verified legacy | Preserve calculations | Planned |
| Promote or repeat override | Verified legacy | Preserve | Planned |
| Promotion history | Verified legacy | Preserve | Planned |
| Infer next class | Verified legacy | Preserve with safer mapping | Improve |
| Mandatory invoice after promotion | Verified legacy | Preserve | Planned |
| Timetable | Not present | Add | Decision required |

## Teaching and learning

| Function | Legacy status | New2 target | Migration status |
|---|---|---|---|
| Publish lesson | Verified legacy | Preserve | Planned |
| Lesson title, summary and body | Verified legacy | Preserve | Planned |
| Lesson images | Verified legacy | Preserve files | Planned |
| Uploaded lesson video | Verified legacy | Preserve files | Planned |
| External lesson video | Verified legacy | Preserve | Planned |
| Supporting resource link | Verified legacy | Preserve | Planned |
| Create assignment | Verified legacy | Preserve | Planned |
| Assignment images | Verified legacy | Preserve files | Planned |
| Assignment deadline | Verified legacy | Preserve server enforcement | Planned |
| Text assignment submission | Verified legacy | Preserve | Planned |
| File assignment submission | Not present | Add configurable file types | Improve |
| Update submission | Verified legacy | Preserve deadline rules | Planned |
| Grade submission | Verified legacy | Preserve | Planned |
| Teacher feedback | Verified legacy | Preserve | Planned |
| Latest content view | Verified legacy | Preserve | Planned |

## Attendance

| Function | Legacy status | New2 target | Migration status |
|---|---|---|---|
| Present status | Verified legacy | Preserve | Planned |
| Absent status | Verified legacy | Preserve | Planned |
| Late status | Verified legacy | Preserve | Planned |
| Excused status | Verified legacy | Preserve | Planned |
| Attendance note | Verified legacy | Preserve | Planned |
| Student and parent attendance history | Verified legacy | Preserve | Planned |
| Attendance summary | Verified legacy | Preserve | Planned |
| Bulk class attendance | Not confirmed | Add after workflow review | Decision required |
| Absence notification | Not present | Add notification event | Improve |

## Assessments, reports and result checker

| Function | Legacy status | New2 target | Migration status |
|---|---|---|---|
| Quiz assessment | Verified legacy | Preserve | Planned |
| Test assessment | Verified legacy | Preserve | Planned |
| Project assessment | Verified legacy | Preserve | Planned |
| Examination assessment | Verified legacy | Preserve | Planned |
| Result entry and update | Verified legacy | Preserve | Planned |
| Maximum-score validation | Verified legacy | Preserve | Planned |
| Subject grouping | Verified legacy | Preserve | Planned |
| Percentage calculation | Verified legacy | Preserve and lock with unit tests | Planned |
| A-F grading | Verified legacy | Preserve initially; make configurable later | Improve |
| Academic remarks | Verified legacy | Preserve | Planned |
| Overall average and grade | Verified legacy | Preserve | Planned |
| Class position with ties | Verified legacy | Preserve | Planned |
| Days open, present and absent | Verified legacy | Preserve | Planned |
| Character traits | Verified legacy | Preserve | Planned |
| Practical skills | Verified legacy | Preserve | Planned |
| Teacher, guidance, principal and house remarks | Verified legacy | Preserve | Planned |
| Portal publication control | Verified legacy | Preserve | Planned |
| Result-checker publication control | Verified legacy | Preserve | Planned |
| Hashed checker PIN | Verified legacy | Preserve | Planned |
| Modern report layout | Verified legacy | Preserve function, redesign output | Planned |
| Classic report layout | Verified legacy | Preserve function | Planned |
| Transcript workflow | Not present | Add after requirements | Decision required |

## CBT

| Function | Legacy status | New2 target | Migration status |
|---|---|---|---|
| CBT global enable or disable | Verified legacy | Preserve | Planned |
| Assessment activation | Verified legacy | Preserve | Planned |
| Objective questions | Verified legacy | Preserve | Planned |
| Theory questions | Verified legacy | Preserve | Planned |
| Question images | Verified legacy | Preserve files | Planned |
| Question uploaded video | Verified legacy | Preserve files | Planned |
| Question external video | Verified legacy | Preserve | Planned |
| Resource link | Verified legacy | Preserve | Planned |
| Correct option and points | Verified legacy | Preserve | Planned |
| Start and end windows | Verified legacy | Preserve | Planned |
| Per-student timer | Verified legacy | Preserve | Planned |
| One attempt per student | Verified legacy | Preserve | Planned |
| Resume in-progress attempt | Verified legacy | Preserve | Planned |
| Submission deadline middleware | Verified legacy | Preserve | Planned |
| Automatic objective grading | Verified legacy | Preserve | Planned |
| Manual theory grading | Verified legacy | Preserve | Planned |
| Question-bank lock after attempt | Verified legacy | Preserve | Planned |
| Result visibility option | Verified legacy | Preserve | Planned |

## Finance and payments

| Function | Legacy status | New2 target | Migration status |
|---|---|---|---|
| Fee items | Verified legacy | Preserve | Planned |
| School, session, term and class scope | Verified legacy | Preserve | Planned |
| Mandatory and optional fees | Verified legacy | Preserve | Planned |
| Individual invoice | Verified legacy | Preserve | Planned |
| Class invoice generation | Verified legacy | Preserve | Planned |
| Duplicate prevention | Verified legacy | Preserve | Planned |
| Manual payment | Verified legacy | Preserve | Planned |
| Partial payment | Verified legacy | Preserve | Planned |
| Invoice balance synchronization | Verified legacy | Preserve | Planned |
| Receipt generation | Verified legacy | Preserve | Planned |
| Debtor reporting | Verified legacy | Preserve | Planned |
| Overpayment tracking | Verified legacy | Preserve | Planned |
| Payment progression | Verified legacy | Preserve | Planned |
| Bank-transfer instructions | Verified legacy | Preserve | Planned |
| Paystack | Verified legacy | Preserve and retest | Planned |
| Flutterwave | Verified legacy | Preserve and retest | Planned |
| Monnify | Verified legacy | Preserve and retest | Planned |
| PalmPay configuration | Verified legacy | Preserve configuration | Planned |
| PalmPay authoritative settlement | Incomplete | Implement or keep disabled | Improve |
| Multi-invoice checkout | Verified legacy | Preserve | Planned |
| Grouped payment allocation | Verified legacy | Preserve with transaction tests | Planned |
| Callback verification | Verified legacy | Preserve | Planned |
| Webhook signature verification | Verified legacy for supported providers | Preserve | Planned |
| Idempotent settlement | Partial | Strengthen | Improve |

## Settings, themes and administration

| Function | Legacy status | New2 target | Migration status |
|---|---|---|---|
| School identity settings | Verified legacy | Preserve | Planned |
| SMTP settings | Verified legacy | Preserve encrypted values | Planned |
| Payment-provider settings | Verified legacy | Preserve encrypted values | Planned |
| Homepage builder | Verified legacy | Preserve content, replace form UX | Planned |
| Media upload settings | Verified legacy | Preserve files and paths | Planned |
| Workspace backgrounds | Verified legacy | Review; do not force decorative backgrounds | Decision required |
| Theme presets | Four legacy presets | Replace with Classic and Dark only | Improve |
| Theme color tokens | Verified legacy | Replace with semantic tokens | Improve |
| User theme choice | Not present | Add only when Admin permits | Planned |
| Targeted announcements | Not present | Add audiences and scope | Improve |
| In-app notification centre | Not present | Add | Planned |
| Backup dashboard | Not present | Add after hosting requirements | Decision required |
| System health dashboard | Not present | Add | Planned |

## Parity acceptance requirements

Each migrated module must satisfy all applicable checks:

1. Legacy routes and behavior mapped.
2. Existing tables and columns mapped.
3. Existing files inventoried.
4. Authorization policy implemented.
5. Form validation implemented.
6. Feature tests pass.
7. Calculation unit tests pass where applicable.
8. Old and new record totals reconcile.
9. Mobile and full-web workflows are usable.
10. Admin approval recorded before legacy replacement.

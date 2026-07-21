# Learning, Results and CBT Workflows

This release restores and expands the teaching, student/parent portal, attendance, assessment, result-reporting and computer-based testing workflows.

## Teaching workspace

Teachers, Principals and authorized administrators can:

- publish lesson notes, summaries, images, videos and supporting links
- create assignments with deadlines and total scores
- choose accepted response types per assignment
- review typed responses and private uploaded files
- grade submissions with score limits and written feedback
- create quizzes, tests, projects and examinations
- enter ordinary assessment results
- take full-class attendance and correct daily records

Every mutation is checked against the exact teacher, class and subject assignment. Admin, Super Admin and Principal retain privileged academic visibility.

## Student and Parent portal

The responsive portal includes:

- lessons and protected learning media
- assignments and submission status
- student-only assignment submission
- ordinary assessment results
- published report cards
- attendance history
- CBT availability and attempt status
- fee invoice balances
- parent child switching for linked families

Parents have read-only academic access. They cannot submit assignments or CBT attempts for a student.

## Assignment submissions

Existing text submissions remain compatible. New assignments may accept any permitted combination of:

- typed text
- images
- PDF files
- documents
- spreadsheets
- audio
- video
- general files

Files are stored on Laravel's private disk and delivered through model-based authorization routes.

## Report engine

Report compilation groups assessment results by subject and preserves the previous grading scale:

- A: 70 and above
- B: 60–69
- C: 50–59
- D: 45–49
- E: 40–44
- F: below 40

Compiled reports include:

- quiz, test, project and examination scores
- score obtained and possible score
- subject percentage, grade and remark
- total score and overall average
- overall grade
- tied class position calculation
- attendance totals
- character traits
- practical skills
- Class Teacher, Guidance, Principal and House Master remarks
- next-term date

Reports can be published privately to the authenticated Student/Parent portal and independently enabled for the public result checker.

Public result PINs are hashed. Generated PINs are displayed once and are not retained in plaintext. Public result responses use no-store cache headers.

## CBT lifecycle

The CBT workflow supports:

- global availability control
- timed assessment windows
- per-attempt duration
- objective and theory questions
- question images, videos and supporting links
- exactly one correct objective option
- automatic objective grading
- manual theory grading
- score synchronization into ordinary assessment results
- submitted versus graded status
- question-bank locking after the first attempt
- one attempt per student
- resume of an in-progress attempt
- bounded two-minute network submission grace after expiry
- result visibility controls

A started attempt remains submit-capable if global CBT access is disabled after the student begins. New attempts remain blocked while global access or the assessment is disabled.

## Private learning media

Protected media routes cover:

- lesson videos and images
- assignment prompt images
- submitted assignment files
- CBT question images and videos

Access is restricted to the student, linked parent, assigned teacher or privileged academic administrator as appropriate. Accounts still requiring their first password replacement cannot access private learning media.

## Interface

The new Classic and Dark interfaces include:

- Teacher Learning Office
- Teacher CBT Workspace
- timed student CBT examination screen
- Student and Parent portal
- report compilation and publication directory
- detailed report review workspace
- print-ready report cards
- public result checker

All workspaces include responsive mobile layouts. Report cards print on A4 landscape while retaining readable tables.

## Automated verification

The release tests:

- lesson and assignment creation
- private file submission and grading
- ordinary assessment grading
- bulk attendance
- report aggregation, grades and class positions
- private and public report publication
- hashed result PIN lookup
- complete objective and theory CBT lifecycle
- global CBT shutdown behavior for started attempts
- question-bank locking
- Teacher, Student, Parent, Report and public-checker route rendering
- private learning-media authorization

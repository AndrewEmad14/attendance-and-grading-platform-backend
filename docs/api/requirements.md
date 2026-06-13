# ITI Attendance & Grading Platform — Requirements Specification

> **Capstone Project | Team of 4–5 | Full Stack (Laravel + Vue 3)**
> All six groups carry equal weight. Every tagged requirement ID (`ACC-`, `ATT-`, `GRD-`, `ENG-`, `BIL-`, `POR-`, `ANN-`, `SEC-`, `ANL-`) is preserved; inferred/implicit requirements are marked **[IMPLICIT]**.

---

## Table of Contents

- [Group 1 — Roles, Access Control & Account Management](#group-1--roles-access-control--account-management)
- [Group 2 — Cohort Lifecycle, Engagements & Scheduling](#group-2--cohort-lifecycle-engagements--scheduling)
- [Group 3 — Attendance, Excuses & the Attendance Ledger](#group-3--attendance-excuses--the-attendance-ledger)
- [Group 4 — Grading, Grade Architecture & Student Tags](#group-4--grading-grade-architecture--student-tags)
- [Group 5 — Student Portal, Announcements & Submissions](#group-5--student-portal-announcements--submissions)
- [Group 6 — Billing, Analytics, Dashboards & Technical Requirements](#group-6--billing-analytics-dashboards--technical-requirements)


---

## Group 1 — Roles, Access Control & Account Management

This group covers who exists in the system, what they are allowed to see and do, and how accounts are provisioned, scoped, and expired.

### 1.1 Roles

| ID | Requirement |
|----|-------------|
| R-001 | The system shall define exactly four roles: **Branch Manager**, **Track Admin**, **Instructor**, and **Student**. |
| R-002 | A role shall determine access scope only; it shall be decoupled from teaching engagements and compensation type. |
| R-003 | A person with an administrative role (e.g. Track Admin) shall also be permitted to hold teaching engagements in other tracks without changing their access role. |

### 1.2 Access / Visibility Rules

| ID | Requirement |
|----|-------------|
| ACC-1 | The Branch Manager shall see branch-wide aggregated analytics with drill-down into any track and cohort. |
| ACC-2 | A Track Admin shall see the full roster and all grades for their own track's active cohort at any time. |
| ACC-3 | An Instructor shall see only the students in the lab group(s) they are personally assigned to; they shall not see students in other groups. |
| ACC-4 | A Student shall see only their own grades, attendance, and submissions; no peer data shall be visible to students. |
| ACC-5 | Student tags and notes shall be visible to every person who grades that student, whether internal or external. |
| R-004 **[IMPLICIT]** | Authorization shall be enforced server-side on every API endpoint; client-side role checks alone shall never be the sole enforcement mechanism. |

### 1.3 Account Provisioning & Lifecycle

| ID | Requirement |
|----|-------------|
| SEC-1 | There shall be no public self-registration; all accounts are provisioned top-down: Branch Manager → Track Admins → Instructors → Students. |
| SEC-2 | Every account shall carry an expiry date; expired accounts shall be unable to log in. |
| R-005 **[IMPLICIT]** | An external instructor's account shall expire at the end of their engagement contract. |
| R-006 **[IMPLICIT]** | A student's account shall expire at the end of their cohort. |
| R-007 **[IMPLICIT]** | The Branch Manager account shall be the root account and shall be provisioned at system setup. |
| R-008 **[IMPLICIT]** | The system shall support token-based authentication (e.g. Laravel Sanctum or Passport). |
| R-009 **[IMPLICIT]** | On login, the system shall return an auth token scoped to the authenticated user's role and account expiry. |

### 1.4 Compensation Types

| ID | Requirement |
|----|-------------|
| R-010 | The system shall define two compensation types: **External Instructor** (pure hourly) and **Track Admin / Internal** (fixed salary + hourly rate for delivered session hours). |
| R-011 **[IMPLICIT]** | Each user record shall store their compensation type and, where applicable, their hourly rate. |
| R-012 **[IMPLICIT]** | A Track Admin acting as instructor in another track shall have their delivered hours tracked separately for the hourly component of their pay. |

---

## Group 2 — Cohort Lifecycle, Engagements & Scheduling

This group covers how cohorts are created and managed, how teaching engagements are booked, and the scheduling rules that govern them.

### 2.1 Cohort Lifecycle

| ID | Requirement |
|----|-------------|
| LC-1 | A track shall have at most one active cohort at any time. |
| LC-2 | Only the Branch Manager shall create cohorts and assign Track Admins. |
| R-013 **[IMPLICIT]** | A cohort shall progress through the stages: **Open → Configure → Deliver → Participate → Rolled Up**. |
| R-014 **[IMPLICIT]** | A cohort shall be associated with exactly one track. |
| R-015 **[IMPLICIT]** | A cohort shall have a defined start date and end date. |
| R-016 **[IMPLICIT]** | A cohort shall support a human-readable name/label (e.g. "Web — Intake 45"). |
| R-017 **[IMPLICIT]** | The Track Admin shall configure courses, grade weights, lab groups, engagements, and instructor assignments during the Configure stage. |

### 2.2 Engagement Types & Rules

| ID | Requirement |
|----|-------------|
| ENG-3 | Each engagement shall record its type, assigned instructor, date range, and scheduled hours per session. |
| ENG-4 | A person may hold multiple engagements across different tracks within their active window. |
| ENG-5 | An instructor's account access shall be limited to their engagement's date range. |
| R-018 **[IMPLICIT]** | The system shall support three engagement types: **Lecture**, **Lab**, and **Business Session**. |
| R-019 **[IMPLICIT]** | Lecture engagements shall require attendance and carry no deliverable. |
| R-020 **[IMPLICIT]** | Lab engagements shall require attendance and carry a deliverable worth 10 points; the deliverable is mandatory for scoring but optional to submit. |
| R-021 **[IMPLICIT]** | Business Session engagements shall track attendance per track even when students from multiple tracks attend the same session. |
| R-022 **[IMPLICIT]** | The Track Admin shall be the only role permitted to create and modify engagements. |
| R-023 **[IMPLICIT]** | An engagement shall be linked to the cohort it belongs to, except Business Sessions which may span multiple tracks. |

### 2.3 Lab Groups

| ID | Requirement |
|----|-------------|
| R-024 **[IMPLICIT]** | A cohort of ~45 students shall be split into 2–3 lab groups of approximately 15 students each. |
| R-025 **[IMPLICIT]** | The Track Admin shall create lab groups and assign students to them. |
| R-026 **[IMPLICIT]** | Each lab group shall have one assigned lab instructor per lab engagement. |
| GRD-4 | Each lab instructor shall grade only the students in their assigned group. |

### 2.4 Lab Deliverable Penalty

| ID | Requirement |
|----|-------------|
| ENG-1 | A lab deliverable shall be worth 10 points. |
| ENG-2 | Each full day late shall deduct 25 % of the deliverable's total (i.e. 2.5 points per day); after 4 full days late the deliverable score is 0. |
| R-027 **[IMPLICIT]** | The system shall calculate the late penalty automatically from the submission timestamp versus the due date/time. |
| R-028 **[IMPLICIT]** | A deliverable submitted on time shall receive no penalty regardless of its raw score. |

---

## Group 3 — Attendance, Excuses & the Attendance Ledger

This group covers how session attendance is recorded, how the ledger tracks points, and how excuse requests are processed.

### 3.1 Check-in Mechanism

| ID | Requirement |
|----|-------------|
| ATT-1 | The system shall support QR-code check-in and check-out, recording a timestamp for each. |
| ATT-2 | The scanner interface shall be a single, minimal screen optimised for fast repeated scanning. |
| ATT-3 | Business-session attendance shall be recorded per track even when multiple tracks attend the same session. |
| R-029 **[IMPLICIT]** | Each attendance record shall store: student ID, session ID, arrived-at timestamp, and left-at timestamp. |
| R-030 **[IMPLICIT]** | A student shall be marked absent for a session if no check-in record exists by the time the session closes. |
| R-031 **[IMPLICIT]** | The QR code displayed to a student shall be unique per student (not per session) to prevent sharing. |
| BONUS-1 | NFC check-in via the Web NFC API shall be supported as a bonus; any NFC implementation must fall back to QR on unsupported devices (Chrome for Android only). |

### 3.2 Attendance Ledger

| ID | Requirement |
|----|-------------|
| ATT-4 | Every student's attendance ledger shall start at 250 points. |
| ATT-5 | An unexcused absence shall deduct 25 points; an approved excused absence shall deduct 5 points. |
| ATT-6 | Missing any session type — including business sessions — shall affect the same single ledger. |
| R-032 **[IMPLICIT]** | The ledger shall span the full cohort program; it is not reset per course. |
| R-033 **[IMPLICIT]** | The ledger shall be updated automatically when an absence is recorded or an excuse is approved/rejected. |
| R-034 **[IMPLICIT]** | The ledger balance shall be visible to the student, their instructor(s), and the Track Admin. |

### 3.3 Excuse Workflow

| ID | Requirement |
|----|-------------|
| EXC-1 | A Student shall submit an excuse request with a reason and an optional attachment. |
| EXC-2 | An attachment shall be no larger than 1 MB and limited to PDF or image formats; the system shall validate size and type on upload. |
| EXC-3 | A Track Admin shall approve or reject each excuse request; approval changes the absence deduction from 25 to 5 points, rejection leaves it at 25. |
| R-035 **[IMPLICIT]** | An excuse request shall follow the state machine: `requested → approved | rejected`. |
| R-036 **[IMPLICIT]** | A student shall only be able to submit one excuse per session absence. |
| R-037 **[IMPLICIT]** | The system shall notify (or surface in the Track Admin dashboard) pending excuse requests awaiting review. |
| R-038 **[IMPLICIT]** | The Track Admin shall see the excuse reason and attachment (if any) before deciding. |

---

## Group 4 — Grading, Grade Architecture & Student Tags

This group covers how courses are scored, how raw grades are normalised, how the grand total is assembled, and how student tags and notes work.

### 4.1 Grade Architecture & Grand Total

| ID | Requirement |
|----|-------------|
| R-039 **[IMPLICIT]** | Grading shall be two-tier: the attendance ledger is a standalone line item; each course is scored out of 100. |
| R-040 **[IMPLICIT]** | The grand total formula shall be: **Grand Total = Attendance Ledger (out of 250) + Sum of all Course Scores (each out of 100)**. |
| R-041 **[IMPLICIT]** | The attendance ledger shall NOT be folded into any course score; it is added as-is. |

### 4.2 Course Configuration

| ID | Requirement |
|----|-------------|
| GRD-1 | A course shall be scored out of 100, split by weight across its graded components (lab deliverables and final exam/project). |
| GRD-2 | Component weights shall be set once per cohort by the Track Admin. |
| R-042 **[IMPLICIT]** | The Track Admin shall define one or more graded components per course (e.g. assignments, final exam/project). |
| R-043 **[IMPLICIT]** | The sum of all component weights within a course shall equal 100. |

### 4.3 Normalisation

| ID | Requirement |
|----|-------------|
| GRD-3 | The system shall normalise each component's raw score onto its configured weight using: `componentScore = (rawScore ÷ rawMax) × componentWeight`. |
| R-044 **[IMPLICIT]** | The Track Admin shall configure `rawMax` (the raw maximum) for each graded component. |
| R-045 **[IMPLICIT]** | Normalised component scores shall be summed to produce the course score (out of 100). |

### 4.4 Grading Permissions & Overrides

| ID | Requirement |
|----|-------------|
| GRD-5 | The Track Admin shall enter the final exam/project grade for each course. |
| GRD-6 | A Track Admin shall be able to override an instructor's evaluation; an override shall require a mandatory explanatory note, and the original value shall be retained for audit. |
| R-046 **[IMPLICIT]** | Grade override history (original value, new value, note, timestamp, actor) shall be stored and queryable. |
| R-047 **[IMPLICIT]** | An instructor shall only be permitted to enter or edit grades for students in their assigned lab group(s). |

### 4.5 Student Tags & Notes

| ID | Requirement |
|----|-------------|
| GRD-7 | The system shall support predefined tags (e.g. "uses AI", "Cheating", "loves extra work") and free-text notes on a student. |
| GRD-8 | Tags and notes shall be accumulative across courses and visible to every person who grades that student, whether internal or external. |
| R-048 **[IMPLICIT]** | A predefined tag list shall be configurable (add/edit tags) by the Track Admin or Branch Manager. |
| R-049 **[IMPLICIT]** | Any instructor or Track Admin shall be able to add tags and notes to a student. |
| R-050 **[IMPLICIT]** | Tags and notes shall display who added them and when. |

---

## Group 5 — Student Portal, Announcements & Submissions

This group covers everything a student interacts with directly: their personal dashboard, how they submit work, how they read announcements, and how they request excuses.

### 5.1 Student Portal

| ID | Requirement |
|----|-------------|
| POR-1 | A Student shall view their attendance ledger and their per-course score breakdown by component. |
| POR-2 | A Student shall view their own attendance record session by session. |
| POR-3 | A Student shall view their own progress over time; no peer comparison shall be shown. |
| POR-4 | A Student shall submit assignments either as a URL (e.g. a repository or drive link) or as a direct file upload; both methods shall be supported. |
| POR-5 | A Student shall submit and track excuse requests (see Group 3). |
| R-051 **[IMPLICIT]** | The student portal shall display the student's grand total (attendance ledger + all course scores). |
| R-052 **[IMPLICIT]** | The student portal shall display the at-risk flag if the student is flagged (see Group 6). |
| R-053 **[IMPLICIT]** | Assignment submissions shall record a submission timestamp to enable late-penalty calculation. |
| R-054 **[IMPLICIT]** | A student shall be able to resubmit an assignment before the deadline; only the latest submission counts. |

### 5.2 Announcements

| ID | Requirement |
|----|-------------|
| ANN-1 | A Track Admin shall post announcements to their cohort at any time. |
| ANN-2 | An Instructor shall post announcements only during their active engagement window. |
| ANN-3 | Announcements shall support article-style rich content and appear in the student feed. |
| R-055 **[IMPLICIT]** | Announcements shall be scoped to the cohort they belong to; students outside the cohort shall not see them. |
| R-056 **[IMPLICIT]** | Announcements shall display the author name and publish timestamp. |
| R-057 **[IMPLICIT]** | Announcements shall be ordered reverse-chronologically in the student feed. |

### 5.3 File Upload Security

| ID | Requirement |
|----|-------------|
| SEC-4 | File uploads (excuse attachments, assignment files) shall be validated for file size and MIME type, and stored safely. |
| R-058 **[IMPLICIT]** | The system shall reject uploads that exceed defined size limits or have disallowed MIME types. |
| R-059 **[IMPLICIT]** | Uploaded files shall not be accessible via a guessable public URL; access shall require authentication. |
| BONUS-2 | MinIO (S3-compatible) storage for uploaded files is a bonus deliverable. |

---

## Group 6 — Billing, Analytics, Dashboards & Technical Requirements

This group covers automated billing forwarded to central accounting, per-role dashboards, at-risk detection, and the full technical stack and deliverable requirements.

### 6.1 Billing & Central Accounting

| ID | Requirement |
|----|-------------|
| BIL-1 | The system shall auto-calculate billable hours per person from their scheduled and delivered sessions. |
| BIL-2 | External instructors shall be billed purely on delivered hours; internal Track Admins shall be billed for delivered hours on top of their fixed salary. |
| BIL-3 | The system shall produce a consolidated billing rollup forwarded to central accounting. |
| BIL-4 | The Branch Manager shall see the billing rollup, including the split between internal and external hours, within their dashboard. |
| R-060 **[IMPLICIT]** | Delivered hours shall be derived from the session schedule automatically; billing figures shall not be entered by hand. |
| R-061 **[IMPLICIT]** | The billing rollup shall be exportable or forwardable in a machine-readable format. |
| R-062 **[IMPLICIT]** | The system shall store a per-person delivered-hours log for audit purposes. |

### 6.2 At-Risk Detection

| ID | Requirement |
|----|-------------|
| ANL-1 | The system shall flag a student as at-risk when: their attendance ledger falls below 150 points, OR any single course grade falls below 60. |
| R-063 **[IMPLICIT]** | At-risk flags shall be recomputed automatically whenever attendance or grades are updated. |
| R-064 **[IMPLICIT]** | At-risk students shall be surfaced prominently in the Track Admin and Branch Manager dashboards. |
| BONUS-3 | Advanced early-warning signals (e.g. attendance trend slope, sudden drop between courses) may be added as bonus analytics. |

### 6.3 Role Dashboards

| ID | Requirement |
|----|-------------|
| R-065 | The **Branch Manager** dashboard shall show: cross-track comparison (attendance %, average grade, pass/dropout rate); cohort attendance trend over time; billing/cost view (total hours, internal vs. external split, cost-per-student); drill-down from track to cohort. |
| R-066 | The **Track Admin** dashboard shall show: cohort grade distribution; lab-group grader-consistency check; attendance per session and per student; deliverable submission status; tag-flagged and at-risk students. |
| R-067 | The **Instructor** dashboard shall show: their group's grade distribution; submission tracker (late/missing); their own delivered hours. |
| R-068 | The **Student** dashboard shall show: own grade breakdown by component; own attendance record; own progress over time. |
| BONUS-4 | Grader-consistency analysis shall surface whether one lab instructor grades systematically harder or more leniently than peers (e.g. group mean deviation before normalisation). |

### 6.4 Technical Stack

| ID | Requirement |
|----|-------------|
| R-069 | The backend shall be a **Laravel REST API** with database migrations, seeders, and request validation. |
| R-070 | The frontend shall be a **Vue 3 single-page application** that consumes the Laravel API. |
| R-071 | Authentication shall be token-based with server-side, role-scoped authorisation enforced on every endpoint. |
| SEC-3 | The API shall enforce role- and scope-based authorisation on every endpoint, matching the visibility rules in Group 1; authorisation shall be enforced server-side, never only in the Vue client. |
| R-072 **[IMPLICIT]** | The backend and frontend shall be developed and deployable independently but integrated continuously. |
| R-073 **[IMPLICIT]** | The system shall serve a single branch only (no multi-branch support). |
| R-074 **[IMPLICIT]** | The system shall not process payments; it produces billing data and forwards it to central accounting only. |

### 6.5 Deliverables

| ID | Requirement |
|----|-------------|
| R-075 | The team shall deliver a **working full-stack application** implementing all mandatory requirements. |
| R-076 | The team shall deliver **database migrations and seed data** sufficient to demonstrate every role and flow. |
| R-077 | The team shall deliver **API documentation** (Postman collection or OpenAPI spec). |
| R-078 | The team shall deliver a **README** covering setup steps, seeded test accounts for each role, and any assumptions made. |
| R-079 | The team shall deliver a **live demo** walking through each role's experience end to end. |
| BONUS-5 | Requirement IDs shall be used in commit messages and branch names for traceability. |

---

## Summary Table

| Group | Theme | Mandatory Requirements | Bonus Items |
|-------|-------|----------------------|-------------|
| 1 | Roles, Access & Accounts | 18 | 0 |
| 2 | Cohorts, Engagements & Scheduling | 22 | 0 |
| 3 | Attendance, Excuses & Ledger | 17 | 1 |
| 4 | Grading, Normalisation & Tags | 20 | 0 |
| 5 | Student Portal, Announcements & Uploads | 17 | 1 |
| 6 | Billing, Analytics, Dashboards & Tech | 21 | 3 |
| **Total** | | **115** | **5** |

> IDs prefixed `R-` are derived/implicit requirements not explicitly numbered in the original specification. All original specification IDs (`ACC-`, `ATT-`, `GRD-`, `ENG-`, `BIL-`, `POR-`, `ANN-`, `SEC-`, `ANL-`, `EXC-`, `LC-`) are preserved verbatim.
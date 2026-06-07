# API Reference — Attendance & Grading Platform

> **Base URL (dev):** `http://127.0.0.1:8000/api` | **Base URL (prod):** `https://api.example.com/api`

## API Reference — Table of Contents

- [Auth](#auth)
  - `POST /auth/login`
  - `POST /auth/logout`
  - `POST /auth/logout-all`
  - `POST /auth/forgot-password`
  - `POST /auth/reset-password`
  - `GET /auth/me`
- [Users](#users)
  - `GET /users`
  - `POST /users`
  - `GET /users/{userId}`
  - `PATCH /users/{userId}`
  - `DELETE /users/{userId}`
- [Tracks](#tracks)
  - `GET /tracks`
  - `POST /tracks`
  - `GET /tracks/{trackId}`
  - `PATCH /tracks/{trackId}`
- [Cohorts](#cohorts)
  - `GET /tracks/{trackId}/cohorts`
  - `POST /tracks/{trackId}/cohorts`
  - `GET /cohorts/{cohortId}`
  - `PATCH /cohorts/{cohortId}`
- [Courses](#courses)
  - `GET /cohorts/{cohortId}/courses`
  - `POST /cohorts/{cohortId}/courses`
  - `GET /courses/{courseId}`
  - `PATCH /courses/{courseId}`
  - `DELETE /courses/{courseId}`
- [Lab Groups](#lab-groups)
  - `GET /cohorts/{cohortId}/lab-groups`
  - `POST /cohorts/{cohortId}/lab-groups`
  - `PATCH /lab-groups/{labGroupId}`
  - `DELETE /lab-groups/{labGroupId}`
  - `GET /lab-groups/{labGroupId}/students`
  - `POST /lab-groups/{labGroupId}/students`
  - `DELETE /lab-groups/{labGroupId}/students/{studentId}`
- [Engagements](#engagements)
  - `GET /engagements`
  - `POST /engagements`
  - `GET /engagements/{engagementId}`
  - `PATCH /engagements/{engagementId}`
  - `DELETE /engagements/{engagementId}`
- [Business Sessions](#business-sessions)
  - `GET /business-sessions`
  - `POST /business-sessions`
  - `GET /business-sessions/{businessSessionId}`
  - `PATCH /business-sessions/{businessSessionId}`
  - `DELETE /business-sessions/{businessSessionId}`
- [Attendance](#attendance)
  - `GET /attendance`
  - `POST /attendance`
  - `GET /attendance/{attendanceId}`
  - `PATCH /attendance/{attendanceId}`
  - `GET /students/{studentId}/attendance-ledger`
- [Excuse Requests](#excuse-requests)
  - `GET /excuse-requests`
  - `POST /excuse-requests`
  - `GET /excuse-requests/{excuseRequestId}`
  - `POST /excuse-requests/{excuseRequestId}/approve`
  - `POST /excuse-requests/{excuseRequestId}/reject`
- [Deliverables](#deliverables)
  - `GET /courses/{courseId}/deliverables`
  - `POST /courses/{courseId}/deliverables`
  - `GET /deliverables/{deliverableId}`
  - `PATCH /deliverables/{deliverableId}`
  - `DELETE /deliverables/{deliverableId}`
- [Submissions](#submissions)
  - `GET /deliverables/{deliverableId}/submissions`
  - `POST /deliverables/{deliverableId}/submissions`
  - `GET /submissions/{submissionId}`
  - `PATCH /submissions/{submissionId}`
  - `POST /submissions/{submissionId}/override`
- [Tags & Notes](#tags--notes)
  - `GET /tags`
  - `POST /tags`
  - `GET /students/{studentId}/tags`
  - `POST /students/{studentId}/tags`
  - `DELETE /students/{studentId}/tags/{tagId}`
  - `PATCH /students/{studentId}/notes`
- [Announcements](#announcements)
  - `GET /cohorts/{cohortId}/announcements`
  - `POST /cohorts/{cohortId}/announcements`
  - `GET /announcements/{announcementId}`
  - `PATCH /announcements/{announcementId}`
  - `DELETE /announcements/{announcementId}`
- [Analytics](#analytics)
  - `GET /analytics/branch`
  - `GET /analytics/cohorts/{cohortId}`
  - `GET /analytics/lab-groups/{labGroupId}`
  - `GET /analytics/students/{studentId}`
  - `GET /analytics/cohorts/{cohortId}/at-risk`
- [Billing](#billing)
  - `GET /billing/rollup`
  - `GET /billing/instructors/{staffProfileId}`

---

## Auth

| Method | Path | Auth | Notes |
|---|---|---|---|
| `POST` | `/auth/login` | ❌ | Returns Bearer token |
| `POST` | `/auth/logout` | ✅ | Revokes current token → `204` |
| `POST` | `/auth/logout-all` | ✅ | Revokes all tokens → `204` |
| `POST` | `/auth/forgot-password` | ❌ | Sends reset link |
| `POST` | `/auth/reset-password` | ❌ | Resets password via email token |
| `GET` | `/auth/me` | ✅ | Returns authenticated user profile |

### `POST /auth/login`
```json
// Request
{ "email": "admin@iti.eg", "password": "secret" }

// Response 200
{
  "token": "1|abc123...",
  "token_type": "Bearer",
  "user": { "id": 1, "name": "Branch Manager", "email": "admin@iti.eg", "role": "branch_manager", "expires_at": null }
}
```

### `POST /auth/forgot-password`
```json
// Request
{ "email": "student@iti.eg" }
// Response 200
{ "message": "Reset link sent." }
```

### `POST /auth/reset-password`
```json
// Request
{ "token": "reset-token-from-email", "email": "student@iti.eg", "password": "newpassword", "password_confirmation": "newpassword" }
// Response 200
{ "message": "Password reset successfully." }
```

### `GET /auth/me`
```json
// Response 200
{ "data": { "id": 5, "name": "Sara Ali", "email": "sara@iti.eg", "role": "student", "expires_at": "2025-03-01T00:00:00Z" } }
```

---

## Users

> No self-registration. All accounts provisioned top-down: `Branch Manager → Track Admins → Instructors & Students`

| Method | Path | Role |
|---|---|---|
| `GET` | `/users` | `branch_manager` |
| `POST` | `/users` | `branch_manager`, `track_admin` (own track) |
| `GET` | `/users/{userId}` | `branch_manager`, `track_admin` (own track), own account |
| `PATCH` | `/users/{userId}` | `branch_manager`, `track_admin` (own track) |
| `DELETE` | `/users/{userId}` | `branch_manager` — soft delete |

**`GET /users` query params:** `page` · `per_page` · `role` (`branch_manager|track_admin|instructor|student`) · `search`

### `POST /users`
```json
// Student
{ "name": "Ahmed Hassan", "email": "ahmed@iti.eg", "role": "student", "password": "...", "expires_at": "2025-03-01T00:00:00Z", "cohort_id": 3 }

// External instructor
{ "name": "Dr. Khaled Samir", "email": "khaled@external.com", "role": "instructor", "password": "...", "expires_at": "2025-01-31T00:00:00Z", "compensation_type": "external", "hourly_rate": 150.00 }

// Internal track admin
{ "name": "Mona Tarek", "email": "mona@iti.eg", "role": "track_admin", "password": "...", "compensation_type": "internal", "hourly_rate": 80.00 }
```

**Field rules:**
- `cohort_id` — required when `role` is `student`
- `compensation_type` — required when `role` is `instructor` or `track_admin`
- `hourly_rate` — required when `compensation_type` is `external`
- `expires_at` — required for instructors and students

Response `201`: User object.

### `PATCH /users/{userId}`
```json
{ "name": "Ahmed Hassan Samir", "expires_at": "2025-04-01T00:00:00Z" }
```
Role changes: Branch Manager only. Response `200`: User object.

### User Object
```json
{ "id": 1, "name": "Ahmed Hassan", "email": "ahmed@iti.eg", "role": "student", "expires_at": "2025-03-01T00:00:00Z", "created_at": "...", "updated_at": "..." }
```

---

## Tracks

| Method | Path | Role |
|---|---|---|
| `GET` | `/tracks` | All roles |
| `POST` | `/tracks` | `branch_manager` |
| `GET` | `/tracks/{trackId}` | All roles |
| `PATCH` | `/tracks/{trackId}` | `branch_manager` |

### `POST /tracks` · `PATCH /tracks/{trackId}`
```json
{ "name": "Mobile Development" }
```

### `GET /tracks` — Response `200`
```json
{
  "data": [{
    "id": 1, "name": "Web Development",
    "active_cohort": { "id": 3, "number": 45, "is_active": true },
    "created_at": "2023-01-01T00:00:00Z"
  }]
}
```

---

## Cohorts

> At most one active cohort per track at any time (LC-1). Only Branch Manager creates cohorts (LC-2).

| Method | Path | Role |
|---|---|---|
| `GET` | `/tracks/{trackId}/cohorts` | All roles |
| `POST` | `/tracks/{trackId}/cohorts` | `branch_manager` |
| `GET` | `/cohorts/{cohortId}` | All roles (scoped) |
| `PATCH` | `/cohorts/{cohortId}` | `branch_manager` |

### `POST /tracks/{trackId}/cohorts`
Returns `422` if track already has an active cohort.
```json
{ "number": 45, "track_admin_ids": [2, 7] }
```

### `PATCH /cohorts/{cohortId}`
```json
{ "track_admin_ids": [2, 7, 9], "is_active": false }
```

### Cohort Object
```json
{ "id": 3, "track_id": 1, "number": 45, "is_active": true, "created_at": "..." }
```

---

## Courses

| Method | Path | Role |
|---|---|---|
| `GET` | `/cohorts/{cohortId}/courses` | All roles (scoped) |
| `POST` | `/cohorts/{cohortId}/courses` | `track_admin` |
| `GET` | `/courses/{courseId}` | All roles (scoped) |
| `PATCH` | `/courses/{courseId}` | `track_admin` |
| `DELETE` | `/courses/{courseId}` | `track_admin` — only if no deliverables/submissions exist |

### `POST /cohorts/{cohortId}/courses` · `PATCH /courses/{courseId}`
```json
{ "name": "JavaScript Fundamentals" }
```

### Course Object
```json
{
  "id": 1, "cohort_id": 3, "name": "JavaScript Fundamentals",
  "deliverables": [
    { "id": 1, "name": "Week 3 Lab", "type": "lab", "course_weight": 40, "max_score": 70 },
    { "id": 2, "name": "Final Exam", "type": "exam", "course_weight": 60, "max_score": 100 }
  ],
  "created_at": "..."
}
```

---

## Lab Groups

| Method | Path | Role |
|---|---|---|
| `GET` | `/cohorts/{cohortId}/lab-groups` | `branch_manager`, `track_admin`, `instructor` (own only) |
| `POST` | `/cohorts/{cohortId}/lab-groups` | `track_admin` |
| `PATCH` | `/lab-groups/{labGroupId}` | `track_admin` |
| `DELETE` | `/lab-groups/{labGroupId}` | `track_admin` |
| `GET` | `/lab-groups/{labGroupId}/students` | `track_admin`, `instructor` (own only) |
| `POST` | `/lab-groups/{labGroupId}/students` | `track_admin` |
| `DELETE` | `/lab-groups/{labGroupId}/students/{studentId}` | `track_admin` |

### `POST /cohorts/{cohortId}/lab-groups` · `PATCH /lab-groups/{labGroupId}`
```json
{ "name": "Group A" }
```

### `POST /lab-groups/{labGroupId}/students`
```json
{ "student_id": 12 }
```
Response `201 Created`.

### LabGroup Object
```json
{ "id": 1, "cohort_id": 3, "name": "Group A", "student_count": 15 }
```

---

## Engagements

> Polymorphic — attaches to a `course`, `lab`, or `business_session` via `engageable_type` + `engageable_id`. Instructor access is limited to the `starts_at` → `ends_at` window (ENG-5).

| Method | Path | Role |
|---|---|---|
| `GET` | `/engagements` | `track_admin` (all in cohort), `instructor` (own only) |
| `POST` | `/engagements` | `track_admin` |
| `GET` | `/engagements/{engagementId}` | `track_admin`, `instructor` (own only) |
| `PATCH` | `/engagements/{engagementId}` | `track_admin` — cannot update after attendance exists |
| `DELETE` | `/engagements/{engagementId}` | `track_admin` — only if no attendance records |

**`GET /engagements` query params:** `cohort_id` · `type` · `instructor_id` · `page` · `per_page`

### `POST /engagements`
```json
// Lecture
{ "instructor_id": 4, "engageable_type": "course", "engageable_id": 1, "type": "lecture", "starts_at": "2024-09-10T09:00:00Z", "ends_at": "2024-11-30T17:00:00Z", "scheduled_hours": 3.0 }

// Lab
{ "instructor_id": 5, "engageable_type": "lab", "engageable_id": 7, "type": "lab", "starts_at": "2024-09-12T10:00:00Z", "ends_at": "2024-11-30T17:00:00Z", "scheduled_hours": 2.0 }
```

### `PATCH /engagements/{engagementId}`
```json
{ "scheduled_hours": 4.0, "ends_at": "2024-12-15T17:00:00Z" }
```

### Engagement Object
```json
{ "id": 3, "instructor_id": 5, "engageable_type": "lab", "engageable_id": 7, "type": "lab", "starts_at": "2024-09-12T10:00:00Z", "ends_at": "2024-11-30T17:00:00Z", "scheduled_hours": 2.0, "created_at": "..." }
```

---

## Business Sessions

> Cross-track events. Attendance recorded per-track even when cohorts share the same session (ATT-3).

| Method | Path | Role |
|---|---|---|
| `GET` | `/business-sessions` | All roles |
| `POST` | `/business-sessions` | `branch_manager`, `track_admin` |
| `GET` | `/business-sessions/{businessSessionId}` | All roles |
| `PATCH` | `/business-sessions/{businessSessionId}` | `branch_manager`, `track_admin` |
| `DELETE` | `/business-sessions/{businessSessionId}` | `branch_manager`, `track_admin` |

### `POST /business-sessions` · `PATCH /business-sessions/{businessSessionId}`
```json
{ "name": "Career Day — Sept 2024", "cohort_ids": [3, 5] }
```

### BusinessSession Object
```json
{
  "id": 1, "name": "Career Day — Sept 2024",
  "cohorts": [{ "id": 3, "number": 45, "track_id": 1 }],
  "created_at": "..."
}
```

---

## Attendance

> Scanner calls `POST /attendance` twice per student per session — first call sets `arrived_at`, second sets `left_at`.
> Attendance ledger starts at 250 pts (ATT-4). Unexcused absence: −25 pts. Approved excuse: −5 pts (ATT-5).

| Method | Path | Role |
|---|---|---|
| `GET` | `/attendance` | `track_admin` (full cohort), `instructor` (own group), `student` (own) |
| `POST` | `/attendance` | `instructor` (during active engagement window) |
| `GET` | `/attendance/{attendanceId}` | `track_admin`, `instructor` (own group), `student` (own) |
| `PATCH` | `/attendance/{attendanceId}` | `track_admin` — manual timestamp correction |
| `GET` | `/students/{studentId}/attendance-ledger` | `track_admin`, `instructor` (own group), `student` (own) |

**`GET /attendance` query params:** `engagement_id` · `student_id` · `page` · `per_page`

### `POST /attendance`
```json
// Request
{ "student_id": 12, "engagement_id": 3 }

// Response 201 — first scan
{ "data": { "id": 87, "student_id": 12, "engagement_id": 3, "arrived_at": "2024-10-15T09:03:22Z", "left_at": null, "excuse": null, "created_at": "..." } }

// Response 201 — second scan
{ "data": { "id": 87, "student_id": 12, "engagement_id": 3, "arrived_at": "2024-10-15T09:03:22Z", "left_at": "2024-10-15T12:01:44Z", "excuse": null } }
```

### `PATCH /attendance/{attendanceId}`
```json
{ "arrived_at": "2024-10-15T09:05:00Z", "left_at": "2024-10-15T12:00:00Z" }
```

### `GET /students/{studentId}/attendance-ledger`
```json
{
  "data": {
    "student_id": 12, "balance": 195,
    "deductions": [
      { "engagement_id": 3, "points_deducted": 25, "reason": "unexcused", "date": "2024-10-08" },
      { "engagement_id": 5, "points_deducted": 5,  "reason": "excused",   "date": "2024-10-22" }
    ]
  }
}
```

### AttendanceRecord Object
```json
{ "id": 87, "student_id": 12, "engagement_id": 3, "arrived_at": "2024-10-15T09:03:22Z", "left_at": "2024-10-15T12:01:44Z", "excuse": null, "created_at": "..." }
```
When an excuse exists, `excuse` embeds the ExcuseRequest object.

---

## Excuse Requests

> State machine: `requested → approved | rejected`. Only Track Admin advances state (EXC-3). Approval changes ledger deduction from −25 to −5 retroactively.

| Method | Path | Role |
|---|---|---|
| `GET` | `/excuse-requests` | `track_admin` (all in cohort), `student` (own) |
| `POST` | `/excuse-requests` | `student` |
| `GET` | `/excuse-requests/{excuseRequestId}` | `track_admin`, `student` (own) |
| `POST` | `/excuse-requests/{excuseRequestId}/approve` | `track_admin` |
| `POST` | `/excuse-requests/{excuseRequestId}/reject` | `track_admin` |

**`GET /excuse-requests` query params:** `status` (`requested|approved|rejected`) · `student_id` · `page` · `per_page`

### `POST /excuse-requests` — `multipart/form-data`
```
attendance_id: 87
reason: I was in hospital for an emergency procedure.
attachment: [optional — PDF / JPG / PNG / WEBP, ≤ 1 MB]
```
**Validation:** attachment ≤ 1 MB, PDF/image only · `attendance_id` must belong to requesting student · cannot re-submit if excuse already exists for this record.

### `POST /excuse-requests/{id}/approve` · `/reject`
Returns `422` if not in `requested` state. Response `200`: Updated ExcuseRequest object.

### ExcuseRequest Object
```json
{ "id": 14, "attendance_id": 87, "reason": "I was in hospital...", "attachment_path": "excuse-attachments/14/hospital-report.pdf", "status": "approved", "created_at": "..." }
```

---

## Deliverables

> All deliverables per course must have `course_weight` values summing to **100** (GRD-1).
> `normalized_score = (raw_score ÷ max_score) × course_weight` (GRD-3)
> Late penalty (labs): `full_days_late × 25% × 10` — score reaches 0 after 4 days (ENG-2)

| Method | Path | Role |
|---|---|---|
| `GET` | `/courses/{courseId}/deliverables` | All roles (scoped) |
| `POST` | `/courses/{courseId}/deliverables` | `track_admin` |
| `GET` | `/deliverables/{deliverableId}` | All roles (scoped) |
| `PATCH` | `/deliverables/{deliverableId}` | `track_admin` |
| `DELETE` | `/deliverables/{deliverableId}` | `track_admin` — only if no submissions exist |

### `POST /courses/{courseId}/deliverables`
```json
{ "name": "Week 3 Lab", "type": "lab", "max_score": 70, "course_weight": 40, "due_date": "2024-10-18T23:59:00Z" }
```
**Validation:** `type` ∈ `lab|exam|project` · total `course_weight` across course ≤ 100 · `max_score` ≥ 1

### `PATCH /deliverables/{deliverableId}`
```json
{ "name": "Week 3 Lab — Updated", "due_date": "2024-10-20T23:59:00Z" }
```

### Deliverable Object
```json
{ "id": 1, "course_id": 1, "name": "Week 3 Lab", "type": "lab", "max_score": 70, "course_weight": 40, "due_date": "2024-10-18T23:59:00Z", "created_at": "..." }
```

---

## Submissions

> The server computes all derived fields — the client never implements grading formulas.

| Method | Path | Role |
|---|---|---|
| `GET` | `/deliverables/{deliverableId}/submissions` | `track_admin` (all), `instructor` (own group), `student` (own) |
| `POST` | `/deliverables/{deliverableId}/submissions` | `student` |
| `GET` | `/submissions/{submissionId}` | `track_admin`, `instructor` (own group), `student` (own) |
| `PATCH` | `/submissions/{submissionId}` | `instructor` (own group — GRD-4) |
| `POST` | `/submissions/{submissionId}/override` | `track_admin` (GRD-6) |

### `POST /deliverables/{deliverableId}/submissions` — `multipart/form-data`
```
// URL submission
submission_type: url
url: https://github.com/student/week3-lab

// File submission
submission_type: file
file: [binary]
```
**Validation:** one submission per student per deliverable · `submission_type` ∈ `url|file`

### `PATCH /submissions/{submissionId}` (Grade)
```json
{ "raw_score": 67 }
```
`raw_score` must not exceed `deliverable.max_score`. Server auto-computes all derived fields.

### `POST /submissions/{submissionId}/override`
```json
{ "override_score": 55, "override_note": "Graded with wrong rubric. Corrected after review." }
```
`override_note` mandatory, ≥ 10 chars. Original `raw_score` retained for audit.

### Submission Object
```json
{
  "id": 22, "deliverable_id": 1, "student_id": 12,
  "submission_type": "url", "submission_path": "https://github.com/...",
  "raw_score": 67, "normalized_score": 38.3,
  "override_score": null, "override_note": null,
  "days_late": 2, "late_penalty": 5.0, "final_score": 33.3,
  "created_at": "..."
}
```
- `normalized_score` = `(raw_score / max_score) × course_weight`
- `late_penalty` = `days_late × 0.25 × 10` (capped at 4 days)
- `final_score` = override (if set) or normalized − late_penalty

---

## Tags & Notes

> Tags/notes accumulate across all courses, visible to every grader of that student (ACC-5, GRD-8).

| Method | Path | Role |
|---|---|---|
| `GET` | `/tags` | All roles |
| `POST` | `/tags` | `track_admin` |
| `GET` | `/students/{studentId}/tags` | `track_admin`, `instructor` (own group) |
| `POST` | `/students/{studentId}/tags` | `track_admin`, `instructor` (own group) |
| `DELETE` | `/students/{studentId}/tags/{tagId}` | `track_admin` |
| `PATCH` | `/students/{studentId}/notes` | `track_admin`, `instructor` (own group) |

### `POST /tags`
```json
{ "tag": "needs mentoring" }
```

### `POST /students/{studentId}/tags`
```json
{ "tag_id": 2 }
```
Response `201 Created`.

### `PATCH /students/{studentId}/notes`
Notes are additive — append, do not replace.
```json
{ "notes": "Week 5: showed significant improvement. Self-corrected after feedback." }
```
Response `200`: Updated StudentProfile object.

### Tag Object
```json
{ "id": 1, "tag": "uses AI", "created_at": "..." }
```

---

## Announcements

> Track Admins post at any time (ANN-1). Instructors post only during active engagement window (ANN-2) — `403` otherwise. Students read only.

| Method | Path | Role |
|---|---|---|
| `GET` | `/cohorts/{cohortId}/announcements` | All roles in cohort |
| `POST` | `/cohorts/{cohortId}/announcements` | `track_admin`, `instructor` (active window) |
| `GET` | `/announcements/{announcementId}` | All roles in cohort |
| `PATCH` | `/announcements/{announcementId}` | Author, `track_admin` |
| `DELETE` | `/announcements/{announcementId}` | `track_admin` |

### `POST /cohorts/{cohortId}/announcements` · `PATCH /announcements/{announcementId}`
```json
{ "title": "Lab 4 deadline extended", "body": "Due to the public holiday, deadline extended to Sunday 23:59..." }
```

### Announcement Object
```json
{ "id": 5, "cohort_id": 3, "author_id": 7, "title": "Lab 4 deadline extended", "body": "...", "created_at": "...", "updated_at": "..." }
```

---

## Analytics

> Role-scoped — each role sees only what they are permitted to see.

| Method | Path | Role | Rule |
|---|---|---|---|
| `GET` | `/analytics/branch` | `branch_manager` | ACC-1 |
| `GET` | `/analytics/cohorts/{cohortId}` | `track_admin` | ACC-2 |
| `GET` | `/analytics/lab-groups/{labGroupId}` | `instructor` (own groups) | ACC-3 |
| `GET` | `/analytics/students/{studentId}` | `student` (own only) | ACC-4 |
| `GET` | `/analytics/cohorts/{cohortId}/at-risk` | `track_admin`, `branch_manager` | ANL-1 |

### `GET /analytics/branch`
```json
{
  "data": {
    "tracks": [{ "track_id": 1, "track_name": "Web Development", "active_cohort_number": 45, "attendance_pct": 87.4, "average_grade": 73.2, "pass_rate": 91.0, "dropout_rate": 3.2 }],
    "attendance_trend": [{ "week_start": "2024-10-07", "attendance_pct": 89.1 }],
    "billing_summary": { "...": "see Billing rollup" }
  }
}
```

### `GET /analytics/cohorts/{cohortId}`
```json
{
  "data": {
    "grade_distribution": { "buckets": [{ "range": "90-100", "count": 4 }, { "range": "80-89", "count": 11 }] },
    "grader_consistency": [
      { "lab_group_id": 1, "lab_group_name": "Group A", "mean_raw_score": 61.2, "deviation_from_cohort_mean": 1.8 },
      { "lab_group_id": 3, "lab_group_name": "Group C", "mean_raw_score": 54.1, "deviation_from_cohort_mean": -5.3 }
    ],
    "attendance_per_session": [{ "engagement_id": 3, "present_count": 42, "absent_count": 3 }],
    "deliverable_submission_status": { "submitted": 38, "missing": 5, "late": 2 },
    "at_risk_count": 3,
    "tag_flagged_count": 7
  }
}
```

### `GET /analytics/lab-groups/{labGroupId}`
```json
{
  "data": {
    "grade_distribution": { "buckets": [{ "range": "80-89", "count": 6 }] },
    "submission_tracker": [
      { "student_id": 12, "student_name": "Ahmed Hassan", "deliverable_id": 1, "status": "submitted" },
      { "student_id": 14, "student_name": "Sara Ali",     "deliverable_id": 1, "status": "late" },
      { "student_id": 19, "student_name": "Omar Farouk",  "deliverable_id": 1, "status": "missing" }
    ],
    "delivered_hours": 18.5
  }
}
```

### `GET /analytics/students/{studentId}`
```json
{
  "data": {
    "attendance_balance": 195,
    "course_scores": [{
      "course_id": 1, "course_name": "JavaScript Fundamentals", "score": 71.6,
      "component_breakdown": [
        { "deliverable_id": 1, "deliverable_name": "Week 3 Lab", "normalized_score": 33.3, "weight": 40 },
        { "deliverable_id": 2, "deliverable_name": "Final Exam",  "normalized_score": 38.3, "weight": 60 }
      ]
    }],
    "grand_total": 266.6,
    "progress_over_time": [{ "week_start": "2024-09-09", "grand_total": 250.0 }]
  }
}
```
`grand_total = attendance_balance + sum(course_scores)`

### `GET /analytics/cohorts/{cohortId}/at-risk`
At-risk when: attendance balance **< 150** OR any single course grade **< 60** (ANL-1).
```json
{
  "data": [{
    "student_id": 19, "student_name": "Omar Farouk", "attendance_balance": 145,
    "failing_courses": [{ "course_id": 1, "course_name": "JavaScript Fundamentals", "current_score": 48.0 }],
    "risk_reasons": ["Attendance balance below 150", "JavaScript Fundamentals score is 48"]
  }]
}
```

---

## Billing

> Derived entirely from the engagement schedule — never entered manually (BIL-1).
> External: `delivered_hours × hourly_rate`. Internal: same formula on top of fixed salary (BIL-2).

| Method | Path | Role |
|---|---|---|
| `GET` | `/billing/rollup` | `branch_manager` |
| `GET` | `/billing/instructors/{staffProfileId}` | `branch_manager`, own account |

**`GET /billing/rollup` query params:** `cohort_id` (optional)

### `GET /billing/rollup`
```json
{
  "data": {
    "total_internal_hours": 120.0, "total_external_hours": 340.0, "cost_per_student": 1850.00,
    "cohorts": [{ "cohort_id": 3, "cohort_number": 45, "track_name": "Web Development", "total_cost": 83250.00 }],
    "staff": [{
      "staff_profile_id": 5, "name": "Dr. Khaled Samir", "compensation_type": "external",
      "hourly_rate": 150.00, "delivered_hours": 36.0, "billable_amount": 5400.00,
      "engagements": [{ "engagement_id": 3, "type": "lab", "delivered_hours": 36.0, "date_range": "2024-09-12 / 2024-11-30" }]
    }]
  }
}
```

### `GET /billing/instructors/{staffProfileId}`
```json
{
  "data": {
    "staff_profile_id": 5, "name": "Dr. Khaled Samir", "compensation_type": "external",
    "hourly_rate": 150.00, "delivered_hours": 36.0, "billable_amount": 5400.00,
    "engagements": [{ "engagement_id": 3, "type": "lab", "delivered_hours": 36.0, "date_range": "2024-09-12 / 2024-11-30" }]
  }
}
```
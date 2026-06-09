# Indexes

Migration: `2024_01_02_000000_add_performance_indexes.php`

---

## Rule of thumb
An index was added only when a query pattern is frequent **and** the table will grow large enough that a sequential scan hurts (cohort roster, submissions, attendance records). FK columns that Laravel adds `constrained()` on are already indexed by PostgreSQL — those are omitted here.

---

## Index list

### `users`
| Index | Columns | Why |
|---|---|---|
| `idx_users_role` | `role` | Auth middleware filters by role on every request |
| `idx_users_expires_at` | `expires_at` | Login check; expired accounts rejected before any query |

### `cohorts`
| Index | Columns | Why |
|---|---|---|
| `idx_cohorts_track_active` | `(track_id, is_active)` | LC-1 check ("one active cohort per track") fires on nearly every page load. Composite covers both filter columns in one scan |

### `cohort_admins`
| Index | Columns | Why |
|---|---|---|
| `idx_cohort_admins_cohort` | `cohort_id` | Track Admin dashboard loads all admins for a cohort |
| `idx_cohort_admins_staff` | `staff_profile_id` | Reverse lookup: which cohorts does this admin manage |

### `student_profiles`
| Index | Columns | Why |
|---|---|---|
| `idx_student_profiles_lab_group` | `lab_group_id` | ACC-3 — instructor scoping filters every student query by lab group |
| `idx_student_profiles_cohort` | `cohort_id` | Track Admin and Branch Manager load full cohort rosters |

### `engagements`
| Index | Columns | Why |
|---|---|---|
| `idx_engagements_instructor` | `instructor_id` | Billing and instructor dashboard aggregate by instructor |
| `idx_engagements_engageable` | `(engageable_type, engageable_id)` | Polymorphic lookup — resolves engagement → lab or business session |
| `idx_engagements_window` | `(starts_at, ends_at)` | ENG-5/SEC-2 — auth middleware checks instructor account window on every request |

### `submissions`
| Index | Columns | Why |
|---|---|---|
| `idx_submissions_deliverable` | `deliverable_id` | Grading list loads all submissions for a deliverable |
| `uq_submissions_student_deliverable` | `(student_id, deliverable_id)` | Unique constraint — prevents duplicate submissions. Also acts as index for the join in `v_instructor_lab_submissions` |

### `attendance_records`
| Index | Columns | Why |
|---|---|---|
| `idx_attendance_student` | `student_id` | Student portal and ledger queries filter by student |
| `idx_attendance_session` | `session_id` | Attendance dashboard loads all records for a session |

### `excuse_requests`
| Index | Columns | Why |
|---|---|---|
| `idx_excuse_student_status` | `(student_id, status)` | Approval queue always filters `WHERE student_id = ? AND status = 'requested'` together |

### `billing_records`
| Index | Columns | Why |
|---|---|---|
| `idx_billing_user` | `user_id` | Billing rollup aggregates delivered hours per person |
| `idx_billing_forwarded` | `forwarded_at` | Accounting job queries `WHERE forwarded_at IS NULL` to find unsent records |
| `uq_billing_record` | `(engagement_id, user_id, billing_period)` | Prevents double-billing. Also covers the common aggregate query |

### `courses_deliverables`
| Index | Columns | Why |
|---|---|---|
| `idx_deliverables_course` | `course_id` | Grade weight setup and grading list load deliverables by course |
| `idx_deliverables_due_date` | `due_date` | Late-penalty calculation in `v_student_scores` compares against due_date |

### `announcements`
| Index | Columns | Why |
|---|---|---|
| `idx_announcements_cohort_date` | `(cohort_id, created_at)` | Student feed: `WHERE cohort_id = ? ORDER BY created_at DESC` |

---

## What was intentionally left out
- `user_id` on `staff_profiles`, `student_profiles` — covered by the FK constraint index Postgres creates automatically
- `graded_by` / `overriden_by` on `submissions` — only queried in the audit trail, which is infrequent
- `course_id` on `labs` — small table, full scan is fine
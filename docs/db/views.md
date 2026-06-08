# Views

Migration: `2026_06_08_231045_add_views.php`
Models: `App\Models\Views\*`

---

## Rule of thumb
A view was created only when the SQL is either (a) too complex to safely inline in multiple places, or (b) contains business-rule thresholds that must stay in one location. Simple aggregates that appear in only one controller stay inline.

---

## Dependency order

```
v_student_scores
    └── v_student_totals
    └── v_at_risk_students

v_instructor_lab_submissions   (independent)
v_submission_grader            (independent)
```

`DROP ... CASCADE` in `down()` handles teardown order automatically.

---

## v_student_scores

**Model:** `StudentScore`

**What it does:** Normalizes each submission's raw score onto its course weight, then applies the lab late-penalty (25% per day, floor 0 at day 4).

**Why a view:** The normalization + penalty formula (GRD-3, ENG-2) would be duplicated across the student portal, Track Admin grade table, and the two aggregate views below. One place to fix if the penalty rule changes.

**Columns:** `student_id`, `course_id`, `deliverable_id`, `type`, `course_weight`, `submitted_at`, `due_date`, `component_score`

**Used by:**
- Student portal — per-component score breakdown (POR-1)
- Track Admin grade table
- `v_student_totals`, `v_at_risk_students`

```php
StudentScore::forStudent($id)->forCourse($courseId)->get();
```

---

## v_student_totals

**Model:** `StudentTotal`

**What it does:** One row per student — `attendance_balance + SUM(component_scores) = grand_total`.

**Why a view:** The grand total is the single most-queried number in the system (student portal, Track Admin, Branch Manager rollup, at-risk check). Building it from `v_student_scores` avoids repeating the GROUP BY everywhere.

**Columns:** `student_id`, `cohort_id`, `attendance_balance`, `course_score`, `grand_total`

**Used by:**
- Student portal grand total (POR-1)
- Track Admin grade distribution dashboard
- Branch Manager cross-track comparison

```php
StudentTotal::forCohort($cohortId)->get();
StudentTotal::forStudent($studentId)->first();
```

---

## v_at_risk_students

**Model:** `AtRiskStudent`

**What it does:** Returns only students who are at-risk — `attendance_balance < 150` OR any course total `< 60` (ANL-1).

**Why a view:** The thresholds (150, 60) are a spec requirement. Putting them in one view means a single migration changes them across all three dashboards that consume this data.

**Columns:** `student_id`, `cohort_id`, `at_risk_attendance` (bool), `at_risk_grade` (bool)

**Used by:**
- Track Admin at-risk panel
- Instructor at-risk panel (scoped to their lab group — add `whereIn('student_id', $groupStudentIds)`)
- Branch Manager dashboard

```php
AtRiskStudent::forCohort($cohortId)->with('student')->get();
AtRiskStudent::forCohort($cohortId)->attendanceRisk()->get();
```

---

## v_instructor_lab_submissions

**Model:** `InstructorLabSubmission`

**What it does:** Given an instructor, returns all lab submissions they are assigned to grade. Resolves the full chain:

```
engagements (instructor_id, engageable_type='lab')
  → labs (lab_group_id)
    → student_profiles (lab_group_id)
      → submissions WHERE deliverable type = 'lab'
```

**Why a view:** The join chain is 4 tables deep and appears in the grading list, the submission tracker widget, and the authorization policy. Inlining it in each location would be error-prone.

**Columns:** `instructor_id`, `engagement_id`, `lab_id`, `lab_group_id`, `student_id`, `submission_id`, `deliverable_id`, `raw_score`, `graded_by`, `graded_at`, `overridden_by`, `submitted_at`

**Used by:**
- Instructor grading list (primary use)
- Submission tracker — late/missing count
- `InstructorLabSubmission::canGrade()` — authorization before saving a grade

```php
// Grading list
InstructorLabSubmission::forInstructor($user->id)->with('student')->get();

// Ungraded only
InstructorLabSubmission::forInstructor($user->id)->ungraded()->get();

// Authorization check in a Policy
abort_unless(InstructorLabSubmission::canGrade($user->id, $submission->id), 403);

// All instructors delivering labs for a course (runtime query, not a view —
// SQL views cannot take parameters)
$ids = InstructorLabSubmission::instructorIdsForCourse($courseId);
$instructors = User::whereIn('id', $ids)->get();
```

---

## v_submission_grader

**Model:** `SubmissionGrader`

**What it does:** The reverse of `v_instructor_lab_submissions` — given a submission, returns the instructor who is authorized to grade it. Resolves:

```
submissions → courses_deliverables (type='lab')
  → student_profiles (lab_group_id)
    → labs (lab_group_id)
      → engagements (instructor_id)
```

**Why a view:** Used in the authorization policy (`SubmissionPolicy@grade`) and the Track Admin override audit trail. The join chain is the same 4-table depth; it belongs in one place.

**Columns:** `submission_id`, `student_id`, `deliverable_id`, `lab_id`, `lab_group_id`, `engagement_id`, `grader_id`

**Used by:**
- `SubmissionPolicy@grade` — is this user allowed to grade this submission?
- Track Admin override audit — who was the original assigned grader?

```php
// In SubmissionPolicy
public function grade(User $user, Submission $submission): bool
{
    return SubmissionGrader::authorizedFor($submission->id, $user->id);
}

// Look up who should grade a submission
$grader = SubmissionGrader::graderFor($submission->id);
```

> **Note:** `v_instructor_lab_submissions` and `v_submission_grader` query the same relationship from opposite directions. Use `canGrade()` / `authorizedFor()` for point-in-time authorization checks; use the full model when you need the list (e.g. rendering the grading UI).
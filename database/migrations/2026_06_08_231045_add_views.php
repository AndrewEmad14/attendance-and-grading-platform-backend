<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------------
        // v_student_scores
        // Normalizes raw score onto course weight and applies the late
        // penalty for lab deliverables (GRD-3, ENG-2).
        // Used by: student portal, Track Admin grade table, v_student_totals,
        //          v_at_risk_students.
        // ----------------------------------------------------------------
        DB::statement('DROP VIEW IF EXISTS v_student_scores CASCADE');

        DB::statement("
            CREATE VIEW v_student_scores AS
            SELECT
                sub.student_id,
                cd.course_id,
                cd.id                              AS deliverable_id,
                cd.type,
                cd.course_weight,
                sub.created_at,
                cd.due_date,

                CASE
                    WHEN cd.type = 'lab'
                         AND sub.created_at > cd.due_date
                         AND EXTRACT(DAY FROM (sub.created_at - cd.due_date)) >= 4
                        THEN 0

                    WHEN cd.type = 'lab' AND sub.created_at > cd.due_date
                        THEN ROUND(
                            (sub.raw_score::numeric / NULLIF(cd.max_score, 0))
                            * cd.course_weight
                            * (1 - 0.25 * EXTRACT(DAY FROM (sub.created_at - cd.due_date)))
                        , 2)

                    ELSE
                        ROUND(
                            (sub.raw_score::numeric / NULLIF(cd.max_score, 0))
                            * cd.course_weight
                        , 2)
                END AS component_score

            FROM submissions          sub
            JOIN courses_deliverables cd ON cd.id = sub.deliverable_id
        ");

        // ----------------------------------------------------------------
        // v_student_totals
        // Grand total per student: attendance_balance + sum of all
        // component_scores. The single number shown everywhere.
        // Used by: student portal, Track Admin grade distribution,
        //          Branch Manager rollup, v_at_risk_students.
        // ----------------------------------------------------------------
        DB::statement('DROP VIEW IF EXISTS v_student_totals CASCADE');

        DB::statement("
            CREATE VIEW v_student_totals AS
            SELECT
                sp.user_id                                                   AS student_id,
                sp.cohort_id,
                sp.attendance_balance,
                COALESCE(SUM(vs.component_score), 0)                         AS course_score,
                sp.attendance_balance + COALESCE(SUM(vs.component_score), 0) AS grand_total
            FROM student_profiles  sp
            LEFT JOIN v_student_scores vs ON vs.student_id = sp.user_id
            GROUP BY sp.user_id, sp.cohort_id, sp.attendance_balance
        ");

        // ----------------------------------------------------------------
        // v_at_risk_students
        // Flags students where ledger < 150 OR any course total < 60 (ANL-1).
        // Thresholds live here so a single migration changes them everywhere.
        // Used by: Track Admin dashboard, Instructor at-risk panel,
        //          Branch Manager dashboard.
        // ----------------------------------------------------------------
        DB::statement('DROP VIEW IF EXISTS v_at_risk_students CASCADE');

        DB::statement("
            CREATE VIEW v_at_risk_students AS
            SELECT
                sp.user_id  AS student_id,
                sp.cohort_id,
                (sp.attendance_balance < 150)        AS at_risk_attendance,
                (failing.student_id IS NOT NULL)     AS at_risk_grade
            FROM student_profiles sp
            LEFT JOIN (
                SELECT student_id
                FROM v_student_scores
                GROUP BY student_id, course_id
                HAVING SUM(component_score) < 60
            ) failing ON failing.student_id = sp.user_id
            WHERE sp.attendance_balance < 150
               OR failing.student_id IS NOT NULL
        ");

        // ----------------------------------------------------------------
        // v_instructor_lab_submissions
        //
        // For a given instructor: all lab submissions they are responsible
        // for grading, resolved through the engagement → lab → lab_group
        // → student chain (GRD-4, ACC-3).
        //
        // Join chain:
        //   engagements (instructor_id, engageable_type='lab', engageable_id)
        //   → labs        (id = engageable_id, lab_group_id)
        //   → student_profiles (lab_group_id)
        //   → submissions (student_id) WHERE deliverable type = 'lab'
        //
        // Used by: grading list on the Instructor dashboard,
        //          v_submission_grader (below).
        // ----------------------------------------------------------------
        DB::statement('DROP VIEW IF EXISTS v_instructor_lab_submissions CASCADE');

        DB::statement("
            CREATE VIEW v_instructor_lab_submissions AS
            SELECT
                e.staff_id,
                e.id            AS engagement_id,
                l.id            AS lab_id,
                l.lab_group_id,
                sp.user_id      AS student_id,
                sub.id          AS submission_id,
                sub.deliverable_id,
                sub.raw_score,
                sub.graded_by,
                sub.overridden_by,
                sub.created_at
            FROM engagements e
            JOIN labs              l   ON l.id           = e.engageable_id
                                      AND e.engageable_type = 'lab'
            JOIN student_profiles  sp  ON sp.lab_group_id = l.lab_group_id
            JOIN submissions       sub ON sub.student_id  = sp.user_id
            JOIN courses_deliverables cd ON cd.id         = sub.deliverable_id
                                        AND cd.type       = 'lab'
        ");

        // ----------------------------------------------------------------
        // v_submission_grader
        //
        // Reverse of the above: for a given submission, who is the
        // instructor assigned to grade it? Resolves through the same
        // chain in reverse (GRD-4).
        //
        // Join chain:
        //   submissions → courses_deliverables (type='lab')
        //   → student_profiles (student_id → lab_group_id)
        //   → labs (lab_group_id)
        //   → engagements (engageable_id = lab.id, engageable_type='lab')
        //
        // Used by: authorization check before saving a grade
        //          ("can this instructor grade this submission?"),
        //          Track Admin override audit trail.
        // ----------------------------------------------------------------
        DB::statement('DROP VIEW IF EXISTS v_submission_grader CASCADE');

        DB::statement("
            CREATE VIEW v_submission_grader AS
            SELECT
                sub.id          AS submission_id,
                sub.student_id,
                sub.deliverable_id,
                l.id            AS lab_id,
                l.lab_group_id,
                e.id            AS engagement_id,
                e.staff_id AS grader_id
            FROM submissions       sub
            JOIN courses_deliverables cd ON cd.id          = sub.deliverable_id
                                        AND cd.type        = 'lab'
            JOIN student_profiles  sp  ON sp.user_id       = sub.student_id
            JOIN labs              l   ON l.lab_group_id   = sp.lab_group_id
            JOIN engagements       e   ON e.engageable_id  = l.id
                                      AND e.engageable_type = 'lab'
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_submission_grader          CASCADE');
        DB::statement('DROP VIEW IF EXISTS v_instructor_lab_submissions CASCADE');
        DB::statement('DROP VIEW IF EXISTS v_at_risk_students           CASCADE');
        DB::statement('DROP VIEW IF EXISTS v_student_totals             CASCADE');
        DB::statement('DROP VIEW IF EXISTS v_student_scores             CASCADE');
    }
};

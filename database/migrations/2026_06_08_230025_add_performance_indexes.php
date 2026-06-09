<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // users — checked on every login and every auth middleware hit
        Schema::table('users', function (Blueprint $table) {
            $table->index('role', 'idx_users_role');
            $table->index('expires_at', 'idx_users_expires_at');
        });

        // cohorts — LC-1 (one active cohort per track) is enforced on every cohort page load
        Schema::table('cohorts', function (Blueprint $table) {
            $table->index(['track_id', 'is_active'], 'idx_cohorts_track_active');
        });

        // cohort_admins — looked up on every Track Admin dashboard load
        Schema::table('cohorts_admins', function (Blueprint $table) {
            $table->index('cohort_id', 'idx_cohorts_admins_cohort');
            $table->index('staff_id', 'idx_cohorts_admins_staff');
        });

        // student_profiles — instructor scoping (ACC-3) filters by lab_group_id constantly
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->index('lab_group_id', 'idx_student_profiles_lab_group');
            $table->index('cohort_id', 'idx_student_profiles_cohort');
        });

        // engagements — account window check (ENG-5/SEC-2) and polymorphic lookup
        Schema::table('engagements', function (Blueprint $table) {
            $table->index('staff_id',                       'idx_engagements_instructor');
            $table->index(['engageable_type', 'engageable_id'],  'idx_engagements_engageable');
            $table->index(['starts_at', 'ends_at'],              'idx_engagements_window');
        });

        // submissions — grading queries always filter by student + deliverable;
        // the unique constraint also prevents duplicate submissions
        Schema::table('submissions', function (Blueprint $table) {
            $table->unique(['student_id', 'deliverable_id'], 'uq_submissions_student_deliverable');
        });

        // attendance_records — every attendance query filters by student or session
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->index('student_id', 'idx_attendance_student');
            $table->index('engagement_id', 'idx_attendance_engagement');
        });

        // excuse_requests — Track Admin approval queue filters by status
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->index(['attendance_id', 'status'], 'idx_excuse_student_status');
        });

        // billing_records — forwarded_at=null means "not yet sent"; queried every billing run
        Schema::table('billing_records', function (Blueprint $table) {
            $table->index('staff_id', 'idx_billing_user');
            $table->index('forwarded_at', 'idx_billing_forwarded');
            $table->unique(['engagement_id', 'staff_id'], 'uq_billing_record');
        });

        // courses_deliverables — due_date needed for late-penalty calculation
        Schema::table('courses_deliverables', function (Blueprint $table) {
            $table->index('course_id', 'idx_deliverables_course');
            $table->index('due_date', 'idx_deliverables_due_date');
        });

        // announcements — student feed is ordered by created_at desc, filtered by cohort
        Schema::table('announcements', function (Blueprint $table) {
            $table->index(['cohort_id', 'created_at'], 'idx_announcements_cohort_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role');
            $table->dropIndex('idx_users_expires_at');
        });

        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropIndex('idx_cohorts_track_active');
        });

        Schema::table('cohort_admins', function (Blueprint $table) {
            $table->dropIndex('idx_cohort_admins_cohort');
            $table->dropIndex('idx_cohort_admins_staff');
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_student_profiles_lab_group');
            $table->dropIndex('idx_student_profiles_cohort');
        });

        Schema::table('engagements', function (Blueprint $table) {
            $table->dropIndex('idx_engagements_instructor');
            $table->dropIndex('idx_engagements_engageable');
            $table->dropIndex('idx_engagements_window');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex('idx_submissions_deliverable');
            $table->dropUnique('uq_submissions_student_deliverable');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_student');
            $table->dropIndex('idx_attendance_session');
        });

        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->dropIndex('idx_excuse_student_status');
        });

        Schema::table('billing_records', function (Blueprint $table) {
            $table->dropIndex('idx_billing_user');
            $table->dropIndex('idx_billing_forwarded');
            $table->dropUnique('uq_billing_record');
        });

        Schema::table('courses_deliverables', function (Blueprint $table) {
            $table->dropIndex('idx_deliverables_course');
            $table->dropIndex('idx_deliverables_due_date');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('idx_announcements_cohort_date');
        });
    }
};

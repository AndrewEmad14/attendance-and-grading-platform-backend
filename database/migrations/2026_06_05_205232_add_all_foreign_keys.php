<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Helper: Add column if it doesn't exist
        $addColumnIfMissing = function ($table, $column, $type = 'unsignedBigInteger') use (&$addColumnIfMissing) {
            if (!Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $table) use ($column, $type) {
                    if ($type === 'unsignedBigInteger') {
                        $table->unsignedBigInteger($column)->nullable();
                    } elseif ($type === 'foreignId') {
                        $table->foreignId($column)->nullable();
                    }
                });
            }
        };

        // 1. Ensure all foreign key columns exist
        $addColumnIfMissing('users', 'track_id');
        $addColumnIfMissing('cohorts', 'track_id');
        $addColumnIfMissing('lab_groups', 'cohort_id');
        $addColumnIfMissing('lab_group_users', 'lab_group_id');
        $addColumnIfMissing('lab_group_users', 'user_id');
        $addColumnIfMissing('engagements', 'cohort_id');
        $addColumnIfMissing('engagements', 'instructor_id');
        $addColumnIfMissing('attendance_records', 'engagement_id');
        $addColumnIfMissing('attendance_records', 'student_id');
        $addColumnIfMissing('excuse_requests', 'attendance_id');
        $addColumnIfMissing('staff_profiles', 'staff_id');
        $addColumnIfMissing('attendance_ledgers', 'student_id');
        $addColumnIfMissing('courses', 'cohort_id');
        $addColumnIfMissing('courses_deliverables', 'course_id');
        $addColumnIfMissing('submissions', 'deliverable_id');
        $addColumnIfMissing('submissions', 'student_id');

        // 2. Add foreign key constraints (now columns exist)
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('track_id')->references('id')->on('tracks')->nullOnDelete();
        });

        Schema::table('cohorts', function (Blueprint $table) {
            $table->foreign('track_id')->references('id')->on('tracks')->cascadeOnDelete();
        });

        Schema::table('lab_groups', function (Blueprint $table) {
            $table->foreign('cohort_id')->references('id')->on('cohorts')->cascadeOnDelete();
        });

        Schema::table('lab_group_users', function (Blueprint $table) {
            $table->foreign('lab_group_id')->references('id')->on('lab_groups')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('engagements', function (Blueprint $table) {
            $table->foreign('cohort_id')->references('id')->on('cohorts')->cascadeOnDelete();
            $table->foreign('instructor_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreign('engagement_id')->references('id')->on('engagements')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->foreign('attendance_id')->references('id')->on('attendance_records')->cascadeOnDelete();
        });

        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->foreign('staff_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('attendance_ledgers', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreign('cohort_id')->references('id')->on('cohorts')->cascadeOnDelete();
        });

        Schema::table('courses_deliverables', function (Blueprint $table) {
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->foreign('deliverable_id')->references('id')->on('courses_deliverables')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Drop foreign keys first (same order as up)
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropForeign(['deliverable_id']);
            $table->dropForeign(['student_id']);
        });
        Schema::table('courses_deliverables', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['cohort_id']);
        });
        Schema::table('attendance_ledgers', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
        });
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->dropForeign(['attendance_id']);
        });
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropForeign(['engagement_id']);
            $table->dropForeign(['student_id']);
        });
        Schema::table('engagements', function (Blueprint $table) {
            $table->dropForeign(['cohort_id']);
            $table->dropForeign(['instructor_id']);
        });
        Schema::table('lab_group_users', function (Blueprint $table) {
            $table->dropForeign(['lab_group_id']);
            $table->dropForeign(['user_id']);
        });
        Schema::table('lab_groups', function (Blueprint $table) {
            $table->dropForeign(['cohort_id']);
        });
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropForeign(['track_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['track_id']);
        });

        // Optionally drop the added columns (reverse order)
        $dropColumnIfExists = function ($table, $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        };

        $dropColumnIfExists('submissions', 'student_id');
        $dropColumnIfExists('submissions', 'deliverable_id');
        $dropColumnIfExists('courses_deliverables', 'course_id');
        $dropColumnIfExists('courses', 'cohort_id');
        $dropColumnIfExists('attendance_ledgers', 'student_id');
        $dropColumnIfExists('staff_profiles', 'staff_id');
        $dropColumnIfExists('excuse_requests', 'attendance_id');
        $dropColumnIfExists('attendance_records', 'student_id');
        $dropColumnIfExists('attendance_records', 'engagement_id');
        $dropColumnIfExists('engagements', 'instructor_id');
        $dropColumnIfExists('engagements', 'cohort_id');
        $dropColumnIfExists('lab_group_users', 'user_id');
        $dropColumnIfExists('lab_group_users', 'lab_group_id');
        $dropColumnIfExists('lab_groups', 'cohort_id');
        $dropColumnIfExists('cohorts', 'track_id');
        $dropColumnIfExists('users', 'track_id');
    }
};

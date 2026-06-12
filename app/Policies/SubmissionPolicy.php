<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\CourseDeliverable;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\Submission;
use App\Models\User;
use App\Models\Views\SubmissionGrader;
use Illuminate\Support\Facades\DB;

class SubmissionPolicy
{
    /**
     * Who may submit to a deliverable (POR-4).
     * Students only, and only for a deliverable in their own cohort.
     */
    public function create(User $user, ?CourseDeliverable $deliverable = null): bool
    {
        if ($user->role !== 'student' || $deliverable === null) {
            return false;
        }

        $student = StudentProfile::where('user_id', $user->id)->first();

        return $student !== null
            && $deliverable->course
            && $deliverable->course->cohort_id === $student->cohort_id;
    }

    /**
     * Who may view a submission (ACC-3 / ACC-4).
     * Owner (student), the assigned grader (instructor), or any track admin.
     */
    public function view(User $user, Submission $submission): bool
    {
        if ($user->role === 'track_admin') {
            return true;
        }

        if ($user->role === 'student') {
            $student = StudentProfile::where('user_id', $user->id)->first();
            return $student !== null && $submission->student_id === $student->id;
        }

        if ($user->role === 'instructor') {
            return SubmissionGrader::authorizedFor($submission->id, $user->id);
        }

        return false;
    }

    /**
     * Who may list submissions for a deliverable (ACC-2 / ACC-3).
     * Track admin: any. Instructor: only if they teach a lab in this
     * deliverable's course. Student: no — they use their own tracker.
     *
     * Coarse gate only: the per-group narrowing (instructor sees only
     * their own group's rows) happens in the index query, not here.
     */
    public function viewAny(User $user, ?CourseDeliverable $deliverable = null): bool
    {
        if ($user->role === 'track_admin' || $user->role === 'branch_manager') {
            return true;
        }

        if ($user->role === 'instructor' && $deliverable !== null) {
            $staffId = StaffProfile::where('user_id', $user->id)->value('id');

            if ($staffId === null) {
                return false;
            }

            return DB::table('engagements')
                ->where('engageable_type', 'lab')
                ->where('staff_id', $staffId)
                ->whereIn('engageable_id', function ($query) use ($deliverable) {
                    $query->select('id')
                        ->from('labs')
                        ->where('course_id', $deliverable->course_id);
                })
                ->exists();
        }

        return false;
    }

    /**
     * Who may read a given student's submission tracker (ACC-4).
     * The student themselves, or a track admin.
     */
    public function viewStudentTracker(User $user, StudentProfile $student): bool
    {
        if ($user->role === 'track_admin') {
            return true;
        }

        if ($user->role === 'student') {
            $ownId = StudentProfile::where('user_id', $user->id)->value('id');
            return $ownId !== null && $ownId === $student->id;
        }

        return false;
    }

    public function grade(User $user, Submission $submission): bool
    {
        if ($user->role === 'track_admin') {
            return true;
        } // admin can grade anything

        if ($user->role === 'instructor') {
            $deliverable = $submission->deliverable;

            if (in_array($deliverable->type, ['exam', 'project'])) {
                return false;
            } // instructor grade only labs

            // get all lab ids for this course that this instructor is assigned to
            $instructorLabIds = DB::table('engagements')
                ->where('engageable_type', 'lab')
                ->where('instructor_id', $user->id)
                ->whereIn('engageable_id', function ($query) use ($deliverable) {
                    $query->select('id')
                        ->from('labs')
                        ->where('course_id', $deliverable->course_id);
                })
                ->pluck('engageable_id');

            // check if submission's student belongs to one of those labs
            return StudentProfile::where('id', $submission->student_id)
                ->whereIn('lab_group_id', function ($query) use ($instructorLabIds) {
                    $query->select('lab_group_id')
                        ->from('labs')
                        ->whereIn('id', $instructorLabIds);
                })
                ->exists();
        }

        return false; // anyone else
    }

    // only admin can override grade
    public function override(User $user, Submission $submission): bool
    {
        return $user->role === 'track_admin';
    }

    public function delete(User $user, Submission $submission): bool {
        return $user->role === Role::TRACK_ADMIN || $user->role ===Role::BRANCH_MANAGER;
    }
}

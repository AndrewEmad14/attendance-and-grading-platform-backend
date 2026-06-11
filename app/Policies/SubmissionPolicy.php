<?php

namespace App\Policies;

use App\Models\StudentProfile;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmissionPolicy
{
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
}

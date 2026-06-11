<?php

namespace App\Services;

use App\Models\CourseDeliverable;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionService
{
    /**
     * Persist a student's submission to a deliverable (POR-4).
     *
     * Two channels, mutually exclusive (already enforced by StoreSubmissionRequest):
     *   - url:  submission_path holds the user-supplied link
     *   - file: the upload is stored and submission_path holds the storage key/URL
     *
     * HTTP-agnostic: receives the validated payload and an optional UploadedFile,
     * never the Request. Wrapped in a transaction so a stored file and its row
     * commit together.
     *
     * @param  array{submission_type:string, url?:string}  $data
     */
    public function submit(
        CourseDeliverable $deliverable,
        StudentProfile $student,
        array $data,
        ?UploadedFile $file = null
    ): Submission {
        return DB::transaction(function () use ($deliverable, $student, $data, $file) {
            $path = $data['submission_type'] === 'url'
                ? $data['url']
                : $this->storeFile($deliverable, $student, $file);

            return Submission::create([
                'deliverable_id' => $deliverable->id,
                'student_id' => $student->id,
                'submission_type' => $data['submission_type'],
                'submission_path' => $path,
            ]);
        });
    }

    /**
     * Store an uploaded file on the configured disk and return its path/URL.
     * Disk is resolved from config (local for MVP, cloudinary/s3 as a swap)
     * so this method never names a concrete driver.
     */
    private function storeFile(
        CourseDeliverable $deliverable,
        StudentProfile $student,
        UploadedFile $file
    ): string {
        return Storage::putFileAs(
            "submissions/{$deliverable->id}",
            $file,
            "{$student->id}_".$file->hashName()
        );
    }

    /**
     * Student-profile ids the instructor is responsible for on this
     * deliverable: students in the lab group(s) of the labs this
     * instructor teaches within the deliverable's course (ACC-3).
     */
    public function studentIdsForInstructor(CourseDeliverable $deliverable, User $user): array
    {
        $staffId = StaffProfile::where('user_id', $user->id)->value('id');

        if ($staffId === null) {
            return [];
        }

        // lab ids this instructor teaches in this course
        $labIds = DB::table('engagements')
            ->where('engageable_type', 'lab')
            ->where('staff_id', $staffId)
            ->whereIn('engageable_id', function ($q) use ($deliverable) {
                $q->select('id')->from('labs')->where('course_id', $deliverable->course_id);
            })
            ->pluck('engageable_id');

        if ($labIds->isEmpty()) {
            return [];
        }

        // lab groups behind those labs → their students
        return StudentProfile::whereIn('lab_group_id', function ($q) use ($labIds) {
                $q->select('lab_group_id')->from('labs')->whereIn('id', $labIds);
            })
            ->pluck('id')
            ->all();
    }
    /**
     * Student-profile ids that *should* have submitted this deliverable,
     * scoped by role:
     *   - track_admin: every student in the deliverable's course lab groups
     *   - instructor:  only their own lab group(s)  (delegates to studentIdsForInstructor)
     *
     * Returns an array of student_profiles.id.
     */
    public function rosterIdsForDeliverable(CourseDeliverable $deliverable, User $user): array
    {
        if ($user->role === 'instructor') {
            return $this->studentIdsForInstructor($deliverable, $user);
        }

        // track_admin (or anything that passed viewAny): full roster for the course
        return StudentProfile::whereIn('lab_group_id', function ($q) use ($deliverable) {
                $q->select('lab_group_id')
                  ->from('labs')
                  ->where('course_id', $deliverable->course_id);
            })
            ->pluck('id')
            ->all();
    }

    public function download(Submission $submission): StreamedResponse
    {
        abort_unless(
            Storage::exists($submission->submission_path),
            404,
            'File not found.'
        );

        return Storage::download(
            $submission->submission_path,
            $submission->file_name ?? basename($submission->submission_path)
        );
    }
}


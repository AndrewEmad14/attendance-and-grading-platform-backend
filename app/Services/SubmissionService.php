<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\CourseDeliverable;
use App\Models\StudentProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
                'deliverable_id'  => $deliverable->id,
                'student_id'      => $student->id,
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
            "{$student->id}_" . $file->hashName()
        );
    }
}

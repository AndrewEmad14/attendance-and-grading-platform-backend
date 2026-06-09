<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GradeSubmissionRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\Submission;
use App\Services\GradingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\OverrideSubmissionRequest;
use App\Policies\SubmissionPolicy;

class SubmissionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private GradingService $gradingService) {}

    // grades a submission — normalizes score on the fly via GradingService
    public function grade(GradeSubmissionRequest $request, Submission $submission)
    {
        $this->authorize('grade', $submission);

        $submission->load('deliverable');

        // manual max check since max is dynamic per deliverable
        if ($request->raw_score > $submission->deliverable->max_score) {
            return response()->json([
                'message' => "raw_score cannot exceed deliverable max_score of {$submission->deliverable->max_score}"
            ], 422);
        }

        // block instructor from re-grading an already overridden submission
        if (!is_null($submission->override_score) && auth()->user()->role !== 'track_admin') {
            return response()->json([
                'message' => 'This submission has been overridden. Only a Track Admin can modify it.'
            ], 403);
        }

        $this->gradingService->applyGrade($submission, $request->raw_score, auth()->id());

        return new SubmissionResource($submission->fresh()->load(['deliverable', 'gradedBy']));
    }


    // overrides an instructor grade — raw_score never touched, audit trail preserved
    public function override(OverrideSubmissionRequest $request, Submission $submission)
    {
        // dd(auth()->user()->role, $submission->id);
        // $this->authorize('override', $submission);
        if (auth()->user()->role !== 'track_admin') {
    return response()->json(['message' => 'Forbidden'], 403);
}
        $submission->load('deliverable');

        // cannot override an ungraded submission
        if (is_null($submission->raw_score)) {
            return response()->json([
                'message' => 'Cannot override an ungraded submission.'
            ], 422);
        }

        // manual max check against deliverable
        if ($request->new_score > $submission->deliverable->max_score) {
            return response()->json([
                'message' => "new_score cannot exceed deliverable max_score of {$submission->deliverable->max_score}"
            ], 422);
        }

        $this->gradingService->applyOverride(
            $submission,
            $request->new_score,
            $request->override_note,
            auth()->id()
        );

        return new SubmissionResource(
            $submission->fresh()->load(['deliverable', 'gradedBy', 'overriddenBy'])
        );
    }
}
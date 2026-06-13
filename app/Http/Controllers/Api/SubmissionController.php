<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GradeSubmissionRequest;
use App\Http\Requests\OverrideSubmissionRequest;
use App\Http\Requests\StoreSubmissionRequest;
use App\Http\Resources\CohortStudentResource;
use App\Http\Resources\SubmissionResource;
use App\Models\CourseDeliverable;
use App\Models\StudentProfile;
use App\Models\Submission;
use App\Services\GradingService;
use App\Services\SubmissionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SubmissionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private GradingService $gradingService,
        private SubmissionService $submissionService,
    ) {}

    // roster of real submissions for a deliverable (ACC-2 / ACC-3)
    public function index(CourseDeliverable $deliverable)
    {
        $this->authorize('viewAny', [Submission::class, $deliverable]);

        $user = auth()->user();

        $query = Submission::query()
            ->where('deliverable_id', $deliverable->id)
            ->with(['deliverable', 'gradedBy.user', 'overriddenBy.user', 'student.user', 'student.labGroup']);

        // ACC-3: instructor sees only their own lab group's students
        if ($user->role === 'instructor') {
            $studentIds = $this->submissionService
                ->studentIdsForInstructor($deliverable, $user);

            $query->whereIn('student_id', $studentIds);
        }
        // track_admin: no narrowing — full roster (ACC-2)

        $submissions = $query
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) request('per_page', 15), 100));

        return SubmissionResource::collection($submissions);
    }

    // a single submission. Authorization (owner student / assigned instructor /
    // track admin) is enforced by SubmissionPolicy::view — a student can only
    // ever fetch their own (ACC-4).
    public function show(Submission $submission)
    {
        $this->authorize('view', $submission);

        return new SubmissionResource($submission->load('deliverable'));
    }

    // removes a submission entirely — the only correction path for a wrong
    // submission, since students cannot resubmit. Track admin only.
    // Deleting a graded row shifts the student's course total + at-risk flag;
    // those recompute downstream through the score views automatically.
    public function destroy(Submission $submission)
    {
        $this->authorize('delete', $submission);

        $this->submissionService->deleteWithFile($submission);

        return response()->noContent(); // 204
    }

    // roster students with NO submission for this deliverable (the gaps)
    public function missing(CourseDeliverable $deliverable)
    {
        $this->authorize('viewAny', [Submission::class, $deliverable]);

        $user = auth()->user();

        // who is the roster for this deliverable, scoped by role?
        $rosterIds = $this->submissionService->rosterIdsForDeliverable($deliverable, $user);

        // who already submitted?
        $submittedIds = Submission::where('deliverable_id', $deliverable->id)
            ->whereIn('student_id', $rosterIds)
            ->pluck('student_id');

        // the gaps: roster minus submitted
        $missing = StudentProfile::query()
            ->whereIn('id', $rosterIds)
            ->whereNotIn('id', $submittedIds)
            ->with('user')
            ->orderBy('id')
            ->paginate(min((int) request('per_page', 15), 100));

        return CohortStudentResource::collection($missing);
    }

    // a student's own submission tracker across deliverables (POR-2 / ACC-4)
    public function studentSubmissions(int $studentId)
    {
        $student = StudentProfile::findOrFail($studentId);

        $this->authorize('viewStudentTracker', [Submission::class, $student]);

        $submissions = Submission::where('student_id', $student->id)
            ->with(['deliverable', 'gradedBy.user', 'overriddenBy.user'])
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) request('per_page', 15), 100));

        return SubmissionResource::collection($submissions);
    }

    // safe file fetch for a submission (grader + owner only)
    public function download(Submission $submission)
    {
        $this->authorize('view', $submission);

        // url submissions have no file to stream — return the link
        if ($submission->submission_type === 'url') {
            return response()->json(['data' => ['url' => $submission->submission_path]]);
        }

        // file submission: submission_path is the storage key / remote URL
        return $this->submissionService->download($submission);
    }

    // student submits to a deliverable — url or file (POR-4)
    public function store(StoreSubmissionRequest $request, CourseDeliverable $deliverable)
    {
        // authorization handled in StoreSubmissionRequest::authorize() via the create policy

        $student = $request->user()->studentProfile;

        $submission = $this->submissionService->submit(
            $deliverable,
            $student,
            $request->safe()->only(['submission_type', 'link']),
            $request->file('file'),
        );

        return (new SubmissionResource($submission->load('deliverable')))
            ->response()
            ->setStatusCode(201);
    }

    // grades a submission — normalizes score on the fly via GradingService
    public function grade(GradeSubmissionRequest $request, Submission $submission)
    {
        $this->authorize('grade', $submission);

        $submission->load('deliverable');

        // manual max check since max is dynamic per deliverable
        if ($request->raw_score > $submission->deliverable->max_score) {
            return response()->json([
                'message' => "raw_score cannot exceed deliverable max_score of {$submission->deliverable->max_score}",
            ], 422);
        }

        // block instructor from re-grading an already overridden submission
        if (! is_null($submission->override_score) && auth()->user()->role !== 'track_admin') {
            return response()->json([
                'message' => 'This submission has been overridden. Only a Track Admin can modify it.',
            ], 403);
        }

        $this->gradingService->applyGrade($submission, $request->raw_score, auth()->user()->staffProfile->id);

        return new SubmissionResource($submission->fresh()->load('deliverable'));
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
                'message' => 'Cannot override an ungraded submission.',
            ], 422);
        }

        // manual max check against deliverable
        if ($request->new_score > $submission->deliverable->max_score) {
            return response()->json([
                'message' => "new_score cannot exceed deliverable max_score of {$submission->deliverable->max_score}",
            ], 422);
        }

        $this->gradingService->applyOverride(
            $submission,
            $request->new_score,
            $request->override_note,
            auth()->user()->staffProfile->id
        );

        return new SubmissionResource(
            $submission->fresh()->load('deliverable')
        );
    }
}

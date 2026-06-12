<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEngagementRequest;
use App\Http\Requests\UpdateEngagementRequest;
use App\Http\Resources\EngagementResource;
use App\Models\Engagement;
use App\Models\Lab;
use App\Services\EngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngagementController extends Controller
{
    protected EngagementService $engagementService;

    public function __construct(EngagementService $engagementService)
    {
        $this->engagementService = $engagementService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Engagement::with([
            'staff.user',
            'engageable' => function ($morphTo) {
                $morphTo->morphWith([
                    Lab::class => ['labGroup'],
                ]);
            },
        ]);

        if ($user->role === 'instructor') {
            $query->where('staff_id', $user->staffProfile->id);
        } elseif ($user->role === 'student') {
            $query->forCohort($user->studentProfile->cohort_id);
        } elseif ($user->role === 'track_admin') {
            $managedCohortIds = $user->staffProfile->managedCohorts()->pluck('cohorts_admins.cohort_id')->toArray();

            if ($request->filled('cohort_id')) {
                $requestedCohortId = (int) $request->get('cohort_id');

                if (! in_array($requestedCohortId, $managedCohortIds)) {
                    abort(403, 'This action is unauthorized.');
                }

                $query->forCohort($requestedCohortId);
            } else {
                $query->forCohort($managedCohortIds);
            }

            if ($request->filled('staff_id')) {
                $query->where('staff_id', $request->get('staff_id'));
            }
        } else {
            if ($request->filled('cohort_id')) {
                $query->forCohort($request->get('cohort_id'));
            }
            if ($request->filled('staff_id')) {
                $query->where('staff_id', $request->get('staff_id'));
            }
        }

        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));

            if ($request->filled('engageable_id')) {
                $query->where('engageable_id', $request->get('engageable_id'));
            }
        }

        if ($request->filled('date_from')) {
            $query->where('starts_at', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('ends_at', '<=', $request->get('date_to'));
        }

        $engagements = $query->paginate($request->get('per_page', 15));

        return EngagementResource::collection($engagements)->response();
    }

    public function store(StoreEngagementRequest $request): JsonResponse
    {
        $engagement = $this->engagementService->createEngagement($request->validated());

        return (new EngagementResource($engagement->load(['engageable', 'engageable.labGroup'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Engagement $engagement): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'instructor' && $engagement->staff_id !== $user->staffProfile->id) {
            abort(403, 'This action is unauthorized.');
        }

        if ($user->role === 'student') {
            $isAuthorized = Engagement::with('staff.user')::where('id', $engagement->id)
                ->forCohort($user->studentProfile->cohort_id)
                ->exists();

            if (! $isAuthorized) {
                abort(403, 'This action is unauthorized.');
            }
        }

        return (new EngagementResource($engagement->load(['engageable', 'engageable.labGroup'])))->response();
    }

    public function update(UpdateEngagementRequest $request, Engagement $engagement): JsonResponse
    {
        $engagement->update($request->validated());

        return (new EngagementResource($engagement->load(['engageable', 'engageable.labGroup'])))->response();
    }

    public function destroy(Request $request, Engagement $engagement): JsonResponse
    {
        if ($request->user()->role !== 'track_admin' && $request->user()->role !== 'branch_manager') {
            abort(403, 'This action is unauthorized.');
        }

        $engagement->delete();

        return response()->json(null, 204);
    }
}

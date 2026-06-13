<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnrollCohortInBusinessSessionRequest;
use App\Http\Requests\StoreBusinessSessionRequest;
use App\Http\Resources\BusinessSessionResource;
use App\Models\BusinessSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role === 'student') {
            abort(403, 'This action is unauthorized.');
        }

        // Build base query with standard cohort data
        $query = BusinessSession::with('cohorts');

        // Conditionally load engagements with deep staff profiles for schedule previewing
        if ($request->boolean('include_engagements')) {
            $query->with(['engagements.staff.user']);
        }

        $businessSessions = $query->paginate($request->get('per_page', 15));

        return BusinessSessionResource::collection($businessSessions)->response();
    }

    public function store(StoreBusinessSessionRequest $request): JsonResponse
    {
        $session = BusinessSession::create($request->validated());

        return (new BusinessSessionResource($session))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, BusinessSession $businessSession): JsonResponse
    {
        if ($request->user()->role === 'student') {
            abort(403, 'This action is unauthorized.');
        }

        // Check for conditional preview parameter on single-lookup detail calls
        $relations = ['cohorts'];
        if ($request->boolean('include_engagements')) {
            $relations[] = 'engagements.staff.user';
        }

        return (new BusinessSessionResource($businessSession->load($relations)))->response();
    }

    public function enrollCohort(EnrollCohortInBusinessSessionRequest $request, BusinessSession $businessSession): JsonResponse
    {
        $businessSession->cohorts()->attach($request->input('cohort_id'));

        return response()->json([
            'message' => 'Cohort successfully registered to the specified business session event context.',
        ], 201);
    }

    public function removeCohort(Request $request, BusinessSession $businessSession, int $cohortId): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'track_admin') {
            $hasCohort = $user->staffProfile->managedCohorts()->where('cohorts.id', $cohortId)->exists();

            if (! $hasCohort) {
                abort(403, 'This action is unauthorized.');
            }
        } elseif ($user->role !== 'branch_manager') {
            abort(403, 'This action is unauthorized.');
        }

        $businessSession->cohorts()->detach($cohortId);

        return response()->json(null, 204);
    }
}

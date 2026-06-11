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

        $businessSessions = BusinessSession::with('cohorts')
            ->paginate($request->get('per_page', 15));

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

        return (new BusinessSessionResource($businessSession->load('cohorts')))->response();
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
        if ($request->user()->role !== 'track_admin' && $request->user()->role !== 'branch_manager') {
            abort(403, 'This action is unauthorized.');
        }

        $businessSession->cohorts()->detach($cohortId);

        return response()->json(null, 204);
    }
}

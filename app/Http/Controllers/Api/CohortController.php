<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCohortRequest;
use App\Http\Requests\UpdateCohortRequest;
use App\Http\Resources\CohortResource;
use App\Models\Cohort;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CohortController extends Controller
{
    public function index(Request $request, ?Track $track = null): JsonResponse
    {
        Gate::authorize('viewAny', Cohort::class);

        $user = $request->user();
        $query = Cohort::query();

        if ($track && $track->exists) {
            $query->where('track_id', $track->id);
        }

        if ($user->role === 'track_admin') {
            $query->whereHas('admins', function ($q) use ($user) {
                $q->where('staff_id', $user->staffProfile->id);
            });
        } elseif ($user->role === 'instructor') {
            $query->whereHas('engagements', function ($q) use ($user) {
                $q->where('staff_id', $user->staffProfile->id);
            });
        } elseif ($user->role === 'student') {
            $query->where('id', $user->studentProfile->cohort_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('include_meta')) {
            $query->withCount('students')
                ->with(['admins.user']);
        }

        $query->with(['track']);

        $cohorts = $query->paginate($request->get('per_page', 15));

        return CohortResource::collection($cohorts)->response();
    }

    public function store(StoreCohortRequest $request, Track $track): JsonResponse
    {
        $validated = $request->validated();
        
        $validated['track_id'] = $track->id;
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $cohort = Cohort::create($validated);

        return (new CohortResource($cohort))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Cohort $cohort): JsonResponse
    {
        Gate::authorize('view', $cohort);

        return (new CohortResource($cohort->load('track')))->response();
    }

    public function update(UpdateCohortRequest $request, Cohort $cohort): JsonResponse
    {
        $cohort->update($request->validated());

        return (new CohortResource($cohort->load('track')))->response();
    }
}

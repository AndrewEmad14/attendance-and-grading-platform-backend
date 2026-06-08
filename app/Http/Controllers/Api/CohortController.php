<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCohortRequest;
use App\Http\Resources\CohortResource;
use App\Models\Cohort;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CohortController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Cohort::class);

        $cohorts = Cohort::with(['track'])
            ->paginate($request->get('per_page', 15));

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
}

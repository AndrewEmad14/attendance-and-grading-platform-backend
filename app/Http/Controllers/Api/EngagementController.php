<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEngagementRequest;
use App\Http\Requests\UpdateEngagementRequest;
use App\Http\Resources\EngagementResource;
use App\Models\Engagement;
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
        $query = Engagement::query();

        if ($user->role === 'instructor') {
            $query->where('staff_id', $user->staff_profile_id);
        } elseif ($user->role === 'student') {
            abort(403, 'This action is unauthorized.');
        } elseif ($request->filled('staff_id')) {
            $query->where('staff_id', $request->get('staff_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        $engagements = $query->paginate($request->get('per_page', 15));

        return EngagementResource::collection($engagements)->response();
    }

    public function store(StoreEngagementRequest $request): JsonResponse
    {
        $engagement = $this->engagementService->createEngagement($request->validated());

        return (new EngagementResource($engagement))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Engagement $engagement): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'instructor' && $engagement->staff_id !== $user->staff_profile_id) {
            abort(403, 'This action is unauthorized.');
        }

        if ($user->role === 'student') {
            abort(403, 'This action is unauthorized.');
        }

        return (new EngagementResource($engagement))->response();
    }

    public function update(UpdateEngagementRequest $request, Engagement $engagement): JsonResponse
    {
        $engagement->update($request->validated());

        return (new EngagementResource($engagement))->response();
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

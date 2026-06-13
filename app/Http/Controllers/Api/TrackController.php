<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrackRequest;
use App\Http\Requests\UpdateTrackRequest;
use App\Http\Resources\TrackResource;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('viewAny', Track::class)) {
            abort(403, 'This action is unauthorized.');
        }

        $query = Track::query();

        // Optional UI metadata expansion checks
        if ($request->boolean('include_cohorts')) {
            $query->with(['cohorts' => function ($q) {
                $q->orderBy('number', 'desc');
            }])->withCount('cohorts');
        }

        $tracks = $query->orderBy('name', 'asc')->get();

        return response()->json(['data' => TrackResource::collection($tracks)]);
    }

    public function store(StoreTrackRequest $request): JsonResponse
    {
        $track = Track::create($request->validated());

        return (new TrackResource($track))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Track $track): JsonResponse
    {
        if (! $request->user()->can('view', $track)) {
            abort(403, 'This action is unauthorized.');
        }

        $relations = [];
        if ($request->boolean('include_cohorts')) {
            $relations[] = 'cohorts';
            $track->loadCount('cohorts');
        }

        return (new TrackResource($track->load($relations)))->response();
    }

    public function update(UpdateTrackRequest $request, Track $track): JsonResponse
    {
        $track->update($request->validated());

        return (new TrackResource($track))->response();
    }

    public function destroy(Request $request, Track $track): JsonResponse
    {
        if (! $request->user()->can('delete', $track)) {
            abort(403, 'This action is unauthorized.');
        }

        // Prevent structural orphaning if active data points depend on this record
        if ($track->cohorts()->exists()) {
            abort(422, 'Cannot remove a track configuration while active structural cohorts are assigned to it.');
        }

        $track->delete();

        return response()->json(null, 204);
    }
}

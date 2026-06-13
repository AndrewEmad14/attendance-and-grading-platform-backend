<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExcuseRequest;
use App\Http\Requests\UpdateExcuseRequest;
use App\Http\Resources\ExcuseRequestResource;
use App\Models\ExcuseRequest;
use App\Services\ExcuseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExcuseRequestController extends Controller
{
    public function __construct(private ExcuseService $excuseService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExcuseRequest::class);
        $requests = $this->excuseService->index(
            $request->user(),
            $request->integer('per_page', 20),
            $request->integer('cohort_id') ?: null,
            $request->string('status')->toString() ?: null,
            $request->string('search')->toString() ?: null,
        );

        return ExcuseRequestResource::collection($requests)->response();
    }

    public function show(ExcuseRequest $excuseRequest): ExcuseRequestResource
    {
        $this->authorize('view', $excuseRequest);

        return new ExcuseRequestResource(
            $excuseRequest->load(['engagement', 'student.user', 'reviewer.user'])
        );
    }

    public function store(StoreExcuseRequest $request): JsonResponse
    {
        $this->authorize('create', ExcuseRequest::class);
        $excuseRequest = $this->excuseService->store(
            user: $request->user(),
            data: $request->validated(),
            attachment: $request->file('attachment'),
        );

        return (new ExcuseRequestResource($excuseRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateExcuseRequest $request, ExcuseRequest $excuseRequest): ExcuseRequestResource
    {
        $this->authorize('update', $excuseRequest);
        $updated = $this->excuseService->update(
            $excuseRequest,
            $request->validated(),
            $request->file('attachment'),
            $request->boolean('remove_attachment')
        );

        return new ExcuseRequestResource($updated);
    }

    public function approve(Request $request, ExcuseRequest $excuseRequest): ExcuseRequestResource
    {
        $this->authorize('review', $excuseRequest);
        $updated = $this->excuseService->review($excuseRequest, $request->user(), 'approved');

        return new ExcuseRequestResource($updated);
    }

    public function reject(Request $request, ExcuseRequest $excuseRequest): ExcuseRequestResource
    {
        $this->authorize('review', $excuseRequest);
        $updated = $this->excuseService->review($excuseRequest, $request->user(), 'rejected');

        return new ExcuseRequestResource($updated);
    }
}

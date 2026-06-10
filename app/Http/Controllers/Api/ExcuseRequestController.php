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
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExcuseRequestController extends Controller
{
  public function __construct(private ExcuseService $excuseService) {}

  /**
   * GET /excuse-requests
   * - branch_manager: all excuse requests
   * - track_admin: excuse requests for their track's students
   * - student: their own excuse requests only
   */
  public function index(Request $request): AnonymousResourceCollection
  {
    $this->authorize('viewAny', ExcuseRequest::class);

    $requests = $this->excuseService->index($request->user());

    return ExcuseRequestResource::collection($requests);
  }

  /**
   * GET /excuse-requests/{id}
   * Accessible by: branch_manager, track_admin — if they have access to this student
   */
  public function show(ExcuseRequest $excuseRequest): ExcuseRequestResource
  {
    $this->authorize('view', $excuseRequest);

    return new ExcuseRequestResource(
      $excuseRequest->load(['attendanceRecord.student.user', 'attendanceRecord.engagement', 'reviewer.user'])
    );
  }

  /**
   * POST /excuse-requests
   * Student only. Optional attachment (PDF or image, max 1MB).
   */
  public function store(StoreExcuseRequest $request): JsonResponse
  {
    $this->authorize('create', ExcuseRequest::class);

    $excuseRequest = $this->excuseService->store(
      student: $request->user()->studentProfile,
      data: $request->validated(),
      attachment: $request->file('attachment'),
    );

    return (new ExcuseRequestResource($excuseRequest))
      ->response()
      ->setStatusCode(201);
  }

  /**
   * PATCH /excuse-requests/{id}
   * Student only — can only edit their own pending (unreviewed) requests.
   */
  public function update(UpdateExcuseRequest $request, ExcuseRequest $excuseRequest): ExcuseRequestResource
  {
    $this->authorize('update', $excuseRequest);

    $updated = $this->excuseService->update($excuseRequest, $request->validated(), $request->file('attachment'));

    return new ExcuseRequestResource($updated);
  }

  /**
   * POST /excuse-requests/{excuseRequest}/approve
   * Track admin only — for students in their track.
   */
  public function approve(ExcuseRequest $excuseRequest): ExcuseRequestResource
  {
    $this->authorize('review', $excuseRequest);

    $updated = $this->excuseService->approve($excuseRequest, request()->user()->staffProfile);

    return new ExcuseRequestResource($updated);
  }

  /**
   * POST /excuse-requests/{excuseRequest}/reject
   * Track admin only — for students in their track.
   */
  public function reject(ExcuseRequest $excuseRequest): ExcuseRequestResource
  {
    $this->authorize('review', $excuseRequest);

    $updated = $this->excuseService->reject($excuseRequest, request()->user()->staffProfile);

    return new ExcuseRequestResource($updated);
  }
}

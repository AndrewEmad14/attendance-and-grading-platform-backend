<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInRequest;
use App\Http\Requests\PatchAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceController extends Controller
{
  public function __construct(private AttendanceService $attendanceService) {}

  public function index(Request $request): JsonResponse
  {
    $this->authorize('viewAny', AttendanceRecord::class);

    $records = $this->attendanceService->index(
      $request->user(),
      $request->integer('per_page', 20)
    );

    return AttendanceResource::collection($records)->response();
  }

  /**
   * GET /attendance
   * Accessible by: branch_manager (all), track_admin (their track), instructor (their engagements)
   */
  // public function index(Request $request): AnonymousResourceCollection
  // {
  //   $this->authorize('viewAny', AttendanceRecord::class);

  //   $records = $this->attendanceService->index($request->user());

  //   return AttendanceResource::collection($records);
  // }

  /**
   * GET /attendance/{id}
   * Accessible by: branch_manager, track_admin, instructor — if they have access to this student
   */
  public function show(AttendanceRecord $attendance): AttendanceResource
  {
    $this->authorize('view', $attendance);

    return new AttendanceResource($attendance->load(['student.user', 'engagement']));
  }

  /**
   * POST /attendance
   * Student-only. Handles both check-in (first scan) and check-out (second scan).
   * Subsequent scans beyond check-out are idempotent.
   */
  public function store(CheckInRequest $request): JsonResponse
  {
    $this->authorize('create', AttendanceRecord::class);

    $record = $this->attendanceService->handleScan(
      student: $request->user()->studentProfile->id,
      engagementId: $request->validated('engagement_id'),
    );

    return (new AttendanceResource($record))
      ->response()
      ->setStatusCode($record->wasRecentlyCreated ? 201 : 200);
  }

  /**
   * PATCH /attendance/{id}
   * Accessible by: branch_manager, track_admin, instructor — if they have access to this student
   */
  public function update(PatchAttendanceRequest $request, AttendanceRecord $attendance): AttendanceResource
  {
    $this->authorize('update', $attendance);

    $updated = $this->attendanceService->correctTimestamps($attendance, $request->validated());

    return new AttendanceResource($updated);
  }
}

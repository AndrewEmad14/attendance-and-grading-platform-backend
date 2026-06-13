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

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendanceService) {}

    public function index(Request $request): JsonResponse
    {
        $records = $this->attendanceService->index(
            $request->user(),
            $request->integer('per_page', 20),
            $request->integer('engagement_id') ?: null,
        );

        return AttendanceResource::collection($records)->response();
    }

    public function show(AttendanceRecord $attendance): AttendanceResource
    {
        $this->authorize('view', $attendance);

        return new AttendanceResource($attendance->load(['student.user', 'engagement']));
    }

    public function store(CheckInRequest $request): JsonResponse
    {
        $this->authorize('create', AttendanceRecord::class);
        $record = $this->attendanceService->handleScan(
            user: $request->user(),
            engagementId: $request->validated('engagement_id'),
            token: $request->validated('token'),
        );

        $outcome = 'idempotent';
        $message = 'Attendance has already been recorded.';

        if ($record->wasRecentlyCreated) {
            $outcome = 'checked_in';
            $message = 'Checked in successfully.';
        } elseif ($record->wasChanged('left_at')) {
            $outcome = 'checked_out';
            $message = 'Checked out successfully.';
        }

        return response()->json([
            'data' => [
                'outcome' => $outcome,
                'message' => $message,
                'record' => new AttendanceResource($record),
            ]
        ], $record->wasRecentlyCreated ? 201 : 200);
    }

    public function update(PatchAttendanceRequest $request, AttendanceRecord $attendance): AttendanceResource
    {
        $this->authorize('update', $attendance);
        $updated = $this->attendanceService->correctTimestamps($attendance, $request->validated());

        return new AttendanceResource($updated);
    }
}

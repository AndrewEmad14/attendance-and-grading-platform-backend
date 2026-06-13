<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExcuseRequest;
use App\Models\StudentProfile;
use App\Services\AccessService;
use App\Services\AttendanceLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceLedgerController extends Controller
{
    public function __construct(
        private AccessService $accessService,
        private AttendanceLedgerService $ledgerService,
    ) {}

    public function show(Request $request, StudentProfile $student): JsonResponse
    {
        if (! $this->accessService->canAccessStudent($request->user(), $student)) {
            abort(403);
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'in:present,absent'],
        ]);

        $paginator = $this->ledgerService->buildLedger($student, $filters, perPage: 15);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function meta(Request $request, StudentProfile $student): JsonResponse
    {
        if (! $this->accessService->canAccessStudent($request->user(), $student)) {
            abort(403);
        }

        return response()->json([
            'data' => [
                'id' => $student->id,
                'name' => $student->user->name,
                'current_balance' => $student->attendance_balance,
            ],
        ]);
    }

    public function absentEngagements(Request $request, StudentProfile $student): JsonResponse
    {
        if (! $this->accessService->canAccessStudent($request->user(), $student)) {
            abort(403);
        }

        $engagements = $student->expectedEngagementsQuery()
            ->with('engageable')
            ->where('starts_at', '<=', now())
            ->get()
            ->filter(function ($engagement) use ($student) {
                $attendance = $student->attendanceRecords()
                    ->where('engagement_id', $engagement->id)
                    ->whereNotNull('arrived_at')
                    ->exists();

                $hasExcuse = ExcuseRequest::where('student_id', $student->id)
                    ->where('engagement_id', $engagement->id)
                    ->exists();

                return ! $attendance && ! $hasExcuse;
            })
            ->values()
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->engageable?->name ?? "Engagement #{$e->id}",
                'date' => $e->starts_at?->toISOString(),
            ]);

        return response()->json(['data' => $engagements]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLabGroupRequest;
use App\Http\Requests\AttachStudentToLabGroupRequest;
use App\Http\Resources\LabGroupResource;
use App\Models\Cohort;
use App\Models\LabGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LabGroupController extends Controller
{
    public function index(Request $request, Cohort $cohort): JsonResponse
    {
        if ($request->user()->role !== 'branch_manager' && $request->user()->role !== 'track_admin') {
            abort(403, 'This action is unauthorized.');
        }

        $labGroups = $cohort->labGroups()
            ->withCount('students')
            ->paginate($request->get('per_page', 15));

        return LabGroupResource::collection($labGroups)->response();
    }

    public function store(StoreLabGroupRequest $request, Cohort $cohort): JsonResponse
    {
        $labGroup = $cohort->labGroups()->create($request->validated());

        return (new LabGroupResource($labGroup))
            ->response()
            ->setStatusCode(201);
    }

    public function attachStudent(AttachStudentToLabGroupRequest $request, LabGroup $labGroup): JsonResponse
    {
        $student = \App\Models\StudentProfile::findOrFail($request->input('student_id'));
        $student->labGroup()->associate($labGroup)->save();
        
        return response()->json([
            'message' => 'Student linked to the lab group assignment matrix successfully.'
        ], 201);
    }

    public function detachStudent(Request $request, LabGroup $labGroup, int $studentId): JsonResponse
    {
        if ($request->user()->role !== 'track_admin' && $request->user()->role !== 'branch_manager') {
            abort(403, 'This action is unauthorized.');
        }

        $labGroup->students()->detach($studentId);

        return response()->json(null, 204);
    }

    public function destroy(Request $request, LabGroup $labGroup): JsonResponse
    {
        if ($request->user()->role !== 'track_admin' && $request->user()->role !== 'branch_manager') {
            abort(403, 'This action is unauthorized.');
        }

        $labGroup->students()->detach();
        $labGroup->delete();

        return response()->json(null, 204);
    }
}
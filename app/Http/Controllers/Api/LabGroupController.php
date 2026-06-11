<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachStudentToLabGroupRequest;
use App\Http\Requests\StoreLabGroupRequest;
use App\Http\Resources\CohortStudentResource;
use App\Http\Resources\LabGroupResource;
use App\Models\Cohort;
use App\Models\LabGroup;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabGroupController extends Controller
{
    public function index(Request $request, Cohort $cohort): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'student') {
            $query = $cohort->labGroups()->where('id', $user->studentProfile?->lab_group_id);
        } elseif ($user->role === 'instructor') {
            $query = $cohort->labGroups()->whereHas('labs.engagements', function ($q) use ($user) {
                $q->where('staff_id', $user->staffProfile->id);
            });
        } elseif ($user->role === 'track_admin' || $user->role === 'branch_manager') {
            $query = $cohort->labGroups();
        } else {
            abort(403, 'This action is unauthorized.');
        }

        if ($request->boolean('include_students') && $user->role !== 'student') {
            $query->with(['students.user']);
        }

        $labGroups = $query->withCount('students')
            ->paginate($request->get('per_page', 15));

        return LabGroupResource::collection($labGroups)->response();
    }

    public function cohortStudents(Request $request, Cohort $cohort): JsonResponse
    {
        if ($request->user()->role !== 'track_admin' && $request->user()->role !== 'branch_manager') {
            abort(403, 'This action is unauthorized.');
        }

        $query = StudentProfile::where('cohort_id', $cohort->id)->with(['user']);

        if ($request->boolean('unassigned_only')) {
            $query->whereNull('lab_group_id');
        }

        $students = $query->paginate($request->get('per_page', 15));

        return CohortStudentResource::collection($students)->response();
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
        $student = StudentProfile::where('cohort_id', $labGroup->cohort_id)
            ->findOrFail($request->input('student_id'));

        $student->labGroup()->associate($labGroup)->save();

        return response()->json([
            'message' => 'Student linked to the lab group assignment matrix successfully.',
        ], 201);
    }

    public function detachStudent(Request $request, LabGroup $labGroup, int $studentId): JsonResponse
    {
        if ($request->user()->role !== 'track_admin' && $request->user()->role !== 'branch_manager') {
            abort(403, 'This action is unauthorized.');
        }

        $student = $labGroup->students()->findOrFail($studentId);
        $student->labGroup()->disassociate()->save();

        return response()->json(null, 204);
    }

    public function destroy(Request $request, LabGroup $labGroup): JsonResponse
    {
        if ($request->user()->role !== 'track_admin' && $request->user()->role !== 'branch_manager') {
            abort(403, 'This action is unauthorized.');
        }

        StudentProfile::where('lab_group_id', $labGroup->id)
            ->update(['lab_group_id' => null]);

        $labGroup->delete();

        return response()->json(null, 204);
    }
}

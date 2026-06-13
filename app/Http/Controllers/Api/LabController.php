<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LabResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabController extends Controller
{
    public function index(Request $request, Course $course): JsonResponse
    {
        // Simple authorization: assuming branch manager and track admins can view all labs for a course.
        $user = $request->user();
        if ($user->role === 'student') {
            abort(403, 'This action is unauthorized.');
        }

        $query = $course->labs()->with('labGroup');

        if ($user->role === 'instructor') {
            $query->whereHas('engagements', function ($q) use ($user) {
                $q->where('staff_id', $user->staffProfile->id);
            });
        }

        $labs = $query->get();

        return LabResource::collection($labs)->response();
    }
}

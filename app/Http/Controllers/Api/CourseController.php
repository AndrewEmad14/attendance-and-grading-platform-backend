<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\CourseDeliverable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CourseController extends Controller
{
    use AuthorizesRequests;

    public function index(int $cohortId) // courses+deliverables
    {
        $this->authorize('viewAny', Course::class);
        $courses = Course::where('cohort_id', $cohortId)->with('deliverables')->get();

        return CourseResource::collection($courses);
    }

    public function store(StoreCourseRequest $request, int $cohortId)
    {
        $this->authorize('create', Course::class);
        $course = Course::create(['cohort_id' => $cohortId, 'name' => $request->name]);

        if ($request->has('deliverables')) {
            foreach ($request->deliverables as $d) {
                CourseDeliverable::create([...$d, 'course_id' => $course->id]);
            }
        }

        return new CourseResource($course->load('deliverables'));
    }

    public function show(Course $course)
    {
        $this->authorize('view', $course);

        return new CourseResource($course->load('deliverables'));
    }

    public function update(UpdateCourseRequest $request, Course $course)
    {
        $this->authorize('update', $course);

        if ($request->has('name')) {
            $course->update(['name' => $request->name]);
        }

        if ($request->has('deliverables')) {
            foreach ($request->deliverables as $d) {
                CourseDeliverable::updateOrCreate(['id' => $d['id'] ?? null, 'course_id' => $course->id], $d);
            }
        }

        return new CourseResource($course->load('deliverables'));
    }

    public function destroy(Course $course)
    {
        $this->authorize('delete', $course);
        $course->delete();

        return response()->noContent();
    }
}

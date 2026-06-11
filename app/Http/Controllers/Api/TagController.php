<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachTagRequest;
use App\Http\Requests\StoreTagRequest;
use App\Http\Resources\TagResource;
use App\Models\StudentProfile;
use App\Models\Tag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

class TagController extends Controller
{
    use AuthorizesRequests;

    public function index()// returns all available tags
    {
        $this->authorize('viewAny', Tag::class);

        return TagResource::collection(Tag::all());
    }

    // creates a new tag by admin only
    public function store(StoreTagRequest $request)
    {
        $this->authorize('create', Tag::class);
        $tag = Tag::create(['tag' => $request->tag]);

        return new TagResource($tag);
    }

    // returns all tags attached to a specific student
    public function studentTags(int $studentId)
    {
        $profile = StudentProfile::where('user_id', $studentId)->firstOrFail();
        $tagIds = DB::table('students_tags')->where('student_id', $profile->id)->pluck('tag_id');

        return TagResource::collection(Tag::whereIn('id', $tagIds)->get());
    }

    // attaches a tag to student
    public function attach(AttachTagRequest $request, int $studentId)
    {
        $this->authorize('attachToStudent', Tag::class);
        $profile = StudentProfile::where('user_id', $studentId)->firstOrFail();
        DB::table('students_tags')->insertOrIgnore([
            'student_id' => $profile->id,
            'tag_id' => $request->tag_id,
        ]);

        return TagResource::collection(Tag::whereIn('id',
            DB::table('students_tags')->where('student_id', $profile->id)->pluck('tag_id')
        )->get());
    }

    // removes a tag from a student by admin only
    public function detach(int $studentId, int $tagId)
    {
        $this->authorize('deletefromStudent', Tag::class);
        $profile = StudentProfile::where('user_id', $studentId)->firstOrFail();
        DB::table('students_tags')->where('student_id', $profile->id)->where('tag_id', $tagId)->delete();

        return response()->noContent();
    }
}

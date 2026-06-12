<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Models\Announcement;
use App\Models\Cohort;

class AnnouncementController extends Controller
{
    /**
     * GET /cohorts/{cohort}/announcements
     */
    public function index(Cohort $cohort)
    {
        $this->authorize('viewAny', [Announcement::class, $cohort]);

        $announcements = Announcement::where(function ($q) use ($cohort) {
            $q->where('cohort_id', $cohort->id)
                ->orWhereNull('cohort_id');
        })
            ->whereNotNull('published_at')
            ->with('staff.user:id,name')
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'message' => 'fetched announcements successfully',
            'status' => 200,
            'data' => $announcements,
        ]);
    }

    /**
     * POST /cohorts/{cohort}/announcements
     */
    public function store(StoreAnnouncementRequest $request, Cohort $cohort)
    {
        $this->authorize('store', [Announcement::class, $cohort]);

        $announcement = $cohort->announcements()->create([
            'staff_id' => $request->user()->staffProfile->id,
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'published_at' => now(),
        ]);

        return response()->json([
            'message' => 'announcement created successfully',
            'status' => 201,
            'data' => $announcement->load('staff.user:id,name'),
        ], 201);
    }

    /**
     * PATCH /announcements/{announcement}
     */
    public function update(UpdateAnnouncementRequest $request, Announcement $announcement)
    {
        $this->authorize('update', $announcement);

        $announcement->update($request->validated());

        return response()->json([
            'message' => 'announcement updated successfully',
            'status' => 200,
            'data' => $announcement->load('staff.user:id,name'),
        ]);
    }

    /**
     * DELETE /announcements/{announcement}
     */
    public function destroy(Announcement $announcement)
    {
        $this->authorize('delete', $announcement);

        $announcement->delete();

        return response()->json([
            'message' => 'announcement deleted successfully',
            'status' => 200,
        ]);
    }

    public function broadcast(StoreAnnouncementRequest $request)
    {
        $announcement = Announcement::create([
            'staff_id' => $request->user()->staffProfile->id,
            'cohort_id' => null,
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'published_at' => now(),
        ]);

        return response()->json([
            'message' => 'announcement broadcast successfully',
            'status' => 201,
            'data' => $announcement->load('staff.user:id,name'),
        ], 201);
    }
}

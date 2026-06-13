<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\StaffProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CohortAssignmentController extends Controller
{
    /**
     * Link a track administrator profile to a specific cohort.
     */
    public function assign(Request $request, Cohort $cohort, StaffProfile $staffProfile): JsonResponse
    {
        if (! $request->user()->can('assignAdmin', $cohort)) {
            abort(403, 'This action is unauthorized.');
        }

        // Verify the staff profile is actually a track admin to prevent system assignment anomalies
        if ($staffProfile->user?->role !== 'track_admin') {
            abort(422, 'The selected staff profile does not hold administrative clearance credentials.');
        }

        // syncWithoutDetaching prevents duplicate row database primary key constraint faults
        $cohort->trackAdmins()->syncWithoutDetaching([$staffProfile->id]);

        return response()->json([
            'message' => 'Track administrator assigned to the targeted cohort successfully.',
        ], 200);
    }

    /**
     * Remove a track administrator profile linkage from a specific cohort.
     */
    public function unassign(Request $request, Cohort $cohort, StaffProfile $staffProfile): JsonResponse
    {
        if (! $request->user()->can('unassignAdmin', $cohort)) {
            abort(403, 'This action is unauthorized.');
        }

        // Explicitly check if the relationship exists before attempting to break it
        $isCurrentlyAssigned = $cohort->trackAdmins()
            ->where('cohorts_admins.staff_id', $staffProfile->id)
            ->exists();

        if (! $isCurrentlyAssigned) {
            abort(422, 'The selected track administrator is not currently assigned to this cohort.');
        }

        $cohort->trackAdmins()->detach($staffProfile->id);

        return response()->json(null, 204);
    }
}

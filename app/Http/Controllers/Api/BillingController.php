<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\StaffProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    /**
     * GET /billing/rollup
     *
     * Consolidated billing: summary and by_staff calculations for finished engagements.
     * Accessible only to branch_manager.
     */
    public function rollup(): JsonResponse
    {
        // 1. Authorization check
        if (auth()->user()->role !== 'branch_manager') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // 2. Fetch finished engagements (where ends_at <= now)
        $now = now();
        $finishedEngagements = DB::table('engagements')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->select('id', 'staff_id', 'scheduled_hours')
            ->get();

        $engagementsByStaff = $finishedEngagements->groupBy('staff_id');

        // 3. Fetch staff profiles with user details (only for staff who have given engagements)
        $finishedStaffIds = $finishedEngagements->pluck('staff_id')->unique()->filter()->values()->toArray();

        $staffProfiles = collect();
        if (! empty($finishedStaffIds)) {
            $staffProfiles = DB::table('staff_profiles')
                ->join('users', 'users.id', '=', 'staff_profiles.user_id')
                ->whereIn('staff_profiles.id', $finishedStaffIds)
                ->select(
                    'staff_profiles.id as staff_profile_id',
                    'users.name',
                    'staff_profiles.compensation_type',
                    'staff_profiles.hourly_rate',
                    'staff_profiles.fixed_salary'
                )
                ->get();
        }

        // 4. Build by_staff and compute summary stats
        $allStaffData = [];
        $totalCost = 0.0;
        $internalCost = 0.0;
        $externalCost = 0.0;

        foreach ($staffProfiles as $staff) {
            $staffEngagements = $engagementsByStaff->get($staff->staff_profile_id, collect());
            $scheduledHours = (float) $staffEngagements->sum('scheduled_hours');
            $hourlyRate = (float) $staff->hourly_rate;
            $fixedSalary = (float) $staff->fixed_salary;

            // Calculate billing amount (calculated_payout)
            if ($staff->compensation_type === 'internal') {
                $billingAmount = $fixedSalary + ($scheduledHours * $hourlyRate);
                $internalCost += $billingAmount;
            } else {
                $billingAmount = $scheduledHours * $hourlyRate;
                $externalCost += $billingAmount;
            }

            $totalCost += $billingAmount;

            $allStaffData[] = [
                'staff_profile_id' => $staff->staff_profile_id,
                'name' => $staff->name,
                'compensation_type' => $staff->compensation_type,
                'scheduled_hours' => $scheduledHours,
                'hourly_rate' => $hourlyRate,
                'billing_amount' => $billingAmount,
            ];
        }

        // 5. Paginate the by_staff results
        $perPage = (int) request()->input('per_page', 15);
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $collection = collect($allStaffData);
        $pagedData = $collection->slice(($currentPage - 1) * $perPage, $perPage)->values()->toArray();

        $paginated = new LengthAwarePaginator(
            $pagedData,
            $collection->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );

        return response()->json([
            'summary' => [
                'total_cost' => round($totalCost, 2),
                'internal_cost' => round($internalCost, 2),
                'external_cost' => round($externalCost, 2),
            ],
            'by_staff' => [
                'data' => $paginated->items(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /billing/instructors/{staffProfileId}
     *
     * Per-instructor billing detail.
     * Accessible only to branch_manager.
     */
    public function instructorBilling($staffProfileId): JsonResponse
    {
        // 1. Authorization check
        if (auth()->user()->role !== 'branch_manager') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // 2. Fetch staff profile
        $staffProfile = StaffProfile::with('user')->find($staffProfileId);
        if (! $staffProfile) {
            return response()->json(['message' => 'Staff profile not found'], 404);
        }

        // 3. Fetch all engagements (finished and unfinished) for this instructor
        $engagements = Engagement::with('engageable')
            ->where('staff_id', $staffProfileId)
            ->get();

        $engDetails = [];
        $totalDeliveredHours = 0.0;
        $hourlyRate = (float) $staffProfile->hourly_rate;
        $fixedSalary = (float) $staffProfile->fixed_salary;

        foreach ($engagements as $eng) {
            $title = $eng->engageable ? $eng->engageable->name : 'N/A';
            $type = $eng->type();

            // Determine status
            $status = 'in_progress';
            if ($eng->ends_at && $eng->ends_at->isPast()) {
                $billingRec = DB::table('billing_records')->where('engagement_id', $eng->id)->first();
                if ($billingRec) {
                    $status = $billingRec->forwarded_at ? 'forwarded' : 'pending';
                } else {
                    $status = 'pending';
                }

                // Sum only finished hours for total delivered hours
                $totalDeliveredHours += (float) $eng->scheduled_hours;
            }

            $estimatedPayout = (float) $eng->scheduled_hours * $hourlyRate;

            $engDetails[] = [
                'engagement_id' => $eng->id,
                'type' => $type,
                'title' => $title,
                'starts_at' => $eng->starts_at?->toDateTimeString(),
                'ends_at' => $eng->ends_at?->toDateTimeString(),
                'scheduled_hours' => (float) $eng->scheduled_hours,
                'estimated_payout' => $estimatedPayout,
                'status' => $status,
            ];
        }

        // 4. Paginate the engagements list
        $perPage = (int) request()->input('per_page', 15);
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $collection = collect($engDetails);
        $pagedData = $collection->slice(($currentPage - 1) * $perPage, $perPage)->values()->toArray();

        $paginated = new LengthAwarePaginator(
            $pagedData,
            $collection->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );

        $baseSalaryComponent = $staffProfile->compensation_type === 'internal' ? $fixedSalary : 0.0;
        $hourlyComponent = $totalDeliveredHours * $hourlyRate;
        $totalCalculatedPayout = $baseSalaryComponent + $hourlyComponent;

        return response()->json([
            'staff_profile_id' => $staffProfile->id,
            'name' => $staffProfile->user->name ?? 'N/A',
            'compensation_type' => $staffProfile->compensation_type,
            'fixed_salary' => $fixedSalary,
            'hourly_rate' => $hourlyRate,
            'payout_summary' => [
                'total_delivered_hours' => (float) $totalDeliveredHours,
                'base_salary_component' => (float) $baseSalaryComponent,
                'hourly_component' => (float) $hourlyComponent,
                'total_calculated_payout' => (float) $totalCalculatedPayout,
            ],
            'engagements' => [
                'data' => $paginated->items(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }
}

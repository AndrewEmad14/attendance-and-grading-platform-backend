<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AtRiskStudentResource;
use App\Models\Cohort;
use App\Services\AtRiskAnalyticsService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AtRiskController extends Controller
{
  public function __construct(private AtRiskAnalyticsService $analyticsService) {}

  /**
   * GET /analytics/cohorts/{cohort}/at-risk
   *
   * Returns students in the cohort whose attendance_balance is below a threshold
   * or who have multiple unexcused absences.
   *
   * Accessible by: branch_manager (any cohort), track_admin (their track's cohorts only)
   */
  public function index(Cohort $cohort): AnonymousResourceCollection
  {
    $this->authorize('viewAtRisk', $cohort);

    $students = $this->analyticsService->getAtRiskStudents($cohort);

    return AtRiskStudentResource::collection($students);
  }
}

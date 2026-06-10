<?php

namespace App\Services;

use App\Models\Course;
use App\Models\BusinessSession;
use App\Models\Engagement;
use App\Models\Lab;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

class AttendanceLedgerService
{
  private const UNEXCUSED_DEDUCTION = 25;
  private const EXCUSED_DEDUCTION   = 5;
  private const STARTING_BALANCE    = 250;

  /**
   * Builds the full attendance ledger for a student.
   *
   * Returns all engagements the student should have attended,
   * matched against their actual attendance records, with absence
   * status, deductions, and the running balance.
   */
  public function buildLedger(StudentProfile $student): array
  {
    // Load all three sets of engagements this student should attend.
    $engagements = $this->fetchEngagementsForStudent($student);

    // Index attendance records by engagement_id for O(1) lookup.
    $attendanceByEngagement = $student
      ->attendanceRecords()
      ->with('excuseRequest')
      ->get()
      ->keyBy('engagement_id');

    $runningBalance = self::STARTING_BALANCE;
    $entries        = [];

    foreach ($engagements as $engagement) {
      $attendance = $attendanceByEngagement->get($engagement->id);
      $present    = $attendance && !is_null($attendance->arrived_at);

      if ($present) {
        $entries[] = [
          'engagement_id'   => $engagement->id,
          'engagement_name' => $this->engagementName($engagement),
          'engagement_type' => $this->engagementType($engagement),
          'date'            => $engagement->starts_at?->toISOString(),
          'arrived_at'      => $attendance->arrived_at?->toISOString(),
          'left_at'         => $attendance->left_at?->toISOString(),
          'absence_status'  => 'present',
          'excuse_status'   => null,
          'deduction'       => 0,
          'running_balance' => $runningBalance,
        ];
        continue;
      }

      // Absent — check for an excuse request.
      $excuse    = $attendance?->excuseRequest;
      $deduction = self::UNEXCUSED_DEDUCTION;

      if ($excuse && $excuse->status === 'approved') {
        $deduction = self::EXCUSED_DEDUCTION;
      }

      $runningBalance -= $deduction;

      $entries[] = [
        'engagement_id'   => $engagement->id,
        'engagement_name' => $this->engagementName($engagement),
        'engagement_type' => $this->engagementType($engagement),
        'date'            => $engagement->starts_at?->toISOString(),
        'arrived_at'      => null,
        'left_at'         => null,
        'absence_status'  => 'absent',
        'excuse_status'   => $excuse?->status ?? 'none',
        'deduction'       => -$deduction,
        'running_balance' => $runningBalance,
      ];
    }

    return [
      'student' => [
        'id'   => $student->id,
        'name' => $student->user->name,
      ],
      'starting_balance' => self::STARTING_BALANCE,
      'current_balance'  => $student->attendance_balance,
      'entries'          => $entries,
    ];
  }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

  /**
   * Collects all engagements a student is expected to attend across
   * lectures (cohort-level), labs (lab-group-level), and business sessions
   * (cohort-level via pivot).
   */
  private function fetchEngagementsForStudent(StudentProfile $student): Collection
  {
    // 1. Lecture engagements — courses belonging to the student's cohort.
    $lectureEngagements = Engagement::with('engageable')
      ->where('engageable_type', Course::class)
      ->whereHas(
        'engageable',
        fn($q) =>
        $q->where('cohort_id', $student->cohort_id)
      )
      ->get();

    // 2. Lab engagements — labs belonging to the student's lab group.
    $labEngagements = Engagement::with('engageable')
      ->where('engageable_type', Lab::class)
      ->whereHas(
        'engageable',
        fn($q) =>
        $q->where('lab_group_id', $student->lab_group_id)
      )
      ->get();

    // 3. Business session engagements — sessions linked to the student's cohort.
    $businessEngagements = Engagement::with('engageable')
      ->where('engageable_type', BusinessSession::class)
      ->whereHas(
        'engageable.cohorts',
        fn($q) =>
        $q->where('cohorts.id', $student->cohort_id)
      )
      ->get();

    return $lectureEngagements
      ->concat($labEngagements)
      ->concat($businessEngagements)
      ->sortBy('starts_at')
      ->values();
  }

  private function engagementType(Engagement $engagement): string
  {
    return match ($engagement->engageable_type) {
      Course::class          => 'lecture',
      Lab::class             => 'lab',
      BusinessSession::class => 'business_session',
      default                => 'unknown',
    };
  }

  private function engagementName(Engagement $engagement): string
  {
    return $engagement->engageable?->name ?? "Engagement #{$engagement->id}";
  }
}

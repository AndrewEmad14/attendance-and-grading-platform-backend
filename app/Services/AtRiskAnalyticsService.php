<?php

namespace App\Services;

use App\Models\Cohort;
use App\Models\ExcuseRequest;
use Illuminate\Database\Eloquent\Collection;

class AtRiskAnalyticsService
{
  /**
   * Threshold: students with attendance_balance at or below this value are flagged.
   * Adjust to match your business rules.
   */
  private const BALANCE_THRESHOLD = -2;

  /**
   * Returns students in the cohort who are considered at-risk based on
   * their attendance_balance or number of unexcused absences.
   *
   * "At-risk" means:
   *   - attendance_balance <= BALANCE_THRESHOLD, OR
   *   - has 2+ unexcused absences (attendance record exists, no approved excuse)
   *
   * Each result is the StudentProfile with appended risk metadata.
   */
  public function getAtRiskStudents(Cohort $cohort): Collection
  {
    $students = $cohort->students()
      ->with([
        'user',
        'attendanceRecords.excuseRequest',
        'attendanceRecords.engagement',
      ])
      ->get();

    return $students
      ->map(function ($student) {
        $unexcusedCount = $student->attendanceRecords
          ->filter(fn($record) => $this->isUnexcused($record))
          ->count();

        $student->setAttribute('unexcused_absences', $unexcusedCount);
        $student->setAttribute('is_at_risk', $this->isAtRisk($student, $unexcusedCount));

        return $student;
      })
      ->filter(fn($student) => $student->is_at_risk)
      ->values();
  }

  private function isAtRisk($student, int $unexcusedCount): bool
  {
    return $student->attendance_balance <= self::BALANCE_THRESHOLD
      || $unexcusedCount >= 2;
  }

  /**
   * An attendance record is "unexcused" if the student was absent
   * (no arrived_at) and has no approved excuse request.
   */
  private function isUnexcused($record): bool
  {
    if ($record->arrived_at !== null) {
      return false; // Student was present
    }

    $excuse = $record->excuseRequest;

    if ($excuse === null) {
      return true; // Absent with no excuse filed
    }

    return $excuse->status === ExcuseRequest::STATUS_REJECTED;
  }
}

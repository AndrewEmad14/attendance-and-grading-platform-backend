<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats the ledger array built by AttendanceLedgerService::buildLedger().
 *
 * Expected $resource shape:
 * [
 *   'student'         => StudentProfile,
 *   'entries'         => Collection of LedgerEntry objects/arrays,
 *   'current_balance' => int,
 * ]
 *
 * Each entry shape:
 * [
 *   'engagement'        => Engagement,
 *   'engagement_type'   => 'lecture' | 'lab' | 'business_session',
 *   'name'              => string,
 *   'starts_at'         => Carbon,
 *   'attendance_record' => AttendanceRecord|null,
 *   'status'            => 'present' | 'unexcused' | 'excused_pending' | 'excused_approved' | 'excused_rejected',
 *   'deduction'         => int,        // 0 if present or excused_approved, negative otherwise
 *   'running_balance'   => int,
 * ]
 */
class AttendanceLedgerResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'student' => [
        'id' => $this->resource['student']->id,
        'name' => $this->resource['student']->user->name,
        'current_balance' => $this->resource['current_balance'],
      ],
      'entries' => collect($this->resource['entries'])->map(fn($entry) => [
        'engagement_id' => $entry['engagement']->id,
        'engagement_type' => $entry['engagement_type'],
        'name' => $entry['name'],
        'ends_at' => $entry['engagement']->ends_at?->toIso8601String(),
        'attendance' => $entry['attendance_record'] ? [
          'id' => $entry['attendance_record']->id,
          'arrived_at' => $entry['attendance_record']->arrived_at?->toIso8601String(),
          'left_at' => $entry['attendance_record']->left_at?->toIso8601String(),
        ] : null,
        'excuse_request' => $entry['excuse_request'] ? [
          'id' => $entry['excuse_request']->id,
          'status' => $entry['excuse_request']->status,
          'reason' => $entry['excuse_request']->reason,
        ] : null,
        'status' => $entry['status'],
        'deduction' => $entry['deduction'],
        'running_balance' => $entry['running_balance'],
      ]),
    ];
  }
}

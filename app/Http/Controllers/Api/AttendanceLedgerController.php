<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceLedgerResource;
use App\Models\StudentProfile;
use App\Services\AttendanceLedgerService;
use App\Services\AccessService;

class AttendanceLedgerController extends Controller
{
  public function __construct(
    private AttendanceLedgerService $ledgerService
  ) {}

  /**
   * GET /students/{student}/attendance-ledger
   *
   * Returns:
   *  - All engagements the student is expected to attend (lectures, labs, business sessions)
   *  - Their attendance record per engagement (if any)
   *  - Absence status: present | excused_pending | excused_approved | excused_rejected | unexcused
   *  - Deduction per entry
   *  - Running balance + current attendance_balance from student_profiles
   *
   * Accessible by: branch_manager, track_admin, instructor — if they have access to this student
   */
  public function show(StudentProfile $student): AttendanceLedgerResource
  {
    $this->authorize('viewLedger', $student);

    $ledger = $this->ledgerService->buildLedger($student);

    return new AttendanceLedgerResource($ledger);
  }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceLedgerResource;
use App\Models\StudentProfile;
use App\Services\AttendanceLedgerService;
use App\Services\AccessService;
use Illuminate\Http\Request;

class AttendanceLedgerController extends Controller
{
  public function __construct(
    private AccessService $accessService,
    private AttendanceLedgerService $ledgerService,
  ) {}

  public function show(Request $request, StudentProfile $student): AttendanceLedgerResource
  {
    if (!$this->accessService->canAccessStudent($request->user(), $student)) {
      abort(403);
    }
    $ledger = $this->ledgerService->buildLedger($student);
    return new AttendanceLedgerResource($ledger);
  }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AtRiskStudentResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'name' => $this->user->name,
      'email' => $this->user->email,
      'attendance_balance' => $this->attendance_balance,
      'unexcused_absences' => $this->unexcused_absences,
      'cohort_id' => $this->cohort_id,
      'lab_group_id' => $this->lab_group_id,
    ];
  }
}

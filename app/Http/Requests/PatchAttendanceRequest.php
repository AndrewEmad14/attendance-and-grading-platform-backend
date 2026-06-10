<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatchAttendanceRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'arrived_at' => ['sometimes', 'nullable', 'date'],
      'left_at' => ['sometimes', 'nullable', 'date', 'after:arrived_at'],
    ];
  }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExcuseRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'attendance_id' => ['required', 'integer', 'exists:attendance_records,id'],
      'reason' => ['required', 'string', 'max:1000'],
      'attachment' => [
        'nullable',
        'file',
        'max:1024',
        'mimes:pdf,jpg,jpeg,png,webp',
      ],
    ];
  }
}

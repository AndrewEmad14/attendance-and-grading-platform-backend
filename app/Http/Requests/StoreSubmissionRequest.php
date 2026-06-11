<?php

namespace App\Http\Requests;

use App\Models\Submission;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubmissionRequest extends FormRequest
{
    /**
     * Authorize the submission attempt.
     *
     * deliverable_id comes from the route, not the body.
     * student_id is always the authenticated user — never client-supplied
     * (a student must not be able to submit as someone else).
     */
    public function authorize(): bool
    {
        $deliverable = $this->route('deliverable');

        return $this->user()?->can('create', [Submission::class, $deliverable]) ?? false;
    }

    /**
     * Mutually-exclusive submission channels (POR-4):
     *   - type=url  → a URL is required, no file allowed
     *   - type=file → a file is required, no URL allowed
     */
    public function rules(): array
    {
        return [
            'submission_type' => ['required', 'in:url,file'],

            'url' => [
                'required_if:submission_type,url',
                'prohibited_unless:submission_type,url',
                'url',
                'max:2048',
            ],

            'file' => [
                'required_if:submission_type,file',
                'prohibited_unless:submission_type,file',
                'file',
                'max:25600', // 25 MB, expressed in kilobytes
                'mimes:pdf,zip,jpg,jpeg,png',
                'mimetypes:application/pdf,application/zip,application/octet-stream,image/jpeg,image/png',
            ],
        ];
    }

    /**
     * Clearer messages for the conditional rules — the defaults read poorly
     * for required_if / prohibited_unless.
     */
    public function messages(): array
    {
        return [
            'submission_type.in' => 'Submission type must be either "url" or "file".',
            'url.required_if' => 'A URL is required when submitting by link.',
            'url.prohibited_unless' => 'A URL may only be provided when the submission type is "url".',
            'file.required_if' => 'A file is required when submitting by upload.',
            'file.prohibited_unless' => 'A file may only be provided when the submission type is "file".',
            'file.max' => 'The file may not be larger than 25 MB.',
            'file.mimes' => 'The file must be a PDF, ZIP, or image (jpg, jpeg, png).',
            'file.mimetypes' => 'The file must be a PDF, ZIP, or image (jpg, jpeg, png).',
        ];
    }
}

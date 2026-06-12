<?php

namespace App\Http\Requests;

use App\Models\Submission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
     *   - type=link → a link is required, no file allowed
     *   - type=file → a file is required, no link allowed
     */
    public function rules(): array
    {
        return [
            'submission_type' => ['required', 'in:link,file'],

            'link' => [
                'required_if:submission_type,link',
                'prohibited_unless:submission_type,link',
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
            'submission_type.in' => 'Submission type must be either "link" or "file".',
            'link.required_if' => 'A link is required when submitting by link.',
            'link.prohibited_unless' => 'A link may only be provided when the submission type is "link".',
            'file.required_if' => 'A file is required when submitting by upload.',
            'file.prohibited_unless' => 'A file may only be provided when the submission type is "file".',
            'file.max' => 'The file may not be larger than 25 MB.',
            'file.mimes' => 'The file must be a PDF, ZIP, or image (jpg, jpeg, png).',
            'file.mimetypes' => 'The file must be a PDF, ZIP, or image (jpg, jpeg, png).',
        ];
    }

    /**
 * Reject a second submission to the same deliverable before it reaches
 * the DB unique constraint, so the student sees a clear 422 rather than
 * a 500 integrity error. Resubmission is not supported: the student must
 * ask their Track Admin to delete the existing one first.
 */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $deliverable = $this->route('deliverable');
            $student     = $this->user()?->studentProfile;

            if (! $deliverable || ! $student) {
                return;
            }

            $exists = Submission::where('deliverable_id', $deliverable->id)
                ->where('student_id', $student->id)
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'submission_type',
                    'You already have a submission for this deliverable. Ask your Track Admin to remove it before submitting again.'
                );
            }
        });
    }
}

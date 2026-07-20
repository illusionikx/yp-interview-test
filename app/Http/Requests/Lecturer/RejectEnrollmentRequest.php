<?php

namespace App\Http\Requests\Lecturer;

use App\Enums\RejectionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectEnrollmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * DIVERGENCE FROM D-09 (same divergence as StoreSectionRequest /
     * AssignLecturerRequest): section/enrollment writes are genuinely
     * per-subject-ownership-gated (SEC-03), unlike exam/subject CRUD's
     * `return true;` convention. Only a lecturer assigned to the bound
     * section's subject via the subject_user pivot may reject a student
     * from it — ANY assigned lecturer qualifies, not only the section's
     * creator (08-CONTEXT.md). Returning `true` here would be an IDOR
     * (T-08-05-IDOR): any lecturer could reject a student from any
     * subject's section.
     */
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section->subject->lecturers()->whereKey($this->user()->id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Rule::enum — the Laravel-native rule for backed enums — keeps
     * validation in lockstep with RejectionReason and matches this
     * codebase's native-enum-everywhere convention (Role, QuestionType,
     * EnrollmentStatus). No hand-rolled Rule::in of raw strings.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(RejectionReason::class)],
        ];
    }
}

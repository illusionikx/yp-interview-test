<?php

namespace App\Http\Requests\Lecturer;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignLecturerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * DIVERGENCE FROM D-09 (same divergence as StoreSectionRequest /
     * UpdateSectionRequest, 07-RESEARCH.md Pattern 2 / Assumption A4):
     * only a lecturer already assigned to the bound subject may manage
     * its lecturer-assignment list (SEC-03 "any assigned lecturer").
     *
     * Bootstrap assumption: this means the very first lecturer on a
     * subject cannot self-assign via this endpoint — that first
     * subject_user row must be seeded (07-07 seeds a demo subject_user
     * row), matching how a subject is created with no lecturers by
     * default and one is attached out-of-band the first time.
     */
    public function authorize(): bool
    {
        $subject = $this->route('subject');

        return $subject->lecturers()->whereKey($this->user()->id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The `exists` rule is scoped to `role=lecturer` so only a lecturer
     * account may be assigned to manage a subject — mirrors the
     * AssignStudentRequest role-scoping idiom (student-only) inverted
     * for this lecturer-only assignment.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', Role::Lecturer->value),
            ],
        ];
    }
}

<?php

namespace App\Http\Requests\Lecturer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The D-06 draft-only mutation gate ("a published exam is immutable")
     * is RETIRED this phase (D-4). Editing a published/attempted exam is
     * now allowed; EDT-04's destructive-warning flow (App\Services\
     * AttemptVoider, wired in ExamController::update()) replaces the old
     * "can't touch it" protection with an explicit, warned one — saving an
     * edit to an attempted exam voids its attempts, after a warning,
     * rather than being refused outright.
     *
     * This introduces no NEW authorization gap: this codebase has no
     * per-exam ownership check anywhere — ownership is subject-level via
     * `subject_user`, established in Phase 2/3 — and the `role:lecturer`
     * middleware on the route group (routes/lecturer.php) is what keeps
     * students out. This mirrors the `return true;` + "no per-record
     * ownership" idiom the deleted `AssignExamRequest` used (10-05).
     *
     * The availability window (`available_from`/`available_until`,
     * 08-06/AVL-01) is inside this same relaxed gate — it is just another
     * exam field, now editable post-publish like everything else.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // subject_id is deliberately NOT here: an exam's subject is fixed at
            // creation (from the subject's own page) and cannot be changed on
            // update — any submitted subject_id is ignored, keeping the subject
            // server-authoritative.
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
        ];
    }
}

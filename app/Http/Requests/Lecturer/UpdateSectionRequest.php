<?php

namespace App\Http\Requests\Lecturer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * DIVERGENCE FROM D-09: unlike Subject/Classroom CRUD (which use
     * `return true;` — role:lecturer middleware is the sole gate),
     * section management is genuinely per-subject-ownership-gated
     * (SEC-03). Only a lecturer assigned to the bound subject via the
     * subject_user pivot may update a section under it — a lecturer
     * NOT assigned must get 403, not merely a hidden UI affordance
     * (07-RESEARCH.md Pattern 2 / Assumption A4).
     */
    public function authorize(): bool
    {
        $subject = $this->route('subject');

        return $subject->lecturers()->whereKey($this->user()->id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The section's display name is a computed accessor
     * (year-semester-sequence), not a writable field, so there's no
     * "name" uniqueness rule — but `sequence` IS immutable while
     * `year`/`semester` ARE editable, and `sections` carries a
     * `unique(['subject_id', 'year', 'semester', 'sequence'])` index.
     * Without a matching validation rule, editing `year`/`semester` into
     * a combination that collides with another section's `(subject_id,
     * year, semester, sequence)` throws an uncaught QueryException (500)
     * instead of a friendly 422 (WR-02). Guard it explicitly, scoped to
     * the bound subject and the row's own (immutable) sequence, ignoring
     * the row being edited.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year' => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique('sections')
                    ->where(fn ($query) => $query
                        ->where('subject_id', $this->route('subject')->id)
                        ->where('semester', $this->input('semester'))
                        ->where('sequence', $this->route('section')->sequence))
                    ->ignore($this->route('section')->id),
            ],
            'semester' => ['required', 'integer', 'in:1,2'],
            'capacity' => ['required', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
        ];
    }
}

<?php

namespace App\Http\Requests\Lecturer;

use Illuminate\Foundation\Http\FormRequest;

class StoreSectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * DIVERGENCE FROM D-09: unlike Subject/Classroom CRUD (which use
     * `return true;` — role:lecturer middleware is the sole gate),
     * section management is genuinely per-subject-ownership-gated
     * (SEC-03). Only a lecturer assigned to the bound subject via the
     * subject_user pivot may create a section under it — a lecturer
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'capacity' => ['required', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
        ];
    }
}

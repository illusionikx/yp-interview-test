<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnswerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * SHAPE validation only — ownership/deadline are the controller's
     * job via AttemptPolicy + Attempt::finalizeIfExpired() (04-RESEARCH.md
     * Pattern 4; the `authorize()`-does-ownership split this mirrors was
     * previously exemplified by the now-removed ExamAssignmentController,
     * deleted in Phase 10/D-1).
     */
    public function authorize(): bool
    {
        // Authorize BEFORE validation runs (a FormRequest checks authorize()
        // first) so a non-owner is 403'd before any scoped `exists` probing —
        // delegates to AttemptPolicy@update, the same gate the controller uses.
        return $this->user()?->can('update', $this->route('attempt')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Never accept is_correct/score/attempt_id from the client — those
     * are grading fields (Phase 5) and the attempt id comes from the
     * route binding only (T-04-05, CWE-915).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $attempt = $this->route('attempt');

        return [
            'question_id' => [
                'required', 'integer',
                Rule::exists('questions', 'id')->where('exam_id', $attempt->exam_id),
            ],
            'selected_option_id' => [
                'nullable', 'integer',
                Rule::exists('options', 'id')->where('question_id', $this->input('question_id')),
            ],
            'answer_text' => ['nullable', 'string'],
        ];
    }
}

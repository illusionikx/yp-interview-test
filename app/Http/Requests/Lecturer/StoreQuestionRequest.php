<?php

namespace App\Http\Requests\Lecturer;

use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The D-06 draft-only mutation gate is RETIRED this phase (D-4),
     * applied consistently across all four editor mutations. Adding a
     * question to a published/attempted exam is now allowed; EDT-04's
     * warn-and-void flow (App\Services\AttemptVoider, wired in
     * ExamQuestionController::store()) replaces the old "can't touch it"
     * protection — the write voids the exam's attempts, after a warning,
     * rather than being refused.
     *
     * No new authorization gap: this codebase has no per-exam ownership
     * check anywhere — ownership is subject-level via `subject_user` — and
     * the `role:lecturer` middleware on the route group is what keeps
     * students out. Mirrors the deleted `AssignExamRequest`'s `return
     * true;` idiom (10-05).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Default `points` to 1 when omitted, since the schema itself
     * defaults `questions.points` to 1 (02-RESEARCH.md "Points
     * validation") — an explicit points<1 must still be rejected by
     * rules(), so the merge only fills a genuinely missing value.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('points') === null || $this->input('points') === '') {
            $this->merge(['points' => 1]);
        }

        // Reindex options to sequential 0..n-1 keys BEFORE validation so the
        // `correct_option` index the after() hook checks matches the
        // `->values()`-reindexed set the controller persists. Without this a
        // sparse-keyed payload (e.g. options[0], options[2], correct_option=2)
        // could pass validation yet save zero correct options.
        if (is_array($this->input('options'))) {
            $this->merge(['options' => array_values($this->input('options'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'body' => ['required', 'string'],
            // max:100 keeps a single answer's score within the answers.score
            // decimal(5,2) column (< 999.99) — without it a huge points value
            // throws inside AttemptGrader mid-finalize and strands the attempt
            // at in_progress (review HIGH-01). 100 is a generous per-question cap.
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'options' => ['required_if:type,mcq', 'array', 'min:2'],
            'options.*.body' => ['required_with:options', 'string'],
            'correct_option' => ['required_if:type,mcq', 'integer'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * Pattern 1 (02-RESEARCH.md): the radio-group UI already guarantees
     * "at most one" correct option is submitted; this hook only needs to
     * confirm a *valid* index was chosen — the entire "exactly one
     * correct" rule collapses to this single check plus rules()' min:2,
     * no boolean-counting Rule class needed (T-02-MCQ).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('type') !== QuestionType::Mcq->value) {
                    return;
                }

                $options = $this->input('options', []);
                $correct = $this->input('correct_option');

                if (! is_numeric($correct) || ! array_key_exists((int) $correct, $options)) {
                    $validator->errors()->add(
                        'correct_option',
                        __('Select which option is correct.')
                    );
                }
            },
        ];
    }
}

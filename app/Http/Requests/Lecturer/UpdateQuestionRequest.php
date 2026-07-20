<?php

namespace App\Http\Requests\Lecturer;

use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The D-06 draft-only mutation gate is RETIRED this phase (D-4),
     * mirrored from StoreQuestionRequest. Editing a question on a
     * published/attempted exam is now allowed; EDT-04's warn-and-void
     * flow (App\Services\AttemptVoider, wired in
     * ExamQuestionController::update()) replaces the old "can't touch it"
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
     * Default `points` to 1 when omitted, mirroring StoreQuestionRequest —
     * `questions.points` defaults to 1 at the DB layer (02-RESEARCH.md
     * "Points validation"); an explicit points<1 must still be rejected by
     * rules(), so the merge only fills a genuinely missing value.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('points') === null || $this->input('points') === '') {
            $this->merge(['points' => 1]);
        }

        // Reindex options to sequential keys before validation (mirrors
        // StoreQuestionRequest) so the validated `correct_option` index matches
        // the `->values()`-reindexed set the controller persists — prevents a
        // sparse payload from saving an MCQ with zero correct options.
        if (is_array($this->input('options'))) {
            $this->merge(['options' => array_values($this->input('options'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Identical rule set to StoreQuestionRequest — the exactly-one-correct
     * + >=2 options + points>=1 invariants must hold on edit too.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'body' => ['required', 'string'],
            // max:100 keeps a single answer's score within answers.score
            // decimal(5,2) — see StoreQuestionRequest (review HIGH-01).
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'options' => ['required_if:type,mcq', 'array', 'min:2'],
            'options.*.body' => ['required_with:options', 'string'],
            'correct_option' => ['required_if:type,mcq', 'integer'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * Identical to StoreQuestionRequest's after() hook (Pattern 1) — the
     * radio-group UI already guarantees "at most one" correct option is
     * submitted; this only confirms a *valid* index was chosen (T-02-MCQ).
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

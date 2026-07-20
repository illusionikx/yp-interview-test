<?php

namespace App\Http\Requests\Lecturer;

use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;

class GradeAnswerRequest extends FormRequest
{
    /**
     * The role:lecturer route group already gates access (D-04, no
     * per-lecturer ownership — matches the "any lecturer" Phase 2/3
     * precedent). Reject only if the route-bound {attempt}/{answer} pair
     * is mismatched, the target answer isn't actually open-text, or the
     * attempt hasn't reached a gradeable state — defense in depth against
     * a crafted URL (T-05-04/T-05-05, 05-RESEARCH.md Code Examples).
     */
    public function authorize(): bool
    {
        $attempt = $this->route('attempt');
        $answer = $this->route('answer');

        return $answer->attempt_id === $attempt->id
            && $answer->question->type === QuestionType::Open
            && in_array($attempt->status, ['submitted', 'graded'], true);
    }

    /**
     * A dynamic, server-computed bound read from the route-bound answer's
     * question — never a client-supplied max (T-05-04).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $points = $this->route('answer')->question->points;

        return [
            'score' => ['required', 'numeric', 'min:0', 'max:'.$points],
        ];
    }
}

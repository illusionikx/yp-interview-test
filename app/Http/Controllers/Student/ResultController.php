<?php

namespace App\Http\Controllers\Student;

use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use Illuminate\View\View;

class ResultController extends Controller
{
    /**
     * The gated student result (D-05/GRD-03/GRD-04). authorize() runs first
     * (IDOR gate, ownership-only — see AttemptPolicy::viewResult, NOT
     * view/update, so an already-graded result survives a later unpublish).
     * When the attempt isn't graded yet, NO score data is built or passed
     * into the view at all — this is a view-model contract, not a template
     * conditional (05-RESEARCH.md Pitfall 3). When graded, the breakdown
     * reveals only the student's own answer + correctness/score — the
     * correct option is never queried or rendered here (D-07).
     */
    public function show(Attempt $attempt): View
    {
        $this->authorize('viewResult', $attempt);

        $attempt->loadMissing('exam');

        if ($attempt->status !== 'graded') {
            return view('student.results.show', [
                'attempt' => $attempt,
                'awaiting' => true,
            ]);
        }

        $answers = $attempt->answers()->get()->keyBy('question_id');

        $breakdown = $attempt->exam->questions()->orderBy('position')->get()
            ->map(function ($question) use ($answers) {
                $answer = $answers->get($question->id);

                return [
                    'body' => $question->body,
                    'points' => $question->points,
                    'type' => $question->type->value,
                    'student_answer' => $question->type === QuestionType::Mcq
                        ? $answer?->selectedOption?->body
                        : $answer?->answer_text,
                    'is_correct' => $answer?->is_correct, // null for open-text, by design
                    'score_awarded' => $answer?->score ?? 0,
                ];
            })
            ->values();

        return view('student.results.show', [
            'attempt' => $attempt,
            'awaiting' => false,
            'breakdown' => $breakdown,
            'totalAwarded' => $attempt->score,
            'totalPossible' => $attempt->exam->questions()->sum('points'),
        ]);
    }
}

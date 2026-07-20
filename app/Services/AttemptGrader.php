<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Answer;
use App\Models\Attempt;

/**
 * MCQ auto-grading + submitted->graded completeness transition (D-01/D-02/
 * D-03/D-08, GRD-01/GRD-03). An explicit service, never an Eloquent model
 * event/observer on Answer (ARCHITECTURE.md/CLAUDE.md constraint) — grading
 * must stay a visible, single-call-site side effect, not a hidden one
 * re-triggered by an unrelated save.
 */
class AttemptGrader
{
    /**
     * Single entry point invoked exactly once, from inside
     * Attempt::lockAndFinalize()'s existing transaction, right after the
     * status flips to submitted (05-RESEARCH.md Pattern 1).
     */
    public function handleFinalized(Attempt $attempt): void
    {
        $this->gradeAutoGradable($attempt);
        $this->syncStatus($attempt);
    }

    /**
     * MCQ auto-grading (D-01, GRD-01). Only writes to Answer rows that
     * already exist — a question never touched by the student (no Answer
     * row) is left with no row, which correctly contributes 0 to the
     * SUM() total later without a placeholder write. Grades defensively
     * against a question with no option flagged is_correct (shouldn't
     * happen per EXM-02, but never crash — 05-RESEARCH.md Pitfall 2).
     */
    public function gradeAutoGradable(Attempt $attempt): void
    {
        $attempt->loadMissing(['exam.questions.options', 'answers']);

        foreach ($attempt->exam->questions->where('type', QuestionType::Mcq) as $question) {
            $answer = $attempt->answers->firstWhere('question_id', $question->id);

            if ($answer === null) {
                continue; // never touched — 0 contribution, no crash
            }

            $correctOptionId = $question->options->firstWhere('is_correct', true)?->id;
            $isCorrect = $correctOptionId !== null
                && $answer->selected_option_id !== null
                && $answer->selected_option_id === $correctOptionId;

            $answer->update([
                'is_correct' => $isCorrect,
                'score' => $isCorrect ? $question->points : 0,
            ]);
        }
    }

    /**
     * Completeness check + submitted -> graded transition (D-03/D-04).
     * Idempotent and safe to call repeatedly, including on a regrade of an
     * already-graded attempt (D-08 "recomputable if a grade changes") —
     * always recomputes the score sum whenever nothing is pending. Called
     * both from handleFinalized() (finalize time) and again, alone, from
     * the lecturer's grade-save action every time an open-text answer is
     * scored (05-RESEARCH.md Pattern 2).
     */
    public function syncStatus(Attempt $attempt): void
    {
        if (! in_array($attempt->status, ['submitted', 'graded'], true)) {
            return; // in_progress attempts are never touched here
        }

        $stillPending = Answer::query()
            ->where('attempt_id', $attempt->id)
            ->whereNull('score')
            ->whereHas('question', fn ($q) => $q->where('type', QuestionType::Open->value))
            ->exists();

        if ($stillPending) {
            return; // remains submitted — result withheld (D-03, UX pitfall)
        }

        $attempt->update([
            'status' => 'graded',
            'score' => $attempt->answers()->sum('score'),
        ]);
    }
}

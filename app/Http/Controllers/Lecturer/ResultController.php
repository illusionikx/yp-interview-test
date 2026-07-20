<?php

namespace App\Http\Controllers\Lecturer;

use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Exam;
use App\Services\AttemptVoider;
use Illuminate\View\View;

class ResultController extends Controller
{
    /**
     * The lecturer per-attempt drill-in / grading screen (D-06, GRD-02/
     * GRD-03). role:lecturer route-group middleware is the sole gate — no
     * per-lecturer ownership, matching the Phase 2/3 "any lecturer"
     * precedent (05-RESEARCH.md Anti-Patterns). Because this is the
     * LECTURER view (not the student-facing D-07 restriction), a wrong MCQ
     * answer's row MAY also surface the correct option's body as a
     * sanity-check line.
     */
    /**
     * The lecturer per-exam results index (D-06, GRD-05). role:lecturer
     * route-group middleware is the sole gate — no per-lecturer ownership,
     * matching show() above and the Phase 2/3 "any lecturer" precedent.
     */
    public function index(Exam $exam): View
    {
        $exam->loadMissing('subject');

        $attempts = $exam->attempts()
            ->with('user')
            ->get()
            ->sortBy(fn (Attempt $attempt) => $attempt->user->name)
            ->values();

        // GRD-06: grading-progress header. Reuses AttemptVoider::summarize()
        // — the ONE grouped COUNT(*) query already maintained for the
        // reset-submissions warning modal (D-2, CLS-07/EDT-04) — rather than
        // looping $attempts, so this page never adds a second, possibly
        // divergent, way to count graded/ungraded attempts.
        $summary = app(AttemptVoider::class)->summarize($exam);
        $progress = [
            'graded' => $summary['graded'],
            'needingGrading' => $summary['submittedUngraded'],
            'gradableTotal' => $summary['graded'] + $summary['submittedUngraded'],
        ];

        return view('lecturer.results.index', [
            'exam' => $exam,
            'attempts' => $attempts,
            'totalPossible' => $exam->questions()->sum('points'),
            'progress' => $progress,
        ]);
    }

    public function show(Exam $exam, Attempt $attempt): View
    {
        // Nested-binding integrity (Phase-2 lesson, ExamQuestionController):
        // {exam} and {attempt} bind independently, so a mismatched pair must
        // 404 rather than silently rendering another exam's attempt.
        abort_unless($attempt->exam_id === $exam->id, 404);

        $attempt->loadMissing(['exam.questions.options', 'answers.selectedOption', 'user']);

        $questions = $attempt->exam->questions->sortBy('position')->values();

        $breakdown = $questions->map(function ($question) use ($attempt) {
            $answer = $attempt->answers->firstWhere('question_id', $question->id);

            return [
                'question' => $question,
                'answer' => $answer,
                'correct_option' => $question->type === QuestionType::Mcq
                    ? $question->options->firstWhere('is_correct', true)
                    : null,
            ];
        });

        // Server-computed grading progress (D-03/D-04, 05-UI-SPEC.md
        // Interaction rule 5) — mirrors AttemptGrader::syncStatus()'s own
        // pending-count semantics exactly: N only counts open-text
        // questions with an EXISTING Answer row, never a raw count of all
        // open-text questions on the exam.
        $openTextQuestionIds = $questions->where('type', QuestionType::Open)->pluck('id');
        $openTextAnswers = $attempt->answers->whereIn('question_id', $openTextQuestionIds);
        $totalOpenText = $openTextAnswers->count();
        $gradedOpenText = $openTextAnswers->whereNotNull('score')->count();

        return view('lecturer.results.show', [
            'exam' => $attempt->exam,
            'attempt' => $attempt,
            'breakdown' => $breakdown,
            'totalOpenText' => $totalOpenText,
            'gradedOpenText' => $gradedOpenText,
            'totalPossible' => $questions->sum('points'),
        ]);
    }
}

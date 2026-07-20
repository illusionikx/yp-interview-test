<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * EDT-03/EDT-05 (12-05): authoring-time position swaps for questions and
 * their MCQ options, plus a one-time option shuffle. Every action here is
 * display-order only — is_correct is never touched, so unlike
 * ExamQuestionController's store/update/destroy these actions do NOT run
 * AttemptVoider and do NOT void attempts (see the plan's threat model
 * T-12-14 and the QuestionReorderTest "attempt not voided" case).
 *
 * Decision #8: move-up/down buttons only, never drag-and-drop.
 * Decision #2: shuffle is a one-shot authoring-time action — never
 * randomized again at any read/take path.
 */
class QuestionReorderController extends Controller
{
    /**
     * Swap the given question's position with its adjacent sibling
     * (direction up/down) within the same exam. A boundary move (first
     * question moving up, or last question moving down) is a no-op.
     */
    public function moveQuestion(Request $request, Exam $exam, Question $question): Response
    {
        // Nested-binding integrity (same idiom as ExamQuestionController) —
        // reject a question that does not belong to the URL exam.
        abort_unless($question->exam_id === $exam->id, 404);

        $validated = $request->validate([
            'direction' => 'required|in:up,down',
        ]);

        DB::transaction(function () use ($exam, $question, $validated) {
            // reorder() (not orderBy/orderByDesc) — Exam::questions() carries a
            // default orderBy('position') asc, which would otherwise dominate as
            // the primary sort and make an appended orderByDesc a no-op tiebreaker,
            // returning the TOPMOST sibling instead of the nearest one below (12-REVIEW CR-01).
            $sibling = $validated['direction'] === 'up'
                ? $exam->questions()->where('position', '<', $question->position)->reorder('position', 'desc')->first()
                : $exam->questions()->where('position', '>', $question->position)->reorder('position', 'asc')->first();

            if ($sibling === null) {
                // Boundary — nothing to swap with, no-op.
                return;
            }

            $questionPosition = $question->position;
            $siblingPosition = $sibling->position;

            $question->update(['position' => $siblingPosition]);
            $sibling->update(['position' => $questionPosition]);
        });

        // Issue #2: the client reorders optimistically and only needs an ack, so
        // an AJAX move gets an empty 204 (no wasteful full-page redirect+GET). A
        // no-JS submit still gets the redirect below. Feature tests don't set the
        // X-Requested-With header, so they continue to assert the redirect.
        if ($request->ajax()) {
            return response()->noContent();
        }

        return redirect()->to(route('lecturer.exams.show', $exam).'?tab=questions')->with('status', __('Question order updated.'));
    }

    /**
     * Swap the given option's position with its adjacent sibling
     * (direction up/down) within the same question. A boundary move is a
     * no-op.
     */
    public function moveOption(Request $request, Exam $exam, Question $question, Option $option): Response
    {
        abort_unless($question->exam_id === $exam->id, 404);
        abort_unless($option->question_id === $question->id, 404);

        $validated = $request->validate([
            'direction' => 'required|in:up,down',
        ]);

        DB::transaction(function () use ($question, $option, $validated) {
            // reorder() clears Question::options()' default orderBy('position') asc
            // so the direction sort is authoritative — see CR-01 note on moveQuestion above.
            $sibling = $validated['direction'] === 'up'
                ? $question->options()->where('position', '<', $option->position)->reorder('position', 'desc')->first()
                : $question->options()->where('position', '>', $option->position)->reorder('position', 'asc')->first();

            if ($sibling === null) {
                return;
            }

            $optionPosition = $option->position;
            $siblingPosition = $sibling->position;

            $option->update(['position' => $siblingPosition]);
            $sibling->update(['position' => $optionPosition]);
        });

        if ($request->ajax()) {
            return response()->noContent();
        }

        return redirect()->to(route('lecturer.exams.show', $exam).'?tab=questions')->with('status', __('Option order updated.'));
    }

    /**
     * One-time authoring shuffle (Decision #2): writes a random
     * permutation of 0..n-1 across the question's options' position
     * values. is_correct is untouched, so grading is unaffected — this is
     * NOT per-student runtime randomization (that is TAK-12, Phase 13).
     */
    public function shuffleOptions(Exam $exam, Question $question): RedirectResponse
    {
        abort_unless($question->exam_id === $exam->id, 404);

        DB::transaction(function () use ($question) {
            $options = $question->options()->get();
            $positions = range(0, $options->count() - 1);
            shuffle($positions);

            $options->values()->each(function (Option $option, int $i) use ($positions) {
                $option->update(['position' => $positions[$i]]);
            });
        });

        return redirect()->to(route('lecturer.exams.show', $exam).'?tab=questions')->with('status', __('Options shuffled.'));
    }
}

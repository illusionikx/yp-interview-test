<?php

namespace App\Http\Controllers\Lecturer;

use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\StoreQuestionRequest;
use App\Http\Requests\Lecturer\UpdateQuestionRequest;
use App\Models\Exam;
use App\Models\Question;
use App\Services\AttemptVoider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ExamQuestionController extends Controller
{
    /**
     * Store a newly created question (and, for MCQ, its options) on the
     * given exam.
     *
     * D-4/D-6 retired the draft-only gate (StoreQuestionRequest::authorize()
     * now returns true unconditionally) — this method is reachable on a
     * published, attempted exam. EDT-04/D-7: the pre-write attempt count
     * decides whether void() fires, and the write, option creation and
     * conditional void all happen inside the SAME atomic transaction (D-7)
     * — no second transaction is opened for the void.
     */
    public function store(StoreQuestionRequest $request, Exam $exam): RedirectResponse
    {
        $voider = app(AttemptVoider::class);

        // Computed BEFORE the write, same rationale as ExamController::update().
        $attemptCounts = $voider->summarize($exam);

        DB::transaction(function () use ($request, $exam, $voider, $attemptCounts) {
            $type = QuestionType::from($request->validated('type'));

            $question = $exam->questions()->create([
                'type' => $type,
                'body' => $request->validated('body'),
                'points' => $request->validated('points'),
                'position' => (int) $exam->questions()->max('position') + 1,
            ]);

            if ($type === QuestionType::Mcq) {
                $correct = (int) $request->validated('correct_option');

                $question->options()->createMany(
                    collect($request->validated('options'))
                        ->values()
                        ->map(fn (array $option, int $i) => [
                            'body' => $option['body'],
                            // is_correct is derived server-side from the
                            // validated correct_option index — never
                            // accepted directly from request input
                            // (T-02-MCQ, T-02-MA).
                            'is_correct' => $i === $correct,
                            'position' => $i,
                        ])
                        ->all()
                );
            }

            if ($attemptCounts['total'] > 0) {
                $voider->void($exam);
            }
        });

        $status = $attemptCounts['total'] > 0
            ? "Question added. {$attemptCounts['total']} affected attempt(s) were reset."
            : 'Question added.';

        return redirect()->route('lecturer.exams.show', $exam)->with('status', $status);
    }

    /**
     * One-click add (issue #3): create a blank default question (MCQ with two
     * starter options, first marked correct) at the end of the exam, with no
     * form payload — the lecturer then fills it in inline in the editor. Adding
     * a question to an attempted exam voids its attempts (D-6), the same
     * warn-and-void contract as store(): the pre-write count decides whether
     * void() fires, and the create + conditional void share one transaction.
     *
     * Redirects to the new question's anchor on the Questions tab so the editor
     * scrolls straight to it.
     */
    public function quickStore(Exam $exam): RedirectResponse
    {
        $voider = app(AttemptVoider::class);
        $attemptCounts = $voider->summarize($exam);

        $question = DB::transaction(function () use ($exam, $voider, $attemptCounts) {
            $question = $exam->questions()->create([
                'type' => QuestionType::Mcq,
                'body' => 'New question',
                'points' => 1,
                'position' => (int) $exam->questions()->max('position') + 1,
            ]);

            $question->options()->createMany([
                ['body' => 'Option 1', 'is_correct' => true, 'position' => 0],
                ['body' => 'Option 2', 'is_correct' => false, 'position' => 1],
            ]);

            if ($attemptCounts['total'] > 0) {
                $voider->void($exam);
            }

            return $question;
        });

        $status = $attemptCounts['total'] > 0
            ? "Question added. {$attemptCounts['total']} affected attempt(s) were reset. Fill it in below."
            : 'Question added. Fill it in below.';

        return redirect()
            ->to(route('lecturer.exams.show', $exam).'?tab=questions#question-'.$question->id)
            ->with('status', $status);
    }

    /**
     * Show the form for editing the specified question.
     *
     * D-4 retired the draft-only edit gate; this route is reachable on a
     * published, attempted exam. $attemptCounts lets plan 09's warning
     * modal read the exact counts the save is about to act on.
     */
    public function edit(Exam $exam, Question $question): View
    {
        // Nested-binding integrity: {exam} and {question} bind independently,
        // so a mismatched pair (e.g. another exam's question ID paired with
        // this exam's URL) must 404 — unrelated to the (retired) publish gate.
        abort_unless($question->exam_id === $exam->id, 404);

        $question->load('options');

        $attemptCounts = app(AttemptVoider::class)->summarize($exam);

        return view('lecturer.exams.questions.edit', compact('exam', 'question', 'attemptCounts'));
    }

    /**
     * Update the specified question (and, for MCQ, replace its options) in
     * storage.
     *
     * D-4 retired the draft-only gate (UpdateQuestionRequest::authorize()
     * now returns true unconditionally) — this method is reachable on a
     * published, attempted exam. Persistence is transactional (Pattern 2):
     * the question's scalar fields are updated, then its entire option
     * set is deleted and recreated from the validated payload. That
     * delete-and-recreate is NOT safe by itself once attempts can exist —
     * a graded Answer's option_id could point at a row that no longer
     * exists, desyncing already-graded scores. This is the same HIGH-02
     * concern plan 05 recorded as superseded: EDT-04's warn-and-void
     * (D-7) is the superseding mechanism — the conditional void() below,
     * in the SAME transaction as the option replacement, removes every
     * attempt/answer that could be desynced, after a warning, rather than
     * leaving them dangling.
     */
    public function update(UpdateQuestionRequest $request, Exam $exam, Question $question): Response
    {
        // Nested-binding integrity (see edit()): reject a question that does
        // not belong to the URL exam — unrelated to the (retired) publish
        // gate, this still prevents pairing another exam's question ID with
        // this exam's URL.
        abort_unless($question->exam_id === $exam->id, 404);

        $voider = app(AttemptVoider::class);

        // Computed BEFORE the write, same rationale as ExamController::update().
        $attemptCounts = $voider->summarize($exam);

        DB::transaction(function () use ($request, $exam, $question, $voider, $attemptCounts) {
            $type = QuestionType::from($request->validated('type'));

            $question->update([
                'type' => $type,
                'body' => $request->validated('body'),
                'points' => $request->validated('points'),
            ]);

            // Delete unconditionally first — also handles a question being
            // switched from mcq to open (Pattern 2).
            $question->options()->delete();

            if ($type === QuestionType::Mcq) {
                $correct = (int) $request->validated('correct_option');

                $question->options()->createMany(
                    collect($request->validated('options'))
                        ->values()
                        ->map(fn (array $option, int $i) => [
                            'body' => $option['body'],
                            // is_correct is derived server-side from the
                            // validated correct_option index — never
                            // accepted directly from request input
                            // (T-02-MCQ, T-02-MA).
                            'is_correct' => $i === $correct,
                            'position' => $i,
                        ])
                        ->all()
                );
            }

            if ($attemptCounts['total'] > 0) {
                $voider->void($exam);
            }
        });

        // Mirrors QuestionReorderController: an AJAX save only needs an ack, so
        // it gets an empty 204 instead of a full redirect+GET; a no-JS or
        // warn-and-void native submit still gets the redirect below. This
        // branch is only reachable for no-attempt saves — the attempted-exam
        // save submits natively via the confirm modal (no X-Requested-With).
        if ($request->ajax()) {
            return response()->noContent();
        }

        $status = $attemptCounts['total'] > 0
            ? "Question updated. {$attemptCounts['total']} affected attempt(s) were reset."
            : 'Question updated.';

        return redirect()->route('lecturer.exams.show', $exam)->with('status', $status);
    }

    /**
     * Remove the specified question from storage.
     *
     * destroy() has no Form Request, so its draft-only gate (D-06/EXM-05/
     * Pitfall 3) was historically enforced inline here — this is D-6's
     * fourth site, and it is RETIRED this phase along with the other
     * three (D-4). Deleting a question on a published/attempted exam is
     * now allowed, routed through the same EDT-04 warn-and-void flow as
     * store()/update(): the delete voids the exam's attempts, after a
     * warning, rather than being refused. Options cascade-delete at the
     * DB layer.
     */
    public function destroy(Exam $exam, Question $question): RedirectResponse
    {
        // Nested-binding integrity (see edit()): the question must belong to the
        // URL exam — unrelated to the (retired) publish gate, this still
        // prevents pairing another exam's question ID with this exam's URL.
        abort_unless($question->exam_id === $exam->id, 404);

        $voider = app(AttemptVoider::class);

        // Computed BEFORE the delete, same rationale as ExamController::update().
        $attemptCounts = $voider->summarize($exam);

        // destroy() had no transaction before this plan; D-7 requires the
        // delete and the conditional void to be ONE atomic unit, same as
        // the other three mutations.
        DB::transaction(function () use ($exam, $question, $voider, $attemptCounts) {
            $question->delete();

            if ($attemptCounts['total'] > 0) {
                $voider->void($exam);
            }
        });

        $status = $attemptCounts['total'] > 0
            ? "Question deleted. {$attemptCounts['total']} affected attempt(s) were reset."
            : 'Question deleted.';

        return redirect()->route('lecturer.exams.show', $exam)->with('status', $status);
    }
}

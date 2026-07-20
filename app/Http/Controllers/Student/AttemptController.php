<?php

namespace App\Http\Controllers\Student;

use App\Enums\QuestionType;
use App\Exceptions\AttemptVanishedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\AnswerRequest;
use App\Http\Requests\Student\SubmitAttemptRequest;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttemptController extends Controller
{
    /**
     * Start or resume the acting student's single attempt at $exam
     * (D-01/D-05, TAK-01/TAK-05).
     *
     * The Phase-1 DB unique(exam_id,user_id) constraint is the actual
     * race-proof backstop; firstOrCreate is the optimistic app-level
     * path, and the QueryException/1062 catch below handles the lost
     * race gracefully instead of erroring (04-RESEARCH.md Pattern 1).
     */
    public function store(Request $request, Exam $exam): RedirectResponse
    {
        /**
         * AVL-03: the availability gate — checked below and nowhere else.
         * It must NEVER be folded into Exam::scopeVisibleTo() or
         * ExamPolicy::takeable(), because either would retroactively hide
         * or 403 an exam a student is mid-attempt on.
         *
         * The $alreadyStarted guard is load-bearing, not an optimisation:
         * without it, a student resuming their own in-progress attempt
         * after the window has since closed would be wrongly refused —
         * precisely the AVL-04 violation 08-03 fixed, arriving through a
         * different door. This gate applies ONLY to the new-attempt branch
         * below; an already-started attempt stays resumable regardless of
         * window state.
         */
        $alreadyStarted = Attempt::where('exam_id', $exam->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        /**
         * CR-02: the takeable() gate delegates to Exam::visibleTo(), which
         * is enrollment-status-driven. Skip it once an attempt already
         * exists — otherwise a mid-attempt withdrawal or lecturer rejection
         * would 403 this "resume" POST the same instant it happens,
         * stranding the student's own in-progress attempt behind a gate
         * that (per AttemptPolicy's ownership-only contract) was never
         * meant to apply again post-start. Starting a NEW attempt still
         * requires takeable(); resuming an existing one does not.
         */
        if (! $alreadyStarted) {
            $this->authorize('takeable', $exam);
        }

        if (! $alreadyStarted && ! $exam->isAvailableNow()) {
            return redirect()->route('student.exams.show', $exam)
                ->with('error', $exam->availabilityState() === 'opening'
                    ? __('This exam is not available yet. It opens :date.', [
                        'date' => $exam->available_from?->format('M j, Y g:ia'),
                    ])
                    : __('This exam is no longer available. It closed :date.', [
                        'date' => $exam->available_until?->format('M j, Y g:ia'),
                    ]));
        }

        try {
            $attempt = DB::transaction(function () use ($exam, $request) {
                return Attempt::firstOrCreate(
                    ['exam_id' => $exam->id, 'user_id' => $request->user()->id],
                    ['started_at' => now(), 'status' => 'in_progress']
                );
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e; // not a duplicate-key violation — a real error
            }

            // Lost the race: another concurrent request already created the row.
            $attempt = Attempt::where('exam_id', $exam->id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
        }

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('student.exams.show', $exam)
                ->with('status', __('You have already submitted this exam.'));
        }

        return redirect()->route('student.attempts.show', $attempt);
    }

    /**
     * The take page (D-04/D-05/D-07). finalizeIfExpired() runs first —
     * a single chokepoint so any touch of a lapsed attempt closes it
     * before anything renders.
     */
    public function show(Request $request, Attempt $attempt): View|RedirectResponse
    {
        $this->authorize('view', $attempt);

        $attempt->loadMissing('exam');
        $attempt->finalizeIfExpired();

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('student.attempts.submitted', $attempt);
        }

        // Explicit column-whitelisted view-model (D-07/TAK-06) — is_correct
        // is NEVER selected here, never mind rendered. `points` is included
        // beyond the plan's literal column list because 04-UI-SPEC.md's
        // per-question meta line ("Question n of total · N points") needs
        // it and it carries no answer-key information (Rule 2).
        $questions = $attempt->exam->questions()
            ->orderBy('position')
            ->get(['id', 'exam_id', 'type', 'body', 'position', 'points'])
            ->map(fn ($question) => [
                'id' => $question->id,
                'type' => $question->type->value,
                'body' => $question->body,
                'points' => $question->points,
                'options' => $question->type === QuestionType::Mcq
                    ? $question->options()->orderBy('position')->get(['id', 'question_id', 'body'])
                    : collect(),
            ])
            ->values()
            ->all();

        // Rehydrate previously-saved answers (TAK-03 refresh/disconnect safety).
        $savedAnswers = $attempt->answers()->get()->keyBy('question_id');

        return view('student.attempts.show', [
            'attempt' => $attempt,
            'questions' => $questions,
            'savedAnswers' => $savedAnswers,
            'remainingSeconds' => $attempt->remainingSeconds(),
        ]);
    }

    /**
     * Autosave a single answer (TAK-03). Routes through the exact same
     * finalizeIfExpired() + status gate as every other attempt-touching
     * action (D-04/D-05, 04-RESEARCH.md Pitfall 1) — no code path may
     * write an Answer past the deadline. updateOrCreate is keyed on the
     * Phase-1 unique(attempt_id,question_id) so repeated autosaves for
     * the same question update one row (D-06). Only the two
     * answer-content fields are ever written — is_correct/score/
     * attempt_id are never accepted from the client (T-04-05, CWE-915).
     */
    public function answer(AnswerRequest $request, Attempt $attempt): JsonResponse
    {
        $this->authorize('update', $attempt);

        $attempt->loadMissing('exam');
        $attempt->finalizeIfExpired();

        $data = $request->validated();

        // Re-check status AND write the answer atomically under a row lock so a
        // racing auto-submit/finalize can't slip in between the check and the
        // write (review blocker: TOCTOU). Serializing on the attempt row also
        // makes the (attempt_id,question_id) upsert race-safe.
        $saved = DB::transaction(function () use ($attempt, $data) {
            // This read is INDEPENDENT of Attempt::lockAndFinalize() — it is
            // a second lock site in its own transaction, which is exactly
            // why guarding the model alone was insufficient (09-RESEARCH.md
            // Pitfall 5). A reader who fixes one site must know to check
            // the other.
            $locked = Attempt::whereKey($attempt->id)->lockForUpdate()->first();

            // Deliberately kept as a separate statement from the
            // status-check below, NOT collapsed into a single combined
            // "not locked OR not in_progress" condition.
            // A non-in_progress attempt is an ordinary expiry and must
            // keep returning false so the existing
            // 422 {'expired': true, 'message': 'This attempt has ended.'}
            // response below fires unchanged, while a vanished row must
            // produce the distinct `vanished` response. Merging them would
            // silently re-label every deleted attempt as "this attempt has
            // ended", which is the wrong message and defeats
            // 09-UI-SPEC.md's copywriting contract.
            if (! $locked) {
                throw new AttemptVanishedException;
            }

            if ($locked->status !== 'in_progress') {
                return false;
            }

            Answer::updateOrCreate(
                ['attempt_id' => $attempt->id, 'question_id' => $data['question_id']],
                collect($data)->only(['selected_option_id', 'answer_text'])->all()
            );

            return true;
        });

        if (! $saved) {
            // `expired: true` lets the client distinguish a genuine deadline
            // rejection (→ drive auto-submit) from an ordinary validation 422
            // (→ just show "save failed"). Status stays 422 to satisfy the
            // deadline-rejection test contract.
            return response()->json(['expired' => true, 'message' => __('This attempt has ended.')], 422);
        }

        return response()->json(['saved' => true, 'remaining_seconds' => $attempt->remainingSeconds()]);
    }

    /**
     * Finalize the attempt (TAK-04, T-04-02/T-04-03). authorize() runs
     * first (IDOR gate), then the entire finalize decision is delegated
     * to Attempt::finalize() — the same lock-then-check-then-update
     * primitive finalizeIfExpired() uses, so there is exactly one place
     * that ever writes status=submitted. A double-click or a second tab
     * racing this call is an idempotent no-op, not an error: the second
     * call's locked re-read sees status already !== 'in_progress' and
     * returns false without writing again.
     */
    public function submit(SubmitAttemptRequest $request, Attempt $attempt): RedirectResponse
    {
        $this->authorize('update', $attempt);

        $attempt->loadMissing('exam');
        $attempt->finalize();

        return redirect()->route('student.attempts.submitted', $attempt);
    }

    /**
     * Submitted confirmation — no score/grade rendered (Phase 5 boundary).
     */
    public function submitted(Request $request, Attempt $attempt): View
    {
        $this->authorize('view', $attempt);

        // Uphold the "every touch finalizes an expired attempt" invariant
        // (D-04/D-05): if a student's only post-deadline touch is this
        // confirmation URL, close the attempt here too rather than leaving it
        // in_progress forever (review medium finding).
        $attempt->loadMissing('exam');
        $attempt->finalizeIfExpired();

        return view('student.attempts.submitted', compact('attempt'));
    }
}

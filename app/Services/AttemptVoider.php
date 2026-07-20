<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;

/**
 * D-2's single voiding authority (INT-02/INT-03, CLS-07, EDT-04) — computes
 * the five warning-modal counts and hard-deletes an exam's attempts. An
 * explicit service, mirroring App\Services\AttemptGrader exactly: a plain
 * class with no interface and no constructor DI, resolved via
 * app(AttemptVoider::class), never an Eloquent `deleting`/`deleted` model
 * event/observer. CLAUDE.md forbids model observers for exactly this class
 * of destructive logic — an event would make an irreversible delete a
 * hidden side effect of "saving a row", indistinguishable from any other
 * save in the codebase. An explicit service called once at a lifecycle
 * transition (a lecturer resetting an exam, or saving an edit to an
 * attempted exam) is easier to reason about and to test.
 *
 * D-2 is a LOCKED decision, stated here plainly so nobody softens it
 * later: void() is a PERMANENT hard delete. `answers.attempt_id` is
 * `cascadeOnDelete()`, so deleting an attempt destroys its answers and any
 * graded scores with it. There is no undo and no audit trail — do not
 * reintroduce a "voided" marker column, an attempt-numbering scheme, or
 * soft deletes; that was the rejected alternative. The warning built from
 * summarize()'s counts is the ONLY safety mechanism between a lecturer and
 * that permanent loss.
 *
 * CLS-07 (plan 07) and EDT-04 (plan 08) both call this service directly.
 * Never duplicate the delete into a controller — this is the phase's ONLY
 * voiding authority.
 */
class AttemptVoider
{
    /**
     * The five UI-SPEC warning counts, computed from ONE grouped aggregate
     * query on `attempts.status` — the plain 3-value enum
     * (in_progress/submitted/graded) AttemptGrader::syncStatus() already
     * maintains as the sole graded/ungraded distinction. There is no
     * `graded_at` column and no second source of truth to drift from it.
     *
     * Deliberately does NOT add a relationship-existence completeness
     * check on the answers table: the UI-SPEC's prose describes
     * submittedUngraded/graded as
     * derived conditions ("status = submitted AND not fully graded"), but
     * that description is imprecise — status already IS the completeness
     * flag, flipped exactly once by AttemptGrader::syncStatus() at
     * grade-save time. Re-deriving it here would duplicate that logic and
     * could disagree with it, which is unacceptable for the number that
     * decides whether a lecturer destroys graded work (T-10-02). Do not
     * "fix" this back toward the UI-SPEC's wording.
     */
    public function summarize(Exam $exam): array
    {
        $counts = Attempt::where('exam_id', $exam->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $inProgress = (int) ($counts['in_progress'] ?? 0);
        $submittedUngraded = (int) ($counts['submitted'] ?? 0);
        $graded = (int) ($counts['graded'] ?? 0);

        return [
            'inProgress' => $inProgress,
            'submittedUngraded' => $submittedUngraded,
            'graded' => $graded,
            'notYetGraded' => $inProgress + $submittedUngraded,
            'total' => $inProgress + $submittedUngraded + $graded,
        ];
    }

    /**
     * Permanently deletes every attempt row for $exam, under the SAME row
     * lock App\Models\Attempt::lockAndFinalize() takes. Takes an Exam
     * model, never a raw ID and never request input, matching
     * AttemptGrader::handleFinalized(Attempt $attempt)'s convention and the
     * codebase's CWE-915 discipline (mass-assignment closed by
     * construction — see AnswerGradeController's "never $request->all()"
     * comment).
     *
     * InnoDB's DELETE already takes an implicit row lock, so the explicit
     * `lockForUpdate()` read below is not what makes this delete safe by
     * itself — it is what makes the SERIALIZATION POINT unambiguous and
     * matches lockAndFinalize()'s established idiom. Whichever
     * transaction's locked read commits first wins; the loser blocks,
     * re-reads inside its own transaction, finds its row gone, and throws
     * AttemptVanishedException (Phase 9, already built and tested). That
     * is D-2's "same row lock" requirement — a racing student
     * autosave/finalize gets a controlled failure, not a crash.
     *
     * Needs no null-guard of its own: it operates on a SET (whereIn), so
     * zero matching rows is a legitimate empty-set outcome (isEmpty() ->
     * 0), not a vanished-row error. The exception fires on the other side
     * of the race, in code that already exists — do not reimplement it
     * here.
     *
     * Answers need no manual cleanup — answers.attempt_id cascades at the
     * FK layer.
     */
    public function void(Exam $exam): int
    {
        return DB::transaction(function () use ($exam) {
            $ids = Attempt::where('exam_id', $exam->id)->lockForUpdate()->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            Attempt::whereIn('id', $ids)->delete();

            return $ids->count();
        });
    }
}

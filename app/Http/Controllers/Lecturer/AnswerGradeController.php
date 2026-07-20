<?php

namespace App\Http\Controllers\Lecturer;

use App\Enums\QuestionType;
use App\Exceptions\AttemptVanishedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\GradeAnswerRequest;
use App\Models\Answer;
use App\Models\Attempt;
use App\Services\AttemptGrader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AnswerGradeController extends Controller
{
    /**
     * Save a lecturer's open-text score and re-evaluate completeness
     * (D-04, GRD-02/GRD-03). GradeAnswerRequest already rejects a
     * mismatched attempt/answer pair, a non-open-text target, or a
     * not-yet-gradeable attempt state (T-05-04/T-05-05); the grade-save +
     * syncStatus() re-check are wrapped in the same DB::transaction +
     * Attempt::lockForUpdate() discipline Student\AttemptController@answer
     * already uses (T-05-06, 05-RESEARCH.md Pattern 3/Pitfall 4), so two
     * racing grade-saves for the last two pending answers of the same
     * attempt can't both skip the submitted->graded transition.
     *
     * The locked read below is null-guarded per T-09-01 (Phase 9's threat
     * model mandates a null-check after every locked re-read of an
     * `attempts` row) — this is the third and last such site in the repo;
     * after this, T-09-01 holds everywhere. Phase 10's exam reset (D-2's
     * hard delete) is what promotes this from an exotic race — a student
     * deleting their own account mid-grade — to a routine one: a lecturer
     * saves a grade while another lecturer resets that same exam.
     */
    public function update(GradeAnswerRequest $request, Attempt $attempt, Answer $answer): RedirectResponse|JsonResponse
    {
        DB::transaction(function () use ($request, $attempt, $answer) {
            $locked = Attempt::whereKey($attempt->id)->lockForUpdate()->first();

            // A vanished row (concurrent delete/reset committed between
            // route-model binding and this locked read) is NOT "already
            // finalized by a racing request" — that case doesn't apply
            // here at all, since this action never flips status itself.
            // A null $locked cannot be synced or graded, so the only
            // honest outcome is a typed failure — a silent no-op would
            // never work here — and never writing the score against a
            // row that no longer exists. Guard BEFORE the write,
            // mirroring Attempt::lockAndFinalize()'s shape.
            if (! $locked) {
                throw new AttemptVanishedException;
            }

            // Explicit single-key write — never $request->all() (T-05-04, CWE-915).
            $answer->update(['score' => $request->validated('score')]);

            app(AttemptGrader::class)->syncStatus($locked);
        });

        // Fresh state after the commit — the grade save may have flipped the
        // attempt submitted->graded, and the open-text progress counts change.
        $attempt->refresh();

        // AJAX save (grading view fetch, X-Requested-With) gets the updated
        // slice as JSON so the page can patch the row + header in place
        // without a full reload; a plain form POST still redirects (the view
        // works with JS off — progressive enhancement).
        if ($request->expectsJson()) {
            $format = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') ?: '0';

            $openTextAnswers = $attempt->answers()
                ->whereHas('question', fn ($q) => $q->where('type', QuestionType::Open))
                ->get();

            return response()->json([
                'status' => $attempt->status,
                'answerScore' => $format($answer->fresh()->score),
                'gradedOpenText' => $openTextAnswers->whereNotNull('score')->count(),
                'totalOpenText' => $openTextAnswers->count(),
                'score' => $format($attempt->score),
                'message' => __('Grade saved.'),
            ]);
        }

        return redirect()
            ->route('lecturer.results.show', [$attempt->exam_id, $attempt])
            ->with('status', __('Grade saved.'));
    }
}

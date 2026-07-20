<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * INT-01 — raised when a locked re-read of an `attempts` row returns null:
 * the row was deleted by a concurrent request between this request's
 * route-model binding and its `lockForUpdate()` read (threat T-09-01, a
 * TOCTOU race). Two independent call sites can hit this — see
 * App\Models\Attempt::lockAndFinalize() and
 * App\Http\Controllers\Student\AttemptController::answer().
 *
 * It exists as a distinct type rather than a `return false` because
 * `false` already means "already finalized by a racing request — an
 * idempotent no-op", and the two need different user-facing outcomes:
 * "here's your result" vs. "that attempt is gone". Conflating them is
 * 09-RESEARCH.md Open Question 1; this is its resolution (Assumption A1,
 * research's own recommendation).
 *
 * Today nothing deletes an in-progress attempt. Phase 10's attempt-reset
 * is the first path that will. The guard lands first, deliberately.
 *
 * This exception self-renders (Laravel calls `render(Request $request)`
 * on the exception instance if one exists, before consulting the global
 * handler) rather than being registered via `bootstrap/app.php`'s
 * `->withExceptions()`. Keeping the whole INT-01 contract — when it
 * fires, and what the user sees — in one readable file avoids editing
 * `bootstrap/app.php`, which this phase otherwise has no reason to touch.
 *
 * Phase 10 adds a third call site outside the student attempt flow:
 * AnswerGradeController::update() (D-5, T-09-01) — a lecturer-facing form
 * PATCH reached when a lecturer saves a grade while another lecturer's
 * exam reset (D-2's hard delete) has just removed the same attempt row.
 * That site falls into this class's existing non-JSON redirect branch,
 * which targets `student.exams.index` — a route gated behind
 * `role:student` (`routes/student.php`). A lecturer sent there is
 * immediately 403'd, stranded on a page telling them to "return to your
 * exam list", which is a dead end, not a guard. (09-RESEARCH.md's Pitfall
 * 3 claimed no change here was needed; that claim was wrong — verified by
 * direct read of both files.) The lecturer-route branch below fixes it,
 * redirecting to a page a lecturer can actually reach.
 */
class AttemptVanishedException extends \RuntimeException
{
    /**
     * 09-UI-SPEC.md's Copywriting Contract value, verbatim. Exposed as a
     * constant so tests and any future caller reference one source rather
     * than re-typing the sentence.
     */
    public const MESSAGE = 'This exam attempt is no longer available. Please return to your exam list.';

    /**
     * Lecturer-facing copy for Site 3 (AnswerGradeController::update()).
     * Not scoped by 10-UI-SPEC.md (it covers only the modals/toasts for
     * CLS-07/EDT-04), so this wording is Claude's discretion — it follows
     * the UI-SPEC's established voice: name the act, state the
     * permanence, no hedging. Exposed as a constant for the same reason
     * MESSAGE is one.
     */
    public const LECTURER_MESSAGE = 'That attempt was removed while you were grading it. Its answers and scores are gone.';

    public function render(Request $request): Response
    {
        // `expectsJson()` covers the general case (real browser autosave
        // traffic goes through window.axios, which bootstrap.js configures
        // to send X-Requested-With: XMLHttpRequest — see resources/js/
        // bootstrap.js). The explicit `routeIs()` check additionally
        // guarantees JSON for student.attempts.answer regardless of
        // request headers: that route's pre-existing sibling response
        // (AttemptController::answer()'s `422 {'expired': true, ...}` at
        // its non-in_progress short-circuit) already returns JSON
        // unconditionally, never branching on Accept headers — this keeps
        // the vanished-row response consistent with that established,
        // always-JSON contract for the same endpoint (and is what the
        // AttemptNullGuardTest autosave test, which posts without special
        // headers, exercises).
        if ($request->expectsJson() || $request->routeIs('student.attempts.answer')) {
            // Both keys are load-bearing. `expired` is retained so the
            // already-shipped client-side autosave handler — which
            // branches on `expired` to drive auto-submit — keeps working
            // unchanged against this new case (09-RESEARCH.md Assumption
            // A2). `vanished` is the added discriminator so Phase 10 can
            // give the reset case its own treatment without guessing.
            return response()->json([
                'expired' => true,
                'vanished' => true,
                'message' => __(self::MESSAGE),
            ], 422);
        }

        // Site 3 (D-5): a lecturer-facing form PATCH. Must come before the
        // student redirect below — that target is `role:student`-gated
        // (routes/student.php), so reusing it here would 403 the lecturer
        // instead of guarding them. `error`, not `status`: this reports
        // destroyed work, and `<x-toast>`'s error variant persists until
        // manually dismissed rather than auto-dismissing (`session('success')`
        // has zero call sites in this codebase; do not introduce it).
        if ($request->routeIs('lecturer.*')) {
            return redirect()->route('lecturer.exams.index')->with('error', __(self::LECTURER_MESSAGE));
        }

        // The `error` flash key is deliberate: it is the key the
        // `<x-toast>` error variant renders (plans 09-03 / 09-07), and
        // per 09-UI-SPEC.md the error variant persists until manually
        // dismissed rather than auto-dismissing — correct for a message
        // the student must act on.
        return redirect()->route('student.exams.index')->with('error', __(self::MESSAGE));
    }
}

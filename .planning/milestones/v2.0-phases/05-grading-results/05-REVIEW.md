---
phase: 05-grading-results
reviewed: 2026-07-16T00:00:00Z
depth: deep
files_reviewed: 17
files_reviewed_list:
  - app/Services/AttemptGrader.php
  - app/Models/Attempt.php
  - app/Policies/AttemptPolicy.php
  - app/Http/Requests/Lecturer/GradeAnswerRequest.php
  - app/Http/Controllers/Student/ResultController.php
  - app/Http/Controllers/Lecturer/ResultController.php
  - app/Http/Controllers/Lecturer/AnswerGradeController.php
  - app/Http/Controllers/Student/AttemptController.php
  - resources/views/student/results/show.blade.php
  - resources/views/lecturer/results/index.blade.php
  - resources/views/lecturer/results/show.blade.php
  - database/factories/AnswerFactory.php
  - database/factories/AttemptFactory.php
  - database/migrations/2026_07_15_100009_create_attempts_table.php
  - database/migrations/2026_07_15_100010_create_answers_table.php
  - routes/lecturer.php
  - routes/student.php
findings:
  blocker: 0
  high: 2
  medium: 2
  low: 3
  total: 7
status: issues_found
---

# Phase 5: Code Review Report — Grading & Results

**Reviewed:** 2026-07-16
**Depth:** deep (cross-file, transaction/lock tracing, schema-vs-input-bound tracing)
**Files Reviewed:** 17
**Status:** issues_found

## Summary

This is a well-engineered phase. The five hard-focus areas requested — auto-grading correctness, the submitted→graded gate, finalize-hook concurrency, ownership-only `viewResult`, and answer-key leakage — are **all correctly implemented and match their documented design decisions (D-01..D-08)**. No blocker was found in any of those five areas; the two HIGH findings below are grading-correctness *edge cases* that live outside the five hard-focus areas' happy paths (schema-vs-validation-bound mismatch, and post-grading exam mutation), not defects in the core grading/gating/concurrency/leak logic itself.

**Confirmed correct (evidence-backed, not just "looks fine"):**

1. **MCQ auto-grading** (`AttemptGrader::gradeAutoGradable`) — single-select correctness comparison is right (`selected_option_id === firstWhere('is_correct', true)?->id`), unanswered MCQ (no `Answer` row) is skipped and correctly contributes 0 via `SUM()`, and a question with **no** `is_correct=true` option is handled defensively (`$correctOptionId !== null` short-circuits `$isCorrect` to `false`) rather than crashing on a null comparison. Verified by `AttemptGraderTest::test_submitting_auto_grades_every_mcq_answer`, which explicitly constructs a no-correct-option MCQ and asserts no crash + `score=0`.
2. **submitted→graded gate** (`AttemptGrader::syncStatus`) — the "pending" query correctly scopes to `answers.score IS NULL` joined to `questions.type = open`, so MCQ answers (always non-null score after `gradeAutoGradable`) never falsely block the transition. Recomputes `Σ answers.score` every time nothing is pending (not just on first transition), satisfying D-08's "recomputable if a grade changes." A skipped open-text question (no `Answer` row at all) silently resolves to 0 and does **not** block the transition forever — this is an explicitly documented, deliberate design choice (05-RESEARCH.md "Alternatives Considered" row A1), not an oversight, and it's internally consistent between `AttemptGrader::syncStatus`, `AttemptGrader::gradeAutoGradable`'s "no row = 0" comment, and `Lecturer\ResultController@show`'s progress-bar denominator (`$totalOpenText` also only counts existing rows).
3. **Finalize-hook concurrency** — `AttemptGrader::handleFinalized()` is invoked only inside the `if ($locked->status === 'in_progress' && $guard($locked))` branch of `Attempt::lockAndFinalize()`, which by construction can only be entered once per attempt (status transitions are one-directional), so it is exactly-once/idempotent. All of its writes (status flip, MCQ grades, possible `graded` transition) happen inside the same `DB::transaction` holding `lockForUpdate()` on the `attempts` row, so they're atomic with the status flip — the Phase-4 idempotency guarantee is preserved, not broken. `Student\AttemptController::answer()` and `Lecturer\AnswerGradeController::update()` both acquire the *same* `attempts` row lock before touching `answers`, so a racing autosave/grade-save is always serialized behind (or ahead of) a finalize on that same attempt — verified by tracing the lock-acquisition order in both call sites (attempt-row lock always precedes answer writes in every code path), which rules out a deadlock cycle. The "two racing grade-saves for the last two pending answers" scenario documented in `AnswerGradeController`'s docblock was traced through and holds: each transaction's `syncStatus()` re-check happens only after acquiring the attempt lock, so the second racing request always sees the first's committed answer.
4. **`viewResult` / IDOR** — `AttemptPolicy::viewResult()` is ownership-only (`$attempt->user_id === $user->id`), deliberately not delegating to `ownAndTakeable()`/`Exam::visibleTo()`, so a graded result survives a later unpublish/reassignment (test: `test_result_visible_after_exam_unpublished`). Cross-student IDOR is blocked and tested (`test_cannot_view_another_students_result`). `Lecturer\ResultController@show` correctly 404s a mismatched `{exam}/{attempt}` pair (`abort_unless($attempt->exam_id === $exam->id, 404)`), and `GradeAnswerRequest::authorize()` closes the same nested-binding gap for `{attempt}/{answer}` (`$answer->attempt_id === $attempt->id`) since the route is a flat, non-scoped binding that Laravel does **not** auto-scope.
5. **Answer-key leakage (D-07)** — traced the student result view-model end to end: `Student\ResultController::show()` never touches `Option`/`is_correct` on the *question* side, only the student's own `selectedOption` and `is_correct`/`score` on their own `Answer`. `resources/views/student/results/show.blade.php` never renders a correct-option body. Verified by the passing `test_breakdown_never_exposes_the_correct_option` test. The lecturer view (`lecturer/results/show.blade.php:90-92`) does render the correct option's body for a wrong MCQ answer — this is explicit, documented, role:lecturer-gated, D-07-compliant behavior (D-07 only restricts the *student* view), not a leak.
6. **`GradeAnswerRequest`** — the `max` bound is server-computed from the route-bound `Answer→Question` (`$this->route('answer')->question->points`), never client-supplied. Only `score` is in `rules()`; the controller writes `['score' => $request->validated('score')]` explicitly (never `$request->all()`), so `is_correct`/`status`/`attempt_id` can never be client-set. `authorize()` rejects a non-open-text target and a not-yet-`submitted`/`graded` attempt, confirmed by `test_grading_an_mcq_answer_is_rejected`.

## Warnings/Issues

### HIGH-01: `questions.points` has no upper bound, but `answers.score`/`attempts.score` are fixed-width DECIMAL columns — a legitimate large point value crashes the finalize transaction

**File:** `app/Services/AttemptGrader.php:53-56`, `database/migrations/2026_07_15_100010_create_answers_table.php:23`, `database/migrations/2026_07_15_100009_create_attempts_table.php:21`, `app/Http/Requests/Lecturer/StoreQuestionRequest.php:57`, `app/Http/Requests/Lecturer/UpdateQuestionRequest.php:59`

**Issue:** `questions.points` validation is `['required', 'integer', 'min:1']` — there is no `max`. `answers.score` is `decimal(5,2)` (max representable value `999.99`) and `attempts.score` is `decimal(6,2)` (max `9999.99`). `config/database.php` has `'strict' => true` for the mysql connection (confirmed lines 58/78), so MySQL runs in strict SQL mode. If a lecturer sets a question's `points` to, say, `1000` (a plausible value for a weighted-scale exam — nothing in the UI or validation prevents it) and a student answers it correctly, `AttemptGrader::gradeAutoGradable()` executes:

```php
$answer->update([
    'is_correct' => $isCorrect,
    'score' => $isCorrect ? $question->points : 0,   // 1000, out of range for decimal(5,2)
]);
```

Under strict mode this throws a `QueryException` ("Out of range value for column 'score'") **inside** the same `DB::transaction` that also flips `attempts.status` to `submitted` (`Attempt::lockAndFinalize()`). The exception propagates uncaught out of `lockAndFinalize()`, rolling back the *entire* finalize — the status flip is undone along with the grade write. The attempt is left permanently `in_progress` past its deadline: every subsequent touch (`finalizeIfExpired()`, another `submit()`) re-enters the same code path and hits the identical overflow again, so the attempt can never finalize through normal use. This is a genuine grading-correctness/availability defect reachable via ordinary, permitted lecturer input (no scripting/curl needed), not a synthetic edge case.

**Fix:** Add a `max` bound to the points rule that respects the schema's precision, e.g.:
```php
// StoreQuestionRequest::rules() / UpdateQuestionRequest::rules()
'points' => ['required', 'integer', 'min:1', 'max:999'],
```
and/or widen the DB columns if larger point values are a real product requirement (`decimal(7,2)` on `answers.score`, matching `attempts.score`'s headroom accordingly), plus a defensive clamp/validation inside `AttemptGrader` so a future schema/validation drift fails loudly at write time rather than crashing the shared finalize transaction.

### HIGH-02: Post-grading exam/question edits are permitted and silently desynchronize the result views from the frozen grade

**File:** `app/Http/Controllers/Student/ResultController.php:37-59`, `app/Http/Controllers/Lecturer/ResultController.php:49-63` (root cause: `app/Http/Controllers/Lecturer/ExamController.php:119-124`, `app/Http/Controllers/Lecturer/ExamQuestionController.php:92-133` — outside the listed Phase-5 file set but directly responsible for the Phase-5 symptom)

**Issue:** `Exam::unpublish()` carries this comment:
```php
/**
 * Unpublish the specified exam back to draft.
 *
 * Reversible (D-06) — no attempts exist against an exam until
 * Phase 4, so returning to draft is always safe here.
 */
public function unpublish(Exam $exam): RedirectResponse
{
    $exam->update(['is_published' => false]);
    ...
```
This comment was accurate in Phase 3 but is now **stale/false**: Phase 4 added `attempts`, and this phase (5) adds grading against them. `unpublish()` and `UpdateQuestionRequest`/`ExamQuestionController::update()`/`destroy()` gate purely on `!$exam->is_published` — none of them check whether `Attempt` rows already exist for the exam. A lecturer can therefore: unpublish a live/graded exam → edit a question's `points`, edit which option `is_correct`, or delete the question entirely (which cascade-deletes every student's `Answer` row for it via `question_id->cascadeOnDelete()`) → republish (or not — `viewResult` doesn't require the exam to still be published).

Both `Student\ResultController::show()` (`$attempt->exam->questions()->orderBy('position')->get()`, `$attempt->exam->questions()->sum('points')`) and `Lecturer\ResultController::show()` (`$questions = $attempt->exam->questions->sortBy('position')`, `$correct_option = $question->options->firstWhere('is_correct', true)`) re-query the exam's **current, live** question/option state on every view, while `attempts.score` and each `answers.is_correct`/`answers.score` are **frozen** at the moment `AttemptGrader` last ran. After a post-grading edit, the result page can display:
- a `totalPossible` (live `Σ points`) that no longer matches the denominator implied by the frozen `attempts.score`,
- a per-question `points` value inconsistent with the `score_awarded` that was actually computed against the old point value,
- a correct-option body (lecturer view) or an `is_correct` flag (student view, frozen) that no longer agree with each other if the correct option was changed after grading,
- a breakdown missing rows entirely for any question deleted post-grading, with that student's now-orphaned `Answer` silently cascade-deleted — no error, no re-grade prompt, no audit trail.

This is a real, currently-untested gap (no phase-5 or phase-2/3 test exercises "edit/delete a question that already has graded attempts"), and it's a plausible real-world lecturer action (fixing a typo'd correct answer or rebalancing points after an exam closes).

**Fix:** Either (a) block `unpublish()`/question edit/delete when `Attempt` rows already exist for the exam (`abort_if($exam->attempts()->exists(), 403)` alongside the existing `is_published` gate), or (b) if post-grading correction must remain possible, snapshot the graded values instead of live-querying: store the awarded point value and/or a copy of the correct option text on the `Answer` row at grading time (or explicitly re-run `AttemptGrader` for all affected attempts on question edit) so the result views always render what was actually graded, not what the exam currently looks like.

### MEDIUM-01: Lecturer regrade validation errors are rendered inside a collapsed accordion, effectively invisible

**File:** `resources/views/lecturer/results/show.blade.php:101-135`

**Issue:**
```blade
<div class="mt-3" x-data="{ editing: {{ $answer->score === null ? 'true' : 'false' }} }">
    <template x-if="!editing">
        <div> ... <button @click="editing = true">Edit</button> </div>
    </template>
    <div x-show="editing">
        <form ...>
            <input ... value="{{ old('answer_id') == $answer->id ? old('score') : $answer->score }}">
            @if (old('answer_id') == $answer->id)
                <x-input-error :messages="$errors->get('score')" class="mt-1" />
            @endif
            ...
```
The Alpine `editing` initial state is derived **only** from `$answer->score === null`. Consider a regrade: the answer already has a score (e.g. `3`), the lecturer clicks "Edit," changes it to an out-of-range value (e.g. `999`), and submits. `GradeAnswerRequest` rejects it (422/redirect-with-errors), the DB write never happens, so on re-render `$answer->score` is **still** `3` (unchanged, non-null) — meaning `editing` initializes to `false` again. The `x-show="editing"` block containing both the corrected input value (`old('score')`) and the `<x-input-error>` message is collapsed by default. The lecturer sees only the read-only "3 / 5 pts · Edit" summary with no visible indication that their last submission was rejected — they have to know to click "Edit" again to discover the error and their previously-typed (now correctly repopulated) value.

This only manifests on a **regrade** of an already-scored answer; the first-time-grading path (`score === null`) is unaffected since `editing` correctly starts `true`.

**Fix:** Include the error state in the initial `editing` expression, e.g.:
```blade
<div class="mt-3" x-data="{ editing: {{ $answer->score === null || ($errors->has('score') && old('answer_id') == $answer->id) ? 'true' : 'false' }} }">
```

### MEDIUM-02: `AnswerGradeController::update()` does not lock/re-fetch the target `Answer` row before writing

**File:** `app/Http/Controllers/Lecturer/AnswerGradeController.php:28-35`

**Issue:** `$answer` is resolved via route-model-binding *before* the transaction begins and is used for the write:
```php
DB::transaction(function () use ($request, $attempt, $answer) {
    $locked = Attempt::whereKey($attempt->id)->lockForUpdate()->first();
    $answer->update(['score' => $request->validated('score')]);
    app(AttemptGrader::class)->syncStatus($locked);
});
```
Correctness of `score` itself is fine (the write is an unconditional `UPDATE ... SET score = ? WHERE id = ?`, not read-modify-write, and the shared `attempts` row lock does serialize two concurrent grade-saves for *different* answers on the same attempt, as documented and verified above). However, two concurrent grade-saves for the **same** `answer` (e.g. a lecturer double-submitting, or two tabs) are not individually locked — the second transaction's `$answer->update()` will simply overwrite the first's value once it acquires the (shared) attempt lock, "last write wins," with no conflict detection. Low practical impact (single-writer-per-answer is the expected usage, and the shared attempt lock does prevent the more consequential status-transition race), but inconsistent with the row-locking discipline used everywhere else in this phase.

**Fix:** For full parity with the rest of the phase's locking discipline, re-fetch `$answer` inside the transaction (optionally with `lockForUpdate()`) rather than relying on the route-bound instance:
```php
$answer = Answer::whereKey($answer->id)->lockForUpdate()->first();
$answer->update(['score' => $request->validated('score')]);
```

## Info

### LOW-01: No test coverage for a skipped (no-row) open-text answer

**File:** `tests/Feature/Grading/AttemptGraderTest.php`

**Issue:** The deliberate "no Answer row for an open-text question → silently contributes 0, doesn't block `graded`" behavior (05-RESEARCH.md A1) is exercised for MCQ (`$untouchedQuestion` in `test_submitting_auto_grades_every_mcq_answer`) but never for open-text. `test_open_text_exam_stays_submitted_until_graded` always creates an `Answer::factory()->openText()` row.

**Fix:** Add a case where an open-text question has no `Answer` row at all and assert the attempt still transitions straight to `graded` with that question contributing 0, and separately assert the lecturer grading UI shows no editable form for it (see `lecturer/results/show.blade.php`'s `@if ($answer)`-wrapped grading form) — this is the concrete manifestation of the design decision and deserves a pinned regression test.

### LOW-02: No regression test for the schema/validation point-value mismatch (HIGH-01)

**File:** `tests/Feature/Grading/AttemptGraderTest.php`

**Issue:** Related to HIGH-01 — there is no test asserting that grading a correctly-answered MCQ with a very large `points` value degrades gracefully (or is rejected earlier) rather than throwing.

**Fix:** Once HIGH-01 is fixed (validation `max` and/or wider columns), add a boundary test at the new limit.

### LOW-03: HTML `step="1"` on the lecturer score input diverges from the server's `numeric` (decimal-accepting) validation

**File:** `resources/views/lecturer/results/show.blade.php:119-120`

**Issue:** The score `<input type="number" ... step="1">` only lets a mouse-driven UI submit whole numbers, but `GradeAnswerRequest::rules()` accepts any numeric value up to `points` (e.g. `4.5`), and `Answer::score` is cast `decimal:2`. This is not a bug — it matches 05-RESEARCH.md's explicit accepted-risk note (A3: relying on `decimal:2`'s silent rounding rather than a stricter `decimal:0,2` rule is "a UX nicety, not a correctness gap") — but the client-side `step="1"` is inconsistent with that decision: a lecturer typing a fractional score directly (bypassing the spinner) can submit it and it will silently round to 2dp, which is fine, but the UI's `step` attribute implies whole-number-only input, which isn't actually enforced.

**Fix:** Either set `step="0.01"` to match what the server actually accepts, or, if whole-number-only scoring is the intended product behavior, add a matching `integer` (or `decimal:0`-equivalent) server-side rule to `GradeAnswerRequest` so client and server agree.

---

_Reviewed: 2026-07-16_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: deep_

---

## Orchestrator Resolution (2026-07-16)

Happy-path grading correctness, the submitted→graded gate, finalize-hook concurrency, ownership-only `viewResult`/IDOR, and no-answer-key-leak were all confirmed airtight (verifier 5/5 + review). Both HIGH integrity gaps fixed in the review-fix commit with a 4-test regression suite; full suite 175/435 green.

| Finding | Severity | Action |
|---------|----------|--------|
| `questions.points` uncapped → a >999-point question overflows `answers.score` decimal(5,2) and crashes the grader mid-finalize, stranding the attempt at `in_progress` | **high** | **FIXED** — `points` capped `max:100` in Store/UpdateQuestionRequest. Regression: over-cap rejected, at-cap accepted. |
| Exam can be unpublished→edited after it has attempts → grade/breakdown desync (stale Phase-3 assumption) | **high** | **FIXED** — `ExamController::unpublish()` refuses once `attempts()->exists()`; stale comment corrected. Regression: attempted exam stays locked, unattempted still unpublishes. |
| Alpine hides regrade validation errors behind a collapsed accordion (grading UX) | medium | **DEFERRED** — grading still functions (server rejects invalid scores; client input has a max); a lecturer would need to expand to see the message. UX polish, not correctness — can refine in a follow-up. |
| Missing row-lock on the target `Answer` in `AnswerGradeController` | medium | **DEFERRED (accepted)** — the shared attempt-row `lockForUpdate` already serializes grade writes for an attempt (reviewer noted "low practical impact"). |
| Test-coverage gaps; minor client/server validation mismatch | low | **DEFERRED** — no correctness impact on verified paths. |

No unresolved blockers or highs.

---
phase: 05-grading-results
plan: 02
subsystem: grading
tags: [laravel, eloquent, mcq-grading, policy, blade]

# Dependency graph
requires:
  - phase: 05-grading-results
    plan: 01
    provides: AnswerFactory openText()/mcqCorrect()/mcqIncorrect() states, AttemptFactory graded() state, AttemptGraderTest/Student ResultTest RED contracts
provides:
  - App\Services\AttemptGrader (handleFinalized/gradeAutoGradable/syncStatus)
  - Attempt::lockAndFinalize() hook (grades + evaluates completeness inside the existing transaction/row lock)
  - AttemptPolicy::viewResult() (ownership-only)
  - Student\ResultController@show + student.attempts.result route
  - resources/views/student/results/show.blade.php (awaiting/graded gated states)
affects: [05-03-lecturer-grading, 05-04-results-views]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Service-called-from-Model hook: Attempt::lockAndFinalize() calls app(AttemptGrader::class)->handleFinalized($locked) inside its existing DB transaction/row lock, not a model event"
    - "View-model gating: controller never builds/passes score data when status !== graded (no template @if fallback)"
    - "Ownership-only Policy method distinct from the take-flow's takeable-based view()/update()"

key-files:
  created:
    - app/Services/AttemptGrader.php
    - app/Http/Controllers/Student/ResultController.php
    - resources/views/student/results/show.blade.php
  modified:
    - app/Models/Attempt.php
    - app/Policies/AttemptPolicy.php
    - routes/student.php
    - tests/Feature/Grading/AttemptGraderTest.php
    - tests/Feature/Student/AttemptSubmitTest.php
    - tests/Feature/Student/AttemptAnswerTest.php
    - tests/Feature/Student/AttemptShowTest.php
    - tests/Feature/Student/Phase4ReviewFixesTest.php

key-decisions:
  - "AttemptGrader::handleFinalized() is called from inside Attempt::lockAndFinalize()'s existing finalized branch — same transaction, same row lock, exactly once — covering both finalize() (manual submit) and finalizeIfExpired() (lazy expiry) with zero controller changes (D-02)."
  - "AttemptPolicy::viewResult() is ownership-only ($attempt->user_id === $user->id), deliberately NOT derived from Exam::visibleTo() like view()/update() — a graded result must survive a later exam unpublish/reassign (D-05, Pitfall 1)."
  - "Student\\ResultController@show never builds score/breakdown data when status !== graded — a view-model contract enforced in the controller, not a Blade @if (Pitfall 3)."
  - "The breakdown never queries Option::where('is_correct', true) — it renders only the student's own selectedOption/answer_text plus is_correct/score already stored on the Answer row (D-07)."
  - "Fixed a genuine authoring bug in the 05-01 RED test test_auto_grading_fires_on_lazy_expiry: it asserted status stayed 'submitted' for an all-MCQ, fully-answered attempt, contradicting D-03's completeness rule and its own sibling test (test_all_mcq_exam_grades_immediately). Corrected the assertion to 'graded'."
  - "Updated 4 pre-existing Phase-4 tests (AttemptSubmitTest x2, AttemptAnswerTest x1, AttemptShowTest x1, Phase4ReviewFixesTest x3) whose fixtures have zero pending open-text (either no questions at all, or a single unanswered MCQ) — these now correctly reach 'graded' immediately per D-03, not just 'submitted'. This is the expected, intentional consequence of wiring in the finalize-time completeness check, not a functional regression."

patterns-established:
  - "Any future finalize-adjacent behavior belongs inside lockAndFinalize()'s guarded branch, not duplicated across the 4 attempt-touching controller actions."

requirements-completed: [GRD-01, GRD-03, GRD-04]

# Metrics
duration: 55min
completed: 2026-07-16
status: complete
---

# Phase 5 Plan 2: MCQ Auto-Grade + Gated Student Result Summary

**AttemptGrader auto-grades every MCQ answer inside the existing finalize row lock (covering both manual submit and lazy expiry), and a new ownership-only viewResult policy gates a student's own result to a status-based awaiting/graded Blade view that never leaks the answer key.**

## Performance

- **Duration:** ~55 min
- **Started:** 2026-07-16
- **Completed:** 2026-07-16
- **Tasks:** 2
- **Files modified:** 12 (3 created, 9 modified — 4 of which are pre-existing test-assertion corrections, see Deviations)

## Accomplishments
- `App\Services\AttemptGrader` (`handleFinalized`/`gradeAutoGradable`/`syncStatus`) grades every MCQ answer defensively (untouched question → no write; no-correct-option question → false/0, never a crash) and recomputes `attempts.score`/`status` idempotently, safe for regrades.
- `Attempt::lockAndFinalize()` calls `AttemptGrader::handleFinalized()` inside its existing `DB::transaction()`/`lockForUpdate()` branch, immediately after the status-flip `update()` — one hook, both `finalize()` and `finalizeIfExpired()` paths, no controller changes.
- `AttemptPolicy::viewResult()` — ownership-only, independent of `Exam::visibleTo()` — added alongside the existing `view()`/`update()` (untouched).
- `Student\ResultController@show` + `GET student/attempts/{attempt}/result` (`student.attempts.result`) — authorize-first IDOR gate, a view-model that carries zero score data while `status !== graded`, and a per-question breakdown (student's own answer + ✓/✗ or a neutral score badge) that never queries the correct option.
- `resources/views/student/results/show.blade.php` — the two UI-SPEC Screen 3 states (awaiting / graded), reusing `x-app-layout` and the established Breeze/Tailwind palette, no new Blade components.
- `AttemptGraderTest` (4/4) and `Tests\Feature\Student\ResultTest` (5/5) GREEN; full suite 164 passed / 7 RED (the 5 `GradeAnswerTest` + 2 Lecturer `ResultTest` methods, correctly out of scope until 05-03/05-04).

## Task Commits

Each task was committed atomically:

1. **Task 1: AttemptGrader service + hook into the finalize chokepoint** - `177325e` (feat)
2. **Task 2: viewResult policy + Student result controller, route, and gated view** - `ba06045` (feat)

## Files Created/Modified
- `app/Services/AttemptGrader.php` - new: `handleFinalized`/`gradeAutoGradable`/`syncStatus`
- `app/Models/Attempt.php` - `lockAndFinalize()` hooks in `AttemptGrader::handleFinalized()`; also syncs the `answers` relation onto the caller's in-memory copy alongside `exam` (matches the existing sync discipline for the "already finalized by a racing request" branch)
- `app/Policies/AttemptPolicy.php` - added `viewResult()`
- `app/Http/Controllers/Student/ResultController.php` - new: `show()`
- `routes/student.php` - added `attempts.result` route
- `resources/views/student/results/show.blade.php` - new: gated awaiting/graded states
- `tests/Feature/Grading/AttemptGraderTest.php` - fixed `test_auto_grading_fires_on_lazy_expiry` assertion (see Deviations)
- `tests/Feature/Student/AttemptSubmitTest.php`, `AttemptAnswerTest.php`, `AttemptShowTest.php`, `Phase4ReviewFixesTest.php` - updated post-finalize status assertions from `submitted` to `graded` for fixtures with zero pending open-text answers (see Deviations)

## Decisions Made
- All key decisions are captured in the frontmatter `key-decisions` above (hook placement, ownership-only policy, view-model gating, no-key-leak query discipline, and the two categories of pre-existing test corrections).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed an authoring bug in the 05-01 RED test `test_auto_grading_fires_on_lazy_expiry`**
- **Found during:** Task 1
- **Issue:** The test builds an all-MCQ exam with a single correctly-answered question, then asserts `attempt->status === 'submitted'` after lazy-expiry finalization. This directly contradicts D-03 ("if the attempt has no open-text answers needing grading... transition status = submitted → graded") and its own sibling test `test_all_mcq_exam_grades_immediately`, which asserts `'graded'` for an equivalent all-MCQ, fully-answered fixture via the manual-submit path. Implementing the locked architecture faithfully (Pattern 1/2 from 05-RESEARCH.md — the same `handleFinalized()` hook fires on both `finalize()` and `finalizeIfExpired()`) necessarily produces `'graded'` here too.
- **Fix:** Corrected the assertion to `'graded'`, with an inline comment explaining why (parity with the sibling test, D-02's single-chokepoint intent).
- **Files modified:** `tests/Feature/Grading/AttemptGraderTest.php`
- **Commit:** `177325e`

**2. [Rule 1 - Bug] Updated 4 pre-existing Phase-4 tests whose fixtures trivially satisfy the completeness gate**
- **Found during:** Task 1 (full-suite regression check)
- **Issue:** `AttemptSubmitTest` (2 methods, zero-question exam fixtures), `AttemptAnswerTest::test_an_expired_attempt_rejects_answer_writes`, `AttemptShowTest::test_visiting_an_expired_attempt_finalizes_it_to_submitted`, and 3 methods in `Phase4ReviewFixesTest` all use fixtures with either no questions at all or a single unanswered MCQ question. Per D-03, an attempt with zero pending open-text answers (vacuously true when there are no questions, or the only questions are unanswered MCQ) transitions straight to `graded` on the same finalize call — these tests, written before `AttemptGrader` existed, asserted the pre-Phase-5 status `'submitted'`.
- **Fix:** Updated the 7 affected assertions from `'submitted'` to `'graded'`, each with an inline comment explaining the Phase-5 cause. This is the correct, intended consequence of wiring in D-03's completeness check at the shared finalize chokepoint — not a functional regression in submit/autosave behavior (which the plan's acceptance criteria was actually guarding against, and which remains intact: submission still finalizes, autosave/expiry rejection still behaves identically, only the *terminal status label* changed from `submitted` to `graded` when there is nothing left to grade).
- **Files modified:** `tests/Feature/Student/AttemptSubmitTest.php`, `tests/Feature/Student/AttemptAnswerTest.php`, `tests/Feature/Student/AttemptShowTest.php`, `tests/Feature/Student/Phase4ReviewFixesTest.php`
- **Commit:** `177325e`

None of these changes touched application behavior beyond what D-02/D-03 require — both fixes align test expectations with the locked architecture, not the other way around.

## Issues Encountered
None beyond the deviations above. A Pint auto-fix pass on `ResultController.php` initially mangled a docblock because a literal `@if (05-RESEARCH.md...)` line in a comment was misparsed as a PHPDoc annotation by the `phpdoc_separation` fixer; reworded the comment to avoid the `@`-prefixed token and re-verified `vendor/bin/pint --test` passes clean.

## Known Stubs
None — `AttemptGrader`, the policy, the controller, and the view are all fully wired; no placeholder data paths.

## Threat Flags
None beyond what the plan's own `<threat_model>` already covers (T-05-01..T-05-08, all implemented as specified: ownership-gated `viewResult`, no-key-leak breakdown query, no premature score, defensive grading).

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `AttemptGrader::syncStatus()` is public and reusable — 05-03's lecturer grade-save action calls it again (alone) under its own `lockForUpdate()`, per 05-RESEARCH.md Pattern 3.
- Route-name contract from 05-01 remains authoritative for 05-03/05-04: `lecturer.attempts.answers.grade`, `lecturer.results.index`, `lecturer.results.show`.
- Full suite: 164 passed / 7 RED (5 `GradeAnswerTest` + 2 Lecturer `ResultTest`) — exactly the expected Wave-3/4 remainder, no unexpected failures.
- No blockers for 05-03 (lecturer open-text grading) or 05-04 (lecturer results views).

---
*Phase: 05-grading-results*
*Completed: 2026-07-16*

## Self-Check: PASSED

All 3 created files found on disk (`app/Services/AttemptGrader.php`, `app/Http/Controllers/Student/ResultController.php`, `resources/views/student/results/show.blade.php`); both task commit hashes (`177325e`, `ba06045`) found in `git log`.

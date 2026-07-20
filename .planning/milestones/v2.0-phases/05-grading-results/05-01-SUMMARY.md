---
phase: 05-grading-results
plan: 01
subsystem: testing
tags: [phpunit, laravel-factories, tdd-red, grading]

# Dependency graph
requires:
  - phase: 04-attempt-taking
    provides: Attempt::lockAndFinalize()/finalize()/finalizeIfExpired() single chokepoint, Answer/Attempt/Question/Option models and factories
provides:
  - AnswerFactory openText()/mcqCorrect(Question)/mcqIncorrect(Question) states
  - AttemptFactory graded(score) state
  - 4 RED feature test classes pinning GRD-01..GRD-05 as the fixed acceptance contract for Waves 2-4
  - Fixed route-name contract for Waves 2-4: lecturer.attempts.answers.grade, lecturer.results.index, lecturer.results.show, student.attempts.result
affects: [05-02-attempt-grader, 05-03-lecturer-grading, 05-04-results-views]

# Tech tracking
tech-stack:
  added: []
  patterns: [Wave-0 RED-test-first contract (matches 03-01/04-01 precedent), factory states record student SELECTION only never the grade]

key-files:
  created:
    - tests/Feature/Grading/AttemptGraderTest.php
    - tests/Feature/Lecturer/GradeAnswerTest.php
    - tests/Feature/Student/ResultTest.php
    - tests/Feature/Lecturer/ResultTest.php
  modified:
    - database/factories/AnswerFactory.php
    - database/factories/AttemptFactory.php

key-decisions:
  - "Route names for Wave 2-4 to implement: PATCH lecturer/attempts/{attempt}/answers/{answer}/grade -> lecturer.attempts.answers.grade; GET lecturer/exams/{exam}/results -> lecturer.results.index; GET lecturer/exams/{exam}/results/{attempt} -> lecturer.results.show; GET student/attempts/{attempt}/result -> student.attempts.result"
  - "GradeAnswerTest uses patchJson()+assertStatus(422) for score-bounds violations (matches 05-VALIDATION.md's literal '422' wording) and non-JSON patch()+assertForbidden()/assertRedirect() for role/type rejections"
  - "Student ResultTest asserts on 05-UI-SPEC.md's exact copy ('Awaiting grading', 'Your Result', '{score} / {total} points') so the RED->GREEN transition also locks in the UI contract, not just the backend one"

patterns-established:
  - "Wave-0 RED-test-first contract: no production grading code in this plan; the four test classes ARE the executable spec Waves 2-4 must satisfy"

requirements-completed: [GRD-01, GRD-02, GRD-03, GRD-04, GRD-05]

# Metrics
duration: 25min
completed: 2026-07-16
status: complete
---

# Phase 5 Plan 1: Grading Matrix Factory States + RED Feature Tests Summary

**Answer/Attempt factory grading states (openText/mcqCorrect/mcqIncorrect/graded) plus 4 RED feature test classes (16 methods) pinning the GRD-01..GRD-05 auto-grade matrix, lecturer grading, and gated-result contracts before any production grading code exists.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-07-16T02:42:00+08:00
- **Completed:** 2026-07-16T02:51:00+08:00
- **Tasks:** 3
- **Files modified:** 6 (2 factories extended, 4 test files created)

## Accomplishments
- `AnswerFactory` gained `openText()`, `mcqCorrect(Question)`, `mcqIncorrect(Question)` states; `AttemptFactory` gained `graded(score)` — all recording only the student's selection/final state, never computing a grade themselves (D-01/D-08 stay owned by the not-yet-built `AttemptGrader`)
- `tests/Feature/Grading/AttemptGraderTest.php` (4 methods) pins the full auto-grade edge-case matrix from 05-RESEARCH.md Pitfall 2 (correct/wrong/untouched/no-correct-option MCQ) plus the manual-submit AND lazy-expiry chokepoint parity, plus the submitted→graded completeness gate
- `tests/Feature/Lecturer/GradeAnswerTest.php` (5 methods) pins bounded `[0,points]` score validation, non-lecturer/guest rejection, and open-text-only enforcement
- `tests/Feature/Student/ResultTest.php` (5 methods) pins the gated result (no score while pending), IDOR protection, ownership-only visibility after exam unpublish (Pitfall 1 regression guard), and the no-correct-option-leak rule (D-07)
- `tests/Feature/Lecturer/ResultTest.php` (2 methods) pins the per-exam results index and per-attempt drill-in
- All 16 new tests fail RED for missing production code (`RouteNotFoundException` for the 12 route-dependent tests, `BindingResolutionException`/failed assertions for the 4 `AttemptGraderTest` methods) — never a parse error
- Full suite: 155 pre-existing (Phase 1-4) tests stay green; 16 new RED tests — exact counts match 05-VALIDATION.md's per-file breakdown (4 + 5 + 5 + 2)

## Task Commits

Each task was committed atomically:

1. **Task 1: Answer/Attempt factory states for the grading matrix** - `00c5119` (feat)
2. **Task 2: AttemptGraderTest — auto-grade matrix + completeness gate (RED)** - `e9d7d27` (test)
3. **Task 3: GradeAnswerTest + Student ResultTest + Lecturer ResultTest (RED)** - `3943d2b` (test)

**Plan metadata:** (this commit)

## Files Created/Modified
- `database/factories/AnswerFactory.php` - added `openText()`/`mcqCorrect(Question)`/`mcqIncorrect(Question)` states
- `database/factories/AttemptFactory.php` - added `graded(score)` state
- `tests/Feature/Grading/AttemptGraderTest.php` - GRD-01/GRD-03 auto-grade + completeness RED matrix (4 tests)
- `tests/Feature/Lecturer/GradeAnswerTest.php` - GRD-02 lecturer grading RED tests (5 tests)
- `tests/Feature/Student/ResultTest.php` - GRD-04 gated result + IDOR + no-key-leak RED tests (5 tests)
- `tests/Feature/Lecturer/ResultTest.php` - GRD-05 results index + drill-in RED tests (2 tests)

## Decisions Made
- **Route-name contract locked for Waves 2-4** (05-RESEARCH.md left exact names to executor discretion): `lecturer.attempts.answers.grade` (PATCH, params `[$attempt, $answer]`), `lecturer.results.index` (GET, param `$exam`), `lecturer.results.show` (GET, params `[$exam, $attempt]` — matches 05-RESEARCH.md's own redirect example), `student.attempts.result` (GET, param `$attempt`). Waves 2-4 MUST use these exact names or the RED tests in this plan won't collect/pass correctly.
- Score-bounds violations (over-points, negative) tested via `patchJson()` + `assertStatus(422)`, matching 05-VALIDATION.md's literal "422" wording; role/type rejections tested via non-JSON `patch()` + `assertForbidden()`/`assertRedirect(route('login'))`, matching this codebase's existing `ExamAssignmentTest`/`Phase4ReviewFixesTest` convention of mixing JSON-assertion and redirect-assertion styles per scenario.
- `test_result_shown_when_graded` and `test_index_lists_attempts_per_exam`/`test_show_renders_breakdown` assert on 05-UI-SPEC.md's exact copy contract ("Awaiting grading", "Your Result", "{score} / {total} points", student/question names) — this locks the UI-SPEC's copy as part of the executable contract, not just backend behavior, so a future implementer can't silently drift from the approved microcopy.
- The "no correct-option-leak" test (`test_breakdown_never_exposes_the_correct_option`) resolves the correct option's `body` text via a direct DB query (not through the not-yet-existing result view) and asserts `assertDontSee` against it — this is deliberately implementation-agnostic so it stays valid regardless of how Wave 2-4 structures the breakdown view-model.

## Deviations from Plan

None - plan executed exactly as written. No production grading code was added; all Wave-0 constraints (no schema changes, no SQLite, no `graded_by`/`graded_at` columns, no `composer require`) were honored.

## Issues Encountered
None. The RED baseline landed exactly as predicted by 05-VALIDATION.md: `--filter=AttemptGraderTest` → 4 failed, `--filter=GradeAnswerTest` → 5 failed, `--filter=ResultTest` → 7 failed (5 Student + 2 Lecturer), full suite → 155 passed / 16 failed, no change to the Phase 1-4 baseline.

## Known Stubs
None — this plan produces test/factory code only, no UI or data-flow stubs.

## Threat Flags
None — this plan introduces no new runtime request-handling surface (per its own threat model: "no trust boundaries added"). The factory states record only the student's selection, never a computed grade, consistent with T-05-00's accepted disposition.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- The four RED test classes are now the executable acceptance contract for 05-02 (AttemptGrader service + finalize hook), 05-03 (lecturer grading route/controller/FormRequest), and 05-04 (result routes/controllers/views).
- Wave 2-4 plans MUST implement the exact route names documented above under "Decisions Made" or the pinned tests will fail to even collect (`RouteNotFoundException` becoming a different, wrong-named-route failure rather than the intended "controller missing" RED).
- No blockers. `php artisan test` baseline confirmed: 155 passed / 16 RED (exactly matching 05-VALIDATION.md's predicted count), ready for 05-02 to begin turning `AttemptGraderTest` green.

---
*Phase: 05-grading-results*
*Completed: 2026-07-16*

## Self-Check: PASSED

All 7 claimed files found on disk; all 3 task commit hashes (00c5119, e9d7d27, 3943d2b) found in git log.

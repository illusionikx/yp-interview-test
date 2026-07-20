---
phase: 10-exam-integrity-auto-assignment-attempt-lifecycle
plan: 02
subsystem: testing
tags: [phpunit, laravel, tdd-red, destructive-actions, attempt-lifecycle, security-test]

# Dependency graph
requires:
  - phase: 09-foundations-semester-model-design-tokens-alerts-entry-pages
    provides: "AttemptGrader service precedent, AttemptVanishedException guard pattern, confirm-modal component, the Gate::after mid-request-delete seam"
  - phase: 10-exam-integrity-auto-assignment-attempt-lifecycle
    provides: "10-01's AttemptVoiderTest (count contract) and CrossSubjectVisibilityTest, same Wave 0 RED convention"
provides:
  - "tests/Feature/Lecturer/ResetSubmissionsTest.php — CLS-07 + INT-03's executable acceptance spec (RED), pinning the lecturer.exams.submissions.reset route-name contract for plan 07"
  - "tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php — EDT-04's executable acceptance spec (RED) across all four editor-mutation gate sites, plus the INT-02 copy-variance requirement on the edit page"
  - "tests/Feature/AttemptNullGuardTest.php (extended) — D-5's third INT-01 crash site (AnswerGradeController) and the latent lecturer-redirect bug in AttemptVanishedException, both pinned RED, with a new App::resolving()-based mid-request-delete seam for routes that never call Gate::check()"
affects: [10-04-attempt-voider-service, 10-06-scope-visible-to-rewrite, 10-07-reset-route, 10-08-published-edit-gate-relaxation, 10-verification]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "RED-before-code Wave 0 spec: land the destructive-path/security-critical test first, let it fail for a documented structural reason, implement later in a separate plan"
    - "App::resolving(FormRequest::class) as a Gate::after-equivalent mid-request-delete seam for routes whose FormRequest::authorize() is a plain boolean check that never invokes the Gate facade — fires after route-model binding, strictly before FormRequest::validateResolved() runs authorize()"

key-files:
  created:
    - tests/Feature/Lecturer/ResetSubmissionsTest.php
    - tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php
  modified:
    - tests/Feature/AttemptNullGuardTest.php

key-decisions:
  - "Used a NEW seam (App::resolving(GradeAnswerRequest::class, ...)) instead of the literally-specified Gate::after seam for D-5's Site 3, because GradeAnswerRequest::authorize() is a plain boolean check that never calls Gate::check()/$this->authorize() — verified by direct read of GradeAnswerRequest, AnswerGradeController, and the role:lecturer middleware, none of which touch the Gate facade. The new seam reproduces the identical after-binding/before-locked-read timing via a different container hook, documented inline in the test file."
  - "Fixed a self-introduced fixture bug before committing: Attempt::factory()->for($exam)->for(User::factory()->student())->count(2)->create() shares ONE resolved User across both replicates (for() resolves its relation once, not per-replicate), violating attempts.unique(exam_id,user_id). Replaced with two separate ->for()->create() calls."
  - "Adjusted two doc-comment phrasings (in ResetSubmissionsTest and ExamUpdateVoidsAttemptsTest) that inadvertently duplicated literal method-name/pivot-call substrings the plan's acceptance criteria greps for an exact count — reworded to reference the method/behavior without repeating the literal string, so 'can_start_the_exam_again' and 'destroys_no_attempts' each match exactly once and 'sections()->sync' matches zero times in both files."
  - "Did not mark CLS-07/INT-03/EDT-04/INT-02 complete in REQUIREMENTS.md — a failing spec does not satisfy a requirement; that update belongs to the plans that make these tests pass (10-04, 10-07, 10-08)."

patterns-established:
  - "Wave 0 files may legitimately fail for MORE THAN ONE independent pre-implementation reason within the same method (e.g. ResetSubmissionsTest's retake method fails on the visibility gate before it can even reach the undefined route) — both are correct RED signals for this wave, not fixture bugs, and are documented as such in the class doc comment."

requirements-completed: []  # Intentional — Wave 0 RED plan. CLS-07, INT-03, EDT-04, INT-02 specs landed RED; requirements remain unchecked until 10-04/10-07/10-08 make them pass.

# Metrics
duration: 35min
completed: 2026-07-17
status: complete
---

# Phase 10 Plan 02: Wave 0 Destructive-Path Specs (RED) Summary

**Landed CLS-07+INT-03's reset/retake spec, EDT-04's four-site warn-and-void spec, and D-5's third INT-01 crash-site regression (plus its latent lecturer-redirect bug), all intentionally RED, with zero production code touched.**

## Performance

- **Duration:** ~35 min
- **Tasks:** 3 completed
- **Files modified:** 3 (2 new, 1 extended)

## Accomplishments

- `tests/Feature/Lecturer/ResetSubmissionsTest.php` — 6 methods pinning CLS-07 (reset deletes all attempts on an exam, exam-scoped, graded scores cascade-deleted, outcome flash via `session('status')`, lecturer-only access) and INT-03 (a reset student can start a genuinely new attempt, proven by asserting the new attempt's id differs from the original). Pins the route-name contract `lecturer.exams.submissions.reset` (`DELETE lecturer/exams/{exam}/submissions` → `ExamController@resetSubmissions`) that plan 07 must conform to.
- `tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php` — 9 methods pinning EDT-04 across all FOUR editor-mutation gate sites (exam update, question store, question update, question destroy — including D-6's inline `abort_if` site), the zero-attempts plain-save branch, the voiding-flash copy, the critical validation-before-void ordering (`test_a_failed_validation_on_an_attempted_exam_destroys_no_attempts`), and INT-02's warning-copy variance on the edit page (the "have already been graded" clause appears only when graded > 0). Explicitly documents `ExamController::destroy()` as out of scope.
- `tests/Feature/AttemptNullGuardTest.php` (extended) — 3 new methods pinning D-5's third and last unguarded `lockForUpdate()->first()` site (`AnswerGradeController::update()`), plus the latent lecturer-redirect bug the plan's objective describes (the inherited exception's non-JSON branch would strand a lecturer on a `role:student`-gated 403). Because this route's `GradeAnswerRequest::authorize()` never calls the Gate facade, the existing `Gate::after` seam cannot fire here; a new, documented `App::resolving(GradeAnswerRequest::class, ...)` seam reproduces the same after-binding/before-locked-read timing. A third new method is a regression guard proving Site 2's existing student-facing JSON contract is unaffected.

## Task Commits

Each task was committed atomically:

1. **Task 1: CLS-07 + INT-03 reset/retake spec** — `2f11bbf` (test)
2. **Task 2: EDT-04 four-site warn-and-void spec** — `4d57e08` (test)
3. **Task 3: D-5 third crash-site regression** — `2429f3a` (test)

_No TDD RED→GREEN→REFACTOR cycle applies here — this plan's entire purpose is to land RED specs and stop. The GREEN commits land in plans 10-04 (AttemptVoider service), 10-07 (reset route), and 10-08 (published-edit gate relaxation)._

## Files Created/Modified

- `tests/Feature/Lecturer/ResetSubmissionsTest.php` — CLS-07 + INT-03's reset/retake spec, 6 test methods
- `tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php` — EDT-04's four-site warn-and-void spec, 9 test methods
- `tests/Feature/AttemptNullGuardTest.php` — D-5's Site 3 spec, +3 test methods, class doc comment extended (no changes to the 5 pre-existing test method bodies)

## Decisions Made

- Built a **new mid-request-delete seam** for D-5's Site 3 (`App::resolving(GradeAnswerRequest::class, ...)`) rather than the literally-specified `Gate::after` seam, because `GradeAnswerRequest::authorize()` is a plain boolean check that never invokes `Gate::check()`/`$this->authorize()` — verified by direct read of the FormRequest, the controller, and the `role:lecturer` route-group middleware. The new seam fires at the same logical point in the request lifecycle (after `SubstituteBindings` has bound `{attempt}`/`{answer}`, strictly before `FormRequest::validateResolved()` runs `authorize()`), preserving the test's intent exactly. Documented inline with full reasoning.
- Followed `AttemptAvailabilityTest::enrolledStudentFor()`'s same-subject fixture template for `ResetSubmissionsTest`, deliberately omitting the `exam_section` pivot sync per the plan's instruction (plan 06 drops the pivot) — this means the retake method's first attempt-start currently fails on the still-pivot-based `scopeVisibleTo()`, a second, independent, and equally legitimate RED reason documented in the class doc comment.
- Followed the plan's `AttemptVoiderTest`-style count contract (`inProgress`/`submittedUngraded`/`graded`/`notYetGraded`/`total`) implicitly via the fixture shape (3 attempts, one per state) used across both new test files, keeping the destructive-warning population math consistent with 10-01's spec.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed a factory-sharing bug in my own test fixture before committing**
- **Found during:** Task 2 (writing `test_the_edit_page_warning_names_only_the_ungraded_population_when_none_are_graded`)
- **Issue:** `Attempt::factory()->for($exam)->for(User::factory()->student())->count(2)->create()` throws a `UniqueConstraintViolationException` — Laravel's `for()` resolves its related-model factory ONCE and reuses the same instance across all `count()` replicates, so both attempts got the same `user_id`, violating `attempts.unique(exam_id, user_id)`.
- **Fix:** Replaced the single `count(2)` call with two separate `->for($exam)->for(User::factory()->student())->create()` calls, each resolving its own student.
- **Files modified:** `tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php`
- **Verification:** Re-ran `php artisan test --filter=ExamUpdateVoidsAttemptsTest` — the method now fails for the intended reason (missing copy on the still-unmodified edit page), not the factory error.
- **Committed in:** `4d57e08` (part of Task 2 commit — caught before commit, so no separate fix commit needed)

**2. [Rule 1 - Bug] Corrected doc-comment wording that accidentally duplicated grep-pinned literal strings**
- **Found during:** Post-write acceptance-criteria verification (running the plan's own `grep -c` checks)
- **Issue:** Class-level doc comments in `ResetSubmissionsTest.php` and `ExamUpdateVoidsAttemptsTest.php` quoted the literal method name `can_start_the_exam_again` / `destroys_no_attempts` and the literal pivot-call syntax `sections()->sync(...)`, pushing those grep counts to 2/2/1 instead of the plan's required 1/1/0.
- **Fix:** Reworded the doc comments to reference the method/behavior by description instead of repeating the exact substring (e.g. "the retake method (see its own method-level doc comment below)" instead of naming it, "this fixture never syncs the exam_section pivot" instead of `sections()->sync()`).
- **Files modified:** `tests/Feature/Lecturer/ResetSubmissionsTest.php`, `tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php`
- **Verification:** Re-ran all of the plan's `grep -c` acceptance checks; all now match exactly (see "Acceptance-criteria greps" below).
- **Committed in:** `2f11bbf`, `4d57e08` (caught before commit, folded into the same task commits)

---

**Total deviations:** 2 auto-fixed (1 test-fixture bug, 1 doc-comment/grep-contract mismatch)
**Impact on plan:** Both fixes are test-file-only corrections made before the task commits landed — no scope creep, no production code touched, no change to the plan's intended RED/GREEN split.

## Issues Encountered

None beyond the two self-caught issues documented above under Deviations. Both new test files and the extended file produced the expected RED/GREEN split on the corrected first run.

## Actual Test Output (recorded verbatim)

**`php artisan test --filter=ResetSubmissionsTest`** — 6 failed (1 assertion recorded before first hard stop; each method fails independently):
- 5 methods (`reset_deletes_every_attempt_on_the_exam`, `reset_permanently_deletes_graded_scores`, `reset_only_affects_the_target_exam`, `reset_reports_the_outcome_to_the_lecturer`, `a_student_is_forbidden_from_resetting_submissions`) — **RouteNotFoundException**: `Route [lecturer.exams.submissions.reset] not defined.` Correct RED — plan 07 defines this route.
- 1 method (`a_student_whose_attempt_was_reset_can_start_the_exam_again`) — **assertion failure**: `Failed asserting that table [attempts] matches expected entries count of 1. Entries found: 0.` at the first `post(route('student.attempts.store', $exam))`. Correct RED for the documented, independent reason: `Exam::scopeVisibleTo()` still walks the `exam_section` pivot (plan 06 has not yet rewritten it to be subject-derived), so the enrolled student's very first attempt-start 403s before this method ever reaches the undefined reset route.

**`php artisan test --filter=ExamUpdateVoidsAttemptsTest`** — 9 failed (9 assertions):
- All 9 methods fail because the published-edit gate is still closed in all four sites (`UpdateExamRequest`, `StoreQuestionRequest`, `UpdateQuestionRequest` all return `! $exam->is_published` from `authorize()`; `ExamQuestionController::destroy()` still has its inline `abort_if($exam->is_published, 403)`). The two copy-variance methods and the zero-count method also fail via this same 403 gate (the GET `edit` route itself isn't gated, but the assertions about post-implementation copy naturally fail since the feature doesn't exist yet). Correct RED — plan 08 relaxes all four sites.

**`php artisan test --filter=AttemptNullGuardTest`** — 6 passed, 2 failed (14 assertions):
- **PASSED (6):** the 5 pre-existing Site 1/2 methods (untouched, still green) + the new regression guard `test_the_students_vanished_row_message_is_unchanged_for_student_routes` (Site 2's JSON contract confirmed unaffected).
- **FAILED (2, correct RED):**
  - `test_a_vanished_attempt_row_during_a_lecturer_grade_save_does_not_crash` — actual response status **500**, caused by `TypeError: App\Services\AttemptGrader::syncStatus(): Argument #1 ($attempt) must be of type App\Models\Attempt, null given` at `AnswerGradeController.php:34`. Exactly the INT-01 crash D-5 exists to close.
  - `test_a_vanished_attempt_row_during_a_lecturer_grade_save_redirects_the_lecturer_somewhere_they_can_actually_go` — same underlying 500/TypeError, so the expected redirect/session assertions never get a chance to evaluate. Confirms plan 04 must add both the null-guard AND the `routeIs('lecturer.*')` branch.

**Full suite — `php artisan test`** — **342 passed, 23 failed** (849 assertions), 34.65s:
- 341 pre-existing baseline (340 Phase-9 baseline + 1 new-passing from plan 10-01): all still passing (0 regressions), **plus** 1 new-and-passing from this plan (the Site-2 regression guard) = **342 passed**.
- 23 failed = 6 pre-existing (5 `AttemptVoiderTest` class-not-found + 1 `CrossSubjectVisibilityTest` positive control, both from plan 10-01, untouched) + 17 new-and-red from this plan (6 `ResetSubmissionsTest` + 9 `ExamUpdateVoidsAttemptsTest` + 2 `AttemptNullGuardTest`).
- Arithmetic reconciles exactly against 10-01-SUMMARY.md's recorded 341/6 baseline: 341+1=342 passed; 6+17=23 failed.

**Regression spot-checks (untouched files, all green):**
- `php artisan test --filter="ExamPublishedEditGateTest|AttemptGraderTest|ExamIndexTest|ExamControllerTest"` — 34 passed (99 assertions).

**Acceptance-criteria greps, all satisfied exactly:**
- `grep -c 'lecturer.exams.submissions.reset' tests/Feature/Lecturer/ResetSubmissionsTest.php` → 8 (≥4 required)
- `grep -c 'can_start_the_exam_again' tests/Feature/Lecturer/ResetSubmissionsTest.php` → 1 (=1 required)
- `grep -c 'only_affects_the_target_exam' tests/Feature/Lecturer/ResetSubmissionsTest.php` → 1 (=1 required)
- `grep -c 'sections()->sync' tests/Feature/Lecturer/ResetSubmissionsTest.php` → 0 (=0 required)
- `grep -c "assertSessionHas('success'" tests/Feature/Lecturer/ResetSubmissionsTest.php` → 0 (=0 required)
- `git diff --name-only HEAD -- routes/` → empty
- `grep -cE 'exams.update|questions.store|questions.update|questions.destroy' tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php` → 7 (≥4 required)
- `grep -c 'destroys_no_attempts' tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php` → 1 (=1 required)
- `grep -c 'have already been graded' tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php` → 2 (≥2 required)
- `grep -c 'exams.destroy' tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php` → 0 (=0 required)
- `grep -c 'sections()->sync' tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php` → 0 (=0 required)
- `grep -c 'lecturer.attempts.answers.grade' tests/Feature/AttemptNullGuardTest.php` → 2 (≥2 required)
- `grep -c "assertRedirect(route('lecturer.exams.index'))" tests/Feature/AttemptNullGuardTest.php` → 1 (≥1 required)
- `grep -c 'Site 3' tests/Feature/AttemptNullGuardTest.php` → 5 (≥1 required)
- `git diff -U0 tests/Feature/AttemptNullGuardTest.php` → all 9 deletions confined to the class doc comment; zero changes inside the 5 pre-existing test method bodies (verified by direct inspection)
- `git diff --name-only HEAD -- app/` → empty (no production code touched)
- `git diff --name-only HEAD -- app/ routes/ database/ resources/` → empty

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- All three destructive-path Wave 0 specs are landed and committed, ready to guard plans 10-04 (`AttemptVoider` service — turns the count/void assertions consistent), 10-07 (the `lecturer.exams.submissions.reset` route/controller — turns `ResetSubmissionsTest` GREEN), 10-08 (relaxing all four published-edit gate sites plus the atomic save+void transaction — turns `ExamUpdateVoidsAttemptsTest` GREEN), and 10-04 again for D-5's null-guard plus the exception's lecturer branch (turns the 2 new `AttemptNullGuardTest` methods GREEN).
- No blockers. The 341-test baseline (inherited from 10-01) is intact; this plan added 18 new test methods with a clean, fully-documented RED signal on the 17 that are supposed to be red at this point in the phase, and exactly 1 new-and-green regression guard.
- REQUIREMENTS.md intentionally left untouched — CLS-07, INT-03, EDT-04, and INT-02 remain unchecked until their implementing plans (10-04, 10-07, 10-08) land.
- **Flag for the next implementing plan (10-04):** the `App::resolving(GradeAnswerRequest::class, ...)` seam introduced here for Site 3 is test-only and requires no production change to work — plan 04 does not need to add any Gate call to `AnswerGradeController`/`GradeAnswerRequest` for this test to eventually pass; it only needs the null-guard + the exception's lecturer branch.

---
*Phase: 10-exam-integrity-auto-assignment-attempt-lifecycle*
*Completed: 2026-07-17*

## Self-Check: PASSED

- FOUND: tests/Feature/Lecturer/ResetSubmissionsTest.php
- FOUND: tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php
- FOUND: tests/Feature/AttemptNullGuardTest.php
- FOUND: commit 2f11bbf
- FOUND: commit 4d57e08
- FOUND: commit 2429f3a

---
phase: 10-exam-integrity-auto-assignment-attempt-lifecycle
plan: 01
subsystem: testing
tags: [phpunit, laravel, tdd-red, security-test, exam-visibility, attempt-lifecycle]

# Dependency graph
requires:
  - phase: 09-foundations-semester-model-design-tokens-alerts-entry-pages
    provides: "AttemptGrader service precedent, AttemptVanishedException guard pattern, confirm-modal component"
provides:
  - "tests/Feature/Student/CrossSubjectVisibilityTest.php — INT-04's executable acceptance spec (RED), pinned across list/direct-access/start with a mandatory assertNotSame subject-ID guard against the factory trap"
  - "tests/Feature/AttemptVoiderTest.php — INT-02's executable acceptance spec (RED/ERROR), pinning all five destructive-warning counts, exam-scoping, and the answers cascade before AttemptVoider exists"
affects: [10-04-attempt-voider-service, 10-06-scope-visible-to-rewrite, 10-verification]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "RED-before-code Wave 0 spec: land the security-critical test first, let it fail for the documented structural reason, implement later in a separate plan"
    - "Explicit assertNotSame/assertSame subject-ID guard as a load-bearing fixture assertion (not decorative) to defeat the ExamFactory/SectionFactory independent-Subject::factory() trap"

key-files:
  created:
    - tests/Feature/Student/CrossSubjectVisibilityTest.php
    - tests/Feature/AttemptVoiderTest.php
  modified: []

key-decisions:
  - "Neither test file touches app/ — verified via git diff --name-only HEAD -- app/ (empty) — this plan is spec-only, per the Wave 0 contract"
  - "Did not mark INT-04/INT-02 complete in REQUIREMENTS.md — a failing spec does not satisfy a requirement; that update belongs to the plans that make these tests pass (10-04, 10-06)"

patterns-established:
  - "Wave 0 negative-test pairing: every negative/exclusion assertion ships with an explicit positive control in the same file, so a scope that denies everyone can't silently satisfy the negative case"

requirements-completed: []  # Intentional — see "Do Not Mark Requirements Complete" below. INT-04/INT-02 specs landed RED; requirements remain unchecked until 10-04/10-06 make them pass.

# Metrics
duration: 12min
completed: 2026-07-17
status: complete
---

# Phase 10 Plan 01: Wave 0 Security Specs (RED) Summary

**Landed INT-04's cross-subject-leak negative spec and INT-02's five-count destructive-warning spec, both intentionally RED, with zero production code touched.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-07-17T08:04:06Z
- **Completed:** 2026-07-17T08:16:00Z
- **Tasks:** 2 completed
- **Files modified:** 2 (both new)

## Accomplishments

- `tests/Feature/Student/CrossSubjectVisibilityTest.php` — two methods pinning INT-04 (threat T-10-01, v2.0's CRITICAL cross-subject leak): a negative test asserting a student enrolled only in a different subject cannot see, open, or start an exam (list + direct GET + POST start, plus a `assertDatabaseMissing` on `attempts`), and a positive control pinning the same-subject case as the mirror image. Both fixtures pin `subject_id` explicitly and assert the two subject IDs actually differ/match (`assertNotSame`/`assertSame`) so the test cannot pass by the documented ExamFactory/SectionFactory "factory trap" accident.
- `tests/Feature/AttemptVoiderTest.php` — five methods pinning INT-02 (threat T-10-02, D-2's irreversible hard delete): exact five-count assertions (`inProgress`, `submittedUngraded`, `graded`, `notYetGraded`, `total`) against a hand-built 1/1/1 fixture, the all-zero case, an exam-scoping guard (a second exam's attempts must not leak into the first exam's counts), the hard-delete-cascades-to-answers guarantee, and the empty-void-is-a-legitimate-no-op case.

## Task Commits

Each task was committed atomically:

1. **Task 1: INT-04 cross-subject visibility spec** — `0df77c4` (test)
2. **Task 2: INT-02 count-correctness spec** — `5a81b77` (test)

_No TDD RED→GREEN→REFACTOR cycle applies here — this plan's entire purpose is to land RED specs and stop; the GREEN commits land in plans 10-04 (AttemptVoider) and 10-06 (scopeVisibleTo rewrite)._

## Files Created/Modified

- `tests/Feature/Student/CrossSubjectVisibilityTest.php` — INT-04's negative regression + positive control, 2 test methods
- `tests/Feature/AttemptVoiderTest.php` — INT-02's five-count spec + exam-scoping + cascade + empty-void, 5 test methods

## Decisions Made

- Followed the plan's fixture guidance verbatim: `enrolledStudentFor()`-style same-subject pinning via `Section::factory()->for($subject)->create()` / `Exam::factory()->for($subject)->create()` (Laravel's `for()` factory helper resolves the `subject()` relation on both models, confirmed present on `Exam` and `Section`).
- Did not stub `App\Services\AttemptVoider` to force a clean failure instead of a `BindingResolutionException` — the plan explicitly requires the ERROR (class-not-found) as the correct RED signal for a not-yet-created service.
- Left `REQUIREMENTS.md` completely untouched per the plan's hard rule — no `requirements.mark-complete` call was made.

## Deviations from Plan

None — plan executed exactly as written. No Rule 1-4 triggers encountered; no production code was touched (verified: `git diff --name-only HEAD -- app/` returns empty both before and after this plan).

## Issues Encountered

None. Both files matched the plan's prescribed content and produced the expected RED state on the first run — no debugging iterations were needed.

## Actual Test Output (RED state, recorded verbatim)

**`php artisan test --filter=CrossSubjectVisibilityTest`** — 1 failed, 1 passed (7 assertions):

- `test_a_student_enrolled_only_in_a_different_subject_cannot_see_open_or_start_the_exam` — **PASSED**. This is expected but for a *pre-D-1* reason, not the post-D-1 reason the test is written to guard: today's `scopeVisibleTo()` still walks the (unpopulated) `exam_section` pivot, so the exam is invisible to every student regardless of subject, since this fixture deliberately never calls `$exam->sections()->sync(...)` (per the plan's post-D-1 fixture shape). The negative assertions therefore currently pass "for the pivot reason," not yet "for the subject reason" — that becomes the real, meaningful guard once plan 06 rewrites `scopeVisibleTo()` to be subject-derived. This is expected and consistent with the plan's own note that the file's overall RED state comes from the *positive control* failing, not a requirement that every method fail individually.
- `test_a_student_enrolled_in_the_exams_own_subject_can_see_open_and_start_it` — **FAILED** (the file's RED signal): `Failed asserting that false is true.` at line 95 (`Exam::visibleTo($student)->whereKey($exam->id)->exists()`). Fails for the documented, correct reason: the pivot-based `scopeVisibleTo()` requires `$exam->sections()->sync(...)`, which this fixture (correctly, per the plan) never calls, since post-D-1 no sync is needed at all.

**`php artisan test --filter=AttemptVoiderTest`** — 5 errored, 0 assertions:

- All five methods (`test_summarize_reports_all_five_counts_exactly`, `test_summarize_reports_zeroes_for_an_exam_with_no_attempts`, `test_summarize_counts_only_the_given_exams_attempts`, `test_void_hard_deletes_every_attempt_and_cascades_to_answers`, `test_void_on_an_exam_with_no_attempts_returns_zero_and_throws_nothing`) errored identically: `BindingResolutionException: Target class [App\Services\AttemptVoider] does not exist.` — exactly the documented correct RED for a service that has not been created yet (plan 10-04 creates it).

**Full suite — `php artisan test`** — **341 passed, 6 failed** (835 assertions), 36.60s:

- 340 pre-existing baseline tests: all still passing (0 regressions).
- 1 new passing method (the negative cross-subject test, passing for the pre-D-1 pivot reason described above).
- 6 failing/erroring: 1 from `CrossSubjectVisibilityTest` (the positive control) + 5 from `AttemptVoiderTest` (all class-not-found).
- Confirmed 341 = 340 baseline + 1 new-and-passing; 6 = 1 + 5 new-and-red. Arithmetic reconciles exactly against the 340 baseline stated in 10-VALIDATION.md.

**Regression spot-checks (both explicitly required by the plan's acceptance criteria):**
- `php artisan test --filter=ExamIndexTest` — 10 passed (24 assertions). Unaffected.
- `php artisan test --filter=AttemptGraderTest` — 4 passed (16 assertions). Unaffected.

**Acceptance-criteria greps, all satisfied:**
- `grep -c 'assertNotSame' tests/Feature/Student/CrossSubjectVisibilityTest.php` → 2 (≥1 required)
- `grep -c 'student.attempts.store' tests/Feature/Student/CrossSubjectVisibilityTest.php` → 2 (≥2 required)
- `grep -vc '^\s*//' tests/Feature/Student/CrossSubjectVisibilityTest.php` → 102 (real code, not a stub)
- `grep -c 'Section::factory()->create()' tests/Feature/Student/CrossSubjectVisibilityTest.php` → 1, but the sole match is inside the class-level doc comment describing the factory trap itself (`* Section::factory()->create() land in different subjects BY ACCIDENT.`), not executable test code — both actual fixture calls use `Section::factory()->for($subject)->create()`. Verified by direct grep -n of the match line.
- `grep -c "inProgress\|submittedUngraded\|notYetGraded" tests/Feature/AttemptVoiderTest.php` → 9 (≥3 required)
- `grep -c "assertDatabaseMissing('answers'" tests/Feature/AttemptVoiderTest.php` → 2 (≥1 required)
- `grep -c 'only_the_given_exams_attempts' tests/Feature/AttemptVoiderTest.php` → 1 (=1 required)
- `test ! -f app/Services/AttemptVoider.php` → succeeds (service does not exist)
- `git diff --name-only HEAD -- app/` → empty (no production code touched)

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Both Wave 0 security specs are landed and committed, ready to guard plans 10-04 (`AttemptVoider` service, turns `AttemptVoiderTest` GREEN) and 10-06 (`scopeVisibleTo()` rewrite off the `exam_section` pivot, turns the `CrossSubjectVisibilityTest` positive control GREEN and converts the negative test's current "passes for the pivot reason" into "passes for the correct subject-boundary reason").
- No blockers. The 340-test baseline is intact; this plan added 7 new test methods with a clean, documented RED signal on the 2 methods/5 methods that are supposed to be red at this point in the phase.
- REQUIREMENTS.md intentionally left untouched — INT-04 and INT-02 remain unchecked until their implementing plans land.

---
*Phase: 10-exam-integrity-auto-assignment-attempt-lifecycle*
*Completed: 2026-07-17*

## Self-Check: PASSED

- FOUND: tests/Feature/Student/CrossSubjectVisibilityTest.php
- FOUND: tests/Feature/AttemptVoiderTest.php
- FOUND: commit 0df77c4
- FOUND: commit 5a81b77

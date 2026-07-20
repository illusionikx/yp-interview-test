---
phase: 10-exam-integrity-auto-assignment-attempt-lifecycle
plan: 03
subsystem: testing
tags: [phpunit, factories, fixtures, laravel-11]

# Dependency graph
requires:
  - phase: 10-02
    provides: Wave 0 RED specs for CLS-05/INT-04 (CrossSubjectVisibilityTest, AttemptVoiderTest, ExamUpdateVoidsAttemptsTest, ResetSubmissionsTest) and the D-5 third-crash-site regression tests in AttemptNullGuardTest
provides:
  - "Every pivot-consuming test fixture in the suite now pins Section::factory()'s subject_id to the paired Exam's subject_id explicitly, rather than relying on two independent Subject::factory() calls to agree by accident"
  - "A verified-inert baseline (338 passed / 23 failed, identical to pre-plan) that plan 06 can build its subject-derived-visibility predicate rewrite and pivot-drop against without also debugging fixture semantics at the same time"
affects: ["10-06"]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Same-subject fixture pinning: build the Exam first, then Section::factory()->create(['subject_id' => $exam->subject_id]), keep sections()->sync() until 10-06 drops the pivot"
    - "When two Section::factory() rows must share one subject_id in the same test, pin `sequence` explicitly too (SectionFactory defaults sequence=1 on every row; year/semester are random 4x2 combos, so two same-subject sections collide on the sections(subject_id,year,semester,sequence) unique key roughly 1-in-8 runs otherwise)"

key-files:
  created: []
  modified:
    - tests/Feature/Student/ExamAccessTest.php
    - tests/Feature/Student/ExamIndexTest.php
    - tests/Feature/Student/ExamVisibilityRegressionTest.php
    - tests/Feature/Student/AttemptStartTest.php
    - tests/Feature/Student/AttemptShowTest.php
    - tests/Feature/Student/AttemptAnswerTest.php
    - tests/Feature/Student/AttemptSubmitTest.php
    - tests/Feature/Student/Phase4ReviewFixesTest.php
    - tests/Feature/AttemptNullGuardTest.php
    - tests/Feature/Grading/AttemptGraderTest.php
    - tests/Feature/Lecturer/GradeAnswerTest.php
    - tests/Feature/Lecturer/ResultTest.php
    - tests/Feature/Student/ResultTest.php
    - tests/Feature/Student/AttemptPolicyTest.php

key-decisions:
  - "Fixed a latent SectionFactory flakiness the pinning exposed: two Section::factory() rows sharing a subject_id can collide on the sections unique(subject_id,year,semester,sequence) key since sequence is hardcoded to 1 by default (ExamAccessTest::test_a_student_enrolled_in_a_different_section_is_forbidden and ExamIndexTest::test_index_excludes_a_published_exam_for_a_different_section) -- pinned explicit sequence values (1 and 2) on those two fixtures only, per Rule 1 (auto-fix bug)"
  - "tests/Feature/Student/ExamShowTest.php and tests/Feature/Student/AttemptAvailabilityTest.php needed NO edit -- both already pinned subject_id correctly (verified, zero diff) -- ExamShowTest was apparently authored correctly in an earlier phase despite RESEARCH.md flagging it as a pivot consumer needing a rewrite"
  - "tests/Feature/AttemptNullGuardTest.php actually had TWO unpinned pivot sites (attemptFixture() and gradableAnswerFixture()), not the one the plan's action text named -- both pinned identically since the acceptance criteria's grep check requires zero unpinned sections file-wide"

requirements-completed: []

# Metrics
duration: 12min
completed: 2026-07-17
status: complete
---

# Phase 10 Plan 03: Disarm the Factory Trap Summary

**Pinned Section::factory() subject_id to the paired Exam's subject_id across 14 test files as a provably behavior-neutral pass (338 passed / 23 failed, byte-identical to pre-plan), so plan 06's pivot-drop and visibility predicate rewrite inherits fixtures that already mean what they say.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-07-17T08:54:54Z
- **Completed:** 2026-07-17T09:06:30Z
- **Tasks:** 2
- **Files modified:** 14 (of 16 named in the plan; 2 required zero changes, verified correct)

## Accomplishments
- Disarmed `ExamFactory`/`SectionFactory`'s independent-`Subject::factory()` trap across every fixture that pairs a `Section` with an `Exam` for pivot-based visibility, by reordering each fixture to create the exam first and pin `Section::factory()->create(['subject_id' => $exam->subject_id])`
- Kept every `$exam->sections()->sync(...)` call intact — the pivot remains the sole authoritative visibility predicate this wave; this plan changes nothing about what any test asserts
- Found and fixed a genuine (if latent) flakiness bug the pinning exposed: two same-subject sections can collide on `sections(subject_id, year, semester, sequence)`'s unique constraint because `SectionFactory`'s `sequence` defaults to `1` on every row — fixed by pinning explicit sequence values on the two fixtures that build two sections in one subject
- Verified the suite's outcome is byte-identical before/after: 338 passed / 23 failed, same test names failing for the same (pre-existing, later-plan-owned) reasons

## Task Commits

Each task was committed atomically:

1. **Task 1: Pin subject on the student-facing visibility and attempt fixtures (9 files)** - `23ca8aa` (test)
2. **Task 2: Pin subject on the grading, results and null-guard fixtures (7 files)** - `1a892e2` (test)

**Plan metadata:** this commit (docs: complete plan)

## Files Created/Modified
- `tests/Feature/Student/ExamAccessTest.php` — 7 sites; the two multi-section methods (`test_a_student_enrolled_in_a_different_section_is_forbidden`) also got explicit `sequence` pins to avoid the unique-constraint collision
- `tests/Feature/Student/ExamIndexTest.php` — `visibleExamFor()` helper (fixes 5 call sites) plus 2 inline sites; `test_index_excludes_a_published_exam_for_a_different_section` also got explicit `sequence` pins
- `tests/Feature/Student/ExamVisibilityRegressionTest.php` — 1 site (data-provider-driven method)
- `tests/Feature/Student/AttemptStartTest.php` — 4 sites
- `tests/Feature/Student/AttemptShowTest.php` — 4 sites
- `tests/Feature/Student/AttemptAnswerTest.php` — 1 site (`mcqAttemptFixture()`)
- `tests/Feature/Student/AttemptSubmitTest.php` — 2 sites
- `tests/Feature/Student/Phase4ReviewFixesTest.php` — 1 site (`fixture()`)
- `tests/Feature/AttemptNullGuardTest.php` — 2 sites (`attemptFixture()` and `gradableAnswerFixture()` — the plan named one, the file had two)
- `tests/Feature/Grading/AttemptGraderTest.php` — 1 site (`fixture()`)
- `tests/Feature/Lecturer/GradeAnswerTest.php` — 1 site (`openTextFixture()`)
- `tests/Feature/Lecturer/ResultTest.php` — 2 sites
- `tests/Feature/Student/ResultTest.php` — 1 site (`fixture()`)
- `tests/Feature/Student/AttemptPolicyTest.php` — 2 sites (`attemptFixture()` and `test_a_student_cannot_view_another_students_attempt`); the IDOR two-students-one-section shape and the `sections()->detach()` premise of `test_attempt_survives_exam_unassigned_from_section_mid_attempt` left untouched, per the plan's explicit exclusion

**Verified correct, zero changes:**
- `tests/Feature/Student/ExamShowTest.php` — already pinned `Section::factory()->create(['subject_id' => $exam->subject_id])` on read; no diff produced
- `tests/Feature/Student/AttemptAvailabilityTest.php` — `enrolledStudentFor()` is the template this whole plan copies; confirmed correct, no diff produced

## Decisions Made
- Left every `sections()->sync()` call in place across all 14 files — removing it is explicitly plan 06's job, not this plan's
- Left `AttemptPolicyTest::test_attempt_survives_exam_unassigned_from_section_mid_attempt`'s `sections()->detach()` call untouched — its entire premise (an "unassign" operation) disappears once plan 06 makes visibility subject-derived; that test's fate belongs to plan 06, not here
- Left `ExamIndexTest`'s two `$exam->sections()->first()->enrollments()->updateExistingPivot(...)` pivot-navigation call sites (CR-02 tests) untouched — plan 06 re-points them once `Exam::sections()` changes shape
- Fixed the `sections(subject_id, year, semester, sequence)` unique-constraint collision risk (Rule 1 — auto-fix bug) by pinning explicit `sequence` values in the two fixtures building two same-subject sections in one test; this is fixture hygiene only and changes no assertion or test outcome

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed a fixture-collision flakiness bug the same-subject pinning exposed**
- **Found during:** Task 1, running the filtered verification suite
- **Issue:** `ExamAccessTest::test_a_student_enrolled_in_a_different_section_is_forbidden` and `ExamIndexTest::test_index_excludes_a_published_exam_for_a_different_section` each create two `Section::factory()` rows now pinned to the same `subject_id`. `SectionFactory`'s `sequence` column defaults to `1` on every row, while `year`/`semester` are drawn randomly from a small space (4 years x 2 semesters = 8 combinations) — so two same-subject sections collide on the `sections(subject_id, year, semester, sequence)` unique constraint roughly 1-in-8 runs. This surfaced as a `UniqueConstraintViolationException` on one run of the filtered suite.
- **Fix:** Pinned explicit, distinct `sequence` values (`1` and `2`) on the two sections in each of these two fixtures, with a short comment explaining why (this is fixture hygiene, not new behavior — the constraint columns were already load-bearing, only exposed because these two sections now legitimately share a subject).
- **Files modified:** `tests/Feature/Student/ExamAccessTest.php`, `tests/Feature/Student/ExamIndexTest.php`
- **Verification:** Reran the filtered suite three times consecutively with zero failures after the fix; the collision no longer reproduces.
- **Committed in:** `23ca8aa` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 — bug)
**Impact on plan:** Necessary for a stable green suite; the fix touches only test fixture data (an explicit `sequence` override), not any assertion, test name, or production file. No scope creep — still purely fixture hygiene.

## Issues Encountered
None beyond the flakiness bug documented above.

## User Setup Required
None — no external service configuration required.

## Next Phase Readiness

Plan 06 (the pivot drop + subject-derived `scopeVisibleTo()` rewrite) can now proceed against fixtures that already mean what they say. Concretely, plan 06 inherits:

- **16 files** with the same-subject fixture already pinned (14 modified here + 2 already-correct: `ExamShowTest`, `AttemptAvailabilityTest`).
- Every one of those files still has its `sections()->sync()` calls intact — plan 06's job is to delete those lines (visibility becomes automatic, no sync needed).
- Two known INVERT targets flagged for plan 06 exactly as CONTEXT.md specifies:
  - `ExamAccessTest::test_a_student_enrolled_in_a_different_section_is_forbidden` (now also has explicit `sequence` pins on its two sections — preserve those when inverting)
  - `ExamIndexTest::test_index_excludes_a_published_exam_for_a_different_section` (same — preserve the `sequence` pins)
- `ExamIndexTest` lines ~154/~183's `$exam->sections()->first()->enrollments()->updateExistingPivot(...)` pivot-navigation call sites — unchanged, still need re-pointing once `Exam::sections()` changes shape.
- `AttemptPolicyTest::test_attempt_survives_exam_unassigned_from_section_mid_attempt` — its `sections()->detach()` premise is unchanged; plan 06 decides whether this test survives, is rewritten, or is deleted once "unassign" is no longer an operation that exists.
- Baseline to diff against going forward: **338 passed / 23 failed** (the 23 are the Wave 0 RED specs for `AttemptVoiderTest`, `ExamUpdateVoidsAttemptsTest`, `ResetSubmissionsTest`, `CrossSubjectVisibilityTest`, and 2 D-5 methods in `AttemptNullGuardTest` — none of which are this plan's concern).

No blockers.

---
*Phase: 10-exam-integrity-auto-assignment-attempt-lifecycle*
*Completed: 2026-07-17*

## Self-Check: PASSED
- FOUND: .planning/phases/10-exam-integrity-auto-assignment-attempt-lifecycle/10-03-SUMMARY.md
- FOUND: commit 23ca8aa
- FOUND: commit 1a892e2

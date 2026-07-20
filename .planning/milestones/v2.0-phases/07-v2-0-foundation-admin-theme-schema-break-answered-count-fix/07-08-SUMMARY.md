---
phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix
plan: 08
subsystem: testing
tags: [laravel, phpunit, enrollment, section, rbac, idor, acceptance-gate]

# Dependency graph
requires:
  - phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix (plan 03)
    provides: Section/Enrollment models, EnrollmentStatus enum, Exam::scopeVisibleTo() enrollment-driven rewrite
  - phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix (plan 07)
    provides: DatabaseSeeder rewritten for section/enrollment demo graph, lecturer/grading test suite swept
provides:
  - Student-side test suite (9 files, 62 tests) swept off the dropped Classroom shape onto Section::factory() + enrollments()->attach()
  - ExamAccessTest/ExamIndexTest IDOR/visibility matrix expanded to cover enrolled/withdrawn/rejected/no-enrollment states
  - Phase 7 hard acceptance gate proven: clean migrate:fresh --seed + full suite green (183 passed) + ENR-08 list-vs-gate regression green
affects: [Phase 8 (enrollment apply/withdraw/capacity/window, exam availability, user manuals)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Student test fixture idiom (final leg of the sweep): Section::factory()->create() + exam->sections()->sync([...]) + section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled/Withdrawn/Rejected]) — completes the same idiom 07-07 established for lecturer/grading tests"
    - "IDOR denial-state coverage: where a v1 test only had one 'wrong classroom' denial case, the v2.0 equivalent enumerates the enrollment state space (not-enrolled, withdrawn, rejected) rather than collapsing to a single case"

key-files:
  created: []
  modified:
    - tests/Feature/Student/AttemptAnswerTest.php
    - tests/Feature/Student/AttemptPolicyTest.php
    - tests/Feature/Student/AttemptShowTest.php
    - tests/Feature/Student/AttemptStartTest.php
    - tests/Feature/Student/AttemptSubmitTest.php
    - tests/Feature/Student/ExamAccessTest.php
    - tests/Feature/Student/ExamIndexTest.php
    - tests/Feature/Student/Phase4ReviewFixesTest.php
    - tests/Feature/Student/ResultTest.php

key-decisions:
  - "ExamAccessTest's single 'no classroom' denial case was split into three explicit methods (withdrawn, rejected, no-enrollment) rather than one generic 'not enrolled' case, since the plan explicitly calls out that the denial semantics now span three distinct enrollment states rather than one absent-classroom state."
  - "Test method names updated where they described the dropped classroom shape (e.g. 'no classroom' -> 'no enrollment', 'different class' -> 'different section') so test names stay accurate to the v2.0 domain, even though this wasn't a strict acceptance-criteria requirement."

patterns-established: []

requirements-completed: [ENR-08, DEL-03]

# Metrics
duration: ~20min
completed: 2026-07-16
status: complete
---

# Phase 7 Plan 8: Student Test Sweep & Phase Acceptance Gate Summary

**Swept all 9 remaining student-side test files (62 tests) off the dropped Classroom/classroom_id shape onto Section::factory() + enrollments()->attach(), expanded ExamAccessTest/ExamIndexTest's IDOR denial matrix to enrolled/withdrawn/rejected/never-applied, then cleared the phase's hard atomic acceptance gate: clean `migrate:fresh --seed`, full suite green (183 passed, 479 assertions, 0 failures), and the ENR-08 list-vs-gate regression confirmed across all four enrollment states.**

## Performance

- **Duration:** ~20 min
- **Completed:** 2026-07-16T07:50:04Z
- **Tasks:** 2
- **Files modified:** 9 (student test sweep only; the gate task required zero code changes)

## Accomplishments
- Swept `AttemptAnswerTest`, `AttemptPolicyTest`, `AttemptShowTest`, `AttemptStartTest`, `AttemptSubmitTest`, `Phase4ReviewFixesTest`, `Student/ResultTest` onto the `Section::factory()` + `exam->sections()->sync()` + `section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled])` fixture idiom — the same pattern 07-07 established for lecturer/grading tests.
- Rebuilt `ExamAccessTest`'s and `ExamIndexTest`'s denial matrix: the old single "wrong classroom" / "no classroom" case is now three explicit enrollment-state cases (withdrawn, rejected, no-enrollment) plus the "different section" case, all still asserting server-side 403/exclusion.
- `php artisan test --filter=Student` GREEN: 62 passed, 124 assertions (includes the 4 `ExamVisibilityRegressionTest` data-provider cases, which live in `Student/` and matched the filter).
- **Phase acceptance gate cleared, zero code changes required:**
  - `php artisan migrate:fresh --seed` completed clean from an empty database (exit 0) — every migration ran, seeder populated the section/enrollment demo graph without error.
  - `php artisan test` (full suite): **183 passed, 479 assertions, 0 failures, 0 errors.**
  - `php artisan test --filter=ExamVisibilityRegressionTest`: **4 passed, 8 assertions** — enrolled (visible), withdrawn (hidden), rejected (hidden), never_applied (hidden), list and gate agree in every case.
- Confirmed no residual functional `Classroom`/`classroom_id`/`->classrooms()`/`exam_classroom` references anywhere in `app/`, `database/`, `tests/`, or `routes/` (a handful of historical doc-comment mentions of the word "Classroom" remain in `StoreSectionRequest`/`UpdateSectionRequest`/`SectionControllerTest`/`SubjectLecturerTest` docblocks — these are prose references to the superseded design, not code identifiers, and don't affect any test or runtime behavior).

## Task Commits

Each task was committed atomically:

1. **Task 1: Sweep student-side tests to the section/enrollment shape** - `59943bd` (test)
2. **Task 2: Phase acceptance gate — full suite + clean reseed + ENR-08 confirmation** - no commit (verification-only; the gate passed on the first run with zero code changes needed)

## Files Created/Modified
- `tests/Feature/Student/AttemptAnswerTest.php` - `mcqAttemptFixture()` swapped Classroom+classroom_id for Section::factory()+enrollments()->attach()
- `tests/Feature/Student/AttemptPolicyTest.php` - both students enrolled in the same section via enrollments()->attach()
- `tests/Feature/Student/AttemptShowTest.php` - all 4 methods swept to Section/enrollments (includes the FIX-01 answered-count assertion carried over from 07-01)
- `tests/Feature/Student/AttemptStartTest.php` - all 4 methods swept to Section/enrollments
- `tests/Feature/Student/AttemptSubmitTest.php` - both methods swept to Section/enrollments
- `tests/Feature/Student/ExamAccessTest.php` - rebuilt denial matrix: enrolled/different-section/unpublished/withdrawn/rejected/no-enrollment/lecturer-forbidden (7 methods, up from 5)
- `tests/Feature/Student/ExamIndexTest.php` - rebuilt to enrolled-sees-exam / unpublished-excluded / different-section-excluded / no-enrollment-empty-index (4 methods)
- `tests/Feature/Student/Phase4ReviewFixesTest.php` - `fixture()` swept to Section/enrollments
- `tests/Feature/Student/ResultTest.php` - `fixture()` swept to Section/enrollments, return type docblock updated from `Classroom` to `Section`

## Decisions Made
- Split the single v1 "wrong classroom" denial case in `ExamAccessTest` into three explicit enrollment-state methods (withdrawn, rejected, no-enrollment) since the plan's action block explicitly frames the v2.0 denial semantics as spanning three distinct states rather than one.
- Renamed test methods that referenced the dropped classroom concept in their names (not just their bodies) — e.g. `test_a_student_with_no_classroom_is_forbidden_direct_access` -> `test_a_student_with_no_enrollment_is_forbidden_direct_access` — for domain accuracy, even though the acceptance criteria only required no `Classroom`/`classroom_id` references in code, not test names.

## Deviations from Plan

None - plan executed exactly as written. The student sweep applied the canonical transformation from the plan's `<action>` block verbatim, and the phase acceptance gate passed on the first run with no residual classroom references, no render errors, and no failing tests requiring in-scope fixes.

## Issues Encountered

None. An initial IDE-diagnostics artifact flagged stale `Classroom`/`classrooms()` references in `ResultTest.php` after the `Write` call; a direct `grep` of the file on disk confirmed zero `Classroom` matches, and `php artisan test` for that file passed — the diagnostic was a stale pre-edit cache, not a real issue.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- The entire ~26-file v1-classroom-to-v2.0-section/enrollment sweep (migrations, models, controllers, routes, views, seeder, and now both the lecturer/grading and student test suites) is complete and proven: `php artisan migrate:fresh --seed` boots clean from an empty database, and `php artisan test` is green end to end (183 passed, 0 failures).
- ENR-08's core invariant — the exam list and the direct-access gate must always agree — is confirmed at phase close across all four enrollment states (enrolled/withdrawn/rejected/never-applied), closing out the phase's hard atomic acceptance gate per STATE.md's blocker note and 07-VALIDATION.md.
- Phase 7 (v2.0 Foundation — Admin Theme, Schema Break & Answered-Count Fix) is complete. Phase 8 (enrollment apply/withdraw/capacity/window/rejection, exam availability window + pre-start page + in-progress safeguards, and the two user manuals) can now build on a coherent, fully-tested section/enrollment foundation with no lingering classroom-shape debt.
- No blockers.

---
*Phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix*
*Completed: 2026-07-16*

## Self-Check: PASSED

All 9 modified student test files verified present on disk with zero `Classroom`/`classroom_id`/`->classrooms()`/`exam_classroom` references. Task 1 commit (`59943bd`) verified present in git log. `php artisan test --filter=Student` re-confirmed 62 passed; full `php artisan test` confirmed 183 passed, 0 failures; `php artisan test --filter=ExamVisibilityRegressionTest` confirmed 4 passed (ENR-08 gate). `php artisan migrate:fresh --seed` confirmed exit code 0 from an empty database.

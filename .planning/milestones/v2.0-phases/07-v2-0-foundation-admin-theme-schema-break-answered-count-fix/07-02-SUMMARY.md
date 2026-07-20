---
phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix
plan: 02
subsystem: testing
tags: [phpunit, red-tests, tdd, laravel, schema-break, authorization, exam-visibility]

# Dependency graph
requires:
  - phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix (plan 01)
    provides: Flowbite/dark-mode/FIX-01 foundation (view-only, independent of this plan's schema contract)
provides:
  - The Wave 0 RED test contract for the v2.0 schema-break slice (Section/Enrollment/EnrollmentStatus/subject_user)
  - ENR-08 hard acceptance gate (list-vs-gate agreement across 4 enrollment states)
  - SEC-01/SEC-02 section CRUD + computed-name + per-(subject,year,semester) sequence contract
  - SEC-03 per-subject lecturer-ownership authorization contract
  - Rewritten DomainSchemaTest/DatabaseSeederTest pinning the new table set and demo-graph shape
affects: [07-03, 07-04, 07-07]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "PHPUnit 11 #[DataProvider] attribute for table-driven cross-consumer regression tests (new to this codebase, first use)"

key-files:
  created:
    - tests/Feature/Student/ExamVisibilityRegressionTest.php
    - tests/Feature/Lecturer/SectionControllerTest.php
    - tests/Feature/Lecturer/SubjectLecturerTest.php
  modified:
    - tests/Feature/DomainSchemaTest.php
    - tests/Feature/DatabaseSeederTest.php

key-decisions:
  - "Copied the ENR-08 regression test near-verbatim from 07-RESEARCH.md Pattern 1, using raw string enrollment-status literals in the DataProvider rather than the EnrollmentStatus enum, keeping the data-provider callable itself free of a hard dependency on a symbol that doesn't exist yet"
  - "Assumed nested route names lecturer.subjects.sections.{store,update,destroy} and lecturer.subjects.lecturers.{store,destroy} per the plan's explicit route-name text and 07-PATTERNS.md's SubjectLecturerController design — these are the executable contract 07-04 must satisfy"
  - "SectionControllerTest and SubjectLecturerTest both scope negative-count assertions to Section::where('subject_id', ...) rather than a bare Section::count(), so per-test isolation holds even once other tests in the suite are seeding sections"

requirements-completed: [SEC-01, SEC-02, SEC-03, ENR-08, DEL-03]

# Metrics
duration: 15min
completed: 2026-07-16
status: complete
---

# Phase 7 Plan 2: v2.0 Schema-Break RED Test Contract Summary

**Five PHPUnit test files (three new, two rewritten) authoring the executable RED contract for sections/subject_user/enrollments — including the ENR-08 hard gate asserting the exam list and the direct-access policy never diverge across enrolled/withdrawn/rejected/never-applied students**

## Performance

- **Duration:** 15 min
- **Started:** 2026-07-16T05:31:00Z (approx.)
- **Completed:** 2026-07-16T05:46:33Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- Locked the ENR-08 "list and gate must always agree" invariant as a table-driven regression test (`#[DataProvider('enrollmentStates')]`) across all four enrollment states — the phase's single hard acceptance gate, per STATE.md's blocker note
- Pinned SEC-01/SEC-02 section CRUD behavior: create/edit/delete, capacity/window validation (blank capacity, `closes_at` before `opens_at`), the computed `year-semester-sequence` name accessor, and per-(subject,year,semester) sequence auto-increment
- Pinned SEC-03 per-subject lecturer ownership as a genuine new authorization shape (403 for a non-assigned lecturer on section create/edit and on lecturer assign/unassign), explicitly diverging from the existing D-09 `authorize(): bool { return true; }` convention
- Rewrote `DomainSchemaTest` to the new table set (`sections`, `subject_user`, `enrollments`, `exam_section`) plus a `classroom_id`-is-gone assertion and an `enrollments` unique(section_id,user_id) index assertion
- Rewrote `DatabaseSeederTest` to the section/subject_user/enrollment demo-graph shape while preserving the natural-key-lookup and idempotency conventions from the Phase 6 version

## Task Commits

Each task was committed atomically:

1. **Task 1: ENR-08 cross-consumer regression test (hard gate) + section CRUD test** - `b30fa4e` (test)
2. **Task 2: SEC-03 subject↔lecturer authorization test + schema/seeder test rewrites** - `be4c731` (test)

**Plan metadata:** (this commit, docs: complete plan)

_Note: This is a Wave 0 RED plan — every commit above is a `test(...)` commit; no `feat`/production-code commits exist in this plan by design._

## Files Created/Modified
- `tests/Feature/Student/ExamVisibilityRegressionTest.php` - NEW. `#[DataProvider('enrollmentStates')]` table-driven test asserting `Exam::visibleTo($student)->whereKey($exam->id)->exists()` equals `ExamPolicy::takeable()` across enrolled/withdrawn/rejected/never_applied.
- `tests/Feature/Lecturer/SectionControllerTest.php` - NEW. 7 tests: create/update/delete, blank-capacity validation, `closes_at`-before-`opens_at` validation, computed name (`2026-2-1` form), and per-(subject,year,semester) sequence auto-increment.
- `tests/Feature/Lecturer/SubjectLecturerTest.php` - NEW. 8 tests: assign a lecturer, multi-lecturer assignment, any-assigned-lecturer-can-manage-sections, non-assigned-lecturer denied (403) on section create/edit and on lecturer assign/unassign, and pivot-detach-on-unassign.
- `tests/Feature/DomainSchemaTest.php` - REWRITTEN. Table list changed to `sections`/`subject_user`/`enrollments`/`exam_section`; `classroom_id`-absent-from-users assertion; new `enrollments` unique(section_id,user_id) index assertion.
- `tests/Feature/DatabaseSeederTest.php` - REWRITTEN. Classroom-shaped assertions replaced with section/subject_user/enrollment demo-graph assertions (natural-key lookups); idempotency test updated to count `sections` instead of `classrooms`.

## Decisions Made
- Used raw string literals (`'enrolled'`, `'withdrawn'`, `'rejected'`, `'never_applied'`) in `ExamVisibilityRegressionTest::enrollmentStates()` rather than `EnrollmentStatus::Enrolled->value`, matching 07-RESEARCH.md Pattern 1's example verbatim — this keeps the PHPUnit data-provider callable's own execution free of a class-not-found error from a symbol that doesn't exist yet, isolating the RED failure to the actual test body (`Section` resolution) rather than data-provider collection.
- Assumed the nested route names `lecturer.subjects.sections.{store,update,destroy}` (explicit in the plan's Task 1 text) and `lecturer.subjects.lecturers.{store,destroy}` (inferred from 07-PATTERNS.md's `SubjectLecturerController@store/destroy` design, no explicit route name given in the plan) — flagging the latter as the concrete route-name contract 07-04's controller/routes must satisfy, since no other plan artifact pins it.
- Kept the existing `DomainSchemaTest` `attempts`/`answers` composite-unique-index tests untouched (verified they still pass against the current schema) — only the table list, the `classroom_id` assertion, and the new `enrollments` index assertion needed rewriting for the schema break.

## Deviations from Plan

None — plan executed exactly as written. No production code was written; all five files are RED test-only artifacts per the Wave 0 contract.

## Issues Encountered

None. Both tasks' automated verify commands (`php artisan test --filter=ExamVisibilityRegressionTest`, `--filter=SubjectLecturerTest`) ran without fatal parse errors, exactly as the plan's `<verification>` section specifies — every failure observed is an `Error`/failed-assertion on a missing symbol (`App\Models\Section`, `Subject::lecturers()`) or the old schema shape, never a syntax/typo error.

## RED Verification (this plan's actual acceptance gate)

Ran each new/rewritten test file individually, then the full suite, to confirm every RED failure traces to the expected missing-schema cause and nothing else in the suite regressed:

| File | Tests | Result | RED cause |
|------|-------|--------|-----------|
| `ExamVisibilityRegressionTest.php` | 4 | 4 errored | `Class "App\Models\Section" not found` |
| `SectionControllerTest.php` | 7 | 7 errored | `Class "App\Models\Section" not found` / `Call to undefined method App\Models\Subject::lecturers()` |
| `SubjectLecturerTest.php` | 8 | 8 errored | `Call to undefined method App\Models\Subject::lecturers()` / `Class "App\Models\Section" not found` |
| `DomainSchemaTest.php` | 5 | 3 failed, 2 passed | New table/column/index assertions fail on current schema; unchanged `attempts`/`answers` index assertions still pass (expected — untouched by this phase) |
| `DatabaseSeederTest.php` | 2 | 2 errored | `Call to undefined method App\Models\Subject::lecturers()` / `Class "App\Models\Section" not found` |

Full-suite run (`php artisan test`) after both commits: **24 failed, 173 passed (439 assertions)**. The 24 failures are exactly the sum of this plan's new/rewritten test methods (4+7+8+3+2=24) — confirming no pre-existing test regressed and this plan's RED contract is fully isolated.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- The executable RED contract for the schema-break slice is locked: 07-03 (migrations/models) and 07-04 (controllers/routes/`scopeVisibleTo()` rewrite) must turn `SectionControllerTest`, `SubjectLecturerTest`, `ExamVisibilityRegressionTest`, and `DomainSchemaTest` green; 07-07 (seeder rewrite) must turn `DatabaseSeederTest` green.
- 07-03/07-04 should treat the assumed route names (`lecturer.subjects.sections.*`, `lecturer.subjects.lecturers.*`) as the fixed contract, per this project's established "pinned RED test route() calls win over plan prose" precedent (see STATE.md Phase 05-04 decision).
- No blockers — this plan intentionally leaves the app in the same (pre-schema-break) runtime state; `migrate:fresh --seed` still works against the current v1 schema until 07-03 lands.

---
*Phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix*
*Completed: 2026-07-16*

## Self-Check: PASSED

All 5 created/modified test files verified present on disk; both task commit hashes (`b30fa4e`, `be4c731`) verified present in `git log --oneline --all`. No missing items.

---
phase: 03-exam-assignment-class-scoped-access
plan: 01
subsystem: testing
tags: [phpunit, laravel-feature-tests, idor, rbac, tdd-red]

# Dependency graph
requires:
  - phase: 02-classroom-subject-exam-authoring
    provides: Exam/Classroom/User models, exam_classroom pivot, ExamFactory/UserFactory/ClassroomFactory, role:lecturer/role:student middleware
provides:
  - "The full executable acceptance contract (14 cases) for ASN-01, ASN-02, and RBAC-05, as failing (RED) feature tests"
  - "The `wave_0_complete` Nyquist verification harness for Phase 3 — a fixed, pre-committed test contract that 03-02/03-03 implementation is measured against"
affects: [03-02-assignment-implementation, 03-03-student-access-implementation]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "RED test-first contract authoring: feature tests reference route names (`lecturer.exams.assignment.update`, `student.exams.index`, `student.exams.show`) not yet registered, so RouteNotFoundException is the expected/correct RED failure mode until Wave 2 lands"

key-files:
  created:
    - tests/Feature/Lecturer/ExamAssignmentTest.php
    - tests/Feature/Student/ExamIndexTest.php
    - tests/Feature/Student/ExamAccessTest.php
  modified: []

key-decisions:
  - "All 14 test method names copied verbatim from 03-VALIDATION.md's Per-Task Verification Map so downstream --filter gates in 03-02/03-03 match exactly"
  - "No production code, routes, controllers, or policies were written in this plan — only test files, per D-06/plan scope"

patterns-established:
  - "Shared-predicate IDOR test shape: ExamIndexTest and ExamAccessTest both arrange the same four fixture combinations (published+assigned, unpublished+assigned, published+other-class, published+null-classroom) but assert list-visibility vs direct-access outcomes respectively — this pairing is the concrete proof surface for the scopeVisibleTo() single-source-of-truth pattern 03-03 will implement"

requirements-completed: [ASN-01, ASN-02, RBAC-05]

# Metrics
duration: 8min
completed: 2026-07-15
status: complete
---

# Phase 3 Plan 01: Wave-0 RED Test Infrastructure Summary

**Three feature test files (14 cases) encoding the full ASN-01 assignment contract, ASN-02 index-visibility contract, and RBAC-05 IDOR matrix as RED tests, ahead of any Wave-2 implementation.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-07-15T15:26:00Z (approx.)
- **Completed:** 2026-07-15T15:34:22Z
- **Tasks:** 3 completed
- **Files modified:** 3 created

## Accomplishments
- `tests/Feature/Lecturer/ExamAssignmentTest.php` — 5 cases covering `sync()` assignment, re-sync detach, non-existent classroom_id rejection, draft-assignable-before-publish, and student-forbidden-from-assignment
- `tests/Feature/Student/ExamIndexTest.php` — 4 cases covering published+assigned visibility, unpublished-but-assigned exclusion, other-classroom exclusion, and the null-classroom empty-index guard
- `tests/Feature/Student/ExamAccessTest.php` — 5 cases forming the full RBAC-05 IDOR matrix (200/403/403/403/403): assigned+published 200, different-class 403, unpublished-but-assigned 403, null-classroom direct-access 403, lecturer-on-student-routes 403
- All 14 new tests confirmed RED for the correct reason (`RouteNotFoundException` on the not-yet-registered `lecturer.exams.assignment.update` / `student.exams.index` / `student.exams.show` route names) — never a syntax or PHPUnit-collection error
- Full suite run (`php artisan test`) confirms exactly 14 failed / 121 passed — the pre-existing Phase 1/2 suite is untouched and green; only the three new files are RED

## Task Commits

Each task was committed atomically:

1. **Task 1: ExamAssignmentTest — the ASN-01 assignment contract (RED)** - `34fd8c8` (test)
2. **Task 2: ExamIndexTest — the ASN-02 student-list contract (RED)** - `acc37f0` (test)
3. **Task 3: ExamAccessTest — the RBAC-05 IDOR matrix (RED)** - `4d7535f` (test)

_Note: These are plain RED authoring tasks (no GREEN/REFACTOR expected in this plan) — Wave 2 plans (03-02, 03-03) will add the corresponding `feat` commits that turn these RED._

## Files Created/Modified
- `tests/Feature/Lecturer/ExamAssignmentTest.php` - 5 ASN-01 cases; asserts `exam_classroom` pivot rows via `assertDatabaseHas`/`assertDatabaseMissing` and `assertSessionHasErrors('classroom_ids.0')` for the invalid-id case
- `tests/Feature/Student/ExamIndexTest.php` - 4 ASN-02 cases; asserts on rendered content (`assertSee`/`assertDontSee`) against `route('student.exams.index')`
- `tests/Feature/Student/ExamAccessTest.php` - 5 RBAC-05 cases; the full IDOR matrix hitting `route('student.exams.show', $exam)` directly, plus the lecturer-on-student-routes case hitting both `student.exams.index` and `student.exams.show`

## Decisions Made
- Followed the plan's exact test method names, file paths, and namespaces (`Tests\Feature\Lecturer`, `Tests\Feature\Student`) with no deviation
- Used only existing factories (`User::factory()->lecturer()/student()`, `Exam::factory()->published()`, `Classroom::factory()`) — no new factory states or fixtures were needed, confirmed by RESEARCH.md and by mirroring the analog test files' conventions
- No production code (routes, controllers, policies, Form Requests) was written — this plan is Wave 0, test infrastructure only

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. All three files passed `php -l` on first write, and `php artisan test --filter=<ClassName>` discovered the exact expected case counts (5, 4, 5) on first run, each failing with `RouteNotFoundException` — the intended RED state, not an unexpected error.

## User Setup Required

None - no external service configuration required.

## Known Stubs

None. This plan is test-only; no UI/data-rendering code was introduced.

## Threat Flags

None. No new runtime surface was introduced — this plan only adds test files that assert against surface Wave 2 (03-02/03-03) will build. See the plan's `<threat_model>` (all `verify-here` dispositions) for the mapping from each test case to the phase's STRIDE threat register.

## Next Phase Readiness
- Wave 0 complete: the full 14-case executable contract for ASN-01/ASN-02/RBAC-05 is committed and RED for the correct reason
- Plan 03-02 (lecturer assignment: `ExamAssignmentController`, `AssignExamRequest`, `lecturer.exams.assignment.update` route) can now target `php artisan test --filter=ExamAssignmentTest` as its precise GREEN gate
- Plan 03-03 (student access: `Student\ExamController`, `Exam::scopeVisibleTo()`, `ExamPolicy::takeable()`, `student.exams.index`/`student.exams.show` routes) can now target `php artisan test --filter=ExamIndexTest` and `--filter=ExamAccessTest` as its precise GREEN gates
- No blockers

---
*Phase: 03-exam-assignment-class-scoped-access*
*Completed: 2026-07-15*

## Self-Check: PASSED

All created files found on disk; all three task commit hashes (34fd8c8, acc37f0, 4d7535f) found in git log.

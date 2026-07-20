---
phase: 06-demo-seeder-delivery
plan: 01
subsystem: testing
tags: [phpunit, laravel-seeder, tdd-red, database-testing]

# Dependency graph
requires:
  - phase: 05-grading-results
    provides: AttemptGrader service, Attempt/Answer models with status/score semantics
provides:
  - Fixed executable RED contract for the expanded demo-seeder graph (tests/Feature/DatabaseSeederTest.php)
  - Fixed executable RED contract for the project README content (tests/Feature/ReadmeTest.php)
  - Consolidation of TestAccountSeederTest.php's coverage into the new graph test
affects: [06-02-seeder-implementation, 06-03-readme, 06-04-clean-clone-gate]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Seeder test asserts strictly by natural/business key (email, classroom name, subject code, exam title), never numeric id — MySQL auto-increment does not reset between seed runs"
    - "README content pinned via assertStringContainsString on exact required substrings rather than full-text matching"

key-files:
  created:
    - tests/Feature/DatabaseSeederTest.php
    - tests/Feature/ReadmeTest.php
  modified: []

key-decisions:
  - "Deleted tests/Feature/TestAccountSeederTest.php — its two assertions (verified lecturer + classroom-assigned student, idempotency) are a strict subset of the new DatabaseSeederTest graph assertions; kept as a single seeder test file per 06-RESEARCH.md Wave 0 Gaps recommendation"
  - "test_seeder_is_idempotent_on_repeat_runs currently passes even before the seeder is expanded, since the minimal Phase-1 seeder is already idempotent — this is expected; the test becomes meaningful once 06-02 expands the graph"

patterns-established:
  - "RED-first feature tests for delivery artifacts (seeder graph, README) are authored as their own Wave 0 plan before any implementation, pinning the exact demo-graph shape and README substrings as a fixed contract"

requirements-completed: [DEL-01, DEL-02]

# Metrics
duration: 4min
completed: 2026-07-15
status: complete
---

# Phase 06 Plan 01: RED Seeder + README Contract Summary

**Wave-0 RED tests pinning the expanded demo-seeder graph (lecturer + 3 students, 2 classrooms, 2 subjects, a published MCQ+open-text exam scoped to a classroom, and a pre-graded submitted demo attempt) and the delivered README content, both failing as intended against the current minimal Phase-1 seeder and Laravel-default README.**

## Performance

- **Duration:** 4 min
- **Started:** 2026-07-15T20:36:52Z
- **Completed:** 2026-07-15T20:40:24Z
- **Tasks:** 2 completed
- **Files modified:** 3 (2 created, 1 deleted)

## Accomplishments
- `tests/Feature/DatabaseSeederTest.php` created with `test_seeder_builds_full_demo_graph` (asserts the full demo graph strictly by natural key: 4 users with verified emails/roles, 2 classrooms with correct student assignment, 2 subjects linked via `classroom_subject`, a published 30-minute "Mathematics Midterm" exam with exactly one MCQ (≥2 options, exactly one correct) and one open question, `exam_classroom` scoping (assigned to Demo Classroom, NOT Advanced Classroom), and a `submitted` demo attempt for student2 with the MCQ answer graded correct and the open-text answer still pending/null-score) and `test_seeder_is_idempotent_on_repeat_runs` (asserts row counts for all 8 entity tables are unchanged across a second seed run)
- Confirmed `test_seeder_builds_full_demo_graph` fails RED against the current minimal seeder (`ModelNotFoundException: No query results for model [App\Models\User]` looking up `student2@example.com`, which the minimal seeder doesn't create) — a real, non-vacuous contract
- `tests/Feature/ReadmeTest.php` created with `test_readme_documents_setup_and_credentials`, asserting `README.md` exists and contains: `Online Examination Portal`, `composer install`, `npm install`, `yp-student-exam`, `migrate:fresh --seed`, `lecturer@example.com`, `student@example.com`
- Confirmed `test_readme_documents_setup_and_credentials` fails RED against the current Laravel-default README (none of the required substrings present)
- Deleted `tests/Feature/TestAccountSeederTest.php` — its two assertions are a strict subset of the new graph test, consolidating seeder test coverage into one file per 06-RESEARCH.md's recommendation
- Verified the rest of the Phase 1-5 suite remains green: `php artisan test` reports 174 passed / 2 failed (the two new intentional RED tests) — no regressions from the consolidation

## Task Commits

Each task was committed atomically:

1. **Task 1: DatabaseSeederTest.php — full-graph + idempotency RED contract** - `4ece63d` (test)
2. **Task 2: ReadmeTest.php — README content RED contract** - `0ba84ee` (test)

**Plan metadata:** (pending — final commit below)

## Files Created/Modified
- `tests/Feature/DatabaseSeederTest.php` - RED contract: full demo graph (users/classrooms/subjects/exam/questions/options/pivots/pre-graded attempt) + idempotency, asserted by natural key
- `tests/Feature/ReadmeTest.php` - RED contract: README.md exists and documents title/setup commands/DB name/seed command/demo credentials
- `tests/Feature/TestAccountSeederTest.php` - deleted (consolidated into DatabaseSeederTest.php)

## Decisions Made
- Consolidated `TestAccountSeederTest.php` into `DatabaseSeederTest.php` rather than keeping both, per 06-RESEARCH.md's explicit recommendation, to avoid two overlapping seeder test files and a duplicate `test_seeder_is_idempotent_on_repeat_runs` method name across classes.
- Used `firstOrFail()`/`where()` lookups by email, classroom name, subject code, and exam title throughout — never a numeric id — per 06-RESEARCH.md Pitfall 3 (MySQL auto-increment does not reset between `RefreshDatabase` transactions within the same test-suite run).
- Asserted the MCQ answer's `score` equals the question's `points` (rather than a fixed literal) and the open answer's `score` is `null`, matching `AttemptGrader::gradeAutoGradable()`/`syncStatus()` semantics exactly as documented in 06-RESEARCH.md Pattern 4.

## Deviations from Plan

None - plan executed exactly as written. Both test files use the exact method names pinned in 06-VALIDATION.md (`test_seeder_builds_full_demo_graph`, `test_seeder_is_idempotent_on_repeat_runs`, `test_readme_documents_setup_and_credentials`) and assert exactly the demo-graph shape and README substrings specified in the plan and research documents. No production code (seeder or README) was touched in this wave — both RED failures are for the correct, expected reason (missing seeder expansion / Laravel-default README).

## Issues Encountered

None. Both new tests failed RED on the first run for the expected reason (`test_seeder_builds_full_demo_graph`: missing `student2@example.com`/graph rows against the minimal seeder; `test_readme_documents_setup_and_credentials`: missing all required substrings against the Laravel-default README), confirming a real executable contract rather than a vacuous or malformed test.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The RED contract is locked: 06-02 (seeder expansion) must make `test_seeder_builds_full_demo_graph` and `test_seeder_is_idempotent_on_repeat_runs` pass without altering their assertions; 06-03 (README) must make `test_readme_documents_setup_and_credentials` pass without altering its assertions.
- Full suite currently sits at 174 passed / 2 failed (both intentional RED, expected to flip green in Wave 2). No blockers.

---
*Phase: 06-demo-seeder-delivery*
*Completed: 2026-07-15*

## Self-Check: PASSED

- FOUND: tests/Feature/DatabaseSeederTest.php
- FOUND: tests/Feature/ReadmeTest.php
- CONFIRMED DELETED: tests/Feature/TestAccountSeederTest.php
- FOUND commit: 4ece63d
- FOUND commit: 0ba84ee

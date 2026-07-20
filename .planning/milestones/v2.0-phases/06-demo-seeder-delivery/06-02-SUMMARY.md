---
phase: 06-demo-seeder-delivery
plan: 02
subsystem: database
tags: [laravel-seeder, eloquent, firstOrCreate, attempt-grading, idempotency]

# Dependency graph
requires:
  - phase: 06-demo-seeder-delivery
    provides: "06-01's RED DatabaseSeederTest (full-graph + idempotency contract) that this plan makes GREEN"
  - phase: 05-grading-results
    provides: AttemptGrader service (gradeAutoGradable/syncStatus), Attempt/Answer models with status/score semantics
provides:
  - Expanded, idempotent DatabaseSeeder::run() building the full DEL-01 demo graph (4 users, 2 classrooms, 2 subjects, 1 published exam with MCQ+open questions)
  - One pre-graded submitted demo attempt for student2 (D-02), seeded directly via AttemptGrader (never HTTP)
  - Verified `php artisan migrate:fresh --seed` and repeat `php artisan db:seed` both work cleanly against live MySQL
affects: [06-03-readme, 06-04-clean-clone-gate]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Parent entities (users, classrooms, subjects, exam) use firstOrCreate on natural keys (email/name/code/title-pair) — never updateOrCreate, which would clobber a reviewer's manual edits on re-seed"
    - "Pivots (classroom_subject, exam_classroom) linked via ->sync() — idempotent by construction, safe to always re-run"
    - "Question/Option child rows (no unique index) guarded by $exam->wasRecentlyCreated rather than a brittle firstOrCreate on body text"
    - "Demo attempt seeded directly via Eloquent + app(AttemptGrader::class)->gradeAutoGradable()/->syncStatus(), reusing the exact production grading service instead of a parallel seeder-local implementation"

key-files:
  created: []
  modified:
    - database/seeders/DatabaseSeeder.php

key-decisions:
  - "Split the single-file implementation into two atomic commits (graph, then attempt) by temporarily stripping/restoring the seedDemoAttempt() code between commits, since both plan tasks touch the same file"
  - "Left student@example.com (Demo Student) with zero attempts so a reviewer can take the exam fresh, per D-02"
  - "Left the open-text answer's score null so AttemptGrader::syncStatus() correctly keeps the attempt at 'submitted' rather than 'graded' — this is the intended demo state (lecturer grading queue has live content)"

patterns-established:
  - "Seeder demo-attempt construction: build Attempt+Answer rows directly via Eloquent with status set to 'submitted' up front, then call the real grading service exactly as production finalize does — never simulate an HTTP submit from a seeder"

requirements-completed: [DEL-01]

# Metrics
duration: 18min
completed: 2026-07-16
status: complete
---

# Phase 06 Plan 02: Full Demo Graph + Pre-Graded Attempt Summary

**Expanded `DatabaseSeeder::run()` from Phase 1's minimal lecturer+student pair into the complete idempotent DEL-01 demo graph (4 users, 2 classrooms, 2 subjects, 1 published MCQ+open-text exam) plus one pre-graded submitted attempt seeded by calling `AttemptGrader` directly.**

## Performance

- **Duration:** 18 min
- **Started:** 2026-07-16T00:00:00Z
- **Completed:** 2026-07-16T00:18:00Z
- **Tasks:** 2 completed
- **Files modified:** 1

## Accomplishments
- `DatabaseSeeder::run()` now builds: 1 Lecturer + 3 verified Students (fixed emails, `email_verified_at` set on every account), 2 Classrooms ("Demo Classroom", "Advanced Classroom") with students assigned via `classroom_id`, 2 Subjects ("Mathematics"/MATH101, "Science"/SCI101) linked via `classroom_subject` `sync()`, and 1 published "Mathematics Midterm" exam (30-minute duration) with one MCQ question (4 options, exactly one correct) and one open-text question, assigned to Demo Classroom only via `exam_classroom` `sync()` — Advanced Classroom deliberately excluded to demonstrate class-scoped denial (ASN-02/RBAC-05)
- Question/Option child rows guarded by `$exam->wasRecentlyCreated` (no unique index on those tables) rather than a brittle text-based `firstOrCreate`
- Added `seedDemoAttempt()`: one `submitted` `Attempt` for `student2@example.com` on the Mathematics Midterm, with a correct MCQ `Answer` and an ungraded open-text `Answer`, graded by calling `app(AttemptGrader::class)->gradeAutoGradable($attempt)` then `->syncStatus($attempt)` — exactly the production finalize path, never a simulated HTTP submit
- `student@example.com` (Demo Student) intentionally left with zero attempts so a reviewer can take the exam fresh
- Verified `php artisan test --filter=DatabaseSeederTest` is fully GREEN (both `test_seeder_builds_full_demo_graph` and `test_seeder_is_idempotent_on_repeat_runs`, 32 assertions)
- Verified `php artisan test` (full suite): 175 passed / 1 failed — the 1 failure is `ReadmeTest` (expected RED, deferred to 06-03), no regressions
- Manually ran `php artisan migrate:fresh --seed` against the live MySQL `yp-student-exam` database — completed with no errors; confirmed via `tinker` the graph counts exactly match (4 users, 2 classrooms, 2 subjects, 1 exam, 2 questions, 4 options, 1 attempt, 2 answers, attempt status `submitted`)
- Manually ran `php artisan db:seed` a second time (no fresh) against the same live database — row counts stayed identical, confirming idempotency outside the test harness too

## Task Commits

Each task was committed atomically:

1. **Task 1: Expand DatabaseSeeder::run() into the full idempotent demo graph** - `234f2c2` (feat)
2. **Task 2: Seed one pre-graded submitted demo attempt via AttemptGrader** - `a472678` (feat)

**Plan metadata:** (pending — final commit below)

## Files Created/Modified
- `database/seeders/DatabaseSeeder.php` - Expanded from the Phase-1 minimal lecturer+student pair into the full idempotent DEL-01 demo graph (users/classrooms/subjects/exam/questions/options/pivots) plus a pre-graded submitted demo attempt via `AttemptGrader`

## Decisions Made
- Split the implementation into two atomic commits matching the plan's two tasks, even though both touch the same file: temporarily removed `seedDemoAttempt()` and its call site to produce a clean "Task 1 only" working tree, committed, then restored the removed code for the "Task 2" commit. Both intermediate and final states were independently test-verified before committing.
- Used private helper methods per entity group (`seedLecturer`, `seedStudents`, `seedSubjects`, `seedExam`, `seedDemoAttempt`) in a single file — no sub-seeder classes — per D-01/06-RESEARCH.md's explicit Claude's Discretion recommendation.
- Kept the MCQ question's 4 options (matching `QuestionFactory::mcq()`'s existing shape) rather than the plan's stated minimum of 2, for a more realistic demo exam while still satisfying "≥2 options, exactly one correct."

## Deviations from Plan

None - plan executed exactly as written. Every natural key, credential, and demo-state detail (emails, classroom names, subject codes, exam title/duration, question bodies, attempt/answer states) matches the plan's D-01/D-02/D-03 specification and the pinned `DatabaseSeederTest` contract exactly.

## Issues Encountered

None. Both `firstOrCreate`-based graph construction and the `AttemptGrader`-based attempt seeding worked as designed on the first implementation pass — `test_seeder_is_idempotent_on_repeat_runs` passed immediately after Task 1, and both `DatabaseSeederTest` methods passed immediately after Task 2, with no debugging iterations required.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `DatabaseSeeder` now produces the complete, verified demo graph and pre-graded attempt that 06-03's README will document credentials for, and that 06-04's clean-clone gate will re-verify end-to-end.
- `AppServiceProvider::boot()` / `Schema::defaultStringLength(191)` untouched, per Pitfall 6.
- Full suite sits at 175 passed / 1 failed (the intentional `ReadmeTest` RED, expected to flip green in 06-03). No blockers.

---
*Phase: 06-demo-seeder-delivery*
*Completed: 2026-07-16*

## Self-Check: PASSED

- FOUND: database/seeders/DatabaseSeeder.php
- FOUND commit: 234f2c2
- FOUND commit: a472678

---
phase: 01-foundation-domain-schema-role-based-access-control
plan: 01
subsystem: database
tags: [laravel, migrations, mysql, schema, phpunit]

# Dependency graph
requires: []
provides:
  - 9 domain tables (classrooms, subjects, classroom_subject, exams, questions, options, exam_classroom, attempts, answers)
  - users table extension (role, classroom_id) via a new non-destructive migration
  - Schema::defaultStringLength(191) portability guard in AppServiceProvider
  - Composite unique constraints baked into first migration version: attempts(exam_id,user_id), answers(attempt_id,question_id)
  - DomainSchemaTest proving the full schema shape
affects: [01-02-models-and-enums, 01-03-rbac-middleware, 01-04-registration-and-seeding]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Extend Breeze's users table via a NEW migration (Schema::table), never edit 0001_01_01_000000_create_users_table.php in place"
    - "Bake composite unique constraints into the first version of a create migration, never retrofit via ALTER"
    - "Pivot table naming resolved explicitly: classroom_subject (Eloquent alphabetical convention, no override needed) vs exam_classroom (kept per ROADMAP name, requires explicit override in belongsToMany)"

key-files:
  created:
    - database/migrations/2026_07_15_100001_create_classrooms_table.php
    - database/migrations/2026_07_15_100002_create_subjects_table.php
    - database/migrations/2026_07_15_100003_add_role_and_classroom_id_to_users_table.php
    - database/migrations/2026_07_15_100004_create_classroom_subject_table.php
    - database/migrations/2026_07_15_100005_create_exams_table.php
    - database/migrations/2026_07_15_100006_create_questions_table.php
    - database/migrations/2026_07_15_100007_create_options_table.php
    - database/migrations/2026_07_15_100008_create_exam_classroom_table.php
    - database/migrations/2026_07_15_100009_create_attempts_table.php
    - database/migrations/2026_07_15_100010_create_answers_table.php
    - tests/Feature/DomainSchemaTest.php
  modified:
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "Migration timestamps 2026_07_15_100001..100010 order parent-before-child so every FK resolves at migrate time (classrooms -> users-extension -> subjects... -> attempts -> answers)"
  - "attempts.unique(exam_id,user_id) and answers.unique(attempt_id,question_id) are in the original create migration, not a follow-up ALTER (D-02)"
  - "classroom_subject pivot uses Eloquent's alphabetical-singular convention (Classroom+Subject) so belongsToMany needs no override in plan 01-02; exam_classroom keeps its ROADMAP name and will need an explicit override"

patterns-established:
  - "Every new migration this phase places timestamps() plus the specific unique/foreign key constraints directly on the create Blueprint, matching the RESEARCH.md verified code examples"

requirements-completed: [RBAC-01]

# Metrics
duration: 3min
completed: 2026-07-15
status: complete
---

# Phase 1 Plan 1: Domain Schema Foundation Summary

**10 ordered Laravel migrations delivering all 9 domain tables plus the users role/classroom_id extension, with both single-attempt and single-answer composite unique constraints baked in from v1, proven by a 4-assertion DomainSchemaTest against the live MySQL `yp-student-exam` database.**

## Performance

- **Duration:** ~3 min (git commit timestamps: 20:13:47 to 20:15:38 local)
- **Started:** 2026-07-15T12:13:00Z (approx, first commit 12:13:47Z local+8)
- **Completed:** 2026-07-15T12:16:06Z
- **Tasks:** 3
- **Files modified:** 12 (1 modified, 11 created)

## Accomplishments
- Added `Schema::defaultStringLength(191)` in `AppServiceProvider::boot()` (D-03 portability guard)
- Delivered all 9 domain tables (classrooms, subjects, classroom_subject, exams, questions, options, exam_classroom, attempts, answers) as ordered migrations with FKs resolving cleanly on a fresh `migrate:fresh` against the live MySQL DB
- Extended `users` non-destructively (new migration, Breeze's original migration untouched) with `role` (default `student`) and nullable `classroom_id` FK to `classrooms` with `nullOnDelete()`
- Baked in both correctness-critical composite unique constraints from the first migration version: `attempts(exam_id,user_id)` and `answers(attempt_id,question_id)`
- `DomainSchemaTest` (4 tests, 13 assertions) proves the full schema shape: all 9 tables, both users columns, both unique composite indexes

## Task Commits

Each task was committed atomically:

1. **Task 1: Portability guard + classrooms, subjects, and users-extension migrations** - `e5886fe` (feat)
2. **Task 2: classroom_subject pivot, exams, questions, options migrations** - `3758e70` (feat)
3. **Task 3: exam_classroom, attempts, answers migrations + DomainSchemaTest** - `9fe5681` (test)

## Files Created/Modified
- `app/Providers/AppServiceProvider.php` - Adds `Schema::defaultStringLength(191)` guard in `boot()`
- `database/migrations/2026_07_15_100001_create_classrooms_table.php` - `classrooms`: id, name unique, timestamps
- `database/migrations/2026_07_15_100002_create_subjects_table.php` - `subjects`: id, name, code unique nullable, timestamps
- `database/migrations/2026_07_15_100003_add_role_and_classroom_id_to_users_table.php` - Extends `users` with `role` (default student) and nullable `classroom_id` FK
- `database/migrations/2026_07_15_100004_create_classroom_subject_table.php` - Pivot: classroom_id/subject_id, unique composite, cascade delete
- `database/migrations/2026_07_15_100005_create_exams_table.php` - `exams`: subject_id/created_by FKs, title, description, duration_minutes, is_published
- `database/migrations/2026_07_15_100006_create_questions_table.php` - `questions`: exam_id FK, type, body, points default 1, position
- `database/migrations/2026_07_15_100007_create_options_table.php` - `options`: question_id FK, body, is_correct default false, position
- `database/migrations/2026_07_15_100008_create_exam_classroom_table.php` - Pivot: exam_id/classroom_id, unique composite, cascade delete
- `database/migrations/2026_07_15_100009_create_attempts_table.php` - `attempts`: exam_id/user_id FKs, started_at/submitted_at, status, score, `unique(exam_id,user_id)` (D-02)
- `database/migrations/2026_07_15_100010_create_answers_table.php` - `answers`: attempt_id/question_id/selected_option_id FKs, answer_text, is_correct, score, `unique(attempt_id,question_id)` (D-02)
- `tests/Feature/DomainSchemaTest.php` - Asserts all 9 tables, users role/classroom_id columns, and both composite unique indexes exist

## Decisions Made
- Followed the plan's pivot naming resolution exactly: `classroom_subject` (Eloquent-convention name for Classroom+Subject, no override needed later) vs `exam_classroom` (kept per ROADMAP, will need explicit `belongsToMany` override in plan 01-02)
- Migration down() for the users-extension migration drops the FK constraint before dropping the columns, matching Laravel's constrained-column teardown order

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Schema foundation is complete and verified against the live MySQL `yp-student-exam` database via a genuine `migrate:fresh` from empty.
- Plan 01-02 can now build `App\Enums\Role`/`App\Enums\QuestionType`, the 7 domain models + extended `User`, and `ClassroomFactory` directly on this stable schema without any table/column changes.
- The `classroom_subject` (no override) vs `exam_classroom` (explicit override) pivot naming distinction is settled and must be honored in plan 01-02's `belongsToMany()` calls.

---
*Phase: 01-foundation-domain-schema-role-based-access-control*
*Completed: 2026-07-15*

## Self-Check: PASSED

All 13 created/modified files confirmed present on disk; all 3 task commits (`e5886fe`, `3758e70`, `9fe5681`) confirmed in git log.

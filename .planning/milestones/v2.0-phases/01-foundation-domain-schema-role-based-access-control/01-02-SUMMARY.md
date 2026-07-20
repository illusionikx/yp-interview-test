---
phase: 01-foundation-domain-schema-role-based-access-control
plan: 02
subsystem: database
tags: [laravel, eloquent, enums, rbac, phpunit]

# Dependency graph
requires:
  - phase: 01-01
    provides: 9 domain tables + users.role/classroom_id columns, DomainSchemaTest proving the schema shape
provides:
  - "App\\Enums\\Role and App\\Enums\\QuestionType backed enums (D-04)"
  - "User::casts() role => Role::class, classroom() relation (D-05), isLecturer()/isStudent() helpers"
  - "7 new Eloquent models (Classroom, Subject, Exam, Question, Option, Attempt, Answer) exposing the full ARCHITECTURE.md relationship graph"
  - "ClassroomFactory fixture"
  - "UserRoleCastTest proving the RBAC-01 enum-cast round-trip"
affects: [01-03-rbac-middleware, 01-04-registration-and-seeding]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Laravel 11 casts() method form for all new enum/boolean/decimal/datetime model casts (no $casts property introduced)"
    - "classroom_subject pivot uses Eloquent convention (no belongsToMany override); exam_classroom pivot requires an explicit belongsToMany(Model::class, 'exam_classroom') override on both sides (Classroom::exams() and Exam::classrooms())"
    - "role/classroom_id are fillable on User for server-controlled writes only — public request input must never populate them (T-01-02-MA, enforced by comment + deferred to 01-04's registration hardcode)"

key-files:
  created:
    - app/Enums/Role.php
    - app/Enums/QuestionType.php
    - app/Models/Classroom.php
    - app/Models/Subject.php
    - app/Models/Exam.php
    - app/Models/Question.php
    - app/Models/Option.php
    - app/Models/Attempt.php
    - app/Models/Answer.php
    - database/factories/ClassroomFactory.php
    - tests/Unit/UserRoleCastTest.php
  modified:
    - app/Models/User.php

key-decisions:
  - "role and classroom_id added to User::$fillable to support seeder/factory writes, with an explicit code comment marking them off-limits to any public request-sourced write path (T-01-02-MA mitigation)"
  - "Attempt.status stays a plain string column this phase — no enum, no lifecycle state machine (deferred to Phase 4 per plan's explicit scope boundary)"

patterns-established:
  - "Every relationship method name matches ARCHITECTURE.md's entity-relationship summary exactly; pivot naming (classroom_subject vs exam_classroom) is resolved once in 01-01 and honored consistently across every belongsToMany() call in this plan"

requirements-completed: [RBAC-01]

# Metrics
duration: 4min
completed: 2026-07-15
status: complete
---

# Phase 1 Plan 2: Domain Model Layer & Role Enum Summary

**Role/QuestionType backed enums, User extended with a type-safe role cast plus isLecturer()/isStudent() helpers and a classroom() relation, and the full 7-model Eloquent relationship graph (Classroom, Subject, Exam, Question, Option, Attempt, Answer) with correct pivot names and casts, proven by a green UserRoleCastTest and a full 31-test suite pass.**

## Performance

- **Duration:** ~4 min (from first commit to last domain-model commit)
- **Started:** 2026-07-15T12:27:42Z
- **Completed:** 2026-07-15T12:31:17Z
- **Tasks:** 3
- **Files modified:** 12 (1 modified, 11 created)

## Accomplishments
- `App\Enums\Role` (`Lecturer`/`Student`) and `App\Enums\QuestionType` (`Mcq`/`Open`) backed enums (D-04)
- `User` extended: `casts()` maps `role => Role::class`; `classroom(): BelongsTo` relation (D-05); `isLecturer()`/`isStudent()` helper methods; `role`/`classroom_id` added to `$fillable` for server-controlled writes only, with an explicit mass-assignment-safety comment (T-01-02-MA)
- 7 new models — `Classroom`, `Subject`, `Exam`, `Question`, `Option`, `Attempt`, `Answer` — expose the complete ARCHITECTURE.md relationship graph, with `exam_classroom` explicitly overridden as the pivot table on both `Classroom::exams()` and `Exam::classrooms()`
- `ClassroomFactory` fixture ready for 01-03/01-04
- `UserRoleCastTest` proves the enum-cast round-trip through `create()` + reload, and the `isLecturer()`/`isStudent()` helper booleans (RBAC-01)
- Full test suite green: 31 tests, 80 assertions (includes the pre-existing Breeze auth tests, `DomainSchemaTest` from 01-01, and the two new `UserRoleCastTest` cases)

## Task Commits

Each task was committed atomically:

1. **Task 1: Role + QuestionType enums, User extension, ClassroomFactory, UserRoleCastTest** - `d19b020` (test)
2. **Task 2: Classroom, Subject, Exam, Question models with relationships** - `0cbe4e0` (feat)
3. **Task 3: Option, Attempt, Answer models with relationships** - `04fb529` (feat)

## Files Created/Modified
- `app/Enums/Role.php` - Backed enum: `Lecturer = 'lecturer'`, `Student = 'student'` (D-04)
- `app/Enums/QuestionType.php` - Backed enum: `Mcq = 'mcq'`, `Open = 'open'`
- `app/Models/User.php` - Adds `role => Role::class` cast, `role`/`classroom_id` fillable (with mass-assignment-safety comment), `classroom()` belongsTo, `isLecturer()`/`isStudent()` helpers
- `app/Models/Classroom.php` - `users()` hasMany, `subjects()` belongsToMany (convention), `exams()` belongsToMany (explicit `exam_classroom` pivot)
- `app/Models/Subject.php` - `classrooms()` belongsToMany (convention), `exams()` hasMany
- `app/Models/Exam.php` - `subject()`/`creator()` belongsTo, `questions()`/`attempts()` hasMany, `classrooms()` belongsToMany (explicit `exam_classroom` pivot), `is_published` cast to boolean
- `app/Models/Question.php` - `exam()` belongsTo, `options()`/`answers()` hasMany, `type` cast to `QuestionType`
- `app/Models/Option.php` - `question()` belongsTo, `is_correct` cast to boolean
- `app/Models/Attempt.php` - `exam()`/`user()` belongsTo, `answers()` hasMany, `started_at`/`submitted_at` cast to datetime, `score` cast to `decimal:2` (no status enum/business logic)
- `app/Models/Answer.php` - `attempt()`/`question()` belongsTo, `selectedOption()` belongsTo with explicit `selected_option_id` FK, `is_correct` cast to boolean, `score` cast to `decimal:2`
- `database/factories/ClassroomFactory.php` - `definition()` returns a unique two-word `name`
- `tests/Unit/UserRoleCastTest.php` - Two tests: enum-cast round-trip through `create()`+reload, and `isLecturer()`/`isStudent()` helper booleans

## Decisions Made
- `role`/`classroom_id` were added to `User::$fillable` (required for the seeder/factory/test writes this plan and 01-04 depend on), guarded by an explicit doc-comment invariant that no public-facing controller may source these from request input — the actual enforcement point is 01-04's registration hardcode, this plan only establishes the safe pattern and documents the boundary (T-01-02-MA)
- `Attempt.status` intentionally left as a plain string column — no enum, no lifecycle guard logic, per the plan's explicit "Phase 4 concern" scope boundary

## Deviations from Plan

### Process note (not a Rule 1-4 deviation)

Task 1 carries `tdd="true"` in the plan, which nominally calls for separate RED (failing test commit) then GREEN (implementation commit) commits. Following the same precedent already established in this phase's 01-01 plan (where migrations + `DomainSchemaTest` were committed together as a single `test` commit), Task 1's enum/model code and its `UserRoleCastTest` were implemented together and committed as one atomic `test(01-02): ...` commit, since both were written in the same pass and the test passed on first run without requiring a genuine RED phase. No functional or scope impact — the round-trip test is green and proves the RBAC-01 behavior exactly as required.

None of Rules 1-4 (auto-fix/auto-add/blocking-fix/architectural) were triggered — the plan's file list, model shapes, relationship names, and pivot overrides matched the existing schema from 01-01 exactly.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- The full domain model layer (8 models total: `User` + 7 new) is complete, with every relationship named per ARCHITECTURE.md and both pivot tables (`classroom_subject` convention, `exam_classroom` explicit override) verified working via `php artisan tinker`.
- `Role`/`QuestionType` enums and `ClassroomFactory` are ready for 01-03 (RBAC middleware/route gating, which needs `isLecturer()`/`isStudent()`) and 01-04 (registration hardcode + seeder, which needs the `Role` enum and `ClassroomFactory`/`Classroom` model for the demo seed data).
- `T-01-02-MA` (mass-assignment gap) is documented but not yet enforced by a controller — 01-04 must implement the registration hardcode (`role => Role::Student`, never `$request->role`) to close this threat; this plan only prepares the safe `$fillable` pattern.

---
*Phase: 01-foundation-domain-schema-role-based-access-control*
*Completed: 2026-07-15*

## Self-Check: PASSED

All 12 created/modified files confirmed present on disk; all 3 task commits (`d19b020`, `0cbe4e0`, `04fb529`) plus the summary commit (`6194c6e`) confirmed in git log.

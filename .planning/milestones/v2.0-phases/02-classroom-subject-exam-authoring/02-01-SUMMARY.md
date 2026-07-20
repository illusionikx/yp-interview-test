---
phase: 02-classroom-subject-exam-authoring
plan: 01
subsystem: testing
tags: [laravel, eloquent-factories, phpunit, testing-infrastructure]

# Dependency graph
requires:
  - phase: 01-foundation-domain-schema-role-based-access-control
    provides: "Subject/Exam/Question/Option models + migrations, Role/QuestionType enums, ClassroomFactory/UserFactory precedent"
provides:
  - "SubjectFactory, ExamFactory (published() state), QuestionFactory (mcq()/open() states), OptionFactory"
  - "UserFactory lecturer()/student() convenience states"
  - "tests/Feature/Lecturer/ directory with a Wave 0 smoke test"
  - "HasFactory trait added to Subject/Exam/Question/Option models (was missing, blocking Model::factory())"
affects: [02-classroom-subject-exam-authoring-plans-02-through-06]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Factory state methods follow the established state(fn (array $attributes) => [...]) shape from UserFactory::unverified()"
    - "QuestionFactory::mcq() uses afterCreating() to attach exactly one is_correct=true Option plus three is_correct=false Options (D-08 fixture invariant)"

key-files:
  created:
    - database/factories/SubjectFactory.php
    - database/factories/OptionFactory.php
    - database/factories/ExamFactory.php
    - database/factories/QuestionFactory.php
    - tests/Feature/Lecturer/FactoriesTest.php
  modified:
    - database/factories/UserFactory.php
    - app/Models/Subject.php
    - app/Models/Exam.php
    - app/Models/Question.php
    - app/Models/Option.php

key-decisions:
  - "Added missing HasFactory trait to Subject/Exam/Question/Option models — the plan assumed Laravel's convention-based factory auto-discovery needs no trait, but Model::factory() actually requires HasFactory on the model itself (confirmed by the existing Classroom/User models, which already had it)."
  - "QuestionFactory::mcq() creates 4 options (1 correct, 3 incorrect) rather than the plan's minimum of 2, to give feature tests more realistic MCQ fixtures while still satisfying the >=2 acceptance criterion."

patterns-established:
  - "Any new Phase-2 model needing Model::factory() must add the HasFactory trait explicitly — this is not automatic from folder/naming convention alone."

requirements-completed: []

# Metrics
duration: 25min
completed: 2026-07-15
status: complete
---

# Phase 2 Plan 01: Wave 0 Test Infrastructure Summary

**Four new Eloquent factories (Subject, Exam, Question, Option) plus lecturer()/student() UserFactory states, verified by a 7-assertion smoke test, with a Rule-3 fix restoring the missing HasFactory trait on four Phase-1 models.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-07-15T13:29:00Z (approx)
- **Completed:** 2026-07-15T13:54:39Z
- **Tasks:** 3
- **Files modified:** 10 (5 created, 5 modified)

## Accomplishments
- `SubjectFactory` and `OptionFactory` created, following the `ClassroomFactory` structural style
- `ExamFactory` (with `published()` state) and `QuestionFactory` (with `mcq()`/`open()` states) created
- `UserFactory` gained `lecturer()`/`student()` convenience states
- `tests/Feature/Lecturer/` directory created with `FactoriesTest`, proving all four factories and UserFactory's new states persist valid models
- Fixed a blocking gap: `Subject`, `Exam`, `Question`, `Option` models were missing the `HasFactory` trait (Phase 1 built the migrations/models but never added it), so `Model::factory()` was undefined until this plan added it

## Task Commits

Each task was committed atomically:

1. **Task 1: SubjectFactory + OptionFactory** - `50794b6` (feat)
2. **Task 2: ExamFactory + QuestionFactory + UserFactory role states (+ Rule 3 HasFactory fix)** - `4cc3eba` (feat)
3. **Task 3: Wave 0 smoke test (FactoriesTest)** - `89c70db` (test)

**Plan metadata:** commit created after this summary (see below)

## Files Created/Modified
- `database/factories/SubjectFactory.php` - unique name + uppercase short code
- `database/factories/OptionFactory.php` - question_id via Question::factory(), is_correct defaults false
- `database/factories/ExamFactory.php` - subject_id + created_by lecturer, published() state
- `database/factories/QuestionFactory.php` - mcq() attaches 4 options (1 correct), open() attaches none
- `database/factories/UserFactory.php` - added lecturer()/student() states
- `app/Models/Subject.php` - added HasFactory trait
- `app/Models/Exam.php` - added HasFactory trait
- `app/Models/Question.php` - added HasFactory trait
- `app/Models/Option.php` - added HasFactory trait
- `tests/Feature/Lecturer/FactoriesTest.php` - 7-test smoke suite covering every new factory/state

## Decisions Made
- Added `HasFactory` to the four Phase-1 models (Rule 3 auto-fix — see Deviations) rather than treating it as an architectural change, since it exactly mirrors the existing `Classroom`/`User` pattern and unblocks the plan's stated deliverable.
- `mcq()` attaches 4 options (1 correct, 3 incorrect) instead of the plan's minimum of 2, for more realistic fixtures; still satisfies the `>=2` acceptance criterion verbatim.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Added missing `HasFactory` trait to Subject/Exam/Question/Option models**
- **Found during:** Task 2 (running `php artisan test --filter=FactoriesTest` after writing ExamFactory/QuestionFactory)
- **Issue:** The plan explicitly stated "no `HasFactory` change needed on the models (they already resolve by convention)" — this is incorrect for Laravel 11. `Model::factory()` is provided by the `HasFactory` trait, which must be `use`d on the model itself; folder/naming convention only tells the trait *which* factory class to resolve, it doesn't grant the static method. Running the new smoke test immediately surfaced `BadMethodCallException: Call to undefined method App\Models\Subject::factory()` (and the same for Exam/Question/Option).
- **Fix:** Added `use HasFactory;` (with the matching `@use HasFactory<...>` docblock) to `Subject`, `Exam`, `Question`, and `Option`, mirroring the existing `Classroom`/`User` models exactly.
- **Files modified:** `app/Models/Subject.php`, `app/Models/Exam.php`, `app/Models/Question.php`, `app/Models/Option.php`
- **Verification:** `php artisan test --filter=FactoriesTest` went from 6 failed/1 passed to 7/7 passed; full suite re-run at 50/50 passed.
- **Committed in:** `4cc3eba` (Task 2 commit)

**2. [Rule 1 - Style] Pint auto-fix on new factory files**
- **Found during:** Task 2, running Pint on plan-touched files before committing (per `STACK.md`'s "Pint: Run before commits" convention)
- **Issue:** `fully_qualified_strict_types`/`ordered_imports` fixers flagged the new/modified factories and models (fully-qualified `@extends Factory<\App\Models\X>` docblocks instead of short names + imports).
- **Fix:** Ran `php vendor/bin/pint` scoped only to the files this plan created/modified (not repo-wide — `pint --test` showed the same style issue pre-existing on unrelated Phase-1 files like `Answer.php`, `Attempt.php`, `bootstrap/providers.php`, which are out of scope per the deviation rules' scope boundary and were left untouched).
- **Files modified:** `database/factories/SubjectFactory.php`, `database/factories/OptionFactory.php`, `database/factories/ExamFactory.php`, `database/factories/QuestionFactory.php`, `database/factories/UserFactory.php`, `app/Models/Subject.php`, `app/Models/Exam.php`, `app/Models/Question.php`, `app/Models/Option.php`
- **Verification:** Full suite re-run green (50/50) after Pint's changes.
- **Committed in:** `4cc3eba` (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 style)
**Impact on plan:** Both fixes were necessary to make the plan's stated deliverable actually work (Rule 3) and to follow the project's documented Pint convention scoped to touched files only (Rule 1). No scope creep — repo-wide pre-existing style issues on unrelated files were explicitly left alone.

## Issues Encountered
None beyond the deviations documented above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All four Phase-2 factories (`SubjectFactory`, `ExamFactory`, `QuestionFactory`, `OptionFactory`) and `UserFactory::lecturer()`/`student()` states are available for every later Phase-2 plan (CLS-01..04, EXM-01..06 feature tests).
- `tests/Feature/Lecturer/` directory exists, ready to receive controller feature tests from plans 02-02 onward.
- `02-VALIDATION.md`'s Wave 0 Requirements checklist items are now all satisfied on disk (checkboxes not edited here — that file is a planning artifact, not owned by this plan's `files_modified`).
- No blockers for subsequent waves.

---
*Phase: 02-classroom-subject-exam-authoring*
*Completed: 2026-07-15*

## Self-Check: PASSED

All 10 claimed files found on disk; all 3 task commits (`50794b6`, `4cc3eba`, `89c70db`) found in git history.

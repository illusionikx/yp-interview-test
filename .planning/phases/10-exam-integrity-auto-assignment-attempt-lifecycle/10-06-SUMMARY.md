---
phase: 10-exam-integrity-auto-assignment-attempt-lifecycle
plan: 06
subsystem: database
tags: [laravel-11, eloquent, phpunit, rbac, authorization]

# Dependency graph
requires:
  - phase: 10-03
    provides: "Every pivot-consuming test fixture already pinned Section::factory()'s subject_id to the paired Exam's subject_id — a provably behavior-neutral baseline (338 passed / 23 failed) this plan's pivot-drop and predicate rewrite could build against without also debugging fixture semantics"
  - phase: 10-05
    provides: "The manual exam-assignment UI/controller/route already deleted, leaving the exam_section pivot a loaded gun with no trigger guard"
provides:
  - "The exam_section pivot table dropped permanently (first schema-break migration in the project)"
  - "Exam::scopeVisibleTo() rewritten to derive visibility from subject enrollment (whereHas('subject.sections.enrollments', ...)) instead of the per-exam assignment pivot"
  - "Exam::sections() and Section::exams() BelongsToMany relations removed — the cross-subject leak (T-10-01) is now structurally unexpressible, not just runtime-guarded"
  - "Subject::exams()/Subject::sections() (HasMany) confirmed untouched and load-bearing (SubjectController's delete guard, the new predicate)"
  - "CLS-05's same-subject entitlement proven on both surfaces (direct access + list), each guarded by an assertSame subject check so it can never silently collapse into a duplicate of INT-04's cross-subject denial"
  - "FIX-03 confirmed satisfied-by-removal (already recorded in REQUIREMENTS.md by plan 05)"
affects: ["10-07", "10-08", "10-09"]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Subject-derived visibility: whereHas('subject.sections.enrollments', ...) composes EXISTING relations (Exam::subject() BelongsTo -> Subject::sections() HasMany -> Section::enrollments() BelongsToMany) — no new relation method needed on any model"
    - "First schema-break migration convention: a NEW timestamped migration whose up() drops the table, never editing/deleting the original create migration; down() is intentionally empty with a comment explaining why recreating the table would reopen the leak"
    - "Test inversion over test repair: when a phase deliberately changes asserted behavior, rename+invert the test (with an explicit assertSame/assertNotSame guard distinguishing same-subject vs cross-subject) rather than deleting it or silently re-pointing it at a different subject, which would collapse two distinct requirements' coverage into one"

key-files:
  created:
    - database/migrations/2026_07_17_100001_drop_exam_section_table.php
  modified:
    - app/Models/Exam.php
    - app/Models/Section.php
    - app/Http/Controllers/Student/ExamController.php
    - database/seeders/DatabaseSeeder.php
    - tests/Feature/DomainSchemaTest.php
    - tests/Feature/DatabaseSeederTest.php
    - tests/Feature/AttemptNullGuardTest.php
    - tests/Feature/Grading/AttemptGraderTest.php
    - tests/Feature/Lecturer/GradeAnswerTest.php
    - tests/Feature/Lecturer/ResultTest.php
    - tests/Feature/Student/AttemptAnswerTest.php
    - tests/Feature/Student/AttemptAvailabilityTest.php
    - tests/Feature/Student/AttemptShowTest.php
    - tests/Feature/Student/AttemptStartTest.php
    - tests/Feature/Student/AttemptSubmitTest.php
    - tests/Feature/Student/ExamShowTest.php
    - tests/Feature/Student/ExamVisibilityRegressionTest.php
    - tests/Feature/Student/Phase4ReviewFixesTest.php
    - tests/Feature/Student/ResultTest.php
    - tests/Feature/Student/ExamAccessTest.php
    - tests/Feature/Student/ExamIndexTest.php
    - tests/Feature/Student/AttemptPolicyTest.php

key-decisions:
  - "Deleted Exam::sections()/Section::exams() BelongsToMany relations entirely rather than keeping them as dead code — any surviving caller would SQL-error (BadMethodCallException) rather than silently misbehave, which is the correct fail-fast for a removed authorization-relevant relation"
  - "Task 2's file list explicitly excluded ExamAccessTest/ExamIndexTest/AttemptPolicyTest from routine sync()-line stripping (they needed semantic decisions) — but those files' OTHER, non-flagged test methods still had ordinary sync() calls that Task 2 never touched. Stripped those in Task 3 too, since the plan's own acceptance criteria required zero pivot navigation left anywhere in tests/ once Task 3 completed."
  - "Re-pointed ExamIndexTest's two CR-02 pivot-navigation call sites through the surviving Subject::sections() HasMany ($exam->subject->sections()->first()->enrollments()->updateExistingPivot(...)) exactly as the plan's action text specified"
  - "Updated DatabaseSeeder's stale doc comments (which described 'assigned to the first Mathematics section only') to describe subject-derived visibility accurately, since the seeder is user-facing documentation for anyone cloning the repo"

requirements-completed: [CLS-05, INT-04, FIX-03]

# Metrics
duration: 22min
completed: 2026-07-17
status: complete
---

# Phase 10 Plan 06: Drop exam_section pivot, derive visibility from subject enrollment (D-1) Summary

**Dropped the exam_section pivot permanently and rewrote Exam::scopeVisibleTo() to derive visibility from subject enrollment (whereHas('subject.sections.enrollments', ...)), making v2.0's CRITICAL cross-subject exam leak structurally unexpressible rather than merely runtime-guarded.**

## Performance

- **Duration:** 22 min
- **Started:** 2026-07-17T09:32:41Z
- **Completed:** 2026-07-17T09:54:56Z
- **Tasks:** 3
- **Files modified:** 22 (1 created, 21 modified)

## Accomplishments
- Closed T-10-01 (v2.0's CRITICAL cross-subject leak) at the structural level: dropped the `exam_section` table and both `BelongsToMany` relations that backed it (`Exam::sections()`, `Section::exams()`) — there is no longer any way to say "exam X is visible to a class of subject Y" other than `exam.subject_id` itself
- Rewrote `Exam::scopeVisibleTo()` to compose the existing relation chain (`Exam::subject()` -> `Subject::sections()` -> `Section::enrollments()`) with no new relation method needed on any model
- Confirmed `Subject::exams()`/`Subject::sections()` (HasMany) survive untouched — `SubjectController`'s delete guard and the new predicate both depend on them
- `CrossSubjectVisibilityTest` (INT-04) green on all three surfaces: list, direct access, and the write/start path
- Stripped the now-dead pivot-sync call from 16 test fixture files (plan 03 had already pinned `subject_id`, so visibility became automatic the moment the sync line was deleted) with zero assertion changes
- Inverted the two tests that asserted the exact denial CLS-05 removes (`ExamAccessTest`/`ExamIndexTest`'s "different section is forbidden/excluded" methods) into CLS-05's positive same-subject entitlement, each guarded by an explicit `assertSame` subject check — the trap of silently converting them into a second copy of INT-04 was avoided
- Deleted `AttemptPolicyTest::test_attempt_survives_exam_unassigned_from_section_mid_attempt` — its "unassign an exam from a section" premise is unrepresentable once assignment is subject-derived; the 5 surviving mid-attempt guards already cover the invariant it protected
- Full suite: 345 passed / 15 failed — the 15 failures are exactly `ResetSubmissionsTest` (6) and `ExamUpdateVoidsAttemptsTest` (9), the plans 07/08/09 RED specs, matching the plan's stated expectation exactly

## Task Commits

Each task was committed atomically:

1. **Task 1: Drop the pivot and derive visibility from subject enrollment (D-1)** - `238ff06` (feat)
2. **Task 2: Strip the pivot from 15 prepared fixtures and the two schema/seeder assertions** - `97c04e6` (test)
3. **Task 3: Resolve the three tests whose premise D-1 deletes — invert, re-point, remove** - `8f0bda2` (test)

**Plan metadata:** this commit (docs: complete plan)

## Files Created/Modified
- `database/migrations/2026_07_17_100001_drop_exam_section_table.php` — new migration, `up()` drops the table, `down()` intentionally empty with a comment explaining why recreating it would reopen the leak
- `app/Models/Exam.php` — `scopeVisibleTo()` now `whereHas('subject.sections.enrollments', ...)`; `sections(): BelongsToMany` deleted; 18-line doc comment extended with the D-1 rationale
- `app/Models/Section.php` — `exams(): BelongsToMany` deleted; `enrollments()` untouched
- `app/Http/Controllers/Student/ExamController.php` — `$enrolledSection` now queries `Section::where('subject_id', $exam->subject_id)->whereHas('enrollments', ...)` directly, no relation on `Exam` needed
- `database/seeders/DatabaseSeeder.php` — pivot sync line removed; `seedExam()`'s now-unused `$section` parameter dropped; stale "assigned to the first Mathematics section only" comments corrected to describe subject-derived visibility
- `tests/Feature/DomainSchemaTest.php` — `exam_section` removed from the domain-table inventory; class doc comment updated
- `tests/Feature/DatabaseSeederTest.php` — pivot assertion replaced with `Exam::visibleTo($student1)->whereKey($exam->id)->exists()`, asserting the real CLS-05 outcome
- 13 fixture-owning test files (`AttemptNullGuardTest`, `Grading/AttemptGraderTest`, `Lecturer/GradeAnswerTest`, `Lecturer/ResultTest`, `Student/AttemptAnswerTest`, `Student/AttemptAvailabilityTest`, `Student/AttemptShowTest`, `Student/AttemptStartTest`, `Student/AttemptSubmitTest`, `Student/ExamShowTest`, `Student/ExamVisibilityRegressionTest`, `Student/Phase4ReviewFixesTest`, `Student/ResultTest`) — every `$exam->sections()->sync([...])` call deleted, zero other changes
- `tests/Feature/Student/ExamAccessTest.php` — inverted `test_a_student_enrolled_in_a_different_section_is_forbidden` into `test_..._of_the_same_subject_can_view_the_exam` (`assertForbidden` -> `assertOk`, `assertSame` subject guard added); stripped its 6 other routine sync() calls
- `tests/Feature/Student/ExamIndexTest.php` — inverted `test_index_excludes_a_published_exam_for_a_different_section` into `test_index_includes_a_published_exam_for_any_section_of_the_same_subject` (`assertDontSee` -> `assertSee`, `assertSame` guard added); re-pointed both CR-02 pivot navigations through `$exam->subject->sections()->first()->...`; stripped 4 other routine sync() calls
- `tests/Feature/Student/AttemptPolicyTest.php` — deleted `test_attempt_survives_exam_unassigned_from_section_mid_attempt` and its `sections()->detach()` line; stripped 2 other routine sync() calls

## Decisions Made
- Kept `Exam::sections()`/`Section::exams()` deleted outright (not left as unused dead code) — any surviving caller fails loudly (`BadMethodCallException`) rather than a silent behavioral gap
- Task 2's plan-stated file list explicitly excluded `ExamAccessTest`/`ExamIndexTest`/`AttemptPolicyTest` from the routine sync()-stripping pass (they needed semantic decisions belonging to Task 3) — but each of those files had additional, non-flagged test methods still calling the dead pivot. Stripped those in Task 3 as well, since the plan's own Task 3 acceptance criteria required zero pivot navigation left in `tests/` by the end of the plan.
- Updated `DatabaseSeeder`'s doc comments describing the old per-section assignment demo, since they would otherwise mislead a reader about how the seeded data actually becomes visible now

## Deviations from Plan

None — plan executed exactly as written. The additional sync()-line removals in `ExamAccessTest`/`ExamIndexTest`/`AttemptPolicyTest` (beyond the explicitly-flagged semantic changes) were required by the plan's own Task 3 acceptance criteria ("no pivot navigation remains anywhere in the suite") and are a natural completion of the plan's stated scope, not new work outside it.

## Issues Encountered
None. IDE lint diagnostics reporting "Method Exam::sections() is not defined" and similar during editing were stale (referencing pre-edit file state); re-reading the files after each edit confirmed the actual content was correct, and the full PHPUnit run confirms no runtime errors from missed callers.

## User Setup Required
None — no external service configuration required.

## Next Phase Readiness

Plans 07/08/09 (D-2's hard-delete voiding, EDT-04's save+void, and their remaining RED specs) can now proceed against a codebase where:
- Cross-subject exam exposure (T-10-01) is closed structurally, not just guarded at runtime
- CLS-05/INT-04/FIX-03 are all satisfied and ready to mark complete in REQUIREMENTS.md via the standard state-update step
- The full suite's only remaining failures (`ResetSubmissionsTest`, `ExamUpdateVoidsAttemptsTest`) are exactly the ones those later plans own — no unexpected regressions introduced by this plan

No blockers.

## Verification Evidence

1. **`CrossSubjectVisibilityTest` — GREEN on all 3 surfaces:**
   ```
   PASS  Tests\Feature\Student\CrossSubjectVisibilityTest
   ✓ a student enrolled only in a different subject cannot see open or start the exam
   ✓ a student enrolled in the exams own subject can see open and start it
   Tests: 2 passed (9 assertions)
   ```

2. **Leak unexpressible — full accounting of every remaining `exam_section`/`->sections()`/`Section::exams`/`Exam::sections` hit** (`grep -rn 'exam_section\|->sections()\|Section::exams\|Exam::sections' app/ database/ tests/`):
   - `app/Models/Exam.php:78-80` — doc comment only, explaining the removal
   - `database/database.sqlite` — binary dev artifact, not tracked by git
   - `database/migrations/2026_07_15_100008_create_exam_section_table.php` — the original create migration, kept per immutable-migration convention
   - `database/migrations/2026_07_17_100001_drop_exam_section_table.php` — this plan's drop migration
   - `tests/Feature/DatabaseSeederTest.php`, `tests/Feature/DomainSchemaTest.php` — doc comments describing the removed table
   - `tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php`, `tests/Feature/Lecturer/ResetSubmissionsTest.php` — doc comments in plans 07/08's own RED specs (out of this plan's scope)
   - `tests/Feature/Student/ExamIndexTest.php:179,208` — the two sanctioned re-pointed calls through the surviving `Subject::sections()` HasMany, exactly as Task 3's action text specified
   - **No hit represents surviving navigation through the deleted `Exam::sections()`/`Section::exams()` relations.**

3. **`SubjectControllerTest` — green, proving `Subject::exams()`'s delete guard survived:**
   ```
   Tests: 7 passed (13 assertions)
   ```

4. **CLS-05 coverage genuinely distinct from INT-04** — both `assertSame` guards present:
   ```
   grep -c 'assertSame' tests/Feature/Student/ExamAccessTest.php  -> 2
   grep -c 'assertSame' tests/Feature/Student/ExamIndexTest.php   -> 2
   ```

5. **`php artisan migrate:fresh --seed`** — exit 0, clean, tail:
   ```
   2026_07_17_100001_drop_exam_section_table ... DONE
   INFO  Seeding database.
   ```

6. **Full suite totals:** 345 passed, 15 failed (851 assertions). All 15 failures attributed:
   - `ExamUpdateVoidsAttemptsTest` (9 methods) — owned by plan 08/09 (EDT-04)
   - `ResetSubmissionsTest` (6 methods) — owned by plan 07 (CLS-07), failing on `RouteNotFoundException: Route [lecturer.exams.submissions.reset] not defined`

7. **`git diff package.json composer.json`** — empty, confirmed. No new dependencies (CLAUDE.md compliance).

---
*Phase: 10-exam-integrity-auto-assignment-attempt-lifecycle*
*Completed: 2026-07-17*

## Self-Check: PASSED
- FOUND: database/migrations/2026_07_17_100001_drop_exam_section_table.php
- FOUND: .planning/phases/10-exam-integrity-auto-assignment-attempt-lifecycle/10-06-SUMMARY.md
- FOUND: commit 238ff06
- FOUND: commit 97c04e6
- FOUND: commit 8f0bda2

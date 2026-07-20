---
phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix
plan: 03
subsystem: database
tags: [laravel, eloquent, migrations, enum-cast-pivot, rbac, visibility-scope]

# Dependency graph
requires:
  - phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix (plan 02)
    provides: The Wave-0 RED test contract (DomainSchemaTest, SectionControllerTest, ExamVisibilityRegressionTest) this plan turns green at the schema/model layer
provides:
  - Reordered/rewritten schema-break migrations (subjects before sections; sections/subject_user/enrollments/exam_section tables; users.classroom_id dropped)
  - EnrollmentStatus backed enum + Enrollment custom Pivot (enum-cast status)
  - Section model (renamed from Classroom) with subject/enrollments/exams relations + computed name accessor
  - Subject.sections()/lecturers(), User.sections()/subjects() relations
  - Exam::scopeVisibleTo() rewritten to be enrollment-driven (single predicate consumed by list + ExamPolicy/AttemptPolicy)
  - ENR-08 hard acceptance gate GREEN (ExamVisibilityRegressionTest, all 4 enrollment states)
affects: [07-04 (section CRUD controllers/routes/views, subject-lecturer assignment, remaining rename sweep), 07-07 (seeder rewrite)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Enum-cast custom Pivot model (Enrollment extends Pivot, casts() to EnrollmentStatus) — first Pivot subclass in this codebase, mirrors the Role/QuestionType casts() convention"
    - "Computed accessor over stored parts (Section::name from year/semester/sequence) instead of a redundant stored column"
    - "Single-predicate visibility scope (scopeVisibleTo) consumed identically by the list query and both Policies — no re-derivation"

key-files:
  created:
    - app/Enums/EnrollmentStatus.php
    - app/Models/Enrollment.php
    - app/Models/Section.php
    - database/factories/SectionFactory.php
    - database/migrations/2026_07_15_100011_create_enrollments_table.php
  modified:
    - database/migrations/2026_07_15_100001_create_subjects_table.php
    - database/migrations/2026_07_15_100002_create_sections_table.php
    - database/migrations/2026_07_15_100003_add_role_to_users_table.php
    - database/migrations/2026_07_15_100004_create_subject_user_table.php
    - database/migrations/2026_07_15_100008_create_exam_section_table.php
    - app/Models/Subject.php
    - app/Models/User.php
    - app/Models/Exam.php
    - app/Policies/ExamPolicy.php
    - app/Policies/AttemptPolicy.php

key-decisions:
  - "Migration files were renamed via git mv (preserving history), then rewritten in place, per the exact Pitfall-1 ordering fix from 07-RESEARCH.md — subjects (100001) now precedes sections (100002)."
  - "scopeVisibleTo's explicit ->when($user->classroom_id, ...) null-guard was dropped, not replaced with an equivalent guard — whereHas('sections.enrollments', ...) naturally resolves to zero rows for a student with no enrollments, which is the same outcome the old guard produced for classroom_id === null."
  - "Section factory defaults to an open enrollment window (opens_at = now()-1day, closes_at = now()+14days) so later section/enrollment tests can enroll immediately without extra state setup."

patterns-established:
  - "Enum-cast Pivot subclass (->using(Enrollment::class)) for any future pivot needing typed pivot columns."

requirements-completed: [SEC-01, SEC-03, ENR-08, DEL-03]

# Metrics
duration: ~25min
completed: 2026-07-16
status: complete
---

# Phase 7 Plan 3: Atomic Schema Break — Sections, Enrollments & Enrollment-Driven Visibility Summary

**Migration reorder/rewrite (subjects-before-sections) + Section/Enrollment model layer + Exam::scopeVisibleTo() rewritten to be enrollment-driven, with the ENR-08 cross-consumer regression test (list vs. direct-access gate, across all 4 enrollment states) GREEN as the hard acceptance gate.**

## Performance

- **Duration:** ~25 min
- **Completed:** 2026-07-16T06:00:11Z
- **Tasks:** 3
- **Files modified:** 16 (5 migrations rewritten, 1 new migration, 5 models modified, 2 policies swept, 2 models+factory renamed/created, 1 enum + 1 pivot created)

## Accomplishments
- Fixed the migration-ordering bug identified in research (Pitfall 1): `subjects` now migrates before `sections`, so `sections.subject_id`'s foreign key resolves on `migrate:fresh`.
- `users.classroom_id` and the `classroom_subject`/`exam_classroom`/`classrooms` tables are fully retired; `sections`, `subject_user`, `enrollments`, `exam_section` exist with correct FKs, cascades, and the `enrollments` unique(section_id, user_id) constraint.
- `Exam::scopeVisibleTo()` is now the single enrollment-driven predicate (`whereHas('sections.enrollments', user_id=X AND status=Enrolled)`), consumed unchanged by both the (future) student exam list and `ExamPolicy::takeable()`/`AttemptPolicy`.
- The ENR-08 hard acceptance gate (`ExamVisibilityRegressionTest`) is GREEN across all four enrollment states (enrolled/withdrawn/rejected/never_applied), proving list-visibility and direct-access-gate agreement.

## Task Commits

Each task was committed atomically:

1. **Task 1: Reorder and rewrite the schema-break migrations** - `966f8c9` (feat)
2. **Task 2: EnrollmentStatus enum, Enrollment pivot, Section model + SectionFactory** - `0f592fd` (feat)
3. **Task 3: Rewire Subject/User/Exam, rewrite scopeVisibleTo, sweep policy comments** - `f0c4c06` (feat)

_Note: no TDD subdivision — plan tasks were single-commit `feat` per the plan's `type="auto"` tasks._

## Files Created/Modified
- `database/migrations/2026_07_15_100001_create_subjects_table.php` - renamed from 100002, content unchanged (now migrates first)
- `database/migrations/2026_07_15_100002_create_sections_table.php` - rewritten from `create_classrooms_table`: subject_id FK, year/semester/sequence/capacity/opens_at/closes_at, unique(subject_id,year,semester,sequence)
- `database/migrations/2026_07_15_100003_add_role_to_users_table.php` - rewritten: adds only `role`, no longer adds/drops `classroom_id`
- `database/migrations/2026_07_15_100004_create_subject_user_table.php` - rewritten from `create_classroom_subject_table`: subject_id/user_id, cascade FKs, unique pair
- `database/migrations/2026_07_15_100008_create_exam_section_table.php` - rewritten from `create_exam_classroom_table`: exam_id/section_id, cascade FKs, unique pair
- `database/migrations/2026_07_15_100011_create_enrollments_table.php` - new: section_id/user_id/status/rejection_reason, unique(section_id,user_id)
- `app/Enums/EnrollmentStatus.php` - new backed enum (Enrolled/Withdrawn/Rejected)
- `app/Models/Enrollment.php` - new custom Pivot, casts status to EnrollmentStatus
- `app/Models/Section.php` - renamed from Classroom.php: subject() belongsTo, enrollments() belongsToMany User (using Enrollment), exams() belongsToMany via exam_section, computed name() accessor
- `database/factories/SectionFactory.php` - renamed from ClassroomFactory.php: subject-bound, open enrollment window by default
- `app/Models/Subject.php` - removed classrooms(); added sections() hasMany, lecturers() belongsToMany via subject_user
- `app/Models/User.php` - removed classroom_id fillable + classroom() relation; added sections() belongsToMany (via enrollments) and subjects() belongsToMany via subject_user
- `app/Models/Exam.php` - renamed classrooms()→sections() (exam_section pivot); rewrote scopeVisibleTo() to be enrollment-driven
- `app/Policies/ExamPolicy.php` - doc comment swept from classroom_id to enrollment wording; no logic change
- `app/Policies/AttemptPolicy.php` - doc comment swept from classroom_id to enrollment wording; no logic change

## Decisions Made
- Kept the migration-file-rename approach (`git mv` then edit) rather than editing timestamps in place, preserving git history per the plan's explicit instruction.
- Dropped the old explicit `->when($user->classroom_id, ...)` null-guard in `scopeVisibleTo()` without an equivalent replacement, per 07-RESEARCH.md Pattern 1 — `whereHas` on an empty enrollment set already produces zero rows, so no guard is needed.
- `EnrollmentStatus`'s default DB value (`'enrolled'`) on the `status` column is a schema-level default only; no enrollment-writing code path exists yet (that's Phase 8), so this default is inert in this wave's scope.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. `php artisan migrate:fresh` (no `--seed`, per the wave note — the seeder isn't rewritten until 07-07) succeeded cleanly after Task 1, confirming the ordering fix. The ENR-08 regression test and `DomainSchemaTest` passed without needing any additional model changes beyond what the plan specified.

As expected per the wave-sequencing note, running the full test suite after this plan shows ~84 pre-existing failures — all in files outside this plan's declared `files_modified` scope (controllers, Form Requests, and test fixtures that still reference `Classroom`/`classroom_id`, e.g. `ExamAssignmentTest`, `ClassroomSubjectLinkageTest`, `ResultTest`, `AttemptStartTest`, plus `SectionControllerTest`/`SubjectLecturerTest`'s HTTP-layer assertions hitting unregistered routes). These are explicitly owned by 07-04 (controller/route/view sweep) and 07-07 (seeder rewrite), not this plan.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- The schema/model core is in place and verified: `migrate:fresh` builds cleanly, `DomainSchemaTest` is GREEN, and the ENR-08 hard gate is GREEN.
- 07-04 can now build `SectionController`/`SubjectLecturerController`/Form Requests and sweep the remaining `Classroom`-referencing controllers, requests, and test fixtures (the 26-file rename-sweep list from 07-RESEARCH.md) against this new schema/model foundation.
- No blockers. The `enrollments.status` default (`'enrolled'`) and the open-window `SectionFactory` default are ready for 07-08's enrollment apply/withdraw/reject UI work in the next milestone phase.

---
*Phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix*
*Completed: 2026-07-16*

## Self-Check: PASSED

All 15 created/modified files verified present on disk; `Classroom.php`/`ClassroomFactory.php` verified absent. All 3 task commits (`966f8c9`, `0f592fd`, `f0c4c06`) verified present in git log.

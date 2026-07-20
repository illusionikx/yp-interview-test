---
phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix
plan: 04
subsystem: api
tags: [laravel, eloquent, form-request, authorization, idor, section-crud, subject-user-pivot]

# Dependency graph
requires:
  - phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix (plan 03)
    provides: Section model, Enrollment pivot, subject_user-ready Subject model, reordered schema-break migrations
provides:
  - "Section CRUD (create/edit/delete) nested under a subject, ownership-gated to lecturers assigned to that subject (SEC-02)"
  - "Per-(subject,year,semester) auto-incrementing section sequence (SEC-01)"
  - "Subject-to-lecturer assignment (subject_user pivot) via SubjectLecturerController, ownership-gated and idempotent (SEC-03)"
  - "Genuine per-subject ownership in Form Request authorize() — a new authorization shape for this codebase, diverging from the D-09 return-true convention"
  - "ExamAssignmentController/AssignExamRequest swung from classrooms to sections (exam_section)"
  - "v1 roster mechanism (ClassroomRosterController/AssignStudentRequest) deleted — no longer writes to the dropped users.classroom_id column"
affects: ["07-05 (views/reskin — lecturer.sections.* views and exams/show.blade.php still need to be built/renamed)", "07-07 (seeder + remaining test sweep)"]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Per-subject ownership in Form Request authorize(): `$this->route('subject')->lecturers()->whereKey($this->user()->id)->exists()` — used by StoreSectionRequest, UpdateSectionRequest, AssignLecturerRequest"
    - "Actions with no backing Form Request (SectionController@destroy, SubjectLecturerController@destroy) apply the identical ownership check inline via abort_unless(..., 403) rather than skipping authorization"
    - "Per-(subject,year,semester) max(sequence)+1 idiom, same shape as the existing Question::position pattern"

key-files:
  created:
    - app/Http/Controllers/Lecturer/SubjectLecturerController.php
    - app/Http/Requests/Lecturer/StoreSectionRequest.php
    - app/Http/Requests/Lecturer/UpdateSectionRequest.php
    - app/Http/Requests/Lecturer/AssignLecturerRequest.php
  modified:
    - app/Http/Controllers/Lecturer/SectionController.php (renamed from ClassroomController.php)
    - app/Http/Controllers/Lecturer/ExamAssignmentController.php
    - app/Http/Controllers/Lecturer/ExamController.php
    - app/Http/Requests/Lecturer/AssignExamRequest.php
    - routes/lecturer.php

key-decisions:
  - "SubjectLecturerController@destroy and SectionController@destroy have no backing Form Request, so the same per-subject ownership check the Form Requests perform is applied inline via abort_unless(...) — SEC-03 requires unassign/delete to be ownership-gated too, not only create/edit/assign (deviation, see below)"
  - "AssignExamRequest::authorize() intentionally stays role-gated only (return true), not per-subject — exam-to-section assignment was not brought into the SEC-03 ownership boundary this phase, matching the plan's explicit scoping"

requirements-completed: [SEC-01, SEC-02, SEC-03]

# Metrics
duration: 6min
completed: 2026-07-16
status: complete
---

# Phase 7 Plan 04: Lecturer Backend for the Schema Slice Summary

**Section CRUD nested under Subject (ownership-gated, term-sequenced) + subject-lecturer assignment via subject_user, both enforcing genuine per-subject ownership in `authorize()` — a deliberate divergence from the codebase's existing D-09 role-middleware-only convention.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-07-16T14:14:20+08:00
- **Completed:** 2026-07-16T14:16:00+08:00
- **Tasks:** 3 completed
- **Files modified:** 13 (4 created, 5 modified, 4 deleted)

## Accomplishments
- `SectionController` (renamed from `ClassroomController`) delivers section CRUD nested under `subjects/{subject}`, with a top-level `lecturer.sections.index` for the navbar, per-(subject,year,semester) auto-incrementing sequence, and nested-binding integrity guards.
- `StoreSectionRequest`/`UpdateSectionRequest`/`AssignLecturerRequest` implement genuine per-subject ownership in `authorize()` — the SEC-03 divergence 07-RESEARCH.md flagged against the existing `return true;` D-09 pattern. A lecturer not assigned to the subject gets a 403, verified for create, edit, assign, and unassign.
- `SubjectLecturerController` assigns/unassigns lecturers via the `subject_user` pivot (`syncWithoutDetaching`/`detach`), idempotent and ownership-gated.
- `ExamAssignmentController`/`AssignExamRequest` swung from `classrooms()->sync()`/`classroom_ids` to `sections()->sync()`/`section_ids` (exam_section pivot); `ExamController` swept its remaining `Classroom` import/eager-load/list to `Section`.
- Orphaned v1 roster mechanism (`ClassroomRosterController`, `AssignStudentRequest`, and the `classrooms/{classroom}/students` routes) deleted outright — it wrote to the dropped `users.classroom_id` column and has no v2.0 equivalent in this phase (Pitfall 4).
- `SectionControllerTest` (7 tests) and `SubjectLecturerTest` (8 tests) — 15/15 GREEN, including the 403 IDOR checks for a lecturer not assigned to the subject.

## Task Commits

Each task was committed atomically:

1. **Task 1: Section CRUD controller + Store/UpdateSection Form Requests (per-subject ownership)** - `743bd3a` (feat) + `fb2f6cf` (fix — see Deviations)
2. **Task 2: SubjectLecturerController + AssignLecturerRequest; delete orphaned roster** - `bdba190` (feat)
3. **Task 3: Routes rewrite + exam-assignment swing to sections** - `2101b20` (feat)

**Plan metadata:** (this commit)

## Files Created/Modified
- `app/Http/Controllers/Lecturer/SectionController.php` - Section CRUD nested under subject, ownership-gated destroy, term-sequenced create
- `app/Http/Controllers/Lecturer/SubjectLecturerController.php` - subject_user assign/unassign, ownership-gated
- `app/Http/Requests/Lecturer/StoreSectionRequest.php` - per-subject ownership authorize() + capacity/window rules
- `app/Http/Requests/Lecturer/UpdateSectionRequest.php` - same, no name-uniqueness (name is computed)
- `app/Http/Requests/Lecturer/AssignLecturerRequest.php` - per-subject ownership authorize() + role=lecturer exists rule
- `app/Http/Controllers/Lecturer/ExamAssignmentController.php` - classrooms()->sync() -> sections()->sync()
- `app/Http/Requests/Lecturer/AssignExamRequest.php` - classroom_ids/exists:classrooms -> section_ids/exists:sections
- `app/Http/Controllers/Lecturer/ExamController.php` - Classroom import/eager-load/list swept to Section
- `routes/lecturer.php` - sections.index + subjects.{subject}.sections.* + subjects.{subject}.lecturers.*; classroom/roster routes removed
- Deleted: `app/Http/Requests/Lecturer/StoreClassroomRequest.php`, `UpdateClassroomRequest.php`, `app/Http/Controllers/Lecturer/ClassroomRosterController.php`, `app/Http/Requests/Lecturer/AssignStudentRequest.php`

## Decisions Made
- Applied the same per-subject ownership check used in the Form Requests directly inline (via `abort_unless(..., 403)`) to the two write actions that have no backing Form Request — `SectionController@destroy` and `SubjectLecturerController@destroy` — because SEC-03's ownership boundary applies to every management action on a subject's sections/lecturers, not only the ones a Form Request happens to gate.
- Kept `AssignExamRequest::authorize()` as `return true;` (role-gated only) per the plan's explicit instruction — exam-to-section assignment was not brought into the new per-subject ownership boundary this phase.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Added inline ownership check to SubjectLecturerController@destroy**
- **Found during:** Task 2/3 verification (`SubjectLecturerTest`)
- **Issue:** The plan's action text for `destroy()` only specified `$subject->lecturers()->detach($lecturer->id)` with no authorization step. `AssignLecturerRequest` only backs `store()`; `destroy()` has no Form Request, so as written it would let any lecturer (not just an assigned one) unassign another lecturer from a subject — `test_a_lecturer_not_assigned_to_the_subject_is_forbidden_from_unassigning_lecturers` failed with 302 instead of 403.
- **Fix:** Added `abort_unless($subject->lecturers()->whereKey(auth()->id())->exists(), 403);` at the top of `destroy()`, mirroring the same ownership check the Form Requests perform.
- **Files modified:** `app/Http/Controllers/Lecturer/SubjectLecturerController.php`
- **Verification:** `SubjectLecturerTest` 8/8 GREEN after the fix.
- **Committed in:** `bdba190` (Task 2 commit)

**2. [Rule 2 - Missing Critical] Added the same inline ownership check to SectionController@destroy**
- **Found during:** Task 1, while closing the same gap class found in deviation 1
- **Issue:** `destroy()` has no backing Form Request either (only `store()`/`update()` do); as written it only guarded nested-binding integrity (`abort_unless($section->subject_id === $subject->id, 404)`), not per-subject ownership — a lecturer not assigned to the subject could delete its sections. No test in this plan's Wave-0 contract exercises this specific path, but leaving it ungated would contradict the SEC-03 "denied server-side (403)" truth statement in the plan's own `must_haves`.
- **Fix:** Added `abort_unless($subject->lecturers()->whereKey(auth()->id())->exists(), 403);` alongside the existing nested-binding guard.
- **Files modified:** `app/Http/Controllers/Lecturer/SectionController.php`
- **Verification:** `SectionControllerTest` 7/7 GREEN; `Pint --test` clean.
- **Committed in:** `fb2f6cf` (folded into the Task 1 fix commit)

**3. [Process] `git mv`-then-rewrite commit ordering left the first Task 1 commit with the old ClassroomController body under the new SectionController.php filename**
- **Found during:** Post-commit self-check (diffing `git show HEAD:...` against the working tree)
- **Issue:** `git mv ClassroomController.php SectionController.php` stages the rename with the file's content at that moment; the subsequent `Write` rewriting the file's body created an unstaged modification on top of the staged rename. The `git add` before the Task 1 commit only re-staged `StoreSectionRequest.php`/`UpdateSectionRequest.php`, not `SectionController.php`, so the commit landed a 100%-similarity rename with the pre-rewrite (Classroom) body.
- **Fix:** Staged and committed the corrected `SectionController.php` content immediately as a follow-up commit rather than amending (per the no-amend policy), before proceeding to Task 2.
- **Files modified:** `app/Http/Controllers/Lecturer/SectionController.php`
- **Verification:** `git show HEAD:app/Http/Controllers/Lecturer/SectionController.php` now matches the working tree; `SectionControllerTest` GREEN.
- **Committed in:** `fb2f6cf`

---

**Total deviations:** 3 (2 Rule 2 auto-fixes for a missing IDOR gate on unassign/delete-by-outsider, 1 process fix for a mis-staged commit). All necessary for SEC-03 correctness; no scope creep.

## Issues Encountered
None beyond the deviations above — the plan's design (models/schema from 07-03, RESEARCH's ownership-in-`authorize()` pattern) mapped directly onto the implementation with no blockers.

## Out-of-Scope Findings (logged, not fixed)

Logged to `.planning/phases/07-v2-0-foundation-admin-theme-schema-break-answered-count-fix/deferred-items.md`:
- `ClassroomControllerTest`, `ClassroomRosterTest`, `ClassroomSubjectLinkageTest`, `ExamAssignmentTest` still reference the deleted `Classroom` model/columns — pre-existing RED before this plan, explicitly deferred to 07-07's test sweep.
- `resources/views/lecturer/exams/show.blade.php` and `resources/views/lecturer/classrooms/*` still reference the old classroom shape/paths — 07-05 (views reskin) scope, not touched here. `SectionController`'s `index()/create()/edit()` will 500 until 07-05 lands the `lecturer/sections/*` views.
- `database/seeders/DatabaseSeeder.php` still seeds the v1 shape — 07-07 scope per the plan's own wave-sequencing note.

## Known Stubs

- `lecturer.sections.index`/`create`/`edit` views do not exist yet (`resources/views/lecturer/sections/*.blade.php`) — `SectionController` points at them per this plan's action text, but they 500 until 07-05 builds the reskinned views. This is an explicit, documented sequencing gap (plan objective: "Views are reskinned in 07-05"), not a silent stub — no route/feature depends on these views rendering for this plan's acceptance criteria (`SectionControllerTest`/`SubjectLecturerTest` only exercise write actions, never `GET .../create|edit` or the index).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `SectionControllerTest` (7/7) and `SubjectLecturerTest` (8/8) are GREEN; `php artisan route:list --path=lecturer` resolves cleanly with sections.index + subjects.{subject}.sections.* + subjects.{subject}.lecturers.* routes and no missing-controller error.
- The lecturer backend for SEC-01/SEC-02/SEC-03 is complete and ownership-gated. 07-05 can now build `resources/views/lecturer/sections/{index,create,edit}.blade.php` and reskin `resources/views/lecturer/exams/show.blade.php` (which now needs a `$sections` variable, not `$classrooms`) against a stable controller/route surface.
- 07-07 still needs to: rewrite `DatabaseSeeder.php` to the section/enrollment model, delete/rewrite the four out-of-scope Classroom-referencing test files listed above, and run the full suite as the phase-wide completeness gate (`ExamVisibilityRegressionTest` hard gate, `DatabaseSeederTest`, `DomainSchemaTest`).
- No blockers for 07-05/07-06/07-07.

---
*Phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix*
*Completed: 2026-07-16*

## Self-Check: PASSED

- FOUND: `app/Http/Controllers/Lecturer/SectionController.php`
- FOUND: `app/Http/Controllers/Lecturer/SubjectLecturerController.php`
- FOUND: `app/Http/Requests/Lecturer/StoreSectionRequest.php`
- FOUND: `app/Http/Requests/Lecturer/UpdateSectionRequest.php`
- FOUND: `app/Http/Requests/Lecturer/AssignLecturerRequest.php`
- FOUND: `routes/lecturer.php`
- FOUND commits: `743bd3a`, `fb2f6cf`, `bdba190`, `2101b20`
- CONFIRMED DELETED: `app/Http/Controllers/Lecturer/ClassroomRosterController.php`
- CONFIRMED DELETED: `app/Http/Requests/Lecturer/AssignStudentRequest.php`
- `php artisan test --filter="SectionControllerTest|SubjectLecturerTest"` → 15/15 GREEN
- `php artisan route:list --path=lecturer` → resolves cleanly

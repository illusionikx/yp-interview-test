---
phase: 02-classroom-subject-exam-authoring
plan: 03
subsystem: api
tags: [laravel, eloquent, form-request, blade, pivot, rbac]

# Dependency graph
requires:
  - phase: 02-classroom-subject-exam-authoring (02-01)
    provides: SubjectFactory, QuestionFactory, OptionFactory, ExamFactory, UserFactory lecturer()/student() states
  - phase: 02-classroom-subject-exam-authoring (02-02)
    provides: Lecturer\SubjectController + Form Request pattern mirrored by this plan
provides:
  - Lecturer\ClassroomController (index/create/store/edit/update/destroy)
  - Lecturer\ClassroomRosterController (store=assign, destroy=unassign)
  - StoreClassroomRequest, UpdateClassroomRequest, AssignStudentRequest
  - classroom_subject pivot sync from the classroom form (create-then-sync ordering)
  - Direct users.classroom_id roster assign/unassign, constrained to role=student, with IDOR guard
affects: [03-exam-classroom-assignment, phase-3-student-facing-access]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Resource controller + namespaced Form Request pair, mirroring SubjectController from 02-02"
    - "Pivot sync (belongsToMany->sync()) for classroom<->subject, plain FK update (not attach/detach) for student roster"
    - "Create-then-sync ordering for a new parent + pivot in one form submission (Pitfall 4)"
    - "AssignStudentRequest constrains target via Rule::exists()->where('role', ...) instead of a raw exists rule"

key-files:
  created:
    - app/Http/Controllers/Lecturer/ClassroomController.php
    - app/Http/Controllers/Lecturer/ClassroomRosterController.php
    - app/Http/Requests/Lecturer/StoreClassroomRequest.php
    - app/Http/Requests/Lecturer/UpdateClassroomRequest.php
    - app/Http/Requests/Lecturer/AssignStudentRequest.php
    - resources/views/lecturer/classrooms/index.blade.php
    - resources/views/lecturer/classrooms/create.blade.php
    - resources/views/lecturer/classrooms/edit.blade.php
    - tests/Feature/Lecturer/ClassroomControllerTest.php
    - tests/Feature/Lecturer/ClassroomSubjectLinkageTest.php
    - tests/Feature/Lecturer/ClassroomRosterTest.php
  modified:
    - routes/lecturer.php
    - resources/views/lecturer/home.blade.php

key-decisions:
  - "Classroom edit view hosts both the subject multi-select (Task 2) and the roster panel (Task 3) in one file, per plan artifact spec"
  - "Roster assign/unassign is a plain users.classroom_id FK write, not a pivot attach/detach, per 02-RESEARCH.md's correction of CONTEXT.md D-04's wording"
  - "AssignStudentRequest's exists rule is scoped to role=student so a lecturer account can never be moved by this action"

patterns-established:
  - "Pattern: separate roster controller for a single-FK 'belongs to one' assignment, distinct from the owning resource's own CRUD controller"

requirements-completed: [CLS-01, CLS-03, CLS-04]

# Metrics
duration: 22min
completed: 2026-07-15
status: complete
---

# Phase 2 Plan 3: Classrooms CRUD + Subject Linkage + Student Roster Summary

**Lecturer classroom CRUD, classroom↔subject sync via `classroom_subject` pivot, and student roster assign/unassign via a direct `users.classroom_id` FK write (not attach/detach), each landed as its own TDD vertical slice.**

## Performance

- **Duration:** 22 min
- **Started:** 2026-07-15T13:48:00Z
- **Completed:** 2026-07-15T14:10:35Z
- **Tasks:** 3
- **Files modified:** 13 (11 created, 2 modified)

## Accomplishments
- Classroom CRUD (CLS-01): `Lecturer\ClassroomController`, unique-name validation, index/create/edit Blade views, "Classrooms" nav link on lecturer home
- Classroom↔subject linkage (CLS-03): `subject_ids[]` multi-select on create/edit, `subjects()->sync()` with create-then-sync ordering (Pitfall 4), invalid-id validation
- Student roster (CLS-04): `Lecturer\ClassroomRosterController` assign/unassign via direct `classroom_id` write, constrained to `role=student`, with cross-classroom IDOR guard (`abort_unless(...,404)`) and a roster panel on the classroom edit page

## Task Commits

Each task was committed atomically (RED then GREEN per TDD task):

1. **Task 1: Classroom CRUD (CLS-01)**
   - `3a91e6c` test: add failing ClassroomController feature test
   - `1337ee8` feat: implement lecturer Classroom CRUD
2. **Task 2: Classroom↔subject linkage (CLS-03)**
   - `d62af1f` test: add failing ClassroomSubjectLinkage feature test
   - `11d023f` feat: sync classroom-subject linkage on store/update
3. **Task 3: Student roster (CLS-04)**
   - `827c232` test: add failing ClassroomRoster feature test
   - `ff6f5af` feat: student roster assign/unassign via direct classroom_id

**Plan metadata:** (recorded after this summary is committed)

## Files Created/Modified
- `app/Http/Controllers/Lecturer/ClassroomController.php` - classroom resource CRUD + subject sync on store/update
- `app/Http/Controllers/Lecturer/ClassroomRosterController.php` - assign (store) / unassign (destroy) a student on a classroom
- `app/Http/Requests/Lecturer/StoreClassroomRequest.php` - validates unique name + subject_ids
- `app/Http/Requests/Lecturer/UpdateClassroomRequest.php` - validates unique name (ignoring self) + subject_ids
- `app/Http/Requests/Lecturer/AssignStudentRequest.php` - validates student_id exists and role=student
- `resources/views/lecturer/classrooms/index.blade.php` - classroom list + delete
- `resources/views/lecturer/classrooms/create.blade.php` - name + subject multi-select form
- `resources/views/lecturer/classrooms/edit.blade.php` - name + subject multi-select + roster panel (assign/unassign)
- `resources/views/lecturer/home.blade.php` - added Classrooms nav link
- `routes/lecturer.php` - added classrooms resource route + nested students store/destroy routes
- `tests/Feature/Lecturer/ClassroomControllerTest.php` - CLS-01 CRUD + validation + 403 coverage
- `tests/Feature/Lecturer/ClassroomSubjectLinkageTest.php` - CLS-03 sync semantics coverage
- `tests/Feature/Lecturer/ClassroomRosterTest.php` - CLS-04 assign/unassign + guard coverage

## Decisions Made
- Mirrored `SubjectController`/`SubjectControllerTest` structure exactly (from 02-02) for the classroom CRUD slice, per plan's `<read_first>` guidance.
- Used `$request->safe()->only('name')` for the `Classroom::create()`/`update()` calls (rather than `$request->validated()` directly) so only `name` is ever mass-assigned, keeping `subject_ids` out of the model write and applied exclusively through `sync()`.
- Kept the auto-generated `lecturer.classrooms.show` route (from `Route::resource`) unimplemented, consistent with the same unused-`show` precedent already established by `SubjectController` in 02-02 — not a new deviation, matches existing project convention.

## Deviations from Plan

None - plan executed exactly as written. All three tasks, their Form Requests, controllers, views, and named tests match the plan's artifact and behavior specification.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Classroom/subject/student class-group entities are fully authored and ready for Phase 3 to build the `exam_classroom` assignment link and student-facing class-scoped access.
- Full test suite (75 tests, 168 assertions) green, including all prior-phase tests — no regressions.
- `php artisan route:list --name=lecturer.classrooms` confirms all 6 resource routes + 2 nested roster routes registered under `role:lecturer`.

---
*Phase: 02-classroom-subject-exam-authoring*
*Completed: 2026-07-15*

## Self-Check: PASSED

All 12 created/modified artifacts and all 6 task commit hashes verified present on disk / in git history.

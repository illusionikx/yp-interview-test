---
phase: 03-exam-assignment-class-scoped-access
plan: 02
subsystem: api
tags: [laravel, form-request, belongsToMany, sync, blade]

# Dependency graph
requires:
  - phase: 03-exam-assignment-class-scoped-access
    provides: "RED ExamAssignmentTest (03-01) defining the exact route name, field name, and 5-case contract for ASN-01"
provides:
  - "Lecturer\\ExamAssignmentController@update syncing the exam_classroom pivot from a validated classroom_ids array"
  - "AssignExamRequest validating classroom_ids.* as integer/distinct/exists:classrooms,id"
  - "PUT lecturer/exams/{exam}/assignment named lecturer.exams.assignment.update, inside the role:lecturer group"
  - "Assign to classes multi-checkbox panel on the lecturer exam show page, pre-checked from current assignment"
affects: [03-03-student-visibility, phase-4-attempt-taking]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Single-purpose sub-resource controller (update-only) mirroring ClassroomRosterController's shape"
    - "sync() on a BelongsToMany pivot as the single write path for a many-to-many assignment, replacing the full set on every submit"

key-files:
  created:
    - app/Http/Requests/Lecturer/AssignExamRequest.php
    - app/Http/Controllers/Lecturer/ExamAssignmentController.php
  modified:
    - routes/lecturer.php
    - app/Http/Controllers/Lecturer/ExamController.php
    - resources/views/lecturer/exams/show.blade.php

key-decisions:
  - "classroom_ids is not `required` in AssignExamRequest — an empty array is a valid submission that clears all assignments via sync()"
  - "No is_published guard added to ExamAssignmentController (D-01) — assignment is allowed on draft exams; visibility gating is deferred to 03-03"
  - "No ownership check on ExamAssignmentController — role:lecturer route-group middleware is the sole gate, matching AssignStudentRequest/ClassroomRosterController precedent (D-09)"

patterns-established:
  - "Sub-resource assignment endpoints: dedicated single-action controller + Form Request with authorize() => true (route-group RBAC) + rules() carrying the exists() ownership-free validation"

requirements-completed: [ASN-01]

# Metrics
duration: 6min
completed: 2026-07-15
status: complete
---

# Phase 3 Plan 2: Lecturer Exam-Classroom Assignment Summary

**Dedicated `ExamAssignmentController@update` that `sync()`s the `exam_classroom` pivot from a validated `classroom_ids` array, plus a checkbox assignment panel on the lecturer exam show page — turning `ExamAssignmentTest` from RED to GREEN.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-07-15T15:36:44Z
- **Completed:** 2026-07-15T15:40:14Z
- **Tasks:** 2
- **Files modified:** 5 (2 created, 3 modified)

## Accomplishments
- `AssignExamRequest` validates `classroom_ids.*` as `integer, distinct, exists:classrooms,id`, with `classroom_ids` itself optional so an empty submission clears all assignments
- `Lecturer\ExamAssignmentController@update` syncs `$exam->classrooms()->sync($request->validated('classroom_ids', []))`, redirecting to `lecturer.exams.show` with a status flash
- New `PUT lecturer/exams/{exam}/assignment` route named `lecturer.exams.assignment.update`, inside the existing `role:lecturer` group — students get a 403 before the controller ever runs
- "Assign to classes" panel added to the lecturer exam show page: one checkbox per classroom, pre-checked from `$exam->classrooms`, posting PUT to the new route, rendered regardless of publish state
- `ExamAssignmentTest`: 5/5 GREEN (assign, resync/detach, invalid-id rejection, draft assignment, student-forbidden)

## Task Commits

Each task was committed atomically:

1. **Task 1: AssignExamRequest + ExamAssignmentController + route** - `560c08b` (feat)
2. **Task 2: "Assign to classes" panel on the lecturer exam show page** - `d65cb46` (feat)

**Plan metadata:** (this commit, see below)

## Files Created/Modified
- `app/Http/Requests/Lecturer/AssignExamRequest.php` - validates classroom_ids array against classrooms.id
- `app/Http/Controllers/Lecturer/ExamAssignmentController.php` - single update() action, sync()s the pivot
- `routes/lecturer.php` - registers PUT exams/{exam}/assignment inside role:lecturer group
- `app/Http/Controllers/Lecturer/ExamController.php` - show() now eager-loads exam.classrooms and passes the full Classroom list
- `resources/views/lecturer/exams/show.blade.php` - new "Assign to classes" card with checkboxes and PUT form

## Decisions Made
- `classroom_ids` left optional (not `required`) in the Form Request — submitting no classrooms is a legitimate way to unassign an exam entirely via `sync([])`.
- No publish-state guard in `ExamAssignmentController` — assignment must work on drafts per D-01; that boundary is intentionally left to the student-visibility work in 03-03.
- No per-exam ownership check — matches the existing `AssignStudentRequest`/`ClassroomRosterController` precedent (D-09): the `role:lecturer` route group is the sole authorization gate, since any lecturer may manage any exam/classroom in this system's model.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. `php artisan test --filter=ExamAssignmentTest` went 5/5 green on the first run; `php artisan test --filter=ExamControllerTest` stayed green after the additive `show()` change.

Full-suite run (`php artisan test`) shows 9 pre-existing failures, all in `Tests\Feature\Student\ExamAccessTest` and `Tests\Feature\Student\ExamIndexTest` — these are the RED tests for ASN-02/RBAC-05 written in 03-01 and are explicitly the scope of the next plan (03-03), not a regression introduced here. All Phase 1/2 tests and this plan's `ExamAssignmentTest`/`ExamControllerTest` pass (126 passed, 0 unexpected failures).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- The `exam_classroom` pivot can now be set by a lecturer for any exam (draft or published), which is the precondition 03-03 needs to implement class-scoped student visibility (`scopeVisibleTo`/`ExamPolicy`).
- 03-03 should NOT need to touch `ExamAssignmentController` or `AssignExamRequest` — they are complete and test-covered for ASN-01.

---
*Phase: 03-exam-assignment-class-scoped-access*
*Completed: 2026-07-15*

## Self-Check: PASSED

All created/modified files confirmed present; both task commits (560c08b, d65cb46) confirmed in git log.

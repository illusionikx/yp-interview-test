---
phase: 02-classroom-subject-exam-authoring
plan: 02
subsystem: crud
tags: [laravel, breeze, blade, form-request, resource-controller, rbac]

# Dependency graph
requires:
  - phase: 02-classroom-subject-exam-authoring (plan 01)
    provides: SubjectFactory, ExamFactory, QuestionFactory, OptionFactory, User lecturer()/student() states
provides:
  - Lecturer\SubjectController resource CRUD (index/create/store/edit/update/destroy)
  - StoreSubjectRequest / UpdateSubjectRequest Form Requests
  - lecturer.subjects.* named routes inside the role:lecturer group
  - Breeze-styled index/create/edit Blade views for subjects
  - Nav link from lecturer home to subjects
affects: [02-04 (exams belong to a subject), later Phase-2 plans establishing the same resource-controller + Form Request + Blade pattern]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Resource controller + paired Store/Update Form Requests under App\\Http\\Controllers\\Lecturer / App\\Http\\Requests\\Lecturer, mounted with Route::resource() inside the existing role:lecturer group"
    - "Form Request authorize() returns true (route-group RBAC only, no per-record ownership per D-09); rules() is the sole gate for input shape"
    - "Update Form Request derives Rule::unique(...)->ignore($this->route('subject')) directly from the route-bound model"

key-files:
  created:
    - app/Http/Controllers/Lecturer/SubjectController.php
    - app/Http/Requests/Lecturer/StoreSubjectRequest.php
    - app/Http/Requests/Lecturer/UpdateSubjectRequest.php
    - resources/views/lecturer/subjects/index.blade.php
    - resources/views/lecturer/subjects/create.blade.php
    - resources/views/lecturer/subjects/edit.blade.php
    - tests/Feature/Lecturer/SubjectControllerTest.php
  modified:
    - routes/lecturer.php
    - resources/views/lecturer/home.blade.php

key-decisions:
  - "Both name and code are unique at the DB/validation layer, and UpdateSubjectRequest ignores the current subject's id on both columns so re-saving a record with its own values does not false-positive as a duplicate."
  - "Controller mass-assigns only $request->validated() output; Form Request rules() expose exclusively name and code, so no privileged field (role/classroom_id/created_by) can reach Subject::create()/update() (D-02)."

patterns-established:
  - "Resource-controller + paired Form Requests + three Blade views (index/create/edit) reusing Breeze components (x-input-label, x-text-input, x-input-error, x-primary-button) is the template for ClassroomController and later CLS/EXM authoring controllers."

requirements-completed: [CLS-02]

# Metrics
duration: 20min
completed: 2026-07-15
status: complete
---

# Phase 2 Plan 02: Subjects CRUD Summary

**Lecturer-only Subject resource CRUD (list/create/edit/delete) via `Lecturer\SubjectController` + paired Form Requests, mounted inside the existing `role:lecturer` route group and reusing Breeze Blade components.**

## Performance

- **Duration:** ~20 min
- **Completed:** 2026-07-15T14:01:39Z
- **Tasks:** 2 (RED test task + GREEN implementation task)
- **Files modified:** 9 (7 created, 2 modified)

## Accomplishments
- `Lecturer\SubjectController` with index/create/store/edit/update/destroy, mounted via `Route::resource('subjects', ...)` inside the pre-existing `role:lecturer` middleware group — yields `lecturer.subjects.*` named routes with zero new middleware.
- `StoreSubjectRequest`/`UpdateSubjectRequest` validate `name` (required, unique) and `code` (nullable, unique), with the update request ignoring the current record on both unique checks.
- Three Breeze-styled Blade views (`index`, `create`, `edit`) reusing `x-app-layout`, `x-input-label`, `x-text-input`, `x-input-error`, `x-primary-button` — index lists subjects with edit links and a per-row delete form with a confirm prompt.
- Lecturer home now links to the subjects index instead of showing placeholder copy.
- `SubjectControllerTest` (7 tests) covers create/update/delete persistence, blank-name rejection, and student-403 on index/create/store — full RED (routes undefined) → GREEN (all pass) cycle verified.

## Task Commits

Each task was committed atomically:

1. **Task 1: SubjectControllerTest — failing CLS-02 feature test** - `faf156c` (test) — RED, confirmed failing only because `lecturer.subjects.*` routes did not yet exist.
2. **Task 2: SubjectController + Form Requests + views + routes** - `9fd4b24` (feat) — GREEN, all 7 SubjectControllerTest assertions pass; full suite (57 tests) green.

**Plan metadata:** committed separately below (docs commit).

## Files Created/Modified
- `app/Http/Controllers/Lecturer/SubjectController.php` - Resource CRUD controller
- `app/Http/Requests/Lecturer/StoreSubjectRequest.php` - Create validation (name required+unique, code nullable+unique)
- `app/Http/Requests/Lecturer/UpdateSubjectRequest.php` - Update validation (same rules, unique ignores self)
- `resources/views/lecturer/subjects/index.blade.php` - Subject list + create/edit/delete affordances
- `resources/views/lecturer/subjects/create.blade.php` - Create form
- `resources/views/lecturer/subjects/edit.blade.php` - Edit form, pre-filled via `old()`/model
- `tests/Feature/Lecturer/SubjectControllerTest.php` - CLS-02 feature coverage (create/update/delete/validation/RBAC)
- `routes/lecturer.php` - Added `Route::resource('subjects', SubjectController::class)` inside `role:lecturer` group
- `resources/views/lecturer/home.blade.php` - Replaced placeholder copy with a Subjects nav link

## Decisions Made
- Kept `authorize()` on both Form Requests returning `true` — the route group's `role:lecturer` middleware is the sole RBAC gate for subjects per D-09 (no per-record ownership).
- Used `Rule::unique('subjects', 'name')` / `Rule::unique('subjects', 'code')->ignore($subject)` (Laravel 11 fluent unique-rule convention) rather than string-based `unique:subjects,name` rules, for consistency with modern Laravel 11 style and easier `ignore()` composition on update.

## Deviations from Plan

None — plan executed exactly as written. `authorize()` design, unique-ignore-self behavior, and Blade component reuse all match the plan's `<action>` guidance.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `lecturer.subjects.*` routes and the `Subject` CRUD pattern are ready for `02-04` (exam authoring), which needs an existing subject to attach exams to.
- The resource-controller + Form Request + Blade pattern established here is the template the next CLS/EXM plans in this phase should follow.
- No blockers.

---
*Phase: 02-classroom-subject-exam-authoring*
*Completed: 2026-07-15*

## Self-Check: PASSED

All created files exist on disk; both task commits (`faf156c`, `9fd4b24`) verified present in git history.

---
phase: 02-classroom-subject-exam-authoring
plan: 04
subsystem: api
tags: [laravel, form-request, eloquent, blade, breeze, exam-authoring]

# Dependency graph
requires:
  - phase: 02-classroom-subject-exam-authoring (02-01)
    provides: ExamFactory (with ->published() state), SubjectFactory, lecturer()/student() UserFactory states
  - phase: 02-classroom-subject-exam-authoring (02-03)
    provides: Lecturer\ClassroomController CRUD + Form Request pattern this plan mirrors for Exam
provides:
  - "Lecturer\\ExamController: full resource CRUD (index/create/store/show/edit/update/destroy) + publish/unpublish actions"
  - "StoreExamRequest/UpdateExamRequest — subject_id/title/duration_minutes validation; UpdateExamRequest.authorize() enforces the D-06 draft-only mutation gate"
  - "exams resource routes + two explicit named routes (lecturer.exams.publish / lecturer.exams.unpublish) under role:lecturer"
  - "exam Blade views (index/create/edit/show) with draft/published-aware affordances"
  - "created_by stamped server-side from auth()->id(), never from request input"
affects: [02-05-question-authoring, 02-06-question-published-gate, phase-3-exam-assignment]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Draft-only mutation gate lives in a Form Request authorize() override (! $this->route('exam')->is_published), not a duplicated inline abort_if per method"
    - "destroy() has no Form Request, so its gate is an inline abort_if($exam->is_published, 403) guard — the one place a duplicate check is unavoidable"
    - "Publish/unpublish are two explicit named PATCH routes (not one toggle endpoint) for self-documenting route lists and independent testability"

key-files:
  created:
    - app/Http/Controllers/Lecturer/ExamController.php
    - app/Http/Requests/Lecturer/StoreExamRequest.php
    - app/Http/Requests/Lecturer/UpdateExamRequest.php
    - resources/views/lecturer/exams/index.blade.php
    - resources/views/lecturer/exams/create.blade.php
    - resources/views/lecturer/exams/edit.blade.php
    - resources/views/lecturer/exams/show.blade.php
    - tests/Feature/Lecturer/ExamControllerTest.php
    - tests/Feature/Lecturer/ExamPublishTest.php
  modified:
    - routes/lecturer.php
    - resources/views/lecturer/home.blade.php

key-decisions:
  - "created_by is merged server-side in ExamController@store from $request->user()->id — never accepted from $request->validated(), matching T-02-MA"
  - "UpdateExamRequest.authorize() is the single source of truth for the draft-only edit gate; a published exam's edit attempt returns 403 via Laravel's AuthorizationException, not a validation error"
  - "show.blade.php buttons (edit/delete/publish/unpublish) are UX-only convenience — hidden per state, but the server-side gate is what's actually authoritative per the plan's threat model"

patterns-established:
  - "Exam-level EXM-05 coverage (this plan) + question-level EXM-05 coverage (02-06's ExamPublishedEditGateTest) together satisfy the full requirement — this plan intentionally covers only the exam's own edit/delete gate"

requirements-completed: [EXM-01, EXM-05, EXM-06]

# Metrics
duration: 8min
completed: 2026-07-15
status: complete
---

# Phase 02 Plan 04: Exam CRUD + Publish/Unpublish + Draft-Only Edit Gate Summary

**Lecturer\ExamController delivers exam CRUD landing on its own show page, a Form-Request-enforced draft-only edit/delete gate, and two explicit publish/unpublish routes with reversible state — created_by always server-stamped.**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-07-15T14:12Z (approx, following 02-03 completion)
- **Completed:** 2026-07-15T14:19:53Z
- **Tasks:** 2
- **Files modified:** 11 (9 created, 2 modified)

## Accomplishments
- Full exam CRUD (`index/create/store/show/edit/update/destroy`) under `Lecturer\ExamController`, mirroring the 02-02/02-03 resource-controller + Form Request pattern
- `created_by` always stamped from `auth()->id()` server-side — a forged `created_by` field in the POST body is provably ignored (tested)
- Draft-only edit/delete gate: `UpdateExamRequest::authorize()` returns `! $exam->is_published`; `destroy()` guards inline with `abort_if($exam->is_published, 403)`
- Publish/unpublish implemented as two explicit named PATCH routes (`lecturer.exams.publish` / `lecturer.exams.unpublish`); unpublish is fully reversible and immediately re-enables editing (tested end-to-end)
- Exam show page lists questions (empty placeholder — 02-05 wires the add-question form) and exposes publish/edit/delete affordances gated by draft/published state

## Task Commits

Each task was committed atomically:

1. **Task 1: Exam CRUD (EXM-01) + draft-only edit gate** - `2b7ea11` (feat)
2. **Task 2: Publish/unpublish (EXM-06) + exam-level gate coverage** - `1950f0b` (feat)

**Plan metadata:** (this commit) `docs(02-04): complete Exam CRUD + publish/unpublish plan`

## Files Created/Modified
- `app/Http/Controllers/Lecturer/ExamController.php` - Resource CRUD + publish/unpublish actions; created_by stamped server-side
- `app/Http/Requests/Lecturer/StoreExamRequest.php` - subject_id/title/description/duration_minutes validation
- `app/Http/Requests/Lecturer/UpdateExamRequest.php` - Same validation + `authorize()` draft-only gate (D-06)
- `resources/views/lecturer/exams/index.blade.php` - Exam list with subject, duration, draft/published badge
- `resources/views/lecturer/exams/create.blade.php` - Create form with subject `<select>`
- `resources/views/lecturer/exams/edit.blade.php` - Draft-only edit form
- `resources/views/lecturer/exams/show.blade.php` - Exam detail, questions placeholder list, publish/edit/delete/unpublish buttons per state
- `routes/lecturer.php` - `Route::resource('exams', ...)` + explicit publish/unpublish PATCH routes
- `resources/views/lecturer/home.blade.php` - Added "Exams" nav link
- `tests/Feature/Lecturer/ExamControllerTest.php` - CRUD, forged created_by, validation, published-exam 403s, student 403
- `tests/Feature/Lecturer/ExamPublishTest.php` - Publish/unpublish, reversibility, student 403 on both actions

## Decisions Made
- `created_by` merge happens in the controller (`Exam::create([...$request->validated(), 'created_by' => $request->user()->id])`) rather than in the Form Request, keeping the Form Request focused purely on input shape and the mass-assignment-safety concern visibly co-located with the `Exam::create()` call.
- Split `show.blade.php` editing across the two tasks: Task 1 built the view with only edit/delete affordances (since publish/unpublish routes didn't exist yet); Task 2 added the publish/unpublish buttons once those routes existed. This avoided a broken `route()` call in Task 1's tests.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. Pint (`--dirty`) initially reformatted two unrelated, pre-existing untracked scaffold files (`bootstrap/providers.php`, `config/auth.php`) that are out of this plan's scope — these were not staged or committed; subsequent Pint runs were scoped explicitly to this plan's files only.

## Known Stubs

- `resources/views/lecturer/exams/show.blade.php` renders an empty "No questions yet." placeholder when `$exam->questions` is empty. This is intentional and matches the plan's explicit scope: 02-05 adds the add-question form and question authoring; this plan only needed the exam's own show page to exist as the landing point (D-05) and the relation to be wired for a future non-empty state.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `Exam` model, routes, and the draft-only gate pattern (Form Request `authorize()`) are in place for 02-05 (question authoring) and 02-06 (question-level EXM-05 gate) to build on directly.
- The exam show page's questions list is ready to be populated by 02-05's add-question form without further scaffolding changes.
- Publish/unpublish plumbing is ready for Phase 3 to gate `exam_classroom` assignment on `is_published`.

---
*Phase: 02-classroom-subject-exam-authoring*
*Completed: 2026-07-15*

## Self-Check: PASSED

All 9 created files found on disk; both task commits (2b7ea11, 1950f0b) found in `git log`.

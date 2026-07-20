---
phase: 03-exam-assignment-class-scoped-access
plan: 03
subsystem: auth
tags: [laravel, policies, eloquent-scopes, idor, rbac, blade]

# Dependency graph
requires:
  - phase: 03-exam-assignment-class-scoped-access
    provides: "03-01 RED test infrastructure (ExamIndexTest, ExamAccessTest); 03-02 exam_classroom assignment (Lecturer\\ExamAssignmentController, sync())"
provides:
  - "Exam::scopeVisibleTo(Builder, User) — single-source-of-truth published+assigned-to-classroom predicate, with an explicit null-classroom guard"
  - "ExamPolicy::takeable(User, Exam) — the direct-access IDOR gate, delegating entirely to Exam::visibleTo()"
  - "AuthorizesRequests trait on the base Controller — first $this->authorize() call in the project"
  - "Student\\ExamController@index (class-scoped list) + @show (policy-gated read-only landing)"
  - "student.exams.index / student.exams.show routes under role:student"
  - "Student home nav link to the exam list"
affects: [phase-04-exam-taking, phase-05-grading-results]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Shared Eloquent local scope (scopeVisibleTo) consumed by both an index query and a Policy method — eliminates index/gate divergence by construction"
    - "First app/Policies/*.php in the project; Laravel 11 auto-discovery, no manual registration"
    - "AuthorizesRequests trait added to the base Controller for all future $this->authorize() calls"
    - "Disabled Phase-4 UI seam pattern: inert button, no route() call to a not-yet-existing route name"

key-files:
  created:
    - app/Policies/ExamPolicy.php
    - app/Http/Controllers/Student/ExamController.php
    - resources/views/student/exams/index.blade.php
    - resources/views/student/exams/show.blade.php
  modified:
    - app/Models/Exam.php
    - app/Http/Controllers/Controller.php
    - routes/student.php
    - resources/views/student/home.blade.php

key-decisions:
  - "scopeVisibleTo keeps the explicit whereRaw('0 = 1') null-classroom branch rather than relying on where()'s null-coercion, per research Pitfall 2/Assumption A1"
  - "ExamPolicy::takeable delegates entirely to Exam::visibleTo() — no inline is_published/classroom_id re-derivation"
  - "Student show() authorizes before loading relations; loadCount('questions') only, never with('questions.options')"
  - "Start button rendered disabled with no route() call — avoids RouteNotFoundException until Phase 4 adds the attempt route"

patterns-established:
  - "Pattern: single shared query scope as the one source of truth for a visibility predicate, called from both a list endpoint and a Policy — the template Phase 4/5's AttemptPolicy should copy"
  - "Pattern: authorize() runs as the first statement after route-model binding in any student/lecturer show-style action, never inferred from binding alone"

requirements-completed: [ASN-02, RBAC-05]

# Metrics
duration: 5min
completed: 2026-07-15
status: complete
---

# Phase 3 Plan 3: Student Class-Scoped Exam Visibility + IDOR Gate Summary

**One shared `Exam::scopeVisibleTo` predicate drives both the student exam index and `ExamPolicy::takeable`, so a class-scoped list and a policy-gated direct-access route can never diverge — closing the IDOR vector RBAC-05 exists to prevent.**

## Performance

- **Duration:** 5 min
- **Started:** 2026-07-15T15:44:00Z (approx, from prior plan's completion commit)
- **Completed:** 2026-07-15T15:49:39Z
- **Tasks:** 3
- **Files modified:** 8 (4 created, 4 modified)

## Accomplishments
- `Exam::scopeVisibleTo(Builder, User)` — the single "published AND assigned to this student's classroom" predicate, with an explicit `whereRaw('0 = 1')` guard for `classroom_id = null`
- `ExamPolicy::takeable()` (first Policy in the project) reuses that exact scope — no second, independently-worded query to drift out of sync
- `Student\ExamController@show` gates with `$this->authorize('takeable', $exam)` before ever loading the exam relations — route-model binding alone never grants access
- `AuthorizesRequests` trait added to the base `Controller` (was an empty abstract class in this Laravel 11 skeleton) — the first `$this->authorize()` call in the codebase, and now available to every controller
- Read-only student landing page exposes only a question count (`loadCount('questions')`) plus title/subject/duration — never question bodies or option `is_correct` data
- Disabled Phase-4 "Start" seam with no `route()` call to a not-yet-existing attempt route
- Student home now links to `student.exams.index`

## Task Commits

Each task was committed atomically:

1. **Task 1: Shared visibility scope + ExamPolicy + AuthorizesRequests enablement** - `2879dcc` (feat)
2. **Task 2: Student\ExamController (scoped index + authorized show) + routes + views** - `2ace3a1` (feat)
3. **Task 3: Student home nav link to the exam list** - `dca13d5` (feat)

_No RED/GREEN split commits were needed — the RED tests (`ExamIndexTest`, `ExamAccessTest`) were already committed in plan 03-01; this plan's `tdd="true"` tasks made them GREEN directly._

## Files Created/Modified
- `app/Models/Exam.php` - added `scopeVisibleTo(Builder $query, User $user)`, the single visibility predicate
- `app/Policies/ExamPolicy.php` - new; `takeable(User, Exam)` delegates to `Exam::visibleTo()`
- `app/Http/Controllers/Controller.php` - added `use AuthorizesRequests;` (was empty)
- `app/Http/Controllers/Student/ExamController.php` - new; `index()` (scoped list) + `show()` (policy-gated read-only landing)
- `routes/student.php` - added `student.exams.index` / `student.exams.show` under the existing `role:student` group
- `resources/views/student/exams/index.blade.php` - new; class-scoped exam list with empty state
- `resources/views/student/exams/show.blade.php` - new; read-only landing + disabled Start seam
- `resources/views/student/home.blade.php` - replaced placeholder text with a welcome line + link to `student.exams.index`

## Decisions Made
- Kept the explicit `whereRaw('0 = 1')` else-branch in `scopeVisibleTo` rather than simplifying it away, per research Pitfall 2 / Assumption A1 — makes the null-classroom-sees-nothing behavior independent of Eloquent's `where(column, null)` auto-coercion.
- `ExamPolicy::takeable` calls `Exam::visibleTo($user)->whereKey($exam->id)->exists()` verbatim rather than restating conditions — single source of truth, matches research Pattern 2 exactly.
- No manual policy registration — Laravel 11 auto-discovers `App\Policies\ExamPolicy` for `App\Models\Exam` by naming convention.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. Baseline RED confirmed before starting (`ExamIndexTest`/`ExamAccessTest` failing with `RouteNotFoundException`); both files GREEN after Task 2's routes/controller/views landed, with no changes needed beyond what Task 1/2/3 specified.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `Exam::scopeVisibleTo` + `ExamPolicy` establish the exact pattern (shared scope + Policy delegating to it, `$this->authorize()` as the first statement after route-model binding) that Phase 4's `AttemptPolicy` and Phase 5's result-access gates should copy.
- The disabled "Start" seam on `student/exams/{exam}` is the exact spot Phase 4 will replace with a real form/link to the attempt-start route.
- Full suite (135 tests, 335 assertions) is green — no Phase 1/2 regressions from the shared `Exam` model change.
- Phase 3 (ASN-01, ASN-02, RBAC-05) is now fully complete across plans 03-01/03-02/03-03.

---
*Phase: 03-exam-assignment-class-scoped-access*
*Completed: 2026-07-15*

## Self-Check: PASSED

All created/modified files verified present; all 3 task commits (2879dcc, 2ace3a1, dca13d5) verified in git log.

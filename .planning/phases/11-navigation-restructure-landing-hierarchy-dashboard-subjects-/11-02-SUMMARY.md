---
phase: 11-navigation-restructure-landing-hierarchy-dashboard-subjects
plan: 02
subsystem: ui
tags: [laravel, blade, eloquent, dashboard, alpine, tailwind]

# Dependency graph
requires:
  - phase: 11-01
    provides: "x-welcome-banner, x-dashboard-card, x-back-button components; trimmed nav shell"
provides:
  - "Lecturer\\HomeController: scoped bounded-aggregate dashboard (classes this/future, enrolled/seats, awaiting grading) + assigned-subject list"
  - "lecturer.home as the single landing page: banner + 3 cards + ungrouped subject CRUD table"
  - "SubjectController scoped to assigned subjects with creator auto-assignment via subject_user"
affects: [12-lecturer-workspace]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Composite semester ordinal comparison in SQL via whereRaw('(year * 2 + (2 - semester)) >= ?', ...) mirroring Semester::ordinal() — never a naive year/semester compare"
    - "Dashboard aggregates are COUNT/SUM/withCount only, computed once per request, no PHP loop over relations"

key-files:
  created:
    - app/Http/Controllers/Lecturer/HomeController.php
    - tests/Feature/Lecturer/DashboardTest.php
  modified:
    - routes/lecturer.php
    - app/Http/Controllers/Lecturer/SubjectController.php
    - resources/views/lecturer/home.blade.php
    - resources/views/lecturer/subjects/create.blade.php
    - resources/views/lecturer/subjects/edit.blade.php
    - tests/Feature/Lecturer/SubjectControllerTest.php
    - tests/Feature/Lecturer/Phase2ReviewFixesTest.php

key-decisions:
  - "SubjectController@index no longer renders a second, divergent subject table — it redirects to lecturer.home (kept alive for reachability/route-name stability)"
  - "store/update/destroy redirects retargeted from lecturer.subjects.index to lecturer.home so every CRUD action lands back on the dashboard+subject-list hub"
  - "'Manage classes' row action interim-targets lecturer.sections.index; a Blade comment notes it retargets to the per-subject hub in Phase 12"

patterns-established:
  - "Pattern: lecturer.home is the canonical landing page for both dashboard aggregates and subject CRUD, consuming 11-01's shared components"

requirements-completed: [DASH-01, DASH-03, SUBJ-01, SUBJ-02]

# Metrics
duration: 55min
completed: 2026-07-18
status: complete
---

# Phase 11 Plan 02: Lecturer Dashboard + Subject CRUD Summary

**Lecturer\HomeController supplies four scoped bounded-aggregate stats (composite-ordinal "this & future semesters" count, enrolled/seats with progress bar, awaiting-grading count) and the assigned-subject list on a single rewritten lecturer/home.blade.php, with SubjectController CRUD retargeted to that same hub and creator auto-assignment wired through subject_user.**

## Performance

- **Duration:** ~55 min
- **Started:** 2026-07-18T00:00:00Z (approx, no prior session marker)
- **Completed:** 2026-07-18
- **Tasks:** 3 completed
- **Files modified:** 8 (2 created, 6 modified)

## Accomplishments
- `Lecturer\HomeController@index` computes classesThisAndFuture (composite-ordinal `whereRaw`), totalSeats/enrolledStudents, and awaitingGrading as pure COUNT/SUM/withCount queries scoped to the acting lecturer's assigned subjects — verified by `grep -nE "foreach|->each\("` returning nothing.
- `lecturer.home` route now resolves to `HomeController@index`; the view composes `x-welcome-banner` + three `x-dashboard-card` instances + an ungrouped subjects table (code/name/#classes/#exams) with Manage classes / Edit / `x-confirm-modal` delete row actions.
- `SubjectController@store` auto-assigns the creating lecturer via `$subject->lecturers()->syncWithoutDetaching(...)` so a freshly created subject is immediately visible on the creator's own scoped list; store/update/destroy/index all redirect to `lecturer.home`.
- Subject create/edit "Cancel" links replaced with `x-back-button` (UX-04).
- `DashboardTest` proves the composite-ordinal past-section exclusion (`assertViewHas('classesThisAndFuture', 1)` with a past section present) and cross-lecturer scoping (`assertDontSee` a second lecturer's subject).

## Task Commits

Each task was committed atomically:

1. **Task 1: Lecturer\HomeController — bounded dashboard aggregates + assigned subjects** - `346f325` (feat)
2. **Task 2: Scope SubjectController, auto-assign creator, retarget redirects** - `ff873bb` (feat)
3. **Task 3: Lecturer home view + dashboard tests** - `8219457` (feat)

**Deviation fix:** `722f402` (fix) — Phase2ReviewFixesTest redirect assertion updated to match Task 2's retargeted redirect.

## Files Created/Modified
- `app/Http/Controllers/Lecturer/HomeController.php` - scoped dashboard aggregates + subject list for the home page
- `routes/lecturer.php` - `home` route now resolves to `HomeController@index`
- `app/Http/Controllers/Lecturer/SubjectController.php` - creator auto-assignment on store; store/update/destroy/index redirect to `lecturer.home`
- `resources/views/lecturer/home.blade.php` - banner + 3 cards + ungrouped subject CRUD table
- `resources/views/lecturer/subjects/create.blade.php` - `x-back-button` replaces "Cancel"
- `resources/views/lecturer/subjects/edit.blade.php` - `x-back-button` replaces "Cancel"
- `tests/Feature/Lecturer/DashboardTest.php` - new: proves aggregates + scoping
- `tests/Feature/Lecturer/SubjectControllerTest.php` - new case: creator auto-assignment surfaces subject on home list
- `tests/Feature/Lecturer/Phase2ReviewFixesTest.php` - redirect assertion retargeted (deviation fix)

## Decisions Made
- Kept `SubjectController@index` and the `lecturer.subjects.index` route name alive (redirecting to `lecturer.home`) rather than deleting them, per the plan's explicit instruction to preserve reachability for existing references.
- Used `assertViewHas(...)` for exact aggregate-value assertions in `DashboardTest` rather than fragile `assertSee('1')`-style markup substring matches, since multiple cards can share the same digit.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Retargeted Phase2ReviewFixesTest's delete-blocked redirect assertion**
- **Found during:** Full-suite verification after Task 3
- **Issue:** Task 2's action explicitly retargets `SubjectController@destroy`'s "cannot delete, has exams" redirect from `route('lecturer.subjects.index')` to `route('lecturer.home')`. A pre-existing regression test (`Phase2ReviewFixesTest::test_deleting_a_subject_with_exams_is_blocked_and_preserves_exams`) — not listed in this plan's `files_modified` — asserted the old redirect target and became the one full-suite failure.
- **Fix:** Updated the assertion to `route('lecturer.home')`.
- **Files modified:** `tests/Feature/Lecturer/Phase2ReviewFixesTest.php`
- **Verification:** `php artisan test --filter=Phase2ReviewFixesTest` passes; full suite green.
- **Committed in:** `722f402`

---

**Total deviations:** 1 auto-fixed (1 Rule 1 bug fix)
**Impact on plan:** Direct, necessary fallout of the plan's own redirect-retargeting instruction. No scope creep.

## Issues Encountered
None beyond the deviation above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Lecturer landing hierarchy (dashboard + subject CRUD) is complete and tested; Phase 12 (Lecturer Workspace) can build the per-subject "class & student management" hub that the interim "Manage classes" link (currently `lecturer.sections.index`) will retarget to.
- Full test suite: 372 passing (baseline 369 + 3 new: 2 DashboardTest, 1 SubjectControllerTest), 0 failures.

---
*Phase: 11-navigation-restructure-landing-hierarchy-dashboard-subjects*
*Completed: 2026-07-18*

## Self-Check: PASSED

- FOUND: app/Http/Controllers/Lecturer/HomeController.php
- FOUND: tests/Feature/Lecturer/DashboardTest.php
- FOUND: .planning/phases/11-navigation-restructure-landing-hierarchy-dashboard-subjects-/11-02-SUMMARY.md
- FOUND commit: 346f325
- FOUND commit: ff873bb
- FOUND commit: 8219457
- FOUND commit: 722f402

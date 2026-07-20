---
phase: 11-navigation-restructure-landing-hierarchy-dashboard-subjects
plan: 03
subsystem: ui
tags: [laravel, blade, eloquent, dashboard, alpine, tailwind]

# Dependency graph
requires:
  - phase: 11-01
    provides: "x-welcome-banner, x-dashboard-card, x-back-button components; trimmed nav shell"
provides:
  - "Student\\HomeController: scoped bounded-aggregate dashboard (subjects enrolled this semester, exams available to take) + NEW enrolled-subjects-grouped-by-semester query"
  - "student.home as the single landing page: banner + 2 cards + grouped enrolled-subjects list + enroll button"
affects: [11-04-subject-browse-catalog, 13-class-page]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Composite semester ordinal comparison in SQL via whereRaw('(year * 2 + (2 - semester)) = ?', ...) mirroring Semester::ordinal() — never a naive year/semester compare"
    - "Dashboard aggregates are COUNT-only, computed once per request; the enrolled-subjects list is one eager-loaded query (section.subject.lecturers) transformed in memory, never a per-row query"
    - "Alpine x-data=\"{ showPast: false }\" + x-show=\"showPast\" toggle for collapsible past-semester groups, mirroring the existing x-data=\"{ editing: ... }\" toggle idiom in lecturer/results/show.blade.php"

key-files:
  created:
    - app/Http/Controllers/Student/HomeController.php
    - tests/Feature/Student/DashboardTest.php
    - tests/Feature/Student/SubjectListTest.php
  modified:
    - routes/student.php
    - resources/views/student/home.blade.php

key-decisions:
  - "The enrolled-subjects-by-semester query is genuinely NEW and distinct from SubjectBrowseController's catalog — grouped from the student's own Enrolled enrollments, not the full subject/section browse list (11-04 owns that page)."
  - "Semester grouping happens in PHP over an already-eager-loaded collection (Enrollment::with(['section.subject.lecturers'])->get()), not per-group queries — avoids N+1 while still allowing arbitrary semester bucketing."
  - "Past-group ordinal uses new Semester($current->year - 1, $current->number) in tests — guaranteed strictly less than the current ordinal regardless of which semester number is current, avoiding the S1/S2 rollover trap documented on Semester::ordinal()."
  - "Class-page action links to the existing student.subjects.show route as an explicit interim target (Blade comment), to be retargeted when Phase 13 ships the full class page."

patterns-established:
  - "Pattern: student.home is the canonical landing page for the student's own dashboard aggregates + their enrolled-subjects list, following the same banner+cards+list composition 11-02 established for the lecturer side."

requirements-completed: [DASH-04, SUBJ-03, SUBJ-04, SUBJ-05]

# Metrics
duration: 45min
completed: 2026-07-18
status: complete
---

# Phase 11 Plan 03: Student Dashboard + Subject List Summary

**Student\HomeController supplies two scoped bounded-aggregate stats (composite-ordinal "subjects enrolled this semester" count, "exams available to take" via Exam::visibleTo minus already-attempted/out-of-window) plus a NEW enrolled-subjects-grouped-by-semester query, rendered on a rewritten student/home.blade.php with an Alpine-gated collapsible past-semester region and an enroll-in-a-class button.**

## Performance

- **Duration:** ~45 min
- **Completed:** 2026-07-18
- **Tasks:** 3 completed
- **Files modified:** 5 (3 created, 2 modified)

## Accomplishments
- `Student\HomeController@index` computes `subjectsThisSemester` (composite-ordinal `whereRaw` scoped COUNT DISTINCT subject_id) and `examsAvailable` (`Exam::visibleTo` minus `whereDoesntHave('attempts', ...)`, bounded by the availability window) as pure COUNT queries — verified by `grep -nE "->lecturers\(\)->get|foreach.*->sections"` returning nothing.
- Built the NEW enrolled-subjects-by-semester structure from a single eager-loaded `Enrollment::with(['section.subject.lecturers'])->get()` call, grouped in memory by `Semester` ordinal (descending), split into `currentOrFutureGroups` (always visible) and `pastGroups` (collapsed by default) — distinct from `SubjectBrowseController`'s catalog, which 11-04 owns.
- `student.home` route now resolves to `HomeController@index`; the view composes `x-welcome-banner` + two `x-dashboard-card` instances + an "Enroll in a class" button (linking to `student.subjects.index`) + the grouped subject tables (Subject / Lecturer / class-page action columns), with past groups wrapped in `x-data="{ showPast: false }"` / `x-show="showPast"`.
- Each subject row shows the joined lecturer name(s) or "Unassigned" (subject_user pivot), and its class-page action links to `student.subjects.show` as an explicit interim target pending Phase 13's full class page.
- `DashboardTest` and `SubjectListTest` prove the composite-ordinal past-enrollment exclusion, the exams-available count dropping after an attempt, lecturer-name rendering, the `showPast` toggle presence, the enroll/class-page links, and N+1-safe rendering with multiple enrolled subjects.

## Task Commits

Each task was committed atomically:

1. **Task 1: Student\HomeController — two bounded aggregates + NEW enrolled-by-semester query** - `c1ca5bb` (feat)
2. **Task 2: Student home view — banner + 2 cards + grouped subject list with hide/unhide + enroll button** - `d4d4af6` (feat)
3. **Task 3: Student dashboard + subject-list tests** - `468ce63` (test)

## Files Created/Modified
- `app/Http/Controllers/Student/HomeController.php` - scoped dashboard aggregates + the NEW enrolled-subjects-by-semester query
- `routes/student.php` - `home` route now resolves to `HomeController@index`
- `resources/views/student/home.blade.php` - banner + 2 cards + grouped enrolled-subjects list + enroll button + collapsible past semesters
- `tests/Feature/Student/DashboardTest.php` - new: proves aggregate scoping (this-semester exclusion, exams-available decrement)
- `tests/Feature/Student/SubjectListTest.php` - new: proves lecturer-name render, showPast toggle, enroll/class-page links, N+1 safety, Unassigned fallback

## Decisions Made
- Used `assertViewHas(...)` for exact aggregate-value assertions in `DashboardTest`, mirroring 11-02's `Lecturer\DashboardTest` idiom, avoiding fragile `assertSee('1')`-style markup substring matches.
- Grouped semester buckets keyed by ordinal in a plain PHP array (`krsort` for descending order, `array_filter` to split current/future vs past) rather than a Collection pipeline — kept the transform readable and avoided a second pass over the eager-loaded data.

## Deviations from Plan
None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Student landing hierarchy (dashboard + enrolled-subjects list) is complete and tested; 11-04 can now build `SubjectBrowseController`'s catalog page independently, since this plan's enrolled-subjects query stays intentionally scoped to `Enrolled` enrollments only.
- Full test suite: 377 passing (baseline 372 + 5 new: 2 DashboardTest, 3 SubjectListTest), 0 failures.

---
*Phase: 11-navigation-restructure-landing-hierarchy-dashboard-subjects*
*Completed: 2026-07-18*

## Self-Check: PASSED

- FOUND: app/Http/Controllers/Student/HomeController.php
- FOUND: tests/Feature/Student/DashboardTest.php
- FOUND: tests/Feature/Student/SubjectListTest.php
- FOUND: .planning/phases/11-navigation-restructure-landing-hierarchy-dashboard-subjects-/11-03-SUMMARY.md
- FOUND commit: c1ca5bb
- FOUND commit: d4d4af6
- FOUND commit: 468ce63

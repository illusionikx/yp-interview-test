---
phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix
plan: 06
subsystem: ui
tags: [blade, flowbite, dark-mode, status-pill, section-rename, classroom-sweep]

# Dependency graph
requires:
  - phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix (plan 01)
    provides: Flowbite/Tailwind dark-mode foundation, pre-paint bootstrap script, x-status-pill component
  - phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix (plan 04)
    provides: "ExamController@show's $exam->sections/$sections view data and ExamAssignmentController's section_ids-based update() route, which this plan's exam-assignment panel now consumes"
  - phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix (plan 05)
    provides: Flowbite navbar shell, x-status-pill component, card/typography/color conventions this plan reuses verbatim
provides:
  - "Dark-aware Flowbite reskin of every remaining lecturer/student content view not already covered by 07-05: dashboard.blade.php, lecturer/exams/{index,show}.blade.php, lecturer/results/{index,show}.blade.php, student/exams/{index,show}.blade.php, student/results/show.blade.php, student/attempts/submitted.blade.php"
  - "lecturer/exams/show.blade.php's exam-assignment panel rebuilt as a section-assignment panel (section_ids[] checkboxes over $sections, checked against $exam->sections, posting to the existing lecturer.exams.assignment.update route) — completes the view-side classroom-to-section rename sweep alongside 07-05's sections/subjects work"
  - "x-status-pill now used for exam Published/Draft state on both the lecturer exams index and show pages"
affects: ["07-07 (seeder + full test sweep — the ~72 pre-existing Classroom-fixture test failures remain that plan's scope, unaffected by this reskin)"]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Continued 07-05's hand-authored blue-600/dark:blue-500 CTA + red-600/dark:red-500 destructive button convention in place of the non-dark-aware x-primary-button/x-secondary-button/x-danger-button Breeze components, for every button touched in this plan's 9 files (Publish/Unpublish/Delete/Update assignment/Save Score/Start-Resume Exam)"
    - "Grading-lifecycle badges (Graded/Submitted on lecturer results index+show) kept as hand-rolled pills with matching dark: variants rather than routed through x-status-pill, since 'Graded'/'Submitted' are not part of the UI-SPEC's locked four-state semantic palette (Enrolled/Published/Open=green, Draft/Withdrawn/Opens=gray, Rejected/Closed=red, FULL=amber) — reserves x-status-pill for the states it was actually built to represent"
    - "Section-assignment checkbox labels show '{{ $section->subject->name }} · {{ $section->name }}' since $sections is a flat cross-subject list (unlike the per-subject-nested sections list in 07-05's Sections panel), so the lecturer can disambiguate which subject each section belongs to"

key-files:
  modified:
    - resources/views/dashboard.blade.php
    - resources/views/lecturer/exams/index.blade.php
    - resources/views/lecturer/exams/show.blade.php
    - resources/views/lecturer/results/index.blade.php
    - resources/views/lecturer/results/show.blade.php
    - resources/views/student/exams/index.blade.php
    - resources/views/student/exams/show.blade.php
    - resources/views/student/results/show.blade.php
    - resources/views/student/attempts/submitted.blade.php

key-decisions:
  - "Replaced lecturer/exams/show.blade.php's classroom checkbox loop with a section checkbox loop reading the controller's existing $sections (all sections, cross-subject) and $exam->sections (currently assigned) — no controller change needed, since ExamController@show already provided both variables from 07-04"
  - "Kept the exam-assignment panel's submit copy as 'Update assignment' (not locked by 07-UI-SPEC's Copywriting Contract) and renamed the panel heading from 'Assign to classes' to 'Assign to sections'"
  - "Left lecturer.exams.questions._form.blade.php (the '@include'd add-question partial) untouched — it is not in this plan's files_modified list; only the surrounding card wrapper in exams/show.blade.php was made dark-aware"
  - "student/attempts/submitted.blade.php and student/exams/show.blade.php do not display any status word that maps to the x-status-pill's four locked semantic states, so no pill was introduced there — 'status pills where applicable' resolved to 'not applicable' for those two views"

patterns-established: []

requirements-completed: [UI-01]

# Metrics
duration: 25min
completed: 2026-07-16
status: complete
---

# Phase 7 Plan 06: Remaining Content-View Reskin + Classroom→Section View Sweep Summary

**Reskinned the last 9 lecturer/student content views to the dark-aware Flowbite card shell and completed the view-side classroom→section rename by rebuilding the exam-assignment panel around `$exam->sections`/`section_ids[]`.**

## Performance

- **Duration:** ~25 min
- **Completed:** 2026-07-16
- **Tasks:** 2 completed
- **Files modified:** 9

## Accomplishments

- Reskinned `dashboard.blade.php`, `lecturer/exams/{index,show}.blade.php`, and `lecturer/results/{index,show}.blade.php` to the dark-aware Flowbite card shell (`bg-white dark:bg-gray-800`, `blue-600`/`dark:blue-500` links and CTAs, `red-600`/`dark:red-500` destructive actions), matching 07-05's established conventions exactly.
- Replaced the hand-rolled Published/Draft badges on the lecturer exams index and show pages with `<x-status-pill>`.
- Rebuilt `lecturer/exams/show.blade.php`'s classroom-assignment panel as a section-assignment panel: checkboxes iterate `$sections` (all sections, labelled `{subject} · {section}`), are checked against `$exam->sections`, and submit `section_ids[]` to the existing `lecturer.exams.assignment.update` route from 07-04 — no controller or route changes were needed since `ExamController@show` already supplied both variables.
- Reskinned `student/exams/index.blade.php`, `student/exams/show.blade.php`, `student/results/show.blade.php`, and `student/attempts/submitted.blade.php` to the same dark-aware shell; updated the one remaining "classroom" comment in `student/exams/index.blade.php` to "section" wording. No visibility logic changed — every view still renders only what its controller already loaded (`Exam::visibleTo()`, no in-view query added).
- Confirmed via `grep -ri classroom resources/views` that zero view files reference classroom terminology after this plan.
- Verified all 9 changed Blade files compile without parse errors (`blade.compiler->compileString()` + `php -l` on the compiled output for each file) and that `npm run build` succeeds.
- Ran a throwaway render-smoke test (not committed, matching 07-05's precedent) covering: lecturer exam show with a section assigned (asserts "Assign to sections" heading, the section name, and the absence of "classroom" anywhere in the response), lecturer exams index, student exams index, and student exam show for a student enrolled in the exam's section — all 4 render 200 with no Blade/PHP errors.

## Task Commits

Each task was committed atomically:

1. **Task 1: Lecturer content views — classroom→section sweep + Flowbite/dark reskin** - `864f167` (feat)
2. **Task 2: Student content views — classroom→section sweep + Flowbite/dark reskin** - `a3982fd` (feat)

**Plan metadata:** (this commit)

## Files Created/Modified

- `resources/views/dashboard.blade.php` - dark-aware card wrapper (route always redirects by role before reaching this view; reskinned for shell consistency per the plan's file list)
- `resources/views/lecturer/exams/index.blade.php` - dark-aware table/card, `x-status-pill` for Published/Draft, blue CTA/links
- `resources/views/lecturer/exams/show.blade.php` - dark-aware card, `x-status-pill`, section-assignment panel (section_ids[] over $sections/$exam->sections), hand-authored blue/red buttons
- `resources/views/lecturer/results/index.blade.php` - dark-aware table/card, dark-variant Graded/Submitted badges
- `resources/views/lecturer/results/show.blade.php` - dark-aware cards, dark-variant status badge, dark-aware grading form
- `resources/views/student/exams/index.blade.php` - dark-aware card, "classroom" comment updated to "section"
- `resources/views/student/exams/show.blade.php` - dark-aware card, hand-authored blue Start/Resume button
- `resources/views/student/results/show.blade.php` - dark-aware cards for both awaiting and graded states
- `resources/views/student/attempts/submitted.blade.php` - dark-aware confirmation card

## Decisions Made

- Continued 07-05's pattern of hand-authored blue-600/dark:blue-500 and red-600/dark:red-500 buttons in place of the non-dark-aware `x-primary-button`/`x-secondary-button`/`x-danger-button` Breeze components, for every button touched across this plan's 9 files.
- Kept lecturer results' Graded/Submitted badges as hand-rolled pills (with dark: variants matching x-status-pill's own palette values) rather than routing them through `<x-status-pill>`, since "Graded"/"Submitted" fall outside the UI-SPEC's locked four-state semantic system.
- Left `lecturer/exams/questions/_form.blade.php` (the add-question partial included by `exams/show.blade.php`) untouched, since it is not in this plan's `files_modified` list — only its surrounding card wrapper was reskinned.

## Deviations from Plan

None — plan executed exactly as written. `ExamController@show` and `ExamAssignmentController` already provided everything the section-assignment panel needed (from 07-04), so no controller changes were required.

## Issues Encountered

- Ran the plan's `<verify>` automated filters (`ExamPublishTest`, `ResultTest` for lecturer/student, `ExamIndexTest`, and the broader `Student` namespace). `ExamPublishTest` (5/5) passes cleanly. All other failures are pre-existing `Class "App\Models\Classroom" not found` fixture-setup errors in test files that still construct `Classroom::factory()` — the `Classroom` model/factory were removed in 07-03/07-04 and this is explicitly documented in 07-04-SUMMARY.md and 07-05-SUMMARY.md as the ~72-failure gap deferred to 07-07's test sweep. None of these failures occur inside the views this plan touched (they fail in test fixture setup, before any HTTP request reaches a controller or view) and none are newly introduced by this plan — confirmed by inspecting each failure's stack trace and by the throwaway render-smoke test proving the reskinned views render correctly end-to-end against the current section/enrollment schema.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All lecturer/student content views are now dark-aware and classroom-free; `grep -ri classroom resources/views` returns zero matches.
- `npm run build` succeeds; all 9 changed Blade files compile without parse errors.
- 07-07's test-sweep scope is unchanged and still needed: `ClassroomControllerTest`, `ClassroomRosterTest`, `ExamAssignmentTest`, `ExamIndexTest` (student), `ResultTest` (lecturer+student), `AttemptAnswerTest`, `AttemptPolicyTest`, `AttemptShowTest`, `AttemptStartTest`, `AttemptSubmitTest`, `ExamAccessTest`, `Phase4ReviewFixesTest`, and any other suite still constructing `Classroom::factory()` need their fixtures rewritten to `Section`/`Enrollment`.
- No blockers for 07-07/07-08.

---
*Phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix*
*Completed: 2026-07-16*

## Self-Check: PASSED

- FOUND: `resources/views/dashboard.blade.php`
- FOUND: `resources/views/lecturer/exams/index.blade.php`
- FOUND: `resources/views/lecturer/exams/show.blade.php`
- FOUND: `resources/views/lecturer/results/index.blade.php`
- FOUND: `resources/views/lecturer/results/show.blade.php`
- FOUND: `resources/views/student/exams/index.blade.php`
- FOUND: `resources/views/student/exams/show.blade.php`
- FOUND: `resources/views/student/results/show.blade.php`
- FOUND: `resources/views/student/attempts/submitted.blade.php`
- FOUND commits: `864f167`, `a3982fd`
- `php artisan test --filter=ExamPublishTest` → 5/5 GREEN
- `npm run build` → succeeds
- All 9 changed Blade files compile with no parse errors (`php -l` on compiled output)
- `grep -ri classroom resources/views` → 0 matches

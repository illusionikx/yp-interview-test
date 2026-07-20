---
phase: 05-grading-results
plan: 04
subsystem: grading
tags: [laravel, blade, grading, results]

# Dependency graph
requires:
  - phase: 05-grading-results
    plan: 03
    provides: Lecturer\ResultController@show (per-attempt breakdown), fixed route-name contract (lecturer.results.show, lecturer.attempts.answers.grade), formatNumber decimal-trim convention
provides:
  - Lecturer\ResultController@index — per-exam attempts list (student, status, score)
  - resources/views/lecturer/results/index.blade.php (results index table + empty state)
  - GET lecturer/exams/{exam}/results -> lecturer.results.index
  - "View Results" entry point on resources/views/lecturer/exams/show.blade.php
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Score column never renders a partial number while pending — a plain em-dash until status===graded, mirroring the student result view's D-07/Pitfall-3 discipline applied to a list context."
    - "Action link copy (Grade vs View) is derived purely from attempt.status, since AttemptGrader::syncStatus() already guarantees a 'submitted' attempt has pending open-text work — no separate per-row pending-count query needed on the index."

key-files:
  created:
    - resources/views/lecturer/results/index.blade.php
  modified:
    - app/Http/Controllers/Lecturer/ResultController.php
    - routes/lecturer.php
    - resources/views/lecturer/exams/show.blade.php

key-decisions:
  - "Route named results.index (full name lecturer.results.index) rather than the plan frontmatter's literal 'exams.results.index' text, matching the pinned RED test's route('lecturer.results.index', $exam) call and the existing results.show naming convention already established in 05-01/05-03."
  - "Attempts sorted by student name in the controller (collection sort after eager-load) for a stable, readable table order — not specified by the plan or the pinned test, but consistent with a browse index's expected UX."

patterns-established:
  - "Lecturer list/table views computing a totalPossible from exam.questions()->sum('points') once per request, passed to the view rather than recomputed per row."

requirements-completed: [GRD-05]

# Metrics
duration: 8min
completed: 2026-07-16
status: complete
---

# Phase 5 Plan 4: Lecturer Per-Exam Results Index Summary

**Lecturer results index (`lecturer.results.index`) lists every attempt on an exam with a status badge, a dash-while-pending score column, and a Grade/View drill-in link into the 05-03 grading screen — closing GRD-05 and the entire grading-results phase.**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-07-16
- **Completed:** 2026-07-16
- **Tasks:** 2
- **Files modified:** 4 (1 created, 3 modified)

## Accomplishments
- `Lecturer\ResultController@index` loads an exam's attempts with `user` eager-loaded, sorts them by student name, and computes `totalPossible` once via `exam->questions()->sum('points')`.
- `resources/views/lecturer/results/index.blade.php` renders the UI-SPEC Screen 2 table (Student / Status / Score / Action), reusing the `lecturer/exams/index.blade.php` table pattern and the `show.blade.php` decimal-trim `formatNumber` convention; score is always `"—"` while `status !== 'graded'`, never a partial number; the empty state renders a single colspan row with "No submissions yet".
- New route `GET lecturer/exams/{exam}/results -> lecturer.results.index`, registered alongside the existing `results.show` route inside the `role:lecturer` group.
- "View Results" link added to the lecturer exam show page's action row, visible regardless of publish state (D-06 sole entry point).
- `Lecturer\ResultTest` both methods (`test_index_lists_attempts_per_exam`, `test_show_renders_breakdown`) GREEN; full suite 171 passed, 0 failed — the Phase 1-5 regression gate is clean.

## Task Commits

Each task was committed atomically:

1. **Task 1: Lecturer results index — ResultController@index, route, and index view** - `d5c22fe` (feat)
2. **Task 2: "View Results" entry point on the lecturer exam page + full-suite gate** - `ee3cbb5` (feat)

## Files Created/Modified
- `app/Http/Controllers/Lecturer/ResultController.php` - added `index(Exam $exam)`, sorted attempts + `totalPossible`
- `resources/views/lecturer/results/index.blade.php` - new: per-exam attempt table + empty state
- `routes/lecturer.php` - added `results.index` route
- `resources/views/lecturer/exams/show.blade.php` - added "View Results" link to the action row

## Decisions Made
All key decisions are captured in the frontmatter `key-decisions` above (route-name source of truth, attempt sort order).

## Deviations from Plan

None - plan executed exactly as written. The route name (`results.index`/`lecturer.results.index`) matched the pinned RED test's `route('lecturer.results.index', $exam)` call directly, so no name reconciliation was needed as it was in 05-03 (the plan frontmatter's shorthand "exams.results.index" `key_links` pattern text was read as a loose cross-reference, not a literal route name — the test was authoritative and matched the implemented name on the first pass).

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- GRD-05 complete; Phase 5 (Grading & Results) is functionally complete — every submitted attempt is reachable, gradeable, and reviewable per exam and per student.
- Full suite: 171 passed, 0 failed — no regression across Phases 1-5.
- This is the last feature phase per the roadmap; no further plans queued under `05-grading-results`.

---
*Phase: 05-grading-results*
*Completed: 2026-07-16*

## Self-Check: PASSED

All modified/created files found on disk (`app/Http/Controllers/Lecturer/ResultController.php`, `resources/views/lecturer/results/index.blade.php`, `routes/lecturer.php`, `resources/views/lecturer/exams/show.blade.php`, `.planning/phases/05-grading-results/05-04-SUMMARY.md`); both task commit hashes (`d5c22fe`, `ee3cbb5`) found in `git log`.

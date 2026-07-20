---
phase: 12-lecturer-workspace-class-management-exam-editor-grading
plan: 03
subsystem: ui
tags: [laravel, blade, grading, results, progress]

# Dependency graph
requires:
  - phase: 5
    provides: ResultController/AnswerGradeController/GradeAnswerRequest grading backend (D-06, GRD-02/03/05)
  - phase: 10
    provides: AttemptVoider::summarize() bounded grouped-COUNT aggregate on attempts.status
provides:
  - "GRD-06: lecturer.results.index (the grading page) shows exam details (title, subject, duration, draft/published pill) and a bounded graded/gradable progress indicator above the full student attempt list"
  - "ResultController::index passes a progress array (graded/needingGrading/gradableTotal) computed via app(AttemptVoider::class)->summarize($exam) — no per-attempt loop"
affects: [12-04]

tech-stack:
  added: []
  patterns:
    - "Reused AttemptVoider::summarize() (built for the CLS-07/EDT-04 reset-warning modal) as the bounded aggregate source for a second, unrelated UI surface — one grouped COUNT query, one source of truth for graded/ungraded counts"
    - "Grading-progress header card mirrors the exams/index.blade.php status-pill usage and the exams/show.blade.php x-back-button/tab-hub retarget pattern"

key-files:
  created:
    - tests/Feature/Lecturer/GradingPageTest.php
  modified:
    - app/Http/Controllers/Lecturer/ResultController.php
    - resources/views/lecturer/results/index.blade.php

key-decisions:
  - "gradableTotal is graded + submittedUngraded only (excludes in_progress attempts) — an attempt a student hasn't submitted yet is not 'needing grading', so it's correctly excluded from both the numerator and denominator of the graded/gradable figure."
  - "Back-to-exam link retargeted from lecturer.exams.show to the subjects.manage exams tab (?tab=exams), matching the identical retarget already established in exams/show.blade.php (UX-04 — the back affordance now names its real destination)."

patterns-established: []

requirements-completed: [GRD-06]

# Metrics
duration: 20min
completed: 2026-07-18
status: complete
---

# Phase 12 Plan 03: Grading Page Summary

**Turned the lecturer per-exam results index into the GRD-06 grading page by adding an exam-details + bounded grading-progress header above the unchanged full student attempt list, reusing `AttemptVoider::summarize()` as the sole progress aggregate.**

## Performance

- **Duration:** ~20 min
- **Tasks:** 2 completed
- **Files modified:** 3 (2 production, 1 new test file)

## Accomplishments
- `ResultController::index()` now eager-loads `exam.subject` and computes a `progress` array (`graded`, `needingGrading`, `gradableTotal`) via `app(AttemptVoider::class)->summarize($exam)` — the same one grouped `COUNT(*)` query already used by the CLS-07/EDT-04 reset-warning modal, so this page never introduces a second, possibly divergent way to count graded/ungraded attempts.
- `results/index.blade.php` gained a header card above the existing student table: exam title, subject name, duration, a `<x-status-pill>` for draft/published, and a "N / M graded" progress line + Tailwind progress bar, with a "No submissions to grade yet." fallback when `gradableTotal` is 0 (divide-by-zero guarded).
- The existing student table (Student / Status / Score / Grade|View action into `results.show`) is untouched — it already listed every student with an attempt; this plan only adds the surrounding context.
- The "Back to exam" link was retargeted to `route('lecturer.subjects.manage', ['subject' => $exam->subject_id]) . '?tab=exams'` via `<x-back-button>`, matching the identical pattern already shipped in `exams/show.blade.php` (UX-04).
- `show()` and `AnswerGradeController` are unchanged — per-attempt grading behavior is untouched.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add exam-details + grading-progress header to the grading page** - `00516b6` (feat)
2. **Task 2: Feature test the grading page details, progress, and full student list** - `ef2f608` (test)

**Plan metadata:** (this commit)

## Files Created/Modified
- `app/Http/Controllers/Lecturer/ResultController.php` - `index()` loads `exam.subject`, computes `progress` via `AttemptVoider::summarize()`, passes it to the view
- `resources/views/lecturer/results/index.blade.php` - new header card (exam details + progress bar/fallback), "Back to exam" retargeted to the subjects-manage exams tab via `<x-back-button>`
- `tests/Feature/Lecturer/GradingPageTest.php` - new: exam details visible, exact progress aggregate via `assertViewHas`, no-submissions fallback, every seeded student's name + grade/view link

## Decisions Made
- `gradableTotal = graded + submittedUngraded` (excludes `in_progress`), matching `AttemptVoider::summarize()`'s existing semantics — an attempt nobody has submitted yet was never "needing grading".
- Reused the exact back-link retarget pattern already established in `exams/show.blade.php` rather than inventing a new destination, per the plan's stated preference for the hub over the (still-valid) editor route.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- 12-04's exams-tab per-exam grading link can point at `lecturer.results.index` unchanged — no interface changes needed on this plan's side.
- Full suite: 403 passing (baseline 401 + 2 new `GradingPageTest` tests), 0 failing.

---
*Phase: 12-lecturer-workspace-class-management-exam-editor-grading*
*Completed: 2026-07-18*

## Self-Check: PASSED

All created/modified files exist on disk; both task commit hashes (00516b6, ef2f608) are present in git log.

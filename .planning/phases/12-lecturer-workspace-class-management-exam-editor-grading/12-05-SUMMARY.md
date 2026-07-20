---
phase: 12-lecturer-workspace-class-management-exam-editor-grading
plan: 05
subsystem: ui
tags: [laravel, blade, alpine, eloquent, tdd]

# Dependency graph
requires:
  - phase: 12-lecturer-workspace-class-management-exam-editor-grading
    provides: "12-02's merged exam editor (Details/Questions tabs on lecturer.exams.show) and 12-01's routes/lecturer.php scaffold"
provides:
  - "QuestionReorderController with moveQuestion/moveOption/shuffleOptions position-swap actions"
  - "Default orderBy('position') on Exam::questions() and Question::options()"
  - "Move-up/down + shuffle controls on the editor Questions tab (EDT-03, EDT-05)"
affects: [phase-13-student-taking-flow]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Adjacent-sibling position swap via where('position', '<'/'>', $current)->orderBy(...)->first() rather than array re-indexing — boundary is naturally a no-op when the sibling query returns null."
    - "One-time authoring shuffle: shuffle(range(0, n-1)) assigned to a fetched collection, all inside one DB::transaction — never re-derived at any read/take path."

key-files:
  created:
    - app/Http/Controllers/Lecturer/QuestionReorderController.php
    - tests/Feature/Lecturer/QuestionReorderTest.php
  modified:
    - app/Models/Exam.php
    - app/Models/Question.php
    - routes/lecturer.php
    - resources/views/lecturer/exams/show.blade.php

key-decisions:
  - "Reorder/shuffle never call AttemptVoider or delete anything — they only mutate `position`, never `is_correct`, so they are structurally non-destructive and do not trigger EDT-04's warn-and-void (proven by a dedicated attempt-survives test)."
  - "Move-up/down only, no drag-and-drop (Decision #8) — zero new dependencies."
  - "Shuffle is a one-shot authoring-time POST, never re-derived at read/take time (Decision #2) — TAK-12 (Phase 13) is the separate per-student runtime mechanism."

patterns-established:
  - "Non-destructive authoring mutation controller pattern: no Form Request needed for simple `direction in:up,down` validation; inline $request->validate() suffices when there's no create/update side effects to gate."

requirements-completed: [EDT-03, EDT-05]

# Metrics
duration: ~20min
completed: 2026-07-18
status: complete
---

# Phase 12 Plan 05: Question/Option Reordering + One-Time Shuffle Summary

**Move-up/move-down position swaps for questions and MCQ options, plus a single authoring-time option shuffle, added to the exam editor's Questions tab via a dedicated non-destructive `QuestionReorderController`.**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-07-18T19:20:00+08:00 (approx)
- **Completed:** 2026-07-18T19:41:31+08:00
- **Tasks:** 3 completed
- **Files modified:** 6 (2 created, 4 modified)

## Accomplishments
- `QuestionReorderController` with `moveQuestion`, `moveOption`, and `shuffleOptions` — all wrapped in `DB::transaction`, all reusing the shipped `abort_unless($question->exam_id === $exam->id, 404)` nested-binding idiom.
- `Exam::questions()` and `Question::options()` now default to `orderBy('position')`, so every consumer (editor, this controller's own read-back, and eventually the student-taking flow) renders in authored order without re-specifying it.
- The Questions tab shows each question's ordinal number with move-up/down chevrons to its left, and each MCQ option with its own move-up/down pair plus one per-question "Shuffle options" button — boundaries disabled via `$loop->first`/`$loop->last`.
- `QuestionReorderTest` (13 tests, 36 assertions): question/option swaps, boundary no-ops, shuffle permutation validity + `is_correct` preservation, non-destructive attempt survival, nested-binding 404s, position-ordered rendering for both questions and options, and role gating across all three routes.

## Task Commits

Each task was committed atomically (Task 1 followed the RED→GREEN TDD gate per its `tdd="true"` attribute):

1. **Task 1: Reorder routes, controller, and default position ordering** (`tdd="true"`)
   - `74cafb4` (test) — RED: failing tests for the not-yet-built controller/routes (confirmed all 11 initial cases failed with `RouteNotFoundException` before implementation existed)
   - `17ef21c` (feat) — GREEN: `QuestionReorderController`, the three PATCH routes, and default `orderBy('position')` on both models
2. **Task 2: Move-up/down and shuffle controls on the editor Questions tab** - `ff93f25` (feat)
3. **Task 3: Feature tests for swaps, shuffle, non-destructiveness, and ordering** - `850c769` (test) — extended the same file with option-order-after-move rendering and a student-403 case for the option-move route

**Plan metadata:** (this commit, docs)

## Files Created/Modified
- `app/Http/Controllers/Lecturer/QuestionReorderController.php` - three actions: `moveQuestion`, `moveOption`, `shuffleOptions`, each nested-binding-checked and transactional
- `app/Models/Exam.php` - `questions()` now `orderBy('position')`
- `app/Models/Question.php` - `options()` now `orderBy('position')`
- `routes/lecturer.php` - registers `exams.questions.move`, `exams.questions.options.move`, `exams.questions.options.shuffle`
- `resources/views/lecturer/exams/show.blade.php` - move-up/down controls beside each question number and each option, plus a per-question shuffle button
- `tests/Feature/Lecturer/QuestionReorderTest.php` - 13 tests covering the full acceptance surface

## Decisions Made
- No Form Request class for the reorder actions — the only input is `direction in:up,down`, validated inline via `$request->validate()`; a dedicated Form Request would add indirection with no benefit since there's no ownership/authorize() logic beyond the shared `role:lecturer` route-group gate (D-09, consistent with ExamQuestionController's existing pattern for this domain).
- Chose a fetch-then-write-back approach for shuffle (`$question->options()->get()` then `shuffle(range(0, n-1))` assigned per option) over a raw SQL `CASE` update — simpler, transactional, and the option count per question is always small (Decision #2's authoring-time-only design never needs to scale this to many rows).

## Deviations from Plan

### Auto-fixed Issues

**1. [TDD gate correction] Task 1 implementation was written before its RED test**
- **Found during:** Task 1 — before committing, noticed the controller/model/route changes were already written in the working tree while `tdd="true"` requires a failing test to exist and be committed first.
- **Issue:** Writing implementation before the RED test violates the plan's TDD gate sequence for Task 1.
- **Fix:** Backed up the implementation files, reverted them to their pre-change committed state, wrote `QuestionReorderTest.php`'s core cases, ran the suite and confirmed all 11 cases failed with `RouteNotFoundException` (genuine RED — not a false pass), committed the test file, restored the implementation, re-ran and confirmed all 11 passed (GREEN), then committed the implementation separately.
- **Files modified:** none beyond the plan's own file list — this was a commit-ordering correction, not a code change.
- **Verification:** `git log` shows `test(12-05)` at `74cafb4` before `feat(12-05)` at `17ef21c`.
- **Committed in:** `74cafb4` (test/RED), `17ef21c` (feat/GREEN)

---

**Total deviations:** 1 (process correction to satisfy the TDD gate; no scope creep, no code behavior change from what was planned)
**Impact on plan:** None on functionality — the eventual code matches the plan's `<action>` spec exactly. The correction only reordered how commits were made to honor `tdd="true"`.

## Issues Encountered

Two acceptance-criteria grep checks produced benign false positives, both confirmed non-issues by direct inspection:
- `grep -c "AttemptVoider\|->delete(" app/Http/Controllers/Lecturer/QuestionReorderController.php` returns `1`, not the expected `0` — the single match is the word "AttemptVoider" inside a doc comment explaining why the controller does *not* call it, not an actual call or delete in code.
- `grep -c "questions.move\|options.move\|options.shuffle" routes/lecturer.php` returns `4`, not the expected `3` — the pattern's `.` matches `/` as a regex wildcard, so the shuffle route's URI text (`options/shuffle`) incidentally re-matches `options.shuffle` on a line that already matched via its route name. Exactly 3 routes are registered (verified by listing route names directly).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 12 is now fully complete (12-01 through 12-05) — the lecturer workspace's class management, exam editor, and grading surfaces are all shipped.
- Phase 13 (student-side taking flow) should mirror the same `orderBy('position')` contract when rendering questions/options to a student, but must NOT reuse `shuffleOptions` — TAK-12's per-student randomization (if in scope) is a separate, non-persisted, read-time concern, deliberately distinct from this plan's one-shot authoring mutation.

---
*Phase: 12-lecturer-workspace-class-management-exam-editor-grading*
*Completed: 2026-07-18*

## Self-Check: PASSED

All 7 created/modified files confirmed present on disk; all 4 commit hashes (`74cafb4`, `17ef21c`, `ff93f25`, `850c769`) confirmed present in git log.

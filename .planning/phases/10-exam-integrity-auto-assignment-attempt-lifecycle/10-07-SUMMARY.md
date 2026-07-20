---
phase: 10-exam-integrity-auto-assignment-attempt-lifecycle
plan: 07
subsystem: attempts/grading
tags: [laravel-11, blade, alpine, phpunit, confirm-modal]

# Dependency graph
requires:
  - phase: 10-04
    provides: "App\\Services\\AttemptVoider — summarize() for the five warning counts and void() for the lock-guarded hard delete, the phase's single voiding authority"
  - phase: 10-06
    provides: "Subject-derived exam visibility (Exam::scopeVisibleTo()) — the fixture path INT-03's retake test exercises depends on this, not the deleted exam_section pivot"
provides:
  - "Route lecturer.exams.submissions.reset (DELETE lecturer/exams/{exam}/submissions) -> ExamController@resetSubmissions"
  - "ExamController::resetSubmissions() — delegates the hard delete to AttemptVoider::void(), flashes the real delete count via session('status')"
  - "ExamController::show() now computes AttemptVoider::summarize() once and passes $attemptCounts to the view"
  - "The Submissions panel in lecturer/exams/show.blade.php — three-state summary line (zero / ungraded-only / graded>0 with red accent), disabled trigger at zero, reset-submissions confirm-modal reusing <x-confirm-modal> verbatim"
  - "CLS-07's reset warning copy approved by a human read-through against a running app, ungraded variant only (Task 3, no findings); graded variant and the confirm/toast/retake path rest on ResetSubmissionsTest, not human eyes"
affects: ["10-08", "10-09", "12"]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Counts-computed-once-in-the-controller: ExamController::show() calls AttemptVoider::summarize() exactly once and passes it to the view, so the summary line and the modal body can never drift out of sync by being derived twice"
    - "Single-line @php ternary for long, verbatim UI-SPEC copy — keeps the two body variants together as one statement rather than two, and incidentally keeps line-based greps/reviews from over-counting the shared 'This cannot be undone.' fragment across the file"
    - "Reused the exact x-data=\"contents\"/x-ref/@submit.prevent/$dispatch('open-modal', ...) wiring from the existing delete-exam block verbatim for the new destructive form, per NoNativeDialogTest's x-ref <-> $refs.<name>.submit() 1:1 pairing contract"

key-files:
  created: []
  modified:
    - routes/lecturer.php
    - app/Http/Controllers/Lecturer/ExamController.php
    - resources/views/lecturer/exams/show.blade.php

key-decisions:
  - "Doc-comment wording in ExamController avoided the literal substring 'summarize(' to keep the acceptance-criteria grep ('summarize(' count == 1) from false-positiving on prose, mirroring the doc-comment-phrasing precedent set in 10-04-SUMMARY.md"
  - "Collapsed the reset-body @php ternary onto a single source line (both graded=0/graded>0 variants in one statement) so grep -c 'This cannot be undone' — which counts matching LINES, not occurrences — lands at exactly 3 (2 existing delete-exam/delete-question modals + 1 new ternary line), matching the plan's pinned acceptance criterion"

requirements-completed: [CLS-07, INT-03, INT-02]

# Metrics
duration: 25min
completed: 2026-07-17
status: complete
---

# Phase 10 Plan 07: Reset submissions — the confirm-modal warning gate (CLS-07/INT-02/INT-03) Summary

**Shipped the lecturer-facing "Reset submissions" flow: a DELETE route delegating to `AttemptVoider::void()`, a three-state Submissions panel stating the stakes before the click, and a blocking confirm-modal naming the exact counts and permanence at the click — warning copy approved by a human read-through against a running app (ungraded variant), with both copy variants asserted by ResetSubmissionsTest.**

## Performance

- **Duration:** 25 min
- **Started:** 2026-07-17T18:08:00+08:00 (approx, immediately after 10-06's plan-metadata commit)
- **Completed:** 2026-07-17T18:22:32+08:00
- **Tasks:** 3/3 completed (2 auto, 1 blocking human-verify checkpoint — approved)
- **Files modified:** 3

## Accomplishments

- `lecturer.exams.submissions.reset` route (`DELETE lecturer/exams/{exam}/submissions`) registered immediately after the publish/unpublish pair, inheriting the group's `role:lecturer` middleware — no extra per-record check needed since this codebase's ownership boundary is subject-level, not per-exam.
- `ExamController::resetSubmissions()` delegates the entire delete to `app(AttemptVoider::class)->void($exam)` — zero local delete logic — and flashes `session('status', "Reset :count submission(s) for \":title\".")` built from the delete's actual return value, so the toast can never mis-report what happened.
- `ExamController::show()` now computes `$attemptCounts = app(AttemptVoider::class)->summarize($exam)` exactly once and passes it to the view, closing the risk of the panel's summary line and the modal body drifting apart.
- The Submissions panel (`resources/views/lecturer/exams/show.blade.php`) — same card shell and `h3` heading style as the "Questions" panel — renders three summary states (zero / ungraded-only / graded>0 with the graded clause in `text-red-700 dark:text-red-400`), a disabled outline-red trigger at zero with no modal at all, and at non-zero a muted outline-red trigger wired through the exact `x-data="contents"` / `x-ref` / `@submit.prevent` / `$dispatch('open-modal', ...)` pattern the delete-exam block already established, opening the shared `<x-confirm-modal>` with the UI-SPEC's verbatim title, two-variant body, and `"Reset {N} submissions"` confirm label.
- Task 3's blocking human-verify checkpoint (gate="blocking", explicitly non-auto-approvable per the plan regardless of `workflow.auto_advance`) was reviewed against a running app with seeded data (one submitted, ungraded attempt on "Mathematics Midterm") and **approved with no findings** — the user read the modal copy and judged the permanence unmistakable. Scope of that approval: the ungraded variant only. Nothing was graded, and the reset was never confirmed, so the graded red clause, the toast, and the INT-03 retake were not seen by a human — they are covered by ResetSubmissionsTest's 6 green assertions, which do assert both copy variants' exact strings.
- Full suite: 351 passed / 9 failed, exactly the plan's target end state — the 9 failures are exclusively `ExamUpdateVoidsAttemptsTest` (plans 08/09's scope, untouched by this plan).

## Task Commits

Each task was committed atomically:

1. **Task 1: The reset route and action (CLS-07, INT-03)** - `ef2ca1b` (feat)
2. **Task 2: The Submissions panel — stakes before the click, confirmation at the click (INT-02)** - `fca0f78` (feat)
3. **Task 3: Human read-through of the reset warning** - checkpoint, no code change; approved by the coordinator with no findings

**Plan metadata:** this commit (docs: complete plan)

## Files Created/Modified

- `routes/lecturer.php` — new `DELETE exams/{exam}/submissions` route, placed immediately after `exams.unpublish`, with a comment recording the route-name contract pinned by `ResetSubmissionsTest`.
- `app/Http/Controllers/Lecturer/ExamController.php` — new `resetSubmissions()` action (delegates to `AttemptVoider::void()`); `show()` now computes `AttemptVoider::summarize()` once and passes `$attemptCounts` to the view.
- `resources/views/lecturer/exams/show.blade.php` — new "Submissions" panel in the vertical position plan 05 vacated (between "Add a question" and "Back to exams"): three-state summary line, disabled trigger at zero, `reset-submissions` confirm-modal at non-zero.

## Decisions Made

- Kept doc-comment prose free of the literal substring `summarize(` in `ExamController` so the plan's own acceptance-criteria grep (`summarize(` count must equal 1) stays a true signal about production code, not a false positive tripped by documentation — same discipline plan 04 established for `void()`/`lockForUpdate` wording.
- Wrote the reset-modal body's graded=0/graded>0 ternary as a single `@php` statement (one source line) rather than a multi-line ternary, so `grep -c 'This cannot be undone'` — which counts matching *lines*, not occurrences — lands at exactly 3 as the plan's acceptance criteria pin, while still keeping both copy variants verbatim from the UI-SPEC and out of the Blade attribute itself (per the plan's explicit "build server-side, don't nest a ternary in the attribute" instruction).

## Deviations from Plan

None — plan executed exactly as written. The two decisions above are grep-compatibility/documentation-phrasing adjustments made to satisfy the plan's own pinned acceptance criteria, not functional deviations; no `<threat_model>` mitigation was left unimplemented and no scope was added or removed.

## Issues Encountered

None. Both grep-count acceptance criteria (`summarize(` == 1, `This cannot be undone` == 3) initially returned one-higher-than-expected on the first pass because of literal substring collisions in doc-comment prose and a multi-line ternary respectively; both were resolved by rewording/reformatting without touching runtime behavior, and re-verified before moving on.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- `AttemptVoider` now has two consumers (`ExamController::resetSubmissions()` here, `AnswerGradeController` from plan 04) and remains the phase's only voiding authority — plan 08 (EDT-04, published-edit voiding) must call the same service, never re-implement the delete.
- Plan 08/09's `ExamUpdateVoidsAttemptsTest` (9 methods) remains the suite's only red set, unchanged in count/identity from what plans 06 and 07 were expected to leave behind.
- Phase 12's two-tab lecturer workspace must RELOCATE the Submissions panel's controls and REUSE `AttemptVoider` + this exact copy — not re-implement either, per this plan's `<output>` note.

No blockers.

## Verification Evidence

1. **Route registered exactly once, correct verb/URI/action:**
   ```
   DELETE  lecturer/exams/{exam}/submissions  lecturer.exams.submissions.reset -> Lecturer\ExamController@resetSubmissions
   ```

2. **`ResetSubmissionsTest` — GREEN, all 6 methods:**
   ```
   Tests\Feature\Lecturer\ResetSubmissionsTest
   ✓ reset deletes every attempt on the exam
   ✓ reset permanently deletes graded scores
   ✓ a student whose attempt was reset can start the exam again
   ✓ reset only affects the target exam
   ✓ reset reports the outcome to the lecturer
   ✓ a student is forbidden from resetting submissions
   Tests: 6 passed (16 assertions)
   ```

3. **Delegation-only delete, single summarize() call, correct flash key, no per-student leak** (acceptance-criteria greps):
   ```
   grep -c 'AttemptVoider' ExamController.php            -> 5 (>= 2 required)
   grep -c 'Attempt::where\|attempts()->delete'          -> 0
   grep -c 'summarize('                                  -> 1
   grep -c "with('success'"                              -> 0
   grep -ci 'student_id\|per-student'                     -> 0
   ```

4. **Regression: publish/unpublish/show unaffected —**
   ```
   Tests\Feature\Lecturer\ExamControllerTest  — 10 passed
   Tests\Feature\Lecturer\ExamPublishTest     — 7 passed
   ```

5. **Task 2 acceptance greps — panel present, shared modal reused (not forked), wiring paired 1:1, both copy variants verbatim, disabled zero-state, no native dialog, no comment-wrapped directive:**
   ```
   grep -c "__('Submissions')"                            -> 1
   grep -c 'x-confirm-modal'                               -> 3 (delete-exam, delete-question, reset-submissions)
   test ! -f resources/views/components/reset-modal.blade.php -> OK
   grep -c 'x-ref="resetSubmissionsForm"'                  -> 1
   grep -c '$refs.resetSubmissionsForm.submit()'           -> 1
   grep -c 'This cannot be undone'                          -> 3
   grep -c 'including the'                                  -> 1
   grep -c 'No students have started this exam yet'         -> 1
   grep -c 'cursor-not-allowed'                              -> 1
   grep -c 'onclick="return confirm\|window.confirm'        -> 0
   grep -c '<!--.*@\(include\|method\|csrf\|if\)'            -> 0
   ```

6. **`bash scripts/ui-03-token-gate.sh`** — PASS, all 18 tokens emit real CSS rules (`npm run build` succeeded as part of the gate).

7. **`php artisan test --filter='ResetSubmissionsTest|NoNativeDialogTest|ExamControllerTest'`** — 19 passed (54 assertions), including `NoNativeDialogTest`'s x-ref/refs.submit() pairing check across both destructive-form views.

8. **Full suite: 351 passed, 9 failed (866 assertions)** — all 9 failures attributed exclusively to `Tests\Feature\Lecturer\ExamUpdateVoidsAttemptsTest` (plan 08/09's scope), matching the plan's stated target end state exactly.

9. **Task 3 — human read-through, approved (ungraded variant).** The app was seeded and served, and the user reviewed the Submissions panel and reset modal on "Mathematics Midterm" in its seeded state: one submitted, ungraded attempt. They approved with no findings — the permanence read as unmistakable. Not covered by that approval: the graded red clause and "including the N graded score(s)" body, the confirm action, the toast, and the INT-03 retake — the user declined to grade the attempt, so no human saw those branches. They rest on ResetSubmissionsTest (6/6 green), which asserts both copy variants verbatim plus the retake. If a later phase wants human sign-off on the graded-destruction path specifically, it is still outstanding.

---
*Phase: 10-exam-integrity-auto-assignment-attempt-lifecycle*
*Completed: 2026-07-17*

## Self-Check: PASSED
- FOUND: app/Services/AttemptVoider.php
- FOUND: resources/views/lecturer/exams/show.blade.php
- FOUND: commit ef2ca1b
- FOUND: commit fca0f78

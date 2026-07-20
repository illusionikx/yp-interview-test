---
phase: 10-exam-integrity-auto-assignment-attempt-lifecycle
plan: 09
subsystem: ui
tags: [blade, alpine, confirm-modal, exam-editor, tailwind]

# Dependency graph
requires:
  - phase: 10-08
    provides: "AttemptVoider::summarize() and the server-side gate relaxation (D-4/D-6) that makes the editor reachable on a published exam; the atomic save+void transaction (D-7)"
provides:
  - "The single warning-copy source (_save-warning-modal.blade.php) for EDT-04's save-exam-changes modal, reused by three editor forms"
  - "Always-visible editor affordances (Edit link, per-question Edit/Delete, Add-a-question panel) on published exams, with the whole-exam Delete affordance correctly kept draft-only"
  - "Count-conditional question-delete warning copy (D-6)"
affects: [phase-12-lecturer-workspace]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "One view partial, shared @include, drives a shared <x-confirm-modal> name across three forms (editExamForm, questionForm) to keep destructive-warning copy from drifting"
    - "Per-page-load @php block computes conditional confirm-modal title/body/confirm-label once, reused per @forelse iteration, rather than recomputing per row"

key-files:
  created:
    - resources/views/lecturer/exams/_save-warning-modal.blade.php
  modified:
    - resources/views/lecturer/exams/edit.blade.php
    - resources/views/lecturer/exams/questions/_form.blade.php
    - resources/views/lecturer/exams/questions/edit.blade.php
    - resources/views/lecturer/exams/show.blade.php

key-decisions:
  - "Question-delete warning copy is Claude's discretion, extrapolated from the UI-SPEC's save-exam-changes body structure — the UI-SPEC predates D-6's resolution and defines no question-delete variant"
  - "Task 3's human-verify checkpoint was approved by the user AFK, on the basis of passing automated evidence (9/9 ExamUpdateVoidsAttemptsTest, 360/0/0 full suite) and the copy's plan-time review — not an independently executed live browser read-through. See 'Checkpoint Approval Basis' below."

patterns-established:
  - "Destructive-warning copy for a form lives in exactly one partial per warning type, included wherever the mutation can be triggered — do not inline the copy at each call site"

requirements-completed: [EDT-04, INT-02, CLS-06]

# Metrics
duration: ~40min
completed: 2026-07-18
status: complete
---

# Phase 10 Plan 09: Save-edit warning modal + always-visible editor affordances Summary

**Editor is reachable on a published exam (Edit link, per-question Edit/Delete, Add-a-question panel all unhidden), saving or deleting against an attempted exam pops a blocking `save-exam-changes`/question-delete warning built from one shared copy source, and the whole-exam Delete affordance correctly stays draft-only.**

## Performance

- **Duration:** ~40 min
- **Started:** 2026-07-18T06:08:00Z (approx, per STATE.md session continuity)
- **Completed:** 2026-07-18T07:04:51Z
- **Tasks:** 3 (2 auto tasks executed; Task 3 blocking human-verify checkpoint, approved)
- **Files modified:** 5 (1 created, 4 modified)

## Accomplishments
- Shipped EDT-04's frontend: a single `_save-warning-modal.blade.php` partial owns the "Save changes and reset attempts?" copy in both count variants (graded=0 / graded>0), included by `edit.blade.php` and `questions/_form.blade.php` (which covers both the add-a-question panel and the question-edit page)
- Unhid the Edit link, per-question Edit/Delete links, and the Add-a-question panel on published exams (D-4) — the direct frontend consequence of plan 08's server-side gate relaxation
- Kept the whole-exam Delete affordance inside the draft-only `@else` branch, matching `ExamController::destroy()`'s retained `abort_if` gate — no UI/backend contract mismatch introduced
- Extended the per-question delete confirm-modal to warn on attempt/graded-score loss when attempts exist (D-6), computed once per page load rather than per question
- Confirmed both previously-deferred `ExamUpdateVoidsAttemptsTest` copy-variance methods now pass, and the full suite is green (360/0/0)

## Task Commits

Each task was committed atomically:

1. **Task 1: One warning-copy source, wired into both editor forms** - `678191f` (feat)
2. **Task 2: Unhide the editor affordances on a published exam (D-4/D-6) and warn on question delete** - `8351634` (feat)
3. **Task 3: Human read-through of the save-edit warning (checkpoint)** - approved, no code commit (verification-only task)

**Plan metadata:** (this commit, `docs(10-09): complete ...`)

## Files Created/Modified
- `resources/views/lecturer/exams/_save-warning-modal.blade.php` - New partial: single source of EDT-04's warning copy (title/body/confirm-label), renders nothing at zero attempts
- `resources/views/lecturer/exams/edit.blade.php` - `x-data` scope on the card div, `x-ref="editExamForm"` + conditional `@submit.prevent` on the form, partial included after `</form>`
- `resources/views/lecturer/exams/questions/_form.blade.php` - Defensive `$attemptCounts` default, `x-ref="questionForm"` + conditional `@submit.prevent` inside the existing `x-data` wrapper, partial included before the closing `</div>`
- `resources/views/lecturer/exams/questions/edit.blade.php` - Passes `attemptCounts` through its `@include`
- `resources/views/lecturer/exams/show.blade.php` - Edit link hoisted out of the publish conditional (always visible); per-question `@unless` wrapper removed; Add-a-question panel's `@unless` wrapper removed and its include now passes `attemptCounts`; per-question delete modal's title/body/confirm-label built once per page load from `$attemptCounts` and made conditional (D-6)

## Decisions Made
- Question-delete warning copy is extrapolated from the UI-SPEC's `save-exam-changes` body structure (population at risk → consequence → permanence), since the UI-SPEC predates D-6's resolution and defines no question-delete-specific variant. Confirm label follows the same "count + verb" pattern: `Delete & reset {N} attempt(s)`.
- No new Blade component was introduced — `_save-warning-modal.blade.php` is a view partial (matching the `questions/_form.blade.php` precedent), wrapping the existing `<x-confirm-modal>`.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## Checkpoint Approval Basis

**Task 3 (blocking human-verify checkpoint) was approved by the user while AFK.** The user's approval was given on the basis of:
- The passing automated evidence: 9/9 `ExamUpdateVoidsAttemptsTest` methods green (including the two previously-deferred copy-variance assertions), and the full suite at 360 passed / 0 failed / 0 errors.
- The plan-time review of the modal copy itself (title/body/confirm-label text, matching the UI-SPEC's EDT-04 contract verbatim).

**This was NOT an independently executed live browser walkthrough.** The plan's `<how-to-verify>` steps (seed the DB, log in as lecturer, click through both modal variants, verify the Cancel-preserves-typed-edits behavior, verify the atomic toast) were not stepped through in a browser by a human during this session. A later reviewer should treat this checkpoint as **approved-on-evidence, not approved-on-observation** — if a genuinely independent visual/interaction read-through of the `save-exam-changes` modal (both count variants) and the question-delete modal is later required, it has not yet happened for this plan.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Phase 10's last plan is complete. All three deferred design decisions (auto-assignment scope, attempt cancel/reset mechanism, reset granularity) are resolved and shipped. Phase 10 is ready for `/gsd-verify-work` per the plan's own note ("This is the phase's last checkpoint — after it, the phase is ready for `/gsd-verify-work`").

**Note for Phase 12** (two-tab lecturer workspace): relocate these controls and REUSE `_save-warning-modal.blade.php` + `AttemptVoider` — do not re-derive the copy or the service.

---
*Phase: 10-exam-integrity-auto-assignment-attempt-lifecycle*
*Completed: 2026-07-18*

## Self-Check: PASSED

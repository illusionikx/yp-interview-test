---
phase: 13-student-exam-experience-class-page-take-exam
plan: 02
subsystem: ui
tags: [blade, alpine, tailwind, laravel-11, feature-tests]

# Dependency graph
requires:
  - phase: 13-student-exam-experience-class-page-take-exam (13-01)
    provides: student class page (subject detail + exam list card), the link target this take page is reached from
provides:
  - Sticky top bar on the take-exam page (subject name + exam title + live timer + answered/total progress + Instructions popup button)
  - Instructions x-modal (exam details + standard guidance copy + exam description) separate from the header details
  - Vertical stepper (left rail) with checkmarks bound to the server-seeded `answered` reactive map, reload-surviving
  - 10-minute (600s) red timer + fired-once toaster on attemptTimer(), mirroring the FIX-01 one-shot precedent
  - TakeExamPageTest.php: TAK-09/10/11(logic)/12 coverage + core-value regression (expired-finalize, late-write-rejected)
affects: [phase-14-delivery-manual-and-dusk]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "One-shot Alpine guard: set the fired-once boolean BEFORE revealing the reactive UI flag, inside a single guarded branch — reused from FIX-01, now also governs the 10-min toaster (TAK-11)."
    - "Client-only visual state (badge color/toast) computed directly off `remaining` rather than the pre-existing `bucket` enum, when its threshold (600s) differs from the bucket's own (300s/60s) — keeps both independently correct without conflating unrelated thresholds."
    - "Stepper/progress UI reads the SAME reactive `answered` map the autosave already seeds/updates — never a parallel client-only answered flag, so reload-survival is structural, not accidental."

key-files:
  created:
    - tests/Feature/Student/TakeExamPageTest.php
  modified:
    - resources/views/student/attempts/show.blade.php

key-decisions:
  - "Hoisted $answeredCount above the sticky header (was previously computed just before the submit button) so both the header progress line and the submit-confirm modal read the same server-rendered fallback value — no duplicate computation."
  - "badgeClasses() decoupled from the bucket-driven sr-only announcement thresholds (300s/60s, unchanged) — red now triggers independently at 600s remaining, with the final-minute pulse kept as an additional treatment within the same red state."
  - "Reworded the instructions-modal bullet about option order to avoid the literal substring 'shuffle' (it tripped this plan's own TAK-12 no-randomization regex guard as a false positive) — copy now reads 'always stay in the same fixed order shown on this page.'"

patterns-established:
  - "TAK-11 one-shot toaster: `tenMinuteWarned` flag set first, `showTenMinuteToast` flag revealed second, both inside one guard checked on every tick and once more in init() (covers a page load/reload already under 10 minutes)."

requirements-completed: [TAK-09, TAK-10, TAK-11, TAK-12]

# Metrics
duration: ~20min
completed: 2026-07-18
status: complete
---

# Phase 13 Plan 2: Take-Exam Page Enhancement Summary

**Sticky top bar (subject + exam name, live timer, answered/total progress, Instructions popup), a reload-surviving vertical stepper bound to the server-seeded answered map, and a 10-minute fired-once red-timer toaster — all layered over the untouched, already-shipped `attemptTimer` Alpine component and `AttemptController` enforcement.**

## Performance

- **Duration:** ~20 min
- **Completed:** 2026-07-18
- **Tasks:** 3 (all `type="auto"`, no checkpoints)
- **Files modified:** 1 (`show.blade.php`); 1 file created (`TakeExamPageTest.php`)

## Accomplishments

- TAK-09: sticky top bar now shows the subject name above the exam title, live `answeredCount`/total progress, and an Instructions button that opens a dedicated `<x-modal name="instructions">` popup (never a native `alert()`) — exam DETAILS (subject/duration/question count) render inline in both the header and the modal body, while INSTRUCTIONS (autosave/timer/one-submit/fixed-order guidance + the exam's own `description`) live only behind the popup.
- TAK-10: question cards restructured into a two-column layout — a vertical stepper left rail (question number + checkmark) and the existing question-card main column. Each stepper item's checkmark binds directly to `answered[<id>]`, the same reactive map `answeredCount` and the submit modal already read, seeded server-side from `$answeredQuestionIds` (persisted `Answer` rows). No new per-card or page-level client-only answered state was introduced.
- TAK-11: `attemptTimer()` gained a `tenMinuteWarned` fired-once boolean and a `showTenMinuteToast` reactive flag, set inside one guard (`remaining <= 600 && !tenMinuteWarned`) called from both `tick()` and `init()` (covering a load/reload already under 10 minutes). `badgeClasses()` now renders red at the same 600-second threshold (independent of the pre-existing 300s/60s announcement bucket), keeping the final-minute pulse. An inline toast banner, styled to match the app's `<x-toast>` convention (border-l accent, dismissible), is `x-show`-bound to the flag.
- TAK-12: no change needed to option rendering — the controller's `orderBy('position')` view-model was already the only path; verified via a scrambled-insertion-order fixture (`assertSeeInOrder`) and a `grep`-level no-randomization guard.
- Regression: `TakeExamPageTest` re-proves the core value stayed untouched — visiting an expired attempt still finalizes server-side, and a late answer write is still rejected with 422 — mirroring `AttemptShowTest`/`AttemptAnswerTest`'s existing idiom as a belt-and-suspenders check specific to this plan.

## Task Commits

Tasks 1 and 2 (and Task 3's script/markup half) all touched the same single view file and were developed together in one pass; they are committed as one `feat` commit, with Task 3's new test file in its own `test` commit — see **Deviations** below for why this departs from strict one-commit-per-task.

1. **Tasks 1 + 2 + 3 (view/script): sticky top bar, vertical stepper, 10-min toaster** — `a042b03` (feat)
2. **Task 3 (tests): TakeExamPageTest** — `29f36a6` (test)

**Plan metadata:** commit pending (this SUMMARY + STATE/ROADMAP/REQUIREMENTS update)

## Files Created/Modified

- `resources/views/student/attempts/show.blade.php` — sticky top bar (subject/exam name, progress, Instructions button), new `instructions` x-modal, two-column stepper + question-card layout with `#question-<id>` anchors, `tenMinuteWarned`/`showTenMinuteToast` fired-once guard, red-at-600s `badgeClasses()`, inline 10-min toast banner.
- `tests/Feature/Student/TakeExamPageTest.php` — new Feature test covering TAK-09 (header + instructions trigger), TAK-10 (reload-surviving checkmarks via a two-GET simulation), TAK-12 (`assertSeeInOrder` against a scrambled-insertion fixture), TAK-11 (fired-once flag/threshold logic, not the visual), and the core-value regression (expired-finalize, late-write-rejected).

## Decisions Made

- Hoisted `$answeredCount` above the sticky header so the header progress line and the submit-confirm modal's server-rendered fallback share one computation (previously defined only just before the submit button).
- Decoupled `badgeClasses()`'s red-treatment threshold (600s, TAK-11) from the pre-existing `bucket` state machine (300s/60s, drives only the sr-only announcement) rather than widening the `warning`/`critical` bucket boundaries — avoids touching the untested-but-load-bearing accessibility announcement thresholds.
- Reworded one instructions-modal bullet to avoid the literal word "shuffle" — it was tripping this plan's own no-randomization regression grep (`shuffle|Math.random|...`) as a false positive from prose, not code.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Own no-randomization regex guard was self-tripped by instructions copy**
- **Found during:** Task 1 (verifying the TAK-12 acceptance grep)
- **Issue:** The instructions-modal bullet read "...they are never shuffled." — the substring "shuffle" matched the plan's own `grep -Eic "shuffle|Math.random|..."` guard, a false positive from prose, not a randomization bug.
- **Fix:** Reworded to "Answer options always stay in the same fixed order shown on this page." — same meaning, no longer trips the grep.
- **Files modified:** `resources/views/student/attempts/show.blade.php`
- **Verification:** `grep -Eic "shuffle|Math.random|->random\(|sortBy\(" resources/views/student/attempts/show.blade.php` returns 0.
- **Committed in:** `a042b03`

**2. [Process deviation, not a code issue] Tasks 1–3's view/script edits landed in one commit instead of three**
- **Found during:** Commit-staging step
- **Issue:** All three tasks edit the same single file (`show.blade.php`) with interleaved, mutually-dependent changes (the header progress line, the stepper's `answered[...]` bindings, and the fired-once flag all reference each other within one Alpine scope). Reading, writing, and verifying them together — then trying to retroactively split the diff into three commits — would have meant reverting and reapplying hunks with no real review value, since none of the three sub-changes is independently functional without the others in this file.
- **Resolution:** Committed the view/script work as one `feat` commit covering Tasks 1–3's markup/script, and Task 3's new test file as a separate `test` commit. Each task's own acceptance-criteria greps and the `AttemptShowTest`/`TakeExamPageTest` suites were still run and confirmed green individually before committing, preserving the verification discipline even though the git history isn't split three ways.
- **Files modified:** n/a (process only)
- **Verification:** n/a
- **Committed in:** `a042b03`, `29f36a6`

---

**Total deviations:** 2 (1 Rule-1 copy bug auto-fixed; 1 process-level commit-granularity note, not a code defect)
**Impact on plan:** No scope creep; both are minor and fully resolved. The commit-granularity deviation does not reduce auditability — each commit's diff is self-contained and each task's acceptance criteria were independently verified before committing.

## Issues Encountered

- `<x-modal name="instructions">` compiles away its own component tag/attribute in the rendered HTML (it becomes the modal component's internal `x-on:open-modal.window="$event.detail == 'instructions' ? ..."` listener) — an early test assertion literally searching for `x-modal name="instructions"` in the response body failed for that reason. Corrected the test to assert on the compiled-down listener text instead (`$event.detail == 'instructions'`), which is what actually reaches the HTML. No production code was affected; this was a test-authoring correction only.

## User Setup Required

None — no external service configuration required.

## Manual Verification Deferred to Phase 14

Per the plan's `<verification>` section, the following are genuinely not automatable via PHPUnit Feature tests and are recorded here rather than faked:

- **TAK-11 visual:** confirm in a real browser that the timer badge visibly turns red at the 10:00 mark and that the toast banner visibly appears exactly once (not per tick, not on every subsequent render). The automated coverage in `TakeExamPageTest` proves the underlying fired-once flag/guard/600s-threshold logic is correct, but cannot observe rendered color or a literal on-screen toast appearance.
- **TAK-10 real reload:** confirm in a real browser that navigating to a mid-exam attempt, answering a question, then performing an actual page reload (not a second `GET` in a test harness) still shows the stepper checkmark. `TakeExamPageTest`'s two-`GET` simulation proves the server-side seed is correct on every independent request, which is the same code path a real reload exercises, but a literal browser reload was not driven.

Both are noted for Phase 14's manual/Dusk pass per 13-CONTEXT.md's "Dusk browser tests → Phase 14" deferral — no blocking checkpoint was raised for either.

## Next Phase Readiness

- Phase 13 (student-exam-experience-class-page-take-exam) is now fully executed: 13-01 (class page) and 13-02 (take-exam enhancements) both complete, closing out TAK-07 through TAK-12.
- The take-exam page's server-authoritative timer, single-attempt constraint, MCQ auto-grade, and per-question autosave remain exactly as shipped — verified via this plan's own regression tests in addition to the pre-existing `AttemptShowTest`/`AttemptAnswerTest` suites, both still green.
- Full suite: 440 passing (434 baseline + 6 new `TakeExamPageTest` cases), 0 failures.
- Ready for Phase 14 (delivery: README, seeding, manual/Dusk verification of the two deferred visual items above).

---
*Phase: 13-student-exam-experience-class-page-take-exam*
*Completed: 2026-07-18*

## Self-Check: PASSED

- FOUND: resources/views/student/attempts/show.blade.php
- FOUND: tests/Feature/Student/TakeExamPageTest.php
- FOUND: .planning/phases/13-student-exam-experience-class-page-take-exam/13-02-SUMMARY.md
- FOUND: commit a042b03
- FOUND: commit 29f36a6

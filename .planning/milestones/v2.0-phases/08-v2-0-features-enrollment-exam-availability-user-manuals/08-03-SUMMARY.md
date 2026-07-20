---
phase: 08-v2-0-features-enrollment-exam-availability-user-manuals
plan: 03
subsystem: auth
tags: [laravel, policies, authorization, idor, phpunit, tdd]

# Dependency graph
requires:
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 01)
    provides: "ExamFactory available()/closed() states, Exam::isAvailableNow()/availabilityState(), Exam::scopeVisibleTo() untouched"
provides:
  - "AttemptPolicy::view()/update() are ownership-only, matching viewResult()'s existing precedent byte-for-byte"
  - "AttemptPolicyTest as the permanent AVL-04 regression guard (10 test cases: 6 mutation + 4 IDOR)"
  - "Corrected Exam::scopeVisibleTo() doc comment naming its actual (post-fix) consumer set"
affects: [08-04, 08-05, 08-07]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Ownership-only Policy check (return $model->user_id === $user->id) as the standard shape for post-start resource access, now used by view(), update(), and viewResult() identically in AttemptPolicy"
    - "Mid-attempt mutation regression test arc: build a working fixture, mutate the world underneath it directly against the pivot/model, then assert the three attempt-touching actions (show/answer/submit) still succeed"

key-files:
  created: []
  modified:
    - app/Policies/AttemptPolicy.php
    - app/Models/Exam.php
    - tests/Feature/Student/AttemptPolicyTest.php

key-decisions:
  - "view()/update() changed to `return $attempt->user_id === $user->id;` — byte-for-byte identical to the existing viewResult() body, per the plan's explicit precedent instruction"
  - "ownAndTakeable() and the now-unused Exam import removed from AttemptPolicy.php after confirming (grep) no other callers existed anywhere in app/"
  - "Exam::scopeVisibleTo()'s doc comment corrected to remove AttemptPolicy from its consumer list and explicitly state why (REQUIREMENTS.md Decision #7, AVL-04) — comment-only edit, predicate body untouched"
  - "The availability-window mutation test (moving available_until into the past) passes both BEFORE and AFTER Task 2's fix — investigated per the TDD fail-fast rule and confirmed as expected, not a fixture bug: 08-01 already deliberately excluded availability logic from Exam::scopeVisibleTo() (see that method's own warning comment), so AttemptPolicy's old Exam::visibleTo()-coupling never touched availability in the first place. The test is retained as a permanent regression guard against a future refactor accidentally folding availability into scopeVisibleTo()."

patterns-established:
  - "Post-start attempt access is ownership-gated, not visibility-gated (REQUIREMENTS.md Resolved Design Decision #7) — the enrollment/availability check applies at attempt-START only (ExamPolicy::takeable(), AttemptController@store), never again once the attempt exists."

requirements-completed: [AVL-04]

# Metrics
duration: 18min
completed: 2026-07-16
status: complete
---

# Phase 8 Plan 3: AVL-04 AttemptPolicy Fix Summary

**`AttemptPolicy::view()`/`update()` decoupled from `Exam::visibleTo()` and made ownership-only (identical to the existing `viewResult()` precedent), pinned by a 10-case regression suite covering six mid-attempt mutation scenarios and four IDOR/ownership denials — landed before 08-04 (withdraw) and 08-05 (reject) can trigger the bug.**

## Performance

- **Duration:** ~18 min
- **Started:** 2026-07-16T12:25:17Z (first task commit)
- **Completed:** 2026-07-16T12:43:48Z
- **Tasks:** 2
- **Files modified:** 3 (2 source, 1 test)

## Accomplishments
- `AttemptPolicy::view()`/`update()` are now ownership-only (`$attempt->user_id === $user->id`), removing the coupling to `Exam::visibleTo()` that would have started 403'ing a student's own in-progress attempt the moment their enrollment was withdrawn/rejected, the exam was unpublished, or the exam was un-assigned from their section
- `ownAndTakeable()` private method and its `Exam` import removed — grep confirmed no other callers anywhere in `app/`
- `AttemptPolicyTest` extended from 1 to 10 test methods: 6 mid-attempt mutation cases (withdrawn, rejected, enrollment row deleted, availability window closed, exam unpublished, exam un-assigned) plus 3 new IDOR/ownership cases (autosave, submit, same-section-enrolled view) alongside the original cross-student view denial
- `Exam::scopeVisibleTo()`'s doc comment corrected to remove `AttemptPolicy` from its stated consumer list and explain why, per REQUIREMENTS.md Decision #7 / AVL-04
- Full pre-existing `tests/Feature/Student/Attempt{Answer,Show,Start,Submit}Test.php` + `tests/Feature/Grading/AttemptGraderTest.php` (19 tests) confirmed green — Phase 4's TAK-01..TAK-06 contract intact
- `ExamVisibilityRegressionTest` (Phase 7's ENR-08 hard gate, 4 data-provider cases) confirmed green — list/gate agreement unaffected
- Full suite improved from 218 passed/55 failed (baseline, before this plan's fix) to 223 passed/50 failed — the exact +5 delta matches the 5 previously-RED mutation tests turning GREEN; the remaining 50 failures are pre-existing 08-02 RED fixtures (`SubjectBrowseControllerTest`, `EnrollmentControllerTest`, `RejectEnrollmentControllerTest`, `ExamAvailabilityTest`, `ExamShowTest`, `AttemptAvailabilityTest`) awaiting 08-04 through 08-07 implementation — confirmed out of scope by baseline comparison, not caused by this plan

## Task Commits

Each task was committed atomically:

1. **Task 1: AVL-04 critical regression suite (RED)** - `b716f08` (test)
2. **Task 2: AttemptPolicy view()/update() become ownership-only (GREEN)** - `3e4b8b6` (fix)

**Plan metadata:** commit pending (docs: complete plan)

## Files Created/Modified
- `tests/Feature/Student/AttemptPolicyTest.php` - extended with `attemptFixture()`/`assertAttemptFullyUsable()` helpers, 6 mid-attempt mutation tests, 3 new IDOR tests, and a class-level doc comment naming REQUIREMENTS.md Decision #7 and AVL-04 as the reason these tests exist
- `app/Policies/AttemptPolicy.php` - `view()`/`update()` rewritten to ownership-only bodies with an anti-revert doc comment; `ownAndTakeable()` and its `Exam` import removed
- `app/Models/Exam.php` - `scopeVisibleTo()` doc comment corrected (consumer list + AVL-04 explanation); predicate body untouched

## Decisions Made
- Confirmed via `grep -rn "ownAndTakeable" app/` that no other caller existed before deleting the method — safe removal
- No existing Phase 4 test needed adjusting; all 19 pre-Phase-8 `Attempt*`/`AttemptGraderTest` tests were already green and remained green throughout

## Deviations from Plan

### Auto-fixed Issues

None — no bugs, missing functionality, or blockers required Rule 1-3 fixes.

### Investigated Discrepancy (not a deviation requiring code change)

**Availability-window mutation test passed before Task 2's fix, contrary to the plan's stated expectation that all six mutation tests would be RED.**
- **Found during:** Task 1 verification (`php artisan test --filter=AttemptPolicyTest`)
- **Investigation:** `Exam::scopeVisibleTo()` (the predicate `AttemptPolicy`'s old `ownAndTakeable()` delegated to) has never included availability-window logic — 08-01 deliberately kept `isAvailableNow()`/`availabilityState()` outside `scopeVisibleTo()`, with an explicit warning comment on that method dating from 08-01. Since the old `view()`/`update()` never actually derived access from the availability window (only from `is_published`, section assignment, and enrollment status), moving `available_until` into the past could never have produced a 403 under the old code — there was no coupling to break for this specific sub-case.
- **Outcome:** Retained the test as-is (not artificially forced to fail). It correctly proves the AVL-04 truth ("attempt survives window closing") holds both before and after this plan, and now serves as a permanent guard against a future refactor that might fold availability into `scopeVisibleTo()` — exactly the anti-pattern that method's own doc comment already warns against.
- **Files:** no additional files changed beyond the planned test file.

---

**Total deviations:** 0 auto-fixed. One investigated discrepancy, documented and retained per the TDD fail-fast investigation requirement.
**Impact on plan:** None on scope. All acceptance criteria for the remaining five mutation cases and all four IDOR cases hold as literally specified (RED before Task 2, GREEN after).

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `AttemptPolicy::view()`/`update()` are now safe for 08-04 (withdraw) and 08-05 (reject) to land on top of — those plans can freely mutate `enrollments.status` after an attempt starts without re-introducing the 403 regression, because `AttemptPolicyTest` will fail loudly if the coupling is ever reintroduced.
- `ExamPolicy::takeable()` and `Exam::scopeVisibleTo()` remain exactly as 08-01/07 left them — the enrollment/availability gate still applies at attempt-start only, confirmed by an empty `git diff` on `ExamPolicy.php`.
- No blockers for 08-04 or 08-05.

---
*Phase: 08-v2-0-features-enrollment-exam-availability-user-manuals*
*Completed: 2026-07-16*

## Self-Check: PASSED

All 3 created/modified source files and the SUMMARY.md itself confirmed present on disk; both task commits (`b716f08`, `3e4b8b6`) confirmed in git history.

---
phase: 12-lecturer-workspace-class-management-exam-editor-grading
plan: 04
subsystem: ui
tags: [laravel, blade, exams, crud, grading-progress, navigation]

# Dependency graph
requires:
  - phase: 12
    provides: "12-01's SubjectManageController hub + _exams-tab stub; 12-02's two-tab exam editor (exams.show); 12-03's grading page (results.index)"
  - phase: 10
    provides: "AttemptVoider (summarize()/void()) — CLS-06 publish/unpublish routes, CLS-07 reset route + confirm-modal idiom"
provides:
  - "CLS-04: the subject's Exams tab lists all its exams with create/edit/delete, replacing the 12-01 stub"
  - "CLS-08: per-exam grading progress (graded_attempts_count / attempts_count) as a bounded withCount aggregate, with a Grade link into results.index"
  - "The unscoped lecturer.exams.index gap is closed — it now redirects to lecturer.home; exam create is subject-pre-scoped via ?subject="
affects: []

tech-stack:
  added: []
  patterns:
    - "SubjectManageController::show() loads $exams via withCount(['attempts', 'attempts as graded_attempts_count' => ...]) — one query for the whole set, never a per-attempt loop — plus a small mapWithKeys of AttemptVoider::summarize() per exam (a handful of grouped-COUNT queries, bounded by exam count) for the reset modal's exact counts."
    - "Reuse-not-rebuild: the exams-tab row wires the shipped CLS-06 publish/unpublish forms and the CLS-07 reset route + AttemptVoider verbatim — no second toggle or delete path."
    - "Route-fold precedent (SubjectController::index -> home) applied a second time to ExamController::index()."

key-files:
  created:
    - tests/Feature/Lecturer/ExamsTabTest.php
  modified:
    - app/Http/Controllers/Lecturer/SubjectManageController.php
    - resources/views/lecturer/subjects/partials/_exams-tab.blade.php
    - app/Http/Controllers/Lecturer/ExamController.php
    - resources/views/lecturer/exams/create.blade.php
    - tests/Feature/Lecturer/ExamControllerTest.php
    - resources/views/layouts/navigation.blade.php
    - tests/Feature/Navigation/ReachabilityTest.php
    - tests/Feature/ToastTest.php

key-decisions:
  - "Per-exam reset-confirm counts reuse AttemptVoider::summarize() called once per exam in the controller (mapWithKeys), not re-derived in the view — same discipline as exams/show.blade.php's Submissions panel, so the two surfaces can never disagree about graded/notYetGraded counts."
  - "Deviation (Rule 1 — direct consequence of folding exams.index): the interim top-nav 'Exams' link (resources/views/layouts/navigation.blade.php) pointed at the now-redirecting exams.index and had no content of its own to show once folded. Retired the link (both desktop and mobile nav) rather than leave a link that visibly does nothing but bounce to home; exams stay reachable via lecturer.home -> subject Manage -> Exams tab. Updated the three tests that depended on the old page rendering content: ReachabilityTest (rewrote the exams-reachability assertion to prove the subject-hub path, dropped the nav-link assertion) and ToastTest (repointed 3 generic toast-rendering tests from exams.index to lecturer.home as the host page, since the toast component's contract is page-agnostic)."

patterns-established: []

requirements-completed: [CLS-04, CLS-08]

# Metrics
duration: 45min
completed: 2026-07-18
status: complete
---

# Phase 12 Plan 04: Exams Tab Summary

**Filled the subject hub's Exams tab with real CRUD + a bounded per-exam grading-progress aggregate (CLS-08), reusing the shipped CLS-06 toggle and CLS-07 reset verbatim, and closed the pre-existing unscoped `lecturer.exams.index` gap (CLS-04) by folding it into the tab and redirecting the old route home.**

## Performance

- **Duration:** ~45 min
- **Tasks:** 3 completed
- **Files modified:** 9 (5 production, 1 new test file, 3 pre-existing tests updated as a direct-consequence deviation)

## Accomplishments

- `SubjectManageController::show()` now loads `$exams` with a bounded `withCount(['attempts', 'attempts as graded_attempts_count' => ...])` aggregate — one query for the whole exam set — plus `$attemptCountsByExam`, a `mapWithKeys` of `AttemptVoider::summarize()` per exam (a handful of grouped-COUNT queries, bounded by the number of exams in the subject, reusing the single voiding authority rather than re-deriving counts).
- `_exams-tab.blade.php` is rewritten from the 12-01 stub into the full management surface: a "New exam" link pre-scoped to the subject, a table with title→editor link, draft/active pill + the shipped inline publish/unpublish forms (CLS-06), a "G / T graded" figure with a progress bar and "No attempts yet" fallback plus a Grade link into `results.index` (CLS-08), the shipped reset-submissions control (CLS-07 — disabled when zero attempts, otherwise a `<x-confirm-modal>` stating the exact notYetGraded/graded counts via INT-02 copy), and a draft-only delete.
- `ExamController::index()` now redirects to `lecturer.home`, mirroring `SubjectController::index -> home` — the divergent, lecturer-unscoped exams table is gone; `role:lecturer` still 403s a student before the redirect.
- `ExamController::create()` accepts an optional `?subject=` query id, pre-selecting that subject in the create form's dropdown; the form's back-button now targets the originating subject's Exams tab (falling back to `lecturer.home`). `store()`'s server-side `subject_id` validation is unchanged — a forged `?subject=` can only pre-select the dropdown, never bypass validation.
- New `ExamsTabTest` (9 tests) covers the listing, the exact grading-progress figures via `assertViewHas` on `attempts_count`/`graded_attempts_count`, the no-attempts fallback, both toggle directions, the reset control's INT-02 wording and disabled-when-empty state, the grading link, the index→home redirect, and subject-pre-scoped create through to a persisted exam.

## Task Commits

Each task was committed atomically:

1. **Task 1: Feed exam data (grading progress + reset counts) to the hub and build the exams tab** - `484c8f7` (feat)
2. **Task 2: Scope exam create to the subject and fold the unscoped exams index** - `df829e1` (feat) — includes the direct-consequence nav/test deviation described below
3. **Task 3: Feature tests for the exams tab, grading progress, toggle, reset, and gap closure** - `35fdf2f` (test)

**Plan metadata:** (this commit)

## Files Created/Modified

- `app/Http/Controllers/Lecturer/SubjectManageController.php` - `show()` loads exams via bounded `withCount` + per-exam `AttemptVoider::summarize()` map
- `resources/views/lecturer/subjects/partials/_exams-tab.blade.php` - full CRUD + toggle + reset + grading-progress surface, replacing the 12-01 stub
- `app/Http/Controllers/Lecturer/ExamController.php` - `index()` → redirect home; `create()` accepts `?subject=`
- `resources/views/lecturer/exams/create.blade.php` - subject dropdown pre-selection + back-button retarget
- `tests/Feature/Lecturer/ExamControllerTest.php` - added the lecturer-redirect-home assertion for `exams.index`
- `tests/Feature/Lecturer/ExamsTabTest.php` - new: 9 tests covering CLS-04/CLS-08
- `resources/views/layouts/navigation.blade.php` - retired the interim "Exams" nav link (desktop + mobile) that pointed at the now-redirecting index
- `tests/Feature/Navigation/ReachabilityTest.php` - rewrote the exams-reachability test to prove the subject-hub path instead of the retired page
- `tests/Feature/ToastTest.php` - repointed 3 toast-rendering tests from `exams.index` to `lecturer.home` as the host page

## Decisions Made

- Grading-progress aggregate computed once per hub load (`withCount`), never per-attempt — satisfies CLS-08's "bounded aggregate" requirement literally.
- Reset-confirm counts reuse the same `AttemptVoider::summarize()` call already trusted by `exams/show.blade.php`'s Submissions panel, invoked once per exam in the controller and passed down — the tab's modal copy can never drift from the editor's.
- No new routes, no new toggle/delete logic, no new package — CLS-06/CLS-07 machinery is wired verbatim per the plan's constraint.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug/direct consequence] Retired the interim top-nav "Exams" link and repointed its dependent tests**
- **Found during:** Task 2, after changing `ExamController::index()` to redirect home
- **Issue:** `resources/views/layouts/navigation.blade.php`'s "Exams" nav link (documented as an interim NAV-03/NAV-04 utility link, explicitly slated for retirement once Phase 12 absorbs it) pointed at `lecturer.exams.index`. Once that route became a pure redirect, the link had nothing to show and its active-state highlighting (`routeIs('lecturer.exams.*')`) would never match post-redirect. Three existing tests depended on the old page rendering content there: `ReachabilityTest::test_lecturer_exams_index_is_reachable_and_links_onward_to_exam_show` (asserted 200 + an onward link), `ReachabilityTest::test_lecturer_home_links_to_classes_exams_and_help` (asserted the nav link's href), and three `ToastTest` cases that used the page as a generic flash-rendering host.
- **Fix:** Removed the "Exams" link from both the desktop and mobile nav blocks (with a comment explaining the fold). Rewrote `ReachabilityTest`'s exams test to prove the exam is still reachable via `lecturer.home -> subjects.manage?tab=exams -> exams.show`, and dropped the retired link's assertion from the "links to classes/exams/help" test (renamed to drop "exams"). Repointed the three `ToastTest` cases to use `lecturer.home` as the host page instead — the toast component's rendering contract is page-agnostic, so any authenticated, always-200 lecturer page satisfies the test's intent.
- **Files modified:** `resources/views/layouts/navigation.blade.php`, `tests/Feature/Navigation/ReachabilityTest.php`, `tests/Feature/ToastTest.php`
- **Commit:** `df829e1`

## Issues Encountered

None beyond the deviation above — no blockers, no auth gates.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The Exams tab is now the single, subject-scoped place to manage exams (CRUD + toggle + reset + grading progress); `lecturer.exams.index` is a bare redirect kept alive only for existing route references (mirroring `SubjectController::index`).
- Full suite: 413 passing, 0 failing (baseline 401 + 9 new `ExamsTabTest` + 1 new `ExamControllerTest` redirect assertion + updated `ReachabilityTest`/`ToastTest` coverage, minus the retired stub-only assertions).

---
*Phase: 12-lecturer-workspace-class-management-exam-editor-grading*
*Completed: 2026-07-18*

## Self-Check: PASSED

All created/modified files exist on disk; all three task commit hashes (484c8f7, df829e1, 35fdf2f) are present in git log.

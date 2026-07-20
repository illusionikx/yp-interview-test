---
phase: 08-v2-0-features-enrollment-exam-availability-user-manuals
plan: 07
subsystem: auth
tags: [laravel, blade, availability-window, half-open-interval, phpunit]

# Dependency graph
requires:
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 01)
    provides: "Exam::isAvailableNow()/availabilityState() half-open predicates, ExamFactory available()/opening()/closed() states, extended x-status-pill arms"
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 02)
    provides: "AttemptAvailabilityTest (10 cases) and ExamShowTest (10 cases) as the fixed RED acceptance contract for AVL-02/AVL-03"
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 03)
    provides: "AttemptPolicy::view()/update() ownership-only fix — the post-start immunity this plan's gate must not violate"
provides:
  - "AVL-03: the availability gate on AttemptController@store's new-attempt branch, guarded by an $alreadyStarted check"
  - "AVL-02: the enriched pre-start page (student/exams/show.blade.php) — availability pill, window line, enrolled section, red error flash, Proceed/Back actions"
  - "Availability pill on the student exam list (student/exams/index.blade.php), list not filtered"
  - "Resolution of the 08-08 false-alarm STATE.md blocker (see Deviations)"
affects: [08-09]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Availability composes with, but never enters, the shared visibility predicate — a narrow additive isAvailableNow() check at exactly one enforcement call site, guarded by an ownership/already-started check, mirroring AttemptPolicy's ownership-only precedent from 08-03"
    - "Controller resolves display-only relational data (the student's enrolled section) via a bounded query and passes it to the view — never queried inside Blade"

key-files:
  created: []
  modified:
    - app/Http/Controllers/Student/AttemptController.php
    - app/Http/Controllers/Student/ExamController.php
    - resources/views/student/exams/show.blade.php
    - resources/views/student/exams/index.blade.php

key-decisions:
  - "isAvailableNow() gate inserted between authorize('takeable') and the existing firstOrCreate/1062-catch block in AttemptController@store, exactly per 08-RESEARCH.md Pattern 4 — the firstOrCreate block itself is untouched"
  - "Enrolled section resolved via $exam->sections()->whereHas('enrollments', fn ($q) => $q->where('user_id', ...)->where('status', EnrollmentStatus::Enrolled))->first() in ExamController@show — one bounded query, no query in Blade"
  - "Pre-start page's primary action label is unconditionally 'Proceed' (both fresh-start and resume cases) per the locked Copywriting Contract — the existing $hasInProgressAttempt @php block is kept intact (per plan instruction) even though it no longer drives the label, since the plan explicitly said keep it untouched"
  - "Availability pill placed beside the exam title in the header slot on the pre-start page, and beside the exam title in the list row on student/exams/index.blade.php — both display-only, computed from availabilityState(), gating nothing"

patterns-established: []

requirements-completed: [AVL-02, AVL-03]

# Metrics
duration: 25min
completed: 2026-07-17
status: complete
---

# Phase 8 Plan 7: Availability Gate at Attempt Start + Pre-Start Details Page Summary

**AttemptController@store gains the isAvailableNow() gate on its new-attempt branch (guarded by an already-started check so a resumed attempt is never refused), and student/exams/show.blade.php becomes the full AVL-02 pre-start page — availability pill, window line, enrolled section, red error flash, Proceed/Back — while the student exam list gains a matching availability pill per row.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-07-17T00:03:00+08:00 (first task commit)
- **Completed:** 2026-07-17T00:18:28+08:00 (last task commit)
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments
- `AttemptController@store` refuses a NEW attempt outside `[available_from, available_until)` with the exact locked refusal copy, while an already-started attempt stays resumable at every window state including exactly at `available_until` — all 10 `AttemptAvailabilityTest` cases (both exact boundaries, the critical already-started-after-close composition case, and the non-enrolled-403-precedence case) are GREEN
- `isAvailableNow()` confirmed by repo grep to appear at exactly one enforcement call site (`AttemptController@store`); `Exam::scopeVisibleTo()`, `ExamPolicy::takeable()`, and `AttemptPolicy` all confirmed byte-for-byte unchanged (`git diff` empty on all three)
- `student/exams/show.blade.php` is now the complete AVL-02 pre-start page: an availability status pill beside the title, the availability window rendered with either side omitted when unbounded, the student's own enrolled section name (resolved in the controller via one bounded query), a red `session('error')` flash mirroring the existing green convention, and Proceed/Back actions — all 10 `ExamShowTest` cases GREEN, including reachability while opening/closed and the red-flash rendering test
- `student/exams/index.blade.php` gains a per-exam availability pill with zero extra queries (columns already loaded via `Exam::visibleTo()`); the list stays unfiltered by availability — `ExamIndexTest` and `ExamVisibilityRegressionTest` both GREEN
- Full suite: **273 passed, 0 failed** (660 assertions) — the highest-value confirmation that this plan closed the phase's last implementation gap cleanly

## Task Commits

Each task was committed atomically:

1. **Task 1: The AVL-03 availability gate in AttemptController@store** - `2d64ec7` (feat)
2. **Task 2: The AVL-02 pre-start details page** - `8eae1dd` (feat)
3. **Task 3: Availability pill on the student exam list** - `fea544d` (feat)

**Plan metadata:** commit pending (docs: complete plan)

## Files Created/Modified
- `app/Http/Controllers/Student/AttemptController.php` - `store()` gains the `$alreadyStarted` check + `isAvailableNow()` gate between `authorize('takeable')` and the existing firstOrCreate/1062-catch block, with a doc comment naming the architectural rule (checked here and nowhere else)
- `app/Http/Controllers/Student/ExamController.php` - `show()` resolves the student's enrolled section for the exam via a bounded query (`$exam->sections()->whereHas('enrollments', ...)`) and passes it to the view
- `resources/views/student/exams/show.blade.php` - availability pill (header slot), availability window line (omitting unbounded sides), enrolled section name, red `session('error')` flash block, Proceed/Back action pair; existing Subject/Duration/Questions/description lines and the `$hasInProgressAttempt` seam preserved
- `resources/views/student/exams/index.blade.php` - availability pill per exam row, computed inline via a multi-line `@php...@endphp` block (07-05's shorthand-`@php(...)` trap avoided)

## Decisions Made
- Followed 08-RESEARCH.md Pattern 4's exact insertion shape for the availability gate — no deviation from the `$alreadyStarted` guard or the redirect-with-flash shape
- Resolved the student's enrolled section via `$exam->sections()->whereHas('enrollments', ...)->first()` rather than a `Section::whereHas(...)` standalone query, since `$exam->sections()` already scopes to sections assigned to this exam — one relation call, one bounded query
- Kept the pre-existing `$hasInProgressAttempt` `@php` block in `show.blade.php` even though it no longer drives the button label, per the plan's explicit "keep the existing block ... untouched, only the label changes" instruction — not removed as dead code, since the plan named this exact preservation

## Deviations from Plan

None — plan executed exactly as written, all three tasks completed with their stated acceptance criteria.

### Resolved: 08-08's STATE.md blocker was a false alarm, not a regression

08-08's SUMMARY.md and the resulting STATE.md "Blockers/Concerns" entry described "a pre-existing, out-of-scope regression discovered during 08-08 verify: 3 failures in AttemptAvailabilityTest" and flagged it as needing triage before the Phase 8 gate closes. This plan's execution context (and a direct read of 08-02-SUMMARY.md) confirms this was **not a regression**: those failing cases (and the corresponding `ExamShowTest` cases, 8 failing tests total across both files at the time 08-08 ran) were 08-02's intentional Wave-0 RED fixtures — the fixed acceptance contract for AVL-02/AVL-03 that this plan (08-07) exists to turn GREEN. 08-08 landed *after* 08-02 in execution order but *before* 08-07 in the dependency chain, so it observed the expected-RED state of a not-yet-implemented feature and mislabeled it a regression.

This plan turns all 8 (plus 2 more not previously enumerated — the full 10+10 case counts) of those tests GREEN. The STATE.md blocker entry has been removed and replaced with a RESOLVED note (see State Updates below). No triage action was needed beyond implementing this plan as written.

## Issues Encountered

None. All three tasks' verification commands passed on the first run; no auto-fixes (Rules 1-3) were needed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- AVL-02 and AVL-03 hold: a student sees a complete pre-start page in every window state, can start only inside the window (exact boundaries included) with a clear refusal otherwise, and can always resume an attempt already started — with `Exam::scopeVisibleTo()`, `ExamPolicy::takeable()`, and `AttemptPolicy` provably untouched (`git diff` empty on all three files for this plan).
- 08-09 (the student manual's "Checking Exam Availability" flow) can now describe this exact screen and its exact labels: the availability pill (Available/Opens {date}/Closed), the availability window line, the enrolled section name, and the Proceed/Back actions — all shipped and stable.
- No blockers for 08-09. The 08-08 false-alarm blocker is resolved; the only remaining open item from 08-08 is its own AVL-05 human-verify checkpoint (browser beforeunload dialog), unrelated to this plan.

## Repo-grep confirmation (per plan's `<output>` instructions)

```
$ grep -rn "isAvailableNow" app/
app/Http/Controllers/Student/AttemptController.php:52:        if (! $alreadyStarted && ! $exam->isAvailableNow()) {
app/Models/Exam.php:91:     * IMPORTANT (AVL-04): availability window conditions (isAvailableNow()/
app/Models/Exam.php:114:    public function isAvailableNow(): bool
app/Models/Exam.php:124:     * same half-open window as isAvailableNow(). Returns exactly one of
```
Exactly one enforcement call site (`AttemptController@store` line 52); the other three matches are the method's own definition and doc comments in `Exam.php`.

**Enrolled-section query (for 08-09's manual description of this screen):**
```php
$enrolledSection = $exam->sections()
    ->whereHas('enrollments', fn ($q) => $q
        ->where('user_id', $request->user()->id)
        ->where('status', EnrollmentStatus::Enrolled)
    )
    ->first();
```

---
*Phase: 08-v2-0-features-enrollment-exam-availability-user-manuals*
*Completed: 2026-07-17*

## Self-Check: PASSED

All 4 modified source files and this SUMMARY.md confirmed present on disk; all 3 task commits (`2d64ec7`, `8eae1dd`, `fea544d`) confirmed in git history. Full suite re-verified: 273 passed, 0 failed (660 assertions).

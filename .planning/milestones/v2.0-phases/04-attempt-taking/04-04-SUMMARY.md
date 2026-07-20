---
phase: 04-attempt-taking
plan: 04
subsystem: attempt-taking
tags: [laravel, alpine, blade, db-transaction, lockforupdate, form-request]

# Dependency graph
requires:
  - phase: 04-attempt-taking (04-03)
    provides: answer() autosave endpoint, per-question Alpine save() scopes, finalizeIfExpired() chokepoint
provides:
  - "AttemptController@submit — transactional, idempotent finalize"
  - "attempts.submit route"
  - "SubmitAttemptRequest Form Request"
  - "Attempt::finalize() + shared lockAndFinalize() private helper (single finalize implementation)"
  - "Live Alpine countdown with warning/critical color escalation and one-shot bucket-change announcement"
  - "Client auto-submit at zero, and on any 422 deadline-rejected write, via a bubbled deadline-expired window event"
  - "Confirm-submit modal wired to the real submit route"
affects: [phase-5-grading-results]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Single shared lockAndFinalize() private helper on Attempt, used by both finalize() (manual submit) and finalizeIfExpired() (lazy expiry) — one lock-then-check-then-update implementation, never duplicated"
    - "submitted_at clamped to min(now(), deadline()) inside the shared finalize primitive — never recorded past the server deadline regardless of which caller triggers it"
    - "Outer page-level Alpine x-data (attemptTimer) wraps the whole take page so autoSubmitting is reachable from nested per-question card scopes without a shared JSON blob (still avoids the Pitfall-3 answer-key leak vector — nested scopes only share the timer's own state, never question/option data)"
    - "Cross-scope expiry signaling via a bubbled window CustomEvent (deadline-expired) rather than relying on Alpine's implicit nested-scope method inheritance — a per-question 422 catch dispatches the event, the outer timer scope listens for it and drives the same autoSubmit() path as hitting zero"

key-files:
  created:
    - app/Http/Requests/Student/SubmitAttemptRequest.php
  modified:
    - app/Http/Controllers/Student/AttemptController.php
    - app/Models/Attempt.php
    - routes/student.php
    - resources/views/student/attempts/show.blade.php

key-decisions:
  - "Refactored Attempt::finalizeIfExpired() to extract a private lockAndFinalize(callable $guard) helper, then added Attempt::finalize() (guard always true) for manual submit — satisfies the checker directive that submit() must reuse the shared finalize path rather than reimplementing a second DB::transaction+lockForUpdate block"
  - "Added SubmitAttemptRequest (authorize()=true, rules()=[]) purely for one-Form-Request-per-write parity with AnswerRequest/STACK.md section 6, even though submit() has no body fields to validate — ownership stays in AttemptPolicy via the controller's explicit authorize() call, matching the existing AnswerRequest split"
  - "Auto-submit trigger from a per-question 422 uses a bubbled window CustomEvent (deadline-expired) rather than assuming Alpine nested-scope method access, keeping the per-question card scope fully decoupled from the outer timer scope's internals"

requirements-completed: [TAK-04, TAK-02]

# Metrics
duration: 12min
completed: 2026-07-15
status: complete
---

# Phase 4 Plan 4: Submit + Live Countdown + Auto-Submit Summary

**Transactional idempotent submit() reusing Attempt::finalize()/finalizeIfExpired()'s shared lockAndFinalize() primitive, plus a live Alpine countdown (300s/60s color escalation, one-shot bucket-change announcement) that auto-submits at zero or on any 422 deadline rejection.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-07-15T17:32:46Z
- **Completed:** 2026-07-15T17:44:00Z
- **Tasks:** 2
- **Files modified:** 5 (1 created, 4 modified)

## Accomplishments
- `AttemptController@submit` finalizes an `in_progress` attempt exactly once under a DB transaction + `lockForUpdate`, safe under double-click/two-tab races (proven by `AttemptSubmitTest`)
- Extracted a single shared `lockAndFinalize()` primitive on `Attempt` so `finalize()` (manual submit) and `finalizeIfExpired()` (lazy on-touch expiry) are two thin callers of one lock-then-check-then-update implementation — no duplicated finalize logic anywhere in the codebase
- `submitted_at` is always `min(now(), deadline())`, so it can never be recorded past the server deadline regardless of which path finalizes the attempt
- Take page's Submit button now opens the `x-modal` confirm dialog ("Submit this exam?" / answered-of-total count / "Keep Working" / "Yes, Submit") which POSTs the real `attempts.submit` route
- Static countdown badge replaced with a live Alpine `attemptTimer()` component: ticks every second from the server-seeded `remaining_seconds`, recolors at the fixed 300s/60s thresholds (indigo → amber → red+pulse), and auto-submits at zero — disabling every form control and showing a non-dismissable "Time's up — submitting your exam…" banner before redirecting to the confirmation
- A 422 from any in-flight per-question autosave (deadline crossed mid-request) now drives the identical auto-submit transition via a bubbled `deadline-expired` window event, matching the UI-SPEC's "deadline-rejected write" copy contract
- Countdown state transitions (not per-tick ticking) write a one-shot `aria-live="assertive"` announcement, keeping the existing per-question `aria-live="polite"` autosave tags untouched

## Task Commits

Each task was committed atomically:

1. **Task 1: AttemptController@submit + route + wire the confirm modal** - `11b8fb7` (feat)
2. **Task 2: Live Alpine countdown (tick + escalation + auto-submit) + transition banner** - `e0c6db2` (feat)

**Plan metadata:** (this commit) - `docs(04-04): complete submit plan`

## Files Created/Modified
- `app/Http/Requests/Student/SubmitAttemptRequest.php` - Near-empty Form Request (authorize()=true, rules()=[]) for submit(), matching the AnswerRequest shape-validation-only split
- `app/Http/Controllers/Student/AttemptController.php` - Added `submit()`: authorize('update') first, then delegates entirely to `Attempt::finalize()`
- `app/Models/Attempt.php` - Added `finalize()` + private `lockAndFinalize(callable $guard)`; refactored `finalizeIfExpired()` to call the same shared helper
- `routes/student.php` - Added `POST attempts/{attempt}/submit` named `attempts.submit`
- `resources/views/student/attempts/show.blade.php` - Wired the confirm-submit modal to the real route; wrapped the whole page in an outer `attemptTimer()` Alpine scope driving the live countdown, color escalation, form-control disabling, auto-submit transition banner, and bucket-change accessibility announcement

## Decisions Made
- Reused `finalizeIfExpired()`'s lock-then-check-then-update shape via a new shared private `lockAndFinalize()` helper instead of hand-rolling a second transaction block in `submit()`, per the checker directive that there must be exactly one finalize implementation
- Added `SubmitAttemptRequest` for Form-Request convention parity even though it validates nothing, following the AnswerRequest precedent of `authorize()=true` (shape only) + controller-level `$this->authorize('update', $attempt)` for the actual ownership/policy gate
- Used a bubbled `window` `CustomEvent('deadline-expired')` to connect a per-question 422 to the outer countdown's `autoSubmit()`, rather than relying on implicit Alpine nested-scope method resolution — keeps the two Alpine scopes decoupled and easy to reason about independently

## Deviations from Plan

None - plan executed exactly as written. The `Attempt::finalize()` + shared `lockAndFinalize()` refactor and the `SubmitAttemptRequest` Form Request were both anticipated by the plan's own critical-notes/checker directives, not unplanned discoveries, so they are recorded here as decisions rather than deviations.

## Issues Encountered
None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Phase 4 (Attempt-Taking) is now fully implemented: single-attempt start/resume, server-authoritative timer, per-question autosave, and transactional idempotent submit with a live client countdown — the two-layer auto-submit (client countdown + server `finalizeIfExpired()` backstop) is closed end-to-end.
- Full Phase 1-4 suite green (150 tests, 372 assertions) with no regressions.
- Phase 5 (Grading & Results) can now build on `attempts.status = submitted`/`submitted_at` as its entry condition; `Attempt::finalize()`/`finalizeIfExpired()` never touch `is_correct`/`score` — grading remains an explicit, separate step per the project's no-model-events convention.

---
*Phase: 04-attempt-taking*
*Completed: 2026-07-15*

## Self-Check: PASSED

All created/modified files verified present on disk; both task commits (`11b8fb7`, `e0c6db2`) verified present in git log.

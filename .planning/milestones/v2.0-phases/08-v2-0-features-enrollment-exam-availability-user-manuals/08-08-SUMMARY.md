---
phase: 08-v2-0-features-enrollment-exam-availability-user-manuals
plan: 08
subsystem: ui
tags: [alpine, blade, beforeunload, attempt-taking]

# Dependency graph
requires:
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals
    provides: "08-03's AttemptPolicy ownership fix and the existing attemptTimer() Alpine factory (04-04) this plan extends"
provides:
  - "beforeunload tab-close/navigate-away warning while an attempt is in_progress, attached in attemptTimer().init()"
  - "detachBeforeUnload(), an idempotent method removing the listener via its stored named reference"
  - "detach wired into both legitimate exits: autoSubmit() (before its axios POST) and the intentional-submit form (x-on:submit)"
affects: [08-09]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Stored named event-handler reference on an Alpine component instance (this._beforeUnloadHandler) so removeEventListener can actually detach it — never an anonymous inline handler"

key-files:
  created: []
  modified:
    - resources/views/student/attempts/show.blade.php

key-decisions:
  - "AVL-05 is UX-only, never an integrity control — server-side finalizeIfExpired()/expires_at remain the sole authoritative enforcement (T-08-08-FALSE, unchanged from plan)"
  - "No custom beforeunload message string set — modern browsers ignore it and render only their own text; only the legacy-compat event.returnValue = '' is set"
  - "Task 2 (human-verify checkpoint) could not be run: the user is away and this behavior has no automated vehicle (PHPUnit executes no browser JS; Dusk would violate CLAUDE.md's no-new-Composer-packages constraint). Code is complete and inspected; live-browser confirmation is deferred — see 'Pending user verification (AVL-05)' below."

patterns-established:
  - "Named-handler-reference pattern for any future addEventListener/removeEventListener pair on an Alpine component: store the closure on `this`, never pass an anonymous function directly to addEventListener if it will ever need to be removed."

requirements-completed: [AVL-05]

# Metrics
duration: 15min
completed: 2026-07-16
status: complete
---

# Phase 08 Plan 08: beforeunload In-Progress Safeguard Summary

**Named-handler `beforeunload` listener on the exam-taking page, attached in `attemptTimer().init()` and explicitly detached before both legitimate exits (intentional submit, timer auto-submit) — code complete and inspected; live-browser confirmation (Task 2) is pending because the user is away.**

## Performance

- **Duration:** 15 min
- **Completed:** 2026-07-16T23:29:26+08:00
- **Tasks:** 1 of 2 completed (Task 2 is a blocking human-verify checkpoint — see below)
- **Files modified:** 1

## Accomplishments

- Extended the existing `attemptTimer()` Alpine factory (no new component) to attach a `beforeunload` listener on init, guarding against firing while `autoSubmitting` is already true.
- The handler is stored as a named reference (`this._beforeUnloadHandler`), never an anonymous inline function, so it is provably removable.
- `detachBeforeUnload()` added: idempotent (`if (this._beforeUnloadHandler)` guard, nulls it after removal), safe to call from both exit paths even if one already ran.
- Wired into both legitimate exits:
  - `autoSubmit()` — detaches immediately after `this.autoSubmitting = true;` and before the axios POST, matching the plan's required ordering.
  - The intentional-submit `<form>` inside the `x-modal` — `x-on:submit="detachBeforeUnload()"` added to the opening tag.
- No custom message string set; countdown, autosave, and FIX-01's reactive answered-count logic untouched.

## Task Commits

Each task was committed atomically:

1. **Task 1: beforeunload attach on init, detach on both intentional exit paths** - `40b3a17` (feat)

Task 2 (checkpoint:human-verify, gate="blocking") was not executable this session — see "Pending user verification (AVL-05)" below. No code work remains for it; it is purely an observational checkpoint.

**Plan metadata:** committed alongside this SUMMARY (see final commit below).

## Files Created/Modified

- `resources/views/student/attempts/show.blade.php` - `attemptTimer()` factory extended: `init()` attaches a named `beforeunload` handler; new `detachBeforeUnload()` method; `autoSubmit()` detaches before its POST; intentional-submit form carries `x-on:submit="detachBeforeUnload()"`.

## Decisions Made

- Followed the plan's exact insertion points from 08-PATTERNS.md verbatim (named handler in `init()`, detach call ordering in `autoSubmit()`, `x-on:submit` on the modal form) — no deviation from the prescribed diff shape.
- Added one defensive guard beyond the plan's literal text: `init()` only attaches the listener `if (!this.autoSubmitting)`. `autoSubmitting` is always `false` at `init()` time in the current codebase (it's a fresh Alpine instance per page load and `AttemptController@show` only renders this view for `in_progress` attempts), so this is inert today, but it documents the invariant explicitly at the attach site per the plan's own instruction ("assert it explicitly at the attach site... rather than relying on that upstream redirect as an invisible precondition"). This is not a Rule 1-4 deviation — it implements the plan's own explicit instruction, just made literal in code rather than left as prose.

## Deviations from Plan

None — plan executed exactly as written. The `if (!this.autoSubmitting)` guard described above is the plan's own "assert it explicitly" instruction made literal, not an unplanned addition.

## Code Inspection Evidence (verifiable without a browser)

Per this plan's `<validation_honesty>` block, PHPUnit cannot execute browser JS or observe a native `beforeunload` dialog. The following is what *can* be verified by direct inspection of the compiled/authored source, with exact file:line citations, as of commit `40b3a17`:

- **Listener attaches while in_progress:** `resources/views/student/attempts/show.blade.php:270-300` — `init()` calls `this.setBucket(false); this.render();` then constructs `this._beforeUnloadHandler` and calls `window.addEventListener('beforeunload', this._beforeUnloadHandler)` at line 298. `init()` only runs via `x-init="init(); start()"` (line 35) on a page that `AttemptController@show` only renders for an `in_progress` attempt (any other status redirects away before this view renders — unchanged, pre-existing behavior), so attach-while-in_progress holds by construction.
- **Named reference, not anonymous:** the handler assigned at line 294-297 is stored as `this._beforeUnloadHandler`, not passed inline to `addEventListener` — satisfying the plan's hard requirement that it be removable.
- **`detachBeforeUnload()` exists and is idempotent:** lines 304-309. Guards on `if (this._beforeUnloadHandler)` before calling `removeEventListener`, then sets it to `null` — a second call is a no-op, matching "safe to call twice."
- **Detach on the intentional-submit path:** line 211 — `<form method="POST" action="{{ route('student.attempts.submit', $attempt) }}" class="p-6" x-on:submit="detachBeforeUnload()">`. Alpine's `x-on:submit` fires synchronously on the browser's native `submit` event, before the POST navigation begins, so the listener is provably gone before any `beforeunload` could fire on that navigation.
- **Detach on the timer's own auto-submit path:** lines 358-369 — `autoSubmit()` sets `this.autoSubmitting = true;` (363) then calls `this.detachBeforeUnload();` (364) *before* `window.axios.post(submitUrl)` (368) and the subsequent `.finally(() => { window.location.href = submittedUrl; })`. Detach happens strictly before the async call that eventually triggers `window.location.href` navigation.
- **grep confirmation (matches this plan's `<verification>` requirement exactly):**
  ```
  298:    window.addEventListener('beforeunload', this._beforeUnloadHandler);
  306:    window.removeEventListener('beforeunload', this._beforeUnloadHandler);
  ```
  Exactly one `addEventListener` and one `removeEventListener`, both using the identical stored reference `this._beforeUnloadHandler`.
- **No custom message string:** the handler body (lines 294-297) contains only `event.preventDefault(); event.returnValue = '';` — no string literal, satisfying the UI-SPEC/prohibition constraint.

What this inspection **cannot** prove — and what remains genuinely pending — is that a real browser actually renders its native dialog on tab-close/navigate-away, and stays silent on the two detach paths, under real sticky-activation conditions. That is Task 2's exclusive purpose.

## Pending user verification (AVL-05)

**Status: pending-user — NOT passed.** Task 2 (`checkpoint:human-verify`, `gate="blocking"`) genuinely requires a human in a real browser; nothing in this session's toolchain can execute or observe a native `beforeunload` dialog. The user is currently away, so this session did not fabricate a pass. When the user is available, they should carry out the following repro steps and report back:

**Setup (~2 minutes):**
1. `php artisan migrate:fresh --seed` then `npm run build` (already run this session — build succeeded, see below) or `npm run dev`.
2. Log in as the seeded student who has NOT yet attempted the demo exam (`student@example.com`, per 06-01's seeder notes — left un-attempted specifically for this kind of walkthrough).
3. Go to My Exams -> the demo exam -> Proceed. You should land on the exam-taking page with the countdown running.

**Check 1 — the warning DOES appear:**
4. Click anywhere on the page first (browsers suppress the dialog without prior interaction — "sticky activation"; expected browser behavior, not a bug).
5. Try to close the tab, or navigate away (nav link, or Back).
6. EXPECT: the browser's own native "Leave site? / Changes you made may not be saved" dialog appears. Wording is the browser's own — do not expect custom text.
7. Cancel/stay on the page.

**Check 2 — no warning on intentional submit:**
8. Answer at least one question (autosave fires), click Submit, confirm in the modal.
9. EXPECT: NO dialog. Lands directly on the submitted-confirmation page.

**Check 3 — no warning on auto-submit:**
10. Start a fresh attempt as a different seeded student on a short-duration exam (temporarily shorten the demo exam's duration before seeding, or create/publish a 1-minute draft exam assigned to that student's section).
11. Start the attempt, let the countdown run to 00:00 without touching anything else.
12. EXPECT: timer auto-submits, redirect happens, NO dialog.

**Check 4 — dark mode sanity:**
13. Toggle dark mode on the exam-taking page; confirm no visual regression.

If Check 1 fails: the listener is not attaching (or step 4's click was skipped).
If Check 2 or 3 fails (a dialog DOES appear): the detach did not fire — report this rather than approving it; per the plan's threat register (T-08-08-UX), this is the failure mode of greatest concern, worse than shipping no warning at all.

**Browser(s) used:** not yet tested (pending).
**Browser-specific notes for 08-09's student manual:** none yet — to be filled in once Task 2 is actually run. The one universally-true note to carry into 08-09's plain-language description: the dialog's exact wording is the browser's own and varies by browser; the manual should describe the *behavior* ("your browser will ask you to confirm before leaving"), not quote specific button/dialog text.

## Issues Encountered

None beyond the expected inability to execute Task 2 without a live browser and an available human — this was anticipated and pre-declared in the plan's own `<validation_honesty>` block, not a surprise.

## Pre-existing, out-of-scope test failures observed (not caused by this plan)

While running the plan's `<automated>` verify commands, `php artisan test --filter=Attempt` surfaced 3 pre-existing failures in `Tests\Feature\Student\AttemptAvailabilityTest` (AVL-03 territory — `starting before available from is refused with no attempt created`, `starting after available until is refused with no attempt created`, `starting exactly at available until is refused`), all `assertDatabaseCount('attempts', 0)` failing with "Entries found: 1". These are unrelated to this plan's file scope (`resources/views/student/attempts/show.blade.php` only — no PHP touched) and were confirmed pre-existing by stashing this plan's change and re-running the same filter: identical 3 failures, 7 passed, with the change absent. Per the deviation rules' Scope Boundary, this is out of scope for 08-08 and is logged here rather than fixed. `--filter=AttemptShowTest` (the test class most directly touching this plan's page) is fully green (4/4).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The `beforeunload` code is complete, committed, and inspected by evidence above; only the live-browser human checkpoint (Task 2) remains, pending the user's return.
- 08-09 (student manual) can proceed and should describe this warning in plain language per its "Taking a Timed Exam" flow, using the behavior description above (not specific dialog wording).
- Flag the 3 pre-existing `AttemptAvailabilityTest` failures (AVL-03) for a future plan/session — they are not blocking 08-08's scope but do represent a real regression somewhere in the AVL-03 availability-window enforcement path (`AttemptController::store` appears to be creating an Attempt row before or despite the availability check in these specific boundary cases) that should be triaged before the phase gate closes.

---
*Phase: 08-v2-0-features-enrollment-exam-availability-user-manuals*
*Completed: 2026-07-16*

## Self-Check: PASSED

- FOUND: resources/views/student/attempts/show.blade.php
- FOUND: .planning/phases/08-v2-0-features-enrollment-exam-availability-user-manuals/08-08-SUMMARY.md
- FOUND: 40b3a17

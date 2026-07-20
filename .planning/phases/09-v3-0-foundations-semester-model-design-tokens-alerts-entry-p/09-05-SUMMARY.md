---
phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p
plan: 05
subsystem: api
tags: [laravel, eloquent, exceptions, race-condition, toctou, phpunit]

# Dependency graph
requires:
  - phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p (plan 01)
    provides: "tests/Feature/AttemptNullGuardTest.php — the 5-test executable spec this plan turns green"
provides:
  - "App\\Exceptions\\AttemptVanishedException — typed, self-rendering failure for a locked attempts read that returns null (422 JSON for the answer endpoint, redirect+flash elsewhere)"
  - "Null guard in Attempt::lockAndFinalize() (crash site 1, reached via finalize()/finalizeIfExpired())"
  - "Null guard in AttemptController::answer()'s independent locked read (crash site 2)"
  - "Unfiltered `php artisan test` now runs to completion — the prior Whoops mid-run crash (missing AttemptVanishedException class) is resolved"
affects: [10 (attempt-reset — the first code path that will actually delete an in-progress attempt row and exercise this guard in production)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Self-rendering domain exceptions (render(Request) on the exception itself) instead of bootstrap/app.php ->withExceptions() registration — keeps a single failure's full contract (when it fires, what the user sees) in one file."
    - "TOCTOU null-guard immediately after every lockForUpdate()->first() on a row that a concurrent request could have deleted, before any member access on the result."

key-files:
  created:
    - app/Exceptions/AttemptVanishedException.php
  modified:
    - app/Models/Attempt.php
    - app/Http/Controllers/Student/AttemptController.php

key-decisions:
  - "Broadened AttemptVanishedException::render()'s JSON-response condition from request->expectsJson() alone to (expectsJson() OR routeIs('student.attempts.answer')) — the answer() endpoint already returns JSON unconditionally regardless of Accept/X-Requested-With headers (matching its pre-existing {'expired': true} sibling response), and the AttemptNullGuardTest autosave test posts without those headers, as real form/test POSTs typically do. Real production autosave traffic goes through window.axios, which bootstrap.js configures to always send X-Requested-With: XMLHttpRequest, so expectsJson() alone would have worked for genuine browser traffic; the routeIs() clause makes the endpoint's always-JSON contract explicit and test-observable rather than relying on client header discipline."
  - "Kept the ordinary-expiry short-circuit (status !== 'in_progress' => return false) as a separate statement from the new null guard in answer(), per the plan's explicit instruction — merging them would mislabel a vanished row as an ordinary 'this attempt has ended' expiry, which is the wrong message."

patterns-established:
  - "Pattern 1: Any future lockForUpdate()->first() read on a row a concurrent request could delete must null-check before member access, throwing a typed self-rendering exception rather than a bare null-dereference crash or a boolean that conflates 'vanished' with 'already-finalized/idempotent-no-op'."

requirements-completed: [INT-01]

# Metrics
duration: 12min
completed: 2026-07-17
status: complete
---

# Phase 9 Plan 05: INT-01 Null-Guard (AttemptVanishedException) Summary

**Both independent `lockForUpdate()->first()` reads on `attempts` (Attempt::lockAndFinalize() and AttemptController::answer()) now null-guard against a concurrently-deleted row, raising a self-rendering `AttemptVanishedException` (422 JSON / redirect+flash) instead of crashing — and the previously suite-halting Whoops bug on the unfiltered `php artisan test` run is resolved as a side effect.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-07-17T12:32:00+08:00 (approx, first Read of plan)
- **Completed:** 2026-07-17T12:44:32+08:00
- **Tasks:** 2 completed
- **Files modified:** 3 (1 created, 2 modified)

## Accomplishments

- Created `App\Exceptions\AttemptVanishedException`, a self-rendering `RuntimeException` carrying the INT-01 copy contract and both response shapes (422 `{expired, vanished, message}` for the autosave endpoint, redirect+`session('error')` flash elsewhere).
- Null-guarded crash site 1 (`Attempt::lockAndFinalize()`, line 141's locked re-read) — reached via both `finalize()` and `finalizeIfExpired()`.
- Null-guarded crash site 2 (`AttemptController::answer()`'s own independent locked read, line 178) — the autosave path, which is NOT a caller of `lockAndFinalize()` for this specific read.
- All 5 `AttemptNullGuardTest` tests pass; the pre-existing `AttemptAnswerTest` regression suite (5 tests) is unaffected — the guard is additive.
- Confirmed the unfiltered `php artisan test` now runs to completion: 324 passed, 15 failed (no crash) — down from a hard mid-run abort before this plan.
- `./vendor/bin/pint --test` clean on all 3 touched files.
- Marked `INT-01` complete in `.planning/REQUIREMENTS.md`.

## Task Commits

Each task was committed atomically:

1. **Task 1: Create app/Exceptions/AttemptVanishedException.php** - `65cb5b8` (feat)
2. **Task 2: Guard both locked reads** - `20a6323` (fix)

**Plan metadata:** (this commit, docs: complete plan)

## Files Created/Modified

- `app/Exceptions/AttemptVanishedException.php` - New self-rendering exception; `MESSAGE` constant with the exact 09-UI-SPEC.md copy; `render()` returns 422 JSON (`expired`+`vanished`+`message`) for `expectsJson()` requests OR the `student.attempts.answer` route, else redirects to `student.exams.index` flashing `session('error')`.
- `app/Models/Attempt.php` - Added `use App\Exceptions\AttemptVanishedException;`; null-guard in `lockAndFinalize()` immediately after the locked re-read, before `setRelation('exam', ...)`, with a long doc-comment explaining the TOCTOU race, why it's distinct from the idempotent-no-op branch, and why the in-memory sync is deliberately skipped on this path.
- `app/Http/Controllers/Student/AttemptController.php` - Added `use App\Exceptions\AttemptVanishedException;`; null-guard in `answer()`'s own locked read, kept as a separate statement above the existing `status !== 'in_progress'` short-circuit, with a comment explaining why the two must not be merged.

## Decisions Made

See `key-decisions` in frontmatter. Summary: the exception's JSON/redirect branch condition needed to be broadened beyond `expectsJson()` alone to also recognize the `student.attempts.answer` route explicitly, because that endpoint's existing (pre-guard) response is unconditionally JSON regardless of request headers, and the test posting to it doesn't set AJAX-style headers.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Broadened AttemptVanishedException::render()'s JSON detection to cover the answer() route explicitly**
- **Found during:** Task 2 verification (`test_autosave_fails_safely_when_the_attempt_row_vanishes_mid_request` initially failed: expected 422, got 302)
- **Issue:** The plan's literal instruction (`if ($request->expectsJson())`) returns `false` for the test's plain `->post()` call, which sends no `Accept: application/json` or `X-Requested-With` header — so the exception rendered as a redirect instead of the required 422 JSON response for the autosave path. `AttemptController::answer()`'s pre-existing (pre-guard) 422 response returns JSON unconditionally regardless of request headers, so the new vanished-row response needs to match that established, always-JSON contract for the same endpoint.
- **Fix:** Changed the condition to `$request->expectsJson() || $request->routeIs('student.attempts.answer')`, with a comment explaining both the general case (real browser autosave traffic via `window.axios`, which `bootstrap.js` configures to send `X-Requested-With: XMLHttpRequest`) and the explicit route-level guarantee needed for the endpoint's always-JSON contract.
- **Files modified:** `app/Exceptions/AttemptVanishedException.php`
- **Verification:** `php artisan test --filter=AttemptNullGuardTest` — all 5 pass (was 4/5 before the fix).
- **Committed in:** `20a6323` (Task 2 commit — file was already created in Task 1's commit `65cb5b8`; this fix amends it as part of Task 2's verification loop, not a separate commit, since Task 2 was still in progress when discovered)

**2. [Rule 1 - Bug] Reworded a code-review comment to avoid tripping the plan's own acceptance-criteria grep**
- **Found during:** Task 2 acceptance-criteria verification
- **Issue:** The acceptance criterion `grep -c "! \$locked || \$locked->status" ... returns 0` (checking the merged-condition anti-pattern is absent from the file) was tripped by my own explanatory comment, which quoted that exact anti-pattern string as an example of what NOT to write.
- **Fix:** Reworded the comment to describe the anti-pattern in prose ("a single combined 'not locked OR not in_progress' condition") instead of quoting the literal code string, preserving the comment's intent without matching the grep.
- **Files modified:** `app/Http/Controllers/Student/AttemptController.php`
- **Verification:** `grep -c "! \$locked || \$locked->status" app/Http/Controllers/Student/AttemptController.php` returns `0`.
- **Committed in:** `20a6323` (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (both Rule 1 — bugs blocking the plan's own stated done-criteria). No scope creep; both fixes are within Task 2's existing file set and required to satisfy the plan's own acceptance criteria and the test spec.

## Issues Encountered

None beyond the deviations above. `./vendor/bin/pint` auto-fixed 3 minor style nits (`new AttemptVanishedException()` → `new AttemptVanishedException`, unary-operator spacing) across `app/Models/Attempt.php` and `app/Http/Controllers/Student/AttemptController.php`; re-verified tests and acceptance-criteria greps still pass after the pint pass.

## User Setup Required

None - no external service configuration required.

## Verification Evidence

**`php artisan test --filter=AttemptNullGuardTest`** — 5 passed (10 assertions), 1.7s.

**`php artisan test --filter=AttemptAnswerTest`** — 5 passed (10 assertions), no regression on the shipped autosave path.

**Unfiltered `php artisan test`** — runs to completion (no mid-run crash, resolving the Whoops bug flagged by 09-03): **324 passed, 15 failed** (809 assertions), ~19s.

The 15 remaining failures are exactly the pre-existing Wave 0 RED specs owned by later plans in this phase — enumerated here so those plans know what they own:

- **Owned by 09-06** (login card / design tokens, `resources/views/auth/login.blade.php`, `tailwind.config.js`): `Tests\Feature\Auth\AuthenticationTest` — 4 failures (`the login screen renders the flowbite card`, `the login card links to the register route`, `the login card links to the password reset route`, `the login card uses the ported design tokens`).
- **Owned by 09-08** (landing page, `resources/views/landing.blade.php`, `routes/web.php`): `Tests\Feature\LandingPageTest` — 6 failures (`the landing page renders for a guest`, `the landing page shows the app title and subtitle`, `the landing page title tag names the app`, `the landing page links to login`, `an authenticated student is redirected from the landing page to the dashboard`, `an authenticated lecturer is redirected from the landing page to the dashboard`).
- **Owned by 09-07** (`<x-toast>` component): `Tests\Feature\ToastTest` — 3 failures (`a status flash renders as a toast`, `an error flash renders as a toast`, `an error flash renders exactly once`).
- **Owned by 09-10** (`<x-confirm-modal>` migration for the 2 lecturer views still using native `confirm()`): `Tests\Feature\NoNativeDialogTest` — 2 failures (`no blade view invokes a native browser dialog`, `the destructive lecturer forms use the confirm modal component`).

This exactly matches the count and identity documented as pre-existing in `09-03-SUMMARY.md`'s whole-suite regression check (15 failed at that point too, same 4 test classes) — confirming this plan introduced zero new failures and the null-guard fix did not touch any of these unrelated areas.

**Acceptance-criteria greps (Task 1):**
- `grep -c "class AttemptVanishedException extends" app/Exceptions/AttemptVanishedException.php` → 1
- `grep -c "public function render" app/Exceptions/AttemptVanishedException.php` → 1
- `grep -c "'vanished' => true" app/Exceptions/AttemptVanishedException.php` → 1
- `grep -c "'expired' => true" app/Exceptions/AttemptVanishedException.php` → 1
- `grep -c "->with('error'" app/Exceptions/AttemptVanishedException.php` → 1
- `grep -c "This exam attempt is no longer available. Please return to your exam list." app/Exceptions/AttemptVanishedException.php` → 1
- `git diff --name-only bootstrap/app.php` → no output (untouched)

**Acceptance-criteria greps (Task 2):**
- `grep -c "throw new AttemptVanishedException" app/Models/Attempt.php` → 1
- `grep -c "throw new AttemptVanishedException" app/Http/Controllers/Student/AttemptController.php` → 1
- `grep -c "status !== 'in_progress'" app/Http/Controllers/Student/AttemptController.php` → 3 (>= 1 required by the acceptance criterion; the ordinary-expiry short-circuit is intact as its own separate `if` statement, not merged with the new null guard)
- `grep -c "! \$locked || \$locked->status" app/Http/Controllers/Student/AttemptController.php` → 0 (merged form absent, after the comment reword above)
- `grep -n "lockForUpdate()->first()"` across both files → exactly 2 lines: `app/Models/Attempt.php:141` and `app/Http/Controllers/Student/AttemptController.php:178`. Both are immediately followed (within the next 3 lines) by an explicit `if (! $locked) { throw new AttemptVanishedException; }` before any member access on the result.
- `./vendor/bin/pint --test app/Exceptions/AttemptVanishedException.php app/Models/Attempt.php app/Http/Controllers/Student/AttemptController.php` → `{"tool":"pint","result":"passed"}`

## Next Phase Readiness

- INT-01 is fully satisfied: both crash sites are guarded, the guard is additive (no happy-path behavior change, confirmed by `test_a_surviving_attempt_still_finalizes_normally` and the full `AttemptAnswerTest` suite), and INT-01 has been marked complete in `.planning/REQUIREMENTS.md`.
- Phase 10's attempt-reset feature can now safely delete an in-progress `attempts` row — any request racing that delete will fail safely (422 for autosave, redirect+flash for page loads) instead of crashing.
- Unfiltered `php artisan test` is now a usable single command for this repo going forward — no plan needs to work around the prior Whoops crash.
- Plans 09-06, 09-07, 09-08, and 09-10 each have their exact remaining RED test list enumerated above.

---
*Phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p*
*Completed: 2026-07-17*

## Self-Check: PASSED

- FOUND: .planning/phases/09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p/09-05-SUMMARY.md
- FOUND: app/Exceptions/AttemptVanishedException.php
- FOUND: commit 65cb5b8
- FOUND: commit 20a6323

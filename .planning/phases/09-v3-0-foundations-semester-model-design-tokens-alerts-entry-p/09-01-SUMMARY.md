---
phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p
plan: 01
subsystem: testing
tags: [phpunit, tdd, value-object, race-condition, carbon]

# Dependency graph
requires: []
provides:
  - "tests/Unit/SemesterTest.php — 17-method executable spec for App\\Support\\Semester (SEM-01/02/03)"
  - "tests/Feature/AttemptNullGuardTest.php — 5-method executable spec for the INT-01 vanished-attempt-row guard, covering both crash sites"
  - "Corrected ordinal() formula finding: year*2 + (2-number), not year*2 + (number-1) as 09-RESEARCH.md proposed"
affects: [09-04, 09-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Gate::after mid-request row deletion — exercises TOCTOU races via the same authorization seam production hits (post route-model-binding, pre locked-read), rather than a pre-request delete that a route-model-bound route would 404 on before reaching the guard"
    - "Monotonicity-by-property test (sort by ordinal() vs sort by startsAt(), assert identical order) instead of pairwise ordinal comparisons — catches any formula that disagrees with real dates, not just the specific cases enumerated"

key-files:
  created:
    - tests/Unit/SemesterTest.php
    - tests/Feature/AttemptNullGuardTest.php
  modified: []

key-decisions:
  - "Fixed test_a_surviving_attempt_still_finalizes_normally to assert status 'graded' (not 'submitted' as the plan's literal instruction stated) — this MCQ-only fixture has no open-text question pending, so AttemptGrader's syncStatus() transitions submitted->graded in the same finalize() call, matching the identical pattern already asserted in tests/Feature/Student/AttemptAnswerTest.php and 4 other existing test files. Verified by grep across tests/ before writing."
  - "Added travelTo() past the deadline in test_the_take_page_redirects_with_an_error_when_the_attempt_row_vanishes_mid_request — without it, AttemptController::show()'s finalizeIfExpired() short-circuits on the not-yet-expired check and never touches the deleted attempts row, so the page would render 200 rather than exercising crash site 1. This makes the test a legitimate RED tied to the actual guard, and ensures it will go GREEN (not remain falsely red) once plan 09-05 implements the guard."

requirements-completed: [SEM-01, SEM-02, SEM-03, INT-01]

# Metrics
duration: 10min
completed: 2026-07-17
status: complete
---

# Phase 09 Plan 01: Wave 0 Failing Tests for Semester Value Object and INT-01 Null Guards Summary

**Two new PHPUnit files pin the Semester value object's API (SEM-01/02/03) and the INT-01 vanished-attempt-row guard contract across both independent crash sites — 22 new tests, 21 legitimately RED (missing class / unguarded null dereference), 1 control test GREEN, zero regressions across the existing 294 tests.**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-07-17T03:50Z
- **Completed:** 2026-07-17T03:59Z
- **Tasks:** 2 completed
- **Files modified:** 2 (both new)

## Accomplishments

- `tests/Unit/SemesterTest.php` — 17 test methods locking `App\Support\Semester`'s full public API (constructor validation, `forDate()`, `startsAt()`/`endsAt()`, `contains()`, `ordinal()`, `isCurrent()`/`isFuture()`/`isPast()`, `label()`) before the class exists. Includes the load-bearing `test_ordinal_is_monotonic_with_start_date`, which sorts a 4-semester set two ways (by `ordinal()`, by `startsAt()`) and asserts identical resulting order — this catches the inverted `ordinal()` formula 09-RESEARCH.md proposed (`year*2 + (number-1)`), which the research's own narrower test would have missed.
- `tests/Feature/AttemptNullGuardTest.php` — 5 test methods locking the INT-01 vanished-row guard across BOTH independent crash sites: `Attempt::lockAndFinalize()` (`app/Models/Attempt.php:141`, reached via `finalize()`/`finalizeIfExpired()`) and `AttemptController::answer()`'s own separate `lockForUpdate()->first()` (`app/Http/Controllers/Student/AttemptController.php:172`). Uses a `Gate::after` hook to delete the attempts row mid-request — after route-model binding, before the locked read — reproducing the actual TOCTOU race rather than a pre-request delete that would 404 before the guard is ever reached.
- Confirmed via full-suite run: 316 total tests (294 pre-existing + 22 new), 18 errors + 3 failures = 21 legitimately RED new tests, 1 new control test passes, **zero pre-existing tests affected**.

## Task Commits

Each task was committed atomically:

1. **Task 1: Write the failing SEM-01/02/03 spec** - `2cbc300` (test)
2. **Task 2: Write the failing INT-01 spec covering BOTH crash sites** - `77967d1` (test)

_TDD RED phase only — no GREEN/REFACTOR commits in this plan; Wave 1 (plans 09-04, 09-05) implements the code these tests pin._

## Files Created/Modified

- `tests/Unit/SemesterTest.php` — 17 methods, no `RefreshDatabase` (pure value object, mirrors `WindowSemanticsTest`'s style)
- `tests/Feature/AttemptNullGuardTest.php` — 5 methods, `RefreshDatabase`, private `attemptFixture()`/`hardDeleteAttemptRow()`/`registerMidRequestDelete()` helpers

## Decisions Made

- **Corrected the plan's literal control-test assertion (Rule 1 — bug in the plan).** The plan's action text for `test_a_surviving_attempt_still_finalizes_normally` instructed asserting `status => 'submitted'`. Read against `AttemptGrader::syncStatus()` and cross-checked against 5 other existing test files (`AttemptAnswerTest`, `AttemptSubmitTest`, `AttemptShowTest`, `AttemptGraderTest`, `Phase4ReviewFixesTest`), a fixture with only an MCQ question (no open-text) always transitions `submitted -> graded` within the same `finalize()` call, since there is no pending open-text answer to withhold the result for. Asserting `'submitted'` as written would have made the control test itself RED, breaking the "1 test must pass" acceptance criterion. Fixed to assert `'graded'`, matching the codebase's established behavior.
- **Added `travelTo()` to the page-load INT-01 test (Rule 1 — bug in the plan, by omission).** `AttemptController::show()` only touches the attempts row via `finalizeIfExpired()`, which short-circuits without any DB read if the attempt is not yet expired. The plan's action text for `test_the_take_page_redirects_with_an_error_when_the_attempt_row_vanishes_mid_request` did not specify traveling past the deadline. Without it, the GET request would never reach the deleted row at all — the response would render 200 (not the intended crash-then-redirect), which is a RED for the wrong reason and, worse, would stay wrongly RED (or accidentally pass for the wrong reason) even after 09-05 implements the guard, since the guarded code path is never exercised. Added `$this->travelTo($attempt->started_at->copy()->addMinutes(31));` (mirroring test 2's pattern) so the request genuinely reaches crash site 1 via the HTTP page-load path, making this test a true full-stack analog of tests 1/2 rather than an unrelated assertion.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Corrected control test's expected final status from 'submitted' to 'graded'**
- **Found during:** Task 2 (writing `test_a_surviving_attempt_still_finalizes_normally`)
- **Issue:** Plan's literal instruction said to assert `status => 'submitted'` via `assertDatabaseHas`. The actual finalize pipeline (`Attempt::lockAndFinalize()` -> `AttemptGrader::handleFinalized()` -> `syncStatus()`) transitions an MCQ-only attempt straight to `graded` in the same call, since there's no open-text answer pending grading.
- **Fix:** Assert `status => 'graded'` instead, matching the pattern already established and asserted in 5 other existing test files.
- **Files modified:** `tests/Feature/AttemptNullGuardTest.php`
- **Verification:** `php artisan test --filter=AttemptNullGuardTest` shows this test passing (the required control-GREEN behavior).
- **Committed in:** `77967d1` (Task 2 commit)

**2. [Rule 1 - Bug] Added `travelTo()` past the deadline to the page-load INT-01 test**
- **Found during:** Task 2 (writing `test_the_take_page_redirects_with_an_error_when_the_attempt_row_vanishes_mid_request`)
- **Issue:** As literally specified (no time travel), the GET request to `student.attempts.show` would never reach the deleted attempts row, since `finalizeIfExpired()` short-circuits on the not-expired check before any DB read of the `attempts` table.
- **Fix:** Added `$this->travelTo($attempt->started_at->copy()->addMinutes(31));` before registering the mid-request delete, so `finalizeIfExpired()` proceeds into `lockAndFinalize()` and genuinely hits crash site 1 through the HTTP page-load path.
- **Files modified:** `tests/Feature/AttemptNullGuardTest.php`
- **Verification:** Test now fails with the expected crash-site-1 `Error: Call to a member function setRelation() on null` inside the request (confirmed via raw `vendor/bin/phpunit` output), rather than an unrelated 200 response.
- **Committed in:** `77967d1` (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (both Rule 1 — bugs in the plan's literal test instructions, discovered by reading the actual production code and cross-checking existing test conventions before writing assertions).
**Impact on plan:** Both fixes were necessary for the tests to be legitimate RED/GREEN specs rather than false positives/negatives. No scope creep — no production code was touched or implemented.

## Issues Encountered

- Laravel's Collision-based `php artisan test` pretty-printer crashes (a secondary `str_replace(): Argument #3 must be of type array|string, null given` error inside `vendor/filp/whoops`) when rendering the "Class ... does not exist" exception thrown by PHPUnit's `expectException()` resolution for a not-yet-existing class. This truncates `artisan test`'s colorized output mid-run but does **not** affect actual test pass/fail results. Worked around by also running `php vendor/bin/phpunit` directly (bypassing Collision) to get the clean, complete summary: `Tests: 5, Assertions: 4, Errors: 2, Failures: 2` for `AttemptNullGuardTest` (2 class-not-found errors on tests 1/2, 2 crash failures on tests 4/5, 1 pass on test 3 — the control), and `Tests: 316, Assertions: 739, Errors: 18, Failures: 3` for the full suite (all pre-existing 294 tests still pass).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Both Wave 0 backend test files exist, are RED for the right reasons, and lock the exact API/behavior contracts plans 09-04 (`App\Support\Semester`) and 09-05 (INT-01 null guards + `App\Exceptions\AttemptVanishedException`) must satisfy.
- `App\Support\Semester` does not exist yet — 09-04 must implement `__construct(int $year, int $number)` (throwing `InvalidArgumentException` for `$number` outside `{1,2}`), `forDate()`, `current()`, `startsAt()`/`endsAt()` (using `endOfMonth()`, not a hardcoded day), `contains()`, `ordinal()` (must satisfy `year*2 + (2-number)` or an equivalent formula that agrees with `startsAt()` ordering — verified by the monotonicity test), `isCurrent()`/`isFuture()`/`isPast()`, and `label()`.
- `App\Exceptions\AttemptVanishedException` does not exist yet — 09-05 must create it as a typed, self-rendering exception: thrown from both `Attempt::lockAndFinalize()`'s null-check and `AttemptController::answer()`'s direct `lockForUpdate()->first()` null-check; rendered as JSON `{vanished: true}` with a 422 status for the autosave path (keeping the existing `expired` key alongside it); rendered as a redirect to `student.exams.index` with `session('error', 'This exam attempt is no longer available. Please return to your exam list.')` for page-load paths (likely via a global exception-handler render callback in `bootstrap/app.php`'s `withExceptions()`, since both `show()` and `submitted()` need the same behavior without duplicating a try/catch in each controller method).
- No blockers. The 09-VALIDATION.md Wave 0 checklist items for SEM-01/02/03 and INT-01 are both satisfied by this plan's two files; the remaining Wave 0 checklist items (`LandingPageTest`, `AuthenticationTest` extension, `NoNativeDialogTest`, `ToastTest`) belong to other 09-0x plans, not this one.

---
*Phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p*
*Completed: 2026-07-17*

## Self-Check: PASSED

- FOUND: tests/Unit/SemesterTest.php
- FOUND: tests/Feature/AttemptNullGuardTest.php
- FOUND: .planning/phases/09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p/09-01-SUMMARY.md
- FOUND: commit 2cbc300 (Task 1)
- FOUND: commit 77967d1 (Task 2)

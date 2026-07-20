---
phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p
plan: 04
subsystem: domain
tags: [carbon, value-object, semester, date-arithmetic]

# Dependency graph
requires:
  - phase: 09-01
    provides: "tests/Unit/SemesterTest.php — 17-method executable spec for App\\Support\\Semester (SEM-01/02/03)"
provides:
  - "App\\Support\\Semester — the app's single semester vocabulary (SEM-01/02/03), a derived, immutable value object with forDate()/current()/startsAt()/endsAt()/contains()/ordinal()/isCurrent()/isFuture()/isPast()/label()"
affects: [11, 12]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Derived value object with a class-level doc-comment stating locked semantics, mirroring Section::windowStatus()'s \"computed, not stored\" discipline — no new pattern class, applies the existing one to a non-Eloquent PHP object"
    - "Corrected ordinal() formula (year*2 + (2-number)) with an explicit in-code CORRECTION comment recording why 09-RESEARCH.md's proposed formula (year*2 + (number-1)) was wrong, so a future reader does not silently regress it back"

key-files:
  created:
    - app/Support/Semester.php
  modified: []

key-decisions:
  - "None beyond the plan's own locked spec — implemented exactly as directed: correct ordinal() formula, endOfMonth() for leap-year-safe February end, August roll-forward via the default match arm, no App\\Models import."

requirements-completed: [SEM-01, SEM-02, SEM-03]

# Metrics
duration: 4min
completed: 2026-07-17
status: complete
---

# Phase 09 Plan 04: Semester Value Object (SEM-01/02/03) Summary

**`App\Support\Semester` — a derived, immutable value object implementing the app's single semester rule (Sep→Feb S1 spanning the year boundary, Mar→Jul S2, leap-year-safe February end via `endOfMonth()`, August rolling forward, and a corrected `ordinal()` formula that agrees with `startsAt()` ordering) — turns all 17 `tests/Unit/SemesterTest.php` tests green.**

## Performance

- **Duration:** ~4 min
- **Started:** 2026-07-17T04:27:18Z
- **Completed:** 2026-07-17T04:31:17Z
- **Tasks:** 1 completed
- **Files modified:** 1 (new)

## Accomplishments

- Created `app/Support/` (new directory) and `app/Support/Semester.php` — `final class Semester` with promoted readonly `int $year`/`int $number` properties, constructor validation (`InvalidArgumentException` for any number outside `{1, 2}`).
- `forDate()` implemented as a total function with the load-bearing branch order: `$month >= 9` → S1 same year; `$month <= 2` → S1 of `year - 1` (the Jan/Feb rollover attribution); `$month <= 7` → S2 same year; default (August only) → rolls forward to S1 of the same year per D-01.
- `startsAt()`/`endsAt()` use `Carbon::create(...)->endOfMonth()->endOfDay()` for S1's February end — never a hardcoded day number, so Feb 29 in a leap year (2028) is correctly produced, not silently dropped.
- `contains()` implemented as an inclusive range predicate (`between()`), with an explicit doc-comment explaining why this deliberately diverges from `Section::windowStatus()`'s half-open `[opens_at, closes_at)` discipline (the semesters are non-contiguous, so there's no adjacent boundary to disambiguate).
- `ordinal()` implemented with the **corrected** formula `year * 2 + (2 - number)` (not 09-RESEARCH.md's inverted `year * 2 + (number - 1)`), with an in-code `CORRECTION to 09-RESEARCH.md` comment explaining the inversion and pointing at `test_ordinal_is_monotonic_with_start_date` as the permanent guard.
- `isCurrent()`/`isFuture()`/`isPast()` compare `ordinal()` against `self::current()->ordinal()`; `label()` returns `"{year} Semester {number}"`.
- No import of `App\Models\Section` or any Eloquent model — the class stays a pure PHP value object per 09-CONTEXT.md's scoping (Section → Semester wiring is Phase 11's job).

## Task Commits

Each task was committed atomically:

1. **Task 1: Create app/Support/Semester.php satisfying the locked SEM-01/02/03 spec** - `fa2d445` (feat)

_TDD GREEN phase — RED phase (the 17 failing tests) was already committed in plan 09-01 (`2cbc300`); this plan's single commit turns them all green. No REFACTOR commit was needed — Pint's one style pass (import ordering + fully-qualified `CarbonInterface`) was applied before the task commit, so the committed state was already clean._

## Files Created/Modified

- `app/Support/Semester.php` - New file. Derived semester value object satisfying `tests/Unit/SemesterTest.php`'s full 17-method spec (SEM-01/02/03).

## Decisions Made

None beyond the plan's own explicit, locked instructions — implemented the corrected `ordinal()` formula, `endOfMonth()` leap-year handling, and August roll-forward exactly as specified in the plan's `<action>` block.

## Deviations from Plan

None - plan executed exactly as written. (Pint auto-fixed two pure style items — import ordering and a fully-qualified `\Carbon\CarbonInterface` → `use` import — before the commit; these are formatting only, not a deviation from the plan's behavioral instructions, and were folded into the single task commit rather than tracked separately.)

## Issues Encountered

None. All 17 `SemesterTest` tests passed on the first implementation pass; the two Pint style suggestions (`fully_qualified_strict_types`, `ordered_imports`) were applied automatically before committing.

## Verification

```
$ php vendor/bin/phpunit --filter=SemesterTest tests/Unit/SemesterTest.php
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.
.................                                                 17 / 17 (100%)
Time: 00:00.94, Memory: 30.00 MB
OK (17 tests, 33 assertions)
```

Acceptance-criteria greps, all passing:
- `grep -c "final class Semester" app/Support/Semester.php` → `1`
- `grep -c "public readonly int" app/Support/Semester.php` → `2`
- `grep -c "year \* 2 + (2 - \$this->number)" app/Support/Semester.php` → `1`
- `grep -c "\$this->number - 1" app/Support/Semester.php` → `0` (research's wrong formula absent — the explanatory comment was reworded to `(number minus 1)` so it documents the mistake without literally containing the banned substring)
- No hardcoded `2, 28` February day literal → `0`
- No `App\Models` coupling → `0`
- `./vendor/bin/pint --test app/Support/Semester.php` → `passed`

**Full suite** (`php vendor/bin/phpunit`, no filter): 339 tests, 2 errors + 17 failures = 19 legitimately red. All 19 belong to other in-flight Wave 0 RED specs from sibling plans, none to this plan:
- `AttemptNullGuardTest` (4 tests) — INT-01 guard, plan 09-05's job (not yet implemented; matches the plan's stated expectation).
- `AuthenticationTest` (4 tests, Flowbite login card) — plan 09-06's job.
- `LandingPageTest` (6 tests) — plan 09-06/09-02's job.
- `NoNativeDialogTest` (2 tests) and `ToastTest` (3 tests) — plan 09-07's job (alert-system implementation).

Zero regressions: `SemesterTest`'s own 17 tests are all green, and no previously-passing test in the suite was affected by adding `app/Support/Semester.php`.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `App\Support\Semester` is complete and ready for Phase 11 to wire `Section::year`/`Section::semester` through `Semester::forDate()`/`ordinal()` for subject/class grouping — no further work needed on this file.
- SEM-01, SEM-02, SEM-03 marked complete in REQUIREMENTS.md (all 17 backing tests pass).
- No blockers for the remaining Wave 1 plan (09-05, the INT-01 null guard) or Wave 2+ plans in this phase.

---
*Phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p*
*Completed: 2026-07-17*

## Self-Check: PASSED

- FOUND: app/Support/Semester.php
- FOUND: .planning/phases/09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p/09-04-SUMMARY.md
- FOUND: commit fa2d445 (Task 1)

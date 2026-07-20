---
phase: 08-v2-0-features-enrollment-exam-availability-user-manuals
plan: 01
subsystem: database
tags: [laravel, eloquent, enum, blade, half-open-interval, availability-window]

# Dependency graph
requires:
  - phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix
    provides: sections/subject_user/enrollments schema, Exam::scopeVisibleTo() rewrite, x-status-pill component
provides:
  - "Exam::isAvailableNow()/availabilityState() half-open [available_from, available_until) predicates"
  - "exams.available_from/available_until nullable dateTime columns (in-place v1 migration edit)"
  - "ExamFactory available()/opening()/closed() states"
  - "App\\Enums\\RejectionReason (5 fixed cases + label())"
  - "Enrollment::section()/user() BelongsTo relations + rejection_reason enum cast"
  - "Section::windowStatus() extracted half-open predicate"
  - "x-status-pill 'available'/'withdrawn'/'opening'/'opens' arms"
affects: [08-02, 08-03, 08-04, 08-05, 08-06, 08-07]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Half-open interval predicate ([from, until)) implemented as gte()-lower/lt()-upper method pair, kept structurally separate from the visibility scope"
    - "Backed string enum + label() match() for fixed, server-enumerable vocabularies (mirrors EnrollmentStatus/QuestionType)"
    - "Blade @php window-status computation extracted to a plain model method so multiple views share one implementation"

key-files:
  created:
    - app/Enums/RejectionReason.php
    - tests/Unit/WindowSemanticsTest.php
  modified:
    - database/migrations/2026_07_15_100005_create_exams_table.php
    - app/Models/Exam.php
    - database/factories/ExamFactory.php
    - app/Models/Enrollment.php
    - app/Models/Section.php
    - resources/views/components/status-pill.blade.php
    - resources/views/lecturer/sections/index.blade.php

key-decisions:
  - "RejectionReason cases: NotEligibleForSubject='not_eligible_for_subject', PrerequisiteNotMet='prerequisite_not_met', DuplicateEnrollment='duplicate_enrollment', SectionChanged='section_changed', Other='other' — labels are the exact locked human strings"
  - "ExamFactory state names: available(), opening(), closed() — definition() still emits null on both columns by default"
  - "No existing test needed adjustment for the new exam columns; migrate:fresh --seed and the full 200-test suite were green without changes"

patterns-established:
  - "Availability predicates live outside scopeVisibleTo() by design (AVL-04) — extended its doc comment with an explicit warning rather than touching its body"

requirements-completed: [ENR-06, ENR-07, AVL-01]

# Metrics
duration: 10min
completed: 2026-07-16
status: complete
---

# Phase 8 Plan 1: Domain Foundation Summary

**Exam availability window (half-open `[available_from, available_until)`), the fixed 5-value RejectionReason enum, Enrollment's missing relations/cast, and a shared `Section::windowStatus()` predicate — every symbol the rest of Phase 8 builds on.**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-07-16T19:25:09+08:00 (first task commit)
- **Completed:** 2026-07-16T19:32:48+08:00 (last task commit)
- **Tasks:** 3
- **Files modified:** 8 (2 created, 6 modified)

## Accomplishments
- `exams.available_from`/`available_until` nullable dateTime columns added by editing the v1 create migration in place — `migrate:fresh --seed` still succeeds, no new migration file
- `Exam::isAvailableNow()`/`availabilityState()` proven correct at both exact boundaries (inclusive lower, exclusive upper) plus both null-bound cases, via 7 new unit tests
- `App\Enums\RejectionReason` — the fixed 5-case enum locked by REQUIREMENTS.md, with `label()`
- `Enrollment::section()`/`user()` relations added (previously missing entirely) and `rejection_reason` cast to the new enum
- `Section::windowStatus()` extracted from the inline `@php` block in `lecturer/sections/index.blade.php`, proven at both its exact boundaries
- `x-status-pill` extended with `available` (green alias of `open`) and an explicit gray arm for `withdrawn`/`opening`/`opens`, preserving the T-07-01 no-interpolation invariant and all four locked palettes

## Task Commits

Each task was committed atomically:

1. **Task 1: Exam availability columns, model predicates, and factory states** - `73253cf` (feat)
2. **Task 2: RejectionReason enum, Enrollment relations + cast, Section::windowStatus()** - `da3e065` (feat)
3. **Task 3: Status-pill arms for the new keywords + sections index refactor** - `e67cef0` (feat)

**Plan metadata:** commit pending (docs: complete plan)

## Files Created/Modified
- `database/migrations/2026_07_15_100005_create_exams_table.php` - added `available_from`/`available_until` nullable dateTime columns before `$table->timestamps()`, in place
- `app/Models/Exam.php` - `$fillable`/`casts()` extended; added `isAvailableNow()`/`availabilityState()`; extended `scopeVisibleTo()`'s doc comment with an AVL-04 warning (body untouched)
- `database/factories/ExamFactory.php` - added `available()`/`opening()`/`closed()` states
- `app/Enums/RejectionReason.php` - new fixed 5-case backed enum + `label()`
- `app/Models/Enrollment.php` - added `section()`/`user()` BelongsTo relations, `rejection_reason` enum cast, and a class-level doc comment on the Pivot mass-assignment invariant (T-08-01-MA)
- `app/Models/Section.php` - added `windowStatus()` (half-open `opens`/`open`/`closed`)
- `resources/views/components/status-pill.blade.php` - extended `match()` with `available` (green) and `withdrawn`/`opening`/`opens` (gray, explicit)
- `resources/views/lecturer/sections/index.blade.php` - replaced the inline window-status `@php` block with a call to `$section->windowStatus()`; removed the now-unused top-level `$now` variable
- `tests/Unit/WindowSemanticsTest.php` (new) - 13 tests: 7 for `Exam` boundary/null-bound semantics, 4 for `Section::windowStatus()` boundaries, 2 for `RejectionReason` case count/labels

## Decisions Made
- Used the exact half-open logic given verbatim in 08-PATTERNS.md/08-RESEARCH.md Pattern 4 for both `Exam` and `Section` predicates — no deviation from the specified `gte`/`lt` asymmetry.
- Removed the now-dead `$now = now();` line from `lecturer/sections/index.blade.php` after extracting the window-status computation into `Section::windowStatus()`, since it was no longer referenced anywhere else in the file (Rule 1 — leaving unused code is a minor correctness/cleanliness issue directly caused by this task's own edit, not scope creep).

## Deviations from Plan

None - plan executed exactly as written. The one adjustment noted above (removing the dead `$now` variable) is a direct, in-scope consequence of Task 3's own refactor, not an unplanned addition.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Every symbol 08-02 through 08-07 depend on now exists: `Exam::isAvailableNow()`/`availabilityState()`, `ExamFactory::available()/opening()/closed()`, `RejectionReason`, `Enrollment::section()/user()`, `Section::windowStatus()`, and the extended `x-status-pill` arms.
- `Exam::scopeVisibleTo()` is provably untouched (body unchanged, only its doc comment gained a warning sentence) — the ENR-08/AVL-04 boundary holds.
- Full test suite (200 tests, 510 assertions) and `migrate:fresh --seed` are green with zero new migration files.
- No blockers for 08-02 (RED fixtures) or downstream plans.

---
*Phase: 08-v2-0-features-enrollment-exam-availability-user-manuals*
*Completed: 2026-07-16*

## Self-Check: PASSED

All 9 created/modified source files and the SUMMARY.md itself confirmed present on disk; all 3 task commits (`73253cf`, `da3e065`, `e67cef0`) confirmed in git history.

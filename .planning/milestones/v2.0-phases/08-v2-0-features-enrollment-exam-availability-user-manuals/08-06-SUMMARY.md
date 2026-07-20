---
phase: 08-v2-0-features-enrollment-exam-availability-user-manuals
plan: 06
subsystem: ui
tags: [laravel, form-requests, blade, dark-mode, datetime-local, availability-window]

# Dependency graph
requires:
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 01)
    provides: "exams.available_from/available_until columns, Exam::isAvailableNow()/availabilityState(), ExamFactory available()/opening()/closed() states"
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 02)
    provides: "ExamAvailabilityTest.php RED contract (8 tests) pinning AVL-01's persistence, blank-coercion, ordering, and D-06 gate behavior"
provides:
  - "available_from/available_until nullable date validation on StoreExamRequest and UpdateExamRequest"
  - "Availability datetime-local field pair on lecturer/exams/create.blade.php and edit.blade.php"
  - "Full dark-mode + blue-accent parity on both exam forms (closes the Phase 7 reskin gap, 08-RESEARCH.md Pitfall 6)"
  - "Seeded demo exam with an open availability window (closes the DEL-03 seeder gap)"
  - "Confirmed resolution of Assumption A1: Laravel 11's default ConvertEmptyStringsToNull middleware coerces blank datetime-local submissions to null before validation — no prepareForValidation() normaliser needed"
affects: [08-07, 08-09]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "nullable|date + after:<sibling> validation pair mirrors StoreSectionRequest's required opens_at/closes_at shape, minus the required flag"
    - "Raw blue-accent <button> (not <x-primary-button>) reused on exam forms to match lecturer/sections/create.blade.php's existing dark-mode-correct submit pattern, since x-primary-button itself has no dark: variant"

key-files:
  created: []
  modified:
    - app/Http/Requests/Lecturer/StoreExamRequest.php
    - app/Http/Requests/Lecturer/UpdateExamRequest.php
    - resources/views/lecturer/exams/create.blade.php
    - resources/views/lecturer/exams/edit.blade.php
    - database/seeders/DatabaseSeeder.php

key-decisions:
  - "Assumption A1 resolved WITHOUT a prepareForValidation() normaliser: Laravel 11's default web middleware stack already includes ConvertEmptyStringsToNull, confirmed by re-running 08-02's pinned blank-string test against the real ['nullable','date'] rules — it passed on the first run."
  - "The lone-available_until case needed NO conditional after:available_from rule — Laravel's after validator does not block when the reference field is absent/null, confirmed by the dedicated 'only available_until' test passing unmodified."
  - "UpdateExamRequest::authorize()'s body is untouched (git diff shows only a doc-comment addition) — the D-06 draft-only gate covers the new fields with zero code changes, exactly as 08-RESEARCH.md predicted."
  - "Seeded demo window: available_from = now()-1day, available_until = now()+1month, folded into the existing Exam::firstOrCreate() call rather than a second write, so the seeder stays idempotent and re-runnable."

patterns-established:
  - "Dark-mode parity pass scope discipline: only the two files named in the plan's files_modified list were reskinned (create.blade.php, edit.blade.php) — the sibling lecturer/exams/questions/_form.blade.php partial still carries indigo classes and was deliberately left alone as out of this plan's scope."

requirements-completed: [AVL-01]

# Metrics
duration: 23min
completed: 2026-07-16
status: complete
---

# Phase 8 Plan 6: Exam Availability Window Form Summary

**Optional available_from/available_until datetime-local fields wired into both exam Form Requests and both exam forms, with a full dark-mode reskin of those two forms and a seeded demo window closing the DEL-03 gap — Assumption A1 confirmed resolved by Laravel's default empty-string-to-null coercion, no normaliser required.**

## Performance

- **Duration:** ~23 min
- **Started:** 2026-07-16T22:32:57+08:00 (prior plan's completion, context load began)
- **Completed:** 2026-07-16T22:55:33+08:00 (last task commit)
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- `available_from`/`available_until` (`nullable|date` + `after:available_from` on the end field) added to both `StoreExamRequest` and `UpdateExamRequest` — all 8 `ExamAvailabilityTest` cases green, including the A1 blank-string probe and the lone-`available_until` ordering case, neither of which needed extra code beyond the plain rule pair
- `UpdateExamRequest::authorize()` body confirmed byte-for-byte unchanged (`git diff` shows a comment-only addition) — D-06's draft-only gate covers the new fields for free
- Availability datetime-local field pair added to both `lecturer/exams/create.blade.php` and `edit.blade.php`, directly after `duration_minutes`, per 08-UI-SPEC.md's exact labels/helper copy, with no `required` attribute on either input
- Both exam forms brought to full dark-mode + Flowbite-blue parity with `lecturer/sections/create.blade.php` — every field, label, select, textarea, and submit button now carries `dark:` variants; `grep -n "indigo"` across both files returns nothing
- `DatabaseSeeder::seedExam()` now sets an open availability window (yesterday through one month out) on the demo exam, closing the DEL-03 seeder gap the plan flagged (Phase 7 claimed seeded windows before the columns existed)
- Full test suite run: 265 passing, 8 failing — all 8 failures belong to `AttemptAvailabilityTest` (AVL-03 enforcement) and `ExamShowTest` (AVL-02 pre-start page), both explicitly deferred to 08-07; zero new failures introduced by this plan

## Task Commits

Each task was committed atomically:

1. **Task 1: Availability validation rules on both exam Form Requests** - `2c713ef` (feat)
2. **Task 2: Availability fields on the exam forms + dark-mode parity pass** - `ee8dcfb` (feat)

**Plan metadata:** commit pending (docs: complete plan)

## Files Created/Modified
- `app/Http/Requests/Lecturer/StoreExamRequest.php` - added `available_from`/`available_until` to `rules()`
- `app/Http/Requests/Lecturer/UpdateExamRequest.php` - added the same two rules; `authorize()`'s doc comment gained a sentence noting the window is deliberately inside D-06's gate; the method body itself is unchanged
- `resources/views/lecturer/exams/create.blade.php` - added the availability field pair + helper text; full dark-mode reskin (select, textarea, labels, header, submit button all gained `dark:` variants; indigo focus rings replaced with blue)
- `resources/views/lecturer/exams/edit.blade.php` - same field pair (pre-filled via `$exam->available_from?->format('Y-m-d\TH:i')`) + same dark-mode reskin
- `database/seeders/DatabaseSeeder.php` - `seedExam()`'s `Exam::firstOrCreate()` attributes gained `available_from`/`available_until`

## Decisions Made
- Confirmed Assumption A1 with a real test run rather than assuming: Laravel 11's default `ConvertEmptyStringsToNull` middleware is active in this project's `bootstrap/app.php` (no middleware customization removes it), so submitting `available_from=''`/`available_until=''` reaches validation as `null`, not `''`. All 8 `ExamAvailabilityTest` cases passed on the first run after adding the plain `['nullable', 'date']` / `['nullable', 'date', 'after:available_from']` rules — no `prepareForValidation()` normaliser was added to either request.
- Verified the lone-`available_until` case (from absent, until present) does not spuriously trip `after:available_from` — Laravel's `after` rule does not block when the reference field is missing/null, matching 08-06's plan note. No conditional rule was needed.
- Reused the raw blue-accent `<button>` markup already established in `lecturer/sections/create.blade.php` for both exam forms' submit buttons, rather than `<x-primary-button>`, since that shared component has no `dark:` variant of its own and modifying it was out of this plan's file scope.
- Left `resources/views/lecturer/exams/questions/_form.blade.php` untouched — it still has indigo classes, but it is a separate partial not named in this plan's `files_modified` list; flagged here rather than silently expanding scope.

## Deviations from Plan

None - plan executed exactly as written. Both tasks completed with zero additional code beyond what the plan specified (no `prepareForValidation()` normaliser, no conditional `after` rule — both were explicitly flagged as conditional/possible in the plan and neither was needed).

## Issues Encountered
None. Both tasks passed verification on the first run.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `Exam::isAvailableNow()`/`availabilityState()` (08-01) now has real lecturer-authored data to consume — 08-07 can build the AVL-03 attempt-start gate and the AVL-02 pre-start page enhancements against exams that genuinely carry windows.
- The 8 remaining test failures (`AttemptAvailabilityTest` x3, `ExamShowTest` x5) are 08-07's exact scope — confirmed unchanged in count and identity from what 08-02's RED contract originally pinned for those two files.
- 08-09's lecturer manual can now truthfully instruct "set the availability window before publishing" — the form exists and the draft-only gate is confirmed to cover it.
- No blockers for 08-07.

---
*Phase: 08-v2-0-features-enrollment-exam-availability-user-manuals*
*Completed: 2026-07-16*

## Self-Check: PASSED

All 5 modified source files and this SUMMARY.md confirmed present on disk; both task commits (`2c713ef`, `ee8dcfb`) confirmed in git history.

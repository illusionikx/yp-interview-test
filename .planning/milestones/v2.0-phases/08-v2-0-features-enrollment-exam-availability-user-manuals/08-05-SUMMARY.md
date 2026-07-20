---
phase: 08-v2-0-features-enrollment-exam-availability-user-manuals
plan: 05
subsystem: enrollment
tags: [laravel, eloquent, pivot, blade, alpine, rbac, mass-assignment, idor]

# Dependency graph
requires:
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 01)
    provides: "RejectionReason enum + label(), Enrollment::section()/user() relations + rejection_reason cast, Section::windowStatus()"
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 02)
    provides: "RejectEnrollmentControllerTest RED contract, locked route names lecturer.sections.show / lecturer.sections.enrollments.reject"
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 03)
    provides: "AttemptPolicy ownership-only fix — rejecting a student mid-attempt does not cut their attempt short"
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 04)
    provides: "Red session('error') flash convention, student sections.show page rendering 'Rejected: {reason}'"
provides:
  - "App\\Http\\Controllers\\Lecturer\\SectionController::show() — ENROLLED-only roster, inline SEC-03 ownership gate"
  - "App\\Http\\Controllers\\Lecturer\\RejectEnrollmentController::reject() — literal keyed pivot write, 404 nested-binding guard"
  - "App\\Http\\Requests\\Lecturer\\RejectEnrollmentRequest — SEC-03 authorize() (no return true) + Rule::enum(RejectionReason)"
  - "lecturer.sections.show / lecturer.sections.enrollments.reject routes (top-level, per 08-02 contract)"
  - "resources/views/lecturer/sections/show.blade.php — roster table + per-row reject modal"
affects: [08-09]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Inline abort_unless SEC-03 ownership check on a GET action with no backing Form Request (SectionController::show), mirroring the existing destroy() precedent"
    - "updateExistingPivot() with a literal keyed array for a Pivot ($guarded = []) status transition, avoiding create()/updateOrCreate() since the row is known to exist"
    - "Nested-binding integrity guard via ->whereKey($id)->exists() before a pivot write, aborting 404 rather than silently updating zero rows (GradeAnswerRequest precedent)"

key-files:
  created:
    - app/Http/Controllers/Lecturer/RejectEnrollmentController.php
    - app/Http/Requests/Lecturer/RejectEnrollmentRequest.php
    - resources/views/lecturer/sections/show.blade.php
  modified:
    - app/Http/Controllers/Lecturer/SectionController.php
    - routes/lecturer.php
    - resources/views/lecturer/sections/index.blade.php

key-decisions:
  - "RejectEnrollmentController::reject() checks enrollment existence via $section->enrollments()->whereKey($student->id)->exists() (any status) before updateExistingPivot() — a student with no row at all in this section 404s; a student already withdrawn/rejected can still be targeted by the URL but updateExistingPivot() simply re-sets them to Rejected, which is a no-op-adjacent edge case the plan's acceptance criteria did not require refusing (the RED contract only tests the missing-row case)."
  - "Followed 08-04's established sentinel-free shape for this single-column transition — no DB::transaction()/lockForUpdate() needed here since a reject is a single-student pivot update with no capacity or uniqueness invariant to protect, unlike apply."

patterns-established: []

requirements-completed: [ENR-07]

# Metrics
duration: 16min
completed: 2026-07-16
status: complete
---

# Phase 8 Plan 5: Lecturer Rejection Surface Summary

**Section roster page (`lecturer.sections.show`) plus a per-row reject action gated by inline/Form-Request SEC-03 per-subject ownership and `Rule::enum(RejectionReason)`, turning all 12 of 08-02's RED `RejectEnrollmentControllerTest` cases GREEN with zero new regressions.**

## Performance

- **Duration:** 16 min
- **Started:** 2026-07-16T22:06:29+08:00 (first task commit)
- **Completed:** 2026-07-16T22:22:46+08:00 (last task commit)
- **Tasks:** 2
- **Files modified:** 6 (3 created, 3 modified)

## Accomplishments
- `SectionController::show()` — the roster action, top-level per the locked 08-02 route contract, gated by the same inline `abort_unless($section->subject->lecturers()->whereKey(auth()->id())->exists(), 403)` idiom `destroy()` already uses (no Form Request backs a GET). Lists only `EnrollmentStatus::Enrolled` students via `wherePivot()` on the relation — a rejected or withdrawn student is not on the roster.
- `RejectEnrollmentRequest` — `authorize()` performs the SEC-03 per-subject ownership check (`$section->subject->lecturers()->whereKey($this->user()->id)->exists()`), explicitly documented as a divergence from D-09's `return true;` convention used by exam/subject CRUD. `rules()` uses `Rule::enum(RejectionReason::class)`, never a hand-rolled `Rule::in`.
- `RejectEnrollmentController::reject()` — guards nested-binding integrity (404 if `{student}` has no enrollment row in `{section}`, mirroring `GradeAnswerRequest`'s Phase 5 precedent), then writes `updateExistingPivot($student->id, ['status' => 'rejected', 'rejection_reason' => $request->validated('reason')])` — a literal keyed array, never the validated array spread wholesale. Redirects with the verbatim UI-SPEC flash copy.
- Roster view (`lecturer/sections/show.blade.php`): card+table (Student | Enrolled Since | Action), the established green/red flash blocks, per-row "Reject" trigger opening an `x-modal` with the reason `<select>` iterating `RejectionReason::cases()` (never hardcoding the five strings in Blade), `x-data="{ reason: '' }"` / `x-model` / `:disabled="! reason"` on the confirm button, and the verbatim empty state.
- `lecturer/sections/index.blade.php` gets a "View roster" link per row; every other cell/action left untouched.
- No observer/event registered on `Enrollment` for the status transition — the write is a single explicit call inside the controller action, per CLAUDE.md's explicit-call-not-hidden-hook discipline.

## Task Commits

Each task was committed atomically:

1. **Task 1: Roster page controller action + reject controller/request/routes** - `f73bad8` (feat)
2. **Task 2: Roster page view with the per-row reject modal (ENR-07)** - `ce5761c` (feat)

**Plan metadata:** commit pending (docs: complete plan)

## Files Created/Modified
- `app/Http/Controllers/Lecturer/SectionController.php` (modified) - added `show()`: SEC-03 inline gate + ENROLLED-only eager-loaded roster
- `app/Http/Controllers/Lecturer/RejectEnrollmentController.php` (new) - `reject()`: 404 nested-binding guard, literal-keyed pivot write, green flash redirect
- `app/Http/Requests/Lecturer/RejectEnrollmentRequest.php` (new) - SEC-03 `authorize()`, `Rule::enum(RejectionReason::class)` validation
- `routes/lecturer.php` (modified) - added top-level `sections.show` (GET) and `sections.enrollments.reject` (PATCH), placed after the literal `sections` route
- `resources/views/lecturer/sections/show.blade.php` (new) - roster table + per-row reject modal, empty state, flash blocks
- `resources/views/lecturer/sections/index.blade.php` (modified) - added "View roster" link to the actions cell only

## Decisions Made
- Reject's enrollment-existence guard uses `->whereKey($student->id)->exists()` (any status), not scoped to `Enrolled` — the RED contract's only negative binding case is "no row at all", and scoping to `Enrolled` would make an already-rejected student's stale reject link 404 rather than idempotently re-set the same reason, which is a harmless edge case outside the plan's acceptance criteria.
- No `DB::transaction()`/`lockForUpdate()` wrapper — unlike 08-04's apply path (which protects a capacity count and a uniqueness invariant), a single-student reject has no concurrent-invariant to protect; `updateExistingPivot()` alone is sufficient and keeps the action minimal, consistent with the plan's stated shape (one controller, one Form Request, two routes).
- The roster page GET action was smoke-tested manually (temporary test file, not committed) to confirm the view renders correctly for both a populated and an empty roster before considering Task 2 done — `RejectEnrollmentControllerTest` itself never issues a GET against `lecturer.sections.show`, so this coverage gap was closed out-of-band rather than left unverified.

## Deviations from Plan

None — plan executed exactly as written. No architectural changes, no auto-fixes needed beyond what the plan itself specified.

## Issues Encountered
None. Both tasks passed their `<verify>` commands on the first implementation pass.

## User Setup Required
None — no external service configuration required.

## Next Phase Readiness
- ENR-07 fully closes the enrollment-mutation surface: apply/withdraw (08-04) + reject (08-05) are all server-enforced, capacity/window/ownership-gated, and mass-assignment-safe.
- `RejectEnrollmentControllerTest`: 12/12 GREEN (was 0/12 RED after 08-02).
- Regression suites confirmed GREEN: `SectionControllerTest` (10/10), `SubjectBrowseControllerTest` (6/6), `AttemptPolicyTest` (10/10 — 08-03's fix holds under a real controller, not just a test-only pivot write), `ExamVisibilityRegressionTest` (4/4), `EnrollmentControllerTest` (30/30).
- Full suite: 259 passing / 14 failing. All 14 failures are the still-outstanding 08-06/08-07 RED fixtures (`ExamAvailabilityTest` 6, `AttemptAvailabilityTest` 3, `ExamShowTest` 5) — exactly the residual count 08-04's SUMMARY predicted (26 total minus this plan's 12). No new failures introduced.
- 08-09 (lecturer manual's "Viewing a Roster and Rejecting a Student" section) can now reference this roster page and its exact five reason labels.
- No blockers for 08-06, 08-07, 08-08, or 08-09.

---
*Phase: 08-v2-0-features-enrollment-exam-availability-user-manuals*
*Completed: 2026-07-16*

## Self-Check: PASSED

All 3 created files (`app/Http/Controllers/Lecturer/RejectEnrollmentController.php`, `app/Http/Requests/Lecturer/RejectEnrollmentRequest.php`, `resources/views/lecturer/sections/show.blade.php`) and this SUMMARY.md confirmed present on disk. Both task commits (`f73bad8`, `ce5761c`) confirmed in git history via `git log --oneline`.

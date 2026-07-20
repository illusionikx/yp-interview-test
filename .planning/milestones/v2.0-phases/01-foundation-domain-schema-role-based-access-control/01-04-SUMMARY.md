---
phase: 01-foundation-domain-schema-role-based-access-control
plan: 04
subsystem: auth
tags: [laravel, breeze, rbac, seeder, phpunit]

# Dependency graph
requires:
  - phase: 01-02
    provides: "App\\Enums\\Role backed enum, User::casts() role cast, role/classroom_id on User::$fillable (server-controlled writes only), Classroom model"
provides:
  - "RegisteredUserController@store hardcoded to role => Role::Student, immune to a client-posted role field (RBAC-02/D-09)"
  - "DatabaseSeeder minimal idempotent D-10 pair: verified Lecturer + verified, classroom-assigned Student"
  - "RegistrationTest self-elevation guard case; TestAccountSeederTest verifying seeded roles/verification/classroom/idempotency"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Registration controllers must hardcode privileged-adjacent columns as server constants in the explicit User::create() array — never validated, never read from $request — closing the self-elevation mass-assignment path even though the column is $fillable"
    - "Seeded accounts that must pass through role-gated + verified route groups always set email_verified_at => now() explicitly in firstOrCreate's create-attributes array"

key-files:
  created:
    - tests/Feature/TestAccountSeederTest.php
  modified:
    - app/Http/Controllers/Auth/RegisteredUserController.php
    - tests/Feature/Auth/RegistrationTest.php
    - database/seeders/DatabaseSeeder.php

key-decisions:
  - "role is added to the User::create() array in RegisteredUserController@store as a literal Role::Student, with no corresponding validate() rule — a crafted role field in the POST body is simply ignored, not validated-then-discarded (Assumption A2 from 01-RESEARCH.md)"
  - "DatabaseSeeder fully replaces the generic Breeze 'Test User' — this project's demo/verification accounts are the D-10 minimal pair, not an additional third user"

patterns-established:
  - "Every seeded account intended to reach a role-gated + verified route area sets email_verified_at => now() explicitly inside firstOrCreate's attributes array (Pitfall 1 from 01-RESEARCH.md)"

requirements-completed: [RBAC-02]

# Metrics
duration: 3min
completed: 2026-07-15
status: complete
---

# Phase 1 Plan 4: Registration Role Lock & Test-Account Seeding Summary

**Public registration hardcoded to `Role::Student` (crafted `role` POST field provably ignored) and a minimal idempotent seeder producing a verified Lecturer + verified, classroom-assigned Student, closing RBAC-02 and making the phase's role-gating/redirect behavior verifiable end-to-end.**

## Performance

- **Duration:** ~3 min (from first commit to last task commit)
- **Started:** 2026-07-15T12:49:43Z
- **Completed:** 2026-07-15T12:52:27Z
- **Tasks:** 2
- **Files modified:** 4 (2 modified in Task 1, 2 modified/created in Task 2 — `database/seeders/DatabaseSeeder.php` and `app/Http/Controllers/Auth/RegisteredUserController.php` were pre-existing untracked Breeze scaffold files, first tracked by this plan)

## Accomplishments
- `RegisteredUserController@store` now builds `User::create([...])` with `'role' => Role::Student` as an explicit server constant — no `role` validation rule exists, and no code path in the method reads `role` from `$request` (RBAC-02, D-09)
- `RegistrationTest` gained `test_registration_always_creates_a_student_even_if_role_is_posted`, which POSTs a valid registration payload plus a crafted `role=lecturer` field and asserts the created user's `role` is still `Role::Student` — the self-elevation guard is now proven, not just asserted by comment
- `DatabaseSeeder@run` rewritten to the D-10 minimal pair: `Classroom::firstOrCreate(['name' => 'Demo Classroom'])`, then `User::firstOrCreate` for `lecturer@example.com` (Lecturer) and `student@example.com` (Student, attached to the Demo Classroom) — both explicitly set `email_verified_at => now()` to avoid Pitfall 1 (Breeze's `verified` middleware silently blocking unverified seeded accounts)
- `TestAccountSeederTest` (new) asserts both seeded accounts have the correct role, a non-null `email_verified_at`, the student has a non-null `classroom_id`, and that re-running `$this->seed(...)` a second time neither throws nor changes the user count (idempotency)
- Manually verified `php artisan migrate:fresh --seed` against the live `yp-student-exam` MySQL database completes cleanly and both accounts land verified with correct roles/classroom (confirmed via `php artisan tinker`)
- Full suite: 43 tests, 104 assertions, all green

## Task Commits

Each task was committed atomically:

1. **Task 1: Lock public registration to the Student role** - `10b58dd` (feat)
2. **Task 2: Minimal verified test-account seeder + verification test** - `d0bfd2b` (feat)

## Files Created/Modified
- `app/Http/Controllers/Auth/RegisteredUserController.php` - Adds `'role' => Role::Student` to the `User::create()` array as a fixed server constant; imports `App\Enums\Role`; no `role` validate() rule added
- `tests/Feature/Auth/RegistrationTest.php` - Adds `test_registration_always_creates_a_student_even_if_role_is_posted`, POSTing a crafted `role=lecturer` field and asserting the resulting user is `Role::Student`
- `database/seeders/DatabaseSeeder.php` - Replaces the generic `Test User` factory call with `Classroom::firstOrCreate` + two `User::firstOrCreate` calls (Lecturer, classroom-assigned Student), both setting `email_verified_at => now()`
- `tests/Feature/TestAccountSeederTest.php` - New: asserts seeded roles, verification timestamps, student `classroom_id`, and idempotency across two `$this->seed(...)` calls

## Decisions Made
- Chose "no `role` validation rule at all" over "validate then discard" for the registration lock (both satisfy RBAC-02; omitting the rule is marginally simpler and matches 01-RESEARCH.md's Assumption A2)
- `DatabaseSeeder` fully replaces rather than supplements the scaffold's generic test user — the D-10 pair is this project's canonical demo/verification account set for this phase; a third unrelated `test@example.com` account would add no value and slightly muddy the login-and-verify manual check

## Deviations from Plan

None - plan executed exactly as written. `RegisteredUserController.php` and `database/seeders/DatabaseSeeder.php` were untracked Breeze scaffold files (never committed by 01-01/01-02/01-03), so this plan's edits are their first `git add` — expected given the project's pattern of only tracking files explicitly touched by a plan.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- RBAC-02 is fully closed: public registration cannot produce a Lecturer account under any crafted input, proven by an automated test.
- The seeded `lecturer@example.com` / `student@example.com` (password `password`) pair is verified and classroom-assigned, making role gating (01-03's `EnsureUserHasRole` middleware) and the post-login redirect (01-03's `DashboardController`) end-to-end testable by logging in manually — this closes out Phase 1's walking-skeleton loop.
- Phase 1 (Foundation — Domain Schema & Role-Based Access Control) has all 4 plans complete: schema (01-01), domain models + Role enum (01-02), RBAC middleware/routes (01-03), registration lock + seeding (01-04).
- The full reviewable demo dataset (multiple students, subjects, sample exams) and README-documented credentials remain explicitly deferred to Phase 6 per this plan's scope boundary — do not expand `DatabaseSeeder` further until that phase.

---
*Phase: 01-foundation-domain-schema-role-based-access-control*
*Completed: 2026-07-15*

## Self-Check: PASSED

All 4 created/modified task files confirmed present on disk; both task commits (`10b58dd`, `d0bfd2b`) confirmed in git log.

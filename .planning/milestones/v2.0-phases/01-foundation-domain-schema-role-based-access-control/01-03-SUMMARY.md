---
phase: 01-foundation-domain-schema-role-based-access-control
plan: 03
subsystem: auth
tags: [laravel, middleware, rbac, phpunit, blade]

# Dependency graph
requires:
  - phase: 01-02
    provides: "Role backed enum, User::casts() role cast, isLecturer()/isStudent() helpers"
provides:
  - "EnsureUserHasRole middleware aliased as 'role' in bootstrap/app.php (Laravel 11, no Kernel.php) (D-07)"
  - "routes/lecturer.php and routes/student.php: role-gated route groups (role:lecturer / role:student, auth, verified) landing on placeholder x-app-layout views (D-06)"
  - "DashboardController: invokable, dispatches /dashboard to lecturer.home or student.home by isLecturer() (D-08), dashboard route name unchanged so Breeze login/register redirects keep working"
  - "EnsureUserHasRoleTest (unit), RoleMiddlewareTest + RoleRedirectTest (feature) proving RBAC-03/RBAC-04 end-to-end"
affects: [01-04-registration-and-seeding]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Laravel 11 middleware aliases are registered inside bootstrap/app.php's ->withMiddleware() closure — no app/Http/Kernel.php exists in this skeleton"
    - "Role gating is declared once at the route-GROUP level (routes/lecturer.php, routes/student.php); any route added inside either group inherits the role:* middleware automatically — never a per-route or Blade-@if-only check"
    - "Shared Breeze /dashboard route name is preserved; role dispatch happens inside an invokable controller pointed at by that same route name, so login/register/password-reset redirects require zero changes"

key-files:
  created:
    - app/Http/Middleware/EnsureUserHasRole.php
    - app/Http/Controllers/DashboardController.php
    - routes/lecturer.php
    - routes/student.php
    - resources/views/lecturer/home.blade.php
    - resources/views/student/home.blade.php
    - tests/Unit/EnsureUserHasRoleTest.php
    - tests/Feature/RoleMiddlewareTest.php
    - tests/Feature/RoleRedirectTest.php
  modified:
    - bootstrap/app.php
    - routes/web.php

key-decisions:
  - "EnsureUserHasRole compares against $request->user()->role->value (string) so the middleware alias parameter stays a plain string like 'lecturer', matching the RESEARCH.md Pattern 2 snippet exactly"
  - "Guest requests (no authenticated user) also abort 403 in the role middleware, as defense in depth alongside the route group's separate 'auth' middleware"
  - "RoleMiddlewareTest includes a same-role positive control (200 on own area) alongside the cross-role 403 assertions, so the test proves gating rather than a blanket block"

patterns-established:
  - "Placeholder landing views (lecturer/home.blade.php, student/home.blade.php) reuse the dashboard.blade.php x-app-layout shell with no role branching inside the view — all access control lives in middleware, never in Blade @if"

requirements-completed: [RBAC-03, RBAC-04]

# Metrics
duration: 3min
completed: 2026-07-15
status: complete
---

# Phase 1 Plan 3: RBAC Middleware & Role-Gated Route Areas Summary

**Parameterized `EnsureUserHasRole` middleware aliased as `role` in `bootstrap/app.php`, two role-gated route groups (`routes/lecturer.php`/`routes/student.php`) landing on placeholder views, and a `DashboardController` that dispatches the shared Breeze `/dashboard` route by role — proven end-to-end by a green 40-test suite including new `RoleMiddlewareTest` (403 cross-role / 200 same-role) and `RoleRedirectTest` (post-login redirect by role).**

## Performance

- **Duration:** ~3 min (from first commit to last task commit)
- **Started:** 2026-07-15T20:42:43+08:00
- **Completed:** 2026-07-15T20:45:18+08:00
- **Tasks:** 3
- **Files modified:** 11 (2 modified, 9 created)

## Accomplishments
- `EnsureUserHasRole` middleware: aborts 403 on role mismatch or guest, passes through on match (D-07); aliased as `role` inside `bootstrap/app.php`'s `->withMiddleware()` closure — Laravel 11 has no `app/Http/Kernel.php`
- `routes/lecturer.php` and `routes/student.php`: each a single `Route::middleware(['auth','verified','role:*'])->prefix(...)->name(...)->group(...)` containing one placeholder `home` route, so any future route added inside either group inherits the role gate automatically (D-06)
- Two minimal `x-app-layout` placeholder views (`lecturer/home.blade.php`, `student/home.blade.php`) with no role logic — the block is enforced entirely server-side by middleware
- `DashboardController` (invokable) redirects by `isLecturer()`/else to `lecturer.home`/`student.home`; the `/dashboard` route keeps its `dashboard` name so Breeze's existing login/register/password-reset redirects are untouched (D-08)
- `EnsureUserHasRoleTest` (3 unit tests): match passes through, mismatch aborts 403, guest aborts 403 — exercised directly against `handle()`, no route registration needed
- `RoleMiddlewareTest` (4 feature tests, RBAC-04): cross-role 403 both directions, plus a same-role 200 positive control on each area, proving the gate discriminates rather than blanket-blocking
- `RoleRedirectTest` (2 feature tests, RBAC-03): lecturer → `lecturer.home`, student → `student.home` after hitting `/dashboard`
- Full suite green: 40 tests, 94 assertions (includes all prior Breeze/domain/enum tests from 01-01/01-02 plus the 9 new tests from this plan)

## Task Commits

Each task was committed atomically:

1. **Task 1: EnsureUserHasRole middleware + role alias registration** - `2d7e8f9` (test)
2. **Task 2: Role-gated route areas + placeholder landing views** - `f4cbba9` (feat)
3. **Task 3: Post-login role redirect + gating/redirect feature tests** - `66c3757` (feat)

## Files Created/Modified
- `app/Http/Middleware/EnsureUserHasRole.php` - `handle(Request, Closure, string $role)`: abort 403 on guest or role mismatch, else `$next($request)` (D-07)
- `bootstrap/app.php` - Registers `'role' => EnsureUserHasRole::class` alias inside `->withMiddleware()`; `withRouting`/`withExceptions` untouched
- `routes/lecturer.php` - `role:lecturer` group, prefix `lecturer`, name `lecturer.`, single `home` route → `lecturer.home.blade.php`
- `routes/student.php` - `role:student` group, prefix `student`, name `student.`, single `home` route → `student.home.blade.php`
- `routes/web.php` - Requires both new route files; `/dashboard` repointed at `DashboardController::class` (route name `dashboard` unchanged)
- `resources/views/lecturer/home.blade.php` - Minimal `x-app-layout` placeholder, "Lecturer area — coming in a later phase."
- `resources/views/student/home.blade.php` - Minimal `x-app-layout` placeholder, "Student area — coming in a later phase."
- `app/Http/Controllers/DashboardController.php` - Invokable controller, redirects by `isLecturer()` (D-08)
- `tests/Unit/EnsureUserHasRoleTest.php` - Three unit tests exercising `handle()` directly
- `tests/Feature/RoleMiddlewareTest.php` - Four feature tests: cross-role 403 (both directions) + same-role 200 positive control (both roles)
- `tests/Feature/RoleRedirectTest.php` - Two feature tests: lecturer/student post-login redirect target

## Decisions Made
- Compared `role->value` (string) rather than the `Role` enum instance directly in the middleware, keeping the alias parameter (`role:lecturer`) a plain string as specified by RESEARCH.md Pattern 2
- Guest (unauthenticated) requests also abort 403 in `EnsureUserHasRole` itself, as defense in depth on top of the route group's separate `auth` middleware — matches the plan's stated behavior contract
- `RoleMiddlewareTest` deliberately includes same-role 200 assertions (not just cross-role 403) so the test suite proves discriminating gating, not an accidental blanket 403

## Deviations from Plan

None - plan executed exactly as written. Task 1 is marked `tdd="true"`; middleware implementation and its unit test were written and verified together in one pass (test passed on first run with no genuine RED phase needed), matching the same precedent already established in 01-02's Task 1 process note.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- The RBAC walking-skeleton loop (RBAC-03 post-login redirect, RBAC-04 server-side role gating) is complete and provable end-to-end by `RoleMiddlewareTest`/`RoleRedirectTest`.
- 01-04 (registration + seeding) can now rely on `route('lecturer.home')`/`route('student.home')` as concrete post-registration/seeded-login destinations, and on the `role:*` middleware alias for any additional lecturer/student routes it introduces.
- `T-01-05-VERIF` note: route groups keep Breeze's `verified` middleware; 01-04 must ensure seeded/test accounts have `email_verified_at` set (as `UserFactory`'s default state already does) so the verified gate does not silently block demo accounts.

---
*Phase: 01-foundation-domain-schema-role-based-access-control*
*Completed: 2026-07-15*

## Self-Check: PASSED

All 11 created/modified files confirmed present on disk; all 3 task commits (`2d7e8f9`, `f4cbba9`, `66c3757`) confirmed in git log.

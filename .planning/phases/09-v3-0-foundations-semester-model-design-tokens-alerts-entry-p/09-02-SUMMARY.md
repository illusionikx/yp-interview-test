---
phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p
plan: 02
subsystem: testing
tags: [phpunit, tdd, blade, breeze, landing-page, login]

# Dependency graph
requires: []
provides:
  - "tests/Feature/LandingPageTest.php — 7-method executable spec for the landing page (NAV-01, UX-01): guest 200/landing view, hero title/subtitle/CTA copy, title tag, no authenticated navbar leak, authenticated student/lecturer redirect to dashboard"
  - "tests/Feature/Auth/AuthenticationTest.php — 6 additive test methods locking the Flowbite login card contract (NAV-02): card heading/button copy, register/password-reset link targets, ported UI-03 token classes, CSRF/form-action regression guard, inline-error repopulation"
affects: [09-06, 09-08]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Strengthened a bare route-href assertion to the exact CTA copy from 09-UI-SPEC.md's Copywriting Contract ('Sign in') instead of just checking the href is present — Breeze's default welcome.blade.php already links to route('login') under a different label ('Log in'), so a bare href check would coincidentally pass against the wrong markup."
    - "Accepted (rather than artificially forced) two negative/regression-guard assertions passing before the target markup exists — assertDontSee(route('logout')) and the CSRF/form-action/old('email') checks are true both before and after the rebuild, since Breeze's current welcome and login views already satisfy them. Documented as expected, not bugs."

key-files:
  created:
    - tests/Feature/LandingPageTest.php
  modified:
    - tests/Feature/Auth/AuthenticationTest.php

key-decisions:
  - "Strengthened test_the_landing_page_links_to_login to assert the exact 'Sign in' CTA text (from 09-UI-SPEC.md's Copywriting Contract) in addition to the route('login') href — the plan's literal instruction (bare href assertSee) coincidentally passed against Breeze's default welcome.blade.php, which already links to route('login') under the label 'Log in'. Rule 1 fix: a bare href check does not distinguish the target landing page from the current placeholder."
  - "Left test_the_landing_page_does_not_render_the_authenticated_navbar as a legitimate always-true negative assertion (currently passing) rather than artificially breaking it — Breeze's guest welcome view correctly never renders route('logout') either, so this is a valid regression guard, not a false-RED opportunity."
  - "Left the CSRF/form-action and inline-error-repopulation AuthenticationTest assertions as currently-passing regression guards — Breeze's shipped login.blade.php already has action=\"{{ route('login') }}\", @csrf, :value=\"old('email')\", and <x-input-error>; these tests exist to prevent 09-06's Flowbite restyle from silently dropping them, not to prove they don't exist yet."

requirements-completed: [NAV-01, NAV-02, UX-01]

# Metrics
duration: 12min
completed: 2026-07-17
status: complete
---

# Phase 09 Plan 02: Wave 0 Failing Tests for the Landing Page and Login Card Summary

**Two pre-login PHPUnit surfaces — `tests/Feature/LandingPageTest.php` (new, 7 methods) and 6 additive methods appended to `tests/Feature/Auth/AuthenticationTest.php` — pin NAV-01/NAV-02/UX-01 against the exact copy in 09-UI-SPEC.md; 10 of 13 new tests are legitimately RED, 3 are legitimate regression guards that already pass, and all 4 pre-existing Breeze auth tests remain green.**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-07-17T04:05Z
- **Completed:** 2026-07-17T04:17Z
- **Tasks:** 2 completed
- **Files modified:** 2 (1 new, 1 additive edit)

## Accomplishments

- `tests/Feature/LandingPageTest.php` — 7 test methods locking the `/` route contract before `resources/views/landing.blade.php` exists: guest gets 200 + `assertViewIs('landing')`, hero shows "Online Examination Portal" / "for Yayasan Peneraju Technical Assessment", `<title>` names the app, the CTA reads "Sign in" and links to `route('login')`, no `route('logout')` leaks to the guest navbar, and both an authenticated student and lecturer are redirected to `route('dashboard')`.
- `tests/Feature/Auth/AuthenticationTest.php` — 6 additive methods locking the Flowbite login card contract before `resources/views/auth/login.blade.php` is restyled: "Sign in to our platform" heading + "Login to your account" button copy, "Not registered?" → `route('register')`, "Lost Password?" → `route('password.request')`, the ported `bg-neutral-primary-soft`/`rounded-base` token classes reaching the markup, the CSRF/form-action regression guard, and inline (not toast) error repopulation on a failed login.
- Confirmed via full-suite run: 329 total tests (316 pre-existing + 13 new), 18 errors (unchanged, carried from 09-01's still-unimplemented `Semester`/`AttemptVanishedException`) + 13 failures (3 carried from 09-01, +10 new legitimate RED from this plan), **zero pre-existing tests affected**.

## Task Commits

Each task was committed atomically:

1. **Task 1: Write the failing NAV-01 + UX-01 spec — tests/Feature/LandingPageTest.php** - `cc9252f` (test)
2. **Task 2: Append the failing NAV-02 spec to tests/Feature/Auth/AuthenticationTest.php** - `5783100` (test)

_TDD RED phase only — no GREEN/REFACTOR commits in this plan; Wave 1 plans 09-06 (login card restyle) and 09-08 (landing page build) implement the code these tests pin._

## Files Created/Modified

- `tests/Feature/LandingPageTest.php` — 7 methods, `RefreshDatabase`, imports `App\Models\User`
- `tests/Feature/Auth/AuthenticationTest.php` — 6 new methods appended after the 4 pre-existing Breeze methods; no existing method touched

## Decisions Made

- Strengthened the landing-page CTA assertion to check the exact "Sign in" copy (not just the href) — see key-decisions above.
- Kept the two `AuthenticationTest` "regression guard" tests (CSRF/action, inline-error repopulation) even though they pass today — their purpose is to catch a regression during the 09-06 restyle, not to prove absence today.
- Kept `test_the_landing_page_does_not_render_the_authenticated_navbar` as-is despite passing today — it is structurally a negative assertion (`assertDontSee`) that cannot be meaningfully RED before a page exists; it will continue to hold once the real landing page ships and guards against a future regression.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Strengthened the landing-page "links to login" assertion to check exact CTA copy**
- **Found during:** Task 1, running `php artisan test --filter=LandingPageTest`
- **Issue:** The plan's literal instruction (`assertSee(route('login'), false)` only) coincidentally passed against Breeze's current `welcome.blade.php`, which already renders `href="{{ route('login') }}"` for guests (under the label "Log in"). A bare href check does not discriminate the not-yet-built landing page from the current placeholder, so the test was not a legitimate RED per the plan's own acceptance criterion ("all 7 tests are red").
- **Fix:** Added `assertSee('Sign in')` — the exact Primary CTA copy from 09-UI-SPEC.md's Copywriting Contract — alongside the existing href check.
- **Files modified:** `tests/Feature/LandingPageTest.php`
- **Verification:** `php artisan test --filter=LandingPageTest` now fails this test for the correct reason (current page says "Log in", not "Sign in").
- **Committed in:** `cc9252f` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 — plan's literal assertion was too weak to discriminate against the pre-existing default view).
**Impact on plan:** Necessary for the test to be a legitimate RED spec rather than a false pass. No scope creep — no production code touched.

## Issues Encountered

- **Task 1 acceptance criterion ("all 7 tests are red") was not fully achievable as literally written.** `test_the_landing_page_does_not_render_the_authenticated_navbar` (`assertDontSee(route('logout'))`) is structurally always-true before any navbar markup exists — Breeze's default guest welcome view also never renders `route('logout')`. This is not a test-design flaw; it is the nature of a negative assertion applied to a not-yet-built page. Result: **6 of 7** `LandingPageTest` methods are RED, 1 (the navbar-leak guard) is a legitimate pass-both-before-and-after control assertion. The overall `--filter=LandingPageTest` run still fails (exit code non-zero), satisfying the plan's broader verification requirement ("`php artisan test --filter=LandingPageTest` fails").
- **Task 2's two "regression guard" style tests pass today, as anticipated by the plan's own `read_first` notes.** The plan's `read_first` for Task 2 explicitly documents that `login.blade.php` already has `<form method="POST" action="{{ route('login') }}">`, `@csrf`, `:value="old('email')"`, and `<x-input-error>`. `test_the_login_form_still_posts_to_the_login_route_with_csrf` and `test_a_failed_login_repopulates_the_email_and_shows_an_inline_error` therefore pass now — this is expected and correct; they exist to catch a regression during 09-06's restyle, not to prove absence today. **4 of 6** new `AuthenticationTest` methods are RED (missing Flowbite card markup/copy/tokens); the overall `--filter=AuthenticationTest` run fails, and all 4 pre-existing Breeze tests in the file remain green — the plan's acceptance criterion ("failures confined to the six new methods, no pre-existing regression") is fully satisfied.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Both Wave 0 pre-login test surfaces exist, are RED for legitimate reasons where the assertion can meaningfully be RED, and pin the exact route names, view names, and verbatim copy that plans 09-06 (login card restyle) and 09-08 (landing page build) must deliver.
- `resources/views/landing.blade.php`, `resources/views/layouts/landing.blade.php`, and the `/` route's guest/authenticated split do not exist yet — 09-08 must create them, redirecting authenticated users to `route('dashboard')` and rendering the `landing` view for guests with the exact hero copy asserted here.
- `resources/views/auth/login.blade.php` still renders Breeze's default markup — 09-06 must restyle it to the Flowbite card per 09-UI-SPEC.md while preserving the CSRF/form-action/inline-error behavior this plan's regression-guard tests already pin as green.
- No blockers. The 09-VALIDATION.md Wave 0 checklist items for `LandingPageTest` and the `AuthenticationTest` extension are both satisfied by this plan; the remaining Wave 0 checklist items (`NoNativeDialogTest`, `ToastTest`) belong to other 09-0x plans, not this one.

---
*Phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p*
*Completed: 2026-07-17*

## Self-Check: PASSED

- FOUND: tests/Feature/LandingPageTest.php
- FOUND: tests/Feature/Auth/AuthenticationTest.php
- FOUND: .planning/phases/09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p/09-02-SUMMARY.md
- FOUND: commit cc9252f (Task 1)
- FOUND: commit 5783100 (Task 2)

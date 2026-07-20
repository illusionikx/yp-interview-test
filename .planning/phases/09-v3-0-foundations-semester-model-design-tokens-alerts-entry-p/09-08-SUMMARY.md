---
phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p
plan: 08
subsystem: ui
tags: [blade, tailwind, alpine, laravel-routing, dark-mode, landing-page]

# Dependency graph
requires:
  - phase: 09-02
    provides: tests/Feature/LandingPageTest.php (the 7-test spec this plan turns GREEN)
  - phase: 09-06
    provides: the UI-03 semantic token layer (bg-brand, text-heading, text-body, text-fg-brand, rounded-base, shadow-xs, etc.) emitting real CSS
  - phase: 09-07
    provides: the <x-toast /> component, hostable in a third shell
provides:
  - A dedicated resources/views/layouts/landing.blade.php shell (full-bleed, pre-paint dark-mode bootstrap, slim top bar, one <x-toast /> host)
  - App\View\Components\LandingLayout so <x-landing-layout> resolves (mirrors GuestLayout/AppLayout)
  - resources/views/landing.blade.php — the branded hero (NAV-01, UX-01)
  - routes/web.php's / route branching guest-vs-authenticated
  - config('app.name') defaulting to "Online Examination Portal" on a clean clone
  - phpunit.xml pinning APP_NAME for deterministic test titles
  - Navbar wordmark updated to "Online Examination Portal"
affects: [09-09 (inline banner removal), 09-10 (confirm-modal migration), Phase 11 (navigation restructure), Phase 14 (README/setup docs)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Third dedicated Blade layout shell (landing.blade.php) rather than branching either shipped shell — documented inline as a Blade comment explaining why guest.blade.php and app.blade.php each fail to fit"
    - "Class-based anonymous-component alias (App\\View\\Components\\LandingLayout) required for <x-landing-layout> to resolve — Laravel resolves <x-guest-layout>/<x-app-layout> via guessClassName() matching a class in App\\View\\Components, not by directory convention"

key-files:
  created:
    - resources/views/layouts/landing.blade.php
    - app/View/Components/LandingLayout.php
    - resources/views/landing.blade.php
  modified:
    - routes/web.php
    - config/app.php
    - phpunit.xml
    - resources/views/layouts/navigation.blade.php
    - tests/Feature/NavigationTest.php

key-decisions:
  - "Top-bar 'Sign in' stays a plain text link (text-fg-brand, no fill); only the hero CTA is the filled bg-brand button, per UI-SPEC's 'do not add a second filled button'"
  - "Landing shell's attribution comment uses Blade comment syntax ({{-- --}}) not an HTML comment, after an HTML comment containing the literal string '@include' was compiled by Blade as a live directive and caused a 500 (ParseError) on every guest request"
  - "welcome.blade.php left on disk, now unreferenced by any route — deliberate, deferred cleanup per plan"

patterns-established:
  - "Any HTML comment in a .blade.php file must avoid literal '@directive'-shaped text (e.g. '@include', '@if') — Blade compiles directives found anywhere in the raw file, including inside <!-- --> comments. Use {{-- --}} Blade comments for prose containing directive-like words."

requirements-completed: [NAV-01, UX-01]

# Metrics
duration: ~30min
completed: 2026-07-17
status: complete
---

# Phase 9 Plan 08: Landing Page & App Title Summary

**Branded "Online Examination Portal" landing page at `/` for guests (with an auth redirect to `/dashboard`), backed by a new dedicated Blade layout shell and a repointed `config('app.name')` default — 7/7 LandingPageTest tests green.**

## Performance

- **Duration:** ~30 min
- **Completed:** 2026-07-17T05:36:17Z
- **Tasks:** 3 (all `type="auto"`, tasks 2-3 tagged `tdd="true"` against pre-existing RED tests)
- **Files modified:** 8 (3 created, 5 modified, including 1 out-of-plan class file and 1 out-of-plan pre-existing-test fix)

## Accomplishments

- A guest visiting `/` now sees a branded hero — "Online Examination Portal" / "for Yayasan Peneraju
  Technical Assessment" / a one-line description / a single "Sign in" CTA — instead of Breeze's
  default `welcome` view.
- An authenticated user (student or lecturer) visiting `/` is redirected to `route('dashboard')`,
  which keeps its existing `['auth', 'verified']` gate untouched; `/` itself carries no `auth`
  middleware (verified via `route:list --json`).
- `config('app.name')` now defaults to "Online Examination Portal" on a clean clone with no
  `APP_NAME` set, and the test suite pins `APP_NAME` so the `<title>` assertion is deterministic
  regardless of the developer's local `.env`.
- The authenticated navbar wordmark reads "Online Examination Portal" (was "Exam Portal").
- All 7 `LandingPageTest` tests pass; the full suite's only remaining failures are the 2
  `NoNativeDialogTest` failures (owned by 09-10) and 1 `ToastTest` single-render failure (owned by
  09-09) — exactly the set the plan predicted.
- `bash scripts/ui-03-token-gate.sh` still passes 18/18 — the new hero and top bar are additional
  consumers of the UI-03 token vocabulary and emit real CSS.

## Task Commits

Each task was committed atomically:

1. **Task 1: Create the dedicated landing shell** - `5d11744` (feat)
2. **Task 2: Create the landing hero and branch the / route** - `e6bb4af` (feat)
3. **Task 3: Make the app read as "Online Examination Portal"** - `4026c76` (feat)
4. **Follow-up: reword a false-positive-triggering comment** - `01c5ebb` (fix)

**Plan metadata:** (this commit, to follow)

## Files Created/Modified

- `resources/views/layouts/landing.blade.php` - new dedicated shell: `<head>` from `guest.blade.php`,
  pre-paint dark-mode bootstrap from `app.blade.php`, a slim top bar (wordmark + verbatim dark-toggle
  + plain-link Sign in), `<main>` slot, one `<x-toast />`
- `app/View/Components/LandingLayout.php` - new class so `<x-landing-layout>` resolves (not in the
  plan's `files_modified` list — see Deviations)
- `resources/views/landing.blade.php` - the branded hero, wrapped in `<x-landing-layout>`
- `routes/web.php` - `/` now branches: `redirect()->route('dashboard')` for authenticated users,
  `view('landing')` for guests
- `config/app.php` - `'name'` default: `'Laravel'` → `'Online Examination Portal'`
- `phpunit.xml` - added `<env name="APP_NAME" value="Online Examination Portal"/>`
- `resources/views/layouts/navigation.blade.php` - wordmark text only: `'Exam Portal'` →
  `'Online Examination Portal'`
- `tests/Feature/NavigationTest.php` - updated 2 pre-existing Phase-7 assertions from `'Exam Portal'`
  to `'Online Examination Portal'` (see Deviations)

## Decisions Made

- **Top-bar Sign in is a plain link, not a second filled button.** 09-UI-SPEC.md's Color contract
  reserves the accent-filled treatment for exactly one primary CTA per surface; the hero's "Sign in"
  button is that CTA, so the top-bar "Sign in" uses `text-fg-brand hover:underline` instead of
  `bg-brand`.
- **`App\View\Components\LandingLayout` created even though not listed in the plan's
  `files_modified`.** Laravel resolves `<x-guest-layout>`/`<x-app-layout>` via
  `ComponentTagCompiler::guessClassName()` matching a studly-cased class in `App\View\Components`
  (`GuestLayout`/`AppLayout` already exist and each `render()`s `view('layouts.guest')` /
  `view('layouts.app')`) — not by any directory-naming convention on `resources/views/layouts/`.
  Without an equivalent `LandingLayout` class, `<x-landing-layout>` throws
  `InvalidArgumentException: Unable to locate a class or view for component [landing-layout]`. This
  is load-bearing scaffolding required for the plan's own named artifact
  (`resources/views/layouts/landing.blade.php`) to be reachable at all.
- **`welcome.blade.php` left on disk, unreferenced.** Per the plan — a deliberate, deferred cleanup,
  not a requirement of this phase.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] HTML comment containing literal "@include" text compiled as a live Blade directive, causing a 500 on every guest request**
- **Found during:** Task 1/2 verification (`php artisan test --filter=LandingPageTest`)
- **Issue:** The landing shell's top-of-file `<!-- -->` HTML comment explained why `app.blade.php`
  `` @include``s the navbar, using the literal backtick-quoted word `` @include`` in prose. Blade
  compiles `@directive` syntax anywhere in a `.blade.php` file's raw text, including inside HTML
  comments — it produced
  `<?php echo $__env->make(, array_diff_key(...))->render(); ?>` with an empty view-name argument,
  a `ParseError` at render time, and every request to `/` returning HTTP 500.
- **Fix:** Converted the top-of-file comment from an HTML comment (`<!-- -->`) to a Blade comment
  (`{{-- --}}`) — Blade strips `{{-- --}}` content entirely at compile time before any directive
  parsing runs, so text inside it is never live-compiled. Also reworded the inline dark-toggle
  attribution comment (separately) to avoid the literal string "layouts/navigation.blade.php" from
  false-matching an unrelated acceptance-criterion grep for navbar leakage (see item 3 below).
- **Files modified:** `resources/views/layouts/landing.blade.php`
- **Verification:** `php artisan test --filter=LandingPageTest` — went from a 500/ParseError to a
  clean pass.
- **Committed in:** `e6bb4af` (folded into the working tree before the task-2 commit; no separate
  commit needed since the file was still uncommitted when found)

**2. [Rule 2 - Missing Critical] `App\View\Components\LandingLayout` class did not exist**
- **Found during:** Task 1, before first verification run
- **Issue:** `<x-landing-layout>` cannot resolve to `resources/views/layouts/landing.blade.php`
  without a matching class-based component, per Laravel's `guessClassName()` resolution (see
  Decisions above). Without it, every use of `<x-landing-layout>` in `landing.blade.php` (task 2)
  would throw.
- **Fix:** Added `app/View/Components/LandingLayout.php`, mirroring the existing
  `GuestLayout`/`AppLayout` classes exactly (a one-method class `render(): View` returning
  `view('layouts.landing')`).
- **Files modified:** `app/View/Components/LandingLayout.php` (new)
- **Verification:** `<x-landing-layout>` resolves; `LandingPageTest` renders view `landing`
  successfully.
- **Committed in:** `5d11744` (Task 1 commit)

**3. [Rule 1 - Regression fix] Pre-existing `NavigationTest` asserted the old "Exam Portal" wordmark**
- **Found during:** Task 3 full-suite verification (`php vendor/bin/phpunit`)
- **Issue:** `tests/Feature/NavigationTest.php` (written in Phase 7) asserted
  `$response->assertSee('Exam Portal')` for both the lecturer and student navbar views. Task 3's
  UX-01 wordmark change (`'Exam Portal'` → `'Online Examination Portal'`) is exactly what this test
  was pinning — the failure is a direct, foreseeable consequence of doing the task correctly, not an
  unrelated regression.
- **Fix:** Updated both assertions (and the class doc-comment) in `NavigationTest.php` to expect
  `'Online Examination Portal'`.
- **Files modified:** `tests/Feature/NavigationTest.php`
- **Verification:** `php vendor/bin/phpunit` — full suite failure count returned to exactly the 3
  failures the plan predicted (2 `NoNativeDialogTest`, 1 `ToastTest`).
- **Committed in:** `4026c76` (Task 3 commit)

**4. [Rule 1 - Bug] False-positive match in T-09-04's paired verification grep**
- **Found during:** Post-implementation acceptance-criteria sweep (before final commit)
- **Issue:** The dark-toggle attribution comment in `landing.blade.php` read "...copied verbatim from
  layouts/navigation.blade.php...". The acceptance criterion's grep
  (`layouts.navigation|route('logout')|route('dashboard')`) treats `.` as "any character" (basic
  regex), so `layouts/navigation` matched `layouts.navigation` even though the comment is not an
  `@include` or route call — it is prose citing the source file the markup was copied from.
- **Fix:** Reworded the comment to "the authenticated navbar" instead of naming the file path
  literally.
- **Files modified:** `resources/views/layouts/landing.blade.php`
- **Verification:** `grep -c "layouts.navigation\|route('logout')\|route('dashboard')"
  resources/views/layouts/landing.blade.php` now returns `0`, matching T-09-04's paired check.
- **Committed in:** `01c5ebb` (follow-up fix commit)

---

**Total deviations:** 4 auto-fixed (2 Rule 1 bug/regression fixes, 1 Rule 1 verification-hygiene fix,
1 Rule 2 missing-critical-scaffolding fix)
**Impact on plan:** All four were necessary for the plan's own stated artifacts and acceptance
criteria to actually work/pass. No scope creep — nothing outside `landing.blade.php`,
`LandingLayout.php`, and the one pre-existing test file it broke was touched.

## Issues Encountered

**Local `.env` overrides the `config/app.php` default for `APP_NAME`.** Per the plan's explicit
instruction, checked the live value:

```
$ php artisan tinker --execute="echo config('app.name');"
Laravel
```

This confirms the plan's predicted caveat: this working copy's `.env` sets `APP_NAME=Laravel`
explicitly, which overrides the new `config/app.php` default at runtime. All 7 `LandingPageTest`
tests pass (the test suite is isolated from this via `phpunit.xml`'s pinned `APP_NAME`), and a clean
clone with no `.env` `APP_NAME` entry will correctly show "Online Examination Portal" in the browser
title bar. **This machine's browser will still show "Laravel" in the tab title until the operator
manually sets `APP_NAME="Online Examination Portal"` in this project's local `.env` file** — not
fixed here per the plan's explicit instruction not to work around `.env` access restrictions or
hardcode around it.

## User Setup Required

**One optional local `.env` edit, not required for grading/clean-clone correctness:**
- To see "Online Examination Portal" instead of "Laravel" in this specific working copy's browser
  tab title, add `APP_NAME="Online Examination Portal"` to `.env` (or delete the existing
  `APP_NAME=Laravel` line so the new `config/app.php` default takes over) and run
  `php artisan config:clear`.
- Not required for the graded deliverable: a clean clone with no `.env` `APP_NAME` set already
  resolves correctly via the new `config/app.php` default, which is what Phase 14's README/setup
  instructions will document.

## Next Phase Readiness

- NAV-01 and UX-01 are both satisfied and requirement-complete: 7/7 `LandingPageTest` tests pass, the
  token gate passes 18/18, and the full suite's only remaining failures are the 3 pre-attributed to
  09-09 (inline-banner removal) and 09-10 (confirm-modal migration) — no new failures introduced.
- Plan 09-09 can proceed: it will delete the ~11 inline `session('status')`/`session('error')`
  banners across lecturer/student views, which is what turns `ToastTest::
  test_a_status_flash_renders_exactly_once` and its error-flash sibling green (only the status one
  is currently red; the error one already passes by coincidence of which view it targets).
- Plan 09-10 can proceed independently: it migrates the 3 native `confirm()` call sites onto
  `<x-confirm-modal>`, which is what turns both `NoNativeDialogTest` failures green.
- No blockers for either successor plan. The new `layouts/landing.blade.php` and
  `App\View\Components\LandingLayout` establish the "third shell needs a matching
  `App\View\Components` class" pattern documented above, relevant if any future phase adds a fourth
  distinct shell.

---
*Phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p*
*Completed: 2026-07-17*

## Self-Check: PASSED

All 8 files verified present on disk; all 4 task/fix commits (`5d11744`, `e6bb4af`, `4026c76`,
`01c5ebb`) verified present in `git log --oneline --all`.

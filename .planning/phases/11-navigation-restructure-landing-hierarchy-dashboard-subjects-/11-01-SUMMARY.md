---
phase: 11-navigation-restructure-landing-hierarchy-dashboard-subjects
plan: 01
subsystem: ui
tags: [blade, tailwind, alpine, navigation, laravel-11, flowbite]

# Dependency graph
requires:
  - phase: 09-design-tokens-flowbite
    provides: Flowbite semantic tokens (bg-brand, border-default, rounded-base, bg-neutral-primary-soft) used by the new components
provides:
  - Trimmed navigation.blade.php top bar (NAV-03) with relabelled interim links
  - x-back-button, x-welcome-banner, x-dashboard-card shared Blade components
  - Two-direction reachability audit (tests/Feature/Navigation/ReachabilityTest.php, NAV-04)
  - Back-button retrofit on the four lecturer sections/exams create+edit views
affects: [11-02-dashboard, 11-03-subjects, 11-04-enrollment]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "x-back-button: destination-naming back affordance — href prop + slot carries copy, no hardcoded destination noun in the component itself"
    - "x-dashboard-card: presentational-only stat card (label/value + optional progressCurrent/progressMax), no queries inside — callers pass pre-aggregated values"

key-files:
  created:
    - resources/views/components/back-button.blade.php
    - resources/views/components/welcome-banner.blade.php
    - resources/views/components/dashboard-card.blade.php
    - tests/Feature/Navigation/ReachabilityTest.php
    - tests/Feature/Navigation/BackButtonTest.php
  modified:
    - resources/views/layouts/navigation.blade.php
    - resources/views/lecturer/sections/create.blade.php
    - resources/views/lecturer/sections/edit.blade.php
    - resources/views/lecturer/exams/create.blade.php
    - resources/views/lecturer/exams/edit.blade.php
    - tests/Feature/NavigationTest.php
    - tests/Feature/HelpPageTest.php

key-decisions:
  - "Kept interim Classes/Exams/Help (lecturer) and Class enrollment/My Exams/Help (student) links in the navbar rather than deleting them, per NAV-04 — they retire once Phase 12/13 build permanent homes"
  - "Removed the lecturer 'Subjects' primary nav link entirely since the home page becomes the subject-list hub (11-02/11-03 scope, not rendered yet — the link removal is safe because subjects.index remains reachable via the home page's existing card grid, untouched by this plan)"
  - "ReachabilityTest proves reachability via route-URL presence in rendered HTML, not pixel/visual checks, matching the plan's explicit intent"

patterns-established:
  - "Pattern: UX-04 back affordances always render via <x-back-button :href=\"...\"> with slot text naming the destination — never a bare Cancel/Back anchor"

requirements-completed: [NAV-03, NAV-04, UX-04, DASH-02]

# Metrics
duration: 8min
completed: 2026-07-18
status: complete
---

# Phase 11 Plan 01: Navigation Trim, Reachability Audit & Shared UI Primitives Summary

**Trimmed navigation.blade.php into a slim top bar with relabelled interim links (Sections→Classes, Enroll→Class enrollment), shipped x-back-button/x-welcome-banner/x-dashboard-card as reusable Blade components, and proved every pre-restructure destination (Sections/Exams/Results/Help, both roles, plus guest landing→login→register) is still reachable via a new two-direction ReachabilityTest.**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-07-18T09:07:00Z (approx, orchestrator hand-off)
- **Completed:** 2026-07-18T09:15:00Z
- **Tasks:** 3 completed
- **Files modified:** 12 (3 created components, 2 created tests, 7 modified)

## Accomplishments
- NAV-03: navigation.blade.php trimmed — lecturer "Subjects" primary link removed, "Sections" relabelled "Classes", student "Enroll" relabelled "Class enrollment"; desktop + mobile menus both updated with a transitional-link blade comment explaining why the interim block still exists
- UX-04/DASH-02: three new shared components (`x-back-button`, `x-welcome-banner`, `x-dashboard-card`) built on the existing `@props` + `$attributes->merge` convention from `confirm-modal`/`status-pill`, verified to render via `Blade::render()` smoke checks
- NAV-04: `tests/Feature/Navigation/ReachabilityTest.php` proves, per role, that the trimmed nav shell still links to every at-risk destination (lecturer: Classes/Exams/Help + results.index reachable for a seeded exam; student: Class enrollment/My Exams/Help), plus the guest landing → login → register path
- UX-04: four lecturer create/edit views (sections create/edit, exams create/edit) retrofitted with `x-back-button`, each naming its destination ("Back to classes", "Back to exams", "Back to exam")

## Task Commits

Each task was committed atomically:

1. **Task 1: Trim navigation.blade.php into the slim landing top bar (NAV-03)** - `7235755` (feat)
2. **Task 2: Create the shared back-button, welcome-banner, and dashboard-card components (UX-04, DASH-02)** - `3e769b7` (feat)
3. **Task 3: Retrofit back-buttons on the four non-Wave-2 create/edit views + reachability & back-button tests (UX-04, NAV-04)** - `3bdcb07` (feat)

**Plan metadata:** (this commit)

## Files Created/Modified
- `resources/views/layouts/navigation.blade.php` - trimmed desktop + mobile role-scoped link groups; relabelled copy; kept every at-risk route reachable
- `resources/views/components/back-button.blade.php` - UX-04 destination-naming back-button primitive (`href` prop + `$slot`)
- `resources/views/components/welcome-banner.blade.php` - DASH-02 brand-gradient greeting banner (`name` prop, defaults to `auth()->user()->name`)
- `resources/views/components/dashboard-card.blade.php` - presentational stat card with optional bounded progress bar (`label`/`value`/`progressCurrent`/`progressMax`)
- `resources/views/lecturer/sections/create.blade.php` - "Cancel" → `x-back-button` "Back to classes"
- `resources/views/lecturer/sections/edit.blade.php` - "Cancel" → `x-back-button` "Back to classes"
- `resources/views/lecturer/exams/create.blade.php` - "Cancel" → `x-back-button` "Back to exams"
- `resources/views/lecturer/exams/edit.blade.php` - "Cancel" → `x-back-button` "Back to exam" (routes to `exams.show`)
- `tests/Feature/NavigationTest.php` - lecturer case now asserts "Classes"/"Exams" (dropped "Sections"/"Subjects")
- `tests/Feature/HelpPageTest.php` - lecturer case asserts "Classes"/"Exams"/"Help"; student case asserts "Class enrollment"/"My Exams"/"Help"
- `tests/Feature/Navigation/ReachabilityTest.php` - new NAV-04 two-direction reachability audit (5 tests)
- `tests/Feature/Navigation/BackButtonTest.php` - new UX-04 render smoke test for `x-back-button` on all four retrofitted views (4 tests)

## Decisions Made
- Interim nav links are explicitly commented as transitional in navigation.blade.php, naming which future phases (12/13) absorb them — matches the plan's NAV-04 rationale and avoids a silent "why is this still here" question later.
- `x-welcome-banner`'s greeting uses `__('Welcome back, :name', ...)` (translation-placeholder style) rather than string concatenation, consistent with the rest of the codebase's `__()` usage.
- BackButtonTest's sections create/edit cases needed the acting lecturer attached to the subject's `lecturers()` pivot (`SectionController::create/edit` are ownership-gated per WR-01/SEC-03) — this is pre-existing authorization behavior, not something this plan changed; the test was written to satisfy it.

## Deviations from Plan

None - plan executed as written. One test-construction detail worth flagging (not a deviation from the plan's content, since the plan didn't specify test fixture setup): `BackButtonTest`'s sections create/edit cases required `$subject->lecturers()->attach($lecturer)` to avoid a pre-existing 403 from `SectionController`'s ownership gate — added while writing the test, no production code touched.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `x-welcome-banner` and `x-dashboard-card` are ready for 11-02 (lecturer/student dashboard) and 11-03 (subject list) to consume directly.
- The trimmed navigation.blade.php and its interim links are stable — 11-02/11-03/11-04 render on top of this shell without further nav changes expected.
- No blockers for Wave 2.

## Self-Check: PASSED

All created files verified present on disk; all three task commits (7235755, 3e769b7, 3bdcb07) verified present in git log.

---
*Phase: 11-navigation-restructure-landing-hierarchy-dashboard-subjects*
*Completed: 2026-07-18*

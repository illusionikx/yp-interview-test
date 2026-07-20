---
phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p
plan: 03
subsystem: testing
tags: [phpunit, blade, alerts, toast, xss, static-scan]

# Dependency graph
requires:
  - phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p (plan 01-02)
    provides: role-aware factories (User::factory()->lecturer()/->student()/->unverified()), existing Feature test conventions
provides:
  - "tests/Feature/ToastTest.php — 8-method executable spec for UX-03 (<x-toast>): status/error flash rendering, single-render invariant, 3-sentinel exclusion list, aria-label=\"Dismiss\" contract, T-09-02 HTML escaping"
  - "tests/Feature/NoNativeDialogTest.php — 2-method static-scan gate for UX-02: zero native confirm()/alert() across resources/views, plus a guard that the 2 known offending files adopt <x-confirm-modal> rather than simply dropping the confirmation"
affects: [09-07 (toast component), 09-09 (inline banner removal), 09-10 (confirm-modal migration)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Flash-rendering specs seed via ->withSession([...]) rather than driving a controller action, isolating the component contract from any specific controller (09-CONTEXT.md: zero controller changes)."
    - "Static-scan gates over resources/views use File::allFiles() + file_get_contents() + str_contains(), asserted with assertSame([], $violations, ...) rather than assertEmpty() so failures name the offending files."

key-files:
  created:
    - tests/Feature/ToastTest.php
    - tests/Feature/NoNativeDialogTest.php
  modified: []

key-decisions:
  - "Plan's acceptance criteria predicted 5 RED / 3 accidental-GREEN for ToastTest; actual RED/GREEN split is 3 RED / 5 GREEN. The 2 extra accidental passes (single-render, HTML-escaping) pass today because the pre-existing inline @if (session('status')) banner already renders the message exactly once via Blade's auto-escaping {{ }} echo — before any <x-toast> exists to duplicate or mis-render it. This does not weaken the tests: they still pin the correct invariant and will fail the moment a naive toast implementation double-renders or raw-echoes flash text. No test file changes made in response to this; documented as a plan-authoring inaccuracy, not a test defect."

requirements-completed: []  # Wave 0 RED plan — UX-02/UX-03 remain incomplete until plans 09-07/09-09/09-10 land the components. Do not mark complete against failing tests.

# Metrics
duration: 25min
completed: 2026-07-17
status: complete
---

# Phase 09 Plan 03: Failing Alert-System Spec (ToastTest + NoNativeDialogTest) Summary

**Landed two Wave 0 RED test files pinning UX-02 (no native browser dialogs) and UX-03 (`<x-toast>` behavior), including the Breeze sentinel-exclusion landmine and the double-render landmine — both correctly FAIL today for the intended reasons.**

## Performance

- **Duration:** 25 min
- **Started:** 2026-07-17T03:56:00Z
- **Completed:** 2026-07-17T04:21:15Z
- **Tasks:** 2 completed
- **Files modified:** 2 (both new)

## Accomplishments

- `tests/Feature/ToastTest.php` (8 tests, 138 lines): pins the status/error flash contract, the single-render invariant that plan 09-09's inline-banner removal must satisfy, all 3 Breeze sentinel exclusions (`verification-link-sent`, `password-updated`, `profile-updated`), the `aria-label="Dismiss"` accessibility contract, and T-09-02's HTML-escaping requirement.
- `tests/Feature/NoNativeDialogTest.php` (2 tests, 65 lines): a static content-scan gate over `resources/views` for `confirm(`/`alert(`, plus a second test that blocks the gate from being satisfied by silently deleting the confirmation step rather than migrating to `<x-confirm-modal>`.
- Verified both files fail for the correct reasons (see Verification below) and produce no failures outside themselves against the rest of the currently-executable suite.

## Task Commits

Each task was committed atomically:

1. **Task 1: Write the failing UX-03 spec — tests/Feature/ToastTest.php** - `8f0eb83` (test)
2. **Task 2: Write the failing UX-02 static-scan gate — tests/Feature/NoNativeDialogTest.php** - `bef27f3` (test)

**Plan metadata:** (this commit, docs: complete plan)

## Files Created/Modified

- `tests/Feature/ToastTest.php` - 8 tests: status-toast render, single-render guard, error-toast render, error single-render guard, 3 sentinel-exclusion tests (profile-updated/password-updated/verification-link-sent), HTML-escaping (T-09-02)
- `tests/Feature/NoNativeDialogTest.php` - 2 tests: whole-view-tree `confirm(`/`alert(` scan, `<x-confirm-modal>` presence guard on the 2 known offending files

## Decisions Made

- Kept the plan's exact test bodies, route targets (`lecturer.exams.index`, `lecturer.sections.index`, `profile.edit`, `verification.notice`), and needle/exclusion-list values verbatim — all verified against the live codebase (routes, factories, and the 3 sentinel-carrying Breeze views) before writing.
- Did not adjust the RED/GREEN split to match the plan's stated 5/3 prediction (see key-decisions above) — the plan's written test *behavior* (the `<action>` sections) is what's authoritative, and I implemented that faithfully; the acceptance-criteria's specific pass/fail prediction for tests 2 and 8 was inaccurate but the tests themselves are correct and will do their job the moment `<x-toast>` lands.

## Deviations from Plan

None — plan executed exactly as written. The RED/GREEN split discrepancy noted above is a documentation observation, not a deviation requiring a Rule 1-4 fix: no code or test logic needed to change, only my expectation of which specific tests would be red today.

## Issues Encountered

- Running the entire suite via `php artisan test` (no filter) crashes mid-run: `tests/Feature/AttemptNullGuardTest.php` (landed in plan 09-01, pre-existing, references `App\Exceptions\AttemptVanishedException` which doesn't exist yet) triggers a secondary fatal error inside Whoops's exception renderer (`str_replace(): Argument #3 ($subject) must be of type array|string, null given` in `vendor/filp/whoops/src/Whoops/Exception/FrameCollection.php`), which aborts the PHPUnit process entirely before it reaches any test file alphabetically after `AttemptNullGuardTest`. This is pre-existing (confirmed via `git log -- tests/Feature/AttemptNullGuardTest.php` → commit `77967d1`, plan 09-01) and out of this plan's scope. Worked around it for verification purposes by running `php artisan test --filter='^(?!.*(AttemptNullGuardTest|SemesterTest)).*$'`, which completed cleanly and confirmed no new failures outside `ToastTest.php` and `NoNativeDialogTest.php` (the other 10 of 15 failures are pre-existing RED tests from plans 09-01/09-02, also awaiting their GREEN-turning plans). Flagging this crash for whichever later plan lands `AttemptVanishedException` (likely 09-04 or 09-05, per INT-01) since it currently makes `php artisan test` unusable as a single whole-suite command.

## Verification

**`php artisan test --filter=ToastTest`** — 3 failed, 5 passed (13 assertions):
- FAILED: `test_a_status_flash_renders_as_a_toast` (no `aria-label="Dismiss"` exists)
- FAILED: `test_an_error_flash_renders_as_a_toast` (`lecturer.sections.index` has no inline `session('error')` banner, so "That section is full." never appears — 0 renders)
- FAILED: `test_an_error_flash_renders_exactly_once` (`Failed asserting that 0 is identical to 1`, same root cause)
- PASSED (accidental, become real regression guards once 09-07 lands): `test_a_status_flash_renders_exactly_once`, `test_the_profile_updated_sentinel_does_not_render_as_a_toast`, `test_the_password_updated_sentinel_does_not_render_as_a_toast`, `test_the_verification_link_sent_sentinel_does_not_render_as_a_toast`, `test_the_toast_escapes_html_in_flash_text`

**`php artisan test --filter=NoNativeDialogTest`** — 2 failed, 0 passed (2 assertions):
- FAILED: `test_no_blade_view_invokes_a_native_browser_dialog` — `Native browser dialog found in: lecturer\exams\show.blade.php, lecturer\subjects\index.blade.php` (exactly the 2 expected files; Windows path separators, same files)
- FAILED: `test_the_destructive_lecturer_forms_use_the_confirm_modal_component` — `<x-confirm-modal` not found in either file

**Acceptance-criteria greps** — all satisfied:
- `grep -c "public function test_" tests/Feature/ToastTest.php` → 8
- `grep -c "substr_count" tests/Feature/ToastTest.php` → 2
- `grep -c "verification-link-sent|password-updated|profile-updated" tests/Feature/ToastTest.php` → 3 each
- `grep -c "public function test_" tests/Feature/NoNativeDialogTest.php` → 2
- `grep -c "resource_path('views')" tests/Feature/NoNativeDialogTest.php` → 1
- `grep -c "x-confirm-modal" tests/Feature/NoNativeDialogTest.php` → 5

**Whole-suite regression check** (via the `AttemptNullGuardTest`/`SemesterTest`-excluding filter — see Issues Encountered): 15 failed, 302 passed. The 15 failures are exactly: 4 pre-existing `AuthenticationTest` (09-02 RED), 6 pre-existing `LandingPageTest` (09-02 RED), 2 `NoNativeDialogTest` (this plan), 3 `ToastTest` (this plan). Zero new failures outside the two files this plan created.

## Next Phase Readiness

- The executable spec for UX-02/UX-03 is in place and correctly RED. Plans 09-07 (`<x-toast>`), 09-09 (inline-banner removal), and 09-10 (`<x-confirm-modal>` migration) each have a concrete gate to turn green.
- Flagging for whichever plan lands `App\Exceptions\AttemptVanishedException` (INT-01): the current Whoops crash on that missing class makes unfiltered `php artisan test` unusable — worth a quick check that this resolves itself once the class exists, otherwise it may need its own fix.

---
*Phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p*
*Completed: 2026-07-17*

## Self-Check: PASSED

- FOUND: tests/Feature/ToastTest.php
- FOUND: tests/Feature/NoNativeDialogTest.php
- FOUND: .planning/phases/09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p/09-03-SUMMARY.md
- FOUND: commit 8f0eb83
- FOUND: commit bef27f3

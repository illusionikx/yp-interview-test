---
phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix
plan: 01
subsystem: ui
tags: [flowbite, tailwind, dark-mode, alpine, blade, status-pill]

# Dependency graph
requires: []
provides:
  - "Flowbite installed and Vite-bundled (Tailwind v3 shape, darkMode: 'class')"
  - "Pre-paint dark-mode bootstrap in resources/views/layouts/app.blade.php (no-FOUC theme toggle target)"
  - "Reusable x-status-pill Blade component (green/gray/red/amber, dark-aware, escaped-label)"
  - "FIX-01 fixed: submit-confirmation modal's answered count is session-reactive, not a page-load snapshot"
affects: [07-05-navbar-reskin, 07-06-view-reskin, ui-01, ui-02]

# Tech tracking
tech-stack:
  added: [flowbite@^4.0.2]
  patterns:
    - "Pre-paint inline <head> script (before @vite) reads localStorage 'theme' with prefers-color-scheme fallback to avoid flash-of-wrong-theme"
    - "Status string -> CSS class mapped through a fixed match() allowlist in a Blade component, never interpolated raw into class/body"
    - "Cross-Alpine-scope communication via bubbled window CustomEvent (question-answered), mirroring the existing deadline-expired idiom, to avoid a whole-page x-data leak vector"

key-files:
  created:
    - resources/views/components/status-pill.blade.php
  modified:
    - package.json
    - package-lock.json
    - tailwind.config.js
    - resources/js/app.js
    - resources/views/layouts/app.blade.php
    - resources/views/student/attempts/show.blade.php
    - tests/Feature/Student/AttemptShowTest.php

key-decisions:
  - "Used a plain reactive object (id -> true) for answeredQuestionIds tracking instead of a JS Set, because Alpine's reactivity does not track Set mutations"
  - "Split the FIX-01 modal copy into a static prefix + x-text-bound span rather than rewriting the whole sentence as one JS template literal, preserving the exact locked UI-SPEC wording and keeping a server-rendered fallback for no-JS"

patterns-established:
  - "Dark-mode-aware Tailwind pairs (bg-gray-50 dark:bg-gray-900, bg-white dark:bg-gray-800) as the dominant/secondary surface convention for the rest of Phase 7's reskin plans"

requirements-completed: [UI-01, UI-02, FIX-01]

# Metrics
duration: 35min
completed: 2026-07-16
status: complete
---

# Phase 07 Plan 01: Flowbite Dark-Mode Foundation + FIX-01 Answered-Count Summary

**Flowbite installed into the Tailwind v3 Vite pipeline with class-based dark mode, a pre-paint no-flash theme bootstrap, a reusable x-status-pill component, and a reactive session-accurate answered-count fix for the attempt-taking submit modal.**

## Performance

- **Duration:** ~35 min
- **Tasks:** 3 completed
- **Files modified:** 7 (1 created, 6 modified)

## Accomplishments
- Flowbite (v4.0.2, npm, package-legitimacy-verified) installed and bundled via Vite alongside the existing Alpine wiring; `tailwind.config.js` carries `darkMode: 'class'`, the flowbite plugin, and its content glob.
- `resources/views/layouts/app.blade.php` gained a pre-paint inline `<head>` script (localStorage `theme` with `prefers-color-scheme` fallback) that sets the `dark` class before any stylesheet paint, plus a dark-aware page shell (`bg-gray-50 dark:bg-gray-900` wrapper, `dark:bg-gray-800` header).
- New `x-status-pill` component maps any status string through a fixed `match()` allowlist to one of the four UI-SPEC-locked palettes (green/gray/red/amber, with `dark:` variants); unrecognised input falls back to gray, and the label is always rendered as escaped Blade text.
- FIX-01: the submit-confirmation modal's "N of M answered" count is now bound to a page-level Alpine `answeredCount` getter, seeded from the server's saved-answer snapshot and kept accurate via a bubbled `question-answered` window event on every card save — no page reload required, and the Phase-4 no-whole-page-`x-data`-blob invariant is preserved.

## Task Commits

Each task was committed atomically:

1. **Task 1: Install Flowbite and enable class-based dark mode in the build** - `4ac14e3` (feat)
2. **Task 2: Pre-paint dark-mode bootstrap + status-pill component** - `44d5ae1` (feat)
3. **Task 3: FIX-01 — reactive answered-count in the submit-confirmation modal** - `aebd561` (feat, includes an inline fix for a bug this plan introduced — see Deviations)

**Plan metadata:** _(this commit)_ docs: complete plan

## Files Created/Modified
- `resources/views/components/status-pill.blade.php` - new `x-status-pill` component (props: `status`)
- `tailwind.config.js` - `darkMode: 'class'`, flowbite plugin + content glob
- `resources/js/app.js` - `import 'flowbite'` alongside existing Alpine wiring
- `resources/views/layouts/app.blade.php` - pre-paint dark-mode head script; dark-aware shell wrapper/header
- `resources/views/student/attempts/show.blade.php` - `answeredCount`/`answered` reactive state + `question-answered` window event wiring; modal `x-text` binding with server-rendered fallback
- `tests/Feature/Student/AttemptShowTest.php` - new seeded-answered-count assertion
- `package.json` / `package-lock.json` - `flowbite` dependency

## Decisions Made
- Tracked answered-question ids as a plain reactive object (`{ [id]: true }`) rather than a `Set`, since Alpine 3's reactivity proxy does not observe `Set.add()` mutations — a getter over `Object.keys(...).length` stays reactive because Alpine tracks property-set on plain objects.
- Preserved the exact locked modal copy ("You won't be able to change your answers after this. N of M questions answered.") by keeping the static prefix as a translated string and only binding the reactive portion via `x-text` on a `<span>`, with the original server-rendered `__()` call kept as the no-JS fallback content of that span.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed a self-introduced Blade-directive false-positive in app.blade.php's pre-paint comment**
- **Found during:** Task 3 verification (`php artisan test --filter=AttemptShowTest`) — two pre-existing, unrelated tests started failing with `Too few arguments to function Illuminate\Foundation\Vite::__invoke()`.
- **Issue:** Task 2's inline HTML comment above the pre-paint `<script>` block contained the literal text "before @vite so" — Blade's compiler scans for `@word` patterns anywhere in a `.blade.php` file, including inside HTML comments, and interpreted this as an invocation of the `@vite` directive with no arguments, breaking every page render through `x-app-layout`.
- **Fix:** Reworded the comment to avoid the literal string "@vite" (now reads "before the Vite scripts below").
- **Files modified:** `resources/views/layouts/app.blade.php`
- **Verification:** `php artisan view:clear` + `php vendor/bin/phpunit --filter=AttemptShowTest` (4/4 pass) + full suite `php vendor/bin/phpunit` (177 tests, 470 assertions, all green)
- **Committed in:** `aebd561` (folded into the Task 3 commit since it was discovered and fixed during that task's verification step)

---

**Total deviations:** 1 auto-fixed (1 bug, self-introduced within this same plan)
**Impact on plan:** No scope creep — the bug was introduced and caught within this plan's own execution/verification loop before any commit reached a broken state on `master` for more than the single intermediate commit. Full test suite is green as of the final commit.

## Issues Encountered
- The Windows/Herd shell environment made background `php artisan test` runs slow to surface output in this session (large buffered TeamCity-format output); switched to `php vendor/bin/phpunit --filter=...` directly for faster, streaming feedback during debugging. No project-level change required.

## User Setup Required

None - no external service configuration required. `npm install flowbite` was already run as part of Task 1's execution.

## Next Phase Readiness
- The Flowbite build pipeline, dark-mode class strategy, and `x-status-pill` component are ready for the navbar rebuild and per-view reskin (plans 07-05/07-06), which are the actual consumers of this foundation.
- FIX-01 is fully closed for this phase's scope (JS-only live-update portion is manual-only per 07-VALIDATION.md — flagged there, not re-litigated here).
- No blockers for the schema-break wave (07-02+) — this plan's changes are view/config-only and touch none of the `Classroom`/`Section` rename surface.

---
*Phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix*
*Completed: 2026-07-16*

## Self-Check: PASSED

All created/modified files confirmed present on disk; all task commit hashes (4ac14e3, 44d5ae1, aebd561) and the summary commit (5f215c6) confirmed present in git history.

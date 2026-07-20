---
phase: 14-delivery-dark-mode-wiki-manual-demo-data-browser-tests
plan: 01
subsystem: ui
tags: [tailwind, dark-mode, blade, alpine, accessibility]

# Dependency graph
requires:
  - phase: 09-foundations-semester-model-design-tokens-alerts-entry-pages
    provides: Partial semantic token layer (text-body/text-heading/bg-neutral-primary-soft/border-default/bg-brand/rounded-base) and the original dark-mode toggle + x-status-pill component
provides:
  - Corrected raw-utility dark:/light: colour pairings across every shared component and every page in the shipped hierarchy (exam editor named checkpoint fixed)
  - tests/Feature/DarkModeContrastTest.php — permanent regression guard on x-status-pill's four palette pairs
affects: [14-02, 14-03, 14-04]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Hand-rolled <x-modal> content blocks must carry their own dark: text pairing (text-gray-900 dark:text-gray-100 / text-gray-600 dark:text-gray-400) — the modal shell's dark:bg-gray-800 does not imply anything about its slot content."
    - "Alpine :class object bindings (e.g. badgeClasses()) must include dark: variants as literal string keys directly in the .blade.php file so Tailwind's JIT scanner can see them — they cannot be constructed dynamically."

key-files:
  created:
    - tests/Feature/DarkModeContrastTest.php
  modified:
    - resources/views/components/secondary-button.blade.php
    - resources/views/lecturer/exams/questions/_form.blade.php
    - resources/views/lecturer/sections/edit.blade.php
    - resources/views/lecturer/sections/index.blade.php
    - resources/views/lecturer/sections/show.blade.php
    - resources/views/lecturer/subjects/edit.blade.php
    - resources/views/student/attempts/show.blade.php
    - resources/views/student/subjects/index.blade.php
    - resources/views/student/subjects/show.blade.php

key-decisions:
  - "Task 3 (visual dark-mode walkthrough) is DEFERRED — MANUAL VERIFICATION REQUIRED, not approved and not faked. User was AFK with no browser available at execution time. Plan 14-01's buildable work (Tasks 1-2: all colour-pairing fixes + the automated contrast guard) is complete and committed; only the human visual pass remains outstanding."
  - "profile/* (Breeze's stock account-deletion page) and welcome.blade.php (unreachable stock Laravel scaffold) are known gaps, deliberately left untouched — out of the plan's declared sweep set (grep -rl 'dark:' resources/views) and out of the shipped hierarchy respectively. Flagged below for the deferred walkthrough to note if they matter."

patterns-established:
  - "Semantic dark-mode audit method: read every match()/ternary palette definition for an inverted or missing dark: arm before touching any single-utility class — a blanket find/replace already broke a non-gray palette once (why this plan exists)."

requirements-completed: [FIX-02]

# Metrics
duration: 14min (Tasks 1-2; Task 3 deferred, not yet run)
completed: 2026-07-18
status: complete
---

# Phase 14 Plan 01: FIX-02 Dark-Mode Legibility Sweep Summary

**Corrected dark-on-dark colour pairings in the exam-editor question form, five hand-rolled confirmation modals, and the take-exam countdown/autosave indicators — plus a permanent status-pill palette regression guard. Visual human-verify walkthrough is deferred, not run.**

## Performance

- **Duration:** 14 min (Tasks 1–2 only)
- **Started:** 2026-07-18T21:06:54+08:00
- **Completed:** 2026-07-18T21:20:52+08:00 (Tasks 1–2); Task 3 not started
- **Tasks:** 2 of 3 executed (Task 3 deferred — see below)
- **Files modified:** 9 (1 new test file, 8 view files)

## Accomplishments

- Audited every shared component (`status-pill`, `toast`, `confirm-modal`, `dashboard-card`, `welcome-banner`, `back-button`, `danger-button`, `secondary-button`) for raw-utility colour pairing correctness; fixed `secondary-button` (had zero `dark:` arm despite being used inside dark-surfaced modals throughout the app).
- Added `tests/Feature/DarkModeContrastTest.php`: 5 tests proving `<x-status-pill>`'s four palettes (green/red/amber/gray) each keep a matched light+dark class pair, plus the unrecognised-status fallback — the regression guard that stops a future blanket find/replace from silently stripping one arm again.
- Fixed the **named exam-editor bug**: `lecturer/exams/questions/_form.blade.php` (the shared question-authoring form used both for "Add a question" and inline "Edit question", rendered inside `exams/show.blade.php`'s `dark:bg-gray-800` card) used Breeze-default `x-input-label`/`x-text-input`/raw `<select>`/`<textarea>` with zero `dark:` arm — literal dark-on-dark (`text-gray-700` labels on a `dark:bg-gray-800` surface). Brought into line with the pattern already established in `exams/show.blade.php`'s own Details-tab form and `exams/create.blade.php`.
- Fixed five hand-rolled `<x-modal>` content blocks (not using `<x-confirm-modal>`) that had zero `dark:` arm on their heading/body text while sitting inside the modal shell's `dark:bg-gray-800` panel: `lecturer/sections/{edit,index,show}.blade.php`, `lecturer/subjects/edit.blade.php`, `student/subjects/{index,show}.blade.php`.
- Fixed `student/attempts/show.blade.php`'s countdown-timer badge (Alpine `badgeClasses()`, all three buckets) and per-question autosave status tags (Saved/expired/retry) — previously zero `dark:` arm on a highly visible, always-present sticky-header element during exam-taking.
- Full sweep of all 36 files matching `grep -rl 'dark:' resources/views` (minus `layouts/navigation.blade.php` and the two help pages, owned by 14-02) — most pages were already correctly paired from Phase 9's original implementation; only the items above needed correction.

## Task Commits

1. **Task 1: Component-first dark-mode audit + status-pill contrast regression guard** - `111d98b` (feat)
2. **Task 2: Page-by-page dark-mode sweep through the shipped hierarchy (exam editor checkpoint)** - `0ba5578` (feat)
3. **Task 3: Visual dark-mode legibility walkthrough** - NOT EXECUTED (deferred — see below)

**Plan metadata:** (this commit)

## Files Created/Modified

- `tests/Feature/DarkModeContrastTest.php` - New regression guard: 5 tests asserting each x-status-pill palette (green/red/amber/gray + unrecognised-status fallback) renders both its light and paired dark class.
- `resources/views/components/secondary-button.blade.php` - Added `dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-600` — this Breeze-default button had no dark arm at all despite being used inside dark-surfaced modals (take-exam submit-confirmation, class-withdrawal, section-deletion, etc.).
- `resources/views/lecturer/exams/questions/_form.blade.php` - THE named exam-editor fix. Added `dark:text-gray-300` labels and `dark:bg-gray-700/dark:border-gray-600/dark:text-white` inputs/selects/textarea; added dark variants to the Remove/Add option/Cancel links.
- `resources/views/lecturer/sections/edit.blade.php` - Delete-section confirm modal: added `dark:text-gray-100`/`dark:text-gray-400` to heading/body.
- `resources/views/lecturer/sections/index.blade.php` - Same delete-section confirm modal fix (duplicated markup, same bug).
- `resources/views/lecturer/sections/show.blade.php` - Reject-student confirm modal: same heading/body dark-arm fix.
- `resources/views/lecturer/subjects/edit.blade.php` - Unassign-lecturer confirm modal: same heading/body dark-arm fix.
- `resources/views/student/attempts/show.blade.php` - Countdown badge `badgeClasses()` (all 3 buckets) gained `dark:bg-*/dark:text-*/dark:border-*`; autosave status tags (Saved/expired/retry) gained `dark:text-green-400`/`dark:text-red-400`. Withdraw-section modal in the same file family also carried the heading/body fix (see student/subjects/*).
- `resources/views/student/subjects/index.blade.php` - Withdraw-from-class confirm modal: heading/body dark-arm fix.
- `resources/views/student/subjects/show.blade.php` - Withdraw-from-section confirm modal (legacy but still-routed `student.subjects.show` page): same fix.

## Decisions Made

- Task 3 is recorded as **deferred, not approved** — see "Task 3: Deferred — Manual Verification Required" below. This is an honest state: no PHPUnit test can prove visual legibility, and the user was AFK with no browser at hand during this execution window.
- `secondary-button.blade.php` and the countdown-badge `badgeClasses()` were touched even though not explicitly named in the plan's `files_modified` list (the plan's file list undercounted — it named 15 page files but the sweep's acceptance criteria required visiting the full `grep -rl 'dark:'` set, 36 files). Both were genuine wrong-pairing bugs discovered during the full sweep (Rule 1 — auto-fix bug), not scope creep.
- `resources/views/profile/*` (Breeze's stock account-deletion page) and `resources/views/welcome.blade.php` (stock Laravel scaffold, unreachable — the app's landing page replaces it) were deliberately left untouched. Neither is in the plan's declared sweep set (`grep -rl 'dark:' resources/views` returns zero hits for either — they have no dark-mode styling of any kind, so they render consistently light regardless of theme, which is not the dark-on-dark defect this plan targets). Flagged in "Known Gaps" below so the deferred human walkthrough can note them if they matter to the reviewer.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `secondary-button.blade.php` had zero dark: arm**
- **Found during:** Task 1 (component audit)
- **Issue:** `bg-white`/`text-gray-700` with no `dark:` counterpart at all — renders as a jarring flat-white button inside every dark-surfaced modal that uses it (take-exam confirm-submit/instructions, class withdrawal, etc.)
- **Fix:** Added `dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-600`, matching the established `confirm-modal.blade.php` cancel-button pattern
- **Files modified:** `resources/views/components/secondary-button.blade.php`
- **Verification:** Full PHPUnit suite green (446/446)
- **Committed in:** `111d98b`

**2. [Rule 1 - Bug] `lecturer/exams/questions/_form.blade.php` — named exam-editor dark-on-dark**
- **Found during:** Task 2 (exam-editor checkpoint review)
- **Issue:** Shared question-authoring form used Breeze-default `x-input-label`/`x-text-input`/raw select/textarea with zero `dark:` arm, rendered inside `exams/show.blade.php`'s `dark:bg-gray-800` card — `text-gray-700` labels directly on a dark-gray-800 surface
- **Fix:** Added `dark:text-gray-300` to every label, `dark:bg-gray-700/dark:border-gray-600/dark:text-white` to every input/select/textarea, dark variants to the Remove/Add option/Cancel controls — matching `exams/show.blade.php`'s own Details-tab form
- **Files modified:** `resources/views/lecturer/exams/questions/_form.blade.php`
- **Verification:** `grep -nE 'text-(gray-800|gray-900|black)'` across the three exam-editor files confirmed every remaining hit carries a paired `dark:` on the same class list; full PHPUnit suite green
- **Committed in:** `0ba5578`

**3. [Rule 1 - Bug] Five hand-rolled `<x-modal>` bodies with zero dark: arm**
- **Found during:** Task 2 (full sweep of `grep -rl 'dark:'` set, cross-checking every `x-modal name=` call site)
- **Issue:** `lecturer/sections/{edit,index,show}.blade.php`, `lecturer/subjects/edit.blade.php`, `student/subjects/{index,show}.blade.php` each open a hand-rolled modal (not `<x-confirm-modal>`) whose heading/body used raw `text-gray-900`/`text-gray-600` with no `dark:` counterpart, while `<x-modal>`'s shell renders `dark:bg-gray-800` — dark-on-dark on every such confirmation dialog
- **Fix:** Added `dark:text-gray-100`/`dark:text-gray-400` to match the already-correct pattern in `student/attempts/show.blade.php`'s confirm-submit/instructions modals
- **Files modified:** the 6 files listed above
- **Verification:** Full PHPUnit suite green
- **Committed in:** `0ba5578`

**4. [Rule 1 - Bug] Take-exam countdown badge + autosave status tags had zero dark: arm**
- **Found during:** Task 2 (take-exam page review, `student/attempts/show.blade.php`)
- **Issue:** Alpine `badgeClasses()` (normal/warning/red buckets) returned light-pastel-only Tailwind classes (`bg-red-50 text-red-700`, `bg-indigo-50 text-gray-800`, `bg-amber-50 text-amber-700`) with no `dark:` variant — the countdown timer, a highly visible always-present sticky-header element during exam-taking, stayed a light pastel box in an otherwise dark-themed page. Autosave "Saved"/"expired"/retry status tags had the same gap.
- **Fix:** Added matching `dark:bg-*/dark:text-*/dark:border-*` classes as additional literal keys in the `badgeClasses()` return object (required for Tailwind JIT to see them, since Alpine class-object keys must exist verbatim in source), and `dark:text-green-400`/`dark:text-red-400` to the status tags
- **Files modified:** `resources/views/student/attempts/show.blade.php`
- **Verification:** Full PHPUnit suite green, including `TakeExamPageTest`'s 6 scenarios that exercise this exact page
- **Committed in:** `0ba5578`

---

**Total deviations:** 4 auto-fixed (all Rule 1 — bug fixes to wrong/missing colour pairing, the exact defect class this plan exists to close)
**Impact on plan:** All four fixes are within FIX-02's declared scope (wrong colour pairing, semantic not syntactic) even though two of the four files weren't in the plan's literal `files_modified` list — the plan's acceptance criteria required visiting the full `grep -rl 'dark:'` sweep set (36 files), which is broader than the 15 files the plan's frontmatter enumerated. No scope creep — every fix is a genuine dark-on-dark or light-only pairing bug discovered while executing the plan's own stated audit method.

## Issues Encountered

None during Tasks 1–2. Task 3 could not be executed — see below.

## Task 3: Deferred — Manual Verification Required

**Status: DEFERRED, not approved, not faked.** FIX-02's acceptance criterion ("no text left dark-on-dark") is inherently visual; no PHPUnit test can prove it. This plan's Task 3 is a blocking `checkpoint:human-verify` gate. At the time this plan was executed, the user was AFK with no browser available to run the walkthrough. Per the plan's own instruction ("If a browser is unavailable, record it as a deferred human-verification item... never mark the visual pass 'passed' without a human confirming it"), this is recorded honestly as outstanding — not approved AFK on the basis of code review alone.

**This joins the milestone's existing deferred human-verification items** (see STATE.md → Deferred Items: the four v2.0-close items already tracked there).

### Exact steps for the user to run at final-push/review time

1. Serve the app (Herd at `APP_URL`, or `php artisan serve`) and log in.
2. Toggle dark mode on (the sun/moon button in the top bar).
3. Walk each page and confirm ALL text is legible against its surface — no dark-on-dark, no light-on-light:
   - Landing + login, both home dashboards (banner, stat cards, subject tables).
   - Lecturer: subject & class-management hub (Classes tab AND Exams tab, status pills), **the EXAM EDITOR** (Details + Questions tabs, question form, the save-warning modal) — this is the page the bug report named, inspect it closely.
   - Student: class page (status pills, taken/graded links), take-exam page (sticky top bar, timer, vertical stepper checkmarks, question body), results.
4. Optionally capture Dusk screenshots in dark mode for the record (the browser run itself is plan 14-04's manual item).
5. **Also check the two known gaps below** while walking through, in case they turn out to matter to a reviewer even though they're outside this plan's declared scope.

### Known Gaps (flagged for the walkthrough, not fixed by this plan)

| Gap | File(s) | Why left as-is |
|-----|---------|-----------------|
| No dark-mode styling at all — page stays light regardless of theme | `resources/views/profile/edit.blade.php` + `resources/views/profile/partials/*.blade.php` (Breeze's stock account-deletion/profile page) | Zero `dark:` occurrences anywhere in this file family — it was never touched by Phase 9's original dark-mode implementation, so it's not in this plan's declared sweep set (`grep -rl 'dark:' resources/views`). Not "dark-on-dark" (background stays white, text stays dark — internally legible), just theme-inconsistent. Reachable via the user-menu "Profile" link. |
| No dark-mode styling at all, media-query-based `dark:` (not the app's `class="dark"` toggle) | `resources/views/welcome.blade.php` | Stock Laravel scaffold, unreachable in this app — the guest landing page (`layouts/landing.blade.php`) replaces it entirely. Left untouched as genuinely dead code. |

**Resume signal for a follow-up agent/session:** once the user runs the walkthrough above, record their verdict ("approved" or the specific page/element still wrong) — if issues are found, they become a fast-follow fix on this branch before the milestone ships; if approved, update this item's status in STATE.md's Deferred Items table.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plans 14-02/14-03/14-04 are unblocked — none of them depend on the Task 3 visual walkthrough outcome, only on the buildable colour-pairing work (Tasks 1–2), which is complete and committed.
- The dark-mode visual walkthrough remains an outstanding deferred-manual item and should be run before the v3.0 milestone's final close, alongside the other four pre-existing deferred human-verification items.

---
*Phase: 14-delivery-dark-mode-wiki-manual-demo-data-browser-tests*
*Completed: 2026-07-18 (Tasks 1-2; Task 3 deferred)*

## Self-Check: PASSED

All 9 modified/created files confirmed present on disk; both task commits (`111d98b`, `0ba5578`) confirmed present in `git log`.

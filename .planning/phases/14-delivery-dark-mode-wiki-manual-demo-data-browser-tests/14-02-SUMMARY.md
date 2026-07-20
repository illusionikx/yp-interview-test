---
phase: 14-delivery-dark-mode-wiki-manual-demo-data-browser-tests
plan: 02
subsystem: ui
tags: [blade, tailwind, alpine, navigation, help, documentation, dark-mode]

# Dependency graph
requires:
  - phase: 14-01
    provides: dark-mode-safe token/raw-pair styling on navigation.blade.php and the app's component layer, which this plan's help button and manual cards reuse verbatim
provides:
  - "A Help icon button as an immediate sibling of the light/dark toggle in both the desktop and mobile top bars (both roles), routing to the role's manual"
  - "Two wiki-style manuals (student, lecturer) with a sticky topic index and cross-links between topic sections"
  - "Manual copy that quotes shipped phases-11-13 UI labels verbatim (verified against source, see accuracy table below)"
affects: [delivery, ui, help]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Wiki-style help page: sticky lg:sticky topic-index sidebar + card sections with id anchors, reusing the take-exam page's lg:sticky stepper-nav precedent"
    - "Utility icon button beside the theme toggle: same p-2.5 rounded-lg / text-gray-500 dark:text-gray-400 classes as the toggle, for visual pairing"

key-files:
  created: []
  modified:
    - resources/views/layouts/navigation.blade.php
    - resources/views/student/help.blade.php
    - resources/views/lecturer/help.blade.php
    - tests/Feature/HelpPageTest.php

key-decisions:
  - "Help button reuses the theme toggle's exact button classes (rounded-lg p-2.5, same gray/dark-gray palette) rather than a new style, so the two utility controls read as a matched pair"
  - "Retired the interim text 'Help' nav link entirely (both desktop and mobile) rather than keeping it alongside the new button — one entry point avoids duplicate/confusing navigation"
  - "Chose topic taxonomies mapping 1:1 to shipped screens: student (Home & dashboard, Enrolling in a class, Your subjects & class page, Taking a timed exam, Viewing your results); lecturer (Home & dashboard, Managing subjects, Class management (Classes tab), Managing exams (Exams tab), The exam editor, Grading)"
  - "For the lecturer 'Managing subjects' topic, used the dashboard's Your-subjects table + the subject edit page's Assigned-Lecturers panel (both nav-reachable) rather than the orphaned lecturer/subjects/index.blade.php page, since dashboard is the actual reachable path for subject CRUD"

requirements-completed: [UX-05, DEL-06]

# Metrics
duration: 10min
completed: 2026-07-18
status: complete
---

# Phase 14 Plan 02: Help Button + Wiki-Style Manuals Summary

**A Help icon button paired with the theme toggle in both top bars, and two rebuilt wiki-style manuals (topic index + cross-links) whose copy quotes the shipped phases-11-13 screens verbatim, replacing the stale v2.0 linear manuals.**

## Performance

- **Duration:** 10 min
- **Started:** 2026-07-18T13:33:49Z
- **Completed:** 2026-07-18T13:43:38Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Help icon button (question-mark SVG, aria-label + sr-only "Help") sits directly beside the light/dark toggle in both the desktop `gap-2` container and the mobile `gap-1` container, for both lecturer and student roles; the old text "Help" nav link is fully retired.
- Both manuals rebuilt from scratch as wiki-style documents: a sticky topic-index sidebar (5 topics for students, 6 for lecturers) plus in-prose cross-links between related topic sections, inside the existing card layout (`bg-white dark:bg-gray-800 rounded-lg shadow-sm`).
- Every quoted UI label (buttons, tabs, pills, links, aria-labels) was copied verbatim from the shipped phases-11-13 screens — see the accuracy table below.
- HelpPageTest extended from 10 to 14 tests: replaced the two stale linear-heading tests with topic-heading, topic-index-anchor, cross-link-count, and verbatim-label assertions per role; updated the two navbar-render tests for the button entry point.

## Task Commits

Each task was committed atomically:

1. **Task 1: Help button beside the theme toggle (UX-05); retire the old Help nav link** - `3077c35` (feat)
2. **Task 2: Rebuild both manuals as wiki-style, cross-linked, verbatim-label documents (DEL-06)** - `a80c2b4` (feat)

**Plan metadata:** (this commit)

## Files Created/Modified
- `resources/views/layouts/navigation.blade.php` - Help icon button added beside the theme toggle in both the desktop and mobile bars; retired text "Help" links removed from both the desktop and mobile interim-links blocks.
- `resources/views/student/help.blade.php` - Rebuilt wiki-style: sticky 5-topic index + cross-linked sections quoting the shipped student screens.
- `resources/views/lecturer/help.blade.php` - Rebuilt wiki-style: sticky 6-topic index + cross-linked sections quoting the shipped lecturer screens.
- `tests/Feature/HelpPageTest.php` - Two navbar tests updated for the button entry point; two stale linear-heading tests replaced with 4 new tests (topic headings, topic-index + cross-links, verbatim labels) per role.

## Topic Taxonomy

**Student manual** (`resources/views/student/help.blade.php`):
1. Home & dashboard (`#topic-home`)
2. Enrolling in a class (`#topic-enrolling`)
3. Your subjects & class page (`#topic-class-page`)
4. Taking a timed exam (`#topic-taking-exam`)
5. Viewing your results (`#topic-results`)

**Lecturer manual** (`resources/views/lecturer/help.blade.php`):
1. Home & dashboard (`#topic-home`)
2. Managing subjects (`#topic-subjects`)
3. Class management — Classes tab (`#topic-classes-tab`)
4. Managing exams — Exams tab (`#topic-exams-tab`)
5. The exam editor (`#topic-exam-editor`)
6. Grading (`#topic-grading`)

Cross-link counts verified via `grep -o 'href="#topic-[a-z-]*"' | sort | uniq -c`: every topic anchor is referenced 2+ times (once from the index, at least once as an in-prose cross-link) — student topic-class-page 4x, topic-results 3x; lecturer topic-exams-tab 3x, all others 2x.

## Verbatim-Label Accuracy Table

| Manual | Asserted string | Shipped view (file:line) |
|--------|------------------|---------------------------|
| Student | "Class enrollment" | `resources/views/student/subjects/index.blade.php:4` |
| Student | "Enroll" | `resources/views/student/subjects/index.blade.php:105,115,122` |
| Student | "Available" | `resources/views/student/subjects/class.blade.php:37` |
| Student | "Closed" | `resources/views/student/subjects/class.blade.php:36` |
| Student | "Submit Exam" | `resources/views/student/attempts/show.blade.php:270` |
| Student | "Your Result" | `resources/views/student/results/show.blade.php:41` |
| Student | "Awaiting grading" | `resources/views/student/results/show.blade.php:17` |
| Student | "Withdraw from Class" | `resources/views/student/subjects/index.blade.php:148` |
| Student | "Instructions" | `resources/views/student/attempts/show.blade.php:100,329` |
| Student | "Saving…" / "Saved" / "Save failed — Retry" | `resources/views/student/attempts/show.blade.php:255,256,259` |
| Student | "10 minutes remaining." | `resources/views/student/attempts/show.blade.php:369` |
| Student | "Yes, Submit" / "Keep Working" | `resources/views/student/attempts/show.blade.php:295,291` |
| Lecturer | "Move question up" / "Move question down" | `resources/views/lecturer/exams/show.blade.php:248,255` |
| Lecturer | "Move option up" / "Move option down" | `resources/views/lecturer/exams/show.blade.php:308,314` |
| Lecturer | "Shuffle options" | `resources/views/lecturer/exams/show.blade.php:295` |
| Lecturer | "Reset submissions" | `resources/views/lecturer/subjects/partials/_exams-tab.blade.php:86,91` |
| Lecturer | "Publish" / "Unpublish" | `resources/views/lecturer/subjects/partials/_exams-tab.blade.php:54,62` |
| Lecturer | "Save Score" | `resources/views/lecturer/results/show.blade.php:127` |
| Lecturer | "Assigned Lecturers" / "Assign a lecturer" / "Assign Lecturer" | `resources/views/lecturer/subjects/edit.blade.php:47,66,74` |
| Lecturer | "Create class" / "View roster" | `resources/views/lecturer/subjects/partials/_classes-tab.blade.php:14,52` |
| Lecturer | "Exam / test name" / "Available from (optional)" / "Available until (optional)" / "Save changes" | `resources/views/lecturer/exams/show.blade.php:120,139,145,154` |

## Decisions Made
- Help button styled identically to the theme toggle (same padding, colors, dark-mode classes) so both read as a matched pair of utility controls, per plan intent.
- Manuals use `lg:sticky lg:top-6` for the topic-index sidebar, mirroring the take-exam page's existing `lg:sticky lg:top-40` stepper-nav pattern already established in the codebase (dark-safe by construction, since it reuses proven classes).
- "Managing subjects" (lecturer topic) documents the dashboard's Your-subjects table (New subject / Manage / Edit / Delete) and the edit page's lecturer-assignment panel, not the standalone `lecturer/subjects/index.blade.php` page — the latter's "Manage" link points at `subjects.edit` rather than the `subjects.manage` two-tab hub and is not linked from the current nav, so it does not reflect the real reachable workflow.

## Deviations from Plan

None — plan executed exactly as written. Both tasks' acceptance criteria (help.show route referenced in both toggle containers and not in the interim block; localStorage.setItem count of 2; `href="#` count ≥ 5 in both manuals; HelpPageTest green; full suite green) were verified directly.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Both UX-05 and DEL-06 requirements are complete and verified by automated tests. The MANUAL verification item flagged in the plan's `<verification>` section (a human read-through of the manual PROSE, not just the asserted labels) remains open and non-blocking — deferred to the project's human-verification list alongside the v2.0 Phase 8 precedent. No blockers for 14-03/14-04.

---
*Phase: 14-delivery-dark-mode-wiki-manual-demo-data-browser-tests*
*Completed: 2026-07-18*

## Self-Check: PASSED

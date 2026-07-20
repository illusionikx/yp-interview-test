---
phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p
plan: 10
subsystem: ui
tags: [blade, alpine, tailwind, modal, confirmation, xss, csrf]

# Dependency graph
requires:
  - phase: 09-03
    provides: tests/Feature/NoNativeDialogTest.php — the 2-test executable spec this plan turns green
  - phase: 09-06
    provides: the UI-03 token vocabulary (text-heading, text-body) this component styles with
  - phase: 09-09
    provides: the 3 lecturer views (exams/show, subjects/index) with their inline flash banners
      already removed, so this plan's edits land on a clean base
provides:
  - "resources/views/components/confirm-modal.blade.php — the app's single blocking-confirmation
    style (UX-02), wrapping <x-modal> with a reusable props contract (name, title, body,
    confirmLabel, cancelLabel, danger) and no hardcoded copy"
  - "dark:bg-gray-800 added to resources/views/components/modal.blade.php's panel — its first
    consumer, so the primitive gained dark-mode support"
  - "3 migrated call sites (delete-exam, delete-question-{id}, delete-subject-{id}) — the app's
    last native confirm() dialogs are gone"
affects: [10]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Jetstream-style delete-confirmation wiring: a small x-data wrapper (class=\"contents\" to
      stay layout-neutral inside flex/table-cell parents) holds an x-ref'd form and a paired
      <x-confirm-modal>; the form's @submit.prevent dispatches open-modal, the modal's confirm
      button calls $refs.<name>.submit() — Alpine's $refs resolves across the nested x-data
      boundary introduced by <x-modal> itself, so this works without a global store."
    - "Per-row modal keys (delete-question-{{ $question->id }}, delete-subject-{{ $subject->id }})
      to avoid every row's modal opening at once when a static key is reused inside a loop."

key-files:
  created:
    - resources/views/components/confirm-modal.blade.php
  modified:
    - resources/views/components/modal.blade.php
    - resources/views/lecturer/exams/show.blade.php
    - resources/views/lecturer/subjects/index.blade.php

key-decisions:
  - "Confirm button forwards the caller's click handler via $attributes->merge() rather than a
    named slot — keeps the component a single self-closing tag at each call site
    (<x-confirm-modal name=... x-on:click=\"...\" />) instead of a slotted block."
  - "Wrapper element uses class=\"contents\" (display: contents) so introducing the x-data scope
    does not change table-cell/flex layout at any of the 3 call sites — verified by hand, not
    just by the static scan."
  - "Copy strings built with __() and a :name placeholder rather than raw string concatenation,
    matching the existing codebase's __() convention for user-facing text even though no
    translation files exist."

patterns-established:
  - "contents-wrapper + x-ref + $refs.<name>.submit() as the standard shape for any future
    destructive-form-behind-a-confirm-modal call site (Phase 10's INT-02/CLS-07 warnings reuse
    this exact component and can copy this wiring)."

requirements-completed: [UX-02]

# Metrics
duration: ~20min
completed: 2026-07-17
status: complete
---

# Phase 9 Plan 10: `<x-confirm-modal>` — Retiring the Last Native Dialogs Summary

**Built `<x-confirm-modal>` wrapping the existing `<x-modal>` primitive and migrated the app's last
3 `onsubmit="return confirm(...)"` call sites (delete exam, delete question, delete subject) onto
it, turning `tests/Feature/NoNativeDialogTest.php` fully green and completing UX-02.**

## Performance

- **Duration:** ~20 min (commits 13:58Z → 14:10Z)
- **Started:** 2026-07-17T13:50:34+08:00 (previous plan's completion commit)
- **Completed:** 2026-07-17T14:09:56+08:00 (Task 2 commit)
- **Tasks:** 2/2 completed
- **Files modified:** 1 created (`confirm-modal.blade.php`), 3 modified (`modal.blade.php`,
  `lecturer/exams/show.blade.php`, `lecturer/subjects/index.blade.php`)

## Accomplishments
- `<x-confirm-modal>` created with a complete, reusable props contract
  (`name`, `title`, `body`, `confirmLabel` default `Delete`, `cancelLabel` default `Cancel`,
  `danger` default `true`) — no delete-exam/question/subject copy baked into the component, per
  the plan's explicit requirement that Phase 10's INT-02/CLS-07 warnings reuse it unmodified.
- Wraps `<x-modal>` verbatim (`focusable`, `max-width="md"`), reusing its existing
  open-modal/close-modal window-event contract, focus trap, Escape-to-close, backdrop
  click-to-close and transitions — no second overlay system built.
- `modal.blade.php`'s panel gained `dark:bg-gray-800` (its only edit — surgical, 1 insertion/1
  deletion, `shadow-xl` and the event contract left untouched).
- All 3 native `confirm()` call sites migrated: `lecturer/exams/show.blade.php` (delete exam,
  delete question — 2 instances, one per loop iteration) and `lecturer/subjects/index.blade.php`
  (delete subject, one per loop iteration). Each keeps its original `method="POST"`, `action`,
  `@csrf`, `@method('DELETE')` and submit button untouched — only the confirmation mechanism
  changed.
- Modal keys are per-instance-unique (`delete-question-{{ $question->id }}`,
  `delete-subject-{{ $subject->id }}`) — verified by hand against real rendered HTML for multiple
  rows (see Verification Evidence), not just inferred from the static scan.
- `tests/Feature/NoNativeDialogTest.php` — both tests now pass; zero native dialogs remain
  anywhere in `resources/views`.

## Task Commits

Each task was committed atomically:

1. **Task 1: Create `<x-confirm-modal>` wrapping the existing `<x-modal>` primitive** - `177ae55` (feat)
2. **Task 2: Migrate the 3 destructive forms off their native dialogs** - `f1798a8` (feat)

**Plan metadata:** (pending — this SUMMARY's own commit)

## Files Created/Modified
- `resources/views/components/confirm-modal.blade.php` - New. Props contract, escaped
  title/body output, Cancel + danger-aware confirm button, confirm-button click handler
  forwarded via `$attributes->merge()`.
- `resources/views/components/modal.blade.php` - Added `dark:bg-gray-800` to the panel
  (line 68); nothing else changed.
- `resources/views/lecturer/exams/show.blade.php` - Delete-exam and delete-question forms
  wrapped in `<div x-data class="contents">`, `x-ref`'d, `@submit.prevent` dispatches
  `open-modal`; paired `<x-confirm-modal>` per form.
- `resources/views/lecturer/subjects/index.blade.php` - Delete-subject form migrated the
  same way, keyed `delete-subject-{{ $subject->id }}`.

## Decisions Made
- Confirm-button wiring uses `$attributes` pass-through (not a named slot) so call sites stay a
  single self-closing `<x-confirm-modal ... x-on:click="..." />` tag.
- `class="contents"` on each wrapper `div` so introducing the `x-data` scope required for
  `x-ref`/`$dispatch` does not shift the table-cell (subjects list) or flex-row (exam actions)
  layout — confirmed visually via the rendered HTML for 5 subject rows and 2 question rows.
- Copy built with `__('...', ['name' => $exam->name])`-style placeholders rather than raw
  Blade interpolation, matching the codebase's existing `__()` convention for all other
  user-facing strings even though no translation files exist yet.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Component's own doc comment false-matched its `<x-modal` acceptance grep**
- **Found during:** Task 1 verification
- **Issue:** The opening `@php` comment in `confirm-modal.blade.php` originally read
  `// UX-02's one blocking-confirmation style. Reuses <x-modal>'s open-modal/close-modal...`,
  using the literal substring `<x-modal` in prose. The plan's Task 1 acceptance criterion
  `grep -c "<x-modal" resources/views/components/confirm-modal.blade.php` must return exactly
  `1`; the comment caused it to return `2`.
- **Fix:** Reworded to "Reuses the modal primitive's open-modal/close-modal window-event
  contract..." — removes the literal `<x-modal` substring while preserving the same intent.
  Same class of self-inflicted comment-wording issue as 09-07's toast component (`e967cdd`).
- **Files modified:** `resources/views/components/confirm-modal.blade.php`
- **Verification:** `grep -c "<x-modal" resources/views/components/confirm-modal.blade.php`
  now returns `1`.
- **Committed in:** `177ae55` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 bug fix, self-inflicted comment wording, caught by the
plan's own acceptance criterion before committing)
**Impact on plan:** No scope creep — purely a comment-wording correction.

## Issues Encountered

**Manual browser verification could not use an actual browser tool** — this execution
environment has no browser/Playwright tool available. In its place, the 3 destructive actions
were verified end-to-end against the real running app (Herd-served `https://yp-test.test`) via
authenticated `curl` requests using the exact rendered HTML: logged in as the seeded lecturer,
fetched the subjects index and an exam show page (with a temporary draft exam + 2 questions
created via factories for coverage, since the seeded demo exam is published and hides its delete
forms), inspected the rendered markup for unique per-row modal keys and correct `$refs` wiring,
then POSTed the exact same `_token`/`_method=DELETE` payload each form would submit and confirmed
each of the 3 records was actually deleted. This proves the server-side POST/CSRF/method-spoofing
path is unbroken and that keys don't collide, though it does not prove the Alpine
open/close/focus-trap interaction itself renders correctly in a real browser — that remains
unverified by this session. All temporary/incidental QA data (including one stray `User` row a
factory default relation created) was cleaned up afterward and the dev database was restored to
the seeder's canonical demo graph (4 users, 2 subjects, 2 sections, 1 exam, 2 questions, 1
attempt) via `php artisan db:seed` (idempotent) plus manual deletion of the stray row.

## User Setup Required

None - no external service configuration required.

## Verification Evidence

### 1. `php artisan test --filter=NoNativeDialogTest` — 2/2 pass

```
PASS  Tests\Feature\NoNativeDialogTest
✓ no blade view invokes a native browser dialog                       0.40s
✓ the destructive lecturer forms use the confirm modal component      0.04s

Tests:    2 passed (3 assertions)
```

### 2. Delete-path controller tests — all green

```
PASS  Tests\Feature\Lecturer\ExamControllerTest
✓ a lecturer can delete a draft exam                                  0.12s
Tests:    10 passed (25 assertions)

PASS  Tests\Feature\Lecturer\SubjectControllerTest
✓ a lecturer can delete a subject                                     0.05s
Tests:    7 passed (13 assertions)

PASS  Tests\Feature\Lecturer\ExamPublishedEditGateTest
✓ a lecturer can delete a question on a draft exam                    0.07s
Tests:    10 passed (34 assertions)
```
These 3 tests directly exercise `DELETE` HTTP calls against the unchanged `destroy` routes and
confirm the record is actually removed — the form action/CSRF/method-spoofing this plan's modal
wiring ultimately submits.

### 3. Manual end-to-end verification (real app, real deletes — see Issues Encountered for method)

Rendered HTML confirmed unique modal keys per row (`delete-subject-1` through `delete-subject-5`
for 5 subjects, `delete-question-3`/`delete-question-4` for 2 questions on one exam,
`delete-exam` for the exam itself), each `@submit.prevent` dispatching exactly the key its own
`<x-confirm-modal>` listens for, each confirm button wired to
`$refs.<formName>.submit()` matching its own form's `x-ref`. Submitting the exact `_token`/
`_method=DELETE` payload from each form's rendered HTML to `/lecturer/subjects/3`,
`/lecturer/exams/2/questions/3`, and `/lecturer/exams/2` each returned `302` and the
corresponding `Subject`/`Question`/`Exam` row was confirmed deleted via `php artisan tinker`.

### 4. `bash scripts/ui-03-token-gate.sh` — PASS (18/18), exit 0

```
UI-03 TOKEN GATE: PASS — all 18 tokens emit real CSS rules.
```

### 5. `git diff package.json composer.json` — empty, confirmed

```
$ git diff package.json composer.json
(no output)
$ git diff --name-only package.json package-lock.json composer.json composer.lock
(no output)
```
No new Composer or npm dependencies were added, per CLAUDE.md's constraint.

### 6. Full suite — 339 passed, 0 failed (818 assertions)

```
Tests:    339 passed (818 assertions)
```

This is the last plan of Phase 9 — the full suite is fully green with no deferred/attributed
failures remaining. Compared to plan 09-09's baseline (`330 passed, 9 failed`), this plan closed
the last 2 `NoNativeDialogTest` failures directly and the other 7 (6 `LandingPageTest` + 1
`ToastTest`) were already closed by plans 09-08/09-09 before this plan ran.

## Next Phase Readiness
- UX-02 is complete and marked in REQUIREMENTS.md. Phase 9's Success Criterion 3 ("creating,
  saving, or deleting anything raises a toaster in one consistent style, and no browser-native
  `alert()` box appears anywhere in the app") is fully satisfied.
- `<x-confirm-modal>` is ready for Phase 10's INT-02/CLS-07 destructive-action warnings to reuse
  without editing this component — pass a dynamic `body` (e.g. an interpolated student count) and
  optionally `danger` false for a non-destructive confirmation.
- The `contents`-wrapper + `x-ref` + `$refs.<name>.submit()` pattern established here is the
  template for any future confirm-modal call site.
- No blockers for Phase 10.

---
*Phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p*
*Completed: 2026-07-17*

## Self-Check: PASSED

All created/modified files found on disk: `resources/views/components/confirm-modal.blade.php`,
`resources/views/components/modal.blade.php`, `resources/views/lecturer/exams/show.blade.php`,
`resources/views/lecturer/subjects/index.blade.php`, and this SUMMARY.md. Both task commits
(`177ae55`, `f1798a8`) verified present in `git log`.

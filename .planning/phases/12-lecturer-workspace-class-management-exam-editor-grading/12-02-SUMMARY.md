---
phase: 12-lecturer-workspace-class-management-exam-editor-grading
plan: 02
subsystem: ui
tags: [laravel, blade, alpine, tailwind, exam-editor, tabs]

# Dependency graph
requires:
  - phase: 12-01
    provides: per-subject two-tab hub shell (Classes/Exams tabs), Exams-tab stub linking to lecturer.exams.show
  - phase: 10
    provides: EDT-04 warn-and-void (_save-warning-modal + AttemptVoider), CLS-06 publish/unpublish, CLS-07 reset submissions
provides:
  - "lecturer.exams.show rewritten as a two-tab editor (Details default, Questions deep-linkable via ?tab=questions)"
  - "EDT-01: exam/test name field on the Details tab, validated"
  - "lecturer.exams.edit redirects (302) to lecturer.exams.show instead of rendering a standalone form"
  - "Inline per-question edit (Alpine toggle) replacing the standalone questions/edit page"
  - "ExamController::show() loads position-ordered questions/options plus the subject list the Details tab needs"
affects: [12-04, 12-05]

tech-stack:
  added: []
  patterns:
    - "Two-tab Alpine scope (x-data=\"{ tab: '{{ request('tab', 'details') }}' }\"), matching the 12-01 hub's tab pattern verbatim"
    - "Shared, parameterized _save-warning-modal partial reused across a PUT details form and MCQ/open question forms via a 'formRef' argument"

key-files:
  created:
    - tests/Feature/Lecturer/ExamEditorTest.php
  modified:
    - app/Http/Controllers/Lecturer/ExamController.php
    - resources/views/lecturer/exams/show.blade.php
    - tests/Feature/Lecturer/ExamAvailabilityTest.php
    - tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php
    - tests/Feature/Navigation/BackButtonTest.php
    - tests/Feature/NoNativeDialogTest.php

key-decisions:
  - "exams.edit stays a live route name but only ever redirects (mirrors Phase 11's SubjectController::index -> home precedent) — questions/edit.blade.php and exams/edit.blade.php are left in place, caller-less."
  - "Fixed a pre-existing bug while rewriting show.blade.php: the delete-exam confirm body referenced the non-existent $exam->name; changed to $exam->title."
  - "NoNativeDialogTest's static x-ref/$refs pairing scan was extended to recognize the shared _save-warning-modal partial's 'formRef' => '...' @include argument as proof of pairing (its x-on:click target is a Blade variable, not a literal name, so the original literal-string regex could never match it), and to exclude those PUT/POST-guarded forms from the DELETE-method-count assertion."

patterns-established:
  - "A destructive-form static scan (NoNativeDialogTest) that assumes 1:1 x-ref-to-inline-modal pairing must special-case reusable, parameterized confirm-modal partials rather than assuming every x-ref's pairing is literal in the same file."

requirements-completed: [EDT-01, EDT-02]

# Metrics
duration: 45min
completed: 2026-07-18
status: complete
---

# Phase 12 Plan 02: Tabbed Exam Editor Summary

**Merged the exam Details form and Questions list into one `lecturer.exams.show` page as two Alpine tabs, surfacing the exam/test name field on Details and folding the retired `exams.edit`/`questions.edit` pages into inline, deep-linkable tabs.**

## Performance

- **Duration:** ~45 min
- **Tasks:** 2 completed
- **Files modified:** 8 (2 production, 5 test fixes, 1 new test file)

## Accomplishments
- EDT-02: `lecturer.exams.show` is now a single two-tab editor (Details default, Questions via `?tab=questions`), replacing the separate `exams/edit.blade.php` details form and `exams/questions/edit.blade.php` per-question page.
- EDT-01: the exam/test name (`title`) field is on the Details tab, labeled "Exam / test name", required and validated by the existing `UpdateExamRequest`.
- `ExamController::show()` now eager-loads questions and options `orderBy('position')` (a precondition plan 12-05's reorder controls depend on) plus the subject list the Details form needs; `ExamController::edit()` now simply redirects to `show()`.
- Every Phase 10 affordance stayed reachable and untouched at the route/controller level: EDT-04's `_save-warning-modal` on the Details save, CLS-06 publish/unpublish, View Results, draft-only whole-exam Delete, and the CLS-07 Submissions reset panel.
- Inline per-question editing (a local Alpine `editing` boolean per question row) replaces navigating to a standalone edit page — reuses `questions/_form.blade.php` unmodified in both add and edit mode, per the plan's read-only instruction for that file.

## Task Commits

Each task was committed atomically:

1. **Task 1: Feed and render the two-tab exam editor** - `25d8bf1` (feat)
2. **Deviation fix: repoint pre-existing tests off the retired exams.edit page** - `9531f33` (fix)
3. **Task 2: Feature tests for the tabbed editor** - `259d271` (test)

**Plan metadata:** (this commit)

## Files Created/Modified
- `app/Http/Controllers/Lecturer/ExamController.php` - `show()` loads position-ordered questions/options + subjects; `edit()` redirects to `show()`
- `resources/views/lecturer/exams/show.blade.php` - rewritten as the two-tab editor (Details/Questions), Submissions panel and header actions preserved
- `tests/Feature/Lecturer/ExamEditorTest.php` - new: 10 tests covering EDT-01/EDT-02, position ordering, tab deep-linking, and reachability
- `tests/Feature/Lecturer/ExamAvailabilityTest.php` - repointed the editor-render assertion from `exams.edit` to `exams.show`
- `tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php` - repointed both edit-page warning-copy assertions from `exams.edit` to `exams.show`
- `tests/Feature/Navigation/BackButtonTest.php` - repointed the back-button assertion from the retired edit page to `exams.show`'s "Back to exams" button
- `tests/Feature/NoNativeDialogTest.php` - extended the x-ref/$refs static-pairing scan to recognize the shared `_save-warning-modal` partial's `formRef` argument

## Decisions Made
- `exams.edit`'s route name is kept alive purely as a redirect target rather than removed, mirroring the `SubjectController::index -> home` precedent from Phase 11 — this avoids a route-name churn ripple through any other view/test that still references `route('lecturer.exams.edit', ...)`.
- `questions/edit.blade.php` and `exams/edit.blade.php` are left in the repository, caller-less, per the plan's explicit instruction (mirrors the retired-but-kept `auth-session-status.blade.php` precedent from Phase 9).
- Fixed `$exam->name` → `$exam->title` in the delete-exam confirm body while rewriting `show.blade.php` — a pre-existing bug (Exam has no `name` attribute) touched directly by this rewrite; not scope creep since it lives in the exact block being reorganized.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Four pre-existing tests broke because `exams.edit` now redirects instead of rendering**
- **Found during:** Task 1 verification (full-suite run)
- **Issue:** `exams.edit` now 302s to `exams.show` per EDT-02's design, but `ExamAvailabilityTest::test_the_create_and_edit_forms_both_render_the_availability_inputs`, `ExamUpdateVoidsAttemptsTest::test_the_edit_page_warning_names_*` (x2), and `BackButtonTest::test_exams_edit_shows_a_back_to_exam_button` all issued a direct `GET lecturer.exams.edit` and asserted on rendered content (200 + specific text), which a 302 redirect response doesn't carry.
- **Fix:** Repointed each assertion at `lecturer.exams.show`, where the same content (availability inputs, attempt-count warning copy, and a retargeted "Back to exams" button) now lives. Renamed the affected test methods and updated their doc comments to explain the retarget.
- **Files modified:** tests/Feature/Lecturer/ExamAvailabilityTest.php, tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php, tests/Feature/Navigation/BackButtonTest.php
- **Verification:** `php artisan test` — all four tests green.
- **Committed in:** `9531f33`

**2. [Rule 1 - Bug] NoNativeDialogTest's static x-ref/$refs pairing scan broke on the merged Details form**
- **Found during:** Task 1 verification (full-suite run)
- **Issue:** `NoNativeDialogTest::test_each_destructive_forms_x_ref_matches_its_confirm_modals_refs_submit_call` statically scans `show.blade.php`'s raw source for `x-ref="..."` and matching `x-on:click="$refs.<name>.submit()"`, and additionally asserts one `@method('DELETE')` per x-ref found. Folding the Details form's `x-ref="editExamForm"` (a PUT form, guarded by the shared `_save-warning-modal.blade.php` partial rather than an inline `<x-confirm-modal>`) into `show.blade.php` broke both assumptions: the partial's `x-on:click` target is the dynamic `{{ $formRef }}` variable (never a literal string the regex can match, regardless of which file includes it), and the form isn't a DELETE.
- **Fix:** Extended the scan to also capture the `'formRef' => '...'` argument passed to the partial's `@include()` call as proof of ref pairing, merged into the modal-refs list; excluded those partial-guarded (non-DELETE) form refs from the `@method('DELETE')`-count assertion, since they were never destructive deletes to begin with.
- **Files modified:** tests/Feature/NoNativeDialogTest.php
- **Verification:** `php artisan test --filter=NoNativeDialogTest` — all 3 tests green.
- **Committed in:** `9531f33`

---

**Total deviations:** 2 auto-fixed (both Rule 1 — regressions in pre-existing tests directly caused by this plan's `exams.edit` redirect + Details-form merge, not new functionality).
**Impact on plan:** Both fixes repoint test assertions at the new location of the same content/behavior; no test coverage was weakened or removed, and no production behavior changed beyond what the plan specified.

## Issues Encountered
None beyond the two auto-fixed test regressions documented above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `exams.show`'s two-tab structure (Details/Questions, `request('tab', ...)` deep-linking) is in place for 12-05 to add question/option move-up/down and shuffle controls to the Questions tab.
- `ExamController::show()` already loads questions/options `orderBy('position')`, satisfying 12-05's rendering precondition with no further controller change needed.
- 12-04's exams-tab CRUD/toggle/reset work links straight into this editor via `lecturer.exams.show` — no interface changes needed on this plan's side.
- Full suite: 401 passing (baseline 391 + 10 new `ExamEditorTest` tests), 0 failing.

---
*Phase: 12-lecturer-workspace-class-management-exam-editor-grading*
*Completed: 2026-07-18*

## Self-Check: PASSED

All created files exist on disk; all task/deviation commit hashes (25d8bf1, 9531f33, 259d271) are present in git log.

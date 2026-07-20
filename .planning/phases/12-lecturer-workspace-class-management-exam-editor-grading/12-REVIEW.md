---
phase: 12-lecturer-workspace-class-management-exam-editor-grading
reviewed: 2026-07-18T11:53:24Z
depth: deep
files_reviewed: 34
files_reviewed_list:
  - app/Http/Controllers/Lecturer/ExamController.php
  - app/Http/Controllers/Lecturer/QuestionReorderController.php
  - app/Http/Controllers/Lecturer/ResultController.php
  - app/Http/Controllers/Lecturer/SectionController.php
  - app/Http/Controllers/Lecturer/SubjectManageController.php
  - app/Http/Requests/Lecturer/StoreSectionRequest.php
  - app/Http/Requests/Lecturer/UpdateSectionRequest.php
  - app/Models/Exam.php
  - app/Models/Question.php
  - app/Models/Section.php
  - database/migrations/2026_07_18_104020_add_location_to_sections.php
  - resources/views/layouts/navigation.blade.php
  - resources/views/lecturer/exams/create.blade.php
  - resources/views/lecturer/exams/show.blade.php
  - resources/views/lecturer/home.blade.php
  - resources/views/lecturer/results/index.blade.php
  - resources/views/lecturer/sections/create.blade.php
  - resources/views/lecturer/sections/edit.blade.php
  - resources/views/lecturer/subjects/manage.blade.php
  - resources/views/lecturer/subjects/partials/_classes-tab.blade.php
  - resources/views/lecturer/subjects/partials/_exams-tab.blade.php
  - routes/lecturer.php
  - tests/Feature/Lecturer/ExamAvailabilityTest.php
  - tests/Feature/Lecturer/ExamControllerTest.php
  - tests/Feature/Lecturer/ExamEditorTest.php
  - tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php
  - tests/Feature/Lecturer/ExamsTabTest.php
  - tests/Feature/Lecturer/GradingPageTest.php
  - tests/Feature/Lecturer/QuestionReorderTest.php
  - tests/Feature/Lecturer/SectionControllerTest.php
  - tests/Feature/Lecturer/SubjectManageTest.php
  - tests/Feature/Navigation/BackButtonTest.php
  - tests/Feature/Navigation/ReachabilityTest.php
  - tests/Feature/NoNativeDialogTest.php
findings:
  critical: 1
  warning: 2
  info: 2
  total: 5
status: fixed
resolved: 2026-07-18
resolution: "CR-01 fixed (reorder() clears the relation's default position-asc so move-up picks the nearest sibling; +2 regression tests for the 2+-preceding case). WR-01 fixed (unique per-form save-warning modal names — save-exam-changes-q{id}/-new — killing the window-scoped name collision). WR-02 reverted/accepted (adding ?tab=questions to question-CRUD redirects rippled to 12 assertions for a cosmetic tab-landing preference; not proportionate — reorder actions keep ?tab=questions, CRUD lands on default tab). IN-01/IN-02 left as-is (bounded per-exam summarize loop is acknowledged; redundant orderBy is harmless). Full suite: 428 passing."
---

# Phase 12: Code Review Report

**Reviewed:** 2026-07-18T11:53:24Z
**Depth:** deep
**Files Reviewed:** 34
**Status:** issues_found

## Summary

Reviewed the diff from `417237f` to `HEAD` covering the per-subject two-tab hub (`SubjectManageController`), the merged Details+Questions exam editor, question/option move-up/down and shuffle (`QuestionReorderController`), the Exams tab CRUD/toggle/reset surface, the grading-page header, and the `Section.location` field.

Authorization is sound: `SubjectManageController::show()` and `SectionController` correctly enforce subject-ownership via the `subject_user` pivot (SEC-03), and the exam-level routes' lack of per-lecturer ownership is a pre-existing, deliberately documented codebase decision (D-09), not a regression introduced by this phase. Nested-binding guards (`question->exam_id === exam->id`, `option->question_id === question->id`) are present and tested on every `QuestionReorderController` route. Reuse of `AttemptVoider::summarize()`/`void()` for the reset-submissions and save-warning flows is correctly wired, and no destructive reordering logic touches `is_correct` or runs the voider.

However, I found and empirically verified a **critical correctness bug**: `QuestionReorderController`'s "move up" swap logic silently returns the wrong sibling (jumps to the topmost item instead of the immediately preceding one) whenever 2+ items precede the one being moved, because `Exam::questions()`/`Question::options()` gained a default `orderBy('position')` this same phase, which stacks with the controller's explicit `orderByDesc('position')` into a no-op secondary sort. This is not caught by the existing test suite because every "move up" test only has exactly one candidate sibling below the moved item.

## Critical Issues

### CR-01: "Move up" swaps a question/option with the wrong sibling on 3+ item lists

**File:** `app/Http/Controllers/Lecturer/QuestionReorderController.php:43-44` (and the identical pattern at `:77-78` for options)

**Issue:** `Exam::questions()` and `Question::options()` were changed this phase (`app/Models/Exam.php:59`, `app/Models/Question.php:43`) to apply a default `->orderBy('position')` (ascending) on the relation. `moveQuestion()`'s "up" branch chains `->orderByDesc('position')` on top of that same relation:

```php
$sibling = $validated['direction'] === 'up'
    ? $exam->questions()->where('position', '<', $question->position)->orderByDesc('position')->first()
    : ...
```

Laravel's query builder *appends* order clauses rather than replacing them, so the generated SQL is `ORDER BY position asc, position desc`. Because `position` values are unique per exam, the first (ascending) key fully determines the sort — the appended `desc` never gets a chance to break a tie — so `->first()` returns the row with the **smallest** position matching the `WHERE` clause, not the largest (nearest) one as intended.

Verified empirically against the real DB (4 questions at positions 0-3, simulating "move up" on the question at position 3):
```
Expected sibling id (position 2): 111
Actual sibling id returned: 109 with position 0
```

Practical impact: clicking "move up" on any question (or MCQ option) that has **2 or more** items positioned before it swaps it all the way to the top of the list instead of moving it one slot up — a silent, wrong reorder with no error surfaced to the lecturer. The mirrored `moveOption()` method has the exact same bug for the same reason (`Question::options()` also gained the default `orderBy`). The "down" branches are unaffected because `orderBy('position')` (ascending) is idempotent with the relation's own default ascending order.

This is not caught by `QuestionReorderTest` because `test_moving_a_question_up_swaps_its_position_with_the_prior_question` only has one question below the moved one (single-candidate WHERE result, so ordering doesn't matter), and no test moves an item that has 2+ predecessors.

**Fix:** Reset the relation's default order before applying the direction-specific one, e.g. with `reorder()`:
```php
$sibling = $validated['direction'] === 'up'
    ? $exam->questions()->where('position', '<', $question->position)->reorder('position', 'desc')->first()
    : $exam->questions()->where('position', '>', $question->position)->orderBy('position')->first();
```
Apply the same fix to `moveOption()` (line ~78). Add a regression test with 3+ preceding items (e.g., 4 questions, move the last one up, assert it swaps with the *third*, not the first).

## Warnings

### WR-01: Duplicate `save-exam-changes` modal name across the merged editor page can open/leak the wrong confirm dialog

**File:** `resources/views/lecturer/exams/show.blade.php:159,325,337` via `resources/views/lecturer/exams/_save-warning-modal.blade.php` and `resources/views/lecturer/exams/questions/_form.blade.php:112`

**Issue:** `_save-warning-modal.blade.php` always renders `<x-confirm-modal name="save-exam-changes" ...>`, and `x-modal`'s open/close wiring is a **window-scoped** event (`x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"` in `resources/views/components/modal.blade.php:42`). Before this phase, Details and Questions were separate pages, so at most one instance of this modal existed per page load. After 12-02's merge, `exams/show.blade.php` now includes this same-named modal **once for the Details form** (line 159), **once per question's inline edit form** (line 325, inside `_form.blade.php`, one per question in `@forelse ($exam->questions as $question)`), and **once for the "Add a question" form** (line 337) — all on one page, all sharing the literal string `"save-exam-changes"`.

Because the listener is `window`-scoped and matched purely by string equality, dispatching `open-modal, 'save-exam-changes'` from any one form (e.g., submitting the Details form with existing attempts) sets `show = true` on **every** matching modal instance simultaneously, including ones nested inside currently-hidden containers (`x-show="tab === 'questions'"`, `x-show="editing"`). Since a `display:none` ancestor suppresses a descendant's own `x-show` visibility, this is not immediately visible — but the internal `show` state has already flipped. Switching tabs or opening a question's inline edit afterward can surface an already-"open" modal the lecturer never triggered, whose confirm button is wired to a *different* `$refs` form (e.g., the empty "Add a question" form) than the one the lecturer actually meant to confirm — risking a stale/empty submission instead of the intended save.

**Fix:** Give each inclusion a unique modal name, e.g. pass and interpolate a suffix through `_save-warning-modal.blade.php`:
```php
@include('lecturer.exams._save-warning-modal', [
    'exam' => $exam,
    'attemptCounts' => $attemptCounts,
    'formRef' => 'questionForm',
    'modalName' => 'save-exam-changes-question-' . ($question->id ?? 'new'),
])
```
and use `$modalName` (defaulting to `'save-exam-changes'` for back-compat) as both the `$dispatch(...)` target and the `<x-confirm-modal name="...">` value.

### WR-02: Adding/editing/deleting a question redirects off the Questions tab

**File:** `app/Http/Controllers/Lecturer/ExamQuestionController.php:74,170,212` (redirect targets), contrasted with `app/Http/Controllers/Lecturer/QuestionReorderController.php:59,92,115`

**Issue:** 12-02 merged the standalone Details/Questions pages into one tabbed `exams.show` view keyed off `?tab=` (`x-data="{ tab: '{{ request('tab', 'details') }}' }"`, `show.blade.php:71`). `QuestionReorderController`'s three actions correctly preserve the tab on redirect (`route('lecturer.exams.show', $exam).'?tab=questions'`). `ExamQuestionController::store()/update()/destroy()` (the add/edit/delete-question actions actually used for editing question content) were not updated to match — they all redirect to plain `route('lecturer.exams.show', $exam)`, which defaults back to the Details tab. A lecturer who adds a question, saves an edited question, or deletes a question is bounced off the Questions tab they were just working in, back to Details — an inconsistent, regressed workflow relative to the reorder actions shipped in the same phase.

**Fix:** Append `.'?tab=questions'` to the three redirects in `ExamQuestionController`, matching `QuestionReorderController`'s convention.

## Info

### IN-01: `attemptCountsByExam` runs one `AttemptVoider::summarize()` query per exam in a loop

**File:** `app/Http/Controllers/Lecturer/SubjectManageController.php:64-66`

**Issue:** `$exams` is already loaded with `withCount(['attempts', 'attempts as graded_attempts_count' => ...])` (one query, bounded), but `attemptCountsByExam` re-derives overlapping data (total/graded/notYetGraded) via a separate grouped `summarize()` query *per exam* in `mapWithKeys`. This is explicitly acknowledged in the doc comment as "a handful of exams per subject", and each iteration is an O(1)-row aggregate rather than a per-attempt scan, so it's bounded rather than a classic N+1 — but it is still N queries for data largely derivable from the `withCount` already run, or from one additional grouped-by-`(exam_id, status)` query across all of `$exams`' ids at once.

**Fix (optional):** Replace the loop with a single grouped query, e.g. `Attempt::whereIn('exam_id', $exams->pluck('id'))->selectRaw('exam_id, status, count(*) as aggregate')->groupBy('exam_id', 'status')->get()->groupBy('exam_id')`, then build the per-exam summary array from that.

### IN-02: Redundant duplicate `orderBy('position')` in eager-load closures

**File:** `app/Http/Controllers/Lecturer/ExamController.php:76-80`

**Issue:** `Exam::questions()` and `Question::options()` now apply `orderBy('position')` by default (`app/Models/Exam.php:59`, `app/Models/Question.php:43`), but `ExamController::show()`'s `load([...])` closures re-specify `->orderBy('position')` again on both relations. Harmless (produces `ORDER BY position, position`, still correct since ascending is idempotent) but dead weight now that the model-level default exists — worth trimming for clarity so a future reader doesn't wonder whether the two orderings could ever disagree.

**Fix:** Drop the redundant closures now that the relations self-order:
```php
$exam->load(['subject', 'questions.options']);
```

---

_Reviewed: 2026-07-18T11:53:24Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: deep_

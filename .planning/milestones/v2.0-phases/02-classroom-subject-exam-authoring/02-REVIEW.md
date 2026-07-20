---
phase: 02-classroom-subject-exam-authoring
reviewed: 2026-07-15T00:00:00Z
depth: deep
files_reviewed: 38
files_reviewed_list:
  - app/Http/Controllers/Lecturer/SubjectController.php
  - app/Http/Controllers/Lecturer/ClassroomController.php
  - app/Http/Controllers/Lecturer/ClassroomRosterController.php
  - app/Http/Controllers/Lecturer/ExamController.php
  - app/Http/Controllers/Lecturer/ExamQuestionController.php
  - app/Http/Requests/Lecturer/StoreSubjectRequest.php
  - app/Http/Requests/Lecturer/UpdateSubjectRequest.php
  - app/Http/Requests/Lecturer/StoreClassroomRequest.php
  - app/Http/Requests/Lecturer/UpdateClassroomRequest.php
  - app/Http/Requests/Lecturer/AssignStudentRequest.php
  - app/Http/Requests/Lecturer/StoreExamRequest.php
  - app/Http/Requests/Lecturer/UpdateExamRequest.php
  - app/Http/Requests/Lecturer/StoreQuestionRequest.php
  - app/Http/Requests/Lecturer/UpdateQuestionRequest.php
  - routes/lecturer.php
  - resources/views/lecturer/home.blade.php
  - resources/views/lecturer/classrooms/create.blade.php
  - resources/views/lecturer/classrooms/edit.blade.php
  - resources/views/lecturer/classrooms/index.blade.php
  - resources/views/lecturer/exams/create.blade.php
  - resources/views/lecturer/exams/edit.blade.php
  - resources/views/lecturer/exams/index.blade.php
  - resources/views/lecturer/exams/show.blade.php
  - resources/views/lecturer/exams/questions/_form.blade.php
  - resources/views/lecturer/exams/questions/edit.blade.php
  - resources/views/lecturer/subjects/create.blade.php
  - resources/views/lecturer/subjects/edit.blade.php
  - resources/views/lecturer/subjects/index.blade.php
  - database/factories/SubjectFactory.php
  - database/factories/ExamFactory.php
  - database/factories/QuestionFactory.php
  - database/factories/OptionFactory.php
  - database/factories/ClassroomFactory.php
  - database/factories/UserFactory.php
  - app/Models/Classroom.php
  - app/Models/Subject.php
  - app/Models/Exam.php
  - app/Models/Question.php
  - app/Models/Option.php
findings:
  critical: 2
  warning: 3
  info: 3
  total: 8
status: issues_found
---

# Phase 2: Code Review Report

**Reviewed:** 2026-07-15
**Depth:** deep (cross-file, incl. migrations/FK behavior and existing tests)
**Files Reviewed:** 38
**Status:** issues_found

## Summary

Reviewed the Phase 2 lecturer authoring surface: Subject/Classroom/Exam/Question CRUD, the student-roster endpoint, all associated Form Requests, routes, Blade views, factories, and the models/migrations they touch. The mass-assignment guardrails called out in the code comments (`created_by` stamped server-side, `is_correct` derived server-side, `classroom_id` only writable through `AssignStudentRequest`, roster IDOR check on `destroy`) are implemented correctly and match the source comments. `role:lecturer` gates the whole route group, and `is_published` gates edit/delete for exams and questions at the Form Request / controller level as documented.

However, two BLOCKER-level defects undermine those very invariants: (1) `ExamQuestionController` never verifies that the bound `Question` actually belongs to the bound `Exam`, so the "a published exam's questions are immutable" gate (EXM-05/D-06) can be bypassed entirely by pairing a real, published exam's question ID with an unrelated draft exam's ID in the URL; and (2) `SubjectController::destroy()` has no guard against `exams.subject_id`'s `cascadeOnDelete()`, so deleting a Subject silently deletes every Exam under it — published or not — bypassing the same invariant from a completely different angle and causing outright data loss. A further High-severity defect in the MCQ options `after()` validator lets a crafted (non-UI) request produce a stored MCQ question with **zero** correct options, silently breaking the "exactly one correct" guarantee the code explicitly claims to enforce.

## Critical Issues

### CR-01: `ExamQuestionController` never checks `Question belongs to Exam` — the draft-only edit/delete gate can be bypassed via a mismatched route pair

**File:** `app/Http/Controllers/Lecturer/ExamQuestionController.php:68-138`
**File:** `app/Http/Requests/Lecturer/UpdateQuestionRequest.php:17-23`
**File:** `routes/lecturer.php:24-30`

**Issue:** The route `exams/{exam}/questions/{question}` binds `$exam` and `$question` **independently** — there is no `Route::resource(...)->scoped(...)` and no in-controller check that `$question->exam_id === $exam->id`. Every gate that is supposed to protect a published exam's questions (`UpdateQuestionRequest::authorize()`, and the inline `abort_if($exam->is_published, 403)` in `destroy()`) only inspects **the exam found in the URL**, not the exam the question actually belongs to.

Concretely: given a published exam `#7` with question `#99`, and any unpublished/draft exam `#5` owned by the same lecturer (trivial to create — exams start as drafts), a lecturer can:

```
PUT /lecturer/exams/5/questions/99   (exam 5 is draft ⇒ authorize() passes)
DELETE /lecturer/exams/5/questions/99  (abort_if($exam->is_published) checks exam 5, not exam 7)
```

`UpdateQuestionRequest::authorize()` returns `! $this->route('exam')->is_published` — i.e. checks exam `#5`, but the controller then does `$question->update(...)` / `$question->delete()` on question `#99`, which actually belongs to published exam `#7`. This directly defeats the "a published exam is immutable" invariant (EXM-05/D-06) that every docblock in this file claims is authoritative ("a question cannot be edited on a published exam even via a forged/replayed PUT/PATCH" — this is false as written). The same gap exists in `edit()`, which has no gate at all (GET requests bypass the Form Request), letting anyone view a published exam's question-edit form through an unrelated draft exam URL.

This is not a hypothetical: any authenticated lecturer already has an unpublished exam available (or can create one in one request), so the bypass requires no special privilege beyond the `role:lecturer` group.

**Fix:** Verify the parent/child relationship before doing anything, in both the Form Requests and the controller (belt-and-braces, since GET `edit()` has no Form Request):

```php
// UpdateQuestionRequest / StoreQuestionRequest::authorize()
public function authorize(): bool
{
    $exam = $this->route('exam');
    $question = $this->route('question'); // null on store

    if ($question !== null && $question->exam_id !== $exam->id) {
        return false;
    }

    return ! $exam->is_published;
}
```

```php
// ExamQuestionController::edit() / destroy()
public function edit(Exam $exam, Question $question): View
{
    abort_unless($question->exam_id === $exam->id, 404);
    $question->load('options');
    return view('lecturer.exams.questions.edit', compact('exam', 'question'));
}

public function destroy(Exam $exam, Question $question): RedirectResponse
{
    abort_unless($question->exam_id === $exam->id, 404);
    abort_if($exam->is_published, 403);
    $question->delete();
    ...
}
```

Add a regression test asserting that `PUT/DELETE lecturer/exams/{draftExam}/questions/{questionOfPublishedExam}` returns 404/403 and does not mutate the question.

---

### CR-02: `SubjectController::destroy()` has no guard against cascading deletion of Exams — silently bypasses the published-exam deletion gate and causes data loss

**File:** `app/Http/Controllers/Lecturer/SubjectController.php:63-68`
**File:** `database/migrations/2026_07_15_100005_create_exams_table.php:16`

**Issue:** `exams.subject_id` is declared `->constrained()->cascadeOnDelete()`. `questions.exam_id` and `options.question_id` are also `cascadeOnDelete()`. `SubjectController::destroy()` calls `$subject->delete()` with **no check at all** — no confirmation of attached exams, no distinction between draft and published exams:

```php
public function destroy(Subject $subject): RedirectResponse
{
    $subject->delete();
    ...
}
```

Meanwhile `ExamController::destroy()` explicitly protects published exams: `abort_if($exam->is_published, 403);`. Deleting the exam's parent Subject completely sidesteps that protection — a lecturer (or an accidental click, since the confirm dialog just says "Delete this subject?" with no mention of cascading impact) can delete a Subject that has a **published** exam attached, and the DB will silently cascade-delete that Exam plus all of its Questions and Options. This is both a business-rule bypass of EXM-05 and an unguarded, irreversible data-loss path with no warning to the user. `SubjectControllerTest::test_a_lecturer_can_delete_a_subject` only exercises deleting a bare subject with no exams, so this path is untested.

**Fix:** Block deletion (or at minimum block deletion while any attached exam is published) before calling `delete()`:

```php
public function destroy(Subject $subject): RedirectResponse
{
    abort_if($subject->exams()->where('is_published', true)->exists(), 422);

    $subject->delete();

    return redirect()->route('lecturer.subjects.index')->with('status', 'Subject deleted.');
}
```

Consider whether Subject deletion should be blocked entirely while *any* exam (draft included) references it, for consistency with how `ExamController`/`ExamQuestionController` treat draft content as safely removable but published content as immutable. Also update the confirm dialog copy in `subjects/index.blade.php:38` to warn about cascading exam deletion.

## Warnings

### WR-01: MCQ `correct_option` validated against raw (possibly sparse) array keys but persisted against a re-indexed collection — a crafted request can save an MCQ question with zero correct options

**File:** `app/Http/Requests/Lecturer/StoreQuestionRequest.php:66-85`
**File:** `app/Http/Requests/Lecturer/UpdateQuestionRequest.php:67-86`
**File:** `app/Http/Controllers/Lecturer/ExamQuestionController.php:41-54, 104-117`

**Issue:** `after()` validates the correct-option index against the **raw** `options` array as submitted:

```php
$options = $this->input('options', []);
$correct = $this->input('correct_option');
if (! is_numeric($correct) || ! array_key_exists((int) $correct, $options)) { ... }
```

but the controller persists using a **re-indexed** collection:

```php
collect($request->validated('options'))->values()->map(fn ($option, $i) => [
    'is_correct' => $i === $correct, ...
])
```

If a request supplies non-contiguous array keys — e.g. `options[0][body]=A&options[5][body]=B&correct_option=5` — `rules()` (`array`, `min:2`) and `after()` (`array_key_exists(5, $options)` ⇒ true) both pass, since they only look at the raw associative array. The controller's `->values()` then re-indexes those two options to keys `0` and `1`; `$correct = 5` no longer matches **either** index, so `is_correct` is `false` for every stored option. The question is persisted with `type=mcq` and **no correct answer at all**, silently violating the exactly-one-correct invariant the code comments claim is guaranteed ("Pattern 1 ... this hook only needs to confirm a *valid* index was chosen — the entire 'exactly one correct' rule collapses to this single check", T-02-MCQ). The normal Alpine-rendered form never produces sparse keys (it always uses the sequential loop index), so this only reaches production via a direct/forged POST — but since any lecturer already has full authoring access, this is a reachable, unauthenticated-adjacent data-integrity bug, not a purely theoretical one, and none of the existing MCQ tests cover it.

**Fix:** Normalize the options array to contiguous 0-based keys *before* validating it, so `rules()`/`after()` and the controller always agree on indices:

```php
protected function prepareForValidation(): void
{
    if ($this->input('points') === null || $this->input('points') === '') {
        $this->merge(['points' => 1]);
    }

    if (is_array($this->input('options'))) {
        $this->merge(['options' => array_values($this->input('options'))]);
    }
}
```

## Info

### IN-01: `Route::resource('subjects', ...)` / `Route::resource('classrooms', ...)` register a `show` route with no controller method — hitting it throws an unhandled error

**File:** `routes/lecturer.php:16-17`

**Issue:** `Route::resource('subjects', SubjectController::class)` and `Route::resource('classrooms', ClassroomController::class)` register the full 7-route RESTful set, including `GET /lecturer/subjects/{subject}` and `GET /lecturer/classrooms/{classroom}` (`subjects.show`/`classrooms.show`). Neither `SubjectController` nor `ClassroomController` defines a `show()` method (both only implement `index`/`create`/`store`/`edit`/`update`/`destroy`), and no view or link in the app references `subjects.show`/`classrooms.show`. Navigating to either URL directly (a guessable, authenticated-only URL) throws an unhandled `Error` (undefined method), surfacing as a 500 to the lecturer instead of a clean 404.

**Fix:**
```php
Route::resource('subjects', SubjectController::class)->except('show');
Route::resource('classrooms', ClassroomController::class)->except('show');
```

### IN-02: `position` column on `questions`/`options` is written but never used for ordering

**File:** `app/Models/Exam.php:43-46`
**File:** `app/Models/Question.php:37-40`
**File:** `app/Http/Controllers/Lecturer/ExamQuestionController.php:35, 51, 115`

**Issue:** `Question::options()` and `Exam::questions()` have no `orderBy('position')`, so every place that renders `$exam->questions` / `$question->options` (show.blade.php, questions/edit form) relies on implicit DB row order rather than the `position` value that's explicitly computed and stored on write (`(int) $exam->questions()->max('position') + 1`, and `$i` per-option index). This happens to render correctly today only because auto-increment IDs currently track insertion order 1:1; it will silently produce out-of-order display the moment questions/options are recreated non-sequentially (e.g. `update()` already deletes and fully recreates a question's options on every save) or if a future reorder feature is added.

**Fix:** Add explicit ordering to both relationships:
```php
// Exam::questions()
public function questions(): HasMany
{
    return $this->hasMany(Question::class)->orderBy('position');
}

// Question::options()
public function options(): HasMany
{
    return $this->hasMany(Option::class)->orderBy('position');
}
```

### IN-03: Subject/roster queries embedded directly in Blade views instead of passed from the controller

**File:** `resources/views/lecturer/classrooms/create.blade.php:23`
**File:** `resources/views/lecturer/classrooms/edit.blade.php:33, 48-52`

**Issue:** `ClassroomController::create()`/`edit()` pass no `$subjects` (unlike `ExamController::create()`/`edit()`, which correctly pass `$subjects` from the controller). Instead, `classrooms/create.blade.php` and `classrooms/edit.blade.php` run `\App\Models\Subject::orderBy('name')->get()` directly inline, and `classrooms/edit.blade.php` additionally builds the roster/unassigned-student lists (`$classroom->users()->where(...)`, `\App\Models\User::where(...)->whereNull('classroom_id')->get()`) inside a `@php` block in the view. This is inconsistent with the rest of the codebase's pattern and makes the view harder to test/reason about independently of the controller.

**Fix:** Move these queries into `ClassroomController::create()`/`edit()` and pass them as view data, mirroring `ExamController`.

---

_Reviewed: 2026-07-15_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: deep_

---

## Orchestrator Resolution (2026-07-15)

Each finding verified against source before action. Fixes committed in `00e77c8` with a 5-test regression suite (`tests/Feature/Lecturer/Phase2ReviewFixesTest.php`); full suite 121/305 green.

| Finding | Severity | Action |
|---------|----------|--------|
| Nested `{exam}/{question}` binding not scoped → published-gate bypass | critical | **FIXED** — `abort_unless($question->exam_id === $exam->id, 404)` added to `ExamQuestionController::edit/update/destroy`. Regression tests cover update + delete via mismatched exam URL → 404. |
| `SubjectController::destroy` cascades into (published) exams | critical | **FIXED** — `destroy()` refuses when `$subject->exams()->exists()`, redirecting with a message. Regression test confirms exams survive. |
| Sparse MCQ option keys → save with zero correct options | high | **FIXED** — `prepareForValidation()` in Store/UpdateQuestionRequest reindexes `options` via `array_values()` so validation and the controller's `->values()` persist agree. Two regression tests (reject sparse-invalid; persist reindexed-valid). |
| `subjects.show`/`classrooms.show` registered without a method → 500 | warning | **FIXED** — `->except(['show'])` on both resource routes. |
| `position` columns unused for ordering; queries in Blade views | info | **DEFERRED** — cosmetic; no correctness impact. Ordering can adopt `position` later; view queries can move to controllers in a polish pass. |

No blocker findings (the Phase-1 git-token blocker is tracked separately and unaffected here).

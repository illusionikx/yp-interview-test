---
phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix
reviewed: 2026-07-16T00:00:00Z
depth: standard
files_reviewed: 31
files_reviewed_list:
  - app/Enums/EnrollmentStatus.php
  - app/Http/Controllers/Lecturer/ExamAssignmentController.php
  - app/Http/Controllers/Lecturer/ExamController.php
  - app/Http/Controllers/Lecturer/SectionController.php
  - app/Http/Controllers/Lecturer/SubjectLecturerController.php
  - app/Http/Controllers/Student/ExamController.php
  - app/Http/Requests/Lecturer/AssignExamRequest.php
  - app/Http/Requests/Lecturer/AssignLecturerRequest.php
  - app/Http/Requests/Lecturer/StoreSectionRequest.php
  - app/Http/Requests/Lecturer/UpdateSectionRequest.php
  - app/Models/Enrollment.php
  - app/Models/Exam.php
  - app/Models/Section.php
  - app/Models/Subject.php
  - app/Models/User.php
  - app/Policies/AttemptPolicy.php
  - app/Policies/ExamPolicy.php
  - database/factories/SectionFactory.php
  - database/migrations/2026_07_15_100001_create_subjects_table.php
  - database/migrations/2026_07_15_100002_create_sections_table.php
  - database/migrations/2026_07_15_100004_create_subject_user_table.php
  - database/migrations/2026_07_15_100008_create_exam_section_table.php
  - database/migrations/2026_07_15_100011_create_enrollments_table.php
  - database/seeders/DatabaseSeeder.php
  - routes/lecturer.php
  - resources/js/app.js
  - resources/views/components/status-pill.blade.php
  - resources/views/layouts/app.blade.php
  - resources/views/layouts/navigation.blade.php
  - resources/views/student/attempts/show.blade.php
  - resources/views/lecturer/exams/show.blade.php
  - tailwind.config.js
findings:
  critical: 1
  warning: 4
  info: 1
  total: 6
status: issues_found
---

# Phase 07: Code Review Report

**Reviewed:** 2026-07-16T00:00:00Z
**Depth:** standard
**Files Reviewed:** 31
**Status:** issues_found

## Summary

Reviewed the v2.0 foundation phase: the subject-scoped section schema break, `subject_user`/`enrollments` pivots, the Flowbite admin theme + dark mode, and the FIX-01 answered-count fix. The per-subject section-ownership work (`StoreSectionRequest`/`UpdateSectionRequest`/`AssignLecturerRequest`, `SectionController::destroy`, `SubjectLecturerController::destroy`) is correctly implemented — no residual `return true;` write-path gap was found there, and `ExamPolicy`/`AttemptPolicy` correctly funnel through the single `Exam::scopeVisibleTo()` predicate with no divergence between the student list and the takeable gate.

However, tracing the exam-to-section assignment path (`ExamAssignmentController` + `AssignExamRequest` + the section checkbox list rendered by `ExamController::show()`) surfaced a real cross-subject data-integrity gap: nothing anywhere in that chain constrains an exam to only be assignable to sections of its own subject, and `Exam::scopeVisibleTo()` — the single predicate that gates both the student exam list and direct access — never checks subject membership either. This is a genuine breach of the project's stated core value ("the right exam reaches the right student") and is reachable through the normal UI, not just a crafted request. See CR-01.

Four warning-level issues (a read-level authorization inconsistency, a missing validation guard that surfaces as an unhandled 500, a create-time race condition, and an incomplete dark-mode rollout on the exam-taking page) and one info-level N+1 round out the findings.

## Critical Issues

### CR-01: Exam-to-section assignment has no subject-consistency check — cross-subject exam leakage

**File:** `app/Http/Controllers/Lecturer/ExamAssignmentController.php:26-31`, `app/Http/Requests/Lecturer/AssignExamRequest.php:32-38`, `app/Models/Exam.php:82-90`, `resources/views/lecturer/exams/show.blade.php:119-130`

**Issue:** `ExamAssignmentController::update()` calls `$exam->sections()->sync($request->validated('section_ids', []))` with no check that the submitted section ids belong to the exam's own `subject_id`. `AssignExamRequest::rules()` only validates `exists:sections,id` — any section id in the whole system passes. The assignment UI (`lecturer/exams/show.blade.php`) actively offers every section from every subject in one flat checkbox list (`@forelse ($sections as $section) ... {{ $section->subject->name }} · {{ $section->name }}`), and `ExamController::show()` populates `$sections` with `Section::orderBy(...)->get()` — unfiltered by subject.

Because `Exam::scopeVisibleTo()` (the single predicate driving both the student exam list and `ExamPolicy::takeable()`/`AttemptPolicy`) only checks `is_published` + active enrollment in an assigned section — never subject membership — a Mathematics exam assigned (deliberately or by a misclick, since nothing in the UI groups/restricts choices) to a Science section becomes fully visible and takeable by every student actively enrolled in that Science section. This directly violates the project's stated core value: "the right exam reaches the right student."

**Fix:** Constrain assignment to same-subject sections at both the query and validation layer, e.g.:
```php
// AssignExamRequest::rules()
'section_ids.*' => [
    'integer',
    'distinct',
    Rule::exists('sections', 'id')->where('subject_id', $this->route('exam')->subject_id),
],
```
and filter the section list the view renders:
```php
// ExamController::show()
$sections = Section::where('subject_id', $exam->subject_id)
    ->orderBy('year')->orderBy('semester')->orderBy('sequence')->get();
```

## Warnings

### WR-01: Section create/edit GET routes have no per-subject ownership check

**File:** `app/Http/Controllers/Lecturer/SectionController.php:33-36` (create), `:64-69` (edit)

**Issue:** `store()`/`update()`/`destroy()` all correctly gate on subject-lecturer ownership (via `StoreSectionRequest`/`UpdateSectionRequest::authorize()` and the inline `abort_unless` in `destroy()`), consistent with the SEC-03 intent documented in those Form Requests ("a lecturer NOT assigned must get 403, not merely a hidden UI affordance"). But `create()` and `edit()` — the GET routes that render those forms — perform no such check at all. `edit()` only verifies `$section->subject_id === $subject->id` (a 404 guard against a mismatched URL, not an ownership guard). A lecturer who is not assigned to a subject can still `GET` the create/edit form for any of its sections and see its capacity/enrollment-window data, even though the same lecturer's `POST`/`PUT` would be rejected by the Form Request.

**Fix:** Add the same ownership check to the GET actions for consistency:
```php
public function create(Subject $subject): View
{
    abort_unless($subject->lecturers()->whereKey(auth()->id())->exists(), 403);
    return view('lecturer.sections.create', compact('subject'));
}

public function edit(Subject $subject, Section $section): View
{
    abort_unless($section->subject_id === $subject->id, 404);
    abort_unless($subject->lecturers()->whereKey(auth()->id())->exists(), 403);
    return view('lecturer.sections.edit', compact('subject', 'section'));
}
```

### WR-02: UpdateSectionRequest allows an edit to collide with the (subject_id, year, semester, sequence) unique index, causing an unhandled 500

**File:** `app/Http/Requests/Lecturer/UpdateSectionRequest.php:35-44`, `app/Http/Controllers/Lecturer/SectionController.php:74-81`

**Issue:** `sections` has `unique(['subject_id', 'year', 'semester', 'sequence'])`. `sequence` is not editable, but `year`/`semester` are. If a lecturer edits a section's `year`/`semester` to a combination that already has another section with the same `sequence` under that subject (e.g. changing section #2's year/semester to match section #1's, both `sequence = 1` vs `2` — or more directly, two sections both at `sequence = 1` for different terms being edited to the same term), the `update()` call throws an uncaught `QueryException` (Laravel's default 500 page) instead of a friendly validation error.

**Fix:** Add a `Rule::unique` scoped to the other three columns, ignoring the current row:
```php
'year' => ['required', 'integer', 'min:2000', 'max:2100', Rule::unique('sections')
    ->where('subject_id', $this->route('subject')->id)
    ->where('semester', $this->input('semester'))
    ->where('sequence', $this->route('section')->sequence)
    ->ignore($this->route('section')->id)],
```
(or an equivalent `withValidator` closure check).

### WR-03: Section sequence auto-increment is not concurrency-safe

**File:** `app/Http/Controllers/Lecturer/SectionController.php:47-56`

**Issue:** `$sequence = Section::where('subject_id', ...)->where('year', ...)->where('semester', ...)->max('sequence') + 1;` is a read-then-write with no locking. Two concurrent `store()` requests for the same `(subject_id, year, semester)` can both read the same `max('sequence')` and attempt to insert the same next value, and the second insert fails the `unique(['subject_id','year','semester','sequence'])` constraint with an unhandled `QueryException` (500) rather than a retry or a friendly error.

**Fix:** Wrap the read+insert in a transaction with a locking read (`->lockForUpdate()`), or catch the unique-violation and retry once:
```php
DB::transaction(function () use (...) {
    $sequence = Section::where(...)->lockForUpdate()->max('sequence') + 1;
    Section::create([...]);
});
```

### WR-04: Dark mode was not applied to the student exam-taking page

**File:** `resources/views/student/attempts/show.blade.php` (throughout, e.g. lines 54-65, 76-80, 99-101, 210-233)

**Issue:** The rest of this phase's reviewed views (`layouts/app.blade.php`, `layouts/navigation.blade.php`, `components/status-pill.blade.php`, `lecturer/exams/show.blade.php`) were fully converted to the Flowbite dark theme with `dark:` variants throughout. `student/attempts/show.blade.php` — the highest-stakes page in the app (the timed exam-taking screen) — was left entirely on the old light-only classes (`bg-white shadow`, `text-gray-800`, `text-gray-500`, no `dark:` variant anywhere). Since the outer layout (`<div class="min-h-screen bg-gray-50 dark:bg-gray-900">`) does switch to a dark background, a student with dark mode enabled will see solid-white sticky header/question cards floating on a dark page background — a jarring, half-migrated UI on the one page students actually spend time on during an exam.

**Fix:** Add `dark:bg-gray-800`, `dark:text-gray-200`/`dark:text-gray-400`, `dark:shadow-none`/border equivalents to the sticky header, question cards, and the "nothing to answer" empty state, mirroring the pattern already used in `lecturer/exams/show.blade.php`.

## Info

### IN-01: N+1 query rendering the section-assignment checkbox list

**File:** `app/Http/Controllers/Lecturer/ExamController.php:56-62`, `resources/views/lecturer/exams/show.blade.php:125`

**Issue:** `ExamController::show()` loads `$sections = Section::orderBy(...)->get()` without eager-loading `subject`, but the view accesses `$section->subject->name` for every row, triggering one query per section rendered. Noted only because it sits directly in the same code path as CR-01, where the fix (scoping `$sections` to the exam's own subject) will also shrink the result set; performance issues are out of scope for this review otherwise.

**Fix:** `Section::with('subject')->where('subject_id', $exam->subject_id)->orderBy(...)->get()` — combines naturally with the CR-01 fix.

---

_Reviewed: 2026-07-16T00:00:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_

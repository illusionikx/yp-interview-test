---
phase: 11-navigation-restructure-landing-hierarchy-dashboard-subjects-
reviewed: 2026-07-18T09:50:04Z
depth: standard
files_reviewed: 14
files_reviewed_list:
  - app/Http/Controllers/Lecturer/HomeController.php
  - app/Http/Controllers/Student/HomeController.php
  - app/Http/Controllers/Lecturer/SubjectController.php
  - app/Http/Controllers/Student/SubjectBrowseController.php
  - resources/views/lecturer/home.blade.php
  - resources/views/student/home.blade.php
  - resources/views/student/subjects/index.blade.php
  - resources/views/layouts/navigation.blade.php
  - resources/views/components/back-button.blade.php
  - resources/views/components/welcome-banner.blade.php
  - resources/views/components/dashboard-card.blade.php
  - resources/views/lecturer/exams/create.blade.php
  - resources/views/lecturer/exams/edit.blade.php
  - resources/views/lecturer/sections/create.blade.php
findings:
  critical: 1
  warning: 2
  info: 2
  total: 5
status: fixed
resolved: 2026-07-18
---

# Phase 11: Code Review Report

> **Resolution (autonomous, 2026-07-18):** CR-01 fixed — `SubjectController::destroy()` now also refuses when the subject has sections (classes), guarding the sections→enrollments cascade; two Feature tests lock both the sections and exams guards. WR-02 fixed — the delete-subject confirm copy now names exams AND classes. WR-01 fixed — added `Exam::scopeAvailableNow()` (query twin of `isAvailableNow()`, additive per AVL-04) and reused it in `Student\HomeController`, removing the duplicated window predicate. IN-01 (dead index view) and IN-02 (krsort future-above-current) intentionally left as-is: IN-01 is harmless and minimal-diff; IN-02 is unreachable in practice (students do not enroll in future semesters, so current→past ordering is correct). Full suite: 382 passing.

**Reviewed:** 2026-07-18T09:50:04Z
**Depth:** standard
**Files Reviewed:** 14 (plus routes/lecturer.php, routes/student.php, app/Support/Semester.php, app/Models/Exam.php, app/Models/Section.php, app/Models/Subject.php, and the phase's new/changed test files read for cross-reference)
**Status:** issues_found

## Summary

Reviewed the navigation restructure (dashboard aggregates, subject CRUD relocation, and the single-page class-enrollment flow) introduced between commit `8a45360` and HEAD. The composite-ordinal semester math (`Semester::ordinal()`, and its raw-SQL mirror in both `Lecturer\HomeController` and `Student\HomeController`) is correct and matches the documented S1/S2 rollover semantics — verified by hand-tracing the ordinal formula and cross-checking against `DashboardTest`'s past/future fixtures. All new dashboard aggregates are single bounded COUNT/SUM/withCount queries, no N+1 loops. Role gating and ownership scoping (subject_user pivot, `Exam::visibleTo()`, `auth()->id()`-scoped enrollment reads) are intact; no cross-role or cross-student leak was found. The NAV-03 navbar trim is backed by an explicit `ReachabilityTest` proving every previously-linked destination (Sections/Exams/Results for lecturers, Class enrollment/My Exams for students, plus Help) is still reachable, and it checks out against the routes file. The enrollment-window gate (ENR-11) is UX-only in the view as intended — the real enforcement is `EnrollmentController@store`'s server-side `windowStatus()` re-check under a row lock, which this phase did not touch and which is sound.

One real defect was found in code touched by this phase: `Lecturer\SubjectController@destroy`'s "can't delete" guard only checks for existing exams, not sections — but `sections.subject_id` is `cascadeOnDelete`, and `enrollments.section_id` is in turn `cascadeOnDelete`, so deleting a subject that has classes with enrolled students (but no exams yet) silently destroys every section and every student's enrollment record with no warning, no confirmation of what will be lost, and no recovery path. This guard's own comment demonstrates the author was aware of exactly this class of bug for exams but didn't extend the same reasoning to sections.

Two warnings and two info items are also reported below (duplicated availability-window predicate logic, dead view file, and a UX-ordering nit).

## Critical Issues

### CR-01: Deleting a subject silently cascade-deletes its classes and every enrolled student's enrollment record

**File:** `app/Http/Controllers/Lecturer/SubjectController.php:68-82`
**Issue:** `destroy()` only refuses deletion when the subject has exams:

```php
if ($subject->exams()->exists()) {
    return redirect()->route('lecturer.home')
        ->with('status', 'Cannot delete a subject that still has exams. Delete or reassign its exams first.');
}

$subject->delete();
```

It does not check `$subject->sections()->exists()`. `sections.subject_id` is `cascadeOnDelete` (`database/migrations/2026_07_15_100002_create_sections_table.php:16`), and `enrollments.section_id` is also `cascadeOnDelete` (`2026_07_15_100011_create_enrollments_table.php:16`). So a subject with one or more classes — including classes with actively enrolled students — but zero exams can be deleted in one click, and every `sections` row and every `enrollments` row under it disappears with it. The confirm modal on the new home page (`resources/views/lecturer/home.blade.php:69`) only warns "Subjects with exams cannot be deleted," reinforcing to the lecturer that classes/enrollments are safe to lose. This is exactly the same failure mode the adjacent exams-guard comment (lines 70-73) was written to prevent, just left unguarded for the sibling relationship. Reachable directly from the new dashboard's subject table (`lecturer.home` → Delete), so this phase's UI surfaces the action.
**Fix:** Extend the guard to also refuse deletion when the subject has any sections (or, if intentional cascading is desired, require it to be enrollment-empty specifically):
```php
if ($subject->exams()->exists() || $subject->sections()->exists()) {
    return redirect()->route('lecturer.home')
        ->with('status', 'Cannot delete a subject that still has classes or exams. Delete or reassign them first.');
}
```

## Warnings

### WR-01: Availability-window predicate duplicated in raw SQL instead of reusing `Exam::isAvailableNow()`

**File:** `app/Http/Controllers/Student/HomeController.php:41-45`
**Issue:** The `examsAvailable` aggregate re-derives the half-open `[available_from, available_until)` window as a raw query builder predicate:
```php
->where(fn ($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
->where(fn ($q) => $q->whereNull('available_until')->orWhere('available_until', '>', now()))
```
This is semantically equivalent to `App\Models\Exam::isAvailableNow()` (`app/Models/Exam.php:111-117`), which exists specifically to be the single source of truth for this window check, per its own docblock ("deliberately additive... lives OUTSIDE scopeVisibleTo()"). Nothing ties the two implementations together, so if the window semantics ever change (e.g. inclusive upper bound, a grace period), this call site can silently drift out of sync with the canonical rule and nothing will catch it — there's no test asserting the two stay equivalent.
**Fix:** Add a query scope on `Exam` (e.g. `scopeAvailableNow()`) that both `isAvailableNow()` and this aggregate can share, or at minimum add a comment here cross-referencing `isAvailableNow()` and a test that would fail if the two diverge.

### WR-02: `Lecturer\SubjectController@destroy`'s confirm-modal copy is misleading given CR-01

**File:** `resources/views/lecturer/home.blade.php:66-72`
**Issue:** The delete-subject confirm modal says only `"This permanently removes ':name'. Subjects with exams cannot be deleted."` — it does not mention that classes and their enrolled students' records are also destroyed. Even after CR-01 is fixed to block deletion when sections exist, the copy should still be updated so a lecturer isn't misled about what "permanently removes" includes for subjects that do get through the guard (e.g., a subject with sections but the guard was fixed to allow deletion once sections are empty — the wording should reflect the actual guard, not just the exams half of it).
**Fix:** Update the modal body to name both blockers once CR-01 lands, e.g. `"Subjects with classes or exams cannot be deleted."`

## Info

### IN-01: `resources/views/lecturer/subjects/index.blade.php` is now dead code

**File:** `resources/views/lecturer/subjects/index.blade.php`
**Issue:** `SubjectController@index()` now unconditionally redirects to `lecturer.home` (`app/Http/Controllers/Lecturer/SubjectController.php:19-22`) and never renders this view. No route, controller, or test references it anymore (confirmed via grep across `resources/views`, `app`, and `tests`). It's a leftover from before the SUBJ-01 relocation.
**Fix:** Delete `resources/views/lecturer/subjects/index.blade.php`.

### IN-02: Student home lists future semesters above the current semester

**File:** `app/Http/Controllers/Student/HomeController.php:75-77`, `resources/views/student/home.blade.php:34`
**Issue:** `$groups` is keyed by composite ordinal and sorted with `krsort($groups)`, which orders keys descending — so if a student ever holds an enrollment in a semester later than the current one, it renders above their current-semester classes, which is the row most students actually want to see first. (Not currently exercisable through the enrollment flow since sections aren't pre-created for future semesters in the seeded data, but nothing in the schema prevents a future-dated `Section` from existing.)
**Fix:** Sort ascending instead (`ksort`) so current semester (the lowest non-past ordinal) leads, or explicitly float the current-semester group to the top before future ones.

---

_Reviewed: 2026-07-18T09:50:04Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_

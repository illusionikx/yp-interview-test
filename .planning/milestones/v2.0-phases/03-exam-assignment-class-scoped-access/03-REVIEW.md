---
phase: 03-exam-assignment-class-scoped-access
reviewed: 2026-07-15T00:00:00Z
depth: deep
files_reviewed: 13
files_reviewed_list:
  - app/Models/Exam.php
  - app/Policies/ExamPolicy.php
  - app/Http/Controllers/Controller.php
  - app/Http/Controllers/Student/ExamController.php
  - app/Http/Controllers/Lecturer/ExamAssignmentController.php
  - app/Http/Requests/Lecturer/AssignExamRequest.php
  - routes/student.php
  - routes/lecturer.php
  - resources/views/student/exams/index.blade.php
  - resources/views/student/exams/show.blade.php
  - resources/views/lecturer/exams/show.blade.php
  - app/Http/Middleware/EnsureUserHasRole.php
  - app/Models/User.php
findings:
  critical: 0
  warning: 0
  info: 2
  total: 2
status: clean
---

# Phase 3: Code Review Report

**Reviewed:** 2026-07-15
**Depth:** deep (cross-file authorization trace)
**Files Reviewed:** 13
**Status:** clean

## Summary

This phase implements class-scoped exam visibility for students and the
lecturer-side classroom-assignment endpoint. I reviewed it adversarially,
specifically hunting for the IDOR class of bug this phase exists to
prevent: an unpublished or wrong-classroom exam becoming reachable via
direct URL even though it's hidden from the index.

**I could not construct an IDOR.** The authorization design is airtight:

- `Exam::scopeVisibleTo()` is the single predicate, consumed identically by
  both `Student\ExamController::index()` (list) and `ExamPolicy::takeable()`
  (direct-access gate) — verified there is no second/looser inline
  `is_published` or `classroom_id` check anywhere in the reviewed file set.
- The null-classroom case is explicitly guarded with `->when($user->classroom_id, ..., fn ($q) => $q->whereRaw('0 = 1'))`, not left to Eloquent's `where(col, null)` auto-coercion (which would incorrectly match `NULL` classroom_id rows). A student with `classroom_id === null` gets zero rows in both the index and `takeable()` — confirmed both by static trace and by the passing `test_a_student_with_no_classroom_is_forbidden_direct_access` / `test_a_student_with_no_classroom_sees_an_empty_index` tests.
- The classroom check is scoped to a *specific* classroom id via `whereHas('classrooms', fn ($pivot) => $pivot->whereKey($classroomId))`, not "has any classroom at all" — this is the exact distinction that would otherwise make the phase's core check meaningless. Confirmed by `whereKey()` applying to the constrained `classrooms` (Classroom model) query, correctly translating to `classrooms.id = $classroomId` inside the `EXISTS` subquery joined through `exam_classroom`.
- `is_published` is combined with the classroom check via `AND` (chained `where`/`when` on the same builder), not `OR` — an unpublished exam cannot leak through even if assigned to the student's classroom. Verified by `test_an_unpublished_but_assigned_exam_is_forbidden` (student route) and `test_index_excludes_an_unpublished_but_assigned_exam` (index route), both passing.
- `ExamPolicy::takeable()` requires `$user->isStudent()` in addition to delegating to the scope — defense in depth even though the route is already gated by `role:student` middleware.
- `Student\ExamController::show()` calls `$this->authorize('takeable', $exam)` as the very first line, before any `load()`/`loadCount()` call — no data is fetched or rendered prior to the authorization check.
- The student landing view (`show.blade.php`) never touches `questions`, `options`, or `is_correct` — it renders only `title`, `subject->name`, `duration_minutes`, `questions_count` (via `loadCount`), and `description`. No answer/option data is loaded into the view model at all, so there is no leakage surface even via view-source or dev tools.
- The "Start" button seam is a plain disabled `<x-primary-button>` with no `route()` call, so it cannot throw `RouteNotFoundException` for the not-yet-built Phase-4 attempt route.
- `routes/lecturer.php` wraps `exams.assignment.update` inside the `role:lecturer` middleware group, and `AssignExamRequest::rules()` validates every `classroom_ids.*` with `exists:classrooms,id`, so a lecturer cannot silently write dangling pivot rows. `EnsureUserHasRole` aborts 403 server-side on both "no user" and "wrong role" — not a client-hidden-nav-only check.
- `ExamAssignmentController::update()` uses `$exam->classrooms()->sync($request->validated('classroom_ids', []))` — `sync()` is not susceptible to mass assignment (it operates on an array of ids, not a fillable-attributes array), and the `[]` default correctly handles the "all checkboxes unchecked → key absent from request" HTML form case.
- I ran the phase's own test suite (`ExamAccessTest`, `ExamIndexTest`, `ExamAssignmentTest` — 14 tests / 30 assertions) and all pass, corroborating the static analysis above rather than just trusting "tests exist."

I looked specifically for the failure modes called out in the task (null-`classroom_id` coercion tricks, `whereIn`-with-null, missing `0=1` guard, `whereHas` scoped to "any classroom" instead of a specific id, re-derived/looser `takeable()` logic, `authorize()` after data load, question/option/`is_correct` leakage in the student view, student access to the assignment endpoint, mass assignment in `sync()`, and N+1 in the index) and none of them are present. This is a case where the implementation should be reported as sound rather than manufacturing severity to have something to report.

Two low-priority informational notes are below — neither is a security gap, both are optional hardening.

## Info

### IN-01: 403 vs 404 lets a student distinguish "exists but not mine" from "does not exist"

**File:** `app/Http/Controllers/Student/ExamController.php:35-42` (interaction with route-model binding)
**Issue:** Route-model binding on `Exam $exam` throws `ModelNotFoundException` (404) for a nonexistent exam id, while `$this->authorize('takeable', $exam)` throws `AuthorizationException` (403) for an exam that exists but isn't visible to the student. A student probing sequential/adjacent exam ids can therefore distinguish "this id belongs to a real exam" from "this id is unused," enabling coarse exam-id enumeration (title/subject/duration are not otherwise exposed, so the practical impact is minimal — it does not leak any exam content, just existence).
**Fix:** Optional hardening only, not required for this phase. If enumeration resistance is desired later, catch `ModelNotFoundException` alongside the authorization failure and return a uniform 404 for both cases (e.g. override `failedAuthorization` or wrap the binding in a scoped query). Not worth doing now given exam ids are not otherwise treated as secrets anywhere else in the app.

### IN-02: `when($user->classroom_id, ...)` relies on PHP truthiness rather than explicit `!== null`

**File:** `app/Models/Exam.php:80-87`
**Issue:** `Builder::when()` evaluates its first argument for truthiness. `classroom_id` is an auto-increment foreign key so it will never legitimately be `0`, making this safe in practice, but the check is technically "falsy" rather than "null," which is a slightly looser guarantee than the docblock ("Explicitly guards `classroom_id === null`") states.
**Fix:** Cosmetic only — if you want the code to literally match the docblock's claim, use `->when($user->classroom_id !== null, fn (Builder $q) => $q->whereHas('classrooms', fn (Builder $pivot) => $pivot->whereKey($user->classroom_id)), fn (Builder $q) => $q->whereRaw('0 = 1'))`. Not required; current behavior is correct for the actual domain (classroom ids start at 1).

---

_Reviewed: 2026-07-15_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: deep_

---

## Orchestrator Resolution

**CLEAN** — no blocker/high/medium findings; the adversarial IDOR review could not break the authorization chain. Verifier independently confirmed 6/6 must-haves. No fixes applied.

Two info-level notes **deferred (accepted as-is)**: (1) 403-vs-404 exam-id enumeration side channel — no content leakage, and 403 is the asserted, honest behavior; (2) `when($user->classroom_id, ...)` truthiness vs `!== null` — safe by construction (classroom ids are auto-increment ≥1, never 0), and the null case is regression-tested.

---
phase: 08-v2-0-features-enrollment-exam-availability-user-manuals
reviewed: 2026-07-16T18:20:30Z
depth: standard
files_reviewed: 25
files_reviewed_list:
  - app/Enums/RejectionReason.php
  - app/Http/Controllers/Lecturer/RejectEnrollmentController.php
  - app/Http/Controllers/Lecturer/SectionController.php
  - app/Http/Controllers/Student/AttemptController.php
  - app/Http/Controllers/Student/EnrollmentController.php
  - app/Http/Controllers/Student/ExamController.php
  - app/Http/Controllers/Student/SubjectBrowseController.php
  - app/Http/Requests/Lecturer/RejectEnrollmentRequest.php
  - app/Http/Requests/Lecturer/StoreExamRequest.php
  - app/Http/Requests/Lecturer/UpdateExamRequest.php
  - app/Http/Requests/Student/EnrollRequest.php
  - app/Models/Enrollment.php
  - app/Models/Exam.php
  - app/Models/Section.php
  - app/Policies/AttemptPolicy.php
  - database/factories/ExamFactory.php
  - database/migrations/2026_07_15_100005_create_exams_table.php
  - database/seeders/DatabaseSeeder.php
  - resources/views/components/status-pill.blade.php
  - resources/views/layouts/navigation.blade.php
  - resources/views/student/attempts/show.blade.php
  - resources/views/student/exams/index.blade.php
  - resources/views/student/exams/show.blade.php
  - resources/views/student/subjects/index.blade.php
  - resources/views/student/subjects/show.blade.php
  - resources/views/lecturer/sections/show.blade.php
  - routes/lecturer.php
  - routes/student.php
findings:
  critical: 2
  warning: 3
  info: 1
  total: 6
status: issues_found
---

# Phase 08: Code Review Report

**Reviewed:** 2026-07-16T18:20:30Z
**Depth:** standard
**Files Reviewed:** 25 (+ 6 read for cross-file context: `app/Enums/EnrollmentStatus.php`, `app/Policies/ExamPolicy.php`, `app/Http/Requests/Student/SubmitAttemptRequest.php`, `app/Http/Requests/Student/AnswerRequest.php`, `app/Models/Attempt.php`, `database/migrations/2026_07_15_100011_create_enrollments_table.php`, `database/migrations/2026_07_15_100002_create_sections_table.php`)
**Status:** issues_found

## Summary

The highest-risk item from the review brief — mass assignment through `Enrollment` (`Pivot`, `$guarded = []`) — is **clean**: every write path (`EnrollmentController@store`, `EnrollmentController@destroy`, `RejectEnrollmentController@reject`) uses literal, explicitly-keyed arrays with `status` set server-side, never raw request input. `RejectEnrollmentRequest::authorize()` does genuine per-subject ownership checking (not `return true`), and `SectionController@show` is ownership-gated. `AttemptPolicy::view()/update()` are correctly ownership-only and no longer call `Exam::visibleTo()`. `Exam::isAvailableNow()`/`availabilityState()` and `Section::windowStatus()` implement the half-open `[from, until)` window correctly with no off-by-one, and the availability gate in `AttemptController@store` is correctly scoped to the new-attempt branch only, and does not leak into `scopeVisibleTo()`. The student exam index does not leak other students' attempts.

However, two real correctness/data-integrity defects were found, both directly relevant to the ENR/AVL risk areas called out in the brief:

1. The per-subject/term "one active enrollment" invariant (ENR-04) has an unprotected race across *different* sections of the same subject/term — the row lock used only covers the single section being applied to, not the sibling sections the cross-section check reads.
2. The enrollment-status-driven `takeable()` gate that guards the exam landing page and the attempt-start route was **not** updated to exempt students who already have an attempt, even though this phase's own `AttemptPolicy` fix explicitly documents the exact scenario ("the instant they withdraw... silently discarding unsaved work") as the thing to prevent. The fix was only applied to `AttemptPolicy`, not to `ExamController@show` / `AttemptController@store`, leaving the normal navigation path to an in-progress attempt breakable by a mid-attempt withdrawal or lecturer rejection.

## Critical Issues

### CR-01: Cross-section race lets a student hold two active enrollments in the same subject/term (ENR-04 violated)

**File:** `app/Http/Controllers/Student/EnrollmentController.php:51-97`
**Issue:**
`store()` locks only the `Section` row being applied to (`Section::whereKey($section->id)->lockForUpdate()->first()`), then, still under that lock, checks `hasActiveElsewhere` for an active enrollment on **other** sections of the same `subject_id`/`year`/`semester`. The doc comment claims "both invariants are consistent under one lock", but that is only true for the capacity invariant (ENR-02) — the section-row lock does not serialize against a concurrent apply to a *sibling* section of the same subject/term, because that request locks a *different* row.

Concretely: sections A and B both belong to subject S, year Y, semester M, both under capacity. A student fires two concurrent `POST /student/sections/{A}/enroll` and `POST /student/sections/{B}/enroll` requests. Request 1 locks section A's row and, since it commits before request 2's `hasActiveElsewhere` check runs (or vice versa, depending on interleaving), reads zero existing active-elsewhere rows — because request 2 hasn't committed its insert yet and locks a different row (section B), so it is never blocked. Both transactions pass their `hasActiveElsewhere` check and both `updateOrCreate` an `Enrolled` row. End state: the student is actively enrolled in *both* A and B of the same subject/term simultaneously.

There is also no DB-level backstop: `database/migrations/2026_07_15_100011_create_enrollments_table.php` only has `unique(['section_id', 'user_id'])`, which does not span the subject/year/semester dimension, so nothing catches this at the schema level either.

**Fix:** Lock every sibling section of the same subject/term (not just the one being applied to) before doing the cross-section read, so all concurrent applies within that term serialize against each other:
```php
$result = DB::transaction(function () use ($section, $request) {
    $locked = Section::whereKey($section->id)->lockForUpdate()->first();

    // Serialize every apply for this (subject, year, semester) term — not
    // just this section — so the ENR-04 cross-section check below is
    // actually race-safe under concurrency.
    Section::where('subject_id', $locked->subject_id)
        ->where('year', $locked->year)
        ->where('semester', $locked->semester)
        ->lockForUpdate()
        ->get();

    $enrolledCount = $locked->enrollments()
        ->wherePivot('status', EnrollmentStatus::Enrolled->value)
        ->count();
    // ...unchanged capacity + hasActiveElsewhere + updateOrCreate below
});
```

### CR-02: Withdraw/reject mid-attempt strands the in-progress attempt behind an unrelaxed `takeable()` gate

**File:** `app/Http/Controllers/Student/ExamController.php:44`, `app/Http/Controllers/Student/AttemptController.php:32`
**Issue:**
This phase's `AttemptPolicy` diff (removing `ownAndTakeable()`, making `view()`/`update()` ownership-only) is explicit that a student's in-progress attempt must not become unreachable "the instant they withdraw, are rejected, the availability window closes, or the exam is unpublished/un-assigned — silently discarding unsaved work." That fix, however, only touches `AttemptPolicy`. It was not carried through to the two entry points a student actually uses to *navigate back* to an in-progress attempt:

- `ExamController@show` (`student.exams.show`) unconditionally calls `$this->authorize('takeable', $exam)`, which delegates to `Exam::visibleTo($user)` — requiring the student to currently hold an `Enrolled` enrollment in an assigned section.
- `AttemptController@store` (`student.attempts.store`, the "Proceed"/resume button's POST target) also unconditionally calls `$this->authorize('takeable', $exam)` *before* the `$alreadyStarted` check that exempts the availability-window gate.

If a student withdraws from their section (`student.sections.withdraw` has no guard against withdrawing while an attempt is in progress) — or a lecturer rejects them via `RejectEnrollmentController` while they are mid-attempt — both of these routes now 403. Since `student/exams/index.blade.php` only links to a finished attempt's result (`$finishedAttempt`), an in-progress attempt has **no other link anywhere in the UI**. The student is left with a running, undiscoverable countdown they cannot navigate back to through the app (only a bookmarked/typed `/student/attempts/{id}` URL still works, because `AttemptPolicy::view()/update()` are correctly ownership-only). There is no scheduled sweep in this project (by design, per CLAUDE.md), so a never-revisited attempt simply stays `in_progress` indefinitely.

This is exactly the bug class the phase's own `AttemptPolicy` comment says it eliminated ("the instant they withdraw... silently discarding unsaved work") — it still happens, just one hop earlier, at the two call sites that were not updated to match.

**Fix:** Mirror the `$alreadyStarted` exemption already used for the availability check in `AttemptController@store`, and apply the same exemption to `ExamController@show`:
```php
// AttemptController@store
public function store(Request $request, Exam $exam): RedirectResponse
{
    $alreadyStarted = Attempt::where('exam_id', $exam->id)
        ->where('user_id', $request->user()->id)
        ->exists();

    if (! $alreadyStarted) {
        $this->authorize('takeable', $exam);
    }
    // ...isAvailableNow() gate (already correctly scoped) unchanged below
}
```
```php
// ExamController@show
public function show(Request $request, Exam $exam): View
{
    $hasAttempt = Attempt::where('exam_id', $exam->id)
        ->where('user_id', $request->user()->id)
        ->exists();

    if (! $hasAttempt) {
        $this->authorize('takeable', $exam);
    }
    // ...unchanged below
}
```

## Warnings

### WR-01: Reject does not verify the student is currently `Enrolled` before rejecting

**File:** `app/Http/Controllers/Lecturer/RejectEnrollmentController.php:32-39`
**Issue:** `$section->enrollments()->whereKey($student->id)->exists()` only proves an `enrollments` pivot row exists for this student/section — of *any* status. It does not check that the current status is `Enrolled`. The roster (`SectionController@show`) only lists `Enrolled` students, so the normal UI path never surfaces this, but the endpoint itself accepts a PATCH for a student who has already withdrawn or was already rejected, silently overwriting `status`/`rejection_reason` — e.g. flipping a `Withdrawn` row to `Rejected` with a lecturer-chosen reason the student never actually triggered. The project's own principle here (window/role enforcement must be server-side, not merely UI-hidden) argues this transition should be guarded the same way.
**Fix:**
```php
$enrolled = $section->enrollments()
    ->wherePivot('status', EnrollmentStatus::Enrolled->value)
    ->whereKey($student->id)
    ->exists();

abort_unless($enrolled, 404);
```

### WR-02: Withdraw has no status-transition guard and always flashes success

**File:** `app/Http/Controllers/Student/EnrollmentController.php:112-123`
**Issue:** `destroy()` runs an unconditional `Enrollment::where('section_id', ...)->where('user_id', ...)->update(['status' => Withdrawn])` and always flashes `"You've withdrawn from {$section->name}."`, regardless of whether a row existed or what its prior status was. Two consequences: (1) a student who was never enrolled in the section gets a false "withdrawn" success message (0 rows affected); (2) a student who was `Rejected` can call this endpoint directly to flip their own row to `Withdrawn`, replacing the lecturer's rejection record with a self-service withdrawal — silently erasing the rejection outcome from their own status display.
**Fix:**
```php
$updated = Enrollment::where('section_id', $section->id)
    ->where('user_id', auth()->id())
    ->where('status', EnrollmentStatus::Enrolled)
    ->update(['status' => EnrollmentStatus::Withdrawn]);

if (! $updated) {
    return back()->with('error', "You're not currently enrolled in this section.");
}

return back()->with('status', "You've withdrawn from {$section->name}.");
```

### WR-03: `EnrollRequest`/`RejectEnrollmentRequest` inputs bypass model casts on write (defensive-only, currently harmless)

**File:** `app/Http/Controllers/Lecturer/RejectEnrollmentController.php:37-38`
**Issue:** `updateExistingPivot()` writes `'status' => 'rejected'` as a raw string literal rather than `EnrollmentStatus::Rejected` (an enum instance, as used consistently by `EnrollmentController@store`/`destroy`). Functionally harmless today because the literal matches the enum's backing value exactly, but it is an inconsistent pattern in a file whose own class-level doc comment is about being careful with this exact model's mass-assignment surface — a future edit that changes `RejectionReason`'s backing values (or copy-pastes this literal elsewhere) would silently diverge with no static-analysis signal.
**Fix:**
```php
$section->enrollments()->updateExistingPivot($student->id, [
    'status' => EnrollmentStatus::Rejected,
    'rejection_reason' => $request->validated('reason'),
]);
```
(requires `use App\Enums\EnrollmentStatus;`)

## Info

### IN-01: Duplicate unnamed route pre-dates this phase (not introduced here, noted for completeness)

**File:** `routes/lecturer.php:54`
**Issue:** `Route::patch('exams/{exam}/questions/{question}', [ExamQuestionController::class, 'update']);` duplicates the named `PUT` route immediately above it with no `->name()`. Confirmed via `git diff 1efad91..HEAD -- routes/lecturer.php` that this line is unchanged by this phase — flagged only for completeness, not attributed to this phase's work.
**Fix:** Out of scope for this phase; remove or name the route in a follow-up if intentional PATCH support is desired.

---

_Reviewed: 2026-07-16T18:20:30Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_

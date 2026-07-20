---
phase: 08-v2-0-features-enrollment-exam-availability-user-manuals
fixed_at: 2026-07-17T00:00:00Z
review_path: .planning/phases/08-v2-0-features-enrollment-exam-availability-user-manuals/08-REVIEW.md
iteration: 1
findings_in_scope: 5
fixed: 5
skipped: 0
status: all_fixed
---

# Phase 08: Code Review Fix Report

**Fixed at:** 2026-07-17T00:00:00Z
**Source review:** .planning/phases/08-v2-0-features-enrollment-exam-availability-user-manuals/08-REVIEW.md
**Iteration:** 1

**Summary:**
- Findings in scope: 5 (2 critical, 3 warning — `fix_scope: critical_warning`; IN-01 excluded, pre-existing/out of scope per REVIEW.md)
- Fixed: 5
- Skipped: 0

Full suite: `php artisan test` — **294 passed** (0 failures), up from the 287-test baseline (+7 new tests added by these fixes). `ExamVisibilityRegressionTest` and `AttemptPolicyTest` (the AVL-04 regression guard) both stay green.

## Fixed Issues

### CR-01: Cross-section race lets a student hold two active enrollments in the same subject/term (ENR-04 violated)

**Files modified:** `app/Http/Controllers/Student/EnrollmentController.php`, `tests/Feature/Student/EnrollmentControllerTest.php`
**Commit:** `fbeeb26`
**Applied fix:** Inside `store()`'s existing `DB::transaction()`, added a `lockForUpdate()` over every sibling `Section` of the same `(subject_id, year, semester)` — not just the target section — before the capacity count and the `hasActiveElsewhere` cross-section read. This serializes all concurrent applies within the same term against each other, closing the race where two applies to different sibling sections could both read zero existing active-elsewhere rows and both succeed. Capacity logic and the pre-existing sequential-simulation-only honesty notes were left intact. Added `test_a_second_active_enrollment_in_any_sibling_section_of_the_same_subject_and_term_is_refused`, extending coverage from one sibling section to three, proving the added lock query doesn't itself break the normal cross-section refusal path (true concurrent interleaving remains untestable in PHPUnit's single-threaded runner, per the existing ENR-02 test's own honesty note).

### CR-02: Withdraw/reject mid-attempt strands the in-progress attempt behind an unrelaxed `takeable()` gate

**Files modified:** `app/Http/Controllers/Student/ExamController.php`, `app/Http/Controllers/Student/AttemptController.php`, `resources/views/student/exams/index.blade.php`, `tests/Feature/Student/AttemptPolicyTest.php`, `tests/Feature/Student/ExamIndexTest.php`
**Commit:** `e3f211f`
**Applied fix:** Mirrored the existing `$alreadyStarted` exemption pattern:
- `AttemptController@store` now only calls `authorize('takeable', $exam)` when no attempt yet exists for this student/exam.
- `ExamController@show` now only calls `authorize('takeable', $exam)` when the student has no existing attempt on this exam. Also corrected the now-stale doc comment claiming `$enrolledSection` is guaranteed non-null.
- `ExamController@index` now additionally loads the student's own `in_progress` attempts via an **ownership-driven** query (`Attempt::where('user_id', ...)`, never `Exam::visibleTo()`), excluding exams already in the main visible list, and passes them to the view as `resumableAttempts`.
- `student/exams/index.blade.php` renders a new "In-progress attempts" section with a "Resume exam" link straight to the ownership-gated `student.attempts.show` route, and the stale comment claiming in-progress attempts "are resumed from the exam page instead" was corrected to describe both paths (still-visible exams via Proceed; orphaned ones via the new Resume section).

Extended `AttemptPolicyTest::assertAttemptFullyUsable()` (shared by all 6 AVL-04 mid-attempt mutation tests — withdrawn, rejected, availability-window-closed, enrollment-row-deleted, exam-unpublished, exam-unassigned) to also assert `ExamController@show` renders OK and `AttemptController@store`'s "resume" POST redirects straight back to the attempt, closing the exact gap CR-02 identified. Added two new tests in `ExamIndexTest`: one confirming the "Resume exam" link appears for an attempt orphaned by withdrawal, one confirming it is strictly ownership-scoped and never surfaces another student's in-progress attempt.

### WR-01: Reject does not verify the student is currently `Enrolled` before rejecting

**Files modified:** `app/Http/Controllers/Lecturer/RejectEnrollmentController.php`, `tests/Feature/Lecturer/RejectEnrollmentControllerTest.php`
**Commit:** `0153d7b`
**Applied fix:** Changed the existence check from `$section->enrollments()->whereKey($student->id)->exists()` to also require `wherePivot('status', EnrollmentStatus::Enrolled->value)`, so `abort_unless(..., 404)` now refuses a reject against a student who already withdrew or was already rejected. Added two tests: rejecting an already-withdrawn student is a 404 with the row unchanged, and rejecting an already-rejected student is a 404 that preserves the original rejection reason.

### WR-02: Withdraw has no status-transition guard and always flashes success

**Files modified:** `app/Http/Controllers/Student/EnrollmentController.php`, `tests/Feature/Student/EnrollmentControllerTest.php`
**Commit:** `11db447`
**Applied fix:** `destroy()`'s update is now scoped to `where('status', EnrollmentStatus::Enrolled)`, and the affected-row count is checked — if zero rows were updated, the response flashes an explicit error (`"You're not currently enrolled in this section."`) instead of the previous unconditional false-success flash. This also closes the self-service path where a `Rejected` student could flip their own row to `Withdrawn`, erasing the lecturer's rejection record. Added two tests: withdrawing when never enrolled flashes the error and leaves no row; withdrawing a `Rejected` enrollment is refused and the original rejection (status + reason) is preserved.

### WR-03: `EnrollRequest`/`RejectEnrollmentRequest` inputs bypass model casts on write

**Files modified:** `app/Http/Controllers/Lecturer/RejectEnrollmentController.php`
**Commit:** `d1ec7f0`
**Applied fix:** Changed `'status' => 'rejected'` to `'status' => EnrollmentStatus::Rejected` in `updateExistingPivot()`, matching the enum-instance convention already used consistently by `EnrollmentController@store`/`destroy`. Purely a consistency fix (already covered by existing `RejectEnrollmentControllerTest` assertions against the persisted backing value) — no new test added.

## Skipped Issues

None — all 5 in-scope findings (2 critical, 3 warning) were fixed. IN-01 (duplicate unnamed route) was excluded by `fix_scope: critical_warning` and is also explicitly marked pre-existing/out-of-scope by REVIEW.md itself.

---

_Fixed: 2026-07-17T00:00:00Z_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_

---
phase: 13-student-exam-experience-class-page-take-exam
reviewed: 2026-07-18T00:00:00Z
depth: deep
files_reviewed: 8
files_reviewed_list:
  - app/Http/Controllers/Student/ClassPageController.php
  - resources/views/student/subjects/class.blade.php
  - resources/views/student/attempts/show.blade.php
  - routes/student.php
  - resources/views/student/home.blade.php
  - tests/Feature/Student/ClassPageTest.php
  - tests/Feature/Student/TakeExamPageTest.php
  - tests/Feature/Student/SubjectListTest.php
findings:
  critical: 0
  warning: 1
  info: 1
  total: 2
status: fixed
resolved: 2026-07-18
resolution: "WR-01 fixed — ClassPageController now surfaces exams the student ATTEMPTED even after they drop out of Exam::visibleTo() (e.g. lecturer unpublishes post-attempt, CLS-06), via an ownership-driven fallback merged into $exams, so the taken/graded result link stays reachable on the primary-nav class page (TAK-07). +1 regression test (unpublished-but-attempted exam keeps its result link). IN-01 (term-scoping of the 'Your class' label) accepted as-is — a pre-existing pattern inherited from ExamController::show(), INFO-level, not worth a cross-site term-scoping refactor in this phase. No critical/blocker findings: core-value server enforcement, authorization/IDOR, and TAK-10/11/12 all verified clean. Full suite: 441 passing."
---

# Phase 13: Code Review Report

**Reviewed:** 2026-07-18
**Depth:** deep
**Files Reviewed:** 8
**Status:** issues_found

## Summary

Reviewed the diff from `51e50a5` (phase-13 plan commit) to `HEAD`: the new student class page (`ClassPageController` + `student/subjects/class.blade.php`), the take-exam page restyle (`student/attempts/show.blade.php` + the `attemptTimer()` Alpine component), the new `subjects/{subject}/class` route, and the `home.blade.php` link retarget.

**Core-value enforcement is intact.** Diffed `show.blade.php` line-by-line against the pre-phase-13 version: `AttemptController::show/answer/submit/submitted` were not touched by this phase, `remainingSeconds` is still seeded once server-side and only counted down client-side, the answer-autosave and submit endpoints are unchanged (`{{ route('student.attempts.answer', $attempt) }}` / `{{ route('student.attempts.submit', $attempt) }}` — same URLs, same POST bodies), and the one-attempt-per-exam DB constraint plus `AttemptPolicy` ownership checks are unmodified. `autoSubmit()`/`tick()` only ever call the existing submit route; nothing computes or trusts a client-side deadline for a write. `ClassPageTest::test_a_second_attempts_store_post_does_not_create_a_second_attempt` explicitly re-proves the disabled Start button is UX-only.

**Authorization on the new class page is correctly scoped**: `ClassPageController::show()` gates entirely on an `Enrolled` enrollment in a section of the requested subject (`abort_unless`), and the exam list is read exclusively through `Exam::visibleTo()` plus a `subject_id` filter — no re-derived visibility logic to drift from the gate. The student's own attempt is eager-loaded with an explicit `where('user_id', ...)` constraint, so the "Resume"/"Taken"/"Graded" links (`student.attempts.show` / `student.attempts.result`) can only ever resolve to the acting student's own attempt — no IDOR path was introduced, and the downstream controllers still independently enforce ownership via `AttemptPolicy::view()`/`viewResult()` regardless.

**TAK-10/TAK-11/TAK-12 are implemented as specified**: the stepper checkmarks and the header/modal "answered" counts all read the same single reactive `answered` map, seeded once from `$savedAnswers` (server-persisted) and only ever flipped `true` on a successful autosave POST — no separate client-only answered state exists. The 10-minute toast is gated behind a single `tenMinuteWarned` boolean checked-then-set atomically in JS (`checkTenMinuteWarning()`), called both from `init()` (covers reload-with-<10-min-left) and from `tick()`, so it can fire at most once per page load. MCQ options are rendered via `$question->options()->orderBy('position')` server-side with no client-side reordering anywhere in the new markup.

One real functional gap was found in the new `ClassPageController` (see WR-01 below): it does not replicate the "resumable/orphaned attempt" fallback that the sibling `Student\ExamController::index()` already implements for the same underlying condition (an exam dropping out of `Exam::visibleTo()` while a student still has a live or gradable attempt on it).

## Warnings

### WR-01: Class page silently drops a student's own attempt if its exam becomes invisible

**File:** `app/Http/Controllers/Student/ClassPageController.php:44-48`, `resources/views/student/subjects/class.blade.php:30-86`

**Issue:** The exam list is built exclusively from `Exam::visibleTo($request->user())->where('subject_id', ...)`. If an exam a student has already started, submitted, or been graded on later becomes invisible under `Exam::visibleTo()` — e.g. a lecturer unpublishes it, or (per `Exam::scopeVisibleTo()`'s own doc comment) the student's enrollment on every section of the subject changes state — that exam disappears from the class page's exam list entirely, taking the "Resume" / "Taken" / "Graded" result link with it. There is no bare-label fallback either: the row is just gone.

This is exactly the class of defect `Student\ExamController::index()` already guards against in the same codebase (comment: "a student's own in-progress attempt must stay reachable even after its exam drops out of `Exam::visibleTo()`"), via a dedicated `$resumableAttempts` query keyed on ownership rather than visibility:
```php
$resumableAttempts = Attempt::where('user_id', $request->user()->id)
    ->where('status', 'in_progress')
    ->whereNotIn('exam_id', $exams->pluck('id'))
    ->with('exam')
    ->get();
```
`ClassPageController` explicitly documents mirroring `ExamController@index`'s eager-load idiom but does not mirror this ownership-driven fallback, and only covers `in_progress` in the sibling controller anyway (a `submitted`/`graded` attempt on a since-unpublished exam is unreachable from either list).

Since `home.blade.php`'s "Open class page" link now points at this new controller as the primary navigation path (this phase's own retarget), this reintroduces a narrower variant of the "unreachable result" defect TAK-07 explicitly sets out to fix (v2.0 defect), specifically for exams whose visibility changes after the student has already interacted with them. It is not a security issue — `AttemptPolicy` ownership checks mean the attempt/result is still directly reachable by URL, and the still-present `/student/exams` page's own (partial) fallback covers the `in_progress` case — but it is a real, untested regression risk on the page this phase built to be the primary entry point.

**Fix:** Add an ownership-driven fallback query in `ClassPageController::show()`, scoped to this subject and excluding exams already present in `$exams`, covering both `in_progress` and `submitted`/`graded` statuses (broader than `ExamController::index()`'s current `in_progress`-only fallback, since a graded result must stay reachable too):
```php
$orphanedAttempts = Attempt::where('user_id', $request->user()->id)
    ->whereHas('exam', fn ($q) => $q->where('subject_id', $subject->id))
    ->whereNotIn('exam_id', $exams->pluck('id'))
    ->with('exam')
    ->get();
```
and render these with the same Resume/Taken/Graded treatment as the main list. Add a regression test mirroring `ClassPageTest`'s existing coverage: unpublish an exam after the student has an attempt on it, then assert the class page still surfaces the result/resume link.

## Info

### IN-01: `Section::first()` enrolled-section pick is ambiguous across terms (pre-existing pattern, now triplicated)

**File:** `app/Http/Controllers/Student/ClassPageController.php:30-35`

**Issue:** `EnrollmentController::store()`'s "one active enrollment per subject" invariant (`hasActiveElsewhere` check) is scoped to `(subject_id, year, semester)`, not the subject alone — a student can legitimately hold simultaneous `Enrolled` rows in two different sections of the same subject across different terms (e.g. retaking a course). `ClassPageController::show()`'s `Section::where('subject_id', ...)->whereHas('enrollments', ...)->first()` (and the identical query in `Student\ExamController::show()` it says it mirrors) does not filter by current term, so if such a case exists the "Your class" label picked is whichever row the DB returns first, not necessarily the current-term one. This is inherited from the pre-existing `ExamController@show` idiom rather than a new defect, but this phase copies it a third time (`SubjectBrowseController` likely has a similar resolution too) rather than centralizing it, so any future fix to this ambiguity will need to be applied in three places.

**Fix:** Not blocking for this phase — worth centralizing into a `Subject`/`User` helper (e.g. `$user->enrolledSectionFor($subject)`, ordered by term descending) the next time enrolled-section resolution is touched, so all three call sites pick up the fix at once.

---

_Reviewed: 2026-07-18_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: deep_

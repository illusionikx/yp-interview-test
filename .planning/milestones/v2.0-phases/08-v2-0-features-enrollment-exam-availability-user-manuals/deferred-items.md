# Deferred Items — Phase 8

Out-of-scope discoveries logged during plan execution, per the executor's
Scope Boundary rule (fix issues directly caused by the current task's
changes; log everything else here rather than fixing it).

## From 08-09 (in-app user manuals)

> **✅ RESOLVED in-phase (2026-07-17, commit `78eb271`) — not deferred.**
> The orchestrator judged this a real hole in the project's core value (a shipped,
> validated Phase 5 feature was unreachable) and a correctness problem for DEL-04,
> whose success criterion requires the student manual to walk through *viewing results* —
> impossible to document truthfully with no click-path. The submit-confirmation page also
> explicitly promised "you'll be able to view your score once grading is complete".
>
> **Fix:** `Student\ExamController@index` now eager-loads the acting student's own attempt
> (user-scoped, bounded, no N+1); `student/exams/index.blade.php` renders a **View result**
> link once that attempt is submitted. In-progress attempts are resumed from the exam page
> instead; another student's attempt is never linked. The result page remains authoritative
> (still withholds the score until grading completes, D-05/GRD-03). The student manual's
> "Viewing Your Results" section now documents the real click-path.
> **Coverage:** 4 new `ExamIndexTest` cases (submitted → link, no attempt → none,
> in-progress → none, other student's attempt → never linked). Full suite 287 passed / 0 failed.
>
> **Lesson worth keeping:** the original gap traces to Phase 5, where GRD-04 was verified by
> route/policy/view *existence* rather than *UI reachability*. Existence ≠ reachability —
> future verification of a user-facing requirement should assert a navigable path.

**Finding (original, now fixed):** There is no in-app UI link anywhere that navigates a student to
their own result page (`student.attempts.result`, `Student\ResultController@show`).
The route, controller, policy (`AttemptPolicy::viewResult()`), and view
(`resources/views/student/results/show.blade.php`) all exist and are fully
tested (`tests/Feature/Student/ResultTest.php`, 5/5 passing) — a student who
knows or is given the URL can view their result correctly, gated and scored
exactly per GRD-04. But nothing in the shipped UI (`student/exams/index.blade.php`,
`student/exams/show.blade.php`, `student/attempts/submitted.blade.php`,
`student/home.blade.php`) renders a link to it. This gap predates Phase 8 —
it traces to Phase 5 (05-02-PLAN.md registered the route with no corresponding
"add a results link" task; 05-VERIFICATION.md verified GRD-04 by route/policy/
view existence, not by UI reachability).

**Why not fixed here:** `app/Http/Controllers/Student/ExamController.php` and
`resources/views/student/exams/index.blade.php` are not in 08-09-PLAN.md's
declared `files_modified` list, and the gap is not caused by any 08-09
change — per the deviation rules' Scope Boundary, out-of-scope pre-existing
issues are logged, not fixed, during plan execution.

**Impact on this plan:** The student manual's "Viewing Your Results" section
(DEL-04, flow 5) was written to describe only verified, real screen states
("Awaiting grading" / "Your Result" headings, the "X / Y points" score line,
the ✓ Correct / ✗ Incorrect per-question labels) without inventing a
click-path to reach that page, since none exists. It does not tell the
student to click anything to get there.

**Recommended follow-up:** A small future plan (or a Rule-2 fix inside a
plan that already touches `student/exams/index.blade.php`) should add a
"View Result" link per exam row once the student's attempt on that exam is
`submitted` or `graded`, following the existing per-row action-link pattern
already used elsewhere (e.g. `lecturer/results/index.blade.php`'s
Grade/View column). This is worth prioritizing given CLAUDE.md's stated
Core Value explicitly includes the student's answers being "reliably
captured and scored" — scoring currently has no student-facing display path.

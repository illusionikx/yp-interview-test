---
phase: 03-exam-assignment-class-scoped-access
verified: 2026-07-15T16:10:00Z
status: passed
score: 6/6 must-haves verified
behavior_unverified: 0
overrides_applied: 0
---

# Phase 3: Exam Assignment & Class-Scoped Access Verification Report

**Phase Goal:** Only the right students, for the right classroom, can reach a published exam — everyone else denied (via listing AND guessed URL). Lecturers assign exams to classrooms.
**Verified:** 2026-07-15T16:10:00Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | ASN-01: A lecturer can assign a published (or draft) exam to one or more classrooms via `sync()` on `exam_classroom`; a student is forbidden from the assignment endpoint | ✓ VERIFIED | `app/Http/Controllers/Lecturer/ExamAssignmentController.php:28` calls `$exam->classrooms()->sync(...)`; route `lecturer.exams.assignment.update` sits inside the `role:lecturer` group (`routes/lecturer.php:22`); `tests/Feature/Lecturer/ExamAssignmentTest.php` 5/5 PASS (ran live, see Behavioral Spot-Checks) |
| 2 | ASN-02: A student's exam index shows only published exams assigned to their own classroom — nothing else, including null-classroom → empty | ✓ VERIFIED | `app/Http/Controllers/Student/ExamController.php:20` — `Exam::visibleTo($request->user())`; `app/Models/Exam.php:76-88` `scopeVisibleTo` ANDs `is_published=true` with classroom `whereHas`, explicit `whereRaw('0 = 1')` for null classroom; `tests/Feature/Student/ExamIndexTest.php` 4/4 PASS (live run) covering published+assigned/unpublished-excluded/other-class-excluded/null-classroom-empty |
| 3 | RBAC-05: A student who directly opens the URL of an exam not assigned to their classroom → 403; unpublished-but-assigned → 403; null classroom → 403; lecturer on student route → 403; assigned+published → 200 | ✓ VERIFIED | `app/Http/Controllers/Student/ExamController.php:37` — `$this->authorize('takeable', $exam)` is the FIRST statement in `show()`, before any relation load/render; `app/Policies/ExamPolicy.php:19-23` — `takeable()` calls `Exam::visibleTo($user)->whereKey($exam->id)->exists()`, the SAME scope method the index uses (no divergent predicate); `tests/Feature/Student/ExamAccessTest.php` 5/5 PASS (live run) — full 200/403/403/403/403 matrix including lecturer-on-student-routes |
| 4 | Index filter and direct-access gate use one shared predicate (no divergence) | ✓ VERIFIED | Single call site of the predicate logic: `Exam::visibleTo(...)` invoked in both `Student\ExamController@index` (`ExamController.php:20`) and `ExamPolicy::takeable` (`ExamPolicy.php:22`) — no second/inline re-derivation of `is_published`/`classroom_id` anywhere in either file (grepped, confirmed absent) |
| 5 | Student landing is read-only — no questions/answers/is_correct exposed | ✓ VERIFIED | `Student\ExamController@show` (`ExamController.php:39`) calls `$exam->load('subject')->loadCount('questions')` — count only, never `with('questions.options')`; `resources/views/student/exams/show.blade.php` renders only title/subject/duration/description/question-count — no `$question` or `is_correct` reference anywhere in the file (grepped, confirmed absent) |
| 6 | No attempt/timer/grading logic in this phase; "Start" is a disabled seam | ✓ VERIFIED | `resources/views/student/exams/show.blade.php:24-27` renders `<x-primary-button disabled>` with NO `route()` call to any attempt/attempt-start route; grepped codebase — no `Attempt` controller, route, or grading logic introduced in any Phase-3 file; `Exam::attempts()` relation exists on the model (pre-declared for Phase 4) but is unused by any Phase-3 code path |

**Score:** 6/6 truths verified (0 present, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Models/Exam.php` | `scopeVisibleTo(Builder, User)` single predicate, explicit null-classroom guard | ✓ VERIFIED | Present, substantive (13 lines of logic + doc comment), wired from 2 call sites |
| `app/Policies/ExamPolicy.php` | `takeable(User, Exam)` delegating to `Exam::visibleTo` | ✓ VERIFIED | Present, delegates entirely, no inline re-derivation |
| `app/Http/Controllers/Student/ExamController.php` | `index()` (scoped list) + `show()` with `authorize('takeable', $exam)` before render | ✓ VERIFIED | Present, `authorize()` call is literally the first statement in `show()` |
| `app/Http/Controllers/Controller.php` | `AuthorizesRequests` trait so `$this->authorize()` works | ✓ VERIFIED | Trait imported and used; this was previously an empty abstract class |
| `app/Http/Controllers/Lecturer/ExamAssignmentController.php` | `update()` syncing `exam_classroom` from validated `classroom_ids` | ✓ VERIFIED | Present, calls `sync()`, redirects with status flash |
| `app/Http/Requests/Lecturer/AssignExamRequest.php` | `classroom_ids.*` validated `integer, distinct, exists:classrooms,id` | ✓ VERIFIED | Present, rules exactly match; `classroom_ids` intentionally not `required` (empty clears assignment) |
| `routes/lecturer.php` | `PUT exams/{exam}/assignment` named `lecturer.exams.assignment.update`, inside `role:lecturer` | ✓ VERIFIED | Confirmed via `php artisan route:list --name=exams.assignment` — single PUT route registered |
| `routes/student.php` | `student.exams.index` + `student.exams.show` under `role:student` | ✓ VERIFIED | Confirmed via `php artisan route:list --name=student.exams` — both GET routes registered |
| `resources/views/lecturer/exams/show.blade.php` | "Assign to classes" checkbox panel posting PUT | ✓ VERIFIED | Panel present, pre-checks current assignment via `$exam->classrooms->pluck('id')->contains(...)`, posts to `exams.assignment.update` |
| `resources/views/student/exams/show.blade.php` | Read-only landing + disabled Start seam | ✓ VERIFIED | Renders title/subject/duration/question-count only; disabled button with no route() call |
| `resources/views/student/exams/index.blade.php` | Class-scoped list, empty-state | ✓ VERIFIED | `@forelse`/`@empty` renders exam cards from `$exams` (the pre-filtered collection), links to `student.exams.show` |
| `resources/views/student/home.blade.php` | Nav link to `student.exams.index` | ✓ VERIFIED | Link present, replaces prior "coming in a later phase" placeholder |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `Student\ExamController@index` | `Exam::scopeVisibleTo` | `Exam::visibleTo($request->user())` | ✓ WIRED | Line 20 of controller |
| `ExamPolicy::takeable` | `Exam::scopeVisibleTo` | `Exam::visibleTo($user)->whereKey($exam->id)->exists()` | ✓ WIRED | Line 22 of policy — same scope method, no drift |
| `Student\ExamController@show` | `ExamPolicy::takeable` | `$this->authorize('takeable', $exam)` before render | ✓ WIRED | Line 37, first statement after route-model binding; Laravel 11 auto-discovers `ExamPolicy` for `Exam` (no manual registration needed, and none found — no `AuthServiceProvider` policy map required in Laravel 11) |
| `resources/views/lecturer/exams/show.blade.php` | `routes/lecturer.php` | form action `route('lecturer.exams.assignment.update', $exam)` + `@method('PUT')` | ✓ WIRED | Confirmed in view source, route exists |
| `Lecturer\ExamAssignmentController@update` | `AssignExamRequest` | type-hinted `AssignExamRequest $request` | ✓ WIRED | Line 26 of controller |
| `Lecturer\ExamAssignmentController@update` | `Exam::classrooms()` | `$exam->classrooms()->sync(...)` | ✓ WIRED | Line 28 of controller |
| `resources/views/student/home.blade.php` | `routes/student.php` | nav link `route('student.exams.index')` | ✓ WIRED | Confirmed in view source |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
|----------|---------------|--------|---------------------|--------|
| `student/exams/index.blade.php` | `$exams` | `Exam::visibleTo($request->user())->with('subject')->orderBy('title')->get()` | Yes — real DB query, filtered by is_published + classroom whereHas | ✓ FLOWING |
| `student/exams/show.blade.php` | `$exam` | route-model-bound `Exam $exam`, gated by policy before load | Yes — real model, loaded relations (`subject`, `questions_count`) | ✓ FLOWING |
| `lecturer/exams/show.blade.php` (assign panel) | `$classrooms` | `Classroom::orderBy('name')->get()` in `Lecturer\ExamController@show` | Yes — real DB query | ✓ FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| ASN-01: assignment contract (5 cases) | `php artisan test --filter=ExamAssignmentTest` | 5 passed (16 assertions) | ✓ PASS |
| ASN-02: index-visibility contract (4 cases) | `php artisan test --filter=ExamIndexTest` | 4 passed (8 assertions) | ✓ PASS |
| RBAC-05: IDOR matrix (5 cases, 200/403/403/403/403) | `php artisan test --filter=ExamAccessTest` | 5 passed (6 assertions) | ✓ PASS |
| Full suite regression check | `php artisan test` | 135 passed (335 assertions), 0 failures | ✓ PASS |
| Assignment route registered | `php artisan route:list --name=exams.assignment` | 1 route: `PUT lecturer/exams/{exam}/assignment` → `ExamAssignmentController@update` | ✓ PASS |
| Student routes registered | `php artisan route:list --name=student.exams` | 2 routes: `GET student/exams` (index), `GET student/exams/{exam}` (show) | ✓ PASS |
| Role middleware returns 403 (not redirect) | Read `app/Http/Middleware/EnsureUserHasRole.php` | `abort(403)` on role mismatch/no user | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|--------------|--------|----------|
| ASN-01 | 03-01, 03-02 | Lecturer can assign an exam to one or more classrooms | ✓ SATISFIED | `ExamAssignmentController@update` + `sync()`; `ExamAssignmentTest` 5/5 green |
| ASN-02 | 03-01, 03-03 | A Student sees only published exams assigned to their own classroom | ✓ SATISFIED | `Exam::scopeVisibleTo` + `Student\ExamController@index`; `ExamIndexTest` 4/4 green |
| RBAC-05 | 03-01, 03-03 | A Student can only access a resource belonging to them/their class — direct URLs to others' resources denied (no IDOR) | ✓ SATISFIED | `ExamPolicy::takeable` reusing the shared scope, `authorize()` called before render; `ExamAccessTest` 5/5 green covering the full matrix |

No orphaned requirements — REQUIREMENTS.md maps exactly ASN-01, ASN-02, RBAC-05 to Phase 3, and all three appear in plan frontmatter and are satisfied.

### Anti-Patterns Found

None. Scanned all 13 files created/modified across the three plans for `TBD`/`FIXME`/`XXX`/`TODO`/`HACK`/`PLACEHOLDER`/hardcoded-empty-return patterns. The only matches were the two intentional, spec-required strings in `resources/views/student/exams/show.blade.php` ("Coming soon" tooltip, "Taking exams is not available yet." caption) on the deliberately-disabled Phase-4 "Start" seam — this is the explicitly designed behavior (truth #6 above), not a debt marker, and carries no unresolved TBD/FIXME/XXX tag.

### Human Verification Required

None. All must-haves are verifiable via source inspection and live automated test execution; no visual/UX/external-service judgment calls remain for this phase's scope.

### Gaps Summary

No gaps. All 6 derived truths (covering the 3 roadmap Success Criteria plus the phase's explicit RBAC-05 divergence/exposure/scope checks) are VERIFIED against actual source code, and independently confirmed by running the phase's own test suite live (14/14 Phase-3 cases pass) plus a full-suite regression run (135/135 pass, no regressions from the shared `Exam` model change). The `scopeVisibleTo`/`ExamPolicy::takeable` single-predicate pattern is the crux of the phase and is implemented exactly as designed — one method, two call sites, no re-derivation. Assignment is correctly decoupled from publish state (D-01), and the student landing exposes no question/answer data. No attempt/timer/grading logic exists in this phase — the Start button is an inert, route-less seam.

---

_Verified: 2026-07-15T16:10:00Z_
_Verifier: Claude (gsd-verifier)_

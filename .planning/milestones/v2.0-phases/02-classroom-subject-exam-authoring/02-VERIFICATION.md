---
phase: 02-classroom-subject-exam-authoring
verified: 2026-07-15T14:50:08Z
status: passed
score: 4/4 must-haves verified
behavior_unverified: 0
overrides_applied: 0
---

# Phase 2: Classroom, Subject & Exam Authoring Verification Report

**Phase Goal:** Lecturers can build the full teaching content model — classrooms, subjects, class-subject links, student rosters, and complete exams — before anything is exposed to a student.
**Verified:** 2026-07-15T14:50:08Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths (ROADMAP Success Criteria)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Lecturer CRUD for classrooms + subjects, and can link ≥1 subject to a classroom (classroom_subject sync) | ✓ VERIFIED | `SubjectController` and `ClassroomController` implement full resource CRUD (`app/Http/Controllers/Lecturer/SubjectController.php:12-69`, `ClassroomController.php:12-73`). `ClassroomController::store/update` call `$classroom->subjects()->sync($request->validated('subject_ids', []))` after create/update (lines 40, 59). `classroom_subject` pivot table confirmed in `database/migrations/2026_07_15_100004_create_classroom_subject_table.php` (unique `[classroom_id, subject_id]`). Tests: `SubjectControllerTest` (7 passing), `ClassroomControllerTest` (8 passing), `ClassroomSubjectLinkageTest` (4 passing — link exact set, replace set, clear set, reject invalid id with no partial link). |
| 2 | Lecturer can assign a student to a classroom via a direct `users.classroom_id` FK (not a pivot); student-only + IDOR guard on the roster endpoint | ✓ VERIFIED | `users` table has a `classroom_id` FK column added in `2026_07_15_100003_add_role_and_classroom_id_to_users_table.php` — confirmed NOT a pivot (no `classroom_user`/`user_classroom` table exists). `ClassroomRosterController::store` does `$student->update(['classroom_id' => $classroom->id])` (direct FK write). `AssignStudentRequest::rules()` scopes `student_id` via `Rule::exists('users','id')->where('role', Role::Student->value)` — a lecturer account can never be targeted. `ClassroomRosterController::destroy` has `abort_unless($student->classroom_id === $classroom->id, 404)` — IDOR guard preventing unassigning a student who belongs to a different classroom. Tests: `ClassroomRosterTest` (6 passing incl. "assigning a non student user is rejected" and "unassigning a student who belongs to a different classroom aborts 404"). |
| 3 | Lecturer can create an exam under a subject with title + duration_minutes; add MCQ (≥2 options, exactly one correct, `is_correct` derived server-side) and open-text questions, each with points (default 1) | ✓ VERIFIED | `StoreExamRequest` validates `subject_id`, `title`, `duration_minutes` (min:1). `ExamController::store` stamps `created_by` from `$request->user()->id`, never from request input. `ExamQuestionController::store`/`update` compute `is_correct` as `$i === $correct` from the validated `correct_option` index inside a `DB::transaction` — `options.*.is_correct` is never a validation rule and is never read from request input (confirmed by grep across `StoreQuestionRequest`/`UpdateQuestionRequest` rules and the `_form.blade.php` view, which posts `correct_option`, not `is_correct`). `StoreQuestionRequest` rules require `options` array `min:2` for MCQ, `correct_option` required_if mcq, and an `after()` hook rejects an out-of-range/non-numeric index. `points` defaults to 1 via `prepareForValidation()` merge and rejects `<1`. Tests: `ExamControllerTest` (10), `ExamQuestionMcqTest` (10 — incl. reject zero-correct, out-of-range, <2 options, blank option body), `ExamQuestionOpenTest` (6). |
| 4 | Edit/delete of an exam and its questions blocked once `is_published=true` (403); allowed while draft; publish/unpublish reversible | ✓ VERIFIED | `UpdateExamRequest::authorize()` returns `! $this->route('exam')->is_published`; `ExamController::destroy` has `abort_if($exam->is_published, 403)`. `StoreQuestionRequest`/`UpdateQuestionRequest::authorize()` mirror the same gate; `ExamQuestionController::destroy` has `abort_if($exam->is_published, 403)`. `ExamController::publish/unpublish` flip `is_published` and are freely reversible (no attempts exist in Phase 2 scope). Tests: `ExamPublishTest` (5 — publish, unpublish, unpublish-makes-editable-again, student-forbidden×2), `ExamPublishedEditGateTest` (9 — add/edit/delete blocked once published, allowed on draft, delete-and-recreate option replacement, mcq→open type switch drops options, student-forbidden×3). |

**Score:** 4/4 truths verified

### Requirement IDs Cross-Reference (CLS-01..04, EXM-01..06)

| Requirement | Owning Plan | REQUIREMENTS.md Status | Codebase Evidence |
|---|---|---|---|
| CLS-01 (classroom CRUD) | 02-03 | Complete | `ClassroomController` full resource CRUD; `ClassroomControllerTest` |
| CLS-02 (subject CRUD) | 02-02 | Complete | `SubjectController` full resource CRUD; `SubjectControllerTest` |
| CLS-03 (classroom↔subject link) | 02-03 | Complete | `classroom_subject` pivot + `sync()`; `ClassroomSubjectLinkageTest` |
| CLS-04 (assign student to classroom) | 02-03 | Complete | `ClassroomRosterController`; `ClassroomRosterTest` |
| EXM-01 (exam under subject, title+duration) | 02-04 | Complete | `ExamController::store` + `StoreExamRequest`; `ExamControllerTest` |
| EXM-02 (MCQ, multi-option, exactly one correct) | 02-05 | Complete | `ExamQuestionController::store` + `StoreQuestionRequest`; `ExamQuestionMcqTest` |
| EXM-03 (open-text questions) | 02-05 | Complete | `ExamQuestionController::store` (type=open, no options); `ExamQuestionOpenTest` |
| EXM-04 (per-question points, default 1) | 02-05 | Complete | `prepareForValidation()` default + `min:1` rule; `ExamQuestionMcqTest`/`OpenTest` points cases |
| EXM-05 (edit/delete only while unpublished) | 02-04 + 02-06 | Complete | Exam-level gate (`UpdateExamRequest`, `ExamController::destroy`) + question-level gate (`UpdateQuestionRequest`, `ExamQuestionController::destroy`); `ExamPublishedEditGateTest` |
| EXM-06 (publish/unpublish, draft vs published) | 02-04 | Complete | `ExamController::publish/unpublish`; `ExamPublishTest` |

All 10 phase requirement IDs are declared in plan frontmatter and cross-reference cleanly against `.planning/REQUIREMENTS.md`'s traceability table (all listed as "Phase 2 ... Complete"). No orphaned requirements found for Phase 2.

### Required Artifacts

| Artifact | Expected | Status | Details |
|---|---|---|---|
| `app/Http/Controllers/Lecturer/SubjectController.php` | Subject CRUD | ✓ VERIFIED | Full resource controller, wired to routes and views |
| `app/Http/Controllers/Lecturer/ClassroomController.php` | Classroom CRUD + subject sync | ✓ VERIFIED | Full resource controller + `subjects()->sync()` |
| `app/Http/Controllers/Lecturer/ClassroomRosterController.php` | Assign/unassign student | ✓ VERIFIED | `store`/`destroy` with direct FK write + IDOR guard |
| `app/Http/Controllers/Lecturer/ExamController.php` | Exam CRUD + publish/unpublish | ✓ VERIFIED | Full resource controller + `publish`/`unpublish` + draft-only gates |
| `app/Http/Controllers/Lecturer/ExamQuestionController.php` | Nested question authoring | ✓ VERIFIED | `store`/`edit`/`update`/`destroy`, transactional option writes |
| `app/Http/Requests/Lecturer/*.php` (8 files) | Validation + authorization gates | ✓ VERIFIED | All present; `authorize()` gates checked on Update/Store*Question and UpdateExam |
| `routes/lecturer.php` | All routes under `role:lecturer` group | ✓ VERIFIED | Single `Route::middleware(['auth','verified','role:lecturer'])` group wraps every subject/classroom/exam/question/roster route |
| `resources/views/lecturer/**` (13 Blade files) | Full CRUD UI incl. roster panel, question form | ✓ VERIFIED | Read `classrooms/edit.blade.php` (subject multi-select + roster panel) and `exams/show.blade.php` (question list, publish/edit/delete affordances gated by `is_published`) — both fully wired, no placeholders |
| `tests/Feature/Lecturer/*.php` (10 files) | Feature coverage | ✓ VERIFIED | 79 Lecturer-scoped tests, all passing |

### Key Link Verification

| From | To | Via | Status |
|---|---|---|---|
| `routes/lecturer.php` | `SubjectController`/`ClassroomController`/`ExamController` | `Route::resource(...)` inside `role:lecturer` group | ✓ WIRED |
| `ClassroomController::store/update` | `classroom_subject` pivot | `$classroom->subjects()->sync(...)` | ✓ WIRED |
| `ClassroomRosterController::store` | `users.classroom_id` | direct `$student->update(['classroom_id' => ...])`, not attach/detach | ✓ WIRED |
| `ExamController::store` | `created_by` | `auth()->id()`/`$request->user()->id`, never request input | ✓ WIRED |
| `ExamQuestionController::store/update` | `options.is_correct` | derived from validated `correct_option` index inside `DB::transaction`, never accepted directly | ✓ WIRED |
| `UpdateExamRequest`/`StoreQuestionRequest`/`UpdateQuestionRequest` | `Exam::is_published` | `authorize()` returns `! $exam->is_published` | ✓ WIRED |
| `exams/show.blade.php` | `ExamQuestionController::store` | form posts to `lecturer.exams.questions.store` | ✓ WIRED |

### Behavioral Spot-Checks / Test Execution

Full Lecturer-scoped test run and full project test suite were executed directly (not narrated from SUMMARY.md):

```
php artisan test --filter=Lecturer
Tests: 79 passed (198 assertions)

php artisan test
Tests: 116 passed (288 assertions)
```

All 79 Phase-2 Lecturer feature tests pass, including negative-path coverage for every must-have (zero-correct MCQ, out-of-range correct index, <2 options, blank option body, points<1, non-student roster assignment, cross-classroom unassign 404, published-exam edit/delete 403, student 403 on every lecturer route). Full 116-test suite (Phase 1 + Phase 2) passes with no regressions.

### Scope-Creep Check (Success Criterion 6)

| Concern | Finding |
|---|---|
| Exam→classroom assignment | Not present in `routes/lecturer.php`. `exam_classroom` table and `Exam::classrooms()`/`Classroom::exams()` relations exist (Phase 1 schema, ROADMAP-scheduled for Phase 3) but no Phase-2 controller or route writes to it. |
| Student-facing exam views | `routes/student.php` contains only the `student.home` route — no exam listing/detail/attempt routes exist yet. |
| Attempts/grading | `attempts`/`answers` tables exist from the Phase-1 schema migration but no controller, route, or view in this phase touches them. |

No scope creep found — the authoring surface stops exactly where Phase 3 (assignment) begins.

### Anti-Patterns Found

None. Grep for `TBD|FIXME|XXX|TODO|HACK|PLACEHOLDER|not yet implemented|coming soon` (case-insensitive) across `app/Http/Controllers/Lecturer/` and `app/Http/Requests/Lecturer/` returned zero matches. Manual read of all controllers, form requests, and the two most state-bearing Blade views (`classrooms/edit.blade.php`, `exams/show.blade.php`) found no stub returns, no empty handlers, and no hardcoded-empty data — all views render from live Eloquent queries/relations.

### Requirements Coverage

All 10 declared phase requirement IDs (CLS-01..04, EXM-01..06) are SATISFIED with direct code + test evidence (see table above). No requirement is BLOCKED or NEEDS HUMAN.

### Human Verification Required

None. All must-haves are server-side, testable behaviors with passing automated feature-test coverage; no visual/real-time/external-service behavior in scope for this phase.

### Gaps Summary

No gaps. All 4 ROADMAP success criteria are verified with direct source-code evidence (not SUMMARY.md claims) and a live, passing test run (79/79 Lecturer tests, 116/116 project-wide). All 10 requirement IDs are accounted for and satisfied. No scope creep into Phase 3/4/5 territory was found.

---

_Verified: 2026-07-15T14:50:08Z_
_Verifier: Claude (gsd-verifier)_

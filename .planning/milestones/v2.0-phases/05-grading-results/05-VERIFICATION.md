---
phase: 05-grading-results
verified: 2026-07-16T00:00:00Z
status: passed
score: 5/5 must-haves verified
behavior_unverified: 0
overrides_applied: 0
---

# Phase 5: Grading & Results Verification Report

**Phase Goal:** Every submitted attempt ends in an accurate, appropriately-gated score both the student and the lecturer can see.
**Verified:** 2026-07-16
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | GRD-01: MCQ answers auto-scored on submission via a single hook inside `lockAndFinalize`, covering manual submit AND lazy expiry, defensive against no-answer/no-correct-option | ✓ VERIFIED | `app/Services/AttemptGrader::gradeAutoGradable()` writes `is_correct`/`score` only for existing Answer rows; skips untouched questions (`continue`); defensive `firstWhere('is_correct', true)?->id` for missing-correct-option case. Hook is called at `app/Models/Attempt.php:159` (`app(AttemptGrader::class)->handleFinalized($locked)`) inside the existing `DB::transaction()`/`lockForUpdate()` branch of `lockAndFinalize()`, which both `finalize()` and `finalizeIfExpired()` call. `tests/Feature/Grading/AttemptGraderTest::test_submitting_auto_grades_every_mcq_answer` and `test_auto_grading_fires_on_lazy_expiry` independently exercise both paths — both PASS (verified by direct test run, not SUMMARY claim). |
| 2 | GRD-02: lecturer grades open-text in [0,points], over/negative rejected 422, open-text-only, non-lecturer forbidden | ✓ VERIFIED | `app/Http/Requests/Lecturer/GradeAnswerRequest.php` — `rules()` computes `max:` from the route-bound answer's `question.points` server-side (never client-supplied); `authorize()` rejects unless `question->type === QuestionType::Open` and attempt status is submitted/graded. `app/Http/Controllers/Lecturer/AnswerGradeController::update()` writes only the `score` key explicitly (never `$request->all()`). Route is inside `role:lecturer` group (`routes/lecturer.php:49-50`). All 5 `tests/Feature/Lecturer/GradeAnswerTest` methods PASS (in-bounds accepted, over-points 422, negative 422, non-lecturer/guest forbidden, MCQ-target rejected). |
| 3 | GRD-03: result hidden until all open-text graded; all-MCQ graded immediately; attempts.score = Σ answers.score; syncStatus idempotent | ✓ VERIFIED | `AttemptGrader::syncStatus()` counts pending via `whereNull('score')->whereHas('question', type=Open)` (missing rows never pending) and only flips to `graded` + recomputes `score = answers()->sum('score')` when nothing pending — called both from `handleFinalized()` (finalize time) and again standalone from `AnswerGradeController::update()` after each grade-save under a fresh `lockForUpdate()`. `test_open_text_exam_stays_submitted_until_graded` proves stays-submitted-then-flips-on-last-grade with correct sum (6 = 2+4); `test_all_mcq_exam_grades_immediately` proves immediate all-MCQ transition. Both PASS. |
| 4 | GRD-04: student views own graded result (total + breakdown); ownership-only viewResult (survives unpublish); IDOR forbidden; no correct-option leak | ✓ VERIFIED | `AttemptPolicy::viewResult()` is `$attempt->user_id === $user->id` only — independent of `Exam::visibleTo()` used by `view()`/`update()`. `Student\ResultController::show()` calls `authorize('viewResult', ...)` first, passes **no** score data when `status !== 'graded'`, and for the graded breakdown never queries `Option::where('is_correct', true)` — only the student's own `selectedOption`/`answer_text` + already-stored `is_correct`/`score`. `tests/Feature/Student/ResultTest`: `test_result_is_withheld_while_pending`, `test_result_shown_when_graded`, `test_cannot_view_another_students_result` (403), `test_result_visible_after_exam_unpublished` (200 after `is_published=false`), `test_breakdown_never_exposes_the_correct_option` (assertDontSee the correct option body) — all 5 PASS. |
| 5 | GRD-05: lecturer views results per exam and per student | ✓ VERIFIED | `Lecturer\ResultController::index($exam)` lists all attempts with status+score (dash while pending); `show($exam, $attempt)` renders the per-question breakdown with nested-binding integrity check (`abort_unless($attempt->exam_id === $exam->id, 404)`). Routes registered in `role:lecturer` group. `resources/views/lecturer/exams/show.blade.php` has the "View Results" entry link. `tests/Feature/Lecturer/ResultTest::test_index_lists_attempts_per_exam` and `test_show_renders_breakdown` PASS. |

**Score:** 5/5 truths verified (0 present-but-behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Services/AttemptGrader.php` | `handleFinalized`/`gradeAutoGradable`/`syncStatus` | ✓ VERIFIED | All 3 methods present, substantive (73 lines), each independently exercised by passing tests. |
| `app/Models/Attempt.php` | `lockAndFinalize()` calls the grader inside the existing lock/transaction | ✓ VERIFIED | Line 159, inside the `if ($locked->status === 'in_progress' && $guard($locked))` branch, same `DB::transaction`/`lockForUpdate` shape unchanged from Phase 4 (verified by reading the full method body — lock-then-check-then-update primitive is intact). |
| `app/Policies/AttemptPolicy.php` | `viewResult()` ownership-only | ✓ VERIFIED | Present, distinct from `ownAndTakeable()`-derived `view()`/`update()`. |
| `app/Http/Requests/Lecturer/GradeAnswerRequest.php` | bounded score validation | ✓ VERIFIED | `max:` computed from route-bound question points; `authorize()` gates type + status. |
| `app/Http/Controllers/Student/ResultController.php` | gated `show()` | ✓ VERIFIED | View-model contract (no score data unless graded); no correct-option query. |
| `app/Http/Controllers/Lecturer/ResultController.php` | `index()` + `show()` | ✓ VERIFIED | Both present, role:lecturer gated, no per-lecturer ownership (matches documented Phase 2/3 precedent). |
| `app/Http/Controllers/Lecturer/AnswerGradeController.php` | locked grade-save + syncStatus | ✓ VERIFIED | `DB::transaction` + `Attempt::lockForUpdate()`, explicit single-key `score` write, calls `syncStatus()`. |
| `resources/views/student/results/show.blade.php` | two gated states | ✓ VERIFIED | Awaiting (zero score data) / graded (total + breakdown, no correct-option text) states present. |
| `resources/views/lecturer/results/show.blade.php` | breakdown + inline grade forms | ✓ VERIFIED | Per-question cards, progress bar, "Save Score" form PATCHing `lecturer.attempts.answers.grade`. |
| `resources/views/lecturer/results/index.blade.php` | per-exam attempt table | ✓ VERIFIED | Student/Status/Score/Action columns, "—" for pending score, "No submissions yet" empty state. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `Attempt::lockAndFinalize()` | `AttemptGrader::handleFinalized()` | called inside the existing finalize transaction | ✓ WIRED | Confirmed at Attempt.php:159, both `finalize()` and `finalizeIfExpired()` route through `lockAndFinalize()`. |
| `Student\ResultController::show()` | `AttemptPolicy::viewResult()` | `$this->authorize('viewResult', $attempt)` | ✓ WIRED | First line of `show()`. |
| `Student\ResultController::show()` | `student/results/show.blade.php` | gated view-model | ✓ WIRED | `awaiting` flag branches data passed; verified render via passing tests + manual read. |
| `Lecturer\AnswerGradeController::update()` | `AttemptGrader::syncStatus()` | called after score write, under `lockForUpdate` | ✓ WIRED | Line 34, inside `DB::transaction`. |
| `lecturer/results/show.blade.php` | `AnswerGradeController::update()` | PATCH form to `lecturer.attempts.answers.grade` | ✓ WIRED | Form action confirmed at line 112 of the view. |
| `lecturer/exams/show.blade.php` | `Lecturer\ResultController::index()` | "View Results" link | ✓ WIRED | Confirmed present per 05-04-SUMMARY and route registration; index route active. |

### Behavioral Spot-Checks / Test Execution

Ran the actual test suite directly (not trusting SUMMARY claims):

| Command | Result | Status |
|---------|--------|--------|
| `php artisan test --filter=AttemptGraderTest` | 4 passed (16 assertions) | ✓ PASS |
| `php artisan test --filter=GradeAnswerTest` | 5 passed (12 assertions) | ✓ PASS |
| `php artisan test --filter=ResultTest` | 7 passed (16 assertions) — 5 Student + 2 Lecturer | ✓ PASS |
| `php artisan test` (full suite) | 171 passed (428 assertions), 0 failed | ✓ PASS |

Full-suite run confirms Phase 1-4 regression tests (AttemptSubmitTest, AttemptAnswerTest, AttemptStartTest, AttemptShowTest, Phase4ReviewFixesTest, AttemptPolicyTest, ExamAccessTest, etc.) all still pass — the finalize hook did not break Phase-4 concurrency/timer/race guarantees. The `lockAndFinalize()` lock-then-check-then-update shape, the 3-retry `DB::transaction`, and the `Attempt::whereKey()->lockForUpdate()` re-read are unchanged; the grader hook was added strictly inside the existing guarded branch.

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| GRD-01 | 05-02 | MCQ auto-graded on submission | ✓ SATISFIED | AttemptGrader + finalize hook; AttemptGraderTest matrix passes. |
| GRD-02 | 05-03 | Lecturer grades open-text within [0,points] | ✓ SATISFIED | GradeAnswerRequest + AnswerGradeController; GradeAnswerTest passes. |
| GRD-03 | 05-02, 05-03 | Result gated until all open-text graded | ✓ SATISFIED | syncStatus completeness gate; both finalize-time and grade-save-time call sites verified. |
| GRD-04 | 05-02 | Student views own graded result, no answer-key leak | ✓ SATISFIED | viewResult policy + no-Option-query breakdown; ResultTest passes including IDOR + no-leak. |
| GRD-05 | 05-03, 05-04 | Lecturer views results per exam and per student | ✓ SATISFIED | index()/show() + View Results entry point; Lecturer ResultTest passes. |

No orphaned requirements — REQUIREMENTS.md traceability table maps all 5 GRD-* IDs to Phase 5 and all 4 plans (05-01..05-04) declare them in frontmatter `requirements`.

### Anti-Patterns Found

None. Scanned all Phase-5-touched production files (`AttemptGrader.php`, `Attempt.php`, `AttemptPolicy.php`, `GradeAnswerRequest.php`, the 3 controllers, the 3 views) for TODO/FIXME/XXX/TBD/HACK/PLACEHOLDER/"not yet implemented"/hardcoded-empty-return patterns. One incidental grep hit was a false positive (the phrase "placeholder write" inside an explanatory doc comment about *not* writing a placeholder row — not a debt marker). No schema changes beyond what Phase 1 already defined (`graded_by`/`graded_at` deliberately not added, matching the plan's explicit constraint). No new Composer packages.

### Scope Check (v2 leakage)

Grepped for "partial credit", "rubric", "analytics", "weighted", "curve" across `app/` — no matches. Grading is strictly binary MCQ (points-or-zero) plus lecturer-assigned bounded open-text score, matching v1 GRD-02's "assigning a score up to the question's point value" — not a rubric/multi-criteria system. No analytics/dashboard code found. No v2 scope leaked into Phase 5.

### Human Verification Required

None. All must-haves are server-side, request/response, and database-state assertions fully covered by the automated Feature test suite, independently re-run during this verification (not just SUMMARY claims).

### Gaps Summary

No gaps found. All 5 observable truths verified against actual source code (not SUMMARY narrative), all required artifacts exist/are substantive/are wired, all key links confirmed, the full 171-test suite passes with zero failures (independently executed), Phase-4 concurrency/lock/transaction shape is unchanged, and no v2 scope crept into the implementation.

---

*Verified: 2026-07-16*
*Verifier: Claude (gsd-verifier)*

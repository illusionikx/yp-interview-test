---
phase: 10-exam-integrity-auto-assignment-attempt-lifecycle
verified: 2026-07-18T00:00:00Z
status: human_needed
score: 12/12 must-haves verified
behavior_unverified: 0
overrides_applied: 0
human_verification:
  - test: "Read the reset-submissions confirm modal with a graded>0 fixture (1 in-progress + 1 submitted + 1 graded attempt) as a logged-in lecturer, and read the save-edit warning modal (both graded=0 and graded>0 variants) live in the browser."
    expected: "The modal copy is understood by a human as stating a permanent, irreversible loss of graded work before they click confirm."
    why_human: "10-VALIDATION.md's one manual-only verification item (INT-02: 'one human read-through of both modals, graded=0 and graded>0'). 10-07's checkpoint was approved live but scope was explicitly limited to the reset modal's ungraded variant only ('the graded red clause, the toast, and the INT-03 retake were not seen by a human'). 10-09's checkpoint for the save-edit warning modal was approved AFK on the basis of automated test evidence and plan-time copy review, not an independently executed live browser read-through (stated explicitly in 10-09-SUMMARY.md). The graded-population variant of both modals has never been read live by a human; only PHPUnit has asserted its exact string content."
---

# Phase 10: Exam Integrity — Auto-Assignment & Attempt Lifecycle Verification Report

**Phase Goal:** Exams reach exactly the right students with no manual assignment step, and a lecturer can revise or reset an exam without silently destroying graded work or stranding a student mid-attempt.
**Verified:** 2026-07-18
**Status:** human_needed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths (Roadmap Success Criteria + Focus Areas)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1a | A student enrolled in any class of a subject automatically sees and can start every active exam in that subject, with no per-class assignment step | ✓ VERIFIED | `app/Models/Exam.php:94-102` `scopeVisibleTo()` derives visibility from `subject.sections.enrollments`, no per-exam assignment. `CrossSubjectVisibilityTest::test_a_student_enrolled_in_the_exams_own_subject_can_see_open_and_start_it` passes (list + show + start, any section of the subject). `ExamIndexTest::test_index_includes_a_published_exam_for_any_section_of_the_same_subject` passes. |
| 1b | A student enrolled only in a *different* subject can never see or start it, prevented structurally rather than by list filtering | ✓ VERIFIED | `exam_section` pivot table dropped (`database/migrations/2026_07_17_100001_drop_exam_section_table.php`) — no `Exam::sections()`/`Section::exams()` relation exists anywhere in `app/` (grep confirms zero non-migration references). `CrossSubjectVisibilityTest::test_a_student_enrolled_only_in_a_different_subject_cannot_see_open_or_start_the_exam` is an explicit **negative** test across all 3 surfaces (list via `Exam::visibleTo()->exists()` assertFalse, direct `GET student.exams.show` assertForbidden, write-path `POST student.attempts.store` assertForbidden + assertDatabaseMissing). Includes a load-bearing `assertNotSame($subjectA->id, $subjectB->id)` fixture guard and a paired positive-control test, per plan 01's mandate that a positive-only test cannot prove the leak stayed closed. `ExamPolicy::takeable()` and `Student\ExamController::index()` both delegate to the same `Exam::visibleTo()` predicate (list/gate cannot diverge). |
| 2 | A lecturer can toggle an exam between draft and active in both directions, including after students have already attempted it | ✓ VERIFIED | `ExamController::publish()`/`unpublish()` (`app/Http/Controllers/Lecturer/ExamController.php:141-173`) each do a single `is_published` update, never touch `attempts`. `ExamPublishTest::test_a_lecturer_can_unpublish_a_published_exam` and `Phase5ReviewFixesTest::test_an_attempted_exam_can_now_be_unpublished` (inverting the old HIGH-02 lock) both pass. |
| 3 | Resetting an exam's submissions — or saving an edit to an exam that has been attempted — warns the lecturer first with the concrete count of attempts and graded results at stake, and changes nothing until confirmed | ✓ VERIFIED | `AttemptVoider::summarize()` computes 5 exact counts from one grouped aggregate (`app/Services/AttemptVoider.php:55-73`), pinned exactly by `AttemptVoiderTest::test_summarize_reports_all_five_counts_exactly` (1 in-progress + 1 submitted + 1 graded → asserts each of 5 numbers). `resources/views/lecturer/exams/show.blade.php`'s Submissions panel and `_save-warning-modal.blade.php` both read the same `$attemptCounts`, gated behind `<x-confirm-modal>` (blocking, Cancel commits nothing). `ExamUpdateVoidsAttemptsTest::test_a_failed_validation_on_an_attempted_exam_destroys_no_attempts` proves a rejected save changes nothing (`assertDatabaseCount('attempts', 3)`). |
| 4 | A student whose attempt was cancelled or reset can take that exam again | ✓ VERIFIED | `ResetSubmissionsTest::test_a_student_whose_attempt_was_reset_can_start_the_exam_again` passes — falls out of `AttemptVoider::void()`'s hard delete releasing `attempts.unique(exam_id,user_id)`, exercised through the real `AttemptController::store()` `firstOrCreate` path. |
| 5 | Changing an exam's assignment or status reports its outcome visibly, rather than appearing to return to the same page with nothing changed | ✓ VERIFIED | `ExamPublishTest::test_publishing_reports_the_outcome_to_the_lecturer` / `test_unpublishing_reports_that_existing_attempts_are_unaffected` pass. `ExamController::resetSubmissions()` flashes `"Reset :count submission(s)..."`. `ExamUpdateVoidsAttemptsTest::test_a_save_that_voids_attempts_reports_the_side_effect_in_its_flash` asserts the exact flash string `"Exam updated. 3 affected attempt(s) were reset."`. The manual-assignment screen (FIX-03's original bug source) is deleted outright — no controller, request, route, or view remains (confirmed by grep: zero hits for `Assignment` in `app/`, `routes/lecturer.php`, `resources/views`). |
| INT-01 (repo-wide guard) | `lockAndFinalize()`'s null-guard is applied at every attempt-deletion-adjacent locked-read site | ✓ VERIFIED | Three sites confirmed: (1) `Attempt::lockAndFinalize()` (`app/Models/Attempt.php:160-162`, Phase 9), (2) `Student\AttemptController::answer()` (`app/Http/Controllers/Student/AttemptController.php:191-193`, Phase 9), (3) `Lecturer\AnswerGradeController::update()` (`app/Http/Controllers/Lecturer/AnswerGradeController.php:49-51`, this phase's D-5 addition — the "third and last such site"). All three throw `AttemptVanishedException`. `AttemptNullGuardTest` exercises all three (8 passing methods, including the lecturer-redirect fix). |
| INT-02 (AttemptVoider lock-guard) | Hard-delete is guarded by the same row lock `lockAndFinalize()` takes; reset and edit paths both route through it, never a local delete | ✓ VERIFIED | `AttemptVoider::void()` (`app/Services/AttemptVoider.php:103-116`) uses `lockForUpdate()` inside `DB::transaction()`. `ExamController::resetSubmissions()` and all 4 EDT-04 mutation sites call `app(AttemptVoider::class)->void()` — grep confirms zero other `Attempt::...->delete()` call sites outside `AttemptVoider.php` and test files. |
| EDT-04 (4 gate sites + atomicity) | `UpdateExamRequest`, `StoreQuestionRequest`, `UpdateQuestionRequest` `authorize()` all `return true`; `ExamQuestionController::destroy()`'s inline `abort_if` is removed; each of the 4 mutations wraps write+conditional-void in one `DB::transaction` (both-or-neither) | ✓ VERIFIED | All 4 Form Request/controller sites read and confirmed (see evidence above). `ExamUpdateVoidsAttemptsTest`'s 9 methods cover all four mutations voiding attempts, the zero-attempt no-op branch, the exact flash copy, and the D-7 validation-before-void ordering. The whole-exam `destroy()` gate (`abort_if($exam->is_published, 403)`) and `role:lecturer` route-group middleware (`routes/lecturer.php:13`) both confirmed unchanged and still present. |
| CLS-05/CLS-06/CLS-07 | Publish toggle never touches attempts; reset delegates to `AttemptVoider`; visibility is subject-derived | ✓ VERIFIED | See rows 1a/2/3 above. |

**Score:** 12/12 truths verified (0 present-but-behavior-unverified).

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `tests/Feature/Student/CrossSubjectVisibilityTest.php` | INT-04 negative regression, all 3 surfaces | ✓ VERIFIED | 2 methods, both green; negative + positive control per plan 01. |
| `tests/Feature/AttemptVoiderTest.php` | INT-02 count-correctness spec | ✓ VERIFIED | 5 methods, all green; exact-count assertions confirmed. |
| `tests/Feature/Lecturer/ResetSubmissionsTest.php` | CLS-07 reset + INT-03 retake | ✓ VERIFIED | 6 methods green. |
| `tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php` | EDT-04 across all 4 mutations | ✓ VERIFIED | 9 methods green. |
| `tests/Feature/AttemptNullGuardTest.php` | D-5 third crash site | ✓ VERIFIED | 8 methods green, including lecturer-redirect fix. |
| `app/Services/AttemptVoider.php` | Single voiding authority — `summarize()`/`void()` | ✓ VERIFIED | 118 lines, `lockForUpdate()` present, both methods implemented and wired from 3 controllers. |
| `app/Http/Controllers/Lecturer/AnswerGradeController.php` | D-5 null-guard | ✓ VERIFIED | Guard present at line 49. |
| `app/Exceptions/AttemptVanishedException.php` | Lecturer-safe redirect branch | ✓ VERIFIED | `routeIs('lecturer.*')` branch present, precedes the student redirect. |
| `app/Models/Exam.php` | `scopeVisibleTo()` subject-derived | ✓ VERIFIED | Confirmed no per-exam section walk; `whereHas('subject.sections.enrollments', ...)`. |
| `database/migrations/2026_07_17_100001_drop_exam_section_table.php` | Schema-break migration | ✓ VERIFIED | `dropIfExists('exam_section')` present; table confirmed absent from all app/route/view code. |
| Assignment feature (controller/request/route/view) | Deleted outright (FIX-03) | ✓ VERIFIED | Zero matches for "Assignment" across `app/`, `routes/lecturer.php`, `resources/views`. `ExamAssignmentTest` file does not exist. |
| `resources/views/lecturer/exams/_save-warning-modal.blade.php` | Single warning-copy source, both variants | ✓ VERIFIED | Both graded=0 and graded>0 copy branches present; included from `edit.blade.php` and `questions/_form.blade.php`. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `tests/Feature/Student/CrossSubjectVisibilityTest.php` | `app/Models/Exam.php` | `Exam::visibleTo()` scope | ✓ WIRED | `scopeVisibleTo` resolves to `Exam::visibleTo()`; both list (`Student\ExamController::index`) and gate (`ExamPolicy::takeable`) delegate to it — confirmed never re-derived elsewhere. |
| `tests/Feature/AttemptVoiderTest.php` | `app/Services/AttemptVoider.php` | `app(AttemptVoider::class)->summarize()/void()` | ✓ WIRED | Confirmed via test pass and controller call sites. |
| `app/Http/Controllers/Lecturer/ExamController.php` | `app/Services/AttemptVoider.php` | `resetSubmissions()`/`update()` call `void()`/`summarize()` | ✓ WIRED | Confirmed inline, no duplicate delete logic. |
| `resources/views/lecturer/exams/show.blade.php` | `resources/views/components/confirm-modal.blade.php` | `x-ref` form + `@submit.prevent` + `x-on:click="$refs...submit()"` | ✓ WIRED | Reset-submissions form/modal pairing confirmed at `show.blade.php:172-181`. |
| `resources/views/lecturer/exams/edit.blade.php` | `_save-warning-modal.blade.php` | `@include` with `formRef` | ✓ WIRED | Confirmed at `edit.blade.php:73`, dispatched via `open-modal` on `@submit.prevent`. |
| `app/Http/Controllers/Lecturer/AnswerGradeController.php` | `app/Exceptions/AttemptVanishedException.php` | Locked-read null throw | ✓ WIRED | Confirmed, `AttemptNullGuardTest` exercises it end-to-end. |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Full test suite (all Phase 10 + regression) | `php artisan test` | 360 passed (880 assertions), 0 failed, 0 errors, 19.46s | ✓ PASS |
| Cross-subject negative test | `--filter=CrossSubjectVisibilityTest` (subset of full run) | 2/2 passed | ✓ PASS |
| Count-correctness spec | `--filter=AttemptVoiderTest` (subset) | 5/5 passed | ✓ PASS |
| Reset + retake | `--filter=ResetSubmissionsTest` (subset) | 6/6 passed | ✓ PASS |
| EDT-04 all 4 mutations, atomicity, copy variance | `--filter=ExamUpdateVoidsAttemptsTest` (subset) | 9/9 passed | ✓ PASS |
| INT-01 repo-wide null-guard, 3 sites | `--filter=AttemptNullGuardTest` (subset) | 8/8 passed | ✓ PASS |
| Publish/unpublish both directions | `--filter=ExamPublishTest` (subset) | 7/7 passed | ✓ PASS |
| Gate relaxation (all 4 sites reachable) | `--filter=ExamPublishedEditGateTest` (subset) | 10/10 passed | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| INT-02 | 01, 02, 04, 07, 08 | Reset/save-edit never destroys graded work silently | ✓ SATISFIED | `AttemptVoiderTest`, `ExamUpdateVoidsAttemptsTest`, warning modal wiring. |
| INT-03 | 02, 04, 07 | Reset/cancelled attempt can retake | ✓ SATISFIED | `ResetSubmissionsTest::test_a_student_whose_attempt_was_reset_can_start_the_exam_again`. |
| INT-04 | 01, 06 | No cross-subject visibility/takeability | ✓ SATISFIED | `CrossSubjectVisibilityTest` (negative + positive), pivot dropped. **Note:** REQUIREMENTS.md's checkbox for INT-04 is still unchecked (`- [ ]`) and its status table row reads "Pending" — this is a stale tracking-document gap, not a code gap; the implementation and tests are complete. |
| CLS-05 | 06 | Auto-assignment via subject enrollment | ✓ SATISFIED | `Exam::scopeVisibleTo()`, `ExamIndexTest`. **Note:** same stale-tracking gap as INT-04 — REQUIREMENTS.md shows `- [ ]` / "Pending" despite complete, tested implementation. |
| CLS-06 | 05 | Bidirectional publish toggle | ✓ SATISFIED | `ExamPublishTest`, `Phase5ReviewFixesTest`. |
| CLS-07 | 04, 07 | Reset submissions behind a warning | ✓ SATISFIED | `ResetSubmissionsTest`, Submissions panel. |
| EDT-04 | 02, 04, 08, 09 | Warn-and-void editor mutations, atomic | ✓ SATISFIED | `ExamUpdateVoidsAttemptsTest`, 4 gate sites relaxed, `_save-warning-modal.blade.php`. |
| FIX-03 | 05 | Clear feedback on assignment change | ✓ SATISFIED | Satisfied by removal — assignment screen deleted outright. |

No orphaned requirements found — all 8 Phase 10 requirement IDs (INT-02, INT-03, INT-04, CLS-05, CLS-06, CLS-07, EDT-04, FIX-03) appear in plan frontmatter and are covered above. INT-01 (Phase 9-owned) was cross-checked as a repo-wide invariant per the verification brief's focus area.

### Anti-Patterns Found

None. Scanned all 16 phase-modified controller/model/service/exception/request files plus the 5 phase-modified Blade views for `TBD`/`FIXME`/`XXX`/`TODO`/`HACK`/`PLACEHOLDER`/empty-return/hardcoded-empty-data patterns. Two incidental matches were reviewed and are non-issues: `AttemptController.php:68` ("not available yet") is a legitimate user-facing availability message, not a stub marker; `questions/_form.blade.php:93` (`:placeholder="__('Option text')"`) is a standard HTML input placeholder attribute, not a code stub.

### Documentation Discrepancy (not a code gap)

`.planning/REQUIREMENTS.md` lines 24 and 60 show `INT-04` and `CLS-05` as unchecked (`- [ ]`), and its per-phase status table (lines 198-199) lists both as "Pending" while every other Phase 10 requirement reads "Complete". Direct code and test verification above confirms both are fully implemented and covered by passing tests (`CrossSubjectVisibilityTest`, `ExamIndexTest`). This looks like a tracking-document update that was missed when the phase closed — worth a follow-up edit to REQUIREMENTS.md, but it does not indicate incomplete implementation.

### Human Verification Required

### 1. Live read-through of the graded-population warning modal copy (both Reset and Save-edit)

**Test:** Log in as the seeded lecturer, seed (or use) an exam with 1 in-progress + 1 submitted-ungraded + 1 graded attempt. Trigger the Reset-submissions modal and read its graded>0 copy. Then edit the same exam's details/questions and read the save-edit warning modal's graded>0 copy.
**Expected:** A human reading either modal understands, before clicking confirm, that graded scores will be permanently and irreversibly deleted — not merely that "attempts will be reset."
**Why human:** This is 10-VALIDATION.md's sole Manual-Only Verification item for INT-02 ("PHPUnit can assert the strings and counts, but not that a lecturer *understands* they are about to permanently delete scores... one human read-through of both modals, graded=0 and graded>0, is warranted"). Two plan checkpoints touched this:
- **10-07** (reset modal): a human did review a running app, but explicitly scoped to the **ungraded** variant only — the SUMMARY states verbatim "the graded red clause, the toast, and the INT-03 retake were not seen by a human — they are covered by ResetSubmissionsTest's 6 green assertions, which do assert both copy variants' exact strings."
- **10-09** (save-edit modal): the checkpoint was approved **AFK**, on the stated basis of "passing automated evidence... and the copy's plan-time review — not an independently executed live browser read-through."
Both variants' exact strings are PHPUnit-verified (`ExamUpdateVoidsAttemptsTest::test_the_edit_page_warning_names_the_graded_population_when_scores_are_at_stake`, and the reset modal's copy is rendered from the same `AttemptVoider::summarize()` output covered by `AttemptVoiderTest`), but the graded-population copy of neither modal has been read live by a human end to end. This is a UX-comprehension check that automated string assertions cannot fully substitute for, given D-2's hard delete has no undo.

### Gaps Summary

No gaps found. All 12 must-have truths (5 roadmap success criteria plus 7 focus-area invariants drawn from the verification brief) are verified against live code and a green 360-test full suite. One human-verification item remains open — a live browser read-through of the graded-population warning copy in both destructive-action modals — which was only partially completed during plan execution (10-07 covered the ungraded variant live; 10-09's checkpoint was approved on automated evidence alone). This does not block the phase goal from being considered achieved at the code level, but should be closed via `/gsd-verify-work 10` before this phase is treated as fully signed off for a system whose single most safety-critical control (D-2's irreversible hard delete) depends on a human correctly reading a warning.

---

_Verified: 2026-07-18_
_Verifier: Claude (gsd-verifier)_

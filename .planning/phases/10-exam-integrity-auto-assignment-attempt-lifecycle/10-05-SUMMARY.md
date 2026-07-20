---
phase: 10-exam-integrity-auto-assignment-attempt-lifecycle
plan: 05
subsystem: api
tags: [laravel, blade, phpunit, exam-lifecycle, requirements-satisfied-by-removal]

# Dependency graph
requires:
  - phase: 10-exam-integrity-auto-assignment-attempt-lifecycle (plans 01-02)
    provides: earlier Wave-0 groundwork this plan builds on within the same phase
provides:
  - The manual exam-assignment feature (controller, request, route, view panel, tests) deleted outright
  - ExamController::unpublish() toggles both directions including post-attempt, without touching attempt data
  - Toast copy on publish()/unpublish() matching 10-UI-SPEC.md verbatim
  - Phase5ReviewFixesTest's HIGH-02 regression inverted to CLS-06's contract, fixture off the exam_section pivot
affects: [10-06 (drops the exam_section table/relations), 10-08 (EDT-04 warn-and-void, reuses the "attempts survive vs. attempts voided" distinction this plan establishes), 12 (lecturer workspace UI, reuses toggle + toast copy)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Requirement satisfied by removal — FIX-03's bug cannot exist once the screen that caused it is deleted; no feature was built to compensate"
    - "Toggle vs. reset distinction — CLS-06's unpublish toggle never touches attempt rows (assertDatabaseCount pins survival); CLS-07's reset (later plan) is the only path that deletes them"

key-files:
  created: []
  modified:
    - app/Http/Controllers/Lecturer/ExamController.php
    - routes/lecturer.php
    - resources/views/lecturer/exams/show.blade.php
    - tests/Feature/Lecturer/ExamPublishTest.php
    - tests/Feature/Lecturer/Phase5ReviewFixesTest.php
    - app/Http/Requests/Student/AnswerRequest.php
    - .planning/REQUIREMENTS.md

key-decisions:
  - "FIX-03 marked satisfied-by-removal, not built-and-verified — the assignment screen no longer exists, so its feedback bug is structurally unreachable"
  - "Deleted a stale doc-comment reference to AssignExamRequest in AnswerRequest.php (Rule 1 — dangling reference to a now-nonexistent class) rather than leaving a misleading analog pointer"
  - "Rewrote publish()'s doc comment (previously described 'making it eligible for section assignment') since that concept no longer exists post-D-1"

patterns-established:
  - "Inverting a regression test: rename + flip assertion + update the class/method doc comment to say WHY the old contract no longer holds, rather than silently flipping assertTrue to assertFalse under a stale comment"

requirements-completed: [FIX-03, CLS-06]

# Metrics
duration: 9min
completed: 2026-07-17
status: complete
---

# Phase 10 Plan 05: Delete Exam Assignment & Fix Publish Toggle Summary

**Deleted the manual exam-assignment feature outright (satisfying FIX-03 by removal) and made the draft/published toggle reversible in both directions post-attempt (CLS-06), with UI-SPEC-verbatim toast copy.**

## Performance

- **Duration:** 9 min
- **Started:** 2026-07-17T08:39:30Z
- **Completed:** 2026-07-17T08:48:34Z
- **Tasks:** 2/2 completed
- **Files modified:** 8 (3 deleted, 5 modified) + REQUIREMENTS.md

## Accomplishments

- The manual exam-to-section assignment surface — `ExamAssignmentController`, `AssignExamRequest`, the `exams.assignment.update` route, the "Assign to sections" view panel, and `ExamAssignmentTest` — no longer exists anywhere in the codebase. FIX-03 ("Update assignment gives no visible feedback") cannot occur because the button that caused it is gone. **No toaster was built for the deleted screen.**
- `ExamController::show()` no longer eager-loads the `sections` relation or queries subject-scoped sections; the now-unused `App\Models\Section` import is gone. `Subject::exams()` (the `HasMany` backing `SubjectController:69`'s delete guard) was left untouched, confirmed by `SubjectControllerTest` staying green.
- `ExamController::unpublish()`'s `attempts()->exists()` guard is removed. The toggle now works in both directions — including after students have attempted the exam — without deleting or touching any attempt row.
- Both `publish()` and `unpublish()` now flash UI-SPEC-verbatim toast copy via `__()`, replacing the old bare "Exam published."/"Exam moved back to draft." strings.
- `Phase5ReviewFixesTest`'s HIGH-02 regression (`test_an_attempted_exam_cannot_be_unpublished`) was **inverted**, not deleted: renamed to `test_an_attempted_exam_can_now_be_unpublished`, asserting the toggle succeeds AND `assertDatabaseCount('attempts', 1)` — proving the toggle is non-destructive, which is exactly what distinguishes CLS-06 from CLS-07's reset. Its fixture dropped the `Section::factory()`/`sections()->sync()`/enrollment scaffolding entirely (a direct `Attempt::factory()->create()` needs no visibility setup), removing this file from plan 06's pivot-drop blast radius.

## Task Commits

1. **Task 1: Delete the exam-assignment feature (D-1, FIX-03 satisfied-by-removal)** — `8a757ff` (feat)
2. **Task 2: CLS-06 — make the toggle work in both directions, post-attempt, and say so** — `e3787a1` (feat)

**Plan metadata:** pending (this commit)

## Files Created/Modified

- `app/Http/Controllers/Lecturer/ExamAssignmentController.php` — **deleted** (whole file; synced the `exam_section` pivot via `sync()`)
- `app/Http/Requests/Lecturer/AssignExamRequest.php` — **deleted** (whole file; unconditional `authorize()` + subject-scoped `Rule::exists` on `section_ids`)
- `tests/Feature/Lecturer/ExamAssignmentTest.php` — **deleted** (whole file, 6 test methods — see "Deviations" for the count correction)
- `routes/lecturer.php` — removed the `ExamAssignmentController` import and the `PUT exams/{exam}/assignment` route
- `resources/views/lecturer/exams/show.blade.php` — removed the "Assign to sections" `<div>` panel (heading, section-checkbox `@forelse`, error block, submit button)
- `app/Http/Controllers/Lecturer/ExamController.php` — `show()` trimmed off the `sections` eager-load, the `$sections` query, and the `Section` import; `publish()`/`unpublish()` toast copy now matches UI-SPEC verbatim; `unpublish()`'s attempts-exist guard removed; both methods' doc comments rewritten (publish() no longer references "section assignment"; unpublish() records HIGH-02 as superseded by D-4/D-6/EDT-04, not abandoned)
- `tests/Feature/Lecturer/ExamPublishTest.php` — added `test_publishing_reports_the_outcome_to_the_lecturer` and `test_unpublishing_reports_that_existing_attempts_are_unaffected`, asserting `assertSessionHas('status', ...)` against the exact UI-SPEC strings
- `tests/Feature/Lecturer/Phase5ReviewFixesTest.php` — inverted and renamed the HIGH-02 test; simplified its fixture off the pivot; rewrote the class-level and inline doc comments to state HIGH-02 is superseded, not silently reversed
- `app/Http/Requests/Student/AnswerRequest.php` — updated a stale doc-comment reference that named the now-deleted `AssignExamRequest` as an analog
- `.planning/REQUIREMENTS.md` — CLS-06 and FIX-03 checked off; FIX-03's annotation rewritten to state explicitly it is satisfied by removal, not by building a fix

## Decisions Made

- **FIX-03 is recorded as satisfied-by-removal**, not "built and verified" — per the plan's explicit instruction, no toaster was fabricated for a deleted screen. `.planning/REQUIREMENTS.md`'s FIX-03 annotation was rewritten to say this plainly rather than leaving the old speculative "may disappear with CLS-05" note in place now that it has.
- Left `Subject::exams(): HasMany` (backing `SubjectController:69`'s subject-delete guard) completely untouched — confirmed distinct from the deleted `Exam::sections()`/`Section::exams()` `BelongsToMany` pair, per the plan's explicit warning. `SubjectControllerTest` staying green is the proof.
- Did not touch `ExamController::destroy()`'s `abort_if($exam->is_published, 403)` — whole-exam deletion stays draft-only; only unpublishing (a non-destructive toggle) was relaxed.
- Did not touch the `exam_section` pivot table or `Exam::sections()`/`Section::exams()` model relations — those are plan 06's job; this plan only removed the feature's controller/request/route/view/tests that *wrote* to the pivot.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug/stale-reference] Corrected a dangling doc-comment reference in `AnswerRequest.php`**
- **Found during:** Task 1 verification (`grep -rn 'ExamAssignmentController\|AssignExamRequest\|exams.assignment.update' app/ routes/ resources/ tests/`)
- **Issue:** `app/Http/Requests/Student/AnswerRequest.php`'s doc comment cited `AssignExamRequest` as the analog for its "authorize() does ownership, not shape" pattern. After Task 1 deleted that class, the comment pointed to a nonexistent file.
- **Fix:** Reworded the comment to describe the pattern without naming a deleted class, while still crediting where the split originated (now-removed `ExamAssignmentController`, noted as removed in Phase 10/D-1).
- **Files modified:** `app/Http/Requests/Student/AnswerRequest.php`
- **Verification:** The acceptance-criteria grep now returns zero live-code hits; `php artisan test` unaffected (this file has no direct test coverage of its doc comment, obviously, but the class's existing tests continued to pass).
- **Committed in:** `8a757ff` (Task 1 commit)

**2. [Rule 1 - Bug/stale-comment] Rewrote `ExamController::publish()`'s doc comment**
- **Found during:** Task 2, while editing the adjacent `unpublish()` method
- **Issue:** `publish()`'s doc comment read "making it eligible for section assignment (Phase 3)" — a concept D-1 (this phase, plan 01/02) already removed by making visibility subject-derived rather than assignment-derived.
- **Fix:** Reworded to describe the actual current behavior (visibility follows subject enrollment per CLS-05/D-1).
- **Files modified:** `app/Http/Controllers/Lecturer/ExamController.php`
- **Verification:** No test asserts doc-comment content; `ExamPublishTest` continued to pass.
- **Committed in:** `e3787a1` (Task 2 commit)

### Plan Correction (not a code deviation)

**`ExamAssignmentTest.php` had 6 test methods, not 7.** The plan's `<output>` section and multiple acceptance criteria stated "7 tests, all for the removed feature" and expected the full-suite total to drop by exactly 7. Direct read of the file before deletion confirmed exactly 6 methods (`test_a_lecturer_can_assign_an_exam_to_sections`, `test_resyncing_removes_unselected_sections`, `test_assignment_rejects_a_nonexistent_section_id`, `test_a_draft_exam_can_be_assigned_before_publishing`, `test_assignment_rejects_a_section_from_a_different_subject`, `test_a_student_is_forbidden_from_assigning_an_exam`). The full suite dropped from 342 to 336 passing after Task 1 (a drop of 6), matching this recount exactly, with the 23 known Wave-0 RED failures unchanged. This is a plan-authoring miscount, not a code or test issue — flagging it here so the discrepancy doesn't look like an untracked regression.

---

**Total deviations:** 2 auto-fixed (both Rule 1 — stale doc-comment references to deleted code, no behavior change) + 1 plan-count correction (documentation only, no code impact).
**Impact on plan:** Both auto-fixes are hygiene-only (comment text), zero scope creep. The test-count correction reconciles an inaccurate plan expectation against directly observed test-suite behavior; no code changed as a result.

## Issues Encountered

None — both tasks executed cleanly against their read-first files with no blocking discoveries beyond the two stale-comment items and the test-count discrepancy documented above.

## Verification Evidence

**1. Targeted filter (the test that blocks CLS-06):**
```
php artisan test --filter='ExamPublishTest|Phase5ReviewFixesTest'
```
```
PASS  Tests\Feature\Lecturer\ExamPublishTest
  a lecturer can publish a draft exam
  publishing reports the outcome to the lecturer
  unpublishing reports that existing attempts are unaffected
  a lecturer can unpublish a published exam
  unpublishing an exam makes it editable again
  a student is forbidden from publishing an exam
  a student is forbidden from unpublishing an exam

PASS  Tests\Feature\Lecturer\Phase5ReviewFixesTest
  a question over the points cap is rejected
  a question at the points cap is accepted
  an attempted exam can now be unpublished
  an unattempted exam can still be unpublished

Tests:    11 passed (21 assertions)
Duration: 3.57s
```
The inverted CLS-06 test (`an attempted exam can now be unpublished`) passes, and its body asserts `assertDatabaseCount('attempts', 1)` — attempts survive the unpublish.

**2. `SubjectControllerTest` (proves `SubjectController:69`'s delete guard, backed by `Subject::exams(): HasMany`, survived untouched):**
```
php artisan test --filter='SubjectControllerTest'
```
```
PASS  Tests\Feature\Lecturer\SubjectControllerTest
  a lecturer can create a subject
  a lecturer can update a subjects name
  a lecturer can delete a subject
  creating a subject with a blank name fails validation
  a student is forbidden from the subjects index
  a student is forbidden from the subject create form
  a student is forbidden from storing a subject

Tests:    7 passed (13 assertions)
```
This confirms the `HasMany` relation was not touched — only the unrelated `BelongsToMany` (`Exam::sections()`/`Section::exams()`) feature was removed.

**3. Full-suite totals, reconciled against the 342/23 starting baseline:**

| Point | Passed | Failed | Note |
|---|---|---|---|
| Before this plan (measured directly) | 342 | 23 | Confirmed baseline via a fresh `php artisan test` run before any edit |
| After Task 1 (assignment feature deleted) | 336 | 23 | Drop of exactly 6 (`ExamAssignmentTest`'s 6 methods — see Plan Correction above), 23 unchanged (Wave-0 RED specs, none of which reference the assignment feature) |
| After Task 2 (+2 new `ExamPublishTest` cases) | 338 | 23 | +2 as expected; 23 unchanged |

No previously-green test regressed at any point. The 23 failures are the same `Wave 0 RED` specs (`CrossSubjectVisibilityTest` and others owned by later plans in this phase) both before and after — verified by inspecting the failure list, which is unchanged in composition.

**4. Dependency hygiene:**
```
git diff package.json composer.json
```
Empty — confirmed explicitly. Zero new Composer/npm dependencies were introduced, per CLAUDE.md's hard constraint.

**5. Deleted files (enumerated):**
- `app/Http/Controllers/Lecturer/ExamAssignmentController.php`
- `app/Http/Requests/Lecturer/AssignExamRequest.php`
- `tests/Feature/Lecturer/ExamAssignmentTest.php`

**6. Token gate:**
```
bash scripts/ui-03-token-gate.sh
```
`UI-03 TOKEN GATE: PASS — all 18 tokens emit real CSS rules.` Exit 0. (This view's only markup change was a deletion, so no new token surface was introduced.)

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- The `exam_section` pivot table, `Exam::sections()`, and `Section::exams()` remain in place, untouched, exactly as this plan's scope fence required — plan 06 removes them together with the migration.
- `Phase5ReviewFixesTest`'s fixture no longer touches the pivot at all, so plan 06's schema-drop migration will not need to revisit this file.
- CLS-06 and FIX-03 are both complete; `.planning/REQUIREMENTS.md`'s traceability table reflects both as `Complete` under Phase 10.
- No blockers for plan 06 (schema drop) or plan 08 (EDT-04 warn-and-void, which will reuse the same "toggle never touches attempts / reset+edit-void does" distinction this plan established in the test suite).

---
*Phase: 10-exam-integrity-auto-assignment-attempt-lifecycle*
*Completed: 2026-07-17*

## Self-Check: PASSED

- Deleted files confirmed absent: `app/Http/Controllers/Lecturer/ExamAssignmentController.php`, `app/Http/Requests/Lecturer/AssignExamRequest.php`, `tests/Feature/Lecturer/ExamAssignmentTest.php`
- Modified files confirmed present: `app/Http/Controllers/Lecturer/ExamController.php`, `routes/lecturer.php`, `resources/views/lecturer/exams/show.blade.php`, `tests/Feature/Lecturer/ExamPublishTest.php`, `tests/Feature/Lecturer/Phase5ReviewFixesTest.php`, `app/Http/Requests/Student/AnswerRequest.php`, `.planning/REQUIREMENTS.md`
- Both task commits confirmed in `git log`: `8a757ff`, `e3787a1`

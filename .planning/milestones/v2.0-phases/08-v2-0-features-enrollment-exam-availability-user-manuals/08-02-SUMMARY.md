---
phase: 08-v2-0-features-enrollment-exam-availability-user-manuals
plan: 02
subsystem: testing
tags: [phpunit, laravel, rbac, tdd-red, enrollment, availability-window]

# Dependency graph
requires:
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 01)
    provides: "Exam::isAvailableNow()/availabilityState(), ExamFactory available()/opening()/closed() states, RejectionReason enum, Enrollment::section()/user() relations, Section::windowStatus(), extended x-status-pill arms"
provides:
  - "Six executable RED test files forming the fixed acceptance contract for enrollment, rejection, and exam-availability surfaces"
  - "Locked route-name contract: student.subjects.index/show, student.sections.enroll/withdraw, lecturer.sections.show, lecturer.sections.enrollments.reject"
  - "ExamShowTest.php as a genuinely new file (corrects 08-VALIDATION.md's 'extend existing' error)"
affects: [08-03, 08-04, 08-05, 08-06, 08-07]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "PHPUnit #[DataProvider] iterating every enum case (RejectionReason::cases()) rather than hardcoding one representative value"
    - "route()-call-throws-RouteNotFoundException as the RED signal for surfaces where no route exists yet, vs. assertion-failure RED for surfaces built on existing routes (e.g. student.attempts.store, lecturer.exams.store)"
    - "Sequential-simulation concurrency test with an explicit doc-comment limitation disclaimer (ENR-02), never implying true race coverage"
    - "Positive-control RED-plan tests that pass before implementation exists, documented explicitly rather than silently accepted, when the assertion encodes a pre-existing or structurally-unavoidable invariant (unbounded exam always startable, blank-datetime-local persists null because the field isn't wired at all yet, D-06's existing published-exam 403 gate)"

key-files:
  created:
    - tests/Feature/Student/SubjectBrowseControllerTest.php
    - tests/Feature/Student/EnrollmentControllerTest.php
    - tests/Feature/Student/AttemptAvailabilityTest.php
    - tests/Feature/Student/ExamShowTest.php
    - tests/Feature/Lecturer/RejectEnrollmentControllerTest.php
    - tests/Feature/Lecturer/ExamAvailabilityTest.php
  modified: []

key-decisions:
  - "Route-name contract finalized exactly as specified in the plan's <route_name_contract> table: student.subjects.index/show (GET), student.sections.enroll (POST)/withdraw (DELETE), lecturer.sections.show (GET, top-level sibling of lecturer.sections.index), lecturer.sections.enrollments.reject (PATCH). 08-04 through 08-07 must match these byte-for-byte."
  - "ExamShowTest.php confirmed genuinely NEW, not an extension. 08-VALIDATION.md's Wave 0 Requirements table row ('extend for AVL-02 pre-start page') should be corrected: tests/Feature/Student/ contains AttemptShowTest, AttemptStartTest, ExamAccessTest, ExamIndexTest, and others, but no ExamShowTest existed before this plan."
  - "Assumption A1 (blank datetime-local -> null coercion) could NOT be resolved by this plan, by design. Both available_from/available_until are entirely unwired in StoreExamRequest/UpdateExamRequest right now, so submitting them as empty strings is indistinguishable from submitting nothing -- the test that exercises A1 currently passes trivially (no validation rule exists to trip on either value). The real A1 verification only becomes meaningful once 08-06 adds the ['nullable','date'] rules; if ConvertEmptyStringsToNull's coercion assumption turns out to be wrong at that point, the fix is a prepareForValidation() normalizer in 08-06, per the plan's explicit instruction not to paper over it here."
  - "Sequential-apply race test (ENR-02) uses two actingAs()->post() calls read via session()->has('status'/'error') immediately after each request, rather than any parallel/async mechanism -- PHPUnit's runner is single-threaded and this is explicitly documented as a simulation, not a concurrency proof."

patterns-established:
  - "For Wave-0 RED plans built on surfaces with zero existing routes (student enrollment, lecturer reject), the correct/expected failure mode is RouteNotFoundException at route() resolution time, not an assertion failure -- verified as the actual failure reason for all 42 such tests before committing."
  - "For Wave-0 RED plans built on ALREADY-EXISTING routes (attempt start, exam show, exam create/update), some pinned tests will legitimately pass before new implementation exists, because they encode invariants that are either already true or structurally unaffected by the missing feature (e.g., an unbounded exam has always been startable; nothing currently touches available_from/until so nothing currently rejects it). These are documented per-file as intentional positive controls rather than treated as authoring defects."

requirements-completed: [ENR-01, ENR-02, ENR-03, ENR-04, ENR-05, ENR-06, ENR-07, AVL-01, AVL-02, AVL-03]

# Metrics
duration: 24min
completed: 2026-07-16
status: complete
---

# Phase 8 Plan 2: Wave 0 RED Contract for Enrollment & Exam Availability Summary

**Six new PHPUnit feature-test files (64 executed test cases, including one 5-case data provider) pinning the executable acceptance contract for ENR-01..ENR-07 and AVL-01..AVL-03 as failing tests, locking six route names and the RejectionReason validation surface before any controller for them exists.**

## Performance

- **Duration:** ~24 min
- **Started:** 2026-07-16T19:54:14+08:00 (first task commit)
- **Completed:** 2026-07-16T20:03:13+08:00 (last task commit)
- **Tasks:** 3
- **Files modified:** 6 (all created, zero modified — test files only, per plan prohibition)

## Accomplishments
- `SubjectBrowseControllerTest` + `EnrollmentControllerTest` — the full ENR-01..ENR-06 contract: live capacity display, out-of-window sections always listed (never hidden), capacity refusal, the ENR-02 sequential-simulation (explicitly disclaimed, not a concurrency proof), exact-boundary window enforcement on both apply and withdraw via `travelTo()`, the one-active-enrollment-per-subject-per-semester rule with two negative controls, re-apply-updates-not-duplicates for both the withdraw and reject paths, and both the T-08-02-MA (forged status/rejection_reason) and T-08-02-IDOR (forged user_id) mass-assignment tests
- `RejectEnrollmentControllerTest` — ENR-07 with all 5 `RejectionReason` cases proven accepted via a `#[DataProvider]` iteration (not hardcoded), the student-visible reason LABEL assertion (not just the DB value), the ENR-07/SEC-03 non-assigned-lecturer 403 negative case, and the "any assigned lecturer, not just the creator" positive case
- `ExamAvailabilityTest` — AVL-01 window persistence (both/only-from/only-until), the A1 blank-string coercion probe, `available_until < available_from` validation, and confirmation that D-06's existing published-exam 403 gate already covers the new fields with zero code changes
- `AttemptAvailabilityTest` — AVL-03 refusal outside the window with exact copy, both exact boundaries via `travelTo()`, and the single most important guard in this plan: an already-started attempt stays resumable even after `available_until` has passed, proving the future gate must live on the new-attempt branch only
- `ExamShowTest` (genuinely new file) — AVL-02's six pre-start page elements (duration, description, window, state pill, enrolled section, Proceed/Back actions) plus reachability while out-of-window and the new red `session('error')` flash convention
- Full suite verified: 200 pre-existing tests still green; 64 new test cases added (14 pass as documented positive controls, 50 fail for the expected missing-implementation reason)

## Task Commits

Each task was committed atomically:

1. **Task 1: Student enrollment RED contract (ENR-01..ENR-06)** - `f496427` (test)
2. **Task 2: Lecturer reject + exam availability RED contract (ENR-07, AVL-01)** - `514b480` (test)
3. **Task 3: Attempt availability gate + pre-start page RED contract (AVL-02, AVL-03)** - `8438977` (test)

**Plan metadata:** commit pending (docs: complete plan)

## Files Created/Modified
- `tests/Feature/Student/SubjectBrowseControllerTest.php` (new) - 6 tests: live capacity, FULL pill + no-Apply, opens/closed-but-listed, open+non-full offers Apply, subject index listing
- `tests/Feature/Student/EnrollmentControllerTest.php` (new) - 18 tests: apply success/refusal (full/window), ENR-02 sequential simulation, both exact-boundary pairs (apply + withdraw), ENR-04 rule + 2 negative controls, ENR-05 re-apply-after-withdraw and re-apply-after-reject (row-count + reason-clear assertions), forged-status/reason, forged-user_id, lecturer-role-refused
- `tests/Feature/Lecturer/RejectEnrollmentControllerTest.php` (new) - 12 tests: 5 data-provider cases (one per `RejectionReason`), exact flash copy, student-visible label, SEC-03 negative, second-assigned-lecturer positive, out-of-enum 422, missing-reason 422, student-role-refused
- `tests/Feature/Lecturer/ExamAvailabilityTest.php` (new) - 8 tests: both/only-from/only-until persistence, A1 blank-string probe, until-before-from 422, draft update, D-06 published-exam 403 (window unchanged), create/edit form field presence
- `tests/Feature/Student/AttemptAvailabilityTest.php` (new) - 10 tests: in-window start, before-open/after-close refusal with exact copy, unbounded/only-from/only-until startable, both exact boundaries, the critical already-started-resumable-after-close composition case, non-enrolled-403-not-availability-flash precedence
- `tests/Feature/Student/ExamShowTest.php` (new) - 10 tests: reachable-while-opening/closed, duration, description, availability window, state pill, enrolled section name, Proceed+Back actions, non-enrolled 403, red error flash rendering

## Decisions Made
- Followed the plan's `<route_name_contract>` table verbatim for all six route names — no deviation, no discretion exercised beyond what the plan already locked.
- Confirmed and recorded the ExamShowTest.php "extend existing → actually new" correction for 08-VALIDATION.md (see key-decisions above and the file's own doc comment).
- For the ENR-02 sequential-simulation test, captured each response's flash state via `session()->has('status'/'error')` immediately after each `actingAs()->post()` call (before the next request runs), rather than any parallel execution mechanism — PHPUnit is single-threaded, and the method name/doc-comment state this limitation explicitly per the plan's instruction.
- Did not attempt to force every single test method to fail before implementation exists. Where a test's fixture and assertion combination is currently satisfied by pre-existing code paths that the new feature does not yet touch (e.g., `student.attempts.store` has always allowed unbounded exams; `UpdateExamRequest::authorize()` has always blocked published-exam edits regardless of which fields are submitted), the test legitimately passes now and will continue to be a valid regression guard once the gate lands. Each such case is called out below rather than reworded to force a false failure.

## Deviations from Plan

None — plan executed exactly as written. No production code was written (verified: `git diff --name-only` across all three task commits touches only files under `tests/`). No file outside the plan's `files_modified` list was created or modified.

### Notable non-deviation observations (documented per plan's `<output>` instructions, not deviations)

**14 of the 64 new test cases currently PASS rather than fail.** This is not a defect in the RED authoring; each falls into one of two categories:

1. **RouteNotFoundException-based files (SubjectBrowseControllerTest, EnrollmentControllerTest, RejectEnrollmentControllerTest)** — every single test in these three files fails, because the routes they depend on (`student.subjects.*`, `student.sections.*`, `lecturer.sections.show`, `lecturer.sections.enrollments.reject`) do not exist at all yet. 100% RED as expected (24 + 12 = 36 of 36 tests fail).
2. **Files built on already-existing routes (ExamAvailabilityTest, AttemptAvailabilityTest, ExamShowTest)** — these hit real, already-routed controllers (`lecturer.exams.store/update/create/edit`, `student.attempts.store`, `student.exams.show`). Some pinned assertions describe behavior that is either already true today or structurally unaffected by the still-missing implementation:
   - `ExamAvailabilityTest`: the blank-datetime-local test (A1) and the D-06 published-exam-403 test pass today (2 of 8) — neither `available_from`/`available_until` is wired into `StoreExamRequest`/`UpdateExamRequest` at all yet, so submitting blanks is indistinguishable from omitting the keys, and `UpdateExamRequest::authorize()`'s existing draft-only gate already blocks the request regardless of which fields it carries.
   - `AttemptAvailabilityTest`: 7 of 10 pass — every "should succeed" case (in-window, unbounded, only-from, only-until, exact-boundary-success, the already-started-resumable composition case, and the non-enrolled-403-precedence case) is currently true simply because `AttemptController@store` has no availability gate yet to interfere with any of them. Only the 3 refusal-copy/boundary-refusal cases, which require a gate that does not exist, fail.
   - `ExamShowTest`: 5 of 10 pass — reachability while out-of-window, duration, description, and the non-enrolled-403 case were already true of the existing pre-start page; only the 5 genuinely new page elements (window, pill, enrolled section, Proceed/Back copy, error flash) fail.

   These are intentional **positive-control regression pins**: once 08-06/08-07 add the availability gate and the new page elements, these tests must remain green, proving the new code does not regress currently-correct behavior. They were not reworded to force artificial failures, per the plan's own framing that some contract elements describe invariants the future implementation must preserve, not just new behavior it must add.

**Total deviations:** 0
**Impact on plan:** None — all six files match the plan's task/file assignments, acceptance criteria, and prohibitions exactly.

## Issues Encountered
None. All six files parsed and ran cleanly on the first attempt (verified via `php artisan test --filter=<EachClass>` per task, then a full-suite run after each commit).

## User Setup Required
None — no external service configuration required.

## Next Phase Readiness
- The route-name contract is now fixed: 08-04 (student enrollment controllers), 08-05 (lecturer reject + roster), and 08-06/08-07 (exam availability + pre-start page) must implement exactly `student.subjects.index/show`, `student.sections.enroll/withdraw`, `lecturer.sections.show`, and `lecturer.sections.enrollments.reject`.
- 08-VALIDATION.md's Wave 0 Requirements table should be corrected: `ExamShowTest.php` is NEW, not an extension of an existing file.
- Assumption A1 (blank datetime-local → null) remains genuinely unverified — it can only be tested meaningfully once 08-06 wires `available_from`/`available_until` into `StoreExamRequest`/`UpdateExamRequest`'s `rules()`. The pinned test (`test_submitting_both_availability_fields_as_blank_strings_persists_null_on_both_with_no_validation_error`) will re-run against real validation at that point; if it fails, 08-06 owns adding a `prepareForValidation()` normalizer, per this plan's explicit instruction not to solve it here.
- Full test suite: 264 tests total (200 pre-existing + 64 new), 214 passing / 50 intentionally RED. `08-03` (AttemptPolicyTest, running in the same wave) is unaffected — no overlap with this plan's six files, confirming the plan's stated parallel-safety boundary.
- No blockers for `08-03` or for `08-04` through `08-07`.

---
*Phase: 08-v2-0-features-enrollment-exam-availability-user-manuals*
*Completed: 2026-07-16*

## Self-Check: PASSED

All 6 created test files and this SUMMARY.md confirmed present on disk; all 3 task commits (`f496427`, `514b480`, `8438977`) confirmed in git history. Full suite re-verified: 200 pre-existing tests unaffected, 64 new test cases (14 pass as documented positive controls, 50 RED for the expected missing-implementation reason).

---
phase: 02-classroom-subject-exam-authoring
plan: 05
subsystem: api
tags: [laravel, blade, alpinejs, form-request, validation, transactions]

# Dependency graph
requires:
  - phase: 02-01
    provides: Subject/Exam/Question/Option models + factories, QuestionType enum
  - phase: 02-04
    provides: ExamController CRUD + draft/published gate pattern (UpdateExamRequest::authorize())
provides:
  - "ExamQuestionController@store — nested question authoring under a draft exam"
  - "StoreQuestionRequest — type/points/options/correct_option validation + exactly-one-correct after() hook + draft-only authorize gate"
  - "lecturer.exams.questions.store route"
  - "Alpine _form.blade.php partial (shared correct_option radio group, stable-keyed dynamic option rows)"
  - "exams/show.blade.php renders questions+options from the DB and mounts the add-question form while draft"
affects: [02-06, phase-4-attempt-taking, phase-5-grading]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Single shared-name correct_option radio group (Pattern 1) instead of per-row is_correct checkboxes — 'at most one correct' is browser-native, server only re-verifies a valid index was chosen"
    - "Form Request prepareForValidation() merge to supply a default (points=1) before rules() run, so a genuinely-omitted field still passes required|min:1"
    - "DB::transaction() wraps Question + Options persistence; is_correct always derived server-side from correct_option, never accepted from request input"

key-files:
  created:
    - app/Http/Controllers/Lecturer/ExamQuestionController.php
    - app/Http/Requests/Lecturer/StoreQuestionRequest.php
    - resources/views/lecturer/exams/questions/_form.blade.php
    - tests/Feature/Lecturer/ExamQuestionMcqTest.php
    - tests/Feature/Lecturer/ExamQuestionOpenTest.php
  modified:
    - routes/lecturer.php
    - app/Http/Controllers/Lecturer/ExamController.php
    - resources/views/lecturer/exams/show.blade.php

key-decisions:
  - "options.*.body and correct_option array/min:2 rules apply whenever those fields are present, not only when type=mcq (required_if only gates *required*-ness) — the Alpine form enforces this by only rendering/submitting the options block for type=mcq"
  - "Question position = current max(position)+1 within the exam; Option position = 0-based submission order"

patterns-established:
  - "Pattern 1: shared correct_option radio group + after() index-validity check — the entire 'exactly one correct' rule collapses to two checks, no boolean-counting Rule class"

requirements-completed: [EXM-02, EXM-03, EXM-04]

# Metrics
duration: 8min
completed: 2026-07-15
status: complete
---

# Phase 2 Plan 05: MCQ + Open-Text Question Authoring Summary

**Nested `ExamQuestionController@store` adds MCQ (shared `correct_option` radio group, server-derived `is_correct`, transactional Option persistence) and open-text questions to a draft exam, with a `StoreQuestionRequest` `after()` hook enforcing exactly-one-correct.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-07-15T22:23:24+08:00 (following 02-04 completion commit)
- **Completed:** 2026-07-15T22:30:38+08:00
- **Tasks:** 2 (TDD: RED then GREEN)
- **Files modified:** 8 (3 created controllers/requests/views, 2 test files, 3 modified)

## Accomplishments
- Lecturers can add MCQ questions with ≥2 options and exactly one marked correct via a single shared `correct_option` radio group (Pattern 1) — the server derives `is_correct` from the validated index, never trusts a client-sent boolean.
- Lecturers can add open-text questions (body + points, no options).
- All D-08 rejection cases enforced server-side: zero-correct, out-of-range `correct_option`, fewer than 2 options, blank option body, `points < 1`.
- Points default to 1 when omitted (`prepareForValidation()` merge) and a custom positive value persists.
- Question + Options persist inside `DB::transaction()`.
- The add-question form only mounts on `exams/show.blade.php` while the exam is a draft (D-06); `StoreQuestionRequest::authorize()` independently rejects a forged/replayed POST to a published exam (T-02-PUB).
- The exam show page now lists existing questions (with options, correct answer marked) read live from the DB.

## Task Commits

Each task was committed atomically:

1. **Task 1: Failing question-authoring feature tests (RED)** - `ec5f9a0` (test)
2. **Task 2: ExamQuestionController@store + StoreQuestionRequest + Alpine _form + show wiring (GREEN)** - `db06427` (feat)

**Plan metadata:** (this commit) `docs(02-05): complete question authoring plan`

## Files Created/Modified
- `app/Http/Controllers/Lecturer/ExamQuestionController.php` - `store()` — transactional Question + (mcq) Option persistence, `is_correct` derived server-side
- `app/Http/Requests/Lecturer/StoreQuestionRequest.php` - draft-only `authorize()`, `prepareForValidation()` points default, `rules()`, `after()` exactly-one-correct hook
- `routes/lecturer.php` - `POST exams/{exam}/questions` -> `lecturer.exams.questions.store`, inside the `role:lecturer` group
- `resources/views/lecturer/exams/questions/_form.blade.php` - Alpine `x-data` component: mcq/open toggle, `x-for` option rows keyed by a stable `nextKey` counter, shared `correct_option` radio
- `resources/views/lecturer/exams/show.blade.php` - renders the questions+options list from the DB, mounts the add-question form only while `is_published === false`
- `app/Http/Controllers/Lecturer/ExamController.php` - `show()` now eager-loads `questions.options`
- `tests/Feature/Lecturer/ExamQuestionMcqTest.php` - happy path, zero/out-of-range/under-count/blank rejection, points default/custom/reject, student 403, published-exam 403
- `tests/Feature/Lecturer/ExamQuestionOpenTest.php` - open happy path, points default/reject, student 403, published-exam 403

## Decisions Made
- `options.*.body`/`correct_option`'s `array`/`min:2`/`integer` rules apply whenever the fields are present in the request, regardless of `type` — `required_if:type,mcq` only controls whether they are *required*. This matches the research-provided Form Request example verbatim; the Alpine form satisfies it by only rendering (and therefore only submitting) the options block when `type === 'mcq'`.
- Question `position` is computed as `max(position)+1` scoped to the exam; Option `position` is the 0-based order of the submitted `options` array (matches the `correct_option` index semantics).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed own RED-phase test assertion contradicting the specified validation rules**
- **Found during:** Task 2 (GREEN) — `php artisan test --filter=ExamQuestionOpenTest`
- **Issue:** A Task-1 test (`test_open_question_ignores_a_submitted_options_array`) asserted that an open-text submission with a 1-entry `options` array would still persist, expecting the field to be silently ignored. But per the research-specified rules (`'options' => ['required_if:type,mcq', 'array', 'min:2']`), `array`/`min:2` apply whenever the field is present regardless of `type` — so a short stray `options` array on an `open` submission correctly fails `min:2` and the whole request is rejected. This was a test-authoring mistake in Task 1, not an implementation bug.
- **Fix:** Renamed/rewrote the test to `test_open_question_with_no_options_field_at_all_persists_cleanly`, asserting the actual (and only sensible) contract: an open-text submission must omit `options`/`correct_option` entirely, which is exactly what the Alpine form does by only rendering that block for `type=mcq`.
- **Files modified:** `tests/Feature/Lecturer/ExamQuestionOpenTest.php`
- **Verification:** `php artisan test --filter=ExamQuestionOpenTest` — 6/6 green.
- **Committed in:** `db06427` (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 bug in the executor's own RED-phase test, not the plan or implementation).
**Impact on plan:** No scope creep; the fix only corrected a test expectation to match the deliberately-specified Form Request rules.

## Issues Encountered
None beyond the deviation above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `ExamQuestionController`/`StoreQuestionRequest` are ready for 02-06 to extend with `update`/`destroy` (question edit/delete + the question-level published-edit gate — out of scope here per the plan's objective).
- The exam show page's questions list (body/type/points/options with correct-answer marking) gives 02-06 a place to add edit/delete affordances per question row.
- No blockers for Phase 3 (exam→classroom assignment): exams can now be authored with real MCQ/open content before assignment.

---
*Phase: 02-classroom-subject-exam-authoring*
*Completed: 2026-07-15*

## Self-Check: PASSED

All created files verified present on disk; both task commits (`ec5f9a0`, `db06427`) verified present in `git log`.

---
phase: 04-attempt-taking
plan: 02
subsystem: api
tags: [laravel-11, mysql, eloquent, db-transaction, lockforupdate, policy, blade, tailwind]

# Dependency graph
requires:
  - phase: 04-attempt-taking
    provides: "AttemptFactory/AnswerFactory + the full RED acceptance contract (AttemptStartTest, AttemptShowTest, AttemptPolicyTest, AttemptAnswerTest, AttemptSubmitTest) with locked route names student.attempts.*"
provides:
  - "Attempt::deadline()/isExpired()/remainingSeconds()/finalizeIfExpired() — the single server-timer chokepoint, never storing the deadline"
  - "AttemptPolicy::view/update — own-attempt AND Exam::visibleTo() (D-08 IDOR gate)"
  - "Student\\AttemptController@store/show/submitted — race-safe start/resume, policy-first + finalize-first show, column-whitelisted view-model"
  - "routes/student.php: student.attempts.store/show/submitted"
  - "student/attempts/show.blade.php + submitted.blade.php — server-rendered take page and score-free confirmation"
  - "Activated Start/Resume seam on student/exams/show.blade.php (D-10)"
affects: [04-03-answer-autosave, 04-04-submit-and-countdown]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "finalizeIfExpired() as the single lock-guarded (DB::transaction + lockForUpdate) chokepoint for time-expiry finalization — called first in every attempt-touching controller action, never re-derived inline"
    - "firstOrCreate + QueryException(1062) catch-and-refetch for atomic start/resume, backed by the Phase-1 DB unique(exam_id,user_id) constraint"
    - "Explicit column-whitelisted array view-model (->get(['id','question_id','body'])) built server-side, never a raw Eloquent model passed into the view — the only mechanism used to prevent answer-key leakage"

key-files:
  created:
    - app/Policies/AttemptPolicy.php
    - app/Http/Controllers/Student/AttemptController.php
    - resources/views/student/attempts/show.blade.php
    - resources/views/student/attempts/submitted.blade.php
  modified:
    - app/Models/Attempt.php
    - routes/student.php
    - resources/views/student/exams/show.blade.php
    - tests/Feature/Student/AttemptShowTest.php

key-decisions:
  - "finalizeIfExpired() re-fetches the row with lockForUpdate INSIDE its own DB::transaction rather than trusting the already-loaded instance, so a racing auto-submit (04-04) and a manual reload can't both finalize the same attempt"
  - "The Start/Resume in_progress-attempt existence check is computed inline in exams/show.blade.php (not added to ExamController@show) to keep this plan's file scope exactly matching its declared files_modified list"
  - "Included `points` in the take-page question view-model's column whitelist beyond the plan's literal id/exam_id/type/body/position list, because 04-UI-SPEC.md's per-question meta line requires it and it carries no answer-key information (Rule 2 — missing critical UI-contract data, not a leak)"

patterns-established:
  - "Every attempt-touching controller action: authorize() first, then loadMissing('exam'), then finalizeIfExpired() before branching on status"
  - "Never eager-load or serialize a raw Question/Option Eloquent collection into a student-facing view — always build the explicit whitelisted array first"

requirements-completed: [TAK-01, TAK-05, TAK-02, TAK-04, TAK-06]

# Metrics
duration: 20min
completed: 2026-07-16
status: complete
---

# Phase 4 Plan 2: Server-Timer Core Summary

**Attempt::finalizeIfExpired() as the single lock-guarded server-timer chokepoint, AttemptPolicy IDOR gate, race-safe start/resume via firstOrCreate+1062 catch, and a column-whitelisted take-page view-model that never leaks the MCQ answer key.**

## Performance

- **Duration:** 20 min
- **Started:** 2026-07-16T00:58:00+08:00
- **Completed:** 2026-07-16T01:18:13+08:00
- **Tasks:** 3
- **Files modified:** 8 (4 created, 4 modified)

## Accomplishments
- `Attempt::deadline()`/`isExpired()`/`remainingSeconds()`/`finalizeIfExpired()` added — deadline is always recomputed from `started_at + exam.duration_minutes`, never stored; `finalizeIfExpired()` is the one place time-expiry flips `in_progress` → `submitted`, guarded by `DB::transaction` + `lockForUpdate` for concurrent-touch safety
- `AttemptPolicy::view`/`update` enforce own-attempt AND exam-still-takeable by delegating to `Exam::visibleTo()` — zero re-derivation of the Phase-3 visibility predicate
- `Student\AttemptController@store` starts/resumes a single attempt per (exam, user) via `firstOrCreate` + `QueryException`(1062) catch-and-refetch, blocking a second start after submission with a flash message
- `Student\AttemptController@show` runs policy-then-finalize first, then builds an explicit column-whitelisted question/option array (`id`/`question_id`/`body` only — `is_correct` never selected) and rehydrates saved answers
- Take page (`student/attempts/show.blade.php`) renders every question (MCQ radios / open textareas) from that whitelisted view-model with a static seeded countdown badge; submitted confirmation page is score-free
- Start/Resume seam activated on the exam landing page (D-10) — real POST to `attempts.store`, label swaps based on an in-progress-attempt existence check
- `AttemptStartTest` (4), `AttemptShowTest` (3), `AttemptPolicyTest` (1) all GREEN; full suite 143 passed, 7 RED (`AttemptAnswerTest`/`AttemptSubmitTest`, owned by 04-03/04-04, exactly as the plan's verification section anticipated) — zero Phase 1-3 regressions

## Task Commits

Each task was committed atomically:

1. **Task 1: Attempt timer methods + AttemptPolicy** - `35a148e` (feat)
2. **Task 2: AttemptController@store/show/submitted + routes + activate Start seam** - `d423594` (feat)
3. **Task 3: Take-page view + submitted confirmation view** - `f781011` (feat, includes a test precision fix — see Deviations)

**Plan metadata:** (this commit) `docs(04-02): complete server-timer core plan`

## Files Created/Modified
- `app/Models/Attempt.php` - added `deadline()`, `isExpired()`, `remainingSeconds()`, `finalizeIfExpired()`
- `app/Policies/AttemptPolicy.php` - new: `view`/`update` delegate to `Exam::visibleTo()` (D-08)
- `app/Http/Controllers/Student/AttemptController.php` - new: `store`/`show`/`submitted`
- `routes/student.php` - added `attempts.store`/`attempts.show`/`attempts.submitted`
- `resources/views/student/exams/show.blade.php` - real Start/Resume POST form replacing the Phase-3 disabled seam
- `resources/views/student/attempts/show.blade.php` - new: server-rendered take page
- `resources/views/student/attempts/submitted.blade.php` - new: score-free confirmation
- `tests/Feature/Student/AttemptShowTest.php` - fixed a microsecond-precision assertion bug (see Deviations)

## Decisions Made
- `finalizeIfExpired()`'s lock-guarded re-check is a self-contained `DB::transaction`, not reliant on an outer transaction from the caller, so it's safe to call unconditionally from `show()` (and later `answer()`/`submit()`) without callers needing to know about locking.
- Kept `ExamController.php` untouched — the plan's declared `files_modified` list for this plan didn't include it, and the plan text explicitly offered "compute it in the exams/show view" as the alternative for the Start/Resume label boolean, so that's what was used.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed a microsecond-precision assertion in AttemptShowTest**
- **Found during:** Task 3 verification (`--filter=AttemptShowTest`)
- **Issue:** `test_visiting_an_expired_attempt_finalizes_it_to_submitted` asserted `$attempt->submitted_at->equalTo($deadline)` where `$deadline` was computed in-memory from a microsecond-precision `now()` (via `freezeTime()`), but `attempts.started_at`/`submitted_at` are plain `timestamp` columns (Phase-1 fixed schema, zero fractional-seconds precision) and Eloquent's default MySQL date format (`'Y-m-d H:i:s'`) drops microseconds on every write. A server-computed deadline read back from the DB can therefore never equal an in-memory `now()` down to the microsecond — verified empirically via `php artisan tinker` (a `.081152`-microsecond `started_at` round-tripped through the real MySQL connection as `.000000`). This is unrelated to the `finalizeIfExpired()` implementation, which correctly sets `submitted_at` to the true deadline at the precision the schema supports.
- **Fix:** Changed the assertion to compare `$deadline->format('Y-m-d H:i:s')` against `$attempt->submitted_at->format('Y-m-d H:i:s')` — preserves the assertion's real intent (submitted_at equals the deadline, never past it) without depending on precision the fixed schema doesn't have. No migration/schema change made (D-02 respected).
- **Files modified:** `tests/Feature/Student/AttemptShowTest.php`
- **Verification:** `php artisan test --filter=AttemptShowTest` — all 3 GREEN; full suite re-run confirmed no other test relies on microsecond-precision timestamp equality.
- **Committed in:** `f781011` (Task 3 commit)

**2. [Rule 2 - Missing Critical] Included `points` in the take-page question view-model**
- **Found during:** Task 2 (building the `show()` view-model)
- **Issue:** The plan's literal action text lists the question column whitelist as `id, exam_id, type, body, position`, but 04-UI-SPEC.md's Screen 1 meta line ("Question n of total · N points") requires the question's point value, which was omitted from that list.
- **Fix:** Added `points` to the `->get([...])` column whitelist and the mapped array. It carries no answer-key information (only `options.is_correct` is sensitive), so this doesn't affect the TAK-06 leakage boundary.
- **Files modified:** `app/Http/Controllers/Student/AttemptController.php`, `resources/views/student/attempts/show.blade.php`
- **Verification:** `AttemptShowTest::test_the_take_page_never_exposes_is_correct` still GREEN; meta line renders correctly.
- **Committed in:** `d423594` / `f781011`

---

**Total deviations:** 2 auto-fixed (1 bug fix in a pre-existing test, 1 missing-critical UI data column)
**Impact on plan:** Both necessary for the plan's own verification gate and UI contract to be satisfiable. No scope creep — no architectural changes, no schema changes, no new packages.

## Issues Encountered
- A `php artisan test --filter=AttemptShowTest` run appeared to hang for several minutes; investigation via `SHOW PROCESSLIST` showed a stale idle MySQL connection but the actual PHPUnit process had already produced its (large, Whoops-error-page) output before the missing-view fix — the apparent hang was slow output flushing through the harness's background-task piping, not a real deadlock or infinite loop. Killing the stale process and re-running confirmed a normal ~4-6s test duration once the take-page view existed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- 04-03 (answer autosave) can now build `AttemptController@answer` + `AnswerRequest` on top of the same `finalizeIfExpired()` chokepoint and the take page's rehydration slots (`name="question_{id}"` inputs already in place).
- 04-04 (submit + live countdown) can wire the take page's inert "Submit Exam" button and the static countdown badge to real Alpine behavior, reusing `remainingSeconds` as the seed and `finalizeIfExpired()`'s same lock pattern for `submit()`.
- No blockers. `AttemptAnswerTest`/`AttemptSubmitTest` remain RED exactly as anticipated — their routes (`attempts.answer`/`attempts.submit`) don't exist until 04-03/04-04.

---
*Phase: 04-attempt-taking*
*Completed: 2026-07-16*

## Self-Check: PASSED

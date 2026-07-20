---
phase: 04-attempt-taking
verified: 2026-07-15T17:51:29Z
status: passed
score: 11/11 must-haves verified
behavior_unverified: 0
overrides_applied: 0
---

# Phase 4: Attempt-Taking Verification Report

**Phase Goal:** A student's timed exam attempt is captured reliably and cannot be gamed, duplicated, or lost.
**Verified:** 2026-07-15T17:51:29Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths (ROADMAP Success Criteria + phase-level guarantees)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | SC1 — Starting an assigned exam creates exactly one timed attempt anchored to a server-recorded `started_at`; a second attempt by the same student on the same exam is rejected at the DB level | ✓ VERIFIED | `attempts` table has `unique(['exam_id','user_id'])` (`database/migrations/2026_07_15_100009_create_attempts_table.php:25`). `AttemptController@store` uses `firstOrCreate` inside `DB::transaction`, catches `QueryException` errorInfo 1062 and re-fetches the winning row (`app/Http/Controllers/Student/AttemptController.php:30-58`). Behavioral tests pass live: `test_starting_an_assigned_exam_creates_an_in_progress_attempt`, `test_starting_the_same_exam_twice_resumes_the_existing_attempt`, `test_a_concurrent_double_start_does_not_create_a_duplicate_attempt`, `test_a_student_cannot_start_a_second_attempt_after_submitting` — all 4 GREEN against live MySQL. |
| 2 | SC2 — The on-screen countdown reflects a server-computed deadline; any answer submitted after the deadline is rejected server-side (422) regardless of client | ✓ VERIFIED | `Attempt::deadline()` recomputes `started_at + exam.duration_minutes` every call, never stored (`app/Models/Attempt.php:60-63`; `grep -n "expires_at" app/Models/Attempt.php` returns nothing). `remainingSeconds()` is server-computed and only seeds the client display; the Alpine `attemptTimer()` counts down purely client-side and is documented as cosmetic (`resources/views/student/attempts/show.blade.php:210-220`). `answer()` calls `finalizeIfExpired()` then gates on status before any write, returning 422 with zero persisted rows past deadline (`AttemptController.php:117-136`). Tests: `test_remaining_seconds_reflects_elapsed_time` (frozen-time exact-value assertion), `test_an_answer_after_the_deadline_is_rejected`, `test_an_expired_attempt_rejects_answer_writes` — all GREEN. |
| 3 | SC3 — Answers save incrementally so refresh/disconnect doesn't lose them, and rehydrate on reload | ✓ VERIFIED | `answer()` does `Answer::updateOrCreate(['attempt_id'=>..,'question_id'=>..], ...)` (`AttemptController.php:130-133`), backed by `answers.unique(['attempt_id','question_id'])` (migration). `show()` rehydrates via `$attempt->answers()->get()->keyBy('question_id')` and the take-page pre-checks radios / pre-fills textareas from `savedAnswers` (`show.blade.php:74,133,152`). Tests: `test_an_answer_saved_before_the_deadline_is_persisted`, `test_autosave_persists_and_survives_reload`, `test_repeated_autosave_upserts_the_same_answer_row` — all GREEN, confirming single-row upsert with latest-value-wins. |
| 4 | SC4 — When the deadline passes, an in_progress attempt auto-finalizes to submitted (lazy `finalizeIfExpired` on touch) and further answer changes are rejected | ✓ VERIFIED | `finalizeIfExpired()` is the single chokepoint, called first in `show()` and `answer()` (`AttemptController.php:70,122`); it is lock-guarded (`lockAndFinalize` — `DB::transaction` + `lockForUpdate`, `Attempt.php:136-157`). Tests: `test_visiting_an_expired_attempt_finalizes_it_to_submitted` (GET path), `test_an_expired_attempt_rejects_answer_writes` (write path, asserts attempt flips to `submitted` on the very write that gets rejected) — both GREEN. Client half (live countdown auto-POST at zero + on any bubbled 422) also present (`show.blade.php:237-294`), with the server backstop remaining authoritative per design. |
| 5 | SC5 — At no point does the take page (HTML/JSON/data) reveal `is_correct` | ✓ VERIFIED | `show()` builds an explicit column-whitelisted view-model selecting only `id, exam_id, type, body, position, points` for questions and `id, question_id, body` for options — `is_correct` is never selected (`AttemptController.php:76-94`). `grep -rn "is_correct" resources/views/student/attempts/` returns 0 hits. Test `test_the_take_page_never_exposes_is_correct` asserts on the raw response body (`assertDontSee('is_correct')`) — GREEN. |
| 6 | submit() is idempotent — double-submit is a no-op success, not an error | ✓ VERIFIED | `submit()` delegates entirely to `Attempt::finalize()`, which calls the same `lockAndFinalize()` primitive; a second call's locked re-read finds `status !== 'in_progress'` and returns `false` without writing (`Attempt.php:121-124,136-157`). Test `test_a_double_submit_is_idempotent` asserts `submitted_at` unchanged on the second call and response status `< 500` — GREEN. |
| 7 | Single finalize path — no divergent second finalize implementation | ✓ VERIFIED | `lockForUpdate` appears exactly 3× in `Attempt.php`: 2 are docblock comments, 1 is the single actual call inside the private `lockAndFinalize()` helper (`Attempt.php:139`). Both `finalize()` and `finalizeIfExpired()` are thin wrappers calling `lockAndFinalize()` with different guards (lines 103-124) — there is exactly one lock-then-check-then-update code path. |
| 8 | AttemptPolicy gates own-attempt + exam-takeable (IDOR) | ✓ VERIFIED | `AttemptPolicy::view`/`update` both delegate to `ownAndTakeable()`, checking `attempt.user_id === user.id AND Exam::visibleTo($user)->whereKey(...)->exists()` (`app/Policies/AttemptPolicy.php`). Called first in every controller action (`authorize('view'|'update', $attempt)`). Test `test_a_student_cannot_view_another_students_attempt` asserts 403 — GREEN. |
| 9 | `is_correct`/`score` stay null — no grading code leaked in (Phase 5 boundary) | ✓ VERIFIED | `AttemptController` never writes `is_correct` or `score`; `answer()`'s `updateOrCreate` value array is explicitly `collect($data)->only(['selected_option_id','answer_text'])` (`AttemptController.php:132`). `AnswerRequest::rules()` has no rule for `is_correct`/`score`/`attempt_id`. `submitted.blade.php` renders no score/grade content. The `is_correct`/`score` columns exist on the `answers`/`attempts` tables (pre-existing Phase-1 schema, reserved for Phase 5) but are fillable-only, never populated by any Phase-4 code path. |
| 10 | AnswerRequest validates shape only, never accepts forged grading fields | ✓ VERIFIED | `app/Http/Requests/Student/AnswerRequest.php` rules restrict `question_id` (scoped `exists` to the attempt's exam), `selected_option_id` (scoped `exists` to the submitted question), `answer_text` (nullable string) — no rule admits `is_correct`/`score`/`attempt_id`. |
| 11 | Start/Resume seam activated on the exam landing page (D-10) | ✓ VERIFIED | `resources/views/student/exams/show.blade.php:29-39` computes `hasInProgressAttempt` and posts a real form to `route('student.attempts.store', $exam)`; the "Taking exams is not available yet" placeholder note from Phase 3 is gone. |

**Score:** 11/11 truths verified (0 present-but-behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Models/Attempt.php` | `deadline()`, `isExpired()`, `remainingSeconds()`, `finalizeIfExpired()`, single lock-guarded finalize chokepoint | ✓ VERIFIED | All methods present with correct signatures; `finalize()`/`finalizeIfExpired()` share one private `lockAndFinalize()` helper. |
| `app/Policies/AttemptPolicy.php` | own-attempt + exam-takeable gating | ✓ VERIFIED | `view`/`update` both delegate to `ownAndTakeable()` reusing `Exam::visibleTo()`. |
| `app/Http/Controllers/Student/AttemptController.php` | `store`/`show`/`answer`/`submit`/`submitted` | ✓ VERIFIED | All 5 actions present, each `authorize()`-first, each routes through `finalizeIfExpired()`/`finalize()` where relevant. |
| `app/Http/Requests/Student/AnswerRequest.php` | shape validation, no grading fields accepted | ✓ VERIFIED | Scoped `exists` rules on `question_id`/`selected_option_id`; no grading-field rules. |
| `app/Http/Requests/Student/SubmitAttemptRequest.php` | shape validation (empty body) | ✓ VERIFIED | `rules()` returns `[]`; ownership enforced by controller-level `authorize()`. |
| `resources/views/student/attempts/show.blade.php` | take page: rendering, rehydration, no leak, autosave, live countdown, confirm modal | ✓ VERIFIED | 298 lines; whitelisted view-model only; per-question Alpine autosave scopes; outer `attemptTimer()` live countdown with 300s/60s escalation and auto-submit at zero; confirm-submit modal wired to real route. |
| `resources/views/student/attempts/submitted.blade.php` | score-free confirmation | ✓ VERIFIED | No score/grade content rendered. |
| `routes/student.php` | `attempts.store/show/answer/submit/submitted` | ✓ VERIFIED | `php artisan route:list --path=attempts` shows all 5 routes correctly named and mapped. |
| `database/migrations/*_create_attempts_table.php` | unique(exam_id,user_id) | ✓ VERIFIED | Present, confirmed by passing `DomainSchemaTest`. |
| `database/migrations/*_create_answers_table.php` | unique(attempt_id,question_id) | ✓ VERIFIED | Present, confirmed by passing `DomainSchemaTest`. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `resources/views/student/exams/show.blade.php` | `routes/student.php` | `route('student.attempts.store', $exam)` form POST | ✓ WIRED | Seam activated, no placeholder text remains. |
| `app/Http/Controllers/Student/AttemptController.php` | `app/Models/Attempt.php` | `finalizeIfExpired()` first in `show()`/`answer()`; `finalize()` in `submit()` | ✓ WIRED | Confirmed by reading and by passing lazy-finalize + idempotent-submit tests. |
| `app/Http/Controllers/Student/AttemptController.php` | `app/Policies/AttemptPolicy.php` | `authorize('view'|'update', $attempt)` as first statement in every action | ✓ WIRED | Confirmed by reading and by passing `AttemptPolicyTest`. |
| `resources/views/student/attempts/show.blade.php` | `routes/student.php` | `window.axios.post(route('student.attempts.answer', $attempt))` on `@change`/`@blur` | ✓ WIRED | Confirmed in view source (`show.blade.php:97`) and by passing `AttemptAnswerTest`. |
| `resources/views/student/attempts/show.blade.php` | `routes/student.php` | confirm-modal "Yes, Submit" form + countdown-at-0 auto-POST to `attempts.submit` | ✓ WIRED | Confirmed in view source (`show.blade.php:185,291`). |

### Behavioral Spot-Checks (real test run, not narration)

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| All 17 Phase-4 attempt tests | `php artisan test --filter=Attempt` | 17 passed (39 assertions), incl. `DomainSchemaTest` unique-index checks | ✓ PASS |
| Full regression suite | `php artisan test` | 150 passed (372 assertions), no failures | ✓ PASS |

These were executed directly in this verification session against the live MySQL test database (`RefreshDatabase`) — not taken from SUMMARY.md narration.

### Requirements Coverage

| Requirement | Source Plan(s) | Description | Status | Evidence |
|-------------|----------------|--------------|--------|----------|
| TAK-01 | 04-01, 04-02 | Student can start an assigned exam, creating a single timed attempt | ✓ SATISFIED | `store()` + unique constraint + `AttemptStartTest` (4/4 GREEN) |
| TAK-02 | 04-01, 04-02, 04-03, 04-04 | Time limit enforced server-side; live countdown driven by server deadline | ✓ SATISFIED | `deadline()`/`remainingSeconds()` + 422 deadline gate + live Alpine countdown; `AttemptShowTest`/`AttemptAnswerTest` GREEN |
| TAK-03 | 04-01, 04-03 | Answers saved incrementally, survive refresh/disconnect | ✓ SATISFIED | `answer()` `updateOrCreate` + rehydration; `AttemptAnswerTest` GREEN |
| TAK-04 | 04-01, 04-02, 04-04 | Auto-submit on expiry; no further answer changes accepted | ✓ SATISFIED | `finalizeIfExpired()` lazy chokepoint + client auto-submit; `AttemptShowTest`/`AttemptAnswerTest`/`AttemptSubmitTest` GREEN |
| TAK-05 | 04-01, 04-02 | Student can attempt each exam only once (DB unique constraint) | ✓ SATISFIED | `unique(exam_id,user_id)` migration + 1062 catch-and-refetch; `AttemptStartTest` GREEN |
| TAK-06 | 04-01, 04-02 | Correct answers never exposed while taking the exam | ✓ SATISFIED | Column-whitelisted view-model; `assertDontSee('is_correct')` test GREEN |

No orphaned requirements — REQUIREMENTS.md maps exactly TAK-01..06 to Phase 4, and all six appear in at least one plan's `requirements` frontmatter and are backed by a passing test.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| — | — | none found | — | Scanned `Attempt.php`, `AttemptPolicy.php`, `AttemptController.php`, `AnswerRequest.php`, `SubmitAttemptRequest.php`, `show.blade.php`, `submitted.blade.php`, `routes/student.php` for `TBD/FIXME/XXX/TODO/HACK/PLACEHOLDER/placeholder/coming soon/not yet implemented` — zero matches. |

No debt markers, no stub returns, no empty handlers, no hardcoded-empty data flowing to render.

### Human Verification Required

None. All must-haves are backed by passing automated tests exercising real state transitions (race conditions, lazy finalize, idempotent double-submit, deadline rejection) against a live MySQL database — not mere presence/wiring checks.

### Gaps Summary

No gaps found. All 5 ROADMAP success criteria, all 6 requirement IDs (TAK-01..06), and the additional adversarial checks requested (idempotent submit reusing a single finalize path, policy IDOR gating, no grading-code leakage) are verified against actual source and a live, passing test run (17/17 Phase-4 tests, 150/150 full suite).

---

_Verified: 2026-07-15T17:51:29Z_
_Verifier: Claude (gsd-verifier)_

---
phase: 04-attempt-taking
plan: 03
subsystem: api
tags: [laravel, alpine, axios, form-request, eloquent-upsert]

# Dependency graph
requires:
  - phase: 04-attempt-taking (plan 02)
    provides: Attempt::finalizeIfExpired()/isExpired()/deadline(), AttemptController@show rehydrating savedAnswers, AttemptPolicy, take-page shell
provides:
  - AnswerRequest (Student) — scoped-exists validation for question_id/selected_option_id, never accepts grading fields
  - AttemptController@answer — deadline-gated, idempotent autosave endpoint
  - attempts.answer POST route
  - Per-question Alpine autosave wiring + Saving/Saved/Save-failed status tags on the take page
affects: [04-04 (submit + auto-submit wiring reuses the same finalizeIfExpired gate and take page), Phase 5 (grading reads the answers table this endpoint writes)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Per-card Alpine x-data scope (status/lastPayload/save/retry) instead of one whole-page JSON blob — avoids embedding question/option data in a shared Alpine state"
    - "FormRequest shape validation only (authorize() returns true); ownership/deadline enforcement stays in the controller via Policy + finalizeIfExpired()"

key-files:
  created:
    - app/Http/Requests/Student/AnswerRequest.php
  modified:
    - app/Http/Controllers/Student/AttemptController.php
    - routes/student.php
    - resources/views/student/attempts/show.blade.php

key-decisions:
  - "answer() reuses finalizeIfExpired() + status gate verbatim from the pattern established in show() — no duplicated deadline logic (D-04/D-05)"
  - "updateOrCreate value array is explicitly collect($data)->only(['selected_option_id','answer_text']) — attempt_id/question_id are the key, is_correct/score are never in $data at all since AnswerRequest never validates them"
  - "Alpine autosave state is scoped per question card (own x-data), not a page-level component — keeps the answer-key leak surface (Pitfall 3) at zero and keeps a failed save from disabling other questions"

patterns-established:
  - "Autosave POST payload is minimal: { question_id, selected_option_id? | answer_text? } — server derives attempt_id from the route binding only"

requirements-completed: [TAK-03, TAK-02]

# Metrics
duration: 12min
completed: 2026-07-15
status: complete
---

# Phase 04 Plan 03: Answer Autosave Summary

**Per-question AJAX autosave (`updateOrCreate` keyed on attempt_id+question_id) gated by the same `finalizeIfExpired()` deadline check as every other attempt-touching action, wired to the take page via scoped Alpine components and `window.axios`.**

## Performance

- **Duration:** 12 min
- **Completed:** 2026-07-15T17:30:41Z
- **Tasks:** 2
- **Files modified:** 4 (1 created, 3 modified)

## Accomplishments
- `AnswerRequest` validates `question_id` (scoped to the attempt's exam) and `selected_option_id` (scoped to that question) via `Rule::exists()`, with `answer_text` a plain nullable string — no rule accepts `is_correct`/`score`/`attempt_id`
- `AttemptController@answer` authorizes via `AttemptPolicy::update`, runs `finalizeIfExpired()` unconditionally first, then rejects with 422 if the attempt is no longer `in_progress` — before any write
- `Answer::updateOrCreate` keyed on `(attempt_id, question_id)` matches the Phase-1 composite unique constraint, so repeated autosaves for one question always update the same row
- Each question card on the take page carries its own Alpine scope (`status`, `lastPayload`, `save()`, `retry()`); MCQ radios autosave on `@change`, textareas on `@blur`, via the already-CSRF-configured `window.axios`
- Autosave status tag (Saving…/Saved/Save failed — Retry) renders inside an `aria-live="polite"` region per card; a 422 shows the deadline-specific "Time's up…" copy instead of a generic failure

## Task Commits

Each task was committed atomically:

1. **Task 1: AnswerRequest + AttemptController@answer + route** - `6c7b2d2` (feat)
2. **Task 2: Alpine per-answer autosave wiring + status tags** - `94b17f6` (feat)

**Plan metadata:** (this commit)

## Files Created/Modified
- `app/Http/Requests/Student/AnswerRequest.php` - Shape validation for the autosave POST body (scoped-exists rules, no grading fields)
- `app/Http/Controllers/Student/AttemptController.php` - Added `answer()` action (authorize + finalizeIfExpired + 422 gate + updateOrCreate)
- `routes/student.php` - Added `POST attempts/{attempt}/answers` → `attempts.answer`
- `resources/views/student/attempts/show.blade.php` - Added per-question Alpine autosave scope, radio `@change`/textarea `@blur` handlers, and the Saving/Saved/Save-failed status tag block

## Decisions Made
- Followed 04-RESEARCH.md Pattern 3/4 verbatim: `answer()`'s body matches the researched code example almost exactly (authorize → loadMissing('exam') → finalizeIfExpired() → status gate → updateOrCreate → JSON response with `remaining_seconds`)
- Kept the Alpine autosave state fully local to each question card (inline `x-data` object literal, matching the existing convention in `lecturer/exams/questions/_form.blade.php`) rather than introducing a page-level `x-data` or a global JS function in `<script>` — no new client dependency, no shared state to leak

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `attempts.answer` is live, deadline-gated, and idempotent — 04-04 (submit + client auto-submit) can rely on the identical `finalizeIfExpired()` chokepoint without re-deriving deadline logic.
- The take page's Submit button remains deliberately inert (no `route()`/form action) — 04-04 wires it to the confirm modal and submit route per the UI-SPEC's Screen 2/3.
- `php artisan test --filter=AttemptAnswerTest` is fully GREEN (5/5). Full suite: 148 passed, 2 failed — both failures are `AttemptSubmitTest` (`Route [student.attempts.submit] not defined`), the expected RED state carried forward until 04-04 per the plan's verification note; no Phase 1-3 regressions.
- `grep -rc 'is_correct' resources/views/student/attempts/` returns 0 on both files (TAK-06 still holds).

---
*Phase: 04-attempt-taking*
*Completed: 2026-07-15*

## Self-Check: PASSED

All created/modified files verified present on disk; both task commits (6c7b2d2, 94b17f6) verified in git log.

---
phase: 04-attempt-taking
reviewed: 2026-07-16T00:00:00Z
depth: deep
files_reviewed: 10
files_reviewed_list:
  - app/Models/Attempt.php
  - app/Policies/AttemptPolicy.php
  - app/Http/Controllers/Student/AttemptController.php
  - app/Http/Requests/Student/AnswerRequest.php
  - app/Http/Requests/Student/SubmitAttemptRequest.php
  - routes/student.php
  - resources/views/student/attempts/show.blade.php
  - resources/views/student/attempts/submitted.blade.php
  - database/factories/AttemptFactory.php
  - database/factories/AnswerFactory.php
findings:
  blocker: 2
  high: 1
  medium: 1
  low: 5
  total: 9
status: issues_found
---

# Phase 4: Code Review Report — Attempt-Taking (correctness-critical core)

**Reviewed:** 2026-07-16
**Depth:** deep (cross-file, call-chain traced against `Attempt`, `AttemptController`, `AttemptPolicy`, `AnswerRequest`, and the Alpine countdown/autosave client)
**Files Reviewed:** 10 (plus cross-referenced: `app/Models/Exam.php`, `app/Models/Answer.php`, `app/Policies/ExamPolicy.php`, `database/migrations/*_create_{attempts,answers}_table.php`, `app/Enums/QuestionType.php`, existing tests under `tests/Feature/Student/Attempt*Test.php`)
**Status:** issues_found — **2 blockers**

## Summary

The server-timer design is fundamentally sound: `deadline()` is always derived from `started_at + exam.duration_minutes`, no client input ever reaches it, `isExpired()` uses a consistent `>=` boundary everywhere it's checked, the take page's option rendering is an explicit column whitelist with `is_correct` never selected (confirmed by grep and by the existing `test_the_take_page_never_exposes_is_correct` test), the single-attempt race is backed by a real DB unique constraint with a narrowly-scoped `QueryException`/1062 catch, and there really is exactly one finalize code path (`lockForUpdate()` appears exactly once in executable code in `Attempt.php`; the other two matches are doc-comment prose, not a divergent implementation).

However, two BLOCKER-level defects were found by tracing the concurrency and error-handling paths, both of which undermine the "a write path can never persist past the deadline" guarantee this phase exists to provide:

1. `lockAndFinalize()` only syncs the caller's in-memory `Attempt` instance when *this* call performs the finalize — not when the guarded re-read discovers the row was **already** finalized by a racing request. That stale in-memory state is exactly what `answer()`/`show()` check afterward, so an autosave POST that races a concurrent finalize (e.g. the client's own auto-submit firing at the same moment as an in-flight answer save) can write an `Answer` row to an attempt that is already `submitted` in the database.
2. The take page's Alpine autosave error handler treats **any** HTTP 422 as "deadline expired" and unconditionally calls `autoSubmit()` — but 422 is also the FormRequest's generic validation-failure status (e.g. a `selected_option_id` that no longer matches its question, from a lecturer editing an exam mid-attempt, or any other validation hiccup). This lets a non-expiry validation error force a real, irreversible server-side finalize of a student's still-valid, still-in-time attempt.

Neither of these is a data-leakage or IDOR issue — the answer-key isolation and ownership gates are airtight (details below) — but both are correctness bugs squarely inside the phase's core guarantee ("no write path may persist past the deadline" / "only actual expiry ends the attempt") and are reachable under ordinary concurrent usage, not just adversarial input.

## Critical Issues

### BL-01: Stale in-memory `Attempt` state after a racing finalize lets `answer()` write past submission

**File:** `app/Models/Attempt.php:136-157` (`lockAndFinalize`), consumed by `app/Http/Controllers/Student/AttemptController.php:117-136` (`answer`) and `:65-105` (`show`)

**Issue:**

```php
private function lockAndFinalize(callable $guard): bool
{
    return DB::transaction(function () use ($guard) {
        $locked = self::whereKey($this->id)->lockForUpdate()->first();
        $locked->setRelation('exam', $this->exam);

        if ($locked->status !== 'in_progress' || ! $guard($locked)) {
            return false; // already finalized / guard declined — idempotent no-op
        }

        $locked->update([...]);

        $this->setRawAttributes($locked->getAttributes()); // sync in-memory copy   <-- line 153, ONLY on this branch

        return true;
    }, 3);
}
```

`$this->setRawAttributes(...)` — the only place that refreshes the caller's in-memory `$attempt` object with the true DB row — is called **only** inside the "we just finalized it" branch. When the locked re-read finds `$locked->status !== 'in_progress'` (i.e. a *different*, racing request already finalized the row while this call was blocked waiting for the row lock), the method returns `false` immediately at line 143 without ever syncing `$this`.

Trace the reachable race:

1. Two requests for the same attempt arrive close together, past the deadline: request A is an `answer()` autosave POST (e.g. the last click on a radio button right as the clock hits zero); request B is the client's own `autoSubmit()` POST to `submit()` (or a second tab's `show()`/`answer()` touching the same row). This is not a contrived scenario — it's the exact shape of the app's own auto-submit design (`show.blade.php:237-253`), which fires a real `submit()` POST from a `setInterval` callback that can race an in-flight per-question autosave POST.
2. Both controllers load their own fresh, unlocked `Attempt` copy via route-model binding — both see `status = 'in_progress'`.
3. B enters `lockAndFinalize`, acquires the row lock, updates `status = 'submitted'`, commits.
4. A's `lockForUpdate()` (blocked until B commits) now returns the row with `status = 'submitted'`. A's `lockAndFinalize` hits the early-return branch (line 142-144) and returns `false` — **without syncing `$this`**.
5. Back in `AttemptController::answer()` (lines 121-133):
   ```php
   $attempt->finalizeIfExpired();          // returns false; $attempt->status is still the stale in-memory 'in_progress'
   if ($attempt->status !== 'in_progress') { return response()->json([...], 422); }   // does NOT trigger — stale value
   ...
   Answer::updateOrCreate(...);            // writes an Answer to an attempt that is ALREADY 'submitted' in the DB
   ```

This is a direct violation of the phase's own stated invariant (D-04: "EVERY write path... re-checks now >= deadline. A write arriving after the deadline is rejected — the answer is not persisted") — here the write is not even gated on the deadline check succeeding, it's gated on a value that was never refreshed. The same staleness also makes `show()` render the take-page form (rather than redirect to the submitted confirmation) for an attempt that has, in truth, already been finalized.

No existing test exercises this path — `AttemptSubmitTest::test_a_double_submit_is_idempotent` and `AttemptAnswerTest::test_an_expired_attempt_rejects_answer_writes` each issue requests sequentially against the *same* PHP `Attempt` object/single test process, so the "second reader never learns the DB changed under it" scenario isn't covered.

**Fix:** Always sync, regardless of which branch is taken:

```php
private function lockAndFinalize(callable $guard): bool
{
    return DB::transaction(function () use ($guard) {
        $locked = self::whereKey($this->id)->lockForUpdate()->first();
        $locked->setRelation('exam', $this->exam);

        // Always sync the caller's in-memory copy to the locked, ground-truth
        // row — even a no-op must correct a caller's post-call status check,
        // or a racing finalize leaves the caller trusting stale 'in_progress'.
        $this->setRawAttributes($locked->getAttributes());

        if ($locked->status !== 'in_progress' || ! $guard($locked)) {
            return false;
        }

        $locked->update([
            'status' => 'submitted',
            'submitted_at' => now()->lessThan($locked->deadline())
                ? now()
                : $locked->deadline(),
        ]);

        $this->setRawAttributes($locked->getAttributes());

        return true;
    }, 3);
}
```

---

### BL-02: Client conflates generic validation failure (422) with deadline expiry, forcing a premature real finalize

**File:** `resources/views/student/attempts/show.blade.php:94-107` (per-question `save()`), interacting with `app/Http/Controllers/Student/AttemptController.php:117-136` (`answer`, which returns 422 both for FormRequest validation failures *and* for the explicit "attempt has ended" case) and `:20-27` (`x-on:deadline-expired.window="autoSubmit()"`)

**Issue:**

```js
save(payload) {
    ...
    window.axios.post('{{ $answerUrl }}', { question_id: {{ $question['id'] }}, ...payload })
        .then(() => { this.status = 'saved'; })
        .catch((error) => {
            if (error.response && error.response.status === 422) {
                this.status = 'expired';
                window.dispatchEvent(new CustomEvent('deadline-expired'));   // <-- triggers a REAL submit() POST
            } else {
                this.status = 'failed';
            }
        });
},
```

Two structurally different server conditions both return HTTP 422 from the `answer` endpoint:

- `AnswerRequest` validation failure (e.g. `selected_option_id` no longer `exists` for the given `question_id` — this happens if a lecturer edits/removes an MCQ option while a student's take page still has the old option rendered, or from any other client/network hiccup that produces a stale/malformed payload).
- The controller's own explicit "attempt has ended" response (`AttemptController.php:124-126`).

The Alpine handler cannot tell these apart — it treats status code `422` alone as proof of expiry and dispatches `deadline-expired`, which the outer scope wires directly to `autoSubmit()` (`show.blade.php:27`), which POSTs the real `submit` route and **permanently finalizes the attempt** (`AttemptController::submit` → `Attempt::finalize()`, which is unconditional once `status === 'in_progress'`).

Net effect: a validation error that has nothing to do with the deadline can end a student's attempt early and irreversibly, cutting them off from answering any remaining questions — the opposite failure mode from what D-04/D-05 are meant to guarantee ("only actual expiry ends the attempt"). This is reachable through ordinary mid-exam content edits or transient client bugs, not just malicious input.

**Fix:** Make the "expired" signal explicit and distinct from "invalid payload," rather than overloading the HTTP status code. Simplest fix — give the controller's expiry response a distinct status (avoids colliding with FormRequest's 422) and check for it specifically:

```php
// AttemptController::answer
if ($attempt->status !== 'in_progress') {
    return response()->json(['message' => __('This attempt has ended.')], 409); // 409 Conflict, not 422
}
```

```js
.catch((error) => {
    if (error.response && error.response.status === 409) {
        this.status = 'expired';
        window.dispatchEvent(new CustomEvent('deadline-expired'));
    } else {
        this.status = 'failed'; // includes plain 422 validation errors — retryable, not fatal
    }
});
```

(Equivalently: keep 422 but add a distinguishing JSON field, e.g. `{"expired": true}`, and check `error.response?.data?.expired === true` client-side instead of the bare status code. Either approach removes the false-positive trigger; update `AttemptAnswerTest::test_an_expired_attempt_rejects_answer_writes` and `test_an_answer_after_the_deadline_is_rejected` to assert the new status/marker.)

## High

### HI-01 (informational — folded into BL-02's fix): no server-side signal distinguishes "expired" from "invalid" today

Not a separate defect — noted here only because BL-02's fix requires a coordinated change across `AttemptController::answer` (server) and `show.blade.php`'s `save()` (client); listing it separately as a reminder that both sides must change together, or the fix is incomplete (fixing only the client without changing the server status code leaves the two 422 sources indistinguishable).

## Medium

### MD-01: `submitted()` never runs the finalize chokepoint — contradicts the phase's own "every touch" invariant

**File:** `app/Http/Controllers/Student/AttemptController.php:161-166`

**Issue:** `Attempt::finalizeIfExpired()`'s docblock states it is "the SINGLE chokepoint... Called first in every attempt-touching controller action" (`app/Models/Attempt.php:84-94`). `submitted()` is attempt-touching (it loads and authorizes against `$attempt`) but does **not** call `finalizeIfExpired()`:

```php
public function submitted(Request $request, Attempt $attempt): View
{
    $this->authorize('view', $attempt);

    return view('student.attempts.submitted', compact('attempt'));
}
```

Normally this is harmless because `submit()` and `show()` already finalize before redirecting here. But a student who bookmarks/directly navigates to `attempts/{attempt}/submitted` for an attempt that is still `in_progress` and already past its deadline (e.g. they abandoned the tab without the auto-submit firing, then later opened the confirmation URL from history) will see the "Exam submitted" confirmation page while the underlying row is still `in_progress` and un-finalized — `submitted_at` stays null indefinitely until some *other* future touch of `show()`/`answer()`/`submit()` happens to run. If no such touch ever occurs, the attempt is stuck `in_progress` forever, which is a plausible way for an attempt to silently miss a downstream grading pass (Phase 5) that expects finalized/`submitted` rows.

**Fix:**
```php
public function submitted(Request $request, Attempt $attempt): View
{
    $this->authorize('view', $attempt);

    $attempt->loadMissing('exam');
    $attempt->finalizeIfExpired();

    return view('student.attempts.submitted', compact('attempt'));
}
```

## Low

### LO-01: `AnswerRequest` validation runs before `AttemptPolicy::update()` authorization — minor ownership oracle

**File:** `app/Http/Requests/Student/AnswerRequest.php:31-46`, `app/Http/Controllers/Student/AttemptController.php:117-119`

**Issue:** `AnswerRequest` is injected as a controller parameter, so Laravel validates it (via `$this->route('attempt')->exam_id` in the `question_id` rule) while resolving the controller's dependencies — **before** the controller body's `$this->authorize('update', $attempt)` runs. A request against another student's attempt ID therefore hits validation logic keyed on that attempt's `exam_id` before the ownership/visibility check fires. The practical leak is narrow (only distinguishes "attempt exists and its exam contains this question_id" vs not — no answer content, no `is_correct`), and any attempt ID that exists at all is already discoverable via the 404-vs-not-404 behavior of implicit route model binding, so this doesn't introduce a *new* class of oracle, just extends it slightly. Still worth tightening for defense in depth.

**Fix:** Either move the ownership check into a route-level `can:update,attempt` middleware (runs before FormRequest resolution) or accept this as a documented, low-severity ordering quirk consistent with the codebase's established `authorize() => true` FormRequest split pattern.

### LO-02: No null-guard on the locked re-read in `lockAndFinalize`

**File:** `app/Models/Attempt.php:139-140`

**Issue:**
```php
$locked = self::whereKey($this->id)->lockForUpdate()->first();
$locked->setRelation('exam', $this->exam);
```
If the row were ever removed between `$this` being loaded and this re-read (no delete endpoint exists today for attempts, so this is currently unreachable), `first()` returns `null` and the next line throws a fatal `Error: Call to a member function setRelation() on null` instead of failing gracefully. Purely defensive — flagging so it isn't forgotten if a future phase adds attempt deletion/archival.

**Fix:** `abort_if($locked === null, 410);` (or equivalent) before use.

### LO-03: No cross-field validation between question type and the answer field actually submitted

**File:** `app/Http/Requests/Student/AnswerRequest.php:31-46`

**Issue:** Nothing stops a client from POSTing `answer_text` for an MCQ question (leaving `selected_option_id` null) or `selected_option_id` for an open question in a way that happens to pass the `exists` rule via a stale/guessed ID belonging to a different question of the same type family. In practice the rendered form only ever sends the field matching the question's type, so this is a data-quality gap rather than an exploitable one — but it does mean `Answer::updateOrCreate` will silently persist a mismatched-shape row that Phase 5's grader will need to defensively handle rather than assume type-consistency.

**Fix (optional hardening):** add a `Rule::prohibitedIf`/`after()` check tying `selected_option_id` presence to the target question's `type === mcq` and `answer_text` to `type === open`.

### LO-04: `Answer::updateOrCreate` has no race protection against the same unique-constraint violation the codebase explicitly mitigates elsewhere

**File:** `app/Http/Controllers/Student/AttemptController.php:130-133`

**Issue:** `answers` has a `unique(attempt_id, question_id)` constraint (`database/migrations/2026_07_15_100010_create_answers_table.php:27`), and `Answer::updateOrCreate` does a SELECT-then-write, not an atomic upsert. Two concurrent identical autosave POSTs for the same question (e.g. a real double-click on a radio input, or a client retry racing the original request) can both miss the initial SELECT and both attempt an INSERT — one succeeds, the other throws an uncaught `QueryException`(1062), surfacing as a 500 to the client instead of the graceful `retry` UX the page already has for other failures. `AttemptController::store()` explicitly catches and handles this exact error code for the *attempt* row; `answer()` does not do the analogous thing for the *answer* row, which is an inconsistency in how the same class of race is handled across the same phase.

**Fix:** Wrap in a narrow catch mirroring `store()`'s pattern:
```php
try {
    Answer::updateOrCreate(
        ['attempt_id' => $attempt->id, 'question_id' => $data['question_id']],
        collect($data)->only(['selected_option_id', 'answer_text'])->all()
    );
} catch (QueryException $e) {
    if (($e->errorInfo[1] ?? null) !== 1062) {
        throw $e;
    }
    // Lost the race — the other concurrent write already has this content; re-apply ours to win last-write.
    Answer::where('attempt_id', $attempt->id)->where('question_id', $data['question_id'])
        ->update(collect($data)->only(['selected_option_id', 'answer_text'])->all());
}
```

### LO-05: Concurrency guarantees are asserted in docblocks but not exercised by the test suite

**File:** `tests/Feature/Student/AttemptStartTest.php:50-68`, `tests/Feature/Student/AttemptSubmitTest.php:36-59`

**Issue:** `test_a_concurrent_double_start_does_not_create_a_duplicate_attempt` pre-seeds the "winning" row and lets `firstOrCreate`'s initial SELECT find it — it never actually drives two requests through the INSERT race, so the `QueryException`/1062 catch branch in `AttemptController::store()` is not covered by any test. Similarly, no test drives two genuinely concurrent requests against the same in-memory-vs-DB gap described in BL-01. This isn't a source bug, but it means the concurrency claims in the code comments (and the "no code path may write... past the deadline" invariant) are unverified where it matters most.

**Fix:** Add a test that opens a second DB connection/transaction (or uses two separate model instances simulating two requests) to genuinely hit the unique-constraint race for `store()`, and a regression test for BL-01 once fixed (assert that after a raced finalize, a subsequent `answer()` call on the *same* pre-loaded `$attempt` instance is rejected).

## What's airtight (verified, not just assumed)

- **Server-timer, CWE-602:** `deadline()`/`isExpired()`/`remainingSeconds()` are 100% server-derived from `started_at` + `exam.duration_minutes`; no controller, request class, or view ever reads a client-supplied time/duration/remaining value into them. `remainingSeconds()` is explicitly documented and used only for display.
- **Boundary consistency:** `isExpired()` uses `now() >= deadline()` uniformly everywhere expiry is evaluated (`finalizeIfExpired`'s outer check and its `lockAndFinalize` guard) — no off-by-one divergence between call sites.
- **Single finalize implementation:** grep confirms `lockForUpdate` appears exactly once in executable code (`Attempt.php:139`); the other two matches are doc-comment prose describing the same method, not a second/divergent code path.
- **Single-attempt race:** backed by a real `unique(exam_id, user_id)` DB constraint; the `QueryException` catch in `store()` is narrowly scoped to MySQL error code 1062 and rethrows everything else.
- **Answer-key leakage, TAK-06:** the take-page question/option view-model is an explicit column whitelist (`['id','exam_id','type','body','position','points']` for questions, `['id','question_id','body']` for options) — `is_correct` is never selected, not merely hidden. No `@json` directive anywhere in the reviewed views. Confirmed by grep (`is_correct`/`@json` absent from `resources/views/student/**`) and corroborated by the existing `test_the_take_page_never_exposes_is_correct` test asserting the whole raw response body.
- **Mass assignment / cross-exam IDOR on answers:** `AnswerRequest` never accepts `is_correct`/`score`/`attempt_id`; `question_id` is scoped to `exists(questions.id where exam_id = $attempt->exam_id)` and `selected_option_id` is scoped to `exists(options.id where question_id = <the validated question>)`, so a student cannot save an answer against a question belonging to a different exam.
- **`submit()` idempotency:** confirmed both by code (`finalize()`'s guard is unconditionally `true`, so a second call simply hits the locked re-read's `status !== 'in_progress'` no-op) and by the existing `test_a_double_submit_is_idempotent` test.
- **AttemptPolicy's own-attempt + exam-still-takeable double gate:** intentional per the locked decision D-08 (`04-RESEARCH.md:637-643` explicitly documents and accepts the "unpublishing an exam mid-attempt locks the student out" tradeoff) — not re-reported here as a defect, consistent with this review's instruction to treat already-known, deliberately-accepted tradeoffs as out of scope.

---

_Reviewed: 2026-07-16_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: deep_

---

## Orchestrator Resolution (2026-07-16)

Each finding verified by tracing the actual code. Fixes committed in `2022e59` with a 5-test regression suite (`tests/Feature/Student/Phase4ReviewFixesTest.php`); full suite 155/384 green.

| Finding | Severity | Action |
|---------|----------|--------|
| `lockAndFinalize` doesn't sync `$this` on the "already finalized" branch → autosave writes to a submitted attempt | **blocker** | **FIXED** — `lockAndFinalize` now `setRawAttributes` on BOTH branches, AND `answer()` re-checks status + writes atomically under `lockForUpdate` (closes the TOCTOU entirely, also fixes the low #4 1062-race). Regression: answering a finalized attempt writes nothing. |
| Autosave treats any 422 as expiry → a validation error force-finalizes a valid attempt | **blocker** | **FIXED** — deadline rejection now returns `{expired: true}` (still 422); the Alpine handler only auto-submits when that flag is present, else shows "save failed". Regressions: deadline→expired flag; validation→no flag, attempt stays in_progress. |
| `submitted()` never runs `finalizeIfExpired()` (invariant hole) | medium | **FIXED** — `submitted()` now finalizes on touch. Regression: confirmation URL finalizes an expired attempt. |
| `AnswerRequest::authorize()` returns `true` → validation before authorization | low | **FIXED** — `authorize()` delegates to `AttemptPolicy@update`. Regression: non-owner → 403 before validation. |
| `answer()` `updateOrCreate` unhandled 1062 race | low | **FIXED (absorbed)** — the atomic `lockForUpdate` write serializes same-attempt saves. |
| No MCQ-vs-open answer-shape cross-validation | low | **DEFERRED** — harmless: Phase-5 grading reads by question type (MCQ→selected_option_id, open→answer_text); a mismatched field is ignored, never mis-scored. Can tighten in Phase 5. |
| Missing null-guard / minor | low | **DEFERRED** — no correctness impact on the verified paths. |

No unresolved blockers. The server-timer math, answer-key isolation, DB-unique backstop, and mass-assignment guards were confirmed airtight by the review and remain so.

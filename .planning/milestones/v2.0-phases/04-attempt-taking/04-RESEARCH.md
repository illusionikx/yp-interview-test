# Phase 4: Attempt-Taking - Research

**Researched:** 2026-07-16
**Domain:** Server-authoritative timed exam attempts (Laravel 11.55 + Breeze + MySQL, Blade + Alpine)
**Confidence:** HIGH (Laravel 11 transaction/locking/testing APIs verified directly against official `laravel.com/docs/11.x` pages) / MEDIUM (Alpine.js countdown/autosave client pattern — cross-verified community sources, no single official Alpine spec for this composite pattern)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Attempt lifecycle & single-attempt integrity (TAK-01, TAK-05)**
- **D-01:** Starting an exam does `firstOrCreate` an `attempts` row keyed on `(exam_id, user_id)` — the Phase-1 **DB unique constraint** is the race-proof backstop; the app also checks status. New attempt: `started_at = now()`, `status = in_progress`. If an attempt already exists and is `in_progress` → **resume** it (same `started_at`). If already `submitted`/`graded` → **blocked** (cannot retake; show "already submitted", link to Phase-5 results later). Wrap the start in a transaction / handle the unique-violation gracefully.
- **D-02:** `attempts.status` (the existing column, default `in_progress`) is the lifecycle: `in_progress` → `submitted` (this phase) → `graded` (Phase 5). Do not add columns (schema is fixed from Phase 1).

**Server-authoritative timer (TAK-02) — the crux**
- **D-03:** The deadline is computed server-side: `deadline = attempts.started_at + exams.duration_minutes`. NEVER trust any client-supplied time/duration/remaining value. The server passes `remaining_seconds` (computed) to the take page for display only.
- **D-04:** EVERY write path (each answer autosave AND submit) re-checks `now() >= deadline` server-side. A write arriving after the deadline is **rejected** (422/redirect) — the answer is not persisted. This is what makes the client countdown cosmetic and the server the sole timing authority.

**Auto-submit on expiry (TAK-04)**
- **D-05:** Two-layer: (1) the client Alpine countdown auto-submits (POSTs the submit) when it reaches 0; (2) **server backstop — lazy finalization**: any touch of an `in_progress` attempt whose deadline has passed finalizes it (`status = submitted`, `submitted_at = deadline`) and refuses further answer changes. No cron/queue (per research: lazy on-touch is sufficient at this scale). An abandoned expired attempt is thus effectively auto-submitted the next time it (or Phase-5 results) is accessed.

**Answer autosave (TAK-03)**
- **D-06:** Each answer is persisted the moment it changes — an AJAX POST per answer (`question_id` + `selected_option_id` for MCQ, or `answer_text` for open) doing `updateOrCreate` on `answers` (Phase-1 unique `(attempt_id, question_id)`). Alpine posts on change/blur. On reload, the take page **rehydrates** existing answers so nothing is lost (refresh/disconnect safe). Autosave writes are subject to the D-04 deadline check.

**No answer leakage (TAK-06)**
- **D-07:** The take-exam page renders each MCQ's option **bodies** but MUST NOT expose `options.is_correct` in HTML, JSON, or any embedded data. Enforce by an explicit select/whitelist (or `$hidden` on `Option::is_correct` for the student render / a dedicated view-model) — never eager-load the raw model with `is_correct` into the Blade/Alpine data. Grading (which reads `is_correct`) is entirely server-side in Phase 5.

**Access control (RBAC-05 attempt clause)**
- **D-08:** An `AttemptPolicy` extending the Phase-3 authorization pattern: a student may take/answer/submit only their **own** attempt (`attempt.user_id === auth()->id()`) AND only when the exam is takeable (reuse `Exam::scopeVisibleTo` / `ExamPolicy::takeable` — published + assigned to their classroom). Enforce via `$this->authorize()` on every attempt route → 403 for another student's attempt or an out-of-class exam. No IDOR on attempts.

**Take UI (UI-SPEC warranted)**
- **D-09:** Student take-exam page: questions listed (MCQ as radio groups, open-text as textarea), a prominent **Alpine countdown** (from server `remaining_seconds`, auto-submits at 0), autosave indicator, and a Submit button. Post-submit → a simple **confirmation** page ("Your exam has been submitted") — NOT a score page (results are Phase 5). This phase HAS a UI hint — generate a UI-SPEC for the take interface (countdown, question rendering, autosave/submit states).
- **D-10:** Wire the Phase-3 "Start" seam: the previously-disabled Start button on the student exam landing becomes the active `POST` that starts/resumes the attempt and redirects to the take page.

### Claude's Discretion
- Exact route/controller names (e.g. `Student\AttemptController` with start/show/answer/submit), whether autosave is one endpoint or per-type, countdown component structure, and the confirmation page copy — planner/executor choice, provided D-01..D-10 hold.

### Deferred Ideas (OUT OF SCOPE)
- **Grading — `AttemptGrader`, MCQ auto-score, lecturer manual grading, results/score display (GRD-01..05)** → Phase 5. This phase leaves `is_correct`/`score` null and shows only a submitted-confirmation (no score).
- **Randomized question/option order, multi-select MCQ** → v2.
- **Scheduled auto-submit sweep (cron/queue)** → out of scope; lazy on-touch finalization is sufficient (research).
- **Demo seeder + README** → Phase 6.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-------------------|
| TAK-01 | Student can start an assigned exam, creating a single timed attempt | Pattern 1 (`firstOrCreate` + unique-violation catch), Code Example "Start/Resume an Attempt" |
| TAK-02 | The exam time limit is enforced server-side; the UI shows a live countdown driven by the server deadline | Pattern 2 (server-anchored deadline), Code Example "Attempt::deadline()/remainingSeconds()", Validation Architecture TAK-02 rows |
| TAK-03 | Student answers are saved incrementally as they are entered (autosave), surviving a refresh or disconnect | Pattern 4 (idempotent `updateOrCreate` autosave), Code Example "Answer autosave endpoint" |
| TAK-04 | When the time limit expires, the attempt auto-submits and further answer changes are rejected | Pattern 3 (lazy on-touch finalization), Code Example "finalizeIfExpired()" |
| TAK-05 | A Student can attempt each exam only once (enforced by a database unique constraint) | Pattern 1, Common Pitfalls "Double-start race", Validation Architecture TAK-05 rows |
| TAK-06 | Correct answers are never exposed to the Student while taking the exam | Pattern 5 (explicit column whitelist, never raw model into `@json`), Validation Architecture TAK-06 rows |
</phase_requirements>

## Summary

Phase 4 is the project's Core Value and the highest-risk phase: every requirement (TAK-01..06) converges on the same architectural rule — **the server is the sole timing, ownership, and correctness authority; the client is presentation only.** The schema is already fixed from Phase 1 (`attempts.started_at`/`submitted_at`/`status`/`score`, `unique(exam_id,user_id)`; `answers.selected_option_id`/`answer_text`/`is_correct`/`score`, `unique(attempt_id,question_id)`), so this phase adds **zero new columns and zero new Composer packages** — it is pure application logic on top of an already-correct data model.

The deadline is never stored; it is computed on every touch as `started_at->copy()->addMinutes($exam->duration_minutes)` (D-03 — no `expires_at` column exists, deliberately, per the locked schema). Every write path (`answer()`, `submit()`) and every read of an in-progress attempt (`show()`) must call a single `finalizeIfExpired()` helper on `Attempt` first — this is the one place D-04's "every write re-checks the deadline" and D-05's "lazy on-touch finalization" collapse into the same code path, so there is no way to implement one without the other. Single-attempt integrity leans on the DB `unique(exam_id, user_id)` constraint verified in Phase 1 as the actual race-proof mechanism; `firstOrCreate` is an optimization for the common case, and the controller must catch the `QueryException` (MySQL error code `1062`, SQLSTATE `23000`) that fires when two concurrent requests lose the race, treating it as "someone else already started this — fetch and use that row" rather than an error. Answer leakage (TAK-06) is prevented not by hiding a column on the shared `Option` model (which is also used, with `is_correct` intentionally visible, by every Lecturer authoring/grading view) but by building an explicit plain-array view-model for the student take page and never passing a raw `Option`/`Question` Eloquent collection into `@json()` or Alpine's `x-data`.

Laravel 11.55's `DB::transaction()` (with its deadlock-retry second argument), `lockForUpdate()`, and the test-time-travel helpers (`$this->travel()`, `$this->travelTo()`, `$this->freezeTime()`) are the exact verified primitives this phase needs — confirmed directly against the official `laravel.com/docs/11.x` pages in this research session, not assumed from training data.

**Primary recommendation:** Add `deadline()`, `remainingSeconds()`, `isExpired()`, and `finalizeIfExpired()` methods to `Attempt` (no new migration); route every attempt-touching controller action through `finalizeIfExpired()` first; use `firstOrCreate` + `QueryException` code-1062 catch for start; use `updateOrCreate` for autosave; build an explicit array view-model (never `$hidden`) for the take-page question/option payload; extend the Phase-3 shared-scope + Policy pattern into `AttemptPolicy`.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Single-attempt creation/resume (TAK-01, TAK-05) | API/Backend (`AttemptController@store`, `firstOrCreate`) | Database/Storage (`unique(exam_id,user_id)` — the actual race-proof backstop) | The app-level check is UX/convenience; the DB constraint is what makes the guarantee true under concurrency |
| Deadline computation (TAK-02) | API/Backend (`Attempt::deadline()`, computed from `started_at` + `exam.duration_minutes`) | — | Never stored, never client-computed; a pure server-side derivation re-evaluated on every touch |
| Live countdown display | Browser/Client (Alpine `setInterval` from a server-passed `remaining_seconds` seed) | API/Backend (re-validates on every write regardless of what the client displays) | Cosmetic only — CWE-602 boundary; the client tier owns *display*, never *enforcement* |
| Answer autosave persistence (TAK-03) | API/Backend (`updateOrCreate` on `answers`) | Browser/Client (Alpine `@change`/`@blur` triggers the AJAX call) | Persistence + the deadline gate live server-side; the client only decides *when* to fire the request |
| Auto-submit on expiry (TAK-04) | API/Backend (`finalizeIfExpired()` lazy on-touch) | Browser/Client (countdown-at-zero auto-POSTs submit, a UX nicety, not the enforcement mechanism) | The server backstop is authoritative even if the client-side auto-submit request never fires (tab closed, JS blocked) |
| No answer leakage (TAK-06) | API/Backend (explicit column-whitelisted view-model built in the controller) | — | Must be enforced before data leaves the server; no client-side mitigation is possible once `is_correct` is in the response |
| Attempt/exam ownership authorization (TAK-01..06, RBAC-05) | API/Backend (`AttemptPolicy` + `$this->authorize()`) | Database/Storage (`Exam::scopeVisibleTo` query predicate reused by the policy) | Object-level authorization is a backend concern; the DB scope is the shared source of truth the policy delegates to, per the Phase-3 pattern |
| Submit finalize (transactional) | API/Backend (`DB::transaction` + `lockForUpdate`) | Database/Storage (row lock prevents concurrent double-finalize) | Idempotency under a race (double-click, two tabs) requires both the transaction boundary and a row-level lock |

## Project Constraints (from CLAUDE.md)

- **Laravel 11 slimmed skeleton:** no `app/Http/Kernel.php`/`app/Console/Kernel.php` — middleware aliases register in `bootstrap/app.php` (already done for `role:*` in this project); no scheduled sweep is being added this phase anyway (D-05 explicitly rejects cron/queue).
- **MySQL via Herd is the real database** — tests must run against the live MySQL `yp-student-exam` connection, not SQLite. `phpunit.xml` in this repo has the SQLite in-memory override lines commented out, confirming the project already tests against live MySQL.
- **Blade + Tailwind + Alpine only** — no SPA/Livewire/Inertia/Echo/Pusher/Reverb. The countdown and autosave must be plain Alpine + the already-configured global `window.axios` (from `resources/js/bootstrap.js`), not a new client dependency.
- **No new Composer packages** for this domain — confirmed again for Phase 4: timer enforcement, single-attempt integrity, and autosave are all implementable with Laravel-native `DB::transaction`, `lockForUpdate`, Carbon, and Eloquent `updateOrCreate`/`firstOrCreate`.
- **`$fillable` allowlists always, never `$guarded = []`; `$request->validated()` never `$request->all()`** — applies directly to the autosave/submit endpoints, which are the most sensitive write surface added this phase (a forged `is_correct`/`score`/`attempt_id` in the POST body must never reach the database).
- **No model events/observers for grading-adjacent side effects** — this phase doesn't grade, but the same principle applies to `finalizeIfExpired()`: it must be an explicit method call at the top of each controller action, not a model `booted()`/`saving` event, so "does this request finalize an expired attempt" stays visible at the call site.
- **GSD workflow enforcement** — downstream planning/execution must go through `/gsd-plan-phase` → `/gsd-execute-phase`, not direct repo edits.

## Standard Stack

### Core (already installed — no `composer require` needed)

| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|---------------|
| `laravel/framework` | 11.55.0 (confirmed via `composer show`) [VERIFIED: composer] | `DB::transaction()`, `lockForUpdate()`, Eloquent `firstOrCreate`/`updateOrCreate`, Carbon-backed `now()`/date casts, `travel()`/`travelTo()`/`freezeTime()` test helpers | All timer/race/autosave primitives this phase needs are native to the already-installed framework version; verified directly against `laravel.com/docs/11.x` |
| `nesbot/carbon` (transitive via `illuminate/support`) | ships with Laravel 11 | `Carbon`/`CarbonImmutable` date math (`addMinutes`, `greaterThanOrEqualTo`, `getTimestamp`) for deadline computation | `Attempt::started_at`/`submitted_at` are already cast `'datetime'` (Phase 1), returning Carbon instances with zero extra wiring |
| Alpine.js | as scaffolded via Breeze/Vite (`resources/js/app.js` already calls `Alpine.start()`) [VERIFIED: codebase] | Countdown display (`x-data`/`x-init`/`setInterval`), autosave triggers (`@change`/`@blur`), auto-submit dispatch at zero | Already the project's only client-interactivity layer (used for MCQ authoring rows in Phase 2); no SPA/Livewire per PROJECT.md constraint |
| `window.axios` | bundled via `resources/js/bootstrap.js` (`import axios from 'axios'; window.axios = axios;`) [VERIFIED: codebase] | AJAX POST for autosave + auto-submit from Alpine handlers | Already globally available and pre-configured; axios's default `xsrfCookieName`/`xsrfHeaderName` (`XSRF-TOKEN` / `X-XSRF-TOKEN`) automatically attaches Laravel's CSRF cookie on same-origin requests — no manual `<meta name="csrf-token">` + `fetch()` header wiring needed |

### Supporting

| Pattern/API | Purpose | When to Use |
|-------------|---------|-------------|
| `Illuminate\Database\QueryException` + `$e->errorInfo[1] === 1062` (or `$e->getCode() === '23000'`) | Detect a lost race on `firstOrCreate` at start | Wrap the `Attempt::firstOrCreate(...)` call; on catch, re-query the now-existing row instead of erroring |
| `DB::transaction($closure, $attempts = 5)` | Atomic multi-statement writes (start, submit) with automatic deadlock retry | Any write that must be all-or-nothing; the 2nd argument is a real, documented Laravel 11 API (retries N times on deadlock before throwing) |
| `Model::lockForUpdate()` inside a transaction | Serialize concurrent writers to the *same* attempt row (double-submit, expiry race) | `submit()` and `finalizeIfExpired()` — lock the specific `Attempt` row before reading/branching on `status` |
| `$this->travel()`/`$this->travelTo()`/`$this->freezeTime()` (PHPUnit test helpers) | Deterministically simulate "time has passed the deadline" in feature tests | Every TAK-02/TAK-04 test — see Validation Architecture |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Lazy on-touch `finalizeIfExpired()` (D-05) | `Schedule::command('exams:auto-submit')->everyMinute()` sweep in `routes/console.php` | Explicitly rejected by CONTEXT.md and REQUIREMENTS.md "Out of Scope" — needs a running scheduler process, a common silent-failure mode for a graded clean-clone deliverable; only worth it if a lecturer needs a *live* dashboard of in-progress attempts (V2-05) |
| Explicit array view-model for student-facing options (D-07 primary recommendation) | `$hidden = ['is_correct']` on `Option` model | `$hidden` is global to the model — it would also suppress `is_correct` in every Lecturer authoring/grading Blade view and any future JSON response, requiring `makeVisible()` calls scattered through Lecturer code to undo it. An explicit per-view whitelist has zero blast radius on existing Phase 2/3 code |
| `firstOrCreate` + catch `QueryException` (D-01) | `Cache::lock()` atomic lock wrapping the create | Adds a cache-store dependency for a guarantee the DB unique constraint already provides for free; only useful if the app needed to lock across *multiple* tables/rows atomically, which single-attempt-per-exam does not |
| Storing `duration_minutes`/`expires_at` on `attempts` at start time (a documented STACK.md alternative) | Live-compute deadline from `exam.duration_minutes` every time (chosen, D-03) | Storing the duration would insulate an in-progress attempt from a lecturer editing `exam.duration_minutes` mid-attempt — but the schema is fixed (D-02: no new columns), **and** Phase 2's `ExamController@edit` already `abort_if($exam->is_published, 403)`s any edit to a published exam, so a published (hence takeable) exam's `duration_minutes` cannot change while attempts exist. The live-compute approach is safe under the actual codebase's edit-gating; see Open Questions for the edge case that remains |

**Installation:** None. `composer install`/`npm install` at current `composer.lock`/`package-lock.json` state is sufficient — no `composer require`, no `npm install <package>`.

**Version verification:** `laravel/framework` confirmed at `v11.55.0`, released 2026-07-14 (`composer show laravel/framework`) [VERIFIED: composer]. Official docs consulted at the `/11.x/` path (`laravel.com/docs/11.x/database`, `/eloquent`, `/queries`, `/mocking`, `/eloquent-serialization`, `/validation`) match this installed major version.

## Package Legitimacy Audit

**Not applicable — this phase introduces zero new Composer or npm packages.** Per CONTEXT.md ("No new packages") and confirmed by the Standard Stack section above, every capability (transactions, locking, Carbon date math, time-travel testing, Eloquent upserts/serialization control, Alpine, axios) is already present in the installed dependency set from Phases 1–3. No `package-legitimacy check` run was needed.

## Architecture Patterns

### System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Browser (Blade + Alpine)                                                │
│  student/exams/show — "Start" button (D-10 seam activation)              │
│  student/attempts/show — countdown (display only) + question form        │
│    ├─ x-init seeds countdown from server `remaining_seconds`             │
│    ├─ setInterval ticks the DISPLAYED clock every 1s (no auth authority) │
│    ├─ @change/@blur on each answer → axios.post(autosave)  (debounced)   │
│    └─ countdown hits 0 → axios.post(submit)  [client auto-submit, D-05]  │
└───────────────┬────────────────────────────────────────────┬─────────────┘
                │ POST (session + CSRF via axios XSRF cookie) │
                ▼                                              ▼
┌───────────────────────────────┐            ┌──────────────────────────────┐
│ POST /student/attempts/{exam} │            │ POST /student/attempts/{a}/  │
│  AttemptController@store       │            │   answers | submit           │
│  1. authorize('takeable',$exam)│            │  1. authorize('update',$attempt)
│  2. firstOrCreate (unique DB   │            │  2. $attempt->finalizeIfExpired()
│     backstop) inside try/catch │            │     — LAZY AUTO-SUBMIT (D-05)│
│     for QueryException 1062    │            │  3. if status!=in_progress → │
│  3. resume in_progress /       │            │     422/redirect, NO write   │
│     block submitted (TAK-05)   │            │     (D-04 deadline gate)     │
└───────────────┬─────────────────┘            │  4. updateOrCreate(answer)  │
                │                               │     OR transactional submit │
                ▼                               │     with lockForUpdate      │
┌───────────────────────────────┐              └───────────────┬──────────────┘
│ GET /student/attempts/{a}     │                              │
│  AttemptController@show        │                              │
│  1. authorize('view',$attempt) │                              │
│  2. finalizeIfExpired() first  │◄─────────────────────────────┘
│     (any TOUCH finalizes late  │   same helper, single source of truth
│     attempts — D-05)           │   for "is this attempt still open"
│  3. build explicit view-model: │
│     questions[].options[] =    │
│     ->get(['id','question_id', │  NEVER pass raw Option/Question models
│            'body'])  (TAK-06)  │  into @json()/x-data (Pattern 5)
│  4. rehydrate answers by       │
│     question_id (TAK-03)       │
│  5. pass remaining_seconds     │
└─────────────────────────────────┘
                │
                ▼
┌───────────────────────────────────────────────────────────────────────┐
│  MySQL: attempts (unique exam_id+user_id), answers (unique attempt_id  │
│  +question_id) — the DB-level backstops that make TAK-01/03/05 true    │
│  under concurrency even if the app-level logic above has a bug         │
└───────────────────────────────────────────────────────────────────────┘
```

### Recommended Project Structure

```
app/
├── Http/
│   ├── Controllers/Student/
│   │   └── AttemptController.php    # store (start/resume), show (take page),
│   │                                 # answer (autosave), submit (finalize)
│   ├── Requests/Student/
│   │   └── AnswerRequest.php        # validates question_id/selected_option_id/
│   │                                 # answer_text shape; never touches is_correct/score
│   └── Middleware/                  # none new — role:student + AttemptPolicy suffice
├── Models/
│   └── Attempt.php                  # + deadline(), remainingSeconds(), isExpired(),
│                                     #   finalizeIfExpired() — no new migration
├── Policies/
│   └── AttemptPolicy.php            # view/update: own attempt AND Exam::visibleTo()
resources/views/student/
├── attempts/
│   ├── show.blade.php               # take page: questions + Alpine countdown/autosave
│   └── submitted.blade.php          # confirmation only, no score (Phase 5 boundary)
routes/
└── student.php                      # + attempts.store/show/answer/submit routes
```

### Pattern 1: `firstOrCreate` + DB unique constraint + `QueryException` catch (TAK-01, TAK-05)

**What:** Attempt to `firstOrCreate` the `(exam_id, user_id)` row optimistically; if a concurrent request wins the race, catch the resulting `QueryException` (MySQL `1062 Duplicate entry`, SQLSTATE `23000`) and re-fetch the row the *other* request created, then branch on its `status`.
**When to use:** Any "start exactly once" flow backed by a DB unique constraint — the app-level `firstOrCreate` check-then-insert is *not* atomic across two concurrent requests (Laravel's `firstOrCreate` runs a `SELECT` then an `INSERT`, not a single atomic operation as of the officially documented 11.x API — the newer `createOrFirst` upsert-style helper referenced in some community write-ups is **not present in the official Laravel 11.x docs consulted in this session**; treat it as unverified for this version and rely on the catch-and-refetch pattern instead) [CITED: laravel.com/docs/11.x/eloquent#retrieving-or-creating-models].
**Trade-offs:** One extra `catch` branch and a re-query vs. relying purely on the DB constraint; the payoff is a graceful "you've already started/resume" response instead of a 500 error under real concurrency (two tabs, double-click, or a retried request).

```php
// Source: laravel.com/docs/11.x/eloquent#retrieving-or-creating-models (firstOrCreate signature, VERIFIED)
//         + laravel.com/docs/11.x/database#database-transactions (DB::transaction, VERIFIED)
//         + community-corroborated QueryException/1062 catch pattern (CITED, cross-verified)
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

public function store(Request $request, Exam $exam)
{
    $this->authorize('takeable', $exam); // ExamPolicy — reused as-is from Phase 3

    try {
        $attempt = DB::transaction(function () use ($exam, $request) {
            return Attempt::firstOrCreate(
                ['exam_id' => $exam->id, 'user_id' => $request->user()->id],
                ['started_at' => now(), 'status' => 'in_progress']
            );
        });
    } catch (QueryException $e) {
        if (($e->errorInfo[1] ?? null) !== 1062) {
            throw $e; // not a duplicate-key violation — a real error
        }
        // Lost the race: another concurrent request already created the row.
        $attempt = Attempt::where('exam_id', $exam->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    if ($attempt->status !== 'in_progress') {
        return redirect()->route('student.exams.show', $exam)
            ->with('status', __('You have already submitted this exam.'));
    }

    return redirect()->route('student.attempts.show', $attempt);
}
```

### Pattern 2: Server-anchored, never-stored deadline (TAK-02)

**What:** `Attempt::deadline()` recomputes `started_at->copy()->addMinutes($exam->duration_minutes)` on every call — never persisted, never accepted from the client. `remainingSeconds()` derives the display value from the same computation.
**When to use:** Every place that needs "how much time is left" — the take-page render, the autosave/submit deadline gate, and nowhere else.

```php
// app/Models/Attempt.php additions
// Source: Carbon methods ship with laravel/framework 11.55.0's illuminate/support;
// started_at is already cast 'datetime' (Phase 1 01-02-SUMMARY.md) [VERIFIED: codebase]
use Illuminate\Support\Carbon;

public function deadline(): Carbon
{
    return $this->started_at->copy()->addMinutes($this->exam->duration_minutes);
}

public function isExpired(): bool
{
    return now()->greaterThanOrEqualTo($this->deadline());
}

public function remainingSeconds(): int
{
    // Timestamp subtraction avoids Carbon::diffInSeconds()'s sign-convention
    // ambiguity (absolute-by-default in some call forms) — explicit and testable.
    return max(0, $this->deadline()->getTimestamp() - now()->getTimestamp());
}
```

**Note:** `deadline()`/`isExpired()`/`remainingSeconds()` all read `$this->exam->duration_minutes` — the caller MUST eager-load `exam` (`Attempt::with('exam')` or route-model-bind with the relation preloaded) to avoid an N+1 query on every deadline check.

### Pattern 3: Lazy on-touch finalization as the single D-04/D-05 chokepoint (TAK-04)

**What:** One method, `finalizeIfExpired()`, called at the top of `show()`, `answer()`, and `submit()`. It is the *only* place that flips `in_progress` → `submitted` due to time expiry, and it doubles as the deadline gate every write must pass.
**When to use:** Any timed-workflow finalization that must happen "on next touch" rather than via a background job (explicitly the chosen approach per D-05 and REQUIREMENTS.md's Out of Scope list).
**Trade-offs:** An attempt nobody ever revisits after expiry stays `in_progress` in the DB until something touches it (a future results-listing query, for instance) — acceptable per the locked decision; Phase 5's grading/results views will also need to call `finalizeIfExpired()` (or filter on computed expiry) when listing attempts, which is a note for that phase's research, not this one.

```php
// app/Models/Attempt.php addition
use Illuminate\Support\Facades\DB;

/**
 * Returns true if this attempt was JUST finalized by this call
 * (i.e. it was in_progress and past its deadline). Idempotent —
 * safe to call on every request that touches an attempt.
 */
public function finalizeIfExpired(): bool
{
    if ($this->status !== 'in_progress' || ! $this->isExpired()) {
        return false;
    }

    return DB::transaction(function () {
        // lockForUpdate serializes concurrent touches of the SAME attempt
        // (e.g. the client's auto-submit firing at the same instant as a
        // manual page reload) so only one finalizes it.
        // Source: laravel.com/docs/11.x/queries#pessimistic-locking (VERIFIED)
        $locked = self::whereKey($this->id)->lockForUpdate()->first();

        if ($locked->status !== 'in_progress' || ! $locked->isExpired()) {
            return false; // someone else already finalized it inside the lock
        }

        $locked->update([
            'status' => 'submitted',
            'submitted_at' => $locked->deadline(),
        ]);

        $this->setRawAttributes($locked->getAttributes()); // sync in-memory copy
        return true;
    });
}
```

Controller usage (every attempt-touching action):

```php
public function answer(AnswerRequest $request, Attempt $attempt)
{
    $this->authorize('update', $attempt);

    $attempt->loadMissing('exam');
    $attempt->finalizeIfExpired(); // D-05 lazy backstop, runs unconditionally first

    if ($attempt->status !== 'in_progress') {
        return response()->json(['message' => __('This attempt has ended.')], 422); // D-04
    }

    $data = $request->validated();

    Answer::updateOrCreate(
        ['attempt_id' => $attempt->id, 'question_id' => $data['question_id']],
        collect($data)->only(['selected_option_id', 'answer_text'])->all()
    );

    return response()->json(['saved' => true, 'remaining_seconds' => $attempt->remainingSeconds()]);
}
```

### Pattern 4: Idempotent `updateOrCreate` autosave keyed on the composite unique (TAK-03)

**What:** `Answer::updateOrCreate(['attempt_id' => ..., 'question_id' => ...], [...])` — matches Phase 1's `unique(attempt_id, question_id)` exactly, so repeated autosave calls for the same question always update the same row rather than erroring or duplicating.
**When to use:** Any "save the latest value for this key" flow where the key already has a DB unique constraint. [CITED: laravel.com/docs/11.x/eloquent#upserts — "the `updateOrCreate` method persists the model, so there's no need to manually call the `save` method"]
**Trade-offs:** None significant at this scale — a single `updateOrCreate` per answer is one `SELECT` + one `UPDATE`/`INSERT`, fine for "dozens of questions per attempt."

```php
// app/Http/Requests/Student/AnswerRequest.php
// FormRequest handles SHAPE validation only; ownership/deadline are the
// controller's job (Pattern 3) — mirrors this codebase's existing
// UpdateExamRequest/StoreQuestionRequest split (inline authorize() for the
// resource-specific mutation gate, Policy+authorize() in the controller
// for cross-cutting ownership — see 03-03-SUMMARY.md's established pattern).
namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership enforced by AttemptPolicy in the controller
    }

    public function rules(): array
    {
        $attempt = $this->route('attempt');

        return [
            'question_id' => [
                'required', 'integer',
                Rule::exists('questions', 'id')->where('exam_id', $attempt->exam_id),
            ],
            // Never accept is_correct/score/attempt_id from the client (T-01-02-MA analog).
            'selected_option_id' => [
                'nullable', 'integer',
                Rule::exists('options', 'id')->where('question_id', $this->input('question_id')),
            ],
            'answer_text' => ['nullable', 'string'],
        ];
    }
}
```

### Pattern 5: Explicit array view-model — never a raw model into `@json()`/`x-data` (TAK-06)

**What:** Build the take-page's question/option payload as a plain PHP array using an explicit column whitelist (`->get(['id', 'question_id', 'body'])`), then `json_encode()` *that array* into Alpine's `x-data`. Never pass an Eloquent `Question`/`Option` collection (or `$model->toJson()`) directly — even with `$hidden` set, a future added column or an `@json($model)` call elsewhere would silently start leaking.
**When to use:** Every student-facing render/response reachable while `attempt->status === 'in_progress'`. [CITED: laravel.com/docs/11.x/eloquent-serialization#hiding-attributes-from-json — confirms `$hidden` only affects `toArray()`/`toJson()` serialization, not raw attribute access or a differently-scoped query]

```php
// AttemptController@show
use App\Enums\QuestionType;

public function show(Request $request, Attempt $attempt)
{
    $this->authorize('view', $attempt);

    $attempt->loadMissing('exam');
    $attempt->finalizeIfExpired();

    if ($attempt->status !== 'in_progress') {
        return redirect()->route('student.attempts.submitted', $attempt);
    }

    $questions = $attempt->exam->questions()
        ->orderBy('position')
        ->get(['id', 'exam_id', 'type', 'body', 'position'])
        ->map(fn ($q) => [
            'id' => $q->id,
            'type' => $q->type->value,
            'body' => $q->body,
            // Explicit column whitelist — is_correct is NEVER selected here.
            'options' => $q->type === QuestionType::Mcq
                ? $q->options()->orderBy('position')->get(['id', 'question_id', 'body'])
                : [],
        ]);

    // Rehydrate previously-saved answers (TAK-03 refresh/disconnect safety).
    $savedAnswers = $attempt->answers()->get()->keyBy('question_id');

    return view('student.attempts.show', [
        'attempt' => $attempt,
        'questions' => $questions,
        'savedAnswers' => $savedAnswers,
        'remainingSeconds' => $attempt->remainingSeconds(),
    ]);
}
```

```blade
{{-- resources/views/student/attempts/show.blade.php --}}
{{-- Source: laraveldaily.com/lesson/alpine-js/countdown-timer-x-init pattern (CITED,
     cross-verified against multiple independent Alpine countdown examples),
     combined with this project's already-global window.axios (VERIFIED: codebase) --}}
<div
    x-data="attemptTimer({{ $remainingSeconds }}, '{{ route('student.attempts.submit', $attempt) }}')"
    x-init="start()"
>
    <p class="font-bold" x-text="display"></p>
    {{-- @json($questions) here is safe: $questions is the explicit array
         built server-side above, never a raw Eloquent collection --}}
    @foreach ($questions as $question)
        {{-- radio group / textarea per question, @change posts to the
             answer endpoint via window.axios.post(...) --}}
    @endforeach
</div>

<script>
function attemptTimer(remaining, submitUrl) {
    return {
        remaining,
        display: '',
        start() {
            this.tick();
            setInterval(() => this.tick(), 1000);
        },
        tick() {
            if (this.remaining <= 0) {
                this.display = '00:00';
                window.axios.post(submitUrl); // client auto-submit (D-05, cosmetic trigger)
                return;
            }
            this.remaining--;
            const m = Math.floor(this.remaining / 60).toString().padStart(2, '0');
            const s = (this.remaining % 60).toString().padStart(2, '0');
            this.display = `${m}:${s}`;
        },
    };
}
</script>
```

### Anti-Patterns to Avoid

- **Reading `time_remaining`/`deadline` from the request body on submit or autosave:** the only server-side deadline source is `Attempt::deadline()`, recomputed from `started_at` + `exam.duration_minutes`. A client-supplied value must never influence the accept/reject decision (CWE-602).
- **Passing `$question->load('options')` (raw Eloquent) into `@json()` or Alpine `x-data`:** even a correctly-`$hidden` model can leak the moment a teammate adds `->toJson()` somewhere else or a new sensitive column is added without updating `$hidden`. Build the explicit array every time (Pattern 5).
- **A single "submit everything at the end" form with no incremental autosave:** directly reintroduces Pitfall 5 from PITFALLS.md — a crashed tab or failed final request loses every answer. Each `answer()` call must persist independently of `submit()`.
- **Using a queued job or `Schedule::command(...)->everyMinute()` as the *only* expiry mechanism:** depends on a worker/scheduler being alive; still requires the on-write server check regardless (per PROJECT.md's "What NOT to Use" table) — adds infrastructure risk without removing any.
- **Checking `attempt->status` without `lockForUpdate()` inside the transaction on `submit()`:** a plain `if ($attempt->status === 'in_progress') { ... }` outside a lock still races under a genuine double-click/two-tab scenario; the check-then-act must happen against a row-locked read (Pattern 3's `finalizeIfExpired()` demonstrates the correct shape — `submit()` should follow the identical lock-then-check-then-update structure).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|--------------|-----|
| Detecting a duplicate-attempt race | A custom `Cache::lock()`/mutex wrapper around the start endpoint | `firstOrCreate` + catch `QueryException` (error 1062) against the existing DB unique constraint | The constraint already provides the atomicity guarantee for free; a cache lock adds a dependency and a false sense of safety if the cache store itself isn't strictly consistent |
| "Is this attempt still open" logic | Duplicating `now() >= deadline` checks inline in three different controller methods | One `Attempt::finalizeIfExpired()` method, called first in every action | A duplicated inline check is exactly how Pitfall 1 (client-only enforcement) sneaks back in — one method, one place to audit, matches this codebase's established "single shared predicate" pattern (`Exam::scopeVisibleTo`) |
| Preventing answer-key leakage | A custom `OptionResource` API Resource class, or per-request `makeHidden()` calls | A plain array built in the controller with an explicit `->get(['id','question_id','body'])` column whitelist | At this project's scale (Blade views, no JSON API layer per PROJECT.md), a full API Resource class is more machinery than the problem needs; the explicit array is one line and impossible to accidentally widen |
| Countdown timer synchronization | A custom polling/websocket sync between server clock and client clock | Seed the Alpine `x-data` with server-computed `remaining_seconds` once at page load, `setInterval` locally from there | The countdown is explicitly cosmetic (Pattern 2) — sub-second server sync is unnecessary complexity; the *enforcement* re-checks the real deadline on every write regardless of client drift |

**Key insight:** Every "Don't Hand-Roll" item above has the same shape — the temptation is to build bespoke concurrency/security machinery, when the actual fix is routing through a mechanism (a DB constraint, a single shared method, an explicit whitelist) that already exists in this codebase or the framework and was specifically placed there in Phase 1/3 for this purpose.

## Common Pitfalls

### Pitfall 1: Deadline check omitted on the autosave endpoint specifically
**What goes wrong:** The team correctly guards `submit()` with a deadline check but forgets `answer()` — a student can keep autosaving answers indefinitely past the deadline even though the final submit is blocked, and depending on how Phase 5 grades, those late-saved answers may still count.
**Why it happens:** `submit()` feels like "the" timing-critical endpoint because it's the one that finalizes; autosave feels like "just persistence."
**How to avoid:** Route `answer()` through the exact same `finalizeIfExpired()` + status check as `submit()` (Pattern 3) — there should be no code path that writes to `answers` without passing this gate.
**Warning signs:** `AnswerController`/`answer()` method has no `finalizeIfExpired()` or deadline check at all; a manual test (travel past the deadline, then POST an answer) still returns 200 and creates a row.

### Pitfall 2: Double-start race not actually exercised by tests
**What goes wrong:** A test asserts "starting twice resumes the same attempt" but only calls the endpoint sequentially in a single PHP process — this never exercises the `QueryException`-catch branch, so a bug in that branch (e.g. it doesn't `firstOrFail()` correctly, or re-throws) ships silently.
**Why it happens:** True concurrency is hard to simulate in PHPUnit's synchronous single-process test runner.
**How to avoid:** Directly unit-test the catch branch by pre-inserting a competing `Attempt` row (simulating "the other request already won") immediately before calling the controller action, and assert the response resolves to that existing row without erroring and without creating a second row (`Attempt::count() === 1`). This exercises the actual catch path even without real OS-level concurrency.
**Warning signs:** The only start-related test is "student can start an exam" with no second/competing-row scenario at all.

### Pitfall 3: `is_correct` leaked via the Alpine `x-data` JSON blob, not the visible HTML
**What goes wrong:** A page-source/Ctrl+F check for `is_correct` in the rendered `<option>` labels passes (the labels only show option *text*), but the developer console's Network tab or "View Page Source" on the full HTML still shows `is_correct` because the *entire* `Question` model (with its `options` relation) was `@json()`'d into a hidden `x-data` attribute for "convenience."
**Why it happens:** It's easy to reach for `@json($question->load('options'))` when wiring up Alpine state, since it's the fastest way to get the whole question into JS.
**How to avoid:** Pattern 5 — build the explicit array server-side, `@json()` only that array. A verification test should assert the *raw response body string* never contains the literal substring `is_correct`, not just that the rendered `<label>` text looks correct.
**Warning signs:** `grep -r "is_correct" resources/views/student/` returns nothing (good) but `grep -r "@json(\$question" resources/views/student/` or `->load('options')` on a variable later passed to `@json()` returns a hit (bad — the whitelisting happened too late or not at all).

### Pitfall 4: `finalizeIfExpired()` called without `exam` eager-loaded, causing an N+1 or a null-property error
**What goes wrong:** `deadline()` reads `$this->exam->duration_minutes`; if the `Attempt` was fetched via plain route-model binding (`Attempt $attempt` in the controller signature) without `->loadMissing('exam')`, every call triggers a fresh query, and on a page rendering many questions/answers this compounds badly — or, in a context where `exam` was somehow not resolvable, throws.
**Why it happens:** Route-model binding gives you the bare `Attempt` row; the `exam` relation is not automatically eager-loaded.
**How to avoid:** Every controller method that calls `finalizeIfExpired()`, `deadline()`, or `remainingSeconds()` must first `$attempt->loadMissing('exam')` (or bind with `Route::get(...)->with(...)` style eager-loading, or use `Attempt::with('exam')->findOrFail(...)` instead of implicit binding).
**Warning signs:** Telescope/query-log shows a repeated `select * from exams where id = ?` for the same attempt within one request; a "N+1 queries" pitfall already flagged generically in PITFALLS.md's Performance Traps, specific instance for this phase.

### Pitfall 5: Testing the countdown display value instead of the enforcement
**What goes wrong:** The verification/UAT for TAK-02 checks that the Alpine countdown *displays* the right number of minutes, and stops there — declaring "timer enforcement" done — without ever testing that a late POST to `answer()`/`submit()` is actually rejected server-side.
**Why it happens:** The countdown is the visible, demoable part; the server rejection is invisible unless specifically exercised.
**How to avoid:** The Validation Architecture below requires an explicit `$this->travelTo($attempt->started_at->addMinutes($duration + 1))` + late-POST test as a hard requirement for TAK-02, independent of any UI/display test.
**Warning signs:** grep the test suite for `travelTo`/`travel(` in `tests/Feature/Student/Attempt*Test.php` — if it's absent, only the display was tested, not the enforcement (this is literally PITFALLS.md's "Looks Done But Isn't" checklist item #1).

## Code Examples

### AttemptPolicy (mirrors the Phase-3 shared-scope pattern)

```php
// Source: pattern established in app/Policies/ExamPolicy.php (03-03-SUMMARY.md) —
// "single shared query scope as the one source of truth for a visibility
// predicate, called from both a list endpoint and a Policy" [VERIFIED: codebase]
namespace App\Policies;

use App\Models\Attempt;
use App\Models\Exam;
use App\Models\User;

class AttemptPolicy
{
    /**
     * Own attempt AND the underlying exam is still takeable (D-08).
     * Delegates to Exam::visibleTo() — never re-derive is_published/
     * classroom_id conditions here, matching ExamPolicy::takeable().
     */
    public function view(User $user, Attempt $attempt): bool
    {
        return $this->ownAndTakeable($user, $attempt);
    }

    public function update(User $user, Attempt $attempt): bool
    {
        return $this->ownAndTakeable($user, $attempt);
    }

    private function ownAndTakeable(User $user, Attempt $attempt): bool
    {
        return $attempt->user_id === $user->id
            && Exam::visibleTo($user)->whereKey($attempt->exam_id)->exists();
    }
}
```

### Transactional, idempotent submit (double-submit / two-tab race safe)

```php
// Source: laravel.com/docs/11.x/database#handling-deadlocks (DB::transaction retry arg, VERIFIED)
//         laravel.com/docs/11.x/queries#pessimistic-locking (lockForUpdate, VERIFIED)
use Illuminate\Support\Facades\DB;

public function submit(Request $request, Attempt $attempt)
{
    $this->authorize('update', $attempt);
    $attempt->loadMissing('exam');

    DB::transaction(function () use ($attempt) {
        $locked = Attempt::with('exam')->whereKey($attempt->id)->lockForUpdate()->first();

        if ($locked->status !== 'in_progress') {
            return; // already submitted/graded — idempotent no-op, not an error
        }

        $locked->update([
            'status' => 'submitted',
            'submitted_at' => now()->lessThan($locked->deadline())
                ? now()
                : $locked->deadline(), // never record a submitted_at past the deadline
        ]);
    }, 3); // retry up to 3 times on a genuine deadlock

    return redirect()->route('student.attempts.submitted', $attempt);
}
```

### Routes

```php
// routes/student.php additions
use App\Http\Controllers\Student\AttemptController;

Route::post('exams/{exam}/attempts', [AttemptController::class, 'store'])->name('attempts.store');
Route::get('attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
Route::post('attempts/{attempt}/answers', [AttemptController::class, 'answer'])->name('attempts.answer');
Route::post('attempts/{attempt}/submit', [AttemptController::class, 'submit'])->name('attempts.submit');
Route::get('attempts/{attempt}/submitted', [AttemptController::class, 'submitted'])->name('attempts.submitted');
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|-------------------|---------------|--------|
| `Kernel.php`-based scheduling for an auto-submit sweep | `routes/console.php` `Schedule::` facade (Laravel 11) | Laravel 11.0 skeleton redesign | Not used this phase anyway (D-05 rejects a sweep), but relevant if a future phase/v2 revisits the "live monitoring" idea (V2-05) |
| `$casts` property for enum/date casting | `casts(): array` method form | Laravel 11 convention (both still work; method form is current) | Already the pattern this codebase uses on `Attempt`/`Answer`/`Question`/`Option` (Phase 1) — this phase's new `Attempt` methods (`deadline()` etc.) are plain methods, not casts, so no change needed |
| `Carbon::setTestNow()` for time-travel tests | `$this->travel()`/`$this->travelTo()`/`$this->freezeTime()` PHPUnit/Pest helpers | Available since Laravel 8+, documented in 11.x `mocking.md` | Recommended for this phase's TAK-02/TAK-04 tests — automatic reset after each test avoids the manual-cleanup footgun of `Carbon::setTestNow()` |

**Deprecated/outdated:** None specific to this phase's APIs — `DB::transaction`, `lockForUpdate`, `firstOrCreate`/`updateOrCreate`, `$hidden`/`$visible`, and the time-travel test helpers are all current, stable Laravel 11.x APIs with no pending deprecation noted in the fetched docs.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|-----------------|
| A1 | `createOrFirst` (a single-query atomic upsert helper referenced in some community write-ups as "the Laravel 10.22+ fix for `firstOrCreate` races") is **not** documented on the official `laravel.com/docs/11.x/eloquent` "Retrieving or Creating Models" page fetched in this session — only `firstOrCreate`/`firstOrNew`/`updateOrCreate` are documented there. This research therefore designs around the catch-`QueryException` pattern rather than `createOrFirst`. | Pattern 1 | LOW — if `createOrFirst` does exist on 11.55.0 (unconfirmed either way; docs are sometimes incomplete), the catch-based approach still works correctly, just with one extra `try/catch`; no correctness risk, only a possible simplification opportunity for the planner to verify with `php artisan tinker` before committing to the pattern |
| A2 | The Alpine countdown + autosave client-side composite pattern (Pattern 5's `<script>` block) is synthesized from cross-verified community sources (Laravel Daily, DEV Community, CodePen examples), not an official Alpine.js or Laravel doc page — no single authoritative source covers "Alpine + Laravel autosave + countdown" as one integrated pattern. | Architecture Patterns, Code Examples | LOW — the underlying primitives (`x-data`, `x-init`, `setInterval`, `window.axios.post`) are each independently well-documented; the composition risk is only around exact event-naming/debounce ergonomics, which the planner/executor has discretion over per CONTEXT.md |
| A3 | Unpublishing an exam (`is_published = false`) while a student has an `in_progress` `Attempt` will cause `AttemptPolicy` (per D-08's mandated re-check of `Exam::visibleTo()` on every touch) to 403 that student out of continuing/submitting their own in-progress attempt, since the exam is no longer "takeable." This is a direct, not-yet-resolved interaction between D-08 (locked) and the existing Lecturer `unpublish()` action (Phase 2) — not fixed by this research, only surfaced. | Open Questions #1 | MEDIUM — if a lecturer unpublishes an exam mid-exam-period (unusual but not prevented by any existing guard), an in-progress student would be locked out; the correct fix (if desired) is a v2/Phase-5 concern, not this phase's, since CONTEXT.md's D-08 is a locked decision this research must implement as written |

**If this table is empty:** N/A — see rows above.

## Open Questions (RESOLVED)

**RESOLVED (Q1):** Implement D-08 literally — `AttemptPolicy` re-checks `Exam::visibleTo()` on every touch (security-conservative locked default); the mid-attempt unpublish→lockout behavior is intended and surfaced in the plans/PR notes. Baked into 04-02 `AttemptPolicy`.
**RESOLVED (Q2):** Confirmation copy is generic ("Your exam has been submitted"), not distinguishing manual vs auto-submit — per D-09 (Claude's Discretion, no locked requirement). Baked into 04-04 confirmation view.

1. **Should `AttemptPolicy` re-check `Exam::visibleTo()` on every touch of an already-started attempt, or only at start time?**
   - What we know: D-08 (locked) explicitly says "own attempt AND exam is takeable... enforce via `$this->authorize()` on every attempt route." The straightforward implementation re-derives `Exam::visibleTo()` on every `view`/`update` policy check, which means unpublishing an exam or removing a classroom assignment mid-attempt would retroactively lock out an in-progress student (see Assumption A3).
   - What's unclear: Whether this retroactive lockout is an intended safety property (a lecturer pulling a bad exam should stop it immediately, even mid-attempt) or an unintended edge case nobody has considered.
   - Recommendation: Implement D-08 literally as written (re-check on every touch) — it is the locked, security-conservative default, and the scenario (lecturer unpublishing a live exam) is rare and arguably *should* halt access. Flag this behavior explicitly in the plan/PR description so it's a documented, reviewable choice rather than a silent side effect. No code change needed to "fix" it unless a future phase's requirements say otherwise.

2. **Does the confirmation page (D-09, "Your exam has been submitted") need to distinguish manual-submit from auto-submit-on-expiry in its copy?**
   - What we know: D-09 says the confirmation page is "NOT a score page" and gives no further copy requirement; D-05 describes both a client auto-submit and a server lazy-finalize path that both land on the same `status = submitted` state.
   - What's unclear: Whether a student who returns to find their attempt was already auto-finalized (they never clicked Submit) should see different messaging than one who explicitly clicked Submit.
   - Recommendation: Claude's Discretion per CONTEXT.md ("confirmation page copy — planner/executor choice"). A single generic confirmation message is sufficient for TAK-04's requirement text ("the attempt auto-submits") — no distinct copy path is required, but the executor may add one as UX polish without violating any locked decision.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|-------------|-----------|---------|----------|
| PHP | Laravel runtime | ✓ | 8.2+ (per PROJECT.md/CLAUDE.md, already established in Phases 1–3) | — |
| MySQL (via Herd, `yp-student-exam`) | `attempts`/`answers` persistence, live-DB feature tests | ✓ | 8.x (already configured, confirmed by Phase 1's `DomainSchemaTest` running against it) | — |
| `laravel/framework` | `DB::transaction`, `lockForUpdate`, Carbon, test time-travel helpers | ✓ | 11.55.0 (confirmed via `composer show`) | — |
| Alpine.js + `window.axios` | Countdown + autosave client code | ✓ | as scaffolded (`resources/js/app.js`/`bootstrap.js`, confirmed present) | — |
| Composer/npm registries | No new packages needed this phase | N/A | — | — |

**Missing dependencies with no fallback:** None.
**Missing dependencies with fallback:** None — everything this phase needs is already present and verified in the codebase.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit (bundled with Laravel 11.55.0), `tests/Feature/Student/*Test.php` convention already established (Phase 3's `ExamIndexTest`/`ExamAccessTest`) |
| Config file | `phpunit.xml` — SQLite in-memory override lines are commented out, confirming tests run against the live MySQL `yp-student-exam` connection configured in `.env` [VERIFIED: codebase] |
| Quick run command | `php artisan test --filter=Attempt` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|---------------------|--------------|
| TAK-01 | Starting an assigned exam creates a single `in_progress` attempt | Feature (`RefreshDatabase`, live MySQL) | `php artisan test --filter=test_starting_an_assigned_exam_creates_an_in_progress_attempt` | ❌ Wave 0 — `tests/Feature/Student/AttemptStartTest.php` |
| TAK-01 | Starting the same exam twice resumes the existing attempt (same `started_at`) | Feature | `php artisan test --filter=test_starting_the_same_exam_twice_resumes_the_existing_attempt` | ❌ Wave 0 — same file |
| TAK-05 | A pre-existing competing `Attempt` row (simulated concurrent winner) is detected via the `QueryException` catch path, and no duplicate row is created | Feature (pre-insert + call controller, assert `Attempt::count() === 1`) | `php artisan test --filter=test_a_concurrent_double_start_does_not_create_a_duplicate_attempt` | ❌ Wave 0 — same file |
| TAK-05 | A student cannot start a second attempt after their first is `submitted` | Feature | `php artisan test --filter=test_a_student_cannot_start_a_second_attempt_after_submitting` | ❌ Wave 0 — same file |
| TAK-02 | An answer POST *before* the deadline is persisted | Feature | `php artisan test --filter=test_an_answer_saved_before_the_deadline_is_persisted` | ❌ Wave 0 — `tests/Feature/Student/AttemptAnswerTest.php` |
| TAK-02 | An answer POST *after* `$this->travelTo($attempt->started_at->addMinutes($duration + 1))` is rejected (422) and not persisted | Feature (time-travel) | `php artisan test --filter=test_an_answer_after_the_deadline_is_rejected` | ❌ Wave 0 — same file |
| TAK-02 | `remaining_seconds` reflects elapsed time after `$this->travel(N)->minutes()` | Feature (time-travel) | `php artisan test --filter=test_remaining_seconds_reflects_elapsed_time` | ❌ Wave 0 — `tests/Feature/Student/AttemptShowTest.php` |
| TAK-03 | Autosaving an answer and reloading the take page shows the previously-saved selection | Feature | `php artisan test --filter=test_autosave_persists_and_survives_reload` | ❌ Wave 0 — `AttemptAnswerTest.php` |
| TAK-03 | Repeated autosave POSTs for the same question upsert (only one `Answer` row, latest value wins) | Feature | `php artisan test --filter=test_repeated_autosave_upserts_the_same_answer_row` | ❌ Wave 0 — same file |
| TAK-04 | Visiting (`GET show`) an `in_progress` attempt after `$this->travelTo()` past the deadline finalizes it to `submitted`, `submitted_at = deadline` | Feature (time-travel) | `php artisan test --filter=test_visiting_an_expired_attempt_finalizes_it_to_submitted` | ❌ Wave 0 — `AttemptShowTest.php` |
| TAK-04 | An expired `in_progress` attempt rejects further `answer()` writes (D-04 restated for the expiry-specific path) | Feature (time-travel) | `php artisan test --filter=test_an_expired_attempt_rejects_answer_writes` | ❌ Wave 0 — `AttemptAnswerTest.php` |
| TAK-06 | The take page's raw response body never contains the literal substring `is_correct` for a student with a mix of correct/incorrect options | Feature (`assertDontSee('is_correct')` on the full response, not just visible text) | `php artisan test --filter=test_the_take_page_never_exposes_is_correct` | ❌ Wave 0 — `AttemptShowTest.php` |
| TAK-06 | The AttemptPolicy blocks another student's attempt (IDOR analog to Phase 3's `ExamAccessTest`) | Feature | `php artisan test --filter=test_a_student_cannot_view_another_students_attempt` | ❌ Wave 0 — `AttemptPolicyTest.php` |

### Sampling Rate
- **Per task commit:** `php artisan test --filter=Attempt`
- **Per wave merge:** `php artisan test` (full suite — must stay green against Phases 1–3's existing 135+ tests, matching the project's established zero-regression bar)
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Student/AttemptStartTest.php` — covers TAK-01, TAK-05 (start/resume/block/race)
- [ ] `tests/Feature/Student/AttemptShowTest.php` — covers TAK-02 (remaining_seconds), TAK-04 (lazy finalize on GET), TAK-06 (no leakage)
- [ ] `tests/Feature/Student/AttemptAnswerTest.php` — covers TAK-02 (deadline gate on write), TAK-03 (autosave + rehydrate), TAK-04 (expired write rejection)
- [ ] `tests/Feature/Student/AttemptPolicyTest.php` — covers D-08/RBAC-05 IDOR checks specific to `Attempt` (own-attempt + exam-takeable double condition)
- [ ] No new factories strictly required — `Attempt`/`Answer` have no factories yet (only `ClassroomFactory`/`ExamFactory`/`QuestionFactory`/`OptionFactory` exist per Phase 1/2); an `AttemptFactory` (and possibly `AnswerFactory`) should be added in Wave 0 to keep the above tests from hand-building attempt rows repeatedly — this mirrors the existing `ClassroomFactory` pattern from Phase 1.
- [ ] Framework install: none — PHPUnit and Laravel's test helpers (`travel`, `travelTo`, `freezeTime`, `RefreshDatabase`) are already present and already used by Phase 1/3's tests.

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|----------------|---------|--------------------|
| V2 Authentication | No (net-new) | Already provided by Breeze scaffold; unchanged this phase |
| V3 Session Management | No (net-new) | Already provided by Laravel's session driver; unchanged this phase |
| V4 Access Control | **Yes** | `AttemptPolicy` (own-attempt + `Exam::visibleTo()` reuse) + `$this->authorize()` as the first statement in every controller action (V4.1 general access control, V4.2 operation-level authorization — direct extension of the Phase-3 `ExamPolicy` pattern) |
| V5 Input Validation | **Yes** | `AnswerRequest` FormRequest: `question_id` must `exists` scoped to the attempt's exam; `selected_option_id` must `exists` scoped to that specific `question_id`; `answer_text` is a plain string; `is_correct`/`score`/`attempt_id` are never accepted from request input — `attempt_id` comes from the route binding, `is_correct`/`score` are never written by this phase at all (grading is Phase 5) |
| V6 Cryptography | No | Not applicable — no new cryptographic operations this phase |
| V11 Business Logic (informal, ASVS 4.0 category not in the L1 subset but directly relevant) | **Yes** (noted for completeness) | The single-attempt/single-answer state machine (`in_progress → submitted`) and the deadline gate are exactly the "business logic enforced server-side, not just client-side" property ASVS's business-logic controls target; covered concretely by V4 (access to the transition) + the deadline-gate pattern above rather than a separate control |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|-----------------------|
| Client-side timer bypass (CWE-602) — student pauses/edits the countdown or replays a submit request after the visual deadline | Tampering | Server recomputes `deadline` from `started_at`+`duration_minutes` on every write; client-supplied timing data is never read (Pattern 2, D-03/D-04) |
| IDOR on `attempts` (CWE-863) — a student guesses/increments an attempt ID in the URL | Elevation of Privilege / Information Disclosure | `AttemptPolicy::view`/`update` checked via `$this->authorize()` before any attempt data is loaded/rendered (Pattern's `ownAndTakeable`) — mirrors Phase 3's `ExamAccessTest` IDOR-check pattern exactly |
| Double-start / double-submit race (CWE-362) — two concurrent requests both pass an app-level "does an attempt exist" check before either commits | Tampering (data integrity) | DB `unique(exam_id, user_id)` constraint (Phase 1) is the actual atomicity guarantee; app-level `firstOrCreate`+catch and `lockForUpdate`-guarded `submit()` are the graceful-degradation layer on top (Patterns 1 and the transactional submit code example) |
| Answer-key leakage (CWE-200 Information Exposure) — `is_correct` reaching the browser during an in-progress attempt | Information Disclosure | Explicit column-whitelisted view-model, never a raw model into `@json()`/`x-data` (Pattern 5) |
| Mass assignment on the autosave/submit endpoints (CWE-915) — a forged `is_correct`/`score`/`attempt_id` field in the POST body | Tampering | `AnswerRequest::rules()` only validates `question_id`/`selected_option_id`/`answer_text`; the controller's `updateOrCreate` second argument only ever includes those two answer-content fields, never `is_correct`/`score` (which this phase never writes at all) |

## Sources

### Primary (HIGH confidence — official docs fetched and read directly this session)
- [Database: Getting Started — Transactions | Laravel 11.x](https://laravel.com/docs/11.x/database#database-transactions) — `DB::transaction()` signature, deadlock-retry second argument
- [Database: Query Builder — Pessimistic Locking | Laravel 11.x](https://laravel.com/docs/11.x/queries#pessimistic-locking) — `sharedLock()`/`lockForUpdate()` exact syntax and transaction-wrapping recommendation
- [Eloquent: Getting Started — Retrieving or Creating Models | Laravel 11.x](https://laravel.com/docs/11.x/eloquent#retrieving-or-creating-models) — `firstOrCreate`/`firstOrNew`/`updateOrCreate` exact signatures; confirms `createOrFirst` is NOT documented at this version (Assumption A1)
- [Mocking — Interacting With Time | Laravel 11.x](https://laravel.com/docs/11.x/mocking#interacting-with-time) — `travel()`, `travelTo()`, `travelBack()`, `freezeTime()`, `freezeSecond()` exact API and closure forms
- [Eloquent: Serialization — Hiding Attributes From JSON | Laravel 11.x](https://laravel.com/docs/11.x/eloquent-serialization#hiding-attributes-from-json) — `$hidden`/`$visible`/`makeVisible`/`makeHidden`/`setVisible`/`setHidden` exact semantics
- Direct codebase reads: `app/Models/Attempt.php`, `app/Models/Answer.php`, `app/Models/Exam.php`, `app/Models/Question.php`, `app/Models/Option.php`, `app/Policies/ExamPolicy.php`, `app/Http/Controllers/Student/ExamController.php`, `app/Http/Requests/Lecturer/UpdateExamRequest.php`, `app/Http/Requests/Lecturer/StoreQuestionRequest.php`, `app/Http/Controllers/Lecturer/ExamController.php`, `routes/student.php`, `resources/js/bootstrap.js`, `resources/js/app.js`, `phpunit.xml`, `composer show laravel/framework` — all [VERIFIED: codebase]

### Secondary (MEDIUM confidence — community sources, cross-verified against multiple independent results or corroborating the official docs above)
- QueryException/MySQL-1062/SQLSTATE-23000 catch pattern for `firstOrCreate` races — cross-verified across Laracasts discussions and the `laravel/framework` GitHub issue tracker (issue #27553) [CITED, cross-verified]
- FormRequest `authorize()` + `$this->user()->can()`/route-model-binding interaction — cross-verified across joelclermont.com, trovster.com, erik.cat [CITED, cross-verified]
- Alpine.js countdown timer `x-data`/`x-init`/`setInterval` + custom-event auto-submit pattern — cross-verified across Laravel Daily's official Alpine lesson, DEV Community, and multiple CodePen examples [CITED, cross-verified]
- `.planning/research/PITFALLS.md` and `.planning/research/ARCHITECTURE.md` (this project's own prior research phase, dated 2026-07-15) — treated as MEDIUM-confidence project-internal research, re-verified against official 11.x docs in this session where it made specific API claims

### Tertiary (LOW confidence — single-source or unverifiable this session)
- The claim that `createOrFirst` "resolved" `firstOrCreate` races "since Laravel 10.22.0" (from a single Medium article surfaced by WebSearch) — explicitly NOT corroborated by the official Laravel 11.x docs fetched in this session (see Assumption A1); do not rely on this method without independent verification via `php artisan tinker` or a fresh docs check at implementation time

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — every load-bearing API (`DB::transaction`, `lockForUpdate`, `firstOrCreate`/`updateOrCreate`, `$hidden`/`$visible`, `travel`/`travelTo`/`freezeTime`) verified directly against the official `laravel.com/docs/11.x` pages in this session, plus direct codebase reads confirming installed versions and existing conventions
- Architecture: HIGH — extends the exact shared-scope + Policy pattern already proven correct and tested in Phase 3 (`ExamPolicy`/`scopeVisibleTo`), applied to the already-fixed Phase 1 schema; no new architectural invention required
- Pitfalls: HIGH — directly sourced from this project's own `.planning/research/PITFALLS.md` (Pitfalls 1, 3, 4, 5, 6 all converge on this phase per that document's own "Pitfall-to-Phase Mapping" table), cross-checked against CWE classifications (CWE-602, CWE-862/863, CWE-362, CWE-915) which are MITRE-authoritative
- Client (Alpine countdown/autosave) pattern: MEDIUM — no single official spec for this exact composite pattern; cross-verified across multiple independent community sources, and the underlying primitives (`x-data`, `setInterval`, `window.axios`) are each independently well-documented and already present/working in this codebase

**Research date:** 2026-07-16
**Valid until:** 2026-08-15 (30 days — stable, versioned Laravel 11.x framework APIs; re-verify sooner only if the project upgrades past Laravel 11.x mid-development)

---
*Research for: Online examination and student management portal (Laravel 11 + Breeze + MySQL) — Phase 4: Attempt-Taking*
*Researched: 2026-07-16*

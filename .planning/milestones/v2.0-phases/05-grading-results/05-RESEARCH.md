# Phase 5: Grading & Results - Research

**Researched:** 2026-07-16
**Domain:** Server-side auto/manual grading and gated results, Laravel 11 + Breeze + MySQL (no new packages)
**Confidence:** HIGH

## Summary

Phase 5 is pure application logic layered on a schema that is already final (Phase 1) and a finalize chokepoint that is already hardened (Phase 4). There is nothing to install and nothing to research at the package level — every mechanism (Eloquent aggregate queries, FormRequest bounded-numeric validation, Policy-gated views) is a documented, HIGH-confidence Laravel-native pattern already in use elsewhere in this codebase. The work is entirely about **where** to hook grading into `Attempt::lockAndFinalize()` without breaking its lock-then-check-then-update concurrency guarantee, and about getting the auto-grading edge-case matrix (unanswered MCQ, unanswered open-text, missing correct-option data) right on the first pass, since PITFALLS.md flags this exact matrix as the phase's highest-risk surface.

The single most important structural decision is **where the `AttemptGrader` hook lives**. `Attempt::lockAndFinalize()` (04-04) is the one and only place `status` ever flips to `submitted`, already wrapped in `DB::transaction()` + `lockForUpdate()`. Calling `AttemptGrader` from inside that same transaction, guarded by the existing `if ($finalized)` branch, grades MCQ answers and evaluates the `submitted → graded` transition atomically with the status flip — there is no window where a reader can observe `status = submitted` with an ungraded MCQ answer. This is the recommended hook point (over calling it after `finalize()`/`finalizeIfExpired()` return `true` at each of the 4 controller call sites), because it requires zero controller changes and inherits the transaction's row lock for free.

The second finding worth flagging explicitly: `AttemptPolicy::view` (Phase 4) is `own attempt AND Exam::visibleTo($user)` — correct for the *in-progress take flow* (a student shouldn't resume an exam that got unpublished mid-attempt) but **wrong for the post-submission results flow**. If a lecturer unpublishes or reassigns the exam after a student has taken and been graded, `Exam::visibleTo()` goes false and the existing policy would incorrectly hide an already-graded result. The student Result view must use ownership-only authorization, not the reused `view`/`update` methods.

**Primary recommendation:** Add `AttemptGrader::gradeAutoGradable()` + `AttemptGrader::syncStatus()` as two focused methods, invoke both from inside `Attempt::lockAndFinalize()`'s existing transaction right after the status-flip `update()`, and call `syncStatus()` again (alone) from the lecturer's grade-save action under its own `lockForUpdate()`. Add a new `AttemptPolicy::viewResult()` (ownership only) for the student Result controller instead of reusing `view`.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| MCQ auto-grading (GRD-01) | API/Backend | Database/Storage | Pure server-side comparison + write, triggered from the existing `Attempt` model transaction; must never reach the browser |
| Open-text manual grading (GRD-02) | API/Backend | — | Lecturer-submitted score, validated server-side (FormRequest), authoritative write |
| submitted→graded transition (GRD-03) | API/Backend | Database/Storage | A computed status transition driven by a DB query (pending-count), not a client signal |
| Score computation (GRD-01/02) | Database/Storage | API/Backend | `SUM()` aggregate query is the correct, N+1-safe mechanism; API layer triggers recompute at the right moments |
| Student result view (GRD-04) | Frontend Server (SSR) | API/Backend | Server-rendered Blade view, gated by a Policy check before any data reaches the template |
| Lecturer results/grading view (GRD-05, GRD-02) | Frontend Server (SSR) | API/Backend | Server-rendered Blade views + POST/PATCH grading forms, role-gated only (no per-lecturer ownership, matching Phase 2/3 precedent) |

## Project Constraints (from CLAUDE.md)

`.claude/CLAUDE.md` aggregates PROJECT.md/STACK.md/CONVENTIONS.md/ARCHITECTURE.md content plus workflow enforcement. Directives applicable to this phase:

- **No new Composer packages** for RBAC, timers, or grading — confirmed again for Phase 5 (grading is plain Eloquent + a Service class).
- **MySQL only for tests** — do not introduce SQLite; `phpunit.xml` has SQLite env vars commented out, meaning the test suite already runs against the real configured MySQL connection (`yp-student-exam`). Every Phase 5 feature test must be written and run against that connection (`RefreshDatabase`, no `:memory:`).
- **Native backed enums via `casts()`**, not `$casts` property — `QuestionType` (`Mcq`/`Open`) is already defined this way; grading code must branch on `$question->type === QuestionType::Mcq`, not a raw string.
- **No model observers/events for grading** — ARCHITECTURE.md explicitly forbids a `saving`/`created` Eloquent event on `Answer` for grading, to avoid a hidden side effect re-triggered by autosave. `AttemptGrader` must be an explicit service method call, invoked exactly once at the finalize transition.
- **`$fillable` allowlists + FormRequest `validated()` everywhere** — `score`/`is_correct` must never be mass-assignable from a lecturer's raw POST body beyond what `GradeAnswerRequest::rules()` permits.
- **GSD workflow enforcement** — file changes for this phase go through `/gsd-execute-phase`, not ad hoc edits.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01:** An `AttemptGrader` service (`app/Services/AttemptGrader.php`) with a method (e.g. `gradeAutoGradable(Attempt)`) that, for each MCQ answer, sets `answers.is_correct = (selected_option_id === the question's correct option id)` and `answers.score = is_correct ? question.points : 0`. Unanswered MCQ (no answer row, or null selection) → treated as incorrect / 0, never a crash (research pitfall). Open-text answers are left ungraded (`is_correct`/`score` stay null) for the lecturer.
- **D-02:** Hook the grader into the **Phase-4 finalize path** so MCQ is graded "on submission" (GRD-01): call `AttemptGrader::gradeAutoGradable($attempt)` inside/right after `Attempt::finalize()`/`finalizeIfExpired()` becomes `submitted` (both the manual-submit and lazy-auto-submit paths — the single finalize chokepoint from Phase 4 means one hook covers both). Keep it inside the finalize transaction or immediately after, idempotently.
- **D-03:** After auto-grading, evaluate completeness: if the attempt has **no open-text answers needing grading** (all questions MCQ, or all open-text already graded), transition `status = submitted → graded` and set `attempts.score = Σ answers.score`. If open-text answers remain ungraded, stay `submitted` (result withheld). The transition to `graded` also fires when the lecturer grades the last open-text answer (D-04).
- **D-04:** A lecturer grading UI (under `routes/lecturer.php`, role:lecturer): list submitted attempts (per exam), open one, and grade each **open-text** answer with a score input constrained to `0..question.points`. A `GradeAnswerRequest` validates the score is an integer/decimal in `[0, points]` and the target answer is an open-text answer belonging to the attempt. Saving a grade sets `answers.score` (and `is_correct` may stay null for open-text, or be derived — keep null; score is authoritative). After each save, re-evaluate completeness (D-03): when all open-text answers are graded, flip the attempt to `graded` and compute `attempts.score`.
- **D-05:** **Student result** (`Student\ResultController` / extend the attempts area, role:student, own attempt via AttemptPolicy): shown ONLY when `attempt.status === graded` (GRD-03) — otherwise a "your submission is awaiting grading" message. Displays total score (`attempts.score` out of the exam's total points) and a **per-question breakdown**: each question, the student's answer, correct/incorrect (for MCQ) or the lecturer's score (for open-text), and points awarded.
- **D-06:** **Lecturer results** (`Lecturer\ResultController`, role:lecturer): per exam, a list of student attempts with status + total score; drill into a student's attempt to see the per-question breakdown (and reach the grading UI for ungraded open-text).
- **D-07:** The Phase-4 no-leakage rule applied only DURING taking. In results (exam over), showing the student's own answer + correct/incorrect + awarded score is expected (GRD-04). Keep it minimal: reveal the student's answer and its correctness/score, but do NOT gratuitously expose the full MCQ answer key (the specific correct option text) in the student result, to avoid leaking keys for reused exams — showing a ✓/✗ + score per question satisfies the breakdown requirement without publishing the key.
- **D-08:** `attempts.score` = sum of `answers.score` across all the attempt's answers (MCQ auto + open-text manual); unanswered questions contribute 0. The exam's total possible = Σ `questions.points`. Compute the stored `attempts.score` at the graded transition (recomputable if a grade changes).

### Claude's Discretion

- Exact controller/route names, whether AttemptGrader is a class or invokable, the grading-UI layout, and whether `is_correct` is set for open-text — planner/executor choice, provided D-01..D-08 hold.

### Deferred Ideas (OUT OF SCOPE)

- **Demo seeder (lecturer/students/classes/subjects/sample exam) + README** → Phase 6.
- **Partial-credit MCQ, rubrics, multi-select scoring, exam analytics/statistics** → v2 (out of scope).
- **Regrade history / audit** → not required.

None are user scope-creep — they are the phase boundaries, noted so the planner doesn't pull them forward.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| GRD-01 | Multiple-choice answers are auto-graded on submission | `AttemptGrader::gradeAutoGradable()` hooked into `Attempt::lockAndFinalize()` — see "Hooking Into the Phase-4 Finalize Chokepoint" and "Code Examples" |
| GRD-02 | Lecturer can grade open-text answers, assigning a score up to the question's point value | `GradeAnswerRequest` bounded-numeric validation (`numeric\|min:0\|max:{points}`, confirmed via Laravel 11 docs — see Sources) + `Lecturer\AnswerGradeController` |
| GRD-03 | A Student's final result is shown only after all open-text answers in the attempt are graded | `AttemptGrader::syncStatus()` — pending-count query over `answers` joined to `questions.type = open`, called at both hook points (finalize + every lecturer grade save) |
| GRD-04 | Student can view their own graded result — total score plus a per-question breakdown | New `AttemptPolicy::viewResult()` (ownership-only, NOT `Exam::visibleTo()`) + `Student\ResultController` gated on `status === graded` |
| GRD-05 | Lecturer can view results per exam and per student | `Lecturer\ResultController@index/@show` — role:lecturer gate only, no per-lecturer ownership, matching the established Phase 2/3 "any lecturer" pattern |
</phase_requirements>

## Standard Stack

### Core

No new libraries. Every mechanism below is native to Laravel 11.31 / PHP 8.2, already present in this project's `composer.json`.

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` | 11.55.0 (installed, verified via `php artisan --version`) | Eloquent aggregates (`sum()`), FormRequest validation, Policies | Already the mandated framework; no version bump needed |
| `phpunit/phpunit` | ^11.0.1 (composer.json) | Feature tests against live MySQL | Already the project's test runner |

### Supporting

None. No new Composer packages for grading, completeness checks, or results rendering.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Plain enum-cast `status` column + service-method transition | A state-machine package (`spatie/laravel-model-states`) | Rejected in STACK.md for the identical reason it applies here: 3 linear states (`in_progress`/`submitted`/`graded`) with two simple guard conditions don't warrant transition-guard/side-effect infrastructure |
| Explicit `AttemptGrader` service call | Eloquent model event/observer (`Answer::saving`) | Rejected in ARCHITECTURE.md: turns grading into a hidden side effect of "saving a row," easy to accidentally re-trigger on a lecturer's own autosave-shaped grade edit |
| `$attempt->answers()->sum('score')` live aggregate | Denormalized running total updated on every answer write | Rejected: this project's scale (a handful of classes/students, per STACK.md) doesn't need the write-time-maintained total; computing at the graded transition (D-08) is simpler and avoids a stale-cache bug class |

**Installation:** None required — no `composer require` for this phase.

**Version verification:** `php artisan --version` → `Laravel Framework 11.55.0` (confirmed in this environment, 2026-07-16). `composer.json` pins `"laravel/framework": "^11.31"`, `"phpunit/phpunit": "^11.0.1"`, `"php": "^8.2"` — all already satisfied by the installed environment; no action needed.

## Package Legitimacy Audit

**Not applicable.** This phase introduces zero new external packages — it is built entirely on the existing Laravel 11 / PHPUnit stack already present in `composer.json` from Phases 1–4. No `composer require` command is expected in any Phase 5 plan; if one appears during planning, treat it as scope creep and re-check against this research (the grading domain has no legitimate reason to need a new dependency).

## Architecture Patterns

### System Architecture Diagram

```
                    ┌─────────────────────────────────────────────────────────┐
                    │        Attempt::lockAndFinalize() (Phase 4, unchanged)   │
                    │        DB::transaction + lockForUpdate on attempts row   │
                    │                                                          │
  finalize()  ──┐   │   1. lockForUpdate() re-read                            │
  (manual       ├──►│   2. guard check (status=in_progress && ...)            │
  submit)       │   │   3. update(status=submitted, submitted_at=clamped)     │
                │   │   4. ── PHASE 5 HOOK ──                                 │
  finalizeIf-   ┘   │      AttemptGrader::handleFinalized($locked):           │
  Expired()         │        a. gradeAutoGradable($locked)  ─┐                │
  (lazy expiry,     │           per MCQ question:             │  same        │
  called from       │           - find correct option id      │  transaction,│
  show/answer/      │           - compare to selected_option  │  same row    │
  submitted)        │           - write is_correct + score    │  lock — no   │
                    │        b. syncStatus($locked)          ─┘  commit gap  │
                    │           - pending open-text count?                    │
                    │           - if 0: status=graded, score=Σanswers.score   │
                    │   5. sync caller's in-memory copy (unchanged)           │
                    └─────────────────────────────────────────────────────────┘
                                          │
                                          ▼
        ┌──────────────────────────────────────────────────────────────┐
        │  attempts.status ∈ {submitted, graded}, answers.is_correct/  │
        │  score populated for MCQ, null for ungraded open-text        │
        └──────────────────────────────────────────────────────────────┘
                     │                                    │
                     ▼                                    ▼
   ┌───────────────────────────────┐    ┌──────────────────────────────────────┐
   │ Lecturer\AnswerGradeController │    │ Student\ResultController@show         │
   │ @update (GradeAnswerRequest)   │    │  authorize('viewResult', $attempt)    │
   │  - lockForUpdate(attempt)      │    │  - status !== graded → "awaiting"     │
   │  - save answers.score          │    │  - status === graded → breakdown view │
   │  - AttemptGrader::syncStatus() │    │    (student's answer + ✓/✗ + score,   │
   │  - redirect to Lecturer result │    │     NEVER the correct option text)    │
   └───────────────────────────────┘    └──────────────────────────────────────┘
                     │
                     ▼
        ┌─────────────────────────────────────┐
        │ Lecturer\ResultController@index/show │
        │  role:lecturer only, no ownership     │
        │  gate (matches Phase 2/3 precedent)   │
        └─────────────────────────────────────┘
```

### Recommended Project Structure

```
app/
├── Services/
│   └── AttemptGrader.php                    # NEW — gradeAutoGradable() + syncStatus() + handleFinalized()
├── Policies/
│   └── AttemptPolicy.php                    # MODIFIED — add viewResult() (ownership-only)
├── Http/
│   ├── Requests/Lecturer/
│   │   └── GradeAnswerRequest.php           # NEW
│   └── Controllers/
│       ├── Lecturer/
│       │   ├── ResultController.php         # NEW — index($exam), show($exam, $attempt)
│       │   └── AnswerGradeController.php    # NEW — update($attempt, $answer)
│       └── Student/
│           └── ResultController.php         # NEW — show($attempt)
resources/views/
├── lecturer/results/
│   ├── index.blade.php                      # per-exam attempt list (status + score)
│   └── show.blade.php                       # per-question breakdown + inline grade forms
└── student/results/
    └── show.blade.php                       # gated breakdown ("awaiting grading" OR full result)
routes/
├── lecturer.php                              # MODIFIED — results + grade routes
└── student.php                               # MODIFIED — result route
```

### Pattern 1: Single shared grading+completeness hook inside the existing lock

**What:** `AttemptGrader::handleFinalized(Attempt $attempt)` is called exactly once, from inside `Attempt::lockAndFinalize()`, in the branch where `$finalized` just became `true` — i.e. the identical branch that already writes `status = 'submitted'`.

**When to use:** This is the ONLY place MCQ auto-grading should fire. Both `finalize()` (manual submit) and `finalizeIfExpired()` (lazy auto-submit, called from `show()`/`answer()`/`submitted()`) route through `lockAndFinalize()` — hooking there means zero controller changes and guarantees exactly-once execution (a racing second call sees `status !== 'in_progress'` in the guard and short-circuits before ever reaching the hook).

**Why here over "after `finalize()` returns true" in each controller:** Hooking inside the transaction keeps the status flip and the MCQ grading write atomic — there is no commit-then-grade window where another request could read `status = submitted` with `is_correct`/`score` still null on an MCQ answer. Hooking after the 4 controller call sites return would require duplicating the `if ($finalized) { grade }` check 4 times (show/answer/submitted/submit) instead of once, violating this codebase's own established single-chokepoint convention (04-RESEARCH.md, checker directive: "no duplicated finalize implementation").

**Tradeoff to flag for the planner:** This makes `Attempt` (a Model) call into `AttemptGrader` (a Service) via `app(AttemptGrader::class)`. That is a reversal of the typical "service orchestrates models" direction, but it is consistent with this codebase's established convention of putting substantial business logic directly on `Attempt` (`deadline()`, `isExpired()`, the whole `lockAndFinalize()` transaction already live there) rather than in thin models. PHP has no compile-time circular-import restriction, so `Attempt.php` importing `App\Services\AttemptGrader` while `AttemptGrader.php` imports `App\Models\Attempt` is safe at runtime.

**Example:**
```php
// Source: this codebase, app/Models/Attempt.php (04-04), extended for Phase 5
private function lockAndFinalize(callable $guard): bool
{
    return DB::transaction(function () use ($guard) {
        $locked = self::whereKey($this->id)->lockForUpdate()->first();
        $locked->setRelation('exam', $this->exam);

        $finalized = false;

        if ($locked->status === 'in_progress' && $guard($locked)) {
            $locked->update([
                'status' => 'submitted',
                'submitted_at' => now()->lessThan($locked->deadline())
                    ? now()
                    : $locked->deadline(),
            ]);
            $finalized = true;

            // Phase 5 hook — same transaction, same row lock, exactly-once.
            app(AttemptGrader::class)->handleFinalized($locked);
        }

        $this->setRawAttributes($locked->getAttributes());
        $this->setRelation('exam', $locked->exam);
        $this->setRelation('answers', $locked->answers);

        return $finalized;
    }, 3);
}
```

### Pattern 2: Two-method AttemptGrader, reused at both invocation points

**What:** `gradeAutoGradable()` is only ever called once (at finalize). `syncStatus()` (the completeness check + `graded` transition + score recompute) is called at BOTH invocation points: once from `handleFinalized()` at finalize time, and again — alone, without `gradeAutoGradable()` — from the lecturer's grade-save action every time an open-text answer is scored.

**When to use:** Any time the "is this attempt fully graded" question needs re-evaluating — which is exactly the two moments D-03/D-04 name.

**Example:**
```php
// Source: recommended shape for app/Services/AttemptGrader.php
namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Answer;
use App\Models\Attempt;

class AttemptGrader
{
    /**
     * Called exactly once, from inside Attempt::lockAndFinalize()'s
     * transaction, right after status flips to submitted.
     */
    public function handleFinalized(Attempt $attempt): void
    {
        $this->gradeAutoGradable($attempt);
        $this->syncStatus($attempt);
    }

    /**
     * MCQ auto-grading (D-01, GRD-01). Only writes to Answer rows that
     * already exist — an entirely unanswered MCQ (no row) is left with
     * no row, which correctly contributes 0 to the SUM() total without
     * needing a placeholder write.
     */
    public function gradeAutoGradable(Attempt $attempt): void
    {
        $attempt->loadMissing(['exam.questions.options', 'answers']);

        foreach ($attempt->exam->questions->where('type', QuestionType::Mcq) as $question) {
            $answer = $attempt->answers->firstWhere('question_id', $question->id);

            if ($answer === null) {
                continue; // never touched — 0 contribution, no crash (Pitfall 7)
            }

            $correctOptionId = $question->options->firstWhere('is_correct', true)?->id;
            $isCorrect = $correctOptionId !== null
                && $answer->selected_option_id !== null
                && $answer->selected_option_id === $correctOptionId;

            $answer->update([
                'is_correct' => $isCorrect,
                'score' => $isCorrect ? $question->points : 0,
            ]);
        }
    }

    /**
     * Completeness check + submitted -> graded transition (D-03/D-04).
     * Idempotent and safe to call repeatedly, including on a regrade of
     * an already-graded attempt (D-08 "recomputable if a grade changes")
     * — always recomputes the score sum when there is nothing pending.
     */
    public function syncStatus(Attempt $attempt): void
    {
        if (! in_array($attempt->status, ['submitted', 'graded'], true)) {
            return; // in_progress attempts are never touched here
        }

        $stillPending = Answer::query()
            ->where('attempt_id', $attempt->id)
            ->whereNull('score')
            ->whereHas('question', fn ($q) => $q->where('type', QuestionType::Open->value))
            ->exists();

        if ($stillPending) {
            return; // remains submitted — result withheld (D-03, UX pitfall)
        }

        $attempt->update([
            'status' => 'graded',
            'score' => $attempt->answers()->sum('score'),
        ]);
    }
}
```

### Pattern 3: Lecturer grade-save under the same row-lock discipline as Phase 4

**What:** Saving a lecturer's score AND re-evaluating completeness happens inside `DB::transaction()` with `Attempt::whereKey($id)->lockForUpdate()`, mirroring `AttemptController@answer`'s existing pattern — not a bare `Answer::update()` + a separate unguarded `syncStatus()` call.

**When to use:** Every grade-save write. Two lecturers (or one lecturer double-submitting a grading form) racing to grade the last two pending open-text answers of the same attempt must not both read "1 pending" and skip the `graded` transition.

**Example:**
```php
// Source: pattern mirrored from this codebase's AttemptController@answer (04-03)
public function update(GradeAnswerRequest $request, Attempt $attempt, Answer $answer): RedirectResponse
{
    DB::transaction(function () use ($request, $attempt, $answer) {
        Attempt::whereKey($attempt->id)->lockForUpdate()->first();

        $answer->update(['score' => $request->validated('score')]);

        app(AttemptGrader::class)->syncStatus($attempt->fresh());
    });

    return redirect()->route('lecturer.results.show', [$attempt->exam_id, $attempt])
        ->with('status', 'Grade saved.');
}
```

### Anti-Patterns to Avoid

- **Grading via an Eloquent model event on `Answer`:** explicitly forbidden by ARCHITECTURE.md — a `saving`/`updating` observer would silently re-run "grading" logic on unrelated writes to the same row (e.g. a lecturer editing their own already-saved grade), and hides the finalize-time trigger from anyone reading the code.
- **Recomputing `attempts.score` only on the FIRST transition to `graded`, never again:** breaks the regrade case (D-08's "recomputable if a grade changes"). `syncStatus()` must always recompute the sum whenever there is nothing pending, not just the first time.
- **Treating "no Answer row" the same as "Answer row with `score IS NULL`" for the open-text pending check:** an open-text question the student never touched has no row at all and should resolve to 0 automatically (nothing to grade); an open-text question the student typed into (row exists, `answer_text` populated, `score` still null) genuinely blocks the `graded` transition until a lecturer scores it. The `whereHas('question', ...)` + `whereNull('score')` query above only counts existing rows, which is the correct behavior — do not "help" by also treating missing rows as pending, or every all-MCQ-plus-one-untouched-essay exam would wrongly hang in `submitted` forever.
- **Reusing `AttemptPolicy::view`/`update` for the student Result controller:** see "Common Pitfalls" — this silently breaks result visibility if an exam is later unpublished/reassigned.
- **Per-lecturer ownership checks on grading/results:** this codebase's established Phase 2/3 convention is "any lecturer may act on any exam" (no `ExamPolicy` used by `ExamController`/`ExamAssignmentController` beyond the `role:lecturer` route group). Adding an ownership Policy for grading/results specifically would be an inconsistent, unrequested new restriction — role:lecturer is the correct, sufficient gate here, matching D-04/D-06's own wording ("role:lecturer") with no ownership qualifier.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Summing answer scores into a total | A PHP loop over `$attempt->answers` accumulating a running total | `$attempt->answers()->sum('score')` | Single SQL `SUM()` query; `NULL` rows are excluded by SQL semantics automatically, matching "unanswered = 0" with zero extra filtering code |
| Bounding a lecturer's score input to `[0, points]` | A custom Rule class or manual `if ($score > $points) abort()` check | FormRequest `rules()`: `['required','numeric','min:0','max:'.$question->points]` | Confirmed (Laravel 11 docs) that `numeric` + `min`/`max` performs true numeric comparison, not string comparison — no custom Rule needed for this bound |
| "Is this attempt fully graded" state | A boolean flag column maintained by hand on every write | A query-derived check (`whereNull('score')->whereHas('question', type=open)->exists()`) inside `syncStatus()` | Query-derived avoids a second source of truth that can drift from the actual `answers` rows; this project's scale doesn't need a maintained flag |
| Attempt lifecycle transitions (in_progress → submitted → graded) | A state-machine package | Plain string column + guard conditions in `AttemptGrader`/`Attempt` | Already the established Phase-4 precedent (STACK.md explicitly rejects a state-machine package for this exact 3-state lifecycle) |

**Key insight:** Every "don't hand-roll" item above is really the same insight restated: this domain's correctness surface is entirely expressible as SQL aggregates + FormRequest validation + a handful of guard conditions on an existing string column. Reaching for a custom abstraction (a Rule class, a maintained boolean flag, a state machine) adds a second thing that can drift from the `answers`/`attempts` rows without adding any expressiveness this project's fixed 3-state lifecycle actually needs.

## Common Pitfalls

### Pitfall 1: Reusing `AttemptPolicy::view`/`update` for the Result view silently hides graded results

**What goes wrong:** `AttemptPolicy::view` (04-02) is `$attempt->user_id === $user->id && Exam::visibleTo($user)->whereKey($attempt->exam_id)->exists()`. `Exam::visibleTo()` requires `is_published = true` AND classroom-assignment match. If a lecturer unpublishes the exam, reassigns classrooms, or the student's `classroom_id` changes after the exam was taken and graded, this policy flips to `false` — a student who legitimately took and was graded on an exam would get a 403 on their own result.

**Why it happens:** `view`/`update` were built for the in-progress take flow (04-CONTEXT.md D-08: "own attempt AND the underlying exam is still takeable"), where "is this exam still available" is exactly the right check. The results flow has a different question — "did I take this attempt" — which is ownership-only and must not depend on the exam's current publish/assignment state.

**How to avoid:** Add a new `AttemptPolicy::viewResult(User $user, Attempt $attempt): bool { return $attempt->user_id === $user->id; }` and use it (not `view`) for `Student\ResultController`. Do not derive it from `Exam::visibleTo()`.

**Warning signs:** A feature test that unpublishes an exam (or clears the student's `classroom_id`) after grading, then requests the result route, and gets a 403 instead of the expected result page.

### Pitfall 2: Auto-grading edge cases (carried forward from PITFALLS.md Pitfall 7, MEDIUM confidence — re-verify empirically)

**What goes wrong:** A grading loop assumes every MCQ question has an answer row, a selected option, and a correct option — and crashes or mis-scores when any of those is missing.

**Why it happens:** The happy-path single-select MCQ with a fully-answered attempt is the easiest case to build and test against.

**How to avoid — the exact matrix to cover:**
| Case | Expected behavior |
|------|--------------------|
| Answer row exists, `selected_option_id` matches the correct option | `is_correct = true`, `score = points` |
| Answer row exists, `selected_option_id` is a different (incorrect) option | `is_correct = false`, `score = 0` |
| Answer row exists, `selected_option_id` is `null` (touched then cleared, or never selected but row was autosaved with `answer_text` for a different question in the same request batch — not actually possible per-question here, but defensively) | `is_correct = false`, `score = 0` — never a null-pointer/crash |
| No answer row at all (question never touched) | No write; contributes 0 to `SUM()` implicitly |
| Question has no option flagged `is_correct` (shouldn't happen — EXM-02 enforces exactly one at authoring time, but grade defensively) | `is_correct = false`, `score = 0`, never a crash on `firstWhere(...)?->id` returning null |
| Open-text question, answered | Left `score = null` — excluded from grading entirely, picked up by `syncStatus()`'s pending check instead |
| Open-text question, never touched (no row) | Not counted as "pending" (see Anti-Patterns above) — implicitly 0, `graded` transition is not blocked by it |

**Verification:** Write the full matrix as a Feature test (`AttemptGraderTest` or similar) with one attempt containing every row shape above, and assert the exact `is_correct`/`score` outcome per answer.

### Pitfall 3: Showing a partial/pre-grading score as if final (carried forward from PITFALLS.md UX Pitfall)

**What goes wrong:** A results view computes and displays `attempts.score` (or a live sum) even while `status === 'submitted'` (open-text still pending), making the student think grading is complete when it isn't.

**How to avoid:** `Student\ResultController@show` must branch on `status` BEFORE building any score-bearing view-model — render the "awaiting grading" view with zero score data when `status !== 'graded'`, never a partial number. This is already the explicit behavior required by D-05 and GRD-03/04.

**Warning signs:** A test that submits an attempt containing an open-text question, immediately requests the result page, and finds ANY numeric score rendered (even 0 or "partial") instead of an awaiting-grading message.

### Pitfall 4: Race between concurrent lecturer grade-saves on the same attempt

**What goes wrong:** Two grade-save requests for the last two pending open-text answers of the same attempt (double-click, or a lecturer grading rapidly) both read "1 item still pending" (the OTHER answer, not yet committed) and both skip the `graded` transition — the attempt never flips to `graded` even though both answers now have scores.

**Why it happens:** An application-level check-then-transition without a lock has the same TOCTOU shape as Phase 4's Pitfall 4 (duplicate attempts) — just applied to the completeness check instead of the attempt-creation check.

**How to avoid:** Wrap the grade-save + `syncStatus()` call in `DB::transaction()` with `Attempt::whereKey($id)->lockForUpdate()` (Pattern 3 above), mirroring the exact discipline `AttemptController@answer` already uses for autosave-vs-finalize races.

**Warning signs:** A feature test firing two near-simultaneous grade-save requests for the two remaining pending answers of an attempt and asserting the attempt ends up `graded` — if it doesn't reliably transition, the lock is missing.

## Code Examples

### GradeAnswerRequest — bounded-numeric validation against a route-resolved model

```php
// Source: pattern confirmed against Laravel 11.x validation docs (numeric+max
// performs numeric, not string, comparison — see Sources), shaped to match
// this codebase's existing AssignExamRequest/AnswerRequest FormRequest style.
namespace App\Http\Requests\Lecturer;

use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;

class GradeAnswerRequest extends FormRequest
{
    /**
     * role:lecturer route group already gates access (D-04, no per-lecturer
     * ownership — matches the "any lecturer" Phase 2/3 precedent). Reject
     * only if the target answer isn't actually an open-text answer, or the
     * attempt hasn't reached a gradeable state — defense in depth against a
     * crafted URL (PITFALLS.md Pitfall 2/6 pattern, applied here).
     */
    public function authorize(): bool
    {
        $answer = $this->route('answer');

        return $answer->question->type === QuestionType::Open
            && in_array($answer->attempt->status, ['submitted', 'graded'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $points = $this->route('answer')->question->points;

        return [
            'score' => ['required', 'numeric', 'min:0', 'max:'.$points],
        ];
    }
}
```

### Score/total display query — exam total possible points

```php
// Source: Eloquent aggregate over the exam's questions relation, same
// N+1-safe SUM() pattern as $attempt->answers()->sum('score')
$totalPossible = $attempt->exam->questions()->sum('points');
$totalAwarded = $attempt->score; // stored at the graded transition (D-08)
```

### Student result breakdown — never expose the correct option (D-07)

```php
// Source: pattern mirrored from AttemptController@show's column-whitelist
// approach (04-02) — the same discipline applies here in reverse: reveal
// the student's OWN answer and its correctness, never the option they
// didn't pick or which one WAS correct if they got it wrong.
$breakdown = $attempt->exam->questions()->orderBy('position')->get()
    ->map(function ($question) use ($attempt) {
        $answer = $attempt->answers->firstWhere('question_id', $question->id);

        return [
            'body' => $question->body,
            'points' => $question->points,
            'type' => $question->type->value,
            'student_answer' => $question->type === QuestionType::Mcq
                ? $answer?->selectedOption?->body
                : $answer?->answer_text,
            'is_correct' => $answer?->is_correct, // null for open-text, by design
            'score_awarded' => $answer?->score ?? 0,
        ];
    });
```

## State of the Art

Not applicable — this is a small, fixed-scope internal domain with no external API/library version drift to track. The relevant "state of the art" check is entirely internal: confirm the Phase 4 finalize chokepoint (`lockAndFinalize`) hasn't changed shape since 04-04 (it hasn't — verified by reading `app/Models/Attempt.php` directly, 2026-07-16).

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | An unanswered open-text question (no `Answer` row) should silently resolve to 0 points and never block the `submitted → graded` transition, rather than requiring an explicit lecturer "0" grade | Pattern 2 / Anti-Patterns / Pitfall 2 | If the grader instead expects the wrong behavior (blocking forever, or requiring a lecturer to explicitly zero every blank essay), the completeness check would need to also handle "missing row" as pending — a straightforward addition to `syncStatus()`'s query, but changes GRD-03's UX contract (some attempts would never auto-transition without lecturer touch, even with zero open-text content) |
| A2 | Lecturer grading/results have NO per-lecturer ownership restriction (any lecturer can grade/view any exam's attempts), matching the established "any lecturer" pattern from `ExamController`/`ExamAssignmentController` | Anti-Patterns, GradeAnswerRequest example | If the actual intent is per-lecturer ownership (only the exam's creator can grade it), `GradeAnswerRequest::authorize()` and `Lecturer\ResultController` both need an added ownership check against `exam.created_by` — currently no such check exists anywhere in Phases 1-4 for exams, so this is a reasonable inference from precedent, not a locked decision |
| A3 | Score validation via `['numeric','min:0','max:'.$points]` (computed server-side from the route-bound `Answer→Question`) is sufficient, without an additional `decimal:0,2` rule, given `answers.score` is `decimal(5,2)` | GradeAnswerRequest example | Low risk — Eloquent's `decimal:2` cast on `Answer::score` will silently round any excess-precision input to 2dp on write regardless; a stricter `decimal:0,2` rule would reject e.g. `7.333` outright instead of silently rounding to `7.33`, which is a UX nicety, not a correctness gap |

**Confirmed via tools this session (not merely assumed):** the FormRequest `numeric`+`max` behaves as numeric (not string) comparison, and dynamic bounds may be computed server-side from a route-bound model rather than requiring a sibling request field — both confirmed via `WebFetch` against `laravel.com/docs/11.x/validation` (see Sources).

## Open Questions

1. **Should `is_correct` ever be derived for open-text answers (e.g. `score === points`)?**
   - What we know: D-04 explicitly leaves this to discretion ("`is_correct` may stay null for open-text, or be derived — keep null; score is authoritative").
   - What's unclear: Whether the results breakdown UI wants a binary ✓/✗ indicator for open-text rows too, or only a raw score.
   - Recommendation: Keep `is_correct = null` for open-text (matches D-04's stated default) and render the breakdown's open-text rows with the numeric score only, no ✓/✗ — avoids inventing an arbitrary "what counts as correct" threshold for a partial-credit-capable field. This is the simpler, safer default and matches the phase's explicit "no partial-credit MCQ, no rubrics" v2 boundary.

2. **Does a lecturer need to be able to change an already-`graded` attempt's score (regrade)?**
   - What we know: D-08 says "recomputable if a grade changes"; Deferred Ideas says "Regrade history/audit — not required" (implying regrade ITSELF may be in scope, just not its audit trail).
   - What's unclear: Whether `GradeAnswerRequest`'s `authorize()` should permit editing an answer on an already-`graded` attempt, or only ever a `submitted` one.
   - Recommendation: Permit it — the `authorize()` example above already allows `status ∈ {submitted, graded}`, and `syncStatus()` is idempotent/re-runs the sum on every call, so a regrade "just works" through the same code path with no special-casing. No audit trail is added (per Deferred Ideas).

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit ^11.0.1 (installed, confirmed via composer.json) |
| Config file | `phpunit.xml` (project root) — `Feature` suite at `tests/Feature`, `Unit` suite at `tests/Unit` |
| Quick run command | `php artisan test --filter=<TestClass>` |
| Full suite command | `php artisan test` |

All Phase 1-4 feature tests use `RefreshDatabase` against the real configured MySQL connection (`phpunit.xml` has the SQLite `DB_CONNECTION`/`DB_DATABASE` env overrides commented out) — Phase 5 tests must follow the identical convention, not introduce SQLite.

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| GRD-01 | Submitting an attempt auto-grades every MCQ answer: correct selection → `score = points`, wrong selection → `score = 0`, unanswered (no row) → contributes 0 to the total, never a crash | feature | `php artisan test --filter=AttemptGraderTest -x` | ❌ Wave 0 |
| GRD-01 | Auto-grading fires identically via BOTH the manual-submit path and the lazy `finalizeIfExpired()` path (single chokepoint, D-02) | feature | `php artisan test --filter=AttemptGraderTest::test_auto_grading_fires_on_lazy_expiry -x` | ❌ Wave 0 |
| GRD-03 | An all-MCQ exam transitions `submitted → graded` immediately on finalize, with `attempts.score` set | feature | `php artisan test --filter=AttemptGraderTest::test_all_mcq_exam_grades_immediately -x` | ❌ Wave 0 |
| GRD-03 | An exam with ≥1 open-text question stays `submitted` (not `graded`) after finalize, until the lecturer grades the last pending open-text answer | feature | `php artisan test --filter=AttemptGraderTest::test_open_text_exam_stays_submitted_until_graded -x` | ❌ Wave 0 |
| GRD-02 | Lecturer grades an open-text answer with a score in `[0, points]` — accepted, `answers.score` updated | feature | `php artisan test --filter=GradeAnswerTest::test_lecturer_can_grade_an_open_text_answer -x` | ❌ Wave 0 |
| GRD-02 | A score greater than `question.points` is rejected (422) | feature | `php artisan test --filter=GradeAnswerTest::test_over_points_score_is_rejected -x` | ❌ Wave 0 |
| GRD-02 | A negative score is rejected (422) | feature | `php artisan test --filter=GradeAnswerTest::test_negative_score_is_rejected -x` | ❌ Wave 0 |
| GRD-02 | A non-lecturer (student, or unauthenticated) is forbidden from grading | feature | `php artisan test --filter=GradeAnswerTest::test_non_lecturer_cannot_grade -x` | ❌ Wave 0 |
| GRD-02 | Grading an MCQ answer (not open-text) via the grade endpoint is rejected — the endpoint is open-text only | feature | `php artisan test --filter=GradeAnswerTest::test_grading_an_mcq_answer_is_rejected -x` | ❌ Wave 0 |
| GRD-04 | Student's result view shows "awaiting grading" (no score) while `status === submitted` | feature | `php artisan test --filter=StudentResultTest::test_result_is_withheld_while_pending -x` | ❌ Wave 0 |
| GRD-04 | Student's result view shows total score + per-question breakdown once `status === graded` | feature | `php artisan test --filter=StudentResultTest::test_result_shown_when_graded -x` | ❌ Wave 0 |
| GRD-04 | A student cannot view another student's result (own-attempt only, IDOR) | feature | `php artisan test --filter=StudentResultTest::test_cannot_view_another_students_result -x` | ❌ Wave 0 |
| GRD-04 | A student CAN still view their own graded result even if the exam is later unpublished/reassigned (Pitfall 1 regression guard) | feature | `php artisan test --filter=StudentResultTest::test_result_visible_after_exam_unpublished -x` | ❌ Wave 0 |
| GRD-04 | The result breakdown never renders the correct option's identity/text for a question the student got wrong (D-07) | feature | `php artisan test --filter=StudentResultTest::test_breakdown_never_exposes_the_correct_option -x` | ❌ Wave 0 |
| GRD-05 | Lecturer results index lists all attempts (status + score) for a given exam | feature | `php artisan test --filter=LecturerResultTest::test_index_lists_attempts_per_exam -x` | ❌ Wave 0 |
| GRD-05 | Lecturer can drill into a specific student's attempt to see the per-question breakdown | feature | `php artisan test --filter=LecturerResultTest::test_show_renders_breakdown -x` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --filter=<TestClass being touched>`
- **Per wave merge:** `php artisan test` (full suite — Phase 1-4 currently green at 150 tests / 372 assertions per 04-04-SUMMARY.md; Phase 5 must not regress this)
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/AttemptGraderTest.php` (or `tests/Feature/Grading/AttemptGraderTest.php`) — covers GRD-01, GRD-03 auto-grading + completeness matrix
- [ ] `tests/Feature/Lecturer/GradeAnswerTest.php` — covers GRD-02
- [ ] `tests/Feature/Student/ResultTest.php` — covers GRD-04
- [ ] `tests/Feature/Lecturer/ResultTest.php` — covers GRD-05
- [ ] Factory support: `Answer::factory()->mcqCorrect()`/`mcqIncorrect()`/`openText()` states, or equivalent inline fixture setup, to build the full edge-case matrix cheaply per PITFALLS.md's recommendation ("write a small grading unit test matrix... this is cheap and catches most of the above before it reaches a real student")
- [ ] Framework install: none — PHPUnit is already installed and configured

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no | Unchanged from Phases 1-4 (Breeze) |
| V3 Session Management | no | Unchanged from Phases 1-4 (Breeze) |
| V4 Access Control | yes | `role:lecturer`/`role:student` route-group middleware (coarse) + `AttemptPolicy` (fine, ownership) — new `viewResult()` method added this phase; `GradeAnswerRequest::authorize()` for the open-text/status-shape check |
| V5 Input Validation | yes | `GradeAnswerRequest` (`numeric\|min:0\|max:{points}`, computed server-side from the route-bound `Question`, never from client-supplied max) |
| V6 Cryptography | no | Not applicable to this phase |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| IDOR — a student requesting `GET /student/attempts/{other_student_attempt}/result` | Information Disclosure | `AttemptPolicy::viewResult()` — ownership-only check (own `user_id`), independent of `Exam::visibleTo()` (Pitfall 1) |
| Mass assignment — a lecturer's grade-save request injecting `is_correct`, `attempt_id`, or a different `answer_id` beyond the routed one | Tampering | `GradeAnswerRequest::rules()` only accepts `score`; the target `Answer` comes from route-model binding, never request body; `Answer::update(['score' => ...])` (explicit, single-key array), never `->update($request->all())` |
| Score-key leakage — the student result view revealing which specific option was correct for a question the student got wrong | Information Disclosure | D-07: reveal the student's own answer + ✓/✗ + score only; never render `Option::where('is_correct', true)` in the student-facing breakdown (Pitfall in Code Examples above) |
| Over-points / negative score injection via the grading form | Tampering | `numeric\|min:0\|max:{question.points}` FormRequest rule, server-computed bound (confirmed numeric-not-string comparison — see Sources) |
| Grading an in-progress (not yet submitted) attempt's answer via a crafted URL | Tampering | `GradeAnswerRequest::authorize()` rejects unless `attempt.status ∈ {submitted, graded}` |

## Sources

### Primary (HIGH confidence)

- This codebase, read directly (2026-07-16): `app/Models/Attempt.php`, `app/Models/Answer.php`, `app/Models/Question.php`, `app/Models/Option.php`, `app/Http/Controllers/Student/AttemptController.php`, `app/Policies/AttemptPolicy.php`, `app/Policies/ExamPolicy.php`, `app/Http/Controllers/Lecturer/ExamController.php`, `app/Http/Controllers/Lecturer/ExamAssignmentController.php`, `app/Http/Requests/Lecturer/AssignExamRequest.php`, `app/Http/Requests/Student/AnswerRequest.php`, `database/migrations/*_create_answers_table.php`, `database/migrations/*_create_attempts_table.php`, `database/factories/{Attempt,Answer,Question}Factory.php`, `routes/lecturer.php`, `routes/student.php`, `phpunit.xml`, `.planning/config.json`
- `php artisan --version` (this environment, 2026-07-16) — `Laravel Framework 11.55.0`
- `.planning/phases/04-attempt-taking/04-02-SUMMARY.md`, `04-04-SUMMARY.md` — the exact `lockAndFinalize()`/`finalize()`/`finalizeIfExpired()` history and established patterns
- `.planning/phases/05-grading-results/05-CONTEXT.md` — locked D-01..D-08 decisions

### Secondary (MEDIUM confidence)

- [Validation | Laravel 11.x docs](https://laravel.com/docs/11.x/validation) — confirmed via WebFetch (2026-07-16): `numeric`+`min`/`max`/`gte`/`lte` perform numeric (not string) comparison; dynamic bounds may be computed server-side in `rules()` from a route-bound model, not only from sibling request fields
- `.planning/research/PITFALLS.md` — Pitfall 7 (auto-grading edge cases), UX Pitfall ("partial score shown as final")
- `.planning/research/SUMMARY.md` §"Phase 7 (Grading & results)" and STACK.md §3 — `AttemptGrader` service pattern, explicit-call-not-observer rationale, live-accessor-vs-denormalized-column tradeoff

### Tertiary (LOW confidence)

- General WebSearch cross-reference on Eloquent `sum()` aggregate behavior and status-column gating conventions — consistent with, and superseded by, the direct codebase evidence above; included for completeness of the research-plan/cache trail, not as the primary basis for any claim in this document

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — zero new packages, every mechanism already present and verified in this exact installed environment (Laravel 11.55.0)
- Architecture: HIGH — the finalize hook point is derived directly from reading the actual `Attempt::lockAndFinalize()` implementation, not inferred from documentation
- Pitfalls: HIGH (IDOR/mass-assignment/leakage mechanisms — Laravel-native, cross-checked against this codebase's own existing Policy/FormRequest patterns) / MEDIUM (the exact auto-grading edge-case enumeration — synthesized, matches PITFALLS.md's own stated MEDIUM confidence for this domain-specific matrix, must be verified empirically via the Wave 0 test matrix)

**Research date:** 2026-07-16
**Valid until:** No external expiry — this phase has zero new package/version dependencies; re-validate only if the Phase 4 `Attempt::lockAndFinalize()` implementation changes shape before Phase 5 executes

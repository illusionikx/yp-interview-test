# Phase 10: Exam Integrity — Auto-Assignment & Attempt Lifecycle - Pattern Map

**Mapped:** 2026-07-17
**Files analyzed:** ~35 (1 new service, 1 new migration, 5 new Wave-0 test files, ~9 modified production files, ~20 test files needing fixture rewrites, several files deleted wholesale)
**Analogs found:** all files have a strong, direct analog already read in full — this phase is a predicate/service rewrite on a shipped codebase, not new infrastructure.

RESEARCH.md already did most of this work concretely (it quotes exact lines and even drafts the new
service). This file exists to (a) confirm every quoted excerpt against a direct re-read of the actual
current file, (b) flag the one place RESEARCH.md's draft needs a small correction against the real
schema, and (c) enumerate the full deletion/rewrite blast radius precisely for the planner.

---

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `app/Services/AttemptVoider.php` (NEW) | service | CRUD (destructive delete) + aggregate-read | `app/Services/AttemptGrader.php` | exact — explicit named precedent in CONTEXT.md D-2 |
| `database/migrations/*_drop_exam_section_table.php` (NEW) | migration | schema | `database/migrations/2026_07_15_100008_create_exam_section_table.php` (the table being dropped — inverse-shape analog) | role-match (no prior DROP migration exists in this repo; this is the first schema break) |
| `tests/Feature/Student/CrossSubjectVisibilityTest.php` (NEW) | test | request-response | `tests/Feature/Student/AttemptAvailabilityTest.php` (fixture helper) + `tests/Feature/Student/ExamAccessTest.php` (denial-matrix shape) | exact |
| `tests/Feature/AttemptVoiderTest.php` (NEW) | test | CRUD/aggregate | `app/Services/AttemptGrader.php` + `database/factories/AttemptFactory.php` | exact |
| `tests/Feature/Lecturer/ResetSubmissionsTest.php` (NEW) | test | request-response | `tests/Feature/Student/AttemptAvailabilityTest.php` (start-after-reset = same `store()` flow as fresh start) | role-match |
| `tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php` (NEW) | test | request-response | existing `ExamPublishedEditGateTest` (being retired/rewritten — same route, opposite assertion) | role-match |
| `app/Models/Exam.php` (MODIFIED — `scopeVisibleTo()` rewrite, `sections()` removed) | model | CRUD (query scope) | itself (in-place rewrite); nested `whereHas` precedent already present in the method being replaced | exact |
| `app/Models/Section.php` (MODIFIED — `exams()` removed) | model | CRUD | itself | exact |
| `app/Http/Requests/Lecturer/UpdateExamRequest.php` (MODIFIED — `authorize()`) | request | request-response | `app/Http/Requests/Lecturer/AssignExamRequest.php` (`return true;` + "no per-record ownership" comment precedent) | exact |
| `app/Http/Requests/Lecturer/StoreQuestionRequest.php` (MODIFIED — `authorize()`) | request | request-response | `AssignExamRequest.php` | exact |
| `app/Http/Requests/Lecturer/UpdateQuestionRequest.php` (MODIFIED — `authorize()`) | request | request-response | `AssignExamRequest.php` | exact |
| `app/Http/Controllers/Lecturer/ExamQuestionController.php` (MODIFIED — `destroy()` gate relaxed, routes through voider) | controller | request-response | `app/Http/Controllers/Lecturer/ExamController.php`'s `unpublish()`/`destroy()` shape | role-match |
| `app/Http/Controllers/Lecturer/AnswerGradeController.php` (MODIFIED — D-5 null-guard) | controller | request-response | `app/Models/Attempt.php::lockAndFinalize()`'s guard shape (same file already shows the *model*-side guard; this is the controller-side mechanical copy) | exact |
| `app/Http/Controllers/Lecturer/ExamController.php` (MODIFIED — `unpublish()` guard removed, new `resetSubmissions()` action, `show()` drops `$sections`) | controller | request-response + CRUD | itself (in-place) | exact |
| `app/Http/Controllers/Student/ExamController.php` (MODIFIED — `$enrolledSection` derivation) | controller | request-response | itself (in-place); new query mirrors `AttemptAvailabilityTest::enrolledStudentFor()`'s pinned-subject shape | exact |
| `database/seeders/DatabaseSeeder.php` (MODIFIED — off the pivot) | seeder | batch | itself (in-place); `seedExam()`/`seedSections()` already build same-subject section+exam correctly, only the `->sections()->sync()` call line needs removing | exact |
| `routes/lecturer.php` (MODIFIED — assignment route removed, reset-submissions route added) | route | — | existing `publish`/`unpublish` PATCH route pair | exact |
| `resources/views/lecturer/exams/show.blade.php` (MODIFIED — assignment panel deleted, Submissions panel added) | view | request-response | its own existing "Delete exam?" confirm-modal block (lines 48-62) | exact |
| `resources/views/lecturer/exams/edit.blade.php`, `questions/_form.blade.php`, `questions/edit.blade.php` (MODIFIED — EDT-04 interception) | view | request-response | `show.blade.php`'s delete-form `@submit.prevent="$dispatch('open-modal', ...)"` pattern | exact |

**DELETED entirely** (not "modified" — full removal, D-1/FIX-03):
- `app/Http/Controllers/Lecturer/ExamAssignmentController.php`
- `app/Http/Requests/Lecturer/AssignExamRequest.php`
- `tests/Feature/Lecturer/ExamAssignmentTest.php` (7 tests, all for the removed feature)
- The `exams.assignment.update` route line in `routes/lecturer.php`
- The "Assign to sections" `<div>` block in `resources/views/lecturer/exams/show.blade.php` (lines 125-156 of the current file)

---

## Pattern Assignments

### `app/Services/AttemptVoider.php` (service, CRUD + aggregate)

**Analog:** `app/Services/AttemptGrader.php` (full file read, reproduced above in context) — the CONTEXT.md-named precedent.

**Shape to mirror exactly:**
- Plain class in `App\Services`, no interface, no DI beyond what `app()` resolves for free.
- One doc-comment block at the class level explaining *why* this is an explicit service and not a model event (copy `AttemptGrader`'s own opening comment's rationale almost verbatim — CLAUDE.md explicitly forbids model observers for exactly this class of destructive/side-effecting logic).
- Public methods take a model (`Exam $exam`), never a raw ID or `$request->all()` — matches `AttemptGrader::handleFinalized(Attempt $attempt)`'s signature convention and the codebase's stated CWE-915 discipline (seen verbatim in `AnswerGradeController.php:31`'s comment "Explicit single-key write — never `$request->all()`").

**Correction to RESEARCH.md's draft — `summarize()`'s status mapping is simpler than described:**

RESEARCH.md's Pattern 3 code and 10-UI-SPEC.md's count table describe `$submittedUngraded` as "status = submitted AND not fully graded" and `$graded` as "status = submitted AND fully graded" — implying a derived condition. Direct read of `AttemptFactory.php` and `AttemptGrader::syncStatus()` shows this is **not** derived — the `attempts.status` column is a plain 3-value string (`'in_progress'` / `'submitted'` / `'graded'`, default `'in_progress'`, no enum cast — `app/Models/Attempt.php:29-30`'s own comment: *"Status stays a plain string column this phase"*), and `syncStatus()` already flips it to `'graded'` the moment every open-text answer has a non-null score. So the three UI-SPEC buckets map 1:1 onto the three literal status values with **no extra WHERE clause**:

```php
// app/Services/AttemptVoider.php — summarize()
$counts = Attempt::where('exam_id', $exam->id)
    ->selectRaw('status, count(*) as aggregate')
    ->groupBy('status')
    ->pluck('aggregate', 'status');

$inProgress = (int) ($counts['in_progress'] ?? 0);
$submittedUngraded = (int) ($counts['submitted'] ?? 0);
$graded = (int) ($counts['graded'] ?? 0);
```

This is RESEARCH.md's own code example (§Code Examples / Pattern 3) — confirmed correct as written; only the UI-SPEC's prose description of `$submittedUngraded`/`$graded` as a derived condition is imprecise. Use the status column directly; do not add a `whereHas('answers', ...)` completeness check — that would double up work `AttemptGrader::syncStatus()` already does at grade-save time and could disagree with it.

**Lock discipline to mirror — `Attempt::lockAndFinalize()`** (`app/Models/Attempt.php:138-198`, full method read above):

```php
// app/Models/Attempt.php:140-141 — the exact lock shape to copy
return DB::transaction(function () use ($guard) {
    $locked = self::whereKey($this->id)->lockForUpdate()->first();
    // ... null-guard, throws AttemptVanishedException if vanished ...
```

`AttemptVoider::void()` must take the **same lock** on the same table before deleting:

```php
public function void(Exam $exam): int
{
    return DB::transaction(function () use ($exam) {
        $ids = Attempt::where('exam_id', $exam->id)
            ->lockForUpdate()
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        Attempt::whereIn('id', $ids)->delete();

        return $ids->count();
    });
}
```

No null-guard/`AttemptVanishedException` is needed *inside* `void()` itself — it operates on a set (`whereIn`), not a single expected row, so "zero rows found" is a legitimate empty-set outcome (`isEmpty()` → `return 0`), not a vanished-row error. The exception fires on the **other side** of the race: a concurrent `Attempt::lockAndFinalize()` call that loses the lock race will find its row gone and throw — that machinery is already built and tested (Phase 9), `AttemptVoider` does not need to reimplement it.

**Call sites (both call `app(AttemptVoider::class)`, matching `app(AttemptGrader::class)` calls in `AnswerGradeController.php:34` and `DatabaseSeeder.php:287-288`):**
1. New `ExamController::resetSubmissions()` action (CLS-07) — calls `void($exam)` directly, no pre-save step.
2. `ExamController::update()` / `ExamQuestionController::store()`/`update()`/`destroy()` (EDT-04, D-6/D-7) — call `summarize($exam)` before the write to decide whether the confirm-modal fires; call `void($exam)` **inside the same atomic transaction as the save** (D-7 — one `DB::transaction`, not two), only when the pre-write `summarize()->total > 0`.

---

### `database/migrations/*_drop_exam_section_table.php` (migration, schema)

**Analog:** `database/migrations/2026_07_15_100008_create_exam_section_table.php` (full file read above) — the table being dropped. No prior migration in this repo drops/alters an existing table (`ls database/migrations` shows 14 files, all `create_*_table`) — this is the **first schema-break migration** in the project, so there is no in-repo DROP precedent to copy the *shape* from beyond Laravel's own `Schema::dropIfExists()`.

**Convention to follow — new migration file, not editing the old one in place:** every other migration in this repo is immutable once created (standard Laravel discipline — migrations already run in dev/CI are never edited after the fact). Create a new timestamped migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('exam_section');
    }

    public function down(): void
    {
        // Intentionally not recreated — D-1 is a permanent structural
        // decision (dropping the pivot is what makes the v2.0 cross-subject
        // leak unexpressible). A down() that recreates the table would
        // silently reintroduce the leak vector on rollback. See CONTEXT.md
        // D-1 and app/Models/Exam.php::scopeVisibleTo()'s doc comment.
    }
};
```

Match the existing migration's minimal style (`Schema::create`/`dropIfExists`, no comments beyond the standard `up()`/`down()` doc blocks) — see the file read in full above for the exact formatting convention (4-space indent, `return new class extends Migration`, no namespace).

**Do NOT delete `2026_07_15_100008_create_exam_section_table.php` itself** — Laravel migrations are additive history; the correct move is a new migration whose `up()` drops the table, mirroring how every other schema evolution in this repo (e.g. `2026_07_15_100003_add_role_to_users_table.php`) was done as a new file, never an edit to an earlier one. (RESEARCH.md's structure listing says "DELETED (project's in-place-edit convention)" for the migration file — that phrasing is imprecise; confirm with the planner that the correct action is a new drop-migration, not deleting the create-migration file, since Laravel replays migration history in order and deleting a `create` migration that other environments have already run would desync `migrations` table bookkeeping.)

---

### Wave 0 test files

#### `tests/Feature/Student/AttemptAvailabilityTest.php::enrolledStudentFor()` — THE fixture template (quoted in full)

```php
// tests/Feature/Student/AttemptAvailabilityTest.php:27-35
private function enrolledStudentFor(Exam $exam): User
{
    $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
    $exam->sections()->sync([$section->id]);
    $student = User::factory()->student()->create();
    $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

    return $student;
}
```

This is the **only** existing fixture helper in the repo that already pins `section.subject_id` to `exam.subject_id` explicitly (`Section::factory()->create(['subject_id' => $exam->subject_id])`), confirming RESEARCH.md's claim. Every new/rewritten fixture in this phase should follow this exact shape, with the pivot line (`$exam->sections()->sync(...)`) simply deleted once D-1 lands (visibility becomes automatic — no sync call needed at all):

```php
// Post-D-1 shape — no sections()->sync() call needed
private function enrolledStudentFor(Exam $exam): User
{
    $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
    $student = User::factory()->student()->create();
    $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

    return $student;
}
```

#### `tests/Feature/Student/ExamAccessTest.php` — the denial-matrix shape to reuse for INT-04's negative test (quoted in full above; 7 test methods, each ~10 lines, same three-line fixture-build/act/assert shape)

Every method in this file follows: `Section::factory()->create()` → `Exam::factory()->published()->create()` → `$exam->sections()->sync([...])` → `User::factory()->student()->create()` → `$section->enrollments()->attach(...)` → `actingAs($student)->get(...)` → `assertOk()`/`assertForbidden()`. INT-04's `CrossSubjectVisibilityTest` should reuse this **exact request/assert shape** (`actingAs($student)->get(route('student.exams.show', $examOnA))` → `assertForbidden()`), but build its fixture as **two explicit subjects** rather than the pivot-based single-subject-implied fixture this file currently uses everywhere. RESEARCH.md's Code Examples section already has the full drafted test — use it verbatim, and additionally assert `$subjectA->id !== $subjectB->id` per the Factory Trap warning (CONTEXT.md/VALIDATION.md both mandate this explicit assertion so the negative test can't pass by factory accident).

**Note for the planner:** `ExamAccessTest.php` itself is in the ~20-file rewrite list (it builds single-subject fixtures via the pivot, which need converting to `Section::factory()->create(['subject_id' => $exam->subject_id])` once the pivot is gone) — it is simultaneously an analog to copy shape from AND a file that needs its own fixture body rewritten.

#### `tests/Feature/AttemptVoiderTest.php` — analog is `AttemptFactory.php`'s `submitted()`/`graded()` states (quoted in full above)

```php
// database/factories/AttemptFactory.php:36-58
public function submitted(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);
}

public function graded(int $score = 0): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'graded',
        'submitted_at' => now(),
        'score' => $score,
    ]);
}
```

Build the "1 in_progress + 1 submitted-ungraded + 1 graded" fixture directly from these three factory states (default state is already `in_progress` — no override needed for that one):

```php
$exam = Exam::factory()->published()->create();
Attempt::factory()->for($exam)->create();                 // in_progress (default)
Attempt::factory()->for($exam)->submitted()->create();     // submitted (ungraded)
Attempt::factory()->for($exam)->graded(5)->create();       // graded

$counts = app(AttemptVoider::class)->summarize($exam);

$this->assertSame(1, $counts['inProgress']);
$this->assertSame(1, $counts['submittedUngraded']);
$this->assertSame(1, $counts['graded']);
$this->assertSame(2, $counts['notYetGraded']);
$this->assertSame(3, $counts['total']);
```

#### `tests/Feature/AttemptNullGuardTest.php` — D-5's extension analog (need to locate the existing `Gate::after` seam)

Not yet read this session — flag to planner: read this file in full during planning/execution to copy its existing `Gate::after`-based row-deletion-mid-request seam verbatim for the third test case targeting `AnswerGradeController::update()`. `AnswerGradeController.php` itself (read in full above) confirms the exact unguarded line to fix:

```php
// app/Http/Controllers/Lecturer/AnswerGradeController.php:29 — CURRENT, unguarded
$locked = Attempt::whereKey($attempt->id)->lockForUpdate()->first();
// ... falls straight into $answer->update(...) and
// app(AttemptGrader::class)->syncStatus($locked) with no null-check ...
```

Fix shape (mirrors `Attempt::lockAndFinalize()`'s guard at `app/Models/Attempt.php:160-162`):

```php
DB::transaction(function () use ($request, $attempt, $answer) {
    $locked = Attempt::whereKey($attempt->id)->lockForUpdate()->first();

    if (! $locked) {
        throw new \App\Exceptions\AttemptVanishedException;
    }

    $answer->update(['score' => $request->validated('score')]);

    app(AttemptGrader::class)->syncStatus($locked);
});
```

`AttemptVanishedException` (full file read above, `app/Exceptions/AttemptVanishedException.php`) self-renders — no changes needed to the exception class. Its non-JSON branch (`redirect()->route('student.exams.index')->with('error', ...)`) is written for a *student*-facing route; confirm during planning whether a lecturer-facing redirect target reads more naturally (e.g. `route('lecturer.results.index', $attempt->exam_id)`), since this is the first call site outside the student attempt flow — this is a judgment call for the planner, not something this pattern map should silently resolve.

---

### `app/Models/Exam.php` — `scopeVisibleTo()` rewrite (full current file read above)

**Current (to be replaced), `app/Models/Exam.php:97-105`:**
```php
public function scopeVisibleTo(Builder $query, User $user): Builder
{
    return $query
        ->where('is_published', true)
        ->whereHas('sections.enrollments', fn (Builder $q) => $q
            ->where('user_id', $user->id)
            ->where('status', EnrollmentStatus::Enrolled)
        );
}
```

**After (D-1):**
```php
public function scopeVisibleTo(Builder $query, User $user): Builder
{
    return $query
        ->where('is_published', true)
        ->whereHas('subject.sections.enrollments', fn (Builder $q) => $q
            ->where('user_id', $user->id)
            ->where('status', EnrollmentStatus::Enrolled)
        );
}
```

Confirmed: `Exam::subject(): BelongsTo` already exists (`app/Models/Exam.php:39-42`); `Subject::sections()` and `Section::enrollments()` are asserted present by RESEARCH.md (not independently re-read this session, but both are load-bearing existing relations already exercised by the current pivot-based predicate one hop shallower — same nested-`whereHas` mechanism, just one relation segment longer). No new relation method needed on any model for this rewrite itself.

**Remove `Exam::sections(): BelongsToMany`** (`app/Models/Exam.php:54-63`, doc-commented block) in the same change — it references the dropped `exam_section` table by name and will SQL-error if any leftover caller invokes it. Grep-confirmed call sites (per RESEARCH.md): `ExamAssignmentController` (deleted wholesale) and `Student\ExamController::show()` (rewritten per Pattern 2 below) — no others in `app/`.

**Doc-comment discipline to preserve:** the existing method has an 18-line doc comment explaining *why* `scopeVisibleTo()` is the single predicate (lines 70-96) and warning against folding `isAvailableNow()`/`availabilityState()` into it. Keep this comment's structure and warnings intact when rewriting the body — only the `whereHas` argument changes; the surrounding discipline (single source of truth, consumed by both list and gate) is exactly what D-1 reinforces, not what it changes.

---

### `app/Http/Controllers/Student/ExamController.php` — `$enrolledSection` derivation (per RESEARCH.md §Pattern 2, not independently re-read this session but the query shape is a direct, mechanical analog of `AttemptAvailabilityTest::enrolledStudentFor()`'s already-confirmed pinning pattern)

```php
$enrolledSection = \App\Models\Section::where('subject_id', $exam->subject_id)
    ->whereHas('enrollments', fn ($q) => $q
        ->where('user_id', $request->user()->id)
        ->where('status', EnrollmentStatus::Enrolled)
    )
    ->first();
```

Same `subject_id`-pinning discipline as the fixture template above — this is the production-code mirror of the test-fixture pattern, not a coincidence.

---

### The 4 published-edit gate sites (D-6) — `authorize()` relaxation

**Analog:** `app/Http/Requests/Lecturer/AssignExamRequest.php::authorize()` (full file read above):

```php
// app/Http/Requests/Lecturer/AssignExamRequest.php:18-21
public function authorize(): bool
{
    return true;
}
```

with its own doc comment explaining the "no per-record ownership" rationale (subject-level ownership via `subject_user`, not exam-level). Apply the identical `return true;` body (with an updated comment referencing D-4/EDT-04 instead of D-09/assignment, since the old published-only guard is what's retiring) to:

1. `app/Http/Requests/Lecturer/UpdateExamRequest.php:22-25` — current body quoted in full above (`return ! $this->route('exam')->is_published;`)
2. `app/Http/Requests/Lecturer/StoreQuestionRequest.php:22` (same shape, per RESEARCH.md D-4 — not independently re-read this session, but RESEARCH.md's line-level citation is consistent with `UpdateExamRequest`'s confirmed shape)
3. `app/Http/Requests/Lecturer/UpdateQuestionRequest.php:22` (same shape)
4. `app/Http/Controllers/Lecturer/ExamQuestionController.php:149` — **not** a Form Request, an inline `abort_if($exam->is_published, 403);` (D-6's fourth site). Per D-6's locked resolution, relax this to unconditional delete, and route the deletion through `AttemptVoider`: compute `summarize($exam)` before delete, intercept with the confirm-modal if `total > 0` (same `save-exam-changes`-shaped modal per D-6's text), delete the question, call `void($exam)` after, inside the same transaction as EDT-04's other three sites (D-7).

**`ExamController::destroy()`'s own `abort_if($exam->is_published, 403)`** (whole-exam deletion) is explicitly **unchanged** — do not touch it; UI-SPEC §1.B states this outright.

---

### `app/Http/Controllers/Lecturer/ExamController.php` — CLS-06 `unpublish()` guard removal

**Current shape to mirror for the after-state (per RESEARCH.md's Pattern 5, and structurally confirmed by `publish()`'s existing bare-update shape visible in `show.blade.php:41-47`'s form target):** `unpublish()` currently guards on `$exam->attempts()->exists()`; remove that guard block entirely so it becomes a bare `$exam->update(['is_published' => false])`, identical in shape to `publish()`. Toast copy is dictated verbatim by 10-UI-SPEC.md's Copywriting Contract table — do not paraphrase.

---

### `resources/views/lecturer/exams/show.blade.php` — the delete-confirm pattern to copy for both new modals (full file read above)

**Analog block, quoted in full** (`show.blade.php:48-62`):
```blade
<div x-data class="contents">
    <form method="POST" action="{{ route('lecturer.exams.destroy', $exam) }}" x-ref="deleteExamForm" @submit.prevent="$dispatch('open-modal', 'delete-exam')">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400 text-sm">{{ __('Delete') }}</button>
    </form>

    <x-confirm-modal
        name="delete-exam"
        :title="__('Delete exam?')"
        :body="__('This permanently removes “:name” and all its questions. This cannot be undone.', ['name' => $exam->name])"
        confirm-label="Delete"
        x-on:click="$refs.deleteExamForm.submit()"
    />
</div>
```

This is the exact `x-data` + `@submit.prevent="$dispatch('open-modal', ...)"` + named `x-ref` + `<x-confirm-modal ... x-on:click="$refs.<ref>.submit()" />` wiring both the CLS-07 "Reset submissions" trigger and the EDT-04 "Save changes" interception must reuse verbatim, per 10-UI-SPEC.md's own instruction ("mirroring the existing delete-form interception pattern in `show.blade.php:49`"). `<x-confirm-modal>`'s prop contract (`name`, `title`, `body`, `confirm-label`, `cancel-label`, `danger`) is confirmed by the full component read above — both new invocations pass `danger` (default `true`, no override needed) and dynamic `title`/`body` built server-side from `AttemptVoider::summarize()`'s counts, per UI-SPEC's copy table.

**Deleted block in the same file** — the "Assign to sections" panel, `show.blade.php:125-156` in the current file (quoted in full above): the whole `<div>` containing the heading, the section checkboxes loop, and the `Update assignment` form posting to `lecturer.exams.assignment.update`. Remove entirely; replace its position in the vertical flow with the new "Submissions" panel per UI-SPEC §1.D.

---

### `database/seeders/DatabaseSeeder.php` — off the pivot (full file read above)

Single line to remove: `seedExam()`'s closing `$exam->sections()->sync([$section->id]);` (current file, inside the `seedExam()` method, right before `return $exam;`) and its accompanying comment block ("exam_section pivot — sync() is safe to always re-run..."). No other change needed — `seedExam()` already receives `$section` scoped to `$mathematics` (the same `$mathematics` subject `$exam` is created under, via `Exam::firstOrCreate(['subject_id' => $mathematics->id, ...])`), so the seeder's demo data is **already same-subject-correct** and needs zero fixture restructuring — only the now-meaningless pivot-sync call is dead code once D-1 lands. This is a good confirmation that the seeder was already written defensively against exactly the trap the Factory Trap warns about, unlike most of the test suite.

The `$section` parameter can likely be dropped from `seedExam()`'s signature entirely once the sync call is gone (it becomes unused) — flag this as a small cleanup for the executor, not a required behavior change.

---

## Shared Patterns

### Explicit-service-over-model-event discipline
**Source:** `app/Services/AttemptGrader.php`'s class-level doc comment (quoted in full above)
**Apply to:** `AttemptVoider` — copy the same rationale into its own class doc comment (never delete via an Eloquent `deleting`/`deleted` event on `Attempt`).

### Row-lock-then-act discipline
**Source:** `app/Models/Attempt.php::lockAndFinalize()` (`DB::transaction()` + `lockForUpdate()` + explicit post-read guard)
**Apply to:** `AttemptVoider::void()`, and the D-5 fix in `AnswerGradeController::update()`.

### `<x-confirm-modal>` / `<x-toast>` wiring
**Source:** `resources/views/lecturer/exams/show.blade.php:48-62` (modal), `session('status')`/`session('error')` flash keys (toast, read by `layouts/app.blade.php`'s existing `<x-toast>` include — not re-read this session but confirmed present by both RESEARCH.md and the exception's own render() branch using `session('error')`)
**Apply to:** every new destructive-action view in this phase (CLS-07 trigger, EDT-04 interception on 3 forms).

### `AssignExamRequest`-style unconditional `authorize()`
**Source:** `app/Http/Requests/Lecturer/AssignExamRequest.php:18-21`
**Apply to:** `UpdateExamRequest`, `StoreQuestionRequest`, `UpdateQuestionRequest` (D-4), and the inline `abort_if` in `ExamQuestionController::destroy()` (D-6).

### Same-subject fixture pinning
**Source:** `tests/Feature/Student/AttemptAvailabilityTest.php::enrolledStudentFor()` (`Section::factory()->create(['subject_id' => $exam->subject_id])`)
**Apply to:** every rewritten test fixture across the ~20-file blast radius, and the production query in `Student\ExamController::show()`.

---

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `database/migrations/*_drop_exam_section_table.php` | migration | schema | No prior DROP migration exists in this repo (all 14 existing migrations are `create_*_table`) — first schema-break migration. Use Laravel's own `Schema::dropIfExists()` idiom (shown above), no in-repo shape to copy beyond that. |

Every other new/modified file has a direct, already-shipped analog in this codebase (see table above) — this phase is deliberately scoped to avoid introducing anything structurally novel.

## Metadata

**Analog search scope:** `app/Services`, `app/Models`, `app/Http/Controllers/Lecturer`, `app/Http/Controllers/Student`, `app/Http/Requests/Lecturer`, `app/Exceptions`, `database/migrations`, `database/factories`, `database/seeders`, `resources/views/components`, `resources/views/lecturer/exams`, `tests/Feature/Student`, `tests/Feature/Lecturer`
**Files scanned (full reads this session):** `AttemptGrader.php`, `Attempt.php`, `AnswerGradeController.php`, `AttemptVanishedException.php`, `Exam.php`, `AttemptFactory.php`, `exam_section` migration, `attempts` migration, `DatabaseSeeder.php`, `confirm-modal.blade.php`, `UpdateExamRequest.php`, `AssignExamRequest.php`, `show.blade.php` (lecturer exam detail), `AttemptAvailabilityTest.php` (partial), `ExamAccessTest.php`, `ExamIndexTest.php` (partial), plus 10-CONTEXT.md / 10-RESEARCH.md / 10-VALIDATION.md / 10-UI-SPEC.md
**Pattern extraction date:** 2026-07-17

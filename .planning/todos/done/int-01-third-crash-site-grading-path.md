---
id: int-01-third-crash-site-grading-path
created: 2026-07-17
source: Phase 9 orchestrator verification (post 09-05)
resolves_phase: 10
severity: medium
type: bug
requirement_refs: [INT-01, INT-02, CLS-07]
threat_ref: T-09-01
---

# Third unguarded `lockForUpdate()->first()` on `attempts` — lecturer grading path

## What

`app/Http/Controllers/Lecturer/AnswerGradeController.php:29` performs a locked read on `attempts`
that is **not null-guarded**:

```php
$locked = Attempt::whereKey($attempt->id)->lockForUpdate()->first();  // may be null
$answer->update(['score' => $request->validated('score')]);
app(AttemptGrader::class)->syncStatus($locked);                        // TypeError when null
```

`AttemptGrader::syncStatus(Attempt $attempt): void` (`app/Services/AttemptGrader.php:69`) has a
**non-nullable** type hint, so a vanished row produces a `TypeError` → hard 500 for the lecturer.
The surrounding `DB::transaction()` rolls back, so this is a crash, not data corruption.

## Why it wasn't caught in Phase 9

Phase 9's INT-01 is scoped by its own wording to `Attempt::lockAndFinalize()`, and by its success
criterion to *"a student's in-flight **autosave** or timer request"*. Plan 09-05 correctly guarded
both student-path sites (`app/Models/Attempt.php:141` and
`app/Http/Controllers/Student/AttemptController.php:178`) and correctly marked INT-01 complete.
The lecturer grading path is a third site of the same class that no source artifact named — found by
the orchestrator grepping `lockForUpdate()->first()` across `app/` while verifying 09-05.

## Reachability — CORRECTED 2026-07-17 (by the Phase 9 security auditor)

**An earlier revision of this file claimed the bug was "latent today — nothing currently deletes an
attempt row." That was WRONG.** Verified against the code:

- `database/migrations/*_create_attempts_table.php:17` —
  `$table->foreignId('user_id')->constrained('users')->cascadeOnDelete();`
- `app/Http/Controllers/ProfileController.php:53` — Breeze's stock, unmodified `destroy()` calls
  `$user->delete()`, letting any authenticated student delete their own account.

So a student deleting their account **today** cascades through to their `attempts` (and `answers`)
rows. **The crash site is reachable in currently-shipped code**, not only once Phase 10 lands reset.

**Severity remains medium, not high** (assessed by the Phase 9 security auditor under
`security_block_on: high`; it did not block Phase 9):
- No confidentiality or integrity impact — the surrounding `DB::transaction()` rolls back cleanly, and
  the cascade delete is itself correct.
- Narrow availability impact — one lecturer request returns a 500 instead of a friendly error.
- No auth bypass.
- Requires a tight race: a lecturer saving a grade at the same moment that specific student deletes
  their account.

## Why it belongs to Phase 10

- Phase 9's threat model **T-09-01** mandates an explicit null-check after **EVERY**
  `lockForUpdate()->first()` on `attempts`. One site remains unguarded, so T-09-01 is not fully
  mitigated repo-wide.
- Phase 10 owns the attempt lifecycle (INT-02/INT-03/CLS-07: cancel/reset submissions), and its reset
  feature **widens** this from a narrow account-deletion race into a routine one: a lecturer saving a
  grade while an exam reset commits underneath them.
- The guard belongs with the feature that makes it routine.

## How to close

Apply the same guard shape 09-05 established — `App\Exceptions\AttemptVanishedException`, which
already self-renders (422 JSON for the answer route, redirect + `session('error')` flash otherwise).
The grading path is a normal form POST, so it takes the redirect+flash branch.

Add a regression test alongside `tests/Feature/AttemptNullGuardTest.php` covering the grading route,
using the same `Gate::after` seam 09-01 used to delete the row after route-model binding but before
the locked read.

Also worth deciding in Phase 10's discuss: whether `attempts` reads should funnel through a single
guarded helper (e.g. `Attempt::lockOrFail()`), so a fourth site can't silently reintroduce this.

## Note

`app/Http/Controllers/Student/EnrollmentController.php:56` also does an unguarded
`lockForUpdate()->first()`, but on `Section` — a different model, outside INT-01/T-09-01's scope
(nothing deletes sections mid-request). Recorded only so a future reader doesn't re-flag it.

## Resolution (2026-07-19, milestone close)
Already fixed in Phase 10 (10-04, D-5): AnswerGradeController::update() throws AttemptVanishedException before the write (app/Http/Controllers/Lecturer/AnswerGradeController.php:49-51); regression covered by tests/Feature/AttemptNullGuardTest.php (Site 3). Todo was never filed out of pending/. Closed as resolved.

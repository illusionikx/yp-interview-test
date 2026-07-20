# Phase 10: Exam Integrity — Auto-Assignment & Attempt Lifecycle - Research

**Researched:** 2026-07-17
**Domain:** Laravel 11 exam-visibility predicate rewrite + a shared destructive-voiding service, on a shipped, tested (340-passing) Breeze/MySQL codebase — no new libraries.
**Confidence:** HIGH (every finding below is grounded in a direct read of this repo's models/controllers/requests/policies/migrations/tests, not generic Laravel advice)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**D-1: Auto-assignment scope — DROP the `exam_section` pivot (CLS-05, INT-04).** Derive exam visibility from subject enrollment, not from an assignment pivot. Rewrite `Exam::scopeVisibleTo()` to walk `exam.subject_id` → that subject's sections → enrollments where `status = Enrolled`, never through a per-exam section list. Remove `Exam::sections()` and the exam-assignment UI/controller/routes. FIX-03 is satisfied by removal (the buggy screen no longer exists) — do not fabricate a toaster for it.

**D-2: Attempt cancel/reset mechanism — HARD DELETE (INT-02, INT-03, CLS-07, EDT-04).** `answers.attempt_id` is `cascadeOnDelete()` — deleting an attempt permanently destroys its answers and their graded scores. No undo, no audit trail. This is compatible with INT-02 because INT-02 forbids *silent* destruction, not destruction — the warning is what makes it non-silent. INT-03 falls out for free: deleting the row releases `attempts.unique(exam_id, user_id)`, so the student can simply start again — **no migration to the unique key is needed.** Do **not** build `voided_at`, `attempt_number`, or any soft-delete column — that alternative was explicitly rejected. Warning copy must differentiate "N students have started but not finished" from "N students have been graded — their scores will be permanently deleted." One shared, lock-guarded voiding service (mirroring `AttemptGrader`) — never duplicate the delete across two controllers; its delete must take the same row lock `Attempt::lockAndFinalize()` takes. Consumes Phase 9's `<x-confirm-modal>` (blocking) — not the toast.

**D-3: Reset granularity — PER-EXAM ONLY (CLS-07).** No per-student affordance. Deferred beyond v3.0.

**D-4: The published-edit gate must relax — THREE Form Requests.** `UpdateExamRequest::authorize()`, `StoreQuestionRequest::authorize()`, `UpdateQuestionRequest::authorize()` (all `return ! $this->route('exam')->is_published;`) must all relax for EDT-04. Relaxing only `UpdateExamRequest` leaves question add/edit still blocked — a half-shipped EDT-04.

**D-5: MANDATORY — close the third INT-01 crash site.** `AnswerGradeController.php:29`'s unguarded `lockForUpdate()->first()` feeds non-nullable `AttemptGrader::syncStatus(Attempt $attempt)` → TypeError → 500. D-2's hard delete promotes this from a narrow race to a routine one (a lecturer resets an exam while another lecturer grades it). Apply Phase 9's `App\Exceptions\AttemptVanishedException` guard shape. Add a regression test using the same `Gate::after` seam `tests/Feature/AttemptNullGuardTest.php` uses.

### Claude's Discretion

- The service's name/shape (mirroring `AttemptGrader`), the migration's exact form, and the CLS-06 draft/active toggle's implementation.
- Whether `Exam::sections()` is removed outright or kept as a `subject->sections` convenience — as long as no code path can scope an exam to a section of a different subject.

### Deferred Ideas (OUT OF SCOPE)

- Per-student reset granularity — only "if per-exam proves insufficient."
- Soft-void / attempt history / audit trail — the rejected D-2 alternative. Do not smuggle it back in as `voided_at`, `attempt_number`, or a soft-delete column.
- A shared `Attempt::lockOrFail()` helper — worth considering once D-5 makes three guarded sites, but not required this phase.
- The two-tab lecturer workspace UI — Phase 12; it consumes this phase's service, does not rebuild it.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| INT-02 | Cancelling/resetting attempts never destroys a graded result silently | §3 Voiding Service, §4 Counts — the warning's counts must be exact |
| INT-03 | A student whose attempt was cancelled/reset can take the exam again | §3 confirms `attempts.unique(exam_id,user_id)` is released by hard delete; `AttemptController::store()`'s existing `firstOrCreate` needs zero changes |
| INT-04 | No exam ever visible/takeable to a student in a different subject's class | §1 `scopeVisibleTo()` rewrite + §6 negative-test pattern (mirrors `ExamVisibilityRegressionTest`) |
| CLS-05 | Auto-assignment: every enrolled student sees every active exam in the subject | §1 `scopeVisibleTo()` rewrite |
| CLS-06 | Lecturer toggles exam draft/active, both directions, even post-attempt | §5 — remove `unpublish()`'s attempts-exist guard; no other side effects |
| CLS-07 | Lecturer resets an exam's submissions, behind a warning | §3 Voiding Service, §4 Counts, §7 UI wiring |
| EDT-04 | Saving an edit to an attempted exam warns first, then voids attempts | §2 gate relaxation (3 Form Requests + a 4th site, see §2.1), §3 Voiding Service |
| FIX-03 | "Update assignment" feedback bug | Satisfied by removal — §1, no code needed |
</phase_requirements>

## Summary

This phase is a predicate rewrite plus a shared destructive service, not new infrastructure — no new
Composer packages, no new Blade components (Phase 9 already shipped `<x-confirm-modal>` and `<x-toast>`
for exactly this). The two hard technical problems are (1) `Exam::scopeVisibleTo()`'s rewrite and its
**test-fixture blast radius** — at least 9 Feature test files build a "visible exam" fixture via
`Section::factory()->create()` + `Exam::factory()->create()` independently, which today only work
because the `exam_section` pivot sync ignores subject match; once visibility becomes subject-derived,
every one of those fixtures must explicitly tie `section.subject_id` to `exam.subject_id` or the test
silently starts failing (some already do this correctly — `AttemptAvailabilityTest`'s
`enrolledStudentFor()` helper is the model to copy); and (2) the voiding service's lock discipline, which
must mirror `Attempt::lockAndFinalize()`'s `SELECT ... FOR UPDATE` shape exactly so a racing
autosave/finalize can't interleave with a lecturer's reset/edit-triggered delete.

A third, non-obvious finding: the UI-SPEC's `$graded`/`$submittedUngraded` counts map **exactly** onto
the existing `attempts.status` enum values (`in_progress` / `submitted` / `graded`) that
`AttemptGrader::syncStatus()` already maintains — there is no `graded_at`/`graded_by` column in this
schema (confirmed via `AttemptFactory::graded()`'s own doc comment), so a single `GROUP BY status`
aggregate query computes all five counts in one round trip. No loop, no N+1, no new column.

**Primary recommendation:** Rewrite `scopeVisibleTo()` to `whereHas('subject.sections.enrollments', ...)`
using the *existing* `Exam::subject()` → `Subject::sections()` → `Section::enrollments()` relation chain
(no new relation methods needed); build one `App\Services\AttemptVoider` class mirroring
`AttemptGrader`'s shape with `summarize(Exam $exam): array` and `void(Exam $exam): int`, both driving off
a single grouped-count query; fix `AnswerGradeController` and relax the three Form Requests in the same
pass since they share the same underlying "an attempted exam is no longer immutable" behavior change.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Exam visibility (subject-derived) | API / Backend (Eloquent scope) | Database (FK/enrollment rows) | Single predicate in `Exam::scopeVisibleTo()`, consumed by both the student list and `ExamPolicy::takeable()` — must never diverge, per this codebase's own established discipline |
| Draft/active toggle | API / Backend (controller) | — | Trivial boolean flip; no other tier involved |
| Attempt voiding (hard delete) | API / Backend (service + DB transaction) | Database (cascade FK) | Must run inside the same row-lock discipline as the student-facing timer/autosave path — a backend/DB-tier concern, not a UI concern |
| Destructive-warning counts | API / Backend (aggregate query) | Browser (confirm-modal rendering) | Counts are computed once server-side and passed to the view; the modal is purely a rendering/interaction concern |
| Published-edit gate relaxation | API / Backend (Form Request `authorize()`) | — | Server-side authorization only; Blade `@unless` wrappers are UX-only per this codebase's own established convention |
| Retake after reset | API / Backend (existing `firstOrCreate`) | Database (unique constraint) | No change needed — the existing code path already handles a missing row correctly |

## Standard Stack

No new libraries. Everything in this phase is Laravel-native: Eloquent relations/scopes, `DB::transaction()`
+ `lockForUpdate()`, Form Requests, and Blade components already shipped in Phase 9. CLAUDE.md's
"NO new Composer/npm dependencies" constraint is trivially satisfied because nothing in scope needs one.

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel | 11.55.0 (installed, verified via `php artisan --version`) [VERIFIED: local `php artisan --version`] | Framework | Already the project's mandated stack |
| MySQL (via Herd) | project DB `yp-student-exam` | Persistence, row locking for the voiding service | `lockForUpdate()`/`DB::transaction()` are already the established pattern (`Attempt::lockAndFinalize()`) |

### Supporting

None — this phase is entirely composed of existing model/controller/service patterns already present in the codebase.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| One shared `AttemptVoider` service | A Form Request-embedded delete, or duplicated logic in `ExamController`/`ExamQuestionController` | Rejected by D-2's explicit mandate — "never duplicate the delete across two controllers" |
| `whereHas('subject.sections.enrollments', ...)` nested relation | A raw SQL join or a new `HasManyThrough` relation | The existing `Exam::subject()`/`Subject::sections()`/`Section::enrollments()` relations already compose into exactly this chain — no new relation code needed |
| Grouped aggregate `COUNT ... GROUP BY status` for the 5 warning counts | Looping `$exam->attempts` in PHP, or 3 separate `->count()` calls | One query beats 3+ queries or an N-row load; matches this codebase's own established "bounded aggregate, never a PHP loop" precedent (`ARCHITECTURE.md` §4) |

**Installation:** None required.

**Version verification:** N/A — no packages added.

## Package Legitimacy Audit

**Not applicable.** This phase installs zero external packages (Composer or npm). CLAUDE.md's hard
constraint ("NO new Composer/npm dependencies") is satisfied trivially — every capability in scope is
built from Laravel primitives already present in `composer.json`/`package.json`.

| Package | Registry | Age | Downloads | Source Repo | Verdict | Disposition |
|---------|----------|-----|-----------|-------------|---------|-------------|
| — | — | — | — | — | — | No packages installed this phase |

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

## Architecture Patterns

### System Architecture Diagram

```
[Lecturer browser]
   |
   |--- PATCH exams/{exam}/publish|unpublish ---------> ExamController::publish()/unpublish()
   |                                                       (D-1: unpublish() drops its attempts-exists guard;
   |                                                        both just flip is_published; no attempt data touched)
   |
   |--- PATCH/POST "Reset submissions" (new) -----------> AttemptVoider::void($exam)   [new, exam-scoped]
   |--- PUT exam edit / POST|PUT question (existing) ---> UpdateExamRequest/StoreQuestionRequest/
   |     routes, now unblocked on published exams          UpdateQuestionRequest::authorize() -> true
   |     (D-4)                                             then controller calls
   |                                                         AttemptVoider::summarize($exam) pre-save (build
   |                                                         warning) and AttemptVoider::void($exam) post-save
   |                                                         IF attempts existed (D-2)
   |                                                            |
   |                                                            v
   |                                              DB::transaction {
   |                                                SELECT * FROM attempts WHERE exam_id=? FOR UPDATE
   |                                                DELETE FROM attempts WHERE id IN (locked ids)
   |                                                -- cascades to `answers` via FK, destroying graded scores
   |                                              }
   |                                                            |
   |                                                            v
   |                                              A racing student request's
   |                                              Attempt::lockAndFinalize() (SELECT ... FOR UPDATE on the
   |                                              same row) blocks until this transaction commits, then
   |                                              re-reads, finds no row, throws AttemptVanishedException
   |                                              (already built, Phase 9) -> safe redirect/422, never a 500
   |
[Student browser]
   |
   |--- GET student.exams.index --------> Exam::scopeVisibleTo($user)
   |                                        ::where('is_published', true)
   |                                        ::whereHas('subject.sections.enrollments', enrolled-this-user)
   |                                        (D-1 — subject-derived, no exam_section pivot)
   |
   |--- GET student.exams.show/{exam} --> ExamPolicy::takeable() delegates to the SAME scopeVisibleTo()
   |                                        predicate — list and gate can never diverge (INT-04)
   |
   |--- POST student.attempts.store ----> Attempt::firstOrCreate(['exam_id'=>.., 'user_id'=>..], ...)
   |                                        (INT-03 — works unmodified once the old row is gone; the
   |                                         unique(exam_id,user_id) index is what makes this safe)
```

### Recommended Project Structure

No new directories. New file:

```
app/Services/
├── AttemptGrader.php     # existing — the precedent this phase mirrors
└── AttemptVoider.php     # NEW — summarize() + void(), same shape as AttemptGrader
```

Modified files (no new controllers/routes beyond one, per UI-SPEC §7):

```
app/Models/Exam.php                          # scopeVisibleTo() rewrite, sections() removed/replaced
app/Models/Section.php                       # exams() BelongsToMany removed (depends on dropped pivot)
app/Http/Controllers/Lecturer/ExamController.php          # unpublish() guard removed; show() drops $sections/assignment
app/Http/Controllers/Lecturer/AnswerGradeController.php   # D-5 null-guard
app/Http/Controllers/Lecturer/ExamQuestionController.php  # destroy() published-gate — see §2.1 open question
app/Http/Controllers/Student/ExamController.php           # $enrolledSection derivation rewritten off subject_id
app/Http/Requests/Lecturer/UpdateExamRequest.php           # authorize() -> true
app/Http/Requests/Lecturer/StoreQuestionRequest.php        # authorize() -> true
app/Http/Requests/Lecturer/UpdateQuestionRequest.php       # authorize() -> true
database/migrations/2026_07_15_100008_create_exam_section_table.php   # DELETED (project's in-place-edit convention)
resources/views/lecturer/exams/show.blade.php              # "Assign to sections" panel deleted; "Submissions" panel added
resources/views/lecturer/exams/edit.blade.php               # EDT-04 interception wiring
resources/views/lecturer/exams/questions/_form.blade.php    # EDT-04 interception wiring
resources/views/lecturer/exams/questions/edit.blade.php     # EDT-04 interception wiring

# REMOVED entirely
app/Http/Controllers/Lecturer/ExamAssignmentController.php
app/Http/Requests/Lecturer/AssignExamRequest.php
routes/lecturer.php: the `exams.assignment.update` route line
tests/Feature/Lecturer/ExamAssignmentTest.php
```

### Pattern 1: `scopeVisibleTo()` rewrite — the correct Eloquent shape

**What:** Replace the pivot-walking predicate with a nested `whereHas` through the *already-existing*
relation chain — `Exam belongsTo Subject`, `Subject hasMany Section` (`app/Models/Subject.php:18`),
`Section belongsToMany User via Enrollment` (`app/Models/Section.php:44`). No new relation method is
required on any model for this predicate itself.

**When to use:** This is the phase's single most load-bearing change — every other visibility-dependent
code path (student list, `ExamPolicy::takeable()`, and Phase 13's future "Class" page) reads through it.

**Example:**
```php
// app/Models/Exam.php — replace the existing scopeVisibleTo() body
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
This requires `Exam::subject(): BelongsTo` (already exists, `app/Models/Exam.php:39`) and
`Subject::sections(): HasMany` (already exists, `app/Models/Subject.php:18`) — `whereHas` correctly
traverses a `belongsTo -> hasMany -> belongsToMany` chain via dotted relation names; this is standard
Eloquent, not a novel pattern for this codebase (the existing predicate already used a two-level
`sections.enrollments` chain — this just inserts `subject.` ahead of it and removes the pivot hop).
[CITED: Laravel 11.x Eloquent relationships docs — nested `whereHas` dot-notation across
belongsTo→hasMany→belongsToMany is documented, standard behavior]

**Keep the existing doc comment's warnings intact** (do not fold `isAvailableNow()`/`availabilityState()`
into this predicate — AVL-04 already established why not, and that reasoning is untouched by this phase).

### Pattern 2: The `$enrolledSection` consumer in `Student\ExamController::show()` — no relation needed

**What:** `app/Http/Controllers/Student/ExamController.php:87-92` currently derives "the section both
assigned to this exam and the student is enrolled in" via `$exam->sections()->whereHas('enrollments', ...)`.
Once `exam_section` is dropped, this becomes "any section of this exam's subject the student is enrolled
in" — a **simpler** query, not requiring `Exam::sections()` to exist at all:

```php
// app/Http/Controllers/Student/ExamController.php
$enrolledSection = \App\Models\Section::where('subject_id', $exam->subject_id)
    ->whereHas('enrollments', fn ($q) => $q
        ->where('user_id', $request->user()->id)
        ->where('status', EnrollmentStatus::Enrolled)
    )
    ->first();
```
This resolves the "Claude's Discretion" question — **remove `Exam::sections()` and `Section::exams()`
entirely** (both `BelongsToMany` relations depend on the dropped `exam_section` table and would SQL-error
if called). The only two production call sites of `$exam->sections()` are
`ExamAssignmentController::update()` (deleted wholesale) and this one (rewritten to a direct `Section`
query as shown) — grep-confirmed, no other consumer exists in `app/`.

### Pattern 3: The shared voiding service — mirroring `AttemptGrader`

**What:** `app/Services/AttemptGrader.php` is the explicit precedent: a plain service class, one public
entry point per concern, invoked from exactly the call sites that need it, never a model event. Build
`App\Services\AttemptVoider` with the identical shape:

```php
// app/Services/AttemptVoider.php
namespace App\Services;

use App\Models\Attempt;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;

class AttemptVoider
{
    /**
     * The five UI-SPEC counts, computed from ONE grouped aggregate query —
     * attempts.status already IS the graded/ungraded distinction
     * (AttemptGrader::syncStatus() only flips to 'graded' once every open
     * answer has a score), so no per-answer graded_at/graded_by lookup is
     * needed or exists in this schema.
     */
    public function summarize(Exam $exam): array
    {
        $counts = Attempt::where('exam_id', $exam->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $inProgress = (int) ($counts['in_progress'] ?? 0);
        $submittedUngraded = (int) ($counts['submitted'] ?? 0);
        $graded = (int) ($counts['graded'] ?? 0);

        return [
            'inProgress' => $inProgress,
            'submittedUngraded' => $submittedUngraded,
            'graded' => $graded,
            'notYetGraded' => $inProgress + $submittedUngraded,
            'total' => $inProgress + $submittedUngraded + $graded,
        ];
    }

    /**
     * Hard-deletes every attempt on $exam (D-2). Answers cascade-delete at
     * the DB FK layer — no manual answer cleanup needed. Locks every
     * matching row with SELECT ... FOR UPDATE BEFORE deleting, inside the
     * same transaction, mirroring Attempt::lockAndFinalize()'s
     * lock-then-act shape — a racing student's own lockForUpdate() read on
     * any of these rows blocks until this transaction commits, then
     * observes a vanished row and throws AttemptVanishedException (Phase 9,
     * already built) instead of crashing.
     */
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
}
```

**Why lock-then-delete, not a bare `->delete()`:** MySQL/InnoDB's `DELETE` statement does take implicit
row locks as it executes, but explicitly locking via `SELECT ... FOR UPDATE` first, in the same
transaction, matches this codebase's own established idiom (`Attempt::lockAndFinalize()`,
`app/Models/Attempt.php:141`) and makes the serialization point unambiguous: whichever transaction's
`FOR UPDATE` read commits first wins; the other blocks until then. This is the "same row lock" D-2
explicitly requires. [VERIFIED: direct read of `Attempt::lockAndFinalize()`'s existing, tested pattern]

**Call sites (both, per D-2 — "never duplicate the delete across two controllers"):**
- CLS-07's "Reset submissions" button — a new small controller action that calls `void()` directly (no
  intermediate confirmation state needed server-side; the confirm-modal is client-side friction only,
  exactly like the existing "Delete exam?"/"Delete question?" modals).
- EDT-04's exam/question save path — the controller calls `summarize($exam)` **before** the save to know
  whether to render the confirm-modal (client round-trip) or save directly, and calls `void($exam)`
  **after** a successful save, but only when `summarize()->total > 0` was true at the start of the
  request. Order matters: void AFTER save, not before, so a validation failure on the edit never
  destroys attempts for a save that didn't actually happen.

### Pattern 4: The published-edit gate relaxation

**What:** All three Form Requests currently gate `authorize()` on `! $exam->is_published`. Per D-4,
relax all three. Given this codebase has no per-exam lecturer-ownership check anywhere in the existing
`ExamController`/`ExamQuestionController` flow (any lecturer may manage any exam — the ownership
boundary in this app is at the *subject* level via `subject_user`, established in Phase 2/3, not at the
per-exam `created_by` level), the simplest, most in-character replacement is the same
`return true;` idiom `AssignExamRequest::authorize()` already uses with its own explanatory comment
("no per-record ownership applies to exams here — D-09"):

```php
// UpdateExamRequest.php / StoreQuestionRequest.php / UpdateQuestionRequest.php
public function authorize(): bool
{
    // D-4: the draft-only mutation gate (D-06) is retired this phase —
    // editing a published/attempted exam is now allowed; EDT-04's
    // destructive-warning flow (AttemptVoider) is what replaces the old
    // "can't touch it" protection. See AssignExamRequest::authorize()
    // for the identical "no per-record ownership" precedent this mirrors.
    return true;
}
```

#### §2.1 — Open question: is there a FOURTH site, not named in D-4?

D-4 names exactly three Form Requests. But `10-UI-SPEC.md` §1.B states: *"Per-question 'Edit'/'Delete'
links (currently `@unless ($exam->is_published)`-gated) become always visible too."* The per-question
**Delete** action is `ExamQuestionController::destroy()`, which enforces its published-gate **inline**,
not via a Form Request: `abort_if($exam->is_published, 403)` at `app/Http/Controllers/Lecturer/
ExamQuestionController.php:149`. D-4's text only lists the three `authorize()` gates; it does not
mention this inline `abort_if`. If the planner makes the Delete link "always visible" per the UI-SPEC
literally, without also relaxing this fourth gate, clicking Delete on a published exam's question will
403 — a UI/backend contract mismatch. **Recommendation for the planner:** treat this as the fourth site
needing relaxation, and treat "deleting a question" as another EDT-04 trigger (compute
`summarize($exam)` before delete, intercept with the same `save-exam-changes`-shaped confirm-modal if
`total > 0`, then delete the question and call `void($exam)` after). This is not explicitly locked by
CONTEXT.md's D-4 — flag it to the user/plan-checker as a judgment call resolving an apparent gap between
D-4's text and the UI-SPEC's rendering contract, not a silent assumption.

`ExamController::destroy()`'s `abort_if($exam->is_published, 403)` (whole-exam deletion) is explicitly
**unchanged** per UI-SPEC §1.B — do not touch it.

### Pattern 5: CLS-06 — the draft/active toggle

**What:** `ExamController::unpublish()` (`app/Http/Controllers/Lecturer/ExamController.php:133-142`)
currently refuses to run if `$exam->attempts()->exists()`. Delete that guard block entirely — the method
becomes a bare `$exam->update(['is_published' => false])`, identical in shape to `publish()`. No other
side effects exist today: both methods only ever touch the `is_published` column. Toggling does not
interact with `AttemptVoider` at all — attempts are untouched by a status flip in either direction,
confirmed by direct read (neither method references `attempts` after the guard is removed).

```php
public function unpublish(Exam $exam): RedirectResponse
{
    $exam->update(['is_published' => false]);

    return back()->with('status', __('Exam moved back to draft. Students can no longer start it, but existing attempts are unaffected.'));
}
```

### Anti-Patterns to Avoid

- **Voiding attempts before validating the save.** If `EDT-04`'s controller action calls `void()`
  before running the Form Request's `rules()`, a validation failure on the edit would still have
  destroyed the students' attempts for nothing. Void only after a successful `update()`/`create()`.
- **Computing the five counts with a loop over `$exam->attempts` in Blade or PHP.** Use the single
  grouped aggregate query in Pattern 3 — this codebase's own `ARCHITECTURE.md` §4 already establishes
  "bounded aggregate, never a PHP loop" as house style.
- **Re-deriving visibility conditions anywhere except `scopeVisibleTo()`.** `Exam.php`'s own doc comment
  on the method already states this discipline explicitly (`app/Models/Exam.php:73-77`) — the new
  "Class" page (Phase 13) and any dashboard card (Phase 11) referencing exam visibility must call this
  same scope, never re-derive `is_published`/enrollment conditions inline.
- **Skipping the `lockForUpdate()` read before `Attempt::whereIn(...)->delete()`.** A bare
  `Attempt::where('exam_id', $exam->id)->delete()` with no prior lock is not proven-safe against a
  racing `Attempt::lockAndFinalize()` transaction in the same explicit, auditable way the codebase's own
  precedent establishes — always lock first, in the same transaction, per Pattern 3.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Destructive-action confirmation | A new modal/dialog component | Phase 9's `<x-confirm-modal>` (`resources/views/components/confirm-modal.blade.php`) | UI-SPEC explicitly forbids a new component this phase; the existing one already supports dynamic title/body/confirm-label/danger via props |
| Post-action feedback | A new flash/toast mechanism | Phase 9's `<x-toast>`, reading `session('status')`/`session('error')` | Already wired app-wide; `session('success')` has 0 call sites — use `status`/`error` only |
| "Attempt row vanished mid-request" handling | A new exception type or ad hoc null-check-then-500 | `App\Exceptions\AttemptVanishedException` (Phase 9, self-rendering) | Already handles both the JSON-autosave case and the redirect+flash case correctly; D-5 reuses it verbatim, does not extend it |
| Grading-completeness computation | A new `graded_at`/`graded_by` column pair | `AttemptGrader::syncStatus()`'s existing `attempts.status` enum (`in_progress`/`submitted`/`graded`) | The schema has no such columns (confirmed via `AttemptFactory::graded()`'s own doc comment) — `status` already IS the completeness signal; adding a parallel column would create two sources of truth |

**Key insight:** every piece of infrastructure this phase's UI/UX layer needs (modal, toast, exception
type, count semantics) was **already built** in Phase 9 or is already implicit in the existing `status`
enum. The actual net-new code is small: one service class, three `authorize()` one-liners, one migration
deletion, and a handful of controller/view edits.

## Common Pitfalls

### Pitfall 1: Test fixtures assume `exam_section` sync alone establishes visibility — most will silently start failing

**What goes wrong:** At least 9 Feature test files (`ExamAccessTest`, `ExamIndexTest`,
`ExamVisibilityRegressionTest`, `AttemptNullGuardTest`, `AttemptPolicyTest`, `AttemptSubmitTest`,
`AttemptStartTest`, `AttemptShowTest`, `AttemptAnswerTest`, `Phase4ReviewFixesTest`, plus `ResultTest` /
`GradeAnswerTest` / `AttemptGraderTest` / `Phase5ReviewFixesTest` in lecturer-side tests — grep-confirmed
20 files total reference `exam_section`/`->sections()`/`sections->sync`) build their "visible exam"
fixture as:
```php
$section = Section::factory()->create();   // random subject_id A
$exam = Exam::factory()->published()->create();  // random subject_id B (independent factory call)
$exam->sections()->sync([$section->id]);   // pivot only — no subject check today
$student->... enrolled in $section ...
```
`ExamFactory` and `SectionFactory` **each independently call `Subject::factory()`** — with no shared
seed, two separate factory calls produce two **different** subjects. Today this doesn't matter because
`scopeVisibleTo()` only checks the pivot, not subject match. Once the predicate becomes
`subject.sections.enrollments`, every one of these fixtures produces an exam/section pair in **different
subjects**, and the "visible" test becomes invisible — tests that expected `assertOk()`/`assertSee(...)`
will start failing with 403/empty-list, and tests that expected `assertForbidden()` for a
*deliberately*-different-subject case may pass for the wrong reason (the fixture was already broken,
not deliberately testing cross-subject denial).

**Why it happens:** The `exam_section` pivot was the *only* thing enforcing "same subject" at test-fixture
time; the `AssignExamRequest`'s own subject-scoped validation rule (`Rule::exists('sections',
'id')->where('subject_id', ...)`) only applies to the *production controller path* (`PUT
exams/{exam}/assignment`), not to `$exam->sections()->sync(...)` called directly in a test, which bypasses
validation entirely.

**How to avoid:** Every fixture-builder that pairs a `Section` and an `Exam` for a "student should see
this" test must explicitly tie them: `Section::factory()->create(['subject_id' => $exam->subject_id])`
(or the reverse). `AttemptAvailabilityTest::enrolledStudentFor()` (`tests/Feature/Student/
AttemptAvailabilityTest.php:27-35`) and `ExamAssignmentTest`'s later test methods already do this
correctly — use them as the copy-paste template. For fixtures that deliberately want a cross-subject
*negative* case (INT-04's negative test), do the opposite explicitly: two `Subject::factory()->create()`
calls, one section per subject, assert denial.

**Warning signs:** A test that creates `Section::factory()->create()` and `Exam::factory()->create()` on
consecutive lines with no shared `subject_id` and later expects the student to see the exam.

### Pitfall 2: Voiding on save must happen strictly after a successful write, not before

**What goes wrong:** If the EDT-04 controller path computes `summarize($exam)` and immediately calls
`void($exam)` before running the Form Request's validated `update()`, a request that fails validation
(e.g., a bad `duration_minutes`) still destroys every student's attempt with nothing to show for it — the
worst possible outcome: data destroyed, no edit saved.

**How to avoid:** `summarize()` before the save (to decide "show the warning" and to build the toast
copy afterward), the actual `update()`/`create()` write, THEN `void()` only if
`summarize()->total > 0` was true when the request started. Wrap the write + void as a single
`DB::transaction()` if the controller wants "both happen or neither" atomicity — reasonable, though not
explicitly mandated by CONTEXT.md; flag as a planner judgment call.

**Warning signs:** A diff where `AttemptVoider::void()` is called before the Form Request's `rules()`
have run, or before `$exam->update(...)`/`$question->update(...)` in the same method.

### Pitfall 3: `Attempt::lockAndFinalize()`'s null-guard already exists — do not re-implement it

**What goes wrong:** A planner unfamiliar with Phase 9's actual delivered state might assume INT-01's
null-guard still needs to be built. It does not — `app/Models/Attempt.php:160-162` already throws
`AttemptVanishedException` when a locked re-read returns null, and `tests/Feature/
AttemptNullGuardTest.php` already pins this behavior GREEN. Phase 10's only remaining obligation here is
D-5 (`AnswerGradeController`'s independent, still-unguarded `lockForUpdate()->first()` call), which is a
**different, third** call site, not a re-opening of the already-fixed ones.

**How to avoid:** Read `app/Models/Attempt.php:138-198` before touching anything attempt-lock-related —
the guard shape (throw `AttemptVanishedException`, do not `return false`) is already established and
tested; D-5's fix is a small, mechanical copy of that same shape into `AnswerGradeController::update()`:

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
Because this controller's route is a form `PATCH` that redirects (not JSON, and not
`student.attempts.answer`), `AttemptVanishedException::render()` (`app/Exceptions/
AttemptVanishedException.php:57-76`) automatically falls into its redirect + `session('error')` branch —
no changes to the exception class itself are needed. [VERIFIED: direct read of the exception's existing
`render()` branching logic]

### Pitfall 4: The migration deletion must also drop `Section::exams()` and `Exam::sections()`

**What goes wrong:** Deleting only the migration file (`database/migrations/
2026_07_15_100008_create_exam_section_table.php`) but leaving `Exam::sections(): BelongsToMany` and
`Section::exams(): BelongsToMany` (both reference the `exam_section` table by name) in place means any
code path that still calls either method — including any test not yet rewritten — throws a SQL error
("table doesn't exist") rather than a clean, obvious failure at the Eloquent-relation level.

**How to avoid:** Remove both relation methods in the same change as the migration deletion (see Pattern
2's grep-confirmed callers — `ExamAssignmentController` deleted wholesale, `Student\ExamController::show()`
rewritten). Grep `resources/views` and `tests/` for `->sections()->sync(` and `$exam->sections()` /
`$section->exams()` as a completeness check before considering this slice done — 20 test files currently
reference the pivot in some form; not all of them use `sync()` (some only assert against the
`exam_section` table directly, e.g. `DomainSchemaTest::test_all_domain_tables_exist()`'s table list, which
must drop `'exam_section'` from its assertion array, and `DatabaseSeederTest`'s `$exam->sections()->
whereKey($section->id)->exists()` assertion, which must be replaced entirely).

**Warning signs:** `php artisan test` erroring (not failing — erroring, `Base table or view not found`)
on any test file after the migration is dropped — that's a sign a `->sections()`/`->exams()` call site
was missed.

## Runtime State Inventory

> This phase is not a rename/refactor/migration of an existing identifier — it is a schema/predicate
> change (dropping a pivot table, hard-deleting rows). The rename-specific inventory categories below
> are answered explicitly as "not applicable" per the protocol's requirement to state this rather than
> leave it blank.

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | None — `exam_section` carries no data that needs migrating forward; it is a pure assignment pivot being replaced by a derived predicate, not a renamed/moved table. `attempts`/`answers` rows destroyed by the voiding service are an intentional, warned deletion (D-2), not a migration concern. | None — the table is dropped, not migrated |
| Live service config | None — no n8n/Datadog/Tailscale/Cloudflare-style external config exists in this project | None |
| OS-registered state | None — no Task Scheduler/pm2/launchd/systemd registrations reference `exam_section` or attempt data | None |
| Secrets/env vars | None — no secret or env var name references `exam_section`, `is_published`, or attempt identifiers | None |
| Build artifacts | None — no compiled binary or installed package embeds the dropped table/column names | None |

## Common Pitfalls (continued — project-specific, from `.planning/research/PITFALLS.md`)

The v3.0-level research already catalogued this phase's two highest-risk pitfalls in depth
(`.planning/research/PITFALLS.md` Pitfalls 1 and 2) — this RESEARCH.md's §Pattern 3 and §Pattern 1 above
are the concrete implementations of those pitfalls' "How to avoid" guidance, now grounded in the locked
D-1/D-2 decisions rather than left open. Re-read PITFALLS.md Pitfall 1's "Technical Debt Patterns" table
row on hard-delete before implementing — it independently arrived at the same warning-copy requirement
CONTEXT.md's D-2 locks in.

## Code Examples

### `scopeVisibleTo()` — before and after

```php
// BEFORE (app/Models/Exam.php:97-105)
public function scopeVisibleTo(Builder $query, User $user): Builder
{
    return $query
        ->where('is_published', true)
        ->whereHas('sections.enrollments', fn (Builder $q) => $q
            ->where('user_id', $user->id)
            ->where('status', EnrollmentStatus::Enrolled)
        );
}

// AFTER
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

### The negative-test pattern INT-04 must mirror

`ExamVisibilityRegressionTest` already established the "list and gate must agree" hard-acceptance-gate
pattern for enrollment status; INT-04 needs the equivalent for cross-*subject* denial. Recommended shape
(new test, e.g. `tests/Feature/Student/CrossSubjectVisibilityTest.php`):

```php
public function test_a_student_enrolled_only_in_a_different_subject_cannot_see_or_take_the_exam(): void
{
    $subjectA = Subject::factory()->create();
    $subjectB = Subject::factory()->create();

    $examOnA = Exam::factory()->published()->for($subjectA)->create();

    $sectionOnB = Section::factory()->for($subjectB)->create();
    $student = User::factory()->student()->create();
    $sectionOnB->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

    // LIST — the exam must not appear
    $listVisible = Exam::visibleTo($student)->whereKey($examOnA->id)->exists();
    $this->assertFalse($listVisible);

    // DIRECT ACCESS — the gate must independently deny it
    $response = $this->actingAs($student)->get(route('student.exams.show', $examOnA));
    $response->assertForbidden();

    // START — the write path must independently deny it too
    $startResponse = $this->actingAs($student)->post(route('student.attempts.store', $examOnA));
    $startResponse->assertForbidden();
}
```
This mirrors `ExamVisibilityRegressionTest`'s "assert list and gate agree" shape but adds a third
assertion (attempt-start) since INT-04's stated acceptance criterion explicitly says "see or start" —
the existing regression test only checked list+takeable, not the store() write path.

### The counts feeding both CLS-07's summary line and EDT-04's modal body

```php
$counts = app(AttemptVoider::class)->summarize($exam);
// $counts = ['inProgress' => .., 'submittedUngraded' => .., 'graded' => .., 'notYetGraded' => .., 'total' => ..]
```
Pass this same array to both `lecturer/exams/show.blade.php` (the "Submissions" panel) and
`lecturer/exams/edit.blade.php`/the question forms (the EDT-04 modal) — computed once per request, in
the controller, never twice, per UI-SPEC's explicit "do not let the summary line and the modal body drift
out of sync by being computed twice" instruction.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| Manual per-exam section assignment via `exam_section` pivot + checkbox UI | Subject-derived automatic visibility | This phase (D-1) | Removes an entire controller/request/route/UI block; simplifies the visibility predicate to one relation hop deeper, zero pivot writes |
| Draft-only edit gate (published exam = immutable) | Always-editable, with a destructive-warning-and-void flow replacing the old protection | This phase (D-4/D-2) | The *mechanism* protecting graded scores moves from "can't edit at all" to "edit destroys attempts, with a mandatory warning" — a strictly more permissive but explicitly-warned model |
| `unpublish()` blocked once attempts exist | `unpublish()` unconditional | This phase (CLS-06) | Toggling no longer has any attempt-data interaction at all — purely a visibility flag |

**Deprecated/outdated:** `ExamAssignmentController`, `AssignExamRequest`, the `exam_section` table, and
`ExamAssignmentTest` are all retired this phase, not merely modified.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `ExamQuestionController::destroy()`'s inline `abort_if($exam->is_published, 403)` is a fourth site needing relaxation (not explicitly named in D-4), and question-deletion should route through the same EDT-04 destructive-warning flow | §2.1 | If wrong (i.e., the user intends question-delete to stay published-gated despite the UI-SPEC's "always visible" wording), the planner ships a Delete link that 403s when clicked on a published exam — a broken-looking button. Low-cost to resolve either way (one `abort_if` line), but the *behavior* decision (warn-and-void vs. stay-blocked) should be confirmed, not silently assumed. |
| A2 | `AttemptVoider::void()` should run inside its own `DB::transaction()` (as shown), and the EDT-04 caller should treat "save the edit" and "void attempts" as two potentially-separate transactions (save first, then void), not one combined transaction | §3, Pitfall 2 | If the user actually wants save+void to be atomic (all-or-nothing), a planner following this research literally could leave a window where the edit saved but a subsequent void failed (rare, but possible under a DB error) — low risk given InnoDB's reliability, but worth the planner explicitly deciding rather than defaulting silently. |
| A3 | No per-exam lecturer-ownership check exists anywhere in `ExamController`/`ExamQuestionController` today (verified: no `created_by` comparison found in either controller or their Form Requests), so relaxing `authorize()` to `return true;` (matching `AssignExamRequest`'s existing precedent) does not introduce a NEW authorization gap — it matches the codebase's existing (subject-level, not exam-level) ownership model | Pattern 4 | If a hidden ownership check exists elsewhere that this research missed, `return true;` could over-permission. Grep-verified absent across `app/Http/Controllers/Lecturer/Exam*.php` and `app/Http/Requests/Lecturer/*Exam*Request.php`, `*Question*Request.php` — confidence is HIGH, not zero-risk. |

**If this table is empty:** N/A — three assumptions logged above; none are compliance/security-critical
beyond A3, which is grep-verified against the actual current codebase, not training-data guesswork.

## Open Questions

1. **Does deleting a question on a published, attempted exam trigger EDT-04's warning-and-void flow, or
   does it stay blocked?**
   - What we know: D-4 names three Form Requests; the UI-SPEC says per-question Delete links become
     "always visible" post-publish.
   - What's unclear: whether "always visible" was meant literally to include Delete, or whether the
     UI-SPEC author intended Delete to stay hidden/blocked and only meant Edit.
   - Recommendation: treat as A1 above — resolve explicitly in planning/discuss, default to "relax it
     and route through the same voiding flow" if no answer is available, since that is the
     internally-consistent reading of the UI-SPEC's own words.

2. **Should the save+void be one transaction or two (save, then void)?**
   - What we know: D-2 mandates the void's *delete* takes a row lock matching `lockAndFinalize()`'s
     shape; it does not explicitly mandate the save-then-void sequence be one atomic unit.
   - What's unclear: acceptable failure mode if the save succeeds but the void throws (extremely
     unlikely under normal operation, but worth a stated default).
   - Recommendation: two sequential operations (save, then void, each in its own transaction) is simpler
     to reason about and matches this codebase's existing style (`AttemptGrader::handleFinalized()` is
     called from *inside* `lockAndFinalize()`'s transaction for a different reason — atomicity with the
     status flip specifically, not a general "always wrap everything" precedent). Flag for planner
     discretion.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Application runtime | Yes [VERIFIED: `php -v`] | 8.2.32 | — |
| Laravel | Framework | Yes [VERIFIED: `php artisan --version`] | 11.55.0 | — |
| MySQL (via Herd) | `yp-student-exam` DB, `lockForUpdate()`/transactions | Assumed available (project's documented, existing dev setup; not independently re-verified this session since Herd's local service state is outside a read-only research pass) | — | — |
| Composer / npm | No new packages needed this phase | N/A | — | — |

**Missing dependencies with no fallback:** none identified.
**Missing dependencies with fallback:** none — this phase needs nothing beyond what's already installed
and running for the existing 340-passing test suite.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit via `php artisan test` (existing `phpunit.xml`, no config changes needed) |
| Config file | `phpunit.xml` (existing, unchanged) |
| Quick run command | `php artisan test --filter=<TestClass>` |
| Full suite command | `php artisan test` (baseline: 340 passing, 0 failing at Phase 9 close) |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CLS-05 | Student enrolled in any section of a subject sees every active exam in that subject | Feature | `php artisan test --filter=ExamIndexTest` (rewritten fixtures) | ✅ (rewrite existing) |
| INT-04 | Student in a different subject cannot see or start the exam — list AND direct access AND start | Feature | `php artisan test --filter=CrossSubjectVisibilityTest` | ❌ Wave 0 — new file, see Code Examples |
| CLS-06 | `unpublish()` succeeds even when attempts exist | Feature | `php artisan test --filter=ExamController` (new/existing test method) | Partial — extend existing exam-publish test file |
| CLS-07 | Reset deletes all attempts, warns with correct counts, student can retake | Feature | `php artisan test --filter=AttemptVoider` / new `ResetSubmissionsTest` | ❌ Wave 0 — new file |
| INT-02 | Warning counts are exact (not just "a modal appears") | Unit/Feature | `php artisan test --filter=AttemptVoiderTest` — assert `summarize()`'s five numbers against a hand-built fixture with 1 in_progress, 1 submitted-ungraded, 1 graded | ❌ Wave 0 — new file |
| INT-03 | Student can start again after a reset | Feature | `php artisan test --filter=ResetSubmissionsTest` (retake assertion) | ❌ Wave 0 — new file |
| EDT-04 | Saving an edit to an attempted exam voids attempts, with warning | Feature | `php artisan test --filter=ExamUpdateVoidsAttemptsTest` | ❌ Wave 0 — new file |
| FIX-03 | Satisfied by removal | N/A — no test needed; `ExamAssignmentTest` deletion itself is the evidence | — | — deleted, not tested |
| D-5 | `AnswerGradeController`'s vanished-row guard | Feature | `php artisan test --filter=AttemptNullGuardTest` (extend with a third-site test, same `Gate::after` seam) | Partial — extend existing file |

### Sampling Rate

- **Per task commit:** the relevant `--filter=<TestClass>` quick run
- **Per wave merge:** `php artisan test` (full suite)
- **Phase gate:** full suite green (340 baseline + new tests) before `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/Student/CrossSubjectVisibilityTest.php` — covers INT-04 (list + direct access + start, all three)
- [ ] `tests/Feature/AttemptVoiderTest.php` (or `tests/Unit/`) — covers INT-02's count-correctness (the
      single most safety-critical test in this phase, given D-2's hard delete has no undo)
- [ ] `tests/Feature/Lecturer/ResetSubmissionsTest.php` — covers CLS-07 + INT-03 (reset, then retake)
- [ ] `tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php` — covers EDT-04 (save an attempted exam,
      assert attempts gone, assert warning copy differs at graded=0 vs graded>0)
- [ ] Extend `tests/Feature/AttemptNullGuardTest.php` with a third `Gate::after`-based test targeting
      `AnswerGradeController::update()` specifically (D-5)
- [ ] Rewrite (not delete) `tests/Feature/Student/ExamIndexTest.php`, `ExamAccessTest.php`,
      `ExamVisibilityRegressionTest.php`, `AttemptNullGuardTest.php`'s `attemptFixture()`,
      `AttemptPolicyTest.php`, `AttemptStartTest.php`, `AttemptShowTest.php`, `AttemptAnswerTest.php`,
      `AttemptSubmitTest.php`, `Phase4ReviewFixesTest.php`, `ResultTest.php` (both roles),
      `Phase5ReviewFixesTest.php`, `GradeAnswerTest.php`, `AttemptGraderTest.php` off the
      `exam_section`/`->sections()->sync()` fixture shape (Pitfall 1) — grep-confirmed 20 files total
      reference the pivot; not every one needs a behavior change, but every one needs its fixture
      verified/fixed
- [ ] Delete `tests/Feature/Lecturer/ExamAssignmentTest.php` entirely (7 tests, all for a removed feature)
- [ ] Update `tests/Feature/DomainSchemaTest.php::test_all_domain_tables_exist()` — drop `'exam_section'`
      from the asserted table list
- [ ] Rewrite `tests/Feature/DatabaseSeederTest.php`'s `exam_section` pivot assertion
      (`$exam->sections()->whereKey($section->id)->exists()`) — see next section, the seeder itself must
      change too

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No | Unchanged this phase — Breeze handles it |
| V3 Session Management | No | Unchanged |
| V4 Access Control | **Yes** | `Exam::scopeVisibleTo()` is the single access-control predicate for exam visibility (V4.1); `ExamPolicy::takeable()` delegates to it (never re-derive), matching the existing house pattern |
| V5 Input Validation | Yes | Form Requests remain the validation boundary; `AttemptVoider::void()` takes an `Exam` model, never raw request IDs, so no new injection surface |
| V6 Cryptography | No | Not applicable |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Cross-subject visibility leak (the exact v2.0 CRITICAL bug this phase must not reopen) | Elevation of Privilege / Information Disclosure | `scopeVisibleTo()`'s subject-derived predicate, verified by both a positive AND negative test (INT-04) — list and gate must never diverge (established house discipline, `Exam.php`'s own doc comment) |
| TOCTOU race between a lecturer's reset/edit-triggered delete and a student's in-flight autosave/finalize | Tampering / Denial of Service (crash) | `AttemptVoider::void()`'s lock-then-delete shape, mirroring `Attempt::lockAndFinalize()`; `AttemptVanishedException` as the typed, non-crashing outcome for the losing side of the race |
| Mass-assignment via a forwarded `$request->all()` on any new bulk-write path this phase introduces | Tampering | `AttemptVoider::void()` takes zero request input — it operates entirely on `$exam->id`; the CLS-07 controller action passes no per-attempt data through from the request at all, closing this vector by construction |
| A lecturer resetting/editing an exam they don't "own" (if ownership were per-exam, which it isn't in this codebase) | Elevation of Privilege | N/A this codebase — ownership is subject-level (`subject_user`), not exam-level; relaxing `authorize()` to `true` does not change this existing model (A3 in Assumptions Log) |

## Sources

### Primary (HIGH confidence — direct codebase reads)

- `app/Models/Exam.php`, `app/Models/Attempt.php`, `app/Models/Section.php`, `app/Models/Enrollment.php`,
  `app/Models/Subject.php`, `app/Models/Question.php`, `app/Models/Answer.php` — full reads
- `app/Services/AttemptGrader.php` — the service precedent
- `app/Http/Controllers/Lecturer/{ExamController,ExamQuestionController,AnswerGradeController,
  ExamAssignmentController}.php`, `app/Http/Controllers/Student/{ExamController,AttemptController}.php`
- `app/Http/Requests/Lecturer/{UpdateExamRequest,StoreQuestionRequest,UpdateQuestionRequest,
  AssignExamRequest}.php`
- `app/Policies/{ExamPolicy,AttemptPolicy}.php`
- `app/Exceptions/AttemptVanishedException.php` — full read, confirms self-rendering branch logic
- `database/migrations/2026_07_15_100008_create_exam_section_table.php`,
  `2026_07_15_100009_create_attempts_table.php`
- `database/seeders/DatabaseSeeder.php`, `database/factories/{ExamFactory,SectionFactory,
  AttemptFactory}.php`
- `resources/views/lecturer/exams/show.blade.php`, `resources/views/components/{confirm-modal,toast}
  .blade.php`
- `routes/lecturer.php`
- `tests/Feature/AttemptNullGuardTest.php`, `tests/Feature/Student/{ExamAccessTest,ExamIndexTest,
  ExamVisibilityRegressionTest,AttemptAvailabilityTest}.php`, `tests/Feature/Lecturer/
  ExamAssignmentTest.php`, `tests/Feature/{DomainSchemaTest,DatabaseSeederTest}.php`
- `php -v` / `php artisan --version` — confirmed PHP 8.2.32, Laravel 11.55.0 [VERIFIED: local shell]
- `.planning/phases/10-.../10-CONTEXT.md`, `10-UI-SPEC.md`, `.planning/ROADMAP.md`,
  `.planning/REQUIREMENTS.md`, `.planning/research/{PITFALLS,ARCHITECTURE}.md`

### Secondary (MEDIUM confidence)

- Laravel 11.x Eloquent relationships documentation (nested `whereHas` dot-notation across
  belongsTo→hasMany→belongsToMany) — standard, well-documented Eloquent behavior, not independently
  re-fetched this session but consistent with the codebase's own existing (pre-phase) two-level
  `sections.enrollments` nested `whereHas` usage, which already proves this exact mechanism works in
  this specific Laravel/PHP version.

### Tertiary (LOW confidence)

- None — every substantive claim in this document traces to a direct file read or a locked CONTEXT.md
  decision.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — zero new dependencies, entirely composed of already-verified, already-running
  Laravel primitives
- Architecture: HIGH — every relation/query pattern recommended already exists in some form elsewhere in
  this exact codebase (nested `whereHas`, lock-then-act transactions, grouped aggregates)
- Pitfalls: HIGH — grounded in a direct grep/read of the actual 20 affected test files and the actual
  factory definitions that produce the fixture-mismatch risk, not generic Laravel test advice

**Research date:** 2026-07-17
**Valid until:** No expiry concern — this is a closed-codebase integration analysis, not an
ecosystem/library currency claim. Stays valid as long as the CONTEXT.md decisions (D-1 through D-5)
remain locked and no other phase touches `Exam`/`Attempt`/`Section` models before Phase 10 executes.

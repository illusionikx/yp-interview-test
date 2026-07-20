# Phase 10: Exam Integrity — Auto-Assignment & Attempt Lifecycle - Context

**Gathered:** 2026-07-17
**Status:** Ready for planning

> **This phase carried all three of v3.0's deferred design decisions.** Research and the roadmapper
> deliberately declined to guess them. They are **RESOLVED BELOW by the user** — treat them as locked.
> An earlier orchestrator "soft-void" guess was retracted once; do not re-open any of them.

<domain>
## Phase Boundary

Exams reach exactly the right students with no manual assignment step, and a lecturer can revise or
reset an exam without silently destroying graded work or stranding a student mid-attempt.

**Requirements:** INT-02, INT-03, INT-04, CLS-05, CLS-06, CLS-07, EDT-04, FIX-03.

**In scope:** the exam-visibility model (subject-derived), the draft/active toggle, the destructive
reset/edit paths and their warnings, and the retake path.

**Out of scope:** the two-tab lecturer workspace UI that *surfaces* these actions (Phase 12 — it must
REUSE this phase's service, not re-implement it); the dashboard (Phase 11); the student-facing exam
list UI (Phase 13).

</domain>

<decisions>
## Implementation Decisions

### D-1: Auto-assignment scope — DROP the `exam_section` pivot (CLS-05, INT-04)

**Decision: drop the pivot entirely; derive exam visibility from subject enrollment.**

Rationale: CLS-05 states visibility *is* subject enrollment ("every student enrolled in the subject's
classes is automatically assigned to every active exam in that subject"). That makes `exam_section`
dead weight. Dropping it makes v2.0's CRITICAL cross-subject leak **structurally unexpressible** —
there is no longer any way to say "exam X is visible to a class of subject Y." The alternative
(keep + auto-populate) leaves the leak one unscoped write away.

**The live leak vector this closes** — `app/Models/Exam.php:97`, `scopeVisibleTo()`:

```
->where('is_published', true)
->whereHas('sections.enrollments', fn ($q) => $q
    ->where('user_id', $user->id)
    ->where('status', EnrollmentStatus::Enrolled))
```

It walks `exam → sections (pivot) → enrollments` and **never checks
`exam.subject_id === section.subject_id`**. Today only the assignment UI prevents a cross-subject
attach. Automating assignment without this change would make the leak reachable.

**What this entails:**
- A migration dropping the `exam_section` table (`database/migrations/2026_07_15_100008_create_exam_section_table.php`).
- Rewriting `scopeVisibleTo()` to derive from `exam.subject_id` → that subject's sections →
  enrollments where `status = Enrolled`. The join must go through `subject_id`, never through a
  per-exam section list.
- Removing `Exam::sections()` and the exam-assignment UI/controller/routes.
- Updating tests, factories, and `DatabaseSeeder` off the pivot shape.
- **FIX-03 disappears with it** — the "Update assignment" feedback bug cannot exist once the feature
  is gone. Mark FIX-03 satisfied-by-removal in REQUIREMENTS.md and say so explicitly in the SUMMARY;
  do not fabricate a toaster for a deleted screen.

**INT-04's acceptance is a NEGATIVE test.** "A student enrolled only in Subject Y's class does NOT
see/cannot start Subject X's exam" — asserted for both the list AND direct access (the v2.0 lesson:
list and gate must never diverge). A positive "the exam appears" test does not prove the leak stayed
closed.

### D-2: Attempt cancel/reset mechanism — HARD DELETE (INT-02, INT-03, CLS-07, EDT-04)

**Decision: hard-delete the attempt rows.** Chosen by the user over the orchestrator's recommended
soft-void alternative. **Build it; do not re-litigate it.**

**What this means, stated plainly so nobody softens it later:**
- `answers.attempt_id` is `constrained()->cascadeOnDelete()`
  (`database/migrations/*_create_answers_table.php:16`). Deleting an attempt **permanently destroys
  its answers and their graded scores.** There is no undo and no audit trail.
- **This is compatible with INT-02 as written** — INT-02 forbids destroying a graded result
  *silently*; it does not forbid destroying one. The warning is what makes it non-silent.
- **Therefore the warning IS the safety mechanism.** It is the only thing between a lecturer and
  unrecoverable loss. Treat its copy and its gating as load-bearing, not as polish.
- **INT-03 falls out for free.** Deleting the row releases `attempts.unique(exam_id, user_id)`
  (`database/migrations/2026_07_15_100009_create_attempts_table.php:25`), so the student can simply
  start again. **No migration to the unique key is needed** — do not add `voided_at`, `attempt_number`,
  or any soft-delete column. Those belonged to the rejected alternative.

**Warning copy must differentiate two populations** (roadmap note):
- "N students have started but not finished" — work in progress is lost.
- "N students have been graded — their scores will be permanently deleted." — this is the severe one.
Show concrete counts, not a generic "are you sure". Nothing changes until confirmed.

**One shared service, not two controllers.** CLS-07 (reset submissions) and EDT-04 (saving an edit to
an attempted exam) must converge on **ONE** lock-guarded voiding service, mirroring the existing
`AttemptGrader` service precedent. Never duplicate the delete across two controllers. Its delete must
take the **same row lock** `Attempt::lockAndFinalize()` takes, or a racing student autosave/finalize
can interleave with the delete.

**Consumes Phase 9's `<x-confirm-modal>`** — blocking, decision-required. NOT the toast (non-blocking,
informational). Phase 9 built the modal specifically for this; wrap it, do not build a second one.

### D-3: Reset granularity — PER-EXAM ONLY (CLS-07)

**Decision: per-exam only.** Matches v3.md, which places the action in the exams list. Per-student is
already recorded under "Future Requirements (deferred beyond v3.0)" in REQUIREMENTS.md — building it
now is speculative. Smaller destructive surface, fewer warning paths.

### D-4: The published-edit gate must relax — and it is THREE Form Requests, not one

EDT-04 requires saving edits to an exam that students have already attempted — i.e. a **published**
exam. Editing a published exam is currently forbidden in **three** places (verified by grep):
- `app/Http/Requests/Lecturer/UpdateExamRequest.php:24` — `return ! $this->route('exam')->is_published;`
- `app/Http/Requests/Lecturer/StoreQuestionRequest.php:22` — same
- `app/Http/Requests/Lecturer/UpdateQuestionRequest.php:22` — same

All three must relax for EDT-04. Relaxing only `UpdateExamRequest` leaves question add/edit still
blocked on a published exam — a half-shipped EDT-04 that passes a narrow test. `ExamPublishedEditGateTest`
pins the current behavior and will need updating; that is expected, not a regression to route around.

### D-5: MANDATORY — close the third INT-01 crash site in this phase

`app/Http/Controllers/Lecturer/AnswerGradeController.php:29` performs an **unguarded**
`lockForUpdate()->first()` on `attempts`, feeding a non-nullable
`AttemptGrader::syncStatus(Attempt $attempt)` — a vanished row TypeErrors into a 500. Filed at
`.planning/todos/pending/int-01-third-crash-site-grading-path.md` (`resolves_phase: 10`).

**D-2's hard delete promotes this from a narrow race to a routine one.** Today it needs a student
deleting their own account (via Breeze's stock profile-delete, which cascades through
`attempts.user_id`'s `cascadeOnDelete()`) at the exact moment a lecturer saves a grade. Once this
phase ships exam reset, it becomes: a lecturer saves a grade while another lecturer resets that exam.
That is an ordinary Tuesday.

Apply Phase 9's established guard shape — `App\Exceptions\AttemptVanishedException`, which already
self-renders (redirect + `session('error')` flash for form POSTs like this one; 422 JSON for the
autosave route). Add a regression test using the same `Gate::after` seam
`tests/Feature/AttemptNullGuardTest.php` uses to delete the row after route-model binding but before
the locked read.

Phase 9's threat model **T-09-01** mandates a null-check after **EVERY** `lockForUpdate()->first()` on
`attempts`. This is the last unguarded one. After this phase, that should be true repo-wide.

### D-6: The published-edit gate is FOUR sites, not three — question DELETE routes through EDT-04 too

**Correction to D-4, found by research and verified by the orchestrator.** D-4 named three Form Requests.
There is a **fourth** site, and it is not a Form Request:

- `app/Http/Controllers/Lecturer/ExamQuestionController.php:149` — inline `abort_if($exam->is_published, 403);`

**Resolution (orchestrator's call, resolving research Open Question 1): YES — relax it, and route question
deletion through the same EDT-04 warn-and-void flow.**

Rationale: deleting a question **is** an editor change, and the most structurally destructive one —
EDT-04 ("saving editor changes cancels all previous student attempts") applies to it by plain reading.
The 10-UI-SPEC.md already implies this by making per-question Delete links "always visible". Leaving this
one gate closed would ship a half-EDT-04: a lecturer could edit an attempted exam's text but not remove a
question, for no principled reason. All four editor mutations must behave identically —
**relax the gate, warn, void, then act.**

### D-7: EDT-04's save + void is ONE atomic transaction

**Resolution (orchestrator's call, resolving research Open Question 2).** Research recommended two
sequential transactions (strictly save-then-void). **Use one atomic transaction instead.**

Rationale: with two transactions, a save that succeeds while the void fails leaves an edited exam with
stale attempts still attached — which is precisely the inconsistency EDT-04 exists to prevent. The
reverse order is worse: attempts destroyed for an edit that never landed. Only an atomic pair gives
"both or neither". The lock-duration cost that motivates splitting is negligible at this project's scale
(a class of students per exam, per PROJECT.md's stated scale), and correctness on a permanently
destructive path outranks lock-hold time. If a deadlock with a racing student autosave shows up in
testing, surface it — do not silently split the transaction to make it go away.

### Claude's Discretion

- The service's name/shape (mirroring `AttemptGrader`), the migration's exact form, and the CLS-06
  draft/active toggle's implementation — all Claude's call within the decisions above.
- Whether `Exam::sections()` is removed outright or kept as a `subject->sections` convenience — as long
  as no code path can scope an exam to a section of a different subject.

</decisions>

<code_context>
## Existing Code Insights

### The four shipped invariants this phase collides with (all verified by direct read)

| Invariant | Location | Collision |
|-----------|----------|-----------|
| `exam_section` pivot + `unique(exam_id, section_id)` | `database/migrations/2026_07_15_100008_create_exam_section_table.php:20` | D-1 drops it |
| `attempts.unique(exam_id, user_id)` | `database/migrations/2026_07_15_100009_create_attempts_table.php:25` | Blocks retake; D-2's delete frees it — no migration needed |
| `answers.attempt_id` → `cascadeOnDelete()` | `database/migrations/*_create_answers_table.php:16` | D-2's delete destroys graded scores through it |
| Published-edit lock in **3** Form Requests | `UpdateExamRequest:24`, `StoreQuestionRequest:22`, `UpdateQuestionRequest:22` | D-4 relaxes all three |

### Reusable Assets (from Phase 9 — inherit, do not re-invent)

- `<x-confirm-modal>` (`resources/views/components/confirm-modal.blade.php`) — blocking confirmation,
  wraps the `<x-modal>` primitive. Built in Phase 9 explicitly for this phase's warnings.
  `tests/Feature/NoNativeDialogTest.php` pins its `x-ref` ↔ `$refs.<name>.submit()` wiring.
- `<x-toast>` (`resources/views/components/toast.blade.php`) — non-blocking, reads
  `session('status')`/`session('error')`. **`session('success')` does not exist (0 call sites).**
- `App\Exceptions\AttemptVanishedException` — self-rendering guard exception (D-5 reuses it).
- `App\Support\Semester` — derived value object; `ordinal()` is `year*2 + (2-number)`.
- The Flowbite semantic token layer. `bash scripts/ui-03-token-gate.sh` must stay PASS/exit 0. A token
  class not in `tailwind.config.js` AND the gate's list silently emits NO CSS — no error, just an
  unstyled page.

### ⚠ THE FACTORY TRAP — the phase's biggest hidden risk (verified by direct read)

`database/factories/ExamFactory.php:23` → `'subject_id' => Subject::factory()`
`database/factories/SectionFactory.php:21` → `'subject_id' => Subject::factory()`

**Each factory independently mints its OWN subject.** So `Exam::factory()->create()` +
`Section::factory()->create()` in the same test produce an exam and a section in **different subjects**.

Today the `exam_section` pivot masks this completely — the test explicitly attaches the exam to the
section, so visibility works regardless of subject. **Once D-1 makes visibility subject-derived, this
becomes fatal**, and it bites in two directions:

1. **~20 test files** reference `exam_section` / `->sections()`. Most visibility tests will simply break
   (student enrolled in subject A, exam in subject B → invisible).
2. **Far more dangerous — INT-04's negative test could pass for the WRONG reason.** "A student in Subject
   Y's class does not see Subject X's exam" passes *trivially* if the factories always disagree by
   accident. It would prove nothing while looking green. **INT-04's negative test MUST construct the
   different-subject case explicitly** — assert the two subject IDs actually differ — and its positive
   counterpart MUST explicitly pin exam and section to the **same** subject. Never let factory defaults
   decide the thing under test.

`tests/Feature/AttemptAvailabilityTest::enrolledStudentFor()` already builds the same-subject fixture
correctly — **it is the template to copy.**

### Established Patterns

- `AttemptGrader` (`app/Services/AttemptGrader.php`) — **the service precedent** D-2's voiding service
  should mirror: explicit service method, called once at a lifecycle transition, never a model event.
- Row-lock discipline: `DB::transaction()` + `lockForUpdate()` + an explicit null-guard after the read
  (Phase 9, T-09-01). The voiding service's delete must take the same lock.
- `Section::windowStatus()` / `Exam::availabilityState()` — computed, not stored; half-open intervals.
- Test command `php artisan test` (**340 passing at Phase 9 close, 0 failing**). Build `npm run build`.
  MySQL `yp-student-exam`; running tests wipes it.
- Blade gotcha (cost Phase 9 a live 503): an HTML comment containing literal `@include`/`@method` text
  is compiled by Blade as a real directive. Use `{{-- --}}` comments.

</code_context>

<specifics>
## Specific Ideas

- v3.md: "saving editor changes cancels all previous student attempts; if any student has attempted
  previously, a warning pops up first" (EDT-04) and the reset action lives in the exams list (CLS-07).
- v3.md excludes exam versioning explicitly ("too complex") — do not introduce it as a way to dodge
  D-2's destructiveness.

</specifics>

<deferred>
## Deferred Ideas

- **Per-student reset granularity** — Future Requirements, only "if per-exam proves insufficient" (D-3).
- **Soft-void / attempt history / audit trail** — the rejected D-2 alternative. Do not smuggle it back
  in as `voided_at`, `attempt_number`, or a soft-delete column.
- **A shared `Attempt::lockOrFail()` helper** so a fourth unguarded locked read can't reappear — worth
  considering once D-5 makes three guarded sites, but not required.
- **The two-tab lecturer workspace UI** — Phase 12; it consumes this phase's service.

</deferred>

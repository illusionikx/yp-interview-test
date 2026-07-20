# Phase 8: v2.0 Features — Enrollment, Exam Availability & User Manuals - Research

> **⚠ RESOLVED (2026-07-16, user decision) — rejection-reason values.** This document discusses a
> conflict between two candidate reason sets. It is **settled**: the authoritative set is
> REQUIREMENTS.md "Resolved Design Decisions (v2.0)" #1 —
> `Not eligible for subject` · `Prerequisite not met` · `Duplicate enrollment` · `Section changed` · `Other`
> (fixed enum, server-validated). 08-CONTEXT.md and 08-UI-SPEC.md have been corrected to match.
> Ignore any other candidate wording appearing below (e.g. "Ineligible for this section",
> "Administrative reallocation", "Other (contact lecturer)") — it is superseded.
>
> **Also resolved:** the availability window is set on a **draft** exam only — the Phase 2 draft-only
> edit gate (D-06) stands, no exception for published exams (answers this document's Open Question 1).

**Researched:** 2026-07-16
**Domain:** Laravel 11 domain logic — concurrency-safe enrollment writes, a composable authorization/visibility gate, browser-native tab-close warning, in-app documentation pages
**Confidence:** HIGH

## Summary

Phase 8 is pure Laravel-native application logic on top of the Phase 7 foundation — no new Composer packages, no new JS libraries. Every one of the eight "hard questions" in scope has a concrete, codebase-verified answer because Phase 7 already established the exact idioms this phase must reuse: the transaction+`lockForUpdate()` race-safety pattern (`SectionController@store`'s sequence auto-increment), the per-subject ownership-in-`authorize()` pattern (`StoreSectionRequest`), and the "ownership-only, independent of the shared visibility predicate" pattern (`AttemptPolicy::viewResult()`).

The single most important finding is a **latent bug this phase must fix, not just avoid introducing**: `AttemptPolicy::view()`/`update()` currently derive access to an in-progress attempt from `Exam::visibleTo($user)`, which is enrollment-status-driven. The moment Phase 8 makes enrollment status mutable after an attempt has started (via withdraw or reject), a student's own in-progress attempt would start 403'ing on every touch — silently violating AVL-04 (an in-progress attempt must run to completion on its own timer). The fix is already modeled in the same file: `AttemptPolicy::viewResult()` is deliberately ownership-only for exactly this reason ("a student's own graded result must stay visible even if the exam is later unpublished or reassigned"). `view()`/`update()` must be changed to the same ownership-only shape. This is also the direct implementation of REQUIREMENTS.md's Resolved Decision #7 ("Post-start attempt access decoupled from live enrollment... attempts are ownership-gated, not visibility-gated").

The second key architectural decision: **availability gating must never enter `Exam::scopeVisibleTo()`**. That predicate is consumed by the exam list, `ExamPolicy::takeable()`, and (after the fix above, no longer by) `AttemptPolicy`. If availability were folded into it, a closed window would retroactively hide an exam a student is mid-attempt on, or (worse, pre-fix) would 403 their own attempt. Availability is instead a narrow, additive check (`Exam::isAvailableNow()`) applied at exactly one call site: `AttemptController@store`, and only on the *new-attempt* branch (an existing in-progress attempt must always be resumable regardless of window state).

The three write actions this phase adds (enrollment apply, enrollment withdraw, section reject) all follow the same shape: Form-Request-level ownership/window checks for the cheap rejections, then a `DB::transaction()` + `lockForUpdate()` on the `Section` row for the one check that is a genuine multi-user race (capacity). Re-apply after withdraw/reject is an `updateOrCreate` keyed on the existing `unique(section_id, user_id)` row, never an insert.

**Primary recommendation:** Build every new write path as Form Request (ownership + shape validation) → controller (transaction + lock only where a real race exists) → flash-message redirect, exactly mirroring `SectionController`/`StoreSectionRequest`; fix `AttemptPolicy::view()`/`update()` to ownership-only as the first task in the phase (it gates everything else); keep `Exam::scopeVisibleTo()` and `ExamPolicy::takeable()` byte-for-byte unchanged.

## Project Constraints (from CLAUDE.md)

- Laravel 11 + Breeze mandated; build on the existing scaffold, do not replace it.
- MySQL (`yp-student-exam` via Herd) — already configured; no new datastore.
- Blade + Tailwind + Alpine only — no SPA/Livewire/Inertia, no Echo/Pusher/Reverb.
- **No new Composer packages for this domain** — RBAC (native enum + Gates/Policies/middleware), timer (server-stored deadline + Alpine display), and grading are all Laravel-native; the same philosophy extends to enrollment/availability: no `spatie/*` package, no state-machine package, no queued/scheduled sweep job (лazy on-touch enforcement only, matching the existing `finalizeIfExpired()` precedent).
- Model events/observers are explicitly forbidden for "hidden side effect" logic (e.g. grading) — the same discipline applies to enrollment status transitions: reject/withdraw/apply must be explicit method calls in a controller, never a model `saving`/`updating` hook.
- Denormalized counters are to be avoided until a *measured* performance problem exists — do not add a `sections.enrolled_count` column; compute capacity live via `count()` inside the locked transaction (matches the existing "live accessor over denormalized column" precedent already used for `Section::name()` and `Attempt::isFullyGraded()`-style accessors).
- Scope discipline: simplest correct implementation. No waitlists, no capacity auto-promotion, no enrollment audit history (all explicitly Out of Scope in REQUIREMENTS.md).

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Section browse + live capacity display (ENR-01) | API / Backend (Blade+controller, SSR) | — | Read-only query, server-rendered; no client-side data fetching layer exists in this stack |
| Capacity-safe apply (ENR-02) | API / Backend | Database (MySQL row lock) | The correctness guarantee is a DB-level exclusive row lock (`SELECT ... FOR UPDATE`) inside a Laravel transaction — the "backend" and "database" tiers are inseparable here, exactly like the existing `finalizeIfExpired()` and section-sequence precedents |
| Withdraw / re-apply (ENR-03, ENR-05) | API / Backend | — | Plain authenticated write, ownership-gated |
| One-active-enrollment-per-subject/semester (ENR-04) | API / Backend | — | Application-level guard, not a DB constraint (MySQL cannot express a partial/filtered unique index the way Postgres can; see Pitfall 3) |
| Section-window status label (ENR-06) | Browser / Client (display only) | API / Backend (source of truth) | Server computes the state (`opens`/`open`/`closed`) each request; Alpine renders nothing dynamic here — no client-side clock needed, unlike the attempt timer |
| Lecturer reject with reason (ENR-07) | API / Backend | — | Form Request ownership check (SEC-03 pattern) + fixed-enum validation |
| Exam availability window (AVL-01) | Database (schema) | API / Backend (gate) | Two nullable `dateTime` columns on `exams`; the gate that reads them is a plain PHP method, not a query scope |
| Pre-start details page (AVL-02) | API / Backend (SSR) | Browser (Proceed/Back are plain links/forms) | Reuses `student.exams.show` — no new route |
| Availability-gated attempt start (AVL-03) | API / Backend | — | Single call site: `AttemptController@store`, new-attempt branch only |
| Started-attempt immunity (AVL-04) | API / Backend (Policy) | — | `AttemptPolicy` ownership-only fix — the load-bearing correction this phase must make |
| beforeunload warning (AVL-05) | Browser / Client | — | Pure client-side JS; the server has no role in this — it cannot detect a tab close |
| In-app manuals (DEL-04, DEL-05) | API / Backend (SSR Blade) | — | Static content pages, same tier as `student.home`/`lecturer.home` |

## Standard Stack

### Core

No new libraries. This phase is built entirely on the framework primitives already in `composer.json` and already exercised in Phases 1-7:

| Component | Version | Purpose | Why Standard (for this codebase) |
|-----------|---------|---------|-----------------------------------|
| Laravel 11.55 [VERIFIED: `php artisan --version`, this session] | 11.x | Transactions (`DB::transaction`), row locking (`lockForUpdate`), Form Requests, Policies, native enum casts | Already the mandated stack; every pattern below is a direct extension of Phase 4/7 code already in the repo |
| MySQL 8.x via Herd | — | `SELECT ... FOR UPDATE` row locking is the enforcement mechanism for ENR-02 | Already configured (`yp-student-exam`); confirmed reachable this session (`php artisan --version` succeeded against the configured connection) |
| Alpine.js (bundled via Breeze) | — | `beforeunload` listener attach/detach (AVL-05); no new Alpine plugin needed, plain `window.addEventListener` inside the existing `attemptTimer()` factory | Already the sole client-side interactivity layer (UI-01/UI-02 lock) |

### Supporting

None — no new Composer or npm packages are required for any of ENR-01..07, AVL-01..05, or DEL-04/05.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| App-level transaction+`lockForUpdate()` capacity guard | A MySQL trigger enforcing capacity at the DB layer | Rejected — hides business logic outside Eloquent/migrations where it can't be unit-tested or code-reviewed the same way; the project has zero existing DB-trigger precedent and CLAUDE.md's minimal-correct philosophy favors the same idiom Phase 7 already proved (transaction+lock) |
| App-level ENR-04 guard (query inside the same transaction) | A MySQL generated-column + composite-unique-index trick (`unique(user_id, subject_id, year, semester, active_marker)` where `active_marker` is `NULL` unless status=enrolled) | Rejected — requires denormalizing `subject_id`/`year`/`semester` onto `enrollments` (they currently live only on `sections`, reached via `section_id`), adds schema complexity for a race window this project's scale (a handful of students) doesn't need to defend against with a DB constraint; the capacity race (ENR-02) is the one explicitly called out as a hard requirement in REQUIREMENTS.md, ENR-04 is not |
| In-app Blade manuals (DEL-04/05) | Markdown files in `docs/` rendered via a Markdown-to-HTML package (e.g. `league/commonmark`) | Explicitly overridden by the user in 08-CONTEXT.md — manuals ship as Blade pages inside the Flowbite shell, no new package |
| Native `beforeunload` | A confirmation library / SPA router guard | Rejected — no SPA router exists in this stack (Blade full-page navigation); the browser-native event is the only mechanism available and is sufficient |

**Installation:** None required — `composer install`/`npm install` already cover everything this phase touches.

## Package Legitimacy Audit

**Not applicable this phase — zero new Composer or npm packages are installed.** No `composer require` / `npm install` commands appear anywhere in this research. If a plan for this phase proposes adding any package, that is a deviation from CLAUDE.md's explicit "no new Composer packages" constraint and must be justified explicitly, not silently introduced.

## Architecture Patterns

### System Architecture Diagram

```
STUDENT ENROLLMENT FLOW (ENR-01..07)
─────────────────────────────────────
Browser: GET /student/subjects            Browser: POST /student/sections/{s}/enroll
   │                                          │
   ▼                                          ▼
SubjectBrowseController@index          EnrollmentController@store
   │ Subject::withCount() / plain query   │
   ▼                                       ▼
Blade: student/subjects/index          ┌─ window check (opens_at/closes_at, half-open) ──▶ refuse (flash red)
   │                                    ├─ ENR-04 check (locked query, other active     ──▶ refuse (flash red)
   ▼                                    │  section same subject+semester)
Browser: GET /student/subjects/{sub}    │
   │                                    ▼
   ▼                                 DB::transaction {
SubjectBrowseController@show            Section::lockForUpdate()->find($id)   ◀── the ONE real race
   │ Section::where(subject_id)         count(enrollments WHERE status=enrolled)
   │  ->with('enrollments' filtered      if >= capacity  ──▶ refuse (flash red, ENR-02)
   │   to auth user)                     else Enrollment::updateOrCreate(      ◀── re-apply = UPDATE
   ▼                                       [section_id,user_id], [status=Enrolled, reason=null])
Blade: renders per-section Apply/       }
Withdraw/Rejected+reason per                │
08-UI-SPEC "four mutually exclusive         ▼
states" table                          redirect back, flash green "You're enrolled..."

LECTURER REJECT FLOW (ENR-07)
──────────────────────────────
Browser: GET /lecturer/sections/{s}     Browser: PATCH /lecturer/sections/{s}/enrollments/{student}/reject
   │                                       │
   ▼                                       ▼
SectionController@show (new)           RejectEnrollmentController@reject
   │ abort_unless(subject->lecturers      │ RejectEnrollmentRequest::authorize()
   │  ->whereKey(auth)->exists())         │  = SEC-03 pattern via $section->subject->lecturers()
   ▼                                      │ rules: reason => Rule::enum(RejectionReason::class)
Blade: roster table, Reject per row      ▼
   → opens x-modal w/ reason <select>   Enrollment::where(section_id,user_id)
                                          ->update(status=Rejected, rejection_reason=$reason)
                                          redirect back, flash "{student} has been rejected..."

EXAM AVAILABILITY + START GATE (AVL-01..05)
─────────────────────────────────────────────
Browser: GET /student/exams/{exam}   (pre-start — ALWAYS reachable if enrolled, per AVL-02)
   │
   ▼
ExamController@show
   │ $this->authorize('takeable', $exam)   ◀── UNCHANGED: enrollment-only gate (scopeVisibleTo)
   │ $exam->availabilityState()            ◀── NEW: 'available'|'opening'|'closed', display only
   ▼
Blade: instructions, window, "your section", Proceed/Back

Browser: POST /student/exams/{exam}/attempts  (Proceed click)
   │
   ▼
AttemptController@store
   │ $this->authorize('takeable', $exam)          ◀── UNCHANGED (enrollment gate)
   │ if (!existingAttempt && !$exam->isAvailableNow())  ◀── NEW: AVL-03, new-attempt branch ONLY
   │     redirect back with refusal message
   ▼
Attempt::firstOrCreate(...)   ◀── UNCHANGED (Phase 4 race-safe create)
   │
   ▼
AttemptController@show / @answer / @submit  (in-progress lifecycle)
   │ $this->authorize('view'|'update', $attempt)
   ▼
AttemptPolicy::view()/update()  ◀── FIXED THIS PHASE: ownership-only
   │  (was: $attempt->user_id === $user->id && Exam::visibleTo($user)->exists() — BROKEN once
   │   enrollment status becomes mutable post-start, see Summary)
   │  now: return $attempt->user_id === $user->id;   ◀── matches viewResult()'s existing shape
   ▼
finalizeIfExpired() / autosave / finalize()  ◀── UNCHANGED — sole timer authority (AVL-04 satisfied)

Browser (client-only): beforeunload listener attached on page load while status=in_progress,
removed immediately before the confirm-submit form's native POST and before autoSubmit()'s
axios POST — never fires on either intentional path (AVL-05).
```

### Recommended Project Structure

```
app/
├── Enums/
│   └── RejectionReason.php                 # NEW — fixed 5-value enum, mirrors QuestionType/EnrollmentStatus
├── Models/
│   ├── Exam.php                            # MODIFY — add available_from/until fillable+cast, isAvailableNow(), availabilityState()
│   ├── Enrollment.php                      # MODIFY — cast rejection_reason => RejectionReason::class
│   └── Section.php                         # MODIFY — windowStatus() accessor (extract the inline @php block from lecturer/sections/index.blade.php so student view can reuse it)
├── Policies/
│   └── AttemptPolicy.php                   # MODIFY — view()/update() become ownership-only (THE critical fix)
├── Http/
│   ├── Controllers/
│   │   ├── Student/
│   │   │   ├── SubjectBrowseController.php # NEW — index (subject list), show (that subject's sections)
│   │   │   ├── EnrollmentController.php    # NEW — store (apply), destroy (withdraw)
│   │   │   └── AttemptController.php       # MODIFY — store() gains the isAvailableNow() branch
│   │   └── Lecturer/
│   │       ├── SectionController.php       # MODIFY — add show() (roster page)
│   │       └── RejectEnrollmentController.php # NEW — reject()
│   └── Requests/
│       ├── Student/
│       │   └── EnrollRequest.php           # NEW — thin; most logic lives in the controller transaction (window+capacity checks need a locked read, not pre-validation)
│       └── Lecturer/
│           └── RejectEnrollmentRequest.php # NEW — SEC-03 ownership + Rule::enum(RejectionReason)
├── Http/Requests/Lecturer/
│   ├── StoreExamRequest.php                # MODIFY — add available_from/available_until rules
│   └── UpdateExamRequest.php                # MODIFY — same
database/
├── migrations/
│   └── 2026_07_15_100005_create_exams_table.php  # MODIFY IN PLACE — add available_from/available_until
├── factories/
│   └── ExamFactory.php                     # MODIFY — optional available()/opening()/closed() states for tests
└── seeders/DatabaseSeeder.php              # MODIFY — seed available_from/until on the demo exam (AVL-01 demo)
resources/views/
├── student/
│   ├── subjects/{index,show}.blade.php     # NEW
│   ├── exams/{index,show}.blade.php        # MODIFY — availability pill, pre-start enhancements, red flash variant
│   └── attempts/show.blade.php             # MODIFY — attemptTimer() gains beforeunload attach/detach
├── lecturer/
│   ├── sections/show.blade.php             # NEW — roster page
│   └── exams/{create,edit}.blade.php       # MODIFY — available_from/until fields
├── student/help.blade.php                  # NEW — DEL-04
├── lecturer/help.blade.php                 # NEW — DEL-05
└── layouts/navigation.blade.php            # MODIFY — Enroll (student), Help (both), per UI-SPEC nav order
routes/
├── student.php                             # MODIFY — subjects/sections/enroll/withdraw/help routes
└── lecturer.php                            # MODIFY — sections.show, reject, help routes
```

### Pattern 1: Capacity-safe apply (ENR-02) — the load-bearing concurrency pattern

**What:** Serialize all concurrent applies to the *same section* by taking an exclusive row lock on that `Section` row inside a transaction, then perform a live count-then-write, never a plain count-then-insert.

**When to use:** Any write that must respect a capacity ceiling shared across concurrent requests. This is the identical shape Phase 7 used for section-sequence auto-increment (`SectionController@store`) — same file, same author, same idiom, now applied to a different column.

**Example — direct extension of the verified in-repo precedent (`app/Http/Controllers/Lecturer/SectionController.php` lines 56-73):**
```php
// EXISTING precedent (Phase 7, verified this session):
DB::transaction(function () use ($request, $subject) {
    $sequence = Section::where('subject_id', $subject->id)
        ->where('year', $request->validated('year'))
        ->where('semester', $request->validated('semester'))
        ->lockForUpdate()
        ->max('sequence') + 1;

    Section::create([...]);
});

// NEW — same shape, applied to capacity (Enrollment is a Pivot subclass,
// directly queryable via its own static methods — see app/Models/Enrollment.php):
DB::transaction(function () use ($section, $user) {
    $locked = Section::whereKey($section->id)->lockForUpdate()->first();

    $enrolledCount = $locked->enrollments()
        ->wherePivot('status', EnrollmentStatus::Enrolled->value)
        ->count();

    if ($enrolledCount >= $locked->capacity) {
        throw new SectionFullException(); // caught by the controller, flashed as the ENR-02 refusal copy
    }

    Enrollment::updateOrCreate(
        ['section_id' => $locked->id, 'user_id' => $user->id],
        ['status' => EnrollmentStatus::Enrolled, 'rejection_reason' => null]
    );
});
```
`lockForUpdate()` places a MySQL exclusive row lock on the `sections` row; a second concurrent request for the *same* section blocks at `->first()` until the first transaction commits or rolls back, so the count it then reads is guaranteed current — this is what makes the count-then-write atomic and race-proof [CITED: web, MEDIUM confidence — cross-checked against the in-repo Phase 7 precedent which is [VERIFIED: codebase] this session].

### Pattern 2: Re-apply is an UPDATE, never an INSERT (ENR-05)

**What:** `enrollments` carries `unique(section_id, user_id)` — a student who withdrew or was rejected from a section already has a row there. Re-applying must resolve to `Enrollment::updateOrCreate(['section_id' => ..., 'user_id' => ...], ['status' => Enrolled, 'rejection_reason' => null])`, exactly as shown in Pattern 1 above. A plain `Enrollment::create(...)` on re-apply throws a duplicate-key `QueryException` (MySQL error 1062) against the existing unique index — this is the exact same class of failure `AttemptController@store`'s existing `firstOrCreate`/1062-catch already defends against for a *different* unique constraint (`exam_id,user_id`), so the codebase already has the precedent for "expect and handle 1062," but `updateOrCreate` avoids needing that catch here since it's a single query.

**Do not** clear `rejection_reason` to `''` — set it to `null` explicitly on re-apply, so a stale reason never leaks onto a fresh Enrolled row (the reason is only ever meaningful while `status === Rejected`).

### Pattern 3: One active enrollment per subject/semester (ENR-04) — application-level, inside the transaction

**What:** Before writing the new Enrolled row (inside the same `DB::transaction` as Pattern 1, so the two checks are consistent under lock), query for any *other* active enrollment this student holds in a section sharing the same `subject_id` + `year` + `semester`:

```php
$hasActiveElsewhere = Enrollment::query()
    ->where('user_id', $user->id)
    ->where('status', EnrollmentStatus::Enrolled)
    ->where('section_id', '!=', $section->id)
    ->whereHas('section', fn ($q) => $q
        ->where('subject_id', $section->subject_id)
        ->where('year', $section->year)
        ->where('semester', $section->semester))
    ->lockForUpdate()
    ->exists();

if ($hasActiveElsewhere) {
    throw new AlreadyActiveEnrollmentException();
}
```
Note: `Enrollment` is a `Pivot` subclass but still needs a `section()` relation for this `whereHas` to work — add `public function section(): BelongsTo { return $this->belongsTo(Section::class); }` to `app/Models/Enrollment.php` (it currently has no relations, only casts). This is a small but necessary addition — verify it's in the plan's file-touch list for `Enrollment.php`.

**Why application-level, not a DB constraint:** MySQL 8 has no partial/filtered unique index (unlike PostgreSQL's `WHERE` clause on a unique index). Expressing "at most one row with status=Enrolled per (user_id, subject_id, year, semester)" at the DB layer would require either a generated/virtual column trick or denormalizing `subject_id`/`year`/`semester` onto `enrollments` — both add schema complexity the project's scale doesn't warrant (see Alternatives Considered). ENR-04 is not called out in REQUIREMENTS.md as a hard concurrency requirement the way ENR-02 explicitly is ("concurrent applies can never oversell a section's capacity") — a `lockForUpdate()`'d application check inside the same transaction as the capacity check is proportionate.

### Pattern 4: Availability composes with `scopeVisibleTo()` — it never enters it

**What:** `Exam::scopeVisibleTo()` stays byte-for-byte unchanged from Phase 7. Availability is a separate, additive instance method:

```php
// app/Models/Exam.php — NEW methods, scopeVisibleTo() untouched
public function isAvailableNow(): bool
{
    $now = now();

    return ($this->available_from === null || $now->gte($this->available_from))
        && ($this->available_until === null || $now->lt($this->available_until));
}

public function availabilityState(): string
{
    $now = now();

    if ($this->available_from !== null && $now->lt($this->available_from)) {
        return 'opening';
    }
    if ($this->available_until !== null && $now->gte($this->available_until)) {
        return 'closed';
    }

    return 'available';
}
```
Half-open interval `[available_from, available_until)`, identical boundary semantics to `Section::opens_at`/`closes_at` per REQUIREMENTS.md Decision #6. `availabilityState()` return values map directly onto the three new `x-status-pill` keywords locked in 08-UI-SPEC.md (`available`/`opening`/`closed`).

**Called from exactly one enforcement site** — `AttemptController@store`, and only on the new-attempt branch:
```php
public function store(Request $request, Exam $exam): RedirectResponse
{
    $this->authorize('takeable', $exam); // UNCHANGED — enrollment gate

    $alreadyStarted = Attempt::where('exam_id', $exam->id)
        ->where('user_id', $request->user()->id)
        ->exists();

    if (! $alreadyStarted && ! $exam->isAvailableNow()) {
        return redirect()->route('student.exams.show', $exam)
            ->with('error', $exam->availabilityState() === 'opening'
                ? __('This exam is not available yet. It opens :date.', ['date' => $exam->available_from->format('M j, Y g:ia')])
                : __('This exam is no longer available. It closed :date.', ['date' => $exam->available_until->format('M j, Y g:ia')]));
    }

    // ...unchanged firstOrCreate/1062-catch logic below
}
```
The `$alreadyStarted` check is essential — without it, a student resuming their own in-progress attempt after the window has since closed would be wrongly refused, which is precisely the AVL-04 violation this phase must avoid introducing.

**Called nowhere else.** `ExamController@show` (the pre-start page) reads `$exam->availabilityState()` for **display only** — it never gates the page itself, since AVL-02 requires the pre-start page to always be reachable (to show "not yet open"/"closed" messaging) for any enrolled student.

### Pattern 5: `AttemptPolicy` ownership-only fix (AVL-04) — the required correction

**What:** `app/Policies/AttemptPolicy.php`'s `view()` and `update()` currently call a private `ownAndTakeable()` that re-derives `Exam::visibleTo($user)->whereKey($attempt->exam_id)->exists()` on every touch. `viewResult()` in the same file already deliberately does **not** do this, with a doc comment explaining exactly why. Extend that same reasoning to `view()`/`update()`:

```php
// BEFORE (current code, verified this session — app/Policies/AttemptPolicy.php)
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

// AFTER (required this phase) — matches viewResult()'s existing shape exactly:
public function view(User $user, Attempt $attempt): bool
{
    return $attempt->user_id === $user->id;
}
public function update(User $user, Attempt $attempt): bool
{
    return $attempt->user_id === $user->id;
}
// ownAndTakeable() and the Exam import can be removed if nothing else uses them —
// verify with a repo-wide grep before deleting.
```
This is not a new capability — it is a correction that becomes *load-bearing* the instant ENR-03/ENR-07 (withdraw/reject) ship, because those are the first code paths in the whole app that can flip `Enrollment.status` away from `Enrolled` for a student who may already have an in-progress attempt. Without this fix, `AttemptController@show`/`@answer`/`@submit` (all of which call `$this->authorize('view'|'update', $attempt)`) would start returning 403 mid-attempt for a withdrawn/rejected student — silently breaking TAK-02/TAK-03/TAK-04 for that student, not just failing an AVL-04 acceptance test. **Recommend this be the first task in the phase's execution order** — every subsequent enrollment-mutation test (withdraw-during-attempt, reject-during-attempt) depends on it being fixed first, not verified after the fact.

**Note on `ExamPolicy::takeable()`:** this policy is unaffected and stays exactly as-is — it is only used by `AttemptController@store` (the start gate) and `Student\ExamController@show` (the pre-start page's authorize call), both of which *should* remain enrollment-gated. Do not apply the same ownership-only fix there.

### Pattern 6: `beforeunload` attach/detach inside the existing `attemptTimer()` scope (AVL-05)

**What:** Extend the already-verified `attemptTimer()` Alpine factory (`resources/views/student/attempts/show.blade.php`, full file read this session) with a named handler so it can be both attached and later removed:

```js
// Inside attemptTimer() — add to the returned object:
init() {
    this.setBucket(false);
    this.render();
    this._beforeUnloadHandler = (event) => {
        event.preventDefault();
        event.returnValue = ''; // required for legacy browser compat; modern browsers ignore the string itself
    };
    window.addEventListener('beforeunload', this._beforeUnloadHandler);
},
detachBeforeUnload() {
    window.removeEventListener('beforeunload', this._beforeUnloadHandler);
},
autoSubmit() {
    if (this.autoSubmitting) { return; }
    this.autoSubmitting = true;
    this.detachBeforeUnload();  // <-- detach BEFORE the axios POST + redirect, not after
    this.display = '00:00';
    clearInterval(this.timerId);
    window.axios.post(submitUrl).finally(() => { window.location.href = submittedUrl; });
},
```
And on the intentional-submit `<form>` inside the `x-modal` (a native POST, not an axios call — the browser will actually navigate away):
```html
<form method="POST" action="{{ route('student.attempts.submit', $attempt) }}" class="p-6"
      x-on:submit="detachBeforeUnload()">
```
The `submit` event fires synchronously before the browser begins unloading the page for that navigation, so calling `detachBeforeUnload()` inside the `x-on:submit` handler reliably removes the listener before `beforeunload` would otherwise fire [CITED: MDN Window: beforeunload event, MEDIUM confidence].

Modern Chrome/Firefox/Safari **ignore any custom string** placed in `event.returnValue` or the handler's return value — only the browser's own generic dialog text displays; Chrome dropped custom-message support from Chrome 51 specifically because sites abused it [CITED: web search cross-referencing MDN + chromestatus.com, MEDIUM confidence]. This matches 08-UI-SPEC.md's explicit instruction: "Do not attempt to set a custom message string (modern browsers ignore it)." The dialog also requires the page to have received at least one user interaction ("sticky activation") before it will fire at all — this is a browser anti-abuse measure, not something the app can control, and should not be treated as a bug if a fully automated/no-interaction test doesn't see the dialog.

### Pattern 7: Ownership-in-`authorize()` for the two new lecturer write actions

**What:** `RejectEnrollmentRequest` follows the exact SEC-03 divergence Phase 7 established for `StoreSectionRequest`/`UpdateSectionRequest`/`AssignLecturerRequest` — **do not** default to `return true;` the way exam/subject CRUD does (D-09 convention is explicitly for resources with no per-record ownership; sections/enrollments are not that).

```php
// app/Http/Requests/Lecturer/RejectEnrollmentRequest.php
public function authorize(): bool
{
    $section = $this->route('section');

    return $section->subject->lecturers()->whereKey($this->user()->id)->exists();
}

public function rules(): array
{
    return [
        'reason' => ['required', Rule::enum(RejectionReason::class)],
    ];
}
```
`Rule::enum()` is the Laravel 11-native validation rule for backed enums (available since Laravel 9) — prefer it over a hand-rolled `Rule::in([...])` array of raw strings, since it stays in sync with the enum automatically and matches this codebase's established "native enum everywhere" convention (`Role`, `QuestionType`, `EnrollmentStatus`).

### Pattern 8: Reason enum mirrors `QuestionType`/`EnrollmentStatus` exactly

```php
// app/Enums/RejectionReason.php — NEW
namespace App\Enums;

enum RejectionReason: string
{
    case PrerequisiteNotMet = 'prerequisite_not_met';
    case IneligibleForSection = 'ineligible_for_section';
    case AdministrativeReallocation = 'administrative_reallocation';
    case DuplicateEnrollment = 'duplicate_enrollment';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PrerequisiteNotMet => 'Prerequisite not met',
            self::IneligibleForSection => 'Ineligible for this section',
            self::AdministrativeReallocation => 'Administrative reallocation',
            self::DuplicateEnrollment => 'Duplicate enrollment',
            self::Other => 'Other (contact lecturer)',
        };
    }
}
```
Cast on `Enrollment` (`app/Models/Enrollment.php`, currently only casts `status`): add `'rejection_reason' => RejectionReason::class` to the `casts()` array — Laravel's enum casting returns `null` cleanly when the underlying column value is `null`, so this is safe for the common case (an Enrolled/Withdrawn row has no reason).

**⚠️ Values must come from 08-CONTEXT.md/08-UI-SPEC.md, not REQUIREMENTS.md** — see Common Pitfalls.

### Anti-Patterns to Avoid

- **Folding availability into `scopeVisibleTo()` or `ExamPolicy::takeable()`:** breaks AVL-04 the moment the window closes on an in-progress attempt whose access still routes through `ExamPolicy` (it currently doesn't, post-fix, but a future refactor that re-derives `AttemptPolicy` from `takeable()` would silently reintroduce the bug — leave a comment at the fix site warning against this).
- **A plain `count()` then `create()` for capacity** (no lock): passes every single-request test, fails under concurrency — indistinguishable from correct code in a synchronous PHPUnit run, which is exactly why this needs to be caught in code review / plan-check, not left to the test suite alone (see Validation Architecture).
- **Model events for status transitions:** do not add a `saving()`/`updating()` hook on `Enrollment` to "auto-grade" or side-effect off a status change — mirrors the explicit CLAUDE.md prohibition on grading-via-observer; keep every transition an explicit controller call.
- **Reusing `session('status')` (green-only) for refusal messages:** every existing view in this codebase only ever renders `session('status')` in green (`text-green-600 dark:text-green-400`) — confirmed via a repo-wide search this session (13 files, all green-only). 08-UI-SPEC.md requires red refusal banners. Introduce a second flash key (e.g. `session('error')`) consistently across every new write action, and extend `student/exams/show.blade.php` (and any other view that now needs it) to render both.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Concurrency-safe capacity check | A custom mutex/semaphore, a Redis lock, a queued job serializer | `DB::transaction()` + `lockForUpdate()` on the `Section` row | MySQL already provides exactly this primitive; Phase 7 already proved the idiom in this exact codebase for an analogous problem (sequence auto-increment) |
| Fixed-choice reason validation | A hand-rolled `in_array()` check or raw string `Rule::in([...])` | `Rule::enum(RejectionReason::class)` on a native backed enum | Matches every other enum in this codebase (`Role`, `QuestionType`, `EnrollmentStatus`) — one source of truth for the 5 valid values, autocompletable, refactor-safe |
| beforeunload confirmation | A custom modal / SPA route guard / "are you sure" JS library | The native `window.addEventListener('beforeunload', ...)` API | No SPA router exists in this stack; this is a solved browser primitive, not a gap to fill |
| Non-technical documentation | A Markdown renderer package, a docs-site generator | Plain Blade views inside the existing `x-app-layout` shell | Explicit user override in 08-CONTEXT.md; two short pages don't need a documentation toolchain |

**Key insight:** every "hard" problem in this phase (the concurrency race, the visibility/authorization composition, the browser warning) has an existing, already-verified precedent somewhere in Phases 4-7 of this exact repository. The work of this phase is almost entirely pattern-matching those precedents onto new tables/routes, plus the one genuine correction (`AttemptPolicy`).

## Runtime State Inventory

Not applicable — this phase adds new tables/columns and new routes; it does not rename, refactor, or migrate any existing entity. (The `create_exams_table.php` edit is additive — two new nullable columns — not a rename.)

## Common Pitfalls

### Pitfall 1: `AttemptPolicy` left unfixed until "later"

**What goes wrong:** Enrollment withdraw/reject ships, then an in-progress attempt for a withdrawn/rejected student starts 403'ing on autosave/submit mid-exam, discarding their unsaved progress and violating AVL-04, TAK-03, TAK-04 simultaneously.
**Why it happens:** `AttemptPolicy::view()`/`update()`'s dependency on `Exam::visibleTo()` is currently invisible risk — every existing test fixture keeps students Enrolled for the duration of an attempt, so nothing exercises the failure path today.
**How to avoid:** Fix `AttemptPolicy` first (Pattern 5), before writing the withdraw/reject controllers, and add a regression test that starts an attempt, withdraws/rejects the student mid-attempt, and asserts `show`/`answer`/`submit` still succeed.
**Warning signs:** Any test titled `..._during_attempt` returning 403 instead of 200/302.

### Pitfall 2: Rejection reason values sourced from the wrong document

**What goes wrong:** REQUIREMENTS.md's "Resolved Design Decisions (v2.0)" table (#1) lists a *different* 5-value set (`Not eligible for subject · Prerequisite not met · Duplicate enrollment · Section changed · Other`) than 08-CONTEXT.md/08-UI-SPEC.md's locked Copywriting Contract (`Prerequisite not met · Ineligible for this section · Administrative reallocation · Duplicate enrollment · Other (contact lecturer)`). These are genuinely different wording and even different concepts (REQUIREMENTS.md has "Section changed", 08-CONTEXT.md has "Administrative reallocation" instead).
**Why it happens:** REQUIREMENTS.md's decision table predates the phase-specific discuss-phase session that produced 08-CONTEXT.md; the wording was refined and the earlier document was never reconciled.
**How to avoid:** Implement the **08-CONTEXT.md / 08-UI-SPEC.md** 5 values — they are the phase-specific, most-recently-authored, and most-detailed source (full Copywriting Contract with exact modal/label text), and are what the UI-SPEC checker will verify against. Flag REQUIREMENTS.md's table as stale in the plan or PR description so it gets reconciled, but do not implement its wording.
**Warning signs:** A test asserting `'Section changed'` or `'Not eligible for subject'` as a valid enum value — those strings are not in the locked UI-SPEC and should not exist in the enum.

### Pitfall 3: Draft-only exam edit gate blocks setting the availability window post-publish

**What goes wrong:** `UpdateExamRequest::authorize()` returns `! $this->route('exam')->is_published` (verified this session, `app/Http/Requests/Lecturer/UpdateExamRequest.php`) — a **published** exam cannot be edited at all via `lecturer.exams.update`. Since AVL-01's `available_from`/`available_until` fields live on that same create/edit form (per 08-CONTEXT.md's explicit instruction to reuse the existing form), a lecturer who publishes an exam *before* setting its availability window has no way to add one afterward through the UI — the edit route will 403.
**Why it happens:** This is pre-existing Phase 2 behavior (D-06), not a bug introduced this phase — but AVL-01 is the first requirement where it has a *new* user-visible consequence (previously, editing a published exam's title/duration was the only thing blocked; now the availability window is blocked too).
**How to avoid:** This is not a bug to fix — it is a consistency question to surface explicitly to the plan/user: either (a) accept it as intentional (availability must be set before publishing, consistent with the existing "published = immutable" policy), which requires no code change and matches the existing grading-integrity rationale in `unpublish()`'s doc comment, or (b) explicitly carve out `available_from`/`available_until` as editable-even-when-published (a narrow exception to D-06, since changing the availability window doesn't affect already-graded scores the way changing questions/points would). **Recommend (a)** — it requires zero new code, is consistent with existing immutability policy, and the lecturer manual (DEL-05) should simply instruct "set the availability window before publishing."
**Warning signs:** A plan task that tries to make `available_from`/`available_until` editable on a published exam without an explicit design decision recorded for it.

### Pitfall 4: `Enrollment` (a `Pivot` subclass) needs a `section()` relation it doesn't currently have

**What goes wrong:** Pattern 3's ENR-04 check (`whereHas('section', ...)`) will throw a `BadMethodCallException` if `Enrollment::section()` isn't defined — verified this session, `app/Models/Enrollment.php` currently defines only `$table` and `casts()`, no relations.
**How to avoid:** Add `public function section(): BelongsTo { return $this->belongsTo(Section::class); }` (and optionally `user(): BelongsTo` too, for symmetry/future use) to `Enrollment.php` as an explicit file-touch in the plan.

### Pitfall 5: Nullable `datetime-local` fields submitting as empty string, not `null`

**What goes wrong:** An unfilled `<input type="datetime-local">` submits as an empty string `''`, not absent/`null`. A rule of `['nullable', 'date']` still fails validation against `''` in some Laravel versions/configurations because `nullable` only skips *absent* or literally-`null` values by default — an empty string can be treated as "present" depending on how the request's null-conversion middleware is configured [CITED: web search, LOW confidence — verify directly against this project's Laravel 11.55 behavior with a quick manual/automated test before relying on it].
**How to avoid:** Laravel's default `TrimStrings`/`ConvertEmptyStringsToNull` middleware (present in a stock Laravel 11 app via `bootstrap/app.php`'s default middleware stack) converts empty-string form inputs to `null` before validation runs — this is very likely already active and sufficient. **Verify this explicitly** with a Wave-0 test (`available_from` submitted as `''` → assert the created exam's `available_from` is `null`, not a validation error) rather than assuming it, since this project's `bootstrap/app.php` middleware configuration hasn't been read this session.
**Warning signs:** A 422 validation error on a legitimately-blank optional availability field during manual QA.

### Pitfall 6: Existing `lecturer/exams/create.blade.php` / `edit.blade.php` predate the Phase 7 dark-mode reskin

**What goes wrong:** Verified this session — both files still use light-mode-only Tailwind classes (no `dark:` variants), unlike `lecturer/sections/create.blade.php` which was reskinned. Naively pasting the new `available_from`/`available_until` fields in with `dark:` classes (matching 08-UI-SPEC.md's inherited system) next to un-reskinned surrounding fields will look visually inconsistent within the same form.
**How to avoid:** When touching these two files to add the new fields, bring the whole form up to the same dark-mode standard as `lecturer/sections/create.blade.php` (add `dark:` variants to every existing field, not just the two new ones) — this is a small scope increase but avoids shipping a visibly broken dark-mode form. Flag this explicitly in the plan rather than silently expanding scope.

## Code Examples

### Half-open window check (reused three times this phase: sections read, section apply/withdraw, exam availability)

```php
// Section: already the established pattern (lecturer/sections/index.blade.php lines 46-56, verified this session)
if ($now->lt($section->opens_at)) {
    $windowStatus = 'opens'; // NOTE: existing code uses 'opens' not 'opening' for sections —
                              // 08-UI-SPEC.md's new 'opening' keyword is for EXAMS only; do not
                              // rename the existing section pill status, it is unchanged/locked.
} elseif ($now->gte($section->closes_at)) {
    $windowStatus = 'closed';
} else {
    $windowStatus = 'open';
}
```
Recommend extracting this into a `Section::windowStatus(): string` accessor (currently duplicated inline in the Blade view per PATTERNS.md) so the new student-facing sections page can reuse it instead of copy-pasting the `@php` block a second time — a small refactor opportunity noted in the Recommended Project Structure above.

### `Rule::enum()` for the reject reason (Laravel 11-native)

```php
use Illuminate\Validation\Rule;

public function rules(): array
{
    return [
        'reason' => ['required', Rule::enum(RejectionReason::class)],
    ];
}
```

## State of the Art

Not applicable in the traditional "framework changed" sense — this is intra-project consistency, not an external ecosystem shift. The one relevant "old → new" is internal to this codebase's own history:

| Old Approach (Phase 4-7) | Current Approach (Phase 8) | When Changed | Impact |
|---------------------------|------------------------------|---------------|--------|
| `AttemptPolicy::view/update` derived from `Exam::visibleTo()` | Ownership-only, matching `viewResult()` | This phase (required fix) | In-progress attempts survive withdraw/reject/window-close, per AVL-04 |
| Only `session('status')` (green) flash convention | `session('status')` (green) + `session('error')` (red) | This phase (new convention) | First red-refusal-banner pattern in the app; document it for future phases too |

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Laravel 11's default `bootstrap/app.php` middleware stack (unmodified in this project) converts empty-string form fields to `null` before Form Request validation runs, so `nullable\|date` on `available_from`/`available_until` will accept a blank `datetime-local` input without a manual `prepareForValidation()` normalization step | Pitfall 5 | If wrong, every blank optional-window submission 422s; low severity (caught immediately by any manual QA of AVL-01), fix is a one-line `prepareForValidation()` addition |
| A2 | `Rule::enum()` is available and idiomatic on this project's Laravel 11.55 (introduced in Laravel 9, so should be present) | Pattern 7 | Extremely low risk — this is long-stable core Laravel API, not a recent addition |
| A3 | Recommending option (a) in Pitfall 3 (accept draft-only availability editing, no code change) rather than carving out an exception — this is a design recommendation, not a verified requirement; REQUIREMENTS.md/CONTEXT.md do not explicitly resolve this question | Pitfall 3 | If the user actually wants availability editable post-publish, this recommendation under-delivers; low severity to fix later since it's an isolated `authorize()` change |
| A4 | `beforeunload`'s `event.returnValue = ''` line is still the correct/harmless legacy-compat idiom on current browsers as of this session (mid-2026) — based on a general web search, not a direct spec read this session | Pattern 6 | Very low risk — this is long-stable, widely-documented Web API behavior; worst case is a no-op extra line |

**A2 has effectively HIGH confidence** despite being framework-API knowledge rather than a codebase-grep — it's long-stable core Laravel functionality (`Illuminate\Validation\Rule::enum()`, Laravel 9+) rather than a fast-moving or contested area; still logged here per the tagging protocol since it was not directly re-verified against this project's `vendor/laravel/framework` source this session.

## Open Questions

1. **Should the availability window be editable on a published exam?**
   - What we know: The current draft-only edit gate (`UpdateExamRequest::authorize()`) blocks it entirely; this is pre-existing Phase 2 behavior, not new to this phase.
   - What's unclear: Whether the user considers this an acceptable constraint (set the window before publishing) or wants a narrow exception carved out for these two fields specifically.
   - Recommendation: Default to no code change (accept the constraint, document it in the lecturer manual) unless discuss-phase/plan-review surfaces a stronger requirement — see Pitfall 3.

2. **Should `RejectionReason::label()` values be persisted as-is on `rejection_reason`, or should the enum's raw string value be stored and the label resolved only at render time?**
   - What we know: `rejection_reason` is a plain nullable `string` column (verified, `2026_07_15_100011_create_enrollments_table.php`); casting it to `RejectionReason::class` means Eloquent stores/reads the enum's backing string value (`'prerequisite_not_met'`, etc.), and the human label (`'Prerequisite not met'`) is a `label()` method call away.
   - What's unclear: Nothing substantive — this is the standard, already-established pattern in this codebase (`QuestionType`, `EnrollmentStatus` both work this way). Listed here only to make explicit that the column stores the enum *value*, not the label, so `"Rejected: {reason label}"` in the UI-SPEC copy means `$enrollment->rejection_reason->label()`, not the raw column read directly.
   - Recommendation: Follow the established pattern; no open decision remains.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|--------------|-----------|---------|----------|
| PHP | All backend logic | ✓ | 8.2.32 [VERIFIED: `php --version`, this session] | — |
| Composer | Dependency management (no new deps needed this phase) | ✓ | 2.8.2 [VERIFIED: `composer --version`, this session] | — |
| Laravel Framework | Everything | ✓ | 11.55.0 [VERIFIED: `php artisan --version`, this session] | — |
| MySQL (`yp-student-exam` via Herd) | `lockForUpdate()`/transactions (ENR-02, ENR-04), all persistence | ✓ (implied — `php artisan` commands run cleanly against the configured connection, and Phase 7's identical-shape migrations/tests already pass against this same DB per STATE.md's "Phase 7 complete" status) | 8.x per CLAUDE.md | — |

**No missing dependencies.** This phase adds zero new environment requirements beyond what Phases 1-7 already established and verified working.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 11 (Laravel 11's default), `Illuminate\Foundation\Testing\RefreshDatabase` trait [VERIFIED: codebase — every existing Feature test in `tests/Feature/**` uses this trait, e.g. `SectionControllerTest`, `DomainSchemaTest`] |
| Config file | `phpunit.xml` (repo root) — `DB_CONNECTION`/`DB_DATABASE` lines are commented out, meaning tests run against the same MySQL connection configured in `.env`, not an in-memory SQLite swap [VERIFIED: `phpunit.xml` read this session] |
| Quick run command | `php artisan test --filter=<TestClassOrMethod>` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ENR-01 | Section list shows live `28/30` capacity + window status | feature | `php artisan test --filter=SubjectBrowseControllerTest` | ❌ Wave 0 |
| ENR-02 | Concurrent applies never exceed capacity | feature (sequential simulation, not true concurrency — see note below) | `php artisan test --filter=test_applying_to_a_section_at_capacity_is_refused` | ❌ Wave 0 |
| ENR-03 | Withdraw before close date succeeds; after close date refused | feature | `php artisan test --filter=EnrollmentControllerTest` | ❌ Wave 0 |
| ENR-04 | Second active enrollment in same subject+semester refused | feature | `php artisan test --filter=test_a_second_active_enrollment_in_the_same_subject_and_semester_is_refused` | ❌ Wave 0 |
| ENR-05 | Re-apply after withdraw/reject succeeds and updates (not duplicates) the existing row | feature | `php artisan test --filter=test_reapplying_after_withdrawal_updates_the_existing_enrollment_row` | ❌ Wave 0 |
| ENR-06 | Out-of-window sections listed with status label, no Apply action | feature | `php artisan test --filter=test_a_not_yet_open_section_shows_no_apply_button` | ❌ Wave 0 |
| ENR-07 | Any assigned lecturer can reject with a fixed reason; student sees it | feature | `php artisan test --filter=RejectEnrollmentControllerTest` | ❌ Wave 0 |
| AVL-01 | Lecturer sets optional start/end window on exam | feature | `php artisan test --filter=test_a_lecturer_can_set_an_optional_availability_window` | ❌ Wave 0 |
| AVL-02 | Pre-start page shows instructions/duration/window/section + Proceed/Back | feature | `php artisan test --filter=ExamShowTest` | existing file, extend |
| AVL-03 | Attempt start refused outside window with clear message | feature | `php artisan test --filter=test_starting_an_attempt_outside_the_availability_window_is_refused` | ❌ Wave 0 |
| AVL-04 | In-progress attempt survives withdrawal/rejection/window close — **the critical regression test** | feature | `php artisan test --filter=test_an_in_progress_attempt_survives_withdrawal_mid_attempt` | ❌ Wave 0 |
| AVL-05 | `beforeunload` listener attached/detached correctly | manual-only (browser JS event, not server-testable via PHPUnit) | N/A — see justification below | N/A |
| DEL-04 | Student manual covers 5 named task flows | manual-only (content review, not behavior) | N/A | N/A |
| DEL-05 | Lecturer manual covers 4 named task flows | manual-only (content review, not behavior) | N/A | N/A |

**AVL-05 manual-only justification:** PHPUnit's `RefreshDatabase`+HTTP-request test model has no browser JS execution — it cannot dispatch a `beforeunload` event or assert a native browser dialog appeared. If Laravel Dusk (browser automation) were already in this project's `composer.json`, it would be the correct automated vehicle for this one requirement; it is not present, and introducing it would violate CLAUDE.md's "no new Composer packages" constraint for a single interaction test. Recommend a `checkpoint:human-verify` task instead: manually start an attempt, attempt to close the tab, confirm the browser's native dialog appears; then submit/let it auto-submit and confirm no dialog appears on that subsequent navigation.

**ENR-02 concurrency-testing honesty note:** PHPUnit's default test runner is single-threaded/synchronous — it cannot literally fire two simultaneous HTTP requests to prove the race is closed. The standard (and only practical) test for this requirement is a *sequential* simulation: fill a section to `capacity - 1`, then make two back-to-back apply calls as two different students and assert exactly one succeeds and one is refused with the ENR-02 copy — this proves the *count check itself* is correct and the lock doesn't introduce a deadlock/logic error, but it does **not** prove the lock prevents a true simultaneous race (that would require a multi-process test harness, e.g. spawning two `php artisan tinker` subprocesses with a deliberate `sleep()` inside the transaction — disproportionate for this project's scope). The actual concurrency-safety argument rests on the structural presence of `lockForUpdate()` inside the transaction (verifiable by code review, matching the exact pattern already proven correct for the Phase 7 sequence-assignment feature), not on an automated test proving it. State this limitation explicitly in the plan's VALIDATION.md rather than implying full concurrency coverage exists.

### Sampling Rate

- **Per task commit:** targeted `php artisan test --filter=<ClassName>` for the file(s) just touched
- **Per wave merge:** `php artisan test --filter=Enrollment` / `--filter=Availability` / `--filter=Attempt` grouped re-runs, plus the full `tests/Feature/Student/ExamVisibilityRegressionTest.php` (Phase 7's hard gate) to confirm the list/gate-agreement invariant still holds after `AttemptPolicy`'s change
- **Phase gate:** `php artisan test` (full suite) green before `/gsd-verify-work`, with special attention to every existing `tests/Feature/Student/Attempt*.php` file (Phase 4) — the `AttemptPolicy` fix changes shared authorization logic those tests already cover, so a regression there is the highest-value signal that Pattern 5 was applied correctly without breaking existing IDOR protections

### Wave 0 Gaps

- [ ] `tests/Feature/Student/SubjectBrowseControllerTest.php` — covers ENR-01, ENR-06
- [ ] `tests/Feature/Student/EnrollmentControllerTest.php` — covers ENR-02, ENR-03, ENR-04, ENR-05
- [ ] `tests/Feature/Lecturer/RejectEnrollmentControllerTest.php` — covers ENR-07
- [ ] `tests/Feature/Lecturer/ExamAvailabilityTest.php` — covers AVL-01 (form save)
- [ ] `tests/Feature/Student/AttemptAvailabilityTest.php` — covers AVL-03
- [ ] `tests/Feature/Student/AttemptPolicyTest.php` (existing file — extend, not new) — covers AVL-04's critical regression case; this file already exists per the repo's test inventory and is the natural home for the ownership-only fix's direct-access tests
- [ ] `database/factories/ExamFactory.php` — add `available()`/`opening()`/`closed()` states (or plain attribute overrides) so the new tests above don't hand-roll raw datetime math per test

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|----------------|---------|-------------------|
| V2 Authentication | No | Unchanged — Breeze scaffold, out of this phase's scope |
| V3 Session Management | No | Unchanged |
| V4 Access Control | **Yes** | Policy-driven (`AttemptPolicy` fix, new `RejectEnrollmentRequest`/`EnrollRequest` `authorize()`), route-group role middleware (`role:student`/`role:lecturer`), matching the existing SEC-03 per-subject-ownership pattern for every new lecturer write |
| V5 Input Validation | **Yes** | Form Requests with typed rules (`Rule::enum()`, `nullable\|date`, `after` comparisons) for every new write; server-side, never trust client state |
| V6 Cryptography | No | No new secrets/crypto surface this phase |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|----------------------|
| IDOR — a student accessing/withdrawing from another student's enrollment, or a lecturer rejecting a student from a section outside their assigned subjects | Elevation of Privilege | Ownership check in every new Form Request's `authorize()` (mirrors SEC-03); enrollment writes always scope to `auth()->id()` server-side, never accept a `user_id` from the request body for the *student's own* apply/withdraw actions |
| Race-condition overselling (capacity bypass) | Tampering (of the capacity invariant) | `lockForUpdate()` inside `DB::transaction()` — Pattern 1 |
| Authorization regression via shared-predicate coupling — the specific AVL-04 bug this phase must fix | Elevation of Privilege *in reverse* (an authorized user wrongly denied access to their own resource — an availability bug, but rooted in the same class of "predicate drifted from its original scope" issue IDOR mitigations exist to prevent) | `AttemptPolicy` ownership-only fix (Pattern 5); the `ExamVisibilityRegressionTest` hard-gate pattern from Phase 7 (list vs. gate agreement) is the template for a similar regression test guarding this fix |
| Mass-assignment on `rejection_reason`/`status` via a crafted student-side request | Tampering | These fields are only ever written from the lecturer-side `RejectEnrollmentController`/enrollment controllers using `Enrollment::updateOrCreate()` with explicit key-value arrays — never `$request->all()` or a `$fillable`-based mass-assignment from student-facing input; `Enrollment` currently has no `$fillable` property at all (it's a `Pivot`, written to exclusively via pivot-relation methods and explicit arrays) — keep it that way |

## Sources

### Primary (HIGH confidence)
- Direct codebase reads this session (30+ files): `app/Models/{Section,Enrollment,Exam,Attempt,Subject,User}.php`, `app/Policies/{ExamPolicy,AttemptPolicy}.php`, `app/Http/Controllers/{Lecturer/SectionController,Lecturer/SubjectLecturerController,Lecturer/ExamController,Student/AttemptController,Student/ExamController}.php`, `app/Http/Requests/Lecturer/{Store,Update}SectionRequest.php`, `app/Http/Requests/Lecturer/{Store,Update}ExamRequest.php`, `app/Http/Requests/Lecturer/{AssignLecturerRequest,GradeAnswerRequest}.php`, all relevant migrations, `resources/views/{lecturer/sections/index,create}.blade.php`, `resources/views/student/{exams/show,attempts/show}.blade.php`, `resources/views/layouts/navigation.blade.php`, `resources/views/components/status-pill.blade.php`, `database/seeders/DatabaseSeeder.php`, `database/factories/{SectionFactory,ExamFactory}.php`, `tests/Feature/Lecturer/SectionControllerTest.php`, `routes/{student,lecturer}.php`, `phpunit.xml`, `.planning/config.json`
- `php --version` / `composer --version` / `php artisan --version` — direct environment verification this session

### Secondary (MEDIUM confidence)
- WebSearch: MDN "Window: beforeunload event" + chromestatus.com custom-message deprecation (Pattern 6) — cross-referenced against 08-UI-SPEC.md's own explicit instruction, which independently states the same constraint
- WebSearch: multiple Laravel-community sources (Medium, dev.to, backpackforlaravel.com) on `lockForUpdate()`+transaction as the standard race-prevention idiom (Pattern 1) — cross-checked against and subordinate to the in-repo Phase 7 precedent, which is the actual authority here

### Tertiary (LOW confidence)
- WebSearch: empty-string-to-null coercion behavior for `datetime-local` fields under Laravel's default middleware — flagged explicitly as Assumption A1, recommend a Wave-0 test rather than relying on this claim alone

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — zero new dependencies, fully verified against the live environment this session
- Architecture (capacity race, visibility composition, AttemptPolicy fix): HIGH — every pattern is either a direct extension of, or a documented correction to, code read in full this session
- Availability window + form UX (datetime-local null handling): MEDIUM — one open assumption (A1) explicitly flagged for a Wave-0 verification test rather than treated as settled
- beforeunload behavior: MEDIUM — codebase-side implementation is HIGH confidence (matches the explicit UI-SPEC instruction and the existing `attemptTimer()` structure); the underlying browser-API behavior claim is WebSearch-sourced, MEDIUM

**Research date:** 2026-07-16
**Valid until:** 30 days (stable Laravel-native domain logic; no fast-moving external dependency in scope)

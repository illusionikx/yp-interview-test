# Phase 3: Exam Assignment & Class-Scoped Access - Research

**Researched:** 2026-07-15
**Domain:** Laravel 11 authorization (Policies, class-scoped Eloquent queries) — IDOR prevention
**Confidence:** HIGH

## Summary

Phase 3 has almost no new domain modeling to do — `exam_classroom`, `Exam::classrooms()`, `Classroom::exams()`, `is_published`, and `User::classroom_id` all already exist and work (verified directly in `app/Models/Exam.php`, `app/Models/Classroom.php`, `app/Models/User.php`, and the `exam_classroom` migration). The entire phase is one authorization pattern applied twice: a lecturer-side write (`sync()` the pivot) and a student-side read/gate pair (index filter + direct-access policy) that **must share one predicate**. This repo has no `app/Policies/` directory yet — `ExamPolicy` is the first Policy in the project, so getting its shape right here sets the convention Phase 4/5 (`AttemptPolicy`) will copy.

The concrete implementation recommended below centralizes the "published AND assigned to this student's classroom" rule in a single Eloquent local scope on `Exam` (`scopeVisibleTo`), called from both `Student\ExamController@index` (the list) and `ExamPolicy::takeable` (the direct-access gate). This isn't just "use the same logic conceptually" — it's the same method call in both places, so the two can never silently drift apart, which is exactly the failure mode PITFALLS.md Pitfall 2 and ARCHITECTURE.md Anti-Pattern 3 describe ("hidden but reachable"). Route-model binding on `Exam` only confirms the row exists; `$this->authorize('takeable', $exam)` in `Student\ExamController@show` is the actual gate.

**Primary recommendation:** Add `Exam::scopeVisibleTo(Builder $query, User $user)` as the single source of truth for "is this exam visible to this student," call it from `Student\ExamController@index`, and call it again (via `->whereKey($exam->id)->exists()`) inside `ExamPolicy::takeable()`. Enforce with `$this->authorize('takeable', $exam)` on the student `show` route. Assignment is a plain `$exam->classrooms()->sync($request->validated('classroom_ids', []))` behind a dedicated `Lecturer\ExamAssignmentController`, validated by a Form Request using `exists:classrooms,id`. No new packages.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Exam → classroom assignment (write, ASN-01) | API/Backend (`Lecturer\ExamAssignmentController`) | Database (`exam_classroom` pivot) | Pure server-side mutation via `sync()`; no client logic needed beyond a multi-select form post |
| Student exam list filtering (ASN-02) | API/Backend (Eloquent scope) | Database (`whereHas` EXISTS subquery) | Filtering must happen in the query, not in a PHP loop over an unfiltered collection (Performance Trap in PITFALLS.md) |
| Class-scoped access gate (RBAC-05) | API/Backend (`ExamPolicy`) | — | Authorization is inherently a backend-only concern; the browser cannot be trusted to enforce it (this is the entire point of the phase) |
| Student exam landing page render | Frontend Server (Blade) | Browser (Alpine, disabled Start affordance) | Read-only server-rendered view; the only client-side piece is the inert "Start" stub, which does nothing until Phase 4 |
| Route-group role gating (lecturer vs student area) | API/Backend (`role:*` middleware, already built in Phase 1) | — | Coarse first-line filter; unchanged this phase, just reused |

## User Constraints

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01 (ASN-01):** Assign on the lecturer exam page — a multi-select of classrooms synced through the `exam_classroom` pivot (`$exam->classrooms()->sync($request->validated('classroom_ids', []))`). A dedicated endpoint (e.g. `Lecturer\ExamAssignmentController@update` or a method on `ExamController`) with a Form Request validating classroom ids exist. Assignment is editable regardless of publish state; visibility to students additionally requires `is_published`.
- **D-02 (ASN-02):** A student sees only exams where `is_published = true` AND the exam is assigned to the student's `classroom_id`. Query via the relationship: `Exam::where('is_published', true)->whereHas('classrooms', fn ($q) => $q->whereKey($student->classroom_id))`. A student with `classroom_id = null` sees an empty list. The index and the policy MUST use the same predicate (single source of truth).
- **D-03 (RBAC-05 core):** An `ExamPolicy` with a method (e.g. `takeable(User $user, Exam $exam)` / `viewAsStudent`) returning true only when the exam is published AND assigned to the user's classroom AND the user is a Student. Enforce with `$this->authorize('takeable', $exam)` (→ 403) on the student exam **show** route, and reuse the *same* predicate to scope the student index. Register the policy (Laravel 11 auto-discovery `App\Policies\ExamPolicy`). This is the cross-cutting authorization convention the research calls for — Phase 4/5 will add `AttemptPolicy`/result gates following the same shape.
- **D-04:** No IDOR: a student opening the URL of an exam not assigned to their classroom, or not published, is denied server-side (403/404), not merely omitted from the list. This is the concrete, testable heart of RBAC-05 for this phase.
- **D-05:** A `Student\ExamController` (index + show) mounted in the existing `routes/student.php` group (already `auth` + `role:student` gated from Phase 1). Index lists assigned published exams; show is a **read-only** landing page (title, subject, duration, question count) with a "Start" button. The Start action targets the Phase-4 attempt route — in this phase it may be a placeholder/disabled-until-Phase-4 stub. Do NOT render questions or any attempt logic here.
- **D-06:** Reuse Breeze Blade + Tailwind + Alpine and the Phase-1 `student.home` placeholder (add navigation to the exam list). No new packages. The `exam_classroom` pivot + `Exam::classrooms()` / `Classroom::exams()` relationships already exist from Phase 1 schema — verify/relabel, do not recreate.

### Claude's Discretion

- Whether assignment lives on `ExamController` vs a dedicated `ExamAssignmentController`; exact policy method name; student landing page layout; how the Phase-4 "Start" stub is represented — planner/executor choice, provided D-01..D-05 hold.

### Deferred Ideas (OUT OF SCOPE)

- **Taking an exam — attempt creation, question rendering, countdown timer, autosave, submit (TAK-01..06)** → Phase 4. The "Start" button is only a seam here.
- **AttemptPolicy + result-access gates (part of RBAC-05's "attempt, result" clause)** → Phases 4/5, following the ExamPolicy pattern established here.
- **Grading & results** → Phase 5.

None of the above are user scope-creep — they are the phase boundaries, noted so the planner doesn't pull them forward.
</user_constraints>

## Phase Requirements

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ASN-01 | Lecturer can assign an exam to one or more classrooms | Dedicated `Lecturer\ExamAssignmentController@update` + `AssignExamRequest` Form Request + `sync()` pattern (Pattern 1 below); mirrors the existing `ClassroomRosterController`/`ExamQuestionController` sub-resource convention already in this codebase |
| ASN-02 | A Student sees only published exams assigned to their own classroom | `Exam::scopeVisibleTo()` local scope (Pattern 2); consumed by `Student\ExamController@index` |
| RBAC-05 | A Student can only access a resource (exam, attempt, result) belonging to them or their class — direct URLs to others' resources are denied (no IDOR) | `ExamPolicy::takeable()` reusing `scopeVisibleTo()`; `$this->authorize()` on `Student\ExamController@show`; full IDOR test matrix (Validation Architecture below) |
</phase_requirements>

## Project Constraints (from CLAUDE.md)

- **Tech stack is fixed:** Laravel 11 + Breeze, MySQL (`yp-student-exam` via Herd), Blade + Tailwind + Alpine — no SPA, no new Composer packages for this domain (confirmed again for this phase: assignment/authorization is 100% native Laravel).
- **No `spatie/laravel-permission` or any RBAC package** — two fixed roles, Policies/Gates/middleware only (STACK.md "What NOT to Use").
- **Route-group middleware + Policies, two layers, never one** (ARCHITECTURE.md Pattern 1 / Anti-Pattern 2) — `role:student` middleware is coarse; `ExamPolicy` is the fine-grained gate this phase adds.
- **GSD workflow enforcement:** file changes for this phase happen through `/gsd-execute-phase`, not ad-hoc edits.
- Existing codebase conventions this phase must match (verified by reading the actual files, not just prior SUMMARYs):
  - Form Requests use `authorize(): bool { return true; }` when the route-group middleware is the only gate needed, with a code comment explaining why (see `StoreExamRequest`).
  - `created_by`/ownership-sensitive fields are always stamped/merged server-side in the controller, never trusted from `$request->validated()`.
  - Sub-resource actions (`ClassroomRosterController`, `ExamQuestionController`) live in their own single-purpose controller nested under the owning resource's routes, not bolted onto the owning resource's own controller. **`ExamAssignmentController` should follow this same convention.**

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | ^11.31 (confirmed in `composer.json`) | Policies, Eloquent `whereHas`/local scopes, Form Requests | Already the project's mandated framework; Policy auto-discovery and `authorize()` → `AuthorizationException` → 403 are native since Laravel 8/11, no package needed [CITED: laravel.com/docs/11.x/authorization] |
| PHPUnit | ^11.0.1 (confirmed in `composer.json`) | Feature tests for the IDOR matrix | Already installed; `RefreshDatabase` + `actingAs()` is the established pattern in every existing `tests/Feature/Lecturer/*Test.php` |

### Supporting

*(none — no new supporting libraries needed)*

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Eloquent Policy (`ExamPolicy`) | `Gate::define('takeable', ...)` closure in `AppServiceProvider` | Gates are for checks with no natural model (STACK.md already reserves Gates for that); this check is entirely model-scoped, so a Policy is the correct native fit and matches ARCHITECTURE.md's stated component responsibilities |
| Single shared `scopeVisibleTo()` predicate | Two independently-written queries (one in the controller index, one inline in the policy) | Two independent queries is exactly the "index and direct-access diverge" bug class D-02/D-03 explicitly warn against — rejected |
| Dedicated `ExamAssignmentController` | Add an `assign`/`updateAssignment` method directly on `Lecturer\ExamController` | The codebase already has two precedents for "nested sub-resource gets its own controller" (`ClassroomRosterController`, `ExamQuestionController`); a dedicated controller keeps `ExamController` from growing an unrelated pivot-management responsibility and matches CONTEXT.md's explicit discretion note |

**Installation:**
```bash
# No composer install needed — everything below uses framework primitives
# already present in composer.json (laravel/framework ^11.31).
php artisan make:policy ExamPolicy --model=Exam
php artisan make:controller Lecturer/ExamAssignmentController
php artisan make:request Lecturer/AssignExamRequest
php artisan make:controller Student/ExamController
```

**Version verification:** `composer.json` confirms `laravel/framework: "^11.31"` and `phpunit/phpunit: "^11.0.1"` directly (read from the repo, not assumed) [VERIFIED: composer.json]. No version bump or new dependency is required for this phase.

## Package Legitimacy Audit

**Not applicable this phase.** Per D-06, Phase 3 introduces zero new Composer packages — `php artisan make:policy`/`make:controller`/`make:request` scaffold plain PHP classes using framework code already resolved in `composer.lock`. The Package Legitimacy Gate protocol (registry checks, `npm view`-equivalent, postinstall-script audit) has nothing to run against.

**Packages removed due to [SLOP] verdict:** none — no packages were proposed.
**Packages flagged as suspicious [SUS]:** none.

## Architecture Patterns

### System Architecture Diagram

```
LECTURER SIDE (write: ASN-01)
──────────────────────────────
Blade multi-select form (lecturer/exams/show.blade.php, new "Assign to classes" panel)
   │  PUT /lecturer/exams/{exam}/assignment   [role:lecturer middleware]
   ▼
AssignExamRequest::rules()  ──► classroom_ids.* validated: integer, distinct, exists:classrooms,id
   │  $request->validated('classroom_ids', [])
   ▼
Lecturer\ExamAssignmentController@update
   │  $exam->classrooms()->sync($validatedIds)   ← no ownership check needed (any lecturer may edit any exam, per D-09 precedent); editable regardless of is_published
   ▼
exam_classroom pivot table  (rows added/removed to exactly match the synced set)


STUDENT SIDE (read + gate: ASN-02, RBAC-05)
──────────────────────────────────────────
GET /student/exams                                    GET /student/exams/{exam}
   │  [role:student middleware]                            │  [role:student middleware]
   ▼                                                        ▼
Student\ExamController@index                          Student\ExamController@show
   │  Exam::visibleTo($request->user())                    │  route-model binding resolves $exam
   │    ──► Exam::scopeVisibleTo()                          │    by EXISTENCE ONLY (no ownership check yet —
   │        where(is_published, true)                       │    this is the exact gap Pitfall 2 describes)
   │        ->whereHas('classrooms', ...)                   │  $this->authorize('takeable', $exam)
   ▼                                                         ▼
Rendered list — only published            ExamPolicy::takeable($user, $exam)
+ assigned-to-my-classroom exams              │  calls the SAME Exam::visibleTo($user) scope
                                               │  ->whereKey($exam->id)->exists()
                                               ▼
                                    true  → render read-only landing (title, subject,
                                            duration, question count, disabled "Start")
                                    false → AuthorizationException → 403
                                            (never a redirect to the index — direct
                                            confirmation the resource is denied, not hidden)
```

### Recommended Project Structure

```
app/
├── Policies/
│   └── ExamPolicy.php                          # NEW — first Policy in the project
├── Http/
│   ├── Controllers/
│   │   ├── Lecturer/
│   │   │   └── ExamAssignmentController.php     # NEW — update() only, sync() the pivot
│   │   └── Student/
│   │       └── ExamController.php               # NEW — index() + show()
│   └── Requests/
│       └── Lecturer/
│           └── AssignExamRequest.php             # NEW — classroom_ids.* exists:classrooms,id
app/Models/Exam.php                               # MODIFIED — add scopeVisibleTo()
routes/
├── lecturer.php                                  # MODIFIED — one PUT route for assignment
└── student.php                                   # MODIFIED — exams.index / exams.show
resources/views/
├── lecturer/exams/show.blade.php                 # MODIFIED — add "Assign to classes" panel
└── student/
    ├── home.blade.php                            # MODIFIED — link to student.exams.index
    └── exams/
        ├── index.blade.php                       # NEW
        └── show.blade.php                        # NEW — read-only landing + disabled Start
tests/Feature/
├── Lecturer/ExamAssignmentTest.php               # NEW — ASN-01
└── Student/
    ├── ExamIndexTest.php                         # NEW — ASN-02
    └── ExamAccessTest.php                        # NEW — RBAC-05 IDOR matrix
```

### Pattern 1: Single-purpose sub-resource controller for the pivot (ASN-01)

**What:** `Lecturer\ExamAssignmentController` has exactly one action (`update`), mirroring `ClassroomRosterController`/`ExamQuestionController`'s already-established shape in this codebase.
**When to use:** Any time a pivot/sub-resource is managed from the owning resource's page but deserves its own controller so the owning resource's controller (`ExamController`) doesn't accumulate unrelated responsibilities.
**Example:**
```php
// app/Http/Controllers/Lecturer/ExamAssignmentController.php
namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\AssignExamRequest;
use App\Models\Exam;
use Illuminate\Http\RedirectResponse;

class ExamAssignmentController extends Controller
{
    /**
     * Sync the classrooms this exam is assigned to.
     *
     * Editable regardless of publish state (D-01) — draft exams may be
     * pre-assigned before publishing. Visibility to students is gated
     * separately by is_published in ExamPolicy/scopeVisibleTo, not here.
     */
    public function update(AssignExamRequest $request, Exam $exam): RedirectResponse
    {
        $exam->classrooms()->sync($request->validated('classroom_ids', []));

        return redirect()->route('lecturer.exams.show', $exam)
            ->with('status', 'Classroom assignment updated.');
    }
}
```
```php
// app/Http/Requests/Lecturer/AssignExamRequest.php
namespace App\Http\Requests\Lecturer;

use Illuminate\Foundation\Http\FormRequest;

class AssignExamRequest extends FormRequest
{
    // role:lecturer route-group middleware is the only gate needed —
    // any lecturer may assign any exam (no per-record ownership, matching
    // ExamController's existing StoreExamRequest/UpdateExamRequest precedent).
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'classroom_ids' => ['array'],
            'classroom_ids.*' => ['integer', 'distinct', 'exists:classrooms,id'],
        ];
    }
}
```
```php
// routes/lecturer.php addition
Route::put('exams/{exam}/assignment', [ExamAssignmentController::class, 'update'])
    ->name('exams.assignment.update');
```
Source: `exists:table,column` array-item validation pattern [CITED: laravel.com/docs/11.x/validation — array validation with the `.*` wildcard]; `sync()` on `BelongsToMany` [VERIFIED: `app/Models/Exam.php`/`Classroom.php` — `classrooms()`/`exams()` relationships already return `BelongsToMany` over the `exam_classroom` pivot].

### Pattern 2: One shared query scope as the single source of truth (ASN-02 + RBAC-05 core)

**What:** `Exam::scopeVisibleTo()` is the *only* place "is this exam visible to this student" is expressed. The index uses it to build a list; the Policy uses it to answer a yes/no question about one exam. Same method, same SQL shape, called twice.
**When to use:** Any time an index filter and a direct-access authorization check need to agree — which is every class-scoped or ownership-scoped resource in this app (and the pattern Phase 4/5's `AttemptPolicy` should copy for attempts/results).
**Example:**
```php
// app/Models/Exam.php — add to the existing Exam model
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

/**
 * The single predicate for "is this exam visible to this student" (D-02/D-03).
 * Consumed by both Student\ExamController@index (the list) and
 * ExamPolicy::takeable() (the direct-access gate) — never re-derive this
 * query anywhere else, or the list and the gate can silently diverge.
 *
 * Explicitly guards classroom_id === null (rather than relying on Eloquent's
 * where(column, null) → whereNull() auto-coercion) so a student with no
 * classroom assignment yet always resolves to zero rows, never "no filter".
 */
public function scopeVisibleTo(Builder $query, User $user): Builder
{
    return $query
        ->where('is_published', true)
        ->when(
            $user->classroom_id,
            fn (Builder $q, int $classroomId) => $q->whereHas(
                'classrooms',
                fn (Builder $pivot) => $pivot->whereKey($classroomId)
            ),
            fn (Builder $q) => $q->whereRaw('0 = 1'), // no classroom => nothing visible
        );
}
```
```php
// app/Http/Controllers/Student/ExamController.php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function index(Request $request): View
    {
        $exams = Exam::visibleTo($request->user())
            ->with('subject')
            ->orderBy('title')
            ->get();

        return view('student.exams.index', compact('exams'));
    }

    public function show(Request $request, Exam $exam): View
    {
        // Route-model binding above only confirmed the exam ROW exists.
        // This is the actual ownership/class-scoping gate (RBAC-05/D-04).
        $this->authorize('takeable', $exam);

        $exam->load('subject')->loadCount('questions');

        return view('student.exams.show', compact('exam'));
    }
}
```
```php
// app/Policies/ExamPolicy.php
namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    /**
     * Can this student open/take this exam (D-03/D-04)?
     * Reuses Exam::visibleTo() — the identical predicate the student
     * index uses — so a student can never reach an exam via direct URL
     * that the index would have hidden from them.
     */
    public function takeable(User $user, Exam $exam): bool
    {
        return $user->isStudent()
            && Exam::visibleTo($user)->whereKey($exam->id)->exists();
    }
}
```
```php
// routes/student.php addition
use App\Http\Controllers\Student\ExamController;

Route::get('exams', [ExamController::class, 'index'])->name('exams.index');
Route::get('exams/{exam}', [ExamController::class, 'show'])->name('exams.show');
```
No explicit policy registration call is required — Laravel 11 auto-discovers `App\Policies\ExamPolicy` for `App\Models\Exam` by naming convention alone [CITED: laravel.com/docs/11.x/authorization — "Policy auto-discovery"]. `$this->authorize()` throws `AuthorizationException`, which Laravel converts to a 403 response automatically [CITED: laravel.com/docs/11.x/authorization].

### Pattern 3: Phase-4 seam without a dead route

**What:** The student exam `show` page has a "Start" button (D-05) but the attempt-start route doesn't exist until Phase 4. Calling `route('student.attempts.store', $exam)` in Blade before that route is registered throws a `RouteNotFoundException` on every page render — a subtle way to accidentally break this phase's own deliverable.
**When to use:** Any UI seam that visually points at a not-yet-built feature.
**Example:**
```blade
{{-- resources/views/student/exams/show.blade.php --}}
<x-primary-button disabled class="opacity-50 cursor-not-allowed" title="{{ __('Coming soon') }}">
    {{ __('Start') }}
</x-primary-button>
<p class="text-xs text-gray-500 mt-1">{{ __('Taking exams is not available yet.') }}</p>
```
No `route()` call to an undefined name; the button is visibly present (satisfies D-05's UI requirement) but inert, and nothing breaks when Phase 4 later replaces it with a real form/link.

### Anti-Patterns to Avoid

- **Trusting route-model binding as authorization:** `Exam $exam` in a controller signature only proves a row with that ID exists — it says nothing about whether *this* student may see it. Always pair with `$this->authorize()` (PITFALLS.md Pitfall 2, ARCHITECTURE.md Anti-Pattern 3).
- **Two independently-written "is visible" queries:** one in the index, a differently-worded one in the policy. They will diverge the first time either is edited without remembering the other exists. Use `scopeVisibleTo()` from both.
- **Filtering the index in PHP after loading everything:** `Exam::all()->filter(...)` instead of pushing the predicate into the query — PITFALLS.md flags this as a performance trap and it also means the "same predicate" rule can't be literally the same code.
- **Silently allowing `classroom_id = null` through an unguarded `whereHas`:** if the null-guard in `scopeVisibleTo()` is dropped, a student with no classroom yet could match an exam whose pivot check degenerates unexpectedly depending on how the null is handled — always test this case explicitly (see Validation Architecture).
- **Gating only `is_published` without also gating classroom assignment (or vice versa):** D-01 explicitly allows assigning classrooms to a *draft* exam — if `ExamPolicy` only checked "assigned to my classroom" and forgot `is_published`, a student could see draft exams the moment a lecturer pre-assigns them, before the lecturer intends to publish.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| "Is this exam mine to see" check duplicated in two places | A second inline `if` in the policy that re-lists the same conditions as the index query | The shared `Exam::scopeVisibleTo()` local scope, called from both | Eloquent local scopes exist exactly for this — one query builder fragment, reusable everywhere, single point of change |
| Validating an array of classroom IDs | Manual loop calling `Classroom::find($id)` per submitted ID and collecting errors | `'classroom_ids.*' => ['integer', 'distinct', 'exists:classrooms,id']` in a Form Request | Laravel's array validation with the `.*` wildcard already does exactly this, with proper per-item error messages, for free |
| Syncing exam↔classroom assignments (add some, remove others, leave others untouched) | Manually diffing the current pivot rows against the submitted list and issuing individual `attach()`/`detach()` calls | `$exam->classrooms()->sync($ids)` | `sync()` is precisely "make the pivot table match this exact set," already computes the diff internally, and is transactional |

**Key insight:** Both "hand-roll" temptations in this phase (a second authorization check, a manual ID-array validator) exist because it briefly looks faster than reusing/learning the shared abstraction — but each one is the exact bug class (drifted authorization rules, missed validation edge cases) this phase exists to prevent.

## Common Pitfalls

### Pitfall 1: Index and policy predicates drift apart

**What goes wrong:** The student index query and `ExamPolicy::takeable()` are written as two separately-worded conditions that happen to agree today. A future edit (e.g., adding a "featured exams" filter to the index, or fixing an off-by-one in the policy) updates one and not the other, and now an exam is either visible-but-unreachable or hidden-but-reachable.
**Why it happens:** It's natural to write the policy check by re-reading and re-typing the index query's intent rather than literally calling the same code.
**How to avoid:** `Exam::scopeVisibleTo()` (Pattern 2) is called by name from both places — there is no second query to drift.
**Warning signs:** Any `grep` for `is_published` or `classroom_id` in `ExamPolicy.php` that isn't `Exam::visibleTo(...)`.

### Pitfall 2: `classroom_id = null` silently matching everything (or nothing, for the wrong reason)

**What goes wrong:** A student who hasn't been assigned to a classroom yet (`classroom_id IS NULL`) either (a) sees every published exam because the `whereHas` clause was accidentally skipped for null, or (b) the code happens to work today by accident of how Laravel's query builder treats `where(column, null)` (auto-converts to `whereNull`), which is easy to break unknowingly in a future refactor.
**Why it happens:** Null is an edge case that doesn't show up until a real not-yet-assigned student logs in; the "it happens to work" path relies on implicit framework behavior that isn't obvious from reading the code.
**How to avoid:** The `->when($user->classroom_id, ..., fn ($q) => $q->whereRaw('0 = 1'))` explicit branch in `scopeVisibleTo()` makes the empty-result intent readable and independent of `where()`'s null-coercion behavior.
**Warning signs:** No test exercises a student factory with `classroom_id: null`.

### Pitfall 3: Assignment editable-regardless-of-publish-state accidentally becomes a visibility leak

**What goes wrong:** D-01 intentionally allows assigning classrooms to a draft exam (so a lecturer can prepare assignment before publishing). If `ExamPolicy`/`scopeVisibleTo()` only checked classroom assignment and not `is_published`, that pre-assignment would make the draft exam immediately visible to students.
**Why it happens:** "Assignment" and "publish" are two independent booleans on two different tables, and it's easy to gate on only one when writing the visibility check quickly.
**How to avoid:** `scopeVisibleTo()` always ANDs `is_published = true` with the classroom-assignment `whereHas` — never one or the other.
**Warning signs:** A test that assigns-but-doesn't-publish an exam and expects it to be invisible to students is missing or failing.

### Pitfall 4: Dead route reference in the Phase-4 "Start" seam

**What goes wrong:** `route('student.attempts.store', $exam)` (or similar) is written into `show.blade.php` before that route exists, throwing `RouteNotFoundException` on every render of the page this phase is supposed to deliver.
**Why it happens:** It's tempting to "wire it forward" to look complete.
**How to avoid:** Pattern 3 — a disabled button with no `route()` call, or a route to a real-but-inert stub, not a call to a name that doesn't exist yet.
**Warning signs:** `php artisan route:list` doesn't show the referenced route name, or the student exam show page 500s.

### Pitfall 5: A lecturer account is never tested against the student routes

**What goes wrong:** D-03 explicitly requires "a Lecturer hitting the student route → blocked by role middleware" as part of the test matrix. It's easy to test the reverse (student on lecturer routes, already covered in Phase 2's `ExamControllerTest`) and forget the direction that matters for this phase's new routes.
**Why it happens:** The existing `role:student` middleware from Phase 1 already blocks this — but "the middleware exists" and "the middleware is proven to cover *these specific new routes*" are different claims until a test asserts it.
**How to avoid:** Include an explicit `test_a_lecturer_is_forbidden_from_the_student_exam_index`/`_show` test in `tests/Feature/Student/ExamAccessTest.php`.
**Warning signs:** `ExamAccessTest.php` only has student-as-wrong-class cases, no lecturer-as-student case.

## Code Examples

### Full IDOR-relevant flow (index → direct access)

```php
// Exam::visibleTo() usage in the index (list)
$exams = Exam::visibleTo($request->user())->with('subject')->orderBy('title')->get();

// The exact same predicate, reused for the direct-access gate
public function takeable(User $user, Exam $exam): bool
{
    return $user->isStudent()
        && Exam::visibleTo($user)->whereKey($exam->id)->exists();
}
```
Source pattern for `whereHas` + pivot-scoped `whereKey`: [CITED: laravel.com/docs/11.x/eloquent-relationships — "Querying Relationship Existence"].

### Policy auto-discovery — no manual registration

```php
// app/Policies/ExamPolicy.php — discovered automatically because
// App\Models\Exam ↔ App\Policies\ExamPolicy follows Laravel's naming
// convention. No AuthServiceProvider registration needed in Laravel 11.
```
Source: [CITED: laravel.com/docs/11.x/authorization — "Policy auto-discovery... Laravel will check for policies in app/Models/Policies then app/Policies"].

### Form Request array-of-ids validation

```php
public function rules(): array
{
    return [
        'classroom_ids' => ['array'],
        'classroom_ids.*' => ['integer', 'distinct', 'exists:classrooms,id'],
    ];
}
```
Source: [CITED: laravel.com/docs/11.x/validation — array validation with the `.*` wildcard and the `exists` rule]. Note: `exists:classrooms,id` issues one query per submitted array item; at this project's scale (a handful of classrooms per assignment form) this is not a performance concern [CITED: github.com/laravel/framework/discussions/53420 — documents this as a known, accepted N+1 for small arrays].

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| Manual `AuthServiceProvider::$policies` array registering each Policy explicitly | Convention-based auto-discovery (`App\Models\X` ↔ `App\Policies\XPolicy`) | Laravel 8+ (still current in 11.x) | `ExamPolicy.php` needs no registration boilerplate — just create the class with the right name in the right namespace |
| `app/Http/Kernel.php` middleware groups | `bootstrap/app.php` → `->withMiddleware()` | Laravel 11 skeleton redesign | Already reflected in this repo's `bootstrap/app.php` (`role` alias registered there) — no action needed this phase, noted only so nothing is added to a non-existent `Kernel.php` |

**Deprecated/outdated:** None specific to this phase's scope — the patterns used here (Policies, local scopes, Form Requests) have been stable Laravel conventions since well before v11 and are not subject to imminent change.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Eloquent's `Builder::where($column, '=', null)` (as used internally by `whereKey($id)` when `$id` is `null`) auto-converts to `whereNull($column)` rather than throwing or matching nothing predictably | Pitfall 2 / Pattern 2 | Low — the recommended implementation explicitly avoids relying on this by using `->when($user->classroom_id, ..., fn ($q) => $q->whereRaw('0 = 1'))`, so this claim only matters if a future edit removes the explicit guard. Flagged so the planner keeps the guard rather than "simplifying" it away. |

**All other claims in this research were verified directly against the checked-out codebase (`app/Models/*.php`, `composer.json`, `routes/*.php`, existing test files) or cited to `laravel.com/docs/11.x` pages returned directly by search — no user confirmation needed beyond A1.**

## Open Questions

1. **Exact name for the `ExamPolicy` ability — `takeable` vs `viewAsStudent`?**
   - What we know: CONTEXT.md D-03 explicitly leaves this to discretion, offering both names as examples.
   - What's unclear: Which reads better alongside a future `AttemptPolicy` (Phase 4) — `takeable` pairs naturally with "start an attempt," `viewAsStudent` pairs naturally with a generic "can this role see this resource" convention reused across future policies.
   - Recommendation: Use `takeable` — it names the actual capability being gated (the student is checking whether they may *take* this exam), and Phase 4's `AttemptPolicy` can independently use `view`/`update` per Laravel's own convention-method names for attempt ownership, so there's no naming collision to resolve later.

2. **Does `Lecturer\ExamAssignmentController` need its own feature test file or can it live inside `ExamControllerTest.php`?**
   - What we know: The codebase's existing precedent (`ExamPublishTest.php` sitting alongside `ExamControllerTest.php` for the same `ExamController`, but `ClassroomRosterTest.php` as its own file for `ClassroomRosterController`) shows mixed practice.
   - What's unclear: No single rule in the codebase settles this either way.
   - Recommendation: Own file (`tests/Feature/Lecturer/ExamAssignmentTest.php`) — assignment is a distinct controller with distinct requirements (ASN-01), matching the `ClassroomRosterTest.php` precedent, and keeps `ExamControllerTest.php` from growing unrelated concerns.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.0.1 (confirmed `composer.json`) via `php artisan test` |
| Config file | `phpunit.xml` — `DB_CONNECTION`/`DB_DATABASE` overrides are commented out, so tests run against the project's configured MySQL (`yp-student-exam` via Herd), per PROJECT.md's constraint to always test against the real configured DB, not SQLite |
| Quick run command | `php artisan test --filter=ExamAssignmentTest` / `--filter=ExamAccessTest` / `--filter=ExamIndexTest` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ASN-01 | Lecturer syncs classroom_ids → exam_classroom pivot rows match exactly | feature | `php artisan test --filter=test_a_lecturer_can_assign_an_exam_to_classrooms` | ❌ Wave 0 |
| ASN-01 | Re-syncing with a smaller set detaches removed classrooms | feature | `php artisan test --filter=test_resyncing_removes_unselected_classrooms` | ❌ Wave 0 |
| ASN-01 | Invalid/non-existent classroom_id is rejected by validation | feature | `php artisan test --filter=test_assignment_rejects_a_nonexistent_classroom_id` | ❌ Wave 0 |
| ASN-01 | Assignment works on a draft (unpublished) exam | feature | `php artisan test --filter=test_a_draft_exam_can_be_assigned_before_publishing` | ❌ Wave 0 |
| ASN-01 | A student is forbidden from the assignment endpoint | feature | `php artisan test --filter=test_a_student_is_forbidden_from_assigning_an_exam` | ❌ Wave 0 |
| ASN-02 | Index shows a published exam assigned to the student's classroom | feature | `php artisan test --filter=test_a_student_sees_a_published_exam_assigned_to_their_classroom` | ❌ Wave 0 |
| ASN-02 | Index excludes an unpublished exam even if assigned | feature | `php artisan test --filter=test_index_excludes_an_unpublished_but_assigned_exam` | ❌ Wave 0 |
| ASN-02 | Index excludes a published exam assigned to a different classroom | feature | `php artisan test --filter=test_index_excludes_a_published_exam_for_a_different_classroom` | ❌ Wave 0 |
| ASN-02 | A student with `classroom_id = null` sees an empty index | feature | `php artisan test --filter=test_a_student_with_no_classroom_sees_an_empty_index` | ❌ Wave 0 |
| RBAC-05 | Student in the assigned + published class can open the exam (200) | feature | `php artisan test --filter=test_a_student_in_the_assigned_class_can_view_the_published_exam` | ❌ Wave 0 |
| RBAC-05 | Student in a different class is forbidden (403), not merely omitted | feature | `php artisan test --filter=test_a_student_in_a_different_class_is_forbidden` | ❌ Wave 0 |
| RBAC-05 | Unpublished exam is forbidden even if assigned to the student's class | feature | `php artisan test --filter=test_an_unpublished_but_assigned_exam_is_forbidden` | ❌ Wave 0 |
| RBAC-05 | Student with `classroom_id = null` gets 403 on direct access (in addition to empty index above) | feature | `php artisan test --filter=test_a_student_with_no_classroom_is_forbidden_direct_access` | ❌ Wave 0 |
| RBAC-05 | A Lecturer hitting the student exam routes is blocked by role middleware | feature | `php artisan test --filter=test_a_lecturer_is_forbidden_from_the_student_exam_routes` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** the relevant filtered test class (`--filter=ExamAssignmentTest` / `ExamIndexTest` / `ExamAccessTest`)
- **Per wave merge:** `php artisan test` (full suite — this phase's changes touch shared `Exam` model behavior, so the existing Phase 1/2 suites must stay green too)
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/Lecturer/ExamAssignmentTest.php` — covers ASN-01 (new file)
- [ ] `tests/Feature/Student/ExamIndexTest.php` — covers ASN-02 (new file)
- [ ] `tests/Feature/Student/ExamAccessTest.php` — covers RBAC-05 IDOR matrix (new file)
- [ ] No new fixtures/factories needed: `ExamFactory` (with `->published()`), `ClassroomFactory`, and `UserFactory::student()`/`::lecturer()` already exist from Phases 1–2 and are sufficient — tests just need `User::factory()->student()->create(['classroom_id' => $classroom->id])`, which works today since `classroom_id` is already `fillable` on `User`.
- [ ] No framework install needed — PHPUnit 11 + `RefreshDatabase` already proven working in every existing `tests/Feature/Lecturer/*Test.php`.

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-------------------|
| V2 Authentication | No (unchanged — Breeze, established Phase 1) | — |
| V3 Session Management | No (unchanged) | — |
| V4 Access Control | **Yes — this phase's entire purpose** | `ExamPolicy::takeable()` (object-level) + existing `role:student`/`role:lecturer` middleware (route-group level); two-layer authorization per ARCHITECTURE.md Pattern 1 |
| V5 Input Validation | Yes | `AssignExamRequest` — `classroom_ids.*` validated `integer`, `distinct`, `exists:classrooms,id` before it ever reaches `sync()` |
| V6 Cryptography | No | — |

### Known Threat Patterns for This Phase's Stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|-----------------------|
| IDOR — student opens a guessed/forged `/student/exams/{id}` URL for an exam not assigned to their class (CWE-639) | Information Disclosure / Elevation of Privilege | `$this->authorize('takeable', $exam)` on every ID-addressable student exam route; never rely on route-model binding or "the index doesn't link to it" alone |
| Divergent authorization logic between list and detail views | Elevation of Privilege | Single shared `Exam::scopeVisibleTo()` predicate (Pattern 2) — eliminates the divergence vector by construction |
| Forged `classroom_ids` payload attempting to assign an exam to an arbitrary classroom ID (e.g. one belonging to a different lecturer's cohort, if multi-tenant were in scope) | Tampering | `exists:classrooms,id` validation rejects any ID not present in the `classrooms` table; no ownership scoping needed since classrooms aren't per-lecturer in this schema (confirmed: `Classroom` has no `created_by`/owner column) |
| Pre-assigning classrooms to a draft exam inadvertently exposing it before intended publish | Information Disclosure | `scopeVisibleTo()` always ANDs `is_published = true` with the classroom check — assignment and publish are independent gates that must both pass |

## Sources

### Primary (HIGH confidence)
- `app/Models/Exam.php`, `app/Models/Classroom.php`, `app/Models/User.php`, `database/migrations/*_exam_classroom_table.php`, `composer.json`, `routes/lecturer.php`, `routes/student.php`, `app/Http/Middleware/EnsureUserHasRole.php`, `bootstrap/app.php`, `tests/Feature/Lecturer/ExamControllerTest.php` — read directly from the repository this session [VERIFIED: codebase]

### Secondary (MEDIUM confidence)
- [Authorization | Laravel 11.x docs](https://laravel.com/docs/11.x/authorization) — policy auto-discovery, `authorize()`/`AuthorizationException` → 403 [CITED]
- [Laravel 11 Adds Policy Auto-Discovery to Models — Shawn Hooper](https://shawnhooper.ca/2024/05/02/laravel-11-adds-policy-auto-discovery-to-models/) — corroborates the official docs claim [CITED]
- [Preventing Insecure Direct Object References (IDOR) in Laravel — Pentest Testing Corp](https://pentest-testing-corp.medium.com/preventing-insecure-direct-object-references-idor-in-laravel-9b8ef97866cb) — same-predicate / policy-based prevention pattern [CITED]
- [`Exists` Rule to Avoid `N+1` Query Issue in Array Validation — laravel/framework Discussion #53420](https://github.com/laravel/framework/discussions/53420) — confirms `exists:table,column` on `.*` array items issues one query per item [CITED]
- `.planning/research/PITFALLS.md` Pitfall 2 (IDOR), `.planning/research/ARCHITECTURE.md` Pattern 1 / Anti-Pattern 3 — prior-phase project research, directly applicable to this phase's scope [CITED: project research]

### Tertiary (LOW confidence)
- None used as load-bearing claims this session — see Assumptions Log (A1) for the one training-knowledge detail not independently re-verified.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages, versions read directly from `composer.json`
- Architecture: HIGH — existing models/relationships/routes read directly from the repo; the shared-scope pattern is a direct, testable consequence of D-02/D-03's own wording
- Pitfalls: HIGH — five of five pitfalls are either drawn from this project's own prior PITFALLS.md research (already MEDIUM-HIGH confidence) or reasoned directly from this phase's specific CONTEXT.md decisions (D-01 draft-assignment interaction, D-05 dead-route seam)

**Research date:** 2026-07-15
**Valid until:** 2026-08-14 (30 days — stable Laravel 11.x conventions, no fast-moving dependency in scope)

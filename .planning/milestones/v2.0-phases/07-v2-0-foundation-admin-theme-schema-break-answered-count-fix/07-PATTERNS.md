# Phase 7: v2.0 Foundation — Admin Theme, Schema Break & Answered-Count Fix - Pattern Map

**Mapped:** 2026-07-16
**Files analyzed:** 30 (26-file rename sweep + 4 net-new UI/schema files)
**Analogs found:** 27 / 30

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `app/Models/Section.php` (renamed from `Classroom.php`) | model | CRUD | `app/Models/Classroom.php` | exact (rename+extend) |
| `app/Models/Enrollment.php` (new) | model (custom Pivot) | CRUD | `app/Enums/Role.php` (enum cast pattern) + `Illuminate\Database\Eloquent\Relations\Pivot` | role-match (new shape, cast-pattern precedent exists) |
| `app/Enums/EnrollmentStatus.php` (new) | enum | — | `app/Enums/QuestionType.php` / `app/Enums/Role.php` | exact |
| `app/Models/Subject.php` (modified) | model | CRUD | itself (existing file, edited in place) | exact |
| `app/Models/User.php` (modified) | model | CRUD | itself (existing file, edited in place) | exact |
| `app/Models/Exam.php` (`scopeVisibleTo` rewrite) | model | request-response (query scope) | itself (existing file, edited in place) | exact |
| `app/Http/Controllers/Lecturer/SectionController.php` (new, renamed from `ClassroomController.php`) | controller | CRUD | `app/Http/Controllers/Lecturer/ClassroomController.php` | exact |
| `app/Http/Controllers/Lecturer/SubjectLecturerController.php` (new) | controller | CRUD | `app/Http/Controllers/Lecturer/ClassroomRosterController.php` (attach/detach shape) + `app/Http/Controllers/Lecturer/ClassroomController.php` (sync shape) | role-match |
| `app/Http/Requests/Lecturer/StoreSectionRequest.php` / `UpdateSectionRequest.php` (new) | middleware (Form Request) | request-response | `app/Http/Requests/Lecturer/StoreClassroomRequest.php` / `UpdateClassroomRequest.php` | exact (structure) / **divergent** (`authorize()` semantics — see Shared Patterns) |
| `app/Http/Requests/Lecturer/AssignLecturerRequest.php` (new) | middleware (Form Request) | request-response | `app/Http/Requests/Lecturer/AssignStudentRequest.php` (shape only — not found/read in repo but referenced by `ClassroomRosterController`; use `StoreClassroomRequest` as the concrete analog instead) | partial |
| `app/Http/Requests/Lecturer/AssignExamRequest.php` (modified: `classroom_ids`→`section_ids`) | middleware (Form Request) | request-response | itself (existing file, edited in place) | exact |
| `app/Policies/ExamPolicy.php` (no logic change, delegates to `Exam::visibleTo()`) | middleware (policy) | request-response | itself (existing file, edited in place — comment/reference sweep only) | exact |
| `app/Policies/AttemptPolicy.php` (no logic change) | middleware (policy) | request-response | itself (existing file, edited in place) | exact |
| `database/migrations/2026_07_15_100001_create_subjects_table.php` (renamed from `100002`) | migration | batch | itself, renamed | exact |
| `database/migrations/2026_07_15_100002_create_sections_table.php` (rewritten from `create_classrooms_table.php`) | migration | batch | `database/migrations/2026_07_15_100001_create_classrooms_table.php` | exact (structure) + new `subject_id` FK |
| `database/migrations/2026_07_15_100003_add_role_to_users_table.php` (rewritten, drops `classroom_id` half) | migration | batch | `database/migrations/2026_07_15_100003_add_role_and_classroom_id_to_users_table.php` | exact |
| `database/migrations/2026_07_15_100004_create_subject_user_table.php` (repurposed from `create_classroom_subject_table.php`) | migration | batch | `database/migrations/2026_07_15_100004_create_classroom_subject_table.php` | exact |
| `database/migrations/2026_07_15_100008_create_exam_section_table.php` (rewritten from `create_exam_classroom_table.php`) | migration | batch | `database/migrations/2026_07_15_100008_create_exam_classroom_table.php` | exact |
| `database/migrations/2026_07_15_100011_create_enrollments_table.php` (new) | migration | batch | `database/migrations/2026_07_15_100004_create_classroom_subject_table.php` (pivot-with-extra-columns shape) | role-match |
| `database/factories/SectionFactory.php` (renamed from `ClassroomFactory.php`) | test (factory) | batch | `database/factories/ClassroomFactory.php` (not yet read — locate before use; `SubjectFactory.php`/`ExamFactory.php` are close siblings) | role-match |
| `database/seeders/DatabaseSeeder.php` (full rewrite) | test (seeder) | batch | itself (existing file, edited in place — see excerpt below) | exact |
| `resources/views/layouts/app.blade.php` (rebuilt Flowbite shell + pre-paint script) | component (Blade layout) | request-response | itself (existing file, edited in place) | exact |
| `resources/views/layouts/navigation.blade.php` (rebuilt Flowbite navbar) | component (Blade partial) | request-response | itself (existing file, edited in place) | exact |
| `resources/views/components/status-pill.blade.php` (new) | component | request-response | no direct analog — Breeze ships no status-mapping component; use Breeze's `x-input-error`/`x-primary-button` as the "single-purpose Blade component" structural precedent | no analog (see below) |
| `resources/views/lecturer/sections/{index,create,edit}.blade.php` (new, from `lecturer/classrooms/*`) | component (Blade view) | CRUD | `resources/views/lecturer/classrooms/{index,create,edit}.blade.php` | exact |
| `resources/views/student/attempts/show.blade.php` (FIX-01: `answeredCount` lift into Alpine) | component (Blade view) | event-driven (Alpine reactive state) | itself (existing file, edited in place — see Pattern excerpt below) | exact |
| `tailwind.config.js` (Flowbite plugin + darkMode) | config | — | itself, edited in place | exact |
| `resources/js/app.js` (`import 'flowbite'`) | config | — | itself, edited in place | exact |
| `tests/Feature/DomainSchemaTest.php` (rewrite) | test | batch | itself, edited in place (see excerpt below) | exact |
| `tests/Feature/DatabaseSeederTest.php` (rewrite) | test | batch | itself (not read this pass — same file, edited in place; mirror `DomainSchemaTest`'s RefreshDatabase/assert-table style) | exact |
| `tests/Feature/Student/ExamVisibilityRegressionTest.php` (new, hard gate) | test | request-response | RESEARCH.md Pattern 1's inline example (`DataProvider`-based); structurally mirrors `tests/Feature/Student/ExamAccessTest.php` (not read this pass — locate for exact `RefreshDatabase`/route conventions) | role-match |
| `tests/Feature/Lecturer/SectionControllerTest.php` (new) | test | CRUD | `tests/Feature/Lecturer/ClassroomControllerTest.php` (rename target; not read this pass — copy structure 1:1 renaming Classroom→Section) | exact |
| `tests/Feature/Lecturer/SubjectLecturerTest.php` (new) | test | request-response | `tests/Feature/Lecturer/ClassroomSubjectLinkageTest.php` (closest existing pivot-authorization test; not read this pass) | role-match |

## Pattern Assignments

### `app/Models/Section.php` (model, CRUD) — renamed from `app/Models/Classroom.php`

**Analog:** `app/Models/Classroom.php` (full file read, 39 lines)

**Current structure to copy wholesale, then extend:**
```php
// app/Models/Classroom.php (current, verbatim)
class Classroom extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    public function users(): HasMany { return $this->hasMany(User::class); }

    public function subjects(): BelongsToMany { return $this->belongsToMany(Subject::class); }

    public function exams(): BelongsToMany { return $this->belongsToMany(Exam::class, 'exam_classroom'); }
}
```

**Target shape for `Section`** (per RESEARCH.md Code Examples, cross-checked against this file's conventions — explicit pivot-name-override comment style must be preserved):
```php
class Section extends Model
{
    use HasFactory;
    protected $fillable = ['subject_id', 'year', 'semester', 'sequence', 'capacity', 'opens_at', 'closes_at'];

    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }

    public function enrollments(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->using(Enrollment::class)
            ->withPivot(['status', 'rejection_reason'])
            ->withTimestamps();
    }

    // exam_section keeps its own explicit name, same convention as
    // exam_classroom's existing comment — Eloquent's alphabetical
    // default (exam_section) happens to match here, but keep the
    // explicit override + comment for consistency with the sibling model.
    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_section');
    }

    protected function name(): Attribute
    {
        return Attribute::make(get: fn () => "{$this->year}-{$this->semester}-{$this->sequence}");
    }
}
```

---

### `app/Http/Controllers/Lecturer/SectionController.php` (controller, CRUD)

**Analog:** `app/Http/Controllers/Lecturer/ClassroomController.php` (full file read, 73 lines)

**Core CRUD pattern to copy verbatim, substituting `Classroom`→`Section`, and nesting under `subject`:**
```php
// Current (Classroom, un-nested)
public function store(StoreClassroomRequest $request): RedirectResponse
{
    // Pitfall 4 (02-RESEARCH.md): the classroom must exist before its
    // pivot relation can be synced — create first, then sync.
    $classroom = Classroom::create($request->safe()->only('name'));
    $classroom->subjects()->sync($request->validated('subject_ids', []));

    return redirect()->route('lecturer.classrooms.index')->with('status', 'Classroom created.');
}
```
Copy this "create first, then sync pivot" ordering discipline for `SectionController@store` if enrollments/exams need syncing at creation (they don't in Phase 7 — sections start with zero enrollments). The `index`/`create`/`edit`/`update`/`destroy` methods map 1:1 in shape; only the model name, request class names, view paths (`lecturer.sections.*`), and route names (`lecturer.sections.*`) change. If nesting sections under a subject (Claude's discretion per CONTEXT.md), add a `Subject $subject` route-model-bound parameter to every method and scope `Section::where('subject_id', $subject->id)`.

**Redirect/flash-message convention** (copy verbatim): `redirect()->route(...)->with('status', '<Entity> <verb>.')`.

---

### `app/Http/Controllers/Lecturer/SubjectLecturerController.php` (controller, CRUD)

**Analog:** `app/Http/Controllers/Lecturer/ClassroomRosterController.php` (full file read, 44 lines) — attach/detach shape; **do not** copy its `classroom_id`-writing mechanics (that column is dropped, Pitfall 4).

```php
// Current ClassroomRosterController@store (mechanics to structurally mirror,
// NOT to reuse — this writes a flat FK; SubjectLecturerController must
// instead sync/attach the subject_user pivot):
public function store(AssignStudentRequest $request, Classroom $classroom): RedirectResponse
{
    $student = User::findOrFail($request->validated('student_id'));
    $student->update(['classroom_id' => $classroom->id]);

    return back()->with('status', 'Student assigned.');
}

// Current ClassroomRosterController@destroy — IDOR guard shape to keep:
public function destroy(Classroom $classroom, User $student): RedirectResponse
{
    abort_unless($student->classroom_id === $classroom->id, 404);
    $student->update(['classroom_id' => null]);

    return back()->with('status', 'Student unassigned.');
}
```

**Target shape for `SubjectLecturerController`** — pivot attach/detach instead of FK write, and IDOR-equivalent existence check via the pivot table itself:
```php
public function store(AssignLecturerRequest $request, Subject $subject): RedirectResponse
{
    $subject->lecturers()->syncWithoutDetaching([$request->validated('user_id')]);

    return back()->with('status', 'Lecturer assigned.');
}

public function destroy(Subject $subject, User $lecturer): RedirectResponse
{
    $subject->lecturers()->detach($lecturer->id);

    return back()->with('status', 'Lecturer unassigned.');
}
```

---

### `app/Http/Requests/Lecturer/StoreSectionRequest.php` / `UpdateSectionRequest.php` (Form Request)

**Analog:** `app/Http/Requests/Lecturer/StoreClassroomRequest.php` (full file, 35 lines) and `UpdateClassroomRequest.php` (full file, 37 lines)

**Structure to copy (rules-array shape, `Rule::unique(...)->ignore()` idiom):**
```php
// StoreClassroomRequest (current, verbatim)
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255', Rule::unique('classrooms', 'name')],
        'subject_ids' => ['nullable', 'array'],
        'subject_ids.*' => ['integer', Rule::exists('subjects', 'id')],
    ];
}

// UpdateClassroomRequest (current, verbatim) — ->ignore($classroom) idiom
public function rules(): array
{
    $classroom = $this->route('classroom');
    return [
        'name' => ['required', 'string', 'max:255', Rule::unique('classrooms', 'name')->ignore($classroom)],
        ...
    ];
}
```

**CRITICAL DIVERGENCE — do NOT copy `authorize(): bool { return true; }`.** Both current files carry the D-09 comment "no per-record ownership applies to classrooms." RESEARCH.md Pattern 2 / Assumption A4 explicitly flags that `StoreSectionRequest`/`UpdateSectionRequest` (and `AssignLecturerRequest`) must implement genuine per-subject ownership:
```php
public function authorize(): bool
{
    $subject = $this->route('subject');

    return $subject->lecturers()->whereKey($this->user()->id)->exists();
}
```
This is a new authorization shape for this codebase — flag it explicitly in the plan rather than silently copying the `return true;` pattern (RESEARCH.md Anti-Patterns / Pitfall list).

**Suggested `rules()` additions per RESEARCH.md Open Questions**: `year`, `semester`, `sequence` integer bounds; `capacity` required integer `min:1`; `opens_at`/`closes_at` both `required|date`, with `closes_at` using `Rule`/`after:opens_at`.

---

### `app/Models/Exam.php` — `scopeVisibleTo()` rewrite (model, request-response query scope)

**Analog:** itself, `app/Models/Exam.php` lines 76-88 (current, verbatim, full file read)

```php
// BEFORE (current code, verified this session)
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
            fn (Builder $q) => $q->whereRaw('0 = 1'),
        );
}
```
Rewrite target (per RESEARCH.md Pattern 1 — canonical, already codebase-verified):
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
Also rename `classrooms(): BelongsToMany` (lines 49-56, explicit `'exam_classroom'` pivot override) to `sections(): BelongsToMany` targeting `'exam_section'`. Keep the doc comment above the method — it documents the "one predicate for two consumers" invariant that `ExamPolicy::takeable()` (`app/Policies/ExamPolicy.php`, full file read, 25 lines) depends on unconditionally; that policy file needs **zero logic changes**, only its doc-comment's mention of `classroom_id` swept to `enrollments`.

---

### `resources/views/layouts/app.blade.php` + `navigation.blade.php` (Blade layout, request-response) — Flowbite rebuild

**Analog:** itself, both files read in full (37 and 100 lines respectively — current Breeze scaffold, pre-Flowbite).

**Pre-paint dark-mode script insertion point** — `app.blade.php` `<head>`, before `@vite(...)` (line 15):
```html
<script>
    if (localStorage.getItem('theme') === 'dark' ||
        (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
</script>
```
`app.blade.php`'s existing `<div class="min-h-screen bg-gray-100">` wrapper needs a `dark:bg-gray-900` sibling class; `@include('layouts.navigation')` call site stays unchanged.

**`navigation.blade.php` rebuild** — current file's Alpine `x-data="{ open: false }"` root + `x-dropdown`/`x-nav-link`/`x-responsive-nav-link` component usage (lines 1, 15-17, 23-52, 68-98) is the direct precedent for how this codebase already wires Alpine-driven nav interactivity; Flowbite's navbar/dropdown markup replaces the Tailwind-only classes but keeps the same `x-data`/`@click`/`:class` idiom already proven in this file. Brand line 6-11 (`x-application-logo` inside an `<a href="{{ route('dashboard') }}">`) is replaced with the "Exam Portal" text wordmark per CONTEXT.md. Role-scoped dropdowns (Lecturer→Subjects/Sections/Exams/Results; Student→Enroll/My Exams/Results) should follow the existing `x-dropdown` slot pattern (lines 23-52) — one dropdown per role-group, gated by `@if(auth()->user()->isLecturer())`/`@else`.

---

### `resources/views/student/attempts/show.blade.php` — FIX-01 answered-count fix

**Analog:** itself, full file read (304 lines) — root cause and fix site both directly located.

**Root cause (verified, lines 174-179 and 198):**
```php
@php
    $answeredCount = $savedAnswers->count();
@endphp
...
{{ __("...:answered of :total questions answered.", ['answered' => $answeredCount, 'total' => count($questions)]) }}
```
Static, computed once server-side at page load — never updates as `x-data="{ status: 'idle', ... }"` per-card scopes (line 91-118) autosave.

**Existing outer-scope pattern to extend (lines 20-28):**
```php
<div
    x-data="attemptTimer(
        {{ $remainingSeconds }},
        '{{ route('student.attempts.submit', $attempt) }}',
        '{{ route('student.attempts.submitted', $attempt) }}'
    )"
    x-init="init(); start()"
    x-on:deadline-expired.window="autoSubmit()"
>
```
Fix per RESEARCH.md Pitfall 2: seed `answeredCount` into the `attemptTimer()` Alpine factory (same JS block at line 227), incremented by each card's `save()` promise resolution. Copy the existing bubbled-window-event idiom already used for `deadline-expired` (line 27, dispatched at line 107) as the cross-scope communication mechanism, OR increment via a shared Alpine store/`$refs` — either way, bind the modal text with `x-text`, not the current static Blade interpolation at line 198. Do not regress the Phase-4 "no whole-page x-data blob" invariant documented in the file's own header comment (lines 76-88).

---

### `database/seeders/DatabaseSeeder.php` (full rewrite)

**Analog:** itself, full file read (239 lines) — current `Classroom`/`classroom_id`/`classroom_subject`/`exam_classroom` usages are the literal 1:1 rename map.

**Idempotency idiom to preserve verbatim (`firstOrCreate` on natural keys, never `updateOrCreate`):**
```php
$demoClassroom = Classroom::firstOrCreate(['name' => 'Demo Classroom']);
$students['student']->update(['classroom_id' => $demoClassroom->id]);
$demoClassroom->subjects()->sync([$mathematics->id, $science->id]);
...
$exam->classrooms()->sync([$demoClassroom->id]);
```
Target rewrite: `Section::firstOrCreate([...'subject_id' => $mathematics->id, 'year' => ..., 'semester' => ..., 'sequence' => ...])`, then `$section->enrollments()->syncWithoutDetaching([$student->id => ['status' => EnrollmentStatus::Enrolled]])` in place of the `classroom_id` direct-assignment update, then `$exam->sections()->sync([$section->id])` in place of `$exam->classrooms()->sync(...)`. Keep the `wasRecentlyCreated` guard pattern (lines 156-179) for question/option seeding — unrelated to the rename, do not touch. Also seed at least one `subject_user` row (`$mathematics->lecturers()->syncWithoutDetaching([$lecturer->id])`) so `SubjectLecturerTest`/SEC-03 has a non-empty demo row.

---

### `tests/Feature/DomainSchemaTest.php` (rewrite)

**Analog:** itself, lines 1-60 read — direct edit-in-place target.

```php
$tables = [
    'classrooms', 'subjects', 'classroom_subject', 'exams', 'questions',
    'options', 'exam_classroom', 'attempts', 'answers',
];
```
Rewrite to: `['sections', 'subjects', 'subject_user', 'enrollments', 'exams', 'questions', 'options', 'exam_section', 'attempts', 'answers']`, plus (per RESEARCH.md Wave-0 gap list) a new assertion for the `enrollments` table's `unique(section_id, user_id)` index, mirroring the existing composite-unique-index assertion pattern already in this file (lines 47-57, `Schema::getIndexes('attempts')` + `collect(...)->contains(...)`).

---

### `tests/Feature/Student/ExamVisibilityRegressionTest.php` (new, hard acceptance gate)

**Analog:** RESEARCH.md Pattern 1's inline example (already codebase-aware, copy near-verbatim):
```php
#[DataProvider('enrollmentStates')]
public function test_exam_index_and_direct_access_gate_agree(string $status, bool $expectedVisible): void
{
    $section = Section::factory()->create();
    $exam = Exam::factory()->published()->create();
    $exam->sections()->sync([$section->id]);
    $student = User::factory()->student()->create();
    if ($status !== 'never_applied') {
        $section->enrollments()->attach($student->id, ['status' => $status]);
    }

    $listVisible = Exam::visibleTo($student)->whereKey($exam->id)->exists();
    $gateVisible = app(ExamPolicy::class)->takeable($student, $exam);

    $this->assertSame($expectedVisible, $listVisible);
    $this->assertSame($listVisible, $gateVisible, 'List and gate must always agree.');
}
```
Use `Tests\TestCase` + `RefreshDatabase` per `DomainSchemaTest`'s existing convention (line 5, 11). This must run all four states from `enrollmentStates()` (enrolled/withdrawn/rejected/never_applied) as a hard gate before phase sign-off, per CONTEXT.md's "Specific Ideas" note.

## Shared Patterns

### Ownership-in-`authorize()` divergence (NEW pattern this phase introduces)
**Source:** RESEARCH.md Pattern 2 / Assumption A4, contrasted against `app/Http/Requests/Lecturer/StoreClassroomRequest.php` / `UpdateClassroomRequest.php` (both `authorize(): bool { return true; }`, D-09 convention)
**Apply to:** `StoreSectionRequest`, `UpdateSectionRequest`, `AssignLecturerRequest` (any Form Request scoped to a specific `Subject`)
```php
public function authorize(): bool
{
    $subject = $this->route('subject');

    return $subject->lecturers()->whereKey($this->user()->id)->exists();
}
```
This is a genuine behavior change from the existing convention — do not copy `return true;` from `StoreClassroomRequest`/`StoreSubjectRequest` for these three new Form Requests.

### Idempotent pivot writes (`sync()`/`syncWithoutDetaching()`)
**Source:** `app/Http/Controllers/Lecturer/ClassroomController.php` lines 39-40, 58-59; `database/seeders/DatabaseSeeder.php` lines 47-49, 183
**Apply to:** `SectionController@store/update` (no pivot to sync in Phase 7 — sections have no direct pivot at creation, but subject_id FK is set directly), `SubjectLecturerController@store` (subject_user), `DatabaseSeeder`'s exam↔section and section↔enrollment writes
```php
$classroom->subjects()->sync($request->validated('subject_ids', []));
```

### Enum-cast Pivot model (custom `->using()` class)
**Source:** `app/Enums/Role.php`/`app/Enums/QuestionType.php` casting convention on plain models (`protected function casts(): array { return ['role' => Role::class]; }`), extended per RESEARCH.md Code Examples to a `Pivot` subclass — no existing Pivot-subclass precedent in this codebase, so `Enrollment.php` is the first of its kind; keep the same `casts()` method-style (Laravel 11 convention, not the `$casts` property) for consistency with `User.php`/`Exam.php`/`Question.php`.

### Migration structure (plain `Schema::create` + FK + composite unique)
**Source:** `database/migrations/2026_07_15_100001_create_classrooms_table.php`, `100004_create_classroom_subject_table.php`, `100008_create_exam_classroom_table.php` (all read in full)
**Apply to:** every rewritten/new migration in this phase's sweep
```php
Schema::create('classroom_subject', function (Blueprint $table) {
    $table->id();
    $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
    $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['classroom_id', 'subject_id']);
});
```
`enrollments` (new table) additionally needs `status` (string, enum-cast in the model) and nullable `rejection_reason` columns beyond this base shape.

### Redirect + flash-message convention
**Source:** `ClassroomController` (all 4 write actions), `ClassroomRosterController` (both actions)
**Apply to:** `SectionController`, `SubjectLecturerController`
```php
return redirect()->route('lecturer.classrooms.index')->with('status', 'Classroom created.');
// or, for non-index-returning actions:
return back()->with('status', 'Student assigned.');
```

## No Analog Found

| File | Role | Data Flow | Reason |
|------|------|-----------|--------|
| `resources/views/components/status-pill.blade.php` | component | request-response | No existing status→color mapping component in this codebase (Breeze ships only generic input/button components: `x-primary-button`, `x-secondary-button`, `x-input-error`, `x-modal`, `x-dropdown`). Build as a small props-driven Blade component (`@props(['status'])` + a `match()` expression mapping to the 4-color palette locked in CONTEXT.md); structurally mirror `x-primary-button.blade.php`'s single-purpose-component shape (not read this session, but standard Breeze scaffold — trivially locatable via `resources/views/components/`). |
| `database/factories/SectionFactory.php` | test (factory) | batch | `ClassroomFactory.php` was not directly read this session (file exists per the rename-sweep inventory but wasn't opened) — before writing, read it and `SubjectFactory.php`/`ExamFactory.php` as the concrete state-method (`->mcq()`/`->essay()`-equivalent) precedent for the new `->enrolled()`/`->withStatus()` states this factory will need. |
| `tests/Feature/Lecturer/ClassroomControllerTest.php` (rename source) | test | CRUD | Not read this session — locate and read before writing `SectionControllerTest.php`; RESEARCH.md confirms it exists in the 26-file sweep list but its exact assertions weren't verified in this pass. |

## Metadata

**Analog search scope:** `app/Models`, `app/Http/Controllers/Lecturer`, `app/Http/Requests/Lecturer`, `app/Policies`, `database/migrations`, `database/seeders`, `resources/views/layouts`, `resources/views/student/attempts`, `tests/Feature`
**Files scanned/read directly this session:** `Classroom.php`, `Exam.php`, `User.php`, `Subject.php`, `ClassroomController.php`, `ClassroomRosterController.php`, `StoreClassroomRequest.php`, `UpdateClassroomRequest.php`, `ExamPolicy.php`, 4 migrations (`create_classrooms_table`, `create_classroom_subject_table`, `add_role_and_classroom_id_to_users_table`, `create_exam_classroom_table`), `navigation.blade.php`, `app.blade.php`, `student/attempts/show.blade.php`, `tailwind.config.js`, `resources/js/app.js`, `DomainSchemaTest.php` (partial), `DatabaseSeeder.php`
**Pattern extraction date:** 2026-07-16

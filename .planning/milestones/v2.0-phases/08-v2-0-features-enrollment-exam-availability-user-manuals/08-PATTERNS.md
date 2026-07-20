# Phase 8: v2.0 Features — Enrollment, Exam Availability & User Manuals - Pattern Map

> **⚠ RESOLVED (2026-07-16, user decision) — the rejection-reason discrepancy flagged in this
> document is settled.** Authoritative set (REQUIREMENTS.md "Resolved Design Decisions (v2.0)" #1):
> `Not eligible for subject` · `Prerequisite not met` · `Duplicate enrollment` · `Section changed` · `Other`
> (fixed enum, server-validated). 08-CONTEXT.md and 08-UI-SPEC.md now match this. Any other wording
> below is superseded — do not guess, use this set.

**Mapped:** 2026-07-16
**Files analyzed:** 28
**Analogs found:** 24 / 28

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|--------------------|------|-----------|-----------------|----------------|
| `app/Policies/AttemptPolicy.php` (MODIFY) | model-authorization | request-response | same file, `viewResult()` method | exact (in-file precedent) |
| `app/Enums/RejectionReason.php` (NEW) | model/enum | transform | `app/Enums/EnrollmentStatus.php` (Phase 7) / `app/Enums/QuestionType.php` | exact |
| `app/Models/Enrollment.php` (MODIFY — add `section()`, `user()` relations, cast `rejection_reason`) | model | CRUD | same file (extend) | exact |
| `app/Models/Exam.php` (MODIFY — add `isAvailableNow()`, `availabilityState()`) | model | transform | `app/Models/Section.php` `name()` accessor idiom + inline window-status `@php` block in `lecturer/sections/index.blade.php` | role-match |
| `app/Models/Section.php` (MODIFY — extract `windowStatus()` accessor) | model | transform | own `name()` Attribute accessor (same file) | exact |
| `app/Http/Controllers/Student/SubjectBrowseController.php` (NEW) | controller | request-response (read) | `app/Http/Controllers/Lecturer/SectionController.php` (`index`) | role-match |
| `app/Http/Controllers/Student/EnrollmentController.php` (NEW, `store`/`destroy`) | controller | CRUD + concurrency | `app/Http/Controllers/Lecturer/SectionController.php` (`store` — transaction+lockForUpdate) | exact (concurrency idiom) |
| `app/Http/Controllers/Lecturer/SectionController.php` (MODIFY — add `show()` roster) | controller | request-response (read) | same file (`index`/`edit`) | exact |
| `app/Http/Controllers/Lecturer/RejectEnrollmentController.php` (NEW) | controller | CRUD (single field update) | `app/Http/Controllers/Lecturer/SectionController.php` (`destroy` — ownership abort_unless pattern) | role-match |
| `app/Http/Controllers/Student/AttemptController.php` (MODIFY — `store()` gains `isAvailableNow()` branch) | controller | request-response | same file, `store()` method | exact |
| `app/Http/Requests/Student/EnrollRequest.php` (NEW) | validation | request-response | `app/Http/Requests/Lecturer/StoreSectionRequest.php` | role-match |
| `app/Http/Requests/Lecturer/RejectEnrollmentRequest.php` (NEW) | validation | request-response | `app/Http/Requests/Lecturer/StoreSectionRequest.php` (SEC-03 ownership `authorize()`) | exact |
| `app/Http/Requests/Lecturer/StoreExamRequest.php` (MODIFY — add `available_from`/`available_until` rules) | validation | request-response | same file | exact |
| `app/Http/Requests/Lecturer/UpdateExamRequest.php` (MODIFY — same rules; `authorize()` untouched) | validation | request-response | same file | exact |
| `database/migrations/2026_07_15_100005_create_exams_table.php` (MODIFY IN PLACE) | migration | schema | same file | exact |
| `database/factories/ExamFactory.php` (MODIFY — add availability states) | factory | transform | `database/factories/SectionFactory.php` (has `opens_at`/`closes_at` precedent — not read this session but same shape as `SectionController@store`'s date fields) | role-match |
| `database/seeders/DatabaseSeeder.php` (MODIFY) | config/seed | batch | same file (not read this session; existing precedent) | role-match |
| `resources/views/student/subjects/index.blade.php` (NEW) | view | request-response (read) | `resources/views/lecturer/sections/index.blade.php` (card+table, `@forelse`) | exact |
| `resources/views/student/subjects/show.blade.php` (NEW) | view | request-response (read+write triggers) | `resources/views/lecturer/sections/index.blade.php` (table + per-row modal pattern) | exact |
| `resources/views/lecturer/sections/show.blade.php` (NEW — roster) | view | request-response (read+write triggers) | `resources/views/lecturer/sections/index.blade.php` (table + per-row `x-modal` delete pattern) | exact |
| `resources/views/student/exams/show.blade.php` (MODIFY — pre-start enhancements) | view | request-response | same file (extend in place) | exact |
| `resources/views/student/exams/index.blade.php` (MODIFY — availability pill) | view | request-response (read) | not read this session; same directory sibling of `show.blade.php` | role-match |
| `resources/views/student/attempts/show.blade.php` (MODIFY — `beforeunload` attach/detach) | view + Alpine component | event-driven (client) | same file, `attemptTimer()` factory | exact |
| `resources/views/lecturer/exams/create.blade.php` / `edit.blade.php` (MODIFY — availability fields + full dark-mode pass) | view | request-response (write form) | `resources/views/lecturer/sections/create.blade.php` (datetime-local field pattern) | exact |
| `resources/views/components/status-pill.blade.php` (MODIFY — extend `match()`) | component | transform | same file | exact |
| `resources/views/student/help.blade.php` (NEW) | view (static content) | request-response (read) | none (see No Analog Found) | none |
| `resources/views/lecturer/help.blade.php` (NEW) | view (static content) | request-response (read) | none (see No Analog Found) | none |
| `resources/views/layouts/navigation.blade.php` (MODIFY — Enroll + Help links) | view (nav partial) | request-response | same file (extend existing `@if (auth()->user()->isLecturer())` blocks) | exact |
| `routes/student.php` / `routes/lecturer.php` (MODIFY) | route | request-response | same files (extend existing `Route::middleware(...)->group()` blocks) | exact |

## Pattern Assignments

### `app/Policies/AttemptPolicy.php` (MODIFY — the critical AVL-04 fix)

**Analog:** same file, `viewResult()` (already ownership-only)

**Current (broken once enrollment becomes mutable post-start):**
```php
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
```

**Required change — copy `viewResult()`'s exact shape:**
```php
public function viewResult(User $user, Attempt $attempt): bool
{
    return $attempt->user_id === $user->id;
}
```
→ `view()` and `update()` become `return $attempt->user_id === $user->id;` (identical body to `viewResult()`). Remove `ownAndTakeable()` and the now-unused `Exam` import only after grepping the whole repo for other callers (there should be none — `Exam::visibleTo()` stays consumed only by `Exam::scopeVisibleTo()`'s own callers: `Student\ExamController@index` and `ExamPolicy::takeable()`).

**Do NOT touch:** `ExamPolicy::takeable()` — stays enrollment-gated, unrelated to this fix.

---

### `app/Enums/RejectionReason.php` (NEW)

**Analog:** `app/Enums/EnrollmentStatus.php` / `app/Enums/QuestionType.php` (not re-read this session, but `Enrollment::casts()` shows the exact consumption idiom)

**Pattern to copy** (backed string enum + `label()` match, per RESEARCH.md Pattern 8):
```php
namespace App\Enums;

enum RejectionReason: string
{
    case PrerequisiteNotMet = 'prerequisite_not_met';
    case IneligibleForSection = 'ineligible_for_section'; // wording per 08-CONTEXT/UI-SPEC, NOT REQUIREMENTS.md — see Pitfall 2
    case DuplicateEnrollment = 'duplicate_enrollment';
    case SectionChanged = 'section_changed'; // ⚠ verify final 5 values against 08-UI-SPEC's locked Copywriting Contract at plan time — UI-SPEC's Copywriting table and its own Phase Notes section list two slightly different sets (see PATTERNS "No Analog" note below)
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PrerequisiteNotMet => 'Prerequisite not met',
            // ...
        };
    }
}
```
**IMPORTANT — flagged discrepancy (do not silently pick one):** 08-UI-SPEC.md's own "Destructive confirmations" table (line ~153) lists the REQUIREMENTS.md-style 5 values (`Not eligible for subject / Prerequisite not met / Duplicate enrollment / Section changed / Other`), while 08-CONTEXT.md's Decisions section and 08-RESEARCH.md Pattern 8 both state a *different* 5-value set (`Prerequisite not met / Ineligible for subject / Administrative reallocation / Duplicate enrollment / Other (contact lecturer)`). This is RESEARCH.md's own logged Pitfall 2. The planner must resolve this explicitly against whichever document is authoritative before writing the enum — do not guess.

**Casting on `Enrollment`** — add to `casts()`:
```php
protected function casts(): array
{
    return [
        'status' => EnrollmentStatus::class,
        'rejection_reason' => RejectionReason::class,
    ];
}
```

---

### `app/Models/Enrollment.php` (MODIFY)

**Analog:** `app/Models/Section.php` (`BelongsTo`/`BelongsToMany` relation style)

**Current file (full, 22 lines) — has NO relations at all**, only `$table` and `casts()`. Add:
```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

public function section(): BelongsTo
{
    return $this->belongsTo(Section::class);
}

public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```
Required by RESEARCH.md Pitfall 4 — Pattern 3's ENR-04 `whereHas('section', ...)` throws `BadMethodCallException` without it.

---

### `app/Models/Exam.php` (MODIFY — add availability methods)

**Analog:** own `scopeVisibleTo()` (same file) for style/doc-comment convention; `Section::name()` `Attribute` accessor for the "computed, not stored" idiom.

**Current file structure (91 lines):** `$fillable`, `casts()`, `subject()`, `creator()`, `questions()`, `sections()`, `attempts()`, `scopeVisibleTo()`.

**Add** (per RESEARCH.md Pattern 4, verbatim):
```php
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
Add `'available_from', 'available_until'` to `$fillable`, and cast both `'datetime'` in `casts()` (matching `Section::casts()`'s `opens_at`/`closes_at` → `'datetime'` idiom exactly).

**Anti-pattern warning (from RESEARCH.md):** never add availability logic inside `scopeVisibleTo()` — leave a comment there warning against it, mirroring the existing doc comment style on that method.

---

### `app/Models/Section.php` (MODIFY — extract `windowStatus()`)

**Analog:** own `name()` `Attribute` accessor (same file, lines 68-73) — "computed, not stored" precedent.

**Current `name()` accessor:**
```php
protected function name(): Attribute
{
    return Attribute::make(
        get: fn () => "{$this->year}-{$this->semester}-{$this->sequence}",
    );
}
```

**New method, extracted from the inline `@php` block in `lecturer/sections/index.blade.php` (lines 46-56):**
```php
public function windowStatus(): string
{
    $now = now();

    if ($now->lt($this->opens_at)) {
        return 'opens';
    }
    if ($now->gte($this->closes_at)) {
        return 'closed';
    }

    return 'open';
}
```
Keep the exact `'opens'`/`'closed'`/`'open'` string values (RESEARCH.md warns: do NOT rename `'opens'` to `'opening'` — that keyword is reserved for exams only). Update `lecturer/sections/index.blade.php` to call `$section->windowStatus()` instead of the inline `@php` block, and reuse the same method in the new `student/subjects/show.blade.php`.

---

### `app/Http/Controllers/Student/SubjectBrowseController.php` (NEW — `index`, `show`)

**Analog:** `app/Http/Controllers/Lecturer/SectionController.php::index()`

**Imports pattern:**
```php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\View\View;
```

**Core read pattern (adapt `index()`):**
```php
public function index(): View
{
    // Subjects with at least one section reachable for enrollment —
    // exact predicate at Claude's discretion; mirror SectionController::index()'s
    // eager-load style ('subject') to avoid N+1 in the Blade table.
    $subjects = Subject::has('sections')->get();

    return view('student.subjects.index', compact('subjects'));
}

public function show(Subject $subject): View
{
    $sections = Section::where('subject_id', $subject->id)
        ->with(['enrollments' => fn ($q) => $q->wherePivot('user_id', auth()->id())])
        ->get();

    return view('student.subjects.show', compact('subject', 'sections'));
}
```
No ownership `authorize()` needed here — read-only, `role:student` middleware is the sole gate (same tier as `Student\ExamController@index`).

---

### `app/Http/Controllers/Student/EnrollmentController.php` (NEW — `store`, `destroy`)

**Analog:** `app/Http/Controllers/Lecturer/SectionController.php::store()` (transaction + `lockForUpdate()`)

**Imports pattern:**
```php
namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\Student\EnrollRequest;
use App\Models\Enrollment;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
```

**Core capacity-safe apply pattern (direct extension of `SectionController@store` lines 56-73):**
```php
public function store(EnrollRequest $request, Section $section): RedirectResponse
{
    $now = now();

    if ($now->lt($section->opens_at) || $now->gte($section->closes_at)) {
        return back()->with('error', __('Enrollment for this section hasn\'t opened yet.')); // or "is closed" — branch on which side
    }

    DB::transaction(function () use ($section, $request) {
        $locked = Section::whereKey($section->id)->lockForUpdate()->first();

        $enrolledCount = $locked->enrollments()
            ->wherePivot('status', EnrollmentStatus::Enrolled->value)
            ->count();

        if ($enrolledCount >= $locked->capacity) {
            abort(409, __('This section just reached capacity — choose another section.'));
            // OR throw a dedicated exception caught in the controller — planner's choice, but must
            // produce the exact flash copy in 08-UI-SPEC.md's Copywriting Contract table.
        }

        // ENR-04 check (Pattern 3, RESEARCH.md) — also inside this same locked transaction:
        $hasActiveElsewhere = Enrollment::query()
            ->where('user_id', $request->user()->id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->where('section_id', '!=', $locked->id)
            ->whereHas('section', fn ($q) => $q
                ->where('subject_id', $locked->subject_id)
                ->where('year', $locked->year)
                ->where('semester', $locked->semester))
            ->lockForUpdate()
            ->exists();

        if ($hasActiveElsewhere) {
            abort(409, __('You already have an active enrollment in this subject for this semester...'));
        }

        Enrollment::updateOrCreate(
            ['section_id' => $locked->id, 'user_id' => $request->user()->id],
            ['status' => EnrollmentStatus::Enrolled, 'rejection_reason' => null] // re-apply is UPDATE not INSERT
        );
    });

    return redirect()->back()->with('status', __("You're enrolled in :section.", ['section' => $section->name]));
}

public function destroy(Section $section): RedirectResponse
{
    $now = now();

    if ($now->gte($section->closes_at)) {
        return back()->with('error', __('You can no longer withdraw — this section\'s enrollment window has closed.'));
    }

    Enrollment::where('section_id', $section->id)
        ->where('user_id', auth()->id())
        ->update(['status' => EnrollmentStatus::Withdrawn]);

    return redirect()->back()->with('status', __('You\'ve withdrawn from :section.', ['section' => $section->name]));
}
```
Flash convention: this is the **first** controller in the app to need a red `session('error')` flash alongside the existing green `session('status')` — see the Shared Patterns section below.

---

### `app/Http/Controllers/Lecturer/SectionController.php` (MODIFY — add `show()`)

**Analog:** same file, `edit()` (ownership `abort_unless` pattern, lines 83-89)

```php
public function show(Subject $subject, Section $section): View
{
    abort_unless($section->subject_id === $subject->id, 404);
    abort_unless($subject->lecturers()->whereKey(auth()->id())->exists(), 403);

    $section->load(['enrollments' => fn ($q) => $q->wherePivot('status', EnrollmentStatus::Enrolled->value)]);

    return view('lecturer.sections.show', compact('subject', 'section'));
}
```

---

### `app/Http/Controllers/Lecturer/RejectEnrollmentController.php` (NEW)

**Analog:** `app/Http/Controllers/Lecturer/SectionController.php::destroy()` (ownership check pattern) — but ownership here belongs in the Form Request per Pattern 7, RESEARCH.md.

```php
namespace App\Http\Controllers\Lecturer;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\RejectEnrollmentRequest;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class RejectEnrollmentController extends Controller
{
    public function reject(RejectEnrollmentRequest $request, Section $section, User $student): RedirectResponse
    {
        Enrollment::where('section_id', $section->id)
            ->where('user_id', $student->id)
            ->update([
                'status' => EnrollmentStatus::Rejected,
                'rejection_reason' => $request->validated('reason'),
            ]);

        return redirect()->route('lecturer.sections.show', [$section->subject, $section])
            ->with('status', __(':student has been rejected from this section.', ['student' => $student->name]));
    }
}
```

---

### `app/Http/Controllers/Student/AttemptController.php` (MODIFY `store()`)

**Analog:** same file, current `store()` (lines 30-58) — extend in place, do not restructure.

**Insert point** (per RESEARCH.md Pattern 4, exact code):
```php
public function store(Request $request, Exam $exam): RedirectResponse
{
    $this->authorize('takeable', $exam); // UNCHANGED

    $alreadyStarted = Attempt::where('exam_id', $exam->id)
        ->where('user_id', $request->user()->id)
        ->exists();

    if (! $alreadyStarted && ! $exam->isAvailableNow()) {
        return redirect()->route('student.exams.show', $exam)
            ->with('error', $exam->availabilityState() === 'opening'
                ? __('This exam is not available yet. It opens :date.', ['date' => $exam->available_from->format('M j, Y g:ia')])
                : __('This exam is no longer available. It closed :date.', ['date' => $exam->available_until->format('M j, Y g:ia')]));
    }

    // ...existing firstOrCreate/1062-catch logic UNCHANGED below this point
}
```

---

### `app/Http/Requests/Student/EnrollRequest.php` (NEW)

**Analog:** `app/Http/Requests/Lecturer/StoreSectionRequest.php` (structure only — this one is thin, per RESEARCH.md: "most logic lives in the controller transaction")

```php
namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class EnrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:student middleware is the sole gate; window/capacity/ENR-04
                      // checks require a locked read and cannot be pre-validated here —
                      // they live in EnrollmentController@store's transaction.
    }

    public function rules(): array
    {
        return [];
    }
}
```

---

### `app/Http/Requests/Lecturer/RejectEnrollmentRequest.php` (NEW)

**Analog:** `app/Http/Requests/Lecturer/StoreSectionRequest.php` (SEC-03 ownership `authorize()` — copy verbatim shape, lines 20-25)

```php
namespace App\Http\Requests\Lecturer;

use App\Enums\RejectionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectEnrollmentRequest extends FormRequest
{
    /**
     * DIVERGENCE FROM D-09 (matches StoreSectionRequest's own comment) —
     * never `return true;`. Any lecturer assigned to the section's subject
     * may reject (per 08-CONTEXT.md: "any lecturer assigned to the subject").
     */
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
}
```

---

### `app/Http/Requests/Lecturer/{Store,Update}ExamRequest.php` (MODIFY — add availability rules)

**Analog:** same files, existing `rules()` array (identical in both, lines 26-34 / 27-35)

**Add to `rules()`:**
```php
'available_from' => ['nullable', 'date'],
'available_until' => ['nullable', 'date', 'after:available_from'],
```
Mirrors `StoreSectionRequest::rules()`'s `opens_at`/`closes_at` shape exactly, except `nullable` (not `required`) per the locked "empty = unbounded" decision. **Do not** modify `UpdateExamRequest::authorize()` — the draft-only gate stays as-is per RESEARCH.md Pitfall 3 recommendation (a).

---

### `database/migrations/2026_07_15_100005_create_exams_table.php` (MODIFY IN PLACE)

**Analog:** same file (full file read, 36 lines) — insert two columns before `$table->timestamps()`.

```php
Schema::create('exams', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->unsignedInteger('duration_minutes');
    $table->boolean('is_published')->default(false);
    $table->dateTime('available_from')->nullable();   // NEW
    $table->dateTime('available_until')->nullable();  // NEW
    $table->timestamps();
});
```
Column type `dateTime` matches `Section` migration's `opens_at`/`closes_at` (not directly re-read this session, but confirmed via `Section::casts()` → `'datetime'`).

---

### `resources/views/student/subjects/index.blade.php` (NEW)

**Analog:** `resources/views/lecturer/sections/index.blade.php` (full file, card+table+`@forelse` structure, lines 1-95)

Copy the outer shell verbatim: `<x-app-layout>` → `$header` slot → `py-12`/`max-w-7xl` container → `session('status')` green flash block → `@forelse`/`@empty` card. For subject list (not grouped-by-subject like the lecturer table), drop the `groupBy` and render one row per subject linking to `student.subjects.show`. Empty-state copy from 08-UI-SPEC.md: "No subjects available" / "There are no subjects open for enrollment right now."

---

### `resources/views/student/subjects/show.blade.php` (NEW)

**Analog:** `resources/views/lecturer/sections/index.blade.php` (table + per-row `x-modal` pattern, lines 34-86)

Reuse the table skeleton (`min-w-full divide-y divide-gray-200 dark:divide-gray-700`, `<thead>`/`<tbody>` header styling `text-sm font-semibold text-gray-500 dark:text-gray-400`). Per-row logic per 08-UI-SPEC.md's "four mutually exclusive states" (Apply / Enrolled+Withdraw / Rejected+reason+Apply / Withdrawn+Apply). Reuse the `x-modal` withdraw-confirm pattern verbatim from the existing "Delete Section" modal (lines 72-85), substituting copy from the Copywriting Contract table. **Apply is a direct POST, no modal** — a plain `<form method="POST">` + `<x-primary-button>` styled like the "Create Section" button (lines 28-31), not a modal trigger.

**Capacity cell:**
```blade
<td class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
    {{ $section->enrollments->where('pivot.status', 'enrolled')->count() }}/{{ $section->capacity }}
    @if ($section->enrollments->where('pivot.status', 'enrolled')->count() >= $section->capacity)
        <x-status-pill status="full">{{ __('FULL') }}</x-status-pill>
    @endif
</td>
```

---

### `resources/views/lecturer/sections/show.blade.php` (NEW — roster)

**Analog:** `resources/views/lecturer/sections/index.blade.php` (table + per-row modal) and `resources/views/lecturer/subjects/index.blade.php` header-row styling (per UI-SPEC Phase Notes reference — not re-read this session, styling class strings already given in UI-SPEC verbatim: `text-sm font-semibold text-gray-500 dark:text-gray-400` header, `divide-y divide-gray-200 dark:divide-gray-700` body).

Columns: Student | Enrolled Since | Action. Reject button opens `x-modal` per-row (same `$dispatch('open-modal', 'reject-{{ $enrollment->id }}')` idiom as the delete-section modal), modal body contains the `<select>` styled like `semester` in `lecturer/sections/create.blade.php` (lines 28-34) plus the reason options from `RejectionReason` cases, submit button "Reject Student" disabled until a reason is chosen (Alpine `x-data="{ reason: '' }"` / `:disabled="!reason"`).

---

### `resources/views/student/exams/show.blade.php` (MODIFY — pre-start page)

**Analog:** same file (full file read, 48 lines) — extend in place.

Current structure to preserve: `x-app-layout` → header slot (exam title) → `session('status')` green flash → info card (Subject/Duration/Questions/description) → Start/Resume form → "Back to my exams" link.

**Additions:**
1. A red `session('error')` flash block, mirroring the existing green one (lines 10-14):
```blade
@if (session('error'))
    <div class="font-medium text-sm text-red-600 dark:text-red-400">
        {{ session('error') }}
    </div>
@endif
```
2. Availability pill next to the title, using `$exam->availabilityState()` mapped through the extended `x-status-pill` arms (`available`/`opening`/`closed`).
3. "Your enrolled section" line (new query needed — pass from controller).
4. Button copy change: `Start Exam`/`Resume Exam` → `Proceed` (per locked Copywriting Contract), plus a new "Back" link/button next to it (reuse the existing "Back to my exams" link's classes: `text-sm text-gray-600 dark:text-gray-400 underline`).

---

### `resources/views/student/attempts/show.blade.php` (MODIFY — `beforeunload`)

**Analog:** same file, `attemptTimer()` factory (lines 249-336, full function read).

**Exact insertion points per RESEARCH.md Pattern 6:**
```js
init() {
    this.setBucket(false);
    this.render();
    this._beforeUnloadHandler = (event) => {
        event.preventDefault();
        event.returnValue = '';
    };
    window.addEventListener('beforeunload', this._beforeUnloadHandler);
},
detachBeforeUnload() {
    window.removeEventListener('beforeunload', this._beforeUnloadHandler);
},
```
`autoSubmit()` (existing, lines 322-334) gets one new line inserted before the `axios.post`:
```js
autoSubmit() {
    if (this.autoSubmitting) { return; }
    this.autoSubmitting = true;
    this.detachBeforeUnload();  // NEW — before the axios POST
    this.display = '00:00';
    clearInterval(this.timerId);
    window.axios.post(submitUrl).finally(() => { window.location.href = submittedUrl; });
},
```
The intentional-submit `<form>` inside `x-modal` (lines 211-233) gets `x-on:submit="detachBeforeUnload()"` added to its opening `<form method="POST" ...>` tag.

---

### `resources/views/lecturer/exams/create.blade.php` / `edit.blade.php` (MODIFY)

**Analog:** `resources/views/lecturer/sections/create.blade.php` (full file, datetime-local field pattern, lines 43-55)

**Field pair to add** (directly after `duration_minutes`, per UI-SPEC Phase Notes):
```blade
<div class="grid grid-cols-2 gap-4 mt-4">
    <div>
        <x-input-label for="available_from" :value="__('Available from (optional)')" class="dark:text-gray-300" />
        <x-text-input id="available_from" name="available_from" type="datetime-local" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('available_from')" />
        <x-input-error :messages="$errors->get('available_from')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="available_until" :value="__('Available until (optional)')" class="dark:text-gray-300" />
        <x-text-input id="available_until" name="available_until" type="datetime-local" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('available_until')" />
        <x-input-error :messages="$errors->get('available_until')" class="mt-2" />
    </div>
</div>
<p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Leave blank for no restriction on that side.') }}</p>
```
Note the **absence** of `required` on both `x-text-input`s (unlike `opens_at`/`closes_at` in the analog, which are required) — this is the one deliberate divergence from the copied pattern. **Scope note (Pitfall 6):** `lecturer/exams/create.blade.php`/`edit.blade.php` currently have NO `dark:` variants anywhere (unlike this analog) — bringing the whole form up to dark-mode parity with `lecturer/sections/create.blade.php` is an explicit in-scope task, not just pasting these two new fields in with `dark:` classes next to un-reskinned ones.

---

### `resources/views/components/status-pill.blade.php` (MODIFY)

**Analog:** same file (full file, 21 lines)

**Current `match()`:**
```php
$classes = match ($normalized) {
    'enrolled', 'published', 'open' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    'rejected', 'closed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    'full' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
};
```
**New arms to add** (per 08-UI-SPEC.md's extended palette table):
```php
$classes = match ($normalized) {
    'enrolled', 'published', 'open', 'available' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    'rejected', 'closed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    'full' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
    'withdrawn', 'opening', 'opens' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
};
```
`'withdrawn'`/`'opening'` become explicit arms per UI-SPEC's instruction ("make it an explicit arm for clarity") even though they already fall through to the same gray `default` — this is a readability change, not a behavior change. `'opens'` (the existing section-only keyword, lowercase, distinct from `'opening'`) already falls through `default` too — leave it there or add it explicitly for symmetry; either is correct since the resulting class string is identical.

---

### `resources/views/layouts/navigation.blade.php` (MODIFY)

**Analog:** same file — extend both the existing `@if (auth()->user()->isLecturer()) ... @else ... @endif` block (desktop, lines 15-40) and its responsive mirror (lines 132-146).

**Pattern to copy** (the existing nav-link classes, exact string, applied to new "Enroll"/"Help" items):
```blade
<a href="{{ route('student.subjects.index') }}"
   class="text-sm font-semibold {{ request()->routeIs('student.subjects.*') ? 'text-blue-600 dark:text-blue-500' : 'text-gray-700 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-500' }}">
    {{ __('Enroll') }}
</a>
```
Insert order per UI-SPEC: Lecturer → Subjects / Sections / Exams / **Help**. Student → **Enroll** / My Exams / **Help**. Remove the two "Phase 8 deferral" comment blocks (lines 28-31, 37-39) — they exist specifically to be replaced this phase. Mirror the same additions in the responsive `<x-responsive-nav-link>` block (lines 132-146), copying that component's exact prop/class pattern.

---

### `routes/student.php` / `routes/lecturer.php` (MODIFY)

**Analog:** same files, existing `Route::middleware([...])->prefix(...)->name(...)->group(function () { ... })` structure.

**student.php additions** (inside the existing group, following the existing flat-then-nested style already used for `attempts.*`):
```php
Route::get('subjects', [SubjectBrowseController::class, 'index'])->name('subjects.index');
Route::get('subjects/{subject}', [SubjectBrowseController::class, 'show'])->name('subjects.show');
Route::post('sections/{section}/enroll', [EnrollmentController::class, 'store'])->name('sections.enroll');
Route::delete('sections/{section}/enroll', [EnrollmentController::class, 'destroy'])->name('sections.withdraw');
Route::get('help', fn () => view('student.help'))->name('help.show'); // matches the existing inline-closure convention used for 'home'
```
**lecturer.php additions** (inside the existing `subjects/{subject}` nested-prefix group, alongside the existing `sections.*` routes):
```php
Route::get('sections/{section}', [SectionController::class, 'show'])->name('sections.show');
Route::patch('sections/{section}/enrollments/{student}/reject', [RejectEnrollmentController::class, 'reject'])->name('sections.enrollments.reject');
```
And, top-level like `home`:
```php
Route::get('help', fn () => view('lecturer.help'))->name('help.show');
```
Note: `Route::get('/', fn () => view('lecturer.home'))->name('home');` (line 17 of `lecturer.php`) is the exact precedent for the inline-closure route style used for both new `help.show` routes.

---

## Shared Patterns

### Capacity-safe write (transaction + `lockForUpdate()`)
**Source:** `app/Http/Controllers/Lecturer/SectionController.php` lines 56-73 (verified, Phase 7)
**Apply to:** `EnrollmentController@store` (capacity check + ENR-04 check, both inside the same locked transaction)
```php
DB::transaction(function () use ($request, $subject) {
    $sequence = Section::where('subject_id', $subject->id)
        ->where('year', $request->validated('year'))
        ->where('semester', $request->validated('semester'))
        ->lockForUpdate()
        ->max('sequence') + 1;

    Section::create([...]);
});
```

### Per-subject ownership `authorize()` (SEC-03) — never `return true;`
**Source:** `app/Http/Requests/Lecturer/StoreSectionRequest.php` lines 20-25 (verified)
**Apply to:** `RejectEnrollmentRequest::authorize()`
```php
public function authorize(): bool
{
    $subject = $this->route('subject');

    return $subject->lecturers()->whereKey($this->user()->id)->exists();
}
```

### Ownership-only Policy check (independent of `Exam::visibleTo()`)
**Source:** `app/Policies/AttemptPolicy.php` lines 32-35, `viewResult()` (verified, existing precedent)
**Apply to:** `AttemptPolicy::view()` / `AttemptPolicy::update()` (the mandatory fix)
```php
public function viewResult(User $user, Attempt $attempt): bool
{
    return $attempt->user_id === $user->id;
}
```

### Flash-message convention — new red `session('error')` alongside existing green `session('status')`
**Source:** every existing view's green-only block, e.g. `resources/views/lecturer/sections/index.blade.php` lines 10-14:
```blade
@if (session('status'))
    <div class="font-medium text-sm text-green-600 dark:text-green-400">
        {{ session('status') }}
    </div>
@endif
```
**Apply to:** every new/modified view that can now receive a refusal (`student/exams/show.blade.php`, `student/subjects/show.blade.php`, and any view rendering after a redirect from `EnrollmentController`/`AttemptController@store`). Add the mirrored red block immediately after:
```blade
@if (session('error'))
    <div class="font-medium text-sm text-red-600 dark:text-red-400">
        {{ session('error') }}
    </div>
@endif
```
This is a **new** convention this phase introduces — document it as the pattern for future phases too (per RESEARCH.md "State of the Art" table).

### `x-modal` destructive-confirm pattern (trigger + per-row modal)
**Source:** `resources/views/lecturer/sections/index.blade.php` lines 63, 72-85 (verified)
**Apply to:** Withdraw modal (`student/subjects/show.blade.php`) and Reject modal (`lecturer/sections/show.blade.php`)
```blade
<button type="button" x-data @click="$dispatch('open-modal', 'delete-section-{{ $section->id }}')" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400">{{ __('Delete') }}</button>

<x-modal name="delete-section-{{ $section->id }}" focusable>
    <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-900">{{ __('Delete Section') }}</h2>
        <p class="mt-2 text-sm text-gray-600">{{ __('...') }}</p>
        <div class="mt-6 flex justify-end gap-3">
            <x-secondary-button x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
            <form method="POST" action="{{ route('...') }}">
                @csrf
                @method('DELETE')
                <x-danger-button>{{ __('Delete Section') }}</x-danger-button>
            </form>
        </div>
    </div>
</x-modal>
```

### Half-open window-status computation (three consumers)
**Source:** `resources/views/lecturer/sections/index.blade.php` lines 46-56 (verified — currently inline `@php`, should be extracted to `Section::windowStatus()` per this phase)
```php
if ($now->lt($section->opens_at)) {
    $windowStatus = 'opens';
} elseif ($now->gte($section->closes_at)) {
    $windowStatus = 'closed';
} else {
    $windowStatus = 'open';
}
```
**Apply to:** `Section::windowStatus()` (new method) and `Exam::isAvailableNow()`/`availabilityState()` (same half-open boundary logic, `[from, until)`).

## No Analog Found

Files with no close match in the codebase (planner should use RESEARCH.md/UI-SPEC patterns instead):

| File | Role | Data Flow | Reason |
|------|------|-----------|--------|
| `resources/views/student/help.blade.php` | view (static content) | request-response | No prior in-app documentation page exists anywhere in the repo. Only the generic `x-app-layout` shell (used by every other view) and the `max-w-3xl`-container convention (`student/exams/show.blade.php`) are reusable as structural analogs — content structure must follow 08-UI-SPEC.md's Phase Notes §4 verbatim (five `<h3>` task sections, `list-decimal list-inside space-y-2` steps). |
| `resources/views/lecturer/help.blade.php` | view (static content) | request-response | Same as above — four `<h3>` task sections per UI-SPEC §4. |
| `database/factories/ExamFactory.php` availability states | factory | transform | Not read this session (file exists per RESEARCH.md file inventory but its current `Sequence`/state-method conventions weren't verified) — planner should read the existing file directly before adding `available()`/`opening()`/`closed()` states, following whatever state-method idiom `SectionFactory` or `QuestionFactory` already establishes for date-bearing attributes. |
| `database/seeders/DatabaseSeeder.php` | seed | batch | Not read this session — existing named-account/`firstOrCreate` pattern (per CLAUDE.md §5) should be followed directly from the current file at plan time. |

## Metadata

**Analog search scope:** `app/Models`, `app/Policies`, `app/Http/Controllers/{Lecturer,Student}`, `app/Http/Requests/{Lecturer,Student}`, `database/migrations`, `resources/views/{lecturer,student}/{sections,exams,attempts}`, `resources/views/components`, `resources/views/layouts`, `routes/{student,lecturer}.php`
**Files scanned/read in full this session:** 15 (`SectionController.php`, `AttemptPolicy.php`, `StoreSectionRequest.php`, `Enrollment.php`, `Exam.php`, `Section.php`, `AttemptController.php` (Student), `UpdateExamRequest.php`, `StoreExamRequest.php`, `2026_07_15_100005_create_exams_table.php`, `navigation.blade.php`, `lecturer/sections/index.blade.php`, `lecturer/sections/create.blade.php`, `student/attempts/show.blade.php`, `student/exams/show.blade.php`, `components/status-pill.blade.php`, `routes/student.php`, `routes/lecturer.php`)
**Pattern extraction date:** 2026-07-16

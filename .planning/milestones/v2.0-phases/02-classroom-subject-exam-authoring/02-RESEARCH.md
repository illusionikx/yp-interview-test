# Phase 2: Classroom, Subject & Exam Authoring - Research

**Researched:** 2026-07-15
**Domain:** Laravel 11 resource-controller CRUD + nested parent→child→grandchild authoring (exam→question→option) with dynamic client-side form rows
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

*(Auto mode: recommended, research/Phase-1-grounded defaults.)*

#### Controllers & routing
- **D-01:** Resource controllers under an `App\Http\Controllers\Lecturer\` namespace — `ClassroomController`, `SubjectController`, `ExamController`, plus nested question/option management (e.g. `Lecturer\ExamQuestionController`). Register routes inside the existing **`routes/lecturer.php`** group (already `auth` + `role:lecturer` gated from Phase 1) with `lecturer.` name prefix.

#### Classroom & subject CRUD (CLS-01, CLS-02)
- **D-02:** Standard resource CRUD (index/create/store/edit/update/destroy) for classrooms and subjects. Names required; keep them unique per entity to avoid duplicates (planner/executor may relax if it complicates seeding). Form Requests validate input — never expose `role`/`classroom_id`/`created_by` as mass-assignable from these forms.

#### Classroom ↔ subject linkage (CLS-03)
- **D-03:** Managed from the **classroom** form as a multi-select of subjects, synced through the `classroom_subject` pivot (`$classroom->subjects()->sync(...)`). Pivot name is `classroom_subject` (resolved in Phase 1).

#### Student roster (CLS-04)
- **D-04:** A student belongs to exactly one classroom (`users.classroom_id`, from Phase 1). Assign/reassign students on the **classroom** page — a manager listing students (role=Student) with attach/detach that sets/clears `classroom_id`. Detaching sets it null (student unassigned).

#### Exam authoring UX (EXM-01, EXM-05, EXM-06)
- **D-05:** An exam `belongs to` a subject and has: `title`, optional `description`, `duration_minutes` (the time limit), `created_by` (the authoring lecturer), `is_published`. Create exam → land on the exam's own page which lists its questions and hosts the add-question form.
- **D-06:** Draft vs published: `is_published=false` exams are fully editable (exam fields, questions, options). Publishing (`is_published=true`) makes the exam eligible for classroom assignment (Phase 3). **Unpublish→edit is allowed** (reversible) because no attempts exist until Phase 4 — a later phase can lock editing once attempts exist. Edit/delete of the exam and its questions is only permitted while unpublished.

#### Question & option authoring (EXM-02, EXM-03, EXM-04)
- **D-07:** Questions share one `questions` table with a `type` discriminator enum (`Mcq` | `Open`, from Phase 1) and a `points` column (default 1). Add-question form on the exam page: choosing MCQ reveals dynamic **option rows** (Alpine.js `x-data` add/remove) with a single "correct" radio; choosing Open shows just the question text. Open-text questions have no options.
- **D-08:** MCQ validation (Form Request): **≥2 options, exactly one marked correct**, single-select only (multi-select is v2, out of scope). `points` is a positive integer, default 1. Reject an MCQ with zero or multiple correct options.

#### Lecturer ownership scoping
- **D-09:** For v1 simplicity, **lecturers share management** of classrooms, subjects, and exams (any lecturer can manage any). The brief specifies a single Lecturer role without per-lecturer ownership boundaries. `exams.created_by` records the author (nullable, `nullOnDelete` from Phase 1 review fix) but is not used to restrict edits in this phase. Per-record ownership policies are NOT required — do not build them. (Student class-scoped access is a different concern, handled in Phase 3.)

#### UI approach
- **D-10:** Reuse the Breeze Blade stack — `x-app-layout`, existing `resources/views/components/*` (input, label, primary-button, etc.), Tailwind, Alpine. Functional CRUD forms and tables, no bespoke design system. (No UI-SPEC needed for this phase — it reuses existing components; the roadmap reserves UI-SPECs for Phases 4/5. Plan with `--skip-ui`.)

### Claude's Discretion
- Exact route/view file layout, whether questions get their own controller vs nested under exam, table vs card listings, name-uniqueness enforcement, and Alpine component structure — planner/executor choice, provided the decisions above hold.

### Deferred Ideas (OUT OF SCOPE)
- **Exam→classroom assignment + student-facing access (ASN-01/02, RBAC-05)** → Phase 3. Do not build `exam_classroom` linking UI or any student exam view here.
- **Taking exams / attempts / timer** → Phase 4.
- **Grading & results** → Phase 5.
- **Randomized question/option order, multi-select MCQ** → v2 (out of scope).
- **Locking exam edits once attempts exist** → revisit in Phase 4 when attempts are introduced.

None of the above were user scope-creep — they are the phase boundaries, noted so the planner doesn't pull them forward.
</user_constraints>

## Summary

Nine of this phase's ten requirements (CLS-01..04, EXM-01, EXM-04..06) are textbook Laravel 11 resource-controller CRUD: Form Requests, Eloquent relationships already built in Phase 1, `sync()` for the `classroom_subject` pivot, and a plain FK update for the student roster (`users.classroom_id` is a `belongsTo`, not a pivot — "assigning a student" is just `User::update(['classroom_id' => ...])`/`null`, no `attach`/`detach` API involved despite the CONTEXT.md wording). D-01 through D-10 in `02-CONTEXT.md` already lock every structural decision; this research does not re-litigate them, it fills in the concrete "how."

The one genuinely non-trivial area is **EXM-02/EXM-03: nested exam→question→option authoring with a dynamic MCQ option list and an exactly-one-correct-option guarantee.** The locked flow (D-05) is create-exam → redirect to the exam's own show page → add each question there, which is the correct Laravel-native shape (each POST is a focused, independently validated Form Request; the exam's show page becomes the natural nested-resource index for its questions). Two implementation details deserve depth because a naive approach gets them wrong:

1. **Use a single HTML radio group (not per-row checkboxes) for "which option is correct."** A `name="options[i][is_correct]"` checkbox per row cannot express "exactly one" natively — the browser allows zero or many checked. A single shared `name="correct_option"` radio, valued by the option's 0-based row position, gets "at most one selected" for free from the browser and reduces the server-side check to "was a valid index submitted" plus a `min:2` option count — no need to count booleans across an array.
2. **On question edit, delete-and-recreate the option set rather than upsert-by-ID.** Because D-06 guarantees no exam attempts exist until it is published (and this phase never builds attempt-taking), no other table can hold a foreign key to an `options.id` while an exam is still editable. That makes "delete all existing options, bulk-insert the submitted set fresh" inside a `DB::transaction()` both simpler and fully correct — it avoids ID-matching/upsert logic entirely. This pattern would need revisiting only once Phase 4 introduces `answers.selected_option_id`, which is explicitly out of scope here.

**Primary recommendation:** Build `Lecturer\ExamQuestionController` (nested resource under `exams`) with a `StoreQuestionRequest`/`UpdateQuestionRequest` pair that validates `type`, `points`, and (when `type=mcq`) an `options` array (`min:2`) plus a single `correct_option` integer naming the correct row's position; persist by deleting and re-inserting the question's `options` rows inside a transaction. Gate every mutating exam/question/option action behind a small reusable `is_published === false` check (a Form Request `authorize()` override or a tiny route-bound middleware) rather than duplicating `abort_if()` calls across five controller methods.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Classroom/Subject CRUD | API/Backend (Laravel controllers + Form Requests) | Browser (Blade forms, no client logic needed) | Plain create/edit/delete against a single table; no client-side state. |
| Classroom ↔ Subject linkage | API/Backend (`sync()` on the pivot relation) | Browser (multi-select `<select multiple>` in the classroom form) | `sync()` is a single Eloquent call; the browser only needs to submit a `subject_ids[]` array — no JS required. |
| Student roster assignment | API/Backend (`User::update(['classroom_id' => ...])`) | Browser (simple per-row assign/unassign forms) | Single FK column, not a pivot — server does one `UPDATE`; no client state needed beyond a normal form POST. |
| Exam CRUD (title/description/duration/subject) | API/Backend | Browser (plain form) | Same as classroom/subject — no dynamic behavior. |
| MCQ question + option authoring | Browser (Alpine.js dynamic rows) | API/Backend (Form Request validation + transactional persistence) | The add/remove option UX genuinely lives client-side (Alpine `x-for`); but correctness (exactly-one-correct, ≥2 options) MUST be re-verified server-side — the client is UX only, never the authority. |
| Open-text question authoring | API/Backend | Browser (plain textarea + points field) | No options, no dynamic rows — a single Form Request field set. |
| Draft/published state gate | API/Backend (authorization check before every mutation) | — | Purely a server-side business rule; nothing client-visible needs to enforce it beyond hiding edit buttons for UX. |
| Persistence (all of the above) | Database (MySQL, Phase 1 schema) | — | Already built; this phase only writes to it through Eloquent, no new tables/columns. |

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | ^11.31 (installed: 11.55.0, confirmed via `php artisan --version`) `[VERIFIED: local install]` | Resource controllers, Eloquent, Form Requests, validation | Already the mandated project stack; no version change needed for this phase. |
| PHP | ^8.2 (installed: 8.2.32, confirmed via `php --version`) `[VERIFIED: local install]` | Runtime | Fixed by the existing scaffold. |
| Laravel Breeze | ^2.4 (installed) | Blade auth scaffold, base layout, Tailwind/Alpine build pipeline | Already scaffolded per PROJECT.md — do not touch, only build within `x-app-layout` and existing components. |
| MySQL | 8.x via Herd, database `yp-student-exam` | Persistence | Already configured; Phase 1's 9 tables + `users.role`/`classroom_id` are the complete schema this phase writes to — no migrations needed in Phase 2. |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Alpine.js | as bundled with Breeze's Vite build (no separate install) | Dynamic option-row add/remove, MCQ/Open toggle, single-correct radio highlight | Client-side UX only for the question/option authoring form — see Pattern 2 below. |
| `fakerphp/faker` | already present (dev dependency) | Factory fixture data (`SubjectFactory`, `ExamFactory`, `QuestionFactory`, `OptionFactory` — none exist yet, see Wave 0 Gaps) | Standard Laravel factory usage; no new package. |

**No new Composer or npm packages are required for this phase** — everything above is already installed. `composer.json`/`package.json` need no changes.

**Version verification:** Confirmed directly against the running local environment (`php --version`, `php artisan --version`, `composer --version`) on 2026-07-15 — see the `[VERIFIED: local install]` tags above. No registry lookups were needed since nothing new is being added.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| A single reusable "is this exam still editable" server-side gate | Duplicated `abort_if($exam->is_published, 403)` in every controller method that touches the exam/questions/options | Duplication is error-prone (easy to forget one endpoint, e.g. the option-delete action) — a shared check is one place to audit, consistent with the codebase's existing preference for centralizing RBAC in `EnsureUserHasRole` rather than ad hoc checks (see `app/Http/Middleware/EnsureUserHasRole.php`). |
| Delete-and-recreate options on question update | Upsert-by-ID (`updateOrCreate(['id' => $row['id'] ?? null], [...])` per option row, matched against posted vs. existing IDs, deleting any DB row whose ID wasn't resubmitted) | Upsert-by-ID is the "textbook complete" approach and would be *required* once option IDs are referenced elsewhere (Phase 4's `answers.selected_option_id`), but for this phase — where no attempt can exist while unpublished — it's strictly more code for the same observable result. Not recommended for this phase; noted so the planner doesn't feel obligated to build it. |
| Single radio group (`name="correct_option"`, value = row position) for "the correct option" | Per-row checkbox named `options[i][is_correct]` | Checkboxes require JS to enforce mutual exclusivity (or a server-side count check) and can submit 0 or N checked; a radio group gets exactly 0-or-1 selected for free from the browser, simplifying both the Alpine component and the Form Request. Strongly recommended — see Pattern 1. |
| `classroom_subject`/roster managed on the classroom's own page (D-03/D-04) | Independent `ClassroomSubjectController`/`RosterController` with their own index pages | Locked by CONTEXT.md D-03/D-04 — not re-litigated here. The "manage from the owning resource's form" pattern also matches `research/ARCHITECTURE.md`'s stated structure rationale ("pivot tables... managed through the owning resource's forms, not independently CRUD'd"). |

**Installation:** None — no new packages.

## Package Legitimacy Audit

**Not applicable this phase.** No external Composer or npm packages are installed — every library used (Laravel 11, Breeze, Alpine.js, Faker) is already present in `composer.json`/`package.json` from Phase 1's scaffold. The Package Legitimacy Gate is skipped because its trigger condition ("phase installs external packages") does not apply.

## Architecture Patterns

### System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│  Browser — Lecturer authoring UI (Blade + Alpine, x-app-layout)      │
│                                                                       │
│  Classroom form ──(subject_ids[] multi-select)──┐                    │
│  Classroom roster panel ──(student_id per row)──┤                    │
│  Exam form ──(subject_id, title, duration)──────┤                    │
│  Question form (Alpine x-data) ─────────────────┤                    │
│    ├─ type toggle: mcq | open                   │                    │
│    ├─ mcq → x-for option rows (add/remove)       │                    │
│    └─ mcq → correct_option radio (shared name)   │                    │
└──────────────────────┬───────────────────────────┴───────────────────┘
                       │ POST/PUT/PATCH/DELETE (session auth + CSRF)
┌──────────────────────▼───────────────────────────────────────────────┐
│  routes/lecturer.php  — middleware: auth, verified, role:lecturer    │
├────────────────────────────────────────────────────────────────────┤
│  ClassroomController          SubjectController                     │
│    index/create/store/edit/     index/create/store/edit/            │
│    update/destroy               update/destroy                     │
│    + subjects()->sync()         (no linkage — read-only from        │
│    (D-03)                        classroom side)                    │
│                                                                       │
│  ClassroomRosterController      ExamController                      │
│    (assign/unassign a student     index/create/store/show/edit/     │
│    — sets/clears classroom_id,    update/destroy                    │
│    D-04)                          + publish/unpublish action        │
│                                                                       │
│  ExamQuestionController — nested under exams (EXM-02/03/04)         │
│    store/update/destroy — persists Question + (if mcq) Options,     │
│    delete-and-recreate options inside DB::transaction()             │
├────────────────────────────────────────────────────────────────────┤
│  Form Requests validate shape + business rules BEFORE the           │
│  controller runs (exactly-one-correct via after(), points>=1,       │
│  type in enum, is_published gate on every mutating action)          │
├────────────────────────────────────────────────────────────────────┤
│  Eloquent models (Phase 1): Classroom, Subject, Exam, Question,     │
│  Option, User — relationships already defined, reused as-is         │
└──────────────────────┬───────────────────────────────────────────────┘
                       │
┌──────────────────────▼───────────────────────────────────────────────┐
│  MySQL (yp-student-exam) — classrooms, subjects, classroom_subject,  │
│  exams, questions, options, users.classroom_id                       │
└────────────────────────────────────────────────────────────────────┘
```

A reader can trace "lecturer adds an MCQ question" end to end: Alpine renders option rows client-side → single POST hits `ExamQuestionController@store` → `StoreQuestionRequest` validates `type=mcq`, `options` (≥2, non-blank bodies), `correct_option` (valid index) and rejects if the exam is already published → controller creates the `Question` row then bulk-inserts `Option` rows with `is_correct` set only at `correct_option`'s position, all inside one transaction → redirect back to the exam show page, which re-lists questions from the DB (never from client state).

### Recommended Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Lecturer/
│   │       ├── ClassroomController.php        # CLS-01, hosts subject multi-select + roster panel views
│   │       ├── ClassroomRosterController.php   # CLS-04 — assign/unassign a student (nested under classrooms)
│   │       ├── SubjectController.php           # CLS-02
│   │       ├── ExamController.php              # EXM-01, EXM-05, EXM-06 (+ publish/unpublish action)
│   │       └── ExamQuestionController.php      # EXM-02, EXM-03, EXM-04 — nested under exams
│   └── Requests/
│       └── Lecturer/
│           ├── StoreClassroomRequest.php, UpdateClassroomRequest.php
│           ├── StoreSubjectRequest.php, UpdateSubjectRequest.php
│           ├── AssignStudentRequest.php         # validates target user role=student
│           ├── StoreExamRequest.php, UpdateExamRequest.php
│           └── StoreQuestionRequest.php, UpdateQuestionRequest.php  # the exactly-one-correct logic lives here
resources/views/lecturer/
├── classrooms/  index, create, edit (edit hosts subject multi-select + roster panel)
├── subjects/    index, create, edit
├── exams/       index, create, show (hosts question list + add-question form), edit
└── exams/questions/
    └── _form.blade.php  # shared partial for create+edit, Alpine x-data component (D-07)
```

### Structure Rationale

- **`ExamQuestionController` nested under `exams`, no separate `OptionController`:** options never exist independently of a question and are always submitted as part of the same question POST (D-07's single add-question form). A dedicated `OptionController` would need its own routes/authorization/views for no behavior the question form doesn't already provide — unnecessary indirection at this scale.
- **`ClassroomRosterController` separate from `ClassroomController`:** roster assignment (mutating `users.classroom_id`) is a distinct write action on a different model (`User`, not `Classroom`) with its own authorization concern (must target `role=student` users only) — worth its own thin controller/Form Request rather than overloading `ClassroomController@update`.
- **Form Requests namespaced `Lecturer\`, mirroring D-01's controller namespace:** keeps the authoring surface self-contained and easy to audit for the mass-assignment discipline called out in CONTEXT.md (never accept `role`/`classroom_id`/`created_by` from generic request input).

### Pattern 1: Single radio group for "exactly one correct option" (the core non-trivial pattern)

**What:** Render MCQ option rows with a text input per row (`options[i][body]`) but a *single, shared-name* radio input for correctness (`name="correct_option"`, `value="i"` per row) rather than a per-row `is_correct` checkbox. The browser natively guarantees at most one radio in a shared group can be checked; this eliminates an entire class of client-side bugs (multiple checked, none checked due to a stray click) before the request is even sent.

**When to use:** Any "pick exactly one from a dynamic list" UI — this is the general solution to EXM-02/D-08, not specific to this framework.

**Server-side, this collapses the "exactly one correct" rule into two much simpler checks** instead of counting booleans across an array:
1. `correct_option` is required (when `type=mcq`) and must be a valid index into the submitted `options` array.
2. `options` has at least 2 non-blank entries (`min:2` after filtering blanks, or `array|min:2` if blank rows are trimmed client-side before submit).

**Example (Form Request):**
```php
// Source: Laravel 11.x official validation docs (laravel.com/docs/11.x/validation)
// — array/wildcard validation + after() hook, adapted to this phase's schema
use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Locked by D-06: mutating an exam's questions is only allowed pre-publish.
        return ! $this->route('exam')->is_published;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'body' => ['required', 'string'],
            'points' => ['required', 'integer', 'min:1'],
            'options' => ['required_if:type,mcq', 'array', 'min:2'],
            'options.*.body' => ['required_with:options', 'string'],
            'correct_option' => ['required_if:type,mcq', 'integer'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('type') !== QuestionType::Mcq->value) {
                    return;
                }
                $options = $this->input('options', []);
                $correct = (int) $this->input('correct_option', -1);

                if (! array_key_exists($correct, $options)) {
                    $validator->errors()->add(
                        'correct_option',
                        'Select which option is correct.'
                    );
                }
            },
        ];
    }
}
```

**Example (Alpine + Blade, shared create/edit partial):**
```html
<!-- Source: Alpine.js official docs (alpinejs.dev/directives/for), adapted -->
<div x-data="{
        type: '{{ old('type', $question->type?->value ?? 'mcq') }}',
        options: {{ Js::from($question->options->map(fn ($o, $i) => ['key' => $i, 'body' => $o->body])->values() ?: [['key' => 0, 'body' => ''], ['key' => 1, 'body' => '']]) }},
        correct: {{ $question->options->search(fn ($o) => $o->is_correct) ?: 0 }},
        nextKey: {{ $question->options->count() ?: 2 }},
        addOption() { this.options.push({ key: this.nextKey++, body: '' }) },
        removeOption(i) { this.options.splice(i, 1) },
     }">
    <select x-model="type" name="type">
        <option value="mcq">Multiple choice</option>
        <option value="open">Open text</option>
    </select>

    <template x-if="type === 'mcq'">
        <div>
            <template x-for="(option, index) in options" :key="option.key">
                <div>
                    <input type="radio" name="correct_option" :value="index" x-model.number="correct">
                    <input type="text" :name="'options[' + index + '][body]'" x-model="option.body">
                    <button type="button" @click="removeOption(index)" x-show="options.length > 2">Remove</button>
                </div>
            </template>
            <button type="button" @click="addOption()">Add option</button>
        </div>
    </template>
</div>
```
Note: `:key="option.key"` uses a stable monotonic counter (`nextKey`), **not** the array index — this is the Alpine-documented requirement for correct DOM tracking across add/remove (see Common Pitfalls).

### Pattern 2: Delete-and-recreate child options on question update

**What:** Inside `ExamQuestionController@update`, wrap the write in `DB::transaction()`: update the `Question` row's own scalar fields, then `$question->options()->delete()` followed by a fresh `$question->options()->createMany([...])` built from the validated payload (only when `type=mcq`; call `$question->options()->delete()` unconditionally first to also handle a question being switched from `mcq` to `open`).

**When to use:** Editing a question while the exam is still a draft — safe specifically because D-06 guarantees no `Attempt`/`Answer` rows exist yet (this phase never creates them), so no foreign key anywhere in the schema can be dangling after a full option-set replace.

**Example:**
```php
// app/Http/Controllers/Lecturer/ExamQuestionController.php
public function update(UpdateQuestionRequest $request, Exam $exam, Question $question)
{
    DB::transaction(function () use ($request, $question) {
        $question->update($request->safe()->only(['type', 'body', 'points']));

        $question->options()->delete(); // safe: no attempts exist pre-publish (D-06)

        if ($question->type === QuestionType::Mcq) {
            $correct = (int) $request->validated('correct_option');
            $question->options()->createMany(
                collect($request->validated('options'))
                    ->values()
                    ->map(fn ($option, $i) => [
                        'body' => $option['body'],
                        'is_correct' => $i === $correct,
                        'position' => $i,
                    ])
                    ->all()
            );
        }
    });

    return redirect()->route('lecturer.exams.show', $exam);
}
```

### Pattern 3: `sync()` for the classroom↔subject pivot, plain FK update for the roster

**What:** `$classroom->subjects()->sync($request->validated('subject_ids', []))` replaces the classroom's entire subject set in one call — detaches anything not in the array, attaches anything new, leaves matches untouched (D-03). The student roster (D-04) is **not** a pivot at all: `users.classroom_id` is a single nullable FK, so "assigning" a student is `$student->update(['classroom_id' => $classroom->id])` and "unassigning" is `$student->update(['classroom_id' => null])` — there is no `attach()`/`detach()` involved despite CONTEXT.md's wording; the pivot vocabulary in D-04 describes the UX ("attach/detach"), not the underlying Eloquent call.

**When to use:** Any many-to-many multi-select form (subjects) vs. any single-FK "belongs to one" assignment form (roster) — these are structurally different operations even though the UI for both can look like a picklist.

**Example:**
```php
// Source: Laravel 11.x official Eloquent relationships docs, sync() semantics
// (belongsToMany — updating the associated models, "Syncing Associations")
public function update(UpdateClassroomRequest $request, Classroom $classroom)
{
    $classroom->update($request->safe()->only(['name']));
    $classroom->subjects()->sync($request->validated('subject_ids', []));

    return redirect()->route('lecturer.classrooms.edit', $classroom);
}

// ClassroomRosterController@store (assign) / @destroy (unassign)
public function store(AssignStudentRequest $request, Classroom $classroom)
{
    User::query()
        ->where('id', $request->validated('student_id'))
        ->where('role', Role::Student) // never move a lecturer account by accident
        ->update(['classroom_id' => $classroom->id]);

    return back();
}

public function destroy(Classroom $classroom, User $student)
{
    abort_unless($student->classroom_id === $classroom->id, 404);
    $student->update(['classroom_id' => null]);

    return back();
}
```

### Anti-Patterns to Avoid

- **Per-row `is_correct` checkboxes instead of a shared-name radio group:** requires extra JS to enforce mutual exclusivity and an array-counting server check for "exactly one" instead of a single index check. Use Pattern 1 instead.
- **Using the Alpine array index as `:key`:** breaks DOM/data tracking the moment a row in the middle of the list is removed (Alpine's own docs call this out explicitly — "don't key by index, as indices change at every iteration"). Use a stable monotonic counter instead (Pattern 1's `nextKey`).
- **Duplicating the `is_published` gate as an inline `abort_if()` in every controller method:** easy to forget on one of the five+ mutating endpoints (exam update/destroy, question store/update/destroy). Centralize via Form Request `authorize()` (as in Pattern 1's example) or a small route-bound middleware, not repeated inline checks — same lesson `research/ARCHITECTURE.md` documents for the RBAC middleware/policy split ("Anti-Pattern 2").
- **Building `spatie/laravel-permission` or any package for the `classroom_subject` linkage:** it's a plain pivot with no extra columns — `belongsToMany()->sync()` is the complete, correct, zero-dependency answer (matches PROJECT.md's stack decision to avoid packages for RBAC/relationship needs this small).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| "Exactly one correct option" enforcement | A custom loop counting `is_correct === true` across a posted array, or a bespoke `Rule` class | Radio-group UI (Pattern 1) + Form Request `after()` hook checking the submitted index is in range | The radio group makes "more than one checked" structurally impossible client-side; server only needs to confirm an index was actually chosen. A counting-based custom Rule class is strictly more code for a weaker guarantee (still allows the client to send malformed data the radio group prevents by construction). |
| Classroom↔subject many-to-many sync | Manual `attach()`/`detach()` diffing (compute which IDs to add, which to remove, loop and call both) | `$classroom->subjects()->sync($ids)` | `sync()` is exactly this diff, built into Eloquent — reimplementing it invites off-by-one/duplicate-row bugs `sync()` already handles atomically. |
| Draft/published edit gate | A new `is_editable` computed column, an Observer, or a bespoke state-machine package | A one-line boolean check (`! $exam->is_published`) in a Form Request `authorize()` or route middleware | Three-state or even two-state lifecycles this simple don't warrant infrastructure — `research/STACK.md`'s "What NOT to Use" table makes the identical call for `Attempt::status` (no state-machine package for linear enum-backed states), and the same reasoning applies here. |
| Question-type-driven form rendering | Two entirely separate Blade view files (mcq-form.blade.php / open-form.blade.php) with duplicated exam/points fields | One Alpine `x-data` component toggling visibility of the options block via `x-if`/`x-show` (Pattern 1) | A single shared partial keeps the points/body fields defined once; branching only the options block avoids duplicating markup for the ~80% of the form both types share. |

**Key insight:** every "hand-roll risk" in this phase collapses to the same lesson — Laravel/Eloquent and native HTML form semantics (radio groups) already express these constraints correctly; the only custom code needed is a thin Form Request layer re-asserting server-side what the UI already enforces client-side, per Anti-Pattern 1 in `research/ARCHITECTURE.md` ("never trust the client alone").

## Common Pitfalls

### Pitfall 1: Keying Alpine's `x-for` by array index instead of a stable ID

**What goes wrong:** Removing option row 2 of 4 causes row 4's input value to visually "jump" into row 3's DOM node (or vice versa), corrupting what the lecturer sees they're editing, because Alpine reuses DOM nodes keyed by position rather than identity.
**Why it happens:** `:key="index"` looks correct at first (rows do reorder correctly on add), but breaks specifically on mid-list removal, which is easy to miss in manual testing if you only ever test "remove the last row."
**How to avoid:** Bind `:key` to a stable, monotonically-increasing counter assigned once per row at creation time (Pattern 1's `nextKey`), never to `index`.
**Warning signs:** A dynamic-rows demo that "works" when you always add/remove from the end of the list but shows stale text when you remove a row from the middle.

### Pitfall 2: Treating the "exactly one correct" rule as purely a database concern

**What goes wrong:** Relying only on `options.is_correct` boolean defaults and hoping the UI never sends a bad combination — with no server-side re-check, a modified/replayed request (or a future API client) can create an MCQ question with zero or multiple correct options.
**Why it happens:** The schema (per Phase 1 / `research/ARCHITECTURE.md`) intentionally leaves this rule out of the database ("enforce in Form Request, not DB") since a DB-level constraint for "exactly one true per group" needs a partial unique index MySQL doesn't support cleanly. That's correct, but it means the Form Request `after()` check (Pattern 1) is the *only* enforcement point — skipping it, or only validating client-side, leaves D-08 unmet.
**How to avoid:** Always run the `after()` hook server-side on both `store` and `update`; never trust that the Alpine radio group alone is sufficient (D-08 requires ≥2 options too, which a radio group doesn't enforce by itself — an MCQ with exactly 1 option and it marked correct passes a naive radio check but fails D-08).
**Warning signs:** A feature test that only exercises the happy path (2 valid options, one correct) — add explicit tests for 0 options, 1 option, and a `correct_option` index out of range (see Validation Architecture below).

### Pitfall 3: Forgetting the exam is a draft *only until D-06's gate is applied everywhere it needs to be*

**What goes wrong:** The exam's own `update`/`destroy` are gated, but a question's `store`/`update`/`destroy` (or an individual option) are left ungated — a published exam's content can still be silently edited through a route the exam-level check doesn't cover.
**Why it happens:** D-06 says "the exam and its questions" — it's easy to implement the check once on `ExamController` and forget it needs re-application on every `ExamQuestionController` action too, since they're separate controllers/Form Requests.
**How to avoid:** Put the check in one reusable place (Form Request `authorize()` pattern shown above, checking `$this->route('exam')->is_published`) and apply the *same* Form Request base logic to every mutating action across both controllers — don't inline `abort_if()` five separate times.
**Warning signs:** A feature test suite that only tests "can't edit a published exam's title" but never tests "can't add/edit/delete a question on a published exam."

### Pitfall 4: `sync()` called before the parent model has an ID

**What goes wrong:** On the *create* flow (new classroom + initial subject selection in one form), calling `$classroom->subjects()->sync($ids)` before `$classroom->save()` throws or silently no-ops, because the pivot relation needs the classroom's primary key.
**Why it happens:** It's tempting to build the classroom and sync its subjects as if it were one atomic "create with relations" call, but Eloquent's `belongsToMany` relations require the owning model to already exist.
**How to avoid:** Two-step store: `$classroom = Classroom::create($request->safe()->only('name'))`, *then* `$classroom->subjects()->sync($request->validated('subject_ids', []))` — same pattern as Pattern 3's `update()`, just with an explicit `create()` first.
**Warning signs:** A classroom created via the "create" form has a `name` but no linked subjects, even though the create form included a subject multi-select.

### Pitfall 5: Non-sequential/blank option rows breaking the `min:2` count

**What goes wrong:** If the lecturer adds 3 rows, deletes the middle one client-side, and Alpine's `splice()` correctly reindexes the array before submit, the POST body is fine — but if a row is left with an empty `body` (added, never filled in, not removed), the server sees 3 "options" but only 2 meaningfully filled, and `min:2` on the raw array passes even though only 1 usable option exists.
**Why it happens:** `array|min:2` on the `options` field only counts array entries, not non-blank ones.
**How to avoid:** Add `options.*.body` as `required_with:options|string` (already in Pattern 1's rules) so a blank row fails validation with a clear per-row error, rather than silently passing as a "valid" 2-option question with an empty label.
**Warning signs:** A published exam containing an MCQ question with a visibly blank option in the student-facing view (Phase 4) — a defect that traces back to a validation gap here.

## Code Examples

### Question type validation using the native backed enum

```php
// Source: Laravel 11.x official validation docs — Rule::enum()
use App\Enums\QuestionType;
use Illuminate\Validation\Rule;

'type' => ['required', Rule::enum(QuestionType::class)],
```
This validates against the enum's exact backing values (`mcq`/`open`) and stays correct automatically if a case is ever renamed — no parallel `in:mcq,open` string list to keep in sync with `App\Enums\QuestionType`.

### Points validation

```php
'points' => ['required', 'integer', 'min:1'], // D-08: "points is a positive integer, default 1"
```
Default of 1 is applied at the model/factory level (`questions.points` already defaults to `1` in the Phase 1 migration — `unsignedInteger('points')->default(1)`), so the Form Request only needs to reject invalid overrides, not supply the default itself.

### Publishing action

```php
// app/Http/Controllers/Lecturer/ExamController.php
public function publish(Exam $exam)
{
    $exam->update(['is_published' => true]);

    return back()->with('status', 'Exam published.');
}

public function unpublish(Exam $exam)
{
    $exam->update(['is_published' => false]); // D-06: reversible, no attempts exist yet

    return back()->with('status', 'Exam moved back to draft.');
}
```
Two explicit actions (not a single `PATCH` toggling based on current state) make the route list self-documenting and each independently authorizable if a future phase needs to restrict unpublishing differently from publishing.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| CLS-01 | Lecturer can create, edit, and delete classrooms | Standard resource CRUD — `Lecturer\ClassroomController`, no non-trivial pattern; see Recommended Project Structure. |
| CLS-02 | Lecturer can create, edit, and delete subjects | Standard resource CRUD — `Lecturer\SubjectController`, identical shape to CLS-01. |
| CLS-03 | Lecturer can associate multiple subjects with a classroom | Pattern 3 (`sync()` on the `classroom_subject` pivot) + Pitfall 4 (create-then-sync ordering). |
| CLS-04 | Lecturer can assign a Student to a classroom | Pattern 3 (plain `classroom_id` FK update, not a pivot — corrects the "attach/detach" framing in CONTEXT.md D-04 to its actual Eloquent shape). |
| EXM-01 | Lecturer can create an exam belonging to a subject with title + time limit | Standard resource CRUD — `Lecturer\ExamController`, fields already exist on `exams` (Phase 1). |
| EXM-02 | Lecturer can add MCQ questions with multiple options, exactly one correct | Pattern 1 (radio-group UI) + Pattern 2 (delete-and-recreate on update) + Pitfalls 1, 2, 5 — this is the phase's core research depth. |
| EXM-03 | Lecturer can add open-text questions | Same `ExamQuestionController`/Form Request as EXM-02, `type=open` branch skips the `options`/`correct_option` rules entirely (`required_if:type,mcq` naturally excludes them). |
| EXM-04 | Lecturer can set a per-question point value (default 1) | Code Examples §"Points validation"; default already at the DB layer from Phase 1. |
| EXM-05 | Lecturer can edit/delete exam + questions while unpublished | Pattern 1's Form Request `authorize()` gate + Pitfall 3 (must cover every mutating endpoint, not just the exam's own). |
| EXM-06 | Lecturer can publish an exam (draft vs published) | Code Examples §"Publishing action". |
</phase_requirements>

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit ^11.0.1 (installed, confirmed via `composer.json`) |
| Config file | `phpunit.xml` — `DB_CONNECTION`/`DB_DATABASE` overrides are commented out, so `RefreshDatabase` runs against the **live MySQL `yp-student-exam` database** configured in `.env`, not an isolated sqlite/in-memory DB. Confirmed by running `php artisan test --filter=RoleMiddlewareTest` during this research session — 4/4 passed against the live DB in ~3.5s. |
| Quick run command | `php artisan test --filter=<TestClassName>` |
| Full suite command | `php artisan test` |

**Implication for this phase's tests:** every `RefreshDatabase` test truncates and reseeds the live dev database on each run. This is consistent with the existing Phase 1 tests (`RoleMiddlewareTest`, `DomainSchemaTest`, `TestAccountSeederTest`) and requires no new configuration — just be aware any manually-entered dev data in `yp-student-exam` is wiped by `php artisan test`.

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CLS-01 | Lecturer creates/edits/deletes a classroom | feature | `php artisan test --filter=ClassroomControllerTest` | ❌ Wave 0 |
| CLS-02 | Lecturer creates/edits/deletes a subject | feature | `php artisan test --filter=SubjectControllerTest` | ❌ Wave 0 |
| CLS-03 | Lecturer links/unlinks subjects on a classroom (pivot sync) | feature | `php artisan test --filter=ClassroomSubjectLinkageTest` | ❌ Wave 0 |
| CLS-04 | Lecturer assigns/unassigns a student to/from a classroom | feature | `php artisan test --filter=ClassroomRosterTest` | ❌ Wave 0 |
| EXM-01 | Lecturer creates an exam with subject/title/duration | feature | `php artisan test --filter=ExamControllerTest` | ❌ Wave 0 |
| EXM-02 | MCQ question with ≥2 options, exactly one correct — happy path + zero/multiple/blank-option rejection | feature | `php artisan test --filter=ExamQuestionMcqTest` | ❌ Wave 0 |
| EXM-03 | Open-text question created with no options | feature | `php artisan test --filter=ExamQuestionOpenTest` | ❌ Wave 0 |
| EXM-04 | Points default to 1; custom point value persists; `points<1` rejected | feature | `php artisan test --filter=ExamQuestionMcqTest` (shared with EXM-02) or dedicated case | ❌ Wave 0 |
| EXM-05 | Edit/delete of exam and its questions blocked once `is_published=true`; allowed while draft | feature | `php artisan test --filter=ExamPublishedEditGateTest` | ❌ Wave 0 |
| EXM-06 | Publish flips `is_published` true; unpublish flips it back (D-06 reversibility) | feature | `php artisan test --filter=ExamPublishTest` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --filter=<TestClassName>` for the controller/Form Request just touched.
- **Per wave merge:** `php artisan test` (full suite — currently 31+ tests from Phase 1, growing with this phase's additions).
- **Phase gate:** Full suite green before `/gsd-verify-work`.

### Wave 0 Gaps

- [ ] `database/factories/SubjectFactory.php` — does not exist yet; needed for exam/question tests.
- [ ] `database/factories/ExamFactory.php` — does not exist yet; needs a `subject_id` + `created_by` relationship and an `is_published` state (`->published()`).
- [ ] `database/factories/QuestionFactory.php` — does not exist yet; needs `mcq()`/`open()` states, and an `mcq()` state should ideally accept a "correct index" or use an `afterCreating` callback to attach `Option` rows with exactly one `is_correct`.
- [ ] `database/factories/OptionFactory.php` — does not exist yet.
- [ ] `tests/Feature/Lecturer/` directory — does not exist yet; all feature tests listed above belong here, mirroring the `Controllers/Lecturer` namespace.
- [ ] Optional but recommended: add `lecturer()`/`student()` state methods to the existing `UserFactory` (currently tests construct role directly via `User::factory()->create(['role' => Role::Student])`, which works but is more verbose than a named state — see `tests/Feature/RoleMiddlewareTest.php` for the current pattern). Not a hard gap since the existing pattern is functional; a convenience improvement only.

None of the above test *infrastructure* exists yet (Phase 1 built `ClassroomFactory`/`UserFactory` only) — the planner must schedule these as Wave 0 tasks before the first feature test that depends on them.

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No (unchanged from Phase 1/Breeze) | — |
| V3 Session Management | No (unchanged from Phase 1/Breeze) | — |
| V4 Access Control | Yes | Route-group `role:lecturer` middleware (Phase 1, reused unchanged) gates the whole authoring surface; the new phase-specific concern is the **draft/published mutation gate** (D-06) — enforced via Form Request `authorize()` per Pattern 1, not a new middleware class, since it's a state check not a role check. |
| V5 Input Validation | Yes | Form Requests per action (`StoreExamRequest`, `StoreQuestionRequest`, etc.) — see Pattern 1 for the array/wildcard + `after()` shape; native Laravel validation, no external validation library. |
| V6 Cryptography | No | Not touched this phase. |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Mass assignment of `role`/`classroom_id`/`created_by` via a generic form field an attacker adds to the POST body (CWE-915) | Tampering / Elevation of Privilege | Never accept these fields as raw `$request->all()`/`$request->validated()` passthrough on any of this phase's forms; `created_by` is set server-side from `auth()->id()`, `classroom_id` is only ever written by the dedicated roster action (constrained to `role=student` targets, per Pattern 3's example), never by a generic `User::update($request->all())`. This is the same invariant Phase 1 already documented on `User::$fillable` (T-01-02-MA) — this phase must not create a new bypass of it. |
| A lecturer (or a replayed/forged request) mutating a published exam's questions/options after the fact, bypassing the "draft only" UI affordance | Tampering | Server-side `is_published` re-check on every mutating action (Pattern 1's `authorize()`, applied consistently per Pitfall 3) — the Blade UI hiding edit buttons for published exams is UX only, never the enforcement point (same principle as `research/ARCHITECTURE.md` Anti-Pattern 1/2). |
| Malformed/adversarial `correct_option` or `options` array (out-of-range index, zero options, all-blank option bodies) crafted to create a broken MCQ question that later confuses grading (Phase 5) | Tampering | Form Request `after()` hook (Pattern 1) rejects out-of-range/missing `correct_option`; `options.*.body` required-with validation (Pitfall 5) rejects blank rows; `min:2` rejects under-populated option sets. |
| IDOR on `ClassroomRosterController@destroy`/`store` — a crafted `student_id` targeting a user in a different classroom, or a non-student `user_id` | Tampering / Elevation of Privilege | `AssignStudentRequest` (or an inline query constraint, as shown in Pattern 3) scopes the target user to `role=student`; the `destroy` (unassign) example explicitly checks `$student->classroom_id === $classroom->id` before clearing it (`abort_unless`), preventing an unassign call from clearing an unrelated student's classroom via a mismatched route pair. Note: per D-09, cross-*lecturer* ownership scoping is explicitly out of scope this phase (any lecturer manages any resource) — this threat is about cross-*student* targeting, which is a distinct, still-in-scope concern. |

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| `Rule::in(['mcq','open'])` string-list validation | `Rule::enum(QuestionType::class)` validating against a native PHP backed enum | Laravel 10 (enum validation rule added), still current in 11.x | Keeps the Form Request's allowed values in lockstep with `App\Enums\QuestionType` automatically — no risk of the two drifting apart. |
| `$casts` property for enum casting | `casts()` method | Laravel 11 convention (already used throughout this project's models per Phase 1's summary) | This phase's new Form Requests/controllers should follow the same convention already established — no `$casts` property should be introduced anywhere new. |

**Deprecated/outdated:** Nothing specific to this phase is deprecated — Laravel 11's validation, Eloquent, and Form Request APIs used throughout are all current.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | The Alpine "delete-and-recreate options on update" approach (Pattern 2) is safe because no `Attempt`/`Answer` rows can exist while `is_published=false` and this phase never creates attempt-taking flows | Pattern 2 | LOW — this is directly entailed by D-06's own text ("no attempts exist until Phase 4") plus this phase's explicit non-scope of attempt-taking; if a future phase somehow allowed attempts against unpublished exams, this pattern would need revisiting (already flagged in Pattern 2's "When to use"). |
| A2 | Using the option's 0-based array *position* (not its DB `id`) as the value carried by the `correct_option` radio, uniformly for both create and edit forms | Pattern 1 | LOW — this is a UI/Form-Request design choice, not a locked decision; the planner/executor could instead key by DB id for existing rows and a placeholder for new ones, at the cost of a slightly more complex Alpine component. Position-based is recommended for its simplicity and because it lets one Blade partial serve both create and edit. |
| A3 | `subjects.name` has no `unique` constraint in the Phase 1 migration (only `subjects.code` is unique+nullable) — CONTEXT.md D-02 says "keep them unique per entity... may relax if it complicates seeding," and this research assumes app-level uniqueness validation (a `unique:subjects,name` Form Request rule) is sufficient without a new migration | Standard Stack / Package Legitimacy Audit note | LOW — confirmed directly from the Phase 1 migration file (`database/migrations/2026_07_15_100002_create_subjects_table.php`), not assumed from training data; flagged as an assumption only because whether to *add* a DB-level unique constraint via a new migration is a planner discretion call this research does not make for them. |

## Open Questions

1. **Should publishing an exam require at least one question to exist?**
   - What we know: Neither REQUIREMENTS.md (EXM-06) nor CONTEXT.md's D-06 mandates a minimum question count before publishing.
   - What's unclear: An exam with zero questions could be published, which is harmless within this phase's scope (no student view exists yet) but might be a papercut once Phase 3 assigns it to a classroom.
   - Recommendation: Leave unconstrained for this phase (matches the locked decisions exactly); if desired, the planner can add a soft UI warning (not a hard validation block) without contradicting anything locked.

2. **Exact route naming for the publish/unpublish actions.**
   - What we know: D-01 locks the controller namespace and `lecturer.` route-name prefix; nothing locks whether publish/unpublish are separate named routes (`lecturer.exams.publish`/`lecturer.exams.unpublish`) or a single toggle endpoint.
   - What's unclear: Neither approach is wrong; CONTEXT.md's "Claude's Discretion" section explicitly leaves "exact route/view file layout" to the planner/executor.
   - Recommendation: Two explicit named routes (as in Code Examples §"Publishing action") — clearer intent in route lists and easier to independently test.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Runtime | ✓ | 8.2.32 | — |
| Composer | Dependency management | ✓ | 2.8.2 | — |
| Laravel Framework | App framework | ✓ | 11.55.0 | — |
| MySQL (`yp-student-exam` via Herd) | Persistence + test DB (RefreshDatabase runs against it directly, no sqlite fallback configured) | ✓ (confirmed via a passing `php artisan test` run against it during this research session) | 8.x | — |

**Missing dependencies with no fallback:** None — this phase adds no new external dependency.
**Missing dependencies with fallback:** None.

## Sources

### Primary (HIGH confidence)
- Local environment (`php --version`, `php artisan --version`, `composer --version`, `php artisan test --filter=RoleMiddlewareTest`) — confirmed directly during this research session, 2026-07-15.
- `app/Models/Exam.php`, `Question.php`, `Option.php`, `Classroom.php`, `Subject.php`, `User.php` — read directly from the Phase 1 codebase; every relationship/column name used in this document is taken from these files, not assumed.
- `database/migrations/2026_07_15_1000{01,02,04,05,06,07}_*.php` — read directly; confirms exact column types/constraints (`points` default 1, `is_correct` default false, `classroom_subject` unique pivot, `subjects.name` not unique).

### Secondary (MEDIUM confidence)
- [Laravel 11.x Validation docs](https://laravel.com/docs/11.x/validation) — array/wildcard validation, `after()` hook, `Rule::enum()`, `boolean`/`distinct`/`min` rules — fetched directly via WebFetch during this session `[CITED: laravel.com/docs/11.x/validation]`.
- [Alpine.js `x-for` directive docs](https://alpinejs.dev/directives/for) — `<template>` + `:key` requirement, key-by-index pitfall — fetched directly via WebFetch during this session `[CITED: alpinejs.dev/directives/for]`.
- WebSearch cross-check on Alpine.js dynamic add/remove form-row patterns (Medium, CodePen, Laracasts community writeups) — consistent across multiple independent sources on the `splice()`/stable-key pattern `[CITED: multiple community sources, cross-checked against official Alpine docs]`.
- `.planning/research/ARCHITECTURE.md` and `.planning/research/STACK.md` (this project's own prior research, PROJECT.md-embedded) — reused for the "don't hand-roll a state machine for a 2-3 state lifecycle" and "never trust the client alone" principles, both directly applicable to this phase's draft/published gate and MCQ validation.

### Tertiary (LOW confidence)
- None used as load-bearing claims — all Laravel/Alpine API specifics were confirmed against official docs (Secondary tier above), and all schema specifics were confirmed against the actual codebase (Primary tier above).

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages, all versions confirmed against the running local environment.
- Architecture: HIGH — every pattern is either directly locked by CONTEXT.md's D-01..D-10 or derived from official Laravel 11/Alpine.js documentation fetched and cited during this session.
- Pitfalls: HIGH — Pitfalls 1 and 2 draw from official Alpine.js docs and this project's own established "never trust the client" principle (`research/ARCHITECTURE.md`); Pitfalls 3-5 are derived directly from the locked decisions (D-06, D-08) plus the concrete Phase 1 schema, not speculative.

**Research date:** 2026-07-15
**Valid until:** 2026-08-14 (30 days — stable framework-native patterns, no fast-moving dependencies)
</content>

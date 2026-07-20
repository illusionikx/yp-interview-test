# Architecture Research — v3.0 Workflow Restructure & UX Polish

**Domain:** Subsequent-milestone integration analysis (Laravel 11 + Breeze exam portal)
**Researched:** 2026-07-17
**Confidence:** HIGH (all findings verified against the real codebase; one external claim — Dusk/RefreshDatabase incompatibility — verified against official Laravel 11.x docs)

This is not a "what exists in the ecosystem" research doc — v3 introduces no new runtime dependencies except Laravel Dusk (testing-only). It is an **integration map**: how the seven v3.md changes land on the real routes/models/policies/controllers that already ship in this repo, what breaks, what's genuinely new, and in what order it's safe to build.

---

## 1. Navigation restructure — route-by-route mapping

v3's tree:

```
login
├── dashboard
├── class enrollment (student only)
├── subject list (+CRUD lecturer)
├── subject and class management (lecturer only)
│   ├── class and student management
│   └── exam management
│       ├── exam editor
│       └── grading
└── class (student only)
    └── exam list
        └── take exam
```

### What "navbar might be unnecessary" actually changes

Today `resources/views/layouts/navigation.blade.php` is a **flat top-level menu**: Subjects / Sections / Exams / Help (lecturer) and Enroll / My Exams / Help (student), each an independent top-level destination with no hierarchy. v3's tree makes navigation **hierarchical and in-page**: e.g. "grading" is only reachable by drilling Subject → Class management → Exams tab → one exam → Grading, not from a persistent top nav item.

Given the orchestrator's decision to keep a slim top bar (dark toggle + help button), the concrete change is:
- **Remove** the role-scoped link list from `navigation.blade.php` (lines 14–46, 137–160) — Subjects/Sections/Exams/Enroll/My Exams/Help links leave the navbar entirely.
- **Keep** the wordmark (relabel to "Online Examination Portal" per v3's official-name requirement, subtitle "for Yayasan Peneraju Technical Assessment"), the dark-mode toggle (unchanged Alpine code, lines 51–68/107–122), and the user dropdown.
- **Add** a help button next to the dark-mode toggle (v3 explicit ask), pointing at the new wiki-style manual root instead of the current linear `lecturer.help.show`/`student.help.show`.
- Every other destination in the tree becomes an **in-page action** (card link, tab, breadcrumb "back" button with clear destination text — v3's "change link to button to go back with clear text where will it send you").

### Existing routes reused as-is (no controller change needed)

| v3 tree node | Existing route(s) | File |
|---|---|---|
| login | Breeze `routes/auth.php` | unchanged |
| dashboard (redirect shell) | `dashboard` → `lecturer.home` / `student.home` | `app/Http/Controllers/DashboardController.php` |
| take exam | `student.attempts.show/answer/submit/submitted` | `app/Http/Controllers/Student/AttemptController.php` |
| exam editor → questions | `lecturer.exams.questions.store/edit/update/destroy` | `app/Http/Controllers/Lecturer/ExamQuestionController.php` |
| grading | `lecturer.results.index/show`, `lecturer.attempts.answers.grade` | `app/Http/Controllers/Lecturer/ResultController.php`, `AnswerGradeController.php` |
| class and student management (roster) | `lecturer.sections.show` (roster, ENR-07 reject action) | `app/Http/Controllers/Lecturer/SectionController.php` |

### Routes that need new grouping/scoping (structural, not cosmetic)

1. **`lecturer.subjects.show` does not exist today** — `Route::resource('subjects', SubjectController::class)->except(['show'])` in `routes/lecturer.php:22`. v3's "subject and class management" hub (reached from a subject-list row action) requires adding `show`, rendering a **two-tab page** (classes / exams) scoped to that one subject. This is new.
2. **`lecturer.sections.index` and `lecturer.exams.index` are currently global** — `SectionController::index()` lists every section across every subject the lecturer manages (`routes/lecturer.php:23`, filtered only by `subject.lecturers`), and `ExamController::index()` lists **every exam in the system**, unscoped by lecturer at all (`Exam::with('subject')->orderBy('title')->get()` — `app/Http/Controllers/Lecturer/ExamController.php:21`, no `subject.lecturers` filter, a pre-existing scope gap this milestone should close in passing). v3's "classes tab" / "exams tab" live *inside* the per-subject hub from point 1, so both listings need a `?subject=` scope (or nest under `subjects/{subject}`) rather than being top-level destinations.
3. **Student "subject list" and "class enrollment" are two different data sources today collapsed into one controller.** See §1a below — this is the least obvious mapping and needs its own explanation.
4. **Student "class" page (subject detail + exam list, not-yet-open/opened/closed + taken/graded markers) does not exist today.** The closest existing view is `student.exams.index` (`app/Http/Controllers/Student/ExamController.php:20`), which is a **flat, cross-subject** list of every visible exam. v3 wants exams grouped *per enrolled class*, reached by drilling from the subject list. This is a new controller/view, but it wraps the exact same `Exam::visibleTo()` predicate the existing `student.exams.index`/`ExamPolicy::takeable()` already use — no new authorization logic, just a different query shape (scoped to one subject/section instead of all of them).

### §1a — the student "subject list" vs "class enrollment" split (important, easy to get wrong)

Today, `Student\SubjectBrowseController` (`app/Http/Controllers/Student/SubjectBrowseController.php`) does **both jobs at once**:
- `index()` lists **every** subject that has at least one section (`Subject::has('sections')`) — i.e. a catalog for discovery, not "my subjects."
- `show()` lists that subject's sections with live capacity/window state and an Enroll/Withdraw button — the funnel.

v3.md's Subject list section describes something narrower for students: *"subject grouped by semester... action to go to class page... option to hide/unhide past semester"* plus, separately, *"button to enroll class."* Read literally, v3 wants:

- **"Subject list"** = subjects the student is **already enrolled in**, grouped by semester (derived from `Section::year`/`semester` via the enrollment), each row linking to the new "Class" page (§ above) — a **new, different query** (`Subject::whereHas('sections.enrollments', fn($q)=>$q->where('user_id',$id)->where('status', Enrolled))`), not the current catalog query.
- **"Class enrollment"** = the existing `SubjectBrowseController::index()`/`show()` catalog-and-enroll funnel, **unchanged in logic**, just relabeled as its own top-level destination reached via the "enroll class" button rather than being the landing subject list.

**Recommendation:** keep `Student\SubjectBrowseController` exactly as-is and re-badge it as "Class enrollment" (rename the Blade copy/route group prefix if desired, e.g. `student.enrollment.*`, but the controller logic doesn't change). Add a **new**, small controller (e.g. `Student\MySubjectsController` or fold into a home-page composite, see §4) for the enrolled-subjects-grouped-by-semester view that v3 calls "subject list."

### Lecturer "subject list" is simpler — mostly unchanged

`Lecturer\SubjectController` (`Route::resource('subjects', ...)->except(['show'])`) already is "all subjects assigned, no grouping, simple CRUD" per v3 — the only gap is the missing `show()` (needed as the entry point into "subject and class management"). Add `show` to the resource; `index`/`create`/`edit`/`store`/`update`/`destroy` stay as-is.

### The "update assignment just sends me to same page" bug — root cause found

`ExamAssignmentController::update()` (`app/Http/Controllers/Lecturer/ExamAssignmentController.php:26`) correctly redirects back to `lecturer.exams.show` with `session('status', 'Section assignment updated.')` — this **is** the same page by design (there's nowhere else to send it). The perceived bug is that `lecturer/exams/show.blade.php` only renders that flash as a small green text line at the very top of a long page (line 10-14 of `exams/show.blade.php`), easily missed after a full-page reload. This is exactly what v3's toaster requirement fixes — wiring the same `session('status')`/`session('error')` keys already used **throughout this codebase** (every controller in this survey uses `->with('status', ...)`/`->with('error', ...)`) into a visible toast component resolves this "bug" without any controller change. Treat it as a symptom of the missing toast system, not a routing defect. Note also: §6 below removes this controller/route entirely, so if §6's exam-visibility change lands first, this "bug" disappears along with the feature it belonged to — sequence-dependent, see Build Order.

---

## 2. Terminology collision: "class" vs `Section` — **keep `Section`, relabel only**

**Decision: do NOT rename `Section` again. Keep the model, table, routes, and code entirely as `Section`/`sections`; change only the UI-facing copy (Blade text, nav labels) to say "Class."**

Rationale, weighing the project's own precedent:

- v2.0 already executed this exact operation once — `classroom` → `section`, in-place migration edit, `migrate:fresh --seed`, documented in `.planning/PROJECT.md` under "Current State (after v2.0)": *"the v1 single-classroom model was replaced in place by subject-scoped `sections`."* The reason that rename happened was a **real domain-model change** (single global classroom → subject-scoped, capacity-bound, semester-numbered section). v3's request is purely a **display-label preference** — the entity itself (subject-scoped, `year-semester-sequence`, capacity, enrollment window) is unchanged. Renaming the model a second time for a label-only reason would touch every FK reference, every factory, every Policy/Request comment, every test in `tests/Feature/Lecturer/SectionControllerTest.php`, `tests/Feature/Student/*`, `tests/Unit/WindowSemanticsTest.php`, etc. — pure churn, zero functional benefit, and it directly recreates the class of risk the project's "edit v1 migrations in place" convention exists to *avoid repeating unnecessarily*.
- The label "Class" is fully achievable in the presentation layer: `Section::name` is already a computed accessor (`app/Models/Section.php:68`, `"{$this->year}-{$this->semester}-{$this->sequence}"`) consumed by Blade — swap the surrounding copy (`__('Section')` → `__('Class')`, page titles, nav labels) without touching the accessor's underlying data or the class name.
- Every doc-comment in the codebase (`ExamPolicy`, `Exam::scopeVisibleTo()`, `SectionController`) references "section" as an established internal vocabulary tied to specific requirement IDs (SEC-01, SEC-03, ENR-07, AVL-02...). Keeping `Section` as the internal name preserves the traceability those comments rely on; a second rename would obsolete all of it.

**What actually changes:** Blade view titles/labels, nav copy, route *names* only if you want prettier URLs (optional, cosmetic, not required — `lecturer.sections.*`/`student.sections.*` can stay as-is with zero user-visible impact since students/lecturers never see raw route names). Recommend leaving route names untouched too, to minimize diff size, and changing only rendered text.

---

## 3. Semester as a first-class concept

**Decision: a small value object, not a table.**

`Section` already stores `year` (int) and `semester` (int, 1 or 2) as plain columns (`database/migrations/2026_07_15_100002_create_sections_table.php`) — the *data* is already first-class. What's missing is the **date-range business rule** (S1 = Sep 1 → last day of Feb next year; S2 = Mar 1 → Jul 31) and a **"current semester" classification** used for dashboard filters and "hide/unhide past semester" toggles.

Add `App\Support\Semester` (mirrors the codebase's existing "live accessor over denormalized column" convention — see `Section::windowStatus()` at `app/Models/Section.php:85` and `Exam::availabilityState()` at `app/Models/Exam.php:129`, both of which are exactly this pattern: computed, not stored):

```php
final class Semester
{
    public function __construct(
        public readonly int $year,
        public readonly int $number, // 1 or 2
    ) {}

    public static function current(): self { /* now()-based */ }
    public static function forDate(Carbon $date): self { /* Sep-Feb=1, Mar-Jul=2 */ }

    public function startsAt(): Carbon { /* 1st of first month */ }
    public function endsAt(): Carbon   { /* last day of last month */ }

    // total-order comparable across the year boundary (S2 2026 < S1 2027)
    public function ordinal(): int { return $this->year * 2 + ($this->number - 1); }

    public function isCurrent(): bool { return $this->ordinal() === self::current()->ordinal(); }
    public function isFuture(): bool  { return $this->ordinal() >  self::current()->ordinal(); }
    public function isPast(): bool    { return $this->ordinal() <  self::current()->ordinal(); }
    public function label(): string  { return "{$this->year} Semester {$this->number}"; }
}
```

**Why derived, not stored (`semesters` table):**
- v3.md states the boundaries as **permanently fixed** ("this info is fixed... semester always starts at 1st day of the first month and ends at the last day of the last month") — there is no configurability requirement, so a table would be a speculative abstraction for a rule that never varies. A table earns its keep only if semester boundaries become admin-editable per year, or if "current semester" ever needs a manual override — neither is in scope.
- `Section.year`/`Section.semester` already exist as the source of truth for *which* semester a section belongs to; `Semester` only wraps *classification* logic (is this year/semester combo current/future/past, what are its calendar bounds) — it has nothing new to persist.
- **What breaks if derived vs. stored:** nothing, as long as the boundary constants (Sep/Feb, Mar/Jul) live in exactly one place (the `Semester` class) and every "current semester" check — dashboard cards, subject-list hide/unhide, "classes assigned this/future semester" — calls through it rather than re-deriving month arithmetic ad hoc. The risk isn't derived-vs-stored, it's **duplicated derivation logic drifting**, same class of bug the project already guards against with `Exam::scopeVisibleTo()`'s single-predicate discipline.

**"This and future semester" needs:** the `ordinal()` total-order comparison above — a naive `year >= currentYear AND semester >= currentSemester` breaks across the year boundary (e.g. it would wrongly exclude S1 2027 when evaluated during S2 2026, since `semester` 1 < 2 even though the year advanced). Any dashboard/list query filtering "this and future" must compare `(year, semester)` as a composite tuple or via a computed `year*2+semester` expression — call this out explicitly to whoever builds the dashboard queries (§4) since it's the one place a naive implementation silently produces a wrong, hard-to-notice-in-a-demo result (only visible once a lecturer has classes spanning a year boundary — exactly the kind of "past data logical to the workflow" the seeder is asked to produce).

---

## 4. Dashboard queries — bounded, N+1-free shapes

### Lecturer cards

**"Total classes assigned for this/future semester":** one `COUNT`, scoped through the existing `subject.lecturers` pivot precedent already used in `SectionController::index()` (`app/Http/Controllers/Lecturer/SectionController.php:24`):

```php
Section::whereHas('subject.lecturers', fn ($q) => $q->whereKey($user->id))
    ->whereRaw('(`year` * 2 + `semester`) >= ?', [Semester::current()->ordinal()])
    ->count();
```

One query, no relation loading, no N+1 — a lecturer's assigned-subject count is small (a handful), and this never touches per-row data.

**"Enrolled vs. seats across all assigned classes" (progress bar):** needs exactly two scalar aggregates, not per-row data:

```php
$sections = Section::whereHas('subject.lecturers', fn ($q) => $q->whereKey($user->id));
$totalSeats    = (clone $sections)->sum('capacity');
$totalEnrolled = Enrollment::where('status', EnrollmentStatus::Enrolled)
    ->whereHas('section', fn ($q) => $q->whereHas('subject.lecturers', fn ($q) => $q->whereKey($user->id)))
    ->count();
```

Both are bounded aggregate queries (2 queries total for the whole card) — no loop over sections in PHP, matching the existing precedent in `Student\SubjectBrowseController::show()` (`withCount(['enrollments as enrolled_total' => ...])`, `app/Http/Controllers/Student/SubjectBrowseController.php:45`) which already established "live `withCount`, never a denormalized counter column."

### Student cards

**"Total subjects enrolled this semester":** one query joining the enrollment pivot to sections, filtered to the current semester and `Enrolled` status, counting distinct subjects (a student can hold at most one active enrollment per subject per semester per the existing ENR-04 "one active section per subject per semester" rule documented in `Student\SubjectBrowseController::show()`'s `$activeTerms` logic — so `COUNT(*)` and `COUNT(DISTINCT subject_id)` are equivalent here, but write it as `DISTINCT` for correctness/self-documentation, not performance):

```php
Enrollment::where('user_id', $user->id)
    ->where('status', EnrollmentStatus::Enrolled)
    ->whereHas('section', fn ($q) => $q
        ->where('year', Semester::current()->year)
        ->where('semester', Semester::current()->number))
    ->count(); // ENR-04 guarantees at most one enrolled section per subject this semester
```

Both dashboards are aggregate-only (`COUNT`/`SUM`), never load a collection to iterate — this is the same shape the codebase already uses everywhere it needs a count (`SubjectBrowseController::show()`'s `withCount`), so there's no new pattern to introduce, just new aggregate targets.

---

## 5. Exam draft↔active + "reset exam submission" — the highest-integrity-risk item in v3

### Is draft/active the same flag?

**Yes — `is_published` is renamed only in UI copy.** No schema change. "Draft" = `is_published: false`, "Active" = `is_published: true`. The `exams.is_published` boolean column, the `Exam::casts()` cast, and every internal reference stay exactly as-is (`app/Models/Exam.php:33`).

### But the *behavior* around that flag must change, and this is the real work

Today the flag is protected by **two guards that v3 explicitly wants removed**:

1. **`unpublish()` refuses to run if attempts exist** (`app/Http/Controllers/Lecturer/ExamController.php:135`: `if ($exam->attempts()->exists()) { return back()->with('status', 'Cannot unpublish...'); }`). v3: *"lecturer can change status of exam from draft to active and back to draft (inactive)"* — stated with no exception. **Recommendation: drop this guard entirely.** Toggling status must never itself touch attempt data (see below) — once that's true, there's no integrity reason left to block the toggle.
2. **The draft-only edit gate blocks editing a published exam at all** — `UpdateExamRequest::authorize()` (`app/Http/Requests/Lecturer/UpdateExamRequest.php:24`, `return ! $this->route('exam')->is_published;`), the identical gate in `StoreQuestionRequest`/`UpdateQuestionRequest::authorize()` (`app/Http/Requests/Lecturer/UpdateQuestionRequest.php:22`), and the inline `abort_if($exam->is_published, 403)` in `ExamQuestionController::destroy()` (`app/Http/Controllers/Lecturer/ExamQuestionController.php:149`) and `ExamController::destroy()` (line 105). Today it is **impossible** to edit an active exam's questions/details through the app at all — v3's *"when changes is saved, all previous student attempt are cancelled. if any student attempted previously, warning will pop up"* only makes sense if editing an active exam becomes possible. **This is a genuine behavior change, not UI polish**: remove the `is_published` check from all three `authorize()`/`abort_if` call sites; editing is now always allowed regardless of status.

### What replaces the removed protection

The old gate existed to stop a lecturer from silently invalidating already-graded scores by changing the answer key underneath students. v3 replaces "prevent the edit" with **"destroy the affected attempts, with a warning"** — two distinct trigger points that must converge on the exact same destructive operation:

- **Explicit: "reset exam submission" button**, exam-wide (v3.md's phrasing — "add action to reset exam submission with warning" — sits in the exams-tab context, not per-student, so build it as *reset every attempt on this exam*, not a per-student reset; flag per-student reset as an out-of-scope follow-up if finer granularity turns out to be wanted).
- **Implicit: saving the exam editor** when `$exam->attempts()->exists()` — same destructive effect, gated behind a confirm-step warning in the UI before the save POST fires (client-side confirm is UX only; the server doesn't need a separate "are you sure" flag, since the destructive action is idempotent-safe either way).

Both must call **one shared method**, e.g. `Exam::resetAttempts(): void` or a small `AttemptResetter` service (mirroring the existing `AttemptGrader` service precedent — grading logic already lives in one explicit, single-call-site service per this codebase's stated convention: *"An explicit service, never an Eloquent model event/observer"*, `app/Services/AttemptGrader.php:9-15`). Do not duplicate the delete logic between the editor-save controller action and the reset-button controller action.

### Integrity constraints and the race against the timer

- **`attempts.unique(exam_id, user_id)`** (`database/migrations/2026_07_15_100009_create_attempts_table.php:25`) already makes "delete then let the student start fresh" safe — once the row is gone, `AttemptController::store()`'s `firstOrCreate` (`app/Http/Controllers/Student/AttemptController.php:77`) simply creates a new row with no unique-constraint conflict. **Deleting `Attempt` rows cascades to `Answer` rows automatically** via the existing `answers.attempt_id` FK (`->cascadeOnDelete()`, `database/migrations/2026_07_15_100010_create_answers_table.php:16`) — no manual answer cleanup needed.
- **The race that must be handled:** `Attempt::lockAndFinalize()` (`app/Models/Attempt.php:137-175`) re-reads the row with `lockForUpdate()->first()` and then unconditionally does `$locked->setRelation(...)`/`$locked->update(...)` — **it never null-checks `$locked`**. Today that's safe because nothing ever deletes an `in_progress` attempt out from under a running request. v3's reset/re-save flow is the **first** code path that does exactly that. Sequence: a student's autosave (`AttemptController::answer()`) or auto-submit is mid-flight, has resolved `$attempt` via route-model binding, and is about to call `finalizeIfExpired()`/`finalize()` → `lockAndFinalize()` — concurrently, a lecturer's reset/save runs `Attempt::where('exam_id', $exam->id)->delete()` inside its own transaction. Whichever transaction's lock-acquisition loses the race blocks until the other commits; if the delete wins, the student's `lockAndFinalize()` call then executes `self::whereKey($this->id)->lockForUpdate()->first()` against a **now-deleted row**, returning `null`, and `$locked->setRelation(...)` on line 141 is a **null-pointer crash**.
- **Required fix (flag for the roadmapper as an explicit task inside this phase, not an incidental bug):** add a null-guard to `lockAndFinalize()` — if `$locked` is `null`, treat it as "this attempt no longer exists" and short-circuit to a safe no-op/false return. `AttemptController::answer()` (`app/Http/Controllers/Student/AttemptController.php:172`) also reads `$locked = Attempt::whereKey(...)->lockForUpdate()->first();` **directly**, not through `lockAndFinalize()`, and needs the same null-check before touching `$locked->status`.
- **Delete must run inside a transaction that takes the same row lock the timer path takes**, so it serializes correctly rather than racing at the SQL level: `DB::transaction(fn () => Attempt::where('exam_id', $exam->id)->lockForUpdate()->delete());` — MySQL/InnoDB will block a concurrent `SELECT ... FOR UPDATE` (what `lockAndFinalize()` issues) against the same rows until this transaction commits, at which point the row is simply gone and the null-guard above takes over cleanly.
- **Graded results:** once attempts are deleted, `ResultController::index()`/`show()` (`app/Http/Controllers/Lecturer/ResultController.php`) naturally reflect zero attempts — no orphan-handling code needed, the FK cascade already guarantees referential integrity.

---

## 6. "All students enrolled automatically assigned to all active exams in this list" — the exam-visibility model changes

This is the single largest structural change in v3 and needs to be flagged explicitly as an **assumption surfaced for override** (auto-mode decision, not a directly-confirmed requirement — v3.md's own closing line is "let me know if there is any suggestion," inviting exactly this kind of call).

### Today

Exam→section assignment is **explicit and manual**: the `exam_section` pivot table (`database/migrations/2026_07_15_100008_create_exam_section_table.php`), `Exam::sections(): BelongsToMany` (`app/Models/Exam.php:60`), and a dedicated checkbox-matrix UI (`ExamAssignmentController::update()` + `AssignExamRequest` + the "Assign to sections" form in `lecturer/exams/show.blade.php:111-142`) let a lecturer assign one exam to an arbitrary subset of that subject's sections. `Exam::scopeVisibleTo()` (`app/Models/Exam.php:97-105`) is keyed off this: visible only if `is_published` **and** enrolled in a section the exam is explicitly assigned to.

### What v3 asks for

*"All student enrolled automatically assigned to all active exam in this list"* — in context (inside "Class management → exams tab," which per §1 lives at the **subject** level, not the section level) reads as: an exam belongs to a subject; once **Active**, it is automatically visible to every student holding an `Enrolled` enrollment in **any** section of that subject. No per-section curation step.

### Recommendation: drop `exam_section`, make visibility subject-driven

Rewrite `Exam::scopeVisibleTo()` from *"enrolled in a section this exam is assigned to"* to *"enrolled in any section of this exam's subject"*:

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

This is a **strict simplification**: it removes an entire controller (`ExamAssignmentController`), Form Request (`AssignExamRequest`), pivot table + migration, model relation (`Exam::sections()`/`Section::exams()`), route (`lecturer.exams.assignment.update`), and UI block (the checkbox matrix), while matching v3's stated "automatic" behavior exactly and staying consistent with "exam versioning omitted, too complex" — v3 is asking for **less** manual configuration surface, not more.

### Consequences to trace through (why this is high-risk/high-churn, not just a one-line scope edit)

- **`Student\ExamController::show()`'s `$enrolledSection` derivation** (`app/Http/Controllers/Student/ExamController.php:87-92`) currently finds "the section both assigned to this exam and the student is enrolled in" — with no assignment pivot left, this becomes "the section of this exam's subject the student is enrolled in," a materially different (simpler) query.
- **`AttemptController::store()`'s `$alreadyStarted`/`takeable()` gate** is unaffected in shape (still delegates to `Exam::visibleTo()`), but its *meaning* changes — takeable now means "enrolled in the subject," not "enrolled in an assigned section."
- **Every test that asserts assignment-driven visibility must be rewritten**, not just re-pointed: `tests/Feature/Lecturer/ExamAssignmentTest.php` (the entire test class goes away with the controller), `tests/Feature/Student/ExamVisibilityRegressionTest.php`, `tests/Feature/Student/ExamIndexTest.php`, `tests/Feature/Student/ExamAccessTest.php`, `tests/Feature/Lecturer/ExamAvailabilityTest.php` — all currently exercise the assignment pivot as part of their visibility setup and need to instead just enroll the student in *a* section of the subject.
- **Migration is an in-place edit** per this project's established convention (`.planning/PROJECT.md`: "edit original v1 migrations in place... clean break, `migrate:fresh --seed`, no alter migrations") — drop `2026_07_15_100008_create_exam_section_table.php` outright (or remove it from the migration set) since there is no production data to preserve, consistent with how v2.0 handled `classroom_subject`.
- **Grading page's "show class details"** (v3.md, Grading section) needs re-deriving too — "which class is this exam's grading for" no longer has a single assigned-section answer; it becomes "which sections of this subject have enrolled students with attempts," a small aggregate query over `Attempt.user → Enrollment → Section`, not a stored assignment.

**Because `scopeVisibleTo()` is the single predicate consumed by the student exam list, `ExamPolicy::takeable()`, and (after §1's restructure) the new "Class" page's exam list, this change must land and be fully re-tested *before* any of the navigation-restructure work in §1 that builds UI on top of exam visibility.** Treat it as its own phase, early, not a drive-by edit inside the nav-restructure phase.

---

## 7. Dusk browser tests alongside the existing PHPUnit/MySQL suite

Verified against Laravel 11.x's official Dusk documentation (HIGH confidence — direct docs, cross-checked).

- **Install:** `composer require laravel/dusk --dev` (not yet in `composer.json` — confirmed absent from both `require` and `require-dev`), then `php artisan dusk:install`, which scaffolds `tests/DuskTestCase.php` and a `tests/Browser/` directory — parallel to, not inside, the existing `tests/Feature`/`tests/Unit` split in `phpunit.xml`.
- **Do not run Dusk through the existing `phpunit.xml` `<testsuites>` block.** Dusk tests run via `php artisan dusk`, a separate command/bootstrap from `php artisan test`/`vendor/bin/phpunit`. Leave `phpunit.xml`'s `Unit`/`Feature` suites exactly as they are; Dusk gets its own invocation path, documented separately in the README's "how to run tests" section.
- **`RefreshDatabase` cannot be used for Dusk tests** — Dusk's browser process makes real HTTP requests against a running server; `RefreshDatabase`'s transaction-per-test isolation doesn't survive across a process boundary. Use `DatabaseTruncation` (preferred over `DatabaseMigrations` for speed — migrate once, truncate between tests) on `DuskTestCase`, scoped explicitly: `protected $connectionsToTruncate = ['mysql'];`.
- **Same MySQL database as PHPUnit's Feature suite is acceptable here, but only run sequentially.** This repo's `phpunit.xml` has `DB_CONNECTION`/`DB_DATABASE` overrides commented out (lines 25-26), meaning Feature tests already run `RefreshDatabase`-wrapped transactions directly against the real `.env` MySQL database (`yp-student-exam`) — there is no separate test database configured. For a small graded deliverable this is an acceptable existing pattern, but it means Dusk's `DatabaseTruncation` truncating the same tables **must never run concurrently with** a `php artisan test` invocation, or truncation will race against another suite's open transactions. Document this explicitly in the README rather than trying to solve it with a second database — introducing a second MySQL database purely for Dusk isolation is more setup-reproducibility risk for a clean-clone grading flow than it's worth at this project's scale.
- **Herd-specific wrinkle (not covered by generic Laravel docs):** Dusk's default `.env.dusk.local` assumes `php artisan serve` is the thing being tested against (`APP_URL=http://127.0.0.1:8000`). This project runs under Laravel Herd, which already serves the app at its own hostname (e.g. `https://yp-test.test`) without `artisan serve`. Point `.env.dusk.local`'s `APP_URL` at the Herd hostname instead of spinning up a competing dev server — verify this against the actual Herd site URL when the phase implementing Dusk is built, since it's environment-specific and not discoverable from the repo alone.
- **ChromeDriver is a local-machine dependency** (`php artisan dusk:chrome-driver`) — note in the README as a setup step for anyone cloning the repo to run the browser suite; there is no CI configuration in this repo today (no `.github/workflows/`), so this only affects local reproducibility, not a pipeline.
- **Where Dusk tests should target:** write them last, per page, after that page's v3 UI work lands (§ Build Order below) — writing Dusk assertions against a UI that's still being restructured is wasted churn. `tests/Browser/` structure should mirror `tests/Feature/{Lecturer,Student}/` role split for consistency with the existing test tree.

---

## Integration Points Summary

| Boundary | Change type | Depends on |
|---|---|---|
| `navigation.blade.php` ↔ role-scoped pages | Modified (strip links, add help button) | Route restructure (§1) landing first, or link targets 404 |
| `Exam::scopeVisibleTo()` ↔ student exam list, `ExamPolicy`, dashboard | **Rewritten predicate** (§6) | Nothing — do first among UI-adjacent work |
| `ExamAssignmentController`/`exam_section` ↔ exam authoring UI | **Removed** (§6) | — |
| `UpdateExamRequest`/`UpdateQuestionRequest::authorize()` ↔ exam editor | **Gate removed**, replaced by attempt-destroy warning flow (§5) | `Attempt::lockAndFinalize()` null-guard fix must land first |
| `Attempt::lockAndFinalize()` ↔ timer/autosave/submit | **New null-guard required** (§5) | — do this before any code path can delete an in_progress attempt |
| `Section` model/table ↔ everything | **Unchanged** — relabel only (§2) | — |
| New `App\Support\Semester` ↔ dashboard, subject list, hide/unhide | **New** value object | Nothing — build first, everything else consumes it |
| `SubjectBrowseController` ↔ "class enrollment" | **Unchanged**, relabeled | — |
| New enrolled-subjects-by-semester query ↔ "subject list" (student) | **New** controller | `Semester` value object |
| Toast component ↔ every controller's `session('status'/'error')` flash | **New** shared Blade/Alpine component | Nothing — build early, many phases depend on it existing |
| `laravel/dusk` ↔ `tests/Browser/` | **New**, additive infra | Stable route/view structure per page before writing that page's tests |

---

## Suggested Build Order

**Phase A — Foundations (no UI dependents block on these, but nearly everything else depends on them):**
1. `App\Support\Semester` value object + unit tests (ordinal comparison across year boundary — §3).
2. `Attempt::lockAndFinalize()` null-guard + `AttemptController::answer()`'s direct locked-read null-guard (§5) — a defensive fix required *before* anything can delete an in-progress attempt.
3. Shared toast/alert Blade+Alpine component wired to existing `session('status')`/`session('error')` flash keys (fixes the "update assignment" perceived bug as a side effect, if it still exists after Phase C).

**Phase B — Exam integrity model (must land before exam editor/grading UI work):**
4. Remove draft-only edit gates (`UpdateExamRequest`, `UpdateQuestionRequest`, `StoreQuestionRequest`, `ExamController::destroy()`/`ExamQuestionController::destroy()` inline `abort_if`), remove the `unpublish()` attempts-exist guard, add the shared attempt-reset operation (explicit reset button + implicit editor-save trigger), transactional/locked delete (§5).

**Phase C — Exam visibility model (high-risk, isolate and fully re-test before building UI on top of it):**
5. Drop `exam_section` pivot, rewrite `Exam::scopeVisibleTo()` to subject-level enrollment, remove `ExamAssignmentController`/`AssignExamRequest`/route/UI block, rewrite `Student\ExamController::show()`'s section derivation, rewrite every visibility-dependent test (§6).

**Phase D — Navigation & route restructure (depends on B + C being stable):**
6. Add `lecturer.subjects.show` hub (classes/exams tabs scoped to subject); scope `SectionController::index`/`ExamController::index` per-subject; new student "subject list" (enrolled-by-semester) controller distinct from the unchanged "class enrollment" controller; new student "Class" page (subject detail + per-class exam list) built on the Phase-C `scopeVisibleTo()`.
7. Slim navbar strip-down + in-page hierarchy/back-buttons (can run in parallel with 6 once route names are fixed).

**Phase E — Dashboard (parallel with D, depends only on Phase A):**
8. Lecturer/student dashboard cards + welcome banner, using the Phase-A `Semester` value object and the aggregate query shapes in §4.

**Phase F — Content pages (mostly independent of each other, sequence for convenience):**
9. Login page restyle + pre-login landing page (zero backend dependency — can run anytime, good early/parallel win).
10. Exam editor merge (details+questions tabs, shuffle, reorder, destructive-save warning UI) — depends on Phase B's backend.
11. Grading page enrichment (class+exam context, progress) — depends on Phase C's subject-level visibility for "which classes."
12. Class enrollment page relabeling (near-zero-diff, depends on Phase D route naming only).
13. Take-exam UI overhaul (sticky bar, stepper, 10-min toast, instructions popup) — purely front-end on existing `AttemptController::show()` data, no backend blocker.
14. Wiki-style user manual (replaces linear help pages) — independent content work.

**Phase G — Polish and testing (last):**
15. Dark-mode contrast sweep across all touched views (do last — every earlier phase touches Blade, so sweeping once at the end avoids re-litigating the same views repeatedly).
16. `laravel/dusk` install + `tests/Browser/` suite, written per-page against the now-stable Phase D/F UI (§7) — install the composer dependency/scaffolding early if convenient, but don't write assertions until the page they target is done.
17. Expanded seeder (more lecturers/students, Dr/PhD prefixes for lecturers only, past graded semesters, every status represented, 3-5 more subjects/exams/classes) — genuinely last, since it must seed data shaped by every model/status decision made in Phases A-F (can't seed "every status" if statuses are still in flux).

---

## Sources

- Direct codebase reads (HIGH confidence, ground truth): `routes/student.php`, `routes/lecturer.php`, `routes/web.php`, `app/Models/{Exam,Section,Enrollment,Attempt,Subject,Question}.php`, `app/Policies/{ExamPolicy,AttemptPolicy}.php`, `app/Http/Controllers/{Lecturer,Student}/*.php`, `app/Http/Requests/Lecturer/{UpdateExamRequest,UpdateQuestionRequest}.php`, `app/Services/AttemptGrader.php`, `database/migrations/*`, `resources/views/layouts/navigation.blade.php`, `resources/views/lecturer/exams/{show,edit}.blade.php`, `composer.json`, `phpunit.xml`, `.planning/v3.md`, `.planning/PROJECT.md`.
- https://laravel.com/docs/11.x/dusk — Dusk install, `.env.dusk.local`, `DatabaseMigrations`/`DatabaseTruncation` vs `RefreshDatabase` incompatibility, local dev-server assumptions (HIGH — official docs, confirmed directly).

---
*Architecture research for: Online Examination Portal v3.0 (Workflow Restructure & UX Polish)*
*Researched: 2026-07-17*

# Phase 7: v2.0 Foundation — Admin Theme, Schema Break & Answered-Count Fix - Research

**Researched:** 2026-07-16
**Domain:** Laravel 11 schema-break migration + Eloquent relationship rename + Flowbite/Tailwind admin shell + Alpine.js reactive state fix
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Admin Shell — Layout & Navigation**
- Top navbar with dropdown navigation — NO sidebar (user override of UI-01's "sidebar" phrasing; the governing decision for this phase). Themed with Flowbite navbar + dropdown components.
- Role-based nav grouped into top-bar dropdowns: Lecturer → Subjects, Sections, Exams, Results; Student → Enroll, My Exams, Results.
- Brand: text wordmark "Exam Portal" in the navbar (replaces the Breeze application-logo SVG).
- Content in Flowbite cards below the top bar; keep the existing Breeze `max-w-7xl` centered container (conventional under a top nav) — Claude's discretion on exact width.

**Theme & Status Pills**
- Flowbite default blue accent — no custom Tailwind color config.
- Semantic status pills: Enrolled / Published / Open = green; Draft / Withdrawn / "Opens…" = gray; Rejected / Closed = red; FULL = amber.
- Comfortable density (Flowbite default spacing).
- Dark mode (UI-02, locked): Tailwind `darkMode: 'class'`; sun/moon toggle in the top bar; persists in `localStorage`; defaults to OS preference (`prefers-color-scheme`) when unset. Apply the class pre-paint (inline head script) to avoid a flash.

### Claude's Discretion
- Exact content container width, card padding scale, section-CRUD page placement (nested under subject vs standalone), and demo-data volume in the reseed.

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope. (Enrollment apply/withdraw/reject UI, capacity locking, availability windows, and manuals are already scoped to Phase 8.)
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| UI-01 | Consistent admin-style shell (top navbar + dropdowns per CONTEXT override, card content, status pills) across student/lecturer areas, built on Tailwind + Flowbite | See "Admin Shell" architecture pattern, `x-status-pill` component design, Flowbite install steps |
| UI-02 | Light/dark toggle in top bar, persists in `localStorage`, defaults to OS preference | See "Dark Mode Toggle" pattern with pre-paint inline script |
| FIX-01 | Submit-confirmation "N of M answered" count reflects answers saved during the session, not just page-load state | See "Pitfall: Static Blade Interpolation of a Live Alpine Count" — root cause identified in current code |
| SEC-01 | Subject can have multiple class-sections; each section belongs to exactly one subject, named `year-semester-count` | See "Schema Break" — `sections` table design, computed-name accessor pattern |
| SEC-02 | Lecturer can create/edit/delete a section (capacity, enrollment open/close dates) | See "Section CRUD" pattern, modeled on `ClassroomController`/`SubjectController` precedent |
| SEC-03 | Subject assignable to one or more lecturers; any assigned lecturer manages that subject's sections/enrollments/exams | See "subject_user pivot" design, `AssignLecturerRequest` pattern |
| ENR-08 | Student sees/takes only published exams assigned to a section they are actively enrolled in; list and gate share one rule | See "scopeVisibleTo Rewrite" — the single highest-blast-radius change in this phase |
| DEL-03 | Seeder/factories rewritten for section/enrollment model; `migrate:fresh --seed` produces a working demo; README updated | See "Seeder & Factory Rewrite" and "Runtime State Inventory" |
</phase_requirements>

## Summary

This phase is dominated by one large, mechanical-but-high-blast-radius task — renaming the v1 `Classroom` (shared, many-subjects) entity into a `Section` (subject-scoped, one-subject) entity, and rewiring every consumer of the old `users.classroom_id` FK and `classroom_subject` pivot to the new `enrollments` pivot — bundled with two small, functionally-independent tasks (a Flowbite/dark-mode reskin, and a one-file Alpine reactivity fix). The milestone-level research (`.planning/research/SUMMARY.md`, `PITFALLS.md`) has already done the hard design thinking for the schema slice; this phase-level research narrows that to Phase 7's exact scope (schema + section CRUD + subject-lecturer assignment + the visibility predicate — **not** the enrollment apply/withdraw/reject UI or exam availability windows, which are Phase 8) and adds codebase-verified specifics: the exact 26-file rename sweep, a migration **ordering bug** the milestone research didn't surface (`sections` needs `subject_id` before `subjects` exists in current file order), the exact root cause of FIX-01 (a Blade-interpolated count baked into HTML at page-load, inside a modal that never re-renders), and a verified Flowbite v4.0.2 npm install path for this repo's Tailwind v3 (not v4) setup.

The three slices are genuinely independent in risk profile: the UI shell and FIX-01 touch views only and can be built/tested/committed in any order relative to the schema slice. The schema slice — migration edits, model rename, `scopeVisibleTo()` rewrite, and seeder/factory rewrite — must land as one atomic unit, because Laravel's migration runner tracks completion by filename (not content), so a partial rewrite either crashes `migrate:fresh --seed` (missing table/column) or silently boots against a half-old/half-new schema depending on what's touched. A cross-consumer regression test (list vs. direct-access gate, across enrolled/withdrawn/rejected/never-applied students) is a hard acceptance gate for this slice per STATE.md's blocker note and PITFALLS.md Pitfall 1.

**Primary recommendation:** Sequence Phase 7 as three parallel-safe waves — (A) Flowbite install + admin shell + dark mode + FIX-01 (view-only, no schema dependency), (B) schema/model rename + migration reorder + `scopeVisibleTo()` rewrite + seeder/factory rewrite as one atomic wave with the cross-consumer regression test as its acceptance gate, (C) section CRUD + subject-lecturer assignment controllers/views (depends on B's schema existing, reuses A's Flowbite shell for its views). Do not split B across separate commits/plans that leave the app non-booting in between.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Admin shell / navbar / dropdowns | Browser (Alpine/Blade) | — | Pure presentation; Flowbite's JS behaviors (dropdown toggle) run client-side, no server state |
| Dark mode toggle | Browser (localStorage + inline head script) | — | Must apply pre-paint to avoid FOUC; no server round-trip, no cookie needed (client-only preference) |
| Status pills | Browser (Blade component) | — | Pure display mapping of a status string to CSS classes; no logic |
| Answered-count fix | Browser (Alpine reactive state) | — | The bug is client-side staleness; server already returns correct per-save data, the view just doesn't re-render from it |
| Section schema (`sections`, `subject_id`, capacity, windows) | Database / Migration | API/Backend (Eloquent model) | Structural data; correctness (FK ordering, uniqueness) is a migration-layer concern |
| `subject_user` (lecturer assignment) | Database / Migration | API/Backend (Policy/middleware gate) | Pivot table; the "any assigned lecturer can manage" rule is enforced in the backend (Form Request `authorize()` or Policy), not the DB |
| `enrollments` (status, rejection_reason) | Database / Migration | API/Backend (custom Pivot model) | Schema in Phase 7; the write-path (apply/withdraw/reject) is Phase 8 — Phase 7 only needs the table + a seed-time consumer |
| `Exam::scopeVisibleTo()` | API/Backend | — | The single shared predicate; must live in exactly one place per the existing v1 invariant (this phase's highest-risk change) |
| Section CRUD (create/edit/delete) | API/Backend | Browser (Blade forms) | Standard Laravel resource-controller pattern already established by `ClassroomController`/`SubjectController` |
| Subject→lecturer assignment | API/Backend | Browser (Blade form) | Same pattern as Section CRUD |
| Seeder/factories | Database / Migration | — | Seed-time only; not a runtime request path, but the actual proof that the schema rewrite is complete |

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel | 11.31 (already installed) | Framework | Locked stack, no change |
| Flowbite | 4.0.2 `[VERIFIED: npm registry]` | Blade-markup-compatible component library (navbar, dropdown, modal JS behaviors) on top of Tailwind | Locked decision in CONTEXT.md/UI-SPEC.md; verified via `npm view flowbite version` → `4.0.2`, published 2026-05-13, 456,529 weekly downloads, repo `github.com/themesberg/flowbite`, no `postinstall` script — clean install |
| Tailwind CSS | 3.1.0+ (already installed, project pinned to v3 not v4) | Utility CSS | Already scaffolded; Flowbite's Tailwind-v3-era config (`tailwind.config.js` `plugins: [require('flowbite/plugin')]`, `content` glob including `node_modules/flowbite/**/*.js`) applies here — **do not** follow Flowbite's own current getting-started docs verbatim, they default to the Tailwind v4 `@import`/`@plugin` CSS-first syntax (Laravel 12 default), which does not apply to this Laravel-11/Tailwind-v3 project `[CITED: flowbite.com/docs/getting-started/laravel/ — page explicitly states "Since Laravel 12, the latest version of Tailwind v4 will be installed by default", confirming the doc targets v4]` |
| Alpine.js | 3.4.2+ (already installed) | Reactive client state | Already used for the attempt timer and existing nav; reuse for dark-mode toggle and the FIX-01 reactive answered-count — no new JS framework, per CLAUDE.md |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| *(none — no new Composer packages)* | — | — | Every schema/backend change in this phase (sections, subject_user, enrollments, scopeVisibleTo rewrite) is native Laravel Eloquent — enum-cast Pivot models, `belongsToMany`/`->using()`, `lockForUpdate()` — all direct extensions of patterns already in this codebase (`Role`, `QuestionType`, `Attempt::lockAndFinalize()`) |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Flowbite (Blade markup + vanilla JS, npm) | Flowbite CDN `<script>` tag | Rejected — CONTEXT.md explicitly locks "Build-based, no CDN — Vite pipeline," consistent with the rest of the asset pipeline |
| Storing `sections.name` as a plain string column | Computed accessor from `year`/`semester`/`sequence` columns | **Recommended: computed accessor.** A stored redundant string can drift from its parts; this codebase already prefers live accessors over denormalized columns unless there's a measured performance need (see CLAUDE.md §3 "total_score" precedent) |
| Renaming `Classroom` → `Section` model/table | Keeping the `Classroom` name, just repurposing its meaning | Rejected — SEC-01 changes the entity's cardinality (many-subjects-per-classroom → exactly-one-subject-per-section) and semantics; keeping the old name would mislead every future reader. PITFALLS.md (milestone research) independently reaches the same conclusion ("the `Classroom`→section model rename") |

**Installation:**
```bash
npm install flowbite
```

**Version verification:** Confirmed via `npm view flowbite version` → `4.0.2`, `npm view flowbite scripts.postinstall` → empty (no postinstall script), `npm view flowbite repository.url` → `git+https://github.com/themesberg/flowbite.git`. No Composer packages added.

## Package Legitimacy Audit

| Package | Registry | Age | Downloads | Source Repo | Verdict | Disposition |
|---------|----------|-----|-----------|-------------|---------|-------------|
| flowbite | npm | published 2026-05-13 (this release; project active since 2019) | 456,529/wk | github.com/themesberg/flowbite | OK | Approved |

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

`flowbite` was already a locked decision from CONTEXT.md/UI-SPEC.md (originating from a prior `gsd-ui-researcher` pass), independently re-verified in this session via `gsd-tools query package-legitimacy check` (verdict `OK`) and `npm view` (version, repo, postinstall all clean). No `[ASSUMED]` tag needed for the package identity itself — installation syntax details (exact `tailwind.config.js` plugin wiring for v3) are tagged `[ASSUMED]`/`[CITED]` below since Flowbite's own current docs default to v4 syntax that doesn't directly apply.

## Architecture Patterns

### System Architecture Diagram

```
Browser (student/lecturer)
   |
   |  GET any page
   v
Blade layout (app.blade.php)
   |-- inline <head> script: read localStorage/prefers-color-scheme -> apply .dark class (pre-paint, UI-02)
   |-- navigation.blade.php: Flowbite navbar + role-scoped dropdowns (UI-01)
   |
   v
Route (web.php / lecturer.php / student.php)
   |
   v
Middleware: auth, verified, role:{lecturer|student}
   |
   v
Controller
   |-- Lecturer\SectionController        (SEC-02, new — mirrors ClassroomController)
   |-- Lecturer\SubjectLecturerController (SEC-03, new — subject_user pivot)
   |-- Student\ExamController@index      (ENR-08 — Exam::scopeVisibleTo($user))
   |-- Student\AttemptController@show    (FIX-01 view only, no controller change)
   v
Model layer
   |-- Section (renamed Classroom) --belongsTo--> Subject
   |             --belongsToMany(User, 'enrollments')->using(Enrollment::class)--
   |             --belongsToMany(Exam, 'exam_section')--
   |-- Subject   --belongsToMany(User, 'subject_user') [lecturers]
   |             --hasMany(Section)
   |-- Exam      --belongsToMany(Section, 'exam_section')
   |             --scopeVisibleTo(): is_published AND whereHas('sections.enrollments',
   |                                  user_id=X AND status=Enrolled)   <-- ENR-08, single predicate
   |-- ExamPolicy::takeable() / AttemptPolicy::ownAndTakeable()
   |             --> delegate 100% to Exam::visibleTo()  <-- must NOT re-derive, this is the
   |                                                          invariant the regression test checks
   v
Database (MySQL, yp-student-exam)
   sections (subject_id FK) -- enrollments (section_id, user_id, status) -- users
   subjects -- subject_user (subject_id, user_id) -- users [lecturers]
   exams -- exam_section (exam_id, section_id) -- sections
```

### Recommended Project Structure
```
app/
├── Models/
│   ├── Section.php                 # renamed from Classroom.php
│   ├── Enrollment.php              # NEW — custom Pivot model (extends Illuminate\Database\Eloquent\Relations\Pivot)
│   ├── Subject.php                 # + lecturers() belongsToMany
│   ├── Exam.php                    # scopeVisibleTo() rewritten; classrooms() -> sections()
│   └── User.php                    # classroom()/classroom_id removed; + sections()/subjects() (lecturer) added
├── Enums/
│   └── EnrollmentStatus.php        # NEW — backed enum: Enrolled, Withdrawn, Rejected (mirrors Role/QuestionType)
├── Http/
│   ├── Controllers/Lecturer/
│   │   ├── SectionController.php          # NEW — mirrors ClassroomController's index/create/store/edit/update/destroy
│   │   └── SubjectLecturerController.php  # NEW — assign/unassign, mirrors ClassroomRosterController's store/destroy shape
│   └── Requests/Lecturer/
│       ├── StoreSectionRequest.php / UpdateSectionRequest.php
│       └── AssignLecturerRequest.php
resources/views/
├── layouts/
│   ├── app.blade.php               # rebuilt Flowbite shell + pre-paint dark-mode script
│   └── navigation.blade.php        # rebuilt as Flowbite top-navbar + dropdowns (NO sidebar)
├── components/
│   └── status-pill.blade.php       # NEW — x-status-pill, maps status string -> 4-color palette
├── lecturer/
│   ├── sections/{index,create,edit}.blade.php   # NEW, nested under subject per UI-SPEC discretion
│   └── subjects/{index,create,edit}.blade.php   # reskinned; + lecturer-assignment sub-section
└── student/attempts/show.blade.php # FIX-01 fix only — no new files
database/
├── migrations/2026_07_15_*.php     # edited in place — see "Schema Break" below for exact reordering
├── factories/SectionFactory.php    # renamed from ClassroomFactory.php, + enrollment-aware states
└── seeders/DatabaseSeeder.php      # rewritten per "Seeder & Factory Rewrite"
```

### Pattern 1: `Exam::scopeVisibleTo()` Rewrite (ENR-08) — the single highest-risk change

**What:** The existing scope swaps its data source from a flat `users.classroom_id` FK to a live `enrollments.status` check through the renamed `sections` relation. Structure stays identical — one scope, two consumers (`Student\ExamController@index`, `ExamPolicy::takeable()`/`AttemptPolicy::ownAndTakeable()`), zero policy code changes.

**When to use:** This is the only correct place any exam-visibility logic may live. No other controller, view, or ad hoc query may independently derive "is this exam visible to this student."

**Example (source: `.planning/research/SUMMARY.md`, verified against current `app/Models/Exam.php`):**
```php
// app/Models/Exam.php — BEFORE (v1, current code, verified read this session)
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

// AFTER (v2.0, this phase) — classrooms() relation renamed to sections()
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
Note the explicit `->when()` null-guard from v1 is **no longer needed** — `whereHas` on a relation chain naturally matches zero rows for a student with no enrollments, which is a genuine simplification (per SUMMARY.md), not a silent behavior change: a student with zero `enrollments` rows produces the same "sees nothing" result the old explicit guard produced for `classroom_id === null`.

**Regression test contract (hard acceptance gate, not optional):**
```php
// One parametrized/table-driven test asserting list-inclusion === policy-truth
// for all four enrollment states, per STATE.md blocker + PITFALLS.md Pitfall 1.
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

public static function enrollmentStates(): array
{
    return [
        'enrolled' => ['enrolled', true],
        'withdrawn' => ['withdrawn', false],
        'rejected' => ['rejected', false],
        'never_applied' => ['never_applied', false],
    ];
}
```

### Pattern 2: `subject_user` — Subject-to-Lecturer Assignment (SEC-03)

**What:** Plain `belongsToMany` pivot, no custom Pivot class needed (no extra pivot columns beyond timestamps) — simpler than `enrollments`.
**When to use:** Anywhere "which lecturers may manage this subject's sections/exams" is checked.

```php
// app/Models/Subject.php
public function lecturers(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'subject_user');
}

// app/Models/User.php
public function subjects(): BelongsToMany
{
    return $this->belongsToMany(Subject::class, 'subject_user');
}
```

**Authorization pattern (matches existing "ownership in `authorize()`" convention, e.g. `UpdateExamRequest`):**
```php
// StoreSectionRequest / UpdateSectionRequest authorize()
public function authorize(): bool
{
    $subject = $this->route('subject');

    return $subject->lecturers()->whereKey($this->user()->id)->exists();
}
```
This is a genuine behavior change from the existing D-09 convention ("no per-record ownership, role:lecturer middleware is sole gate") used by `SubjectController`/`ClassroomController` — SEC-03 explicitly requires per-subject ownership now ("any lecturer assigned to it — not only its creator"). Document this divergence in the plan; do not silently copy the D-09 `return true;` pattern for section/subject-assignment-scoped Form Requests.

### Pattern 3: Dark Mode Toggle (UI-02)

**What:** Pre-paint inline `<head>` script (avoids FOUC) + Alpine component for the toggle button + `localStorage` persistence.
**When to use:** `layouts/app.blade.php` head, once.

```html
<!-- resources/views/layouts/app.blade.php, in <head>, before any stylesheet -->
<script>
    if (localStorage.getItem('theme') === 'dark' ||
        (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
</script>
```

```html
<!-- toggle button, in navigation.blade.php -->
<button
    type="button"
    x-data="{ dark: document.documentElement.classList.contains('dark') }"
    x-on:click="
        dark = !dark;
        document.documentElement.classList.toggle('dark', dark);
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    "
    aria-label="Toggle dark mode"
    class="p-3 rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 focus:ring-4 focus:ring-blue-500"
>
    <svg x-show="!dark" ...><!-- sun icon --></svg>
    <svg x-show="dark" ...><!-- moon icon --></svg>
</button>
```

```js
// tailwind.config.js
export default {
    darkMode: 'class',
    // ...existing content/theme/plugins
};
```
`[CITED: tailwindcss.com/docs/dark-mode — class-based strategy + prefers-color-scheme fallback is the documented standard pattern for exactly this "toggle + persist + default to OS" requirement]`

### Pattern 4: Flowbite Install for This Repo's Tailwind v3 Setup

**What:** `tailwind.config.js` plugin registration + content glob; `app.js` JS import — the **Tailwind v3** shape, not the v4 CSS-first shape Flowbite's current docs default to.

```bash
npm install flowbite
```

```js
// tailwind.config.js
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import flowbitePlugin from 'flowbite/plugin';

export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './node_modules/flowbite/**/*.js',   // NEW — Flowbite's own component JS uses Tailwind classes dynamically
    ],
    darkMode: 'class',                        // NEW — UI-02
    theme: { extend: { fontFamily: { sans: ['Figtree', ...defaultTheme.fontFamily.sans] } } },
    plugins: [forms, flowbitePlugin],         // NEW — flowbitePlugin added
};
```

```js
// resources/js/app.js
import './bootstrap';
import Alpine from 'alpinejs';
import 'flowbite';   // NEW — registers Flowbite's vanilla-JS behaviors (dropdown, modal triggers via data-attributes)

window.Alpine = Alpine;
Alpine.start();
```

`app.css` needs no change — `@tailwind base/components/utilities` already present and correct for v3; Flowbite v3-era docs do not require a `@import "flowbite"` line (that's the v4 CSS-first syntax this project does not use).

`[ASSUMED — MEDIUM confidence]`: the exact `flowbite/plugin` import path and `content` glob pattern were confirmed via WebSearch cross-referencing multiple community sources (dev.to, Medium "How to install Flowbite and Tailwind CSS with Laravel") converging on the same shape, but a direct fetch of Flowbite's own v3-pinned docs page (`v3.flowbite.com`) was rate-limited (HTTP 429) during this research session and could not be independently re-confirmed against the primary source. This is a well-trodden, low-risk community pattern per the milestone SUMMARY.md's own risk assessment ("standard, self-contained frontend styling/theming task"); recommend a quick `npm run build` smoke-check during execution (does a Flowbite dropdown actually toggle) rather than re-deriving the config from scratch if something doesn't work.

### Anti-Patterns to Avoid
- **Re-deriving visibility logic anywhere outside `Exam::scopeVisibleTo()`:** e.g., a new "sections I can see" or "my exams" widget built ad hoc for the reskinned dashboard that queries `Section`/`Exam`/`Enrollment` directly instead of calling `Exam::visibleTo($user)`. This is exactly the v1 invariant that must survive (PITFALLS.md Pitfall 1).
- **Copying the `finalizeIfExpired()`-style "check on every touch" idiom for anything in this phase's scope:** not applicable to Phase 7 directly (that's an AVL-0x/Phase-8 concern), but the codebase's own established idiom is easy to over-apply; keep it scoped to attempt-timer logic only.
- **Storing `sections.name` as a plain writable string:** invites drift from `year`/`semester`/`sequence`. Compute it.
- **Silently keeping `return true;` in `authorize()` for section/subject-lecturer Form Requests, copy-pasted from `StoreSubjectRequest`/`StoreClassroomRequest`:** SEC-03 requires actual per-subject-lecturer ownership checks now — this is a new authorization shape this phase introduces, not a continuation of the D-09 "no ownership" convention.
- **Editing only some of the 26 files that reference `classroom`/`Classroom`:** see Runtime State Inventory below for the full enumerated list — this is the exact mechanism of PITFALLS.md Pitfall 7 (seeder/factory/test drift breaks `migrate:fresh --seed`).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Dark mode persistence | A cookie + server-side theme preference on `User` | `localStorage` + Tailwind `darkMode: 'class'` | UI-02 explicitly specifies client-only persistence (no account-level setting requirement); a DB column/migration for this would be unrequested scope |
| Section "any of N lecturers can manage" check | A custom Gate closure enumerating logic per action | `$subject->lecturers()->whereKey($user->id)->exists()` in each Form Request's `authorize()` | Mirrors this codebase's own established "ownership check in `authorize()`, reusable Policy call where a Policy already exists" convention |
| Pivot enum casting (`enrollments.status`) | A raw string column + manual `match()` validation scattered across controllers | Custom `Enrollment extends Pivot` class with `casts()` (same pattern as `Role`/`QuestionType`) attached via `->using(Enrollment::class)` | Documented as "the one non-obvious stack detail" in SUMMARY.md — `withPivot()` alone does not cast; skipping the custom Pivot class means `$enrollment->pivot->status` stays a raw string everywhere it's read |
| Status pill styling | Per-view ad hoc `<span class="...">` markup for each of Enrolled/Published/Draft/etc. | One `x-status-pill` Blade component (UI-SPEC, locked) | UI-SPEC explicitly calls this out: "Build this as one reusable Blade component... do not hand-roll pill markup per view" |

**Key insight:** Every backend mechanism this phase needs (enum-cast pivot, ownership-in-`authorize()`, `sync()`-based pivot writes, computed accessors over stored redundant columns) already has a working precedent in this exact codebase from Phases 1-6. The research risk in this phase is not "what pattern to use" — it's "did the rename/rewrite sweep touch every one of the ~26 files that reference the old schema," which is a completeness problem, not a design problem.

## Runtime State Inventory

> This phase is a rename/schema-break phase (v1 `Classroom` → v2.0 `Section`, `users.classroom_id` removed, `classroom_subject` pivot removed). Each category below was explicitly checked against the live repo this session.

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | **None.** This project has no deployed/production database — the only database is the local Herd MySQL `yp-student-exam` instance, always rebuilt via `migrate:fresh --seed` (confirmed: README's setup instructions already specify `migrate:fresh --seed`, not incremental `migrate`, and PITFALLS.md Pitfall 3 confirms this is the only correct path post-schema-break). No existing rows need migrating; the schema-break simply drops and recreates. | None — verified by reading README.md setup section and `.planning/research/PITFALLS.md` Pitfall 3. |
| Live service config | **None.** No external services (n8n, Datadog, Tailscale, Cloudflare) are part of this project's stack — confirmed via `composer.json`/`package.json` read this session (Laravel/Breeze/Tailwind/Alpine/Flowbite only, no integration packages). | None. |
| OS-registered state | **None.** No scheduled tasks, pm2 processes, or systemd units reference "classroom" — this project explicitly avoids a scheduler/queue-worker dependency (CLAUDE.md "What NOT to Use": no queued/delayed job for auto-submit, no `Schedule::command` sweep is in use). | None. |
| Secrets/env vars | **None.** `.env`/`.env.example` contain only standard Laravel/DB/mail keys — no key name embeds "classroom" (confirmed by reading `.env.example`-equivalent DB config documented in README; direct `.env` read was blocked by this session's file-permission sandboxing, but README's documented `DB_*` keys are generic and unrelated to the rename). | None. |
| Build artifacts / installed packages | **None requiring reinstall.** `bootstrap/cache/` contains only `packages.php`/`services.php` (Composer package-discovery cache, auto-regenerated, not name-sensitive to "classroom"). No `.egg-info`-equivalent stale build artifact exists in a PHP/Node project of this shape. Compiled Blade view cache (`storage/framework/views/*.php`) and Vite's `public/build/` output are both regenerated on next request/build — no manual clear needed beyond normal deploy hygiene. | None — optionally run `php artisan view:clear` and `npm run build` as part of normal verification, not a special rename-driven step. |

**Code-level rename sweep (not "runtime state," but the equivalent completeness list for this phase — grep-verified this session, 26 files):**
```
app/Http/Controllers/Lecturer/ClassroomController.php        -> SectionController.php
app/Http/Controllers/Lecturer/ClassroomRosterController.php  -> (superseded — roster becomes enrollment-driven; Phase 8 owns the write actions, Phase 7 removes the classroom_id-writing version)
app/Http/Controllers/Lecturer/ExamAssignmentController.php   -> classrooms()->sync() to sections()->sync()
app/Http/Controllers/Lecturer/ExamController.php              -> any classroom references
app/Http/Requests/Lecturer/AssignExamRequest.php               -> classroom_ids -> section_ids, exists:classrooms -> exists:sections
app/Http/Requests/Lecturer/AssignStudentRequest.php            -> superseded by enrollment-based assignment (Phase 8 territory for the write; Phase 7 must remove/replace this Form Request since users.classroom_id is dropped)
app/Http/Requests/Lecturer/StoreClassroomRequest.php           -> StoreSectionRequest.php
app/Http/Requests/Lecturer/UpdateClassroomRequest.php          -> UpdateSectionRequest.php
app/Models/Classroom.php                                        -> Section.php
app/Models/Exam.php                                             -> classrooms() -> sections(), scopeVisibleTo() rewrite
app/Models/Subject.php                                           -> classrooms() belongsToMany removed, hasMany(Section) added, lecturers() added
app/Models/User.php                                              -> classroom()/classroom_id removed; sections()/subjects() added
app/Policies/AttemptPolicy.php                                   -> no logic change, delegates to Exam::visibleTo() (verify comment references still accurate)
app/Policies/ExamPolicy.php                                      -> no logic change, delegates to Exam::visibleTo()
database/factories/ClassroomFactory.php                          -> SectionFactory.php (subject_id, year, semester, sequence, capacity, windows)
database/migrations/2026_07_15_100001_create_classrooms_table.php     -> reordered + rewritten, see Schema Break below
database/migrations/2026_07_15_100003_add_role_and_classroom_id_to_users_table.php -> drop the classroom_id half, keep role
database/migrations/2026_07_15_100004_create_classroom_subject_table.php -> repurposed to create_subject_user_table.php
database/migrations/2026_07_15_100008_create_exam_classroom_table.php    -> rewritten to create_exam_section_table.php
database/seeders/DatabaseSeeder.php                               -> full rewrite, see "Seeder & Factory Rewrite"
resources/views/lecturer/classrooms/{create,edit,index}.blade.php -> lecturer/sections/{create,edit,index}.blade.php
resources/views/lecturer/exams/show.blade.php                     -> classroom references -> section
resources/views/lecturer/home.blade.php                            -> nav link updates (superseded by full navbar rebuild anyway)
resources/views/student/exams/index.blade.php                      -> classroom references -> section
routes/lecturer.php                                                -> classrooms resource route -> sections; roster routes removed/replaced
tests/Feature/DatabaseSeederTest.php, DomainSchemaTest.php, Grading/AttemptGraderTest.php, Lecturer/ClassroomControllerTest.php, Lecturer/ClassroomRosterTest.php, Lecturer/ClassroomSubjectLinkageTest.php, Lecturer/ExamAssignmentTest.php, Lecturer/GradeAnswerTest.php, Lecturer/Phase5ReviewFixesTest.php, Lecturer/ResultTest.php, Student/AttemptAnswerTest.php, Student/AttemptPolicyTest.php, Student/AttemptShowTest.php, Student/AttemptStartTest.php, Student/AttemptSubmitTest.php, Student/ExamAccessTest.php, Student/ExamIndexTest.php, Student/Phase4ReviewFixesTest.php, Student/ResultTest.php -> every `Classroom::factory()`, `classroom_id`, `->classrooms()`, `route('lecturer.classrooms...')` call needs an equivalent `Section`/`section_id`/`->sections()`/enrollment-based rewrite
```
This list was produced via `grep -rli "classroom" app database routes resources/views tests` this session — treat it as the literal checklist for the schema-break wave's completeness, per PITFALLS.md Pitfall 7's own prescribed verification method.

## Common Pitfalls

### Pitfall 1: Migration ordering — `sections` needs `subject_id` FK, but the current file order creates `classrooms` (100001) *before* `subjects` (100002)

**What goes wrong:** If the `create_classrooms_table.php` migration is edited in place (same filename/timestamp, `100001`) to create `sections` with a `subject_id` foreign key `->constrained()`, `migrate:fresh` fails immediately — Laravel runs migrations in filename-timestamp order, and `subjects` (currently `100002`) doesn't exist yet when `100001` tries to add the FK constraint.

**Why it happens here:** This is a genuinely new finding from this phase's research — the milestone-level SUMMARY.md/PITFALLS.md describe the rename and the atomic-slice requirement but do not call out this specific ordering collision, because in v1 `classrooms` legitimately had no FK dependency on `subjects` (the relationship was a pivot, not a direct FK) — the ordering only becomes a hard blocker once `sections.subject_id` becomes a direct foreign key.

**How to avoid:** Reorder the migration *files* (rename, not just edit content) so subjects is created first:
- `2026_07_15_100001_create_subjects_table.php` (was `100002`, content unchanged)
- `2026_07_15_100002_create_sections_table.php` (was `100001_create_classrooms_table.php`, rewritten with `subject_id` FK)
- `2026_07_15_100003_add_role_to_users_table.php` (was `..._add_role_and_classroom_id_to_users_table.php` — drop the `classroom_id` half entirely)
- `2026_07_15_100004_create_subject_user_table.php` (was `create_classroom_subject_table.php`, repurposed — same file slot, new pivot)
- `2026_07_15_100005`–`100007`: exams/questions/options, unchanged
- `2026_07_15_100008_create_exam_section_table.php` (was `create_exam_classroom_table.php`, FK renamed `classroom_id`→`section_id`)
- `2026_07_15_100009`–`100010`: attempts/answers, unchanged
- `2026_07_15_100011_create_enrollments_table.php` (**new file** — this table did not exist in v1 in any form, so it cannot be an "edit in place"; append it at the end of the sequence)

**Warning signs:** `migrate:fresh` throws a foreign key constraint error naming a table that "doesn't exist" during the early migration steps.

### Pitfall 2: Static Blade interpolation of a live Alpine count (FIX-01 root cause)

**What goes wrong:** `resources/views/student/attempts/show.blade.php` currently computes `$answeredCount = $savedAnswers->count()` in PHP (line ~178) from the page-load snapshot, then interpolates it directly into the confirm-submit modal's Blade string (line ~198: `__("...:answered of :total questions answered.", ['answered' => $answeredCount, ...])`). Because this text is rendered once, server-side, at initial page load, it never updates as the student's per-question Alpine cards autosave answers via `window.axios.post()` — the modal always shows the count from the moment the page was first requested, not the true saved-answer count at the moment "Submit Exam" is clicked.

**Why it happens here (verified this session by direct read of the file):** Each question card already has its own isolated Alpine `x-data` scope (`status`, `lastPayload`) tracking its own save state, by deliberate design (per the file's own comment: "deliberately NOT a single whole-page x-data JSON blob of every question" — the answer-key leak prevention pattern from Phase 4). This isolation is correct for the leak-prevention goal but means there is currently no page-level Alpine state that aggregates "how many cards have `status === 'saved'` or a pre-existing saved answer" — the count logic lives only in PHP, computed once.

**How to avoid:** Lift a minimal count into the *outer* `attemptTimer()` Alpine scope (already page-level, already handles cross-cutting concerns like `autoSubmitting`) — e.g., an `answeredCount` reactive property seeded server-side from `$savedAnswers->count()` (as today, for the pre-JS/no-flash initial render) but incremented/decremented by each question card's save transition via a bubbled event (mirroring the existing `deadline-expired` window-event pattern already used for the auto-submit trigger) or, more simply, computed reactively via Alpine's `$refs`/store from each card's own `status` — then bind the modal's `:answered of :total` text to `x-text`, not a static Blade string. Do not attempt to solve this with a page-level re-render (no full-page Livewire/polling) — that would violate the "no new JS framework" constraint and is unnecessary for a single reactive integer.

**Warning signs:** Any fix that re-adds a whole-page `x-data` JSON blob of every question (regresses the Phase 4 answer-key leak-prevention pattern) or that computes the count via a fresh server round-trip on modal-open (unnecessary network call for data the client already knows).

### Pitfall 3: `Exam::classrooms()`/`Section::exams()` pivot rename breaks silently if only the table name changes

**What goes wrong:** Renaming the `exam_classroom` table to `exam_section` (or `classroom_id` column to `section_id` within it) without also updating the explicit `belongsToMany(Exam::class, 'exam_classroom')` / `belongsToMany(Classroom::class, 'exam_classroom')` calls in `Exam.php`/`Section.php` (both currently have explicit pivot-name overrides because they deviate from Eloquent's alphabetical convention, per the existing code comments) leaves the relation querying a table that no longer exists — a runtime `Illuminate\Database\QueryException` the first time any exam-assignment code path runs, not caught until execution.

**Why it happens here:** This pivot already required an explicit override in v1 (documented in-code: "exam_classroom keeps its ROADMAP name... requires an explicit override") — it's the kind of manually-specified string that a search-and-replace on the *migration* file can miss if the sweep isn't also checked against the *model* files.

**How to avoid:** Grep `exam_classroom` (not just `classroom`) as a second, narrower pass after the main rename sweep, to specifically catch this explicit-pivot-name string in both migration and model files.

### Pitfall 4: `AssignStudentRequest`/`ClassroomRosterController` become orphaned by the schema break

**What goes wrong:** `users.classroom_id` is dropped this phase. `ClassroomRosterController@store/destroy` write directly to that column (`$student->update(['classroom_id' => $classroom->id])`) — this code cannot simply be renamed, it must be **removed or fundamentally rewritten**, because there is no longer a column for it to write to. Section-membership is now expressed exclusively through the `enrollments` pivot.

**Why it happens here:** This phase's scope explicitly excludes the *enrollment apply/withdraw UI* (that's Phase 8, ENR-01 through ENR-07) — but the roster-assignment controller this phase must remove is the *v1 lecturer-assigns-a-student-directly* flow, a different mechanism than v2.0's student-self-enrolls flow. It would be a scope-creep trap to rebuild `ClassroomRosterController` as a lecturer-side "assign student to section" tool (that's not in SEC-01/02/03's scope, and duplicates what Phase 8's enrollment flow will do) — but it would be a **correctness bug** to leave the old controller/routes/views referencing a dropped column.

**How to avoid:** Delete `ClassroomRosterController.php`, `AssignStudentRequest.php`, and the `classrooms/{classroom}/students` routes entirely in this phase (they have no v2.0 equivalent within Phase 7's scope). Confirm no other file references `route('lecturer.classrooms.students...')`.

## Code Examples

### `Enrollment` Custom Pivot Model (SEC-01/02/03 schema, consumed by ENR-08's `scopeVisibleTo`)
```php
// app/Models/Enrollment.php — NEW
namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Enrollment extends Pivot
{
    protected $table = 'enrollments';

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
        ];
    }
}
```
```php
// app/Models/Section.php (renamed from Classroom.php)
public function enrollments(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'enrollments')
        ->using(Enrollment::class)
        ->withPivot(['status', 'rejection_reason'])
        ->withTimestamps();
}

public function subject(): BelongsTo
{
    return $this->belongsTo(Subject::class);
}

public function exams(): BelongsToMany
{
    return $this->belongsToMany(Exam::class, 'exam_section');
}

// SEC-01: computed, not stored — mirrors the existing "live accessor over
// denormalized column" precedent (Attempt::deadline(), Exam total_score guidance).
protected function name(): Attribute
{
    return Attribute::make(
        get: fn () => "{$this->year}-{$this->semester}-{$this->sequence}",
    );
}
```
`Source: pattern synthesized from .planning/research/SUMMARY.md ("the enrollments pivot needs a custom Pivot model via ->using() for enum casts") + this session's direct read of app/Models/Classroom.php's existing explicit-pivot-name precedent.`

### Sequence Assignment for New Sections (SEC-01, mirrors existing Question::position precedent)
```php
// app/Http/Controllers/Lecturer/SectionController.php@store
$sequence = Section::where('subject_id', $subject->id)
    ->where('year', $request->validated('year'))
    ->where('semester', $request->validated('semester'))
    ->max('sequence') + 1;

Section::create([...$request->validated(), 'subject_id' => $subject->id, 'sequence' => $sequence]);
```
`Source: STATE.md Phase 02 decision — "Question position = max(position)+1 scoped to exam" — same idiom applied to the new scoped-sequence requirement.`

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `users.classroom_id` flat FK, one classroom per student, classroom shares many subjects via pivot | `enrollments` pivot (many-to-many, status-gated) between students and subject-scoped sections | This phase (v2.0 schema break) | Enables self-service enrollment (Phase 8), capacity/window gating, and per-subject section granularity that the v1 flat model couldn't express |
| Lecturer directly assigns students to a classroom (`ClassroomRosterController`) | Student self-applies to a section; lecturer can only reject (Phase 8) | This phase removes the old mechanism; Phase 8 adds the new one | The write-path for "who's in this section" moves from lecturer-initiated to student-initiated |

**Deprecated/outdated:**
- `Classroom` model/`classrooms` table (as a many-subjects entity): fully replaced by `Section` (one-subject entity) this phase.
- `classroom_subject` pivot: removed; a section now has a direct `subject_id` FK instead of a many-to-many relation to subjects.
- `ClassroomRosterController`/`AssignStudentRequest`: removed this phase (see Pitfall 4), no direct v2.0 replacement within Phase 7's scope.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Flowbite v3-era `tailwind.config.js` plugin wiring (`content` glob incl. `node_modules/flowbite/**/*.js`, `plugins: [flowbitePlugin]`) and `app.js` `import 'flowbite'` are the correct integration shape for this repo's Tailwind v3 (not v4) setup | Pattern 4: Flowbite Install | Low-to-medium — if the exact glob/import is slightly off, the symptom is immediately visible (Flowbite dropdowns/modals don't toggle) and cheap to fix at execution time via a build smoke-check; does not risk silent data corruption |
| A2 | `Section.name` should be a computed accessor (not a stored column) | Architecture Patterns, Code Examples | Low — if the planner instead chooses a stored column, functionality is unaffected, only a minor "single source of truth" concern; easy to change later without a data-migration risk since there's no persisted user data |
| A3 | `ClassroomRosterController`/`AssignStudentRequest` should be deleted outright in Phase 7, not preserved/repurposed | Pitfall 4 | Medium — if the planner instead tries to preserve this controller for some interim lecturer-assigns-student flow, it will either reference the dropped `classroom_id` column (crash) or need non-trivial rework that duplicates Phase 8's enrollment scope; flagging this explicitly so the plan doesn't accidentally scope-creep into Phase 8 territory |
| A4 | `subject_user`/section Form Requests need genuine per-subject ownership checks in `authorize()` (a new pattern for this codebase, diverging from the existing D-09 "role-middleware-only" convention used by Subject/Classroom CRUD) | Pattern 2: subject_user | Medium — if the planner copies the existing `return true;` D-09 pattern instead, SEC-03's "any assigned lecturer — not only its creator" requirement still technically works (any lecturer can act) but a lecturer NOT assigned to the subject could also manage its sections, which contradicts SEC-03's stated scoping intent |

## Open Questions

1. **Exact validation bounds for section `capacity`/enrollment window dates**
   - What we know: SEC-02 requires "capacity and an enrollment window (open date, close date)"; the milestone Resolved Design Decisions table (REQUIREMENTS.md #6) locks the boundary semantics as half-open `[opens_at, closes_at)`.
   - What's unclear: whether Phase 7's section CRUD form should enforce `closes_at > opens_at` (rejecting a zero-width or inverted window) as a `rules()` validation, given PITFALLS.md Pitfall 6 flags this as an "explicitly accept and test" decision rather than an obvious default.
   - Recommendation: Phase 7 only *creates* sections (no enrollment apply/withdraw logic lives here yet — that's Phase 8), so the safest default is to validate `closes_at` is `after:opens_at` at creation time (a normal Laravel `rules()` validation, no new pattern), deferring the deeper "is the boundary inclusive/exclusive at apply-time" question to Phase 8 where it's actually exercised.

2. **Does `sections.capacity`/enrollment-window need to be nullable or does SEC-02 imply both are always required?**
   - What we know: SEC-02 says "setting its capacity and an enrollment window" without qualifying either as optional.
   - What's unclear: whether a lecturer can create a section with no window set yet (to be filled in later) — REQUIREMENTS.md's resolved decision #6 discusses null bounds for *exam* availability windows (AVL-01, explicitly "each optional"), but SEC-02's enrollment window isn't given the same explicit optionality.
   - Recommendation: require both bounds at section-creation time (simpler, matches SEC-02's flat phrasing more literally than AVL-01's explicit "empty = unbounded" carve-out); this is a low-risk default the planner can adjust with a one-line rules() change if a later phase needs otherwise.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Laravel runtime | ✓ | 8.2.32 | — |
| Composer | Backend deps | ✓ | 2.8.2 | — |
| Node.js | Vite/npm build | ✓ | 20.14.0 | — |
| npm | Flowbite install | ✓ | 10.7.0 | — |
| MySQL (via Herd) | `yp-student-exam` DB | Not directly checked via `mysql` CLI (not on PATH in this shell) — inferred available via Herd's managed service, consistent with prior phases' passing `migrate:fresh --seed` runs (STATE.md Phase 06 log confirms this was exercised successfully) | — | If unavailable at execution time, verify via `php artisan migrate:status` or `php artisan db:show` rather than the raw `mysql` CLI, since this project's MySQL is Herd-managed, not a standalone CLI install |

**Missing dependencies with no fallback:** none.
**Missing dependencies with fallback:** MySQL CLI binary not on PATH — use Laravel's own `artisan` commands (`migrate:status`, `db:show`) to verify DB connectivity instead of a raw `mysql` client call.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.0.1 (via `phpunit.xml`), Laravel's `Tests\TestCase` + `RefreshDatabase` trait |
| Config file | `phpunit.xml` (project root) |
| Quick run command | `php artisan test --filter=<TestClass>` or `vendor/bin/phpunit --filter <method>` |
| Full suite command | `php artisan test` (runs `tests/Unit` + `tests/Feature` against the real `yp-student-exam` MySQL DB via `RefreshDatabase`, per existing `phpunit.xml` — no in-memory sqlite override is configured) |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| UI-01 | Navbar/dropdowns render on lecturer + student pages; status pill component maps status→correct classes | feature (assertSee/assertOk) | `php artisan test --filter=NavigationTest` | ❌ Wave 0 — new test |
| UI-02 | Dark-mode toggle persists via localStorage; `darkMode: 'class'` applied | manual/browser-only (localStorage + `prefers-color-scheme` are not directly testable via PHPUnit HTTP assertions) | manual UAT + visual check | N/A — flagged manual-only, justification: client-only browser API behavior, no server round-trip to assert against |
| FIX-01 | Confirm-modal answered-count updates after an autosave, before page reload | feature (assert response reflects live count) or manual/browser (Alpine reactivity is JS-only) | Feature test can assert the *initial* server-rendered seed value is correct; the *live update after autosave without reload* is inherently a browser-JS behavior — flag as manual-only for the reactive portion, feature-testable for the initial-render portion | ⚠️ Partial — Wave 0 needs a feature test for initial value; manual UAT covers the reactive update |
| SEC-01 | `sections` table has `subject_id`, `year`, `semester`, `sequence`, computed `name` matches `year-semester-sequence` | feature (schema + model test) | `php artisan test --filter=SectionModelTest` | ❌ Wave 0 — new test (mirrors `DomainSchemaTest`) |
| SEC-02 | Lecturer can create/edit/delete a section with capacity + window | feature (CRUD test, mirrors `ClassroomControllerTest`) | `php artisan test --filter=SectionControllerTest` | ❌ Wave 0 — new test |
| SEC-03 | Subject assignable to multiple lecturers; any assigned lecturer can manage its sections | feature (authorization test) | `php artisan test --filter=SubjectLecturerTest` | ❌ Wave 0 — new test |
| ENR-08 | List (`Student\ExamController@index`) and gate (`ExamPolicy::takeable`) agree across enrolled/withdrawn/rejected/never-applied | feature (cross-consumer regression, see Pattern 1 above) | `php artisan test --filter=ExamVisibilityRegressionTest` | ❌ Wave 0 — new test, **hard acceptance gate** per STATE.md blocker |
| DEL-03 | `migrate:fresh --seed` succeeds and produces a working demo for every seeded role | feature/smoke (mirrors existing `DatabaseSeederTest`) | `php artisan migrate:fresh --seed && php artisan test --filter=DatabaseSeederTest` | ⚠️ Existing file needs full rewrite, not new |

### Sampling Rate
- **Per task commit:** targeted `--filter` run for the touched test class(es)
- **Per wave merge:** `php artisan test` (full suite) — critical for the schema-break wave, since a passing full suite is the actual proof every one of the 26 rename-swept files/tests was updated correctly (mirrors PITFALLS.md Pitfall 7's own prescribed verification: "re-run the seeder... as the actual verification step, not just migrations ran without error")
- **Phase gate:** Full suite green + a manual `php artisan migrate:fresh --seed` from a clean state, before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Lecturer/SectionControllerTest.php` — covers SEC-01/SEC-02
- [ ] `tests/Feature/Lecturer/SubjectLecturerTest.php` — covers SEC-03
- [ ] `tests/Feature/Student/ExamVisibilityRegressionTest.php` — covers ENR-08 (hard gate — list/gate agreement across 4 enrollment states)
- [ ] `tests/Feature/DatabaseSeederTest.php` — needs full rewrite (not new, but existing assertions reference dropped `classroom_id`/`classroom_subject` shape) — covers DEL-03
- [ ] `tests/Feature/DomainSchemaTest.php` — needs rewrite: table list (`sections`, `subject_user`, `enrollments`, `exam_section` replacing `classrooms`/`classroom_subject`/`exam_classroom`), plus a new assertion for `enrollments` unique(section_id,user_id) index
- [ ] Every test file listed in the Runtime State Inventory's rename sweep — not a *new* gap, but each is a modification gap that must be swept before the full suite can pass

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No | Unchanged this phase — Breeze scaffold untouched |
| V3 Session Management | No | Unchanged this phase |
| V4 Access Control | Yes | New per-subject lecturer-assignment ownership check (SEC-03) in Form Request `authorize()`, mirroring existing `UpdateExamRequest`/Policy conventions; `Exam::scopeVisibleTo()` rewrite is itself an access-control-critical change (ENR-08) |
| V5 Input Validation | Yes | Standard Laravel `FormRequest::rules()` — `year`/`semester`/`capacity` integer bounds, `Rule::exists()` for `subject_id`/lecturer `user_id` on assignment |
| V6 Cryptography | No | Not applicable this phase |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| IDOR — a lecturer not assigned to a subject manages its sections via a crafted request (route param tampering) | Elevation of Privilege | Ownership check in `authorize()` (`$subject->lecturers()->whereKey($user->id)->exists()`), not relying on route-model-binding alone — matches existing project convention (`ExamPolicy`, `UpdateExamRequest` D-06) |
| Visibility-predicate divergence — a student reaches an exam via direct URL that the index would have hidden (the exact v1 invariant this phase must preserve, ENR-08) | Elevation of Privilege / Information Disclosure | Single shared `Exam::scopeVisibleTo()` predicate, consumed identically by index and Policy — cross-consumer regression test as hard gate (Pattern 1 above) |
| Stale/removed roster-assignment endpoint left reachable after `users.classroom_id` is dropped | Tampering (writes to a non-existent column → app error, not exploitable, but a reliability/availability concern) | Delete `ClassroomRosterController`/`AssignStudentRequest`/associated routes outright (Pitfall 4), don't leave a broken endpoint reachable |

## Sources

### Primary (HIGH confidence)
- Direct reads of the current repository this session: `app/Models/{Exam,User,Classroom,Subject,Attempt}.php`, `app/Policies/{ExamPolicy,AttemptPolicy}.php`, `app/Http/Controllers/{Lecturer,Student}/*.php`, `app/Http/Requests/**`, all `database/migrations/2026_07_15_*.php`, `database/seeders/DatabaseSeeder.php`, `resources/views/layouts/*.blade.php`, `resources/views/student/attempts/show.blade.php`, `tailwind.config.js`, `package.json`, `composer.json`, `phpunit.xml`, `routes/{web,lecturer,student}.php`
- `.planning/research/SUMMARY.md` and `.planning/research/PITFALLS.md` — milestone-level v2.0 research (dated 2026-07-16, same day), already HIGH-confidence-rated for architecture/pitfalls per its own Confidence Assessment section; this phase-research narrows and codebase-verifies its claims rather than re-deriving them
- `.planning/STATE.md`, `.planning/REQUIREMENTS.md`, `07-CONTEXT.md`, `07-UI-SPEC.md` — locked decisions and requirement text
- `npm view flowbite version/repository.url/scripts.postinstall` — direct registry query, this session
- `gsd-tools query package-legitimacy check --ecosystem npm flowbite` → verdict `OK`, this session

### Secondary (MEDIUM confidence)
- `tailwindcss.com/docs/dark-mode` (WebFetch not directly re-confirmed this session, but this is standard, widely-documented Tailwind behavior consistent with the codebase's `darkMode: 'class'` requirement already specified in CONTEXT.md)
- WebSearch cross-references (dev.to, Medium) on Flowbite + Laravel + Vite install shape — converged across multiple independent sources on the same `tailwind.config.js`/`app.js` pattern

### Tertiary (LOW confidence)
- Flowbite's own current getting-started docs (`flowbite.com/docs/getting-started/laravel/`) — fetched this session but found to describe the Tailwind v4/Laravel 12 default, explicitly not this project's v3 setup; used only to confirm the *general* install shape (npm install, plugin registration, JS import), not as an authoritative source for the exact v3 syntax
- `v3.flowbite.com` direct fetch attempted this session, returned HTTP 429 (rate-limited) — not independently confirmed; flagged in Assumptions Log (A1)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — Flowbite version/legitimacy directly verified via npm + package-legitimacy seam; all backend patterns are direct precedent-copies of already-shipped code in this exact repo
- Architecture: HIGH — every schema/relationship claim is either a direct read of existing v1 source or a mechanical, precedent-following consequence of the locked SEC-01/02/03/ENR-08 requirements; migration-ordering finding (Pitfall 1) independently verified by reading actual migration file timestamps this session
- Pitfalls: HIGH (codebase-derived, 4 of 4 pitfalls verified against actual current source) / MEDIUM (Flowbite v3 exact config, per A1)

**Research date:** 2026-07-16
**Valid until:** 30 days (stable Laravel/Tailwind ecosystem; re-verify Flowbite version if execution is delayed past that window, given npm packages update independently of this research)

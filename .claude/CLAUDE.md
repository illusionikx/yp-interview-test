<!-- GSD:project-start source:PROJECT.md -->

## Project

**Online Examination Portal**

A web portal for online examinations and student management, built on Laravel 11 with Breeze. Lecturers author subject exams (multiple-choice and open-text questions) and assign them to classes; students log in, see only the exams assigned to their class, and complete them within a time limit. Multiple-choice answers are auto-graded; open-text answers are graded by the lecturer. Built as an assessment deliverable for YP, shipped to a public GitHub repository with a README.

**Core Value:** A student can take a time-limited exam that is correctly restricted to their class, and their answers are reliably captured and scored. If everything else fails, this must work: the right exam reaches the right student, the clock is enforced, and the submission is saved and graded.

### Constraints

- **Tech stack**: Laravel 11 + Breeze — mandated by the brief. Build on the existing scaffold; do not replace it.
- **Database**: MySQL (`yp-student-exam` via Herd) — configured in `.env`; the README must document creating the database and running migrations/seeders.
- **Frontend**: Breeze Blade + Tailwind + Alpine — already scaffolded; no SPA.
- **Delivery**: All code pushed to a public GitHub repository with a README — this is a graded deliverable, so setup must be reproducible from a clean clone.
- **Scope discipline**: minimal, correct implementation over feature breadth — favor the simplest thing that satisfies each requirement.

<!-- GSD:project-end -->

<!-- GSD:stack-start source:research/STACK.md -->

## Technology Stack

## Recommended Stack

### Core Technologies (already fixed — confirmed compatible, no action needed)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Laravel | 11.x (project has 11.31) | Application framework | Mandated. Laravel 11 slimmed the app skeleton: no `app/Http/Kernel.php` or `app/Console/Kernel.php` — middleware aliases are registered in `bootstrap/app.php` via `->withMiddleware()`, and scheduled tasks are defined in `routes/console.php` via the `Schedule` facade. Every recommendation below targets this Laravel-11-shaped skeleton, not the Laravel-10 Kernel-based one. |
| Breeze | 2.4 (installed) | Auth scaffolding | Already provides register/login/logout/password-reset/email-verification/profile and the base Blade layout. Do not touch. |
| MySQL | 8.x (via Herd, `yp-student-exam`) | Persistence | Already configured in `.env`. All domain tables below are plain relational tables — no MySQL-specific features (JSON columns, etc.) are required. |
| Blade + Tailwind 3 + Alpine.js | as scaffolded | Views/interactivity | Sufficient for a countdown timer and grading forms — see Supporting Libraries. No SPA/Livewire/Inertia needed. |

### Supporting Libraries (what to actually add)

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| *(none — no new Composer packages required for RBAC, timer, or grading)* | — | — | The entire exam domain (roles, timed attempts, auto-grade, manual grade, seeding) is implementable with Laravel-native primitives already in `composer.json`. See "What NOT to Use" for the packages you'll be tempted to add and why they're unnecessary. |
| `fakerphp/faker` | already present (transitive via `laravel/framework`'s factory support, or dev dependency in a fresh Breeze install) | Realistic fake data in factories (student names, subject titles, etc.) | Already available — do not `composer require` it separately; just use `fake()`/`$this->faker` inside factories. |

### Development Tools (already present — no changes)

| Tool | Purpose | Notes |
|------|---------|-------|
| PHPUnit | Automated tests | Use Feature tests for role gating, one-attempt-per-exam enforcement, and expiry behavior — these are exactly the areas most likely to regress silently. |
| Pint | Code style | Run before commits; no config changes needed. |
| Pail / Telescope | Log tailing / debugging | Useful while building the timer-expiry edge cases; not required by any recommendation below. |

## Detailed Recommendations

### 1. Role-based access control (Lecturer, Student) — Confidence: HIGH

- Add a `role` column to `users` (migration: `$table->string('role')->default('student');`), backed by a PHP native enum: `app/Enums/Role.php` → `enum Role: string { case Lecturer = 'lecturer'; case Student = 'student'; }`.
- Cast it on the `User` model using Laravel 11's `casts()` method (the newer convention that replaces/complements the `$casts` property): `protected function casts(): array { return ['role' => Role::class]; }`. This gives `$user->role === Role::Lecturer` type safety and IDE autocomplete with zero extra dependency — Laravel has supported native backed-enum casting since Laravel 8+.
- Add two trivial helper methods on `User`: `isLecturer(): bool` and `isStudent(): bool`. Every other role check in the app (Blade `@if`, Form Request `authorize()`, Policies) reads through these, not raw string comparisons.
- **Route-group protection via custom middleware, not Gates.** Create `app/Http/Middleware/EnsureUserHasRole.php` (single parameterized middleware: `middleware('role:lecturer')` / `middleware('role:student')`), register the alias in `bootstrap/app.php`:
- **Model-scoped authorization via Policies, not Gates.** Anything tied to a specific record — "can this lecturer edit *this* exam", "can this student view *this* attempt" — belongs in a Policy (`ExamPolicy`, `AttemptPolicy`, `AnswerPolicy`), auto-discovered by Laravel's `app/Models` ↔ `app/Policies` naming convention (no manual registration needed). Call via `$this->authorize('update', $exam)` in controllers or the `can:` middleware/Blade directive.
- Reserve **Gates** (`Gate::define` in `AppServiceProvider::boot()`) only for the rare check that isn't tied to a model at all (e.g., "can view the lecturer dashboard shell"). For this domain there are very few of these — most access decisions are either route-group-level (middleware) or record-level (policy).

### 2. Server-enforced exam timer with client countdown + auto-submit — Confidence: MEDIUM-HIGH

- `started_at` (timestamp) — set once, when the student begins the attempt.
- `duration_minutes` (unsigned int) — **copied from the exam at start time**, not read live from `exams.duration_minutes` on every check. This is important: if a lecturer edits an in-progress exam's duration, already-started attempts must not shift underneath the student.
- `expires_at` (timestamp) — computed and **stored** at start time (`started_at->addMinutes($duration)`), not recomputed on every request. Storing it once avoids drift and makes every subsequent check a single indexed comparison (`now() > expires_at`).
- `submitted_at` (nullable timestamp) — null while in progress, set on finalization (student-initiated or auto-submit).
- Every write-path route that touches an attempt (save an answer, finalize/submit) must check `now() >= $attempt->expires_at` (or `submitted_at !== null`) **before** accepting the write, and short-circuit into "auto-finalize and redirect to results" if expired. Do this once, centrally — either a Form Request's `authorize()` (e.g. `SubmitAnswerRequest`) or a small `EnsureAttemptIsActive` middleware applied to the attempt-taking route group — not duplicated ad hoc in each controller method.
- **Recommended enforcement mechanism for this project: lazy/on-touch finalization, not a cron sweep.** Any request that touches an expired-but-not-yet-submitted attempt (the exam-taking page itself, or an answer-save call) triggers finalization inline, synchronously, in that request. This requires zero background infrastructure — no queue worker, no `schedule:run` cron entry — which matters because this is a small graded deliverable evaluated by cloning the repo and clicking through it; a queue worker that isn't running is a common and confusing demo failure mode, and this project doesn't need attempts to close themselves the instant they expire if nobody is looking at them.
- **Optional, not required for MVP:** a scheduled sweep (`routes/console.php`: `Schedule::command('exams:auto-submit')->everyMinute();`, backed by an Artisan command that finds `expires_at < now() AND submitted_at IS NULL` and finalizes them) is the "textbook" complete answer and is easy to add later if the requirement grows (e.g., a lecturer needs to see an attempt flip to "closed" in real time without the student revisiting the tab). Don't build it up front — it's additional infrastructure (a cron entry, or `schedule:work` running continuously) for a scenario the current requirements don't call for. Note this is a Laravel-11-specific location: since Laravel 11 removed `Console/Kernel.php`, scheduled commands are defined directly in `routes/console.php` (or a closure in `bootstrap/app.php`'s `withSchedule()`), not in a Kernel `schedule()` method.
- Pass the attempt's `expires_at` to the view as an ISO-8601 string or epoch-ms integer (`{{ $attempt->expires_at->timestamp * 1000 }}`), not a pre-computed "seconds remaining" — computing remaining time client-side from an absolute timestamp avoids drift from page-load delay.
- A single Alpine component handles display and the auto-submit trigger:
- This is purely UX — the actual submit endpoint still re-checks `now()` against the stored `expires_at` server-side, so a student pausing JS or editing the DOM cannot extend their time.

### 3. Auto-grading MCQ, manual grading open-text — Confidence: HIGH

- `questions`: `exam_id`, `type` (native enum: `App\Enums\QuestionType::{Mcq, Essay}`, cast via `casts()`), `body`, `max_score`.
- `options`: `question_id`, `body`, `is_correct` (bool) — only populated for `mcq` questions.
- `answers`: `attempt_id`, `question_id`, nullable `option_id` (the student's MCQ choice), nullable `text_answer` (essay response), nullable `is_correct` (bool, MCQ only), nullable `score_awarded` (decimal), nullable `graded_by` (FK to `users`), nullable `graded_at`.
- Put grading logic in `app/Services/AttemptGrader.php` with a method like `gradeMcqAnswers(Attempt $attempt): void` that, for each MCQ answer, compares `answer->option_id` to the question's correct option and sets `is_correct` + `score_awarded` accordingly. Invoke this explicitly at finalization time (student submits, or auto-submit fires) — **not** via an Eloquent model `saving`/`created` event/observer on `Answer`. Model events make grading a hidden side effect of "saving a row," which is surprising to a reader and easy to accidentally re-trigger (e.g., re-grading on every autosave of a draft answer). An explicit service method called exactly once, at exactly the "this attempt is now finished" transition, is easier to reason about and to test.
- Manual grading (essay questions) is a simple lecturer-facing form: a `GradeAnswerRequest` Form Request validates `score` is numeric and within `[0, $question->max_score]`, the controller sets `score_awarded`, `graded_by = auth()->id()`, `graded_at = now()`.
- Track whether an attempt is "fully graded" (all essay answers have `graded_at`) — expose as an accessor on `Attempt` (e.g. `isFullyGraded(): bool`) computed from its answers relationship. Only compute/cache a denormalized `total_score` on `attempts` if the results-listing page shows scores for many attempts at once and recomputing per-row on every page load becomes noticeably expensive — for this project's scale (a handful of classes/students) a live accessor is simpler and avoids a stale-cache bug class; don't add the denormalized column pre-emptively.

### 4. Eloquent relationships / domain schema — Confidence: HIGH

### 5. Factories & seeders (demo data) — Confidence: HIGH

- Write a factory per model: `UserFactory` (add `lecturer()` and `student()` state methods setting the `role` enum and, for students, an associated class), `SchoolClassFactory`, `SubjectFactory`, `ExamFactory`, `QuestionFactory` (with an `mcq()`/`essay()` state, and an `hasOptions()` callback that attaches 3-4 `Option` rows with exactly one `is_correct`), `AttemptFactory`, `AnswerFactory`.
- `DatabaseSeeder` should produce a **reviewable demo scenario**, not just random noise: one lecturer and a handful of students with **known, fixed credentials documented in the README** (e.g. `lecturer@example.com` / `student1@example.com`, a shared demo password), 1-2 classes, 2-3 subjects, and at least one sample exam per subject containing both an MCQ and an essay question, assigned to a class. Use explicit `User::factory()->lecturer()->create(['email' => 'lecturer@example.com', ...])` (or `firstOrCreate`) for these named accounts so `php artisan db:seed` is safely re-runnable without unique-constraint errors; use plain `factory()->count(n)->create()` for filler/bulk data where exact identity doesn't matter.
- No seeding package is needed — `fakerphp/faker` (already available through Laravel's factory support) covers realistic names/text, and Laravel's built-in `Model::factory()` states/sequences cover everything else (roles, question types, correct-option selection).

### 6. Form Requests — Confidence: HIGH

- One Form Request per meaningful write action: `StoreExamRequest`, `UpdateExamRequest`, `AssignExamToClassesRequest`, `StartAttemptRequest`, `SubmitAnswerRequest`, `FinalizeAttemptRequest`, `GradeAnswerRequest`.
- Put **ownership/role checks** in `authorize()` (e.g. `return $this->user()->isLecturer() && $this->route('exam')->author_id === $this->user()->id;`) so invalid requests are rejected before validation runs. For anything already covered by a Policy, call `$this->user()->can('update', $this->route('exam'))` inside `authorize()` instead of re-deriving the check inline — keep the ownership rule defined once, in the Policy, and reused everywhere (Form Request, Blade `@can`, controller `authorize()` calls).
- Put **shape/format validation** (required fields, `max_score` bounds, timer duration ranges, etc.) in `rules()` as usual.

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|--------------------------|
| Role enum column + Gates/Policies/middleware | `spatie/laravel-permission` | Roles/permissions become dynamic (admin can create new roles or assign fine-grained permissions at runtime) or the number of roles grows past a handful with overlapping permission sets. |
| Lazy/on-touch attempt finalization | Scheduled sweep (`Schedule::command(...)->everyMinute()`) closing expired attempts | You need an attempt to visibly flip to "closed"/"submitted" the instant it expires even if the student never returns to the tab (e.g., a lecturer live-monitoring dashboard). |
| Plain Eloquent enum-cast `status` column on `Attempt` (`in_progress`/`submitted`/`graded`) | `spatie/laravel-model-states` (or similar state-machine package) | The attempt lifecycle grows real branching transitions with side effects per transition (e.g., notifications, multi-step review workflow) — three linear states don't warrant it. |
| Live accessor for attempt total score | Denormalized `total_score` column recomputed on grade save | The results-listing view needs to sort/filter by score across hundreds of attempts and recomputing per request becomes a measured performance problem. |
| Custom `EnsureUserHasRole` middleware | Laravel's built-in `can:` middleware exclusively | If every access rule is naturally model-scoped (no route-group-level "lecturer area" vs "student area" split), you could skip the custom role middleware and rely purely on Policies + `can:` — this project has enough route-group-level splitting (whole "lecturer" vs "whole "student" areas) that the extra middleware pays for itself. |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| `spatie/laravel-permission` | Built for dynamic, database-editable roles/permissions with a permission cache layer and pivot tables; for exactly two fixed roles it adds migrations, a caching model to reason about, and indirection with no correctness benefit. | `role` enum column on `users` + native Gates/Policies/middleware. |
| Livewire / Inertia / any SPA layer | Breeze's Blade+Tailwind+Alpine stack is already scaffolded and mandated; introducing a reactive component framework mid-project for a handful of forms and a timer is unjustified surface area. | Blade forms + the Alpine countdown component above. |
| Laravel Echo / Pusher / Reverb (broadcasting) | The timer is single-user; there's no cross-client real-time sync requirement. | Alpine `setInterval` client display + server-side `expires_at` check on write. |
| Queued/delayed job as the *sole* auto-submit mechanism | Depends on a queue worker being alive at the precise moment; delayed jobs can slip under load; still requires the on-write server check regardless, so it adds infrastructure without removing any risk. | On-write `now() >= expires_at` check (lazy finalization), optionally supplemented later by a `Schedule::command(...)->everyMinute()` sweep. |
| Third-party "Laravel quiz/exam" packages found on GitHub/Packagist | Low adoption, generic schemas that won't match this project's Subject/Class-scoped assignment model; adapting one costs more than building the ~6 focused domain tables directly. | The custom schema in section 4 above. |
| A state-machine package for attempt status | Three linear states (`in_progress` → `submitted` → `graded`) don't need transition guards/side-effect hooks. | Plain enum-cast `status` column with a couple of guard checks in the controller/service. |
| Model observers/events for grading | Turns grading into a hidden side effect of "saving a row"; easy to accidentally re-trigger on autosave. | Explicit `AttemptGrader` service method called once, at finalization. |

## Stack Patterns by Variant

- Extend the `Role` enum with one more case; add one more middleware alias/route group. Still no package needed at 3 fixed roles.
- Only reach for `spatie/laravel-permission` if that Admin role starts needing to grant/revoke fine-grained permissions per-user rather than "is an admin or not."
- That's when the scheduled sweep (optional pattern above) starts earning its keep, and potentially a lightweight polling refresh (`wire:poll`-equivalent via a simple `setInterval` + fetch in Alpine) rather than full broadcasting — still no need for Echo/Pusher/Reverb unless true push updates across many simultaneous viewers become a requirement.

## Version Compatibility

| Package A | Compatible With | Notes |
|-----------|------------------|-------|
| Laravel 11.x | PHP 8.2+ | Matches the existing scaffold (PROJECT.md notes PHP 8.2+ already). |
| Native backed enums + `casts()` model method | Laravel 8+ (casting), `casts()` method style specifically Laravel 11+ | Either the `$casts` property or the `casts()` method works in Laravel 11; the method form is the more current convention and is what new Laravel 11 code should use. |
| `Schedule::command(...)` in `routes/console.php` | Laravel 11.x only (this location is new) | Laravel 10 and earlier defined the schedule in `app/Console/Kernel.php::schedule()`, which no longer exists in the Laravel 11 skeleton — do not follow Laravel-10-era tutorials that reference `Kernel.php` for this. |
| `spatie/laravel-permission` (if ever adopted) | Composer resolves an ^6.x-line release against Laravel 11; the current 8.3.0 release requires Laravel ^12\|^13 | Not relevant to this project's recommendation (avoid it), but flagged so nobody accidentally `composer require`s the latest major and breaks on a Laravel-version conflict. |

## Sources

- https://laravel.com/docs/11.x/authorization — Gates vs Policies, policy auto-discovery, `can` middleware (confirmed directly)
- https://laravel.com/docs/11.x/scheduling — Laravel 11 scheduling location (`routes/console.php`, no `Console/Kernel.php`) (confirmed directly)
- https://laravel.com/docs/12.x/queues — delayed dispatch semantics, queue-vs-scheduler tradeoffs, cross-checked against 11.x behavior (MEDIUM — 12.x doc used to corroborate 11.x-era mechanics, framework version noted)
- https://packagist.org/packages/spatie/laravel-permission — current version (8.3.0, requires Laravel ^12|^13), install base, license (MEDIUM — community package registry, not official Laravel docs)
- https://laravel-news.com/laravel-gates-policies-guards-explained — cross-reference on Gates/Policies/Guards distinction (MEDIUM — reputable Laravel community publication)
- https://laraveldaily.com/lesson/alpine-js/countdown-timer-x-init — Alpine.js `x-init`/`setInterval` countdown pattern (MEDIUM — established Laravel-focused training resource, cross-checked against multiple similar examples)
- General web search cross-referencing Laravel quiz/exam implementations (Laracasts forum threads, Medium walkthroughs) for the "store `started_at`/`duration`, check server-side on every request" timer-enforcement pattern (MEDIUM — pattern consistent across multiple independent community sources, no single authoritative official doc for this exact scenario since it's application-level architecture, not a framework API)

<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->

## Conventions

Conventions not yet established. Will populate as patterns emerge during development.
<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->

## Architecture

Architecture not yet mapped. Follow existing patterns found in the codebase.
<!-- GSD:architecture-end -->

<!-- GSD:skills-start source:skills/ -->

## Project Skills

No project skills found. Add skills to any of: `.claude/skills/`, `.agents/skills/`, `.cursor/skills/`, `.github/skills/`, or `.codex/skills/` with a `SKILL.md` index file.
<!-- GSD:skills-end -->

<!-- GSD:workflow-start source:GSD defaults -->

## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:

- `/gsd-quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd-debug` for investigation and bug fixing
- `/gsd-execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->

<!-- GSD:profile-start -->

## Developer Profile

> Profile not yet configured. Run `/gsd-profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->

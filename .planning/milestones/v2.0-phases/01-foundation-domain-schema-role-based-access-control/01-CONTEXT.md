# Phase 1: Foundation — Domain Schema & Role-Based Access Control - Context

**Gathered:** 2026-07-15
**Status:** Ready for planning

<domain>
## Phase Boundary

This phase establishes the foundation everything else builds on: (1) the complete domain database schema — every table with correct foreign keys and the unique constraints later phases rely on — and (2) a two-role access-control layer (Lecturer / Student) sitting on top of the existing Breeze auth scaffold.

**In scope:** all domain migrations + Eloquent models with relationships; the `role` mechanism on users; role-gated route areas; registration locked to Student; role-based post-login routing; minimal test accounts to verify gating. Covers RBAC-01, RBAC-02, RBAC-03, RBAC-04.

**Out of scope (later phases):** any lecturer CRUD UI for classrooms/subjects/exams (Phase 2), exam assignment + per-record class-scoped Policies / IDOR enforcement (Phase 3 — RBAC-05 lives there), attempt-taking (Phase 4), grading (Phase 5), the full demo seeder + README (Phase 6). Building the tables here does NOT mean building their management UIs here.
</domain>

<decisions>
## Implementation Decisions

*(Auto mode: each gray area resolved with the recommended, research-grounded default. All align with PROJECT.md Key Decisions and research/SUMMARY.md.)*

### Schema scope
- **D-01:** Create the **entire** domain schema in this phase, not just RBAC tables — migrations for `classrooms`, `subjects`, `class_subject` (pivot), `exams`, `questions`, `options`, `exam_classroom` (pivot), `attempts`, `answers`, plus the `users` extension. Rationale: SUMMARY.md is schema-first, and ROADMAP Phase 1 success criterion #1 explicitly requires every domain table to exist after this phase. Later phases add behavior on a stable schema; they do not add tables.
- **D-02:** Bake required constraints into the migrations from the first version (not retrofitted later): `attempts` gets a **unique `(exam_id, user_id)`** constraint (single-attempt integrity), `answers` gets a **unique `(attempt_id, question_id)`**, and `options.is_correct` boolean. Retrofitting a unique constraint after duplicate rows exist is a costly repair (PITFALLS.md).
- **D-03:** Call `Schema::defaultStringLength(191)` (or use explicit lengths) to avoid the MySQL `utf8mb4` index-length trap on a clean `migrate:fresh` (PITFALLS.md, clean-clone).

### Role storage
- **D-04:** Store role as a **`role` column on `users`**, cast to a PHP backed enum `App\Enums\Role { Lecturer, Student }` via the Laravel 11 `casts()` method. No `spatie/laravel-permission` or any RBAC package — two fixed roles do not justify it (PROJECT.md Key Decision, STACK.md explicit avoid-list).

### Student ↔ classroom link
- **D-05:** A student belongs to **one** classroom: a nullable `classroom_id` foreign key on `users` (not a many-to-many). Matches the brief ("students grouped into classes") and REQUIREMENTS CLS-04/ASN-02. `classrooms` must be migrated before the `classroom_id` FK is added (migration ordering).

### Route / area separation
- **D-06:** Separate the two areas into **role-gated route groups** — lecturer routes under a `lecturer` prefix+name and student routes under a `student` prefix+name, each wrapped in `auth` + the role middleware. Organize as separate route files (`routes/lecturer.php`, `routes/student.php`) registered in `bootstrap/app.php`. (Leanest alternative — prefixed groups inside `web.php` — is acceptable if the planner prefers fewer files; the behavior, not the file count, is what matters.)

### Role middleware
- **D-07:** A custom middleware `EnsureUserHasRole` registered as a **route-middleware alias `role`** in `bootstrap/app.php` (Laravel 11 has no `app/Http/Kernel.php`). Usage: `->middleware('role:lecturer')`. Server-enforced 403/redirect for the wrong role — never rely on hiding nav links (RBAC-04).

### Post-login redirect
- **D-08:** After login, dispatch by role from the existing Breeze **`/dashboard`** route: Lecturers land on a lecturer home, Students on a student home. Implement by pointing `/dashboard` at a small controller/closure that redirects on `role`, rather than forking Breeze's auth controllers. Keeps the Breeze login flow untouched (RBAC-03).

### Registration lock
- **D-09:** Public registration always creates a **Student**: hardcode `role => Role::Student` in Breeze's `RegisteredUserController@store` and ignore any client-supplied role. There is no public path to a Lecturer account (RBAC-02). Lecturers are provisioned by seeding.

### Test accounts (phase-scoped)
- **D-10:** Seed a **minimal** pair — one Lecturer and one Student (assigned to a classroom) — so the role gating and redirects (success criteria #2–4) are actually verifiable this phase. This is deliberately thin; the full reviewable demo dataset + README is Phase 6 (DEL-01/DEL-02). Use `firstOrCreate` on fixed emails so it is idempotent and safe to re-run.

### Claude's Discretion
- Exact column names/types beyond those fixed above, model file organization, factory shapes, and whether route areas live in separate files vs grouped in `web.php` — planner/executor choice, provided the decisions above hold.
</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase scope & requirements
- `.planning/ROADMAP.md` §"Phase 1" — goal, the 4 success criteria (incl. "every domain table exists" and server-side role blocking)
- `.planning/REQUIREMENTS.md` — RBAC-01..04 (this phase); RBAC-05 is Phase 3, not here
- `.planning/PROJECT.md` §"Key Decisions" — role-via-column, MySQL, build-on-Breeze

### Architecture & stack (schema + RBAC)
- `.planning/research/ARCHITECTURE.md` — the 9-table data model with fields/FKs/relationships, role middleware + Policy scaffolding, Breeze/User integration, dependency-ordered build sequence
- `.planning/research/STACK.md` — Laravel 11 specifics (`bootstrap/app.php` middleware, no `Kernel.php`), backed-enum casts, explicit "do not install" list
- `.planning/research/SUMMARY.md` §"Implications for Roadmap" (Phase 1 & 2) and §"Gaps to Address" (Classroom naming standard)
- `.planning/research/PITFALLS.md` — migration/seeder clean-clone (utf8mb4 index length), unique-constraint-from-first-version

### Existing scaffold (build on, don't replace)
- `routes/auth.php`, `app/Http/Controllers/Auth/RegisteredUserController.php` — Breeze auth to extend (registration role lock)
- `app/Models/User.php` — add `role` cast + `classroom_id` + relationships
- `bootstrap/app.php` — where the `role` middleware alias is registered
- `resources/views/dashboard.blade.php`, `resources/views/layouts/` — Breeze dashboard/layout to branch by role
</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **Breeze auth** (register/login/logout/reset/verify/profile) — fully scaffolded; reuse as-is, only extend registration to set role and dashboard to route by role.
- **`App\Models\User`** — the anchor model; extend with `role` cast, `classroom_id`, and `classroom()` / (later) `attempts()` relationships.
- **Breeze Blade layouts + Tailwind + Alpine + Vite** — already configured; role dashboards reuse `x-app-layout` and existing nav components.
- **`database/migrations/0001_01_01_000000_create_users_table.php`** — existing users migration; add role/classroom_id via a **new** migration (ordered after `classrooms`) rather than editing Breeze's original.

### Established Patterns
- **Laravel 11 skeleton** — middleware aliases in `bootstrap/app.php`, scheduled tasks in `routes/console.php`, no `Kernel.php`. Do not follow Laravel-10-era tutorials for these.
- **Convention-over-config** — resource controllers, form requests, Eloquent relationships; no repository/service layer for plain CRUD.

### Integration Points
- `bootstrap/app.php` — register `role` middleware alias; optionally load `routes/lecturer.php` / `routes/student.php`.
- Breeze `RegisteredUserController@store` — force `role = Student`.
- `/dashboard` route (currently a simple view) — becomes the role-dispatch point.
- `database/seeders/DatabaseSeeder.php` — add minimal lecturer/student test accounts.
</code_context>

<specifics>
## Specific Ideas

- Class-group entity is named **`Classroom`** (table `classrooms`) everywhere — never `SchoolClass`/`ClassGroup`/`Cohort` (SUMMARY.md resolved naming gap).
- `questions` uses a single table with a `type` discriminator (`mcq` | `open`) cast to a backed enum, plus a `points` column defaulting to 1.
</specifics>

<deferred>
## Deferred Ideas

- **RBAC-05 (per-record class-scoped Policies / IDOR denial)** — belongs to Phase 3, where exam/attempt/result records first become reachable. Phase 1 only establishes the coarse role gate.
- **Full demo seeder + README (DEL-01/DEL-02)** — Phase 6. Phase 1 seeds only the minimal accounts needed to verify gating.
- **Any lecturer management UI (classrooms/subjects/exams)** — Phase 2. Phase 1 creates the tables but no CRUD screens.

None of the above were scope creep from the user — they are the natural boundaries between phases, noted so the planner does not pull them forward.
</deferred>

---

*Phase: 1-Foundation — Domain Schema & Role-Based Access Control*
*Context gathered: 2026-07-15*

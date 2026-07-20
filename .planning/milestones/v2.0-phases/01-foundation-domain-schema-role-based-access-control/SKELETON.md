# Walking Skeleton — Online Examination Portal

**Phase:** 1
**Generated:** 2026-07-15

## Phase Goal (MVP user story)

**As a** person using the exam portal, **I want to** self-register (always as a Student), log in, and land in my own role-appropriate area — with lecturer-only pages blocked for students server-side — **so that** the two-role foundation everything else is built on is provably enforced end-to-end.

> Foundation/skeleton reconciliation: the walking skeleton is the **RBAC vertical loop** above (the thinnest end-to-end proof the full stack works). Separately, this phase also lays the complete **9-table domain schema** as its foundation (ROADMAP success criterion #1 — non-negotiable), because later phases add behavior on a stable schema rather than adding tables. The migrations deliver the schema; the RBAC loop is the skeleton.

## Capability Proven End-to-End

A visitor registers through the public Breeze form and is always created as a **Student**; after logging in they are redirected to the student area; and if they hand-type a `/lecturer` URL they are blocked server-side with a 403 — while a seeded Lecturer account logs in and lands in the lecturer area. This exercises routing → role middleware → Eloquent enum on `users` → MySQL read/write → a rendered Blade landing page.

## Architectural Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Framework | Laravel 11.55 + Breeze 2.4 (Blade + Tailwind + Alpine + Vite), already scaffolded | Mandated by the brief; build on the scaffold, do not replace it (PROJECT.md) |
| Data layer | MySQL 8.0.45 (`yp-student-exam` via Herd) + Eloquent; plain relational tables | Already configured/live; no MySQL-specific features needed for this schema |
| Role model | `role` string column on `users` cast to native backed enum `App\Enums\Role` via Laravel 11 `casts()`; nullable `classroom_id` FK for the student↔classroom link | Two fixed roles do not justify `spatie/laravel-permission` (D-04); one classroom per student (D-05) |
| Access control (coarse) | Custom `EnsureUserHasRole` middleware aliased `role` in `bootstrap/app.php`; role-gated route groups `routes/lecturer.php` / `routes/student.php` (`auth` + `verified` + `role:*`) | Laravel 11 has no `Kernel.php`; group-level middleware means new routes inherit the gate (D-06/D-07). Record-level Policies (IDOR) deferred to Phase 3 |
| Auth flow | Breeze session auth kept intact; `/dashboard` repointed to a `DashboardController` that redirects by role; registration hardcodes `role => Role::Student` | Keeps the Breeze login/register flow untouched (D-08); no public path to a Lecturer account (D-09) |
| Deployment target | Local: Herd + MySQL; `php artisan migrate:fresh --seed` then serve; full suite via `php artisan test` | Graded deliverable reproduced from a clean clone; queue-free by design (later phases) |
| Directory layout | `app/Enums/*`, `app/Models/*` (one model per domain concept), `app/Http/Middleware/EnsureUserHasRole`, split `routes/lecturer.php` + `routes/student.php`, `resources/views/{lecturer,student}/*` | Mirrors the two-role boundary in the filesystem; convention-over-config, no repository/service layer for plain CRUD |

## Stack Touched in Phase 1

- [x] Project scaffold — reuse existing Breeze scaffold (framework, build, lint, PHPUnit); portability guard `Schema::defaultStringLength(191)` added
- [x] Routing — real role-gated routes: `/dashboard` (redirect), `/lecturer`, `/student`
- [x] Database — real writes (10 migrations, seeded Lecturer + Student + Classroom) AND real reads (login → role lookup → redirect; middleware role check)
- [x] UI — interactive: public registration form (existing) creates a Student; two role landing views the redirect lands on
- [x] Deployment — documented local full-stack run: `php artisan migrate:fresh --seed`, log in as seeded accounts, `php artisan test`

## Out of Scope (Deferred to Later Slices)

- Lecturer CRUD UIs for classrooms / subjects / exams / questions / options — **Phase 2** (tables exist now, no management screens)
- Exam→classroom assignment + per-record class-scoped Policies / IDOR denial (RBAC-05) — **Phase 3**
- Attempt-taking: server-anchored timer, single-attempt enforcement in the flow, autosave, no-answer-leakage — **Phase 4** (the `unique(exam_id,user_id)` constraint is provisioned now; the flow that relies on it is Phase 4)
- Auto-grading MCQ + manual grading open-text + results — **Phase 5**
- Full reviewable demo dataset + README — **Phase 6** (this phase seeds only a minimal verified Lecturer/Student pair for gating verification)
- Any record-level authorization, Policies, Gates, or Services (`AttemptGrader`) — not scaffolded prematurely

## Subsequent Slice Plan

Each later phase adds one vertical slice on top of this skeleton without altering its architectural decisions:

- **Phase 2:** Lecturer builds the teaching content model — classrooms, subjects, class-subject links, rosters, and complete exams (MCQ + open-text).
- **Phase 3:** Exams are assigned to classrooms and only the right students can reach them (adds Policies / class-scoped access, RBAC-05).
- **Phase 4:** A student's timed exam attempt is captured reliably, once, without leaking answers.
- **Phase 5:** MCQ auto-grades, lecturers grade open-text, and both roles see accurate results.
- **Phase 6:** A clean clone stands up a working, populated demo with a documented README.

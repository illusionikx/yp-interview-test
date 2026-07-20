# Phase 1: Foundation — Domain Schema & Role-Based Access Control - Research

**Researched:** 2026-07-15
**Domain:** Laravel 11 relational schema design + coarse route-group RBAC on top of an existing Breeze scaffold
**Confidence:** HIGH

## Summary

This phase has two deliverables that are both standard, well-documented Laravel 11 patterns with no novel API surface: (1) the complete domain schema — 9 new tables plus a `users` extension, all migrations, all Eloquent models with relationships, and (2) a coarse, two-role (Lecturer/Student) access-control layer built entirely from Laravel-native primitives (a `role` column cast to a backed PHP enum, a custom `role` middleware alias registered in `bootstrap/app.php`, and role-gated route groups) — no new Composer packages. Every architectural decision (schema shape, role storage, middleware registration location, registration lock, post-login redirect) is already locked in `01-CONTEXT.md` (D-01 through D-10); this research focuses on **how** to implement each decision correctly against the actual installed stack, and on codebase-specific facts discovered by inspecting this repository directly (not just the four prior research docs).

Direct inspection of the running project confirms the prior research's assumptions are accurate: Laravel Framework **11.55.0** is installed (composer.json requires `^11.31`), Breeze 2.4's default scaffold is untouched (`RegisteredUserController@store` still does a plain `User::create($request->only(...))`, `routes/web.php` has a bare `/dashboard` closure view, `bootstrap/app.php`'s `withMiddleware()` closure is empty), MySQL **8.0.45** is live and reachable at `127.0.0.1:3306` with the `yp-student-exam` database already containing the 9 framework tables (cache, jobs, sessions, users, etc.) and zero domain tables — this phase starts from a genuinely clean schema slate. One codebase-specific risk not covered by the four prior research files was found during this research: Breeze's default `dashboard` route and the recommended lecturer/student route groups carry the `verified` middleware, and the phase's own D-10 test accounts are seeded (not registered) — a seeded user's `email_verified_at` must be explicitly set, or the seeded Lecturer/Student accounts will be blocked by `verified` and success criteria #3/#4 will be unverifiable. See Common Pitfalls.

**Primary recommendation:** Build the schema first (migrations timestamped parent-before-child, `Schema::defaultStringLength(191)` set defensively even though MySQL 8 doesn't strictly require it), then the `Role` backed enum + `casts()` on `User`, then `EnsureUserHasRole` middleware aliased in `bootstrap/app.php`, then the two route-group files, then the registration hardcode and dashboard-redirect controller, then the seeder — in that dependency order, exactly as ARCHITECTURE.md's build order and SUMMARY.md's roadmap already specify.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Domain schema (9 tables + FKs + unique constraints) | Database | Backend (migrations are backend code, but the constraint enforcement lives in MySQL) | MySQL is the last-resort integrity guarantee for the single-attempt/single-answer uniqueness rules; migrations are the backend artifact that provisions it |
| Role storage (`role` column, `classroom_id` FK) | Database | Backend (Eloquent cast, helper methods) | Column lives in `users`; the backed-enum cast and `isLecturer()`/`isStudent()` helpers are a thin backend-only convenience layer over it |
| Route-group role gating (`role:lecturer`/`role:student` middleware) | Backend | — | Coarse, route-group-level access control belongs entirely server-side; there is no client tier for this phase (no lecturer/student CRUD UI yet — RBAC-04 is purely "can this account type reach this URL") |
| Registration role lock (public register always → Student) | Backend | — | `RegisteredUserController@store` is a server-side controller; the fix is to never read `role` from request input, not a client-side change |
| Post-login role-based redirect | Backend (Frontend Server / SSR equivalent in a Blade monolith) | Browser (final rendered landing page) | Laravel's Blade rendering is server-side; the `/dashboard` redirect decision is made entirely in a controller before any HTML is sent — there is no distinct "frontend server" tier in this monolith, so Backend/SSR is the correct single owner |
| Test-account seeding (D-10) | Database | Backend (seeder code) | The seeder is backend code, but its purpose is purely to populate rows for the verification steps below — no runtime behavior depends on it beyond fixture data |

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|---------------|
| Laravel Framework | 11.55.0 installed (composer.json `^11.31`) [VERIFIED: `php artisan --version` in this repo] | Application framework | Mandated; already installed and running against MySQL 8.0.45 in this repo |
| Breeze | 2.4 (composer.json `^2.4`, dev dependency) [VERIFIED: composer.json read directly] | Auth scaffold | Register/login/logout/password-reset/verification/profile fully scaffolded and unmodified — build on it, do not replace |
| MySQL | 8.0.45 [VERIFIED: `php artisan db:show` — live connection to `yp-student-exam`, 9 tables, 144 KB, confirmed reachable] | Persistence | Already configured in `.env` and connected; no MySQL-specific features required for this schema |
| PHP | 8.2.32 [VERIFIED: `php --version`] | Runtime | Matches composer.json's `^8.2` constraint |
| Blade + Tailwind + Alpine + Vite | as scaffolded [VERIFIED: `resources/views/`, `vite.config.js`, `package.json` present] | Views | Sufficient for this phase's minimal dashboard-redirect view changes; no new frontend tooling needed |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| *(none — no new Composer packages for this phase)* | — | — | Role storage, casting, middleware, and Policy scaffolding are all Laravel-native. See "Package Legitimacy Audit" below. |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `role` string column cast to a native backed enum + custom middleware | `spatie/laravel-permission` | Only worth it if roles/permissions become dynamic/admin-editable at runtime, or a third role with fine-grained permissions is added later (see STACK.md §"Stack Patterns by Variant"). Two fixed roles assigned once at account creation do not justify the package's migrations/cache layer — explicitly excluded by D-04 and PROJECT.md. |
| `role:lecturer`/`role:student` custom middleware | Laravel's built-in `can:` middleware exclusively | Would work if every access rule were record-scoped, but this phase needs whole-route-group gating ("the entire /lecturer area") before any individual record exists to scope a Policy against — the custom middleware is the correct tool for D-06/D-07. |

**Installation:**
```bash
# No new packages required for this phase.
# All mechanisms (Role enum, casts(), custom middleware, Policy auto-discovery)
# ship with laravel/framework ^11.31, already in composer.json.
```

**Version verification:** Laravel Framework version confirmed directly against this repo via `php artisan --version` → `Laravel Framework 11.55.0` [VERIFIED: command run in this session]. Breeze version confirmed via direct read of `composer.json` → `laravel/breeze": "^2.4"` [VERIFIED: file read]. No package versions in this research rely on training-data recall alone.

## Package Legitimacy Audit

**No new external packages are installed by this phase.** D-04 (CONTEXT.md) and STACK.md both explicitly rule out `spatie/laravel-permission` or any other RBAC package — the entire feature set (role storage, casting, middleware, route grouping, Policy auto-discovery) uses mechanisms already shipped in `laravel/framework`, which is already present in this project's `composer.lock`.

| Package | Registry | Age | Downloads | Source Repo | Verdict | Disposition |
|---------|----------|-----|-----------|--------------|---------|-------------|
| `laravel/framework` | Packagist | pre-existing (installed) | N/A — not a new install | github.com/laravel/framework | OK | Already present, not installed by this phase |
| `laravel/breeze` | Packagist | pre-existing (installed) | N/A — not a new install | github.com/laravel/breeze | OK | Already present, not installed by this phase |

**Packages removed due to [SLOP] verdict:** none.
**Packages flagged as suspicious [SUS]:** none.

*No packages discovered via WebSearch or training data are proposed for installation in this phase. If the planner later decides a package IS needed (deviating from this research), it must be run through the full Package Legitimacy Gate before being added to a plan.*

## Architecture Patterns

### System Architecture Diagram

```
                         ┌────────────────────────────────────────┐
                         │        Browser (Blade forms)            │
                         │  /register  /login  /dashboard  /lecturer/*  /student/*
                         └───────────────────┬──────────────────────┘
                                             │ HTTP (session, CSRF)
                    ┌────────────────────────▼─────────────────────────┐
                    │                  routes/*.php                     │
                    │  web.php: /register, /login, /dashboard (redirect)│
                    │  lecturer.php: prefix('lecturer') + role:lecturer │
                    │  student.php:  prefix('student')  + role:student  │
                    └────────────────────────┬─────────────────────────┘
                                             │
        ┌────────────────────────────────────┼───────────────────────────────┐
        │                                    │                               │
┌───────▼────────────┐        ┌─────────────▼───────────┐      ┌───────────▼────────────┐
│ RegisteredUser      │        │  EnsureUserHasRole        │      │  DashboardController /  │
│ Controller@store    │        │  middleware (alias "role")│      │  closure                │
│ — hardcodes          │        │  registered in            │      │  — reads auth()->user()  │
│   role = Student     │        │  bootstrap/app.php        │      │    ->role, redirects to  │
│   (ignores any        │        │  — 403/redirect on         │      │    lecturer.home or      │
│   client role field) │        │    role mismatch           │      │    student.home          │
└───────┬─────────────┘        └─────────────┬────────────┘      └───────────┬────────────┘
        │                                    │ (blocks wrong role,           │
        │                                    │  never hides-only)           │
        └────────────────┬───────────────────┴───────────────────────────────┘
                         │
                 ┌───────▼─────────────────────────────────────────────┐
                 │             Eloquent Models (app/Models)              │
                 │  User(role: Role enum, classroom_id) ─ Classroom       │
                 │  ─ Subject ─ Exam ─ Question ─ Option                  │
                 │  ─ Attempt ─ Answer                                    │
                 └───────┬───────────────────────────────────────────────┘
                         │
                 ┌───────▼───────────────────────────────────────────────┐
                 │                     MySQL (yp-student-exam)             │
                 │ users(role, classroom_id) classrooms subjects           │
                 │ class_subject exams questions options exam_classroom    │
                 │ attempts(unique exam_id,user_id) answers(unique          │
                 │ attempt_id,question_id)                                 │
                 └─────────────────────────────────────────────────────────┘
```

A reader can trace both required flows end-to-end: **registration** enters at `/register`, is force-set to `Student` in the controller (never trusting client input), persists to `users`; **role-gated access** enters any `/lecturer/*` or `/student/*` URL, passes through the `role` middleware alias defined once in `bootstrap/app.php`, and is rejected server-side (403/redirect) before any controller code runs if the role doesn't match — satisfying RBAC-04's "server-enforced, not just hidden" requirement.

### Recommended Project Structure

```
app/
├── Enums/
│   └── Role.php                       # enum Role: string { case Lecturer = 'lecturer'; case Student = 'student'; }
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── RegisteredUserController.php   # EDIT: hardcode role => Role::Student
│   │   └── DashboardController.php             # NEW: redirect by role (replaces web.php closure)
│   └── Middleware/
│       └── EnsureUserHasRole.php               # NEW: role:lecturer / role:student
└── Models/
    ├── User.php            # EDIT: casts() adds 'role' => Role::class; classroom() relation; isLecturer()/isStudent()
    ├── Classroom.php        # NEW
    ├── Subject.php          # NEW
    ├── Exam.php             # NEW
    ├── Question.php         # NEW
    ├── Option.php           # NEW
    ├── Attempt.php          # NEW
    └── Answer.php           # NEW

database/
├── migrations/
│   ├── xxxx_01_create_classrooms_table.php
│   ├── xxxx_02_create_subjects_table.php
│   ├── xxxx_03_add_role_and_classroom_id_to_users_table.php   # after classrooms (FK dependency)
│   ├── xxxx_04_create_class_subject_table.php
│   ├── xxxx_05_create_exams_table.php
│   ├── xxxx_06_create_questions_table.php
│   ├── xxxx_07_create_options_table.php
│   ├── xxxx_08_create_exam_classroom_table.php
│   ├── xxxx_09_create_attempts_table.php       # unique(exam_id, user_id)
│   └── xxxx_10_create_answers_table.php        # unique(attempt_id, question_id)
├── factories/
│   ├── ClassroomFactory.php    # minimal — needed so the student test account can attach to a classroom
│   └── (SubjectFactory/ExamFactory/etc. — only if the plan chooses to stub them now; not required by this phase's scope)
└── seeders/
    └── DatabaseSeeder.php      # EDIT: firstOrCreate one Lecturer + one Student (classroom-attached), both with email_verified_at set

routes/
├── web.php       # dashboard route becomes DashboardController@index; requires lecturer.php/student.php
├── lecturer.php   # NEW: Route::middleware(['auth','verified','role:lecturer'])->prefix('lecturer')->name('lecturer.')->group(...)
└── student.php    # NEW: Route::middleware(['auth','verified','role:student'])->prefix('student')->name('student.')->group(...)

bootstrap/
└── app.php        # EDIT: ->withMiddleware(fn ($middleware) => $middleware->alias(['role' => EnsureUserHasRole::class]))
```

### Structure Rationale

- **`app/Enums/Role.php` as a native backed enum, not a package or plain string constants:** Laravel 11's `casts()` method gives type-safe `$user->role === Role::Lecturer` comparisons and IDE autocomplete for zero extra dependency [CITED: laravel.com/docs/11.x/eloquent-mutators].
- **New migration for `role`/`classroom_id`, not editing Breeze's original `create_users_table`:** the Breeze-generated migration should stay byte-for-byte as scaffolded so future Breeze upgrades/diffs stay clean; this is standard Laravel practice for extending a vendor-generated table.
- **`classrooms` migrated before the `users` role/classroom_id migration:** D-05 requires `classroom_id` to be a `foreignId()->constrained('classrooms')`, which fails at migrate time if `classrooms` doesn't exist yet — migration timestamps must be ordered accordingly (classrooms first, then the users-extension migration).
- **Route files split by role area (`routes/lecturer.php`, `routes/student.php`):** matches D-06's stated preference and ARCHITECTURE.md's structure rationale — declares the `role:*` middleware group once per file instead of per-route. D-06 explicitly also permits grouping inline in `web.php` if the planner prefers fewer files; either is acceptable.
- **No `app/Policies/*` in this phase's file tree:** per CONTEXT.md's explicit scope boundary, record-level Policies (`ExamPolicy`, `AttemptPolicy`, etc.) are Phase 3's RBAC-05 concern. This phase's access control is coarse (route-group only) — do not scaffold Policy classes prematurely; there are no records yet for them to authorize.

### Pattern 1: Backed enum role column with `casts()`

**What:** `role` column (string) on `users`, backed by `App\Enums\Role: string { case Lecturer = 'lecturer'; case Student = 'student'; }`, cast via the model's `casts()` method (Laravel 11's current convention, not the older `$casts` property).
**When to use:** Any small, fixed set of mutually-exclusive states stored as a string column — exactly this project's two roles.
**Example:**
```php
// Source: https://laravel.com/docs/11.x/eloquent-mutators (official docs, fetched this session)
// app/Enums/Role.php
enum Role: string
{
    case Lecturer = 'lecturer';
    case Student = 'student';
}

// app/Models/User.php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => Role::class,
    ];
}

public function isLecturer(): bool { return $this->role === Role::Lecturer; }
public function isStudent(): bool { return $this->role === Role::Student; }

public function classroom(): BelongsTo
{
    return $this->belongsTo(Classroom::class);
}
```

### Pattern 2: Custom middleware alias registered in `bootstrap/app.php` (no `Kernel.php` in Laravel 11)

**What:** `EnsureUserHasRole` middleware receives a role-name parameter, registered as an alias so routes use `->middleware('role:lecturer')`.
**When to use:** Whole-route-group gating by account type — this project's lecturer-area vs. student-area split.
**Example:**
```php
// Source: https://laravel.com/docs/11.x/middleware (official docs, fetched this session)
// app/Http/Middleware/EnsureUserHasRole.php
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user() || $request->user()->role->value !== $role) {
            abort(403);
        }
        return $next($request);
    }
}

// bootstrap/app.php
use App\Http\Middleware\EnsureUserHasRole;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// routes/lecturer.php
Route::middleware(['auth', 'verified', 'role:lecturer'])
    ->prefix('lecturer')->name('lecturer.')
    ->group(function () {
        Route::get('/', fn () => view('lecturer.home'))->name('home');
    });
```
This is a direct, minimal edit to the currently-empty `->withMiddleware(function (Middleware $middleware) { // })` closure in this repo's actual `bootstrap/app.php` [VERIFIED: file read this session].

### Pattern 3: Registration role lock — never trust client input for `role`

**What:** `RegisteredUserController@store` hardcodes `role => Role::Student` and never reads a `role` field from the request, regardless of what the client POSTs.
**When to use:** Any registration form where privilege must never be client-selectable (RBAC-02).
**Example:**
```php
// app/Http/Controllers/Auth/RegisteredUserController.php
$user = User::create([
    'name' => $request->name,
    'email' => $request->email,
    'password' => Hash::make($request->password),
    'role' => Role::Student,           // never $request->role — no client-supplied role path exists
]);
```
This directly patches the actual `RegisteredUserController::store` method read from this repo this session [VERIFIED: file read] — today it is the unmodified Breeze scaffold with no `role` field at all, so this is a pure addition, not a conflict with existing logic. `role` must also be added to `User::$fillable` for this create to work (see Common Pitfalls — mass assignment).

### Pattern 4: Post-login role-based redirect at `/dashboard`

**What:** Replace the `/dashboard` closure in `routes/web.php` with a controller that redirects by role, per D-08.
**When to use:** Any two-audience app sharing a single Breeze auth flow.
**Example:**
```php
// app/Http/Controllers/DashboardController.php
class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return $request->user()->isLecturer()
            ? redirect()->route('lecturer.home')
            : redirect()->route('student.home');
    }
}

// routes/web.php
Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
```
Login/registration both already redirect to `route('dashboard', absolute: false)` [VERIFIED: `RegisteredUserController::store` and Breeze's `AuthenticatedSessionController` both reference this route name] — pointing `dashboard` at a redirecting controller keeps every existing Breeze auth flow (login, register, password reset landing) untouched, satisfying D-08's "keep the Breeze login flow untouched."

### Pattern 5: Policy auto-discovery (informational — not built this phase)

**What:** Laravel 11 auto-discovers a `Policy` class named `{Model}Policy` in `app/Policies` for a model of the same name in `app/Models`, no manual `Gate::policy()` registration needed [CITED: laravel.com/docs/11.x/authorization, fetched this session].
**When to use:** Not this phase — flagged here only so the planner does not accidentally scaffold empty Policy classes now. Phase 3 (RBAC-05) will introduce `ExamPolicy`/`AttemptPolicy`/etc. and they will auto-register with zero config, provided they follow this naming convention.

### Anti-Patterns to Avoid

- **Editing Breeze's original `0001_01_01_000000_create_users_table.php` migration to add `role`/`classroom_id` directly:** breaks the clean separation between vendor-scaffolded and app-specific migrations; use a new migration instead (per canonical_refs in CONTEXT.md).
- **Scattering `if ($user->role === 'lecturer')` string comparisons across controllers/Blade:** duplicates the authorization rule and is easy to miss in one spot. Gate the route group once via middleware; use the `isLecturer()`/`isStudent()` helpers everywhere else, never raw string/enum comparisons inline.
- **Reading `role` from `$request->all()` or any client input during registration:** this is exactly the public-lecturer-registration security hole RBAC-02 exists to close (also see PITFALLS.md's mass-assignment section) — the value must be a server-side constant.
- **Scaffolding Policy classes in this phase "to be ready for later":** out of scope per CONTEXT.md's explicit deferral of RBAC-05 to Phase 3; adds files with no behavior to review/verify this phase.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Two fixed user roles | A custom roles/permissions table + pivot | `role` string column + native backed enum cast | Two roles, fixed at build time, never reassigned via UI — a package's migrations/cache layer buys nothing (D-04, explicit project decision) |
| Route-group access gating | Ad hoc `if` checks inside every controller action | One `EnsureUserHasRole` middleware aliased once in `bootstrap/app.php`, applied at the route-group level | Centralizes the rule in one place; a controller action can never "forget" the check if it's declared on the group, not per-action |
| Composite unique DB constraints (`attempts`, `answers`) | Application-level "check then insert" existence checks | `$table->unique([...])` in the migration itself, from the first version | An app-level check has a race window (two tabs, double-click); the DB unique index is the only race-safe guarantee, and retrofitting it after duplicate rows exist is a much costlier repair (PITFALLS.md, D-02) |
| `class` as an entity name | `SchoolClass`/`ClassGroup`/`Cohort` model names (seen inconsistently across the four prior research docs) | `Classroom` / table `classrooms` | `class` is a reserved PHP keyword; `Classroom` is the SUMMARY.md-resolved standard name — do not let `SchoolClass` resurface from STACK.md during planning |

**Key insight:** Every mechanism this phase needs — enum-backed columns, custom middleware aliases, Policy auto-discovery, unique composite indexes — ships in `laravel/framework` itself. The discipline required is *where* to put each rule (route-group middleware vs. model helper vs. DB constraint), not *what library* to reach for.

## Common Pitfalls

### Pitfall 1: Seeded test accounts blocked by the `verified` middleware

**What goes wrong:** D-10 requires seeding a Lecturer and a Student account directly (not via the registration flow) so gating/redirects are verifiable. If the seeder doesn't explicitly set `email_verified_at`, and the lecturer/student route groups carry Breeze's `verified` middleware (as ARCHITECTURE.md recommends, and as the existing `/dashboard` route already does — `->middleware(['auth', 'verified'])` [VERIFIED: `routes/web.php` read this session]), then logging in as the seeded lecturer or student redirects to Breeze's "verify your email" notice screen instead of the lecturer/student area — silently breaking success criteria #3 and #4 even though the middleware/redirect code is otherwise correct.
**Why it happens:** `UserFactory`'s default state already sets `email_verified_at => now()` [VERIFIED: `database/factories/UserFactory.php` read this session] so this is easy to miss if the seeder is written with `User::factory()->create([...])` and someone later strips `email_verified_at` while customizing the lecturer/student states, or if `firstOrCreate` is used with a state that doesn't inherit the factory default.
**How to avoid:** When seeding the D-10 test accounts, explicitly confirm (via `User::factory()->create()` or `firstOrCreate` with an explicit `email_verified_at => now()`) that both accounts have a verified timestamp. Manually verify by logging in as each seeded account and confirming no "please verify your email" redirect appears.
**Warning signs:** Logging in as the seeded lecturer/student lands on `verification.notice` instead of the expected role area.

### Pitfall 2: Migration/seeder failing on a genuinely clean MySQL schema (utf8mb4 key length)

**What goes wrong:** A fresh `php artisan migrate:fresh` against an empty MySQL schema throws "Specified key was too long; max key length is 767 bytes" on any indexed/unique `varchar(255)` column under `utf8mb4`.
**Why it happens:** This is only a real risk on MySQL <5.7.7/MariaDB <10.2.2 — this project's actual MySQL is **8.0.45** [VERIFIED: `php artisan db:show`], which raises the default index-prefix limit and does **not** hit this error under normal circumstances [CITED: laravel-news.com/laravel-5-4-key-too-long-error, cross-checked via WebSearch this session]. However, D-03 (CONTEXT.md) still requires calling `Schema::defaultStringLength(191)` defensively — this is correct as a portability safeguard (e.g., if the grader's environment or CI ever runs an older MySQL/MariaDB), not because this specific installed DB currently needs it.
**How to avoid:** Add `Schema::defaultStringLength(191);` in `AppServiceProvider::boot()` (currently an empty method [VERIFIED: file read this session]) regardless of whether the local MySQL 8.0.45 strictly requires it — it's a zero-cost, standard defensive line per D-03.
**Warning signs:** `migrate:fresh` fails with a 1071 "Specified key was too long" error on any environment other than this exact one.

### Pitfall 3: Mass-assignment gap after adding `role`/`classroom_id` to `User`

**What goes wrong:** The current `User::$fillable` is `['name', 'email', 'password']` [VERIFIED: `app/Models/User.php` read this session]. If `role` and `classroom_id` aren't added to `$fillable`, `User::create([...'role' => Role::Student...])` in the (edited) `RegisteredUserController` silently drops the `role` value, leaving every new user at the column's DB default — likely breaking RBAC-02 in a way that's easy to miss because registration still "succeeds," just with the wrong role.
**Why it happens:** Adding a new migration column doesn't automatically make it mass-assignable; `$fillable` must be updated in the same change.
**How to avoid:** When extending `User`, explicitly add `role` to `$fillable` (and decide whether `classroom_id` needs to be — likely yes, for the seeder's student-account creation, but it should never be settable from the public registration form's request input). Write a feature test asserting `User::factory()->create(['role' => Role::Lecturer])->role === Role::Lecturer` survives a full `create()` round-trip.
**Warning signs:** A seeded/created user's `role` column silently reverts to the migration's default value instead of the value passed to `create()`.

### Pitfall 4: Retrofitting the unique constraints instead of baking them in from the first migration

**What goes wrong:** If `attempts` or `answers` migrations are written without their unique constraints and the constraint is "added later," any duplicate rows created in the meantime (e.g., during manual testing) make the later `ALTER TABLE ... ADD UNIQUE` fail outright.
**Why it happens:** It's tempting to get the happy-path schema working first and add integrity constraints as a follow-up.
**How to avoid:** D-02 already mandates this — `$table->unique(['exam_id', 'user_id'])` on `attempts` and `$table->unique(['attempt_id', 'question_id'])` on `answers` must be present in each table's **first** migration version, not added in a later one. Verify with a fresh-DB test (see Validation Architecture) rather than eyeballing the migration file.
**Warning signs:** A second migration file exists solely to `ALTER TABLE` and add a unique index that should have been in the `create` migration.

### Pitfall 5: `class_subject`/`exam_classroom` pivot naming or column-order mismatches with Eloquent's `belongsToMany` conventions

**What goes wrong:** Laravel's `belongsToMany` defaults assume a pivot table named as the two related tables' singular forms in alphabetical order (`class_subject`, `exam_classroom` — both already correctly ordered per ARCHITECTURE.md) and foreign key columns named `{singular_model}_id`. Deviating from this (e.g., `classroom_subject` instead of `class_subject`, despite the entity being `Classroom`) forces every relationship definition to pass explicit table/key overrides, which is easy to get wrong once and never notice until a many-to-many query silently returns nothing.
**Why it happens:** ARCHITECTURE.md predates the SUMMARY.md `Classroom` naming resolution and still calls the pivot `class_subject` (not `classroom_subject`) — this is intentional (Laravel's convention derives the pivot name from the *table* names, and `classrooms`/`subjects` alphabetically would actually suggest `classroom_subject`, but ARCHITECTURE.md's `class_subject` was written under an earlier `SchoolClass`/`class` naming attempt). This is a genuine naming inconsistency between two locked research documents.
**How to avoid:** Since D-01 defers exact column/table naming beyond the fixed decisions to planner/executor discretion, and the entity is `Classroom`/`classrooms` (SUMMARY.md, D-01's canonical_refs), the pivot table should be named to match Eloquent's convention for the actual model names in use: `classroom_subject` (alphabetical singular of `Classroom`+`Subject`) is the Eloquent-idiomatic default and avoids the need for an explicit `belongsToMany(..., 'class_subject')` table-name override. If the plan prefers to keep the shorter `class_subject`/`exam_classroom` names from ARCHITECTURE.md for brevity, that's acceptable too — but every `belongsToMany()` call must then explicitly pass the pivot table name and both foreign keys, since Eloquent's naming inference will not find `class_subject` on its own for a `Classroom` model.
**Warning signs:** `$classroom->subjects` or `$exam->classrooms` returns an empty collection despite matching rows existing in the pivot table — almost always a silent naming-convention mismatch, not a query bug.

## Code Examples

### Migration: `classrooms` (must precede the users-extension migration)
```php
// Source: pattern per ARCHITECTURE.md + Laravel 11 migration conventions
Schema::create('classrooms', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->timestamps();
});
```

### Migration: extending `users` (new migration, not editing Breeze's original)
```php
// database/migrations/xxxx_xx_xx_add_role_and_classroom_id_to_users_table.php
Schema::table('users', function (Blueprint $table) {
    $table->string('role')->default('student');
    $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
});
```

### Migration: `attempts` — the single-attempt integrity guarantee
```php
Schema::create('attempts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('submitted_at')->nullable();
    $table->string('status')->default('in_progress');
    $table->decimal('score', 6, 2)->nullable();
    $table->timestamps();

    $table->unique(['exam_id', 'user_id']); // D-02 — baked in from v1
});
```

### Migration: `answers` — the per-question-per-attempt integrity guarantee
```php
Schema::create('answers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
    $table->foreignId('question_id')->constrained()->cascadeOnDelete();
    $table->foreignId('selected_option_id')->nullable()->constrained('options')->cascadeOnDelete();
    $table->text('answer_text')->nullable();
    $table->boolean('is_correct')->nullable();
    $table->decimal('score', 5, 2)->nullable();
    $table->timestamps();

    $table->unique(['attempt_id', 'question_id']); // D-02 — baked in from v1
});
```

### `AppServiceProvider::boot()` — defensive string-length guard (D-03)
```php
// app/Providers/AppServiceProvider.php — currently an empty boot() method [VERIFIED this session]
use Illuminate\Support\Facades\Schema;

public function boot(): void
{
    Schema::defaultStringLength(191);
}
```

### `DatabaseSeeder` — minimal, idempotent test accounts (D-10)
```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $classroom = Classroom::firstOrCreate(['name' => 'Demo Classroom']);

    User::firstOrCreate(
        ['email' => 'lecturer@example.com'],
        [
            'name' => 'Demo Lecturer',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),   // required — see Pitfall 1
            'role' => Role::Lecturer,
        ]
    );

    User::firstOrCreate(
        ['email' => 'student@example.com'],
        [
            'name' => 'Demo Student',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),   // required — see Pitfall 1
            'role' => Role::Student,
            'classroom_id' => $classroom->id,
        ]
    );
}
```
`firstOrCreate` on the fixed email keys makes this safe to re-run per D-10's explicit requirement.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|-------------------|---------------|--------|
| Middleware/route-middleware registered in `app/Http/Kernel.php` | Middleware aliased via `->withMiddleware()` in `bootstrap/app.php` | Laravel 11.0 skeleton redesign (Mar 2024) | Do not follow any Laravel-10-or-earlier tutorial referencing `Kernel.php` for this project — it does not exist in this app [VERIFIED: no `app/Http/Kernel.php` present; `bootstrap/app.php` is the actual registration point] |
| `protected $casts = [...]` property for enum casting | `protected function casts(): array { return [...]; }` method | Laravel 11 introduced the method form as the current convention; both still work | This project should use the method form for any new casts added to `User` — the existing `User` model already uses `casts()` for `email_verified_at`/`password` [VERIFIED: file read], so adding `'role' => Role::class` to the same method is a pure addition, not a style change |
| Manual `Gate::policy()` registration in `AuthServiceProvider` | Policy auto-discovery by `app/Policies/{Model}Policy` naming convention | Ongoing Laravel convention, reconfirmed in 11.x docs | Not used this phase (no Policies yet), but relevant for whoever plans Phase 3 — no `AuthServiceProvider` registration boilerplate will be needed there either |

**Deprecated/outdated:** Nothing in this phase's scope relies on a deprecated Laravel 11 API; all patterns above were confirmed directly against the currently-published `laravel.com/docs/11.x` pages this session, not recalled from older training data alone.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|----------------|
| A1 | The `role:{lecturer|student}` middleware should respond with a hard `abort(403)` on mismatch, rather than a redirect-with-flash-message | Pattern 2 / Common Pitfalls | Low — D-07 says "server-enforced 403/redirect for the wrong role," leaving the exact response form to the planner/executor; a 403 is the simplest correct choice and is trivially testable, but a redirect-to-own-dashboard UX is equally valid and would need a one-line change to the middleware and its tests |
| A2 | `role` should remain out of `$request->validate()`'s rule set entirely on registration (rather than being validated-then-discarded) | Pattern 3 | Low — either approach satisfies RBAC-02 as long as the value written to the DB is always the hardcoded `Role::Student`; simply not accepting the field is marginally simpler than accepting-and-ignoring it |
| A3 | The lecturer/student route groups should carry the `verified` middleware (matching the existing `/dashboard` route), rather than omitting it | Recommended Project Structure, Pitfall 1 | Medium — if omitted, Pitfall 1 (seeded-account email verification) disappears, but the project loses parity with the rest of the Breeze scaffold's security posture; ARCHITECTURE.md's Integration Points table explicitly recommends keeping `verified` on all role-gated groups, so this is a light assumption, not a fresh guess |

**If this table is empty:** N/A — see rows above. All other claims in this research were verified directly against this repository's files/CLI output this session, or cited from `laravel.com/docs/11.x` pages fetched this session.

## Open Questions (RESOLVED)

1. **Should `role`/`classroom_id` be exposed via any Blade `@if`/`@can` directive in this phase's scaffolding, or strictly left for Phase 2+ CRUD UI?**
   - What we know: CONTEXT.md explicitly excludes any lecturer/student CRUD UI from this phase; only the coarse role gate and a role-based redirect target are in scope.
   - What's unclear: Whether the lecturer/student "landing" views this phase creates (to have somewhere for the redirect to land) should be a single trivial placeholder Blade view each, or something more.
   - Recommendation: A minimal placeholder view per area (e.g., "Lecturer area — coming in Phase 2") is sufficient to make RBAC-03/RBAC-04 observable; do not build any real content into these views this phase.
   - **RESOLVED:** Plan `01-03` Task 2 creates minimal `x-app-layout` placeholder landing views per area with no role logic/content — exactly the recommended minimum.

2. **Pivot table naming: `class_subject` (ARCHITECTURE.md) vs. `classroom_subject` (Eloquent's own naming convention for a `Classroom` model)?**
   - What we know: See Pitfall 5 — this is a genuine, unresolved naming inconsistency between the locked `Classroom` entity name (SUMMARY.md/D-01) and ARCHITECTURE.md's pivot table name, which was written before that naming was finalized.
   - What's unclear: Which name the planner should commit to; D-01 leaves this to planner/executor discretion.
   - Recommendation: Use `classroom_subject` for Eloquent-convention-free `belongsToMany()` calls, or explicitly pass the pivot name/keys if `class_subject` is kept for brevity. Either is fine — just be consistent and explicit in the plan.
   - **RESOLVED:** Plan `01-01` commits to `classroom_subject` (Laravel convention, no override); `exam_classroom` keeps its ROADMAP name with an explicit `belongsToMany(..., 'exam_classroom')` override in `01-02`. Migration, model, factory, seeder, and test all agree.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|--------------|-----------|---------|----------|
| PHP | Application runtime | ✓ | 8.2.32 | — |
| Composer | Dependency management (no new installs needed) | ✓ | 2.8.2 | — |
| MySQL | Domain schema persistence | ✓ | 8.0.45, live at 127.0.0.1:3306, `yp-student-exam` DB reachable with 9 existing framework tables | — |
| Node/npm | Vite asset build (unchanged this phase) | ✓ | npm 10.7.0 | — |
| `laravel/framework` | Everything | ✓ | 11.55.0 installed | — |
| `laravel/breeze` | Auth scaffold (unmodified base) | ✓ | ^2.4 | — |

**Missing dependencies with no fallback:** none.
**Missing dependencies with fallback:** none — every dependency this phase needs is already installed and verified reachable.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.0.1 (composer.json `phpunit/phpunit ^11.0.1`), driven via `php artisan test` or `vendor/bin/phpunit` |
| Config file | `phpunit.xml` (repo root) [VERIFIED: file read this session] |
| Quick run command | `php artisan test --filter=<TestClassOrMethod>` |
| Full suite command | `php artisan test` |

**Important environment fact [VERIFIED: `phpunit.xml` read this session]:** `phpunit.xml`'s `DB_CONNECTION`/`DB_DATABASE` overrides are **commented out** — tests run against the *same* MySQL connection configured in `.env` (`mysql` / `yp-student-exam`), not an isolated in-memory SQLite DB. The existing Breeze feature tests (`tests/Feature/Auth/RegistrationTest.php`, etc.) already use `RefreshDatabase` against this same live connection [VERIFIED: file read]. This is **pre-existing project behavior, not something this phase introduces** — but every new test this phase adds must be aware that running the suite will migrate/truncate the actual `yp-student-exam` database. This is acceptable for a local dev/grading workflow but is worth flagging so the executor doesn't assume test isolation from dev data.

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|---------------------|--------------|
| Success criterion #1 (schema) | Fresh migration creates all 9 domain tables + `attempts`/`answers` unique constraints | feature (schema assertion) | `php artisan test --filter=DomainSchemaTest` | ❌ Wave 0 — new `tests/Feature/DomainSchemaTest.php` |
| RBAC-01 | `role` column stored on `users`, cast to `Role` enum | unit | `php artisan test --filter=UserRoleCastTest` | ❌ Wave 0 — new `tests/Unit/UserRoleCastTest.php` |
| RBAC-02 | Public registration always creates a Student, even if a client posts a `role` field | feature | `php artisan test --filter=RegistrationTest` | ✅ `tests/Feature/Auth/RegistrationTest.php` exists — add a new test method to it |
| RBAC-03 | Post-login redirect lands Lecturer/Student on the correct area | feature | `php artisan test --filter=RoleRedirectTest` | ❌ Wave 0 — new `tests/Feature/RoleRedirectTest.php` |
| RBAC-04 | A Student hitting a lecturer-only URL is blocked server-side (and vice versa) | feature | `php artisan test --filter=RoleMiddlewareTest` | ❌ Wave 0 — new `tests/Feature/RoleMiddlewareTest.php` |

### Sampling Rate

- **Per task commit:** `php artisan test --filter=<relevant test>`
- **Per wave merge:** `php artisan test` (full suite)
- **Phase gate:** Full suite green, plus a manual `php artisan migrate:fresh --seed` run against the live `yp-student-exam` DB confirming all 9 domain tables + constraints exist, before `/gsd-verify-work`.

### Wave 0 Gaps

- [ ] `tests/Feature/DomainSchemaTest.php` — covers success criterion #1 (assert `Schema::hasTable(...)` for all 9 domain tables; assert the composite unique indexes on `attempts(exam_id,user_id)` and `answers(attempt_id,question_id)` exist, e.g. via `Schema::getIndexes('attempts')`/`getIndexes('answers')` on the Doctrine/Illuminate schema builder)
- [ ] `tests/Unit/UserRoleCastTest.php` — covers RBAC-01 (assert `$user->role instanceof Role` after `User::factory()->create(['role' => Role::Lecturer])`)
- [ ] `tests/Feature/RoleRedirectTest.php` — covers RBAC-03 (login as a lecturer/student fixture, assert redirect target)
- [ ] `tests/Feature/RoleMiddlewareTest.php` — covers RBAC-04 (as a Student, `GET` a lecturer-only route, assert 403 or redirect; as a Lecturer, `GET` a student-only route, assert 403 or redirect)
- [ ] Extend `tests/Feature/Auth/RegistrationTest.php` — covers RBAC-02 (POST `/register` with an extra `role=lecturer` field in the payload, assert the created user's role is still `Role::Student`)
- [ ] `database/factories/ClassroomFactory.php` — not a test file itself, but required as a fixture dependency for any test/seeder that attaches a Student to a classroom

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|-----------------|---------|--------------------|
| V2 Authentication | yes (unchanged) | Breeze's session-based auth scaffold — not modified this phase |
| V3 Session Management | yes (unchanged) | Laravel's default session driver/config — not modified this phase |
| V4 Access Control | yes — primary focus of this phase | Custom `role` middleware alias gating whole route groups (coarse); record-level Policies deferred to Phase 3 (RBAC-05) per explicit scope boundary |
| V5 Input Validation | yes | `RegisteredUserController`'s existing `$request->validate()` call; the `role` value must never be sourced from request input regardless of validation rules |
| V6 Cryptography | yes (unchanged) | Breeze's existing `Hash::make()`/`'password' => 'hashed'` cast — not modified this phase |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|------------------------|
| Client-supplied `role` field on the registration POST body attempting to self-elevate to Lecturer | Tampering / Elevation of Privilege | Server hardcodes `role => Role::Student` in `RegisteredUserController@store`; never reads `role` from `$request` (Pattern 3) — this is precisely RBAC-02's stated purpose |
| Forgetting the `role:*` middleware on a newly added lecturer/student route (added later, outside this phase) | Elevation of Privilege | Route-group-level middleware declaration (once per file, not per-route) means new routes added *inside* an existing `Route::middleware([...])->group()` block automatically inherit the gate — this is exactly why D-06/D-07 mandate group-level, not per-route, middleware application |
| Mass-assignment of `role`/`classroom_id` via a crafted extra POST field on an unrelated form (e.g. profile update) | Tampering | Keep `role` and `classroom_id` out of any user-facing form's validated/fillable path outside the specific server-controlled writes (registration hardcode, seeder); `User::$fillable` additions in this phase should be reviewed to ensure no public-facing controller can mass-assign them from arbitrary request input |
| Seeded privileged (Lecturer) account left with a guessable/shared password across environments | Information Disclosure / Elevation of Privilege | Acceptable for this phase's demo scope per D-10 (explicitly deferred to Phase 6's full demo/README treatment) — D-10's minimal seeded pair is for internal verification only, not the final shipped credentials, which Phase 6 documents properly |

## Sources

### Primary (HIGH confidence)
- Direct codebase inspection this session: `composer.json`, `phpunit.xml`, `.env` (DB_* only), `bootstrap/app.php`, `routes/web.php`, `app/Models/User.php`, `database/migrations/0001_01_01_000000_create_users_table.php`, `app/Http/Controllers/Auth/RegisteredUserController.php`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`, `app/Providers/AppServiceProvider.php`, `resources/views/dashboard.blade.php` — all read directly, not assumed
- CLI verification this session: `php artisan --version` (11.55.0), `php artisan db:show` (MySQL 8.0.45, `yp-student-exam`, 9 tables live), `php --version` (8.2.32), `composer --version` (2.8.2), `npm --version` (10.7.0), `php artisan route:list`
- [Middleware | Laravel 11.x docs](https://laravel.com/docs/11.x/middleware) — fetched directly this session via WebFetch, confirms `bootstrap/app.php` alias registration pattern and absence of `Kernel.php`
- [Authorization | Laravel 11.x docs](https://laravel.com/docs/11.x/authorization) — fetched directly this session via WebFetch, confirms Policy auto-discovery convention (`app/Policies/{Model}Policy`)
- [Eloquent: Mutators & Casting | Laravel 11.x docs](https://laravel.com/docs/11.x/eloquent-mutators) — confirmed via WebSearch this session, `casts()` method + backed enum pattern

### Secondary (MEDIUM confidence)
- [Laravel: Specified key was too long error — Laravel News](https://laravel-news.com/laravel-5-4-key-too-long-error) — confirmed via WebSearch this session, `Schema::defaultStringLength(191)` fix and the MySQL/MariaDB version threshold where it matters
- `.planning/research/ARCHITECTURE.md`, `.planning/research/STACK.md`, `.planning/research/PITFALLS.md`, `.planning/research/SUMMARY.md` — prior project research, cross-checked against this session's direct codebase/docs verification and found consistent (with the one pivot-naming discrepancy noted in Pitfall 5)

### Tertiary (LOW confidence)
- None used without cross-checking — every claim above was either verified directly against this repository, fetched from official `laravel.com/docs/11.x` this session, or cross-referenced against the pre-existing project research files.

## Project Constraints (from CLAUDE.md)

`./CLAUDE.md` was not found at the repository root; project directives are instead maintained at `./.claude/CLAUDE.md` (per `.planning/config.json`'s `claude_md_path`). Extracted directives relevant to this phase:

- **Tech stack is fixed:** Laravel 11 + Breeze — build on the existing scaffold, do not replace it. (Honored throughout this research — no new packages, no scaffold replacement.)
- **Database is MySQL** (`yp-student-exam` via Herd) — confirmed live and reachable this session.
- **Frontend is Breeze Blade + Tailwind + Alpine** — no SPA. Not touched by this phase beyond two placeholder landing views.
- **Scope discipline: minimal, correct implementation over feature breadth.** Directly reflected in this phase's exclusion of Policies/CRUD UI (deferred to later phases).
- **GSD Workflow Enforcement:** file-changing work should go through a GSD command (`/gsd-execute-phase`, etc.) rather than direct ad hoc edits — applies to the executor, not this research step.
- No forbidden libraries, required tools, or testing rules beyond what's already reflected in STACK.md's "What NOT to Use" table (already incorporated above).

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|--------------|----------------------|
| RBAC-01 | System supports two roles — Lecturer and Student — stored on the user | Pattern 1 (backed enum + `casts()`), migration example for `users` extension, Code Examples section |
| RBAC-02 | Public self-registration always creates a Student; Lecturer accounts are provisioned via seeding | Pattern 3 (registration role lock), Pitfall 3 (mass-assignment gap), Security Domain (client-tampering threat pattern) |
| RBAC-03 | After login, a user is directed to a role-appropriate area (lecturer vs student) | Pattern 4 (post-login redirect), Architecture Diagram, Open Question #1 (placeholder landing views) |
| RBAC-04 | Lecturer-only pages and actions are inaccessible to Students (server-enforced by middleware, not just hidden in the UI) | Pattern 2 (custom middleware alias + route-group gating), Security Domain (forgotten-middleware threat pattern), Validation Architecture (`RoleMiddlewareTest`) |
</phase_requirements>

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — every version/package claim verified directly against this repository's own files/CLI output this session, not recalled from training data
- Architecture: HIGH — Laravel 11 conventions (middleware registration, Policy auto-discovery, enum casting) confirmed against official `laravel.com/docs/11.x` pages fetched this session
- Pitfalls: HIGH for codebase-specific findings (verified email/seeding interaction, mass-assignment gap, pivot-naming discrepancy — all discovered via direct file inspection this session); MEDIUM-HIGH for the general utf8mb4/migration-ordering pitfalls (cross-checked via WebSearch against reputable sources, and this project's actual MySQL 8.0.45 makes the classic version of that pitfall largely moot)

**Research date:** 2026-07-15
**Valid until:** 30 days (stable Laravel 11.x conventions; re-verify if the project upgrades to Laravel 12/13 before this phase is planned)

---
phase: 01-foundation-domain-schema-role-based-access-control
verified: 2026-07-15T21:10:00Z
status: passed
score: 13/13 must-haves verified
behavior_unverified: 0
overrides_applied: 0
---

# Phase 1: Foundation — Domain Schema & Role-Based Access Control Verification Report

**Phase Goal:** All domain tables exist with correct constraints, and two role-gated areas (Lecturer/Student) sit on top of the existing Breeze auth scaffold.
**Verified:** 2026-07-15T21:10:00Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Fresh `migrate:fresh` against empty MySQL DB creates all 9 domain tables + users.role/classroom_id | ✓ VERIFIED | Ran `php artisan migrate:fresh` live against the project's real MySQL DB — all 10 new migrations (classrooms, subjects, users-extension, classroom_subject, exams, questions, options, exam_classroom, attempts, answers) executed with `DONE`, no errors. `DomainSchemaTest::test_all_domain_tables_exist` and `test_users_table_has_role_and_classroom_id_columns` pass. |
| 2 | `attempts` has composite UNIQUE(exam_id, user_id) | ✓ VERIFIED | `database/migrations/2026_07_15_100009_create_attempts_table.php` line 25: `$table->unique(['exam_id', 'user_id']);` baked into the create migration (no ALTER). `DomainSchemaTest::test_attempts_table_has_composite_unique_index_on_exam_id_and_user_id` asserts via `Schema::getIndexes('attempts')` and passes. |
| 3 | `answers` has composite UNIQUE(attempt_id, question_id) | ✓ VERIFIED | `database/migrations/2026_07_15_100010_create_answers_table.php` line 25: `$table->unique(['attempt_id', 'question_id']);` baked into the create migration. `DomainSchemaTest::test_answers_table_has_composite_unique_index_on_attempt_id_and_question_id` asserts via `Schema::getIndexes('answers')` and passes. |
| 4 | Every FK resolves at migrate time (parents before children) | ✓ VERIFIED | Migration timestamps order classrooms(100001) → subjects(100002) → users-ext(100003) → classroom_subject(100004) → exams(100005) → questions(100006) → options(100007) → exam_classroom(100008) → attempts(100009) → answers(100010). Live `migrate:fresh` completed with zero FK errors. |
| 5 | `$user->role` returns an `App\Enums\Role` instance, not a raw string (RBAC-01) | ✓ VERIFIED | `app/Models/User.php` casts `'role' => Role::class`. `UserRoleCastTest::test_role_is_cast_to_role_enum_after_create_and_reload` create+reload round-trip passes. |
| 6 | `isLecturer()`/`isStudent()` reflect stored role | ✓ VERIFIED | `app/Models/User.php` lines 68-76. `UserRoleCastTest::test_is_lecturer_and_is_student_helpers_reflect_stored_role` passes for both roles. |
| 7 | Student `$user->classroom` resolves belongsTo; Classroom↔Subject and Exam↔Classroom many-to-many resolve through correct pivots | ✓ VERIFIED | `User::classroom()` belongsTo Classroom (app/Models/User.php:63). `Classroom::exams()`/`Exam::classrooms()` both pass explicit `'exam_classroom'` pivot; `Classroom::subjects()`/`Subject::classrooms()` use Laravel's default `classroom_subject` convention — table names match migrations exactly. `TestAccountSeederTest` proves the classroom_id FK write+read round-trips (student attached to Demo Classroom). |
| 8 | Public self-registration always yields a Student — no public path to Lecturer (RBAC-02) | ✓ VERIFIED | `RegisteredUserController@store` hardcodes `'role' => Role::Student` (app/Http/Controllers/Auth/RegisteredUserController.php:47), no `role` validation rule, no request-sourced role read. `RegistrationTest::test_registration_always_creates_a_student_even_if_role_is_posted` POSTs a crafted `role=lecturer` field and asserts the created user is `Role::Student` — passes. |
| 9 | Post-login role-based redirect: Lecturer→lecturer area, Student→student area (RBAC-03) | ✓ VERIFIED | `DashboardController::__invoke` uses `isLecturer()` to redirect to `lecturer.home`/`student.home` (app/Http/Controllers/DashboardController.php). `RoleRedirectTest` (2 tests) passes; `dashboard` route name unchanged (Breeze login/register flow intact — confirmed in `routes/web.php`). |
| 10 | Student hitting lecturer-only URL is blocked server-side by group-level middleware (RBAC-04) | ✓ VERIFIED | `RoleMiddlewareTest` (4 tests: cross-role 403 both directions + same-role 200 both directions) passes. `php artisan route:list -vv` confirms `lecturer.home` carries `auth`, `verified`, `role:lecturer` and `student.home` carries `auth`, `verified`, `role:student` at the **route-group** level (not per-controller/nav-only). |
| 11 | Block is middleware-enforced at group level, not hidden nav | ✓ VERIFIED | `routes/lecturer.php` and `routes/student.php` each declare `Route::middleware([...])->group(...)` wrapping all routes in the group; `bootstrap/app.php` registers the `role` alias → `EnsureUserHasRole::class`. `EnsureUserHasRoleTest` unit-tests the middleware directly (match/mismatch/guest → 403), independent of routing. |
| 12 | Seeding creates a verified Lecturer and a verified, classroom-assigned Student, idempotently (D-10) | ✓ VERIFIED | `database/seeders/DatabaseSeeder.php` uses `firstOrCreate` keyed on email for both accounts, both set `email_verified_at => now()`, student gets `classroom_id`. `TestAccountSeederTest` (2 tests: content assertions + idempotency via repeat-seed user-count check) passes. Live `php artisan migrate:fresh --seed` ran cleanly end-to-end. |
| 13 | Role/classroom_id not reachable via any public mass-assignment path beyond registration/seeder | ✓ VERIFIED | `User::$fillable` includes `role`/`classroom_id` (required for seeder/factories), but the only other public write path — `ProfileController@update` — calls `$request->user()->fill($request->validated())`, and `ProfileUpdateRequest::rules()` validates **only** `name` and `email` (app/Http/Requests/ProfileUpdateRequest.php). `validated()` therefore can never contain `role`/`classroom_id` regardless of what the client POSTs — no privilege-escalation path exists through profile update. |

**Score:** 13/13 truths verified (0 present, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Providers/AppServiceProvider.php` | `Schema::defaultStringLength(191)` guard | ✓ VERIFIED | Present in `boot()`, line 23 |
| `database/migrations/2026_07_15_100001_create_classrooms_table.php` | classrooms table, name unique | ✓ VERIFIED | `Schema::create('classrooms', ...)`, `string('name')->unique()` |
| `database/migrations/2026_07_15_100002_create_subjects_table.php` | subjects table | ✓ VERIFIED | `code` unique nullable |
| `database/migrations/2026_07_15_100003_add_role_and_classroom_id_to_users_table.php` | users.role + classroom_id FK | ✓ VERIFIED | `role` default `student`; `classroom_id` nullable, `nullOnDelete()` |
| `database/migrations/2026_07_15_100004_create_classroom_subject_table.php` | classroom_subject pivot | ✓ VERIFIED | unique(classroom_id, subject_id) |
| `database/migrations/2026_07_15_100005_create_exams_table.php` | exams table | ✓ VERIFIED | FKs to subjects, users(created_by), both cascadeOnDelete |
| `database/migrations/2026_07_15_100006_create_questions_table.php` | questions table | ✓ VERIFIED | points default 1 |
| `database/migrations/2026_07_15_100007_create_options_table.php` | options table | ✓ VERIFIED | is_correct default false |
| `database/migrations/2026_07_15_100008_create_exam_classroom_table.php` | exam_classroom pivot | ✓ VERIFIED | unique(exam_id, classroom_id) |
| `database/migrations/2026_07_15_100009_create_attempts_table.php` | attempts table, unique(exam_id,user_id) | ✓ VERIFIED | Constraint present in create migration |
| `database/migrations/2026_07_15_100010_create_answers_table.php` | answers table, unique(attempt_id,question_id) | ✓ VERIFIED | Constraint present in create migration |
| `tests/Feature/DomainSchemaTest.php` | SC#1 schema assertions | ✓ VERIFIED | 4 tests, all passing |
| `app/Enums/Role.php` | backed enum Lecturer/Student | ✓ VERIFIED | `enum Role: string { Lecturer='lecturer'; Student='student'; }` |
| `app/Enums/QuestionType.php` | backed enum Mcq/Open | ✓ VERIFIED | Present, correct cases |
| `app/Models/User.php` | role cast, classroom(), isLecturer/isStudent | ✓ VERIFIED | All present and tested |
| `app/Models/{Classroom,Subject,Exam,Question,Option,Attempt,Answer}.php` | 7 models w/ relationships | ✓ VERIFIED | All present, relationships match ARCHITECTURE.md, correct pivot names/FKs |
| `database/factories/ClassroomFactory.php` | Classroom fixture | ✓ VERIFIED | Unique name |
| `tests/Unit/UserRoleCastTest.php` | RBAC-01 round-trip test | ✓ VERIFIED | 2 tests passing |
| `app/Http/Middleware/EnsureUserHasRole.php` | parameterized role gate | ✓ VERIFIED | 403 on mismatch/guest, passthrough on match |
| `bootstrap/app.php` | `role` middleware alias | ✓ VERIFIED | Registered in `withMiddleware` closure |
| `routes/lecturer.php`, `routes/student.php` | role-gated route groups | ✓ VERIFIED | Group-level `role:lecturer`/`role:student` + `auth` + `verified` |
| `app/Http/Controllers/DashboardController.php` | post-login role dispatch | ✓ VERIFIED | Uses `isLecturer()`, not raw string compare |
| `tests/Feature/RoleMiddlewareTest.php`, `RoleRedirectTest.php` | RBAC-03/04 feature tests | ✓ VERIFIED | 6 tests total, all passing |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | role hardcoded to Student | ✓ VERIFIED | `'role' => Role::Student`, explicit array (not `$request->all()`) |
| `database/seeders/DatabaseSeeder.php` | idempotent verified Lecturer+Student pair | ✓ VERIFIED | `firstOrCreate`, `email_verified_at` set, classroom attached |
| `tests/Feature/TestAccountSeederTest.php` | seeder verification | ✓ VERIFIED | 2 tests, content + idempotency |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| users-extension migration | classrooms migration | `constrained('classrooms')` | ✓ WIRED | FK resolves; verified live via `migrate:fresh` |
| answers migration | attempts migration | `attempt_id` FK cascadeOnDelete | ✓ WIRED | FK resolves live |
| `app/Models/User.php` | `app/Enums/Role.php` | `casts() => Role::class` | ✓ WIRED | Round-trip tested |
| `app/Models/Exam.php` | `app/Models/Classroom.php` | `belongsToMany(Classroom::class, 'exam_classroom')` | ✓ WIRED | Explicit pivot name matches migration table name |
| `bootstrap/app.php` | `EnsureUserHasRole.php` | middleware alias `'role'` | ✓ WIRED | Confirmed via `route:list -vv` showing `role:lecturer`/`role:student` resolved on live routes |
| `routes/web.php` | `DashboardController.php` | `/dashboard` route | ✓ WIRED | `route:list` confirms `dashboard › DashboardController` |
| `routes/web.php` | `routes/lecturer.php` + `routes/student.php` | `require` | ✓ WIRED | Both routes present and reachable in `route:list` |
| `RegisteredUserController` | `Role::Student` | server-constant array literal | ✓ WIRED | Verified by crafted-role RegistrationTest |
| `DatabaseSeeder` | `Classroom` | `firstOrCreate` + student `classroom_id` | ✓ WIRED | TestAccountSeederTest asserts non-null classroom_id |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Fresh migration against live MySQL DB | `php artisan migrate:fresh` | All 13 migrations `DONE`, no errors | ✓ PASS |
| Fresh migration + seed against live MySQL DB | `php artisan migrate:fresh --seed` | Migrations + seeding completed cleanly | ✓ PASS |
| Full automated test suite | `php artisan test` | 43 passed (104 assertions), 0 failures | ✓ PASS |
| Role middleware applied at route-group level | `php artisan route:list -vv` (grep lecturer.home/student.home) | `auth`, `verified`, `role:lecturer` / `role:student` present on both | ✓ PASS |
| Mass-assignment surface (profile update) | Read `ProfileUpdateRequest::rules()` | Only `name`/`email` validated — `role`/`classroom_id` unreachable via `validated()` | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| RBAC-01 | 01-01, 01-02 | Two roles stored on user, type-safe enum | ✓ SATISFIED | Role enum + cast + helpers, UserRoleCastTest green |
| RBAC-02 | 01-04 | Public registration always Student, no public Lecturer path | ✓ SATISFIED | Hardcoded role, crafted-role test green, no mass-assignment leak |
| RBAC-03 | 01-03 | Post-login role-based redirect | ✓ SATISFIED | DashboardController + RoleRedirectTest green |
| RBAC-04 | 01-03 | Lecturer-only pages inaccessible to Students, server-enforced | ✓ SATISFIED | Group-level middleware + RoleMiddlewareTest green, route:list confirms |

No orphaned requirements — REQUIREMENTS.md maps exactly RBAC-01 through RBAC-04 to Phase 1, and all four appear in plan frontmatter `requirements:` fields (01-01: RBAC-01, 01-02: RBAC-01, 01-03: RBAC-03/RBAC-04, 01-04: RBAC-02).

### Anti-Patterns Found

None. Grep for `TBD|FIXME|XXX|TODO|HACK|PLACEHOLDER|placeholder|coming soon|not yet implemented` across all phase-modified controllers, middleware, models, enums, migrations, seeder, and route files returned zero matches. The two placeholder Blade views (`lecturer/home.blade.php`, `student/home.blade.php`) contain the string "Lecturer area — coming in a later phase." / "Student area — coming in a later phase." — this is the **explicitly planned deliverable** for this phase (PLAN 01-03 acceptance criteria: "minimal x-app-layout pages... No real feature content, no role logic in the view"), not an unplanned stub; it is not flagged as a gap.

### Human Verification Required

None. All must-haves are server-side, deterministically testable behaviors (schema constraints, enum casts, redirect targets, HTTP status codes, mass-assignment surface) and were verified via live migration runs, a full green test suite (43/43), and direct route/middleware inspection.

### Gaps Summary

No gaps found. All 4 ROADMAP success criteria and all 4 requirement IDs (RBAC-01 through RBAC-04) are verified against the live codebase and a live MySQL database — not just SUMMARY.md claims. The security spot-check on the mass-assignment surface (profile update) confirmed no privilege-escalation path exists beyond the server-controlled registration/seeder writes.

---

_Verified: 2026-07-15T21:10:00Z_
_Verifier: Claude (gsd-verifier)_

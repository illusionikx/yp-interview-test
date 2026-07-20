---
phase: 01-foundation-domain-schema-role-based-access-control
reviewed: 2026-07-15T00:00:00Z
depth: standard
files_reviewed: 32
files_reviewed_list:
  - database/migrations/2026_07_15_100001_create_classrooms_table.php
  - database/migrations/2026_07_15_100002_create_subjects_table.php
  - database/migrations/2026_07_15_100003_add_role_and_classroom_id_to_users_table.php
  - database/migrations/2026_07_15_100004_create_classroom_subject_table.php
  - database/migrations/2026_07_15_100005_create_exams_table.php
  - database/migrations/2026_07_15_100006_create_questions_table.php
  - database/migrations/2026_07_15_100007_create_options_table.php
  - database/migrations/2026_07_15_100008_create_exam_classroom_table.php
  - database/migrations/2026_07_15_100009_create_attempts_table.php
  - database/migrations/2026_07_15_100010_create_answers_table.php
  - app/Enums/Role.php
  - app/Enums/QuestionType.php
  - app/Models/User.php
  - app/Models/Classroom.php
  - app/Models/Subject.php
  - app/Models/Exam.php
  - app/Models/Question.php
  - app/Models/Option.php
  - app/Models/Attempt.php
  - app/Models/Answer.php
  - app/Http/Middleware/EnsureUserHasRole.php
  - bootstrap/app.php
  - routes/lecturer.php
  - routes/student.php
  - routes/web.php
  - app/Http/Controllers/DashboardController.php
  - app/Http/Controllers/Auth/RegisteredUserController.php
  - database/seeders/DatabaseSeeder.php
  - database/factories/ClassroomFactory.php
  - resources/views/lecturer/home.blade.php
  - resources/views/student/home.blade.php
  - app/Http/Controllers/ProfileController.php (consulted — mass-assignment cross-check)
  - app/Http/Requests/ProfileUpdateRequest.php (consulted — mass-assignment cross-check)
findings:
  blocker: 1
  high: 2
  medium: 1
  low: 2
  total: 6
status: issues_found
---

# Phase 01: Code Review Report — Foundation, Domain Schema & RBAC

**Reviewed:** 2026-07-15T00:00:00Z
**Depth:** standard
**Files Reviewed:** 32
**Status:** issues_found

## Summary

The RBAC surface itself is well built: registration hardcodes `role => Role::Student` server-side (`RegisteredUserController.php:47`), is directly covered by a test that posts a spoofed `role=lecturer` and asserts the account is still created as `Student` (`tests/Feature/Auth/RegistrationTest.php:34-50`), `EnsureUserHasRole` fails closed on missing auth via short-circuit evaluation, and the lecturer/student route files apply `role:*` at the group level with no bypassable individual routes. Profile self-update only ever fills `ProfileUpdateRequest::validated()` (name/email), so the sensitive `role`/`classroom_id` entries in `User::$fillable` are not currently reachable from any public request despite being present in the fillable list. The pivot relationship definitions (`exam_classroom`, `classroom_subject`) correctly match their migrations' column names and Eloquent's default/explicit pivot-key inference.

That said, this review surfaced one severe out-of-band credential leak in the git history feeding this "public GitHub repo" deliverable, one security control that is silently inert (`verified` middleware with no `MustVerifyEmail` implementation), and one FK cascade design that lets a routine self-service action (account deletion) destroy other users' historical exam data. These are detailed below.

## Blocker Issues

### BL-01: Live-looking GitLab Personal Access Token committed as the author identity on every commit

**File:** git history (all commits, e.g. `c1d97cf`, `e5886fe`, `d19b020`, `672ce45`) — not a tracked file, but ships with the repository
**Issue:** Every commit in this repository's history has its author field set to:
```
muhamad-rubmin <glpat-SbrggmIFlZBaBI22Dn1EGWM6MQpvOjEKdTpkZXc5Nw8.01.1711vb7l3>
```
The email field is not an email address — it has the exact shape of a GitLab Personal Access Token (`glpat-` prefix). `git config user.email` was evidently set to a copy-pasted token instead of an email address, and it is now baked into the permanent, immutable commit metadata of every single commit (confirmed via `git log --format='%an <%ae>' | sort -u`). CLAUDE.md/PROJECT.md constraints state this project is "shipped to a public GitHub repository with a README" — pushing this history publishes a live-looking credential to the internet in every commit's metadata, which is scraped by bots within minutes of going public.
**Fix:**
1. Rotate/revoke this token in GitLab immediately, treating it as compromised regardless of whether the repo has been pushed yet.
2. Fix the local git identity: `git config user.email "you@realdomain.example"` (and `user.name` if also wrong) before any further commits.
3. Before making the repository public, rewrite history to scrub the token from all existing commits (e.g. `git filter-repo --mailmap` or a fresh history via `git commit-tree`/squash-and-reinit), since amending only `HEAD` leaves the token in every ancestor commit.
4. Do not push the current history to the public remote until step 3 is done.

## High Issues

### HI-01: `verified` middleware is a silent no-op — `User` does not implement `MustVerifyEmail`

**File:** `app/Models/User.php:1,12` (import commented out, interface not implemented); consumed at `routes/lecturer.php:5`, `routes/student.php:5`, `routes/web.php:11-13`
**Issue:** `routes/lecturer.php` and `routes/student.php` both apply `['auth', 'verified', 'role:...']` at the route-group level, and `/dashboard` applies `['auth', 'verified']`. Laravel's `Illuminate\Auth\Middleware\EnsureEmailIsVerified` only enforces verification when `$request->user() instanceof MustVerifyEmail`:
```php
if (! $request->user() ||
    ($request->user() instanceof MustVerifyEmail && ! $request->user()->hasVerifiedEmail())) {
    // block
}
return $next($request); // otherwise always passes
```
Because `App\Models\User` (`app/Models/User.php:12`) is declared as `class User extends Authenticatable` — with `// use Illuminate\Contracts\Auth\MustVerifyEmail;` left commented out and no `implements MustVerifyEmail` — the `instanceof` check is always `false`, so `verified` passes through unconditionally for every authenticated user, verified or not. This is not an intentional simplification: `database/seeders/DatabaseSeeder.php:31` and `:41` each carry the comment `// required — Breeze's 'verified' middleware otherwise blocks this account`, showing the implementer believed this gate was active and set `email_verified_at` defensively — it isn't active, so that defensive step is currently a no-op too. It also explains why no test caught this: both `UserFactory::definition()` (`database/factories/UserFactory.php:29`) and the seeder always set `email_verified_at`, so no test exercises an actually-unverified user hitting a `verified`-gated route.
**Fix:** Uncomment the import and implement the interface:
```php
use Illuminate\Contracts\Auth\MustVerifyEmail;
...
class User extends Authenticatable implements MustVerifyEmail
```
Then add a regression test that creates a user via `User::factory()->unverified()->create(...)` and asserts a `verified`-gated route (e.g. `/dashboard` or `/lecturer`) redirects to `verification.notice` instead of succeeding.

### HI-02: `exams.created_by` cascade delete lets a lecturer's self-service account deletion destroy other students' exam-attempt history

**File:** `database/migrations/2026_07_15_100005_create_exams_table.php:17`
**Issue:** `created_by` is defined as `$table->foreignId('created_by')->constrained('users')->cascadeOnDelete();`. `routes/web.php:18` already wires Breeze's default `ProfileController::destroy` (`app/Http/Controllers/ProfileController.php:43-59`), which lets any authenticated user — including a Lecturer — delete their own account with just their current password. Deleting a Lecturer cascades: `users` row deleted → every `exams` row they authored is cascade-deleted (`created_by` FK) → each of those exams' `questions` cascade-deletes (`questions.exam_id` FK) → each question's `options` cascade-delete → and separately, `attempts.exam_id` also cascade-deletes (`database/migrations/2026_07_15_100009_create_attempts_table.php:16`), which cascades into `answers.attempt_id` (`database/migrations/2026_07_15_100010_create_answers_table.php:16`). The net effect: one lecturer choosing to delete their own account irrecoverably destroys every other student's attempt/answer records for every exam that lecturer ever created, with no confirmation, archival, or ownership-transfer step — this crosses account boundaries (Lecturer action destroying Student data) rather than only affecting the deleting user's own rows.
**Fix:** Change the FK to fail safe instead of cascading across accounts, e.g.:
```php
$table->foreignId('created_by')->constrained('users')->restrictOnDelete();
```
and handle "lecturer account has existing exams" as an explicit blocked-deletion / reassignment / soft-delete flow in a later phase, rather than a silent hard cascade. (By contrast, `attempts.user_id` and `answers.*` cascading from a *student's own* account deletion is reasonable, since that only removes the deleting user's own data.)

## Medium Issues

### ME-01: `answers.selected_option_id` cascade delete destroys the entire answer row when an MCQ option is edited/removed

**File:** `database/migrations/2026_07_15_100010_create_answers_table.php:18`
**Issue:** `$table->foreignId('selected_option_id')->nullable()->constrained('options')->cascadeOnDelete();`. If a lecturer edits a question after students have already answered it (e.g. removes/replaces an MCQ option, which later phases will need for exam editing), deleting that `Option` row cascade-deletes every `Answer` row that referenced it as `selected_option_id` — not just the reference. This silently destroys the whole answer record (including `answer_text`, `is_correct`, `score` for that attempt/question), and undermines the `unique(['attempt_id','question_id'])` guarantee's intent by making the row vanish rather than degrade gracefully.
**Fix:** Use `nullOnDelete()` instead so the answer survives an option edit with `selected_option_id` reset to `null`:
```php
$table->foreignId('selected_option_id')->nullable()->constrained('options')->nullOnDelete();
```

## Low Issues

### LO-01: `role`/`classroom_id` mass-assignability relies entirely on caller discipline, not a structural guard

**File:** `app/Models/User.php:28-34`
**Issue:** `$fillable` includes `role` and `classroom_id` "for server-controlled writes only," per the inline comment. Today this is safe — `RegisteredUserController::store` hardcodes `Role::Student` (`app/Http/Controllers/Auth/RegisteredUserController.php:47`) and `ProfileController::update` only fills `ProfileUpdateRequest::validated()`, whose `rules()` exposes just `name`/`email` (`app/Http/Requests/ProfileUpdateRequest.php:17-30`) — but the guard rail is "no current caller happens to pass these keys," not something the framework enforces. A future FormRequest that adds `'role'` to its `rules()` for an unrelated reason (e.g. a lecturer self-service preference form cloned from `ProfileUpdateRequest`), or any future `$request->all()`/`User::create($request->validated())` call site, becomes a silent privilege-escalation vector with no compile-time or framework-level signal.
**Fix:** Consider removing `role`/`classroom_id` from `$fillable` entirely and setting them only via `forceFill()`/direct attribute assignment at the two legitimate server-controlled call sites (registration, seeder/factories). This makes "no public form can ever touch these fields" a structural property instead of a code-review convention.

### LO-02: `EnsureUserHasRole` throws an uncaught `ValueError` (HTTP 500) instead of a controlled 403 on a corrupted/unbacked role value

**File:** `app/Http/Middleware/EnsureUserHasRole.php:21`
**Issue:** `$request->user()->role->value !== $role` dereferences the `role` cast, which uses Laravel's native backed-enum casting. If the `role` column ever holds a string that isn't one of `Role`'s cases (e.g. a manual DB edit, a future migration default drift, or a partially-applied data fix), accessing `->role` throws `ValueError: "X" is not a valid backing value for enum App\Enums\Role` before the middleware's own `abort(403)` logic ever runs, surfacing as an unhandled 500 rather than the intended "wrong role → 403" behavior. Low likelihood under normal application flow (migration defaults to `'student'` and no code path writes an arbitrary string), but the middleware is the sole server-side enforcement point for RBAC-04 and currently has no defensive handling for this case.
**Fix:** Optional hardening — wrap the comparison in a try/catch (or read the raw DB attribute) and `abort(403)` on any non-matching/invalid value instead of letting the cast throw:
```php
$role = $request->user()?->getRawOriginal('role');
if ($role !== $requiredRole) {
    abort(403);
}
```

---

_Reviewed: 2026-07-15T00:00:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_

---

## Orchestrator Resolution (2026-07-15)

Each finding was verified against source before action (no blind acceptance).

| ID | Severity | Verified | Action |
|----|----------|----------|--------|
| BL-01 | blocker | ✅ (git author email IS a `glpat-` token) | **NEEDS USER.** Stopped future leakage by resetting local `user.email`. Token must be **rotated in GitLab** and history **scrubbed** before any public push. Tracked in project memory. |
| HI-02 | high | ✅ | **FIXED** (`187e39e`) — `exams.created_by` → `nullable()->nullOnDelete()`. Migrate:fresh clean, suite green. |
| ME-01 | medium | ✅ | **FIXED** (`187e39e`) — `answers.selected_option_id` → `nullOnDelete()`. |
| HI-01 | high | ✅ but out of scope | **DEFERRED (intentional).** Email verification is not a brief requirement and no mailer is configured; `verified` middleware present-but-inert is stock Breeze. Revisit only if email verification becomes a requirement. |
| LO-01 | low | ✅ (verifier proved safe) | **DEFERRED.** `role`/`classroom_id` in `$fillable` is required for seeder/factory; verifier confirmed no public mass-assignment path (`ProfileUpdateRequest` validates only name/email). Optional hardening later. |
| LO-02 | low | ✅ (can't occur today) | **DEFERRED.** `role` is always a valid enum via cast; out-of-set value would need DB corruption. Optional defensive 403 later. |

**Blocker gate:** BL-01 does not block continued development (it concerns the git identity/push, not code correctness), but it **hard-blocks the public GitHub push** until the user rotates the token and rewrites history.

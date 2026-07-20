# Phase 6: Demo Seeder & Delivery - Research

**Researched:** 2026-07-16
**Domain:** Laravel 11 database seeding (idempotent demo graph) + delivery documentation (README) for a graded, clean-clone deliverable
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01:** Expand `database/seeders/DatabaseSeeder.php` (building on the Phase-1 minimal lecturer+student) into a reviewable demo, all via **`firstOrCreate`/idempotent** so `migrate:fresh --seed` AND a re-run are safe: (a) 1 Lecturer + ~3 Students (fixed emails, `email_verified_at` set — Phase-1 pitfall); (b) 1–2 **Classrooms** with the students assigned (`classroom_id`); (c) 2–3 **Subjects** linked to a classroom (`classroom_subject`); (d) at least one **published Exam** (with a time limit) scoped to a subject, containing BOTH a multiple-choice question (≥2 options, one correct) and an open-text question, **assigned to the students' classroom** (`exam_classroom`) — so a seeded student can immediately take it.
- **D-02:** Seed ONE **pre-submitted, auto-graded demo attempt** for a second demo student (create the attempt + answers, then run `AttemptGrader::gradeAutoGradable()` + `syncStatus()` so it reaches a realistic `submitted`/`graded` state) — so the grading + results screens have content on first load (demonstrates the full pipeline without manual steps). Keep the FIRST demo student un-attempted so a reviewer can take the exam fresh. Keep this lightweight.
- **D-03:** Use factories where they exist (Classroom/Subject/Exam/Question/Option/Answer/Attempt) but pin **fixed, documented credentials** (deterministic emails + a shared demo password) so the README can list exact logins. Do not rely on random faker data for the accounts a reviewer logs into.
- **D-04:** Replace the Laravel-default `README.md` with a project README covering: what the app is (one-paragraph overview + the two roles); tech stack (Laravel 11 + Breeze, MySQL, Blade+Tailwind+Alpine); **setup from a clean clone** — `git clone`, `composer install`, `npm install`, copy `.env` + `php artisan key:generate`, **create the MySQL database `yp-student-exam`** (and set `DB_*`), `php artisan migrate:fresh --seed`, `npm run build` (or `npm run dev`), `php artisan serve`; the **seeded demo credentials** (lecturer + students, exact emails/password); a short **per-role walkthrough** (lecturer: manage classrooms/subjects, author + publish + assign an exam, grade open-text, view results; student: see assigned exam, take it under the timer, view result); how to **run the tests** (`php artisan test`) with the note that they use `RefreshDatabase` against the configured MySQL DB (so run against a dev DB); and a short **"Publishing to GitHub"** note flagging that the git author identity must be set correctly before pushing.
- **D-05:** Verify the whole path works: `php artisan migrate:fresh --seed` on an empty `yp-student-exam` succeeds (all migrations + the full seed), and the documented demo credentials log in as both roles. This is the phase's acceptance gate (a feature test asserting the seeder produces the expected graph, plus a manual clean-clone run).
- **D-06:** The public-repo push is deferred to the user. Do NOT run `git push` / create a remote / publish anything this phase — README documents the intended repo/setup only.

### Claude's Discretion

- Exact number of demo students/subjects/questions, the seeder's internal structure (one file vs helper seeders), and README section ordering — planner/executor choice, provided D-01..D-06 hold.

### Deferred Ideas (OUT OF SCOPE)

- **The actual `git push` to a public GitHub repo** → USER action (needs token rotation + repo creation + history scrub). Documented, not automated.
- **Any new app features / v2** (partial-credit, analytics, etc.) → out of scope.
- **CI/CD, Docker, deployment** → not required by the brief.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| DEL-01 | A seeder creates demo data (lecturer, students, classrooms, subjects, and a sample exam with both question types assigned to a classroom), runnable via `php artisan migrate:fresh --seed` | See Architecture Patterns (Patterns 1-4), Code Examples, Common Pitfalls (1, 2, 4, 5, 6), Validation Architecture |
| DEL-02 | README documents setup, database creation, migration, seeding, demo credentials, and how to run/test the app | See Architecture Patterns (README structure), Code Examples (README skeleton), Common Pitfalls (8), Validation Architecture |
</phase_requirements>

## Summary

Phase 6 is pure glue work: no new packages, no new endpoints, no new domain logic. Everything needed already exists — `DatabaseSeeder.php` (Phase 1's minimal lecturer+student pair), full model factories for every domain entity (Phase 1-5), and `AttemptGrader` (Phase 5, already idempotent by design). The job is to (1) expand `DatabaseSeeder::run()` into a complete, idempotent demo graph built entirely from `firstOrCreate`/`sync()` calls and direct `AttemptGrader` invocation (never through HTTP), (2) write a feature test that asserts the graph exists and that re-seeding doesn't duplicate rows, (3) replace the Laravel-default `README.md` with a project README, and (4) manually verify `php artisan migrate:fresh --seed` against a genuinely empty MySQL schema.

The environment was verified live during this research: Laravel Framework 11.55.0, PHP 8.2.32, Composer 2.8.2, Node 20.14.0, npm 10.7.0, and MySQL 8.0.45 (Herd, `127.0.0.1:3306`, database `yp-student-exam`, currently reachable with 18 tables) are all installed and connected. `Schema::defaultStringLength(191)` is already set in `AppServiceProvider::boot()` (the classic `utf8mb4` "specified key too long" guard) — do not touch it.

The one design decision with real leverage: the pre-graded demo attempt (D-02) should include an **ungraded open-text answer**. Calling `gradeAutoGradable()` grades only the MCQ; calling `syncStatus()` afterward will correctly leave the attempt at `submitted` (not `graded`) because `AttemptGrader::syncStatus()` checks for any open-text answer with `score === null` before allowing the `submitted → graded` transition. This is not a bug to work around — it is the desired demo state: it gives the lecturer's grading queue live content on first load (GRD-02 walkthrough) and gives GRD-03's gating behavior something real to demonstrate, rather than starting from an already-fully-graded, nothing-left-to-do attempt.

**Primary recommendation:** Extend the existing `DatabaseSeeder::run()` in place (single file, private helper methods per entity group — no new sub-seeder classes needed at this scale) using `firstOrCreate` for every named/demo entity and `->sync()` for every pivot, guard question/option creation behind `$exam->wasRecentlyCreated` (questions/options have no unique index), and grade the second demo student's attempt by calling `app(AttemptGrader::class)->gradeAutoGradable($attempt)` then `->syncStatus($attempt)` directly — never via a simulated HTTP request.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Demo data construction (users, classrooms, subjects, exam, questions, options) | Database / Storage | — | Seeder writes directly via Eloquent models; no HTTP/controller layer involved, no new business logic |
| Pivot linking (classroom_subject, exam_classroom) | Database / Storage | — | Pure relationship-table writes via `BelongsToMany::sync()` |
| Grading the demo attempt | Database / Storage (via `AttemptGrader` service) | — | Same service the API/backend tier calls at real finalize-time (Phase 5); seeder reuses it directly, bypassing the HTTP/routing/auth layer entirely since there's no real "student submitting" during seeding |
| Clean-clone verification (`migrate:fresh --seed`) | CLI / Artisan (outside the 5-tier web-app map) | Database / Storage | Artisan command orchestrates migrations then the seeder; not a runtime request-serving tier |
| README | Documentation (repo root, not a runtime tier) | — | Static delivery artifact, not code |

## Standard Stack

### Core

No new libraries. This phase uses only what is already installed and verified in the running project:

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` | 11.55.0 installed (`^11.31` pinned) [VERIFIED: `php artisan --version`] | Seeder base class, Eloquent, Artisan | Already the mandated framework; `Illuminate\Database\Seeder` and `firstOrCreate`/`sync()` are core Eloquent, no package needed |
| `fakerphp/faker` | `^1.23` [VERIFIED: composer.json] | Realistic filler data inside factories (already used by every Phase 1-5 factory) | Already a dev dependency, used transitively via `fake()` in factories — no change needed |
| `phpunit/phpunit` | `^11.0.1` [VERIFIED: composer.json] | Feature test for the seeder + README assertions | Already the project's test runner |

### Supporting

None. There is no supporting-library need for this phase — see Don't Hand-Roll below for what NOT to add.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| One `DatabaseSeeder.php` with private helper methods | Split into `UserSeeder`, `ClassroomSeeder`, `SubjectSeeder`, `ExamSeeder` called via `$this->call([...])` | Sub-seeders add indirection with no benefit at this scale (a handful of records, single developer/reviewer reading the file); only worth it if the demo graph grows materially larger. Left as Claude's Discretion per CONTEXT.md — this research recommends staying with one file. |
| `firstOrCreate` per row | `updateOrCreate` | `updateOrCreate` would silently overwrite a reviewer's manual edits (e.g. a lecturer who renamed the demo classroom, or graded the pending open-text answer) on every re-seed — wrong semantics for a demo seeder that must be safely re-runnable without clobbering interactive state. `firstOrCreate` is correct here. |

**Installation:**
No new installs required — `composer install && npm install` (already documented setup) covers everything this phase needs.

**Version verification:** Verified live in this session, not from training data:
```
$ php artisan --version
Laravel Framework 11.55.0
$ php artisan db:show
MySQL 8.0.45, Connection mysql, Database yp-student-exam, Host 127.0.0.1:3306, Tables 18
```
[VERIFIED: local artisan / db:show, run 2026-07-16]

## Package Legitimacy Audit

**No external packages are installed by this phase.** DEL-01 and DEL-02 are implemented entirely with already-installed, already-vetted dependencies (`laravel/framework`, `fakerphp/faker`, `phpunit/phpunit` — all present since project inception and used throughout Phases 1-5). The Package Legitimacy Gate protocol is not applicable; no `npm view`/`pip index`/registry checks were needed.

**Packages removed due to [SLOP] verdict:** none (none proposed)
**Packages flagged as suspicious [SUS]:** none

## Architecture Patterns

### System Architecture Diagram

**Seeding flow (DEL-01):**

```
CLI: php artisan db:seed  (or migrate:fresh --seed)
        │
        ▼
DatabaseSeeder::run()
        │
        ├─▶ seedUsers()          firstOrCreate: 1 Lecturer + 3 Students
        │                        (fixed emails, email_verified_at=now(), Hash::make(password))
        │
        ├─▶ seedClassrooms()     firstOrCreate: Classroom(s); students linked via
        │                        User::update(['classroom_id' => ...]) at creation time
        │
        ├─▶ seedSubjects()       firstOrCreate: Subject(s)
        │                        └─▶ $classroom->subjects()->sync([...])   [classroom_subject pivot]
        │
        ├─▶ seedExam()           firstOrCreate: Exam (subject_id, created_by=lecturer, published)
        │       │                └─ guarded by $exam->wasRecentlyCreated:
        │       ├─▶ Question::firstOrCreate(mcq)  + 2-4 Options (one is_correct=true)
        │       ├─▶ Question::firstOrCreate(open) + no options
        │       └─▶ $exam->classrooms()->sync([...])                      [exam_classroom pivot]
        │
        └─▶ seedDemoAttempt()    Attempt::firstOrCreate(exam_id, user_id=student2) [status=submitted]
                │                Answer::firstOrCreate (MCQ: selected_option_id, Open: answer_text, score=null)
                ▼
            app(AttemptGrader::class)->gradeAutoGradable($attempt)   ─▶ grades the MCQ answer only
                ▼
            app(AttemptGrader::class)->syncStatus($attempt)          ─▶ stays 'submitted' (open-text pending)
                                                                          — this is the desired demo state
        ▼
MySQL (yp-student-exam)
```

**Clean-clone delivery flow (DEL-02 / D-05):**

```
git clone <repo>
   │
   ▼
composer install  ──▶  npm install
   │                        │
   ▼                        ▼
cp .env.example .env   (npm run build   OR  npm run dev, later)
   │
   ▼
php artisan key:generate
   │
   ▼
create MySQL database `yp-student-exam`  (manual — CREATE DATABASE, README documents exact command)
set DB_* in .env to match
   │
   ▼
php artisan migrate:fresh --seed   ◀── the single command this phase's seeder must survive
   │
   ▼
npm run build   (or npm run dev for local iteration)
   │
   ▼
php artisan serve
   │
   ▼
Browser: log in as lecturer@example.com / student@example.com / student2@example.com (password: "password")
```

### Recommended Project Structure

No new directories. Only these two files change:

```
database/seeders/
└── DatabaseSeeder.php     # expanded in place — private helper methods, one per entity group
README.md                  # replaced wholesale (Laravel default → project README)
tests/Feature/
└── DatabaseSeederTest.php # new — supersedes/extends TestAccountSeederTest.php's 2 assertions
```

### Pattern 1: Idempotent entity seeding via `firstOrCreate`

**What:** Every named/demo row (users, classroom, subjects, exam) is created via `Model::firstOrCreate($uniqueKey, $attributes)` — the first argument is the natural key to look up, the second is applied only on creation.

**When to use:** Every entity the seeder is solely responsible for creating (as opposed to bulk filler data, which this phase does not need).

**Example:**
```php
// Source: existing DatabaseSeeder.php pattern (Phase 1), extended
$lecturer = User::firstOrCreate(
    ['email' => 'lecturer@example.com'],
    [
        'name' => 'Demo Lecturer',
        'password' => Hash::make('password'),
        'email_verified_at' => now(), // required — Breeze's `verified` middleware
        'role' => Role::Lecturer,
    ]
);
```

### Pattern 2: Idempotent pivot linking via `->sync()`

**What:** `BelongsToMany::sync($ids)` is inherently idempotent — calling it twice with the same array produces the same pivot rows both times, no duplicate-key risk, no manual "does this pivot row already exist" check needed.

**When to use:** `classroom_subject` (`$classroom->subjects()->sync([...])`) and `exam_classroom` (`$exam->classrooms()->sync([...])`) — confirmed as the app's own convention (`tests/Feature/Lecturer/ClassroomSubjectLinkageTest.php`, `tests/Feature/Lecturer/ExamAssignmentTest.php`).

**Example:**
```php
// Source: app/Models/Classroom.php + app/Models/Exam.php relation methods
$classroom->subjects()->sync($subjectIds);
$exam->classrooms()->sync([$classroom->id]);
```

### Pattern 3: Idempotent parent-guarded child creation (no unique index on children)

**What:** `questions` and `options` have no unique constraint (only `attempts` and `answers` do — see Migration Notes below). `firstOrCreate` on a `Question` keyed by `['exam_id' => ..., 'body' => ...]` would technically work but is fragile (body text as a "key" is brittle). The robust pattern: guard child creation behind whether the **parent** (`Exam`) was just created.

**When to use:** Building the exam's questions/options — anything that hangs off a `firstOrCreate`d parent with no natural key of its own.

**Example:**
```php
// Source: reasoned from database/migrations/2026_07_15_100006_create_questions_table.php
// (no unique index) + Eloquent's documented wasRecentlyCreated flag
$exam = Exam::firstOrCreate(
    ['subject_id' => $mathSubject->id, 'title' => 'Mathematics Midterm'],
    [
        'created_by' => $lecturer->id,
        'description' => 'Demo exam covering basic arithmetic.',
        'duration_minutes' => 30,
        'is_published' => true,
    ]
);

if ($exam->wasRecentlyCreated) {
    $mcq = Question::create(['exam_id' => $exam->id, 'type' => QuestionType::Mcq, 'body' => '2 + 2 = ?', 'points' => 1, 'position' => 0]);
    Option::create(['question_id' => $mcq->id, 'body' => '4', 'is_correct' => true, 'position' => 0]);
    Option::create(['question_id' => $mcq->id, 'body' => '5', 'is_correct' => false, 'position' => 1]);

    Question::create(['exam_id' => $exam->id, 'type' => QuestionType::Open, 'body' => 'Explain your reasoning.', 'points' => 5, 'position' => 1]);
}

$exam->classrooms()->sync([$classroom->id]); // safe to always re-run — sync is idempotent regardless
```

### Pattern 4: Seeding a pre-graded attempt via the domain service directly

**What:** Build the `Attempt` and `Answer` rows directly with Eloquent (not via a simulated HTTP `post(route('student.attempts.submit', ...))` call), set `status: 'submitted'` up front, then invoke `AttemptGrader` exactly as `Attempt::lockAndFinalize()` would in production — but without going through routing/middleware/CSRF, which the seeder has no reason to exercise.

**When to use:** D-02's pre-graded demo attempt.

**Example:**
```php
// Source: app/Services/AttemptGrader.php (read directly) + database/factories/AttemptFactory.php state shape
$attempt = Attempt::firstOrCreate(
    ['exam_id' => $exam->id, 'user_id' => $student2->id],
    [
        'started_at' => now()->subMinutes(20),
        'submitted_at' => now(),
        'status' => 'submitted', // MUST be 'submitted' before grading — see Pitfall 2
        'score' => null,
    ]
);

Answer::firstOrCreate(
    ['attempt_id' => $attempt->id, 'question_id' => $mcq->id],
    ['selected_option_id' => $mcq->options()->where('is_correct', true)->value('id')],
);

Answer::firstOrCreate(
    ['attempt_id' => $attempt->id, 'question_id' => $openQuestion->id],
    ['answer_text' => 'Because 2 apples plus 2 apples makes 4 apples.', 'score' => null], // left ungraded — see Pitfall 4
);

app(AttemptGrader::class)->gradeAutoGradable($attempt);
app(AttemptGrader::class)->syncStatus($attempt);
// Result: MCQ answer graded (is_correct/score set), attempt stays 'submitted'
// (not 'graded') because the open-text answer is still pending — by design.
```

### Anti-Patterns to Avoid

- **Raw SQL inserts for demo data:** Bypasses Eloquent casts (`role` enum, `hashed` password cast), model events, and the `wasRecentlyCreated` idempotency signal used above — always use Eloquent.
- **Simulating grading through an HTTP request in the seeder:** Couples the seeder to routing, CSRF tokens, and auth middleware for no benefit — call `AttemptGrader` directly (Pattern 4).
- **Hardcoded numeric IDs (`User::find(1)`):** Already flagged in PITFALLS.md Pitfall 8 as a documented technical-debt pattern for this project — every lookup in the seeder must go through `firstOrCreate`'s own return value or a fresh query by natural key, never an assumed ID.
- **Fully grading every demo answer:** Leaves nothing for the lecturer grading walkthrough to demonstrate — see Pitfall 4.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|--------------|-----|
| Idempotent "insert if not exists" | Manual `if (!Model::where(...)->exists()) { Model::create(...) }` | `Model::firstOrCreate($key, $attrs)` | The manual form is not meaningfully safer for a single-process CLI seeder and is strictly more code; `firstOrCreate` is the documented idiom and is what Phase 1's existing seeder already uses. |
| Pivot table sync | Manual `DB::table('exam_classroom')->insert(...)` guarded by an existence check | `$model->relation()->sync($ids)` | `sync()` already diffs the desired set against the existing set, adds/removes only the delta, and handles pivot `timestamps()` — reimplementing this is pure waste. |
| Auto-grading the demo attempt | A second, seeder-local "compute MCQ correctness" implementation | `app(AttemptGrader::class)->gradeAutoGradable()` / `->syncStatus()` | The real grading logic is already built, tested (`tests/Feature/Grading/AttemptGraderTest.php`), and is the single source of truth for grading semantics (defensive against a question with no correct option flagged, etc. — Pitfall 2 from PITFALLS.md). A parallel implementation in the seeder would silently drift from the real grading rules over time. |
| Demo account credentials | Faker-random emails/passwords for the lecturer/student accounts a reviewer logs into | Fixed, hardcoded strings, documented verbatim in the README | The entire point of D-03/D-04 is that the README's login table is exact and reproducible — random data breaks the deliverable's core promise. |
| README boilerplate about what Laravel/Breeze are | Re-explaining framework internals a grader already knows | A concise, project-specific overview (what this app does, the two roles, the seeded credentials, the walkthrough) | The Laravel-default README already over-explains the framework; the graded deliverable needs a project README, not a framework README. |

**Key insight:** Every piece of this phase reuses something Phases 1-5 already built and tested. The only genuinely new code is the sequencing/wiring inside `DatabaseSeeder::run()` and the prose in `README.md` — there is no domain logic to design from scratch here.

## Common Pitfalls

### Pitfall 1: Re-seeding crashes because child tables have no unique index

**What goes wrong:** A naive re-run of the seeder calls `Question::create(...)` / `Option::create(...)` unconditionally on every invocation, so a second `db:seed` run (or a second `migrate:fresh --seed` on top of an already-seeded DB, which shouldn't happen but the test must cover plain re-seeding too) creates duplicate questions/options for the same exam.

**Why it happens:** `questions` and `options` (unlike `attempts` and `answers`) have **no unique constraint** in their migrations [VERIFIED: `database/migrations/2026_07_15_100006_create_questions_table.php`, `..._100007_create_options_table.php` — read directly, no `$table->unique(...)` present]. There is nothing at the DB layer to stop a duplicate insert.

**How to avoid:** Guard question/option creation behind the parent `Exam`'s `wasRecentlyCreated` flag (Pattern 3) — only build questions/options the first time the exam itself is created.

**Warning signs:** `Question::count()` grows on every `db:seed` re-run; the idempotency test (see Validation Architecture) fails on its second seed call.

### Pitfall 2: `AttemptGrader::syncStatus()` silently no-ops if the attempt isn't already `submitted`/`graded`

**What goes wrong:** The seeder creates the demo `Attempt` using the bare `Attempt::factory()` default state (`status: 'in_progress'`) or forgets to explicitly set `status: 'submitted'`, then calls `gradeAutoGradable()` + `syncStatus()` expecting the attempt to transition — but `syncStatus()` returns immediately (`if (! in_array($attempt->status, ['submitted', 'graded'], true)) { return; }`) [VERIFIED: `app/Services/AttemptGrader.php` read directly, lines 71-73], so the attempt is left `in_progress` with graded-looking answers underneath it — an inconsistent state no real code path produces.

**Why it happens:** `AttemptFactory::definition()`'s default `status` is `'in_progress'`; only the `->submitted()` state (or an explicit override, as in Pattern 4's example) sets `status: 'submitted'`.

**How to avoid:** Explicitly set `'status' => 'submitted'` (and a non-null `submitted_at`) on the `Attempt` row **before** calling `gradeAutoGradable()`/`syncStatus()` — do not rely on the factory's bare default state.

**Warning signs:** The demo attempt's `status` column reads `in_progress` after seeding despite having `is_correct`/`score` populated on its MCQ answer.

### Pitfall 3: MySQL auto-increment does not reset between test runs or re-seeds

**What goes wrong:** A test (or a manual review) asserts on a hardcoded primary-key `id` for a seeded row, and the assertion is correct the first time but breaks after any re-seed, or across separate test runs within the same test-class session.

**Why it happens:** [CITED via WebSearch cross-referenced against official Laravel database-testing docs] Laravel's `RefreshDatabase` trait, against MySQL/PostgreSQL (as opposed to SQLite in-memory), runs real migrations once and wraps each individual test in a DB transaction that is rolled back afterward — this resets row **data** but **not** MySQL's `auto_increment` counter. IDs keep climbing across re-seeds/tests within the same run.

**How to avoid:** Every assertion (in the seeder itself and in its test) must look entities up by natural/business key (email, name+subject pair) via `firstOrFail()`/`where()`, never by an assumed numeric ID.

**Warning signs:** A test that passes in isolation but fails when run as part of the full suite (order-dependent ID assumptions).

### Pitfall 4: Fully grading the demo attempt removes the very story it's supposed to tell

**What goes wrong:** If the seeder grades the open-text answer too (e.g. `'score' => 4` set directly in the seeder, or calling a manual-grade path), the demo attempt reaches `status: 'graded'` immediately. On first login, the lecturer's Results/grading screen has **nothing pending to grade**, and the student's result page shows a final score with zero interactive story left — which defeats D-02's stated purpose ("so the grading + results screens have content on first load").

**Why it happens:** It's tempting to make the demo "fully done" for a clean first impression, but "fully done" means there's nothing left for a reviewer to click through and verify (GRD-02's open-text grading flow specifically).

**How to avoid:** Leave the open-text `Answer.score` as `null` (the `AnswerFactory`'s own default/`openText()` state already does this — do not override it). Calling `gradeAutoGradable()` + `syncStatus()` as shown in Pattern 4 will correctly leave the attempt at `submitted`, not `graded`, without any special-casing.

**Warning signs:** Zero rows in the DB where `answers.score IS NULL AND questions.type = 'open'` after seeding — means every open-text answer got graded and there's no pending-grading demo content left.

### Pitfall 5: `email_verified_at` must be set on **every** seeded account, not just the original two

**What goes wrong:** A newly added demo student (student2, student3) is created without `email_verified_at`, and Breeze's `verified` middleware — present on **both** `routes/lecturer.php` and `routes/student.php` route groups [VERIFIED: read directly, `Route::middleware(['auth', 'verified', 'role:...'])`] — silently redirects that account to the "verify your email" screen on every login attempt. With no mail driver configured in local dev, this looks exactly like a broken login to a reviewer, with no obvious error.

**Why it happens:** It's easy to copy the pattern for the first two accounts and forget the field on subsequently added ones, since the app never rejects the *creation* of an unverified user — only blocks it downstream at the route-middleware layer.

**How to avoid:** Every `User::firstOrCreate` call for a named demo account must include `'email_verified_at' => now()`. Cover this with an explicit assertion per seeded user in the validation test, not just for the first lecturer/student pair.

**Warning signs:** A demo login works (session created) but the user is stuck being redirected to `verification.notice` instead of their role home.

### Pitfall 6: Do not touch `Schema::defaultStringLength(191)`

**What goes wrong:** Someone "cleans up" `AppServiceProvider::boot()` during this phase and removes the `Schema::defaultStringLength(191)` call, assuming it's dead code since no migration errors are currently visible on the already-migrated dev database.

**Why it happens:** The guard is invisible during normal development because migrations only run once against an already-correctly-configured DB; its absence only manifests on a **genuinely fresh** `utf8mb4` MySQL schema — exactly the clean-clone scenario D-05 exists to test — where `classrooms.name` / `subjects.code`'s unique string indexes would otherwise hit InnoDB's index-byte-length limit ("Specified key was too long").

**How to avoid:** Leave `Schema::defaultStringLength(191)` in `AppServiceProvider::boot()` untouched [VERIFIED: `app/Providers/AppServiceProvider.php` read directly, line 23 — already present]. This phase should not modify `AppServiceProvider`.

**Warning signs:** `php artisan migrate:fresh --seed` fails with "Specified key was too long; max key length is 3072 bytes" (or similar) on a truly empty schema.

### Pitfall 7: `role`/`classroom_id` fillable is a documented one-time carve-out, not a precedent

**What goes wrong:** Reading `User::$fillable` including `role` and `classroom_id` might suggest it's fine to mass-assign these from any future controller.

**Why it happens:** The `User` model's own docblock [VERIFIED: `app/Models/User.php` lines 20-24, read directly] explicitly scopes this: *"`role` and `classroom_id` are fillable for server-controlled writes only (seeder, factories, tests). No public-facing controller may build a User from request-sourced input for these two attributes."*

**How to avoid:** It is safe and correct for this phase's seeder to mass-assign `role`/`classroom_id` (that's exactly the carve-out's intended use). Just don't generalize this pattern to any HTTP-facing code — out of scope for this phase, but worth stating in code comments if a reviewer skims the seeder.

**Warning signs:** N/A for this phase — this is a forward-looking note, not something Phase 6 code can violate on its own.

### Pitfall 8: Running `php artisan test` wipes the real dev database's seeded demo data

**What goes wrong:** `phpunit.xml` has `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` **commented out** [VERIFIED: `phpunit.xml` read directly, lines 25-26], meaning tests run against whatever `DB_CONNECTION`/`DB_DATABASE` the actual `.env` specifies — i.e. the real configured MySQL `yp-student-exam` database. `RefreshDatabase` runs migrations against that live DB and rolls back per-test, but a reviewer who runs `php artisan test` and then immediately tries to log in with the demo credentials will find the seeded demo rows are gone (never persisted past `RefreshDatabase`'s per-test rollback and the initial fresh-migrate at test-suite start, which drops all data).

**Why it happens:** This is a deliberate project-level tradeoff (see PROJECT.md/PITFALLS.md — MySQL-only testing to avoid SQLite-vs-MySQL behavior drift), not a bug, but it's a sharp edge for a reviewer who doesn't expect `php artisan test` to touch their demo data.

**How to avoid:** The README's "run the tests" section MUST warn: running the test suite will reset the configured MySQL database; re-run `php artisan migrate:fresh --seed` afterward to restore the demo dataset for manual review.

**Warning signs:** Demo login credentials from the README stop working after running `php artisan test`.

## Code Examples

### Full `DatabaseSeeder::run()` skeleton

```php
// Source: synthesized from existing DatabaseSeeder.php (Phase 1) + AttemptGrader.php +
// factories, verified against read migrations/models for this project
public function run(): void
{
    $lecturer = $this->seedLecturer();
    [$student1, $student2, $student3] = $this->seedStudents();

    $classroomA = $this->seedClassroom('Demo Classroom', [$student1, $student2]);
    $classroomB = $this->seedClassroom('Unassigned Classroom', [$student3]); // demonstrates ASN-02/RBAC-05 scoping

    [$mathematics, $science] = $this->seedSubjects();
    $classroomA->subjects()->sync([$mathematics->id, $science->id]);
    $classroomB->subjects()->sync([$mathematics->id]);

    $exam = $this->seedExam($mathematics, $lecturer, $classroomA); // NOT assigned to classroomB — see Pattern above

    $this->seedDemoAttempt($exam, $student2); // student1 stays un-attempted; student3's classroom can't see this exam at all
}
```

### `.env.example` / setup command block for the README

```bash
# Source: composer.json scripts (post-root-package-install/post-create-project-cmd) +
# PROJECT.md Context section, cross-checked against `php artisan db:show` live output this session
git clone <repo-url> yp-student-exam && cd yp-student-exam
composer install
npm install
cp .env.example .env
php artisan key:generate

# Create the MySQL database (adjust for your MySQL client/credentials):
mysql -u root -e "CREATE DATABASE \`yp-student-exam\`;"
# Then set DB_DATABASE=yp-student-exam (and DB_USERNAME/DB_PASSWORD) in .env to match.

php artisan migrate:fresh --seed
npm run build
php artisan serve
```

### Demo credentials table shape for the README

```markdown
| Role     | Email                  | Password  |
|----------|-------------------------|-----------|
| Lecturer | lecturer@example.com    | password  |
| Student  | student@example.com     | password  | ← un-attempted, take the exam fresh
| Student  | student2@example.com    | password  | ← has a submitted attempt awaiting open-text grading
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|-------------------|---------------|--------|
| N/A | N/A | — | Laravel's `firstOrCreate`/`updateOrCreate`/`BelongsToMany::sync()` seeding idioms have been stable across Laravel 8.x-13.x [CITED: laravel.com/docs seeding pages for 8.x through 13.x surfaced identical guidance during this research's WebSearch]; there is no version-drift risk to flag for this phase. |

**Deprecated/outdated:** None applicable — this phase does not touch anything with a recent API change.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|----------------|
| A1 | Recommended exact demo shape (2 classrooms — one assigned the exam, one not, to visually demonstrate class-scoped access; 3 named students; 2 subjects; 1 exam with 1 MCQ + 1 open question) | Architecture Patterns, Code Examples | Low — CONTEXT.md explicitly leaves exact counts to Claude's Discretion; the planner may choose a simpler 1-classroom shape without violating D-01..D-06. The 2-classroom design is a recommendation to add real demo value (proving ASN-02/RBAC-05), not a hard requirement. |
| A2 | Single-file `DatabaseSeeder.php` (no sub-seeder classes) is the right structure | Standard Stack (Alternatives Considered), Architecture Patterns | Low — also explicit Claude's Discretion; sub-seeders are a valid alternative if the executor prefers more separation. |
| A3 | Demo password `"password"` (matching the existing Phase-1 convention and `UserFactory`'s default `Hash::make('password')`) is reused for all seeded accounts | Code Examples | Low — this is already the established convention in the codebase (`UserFactory`, existing `DatabaseSeeder`), not a new choice. |
| A4 | `mysql -u root -e "CREATE DATABASE..."` is the right README command shape for Herd's bundled MySQL | Code Examples | Low-Medium — the exact CLI invocation (root/no-password vs Herd-specific tooling) may need adjusting per the executor's actual local MySQL client setup; the README should note this is adjustable. `.env.example` itself could not be read this session (permission-denied on the file), so exact `DB_*` variable names are taken from PROJECT.md's prose description, not a direct file read. |

**If this table is empty:** N/A — see rows above. All other claims in this research are `[VERIFIED]` (direct file reads or live CLI checks run in this session) or `[CITED]` (WebSearch cross-referenced against official Laravel docs).

## Open Questions (RESOLVED)

**RESOLVED (OQ-1):** Deferred to execution — `06-03`'s README task reads the real `.env.example` (`cat`) before writing the DB-setup steps, so the exact `DB_*` variable names are confirmed live during execution (standard Laravel 11: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

1. **Exact `.env.example` variable names for the MySQL connection**
   - What we know: PROJECT.md states `.env` sets `DB_CONNECTION=mysql`, database `yp-student-exam` on `127.0.0.1:3306`; `php artisan db:show` (run live this session) confirms the app is currently connected with those exact values.
   - What's unclear: The literal `.env.example` file could not be read this session (blocked by local file-permission sandboxing on dotfiles, not a project-level restriction) so its exact current placeholder values are unconfirmed.
   - Recommendation: The planner/executor should `cat .env.example` directly (no sandboxing restriction should apply during actual execution) before writing the README's exact `.env` edit instructions, and confirm the placeholder `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` keys match what's documented here.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|--------------|-----------|---------|----------|
| PHP | Laravel runtime | ✓ | 8.2.32 (Herd) | — |
| Composer | `composer install` | ✓ | 2.8.2 | — |
| Node.js | `npm install` / Vite build | ✓ | 20.14.0 | — |
| npm | asset pipeline | ✓ | 10.7.0 | — |
| MySQL | app DB (`yp-student-exam`) | ✓ | 8.0.45 (Herd, `127.0.0.1:3306`, already reachable with 18 tables) | — |
| Laravel Framework | app framework | ✓ | 11.55.0 installed | — |

**Missing dependencies with no fallback:** None — every dependency this phase touches is already installed and connected, verified live during this research session.

**Missing dependencies with fallback:** None.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.0.1 [VERIFIED: composer.json] |
| Config file | `phpunit.xml` (DB env vars commented out → uses `.env`'s live MySQL connection) |
| Quick run command | `php artisan test --filter=DatabaseSeederTest` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|---------------------|--------------|
| DEL-01 | Seeder creates lecturer + students with verified emails + correct roles | Feature | `php artisan test --filter=test_seeder_builds_full_demo_graph` | ❌ Wave 0 |
| DEL-01 | Classrooms exist with students assigned via `classroom_id` | Feature | (same test as above) | ❌ Wave 0 |
| DEL-01 | Subjects linked to classroom(s) via `classroom_subject` | Feature | (same test) | ❌ Wave 0 |
| DEL-01 | A published, time-limited exam with an MCQ (≥2 options, one correct) and an open-text question, assigned to a classroom via `exam_classroom` | Feature | (same test) | ❌ Wave 0 |
| DEL-01 | The pre-graded demo attempt reaches the expected `submitted` state with its MCQ answer graded and open-text answer pending | Feature | (same test) | ❌ Wave 0 |
| DEL-01 (D-05) | Re-running the seeder is idempotent — no duplicate rows on `users`, `classrooms`, `subjects`, `exams`, `questions`, `options`, `attempts`, `answers` | Feature | `php artisan test --filter=test_seeder_is_idempotent_on_repeat_runs` | ❌ Wave 0 (extends existing `TestAccountSeederTest.php` pattern — recommend consolidating into the new file) |
| DEL-01 (D-05) | `php artisan migrate:fresh --seed` succeeds against a genuinely empty MySQL schema | Manual | `php artisan migrate:fresh --seed` (run by executor/human against the live `yp-student-exam` DB — already confirmed reachable) | N/A — manual checkpoint, not automatable inside this environment's test harness |
| DEL-02 | README exists at repo root, contains the setup commands and demo credentials | Feature | `php artisan test --filter=test_readme_documents_setup_and_credentials` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --filter=DatabaseSeederTest`
- **Per wave merge:** `php artisan test` (full suite)
- **Phase gate:** Full suite green, plus the manual `php artisan migrate:fresh --seed` clean-clone check, before `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/DatabaseSeederTest.php` — covers DEL-01 (full graph assertions + idempotency). Recommend consolidating `tests/Feature/TestAccountSeederTest.php`'s two existing tests into this new file (its two assertions become a strict subset of the expanded graph's assertions) rather than maintaining two overlapping seeder test files — or leave `TestAccountSeederTest.php` in place and add the new file alongside it if the executor prefers not to touch existing passing tests. Either is acceptable; note the overlap so it's a deliberate choice, not an oversight.
- [ ] A README-content assertion — either a new small `tests/Feature/ReadmeTest.php` or a few extra assertion methods folded into `DatabaseSeederTest.php` (`assertFileExists(base_path('README.md'))`, `assertStringContainsString('migrate:fresh --seed', file_get_contents(...))`, `assertStringContainsString('lecturer@example.com', ...)`). Recommend a separate lightweight test class since a README assertion is conceptually unrelated to seeder-graph assertions.
- [ ] No framework/config install needed — PHPUnit 11 and the live MySQL connection are already fully set up and verified this session.

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|----------------|---------|--------------------|
| V2 Authentication | No — no new auth code this phase | Already covered by Breeze + Phase 1 (`role`/middleware); this phase only creates accounts through the already-hashed (`'password' => 'hashed'` cast) `User` model, never bypassing the hash |
| V3 Session Management | No — no new session code | N/A |
| V4 Access Control | No — no new endpoints/routes | N/A — the seeder writes data, it does not expose any new access-controlled surface |
| V5 Input Validation | No — seeder input is developer-authored constants, not user input | N/A |
| V6 Cryptography | Partial — see below | Demo passwords are hashed via the existing `User` model cast (`Hash::make`/`'hashed'` cast), same as every other account in the app; never store plaintext |

### Known Threat Patterns for this phase

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|------------------------|
| Publicly documented, intentionally weak demo credentials (`password`) shipped in a public README | Information Disclosure (by design, but must be scoped) | README must clearly frame these as **demo/evaluation-only credentials for a scoped grading deliverable**, not a production security posture — this is already what D-03/D-04 call for; the risk this mitigates is a reader assuming the weak password reflects the app's real security bar rather than a deliberate, disclosed demo convenience. |
| `.env` (with a real, generated `APP_KEY` and possibly real DB credentials) accidentally committed alongside the public README | Information Disclosure | Confirm `.env` remains gitignored (standard Laravel `.gitignore` already covers this — not modified by this phase) and note in the README's "Publishing to GitHub" section that only `.env.example` (placeholders) should ever be committed, distinct from the intentionally-public demo *application* credentials. |
| `APP_DEBUG=true` / Telescope left publicly reachable in the shipped default config | Information Disclosure | Already flagged in `.planning/research/PITFALLS.md` Pitfall 8/Security Mistakes — out of this phase's direct scope to fix (no `.env.example` edits mandated by D-01..D-06), but the README should not instruct anyone to set `APP_DEBUG=true` for any documented step. |

## Sources

### Primary (HIGH confidence)
- `database/seeders/DatabaseSeeder.php`, `database/factories/*.php`, `app/Services/AttemptGrader.php`, `app/Models/*.php`, `app/Enums/*.php`, `database/migrations/*.php`, `routes/lecturer.php`, `routes/student.php`, `phpunit.xml`, `composer.json`, `package.json`, `.claude/CLAUDE.md`, `tests/Feature/TestAccountSeederTest.php`, `tests/Feature/Grading/AttemptGraderTest.php`, `tests/Feature/DomainSchemaTest.php`, `tests/Feature/Lecturer/ClassroomSubjectLinkageTest.php`, `tests/Feature/Lecturer/ExamAssignmentTest.php` — all read directly this session.
- `php artisan --version`, `php artisan db:show` — run live this session against the actual project.
- `.planning/phases/06-demo-seeder-delivery/06-CONTEXT.md`, `.planning/REQUIREMENTS.md`, `.planning/PROJECT.md`, `.planning/research/PITFALLS.md` — read directly.

### Secondary (MEDIUM confidence)
- [Database: Seeding | Laravel 11.x docs](https://laravel.com/docs/11.x/seeding) — surfaced via WebSearch, cross-referenced against 8.x-13.x versions of the same doc page showing stable `firstOrCreate`/`call()` semantics.
- [Database Testing | Laravel 13.x docs](https://laravel.com/docs/13.x/database-testing) — RefreshDatabase transaction-vs-migration behavior by driver, cross-referenced via WebSearch.
- [Improve the Performance of Laravel Feature Tests using MySQL Instead of SQLite — Owen Conti](https://owenconti.com/posts/improve-performance-laravel-feature-tests-using-mysql-instead-of-sqlite-or-memory-databases) — MySQL auto-increment-doesn't-reset behavior, MEDIUM confidence community source cross-checked against official docs' RefreshDatabase description.

### Tertiary (LOW confidence)
- None used as a sole basis for any claim in this document — all WebSearch findings above were cross-referenced against official `laravel.com/docs` results appearing in the same search.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages; every version claim verified live (`php artisan --version`, `db:show`, `composer.json` read directly)
- Architecture: HIGH — every pattern is derived from reading the actual existing models/migrations/services/tests in this codebase, not inferred
- Pitfalls: HIGH for codebase-specific pitfalls (1, 2, 5, 6, 7, 8 — all directly verified against source); MEDIUM for the MySQL auto-increment pitfall (3 — WebSearch cross-referenced against official docs, not independently reproduced in this session)

**Research date:** 2026-07-16
**Valid until:** 2026-08-16 (30 days — this phase depends only on already-stable, already-installed Laravel 11 primitives; no fast-moving external dependency)

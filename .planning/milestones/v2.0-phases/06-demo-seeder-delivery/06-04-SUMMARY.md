---
phase: 06-demo-seeder-delivery
plan: 04
subsystem: testing
tags: [laravel, mysql, migrate-fresh, phpunit, breeze, clean-clone, acceptance-gate]

# Dependency graph
requires:
  - phase: 06-02
    provides: idempotent DatabaseSeeder building the full demo graph (lecturer, 3 students, 2 classrooms, 2 subjects, published exam, pre-graded attempt)
  - phase: 06-03
    provides: project README documenting setup, demo credentials, and per-role walkthrough
provides:
  - Confirmed `php artisan migrate:fresh --seed` succeeds end-to-end on the live MySQL `yp-student-exam` schema (13 migrations + full seed, no key-length error)
  - Confirmed full `php artisan test` suite green (176 passed / 469 assertions) against the same MySQL DB
  - Confirmed the seeded demo graph and documented credentials are exactly correct (roles, verification, classroom assignment, exam config, pre-graded attempt state) via `php artisan tinker`
  - Confirmed class-scoped exam visibility live (`Exam::visibleTo()`) for both the fresh-take student and the excluded student3
  - Fixed a project-wide gap discovered during the gate: the base Laravel/Breeze scaffold had never been committed to git, which would have made a real clean clone fail at `composer install`
  - DB left seeded with the full demo dataset, ready for the end-of-phase human README walkthrough
affects: [milestone-close, project-delivery]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Acceptance gate runs migrate:fresh --seed, then the full suite, then re-seeds (RefreshDatabase wipes the live dev DB) so the demo dataset is present for the human walkthrough."

key-files:
  created:
    - .planning/phases/06-demo-seeder-delivery/06-04-SUMMARY.md
  modified: []

key-decisions:
  - "Committed 98 previously-untracked Laravel/Breeze scaffold files (composer.json, artisan, config/, public/index.php, bootstrap/providers.php, base users/cache/jobs migrations, Breeze auth controllers/views/routes, .env.example, build tooling manifests) that had existed on disk but were never added to git across the project's entire history, blocking the very clean-clone path this plan exists to gate (Rule 2 - missing critical functionality)."

patterns-established: []

requirements-completed: [DEL-01, DEL-02]

# Metrics
duration: 15min
completed: 2026-07-15
status: complete
---

# Phase 6 Plan 4: Clean-Clone Acceptance Gate Summary

**Fixed a repo-wide gap where the Laravel/Breeze scaffold was never committed to git, then proved `migrate:fresh --seed` + the full 176-test suite pass clean against the live MySQL `yp-student-exam` database and the seeded demo graph exactly matches the README.**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-07-15T20:55:00Z (approx)
- **Completed:** 2026-07-15T21:10:07Z
- **Tasks:** 1 (verification/acceptance task, no plan-scoped source changes)
- **Files modified:** 98 (scaffold fix, see Deviations)

## Accomplishments

- `php artisan migrate:fresh --seed` exits 0 against the live MySQL `yp-student-exam` schema — all 13 migrations plus the full demo seed complete cleanly, no "Specified key was too long" error (the `defaultStringLength(191)` guard from Phase 1 is intact).
- Full `php artisan test` is green: **176 passed, 469 assertions**, including `DatabaseSeederTest` (full graph + idempotency) and `ReadmeTest` (README content contract), run against the same MySQL connection with `RefreshDatabase`.
- Re-ran `migrate:fresh --seed` after the suite to restore the demo dataset (the suite's `RefreshDatabase` wipes it), leaving the DB seeded and immediately usable.
- Verified the full demo graph programmatically via `php artisan tinker`, matching the README's "Seeded Demo Credentials" table exactly:
  - `lecturer@example.com` — role `lecturer`, verified, password `password` valid.
  - `student@example.com` — role `student`, verified, classroom `Demo Classroom`, **no attempt** (fresh take available, matches README).
  - `student2@example.com` — role `student`, verified, classroom `Demo Classroom`, attempt status `submitted` (not `graded`): MCQ answer auto-graded correct (score 1.00), open-text answer ungraded (score null) — exactly the pending-grading state the README's lecturer walkthrough describes.
  - `student3@example.com` — role `student`, verified, classroom `Advanced Classroom`.
  - Classrooms: `Demo Classroom`, `Advanced Classroom`. Subjects: `MATH101`/Mathematics, `SCI101`/Science.
  - Exam "Mathematics Midterm": published, 30-minute duration, assigned only to `Demo Classroom`, 2 questions (1 MCQ, 1 open-text).
- Confirmed class-scoped access live via `Exam::visibleTo()`: `student` sees `["Mathematics Midterm"]`; `student3` sees `[]` (Advanced Classroom is not assigned the exam) — matches the README's documented class-scoping demonstration.
- Confirmed the `login` route is registered and reachable (`GET|HEAD login`).
- Cross-checked the README against reality end-to-end (setup commands, `.env` DB vars, seed command, demo credentials table, per-role walkthrough steps, test-suite caveat, publishing-gate note) — all accurate; no textual mismatch found.
- No `git push`, `git remote add`, or any publishing action was performed (D-06 stays user-gated).

## Task Commits

Each task was committed atomically:

1. **Task 1: Clean-clone acceptance gate** — no plan-scoped source changes were required (verification-only task); the one code change made was the Rule 2 scaffold fix below, committed separately as it was discovered as a blocking prerequisite for the gate itself.

**Deviation commit:** `0ccf34a` (fix: commit missing Laravel/Breeze scaffold blocking clean clone)

**Plan metadata:** (this SUMMARY + STATE/ROADMAP update, committed via final metadata commit)

## Files Created/Modified

- `.planning/phases/06-demo-seeder-delivery/06-04-SUMMARY.md` - this summary

98 previously-untracked scaffold files were added to git in commit `0ccf34a` (see Deviations) — no application/domain logic was changed, only git tracking state.

## Decisions Made

- Treated the untracked-scaffold discovery as a Rule 2 (missing critical functionality) auto-fix rather than an architectural question: without `composer.json`/`artisan`/`config/`/`public/index.php`/base migrations tracked in git, a real `git clone` cannot run a single README step, which is the exact failure mode DEL-01/DEL-02 exist to prevent. Fixed inline, verified, and re-ran the gate rather than pausing for a checkpoint.
- Added files individually by explicit path (never `git add .`/`git add -A`), relying on git's automatic respect for nested `.gitignore` placeholders (`storage/*/.gitignore`, `bootstrap/cache/.gitignore`) so generated/compiled content (`storage/framework/views/*.php`, `storage/logs/laravel.log`, `bootstrap/cache/packages.php`/`services.php`, `public/build/*`, `vendor/`, `node_modules/`, `.env`) correctly stayed ignored.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical Functionality] Committed the never-tracked Laravel/Breeze application scaffold**
- **Found during:** Task 1, before running `migrate:fresh --seed` — a `git status` audit surfaced 98 untracked files that are all essential to running the app at all.
- **Issue:** Across the project's entire git history (Phases 1-6), only application-specific domain code (models, controllers, migrations for domain tables, views for the exam workflow, etc.) had ever been committed. The base Laravel 11 + Breeze scaffold — `composer.json`, `composer.lock`, `artisan`, `bootstrap/providers.php`, all of `config/`, `public/index.php` and static assets, the three base `users`/`cache`/`jobs` migrations, Breeze's auth controllers/requests/views/routes, `package.json`/`package-lock.json`, build tooling configs (`vite.config.js`, `tailwind.config.js`, `postcss.config.js`), `phpunit.xml`, `.env.example`, and the Laravel-default `storage`/`bootstrap/cache` `.gitignore` placeholders — was present on disk (because the working tree had been `composer create-project`'d locally) but never `git add`ed. A real `git clone` of this repository, before this fix, could not run `composer install` (no `composer.json`) or any `php artisan` command (no `artisan` file) — the clean-clone path DEL-01/DEL-02 exist to gate was entirely broken, and the README's own "Publishing to GitHub" section ("Only `.env.example`... is ever committed") was itself false since `.env.example` wasn't committed either.
- **Fix:** Staged and committed all 98 files by explicit path (never a blanket `git add .`), confirming nested Laravel `.gitignore` placeholders correctly excluded all generated/compiled/vendor content from the commit.
- **Files modified:** 98 files across `app/Http/Controllers/Auth/`, `app/Http/Requests/`, `app/View/Components/`, `artisan`, `bootstrap/`, `composer.json`/`composer.lock`, `config/`, `database/.gitignore` + 3 base migrations, `package.json`/`package-lock.json`, `phpunit.xml`, `postcss.config.js`/`tailwind.config.js`/`vite.config.js`, `public/`, `resources/css/`, `resources/js/`, `resources/views/{auth,components,layouts,profile}/` + `dashboard.blade.php`/`welcome.blade.php`, `routes/auth.php`/`routes/console.php`, `storage/` gitignore placeholders, `tests/` (Breeze's default Auth/Profile/Example tests), `.editorconfig`/`.env.example`/`.gitattributes`/`.gitignore`.
- **Verification:** After committing, `php artisan migrate:fresh --seed` and `php artisan test` were run and both succeeded exactly as documented above — confirming the tracked scaffold is complete and self-sufficient.
- **Committed in:** `0ccf34a` (fix(06-04): commit missing Laravel/Breeze scaffold blocking clean clone)

---

**Total deviations:** 1 auto-fixed (Rule 2 - missing critical functionality)
**Impact on plan:** Essential fix — without it, DEL-01/DEL-02 could never have been satisfied by an actual `git clone`, only by the pre-populated working directory this agent (and every prior phase's agent) happened to be running in. No scope creep: no application/domain behavior was changed, only git tracking state of files that already existed on disk.

## Issues Encountered

None beyond the deviation above. `migrate:fresh --seed` and the full test suite both passed on the first attempt after the scaffold fix.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 6 (Demo Seeder & Delivery) is functionally complete: DEL-01 (seeder + clean-clone `migrate:fresh --seed`) and DEL-02 (README + demo credentials) are both verified against the live MySQL database, not just the transactional test harness.
- **Outstanding for end-of-phase human verification (not blocking this plan, per `workflow.human_verify_mode: end-of-phase`):** a human should follow the README's Per-Role Walkthrough in a browser — log in as `lecturer@example.com`/`password`, grade `student2`'s pending open-text answer, view results; log out and log in as `student@example.com`/`password`, take the exam under the live countdown timer, submit; log in as `student3@example.com`/`password` and confirm the Mathematics Midterm is not visible. This plan's automated + programmatic verification (tinker-level credential/graph/visibility checks, `route('login')` reachability) strongly predicts this will pass, but the actual browser session is the documented human-check.
- This is the final plan of Phase 6 and of the project's v1.0 milestone build. Publishing to a public GitHub repository (D-06) remains a deliberate user-gated step outside this codebase, per README's "Publishing to GitHub" section — nothing was pushed or remoted in this plan.

---
*Phase: 06-demo-seeder-delivery*
*Completed: 2026-07-15*

## Self-Check: PASSED

- FOUND: commit `0ccf34a` (scaffold fix) in git log
- FOUND: `database/migrations/0001_01_01_000000_create_users_table.php`
- FOUND: `composer.json` tracked in git
- FOUND: `.planning/phases/06-demo-seeder-delivery/06-04-SUMMARY.md`

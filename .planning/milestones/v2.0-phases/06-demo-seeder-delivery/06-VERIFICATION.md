---
phase: 06-demo-seeder-delivery
verified: 2026-07-15T21:21:11Z
status: human_needed
score: 7/8 must-haves verified
behavior_unverified: 1
overrides_applied: 0
human_verification:
  - test: "Following ONLY the README (no insider knowledge), after `php artisan migrate:fresh --seed` and `php artisan serve`: (1) log in as lecturer@example.com/password, confirm the Mathematics Midterm shows a pending open-text answer for student2's submitted attempt, grade it, view results per exam/student; (2) log out, log in as student@example.com/password, confirm the assigned Mathematics Midterm is visible, start it under the live countdown timer, submit; (3) log in as student3@example.com/password, confirm the Mathematics Midterm is NOT visible (class-scoped access)."
    expected: "Both roles log in successfully via the documented credentials and every step behaves exactly as the README describes — including the live timer UI and the grading UI interaction, which cannot be exercised by an automated check."
    why_human: "This is a real-time browser UI/UX flow (countdown timer rendering, grading form interaction, page-by-page navigation using only README instructions) that grep/test-suite checks cannot observe. The plan (06-04-PLAN.md) itself defers this exact check to end-of-phase human verification per workflow.human_verify_mode=end-of-phase. Programmatic evidence gathered during this verification (Auth::attempt success for all 4 seeded accounts, full 176-test suite green including class-scoping ExamAccessTest/ExamIndexTest, and 06-04's tinker-level Exam::visibleTo() checks) strongly predicts a pass but does not substitute for the actual browser session."
---

# Phase 6: Demo Seeder & Delivery Verification Report

**Phase Goal:** A stranger with an empty MySQL database and a git clone can stand up a working, populated demo end-to-end.
**Verified:** 2026-07-15T21:21:11Z
**Status:** human_needed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | `php artisan migrate:fresh --seed` on an empty DB produces the full demo graph (lecturer, verified students, classrooms with students, subjects linked, published time-limited exam with MCQ + open-text question assigned to a classroom, pre-graded submitted attempt) — ROADMAP SC1 / DEL-01 | ✓ VERIFIED | Read `database/seeders/DatabaseSeeder.php` — confirms `firstOrCreate`-based graph construction exactly as claimed. Ran `php artisan migrate:fresh --seed` myself against the live MySQL `yp-student-exam` DB — exit code 0, all 13 migrations + seed completed, no "Specified key was too long" error. Confirmed via `php artisan tinker`: 4 users, 2 classrooms, 2 subjects, 1 exam, 2 questions, 4 options, 1 attempt (status `submitted`), 2 answers. Ran `php artisan test --filter=DatabaseSeederTest` myself — both `test_seeder_builds_full_demo_graph` (32 assertions across the full graph) and idempotency test PASS. |
| 2 | Re-running the seeder is idempotent (no duplicate rows by natural key) — DEL-01 / D-05 | ✓ VERIFIED | Ran `php artisan db:seed --force` a second time against the live MySQL DB (no `migrate:fresh`) and re-checked counts via tinker: users=4, classrooms=2, subjects=2, exams=1, questions=2, options=4, attempts=1, answers=2 — identical to the first run. `test_seeder_is_idempotent_on_repeat_runs` (which seeds twice inside a transaction and asserts row-count equality across all 8 tables) passes. |
| 3 | README.md documents setup, DB creation, migration, seeding, demo credentials, run/test, and the credentials match the seeder exactly — ROADMAP SC2 / DEL-02 | ✓ VERIFIED | Read `README.md` — contains clean-clone setup (clone/composer install/npm install/.env/key:generate/CREATE DATABASE/DB_* vars/migrate:fresh --seed/npm run build/php artisan serve), a demo-credentials table with all 4 seeded emails (byte-identical to `DatabaseSeeder.php`: lecturer@example.com, student@example.com, student2@example.com, student3@example.com, all password `password`), per-role walkthrough, a tests-wipe-demo-data caveat, and a manual (non-automated) GitHub-publishing note. Ran `php artisan test --filter=ReadmeTest` myself — passes (8 assertions). |
| 4 | The repo is actually cloneable/buildable — base Laravel/Breeze scaffold (`composer.json`, `artisan`, `config/`, `public/index.php`, `bootstrap/app.php`, `.env.example`) is git-tracked | ✓ VERIFIED | Ran `git ls-files \| grep -E "^composer\.json$\|^artisan$\|^bootstrap/app\.php$\|^public/index\.php$\|^\.env\.example$"` — all 5 present. `config/` has 10 tracked files (app/auth/cache/database/filesystems/logging/mail/queue/services/session). `bootstrap/providers.php`, `phpunit.xml`, `package.json` also tracked. This directly confirms the 06-04-SUMMARY claim that 98 previously-untracked scaffold files were committed in `0ccf34a` — verified independently via git, not by trusting the SUMMARY narrative. |
| 5 | No secrets committed — `.env`, `vendor/`, `node_modules/` are NOT git-tracked; no `.env` anywhere in git history | ✓ VERIFIED | `git ls-files \| grep -E "^\.env$\|^vendor/\|^node_modules/"` returns empty. `git log --all --full-history -- ".env"` returns empty (never committed, any point in history). `git check-ignore -v .env` confirms `.gitignore:9:.env` actively ignores it. `git show HEAD:.env.example` confirms the tracked example file contains only placeholder values (`APP_KEY=` empty, `DB_PASSWORD=` commented/empty, no real secrets). |
| 6 | Full test suite is green (176 tests) | ✓ VERIFIED | Ran `php artisan test` myself (full suite, single run) against the live MySQL DB (confirmed via `phpunit.xml` — the `DB_CONNECTION=sqlite` override is commented out, so the suite genuinely exercises MySQL, not an in-memory shortcut). Result: **176 passed, 469 assertions**, 0 failures — matches the 06-04-SUMMARY claim exactly, independently reproduced. Re-ran `migrate:fresh --seed` afterward to restore the demo dataset the suite's `RefreshDatabase` wiped. |
| 7 | No `git push`/remote was automated — publishing stays a user-gated manual step (D-06) | ✓ VERIFIED | `grep -n "git push\|git remote" README.md` returns nothing. `git remote -v` returns empty (no remote configured in this repo at all). `git log --oneline -5` shows only local commits (`0fd963c`, `0ccf34a`, `574ddad`, `d3eb37d`, `cf55e3a`) with no push/remote activity. README's "Publishing to GitHub" section documents the step as manual-only, with no command included. |
| 8 | Following only the README, a human logs in as both a Lecturer and a Student, and the author/assign/take/grade/results + class-scoping walkthrough behaves as documented (ROADMAP SC2 / DEL-02 / D-05) | ⚠️ PRESENT_BEHAVIOR_UNVERIFIED | Strong supporting programmatic evidence gathered: ran `Auth::attempt(['email' => X, 'password' => 'password'])` against the live seeded DB myself for all 4 accounts — all 4 return `true` (real credential-check success, not just field presence). Full suite includes `Student\ExamAccessTest`/`Student\ExamIndexTest` (class-scoping/visibility logic green) and `login` route confirmed registered (per 06-04-SUMMARY, independently plausible from routes files). However, the actual browser session — live countdown timer rendering, the lecturer's grading-form interaction, and step-by-step README-only navigation — cannot be exercised by grep/test-suite checks. The phase's own 06-04-PLAN.md defers this exact check to `<human-check>` at end-of-phase (`workflow.human_verify_mode: end-of-phase`), which is honored here rather than silently marked passed. |

**Score:** 7/8 truths verified (1 present + wired, behavior-unverified — routed to human verification below)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `database/seeders/DatabaseSeeder.php` | Expanded idempotent demo-graph seeder (D-01/D-02/D-03) | ✓ VERIFIED | 239 lines. Contains `firstOrCreate` (parents), `wasRecentlyCreated` guard (questions/options — no unique index), `sync()` (pivots), and a `seedDemoAttempt()` helper that calls the real `AttemptGrader` service. Substantive, not a stub — confirmed by reading the full file and by my own `migrate:fresh --seed` + `db:seed` runs against live MySQL. |
| `README.md` | Project README covering setup, DB creation, seeding, demo credentials, per-role walkthrough, tests, GitHub-publishing note | ✓ VERIFIED | 108 lines. All required sections present (title, stack, clean-clone setup, credentials table, per-role walkthrough, tests-wipe caveat, manual GitHub-publish note). No git push/remote command present. |
| `tests/Feature/DatabaseSeederTest.php` | Executable contract for the expanded seeder graph + idempotency | ✓ VERIFIED | 141 lines. Two named methods (`test_seeder_builds_full_demo_graph`, `test_seeder_is_idempotent_on_repeat_runs`), asserting strictly by natural key (email/classroom name/subject code/exam title), never by numeric id. Both pass when I ran them. |
| `tests/Feature/ReadmeTest.php` | Executable contract for README content | ✓ VERIFIED | 31 lines. One named method asserting 7 required substrings including both `lecturer@example.com` and `student@example.com`. Passes when I ran it. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `DatabaseSeederTest.php` | `DatabaseSeeder.php` | `$this->seed(DatabaseSeeder::class)` then assert-by-natural-key | ✓ WIRED | Confirmed present in test file; test passes against live seeder. |
| `DatabaseSeeder.php` | `AttemptGrader.php` | `app(AttemptGrader::class)->gradeAutoGradable()` then `->syncStatus()` | ✓ WIRED | Both calls present in `seedDemoAttempt()`; confirmed the resulting attempt is `submitted` with MCQ graded correct (score = points) and open-text `score` null via my own tinker query. |
| `DatabaseSeeder.php` | `exam_classroom` pivot | `$exam->classrooms()->sync([$demoClassroom->id])` | ✓ WIRED | Present in `seedExam()`. `DatabaseSeederTest` asserts the exam IS assigned to Demo Classroom and IS NOT assigned to Advanced Classroom — both pass. |
| `README.md` | `DatabaseSeeder.php` | Demo-credentials table lists the exact seeded emails/password | ✓ WIRED | Cross-checked byte-for-byte: all 4 emails + `password` match between the two files exactly. |
| `README.md` | `.env.example` | Documents copying `.env.example` and setting the `DB_*` keys it defines | ✓ WIRED | README's documented `DB_CONNECTION`/`DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` match the variable names present in the tracked `.env.example` (confirmed via `git show HEAD:.env.example`, not the live untracked `.env`). |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| `migrate:fresh --seed` succeeds on the live MySQL schema | `php artisan migrate:fresh --seed` | Exit 0; 13 migrations + full seed, no key-length error | ✓ PASS |
| Seeder is idempotent outside the test harness | `php artisan db:seed --force` (second run, live DB) | Row counts identical to first run (4/2/2/1/2/4/1/2) | ✓ PASS |
| DatabaseSeederTest passes | `php artisan test --filter=DatabaseSeederTest` | 2 passed, 32 assertions | ✓ PASS |
| ReadmeTest passes | `php artisan test --filter=ReadmeTest` | 1 passed, 8 assertions | ✓ PASS |
| Full suite is green | `php artisan test` (single run) | 176 passed, 469 assertions, 0 failures | ✓ PASS |
| Seeded credentials actually authenticate | `Auth::attempt(['email'=>X,'password'=>'password'])` via tinker for all 4 accounts | All 4 return `true` | ✓ PASS |
| No debt markers in phase-modified files | `grep -n -E "TBD\|FIXME\|XXX\|TODO\|HACK\|PLACEHOLDER"` on `DatabaseSeeder.php`, `README.md`, both new tests | No matches (one incidental README line describing itself, not a stub) | ✓ PASS |
| No automated git push/remote | `grep "git push\|git remote" README.md`; `git remote -v` | Both empty | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|--------------|--------|----------|
| DEL-01 | 06-01, 06-02, 06-04 | Seeder creates demo data via `migrate:fresh --seed` (lecturer, students, classrooms, subjects, exam with both question types assigned to a classroom) | ✓ SATISFIED | Verified directly against live MySQL; DatabaseSeederTest green. |
| DEL-02 | 06-01, 06-03, 06-04 | README documents setup, DB creation, migration, seeding, demo credentials, and how to run/test | ✓ SATISFIED (credential-login programmatically verified; full UI walkthrough is the one human-check item) | README content verified against ReadmeTest + manual read-through; `Auth::attempt` confirms credentials work; full browser walkthrough deferred to human-check per plan's own design. |

No orphaned requirements found — REQUIREMENTS.md maps only DEL-01/DEL-02 to Phase 6, both claimed by plans.

### Anti-Patterns Found

None. Scanned `database/seeders/DatabaseSeeder.php`, `README.md`, `tests/Feature/DatabaseSeederTest.php`, `tests/Feature/ReadmeTest.php` for `TBD`/`FIXME`/`XXX`/`TODO`/`HACK`/`PLACEHOLDER`/stub patterns — no matches (one README line mentioning "placeholder values" is describing `.env.example`'s intended use, not a stub in this deliverable).

### Human Verification Required

### 1. README-only browser walkthrough as both roles

**Test:** Following ONLY the README (no insider knowledge), after `php artisan migrate:fresh --seed` and `php artisan serve`:
1. Log in as `lecturer@example.com` / `password` — the Mathematics Midterm shows a pending open-text answer for student2's submitted attempt; grade it; view results per exam/student.
2. Log out; log in as `student@example.com` / `password` — the assigned Mathematics Midterm is visible; start it under the live countdown timer and submit.
3. Log in as `student3@example.com` / `password` — the Mathematics Midterm is NOT visible (class-scoped access: student3 is in "Advanced Classroom", which the exam is not assigned to).

**Expected:** Both roles log in successfully and every step behaves as the README describes.

**Why human:** Real-time UI behavior (live countdown timer rendering, grading-form interaction) and end-to-end navigation using only the README's documented steps cannot be verified by grep or the automated test suite. This is explicitly deferred by the phase's own 06-04-PLAN.md to an end-of-phase `<human-check>` (project config: `workflow.human_verify_mode: end-of-phase`) rather than a blocking mid-execution checkpoint. Automated/programmatic evidence gathered in this verification (all 4 seeded accounts pass a real `Auth::attempt` credential check; the full 176-test suite — including class-scoping tests `Student\ExamAccessTest` and `Student\ExamIndexTest` — is green; 06-04-SUMMARY's own tinker-level `Exam::visibleTo()` checks matched expectations) strongly predicts this will pass, but a live browser session is the only way to confirm the timer and grading UI actually render and behave correctly.

### Gaps Summary

No gaps found. All ROADMAP Success Criteria and PLAN-frontmatter must-haves for DEL-01 and DEL-02 are verified directly against the live codebase and a live MySQL database (not merely re-stated from SUMMARY.md claims): the seeder produces the full idempotent demo graph, the README's documented setup/credentials/commands match the seeder exactly, the previously-undiscovered git-tracking gap (98 untracked Laravel/Breeze scaffold files) that would have broken a real clean clone was independently confirmed fixed (scaffold now tracked; `.env`/`vendor`/`node_modules` correctly excluded; no secrets in git history), the full 176-test suite is green, and no automated git push/remote occurred. The single outstanding item is the end-of-phase human browser walkthrough that the phase's own plan intentionally deferred (not a defect) — this routes the phase to `human_needed` rather than `passed`, per the verification decision tree (a `passed` status is invalid whenever any human-verification item exists).

---

*Verified: 2026-07-15T21:21:11Z*
*Verifier: Claude (gsd-verifier)*

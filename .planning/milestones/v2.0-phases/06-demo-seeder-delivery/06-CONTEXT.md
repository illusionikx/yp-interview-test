# Phase 6: Demo Seeder & Delivery - Context

**Gathered:** 2026-07-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Make the finished app reproducible and reviewable from a clean clone: a demo seeder that stands up a working, populated dataset, and a README that documents setup, seeded credentials, and how to run/test. A stranger with an empty MySQL database and a git clone should be able to `create db → install → migrate → seed → run` and log in as both roles to exercise every feature. Covers DEL-01, DEL-02.

**In scope:** expand `DatabaseSeeder` into a full, idempotent demo dataset; replace the Laravel-default README with a project README; and verify the clean-clone path (`php artisan migrate:fresh --seed` against an empty `yp-student-exam`).

**Out of scope:** the actual **public GitHub push** (brief technical requirement #3) — that is a USER-gated action (it needs the leaked-`glpat`-token rotated + a repo created under the user's account; see project memory). This phase documents the push in the README but does NOT push. No new app features (v2 is out).
</domain>

<decisions>
## Implementation Decisions

*(Auto mode: recommended defaults.)*

### Demo seeder (DEL-01)
- **D-01:** Expand `database/seeders/DatabaseSeeder.php` (building on the Phase-1 minimal lecturer+student) into a reviewable demo, all via **`firstOrCreate`/idempotent** so `migrate:fresh --seed` AND a re-run are safe: (a) 1 Lecturer + ~3 Students (fixed emails, `email_verified_at` set — Phase-1 pitfall); (b) 1–2 **Classrooms** with the students assigned (`classroom_id`); (c) 2–3 **Subjects** linked to a classroom (`classroom_subject`); (d) at least one **published Exam** (with a time limit) scoped to a subject, containing BOTH a multiple-choice question (≥2 options, one correct) and an open-text question, **assigned to the students' classroom** (`exam_classroom`) — so a seeded student can immediately take it.
- **D-02:** Seed ONE **pre-submitted, auto-graded demo attempt** for a second demo student (create the attempt + answers, then run `AttemptGrader::gradeAutoGradable()` + `syncStatus()` so it reaches a realistic `submitted`/`graded` state) — so the grading + results screens have content on first load (demonstrates the full pipeline without manual steps). Keep the FIRST demo student un-attempted so a reviewer can take the exam fresh. Keep this lightweight.
- **D-03:** Use factories where they exist (Classroom/Subject/Exam/Question/Option/Answer/Attempt) but pin **fixed, documented credentials** (deterministic emails + a shared demo password) so the README can list exact logins. Do not rely on random faker data for the accounts a reviewer logs into.

### README (DEL-02)
- **D-04:** Replace the Laravel-default `README.md` with a project README covering: what the app is (one-paragraph overview + the two roles); tech stack (Laravel 11 + Breeze, MySQL, Blade+Tailwind+Alpine); **setup from a clean clone** — `git clone`, `composer install`, `npm install`, copy `.env` + `php artisan key:generate`, **create the MySQL database `yp-student-exam`** (and set `DB_*`), `php artisan migrate:fresh --seed`, `npm run build` (or `npm run dev`), `php artisan serve`; the **seeded demo credentials** (lecturer + students, exact emails/password); a short **per-role walkthrough** (lecturer: manage classrooms/subjects, author + publish + assign an exam, grade open-text, view results; student: see assigned exam, take it under the timer, view result); how to **run the tests** (`php artisan test`) with the note that they use `RefreshDatabase` against the configured MySQL DB (so run against a dev DB); and a short **"Publishing to GitHub"** note flagging that the git author identity must be set correctly before pushing (see below).

### Clean-clone verification (the DEL-01 gate)
- **D-05:** Verify the whole path works: `php artisan migrate:fresh --seed` on an empty `yp-student-exam` succeeds (all migrations + the full seed), and the documented demo credentials log in as both roles. This is the phase's acceptance gate (a feature test asserting the seeder produces the expected graph, plus a manual clean-clone run).

### GitHub push (USER-gated — do NOT automate)
- **D-06:** The public-repo push is deferred to the user. The build's early commits (pre-`187e39e`) carry a leaked GitLab token in the author-email metadata; before any push the user must rotate that token, set their git identity, and scrub history (see `[[autonomous-assessment-build]]` memory). The README documents the intended repo/setup; this phase must NOT run `git push` / create a remote / publish anything.

### Claude's Discretion
- Exact number of demo students/subjects/questions, the seeder's internal structure (one file vs helper seeders), and README section ordering — planner/executor choice, provided D-01..D-06 hold.
</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase scope & requirements
- `.planning/ROADMAP.md` §"Phase 6" — goal + success criteria; DEL-01, DEL-02
- `.planning/REQUIREMENTS.md` — DEL-01/02 text
- `.planning/PROJECT.md` — "What This Is", stack, constraints, the validated feature list (for the README overview + walkthrough)
- `.planning/research/PITFALLS.md` — migration/seeder clean-clone (utf8mb4 index length — already guarded via defaultStringLength(191); `firstOrCreate` idempotency); seeded accounts need `email_verified_at`

### Existing surface (all phases)
- `database/seeders/DatabaseSeeder.php` (Phase-1 minimal seed to expand), `database/factories/*` (all model factories incl. grading states from Phase 5)
- `app/Services/AttemptGrader.php` (to grade the demo attempt), `app/Models/*` (the full domain), `app/Enums/{Role,QuestionType}.php`
- `README.md` (Laravel default — replace), `.env.example`, `composer.json`/`package.json` (setup commands), `config/database.php` (MySQL)
- Route names for the walkthrough: `routes/lecturer.php`, `routes/student.php`
</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`DatabaseSeeder`** (Phase 1) — minimal lecturer+student with `email_verified_at`; extend it.
- **All model factories** incl. Phase-5 Answer grading states (`mcqCorrect`/`mcqIncorrect`/`openText`), `Attempt::factory()->graded()`, `Exam::factory()->published()`.
- **`AttemptGrader`** (Phase 5) — grade the seeded demo attempt realistically.
- **`Schema::defaultStringLength(191)`** (Phase 1 AppServiceProvider) — the clean-clone utf8mb4 guard already in place.
- The full validated feature set + route names for the README walkthrough.

### Established Patterns
- Idempotent seeding via `firstOrCreate` on fixed keys (Phase 1 pattern).
- Fixed, documented demo credentials (Phase 1 seeded a lecturer/student already).

### Integration Points
- `DatabaseSeeder::run()` orchestrates the full graph.
- README at repo root replaces the Laravel default.
</code_context>

<specifics>
## Specific Ideas

- `migrate:fresh --seed` is the one-command clean-clone path — it MUST work end-to-end.
- Fixed demo credentials documented in the README (not random).
- One demo student un-attempted (reviewer takes it fresh); one with a pre-graded attempt (results visible immediately).
- The GitHub push is the user's step — do not automate it.
</specifics>

<deferred>
## Deferred Ideas

- **The actual `git push` to a public GitHub repo** → USER action (needs token rotation + repo creation + history scrub). Documented, not automated.
- **Any new app features / v2** (partial-credit, analytics, etc.) → out of scope.
- **CI/CD, Docker, deployment** → not required by the brief.

None are user scope-creep — they are the phase/project boundaries.
</deferred>

---

*Phase: 6-Demo Seeder & Delivery*
*Context gathered: 2026-07-16*

# Phase 14: Delivery — Dark Mode, Wiki Manual, Demo Data & Browser Tests - Context

**Gathered:** 2026-07-18
**Status:** Ready for planning
**Mode:** Smart discuss (autonomous, AFK-accepted) — grounded in `.planning/v3.md` (§User manual, §Others, §Bugfixes), ROADMAP Phase 14 notes, locked Decisions #6/#7, and a codebase scout. Reviewable/overridable.

<domain>
## Phase Boundary

The finished portal is legible in dark mode everywhere, explains itself (wiki manual behind a help button by the theme toggle), stands up from a clean clone (`migrate:fresh --seed`), and is proven in a real browser (Dusk). This is the final v3.0 phase.

**In scope:** FIX-02 (dark-mode legibility sweep), UX-05 (help button + wiki manual), DEL-06 (manual content), SEED-01..04 (demo data), TEST-01..04 (Dusk browser tests + suite stays green).

**Nothing deferred beyond this phase** — this is milestone close.
</domain>

<decisions>
## Implementation Decisions

### FIX-02 — dark-mode legibility sweep (SEMANTIC, not syntactic)
- The bug is **wrong color pairing** (e.g. dark text on a dark surface), NOT a missing `dark:` prefix — no grep for "missing dark:" finds it. ~416 `dark:` occurrences across ~28 files, no token layer.
- **Audit components FIRST** (highest leverage): `<x-status-pill>` and any non-gray semantic color are where a past blanket find/replace already broke once. Then sweep **page by page** through the new hierarchy. The **exam editor** was named in the bug report — treat it as a required checkpoint.
- `tailwind.config.js` already has `darkMode: 'class'` and the theme toggle (navigation.blade.php) already works — this is fixing pairings, not wiring dark mode.
- **Verification is inherently VISUAL** — "no text dark-on-dark" is confirmed by eye/Dusk screenshot, not PHPUnit. Fix systematically; flag the final legibility pass as a manual/Dusk check.

### UX-05 + DEL-06 — help button + wiki manual
- A **help button beside the light/dark toggle** (currently help is a separate nav link — move/add it next to the toggle in navigation.blade.php). Opens the user manual.
- Manual is **wiki-style**: topic navigation (a sidebar/index of topics) with **cross-links between topics**. Build on the existing `resources/views/{lecturer,student}/help.blade.php`.
- **DEL-06 content names shipped UI labels VERBATIM** — this is last on purpose (v2.0 Phase 8 precedent): phases 11–13 are done, so the labels are now final. Walk the actual shipped screens and quote their button/link/tab text exactly.

### SEED-01..04 — demo data (re-runnable)
- `php artisan migrate:fresh --seed` from an empty DB produces: **many uniquely-named lecturers** (the ONLY accounts carrying Dr/PhD prefixes) and **many students**; **past-semester** data holding graded exams and filled classes that **exercise every available status**; plus **3–5 further** subjects, classes, and exams.
- **Past-semester data MUST route through `App\Support\Semester`** (Phase 9), NEVER a `->subMonths(N)` fudge (SEED-02).
- Keep re-runnable: `firstOrCreate` for the documented named accounts (`lecturer@example.com`/`student@example.com`, already present), plain `factory()->count(n)` for bulk. `db:seed` must not throw on a second run.
- **This is autonomously verifiable** — running `migrate:fresh --seed` is a DB operation (no browser).

### TEST-01..04 — Dusk browser tests
- **Install `laravel/dusk` as a dev dependency** (`composer require --dev laravel/dusk`, then `php artisan dusk:install`). This is a **sanctioned exception** to CLAUDE.md's "no new packages": the brief mandates Dusk (`v3.md` §Others "use dusk for testing") and Decision #7 depends on it. Dev-only, testing tool.
- **Decision #7 (LOCKED):** Dusk gets **`.env.dusk.local` + `DatabaseTruncation`** against a **separate database** BEFORE the first test runs — `RefreshDatabase` cannot cross Dusk's real HTTP process boundary, and the default would wipe the documented `yp-student-exam` demo seed. NEVER point Dusk at `yp-student-exam`.
- Dusk drives the **primary student and lecturer flows** by **CLICKING through the navigation** (proves NAV-04 reachability) — not `visit(route(...))` directly. Use **`waitFor()` over `pause()`** for every Alpine-driven assertion.
- **Decision #6 (LOCKED):** `beforeunload` (v2.0's AVL-05) stays a **MANUAL** check — ChromeDriver 126+ auto-dismisses the native prompt before Dusk's dialog API sees it. Do NOT plan to automate it.
- The existing **PHPUnit suite stays green** alongside Dusk (they use separate databases/configs).

### Claude's Discretion
- Manual topic taxonomy and page structure; exact seeded counts within "many"/"3–5"; which flows Dusk covers first.
</decisions>

<execution_boundary>
## Autonomous vs. Manual (AFK run)
**Buildable + autonomously verifiable now:** the dark-mode pairing fixes (code), the help button + wiki manual (Blade), the seeder expansion (`migrate:fresh --seed` runs headless), the Dusk test FILES + `.env.dusk.local` + config, and keeping the PHPUnit suite green.

**Requires the user's machine (flag as manual-verification items, joining the existing deferred list — do NOT fake):**
- Actually running `php artisan dusk` — needs Chrome + ChromeDriver + Herd serving at `APP_URL`; Windows/Herd behavior is LOW-MEDIUM confidence and must be verified hands-on.
- Dark-mode visual legibility confirmation across pages.
- The `beforeunload` dialog (Decision #6 — manual by nature).

If `composer require --dev laravel/dusk` cannot run in this environment (no network), the executor documents that and leaves the Dusk test files + config in place for the user to `composer install` and run — it must NOT silently skip TEST-* or mark them passed.
</execution_boundary>

<code_context>
## Existing Code Insights

### Reusable Assets
- `tailwind.config.js` — `darkMode: 'class'` already set; the theme toggle (sun/moon, localStorage 'theme') lives in `resources/views/layouts/navigation.blade.php` ~line 58. FIX-02 fixes pairings, not wiring.
- `resources/views/lecturer/help.blade.php`, `resources/views/student/help.blade.php` — existing help pages + `lecturer.help.show`/`student.help.show` routes; expand into the wiki manual, move the entry to a button by the toggle.
- `database/seeders/DatabaseSeeder.php` — `firstOrCreate` idiom for named accounts (`lecturer@example.com`/`student@example.com`), subjects, sections already present; expand with bulk factories + past-semester data.
- `App\Support\Semester` — for all past/current/future dating (SEED-02). `<x-status-pill>` for statuses.
- Factories: User (`lecturer()`/`student()` states), Subject, Section, Exam, Question/Option, Attempt, Answer, Enrollment.

### Established Patterns
- Blade + Tailwind 3 + Alpine; NO new RUNTIME packages (Dusk is a sanctioned DEV/testing exception). `<x-toast>`, `<x-status-pill>`, `<x-confirm-modal>`, `<x-back-button>`.
- PHPUnit Feature tests use `RefreshDatabase` against the test DB; Dusk MUST use its own DB via `.env.dusk.local` + `DatabaseTruncation` (Decision #7).

### Integration Points
- navigation.blade.php: help button beside the theme toggle.
- DatabaseSeeder: bulk + past-semester demo data.
- New `tests/Browser/` (Dusk), `DuskTestCase`, `.env.dusk.local`, `tests/DuskTestCase.php` from `dusk:install`.
- README: document the demo credentials + `migrate:fresh --seed` + how to run Dusk (setup reproducibility is a graded criterion).
</code_context>

<specifics>
## Specific Ideas
- v3.md §User manual ("wiki style"), §Others (seed many uniquely-named lecturers/students, past data with graded exams + filled classes exercising all statuses, 3–5 more subjects/classes/exams, "use dusk for testing"), §Bugfixes (exam-editor dark-on-dark text + site-wide dark-mode check).
- Decisions #6 (beforeunload manual) and #7 (Dusk separate DB) are LOCKED.
- README must let a grader reproduce setup from a clean clone (create DB, migrate, seed, documented credentials, run tests).
</specifics>

<deferred>
## Deferred Ideas
- None — this is milestone close. Any Dusk run / dark-mode visual / beforeunload that can't be executed headless is a MANUAL VERIFICATION item for the user, not a deferral of scope.
</deferred>
</content>

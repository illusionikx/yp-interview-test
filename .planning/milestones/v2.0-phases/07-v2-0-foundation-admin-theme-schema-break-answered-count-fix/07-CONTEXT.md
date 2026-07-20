# Phase 7: v2.0 Foundation — Admin Theme, Schema Break & Answered-Count Fix - Context

**Gathered:** 2026-07-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 7 lands three functionally-independent slices together so a clean clone always boots:

1. **Admin theme + dark mode** (UI-01, UI-02) — views only; no model/migration/policy change.
2. **Answered-count bugfix** (FIX-01) — single Blade file; fully independent.
3. **Schema break + subject-scoped sections + visibility rewrite** (SEC-01/02/03, ENR-08, DEL-03) — one atomic sub-slice: in-place migration edits, `subject_user` + `enrollments` tables, `Exam::scopeVisibleTo()` rewrite, and seeder/factory rewrite must all land together or `migrate:fresh --seed` fails on a clean clone.

Student-facing enrollment *actions* (apply/withdraw/reject UI, capacity locking, availability windows, manuals) are **Phase 8** — Phase 7 only builds the schema, the section CRUD for lecturers, the subject↔lecturer assignment, and the enrollment-driven visibility predicate.

</domain>

<decisions>
## Implementation Decisions

### Admin Shell — Layout & Navigation
- **Top navbar with dropdown navigation — NO sidebar** (user override of UI-01's "sidebar" phrasing; the governing decision for this phase). Themed with Flowbite navbar + dropdown components.
- Role-based nav grouped into top-bar dropdowns: Lecturer → Subjects, Sections, Exams, Results; Student → Enroll, My Exams, Results.
- Brand: text wordmark **"Exam Portal"** in the navbar (replaces the Breeze application-logo SVG).
- Content in Flowbite cards below the top bar; keep the existing Breeze `max-w-7xl` centered container (conventional under a top nav) — Claude's discretion on exact width.

### Theme & Status Pills
- **Flowbite default blue** accent — no custom Tailwind color config.
- Semantic status pills: Enrolled / Published / Open = **green**; Draft / Withdrawn / "Opens…" = **gray**; Rejected / Closed = **red**; FULL = **amber**.
- **Comfortable** density (Flowbite default spacing).
- Dark mode (UI-02, locked): Tailwind `darkMode: 'class'`; sun/moon toggle in the top bar; persists in `localStorage`; defaults to OS preference (`prefers-color-scheme`) when unset. Apply the class pre-paint (inline head script) to avoid a flash.

### Claude's Discretion
- Exact content container width, card padding scale, section-CRUD page placement (nested under subject vs standalone), and demo-data volume in the reseed.

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- Breeze Blade layout: `layouts/app.blade.php` (`$slot` + optional `$header`), `layouts/navigation.blade.php` (top nav), `x-dropdown`, `x-nav-link`, `x-responsive-nav-link`, `x-application-logo`.
- Alpine.js 3 already bundled (used for the existing nav `x-data="{ open }"` and the attempt timer) — reuse for the dark-mode toggle; no new JS framework.
- Tailwind 3 + `@tailwindcss/forms` + Vite already wired (`resources/css/app.css`, `resources/js/app.js`, `tailwind.config.js`).
- Existing role views to reskin: `resources/views/lecturer/{classrooms,subjects,exams,results}`, `resources/views/student/{exams,attempts,results}`, `dashboard.blade.php`.

### Established Patterns
- Breeze `x-app-layout` slot component pattern; top-nav in `navigation.blade.php`.
- Locked-upstream schema patterns (research SUMMARY.md / REQUIREMENTS.md resolved-decisions table): `Exam::scopeVisibleTo()` is the ONE predicate shared by `Student\ExamController@index` and `ExamPolicy`/`AttemptPolicy` — only its WHERE clause changes (flat `classroom_id` → `whereHas('classrooms.enrollments', status=Enrolled)`); `enrollments` pivot needs a custom `Enrollment extends Pivot` via `->using()` for enum casts; `subject_user` cascade on delete; sections named `year-semester-count`.

### Integration Points
- Flowbite is **NOT installed** — add via npm (`flowbite`) + `tailwind.config.js` plugin & content glob + import in `app.css`/`app.js`. (Build-based, no CDN — Vite pipeline.)
- `tailwind.config.js`: add `darkMode: 'class'`.
- `layouts/app.blade.php` + `navigation.blade.php`: rebuild shell as Flowbite top-navbar; add pre-paint dark-mode head script.
- Migrations edited in place (`database/migrations/2026_07_15_*`), `database/seeders/DatabaseSeeder.php` + factories rewritten — README updated to note `migrate:fresh --seed` is mandatory (schema break).

</code_context>

<specifics>
## Specific Ideas

- User was explicit: **top navbar + dropdowns, no sidebar.** Plan-phase and ui-phase must honor this over the ROADMAP/REQUIREMENTS "sidebar navigation" wording.
- The schema-break + `scopeVisibleTo()` rewrite + seeder rewrite is a hard atomic unit — plan it as one cohesive execution slice with a cross-consumer regression test (list vs. gate agreement, covering enrolled / non-enrolled / withdrawn / rejected students) as a hard acceptance gate, not left to code review (per STATE.md blocker note).

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope. (Enrollment apply/withdraw/reject UI, capacity locking, availability windows, and manuals are already scoped to Phase 8.)

</deferred>

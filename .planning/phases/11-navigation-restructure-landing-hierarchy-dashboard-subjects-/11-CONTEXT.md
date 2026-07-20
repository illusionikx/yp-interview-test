# Phase 11: Navigation Restructure — Landing Hierarchy, Dashboard, Subjects & Enrollment - Context

**Gathered:** 2026-07-18
**Status:** Ready for planning
**Mode:** Smart discuss (autonomous) — grey areas proposed and auto-accepted while the user was AFK; recommendations grounded in `.planning/v3.md`, prior-phase decisions, and existing codebase conventions. Every decision below is reviewable and overridable.

<domain>
## Phase Boundary

Both roles land on ONE page after login (`dashboard` → role home) that carries a dashboard (gradient welcome banner + role-specific stat cards) **above** their subject list. From there, every destination drills down through the v3.md hierarchy:

```
login
├── dashboard (+ subject list, same page = home)
├── class enrollment (student only)
├── subject list (+simple CRUD for lecturer)
├── subject & class management (lecturer only) → {class & student mgmt, exam mgmt → {editor, grading}}
└── class (student only) → exam list → take exam
```

**In scope:** NAV-03 (hierarchy), NAV-04 (reachability audit, both directions), UX-04 (back buttons name their destination), DASH-01..04 (dashboard + cards), SUBJ-01..05 (subject lists + lecturer CRUD + student grouping/hide/enroll button), ENR-09..11 (single-page enrollment).

**Out of scope (later phases):** Class & student management internals and exam editor/grading (Phase 12), the student class page + take-exam (Phase 13), the help button + wiki manual + dark-mode sweep + seeding + Dusk (Phase 14). Phase 11 must not orphan the existing Results or help pages in the interim (NAV-04).
</domain>

<decisions>
## Implementation Decisions

### Dashboard — banner & cards (DASH-01..04)
- **One full-width welcome banner** at the top of the home page: greeting "Welcome back, {name}" with a subtle brand gradient (Tailwind `bg-gradient-to-r` over Phase 9 brand tokens) for pizzazz. Purely decorative; no data dependency.
- **Lecturer cards (3):** (1) Classes assigned **this and future semesters** — counted via `Semester` composite ordinal (`ordinal() >= Semester::current()->ordinal()`), never a naive `year>=Y AND semester>=S`; (2) Students enrolled vs total seats across all assigned classes, **with a progress bar** (`SUM(enrollments)` / `SUM(max_students)`); (3) third relevant stat: **attempts awaiting grading** (submitted-but-ungraded) — actionable for a lecturer.
- **Student cards (2):** (1) Subjects enrolled **this semester**; (2) second relevant stat: **exams available to take** (published exams in enrolled subjects not yet attempted).
- **All cards are bounded aggregates** — `COUNT` / `SUM` / `withCount` only, never a PHP loop over relationships (per phase notes; guards N+1 and keeps the page cheap).
- Layout: banner → responsive card grid (`grid sm:grid-cols-2 lg:grid-cols-3`) → subject list, all on the same home page. Cards reuse Phase 9's Flowbite token surfaces (`bg-neutral-primary-soft`, `border-default`, `rounded-base`).

### Subject list (SUBJ-01..05) — rendered as a table
- **Lecturer:** one **ungrouped** table of every assigned subject; columns: subject code, name, #classes, #exams; row action → subject & class management. Simple **CRUD** (create / edit / delete) reusing the existing `Lecturer\SubjectController` — add the missing create/edit/delete UI + confirm-modal on delete (Phase 9 `<x-confirm-modal>`).
- **Student:** table **grouped by semester** (semester heading rows, current + future first, past collapsible); each row shows subject detail **including the lecturer's name**, action → class page (Phase 13 target — link to `student.subjects.show` / class page route as it lands). **Hide/unhide past semesters** via an Alpine toggle, past **collapsed by default**.
- The student subject list is a **NEW query** (subjects the student is enrolled in, grouped by semester) — it is NOT `SubjectBrowseController`'s catalog.

### Class enrollment (ENR-09..11)
- **Single page**, explicit flow: **select subject → select class → enroll**. This is the existing `SubjectBrowseController` catalog **relabeled** "Class enrollment" (ENR-09) — logic unchanged, presentation clarified into the three-step flow.
- **No credit limit** — a student may enroll in as many subjects/classes as they want (ENR-10).
- Only classes whose **enrollment window is currently open** are enrollable (ENR-11) — disable/omit the enroll action otherwise. Reuse existing `EnrollmentController@store` + `EnrollRequest`.

### Navigation restructure & reachability (NAV-03, NAV-04, UX-04)
- The drill-down hierarchy is the primary navigation; the **navbar is trimmed** to what the hierarchy doesn't cover (brand, profile, light/dark toggle). The help button is Phase 14 — until then the existing help route stays reachable (do not delete it).
- **NAV-04 is a two-direction audit:** before removing any link, `grep resources/views` for every clickable `route(...)` reachable today and confirm each has a path in the new tree. **Results** and **help/manual** are the named at-risk destinations — they must remain reachable.
- **UX-04:** every back affordance is a **button with text that names where it leads** (e.g. "← Back to subject list"), never a bare "Back" link.

### Claude's Discretion
- Exact gradient colors, card iconography, and table column ordering.
- The precise third lecturer stat / second student stat if a more useful bounded aggregate emerges during planning (keep it a single aggregate query).
- Whether the student subject list and enrollment page share a layout partial.
</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `App\Support\Semester` — full API (`current()`, `ordinal()`, `isFuture()`, `isPast()`, `contains()`); use `ordinal()` for the "this and future semester" composite comparison.
- `App\Http\Controllers\DashboardController` (invokable) already dispatches `/dashboard` by role to `lecturer.home` / `student.home` — the home pages are where the dashboard+subject-list render.
- `Lecturer\SubjectController`, `StoreSubjectRequest`, `UpdateSubjectRequest` — subject CRUD backend exists; phase adds UI + wires delete.
- `Student\SubjectBrowseController` (catalog) → relabel to "Class enrollment". `Student\EnrollmentController` + `EnrollRequest` — enroll/withdraw exist.
- `Section` model = "Class" (Decision #3: UI-copy relabel only — do NOT rename model/table/FK/routes).
- Phase 9 components: `<x-toast>` (single flash renderer), `<x-confirm-modal>` (destructive confirm), Flowbite semantic tokens in `tailwind.config.js`.

### Established Patterns
- Route groups: `routes/web.php` (`/dashboard`), `routes/lecturer.php` (`role:lecturer`), `routes/student.php` (`role:student`). `student.home` currently `view('student.home')`; `student.help` exists.
- Flash via `<x-toast>` on `session('status')`/`session('error')`; toasts on create/save/delete (UX-03, already the app convention).
- Blade + Tailwind 3 + Alpine, no SPA. Views under `resources/views/{lecturer,student}`.

### Integration Points
- `student.home` / `lecturer.home` routes → new dashboard+subject-list views.
- New student "enrolled subjects grouped by semester" query (controller method + view).
- Enrollment page relabel (route name/label copy, not logic).
- Navbar partial in `resources/views/layouts` — trim per hierarchy, keep toggle/profile.
</code_context>

<specifics>
## Specific Ideas
- Login-page Flowbite reference and the exact hierarchy tree are fixed in `.planning/v3.md` (§General workflow, §UI details).
- Semester rule is fixed: S1 = Sep→Feb(next year), S2 = Mar→Jul; already implemented in `App\Support\Semester` (Phase 9).
- Dashboard "welcome + random gradient for pizzazz", cards as listed in v3.md §Dashboard.
</specifics>

<deferred>
## Deferred Ideas
- Help button beside the light/dark toggle + wiki-style user manual → Phase 14 (UX-05).
- Class & student management, exam editor, grading → Phase 12.
- Student class page + take-exam experience → Phase 13.
- Dark-mode compatibility sweep + demo seeding + Dusk browser tests → Phase 14.
</deferred>
</content>
</invoke>

# Online Examination Portal

## What This Is

A web portal for online examinations and student management, built on Laravel 11 with Breeze. Lecturers author subject exams (multiple-choice and open-text questions) and assign them to classes; students log in, see only the exams assigned to their class, and complete them within a time limit. Multiple-choice answers are auto-graded; open-text answers are graded by the lecturer. Built as an assessment deliverable for YP, shipped to a public GitHub repository with a README.

## Core Value

A student can take a time-limited exam that is correctly restricted to their class, and their answers are reliably captured and scored. If everything else fails, this must work: the right exam reaches the right student, the clock is enforced, and the submission is saved and graded.

## Current Milestone: v3.0 Workflow Restructure & UX Polish

**Source:** `.planning/v3.md` (user feature request, ingested 2026-07-17).

**Goal:** Restructure the app around one clear navigation hierarchy (login → dashboard + subject list → enrollment / class management / class → exam), anchored to a real semester model, with richer dashboards and demo data, a consistent alert/toast system, a reworked exam editor and take-exam experience, and browser-level tests.

**Target features:**
- Flowbite 4.0 login page restyle, plus a landing page before login (replacing the Laravel default)
- Navigation restructured to the v3 hierarchy; dashboard + subject list share one landing page after login
- Semester model as a first-class concept — **1st semester: September → February (following year); 2nd semester: March → July**; a semester always starts on the 1st day of its first month and ends on the last day of its last month
- Dashboard: welcome banner (gradient) + role-specific stat cards (lecturer: classes assigned this/future semester, enrolled vs. seats with progress bars; student: subjects enrolled this semester)
- Subject list: lecturer sees all assigned subjects with simple CRUD; student sees subjects grouped by semester with hide/unhide past semesters
- Class enrollment: one page, subject → class → enroll, no credit limit
- Class management: two tabs (classes, exams) with CRUD, exam draft/active status, reset-submission action with warning, grading progress
- Exam editor: details + questions as two tabs on one page, arrangeable answers with a shuffle option, question reordering, and a warning that saving cancels prior attempts
- Grading page: class/exam details, grading progress, full student list
- Class page: subject detail + exam list with not-yet-open / opened / closed status and taken/graded markers
- Take exam: sticky top bar (title, timer, progress), vertical stepper navigation with checkmarks, 10-minute toaster + red timer, instructions popup, answers in authored order (no randomization)
- App-wide UI system: official name **"Online Examination Portal"** with subtitle *"for Yayasan Peneraju Technical Assessment"*, one popup/alert style (no native `alert`), toasters on create/save/delete, explicit back buttons, help button beside the dark-mode toggle
- Wiki-style user manual (replacing v2.0's linear in-app manuals)
- Seed data: many lecturers and students with unique names (only lecturers carry Dr/PhD prefixes), past semesters with graded exams and filled classes covering every status, plus 3–5 more subjects/classes/exams
- **Laravel Dusk** browser testing
- Bugfixes: dark-mode text contrast on the exam editor and site-wide; "update assignment" returning to the same page

**Constraint change (v3):** v2.0 forbade new Composer packages for domain logic, which is why AVL-05's `beforeunload` could not be automated. v3 **explicitly adopts Laravel Dusk** for browser testing — this reverses that constraint for the testing layer only, and makes v2.0's deferred browser UAT items automatable.

**Autonomous decisions taken while the user was away** (flagged for override):
- *Navbar:* v3.md says "navbar might be unnecessary". Kept as a **slim top bar** — it must survive to host the dark-mode toggle and the help button v3.md explicitly asks for beside it; navigation itself moves into the in-page hierarchy.
- *Version:* v3.0 (major) — this is a workflow restructure, not an increment.

## Requirements

### Validated

<!-- Shipped and confirmed valuable — provided by the existing Laravel 11 + Breeze scaffold. -->

- ✓ Secure authentication: register, login, logout, password reset, email verification — existing (Breeze)
- ✓ Authenticated user profile management — existing (Breeze)
- ✓ Session persistence across browser refresh — existing (Breeze)
- ✓ Two roles (Lecturer/Student) with server-enforced role-gated access; public registration locked to Student — Phase 1
- ✓ Full domain schema (classrooms, subjects, exams, questions, options, attempts, answers + pivots) with single-attempt constraints — Phase 1
- ✓ Lecturers manage classrooms + subjects, link subjects to classrooms, assign students to a classroom — Phase 2
- ✓ Lecturers author exams (scoped to a subject) with MCQ (exactly one correct) + open-text questions, points, and publish/draft state — Phase 2
- ✓ Lecturers assign exams to classrooms; students see only published exams assigned to their class, with IDOR-safe server-side access control (shared ExamPolicy/scope) — Phase 3
- ✓ Students take a time-limited exam: server-authoritative countdown + auto-submit on expiry, per-answer autosave, single attempt (DB-enforced), no answer-key leakage — Phase 4
- ✓ MCQ auto-graded on submission; lecturers grade open-text; results gated until fully graded; students see their own result + breakdown; lecturers see per-exam/per-student results — Phase 5
- ✓ Demo seeder: lecturer, students, classes, subjects, and a sample exam so the app is testable on first clone — Phase 6
- ✓ README documenting setup, DB, seeding, demo credentials, run/test — Phase 6
- ✓ Answered-count in the submit-confirmation modal reflects answers saved during the session (reactive, not a page-load snapshot) — Phase 7 (FIX-01)
- ✓ Subject-scoped class-sections (`year-semester-count`, e.g. `2026-2-1`) with per-section capacity + enrollment open/close window; lecturer section CRUD — Phase 7 (SEC-01, SEC-02)
- ✓ Subject↔lecturer assignment; any assigned lecturer (not only the creator) manages that subject's sections via genuine per-subject ownership authorization — Phase 7 (SEC-03)
- ✓ App-wide Flowbite admin shell (top-navbar, cards, status pills) + light/dark toggle (instant, localStorage-persisted, OS-preference default) — Phase 7 (UI-01, UI-02)
- ✓ Enrollment-driven exam visibility: one `Exam::scopeVisibleTo()` predicate drives both the student list and the takeable gate; in-place schema break (sections/subject_user/enrollments added; `classroom_subject` + `users.classroom_id` dropped) with rewritten demo seeder, `migrate:fresh --seed` clean — Phase 7 (ENR-08, DEL-03)

- ✓ Students choose a subject, see sections with live capacity, self-enroll (immediate, no approval) after the open date, and withdraw before the close date; concurrent applies never exceed capacity — Phase 8 (ENR-01..ENR-06)
- ✓ Any lecturer assigned to a subject can reject an enrolled student with a reason from a fixed dropdown; the rejected student sees the reason — Phase 8 (ENR-07)
- ✓ Exams carry an optional availability window (set while draft) with a pre-start details page and a server-side, window-gated start; a started attempt runs to completion on its own timer — Phase 8 (AVL-01..AVL-04)
- ✓ In-progress attempt warns before tab/window close (silent on intentional submit + auto-submit) — Phase 8 (AVL-05) *(live browser check pending — see STATE.md Deferred Items)*
- ✓ Non-technical student and lecturer user manuals, shipped as in-app help pages — Phase 8 (DEL-04, DEL-05)

### Active

<!-- v2.0 shipped. Next milestone's scope is defined by /gsd-new-milestone. -->
- *(none — v2.0 complete; next milestone scope to be defined)*

### Out of Scope

<!-- Explicit boundaries with reasoning. -->

- Public self-signup for lecturers — lecturers are seeded/admin-provisioned; open registration to a privileged role is a security risk for an exam system
- Real-time proctoring / webcam monitoring — high complexity, out of scope for the assessment
- Question bank import/export, rich media in questions — not core to the exam-delivery value
- Live collaborative or timed-multiplayer features — single-taker exams only
- SPA / separate API frontend — Breeze Blade stack is sufficient and already scaffolded

## Context

- **Existing scaffold**: Laravel 11.31 (PHP 8.2+) with Breeze 2.4 already installed — auth routes (`routes/auth.php`), auth/dashboard/profile Blade views, and the `User` model exist. Frontend is the Breeze Blade stack: Tailwind 3 + Alpine.js + Vite.
- **Database**: MySQL — `.env` sets `DB_CONNECTION=mysql`, database `yp-student-exam` on `127.0.0.1:3306` (Laravel Herd provides MySQL). This overrides the SQLite default in `config/database.php`, so the effective database is MySQL.
- **Only default migrations exist** (users, cache, jobs). All domain tables (roles, classes, subjects, exams, questions, options, assignments, attempts, answers) are net-new.
- **Deliverable**: public GitHub repo shared with YP, including a README covering setup, seeded demo credentials, and how to run/test.
- **Dev tooling present**: PHPUnit, Pint, Pail, Telescope, Boost (available but not required by scope).

## Constraints

- **Tech stack**: Laravel 11 + Breeze — mandated by the brief. Build on the existing scaffold; do not replace it.
- **Database**: MySQL (`yp-student-exam` via Herd) — configured in `.env`; the README must document creating the database and running migrations/seeders.
- **Frontend**: Breeze Blade + Tailwind + Alpine — already scaffolded; no SPA.
- **Delivery**: All code pushed to a public GitHub repository with a README — this is a graded deliverable, so setup must be reproducible from a clean clone.
- **Scope discipline**: minimal, correct implementation over feature breadth — favor the simplest thing that satisfies each requirement.

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Build roles on top of Breeze auth (add a `role` column + middleware/Gates) rather than a permissions package | Two fixed roles don't justify a package (spatie/permission); a column + Gate is minimal and clear | — Pending |
| MySQL as the database (`yp-student-exam`) | Configured in `.env` and provided by Herd; production-grade and matches the running environment. README documents DB creation + migrate + seed | — Pending |
| Keep the Breeze Blade stack (Tailwind + Alpine), no SPA | Already scaffolded; brief mandates Breeze; SPA adds no value here | — Pending |
| MCQ auto-graded, open-text lecturer-graded | The system knows correct MCQ answers; free-text needs human judgment | — Pending |
| Lecturers are seeded, not self-registered | A self-signup path to a privileged grading role is an integrity/security risk | — Pending |
| Enforce exam time limit server-side with client countdown + auto-submit | Client timer alone is trivially bypassed; server must be the source of truth | — Pending |
| v2.0: UI theme = custom Tailwind app shell + Flowbite (free/MIT) + dark-mode toggle, not a Bootstrap admin template | Breeze stack is Tailwind + Alpine; Bootstrap/jQuery themes clash. Flowbite is Tailwind-native, one small dependency, ships dark-mode variants | — Pending |
| v2.0: edit original v1 migrations in place (clean break, migrate:fresh --seed) rather than add alter migrations | Pre-release seeded assessment build with no production data; a clean final schema reads better for a graded clean-clone deliverable | — Pending |
| v2.0: enrollment is immediate (no approval); one active section per subject per semester; withdrawn AND rejected students may re-apply while the window is open | User-chosen minimal enrollment model; rejection is a corrective action with a visible reason, not a permanent ban | — Pending |
| v2.0: UI theme = custom Tailwind app shell + Flowbite (free/MIT), not a Bootstrap admin template | Breeze stack is Tailwind + Alpine; Bootstrap/jQuery themes clash. Flowbite is Tailwind-native, one small dependency, prebuilt components | — Pending |
| v2.0: edit original v1 migrations in place (clean break, migrate:fresh --seed) rather than add alter migrations | Pre-release seeded assessment build with no production data; a clean final schema reads better for a graded clean-clone deliverable | — Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
## Current State (after v3.0)

**Shipped:** v3.0 — Workflow Restructure & UX Polish (2026-07-19). 60/60 requirements
satisfied; milestone audit `passed_with_tech_debt`, all cross-phase flows wired.

- **Suite:** 460 PHPUnit tests passing, 0 failures. `php artisan migrate:fresh --seed` boots a
  clean clone end-to-end. Laravel Dusk drives the primary student + lecturer flows against Herd
  on a separate `yp-student-exam-dusk` database (`.env.dusk.local` + `DatabaseTruncation`).
- **Stack:** Laravel 11 + Breeze, PHP 8.2, MySQL (`yp-student-exam`), Blade + Tailwind 3 +
  Flowbite (token values ported into the Tailwind config, no v4 upgrade) + Alpine + Vite.
  Laravel Dusk added as the only new (dev-only) dependency, for browser testing.
- **v3.0 size:** 187 commits, 459 files changed (+33,242 / −2,387), 6 phases (9–14), 34 plans.
- **Model:** app restructured around one navigation hierarchy anchored to a **derived** semester
  (`App\Support\Semester`, no stored table). Exam visibility is derived from subject enrollment
  (the `exam_section` pivot was dropped) — auto-assignment with the cross-subject leak closed
  structurally. `Section` stays the model name; "Class" is UI copy only.
- **Known deferred:** 3 human/machine verification items — dark-mode visual walkthrough,
  `php artisan dusk` run on a Chrome host (build machine is Edge-only), and the native
  `beforeunload` check (un-automatable, Decision #6) — plus carried-over Phase 08/06 UAT. No
  code defects outstanding. See STATE.md → Deferred Items.

**Notable defects caught and fixed before close:** a CRITICAL data-loss bug in the navigation
restructure (P11); a CRITICAL question/option reorder bug (P12); a restored unreachable-result
guard (P13, the recurring v2.0 lesson); the third unguarded `attempts` locked-read on the
grading path (T-09-01 now holds repo-wide); and four UI-review defects at close (x-cloak flash,
a zero-`dark:` page, a dropdown missing its dark arm, an off-brand indigo accent).

## Next Milestone Goals

v3.0 is shipped. Before the next milestone: the user runs the three deferred manual/browser
checks and pushes to the public GitHub repo (the graded deliverable). Then define the next
milestone's scope with `/gsd-new-milestone` — no scope is committed yet. Candidate carry-overs
from the v3.0 backlog: drag-and-drop reordering, an aggregate lecturer Results landing route,
and per-student reset granularity if per-exam proves insufficient. Tech debt to retire or
formalize: the orphaned `student.subjects.show` surface.

---
*Last updated: 2026-07-19 after the v3.0 milestone.*

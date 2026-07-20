# Project Research Summary

**Project:** Online Examination Portal — v3.0 Workflow Restructure & UX Polish
**Domain:** Subsequent-milestone integration work on a shipped Laravel 11 + Breeze exam portal (294 passing tests, `migrate:fresh --seed` clean)
**Researched:** 2026-07-17
**Confidence:** HIGH

## Executive Summary

v3.0 is not a greenfield build — it is a restructuring of navigation, UI, and a handful of workflow rules on top of an exam portal that already has real invariants (a server-authoritative timer, a single-attempt-per-exam constraint, a cascade-delete FK from answers to attempts, a draft-only edit gate) deliberately built to prevent bugs v2.0 already found and fixed once. The milestone introduces exactly one new Composer package (`laravel/dusk`, testing only) and zero mandatory new npm packages — every UI ask is achievable with the already-installed Flowbite 4.0.2 + Alpine 3.15.12 + Tailwind 3 stack, provided one wiring gap is closed first: Flowbite 4's semantic design tokens (`bg-brand`, `text-heading`, `rounded-base`, etc.) are Tailwind-v4-only and compile to nothing under this project's Tailwind v3 setup — verified directly against `node_modules/flowbite/src/themes/default.css`. The fix is to port the token values into `tailwind.config.js` as v3 `theme.extend` entries, once, early.

The single highest-risk decision is how "auto-assign all enrolled students to all active exams" gets implemented. v2.0 shipped a CRITICAL fix (Phase 7) to stop an exam from being assignable to a section outside its own subject. Architecture's recommended path — drop the `exam_section` pivot entirely and rewrite `Exam::scopeVisibleTo()` to derive visibility from subject-level enrollment — makes that leak structurally impossible. Pitfalls converged on the same conclusion from the opposite direction: if the pivot is kept and auto-populated instead, every write must be scoped by `exam.subject_id === section.subject_id` or the leak reopens. This is one decision with two safe resolutions and one unsafe one, and must be settled before any exams-tab UI is built.

Three other findings cut across every phase: (1) `beforeunload` cannot be automated by Dusk even after adoption — stays a manual checklist item; (2) Dusk must run against a dedicated database, never the documented `yp-student-exam` dev database; (3) attempt-cancellation/reset is the first code path that can delete an in-progress attempt while a student's timer is live — `Attempt::lockAndFinalize()` needs a null-guard before either destructive feature ships.

## Key Findings

### Recommended Stack

No new runtime dependencies beyond `laravel/dusk ^8.6` (dev-only). Toasts, vertical stepper, arrangeable options — all buildable with existing Flowbite 4.0.2 + Alpine 3.15.12 + Tailwind 3. Flowbite 4's semantic CSS tokens require Tailwind v4's `@theme` and do not exist as utilities under this project's Tailwind v3 build. `beforeunload` remains permanently non-automatable via Dusk (ChromeDriver 126+ auto-dismisses it).

**Core technologies:**
- `laravel/dusk ^8.6` — browser/E2E testing; use `DatabaseTruncation` against a dedicated `.env.dusk.local` database.
- Flowbite 4.0.2 (existing) + Tailwind v3 `theme.extend` port of token values.
- Alpine 3.15.12 (existing) — toasts, stepper state, tab switching, countdown escalation.

### Expected Features

**Must have (P1):** semester-aware dashboard, navigation restructure, subject list restructure, single-page class enrollment, class management two-tab shell + reset-submission, exam editor two-tab merge (arrangeable/shuffle options, question reorder, invalidate-on-save), grading page restructure, student class page (status pills), take-exam restructure (sticky bar, vertical stepper, 10-min toaster, instructions popup, fixed order), landing page, login restyle, dark-mode/redirect bugfixes.

**Should have (P2):** wiki-style user manual (re-chunk v2.0 content), expanded seed data, Laravel Dusk suite.

**Defer:** exam versioning, enrollment credit limits, per-question timers, per-student runtime shuffling, full-text search for manual, historical trend charts/live dashboards.

Foundational infrastructure blocking multiple features: the semester date-range helper and the shared toast/modal system.

### Architecture Approach

v3 needs no new services beyond `App\Support\Semester` and a shared attempt-voiding primitive — everything else is UI reorganization of already-shipped controllers/routes or a targeted rewrite of `Exam::scopeVisibleTo()`. `Section` should NOT be renamed again — "class" is a UI-copy relabel only.

**Major components:**
1. `App\Support\Semester` (new) — value object wrapping `Section.year`/`Section.semester`; `ordinal()` for total order across year boundary; no new table.
2. `Exam::scopeVisibleTo()` (rewritten) — single predicate for exam visibility; subject-level enrollment instead of the `exam_section` pivot.
3. Shared toast/modal component (new) — listens to server-flash and client-dispatched Alpine events; separate from confirmation modals.
4. Attempt-voiding primitive (new, shared service) — called from exam-editor save and class-management reset; must lock like `Attempt::lockAndFinalize()`.
5. Route/controller restructure (mostly reuse) — `lecturer.subjects.show` hub, per-subject scoping, new student "subject list" and "Class" pages.

### Critical Pitfalls

1. Auto-assignment reopening the cross-subject leak v2.0's Phase 7 fixed — scope every write by `exam.subject_id === section.subject_id`; ship a negative regression test.
2. "Save cancels attempts"/"reset submission" collide with the draft-only edit gate, the cascade-delete FK, and the unique attempt constraint — build one shared, lock-guarded service; decide hard-delete vs. soft-cancel explicitly.
3. `Attempt::lockAndFinalize()` has no null-guard — add it before either destructive feature ships.
4. Flowbite 4 tokens silently emit no CSS under Tailwind v3 — verify via compiled CSS grep, not eyeballing.
5. Semester date math at the boundaries (year-rollover, leap year, August gap) needs one canonical, tested helper.
6. Dusk on Windows/Herd must use a separate database or it wipes the demo seed.
7. Navigation restructure must not orphan currently-reachable routes (results, help/manual, sections CRUD).

## Implications for Roadmap

### Phase 1: Foundations — Semester helper, attempt-lock safety, toast/modal system
**Rationale:** Nearly every later phase depends on one of these three primitives.
**Delivers:** `App\Support\Semester` (with boundary/leap-year/August-gap tests), null-guard fix to `Attempt::lockAndFinalize()` + `AttemptController::answer()`, shared toast/modal component wired to `session('status')`/`session('error')`.
**Addresses:** Semester model, toast/alert unification, timer-race defensive fix.
**Avoids:** Pitfalls 3, 1 (race sub-issue), 8.

### Phase 2: Exam integrity model — attempt cancellation/reset, draft↔active behavior
**Rationale:** Must land before exam-editor/class-management UI; schema/invariant-level work; Phase 1's lock-guard is a hard prerequisite.
**Delivers:** Removal of draft-only edit gates and the `unpublish()` attempts-exist guard; one shared, lock-guarded attempt-voiding service for both "save cancels attempts" and "reset submission"; explicit hard-delete-vs-soft-cancel decision; differentiated warning copy.
**Addresses:** Exam Editor invalidate-on-save, Class Management reset-submission.
**Avoids:** Pitfall 1.

### Phase 3: Exam visibility model — drop `exam_section`, subject-scoped auto-assignment
**Rationale:** High-risk, high-churn; must be isolated and fully re-tested before UI builds on it. Strongest cross-researcher convergence.
**Delivers:** Rewritten `scopeVisibleTo()`; removal of `ExamAssignmentController`/`AssignExamRequest`/pivot; rewritten `Student\ExamController::show()`; full rewrite of visibility-dependent tests incl. cross-subject negative test.
**Addresses:** "All enrolled students automatically assigned to all active exams."
**Avoids:** Pitfall 2.

### Phase 4: Navigation & route restructure
**Rationale:** Depends on Phases 2 and 3 being stable.
**Delivers:** `lecturer.subjects.show` hub, per-subject scoping of section/exam listings, new student "subject list" and "Class" pages, slimmed navbar, in-page back-buttons with explicit destination text.
**Uses:** Phase 1 Semester helper, Phase 3 visibility predicate.
**Acceptance gate:** every old-navbar-reachable route verified reachable in the new hierarchy.

### Phase 5: Dashboard
**Rationale:** Parallel to Phase 4; depends only on Phase 1.
**Delivers:** Welcome banner, lecturer cards (classes assigned this+future semester, enrolled-vs-seats progress, pending-grading count, draft-exam count), student cards (subjects enrolled, open exams, results) — bounded aggregate queries only.

### Phase 6: UI system — Flowbite token layer, login/landing page
**Rationale:** Independent of functional restructure; can run in parallel with Phases 2-5 but should land early since later phases reuse its token vocabulary.
**Delivers:** Ported Flowbite token values (or explicit gray-scale-translation decision), restyled login page, new landing page.
**Avoids:** Pitfalls 4/6.

### Phase 7: Content pages — exam editor tabs, grading, take-exam, class enrollment, class page, wiki manual
**Rationale:** Mostly independent of each other; each depends on its backend phase (2, 3) and Phase 1's toast/modal system.
**Delivers:** Exam editor two-tab merge + reorder/shuffle; grading page enrichment; take-exam overhaul (sticky bar, stepper, 10-min toaster, instructions popup, fixed order); class enrollment relabeling; student class page status pills; wiki-style manual re-chunk.

### Phase 8: Polish and testing — dark mode, Dusk, seed data
**Rationale:** Genuinely last — needs a stable view surface and finalized status/model decisions.
**Delivers:** Dark-mode contrast sweep (component-first); Dusk scaffold (`.env.dusk.local`, dedicated DB, README) + per-page browser suite (excluding `beforeunload`); expanded seeder (unique names, Dr/PhD gating, past-semester graded data via Phase 1 helper, every status represented).
**Avoids:** Pitfalls 5, 7, 9.

### Phase Ordering Rationale

- Foundational primitives first — avoids drift from independent reimplementation.
- Integrity-model phases (2, 3) before any UI surfacing them — both are schema/invariant-level changes wearing UX clothing.
- Navigation restructure follows both integrity phases since it links directly into their controllers.
- Dashboard and UI-system are parallelizable with the functional restructure.
- Content pages trail their backend phase but are independent of each other — good wave-parallelization candidates.
- Polish/testing last by construction.

### Research Flags

Needs deeper research during planning:
- **Phase 2:** hard-delete-vs-soft-cancel decision and unique-index implications — confirm as a requirement, don't assume.
- **Phase 3:** exact scope of "auto-assign" — confirm with user per v3.md's own invitation.
- **Phase 8:** Windows/Herd-specific ChromeDriver/`APP_URL` behavior is community-sourced (LOW-MEDIUM) — verify hands-on.

Standard patterns (skip research-phase):
- **Phase 1:** direct extensions of existing codebase conventions.
- **Phase 4:** route/controller reorganization over already-shipped logic.
- **Phase 5:** conventional bounded-aggregate query patterns, already precedented.
- **Phase 6:** the hard technical question is already resolved with HIGH confidence in STACK.md.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Flowbite/Tailwind conflict and Dusk compatibility verified directly against installed source/Packagist; Windows/Herd specifics LOW-MEDIUM |
| Features | HIGH | Semester modeling grounded in existing schema; some "other relevant stats" choices are MEDIUM judgment calls |
| Architecture | HIGH | Verified against real routes/models/controllers; one external claim cross-checked against official docs |
| Pitfalls | HIGH | Grounded in this codebase's models/policies/migrations/seeder and its own RETROSPECTIVE.md |

**Overall confidence:** HIGH

### Gaps to Address

- August semester gap policy — recommend roll-forward-to-next-semester, confirm with user before Phase 1 locks it in.
- Auto-assignment exact scope — confirm before Phase 3.
- "Cancelled" attempt semantics (hard-delete vs. soft status) — confirm before Phase 2.
- "Reset exam submission" granularity (exam-wide vs. per-student) — confirm before Phase 2/Class-Management.
- Flowbite option A/B decision (token porting vs. gray-scale translation) — surface explicitly in Phase 6.
- Windows/Herd Dusk behavior — verify hands-on during Phase 8.

## Sources

### Primary (HIGH confidence)
- Direct codebase reads: models, policies, controllers, Form Requests, `AttemptGrader`, migrations, seeder, navigation.blade.php, tailwind.config.js, composer.json, phpunit.xml.
- `node_modules/flowbite/src/themes/default.css`, `node_modules/flowbite/package.json`.
- https://laravel.com/docs/11.x/dusk
- `.planning/RETROSPECTIVE.md`, `.planning/v3.md`, `.planning/PROJECT.md`.

### Secondary (MEDIUM confidence)
- Packagist/npm registry version checks.
- Moodle forum/docs on post-attempt quiz editing.
- LMS instructor-dashboard survey.
- Wiki.js/Wikipedia navigation-template docs.

### Tertiary (LOW confidence)
- Windows/Herd-specific Dusk driver-drift and `APP_URL` behavior.
- Flowbite Stepper/Toast component documentation (fetched, not hands-on reproduced).

---
*Research completed: 2026-07-17*
*Ready for roadmap: yes*

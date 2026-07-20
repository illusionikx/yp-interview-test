# Roadmap: Online Examination Portal

## Milestones

- ✅ **v2.0 — Enrollment, Exam Availability & Fixes** — Phases 1–8 (shipped 2026-07-17) — [archive](milestones/v2.0-ROADMAP.md)
- ✅ **v3.0 — Workflow Restructure & UX Polish** — Phases 9–14 (shipped 2026-07-19) — [archive](milestones/v3.0-ROADMAP.md)

## Phases

<details>
<summary>✅ v2.0 — Enrollment, Exam Availability & Fixes (Phases 1–8) — SHIPPED 2026-07-17</summary>

**Core build (Phases 1–6)** — the exam portal itself:

- [x] Phase 1: Foundation — Domain Schema & Role-Based Access Control (4/4 plans)
- [x] Phase 2: Classroom, Subject & Exam Authoring (6/6 plans)
- [x] Phase 3: Exam Assignment & Class-Scoped Access (3/3 plans)
- [x] Phase 4: Attempt-Taking (4/4 plans)
- [x] Phase 5: Grading & Results (4/4 plans)
- [x] Phase 6: Demo Seeder & Delivery (4/4 plans)

**v2.0 scope (Phases 7–8)** — theme, schema break, enrollment, availability, manuals:

- [x] Phase 7: v2.0 Foundation — Admin Theme, Schema Break & Answered-Count Fix (8/8 plans) — completed 2026-07-16
- [x] Phase 8: v2.0 Features — Enrollment, Exam Availability & User Manuals (9/9 plans) — completed 2026-07-17

**Milestone result:** 22/22 v2.0 requirements satisfied · 0 blockers · clean cross-phase
integration · 294 tests passing · clean `migrate:fresh --seed`.

Full detail: [v2.0-ROADMAP.md](milestones/v2.0-ROADMAP.md) · [v2.0-MILESTONE-AUDIT.md](milestones/v2.0-MILESTONE-AUDIT.md)

</details>

<details>
<summary>✅ v3.0 — Workflow Restructure & UX Polish (Phases 9–14) — SHIPPED 2026-07-19</summary>

Semester model, exam-integrity auto-assignment, navigation restructure, lecturer
workspace, student take-exam experience, and delivery (dark mode, wiki manual, demo
data, Dusk):

- [x] Phase 9: v3.0 Foundations — Semester Model, Design Tokens, Alerts & Entry Pages (10/10 plans) — completed 2026-07-17
- [x] Phase 10: Exam Integrity — Auto-Assignment & Attempt Lifecycle (9/9 plans) — completed 2026-07-18
- [x] Phase 11: Navigation Restructure — Landing Hierarchy, Dashboard, Subjects & Enrollment (4/4 plans) — completed 2026-07-18
- [x] Phase 12: Lecturer Workspace — Class Management, Exam Editor & Grading (5/5 plans) — completed 2026-07-18
- [x] Phase 13: Student Exam Experience — Class Page & Take Exam (2/2 plans) — completed 2026-07-18
- [x] Phase 14: Delivery — Dark Mode, Wiki Manual, Demo Data & Browser Tests (4/4 plans) — completed 2026-07-18

**Milestone result:** 60/60 v3.0 requirements satisfied · audit `passed_with_tech_debt` ·
460 PHPUnit tests passing · clean `migrate:fresh --seed` · Dusk flows wired + DB-verified
(run needs a Chrome host). UI reviewed (phases 9/11/12/13/14) with 4 defects fixed at close.

Full detail: [v3.0-ROADMAP.md](milestones/v3.0-ROADMAP.md) · [v3.0-MILESTONE-AUDIT.md](v3.0-MILESTONE-AUDIT.md)

</details>

## Next

v3.0 shipped. Remaining before the public GitHub push (all human/machine, no code defects):
the dark-mode visual walkthrough, the `php artisan dusk` run on a Chrome host, and the native
`beforeunload` check — see STATE.md → Deferred Items. Start the next milestone with
`/gsd-new-milestone`.

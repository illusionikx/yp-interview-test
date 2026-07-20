---
phase: 7
slug: v2-0-foundation-admin-theme-schema-break-answered-count-fix
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-07-16
---

# Phase 7 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Derived from 07-RESEARCH.md ## Validation Architecture.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.0.1 — Laravel `Tests\TestCase` + `RefreshDatabase` |
| **Config file** | `phpunit.xml` (project root) |
| **Quick run command** | `php artisan test --filter=<TestClass>` |
| **Full suite command** | `php artisan test` (runs `tests/Unit` + `tests/Feature` against MySQL `yp-student-exam` via `RefreshDatabase`) |
| **Estimated runtime** | ~30–90 seconds (full suite) |

---

## Sampling Rate

- **After every task commit:** targeted `php artisan test --filter=<TouchedTestClass>`
- **After every plan wave:** `php artisan test` (full suite) — critical for the schema-break wave: a green full suite is the actual proof all 26 rename-swept files were updated correctly
- **Before `/gsd-verify-work`:** Full suite green + a manual `php artisan migrate:fresh --seed` from a clean state
- **Max feedback latency:** ~90 seconds

---

## Per-Requirement Verification Map

> Task IDs assigned by the planner; this map is requirement-level (created pre-plan).

| Requirement | Behavior | Test Type | Automated Command | File Exists |
|-------------|----------|-----------|-------------------|-------------|
| UI-01 | Navbar/dropdowns render on lecturer + student pages; status-pill component maps status→classes | feature (assertSee/assertOk) | `php artisan test --filter=NavigationTest` | ❌ W0 |
| UI-02 | Dark-mode toggle persists via localStorage; `darkMode:'class'` applied | manual/browser (client-only JS API) | manual UAT + visual check | N/A manual-only |
| FIX-01 | Confirm-modal answered-count updates after autosave, before reload | feature (initial value) + manual (reactive update) | `php artisan test --filter=AttemptShowTest` + manual UAT | ⚠️ Partial W0 |
| SEC-01 | `sections` table has `subject_id`,`year`,`semester`,`sequence`; computed `name` = `year-semester-sequence` | feature (schema + model) | `php artisan test --filter=SectionModelTest` | ❌ W0 |
| SEC-02 | Lecturer can create/edit/delete a section with capacity + enrollment window | feature (CRUD) | `php artisan test --filter=SectionControllerTest` | ❌ W0 |
| SEC-03 | Subject assignable to multiple lecturers; any assigned lecturer can manage its sections | feature (authorization) | `php artisan test --filter=SubjectLecturerTest` | ❌ W0 |
| ENR-08 | List (`Student\ExamController@index`) and gate (`ExamPolicy::takeable`) agree across enrolled/withdrawn/rejected/never-applied | feature (cross-consumer regression) | `php artisan test --filter=ExamVisibilityRegressionTest` | ❌ W0 — **HARD GATE** |
| DEL-03 | `migrate:fresh --seed` succeeds; working demo for every seeded role | feature/smoke | `php artisan migrate:fresh --seed && php artisan test --filter=DatabaseSeederTest` | ⚠️ rewrite existing |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Lecturer/SectionControllerTest.php` — SEC-01/SEC-02 (mirrors `ClassroomControllerTest`)
- [ ] `tests/Feature/Lecturer/SubjectLecturerTest.php` — SEC-03 (per-subject lecturer ownership)
- [ ] `tests/Feature/Student/ExamVisibilityRegressionTest.php` — ENR-08 (**hard gate** — list/gate agreement across 4 enrollment states)
- [ ] `tests/Feature/DatabaseSeederTest.php` — rewrite (existing assertions reference dropped `classroom_id`/`classroom_subject`)
- [ ] `tests/Feature/DomainSchemaTest.php` — rewrite table list (`sections`, `subject_user`, `enrollments`, `exam_section`) + `enrollments` unique(section_id,user_id) assertion
- [ ] Rename-sweep: every existing test referencing `classroom` updated (26-file sweep from RESEARCH Runtime State Inventory) — modification gaps, swept before the full suite can pass

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Dark-mode toggle instant switch + localStorage persistence + OS default | UI-02 | `localStorage` + `prefers-color-scheme` are client-only browser APIs, no server round-trip to assert | Toggle in top bar → whole app switches; reload → choice persists; clear localStorage → follows OS preference |
| Answered-count reactive update after autosave (without reload) | FIX-01 | Alpine reactivity is JS-only; feature test covers only the initial server-rendered value | Answer a question → wait for autosave → open confirm modal → count reflects the just-saved answer |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references (incl. 26-file rename sweep)
- [ ] No watch-mode flags
- [ ] Feedback latency < 90s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending

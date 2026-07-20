---
phase: 2
slug: classroom-subject-exam-authoring
status: approved
nyquist_compliant: true
wave_0_complete: false
created: 2026-07-15
---

# Phase 2 — Validation Strategy

> Per-phase validation contract. Derived from `02-RESEARCH.md` §Validation Architecture.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.0.1 |
| **Config file** | `phpunit.xml` (DB overrides commented out → runs against live MySQL `yp-student-exam` with `RefreshDatabase`) |
| **Quick run** | `php artisan test --filter=<TestClassName>` |
| **Full suite** | `php artisan test` |
| **Runtime** | ~15–40s (grows with this phase) |

> ⚠ `php artisan test` truncates/reseeds the live dev DB (same as Phase 1). No isolation from dev data.

---

## Sampling Rate

- **After each task commit:** `php artisan test --filter=<TestClassName>` for the controller/Form Request just touched
- **After each wave:** `php artisan test` (full suite)
- **Phase gate:** full suite green + a manual `migrate:fresh --seed` sanity check

---

## Per-Task Verification Map

| Plan | Wave | Requirement | Behavior | Test | File |
|------|------|-------------|----------|------|------|
| TBD | 0 | — | Test factories exist | (infra) | SubjectFactory, ExamFactory, QuestionFactory, OptionFactory |
| TBD | — | CLS-01 | Lecturer CRUD a classroom | feature | `ClassroomControllerTest` ❌ W0 |
| TBD | — | CLS-02 | Lecturer CRUD a subject | feature | `SubjectControllerTest` ❌ W0 |
| TBD | — | CLS-03 | Link/unlink subjects on a classroom (`classroom_subject` sync) | feature | `ClassroomSubjectLinkageTest` ❌ W0 |
| TBD | — | CLS-04 | Assign/unassign a student (direct `classroom_id` FK update) | feature | `ClassroomRosterTest` ❌ W0 |
| TBD | — | EXM-01 | Create exam (subject/title/duration_minutes/created_by) | feature | `ExamControllerTest` ❌ W0 |
| TBD | — | EXM-02 | MCQ ≥2 options, exactly one correct — happy + reject zero/multiple/blank | feature | `ExamQuestionMcqTest` ❌ W0 |
| TBD | — | EXM-03 | Open-text question, no options | feature | `ExamQuestionOpenTest` ❌ W0 |
| TBD | — | EXM-04 | points default 1; custom persists; `points<1` rejected | feature | `ExamQuestionMcqTest` (shared) ❌ W0 |
| TBD | — | EXM-05 | Edit/delete blocked once `is_published=true`; allowed while draft | feature | `ExamPublishedEditGateTest` ❌ W0 |
| TBD | — | EXM-06 | Publish flips `is_published`; unpublish reverses (D-06) | feature | `ExamPublishTest` ❌ W0 |

*Status flips to green as each plan's TDD tasks land. Plan/Wave IDs filled by the planner.*

---

## Wave 0 Requirements

- [ ] `database/factories/SubjectFactory.php`
- [ ] `database/factories/ExamFactory.php` (`subject_id` + `created_by`; `->published()` state)
- [ ] `database/factories/QuestionFactory.php` (`mcq()` / `open()` states; `mcq()` attaches options with exactly one `is_correct`)
- [ ] `database/factories/OptionFactory.php`
- [ ] `tests/Feature/Lecturer/` directory (mirrors `Controllers/Lecturer`)
- [ ] Optional: `lecturer()`/`student()` states on `UserFactory` (convenience)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Instructions |
|----------|-------------|------------|--------------|
| Dynamic MCQ option-row UX (Alpine add/remove + single correct radio) | EXM-02 | Client interaction | Open the exam authoring page, add/remove option rows, select correct, submit — confirm persistence |

---

## Validation Sign-Off

- [x] Every requirement maps to an automated feature test
- [x] Wave 0 factories/infra identified
- [x] No watch-mode flags
- [x] `nyquist_compliant: true`

**Approval:** approved 2026-07-15 (test map covers all 10 requirements; Wave 0 infra scheduled)

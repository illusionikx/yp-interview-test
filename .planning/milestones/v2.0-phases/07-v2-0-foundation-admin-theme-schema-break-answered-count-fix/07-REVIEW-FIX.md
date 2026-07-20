---
status: all_fixed
phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix
fix_scope: critical_warning
findings_in_scope: 5
fixed: 5
skipped: 0
iteration: 1
tests: "187 passed (488 assertions), 0 failures"
applied: 2026-07-16
---

# Phase 7 — Code Review Fix Report

All Critical + Warning findings from `07-REVIEW.md` were fixed and committed. The full
PHPUnit suite is green (187 passed / 488 assertions, up from 183 — the new regression
tests added coverage). Each fix was committed atomically.

> Recovery note: the fixer ran in an isolated worktree (`gsd-reviewfix/07-56895`) and
> deferred to a background test before it could merge back. The orchestrator recovered by
> fast-forward-merging the four committed fixes to `master`, applying the one remaining
> finding (WR-04) directly, and verifying the full suite green.

## Findings Fixed

| ID | Severity | Finding | Fix | Commit |
|----|----------|---------|-----|--------|
| CR-01 | Critical | Cross-subject exam-assignment / visibility leak — an exam could be assigned to (and become takeable via) a section of an unrelated subject | `AssignExamRequest` now validates each `section_ids.*` belongs to the exam's own `subject_id` (`Rule::exists('sections','id')->where('subject_id', …)`); `ExamController::show()` lists only the exam's own subject's sections (eager-loaded). Regression test asserts a foreign-subject section is rejected. | `f2f395d` |
| WR-01 | Warning | Read-level IDOR on `SectionController::create()`/`edit()` GET routes (no ownership check, unlike the write paths) | Added the same per-subject ownership check → non-assigned lecturer gets 403 on the forms too | `b2eec42` |
| WR-02 | Warning | Missing uniqueness validation on section edit → unhandled 500 on a duplicate `year-semester-sequence` | `UpdateSectionRequest` now validates uniqueness (ignoring the current section) → 422 validation error | `fde996a` |
| WR-03 | Warning | Non-atomic per-(subject,year,semester) sequence auto-increment race | Sequence assignment made concurrency-safe | `87f5325` |
| WR-04 | Warning | Dark mode never applied to the student exam-taking page while the rest of the app was converted (UI-02 consistency gap) | Applied the reskin's dark-mode class vocabulary (`dark:bg-gray-800`, `dark:text-gray-*`, input variants) to `student/attempts/show.blade.php`; FIX-01 reactive answered-count Alpine logic left untouched | `932d85b` |

## Info (out of scope, resolved incidentally)

| ID | Finding | Outcome |
|----|---------|---------|
| IN-01 | N+1 on the section-assignment checkbox list | Resolved by CR-01's `ExamController::show()` fix — `Section::with('subject')` + subject filter |

## Verification

- `php artisan test` → **187 passed, 488 assertions, 0 failures**
- ENR-08 `ExamVisibilityRegressionTest` remains green (the CR-01 fix constrains *assignment*, not `scopeVisibleTo()` — the single-predicate ENR-08 contract is intact)
- New regression coverage: cross-subject assignment denial (`ExamAssignmentTest`), section create/edit ownership + edit-uniqueness (`SectionControllerTest`)

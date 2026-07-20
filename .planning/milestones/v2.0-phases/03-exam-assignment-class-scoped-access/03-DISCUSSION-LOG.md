# Phase 3: Exam Assignment & Class-Scoped Access - Discussion Log

> **Audit trail only.** Decisions are in CONTEXT.md.

**Date:** 2026-07-15
**Phase:** 3-Exam Assignment & Class-Scoped Access
**Mode:** `--auto` (recommended, research-grounded defaults; no interactive prompts)

| Area | Auto choice | Rationale |
|------|-------------|-----------|
| Assignment UI (ASN-01) | Multi-select classrooms on lecturer exam page → `exam_classroom` sync | Natural place; pivot already exists |
| Student exam list (ASN-02) | published AND assigned to student's classroom | Direct requirement |
| Access policy (RBAC-05) | `ExamPolicy@takeable` (published + assigned + is-student); enforce on show, reuse to scope index | Two-layer authz; IDOR is the #1 pitfall |
| No IDOR (D-04) | Direct URL to unassigned/unpublished exam → 403/404 server-side | The testable heart of RBAC-05 |
| Student area | `Student\ExamController` (index + show) in routes/student.php | Reuse Phase-1 role:student gate |
| Phase boundary | Read-only landing only; "Start" is a Phase-4 seam | Taking/attempts/timer are Phase 4 |

## Deferred Ideas
- Taking exams (attempt/timer/autosave/submit, TAK-01..06) → Phase 4
- AttemptPolicy + result-access gates → Phases 4/5 (same ExamPolicy pattern)
- Grading & results → Phase 5

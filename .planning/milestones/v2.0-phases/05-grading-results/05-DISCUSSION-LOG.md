# Phase 5: Grading & Results - Discussion Log

> **Audit trail only.** Decisions are in CONTEXT.md.

**Date:** 2026-07-16
**Phase:** 5-Grading & Results
**Mode:** `--auto` (recommended, research-grounded defaults; no interactive prompts).

| Area | Auto choice | Rationale |
|------|-------------|-----------|
| MCQ auto-grade (GRD-01) | AttemptGrader service hooked into the Phase-4 finalize chokepoint | "auto-graded on submission"; one hook covers manual + lazy submit |
| Status → graded (GRD-03) | submitted→graded when all open-text graded (or immediately if no open-text) | Withhold result until complete |
| Lecturer grading (GRD-02) | grade open-text 0..points via GradeAnswerRequest; last grade flips → graded | Server-validated score bounds |
| Results (GRD-04/05) | student sees own gated on graded; lecturer sees per-exam/per-student | Own-attempt policy reuse |
| Score calc | attempts.score = Σ answers.score; MCQ=points if correct else 0; unanswered=0 | Existing score columns |
| Post-submit visibility | result shows ✓/✗ + score, not the full MCQ key | Avoid leaking keys for reused exams |
| UI | grading + results views warrant a UI-SPEC (UI hint: yes) | Real lecturer/student surfaces |

## Deferred Ideas
- Demo seeder + README → Phase 6
- Partial-credit MCQ, rubrics, analytics → v2

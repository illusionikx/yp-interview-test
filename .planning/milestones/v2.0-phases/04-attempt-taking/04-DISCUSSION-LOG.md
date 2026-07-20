# Phase 4: Attempt-Taking - Discussion Log

> **Audit trail only.** Decisions are in CONTEXT.md.

**Date:** 2026-07-16
**Phase:** 4-Attempt-Taking
**Mode:** `--auto` (recommended, research-grounded defaults; no interactive prompts). Highest-risk phase — decisions favor server-authoritative correctness.

| Area | Auto choice | Rationale |
|------|-------------|-----------|
| Attempt lifecycle (TAK-01/05) | firstOrCreate on (exam_id,user_id); resume in_progress, block submitted; DB unique backstop | Single-attempt integrity, race-proof |
| Server timer (TAK-02) | deadline = started_at + duration; recheck now≥deadline on EVERY write; remaining_seconds to client display-only | CWE-602 — server is sole authority |
| Auto-submit (TAK-04) | client countdown submits at 0 + server lazy-finalize expired in_progress on touch (no cron) | Backstop without a queue |
| Autosave (TAK-03) | AJAX per answer, updateOrCreate on answers unique; rehydrate on reload | Refresh/disconnect safe |
| No leakage (TAK-06) | render option bodies, never is_correct (explicit whitelist/$hidden) | Answers must not reach the browser |
| Access policy | AttemptPolicy: own attempt AND exam takeable (reuse Phase-3 scope) | RBAC-05 attempt clause; no IDOR |
| Grading seam | submit → status=submitted, ungraded (is_correct/score null) | Grading is Phase 5 |
| UI | take page + Alpine countdown warrants a UI-SPEC (UI hint: yes) | Real interactive surface |

## Deferred Ideas
- Grading (AttemptGrader, MCQ auto-score, manual grading, results) → Phase 5
- Randomized order, multi-select MCQ → v2; scheduled sweep → out of scope

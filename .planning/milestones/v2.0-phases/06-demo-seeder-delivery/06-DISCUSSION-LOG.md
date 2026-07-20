# Phase 6: Demo Seeder & Delivery - Discussion Log

> **Audit trail only.** Decisions are in CONTEXT.md.

**Date:** 2026-07-16
**Phase:** 6-Demo Seeder & Delivery
**Mode:** `--auto` (recommended defaults; no interactive prompts).

| Area | Auto choice | Rationale |
|------|-------------|-----------|
| Seeder scope (DEL-01) | Full idempotent demo: lecturer + students, classrooms, subjects+links, published assigned exam (MCQ+open) | "sample exam with both types assigned to a classroom" |
| Pre-graded demo | One submitted attempt auto-graded via AttemptGrader; keep one student un-attempted | Results/grading visible on first load; reviewer can also take it fresh |
| Demo credentials | Fixed, documented emails + shared password | README lists exact logins |
| README (DEL-02) | Replace Laravel default: overview, stack, MySQL setup, migrate:fresh --seed, run, test, creds, per-role walkthrough | Reproducible from clean clone |
| Clean-clone verify | `migrate:fresh --seed` on empty MySQL is the acceptance gate | DEL-01 |
| GitHub push | USER-gated (rotate leaked token + create repo); documented not automated | Outward-facing; needs user auth + history scrub |

## Deferred Ideas
- Actual `git push` to public GitHub → USER action (token rotation + repo + history scrub)
- v2 features, CI/CD, Docker → out of scope

---
phase: 4
slug: attempt-taking
status: approved
nyquist_compliant: true
wave_0_complete: false
created: 2026-07-16
---

# Phase 4 — Validation Strategy

> Derived from `04-RESEARCH.md` §Validation Architecture. Timer/race/leakage are the critical coverage. Deadline tests use Laravel 11 time-travel (`travelTo`/`travel`/`freezeTime`, auto-reset between tests).

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11, `php artisan test` |
| **Config** | `phpunit.xml` (overrides commented → live MySQL `yp-student-exam`, `RefreshDatabase`) |
| **Quick run** | `php artisan test --filter=Attempt` |
| **Full suite** | `php artisan test` |

## Sampling Rate
- Per task: `php artisan test --filter=Attempt`
- Per wave: `php artisan test` (must stay green vs Phases 1–3's 135 tests)
- Phase gate: full suite green

## Per-Task Verification Map

| Req | Behavior | Test file |
|-----|----------|-----------|
| TAK-01 | Start creates a single `in_progress` attempt | `tests/Feature/Student/AttemptStartTest.php` |
| TAK-01 | Starting again resumes (same `started_at`) | AttemptStartTest |
| TAK-05 | Concurrent double-start (QueryException catch) → no duplicate (`count===1`) | AttemptStartTest |
| TAK-05 | Cannot start a second attempt after `submitted` | AttemptStartTest |
| TAK-02 | Answer before deadline persisted | `tests/Feature/Student/AttemptAnswerTest.php` |
| TAK-02 | Answer after deadline (travelTo) → **422**, not persisted | AttemptAnswerTest (time-travel) |
| TAK-02 | `remaining_seconds` reflects elapsed time (travel) | `tests/Feature/Student/AttemptShowTest.php` (time-travel) |
| TAK-03 | Autosave persists + survives reload | AttemptAnswerTest |
| TAK-03 | Repeated autosave upserts one Answer row (latest wins) | AttemptAnswerTest |
| TAK-04 | GET on expired in_progress finalizes → `submitted`, `submitted_at=deadline` | AttemptShowTest (time-travel) |
| TAK-04 | Expired attempt rejects further answer writes | AttemptAnswerTest (time-travel) |
| **TAK-06** | Take page raw body never contains `is_correct` (`assertDontSee('is_correct')`) | AttemptShowTest |
| **TAK-06** | A student cannot view another student's attempt (IDOR) | `tests/Feature/Student/AttemptPolicyTest.php` |

## Wave 0 Requirements
- [ ] `database/factories/AttemptFactory.php` (+ `AnswerFactory.php` if needed) — no attempt/answer factories exist yet
- [ ] `tests/Feature/Student/AttemptStartTest.php` (TAK-01, TAK-05)
- [ ] `tests/Feature/Student/AttemptShowTest.php` (TAK-02 remaining, TAK-04 finalize, TAK-06 no leak)
- [ ] `tests/Feature/Student/AttemptAnswerTest.php` (TAK-02 gate, TAK-03 autosave, TAK-04 reject)
- [ ] `tests/Feature/Student/AttemptPolicyTest.php` (own-attempt + exam-takeable IDOR)

## Manual-Only Verifications
| Behavior | Req | Why | Instructions |
|----------|-----|-----|--------------|
| Live Alpine countdown ticks + auto-submits at 0 | TAK-02/04 | Client timing | Start an exam, watch the countdown; at 0 the page auto-submits to the confirmation |
| Autosave indicator + refresh mid-exam keeps answers | TAK-03 | Client interaction | Answer, refresh, confirm answers persist |

## Validation Sign-Off
- [x] Every TAK requirement mapped; timer/race/leakage all covered with time-travel where needed
- [x] `is_correct`-leak test asserts against the raw response body
- [x] `nyquist_compliant: true`

**Approval:** approved 2026-07-16 (server-authoritative deadline + single-attempt race + no-leakage are the gates)

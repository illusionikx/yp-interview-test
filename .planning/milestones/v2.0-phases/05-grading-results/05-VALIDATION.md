---
phase: 5
slug: grading-results
status: approved
nyquist_compliant: true
wave_0_complete: false
created: 2026-07-16
---

# Phase 5 — Validation Strategy

> Derived from `05-RESEARCH.md` §Validation Architecture. Grading correctness (edge-case matrix), the submitted→graded gate, and the ownership-only result policy are the critical coverage.

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11, `php artisan test` |
| **Config** | `phpunit.xml` (overrides commented → live MySQL `yp-student-exam`, `RefreshDatabase`) |
| **Quick run** | `php artisan test --filter=<TestClass>` |
| **Full suite** | `php artisan test` |

## Sampling Rate
- Per task: `php artisan test --filter=<TestClass>`
- Per wave: `php artisan test` (must stay green vs Phases 1–4's 155 tests)
- Phase gate: full suite green

## Per-Task Verification Map

| Req | Behavior | Test file |
|-----|----------|-----------|
| GRD-01 | Submit auto-grades MCQ: correct→points, wrong→0, unanswered→0, no crash | `tests/Feature/Grading/AttemptGraderTest.php` |
| GRD-01 | Auto-grade fires on BOTH manual submit AND lazy expiry (single chokepoint) | AttemptGraderTest |
| GRD-03 | All-MCQ exam → `graded` immediately, `attempts.score` set | AttemptGraderTest |
| GRD-03 | Exam with open-text stays `submitted` until lecturer grades the last one | AttemptGraderTest |
| GRD-02 | Lecturer grades open-text with score in `[0,points]` → accepted | `tests/Feature/Lecturer/GradeAnswerTest.php` |
| GRD-02 | Score > points → **422**; negative → **422** | GradeAnswerTest |
| GRD-02 | Non-lecturer forbidden | GradeAnswerTest |
| GRD-02 | Grading an MCQ answer via the endpoint → rejected (open-text only) | GradeAnswerTest |
| GRD-04 | Result withheld ("awaiting grading") while `submitted` | `tests/Feature/Student/ResultTest.php` |
| GRD-04 | Result shows total + per-question breakdown once `graded` | ResultTest |
| GRD-04 | A student cannot view another student's result (IDOR) | ResultTest |
| **GRD-04** | Own graded result STILL visible after the exam is unpublished/reassigned (ownership-only `viewResult`) | ResultTest |
| **GRD-04** | Breakdown never renders the correct option identity for a wrong answer (D-07) | ResultTest |
| GRD-05 | Lecturer results index lists attempts (status + score) per exam | `tests/Feature/Lecturer/ResultTest.php` |
| GRD-05 | Lecturer drills into a student's attempt breakdown | Lecturer/ResultTest |

## Wave 0 Requirements
- [ ] `database/factories/AnswerFactory.php` states (`mcqCorrect`/`mcqIncorrect`/`openText`) or inline fixtures for the grading matrix
- [ ] `tests/Feature/Grading/AttemptGraderTest.php` (GRD-01, GRD-03)
- [ ] `tests/Feature/Lecturer/GradeAnswerTest.php` (GRD-02)
- [ ] `tests/Feature/Student/ResultTest.php` (GRD-04)
- [ ] `tests/Feature/Lecturer/ResultTest.php` (GRD-05)

## Manual-Only Verifications
| Behavior | Req | Why | Instructions |
|----------|-----|-----|--------------|
| Lecturer grading UI (score input, save, completeness) | GRD-02 | Client interaction | Open a submitted attempt with open-text, enter scores, save; confirm the attempt flips to graded |
| Student result page renders breakdown | GRD-04 | Visual | View a graded result; confirm total + per-question breakdown, no answer key |

## Validation Sign-Off
- [x] Every GRD requirement mapped; auto-grade edge cases + ownership-only result + no-key-leakage covered
- [x] `nyquist_compliant: true`

**Approval:** approved 2026-07-16 (grading correctness + result gating + ownership-only policy are the gates)

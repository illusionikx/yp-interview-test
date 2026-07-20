---
phase: 3
slug: exam-assignment-class-scoped-access
status: approved
nyquist_compliant: true
wave_0_complete: false
created: 2026-07-15
---

# Phase 3 — Validation Strategy

> Derived from `03-RESEARCH.md` §Validation Architecture. The RBAC-05 IDOR matrix is the critical coverage.

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.0.1, `php artisan test` |
| **Config** | `phpunit.xml` (overrides commented → live MySQL `yp-student-exam`, `RefreshDatabase`) |
| **Quick run** | `php artisan test --filter=ExamAssignmentTest` / `ExamIndexTest` / `ExamAccessTest` |
| **Full suite** | `php artisan test` |

## Sampling Rate
- Per task commit: relevant filtered test class
- Per wave: `php artisan test` (this phase touches shared `Exam` behavior — Phase 1/2 suites must stay green)
- Phase gate: full suite green

## Per-Task Verification Map

| Req | Behavior | Test file / case |
|-----|----------|------------------|
| ASN-01 | Lecturer syncs classroom_ids → `exam_classroom` matches exactly | `tests/Feature/Lecturer/ExamAssignmentTest.php` |
| ASN-01 | Re-sync detaches removed classrooms | ExamAssignmentTest |
| ASN-01 | Non-existent classroom_id rejected | ExamAssignmentTest |
| ASN-01 | Draft exam assignable before publishing | ExamAssignmentTest |
| ASN-01 | Student forbidden from assignment endpoint | ExamAssignmentTest |
| ASN-02 | Index shows published + assigned-to-my-classroom | `tests/Feature/Student/ExamIndexTest.php` |
| ASN-02 | Index excludes unpublished-but-assigned | ExamIndexTest |
| ASN-02 | Index excludes published-but-other-classroom | ExamIndexTest |
| ASN-02 | Null classroom_id → empty index | ExamIndexTest |
| **RBAC-05** | Assigned+published class → 200 | `tests/Feature/Student/ExamAccessTest.php` |
| **RBAC-05** | Different class → **403** (not omitted) | ExamAccessTest |
| **RBAC-05** | Unpublished-but-assigned → **403** | ExamAccessTest |
| **RBAC-05** | Null classroom → **403** direct access | ExamAccessTest |
| **RBAC-05** | Lecturer on student routes → **403** (role middleware) | ExamAccessTest |

## Wave 0 Requirements
- [ ] `tests/Feature/Lecturer/ExamAssignmentTest.php` (ASN-01)
- [ ] `tests/Feature/Student/ExamIndexTest.php` (ASN-02)
- [ ] `tests/Feature/Student/ExamAccessTest.php` (RBAC-05 IDOR matrix)
- [ ] No new factories — `ExamFactory->published()`, `ClassroomFactory`, `UserFactory::student()/lecturer()` suffice

## Manual-Only Verifications
| Behavior | Req | Why | Instructions |
|----------|-----|-----|--------------|
| "Start" button is a disabled Phase-4 seam (no RouteNotFoundException) | — | Client render | Open a student exam landing; confirm the Start control renders disabled, page doesn't error |

## Validation Sign-Off
- [x] Every requirement mapped; RBAC-05 IDOR matrix fully enumerated (5 cases)
- [x] `nyquist_compliant: true`

**Approval:** approved 2026-07-15 (IDOR matrix is the gate; same-predicate scope shared by index + policy)

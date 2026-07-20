---
phase: 10
slug: exam-integrity-auto-assignment-attempt-lifecycle
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-07-17
---

# Phase 10 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Derived from `10-RESEARCH.md` § Validation Architecture.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit via `php artisan test` (existing `phpunit.xml`, no config changes) |
| **Quick run command** | `php artisan test --filter=<TestClass>` |
| **Full suite command** | `php artisan test` |
| **Baseline** | **340 passing, 0 failing** at Phase 9 close |
| **Estimated runtime** | ~30s full suite |

**Non-PHPUnit gate (inherited from Phase 9):** `bash scripts/ui-03-token-gate.sh` must stay PASS/exit 0.
Any token class used in this phase's markup that is absent from `tailwind.config.js` AND the gate's list
silently emits NO CSS — unstyled markup, no error.

---

## Sampling Rate

- **After every task commit:** the relevant `php artisan test --filter=<TestClass>`.
- **After every plan wave:** `php artisan test` (full suite) + the token gate if any Blade changed.
- **Before `/gsd-verify-work`:** full suite green (340 baseline + this phase's new tests).
- **Max feedback latency:** ~30 seconds.

---

## Per-Task Verification Map

| Req ID | Behavior | Threat Ref | Test Type | Automated Command | File Exists | Status |
|--------|----------|------------|-----------|-------------------|-------------|--------|
| CLS-05 | A student enrolled in ANY section of a subject sees every active exam in that subject — no per-class step | — | feature | `php artisan test --filter=ExamIndexTest` | ✅ rewrite fixtures | ⬜ pending |
| INT-04 | A student in a **different** subject cannot see, access, or start the exam — **list AND direct access AND start** | T-10-01 | feature | `php artisan test --filter=CrossSubjectVisibilityTest` | ❌ W0 | ⬜ pending |
| CLS-06 | Draft↔active toggles **both** directions, including after attempts exist | — | feature | `php artisan test --filter='ExamPublish\|Phase5ReviewFixes'` | ✅ extend | ⬜ pending |
| CLS-07 | Reset deletes all attempts; student can retake | T-10-02 | feature | `php artisan test --filter=ResetSubmissionsTest` | ❌ W0 | ⬜ pending |
| **INT-02** | **The five warning counts are EXACT** — not merely "a modal appears" | T-10-02 | unit/feature | `php artisan test --filter=AttemptVoiderTest` | ❌ W0 | ⬜ pending |
| INT-03 | A student whose attempt was reset can start again | — | feature | `php artisan test --filter=ResetSubmissionsTest` | ❌ W0 | ⬜ pending |
| EDT-04 | Saving an edit to an attempted exam voids attempts, after warning; all **four** gate sites relaxed (D-6) | T-10-02 | feature | `php artisan test --filter=ExamUpdateVoidsAttemptsTest` | ❌ W0 | ⬜ pending |
| FIX-03 | **Satisfied by removal** (D-1 drops the assignment screen) | — | n/a | The deletion of `ExamAssignmentTest` IS the evidence | — | ⬜ pending |
| D-5 | `AnswerGradeController`'s vanished-row guard (the 3rd/last INT-01 site) | T-09-01 | feature | `php artisan test --filter=AttemptNullGuardTest` | ✅ extend | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Student/CrossSubjectVisibilityTest.php` — **INT-04**. Must cover all three surfaces:
      the list, direct access, and start. **Must explicitly assert the two subject IDs differ** — see the
      factory trap below. A positive "exam appears" test does not prove the leak stayed closed.
- [ ] `tests/Feature/AttemptVoiderTest.php` — **INT-02's count-correctness. The single most
      safety-critical test in this phase.** D-2's hard delete has no undo, so the warning is the only
      protection; a modal reporting "0 graded" while 3 are graded is the catastrophic failure. Assert all
      five numbers (`$inProgress`, `$submittedUngraded`, `$graded`, `$notYetGraded`, `$total`) against a
      hand-built fixture with exactly 1 in_progress + 1 submitted-ungraded + 1 graded.
- [ ] `tests/Feature/Lecturer/ResetSubmissionsTest.php` — **CLS-07 + INT-03** (reset, then prove retake).
- [ ] `tests/Feature/Lecturer/ExamUpdateVoidsAttemptsTest.php` — **EDT-04**. Assert attempts are gone,
      and that the warning copy differs at `graded = 0` vs `graded > 0`.
- [ ] Extend `tests/Feature/AttemptNullGuardTest.php` — **D-5**, a third `Gate::after`-seam test targeting
      `AnswerGradeController::update()`.
- [ ] **Rewrite (do NOT delete)** ~20 test files off the `exam_section` shape — see below.

*No framework install needed — PHPUnit is configured and 340 tests already pass.*

---

## ⚠ The Factory Trap — read before writing any fixture

`ExamFactory:23` and `SectionFactory:21` **each independently call `Subject::factory()`**. So
`Exam::factory()->create()` + `Section::factory()->create()` land in **different subjects**. The
`exam_section` pivot masks this today; D-1 makes it fatal.

- **Every same-subject fixture must pin `subject_id` explicitly.** Copy
  `tests/Feature/AttemptAvailabilityTest::enrolledStudentFor()` — it already does this correctly.
- **INT-04's negative test must construct the different-subject case deliberately** and assert the IDs
  differ. If it relies on the factories disagreeing by accident, it passes while proving nothing — a
  green test guarding an open CRITICAL leak.

**~20 files reference `exam_section` / `->sections()`** and must be rewritten off it: `ExamIndexTest`,
`ExamAccessTest`, `ExamVisibilityRegressionTest`, `AttemptNullGuardTest::attemptFixture()`,
`AttemptPolicyTest`, `AttemptStartTest`, `AttemptShowTest`, `AttemptAnswerTest`, `AttemptSubmitTest`,
`Phase4ReviewFixesTest`, `ResultTest` (both roles), `Phase5ReviewFixesTest`, `GradeAnswerTest`,
`AttemptGraderTest`, and others. This is the phase's largest blast radius — a missed consumer is a
runtime fatal, not a soft failure.

---

## ⚠ Tests that assert behavior this phase deliberately INVERTS

Found by the planner via grep; **verified by the orchestrator**. These currently pass and *must* be
updated — they are not regressions when they fail, they are the old contract.

| Test | File:line | Why it must change |
|------|-----------|--------------------|
| `test_an_attempted_exam_cannot_be_unpublished` | `tests/Feature/Lecturer/Phase5ReviewFixesTest.php:57` | **Directly blocks CLS-06** ("toggle in BOTH directions, including after students have attempted"). **The original `--filter=ExamPublish` in this map did NOT match this filename — CLS-06 would have shipped RED under its own stated gate.** Filter corrected above. |
| `test_editing_a_published_exam_is_forbidden` | `tests/Feature/Lecturer/ExamControllerTest.php:120` | Asserts the D-4 gate that EDT-04 relaxes. |
| `test_setting_the_window_on_a_published_exam_is_forbidden_and_unchanged` | `tests/Feature/Lecturer/ExamAvailabilityTest.php:157` | Same gate. Its doc comment insists "D-06 stands, no exception carved" — that comment is now stale and must be corrected, not worked around. |

**Also — two visibility tests must be INVERTED, not repaired** (planner finding):
`test_a_student_enrolled_in_a_different_section_is_forbidden` and its list twin assert exactly the
denial **CLS-05 removes** (a student in *any* section of the subject must now see the exam). The trap:
mechanically pinning `subject_id` makes them fail, and the tempting fix is to re-point them at a
different *subject* — which silently converts CLS-05's only coverage into a duplicate of INT-04, leaving
CLS-05 untested while everything looks green. Invert them with explicit `assertSame` subject guards.

**Also — `AttemptVanishedException` has a latent lecturer bug** (planner finding, orchestrator-verified):
`app/Exceptions/AttemptVanishedException.php:76` redirects to `student.exams.index`, which
`routes/student.php:10` gates behind `role:student`. D-5's guard site (`AnswerGradeController`) is a
**lecturer** route — so the guard would catch the crash and then strand the lecturer on a 403 telling
them to "return to your exam list." Phase 9 shipped this latently (no lecturer path reached it yet);
D-5 activates it. Needs a `routeIs('lecturer.*')` branch with lecturer-appropriate copy.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| The confirmation modal's rendered copy reads correctly to a human about to destroy graded work | INT-02 | PHPUnit can assert the strings and counts, but not that a lecturer *understands* they are about to permanently delete scores. Given D-2 has no undo, one human read-through of both modals (graded=0 and graded>0) is warranted. | Log in as the seeded lecturer, seed an exam with 1 in-progress + 1 submitted + 1 graded attempt, trigger both Reset and Save-edit, read both modals. |

---

## Threat Model Refs

| ID | Threat | STRIDE | Mitigation (this phase) |
|----|--------|--------|--------------------------|
| T-10-01 | Cross-subject exam exposure — a student sees/starts an exam from a subject they are not enrolled in (v2.0's CRITICAL leak) | Information Disclosure / Elevation of Privilege | D-1: drop the `exam_section` pivot so the relationship is **unexpressible**. Verified by an explicit negative test across list + direct access + start. |
| T-10-02 | Irreversible destruction of graded student work via reset/edit | Tampering / Destruction | D-2's hard delete is permanent (`answers.attempt_id` cascades). Mitigation is the **warning** — exact counts, both populations named, permanence stated, nothing changes until confirmed. Count-correctness is therefore a security control, not a UX detail. |
| T-09-01 | TOCTOU: a vanished attempt row crashes a locked read (carried from Phase 9) | Tampering / DoS | D-5 guards the last unguarded site (`AnswerGradeController:29`). After this phase, T-09-01 should hold repo-wide. |

**ASVS L1 note:** V4 (Access Control) is the live category — T-10-01 is an authorization boundary, and
D-1 converts it from a runtime check into a structural impossibility. V11 (business logic) covers
T-09-01/T-10-02.

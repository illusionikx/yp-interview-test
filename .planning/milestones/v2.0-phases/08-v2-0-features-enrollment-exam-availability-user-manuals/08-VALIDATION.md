---
phase: 8
slug: v2-0-features-enrollment-exam-availability-user-manuals
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-07-16
---

# Phase 8 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Derived from 08-RESEARCH.md ## Validation Architecture.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11 — Laravel `Tests\TestCase` + `RefreshDatabase` |
| **Config file** | `phpunit.xml` (root) — DB lines commented out, so tests run against the `.env` MySQL connection (`yp-student-exam`), not SQLite |
| **Quick run command** | `php artisan test --filter=<TestClass>` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~30–90 seconds (full suite; 187 green entering this phase) |

---

## Sampling Rate

- **After every task commit:** targeted `php artisan test --filter=<TouchedTestClass>`
- **After every wave:** grouped re-runs (`--filter=Enrollment` / `--filter=Availability` / `--filter=Attempt`) **plus** `ExamVisibilityRegressionTest` (Phase 7's hard gate) to confirm the list/gate-agreement invariant survives the `AttemptPolicy` change
- **Phase gate:** full `php artisan test` green, with special attention to every existing `tests/Feature/Student/Attempt*.php` (Phase 4) — the `AttemptPolicy` fix alters shared authorization logic those tests already cover; a regression there is the highest-value signal
- **Max feedback latency:** ~90 seconds

---

## Per-Requirement Verification Map

| Requirement | Behavior | Test Type | Automated Command | File Exists |
|-------------|----------|-----------|-------------------|-------------|
| ENR-01 | Section list shows live `28/30` capacity + window status | feature | `--filter=SubjectBrowseControllerTest` | ❌ W0 |
| ENR-02 | Applies never exceed capacity | feature (**sequential simulation** — see limitation) | `--filter=EnrollmentControllerTest` | ❌ W0 |
| ENR-03 | Withdraw before close succeeds; after close refused | feature | `--filter=EnrollmentControllerTest` | ❌ W0 |
| ENR-04 | Second active enrollment in same subject+semester refused | feature | `--filter=EnrollmentControllerTest` | ❌ W0 |
| ENR-05 | Re-apply after withdraw/reject **updates** (never duplicates) the existing row | feature | `--filter=EnrollmentControllerTest` | ❌ W0 |
| ENR-06 | Out-of-window sections listed with label, no Apply action | feature | `--filter=SubjectBrowseControllerTest` | ❌ W0 |
| ENR-07 | Any assigned lecturer rejects with fixed reason; student sees it | feature | `--filter=RejectEnrollmentControllerTest` | ❌ W0 |
| AVL-01 | Lecturer sets optional start/end window (on a **draft** exam) | feature | `--filter=ExamAvailabilityTest` | ❌ W0 |
| AVL-02 | Pre-start page shows instructions/duration/window/section + Proceed/Back | feature | `--filter=ExamShowTest` | ⚠️ extend existing |
| AVL-03 | Attempt start refused outside window with a clear message | feature | `--filter=AttemptAvailabilityTest` | ❌ W0 |
| AVL-04 | **In-progress attempt survives withdrawal/rejection/window-close — CRITICAL regression** | feature | `--filter=AttemptPolicyTest` | ⚠️ extend existing |
| AVL-05 | `beforeunload` attached in-progress / detached on submit + auto-submit | **manual-only** | N/A — see below | N/A |
| DEL-04 | Student manual covers its 5 named task flows | **manual-only** (content review) | N/A | N/A |
| DEL-05 | Lecturer manual covers its 4 named task flows | **manual-only** (content review) | N/A | N/A |

**Boundary rule (REQUIREMENTS.md #6):** windows are half-open — `[opens_at, closes_at)` and `[available_from, available_until)`; allowed while `open ≤ now < close`; a null bound is unbounded. **Every window test must include the exact-boundary case.**

---

## Stated Coverage Limitations (do not imply otherwise)

- **ENR-02 concurrency is NOT proven by an automated test.** PHPUnit's runner is single-threaded; it cannot fire two truly simultaneous requests. The automated test is a *sequential simulation* (fill to `capacity - 1`, two back-to-back applies, assert exactly one succeeds) — it proves the count check and that the lock introduces no deadlock/logic error. The actual concurrency-safety argument rests on the **structural presence of `lockForUpdate()` inside the transaction**, verifiable by code review and matching the pattern already proven for Phase 7's section-sequence assignment. A true multi-process race harness is disproportionate for this project's scope.
- **AVL-05 (`beforeunload`) has no automated vehicle.** PHPUnit's HTTP-request model executes no browser JS and cannot assert a native dialog. Laravel Dusk is not in `composer.json` and adding it for a single interaction test would violate CLAUDE.md's no-new-Composer-packages constraint. Covered by a human-verify checkpoint instead.

---

## Wave 0 Requirements

- [ ] `tests/Feature/Student/SubjectBrowseControllerTest.php` — ENR-01, ENR-06
- [ ] `tests/Feature/Student/EnrollmentControllerTest.php` — ENR-02, ENR-03, ENR-04, ENR-05
- [ ] `tests/Feature/Lecturer/RejectEnrollmentControllerTest.php` — ENR-07 (incl. non-assigned lecturer → 403, per SEC-03)
- [ ] `tests/Feature/Lecturer/ExamAvailabilityTest.php` — AVL-01 (incl. empty datetime-local → null coercion, the research's flagged assumption)
- [ ] `tests/Feature/Student/AttemptAvailabilityTest.php` — AVL-03 (incl. exact-boundary case)
- [ ] `tests/Feature/Student/AttemptPolicyTest.php` — **extend existing**: AVL-04 critical regression (in-progress attempt survives withdrawal / rejection / window close)
- [ ] `tests/Feature/Student/ExamShowTest.php` — extend for AVL-02 pre-start page
- [ ] `database/factories/ExamFactory.php` — add `available()` / `opening()` / `closed()` states so window tests don't hand-roll datetime math

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Tab-close warning during an in-progress attempt | AVL-05 | Native browser dialog; no JS execution in PHPUnit; Dusk not available | Start an attempt → try to close the tab/navigate away → confirm the browser's native confirmation appears |
| No warning on intentional submit or auto-submit | AVL-05 | Same | Submit the exam (and separately let the timer auto-submit) → confirm NO dialog appears on that navigation |
| Student manual accuracy | DEL-04 | Content review vs. shipped UI | Read the in-app student manual; confirm each of the 5 flows matches the real screens |
| Lecturer manual accuracy | DEL-05 | Content review vs. shipped UI | Read the in-app lecturer manual; confirm each of the 4 flows matches the real screens |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or a Wave 0 dependency / documented manual-only justification
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] Every window test includes the exact-boundary case (REQUIREMENTS.md #6)
- [ ] AVL-04 critical regression green (in-progress attempt survives withdraw/reject/window-close)
- [ ] Phase 7's `ExamVisibilityRegressionTest` still green after the `AttemptPolicy` change
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending

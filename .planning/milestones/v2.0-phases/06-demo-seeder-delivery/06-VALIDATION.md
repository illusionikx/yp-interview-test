---
phase: 6
slug: demo-seeder-delivery
status: approved
nyquist_compliant: true
wave_0_complete: false
created: 2026-07-16
---

# Phase 6 — Validation Strategy

> Derived from `06-RESEARCH.md` §Validation Architecture. The clean-clone `migrate:fresh --seed` path is the acceptance gate.

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11, `php artisan test` |
| **Config** | `phpunit.xml` (overrides commented → live MySQL `yp-student-exam`, `RefreshDatabase`) |
| **Quick run** | `php artisan test --filter=DatabaseSeederTest` |
| **Full suite** | `php artisan test` |

## Sampling Rate
- Per task: `php artisan test --filter=DatabaseSeederTest`
- Per wave: `php artisan test` (must stay green vs Phases 1–5's 175 tests)
- Phase gate: full suite green **+ the manual `php artisan migrate:fresh --seed` clean-clone run**

## Per-Task Verification Map

| Req | Behavior | Test |
|-----|----------|------|
| DEL-01 | Seeder builds the full graph: lecturer + students (verified emails, roles), classrooms with students, subjects linked, a published time-limited exam with MCQ (≥2 opts, one correct) + open-text assigned to a classroom, and the pre-graded demo attempt at `submitted` (MCQ graded, open-text pending) | `tests/Feature/DatabaseSeederTest.php::test_seeder_builds_full_demo_graph` |
| DEL-01/D-05 | Re-running the seeder is idempotent — no duplicate rows (assert by natural key, not id) | `DatabaseSeederTest::test_seeder_is_idempotent_on_repeat_runs` |
| DEL-01/D-05 | `php artisan migrate:fresh --seed` succeeds on an empty schema; demo credentials log in | **Manual** clean-clone checkpoint (executor runs it) |
| DEL-02 | README exists at repo root, contains the setup commands + demo credentials | `tests/Feature/ReadmeTest.php::test_readme_documents_setup_and_credentials` (assertFileExists + assertStringContainsString `migrate:fresh --seed`, demo email) |

## Wave 0 Requirements
- [ ] `tests/Feature/DatabaseSeederTest.php` (full graph + idempotency; may consolidate the existing `TestAccountSeederTest.php` or run alongside)
- [ ] `tests/Feature/ReadmeTest.php` (README presence/content assertions)

## Manual-Only Verifications
| Behavior | Req | Why | Instructions |
|----------|-----|-----|--------------|
| Clean-clone end-to-end | DEL-01 | Real provisioning | `php artisan migrate:fresh --seed` on empty `yp-student-exam`; then log in as the seeded lecturer + student and exercise author→assign→take→grade→result |

## Validation Sign-Off
- [x] Seeder graph + idempotency + README asserted; clean-clone is the manual gate
- [x] `nyquist_compliant: true`

**Approval:** approved 2026-07-16 (idempotent seeder + working `migrate:fresh --seed` + documented README are the gates)

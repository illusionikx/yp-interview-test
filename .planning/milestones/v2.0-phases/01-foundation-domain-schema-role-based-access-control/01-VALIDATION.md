---
phase: 1
slug: foundation-domain-schema-role-based-access-control
status: approved
nyquist_compliant: true
wave_0_complete: false
created: 2026-07-15
---

# Phase 1 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution. Derived from `01-RESEARCH.md` §Validation Architecture.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.0.1 (`phpunit/phpunit ^11.0.1`) |
| **Config file** | `phpunit.xml` (repo root) |
| **Quick run command** | `php artisan test --filter=<TestClassOrMethod>` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~15–30 seconds (small suite) |

> ⚠ **Environment note (from research):** `phpunit.xml`'s `DB_CONNECTION`/`DB_DATABASE` overrides are **commented out**, so the suite runs `RefreshDatabase` against the **live** `yp-student-exam` MySQL database — not an isolated test DB. Running `php artisan test` will migrate/truncate the real dev database. Acceptable for this local/grading workflow (the seeder rebuilds demo data), but the executor must not assume test isolation from dev data.

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter=<relevant test>`
- **After every plan wave:** Run `php artisan test` (full suite)
- **Before `/gsd-verify-work`:** Full suite green, plus a manual `php artisan migrate:fresh --seed` against `yp-student-exam` confirming all 9 domain tables + unique constraints exist
- **Max feedback latency:** ~30 seconds

---

## Per-Task Verification Map

Task IDs are assigned by the planner; rows below are keyed by requirement until plans exist.

| Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 01-01 | 1 | Success #1 (schema) | — | All 9 tables + `attempts(exam_id,user_id)` & `answers(attempt_id,question_id)` unique indexes exist | feature (schema) | `php artisan test --filter=DomainSchemaTest` | ❌ W0 | ⬜ pending |
| 01-02 | 2 | RBAC-01 | — | `role` stored on users, cast to `Role` enum | unit | `php artisan test --filter=UserRoleCastTest` | ❌ W0 | ⬜ pending |
| 01-04 | 3 | RBAC-02 | T-tamper-role | Registration ignores client `role`, always Student | feature | `php artisan test --filter=RegistrationTest` | ✅ (extend) | ⬜ pending |
| 01-03 | 3 | RBAC-03 | — | Post-login redirect lands each role in its area | feature | `php artisan test --filter=RoleRedirectTest` | ❌ W0 | ⬜ pending |
| 01-03 | 3 | RBAC-04 | T-forgot-mw | Student→lecturer URL blocked server-side (403/redirect), and vice versa | feature | `php artisan test --filter=RoleMiddlewareTest` | ❌ W0 | ⬜ pending |

*Wave 0 test files are authored during execution (TDD tasks in 01-02/01-03/01-04); `File Exists` flips to ✅ and `Status` to green as each wave lands.*

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/DomainSchemaTest.php` — Success #1: assert `Schema::hasTable()` for all 9 domain tables; assert composite unique indexes on `attempts(exam_id,user_id)` and `answers(attempt_id,question_id)`
- [ ] `tests/Unit/UserRoleCastTest.php` — RBAC-01: assert `$user->role instanceof Role` after `User::factory()->create(['role' => Role::Lecturer])`
- [ ] `tests/Feature/RoleRedirectTest.php` — RBAC-03: login as lecturer/student fixture, assert redirect target
- [ ] `tests/Feature/RoleMiddlewareTest.php` — RBAC-04: as Student GET a lecturer-only route → 403/redirect; as Lecturer GET a student-only route → 403/redirect
- [ ] Extend `tests/Feature/Auth/RegistrationTest.php` — RBAC-02: POST `/register` with an extra `role=lecturer` field, assert created user is still `Role::Student`
- [ ] `database/factories/ClassroomFactory.php` — fixture dependency for attaching a Student to a classroom

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Clean-clone schema stands up | Success #1 | End-to-end DB provisioning against live MySQL | `php artisan migrate:fresh --seed` on `yp-student-exam`, then confirm 9 domain tables + constraints (`php artisan db:show` / `SHOW INDEX`) |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 30s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-07-15 (plan-checker verified: 0 blockers; every task carries an automated verify command)

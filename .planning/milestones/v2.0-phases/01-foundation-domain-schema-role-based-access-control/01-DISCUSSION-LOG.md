# Phase 1: Foundation — Domain Schema & Role-Based Access Control - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-15
**Phase:** 1-Foundation — Domain Schema & Role-Based Access Control
**Mode:** `--auto` (Claude auto-selected the recommended, research-grounded option for each area; no interactive prompts)
**Areas discussed:** Schema scope, Role storage, Student↔classroom link, Route/area separation, Role middleware, Post-login redirect, Registration lock, Test accounts

---

## Schema scope

| Option | Description | Selected |
|--------|-------------|----------|
| All domain tables now | Migrate every table (classrooms…answers) this phase | ✓ |
| RBAC-only tables now | Add only users.role; defer other tables to their feature phases | |

**Auto choice:** All domain tables now (recommended).
**Notes:** SUMMARY.md is schema-first and ROADMAP Phase 1 criterion #1 requires every table to exist. Constraints (unique `(exam_id,user_id)`, unique `(attempt_id,question_id)`) baked in from v1.

---

## Role storage

| Option | Description | Selected |
|--------|-------------|----------|
| `role` enum column on users | Backed-enum cast, no package | ✓ |
| Separate roles table | Relational roles/permissions | |
| spatie/laravel-permission | Package-managed RBAC | |

**Auto choice:** `role` enum column (recommended).
**Notes:** Two fixed roles; STACK.md explicitly advises against a permissions package. Matches PROJECT.md Key Decision.

---

## Student ↔ classroom link

| Option | Description | Selected |
|--------|-------------|----------|
| Single `classroom_id` on users | One class per student | ✓ |
| Many-to-many pivot | Student in multiple classes | |

**Auto choice:** Single `classroom_id` FK (recommended).
**Notes:** Brief implies one class per student; simplest correct model.

---

## Route / area separation

| Option | Description | Selected |
|--------|-------------|----------|
| Role-gated route groups (separate files) | routes/lecturer.php + routes/student.php | ✓ |
| Prefixed groups inside web.php | Fewer files | |

**Auto choice:** Role-gated route groups (recommended); prefixed groups in web.php acceptable at planner discretion.
**Notes:** Behavior (role-gated prefixes) matters more than file count.

---

## Role middleware

| Option | Description | Selected |
|--------|-------------|----------|
| Custom `EnsureUserHasRole` alias in bootstrap/app.php | `role:lecturer` usage | ✓ |
| Inline Gate checks per controller | No middleware | |

**Auto choice:** Custom `role` middleware alias (recommended).
**Notes:** Laravel 11 registers aliases in bootstrap/app.php (no Kernel.php). Server-enforced, not nav-hiding.

---

## Post-login redirect

| Option | Description | Selected |
|--------|-------------|----------|
| Role-dispatch on Breeze `/dashboard` | Redirect by role from dashboard route | ✓ |
| Fork Breeze auth controllers | Override login redirect | |

**Auto choice:** Role-dispatch on `/dashboard` (recommended).
**Notes:** Keeps Breeze login flow untouched.

---

## Registration lock

| Option | Description | Selected |
|--------|-------------|----------|
| Force role=student in RegisteredUserController | Ignore client input | ✓ |
| Allow role selection at registration | Public role choice | |

**Auto choice:** Force role=student (recommended).
**Notes:** No public path to a Lecturer account (security). Lecturers seeded.

---

## Test accounts

| Option | Description | Selected |
|--------|-------------|----------|
| Minimal lecturer+student seed now | Enough to verify gating | ✓ |
| Defer all seeding to Phase 6 | No accounts to test with this phase | |

**Auto choice:** Minimal seed now (recommended).
**Notes:** Success criteria #2–4 require logging in as each role. Full demo dataset + README remain Phase 6.

---

## Claude's Discretion

- Exact column names/types beyond fixed constraints, model/factory organization, and route-file layout (separate vs grouped).

## Deferred Ideas

- RBAC-05 per-record class-scoped Policies / IDOR denial → Phase 3
- Full demo seeder + README (DEL-01/DEL-02) → Phase 6
- Lecturer management UIs (classrooms/subjects/exams) → Phase 2

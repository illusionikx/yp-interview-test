# Phase 3: Exam Assignment & Class-Scoped Access - Context

**Gathered:** 2026-07-15
**Status:** Ready for planning

<domain>
## Phase Boundary

Connect exams to classrooms and enforce that only the right students can reach them. Lecturers assign a (published) exam to one or more classrooms via the `exam_classroom` pivot; students see and can open only published exams assigned to their own classroom; and every ID-addressable exam route is gated server-side by an authorization policy so a guessed/forged URL to an unassigned exam is denied — not merely hidden. Covers ASN-01, ASN-02, RBAC-05.

**In scope:** lecturer exam→classroom assignment UI/endpoint (`exam_classroom` sync); a student-facing exam **list** (published + assigned to the student's classroom) and a read-only exam **landing/detail** page; an `ExamPolicy` that establishes the class-scoped access convention (used both to filter the index and to authorize direct access).

**Out of scope (later phases):** actually **taking** an exam — starting an attempt, rendering questions, the countdown timer, autosave, submit (Phase 4); grading & results, and the `AttemptPolicy`/result-access gates (Phases 4/5). This phase establishes the authorization *pattern* on the exam resource; attempts/results extend it later. The student exam landing page has a "Start" affordance that is wired to the real take-flow in Phase 4 (this phase may stub it / point to a placeholder).
</domain>

<decisions>
## Implementation Decisions

*(Auto mode: recommended, research/prior-phase-grounded defaults.)*

### Exam → classroom assignment (ASN-01)
- **D-01:** Assign on the **lecturer exam page** — a multi-select of classrooms synced through the `exam_classroom` pivot (`$exam->classrooms()->sync($request->validated('classroom_ids', []))`). A dedicated endpoint (e.g. `Lecturer\ExamAssignmentController@update` or a method on `ExamController`) with a Form Request validating classroom ids exist. Assignment is editable regardless of publish state; visibility to students additionally requires `is_published`.

### Student exam visibility (ASN-02)
- **D-02:** A student sees only exams where `is_published = true` AND the exam is assigned to the student's `classroom_id`. Query via the relationship: `Exam::where('is_published', true)->whereHas('classrooms', fn ($q) => $q->whereKey($student->classroom_id))`. A student with `classroom_id = null` sees an empty list. The index and the policy MUST use the same predicate (single source of truth).

### Class-scoped access control — the RBAC-05 core
- **D-03:** An `ExamPolicy` with a method (e.g. `takeable(User $user, Exam $exam)` / `viewAsStudent`) returning true only when the exam is published AND assigned to the user's classroom AND the user is a Student. Enforce with `$this->authorize('takeable', $exam)` (→ 403) on the student exam **show** route, and reuse the *same* predicate to scope the student index. Register the policy (Laravel 11 auto-discovery `App\Policies\ExamPolicy`). This is the cross-cutting authorization convention the research calls for — Phase 4/5 will add `AttemptPolicy`/result gates following the same shape.
- **D-04:** No IDOR: a student opening the URL of an exam not assigned to their classroom, or not published, is denied server-side (403/404), not merely omitted from the list. This is the concrete, testable heart of RBAC-05 for this phase.

### Student area (routes/student.php)
- **D-05:** A `Student\ExamController` (index + show) mounted in the existing `routes/student.php` group (already `auth` + `role:student` gated from Phase 1). Index lists assigned published exams; show is a **read-only** landing page (title, subject, duration, question count) with a "Start" button. The Start action targets the Phase-4 attempt route — in this phase it may be a placeholder/disabled-until-Phase-4 stub. Do NOT render questions or any attempt logic here.

### Reuse & scope
- **D-06:** Reuse Breeze Blade + Tailwind + Alpine and the Phase-1 `student.home` placeholder (add navigation to the exam list). No new packages. The `exam_classroom` pivot + `Exam::classrooms()` / `Classroom::exams()` relationships already exist from Phase 1 schema — verify/relabel, do not recreate.

### Claude's Discretion
- Whether assignment lives on `ExamController` vs a dedicated `ExamAssignmentController`; exact policy method name; student landing page layout; how the Phase-4 "Start" stub is represented — planner/executor choice, provided D-01..D-05 hold.
</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase scope & requirements
- `.planning/ROADMAP.md` §"Phase 3" — goal + success criteria; ASN-01, ASN-02, RBAC-05
- `.planning/REQUIREMENTS.md` — ASN/RBAC-05 text
- `.planning/research/PITFALLS.md` — IDOR is the #1 pitfall; Policy per ID-addressable resource + class-scoped index queries (route-model binding checks existence, not ownership)
- `.planning/research/ARCHITECTURE.md` — two-layer authz (role middleware + Policies); `exam_classroom` pivot; ExamPolicy shape

### Existing surface to build on (Phases 1–2)
- `routes/student.php` — the `role:student`-gated group to add student exam routes to
- `routes/lecturer.php` + `app/Http/Controllers/Lecturer/ExamController.php` — where assignment lives
- `app/Models/Exam.php`, `app/Models/Classroom.php`, `app/Models/User.php` — `exam_classroom` relationship (`Exam::classrooms()`), `is_published`, `User::classroom_id`
- `app/Http/Requests/Lecturer/` — Form Request pattern to mirror for the assignment request
</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`exam_classroom` pivot + relationships** (Phase 1 schema) — `Exam::classrooms()` / `Classroom::exams()` belongsToMany. Use `sync()` for assignment.
- **`is_published`** on exams (Phase 2) — the visibility gate.
- **`User::classroom_id`** (Phase 1) — the student's class; the scoping key.
- **`routes/student.php`** + `role:student` middleware (Phase 1) — mount student exam routes here.
- **Lecturer exam show page** (`resources/views/lecturer/exams/show.blade.php`, Phase 2) — add the "Assign to classes" section here.
- **Breeze Blade/Tailwind/Alpine + Form Request pattern** — reuse throughout.

### Established Patterns
- Laravel 11 policy auto-discovery (`App\Policies\ExamPolicy`), `$this->authorize()` in controllers.
- Same-predicate rule: the index filter and the direct-access policy must share one query/logic so the list and the gate never diverge (prevents "hidden but reachable").

### Integration Points
- New student routes in `routes/student.php`; assignment endpoint in `routes/lecturer.php`.
- `ExamPolicy` registered (auto-discovered) and applied on the student show route.
- Student `student.home` (placeholder) gains a link to the exam list.
</code_context>

<specifics>
## Specific Ideas

- The index filter and the policy `takeable()` MUST use the identical published+assigned predicate — do not hand-roll two different checks.
- Phase 3 delivers a **read-only** student exam landing; the "Start" button is a Phase-4 seam (stub/placeholder now).
</specifics>

<deferred>
## Deferred Ideas

- **Taking an exam — attempt creation, question rendering, countdown timer, autosave, submit (TAK-01..06)** → Phase 4. The "Start" button is only a seam here.
- **AttemptPolicy + result-access gates (part of RBAC-05's "attempt, result" clause)** → Phases 4/5, following the ExamPolicy pattern established here.
- **Grading & results** → Phase 5.

None of the above are user scope-creep — they are the phase boundaries, noted so the planner doesn't pull them forward.
</deferred>

---

*Phase: 3-Exam Assignment & Class-Scoped Access*
*Context gathered: 2026-07-15*

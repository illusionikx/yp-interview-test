# Phase 2: Classroom, Subject & Exam Authoring - Context

**Gathered:** 2026-07-15
**Status:** Ready for planning

<domain>
## Phase Boundary

Lecturers build the complete teaching content model on top of Phase 1's schema/models: create/edit/delete **classrooms** and **subjects**, link subjects to classrooms, assign students to a classroom, and author **exams** (belonging to a subject) with **multiple-choice** (options, exactly one correct) and **open-text** questions, each with a point value, then publish them. Covers CLS-01..04, EXM-01..06.

**In scope:** lecturer-facing CRUD for classrooms, subjects, classroom↔subject linkage, student roster assignment, exams, nested questions/options, draft→published state.

**Out of scope (later phases):** assigning exams to classrooms + student-facing class-scoped access (Phase 3, ASN/RBAC-05 — building the `exam_classroom` link and any student view belongs there, NOT here); students taking exams (Phase 4); grading & results (Phase 5); full demo seeder + README (Phase 6). This phase produces authoring UIs for the lecturer only — no student-facing screens.
</domain>

<decisions>
## Implementation Decisions

*(Auto mode: recommended, research/Phase-1-grounded defaults.)*

### Controllers & routing
- **D-01:** Resource controllers under an `App\Http\Controllers\Lecturer\` namespace — `ClassroomController`, `SubjectController`, `ExamController`, plus nested question/option management (e.g. `Lecturer\ExamQuestionController`). Register routes inside the existing **`routes/lecturer.php`** group (already `auth` + `role:lecturer` gated from Phase 1) with `lecturer.` name prefix.

### Classroom & subject CRUD (CLS-01, CLS-02)
- **D-02:** Standard resource CRUD (index/create/store/edit/update/destroy) for classrooms and subjects. Names required; keep them unique per entity to avoid duplicates (planner/executor may relax if it complicates seeding). Form Requests validate input — never expose `role`/`classroom_id`/`created_by` as mass-assignable from these forms.

### Classroom ↔ subject linkage (CLS-03)
- **D-03:** Managed from the **classroom** form as a multi-select of subjects, synced through the `classroom_subject` pivot (`$classroom->subjects()->sync(...)`). Pivot name is `classroom_subject` (resolved in Phase 1).

### Student roster (CLS-04)
- **D-04:** A student belongs to exactly one classroom (`users.classroom_id`, from Phase 1). Assign/reassign students on the **classroom** page — a manager listing students (role=Student) with attach/detach that sets/clears `classroom_id`. Detaching sets it null (student unassigned).

### Exam authoring UX (EXM-01, EXM-05, EXM-06)
- **D-05:** An exam `belongs to` a subject and has: `title`, optional `description`, `duration_minutes` (the time limit), `created_by` (the authoring lecturer), `is_published`. Create exam → land on the exam's own page which lists its questions and hosts the add-question form.
- **D-06:** Draft vs published: `is_published=false` exams are fully editable (exam fields, questions, options). Publishing (`is_published=true`) makes the exam eligible for classroom assignment (Phase 3). **Unpublish→edit is allowed** (reversible) because no attempts exist until Phase 4 — a later phase can lock editing once attempts exist. Edit/delete of the exam and its questions is only permitted while unpublished.

### Question & option authoring (EXM-02, EXM-03, EXM-04)
- **D-07:** Questions share one `questions` table with a `type` discriminator enum (`Mcq` | `Open`, from Phase 1) and a `points` column (default 1). Add-question form on the exam page: choosing MCQ reveals dynamic **option rows** (Alpine.js `x-data` add/remove) with a single "correct" radio; choosing Open shows just the question text. Open-text questions have no options.
- **D-08:** MCQ validation (Form Request): **≥2 options, exactly one marked correct**, single-select only (multi-select is v2, out of scope). `points` is a positive integer, default 1. Reject an MCQ with zero or multiple correct options.

### Lecturer ownership scoping
- **D-09:** For v1 simplicity, **lecturers share management** of classrooms, subjects, and exams (any lecturer can manage any). The brief specifies a single Lecturer role without per-lecturer ownership boundaries. `exams.created_by` records the author (nullable, `nullOnDelete` from Phase 1 review fix) but is not used to restrict edits in this phase. Per-record ownership policies are NOT required — do not build them. (Student class-scoped access is a different concern, handled in Phase 3.)

### UI approach
- **D-10:** Reuse the Breeze Blade stack — `x-app-layout`, existing `resources/views/components/*` (input, label, primary-button, etc.), Tailwind, Alpine. Functional CRUD forms and tables, no bespoke design system. (No UI-SPEC needed for this phase — it reuses existing components; the roadmap reserves UI-SPECs for Phases 4/5. Plan with `--skip-ui`.)

### Claude's Discretion
- Exact route/view file layout, whether questions get their own controller vs nested under exam, table vs card listings, name-uniqueness enforcement, and Alpine component structure — planner/executor choice, provided the decisions above hold.
</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase scope & requirements
- `.planning/ROADMAP.md` §"Phase 2" — goal + success criteria; CLS-01..04, EXM-01..06
- `.planning/REQUIREMENTS.md` — the CLS/EXM requirement text
- `.planning/PROJECT.md` §"Key Decisions" — MCQ single-correct, points-per-question, build-on-Breeze

### Schema & models (built in Phase 1 — do not recreate)
- `.planning/phases/01-foundation-domain-schema-role-based-access-control/01-02-SUMMARY.md` — the models (Exam, Question, Option, Classroom, Subject, User) + relationships + `QuestionType`/`Role` enums this phase drives
- `.planning/phases/01-foundation-domain-schema-role-based-access-control/01-01-SUMMARY.md` — the migrations/columns (exams: subject_id, created_by, duration_minutes, is_published; questions: type, points; options: is_correct)
- `.planning/research/ARCHITECTURE.md` — full field-level schema reference

### Existing RBAC surface to build within (Phase 1)
- `routes/lecturer.php` — the `role:lecturer`-gated group to add authoring routes to
- `app/Models/*` — Eloquent models + relationships (classroom↔subject, exam→subject, exam→questions→options)
</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets (from Phase 1)
- **Models**: `Classroom`, `Subject`, `Exam`, `Question`, `Option`, `User` (with `classroom()` relationship + `role`/`isLecturer()`), enums `Role`/`QuestionType`. Relationships (`exam->subject`, `exam->questions`, `question->options`, `classroom->subjects`, `classroom->students`) already defined — reuse them.
- **`ClassroomFactory`** exists; question/option/exam/subject factories will likely be needed for tests (add as required).
- **`routes/lecturer.php`** + `role:lecturer` middleware alias — mount authoring routes here (already gated).
- **Breeze Blade components** (`resources/views/components/`), `x-app-layout`, Tailwind + Alpine + Vite — reuse for all forms/tables.

### Established Patterns
- Laravel 11 resource controllers + Form Requests; Eloquent relationships; `sync()` for pivots. No repository/service layer for CRUD.
- Mass-assignment discipline (Phase 1): never accept `role`/`classroom_id`/`created_by` from request input on these forms; set `created_by = auth()->id()` server-side.

### Integration Points
- New routes go in `routes/lecturer.php`.
- Lecturer dashboard/home (`resources/views/lecturer/home.blade.php`, placeholder from Phase 1) — add navigation to classrooms/subjects/exams here.
- `classroom_subject` pivot (sync from classroom form); `users.classroom_id` (roster assignment).
</code_context>

<specifics>
## Specific Ideas

- MCQ = single correct answer only (multi-select deferred to v2 per REQUIREMENTS).
- `points` per question, default 1 (already in schema).
- Publishing is the gate that makes an exam assignable in Phase 3 — this phase just sets `is_published`; it does NOT build the `exam_classroom` assignment (that's Phase 3).
</specifics>

<deferred>
## Deferred Ideas

- **Exam→classroom assignment + student-facing access (ASN-01/02, RBAC-05)** → Phase 3. Do not build `exam_classroom` linking UI or any student exam view here.
- **Taking exams / attempts / timer** → Phase 4.
- **Grading & results** → Phase 5.
- **Randomized question/option order, multi-select MCQ** → v2 (out of scope).
- **Locking exam edits once attempts exist** → revisit in Phase 4 when attempts are introduced.

None of the above were user scope-creep — they are the phase boundaries, noted so the planner doesn't pull them forward.
</deferred>

---

*Phase: 2-Classroom, Subject & Exam Authoring*
*Context gathered: 2026-07-15*

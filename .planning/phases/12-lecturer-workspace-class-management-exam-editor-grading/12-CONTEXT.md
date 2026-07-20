# Phase 12: Lecturer Workspace — Class Management, Exam Editor & Grading - Context

**Gathered:** 2026-07-18
**Status:** Ready for planning
**Mode:** Smart discuss (autonomous, AFK-accepted) — grounded in `.planning/v3.md` (§Class management, §Exam Editor, §Grading), Phase 10/11 decisions, and a codebase scout. Reviewable/overridable.

<domain>
## Phase Boundary

A lecturer runs everything about a subject — its classes, its exams, question authoring, and grading — from **one per-subject hub** reached from the Phase 11 subject list. The hub has two tabs (Classes default, Exams second). The exam editor merges details + questions into one two-tab page with question/option reordering. Grading shows class + exam context, progress, and every student.

**In scope:** CLS-01 (2-tab hub), CLS-02 (classes tab), CLS-03 (class CRUD), CLS-04 (exams tab CRUD), CLS-08 (per-exam grading progress), EDT-01 (exam name), EDT-02 (details+questions two tabs), EDT-03 (reorder options + authoring-time shuffle), EDT-05 (question number + move up/down), GRD-06 (grading page).

**Explicitly REUSE, do not reimplement (Phase 10 delivered these):** CLS-06 draft/active toggle, CLS-07 reset submissions, EDT-04 editor-save warn-and-void. This phase *surfaces* them inside the hub/editor.

**Out of scope:** student class page + take-exam (Phase 13); dark-mode sweep, manual, seeding, Dusk (Phase 14).
</domain>

<decisions>
## Implementation Decisions

### Per-subject hub with tabs (CLS-01)
- A new **per-subject hub** page, reached from the lecturer subject list's "subject & class management" row action (Phase 11). Scope everything to the `{subject}` in the route — this also closes the pre-existing gap where `lecturer.exams.index` is unscoped by lecturer (fold the exam listing into the subject-scoped hub).
- **Two tabs**: Classes (default) + Exams. Alpine `x-data` tab switching, no SPA; support `?tab=exams` deep-linking so post-action redirects can land on the right tab.
- All hub actions route through the existing per-subject **ownership authorization (SEC-03)** — no new ad-hoc inline checks.

### Classes tab (CLS-02, CLS-03)
- Classes **grouped by semester**, past semesters hide/unhide (reuse the Phase 11 Alpine collapse pattern, past collapsed by default).
- Each class row: class code, **total/max students as a progress bar**, actions (view / edit / delete-with-`<x-confirm-modal>`).
- **CRUD reuses the existing `Lecturer\SectionController`** + Store/UpdateSectionRequest (class = Section, copy-only relabel). Form fields: **max students, location, enrollment period (start/end)**.

### Exams tab (CLS-04, CLS-08)
- Lists all exams **for this subject** with create / edit / delete.
- Surfaces Phase 10's **draft↔active toggle (CLS-06)** and **reset submissions (CLS-07)** inline per exam — reuse the existing routes/actions and confirm modals, do not rebuild.
- **CLS-08 grading progress per exam**: a bounded aggregate — graded vs. total submitted attempts (or ungraded-answer count) shown as a small progress indicator per row. No per-attempt loop.

### Exam editor (EDT-01, EDT-02, EDT-03, EDT-05)
- **EDT-01**: `exams.title` already exists — the editor's Details tab carries the exam/test name field (ensure present + validated).
- **EDT-02**: merge exam **Details** and **Questions** into ONE page as two tabs (Alpine), replacing the current separate exam-edit + question pages. Keep the existing `_save-warning-modal` (EDT-04) wired on the Details save.
- **EDT-03**: answer **options reorderable** via move-up/move-down buttons (Decision #8 — no drag-and-drop) operating on the existing `options.position` column; plus **one "shuffle" button** that randomizes the stored `position` order **once at authoring time** (Decision #2 — never per-student runtime randomization; TAK-12 is the student-side mirror).
- **EDT-05**: each question shows **its number with move-up / move-down buttons on its left**, operating on the existing `questions.position` column.
- Reordering is **persisted immediately** (small POST/PATCH per swap or a reorder endpoint); render always `orderBy('position')`. `position` columns already exist on both tables — **no migration needed**.

### Grading (GRD-06)
- Grading page shows **class details + exam details + grading progress + lists every student** (reuse `ResultController` index/show + `AnswerGradeController`). MCQ auto-graded (Phase 4); open-text graded here. Progress = graded/total needing grading.

### Claude's Discretion
- Exact hub route name/URL shape (subject-scoped), tab component structure, and whether reordering uses per-swap requests or a batch reorder endpoint (favor the simplest that persists correctly and is Dusk-testable).
- Grading-progress metric presentation; column ordering.
</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `Lecturer\SectionController` (full CRUD: index/create/store/show/edit/update/destroy) + Store/UpdateSectionRequest — class management backend exists; hub re-presents it per subject.
- `Lecturer\ExamController` (resource + publish/unpublish + resetSubmissions), `ExamQuestionController` (store/edit/update/destroy), `_save-warning-modal.blade.php`, `<x-confirm-modal>` — Phase 10 editor-save/reset/toggle machinery. REUSE.
- `Lecturer\ResultController` (index/show), `AnswerGradeController@update`, `GradeAnswerRequest` — grading backend exists.
- `App\Support\Semester` — grouping/hide-past (as Phase 11 used it).
- `questions.position` and `options.position` columns + `Question`/`Option` `position` in fillable — reordering has storage already.
- `exams.title` column — EDT-01 name field exists.
- Phase 11 patterns: subject-scoped lecturer pages, `x-back-button`, semester-grouped collapsible tables, toast + confirm-modal conventions.

### Established Patterns
- `routes/lecturer.php`: `role:lecturer` group; nested ownership-gated group (SEC-03) already wraps section create/update/destroy. Exams via `Route::resource`.
- Blade + Tailwind 3 + Alpine; Flowbite semantic tokens; `<x-toast>` single flash renderer; no SPA; NO new packages (CLAUDE.md).
- Tests: PHPUnit Feature tests per controller under tests/Feature/Lecturer.

### Integration Points
- New per-subject hub route + view (tabs), reached from Phase 11 lecturer subject list.
- Scope `lecturer.exams.index` listing into the subject hub (closes the unscoped-listing gap).
- Reorder endpoints for questions/options (or extend question update), operating on `position`.
- Tabbed exam editor view replacing separate edit + question pages, keeping `_save-warning-modal`.
</code_context>

<specifics>
## Specific Ideas
- v3.md §Class management (2 tabs, class rows with progress bar, class form fields, exams tab reset/toggle/grading-progress), §Exam Editor (name, 2 tabs, reorder + shuffle, question number + move up/down), §Grading (class+exam details, progress, list all students) are the layout source of truth.
- Decision #8 (move up/down, no drag-drop) and Decision #2 (authoring-time shuffle only) are LOCKED.
- Exam versioning is explicitly OMITTED (v3.md) — editing an attempted exam voids attempts via EDT-04, already shipped.
</specifics>

<deferred>
## Deferred Ideas
- Student-facing class page + take-exam (TAK-*, Phase 13).
- Dark-mode compatibility sweep, wiki manual + help button, demo seeding, Dusk browser tests (Phase 14).
- Drag-and-drop reordering — deferred past v3.0 (Decision #8).
</deferred>
</content>

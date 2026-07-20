# Phase 5: Grading & Results - Context

**Gathered:** 2026-07-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Every submitted attempt ends in an accurate, appropriately-gated score both the student and the lecturer can see. Multiple-choice answers are auto-scored the moment an attempt is finalized; open-text answers are graded by the lecturer; a student's final result is withheld until every open-text answer in their attempt has been graded; and both roles get results views. Covers GRD-01..05.

**In scope:** an `AttemptGrader` service (auto-score MCQ), hooking it into the Phase-4 finalize path, the `submitted → graded` status transition, a lecturer open-text grading UI, a student result view (own, gated on `graded`), and a lecturer results view (per exam / per student). Uses the existing `answers.is_correct`/`answers.score`/`attempts.score` columns (Phase-1 schema).

**Out of scope (later):** the demo seeder + README (Phase 6). No new grading dimensions beyond MCQ-auto + open-text-manual (no partial-credit MCQ, no rubrics, no analytics — those are v2). This is the last feature phase; after it the app is functionally complete.
</domain>

<decisions>
## Implementation Decisions

*(Auto mode: recommended, research/prior-phase-grounded defaults.)*

### MCQ auto-grading on submission (GRD-01)
- **D-01:** An `AttemptGrader` service (`app/Services/AttemptGrader.php`) with a method (e.g. `gradeAutoGradable(Attempt)`) that, for each MCQ answer, sets `answers.is_correct = (selected_option_id === the question's correct option id)` and `answers.score = is_correct ? question.points : 0`. Unanswered MCQ (no answer row, or null selection) → treated as incorrect / 0, never a crash (research pitfall). Open-text answers are left ungraded (`is_correct`/`score` stay null) for the lecturer.
- **D-02:** Hook the grader into the **Phase-4 finalize path** so MCQ is graded "on submission" (GRD-01): call `AttemptGrader::gradeAutoGradable($attempt)` inside/right after `Attempt::finalize()`/`finalizeIfExpired()` becomes `submitted` (both the manual-submit and lazy-auto-submit paths — the single finalize chokepoint from Phase 4 means one hook covers both). Keep it inside the finalize transaction or immediately after, idempotently.

### Status transition submitted → graded (GRD-03)
- **D-03:** After auto-grading, evaluate completeness: if the attempt has **no open-text answers needing grading** (all questions MCQ, or all open-text already graded), transition `status = submitted → graded` and set `attempts.score = Σ answers.score`. If open-text answers remain ungraded, stay `submitted` (result withheld). The transition to `graded` also fires when the lecturer grades the last open-text answer (D-04).

### Lecturer manual grading (GRD-02)
- **D-04:** A lecturer grading UI (under `routes/lecturer.php`, role:lecturer): list submitted attempts (per exam), open one, and grade each **open-text** answer with a score input constrained to `0..question.points`. A `GradeAnswerRequest` validates the score is an integer/decimal in `[0, points]` and the target answer is an open-text answer belonging to the attempt. Saving a grade sets `answers.score` (and `is_correct` may stay null for open-text, or be derived — keep null; score is authoritative). After each save, re-evaluate completeness (D-03): when all open-text answers are graded, flip the attempt to `graded` and compute `attempts.score`.

### Results views (GRD-04, GRD-05)
- **D-05:** **Student result** (`Student\ResultController` / extend the attempts area, role:student, own attempt via AttemptPolicy): shown ONLY when `attempt.status === graded` (GRD-03) — otherwise a "your submission is awaiting grading" message. Displays total score (`attempts.score` out of the exam's total points) and a **per-question breakdown**: each question, the student's answer, correct/incorrect (for MCQ) or the lecturer's score (for open-text), and points awarded.
- **D-06:** **Lecturer results** (`Lecturer\ResultController`, role:lecturer): per exam, a list of student attempts with status + total score; drill into a student's attempt to see the per-question breakdown (and reach the grading UI for ungraded open-text).

### Post-submit answer visibility
- **D-07:** The Phase-4 no-leakage rule applied only DURING taking. In results (exam over), showing the student's own answer + correct/incorrect + awarded score is expected (GRD-04). Keep it minimal: reveal the student's answer and its correctness/score, but do NOT gratuitously expose the full MCQ answer key (the specific correct option text) in the student result, to avoid leaking keys for reused exams — showing a ✓/✗ + score per question satisfies the breakdown requirement without publishing the key.

### Score computation
- **D-08:** `attempts.score` = sum of `answers.score` across all the attempt's answers (MCQ auto + open-text manual); unanswered questions contribute 0. The exam's total possible = Σ `questions.points`. Compute the stored `attempts.score` at the graded transition (recomputable if a grade changes).

### Claude's Discretion
- Exact controller/route names, whether AttemptGrader is a class or invokable, the grading-UI layout, and whether `is_correct` is set for open-text — planner/executor choice, provided D-01..D-08 hold.
</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase scope & requirements
- `.planning/ROADMAP.md` §"Phase 5" — goal + success criteria; GRD-01..05 (**UI hint: yes**)
- `.planning/REQUIREMENTS.md` — GRD-01..05 text
- `.planning/research/SUMMARY.md` §"Phase 7 (Grading & results)" — AttemptGrader service, gate results until open-text graded, auto-grading edge-case matrix
- `.planning/research/PITFALLS.md` — auto-grading edge cases (unanswered, type-branching); showing a partial score as final (UX pitfall)
- `.planning/research/ARCHITECTURE.md` — AttemptGrader at the finalize transition (not a model observer)

### Existing surface to build on (Phases 1–4)
- `app/Models/Attempt.php` (`finalize`/`finalizeIfExpired` chokepoint to hook grading into; `status`, `score`), `app/Models/Answer.php` (`is_correct`, `score`, `selected_option_id`, `answer_text`), `app/Models/Question.php` (`type`, `points`), `app/Models/Option.php` (`is_correct`)
- `app/Http/Controllers/Student/AttemptController.php` + `app/Policies/AttemptPolicy.php` (own-attempt gate to reuse for results)
- `app/Http/Controllers/Lecturer/` + `routes/lecturer.php` (grading + lecturer results live here)
- `resources/views/student/attempts/`, `resources/views/lecturer/exams/` — analog views
</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **Schema is ready** (Phase 1): `answers.is_correct`, `answers.score`, `attempts.score` columns exist — no migration needed.
- **The Phase-4 finalize chokepoint** (`Attempt::finalize`/`finalizeIfExpired` → status=submitted) — the single hook point for auto-grading (D-02).
- **AttemptPolicy** (Phase 4) — reuse own-attempt gate for the student result view.
- **role:lecturer / role:student route groups + Form Request pattern** — grading + results controllers.
- **Breeze Blade/Tailwind/Alpine** — grading + results views (UI-SPEC this phase).

### Established Patterns
- Server-side authoritative writes; Form Requests never accept privileged fields (score comes validated 0..points, is_correct derived server-side).
- Single shared predicate / single chokepoint (Phases 3/4) — keep grading logic in one AttemptGrader service, not scattered.

### Integration Points
- `Attempt::finalize()`/`finalizeIfExpired()` → call AttemptGrader.
- New grading + results routes in `routes/lecturer.php` and `routes/student.php`.
- Lecturer exam results link from the lecturer exam page.
</code_context>

<specifics>
## Specific Ideas

- MCQ auto-graded at finalize; open-text lecturer-graded; result gated until ALL open-text graded (D-03).
- Unanswered / missing answers score 0, never crash (research pitfall).
- Do not show a partial/pre-grading score as if final (UX pitfall) — withhold until `graded`.
</specifics>

<deferred>
## Deferred Ideas

- **Demo seeder (lecturer/students/classes/subjects/sample exam) + README** → Phase 6.
- **Partial-credit MCQ, rubrics, multi-select scoring, exam analytics/statistics** → v2 (out of scope).
- **Regrade history / audit** → not required.

None are user scope-creep — they are the phase boundaries, noted so the planner doesn't pull them forward.
</deferred>

---

*Phase: 5-Grading & Results*
*Context gathered: 2026-07-16*

# Phase 4: Attempt-Taking - Context

**Gathered:** 2026-07-16
**Status:** Ready for planning

<domain>
## Phase Boundary

The correctness-critical core: a student takes a time-limited exam and their answers are captured reliably, once, without leaking correct answers. A student starts an assigned exam (creating a single timed attempt), answers MCQ + open-text questions with those answers auto-saved as they go, sees a live countdown driven by a server deadline, and the attempt auto-submits when time expires — with all timing enforced server-side. Covers TAK-01..06.

**In scope:** starting an attempt (single-attempt integrity), the take-exam page (questions + Alpine countdown), per-answer autosave, server-side deadline enforcement on every write, auto-submit on expiry, explicit submit, resume-on-reload, an `AttemptPolicy` (own attempt + exam takeable), and NOT leaking `is_correct` to the student.

**Out of scope (later phases):** grading — the `AttemptGrader` service, MCQ auto-scoring, lecturer manual grading of open-text, and any score/result display (Phase 5). This phase finalizes an attempt to `status = submitted` with answers captured but **ungraded** (`answers.is_correct` / `answers.score` stay null; `attempts.score` stays null). The post-submit page is a simple "submitted" confirmation — NOT a results/score page. Demo seeder + README are Phase 6.
</domain>

<decisions>
## Implementation Decisions

*(Auto mode: recommended, research/prior-phase-grounded defaults. This phase is the highest-risk — decisions favor server-authoritative correctness.)*

### Attempt lifecycle & single-attempt integrity (TAK-01, TAK-05)
- **D-01:** Starting an exam does `firstOrCreate` an `attempts` row keyed on `(exam_id, user_id)` — the Phase-1 **DB unique constraint** is the race-proof backstop; the app also checks status. New attempt: `started_at = now()`, `status = in_progress`. If an attempt already exists and is `in_progress` → **resume** it (same `started_at`). If already `submitted`/`graded` → **blocked** (cannot retake; show "already submitted", link to Phase-5 results later). Wrap the start in a transaction / handle the unique-violation gracefully.
- **D-02:** `attempts.status` (the existing column, default `in_progress`) is the lifecycle: `in_progress` → `submitted` (this phase) → `graded` (Phase 5). Do not add columns (schema is fixed from Phase 1).

### Server-authoritative timer (TAK-02) — the crux
- **D-03:** The deadline is computed server-side: `deadline = attempts.started_at + exams.duration_minutes`. NEVER trust any client-supplied time/duration/remaining value. The server passes `remaining_seconds` (computed) to the take page for display only.
- **D-04:** EVERY write path (each answer autosave AND submit) re-checks `now() >= deadline` server-side. A write arriving after the deadline is **rejected** (422/redirect) — the answer is not persisted. This is what makes the client countdown cosmetic and the server the sole timing authority.

### Auto-submit on expiry (TAK-04)
- **D-05:** Two-layer: (1) the client Alpine countdown auto-submits (POSTs the submit) when it reaches 0; (2) **server backstop — lazy finalization**: any touch of an `in_progress` attempt whose deadline has passed finalizes it (`status = submitted`, `submitted_at = deadline`) and refuses further answer changes. No cron/queue (per research: lazy on-touch is sufficient at this scale). An abandoned expired attempt is thus effectively auto-submitted the next time it (or Phase-5 results) is accessed.

### Answer autosave (TAK-03)
- **D-06:** Each answer is persisted the moment it changes — an AJAX POST per answer (`question_id` + `selected_option_id` for MCQ, or `answer_text` for open) doing `updateOrCreate` on `answers` (Phase-1 unique `(attempt_id, question_id)`). Alpine posts on change/blur. On reload, the take page **rehydrates** existing answers so nothing is lost (refresh/disconnect safe). Autosave writes are subject to the D-04 deadline check.

### No answer leakage (TAK-06)
- **D-07:** The take-exam page renders each MCQ's option **bodies** but MUST NOT expose `options.is_correct` in HTML, JSON, or any embedded data. Enforce by an explicit select/whitelist (or `$hidden` on `Option::is_correct` for the student render / a dedicated view-model) — never eager-load the raw model with `is_correct` into the Blade/Alpine data. Grading (which reads `is_correct`) is entirely server-side in Phase 5.

### Access control (RBAC-05 attempt clause)
- **D-08:** An `AttemptPolicy` extending the Phase-3 authorization pattern: a student may take/answer/submit only their **own** attempt (`attempt.user_id === auth()->id()`) AND only when the exam is takeable (reuse `Exam::scopeVisibleTo` / `ExamPolicy::takeable` — published + assigned to their classroom). Enforce via `$this->authorize()` on every attempt route → 403 for another student's attempt or an out-of-class exam. No IDOR on attempts.

### Take UI (UI-SPEC warranted)
- **D-09:** Student take-exam page: questions listed (MCQ as radio groups, open-text as textarea), a prominent **Alpine countdown** (from server `remaining_seconds`, auto-submits at 0), autosave indicator, and a Submit button. Post-submit → a simple **confirmation** page ("Your exam has been submitted") — NOT a score page (results are Phase 5). This phase HAS a UI hint — generate a UI-SPEC for the take interface (countdown, question rendering, autosave/submit states).
- **D-10:** Wire the Phase-3 "Start" seam: the previously-disabled Start button on the student exam landing becomes the active `POST` that starts/resumes the attempt and redirects to the take page.

### Claude's Discretion
- Exact route/controller names (e.g. `Student\AttemptController` with start/show/answer/submit), whether autosave is one endpoint or per-type, countdown component structure, and the confirmation page copy — planner/executor choice, provided D-01..D-10 hold.
</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase scope & requirements
- `.planning/ROADMAP.md` §"Phase 4" — goal + success criteria; TAK-01..06 (has **UI hint: yes**)
- `.planning/REQUIREMENTS.md` — TAK-01..06 text
- `.planning/research/PITFALLS.md` — CWE-602 client-timer bypass, race on submit, lost-answers-on-timeout, answer leakage (ALL converge here)
- `.planning/research/ARCHITECTURE.md` — server-anchored timer (`started_at` + `duration_minutes`), transactional submit, attempt state machine
- `.planning/research/SUMMARY.md` §"Research Flags" — Phase 6 (attempt-taking) flagged as needing deep research

### Existing surface to build on (Phases 1–3)
- `app/Models/Exam.php` (`scopeVisibleTo`, `duration_minutes`, `is_published`), `app/Models/Attempt.php`, `app/Models/Answer.php`, `app/Models/Question.php`, `app/Models/Option.php` — the schema/relationships (attempts: started_at/submitted_at/status/score; answers: selected_option_id/answer_text/is_correct/score)
- `app/Policies/ExamPolicy.php` + `ExamController::show` — the Phase-3 authz pattern to extend with `AttemptPolicy`
- `app/Http/Controllers/Student/ExamController.php`, `resources/views/student/exams/show.blade.php` — the landing page with the disabled "Start" seam (D-10 activates it)
- `routes/student.php` — the `role:student` group to add attempt routes to
- Phase-2 MCQ authoring / `_form` Alpine patterns — analog for the take-page Alpine
</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`attempts`/`answers` tables + models** (Phase 1) with the unique constraints — the integrity backstop. `attempts.status` default `in_progress`.
- **`Exam::scopeVisibleTo` + `ExamPolicy`** (Phase 3) — reuse for the "exam takeable" half of `AttemptPolicy`.
- **`Student\ExamController` + landing page** (Phase 3) — the Start seam to activate.
- **Alpine.js** (used for MCQ authoring rows in Phase 2) — analog for the countdown + autosave.
- **Breeze Blade/Tailwind** — take page + confirmation page.

### Established Patterns
- Server-authoritative security (Phase 1/3): never trust client input for authorization OR (now) timing.
- Single shared predicate (Phase 3): reuse `visibleTo`/`takeable` in `AttemptPolicy` rather than re-deriving.
- Transactional writes (Phase 2 option persistence) — apply to attempt start (unique-race) and submit.

### Integration Points
- New attempt routes in `routes/student.php`; `AttemptPolicy` auto-discovered (`App\Policies\AttemptPolicy`).
- Start seam in `resources/views/student/exams/show.blade.php`.
- Autosave endpoint(s) consumed by the take-page Alpine.
</code_context>

<specifics>
## Specific Ideas

- The server is the SOLE timing authority — client countdown is display only; every write revalidates the deadline (CWE-602).
- Answers persist incrementally (autosave) so timeout/refresh never loses work; auto-submit just flips status on already-saved rows.
- `is_correct` NEVER reaches the student browser.
- Submit finalizes to `status = submitted`, ungraded — grading is Phase 5.
</specifics>

<deferred>
## Deferred Ideas

- **Grading — `AttemptGrader`, MCQ auto-score, lecturer manual grading, results/score display (GRD-01..05)** → Phase 5. This phase leaves `is_correct`/`score` null and shows only a submitted-confirmation (no score).
- **Randomized question/option order, multi-select MCQ** → v2.
- **Scheduled auto-submit sweep (cron/queue)** → out of scope; lazy on-touch finalization is sufficient (research).
- **Demo seeder + README** → Phase 6.

None are user scope-creep — they are the phase boundaries, noted so the planner doesn't pull them forward.
</deferred>

---

*Phase: 4-Attempt-Taking*
*Context gathered: 2026-07-16*

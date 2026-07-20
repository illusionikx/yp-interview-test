# Phase 8: v2.0 Features — Enrollment, Exam Availability & User Manuals - Context

**Gathered:** 2026-07-16
**Status:** Ready for planning
**Mode:** Smart discuss (autonomous) — 4 grey areas proposed, 3 accepted as recommended, 1 overridden by user

<domain>
## Phase Boundary

The user-facing feature layer built on Phase 7's foundation. This phase delivers:

1. **Student self-enrollment** — browse a subject's sections with live capacity and enrollment-window status, apply to an open non-full section (immediate, no approval), withdraw before close, and re-apply after withdrawal/rejection while the window is open.
2. **Lecturer rejection** — any lecturer assigned to the subject can reject an enrolled student with a reason from a fixed dropdown; the rejected student sees that reason.
3. **Exam availability** — an optional start/end availability window on an exam, a pre-start details page, window-gated attempt start, and in-progress safeguards (a started attempt runs to completion on its own timer; a browser warning on tab-close/navigate-away).
4. **User manuals** — non-technical student and lecturer manuals documenting the shipped v2.0 UI and flows.

Requirements: ENR-01..ENR-07, AVL-01..AVL-05, DEL-04, DEL-05.

**Out of boundary:** the schema, sections, subject↔lecturer assignment, exam-visibility predicate, admin theme, and dark mode — all shipped in Phase 7.

</domain>

<decisions>
## Implementation Decisions

### Enrollment Browse & Apply
- Student nav **"Enroll"** (deferred from Phase 7) routes to a subject list → that subject's sections page — mirrors the lecturer's subject→section nesting. (`student.subjects.index` → `student.subjects.show`; exact route naming at Claude's discretion.)
- Capacity rendered as text `28/30` plus the amber **FULL** pill at capacity — reuses Phase 7's locked status-pill palette.
- Applying is a **direct POST + flash confirmation — no confirm modal**: enrollment is immediate with no approval step (locked v2.0 decision).
- A student's own enrollment state shows **inline on the sections page** (green **Enrolled** pill + Withdraw action). **No separate "My Enrollments" page** — keeps the surface minimal.

### Withdraw & Rejection
- **Withdraw is behind a confirm modal** — it is consequential and matches the UI-SPEC destructive-confirmation convention.
- **Fixed rejection-reason dropdown (5 values, server-validated enum):** `Not eligible for subject` · `Prerequisite not met` · `Duplicate enrollment` · `Section changed` · `Other`.
  *(Authoritative source: REQUIREMENTS.md "Resolved Design Decisions (v2.0)" #1 — a user-reviewed decision. Smart discuss initially proposed different wording; the user resolved the conflict in favour of the REQUIREMENTS.md set.)*
- A rejected student sees the red **Rejected** pill **plus the reason text inline** on the sections page.
- The lecturer rejects from a **new section roster page** (`lecturer.sections.show`) listing that section's enrolled students with a Reject action.

### Exam Availability & Pre-Start
- The lecturer sets optional **`available_from` / `available_until`** datetime fields on the **existing exam create/edit form**; empty = unbounded on that side. Columns are added by **editing the v1 `create_exams_table` migration in place** (v2.0 clean-break decision — no alter migration).
- **The window is set while the exam is a DRAFT.** The existing Phase 2 draft-only edit gate (D-06, `UpdateExamRequest::authorize()`) stands — **no exception is carved for published exams** (user decision). A lecturer sets the window before publishing.
- The **pre-start details page reuses the existing `student.exams.show`** route/view (reskinned in 07-06) rather than adding a new route — enhanced to show instructions, duration, the availability window, the student's enrolled section, and **Proceed / Back** actions.
- Availability state on the student exam list uses the locked pill palette: green **Available** · gray **Opens {date}** · red **Closed**.
- In-progress safeguard: a **`beforeunload` listener while the attempt is `in_progress`**, **detached on intentional submit and on the timer's own auto-submit** so neither path triggers the warning.

### User Manuals — *(user override: in-app, not Markdown)*
- The manuals ship as **in-app help pages (Blade), rendered inside the Flowbite shell** — **not** Markdown files in `docs/`. Role-scoped: a student manual and a lecturer manual, reachable from the top navbar.
- **Text-only** — refer to UI elements by their visible label; **no screenshots** (they rot and add no value in a graded repo).
- **Task-based walkthroughs** mirroring the success-criteria flows, not a reference-style feature list.
- **Written last**, after all Phase 8 UI ships, so they describe the real screens (per STATE.md sequencing guidance).

### Inherited Resolved Decisions — REQUIREMENTS.md "Resolved Design Decisions (v2.0)" (AUTHORITATIVE)

These were user-reviewed at milestone definition and are **binding on this phase's plans**:

- **#6 Window boundary semantics:** half-open — `[opens_at, closes_at)` and `[available_from, available_until)`. Allowed while `open ≤ now < close`; a **null bound is unbounded** on that side. **Must be tested at the exact boundary.** (SEC-02, AVL-01/03)
- **#7 Post-start attempt access is decoupled from live enrollment:** attempts are **ownership-gated** (like results), **not** visibility-gated; the enrollment/availability check applies **at start only**. (AVL-04/05, ENR-08)
  → **Consequence (research finding, must fix first):** `AttemptPolicy::view()` / `update()` currently derive access from `Exam::visibleTo()`, which is enrollment-status-driven. Once withdraw/reject exist, an in-progress attempt would start 403'ing mid-exam. Both must become ownership-only, matching the existing `viewResult()` precedent in the same file.
- **#3** At most one **active** enrollment per subject per semester. **#4** Withdrawn **and** rejected students may re-apply while the window is open. **#5** Out-of-window sections stay listed with a status label, never hidden.
- **Availability gating must NOT enter `Exam::scopeVisibleTo()`** (research): it is a narrow additive check (e.g. `Exam::isAvailableNow()`) applied at exactly one call site — `AttemptController@store`, new-attempt branch only. An already-started attempt stays resumable regardless of window state.

### Claude's Discretion
- Exact help-page route names and nav placement (top-bar item vs. dropdown entry), the manual copy itself, and controller/view file organization for the help pages.
- Whether the README also points at the in-app help.
- Exact route naming for the student subject/section browse pages, and card/table layout within the locked Flowbite + pill vocabulary.

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Enums/EnrollmentStatus.php` — `Enrolled` / `Withdrawn` / `Rejected` (Phase 7).
- `enrollments` table already carries **`rejection_reason`** (nullable string) and `unique(section_id, user_id)` — no migration needed for the reason.
- `sections` already has `capacity`, `opens_at`, `closes_at` (all non-nullable).
- `x-status-pill` Blade component (Phase 7) implementing the locked palette.
- Flowbite top-navbar shell + class-based dark mode with pre-paint script (Phase 7).
- `Exam::scopeVisibleTo()` — the single enrollment-driven visibility predicate (ENR-08). **Availability gating must compose with it, never duplicate or fork it.**
- `student.exams.show` route + view already exist (reskinned 07-06) → becomes the pre-start page.
- `Section` model + `SectionFactory`; `SectionController` / `SubjectLecturerController` carry the SEC-03 per-subject ownership pattern to mirror for reject authorization.

### Established Patterns
- One Form Request per write action; `authorize()` carries ownership. SEC-03 per-subject lecturer ownership: `$subject->lecturers()->whereKey($this->user()->id)->exists()` — **do not** fall back to the old D-09 `return true;`.
- Role-gated route groups (`role:student` / `role:lecturer`) for area access; Policies for record-level checks.
- Server-authoritative enforcement on every write (attempt-timer precedent); Alpine only for client UX.
- Concurrency: Phase 7's section-sequence fix established a transaction + locking-read idiom — **the same approach applies to capacity-safe apply**.

### Integration Points
- `routes/student.php` — subject/section browse, enroll, withdraw, help page.
- `routes/lecturer.php` — section roster, reject, help page.
- `resources/views/layouts/navigation.blade.php` — the Phase 7-deferred student **"Enroll"** item plus a help entry (both roles).
- `database/migrations/*_create_exams_table.php` — availability columns, edited in place.
- `app/Models/Exam.php` / `ExamPolicy` — availability gate composing with `scopeVisibleTo()`.

</code_context>

<specifics>
## Specific Ideas

- **Capacity race is a hard requirement:** concurrent applies to a nearly-full section must never exceed capacity — enforce server-side atomically (transaction + locking read, mirroring Phase 7's sequence fix), not with a plain count-then-insert.
- **Window enforcement must be a server-side refusal with a clear message**, not merely a hidden button — an out-of-window apply and an out-of-window attempt start both refuse explicitly.
- **A started attempt runs to completion on its own timer** — a later withdrawal, rejection, or the availability window closing must never cut it short. This composes with `Attempt::finalizeIfExpired()`, which stays the sole finalization authority.
- **One active enrollment per subject per semester** (locked v2.0 decision); withdrawn **and** rejected students may re-apply while the window is open.
- Manuals must reflect the **shipped** v2.0 UI — hence written last in the phase.

</specifics>

<deferred>
## Deferred Ideas

- Aggregate lecturer **"Results"** landing route — flagged as an intentional deferral in Phase 7 (07-05); per-exam results stay reachable from each exam's page. Still out of scope.
- `.planning/v3.md` — the user's own v3 feature notes (Flowbite 4.0 login-page restyle, etc.). Out of scope for v2.0; belongs to a future milestone.

</deferred>

---

*Phase: 08-v2-0-features-enrollment-exam-availability-user-manuals*
*Context gathered: 2026-07-16 via smart discuss (autonomous)*

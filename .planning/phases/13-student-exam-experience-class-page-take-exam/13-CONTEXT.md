# Phase 13: Student Exam Experience — Class Page & Take Exam - Context

**Gathered:** 2026-07-18
**Status:** Ready for planning
**Mode:** Smart discuss (autonomous, AFK-accepted) — grounded in `.planning/v3.md` (§Class, §Take Exam), the ROADMAP Phase 13 notes, and a scout of the already-shipped take-exam code. Reviewable/overridable.

<domain>
## Phase Boundary

A student finds the right exam from their **class page** and **takes it** knowing how much time is left, how far along they are, and what the instructions say. This is **presentation over already-shipped backend** — the server-authoritative timer (`attempts.expires_at`), the once-per-class single-attempt constraint, MCQ auto-grade, and per-question autosave all stay UNTOUCHED underneath the restyle.

**In scope:** TAK-07 (class page: subject detail + exam-list card with status + taken/graded link), TAK-08 (start-once, disabled afterward), TAK-09 (sticky top bar + instructions popup), TAK-10 (vertical stepper with reload-surviving checkmarks), TAK-11 (10-min red timer + one-shot toaster), TAK-12 (authored option order, never randomized).

**Out of scope:** dark-mode sweep, wiki manual + help button, demo seeding, Dusk browser tests (Phase 14).
</domain>

<decisions>
## Implementation Decisions

### Class page (TAK-07, TAK-08)
- The student **class page** — reached from the Phase 11 student subject list's "class page" action — shows the **subject's detail** plus an **exam list in its own card**.
- Each exam row shows a **status marker** via the existing `Exam::availabilityState()` + `<x-status-pill>`: not-yet-open ("opening") / opened ("available") / closed. Plus a **taken/graded** indicator.
- **taken/graded is a LINK to the result**, not a bare label (ROADMAP note — v2.0's sharpest defect was a tested-but-unreachable result page). Link to `student.attempts.result` / the submitted view.
- **TAK-08 start-once-per-class:** the Start button is **disabled** once the student has an attempt for that exam, blocking a second trip to the start page. Enforcement stays server-side (existing single-attempt constraint); the disabled button is the UX mirror.

### Take-exam page (TAK-09, TAK-10, TAK-11, TAK-12)
- **TAK-09 sticky top bar** carrying: subject name + exam/test name, the timer, and question progress (answered/total), plus a **button that opens the exam instructions in a popup** (reuse the app's single modal style — `<x-confirm-modal>`/`x-modal`, never native `alert`). **Exam details and instructions read as separate things** (details in the header/body, instructions behind the popup button).
- **TAK-10 vertical stepper**: question navigation as a vertical stepper that **checkmarks answered questions**, with each question showing its number. **Checkmarks derive from the server-persisted answers** the autosave already writes (the existing `$answeredQuestionIds` → `attemptTimer.answered` seed), NEVER from client-only Alpine state — so they **survive a mid-exam page reload** (criterion 4 exists to catch exactly that). The stepper reads the same reactive `answered` map the answered-count already uses; the autosave `question-answered` event keeps it live.
- **TAK-11**: at **10 minutes remaining** the timer turns **red** AND a **toaster appears exactly once** — a one-shot Alpine watcher that flips a fired-once flag (mirroring the FIX-01 reactive-counter precedent), NEVER on every timer tick. Reuse `<x-toast>` styling / the app toast convention.
- **TAK-12**: answer options always render in their **authored arrangement** (`orderBy('position')`, already the default on `Question::options()` since Phase 12) — **no runtime randomization, ever** (student-side face of Decision #2).
- **Server-authoritative timer stays untouched**: `expires_at` is passed as remaining-seconds/epoch to the client, which counts down purely client-side (`setInterval`) and never extends time; every write path re-checks server-side. Do NOT weaken this.

### Claude's Discretion
- Exact stepper placement (left rail) and styling; how the top bar collapses on mobile.
- Whether the class page is a new route/controller method or an enhancement of an existing student subject/exam-list view — pick the one that reuses the most and keeps routing coherent with Phase 11's "class page" link target.
- Instructions-popup trigger styling.
</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets (SHIPPED — restyle/enhance, do not rebuild)
- `Student\AttemptController` — `store` (single-attempt-gated start), `show` (take page data), `answer` (JSON autosave), `submit`, `submitted`. Backend complete.
- `resources/views/student/attempts/show.blade.php` — the take-exam page. Already has an `attemptTimer(remaining, submitUrl, submittedUrl, answeredQuestionIds)` Alpine component: `answered` map seeded from **server-persisted** `$answeredQuestionIds`, `markAnswered()` on the `question-answered` autosave event, `answeredCount` getter, `setInterval` tick, auto-submit at zero. TAK-10's reload-surviving checkmarks and TAK-09's progress build directly on this.
- `resources/views/student/exams/show.blade.php` — already uses `Exam::availabilityState()` + `<x-status-pill>` + an in-progress-attempt check + the Start form.
- `Exam::availabilityState()` → 'opening'|'available'|'closed'; `<x-status-pill>` renders it. `Question::options()`/`Exam::questions()` default `orderBy('position')` (Phase 12).
- Phase 9 `<x-toast>` (TAK-11), the single modal style (TAK-09 instructions popup), Phase 11 `<x-back-button>`.

### Established Patterns
- Server-authoritative timer with client countdown from an absolute/remaining value (CLAUDE.md §2). One-shot reactive watcher precedent: FIX-01.
- Blade + Tailwind 3 + Alpine, no SPA; `<x-toast>` single flash renderer; NO new packages.
- Tests: PHPUnit Feature tests under tests/Feature/Student (attempt lifecycle, availability, visibility).

### Integration Points
- Phase 11 student subject list "class page" action → this phase's class page (wire the link target).
- Class page exam rows → Start (disabled if attempted) / take page / result link.
- Take page: sticky top-bar partial, vertical stepper partial, instructions popup, 10-min toaster watcher — all over the existing `attemptTimer` component.
</code_context>

<specifics>
## Specific Ideas
- v3.md §Class (subject detail, exam list card, status markers, taken/graded) and §Take Exam (sticky top bar with title/timer/progress, instructions popup button, vertical stepper with checkmarks, 10-min red timer + toaster, question number on left, no answer randomization) are the layout source of truth.
- Decision #2 (no runtime randomization) is LOCKED — TAK-12 is its student-side face.
- Core value: "the clock is enforced and the submission is saved and graded" — nothing in this restyle may weaken the server-side enforcement.
</specifics>

<deferred>
## Deferred Ideas
- Dark-mode compatibility sweep across the take-exam page and everywhere else, wiki manual + help button, demo seeding (past graded exams, filled classes), Dusk browser tests → Phase 14.
</deferred>
</content>

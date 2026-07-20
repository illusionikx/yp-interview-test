# Feature Research

**Domain:** Academic exam/LMS portal — v3.0 workflow restructure & UX polish (subsequent milestone)
**Researched:** 2026-07-17
**Confidence:** HIGH (semester modeling, quiz-editing conventions, IA restructure — all grounded in existing schema + established LMS patterns); MEDIUM (exact "other relevant stats" choices, wiki-manual depth — these are judgment calls, not domain law)

## Context: what already exists (do not re-propose)

v2.0 shipped: role-gated Lecturer/Student areas, subject/exam authoring (MCQ + open-text), publish/draft, subject-scoped `sections` (fields already on the model: `year` int, `semester` int, `sequence` int, capacity, enrollment window — code format `{year}-{semester}-{sequence}`, e.g. `2026-2-1`), student self-enrollment (apply/withdraw/re-apply), lecturer rejection with fixed-reason dropdown, exam availability windows + pre-start page, timed attempts (server-authoritative timer, autosave, single attempt), auto-grade MCQ + manual grade open-text, results, Flowbite top-navbar shell + dark mode, in-app **linear** user manuals.

Critically: **`Section` already stores `year` and `semester` as plain integers.** v3's "semester model" is not a new entity — it's a date-derivation layer on top of columns that already exist.

---

## Feature Landscape

### 1. Semester model — derive, do not store

**Recommendation: a stateless value object / helper (e.g. `App\Support\Semester` or a method on `Section`), not a `semesters` database table.**

This mirrors how academic date ranges are conventionally modeled: a semester's calendar boundaries are a deterministic function of `(year, semester_number)`, not independent facts that need their own row. Storing computed `starts_at`/`ends_at` columns would create a second source of truth that can drift from the `(year, semester)` pair already on every `Section` — classic derived-attribute anti-pattern (compute at read time from the base columns, the same way age is computed from date-of-birth rather than stored).

Concretely, given the user's fixed rule:
- **Semester 1** of academic-year `Y`: `Y-09-01` → `(Y+1)-02-{last day of Feb, leap-aware}`
- **Semester 2** of academic-year `Y`: `(Y+1)-03-01` → `(Y+1)-07-31`

A single pure function `semesterRange(int $year, int $semester): array{start: Carbon, end: Carbon}` covers both, and `currentSemester(?Carbon $now = null): array{year, semester}` walks "today" against those two ranges to find (or fail to find) a match. Laravel's `Carbon::createFromDate($y, $m, 1)->endOfMonth()` handles the leap-year edge for February for free — no manual leap logic needed.

**The August gap is real and must be handled explicitly, not glossed over.** Jul 31 (end of Sem 2) → Sep 1 (start of Sem 1 of the *next* academic year) leaves August with no semester covering it. This is not a bug in the user's rule — it's presumably intentional (a between-terms break) — but it means `currentSemester()` can return "none." Two behaviors are defensible and this is a genuine open question for requirement definition, not something research can resolve unilaterally:
- **(a) Null/"no active semester" during August** — dashboards show zero for "this semester" stats, which is technically correct but may look broken/empty for a whole month.
- **(b) Roll forward to the *next* semester** during the gap (August → treat upcoming Sem 1 as "current") — more useful for a lecturer/student looking ahead at what's about to start, and matches how most academic-calendar UIs behave (a "currently in session or next up" pattern, common in registrar/LMS dashboards).

Recommendation: **(b)** — treat "current semester" during any gap as "the next semester chronologically," since a lecturer/student in August cares about what's coming, not an empty state. Flag this as a decision to confirm at requirement-definition time (the user's spec doesn't say).

**"This semester and future semester" for dashboard stats** (v3.md: "total classes assigned for this semester and future semester") means: compute `(currentYear, currentSemesterNum)` per the rule above, then filter `Section`s (and anything hanging off them — enrollments, exam assignments) where the `(year, semester)` tuple is `>= (currentYear, currentSemesterNum)` under natural chronological ordering (`year * 2 + semester` sorts correctly since semester ∈ {1,2}). This is a single indexed/computed WHERE clause, not a join to a calendar table — cheap and consistent with "no new entity."

Complexity: **LOW** — one helper class/trait, a handful of unit tests for boundary dates (Feb 28/29, Jul 31→Aug 1, Aug 31→Sep 1) and the current/future comparison. No migration required.

Dependency: none new — reads existing `Section.year`/`Section.semester`. Every "grouped by semester," "hide/unhide past semester," and "this/future semester" stat in v3.md depends on this helper being built first — it is a shared primitive, build it before any dashboard/subject-list/class-management work.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Derived semester date-range helper | Every semester-aware UI (dashboard, subject list, class list) needs a single consistent definition of "current/past/future semester" | LOW | Pure function over existing `year`/`semester` columns; no new table |
| Semester ordering/comparison utility | "This and future semester" filters require chronological comparison across academic years | LOW | `year*2+semester` integer sort or equivalent tuple comparison |
| Explicit August-gap policy | Silently undefined behavior for a real calendar gap will produce inconsistent "current semester" results across pages if each page derives it ad hoc | LOW (once decided) | Recommend roll-forward-to-next; confirm with user before locking as a requirement |

---

### 2. Dashboard

Conventional LMS/exam-portal instructor dashboards center on: enrollment counts, capacity utilization, and pending-action counts (things needing the instructor's attention *now*). Student dashboards center on: what's enrolled, what's due/open, and recent results. The user's spec explicitly asks for a subset of this plus "other relevant stats" — the research question is what's genuinely useful given what this app *already stores* (not a generic LMS wish list).

**Lecturer cards (explicit in v3.md):**
- Total classes (sections) assigned, this + future semester
- Total students enrolled vs. total seats across assigned sections, with a progress bar

**Lecturer "other relevant stats" — grounded in existing data, ranked by usefulness:**
1. **Pending grading count** (open-text answers awaiting a score, across the lecturer's exams) — this is the single most actionable number for a lecturer; every LMS instructor dashboard surfaces a "needs attention" count because it drives what the user does next. Directly computable from existing `Attempt`/`Answer` grading state.
2. **Draft exams** count (exams not yet published/active) — a lightweight reminder of unfinished authoring work.
3. **Pending enrollment applications awaiting action** — if rejection is a manual per-student action (it is, per v2.0), a count of "students enrolled but not yet reviewed" is arguably more useful than a raw enrollment number, though v2.0's model treats enrollment as immediate/auto-accept with reject-after-the-fact, so this may not map cleanly — verify against the actual `Enrollment` states before committing to this as a card.
4. Upcoming exam availability windows opening/closing soon (a "next 7 days" glance) — nice-to-have, higher complexity (date-range query across all assigned sections' exams), defer unless trivial.

**Student card (explicit in v3.md):** total subjects enrolled this semester.

**Student "other relevant stats" — ranked:**
1. **Open/available exams right now** (exams whose availability window is currently open and not yet attempted) — this is the thing a student most needs to know at a glance; directly derivable from the existing `Exam::scopeVisibleTo()` + availability-window logic.
2. **Results awaiting release / recently graded** — "you have N results back" is a natural companion to "N exams open," and both use data v2.0 already tracks (attempt status).
3. Enrollment status summary (active / pending / rejected counts) — secondary, useful mainly right after the enrollment period, lower priority than the two above.

**Welcome banner with a gradient:** table stakes as specified — purely cosmetic, Tailwind gradient utility classes, zero backend dependency. Complexity LOW.

| Feature | Why Expected/Valuable | Complexity | Notes |
|---------|------------------------|------------|-------|
| Welcome banner (gradient) | Explicit in v3.md; standard "hero" pattern on portal home pages | LOW | Pure Tailwind, randomize gradient client- or server-side (e.g. hash user id → palette index) |
| Lecturer: classes assigned this+future semester | Explicit in v3.md | LOW | Depends on §1 semester helper |
| Lecturer: enrolled vs. seats progress bar | Explicit in v3.md | LOW | Aggregate `SUM(enrollments) / SUM(capacity)` across assigned sections; Flowbite progress-bar component already used elsewhere (v2.0 section rosters) |
| Lecturer: pending open-text grading count | Most actionable "needs your attention" signal available in this app's data model | LOW-MEDIUM | Count ungraded `Answer`s on essay questions across the lecturer's exams |
| Lecturer: draft exam count | Cheap authoring-progress reminder | LOW | Count where `status = draft` |
| Student: subjects enrolled this semester | Explicit in v3.md | LOW | Depends on §1 semester helper |
| Student: currently-open exams count | Most actionable "what do I do next" signal for a student | LOW-MEDIUM | Reuses existing availability-window + `scopeVisibleTo` logic |
| Student: results awaiting/recently graded | Natural companion metric, cheap to compute | LOW | Attempt status counts |

**Anti-features for dashboard:**

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|------------------|-------------|
| Historical trend charts (enrollment over time, grade distributions) | "Looks like a real analytics dashboard" | Out of proportion for a graded assessment deliverable with a handful of classes; adds charting dependency for data too sparse to be meaningful | Flat stat cards; defer charts entirely |
| Real-time/live-updating dashboard (polling or websockets) | Feels "modern" | No concurrent-viewing use case justifies it (per existing CLAUDE.md guidance: no broadcasting infra for this project) | Compute on page load; refresh on navigation |

---

### 3. Wiki-style user manual

v2.0 shipped **linear, task-based** manuals: fixed sequences of numbered steps per user flow ("Enroll in a section," "Grade an attempt"), reachable from a Help nav item, no cross-linking, no independent navigation — the reader is assumed to walk the whole page top to bottom.

**What "wiki style" conventionally means, in contrast:**
- **A persistent sidebar/index of topics**, not a single linear page — the reader picks an entry point rather than scrolling through everything.
- **Cross-links between topics** — a page on "Taking an Exam" links to "Enrollment" and "Understanding Your Results" rather than repeating or ignoring that context. Cross-linking is the defining property of a wiki (as opposed to a manual/tutorial) — content becomes a graph, not a sequence.
- **Non-sequential access** — a reader can land directly on any topic (e.g. via search or a direct link) without having read what came before, so each page must be reasonably self-contained.
- Optionally: a **search/filter** over topics — valuable but not definitional; the minimal honest wiki is a sidebar + cross-links, search can be a simple client-side filter over topic titles (Alpine `x-show` filter) rather than a real search index — a real search backend (Scout, Meilisearch) would be over-engineering for a help system with a few dozen pages.

**Minimal honest interpretation for this project:** restructure the existing linear manual content (already written, already verified against the shipped UI in v2.0 Phase 8) into **topic-sized Blade partials** presented behind a **two-pane layout** — a left sidebar listing topics (grouped by role or by area: Enrollment, Exams, Grading, Account) and a right content pane — with **inline links** between related topics where v2.0's linear pages already implicitly assumed sequence (e.g., the "Taking an Exam" topic links to "Enrollment" instead of restating it). This is primarily a *content reorganization + navigation shell* task, not new content — the underlying step-by-step instructions from v2.0 are still accurate and reusable, just chunked and cross-linked differently.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Sidebar topic index (role-scoped) | Defining feature that distinguishes "wiki" from "linear manual" | LOW-MEDIUM | Static Blade partials + a topic-list data structure (array or lightweight config), no DB table needed |
| Cross-links between related topics | Second defining property of "wiki style" | LOW | Manual `<a>` links inside content; no auto-linking needed at this scale |
| Client-side topic filter/search | Convenience, not definitional | LOW | Alpine `x-show`/`x-if` filter over the topic list; skip a real search index |
| Reuse v2.0's linear manual content, re-chunked | Content is already written and verified against real UI labels | LOW | Split existing pages into topic-sized partials rather than rewriting from scratch |

**Anti-feature:**

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|------------------|-------------|
| Full-text search backend (Scout/Meilisearch/Algolia) for the manual | "Wiki" implies searchable | New infra dependency for a help system with a few dozen short pages — the entire content fits in one page of results from a client-side substring filter | Alpine-based client-side title/keyword filter over a static topic list |
| User-editable wiki (in-app CMS for manual content) | "Wiki" implies editable-by-anyone | Nobody but the developer maintains this content; a real editable wiki needs revision history, permissions, etc. — total scope mismatch for a graded deliverable | Static Blade content, edited in the repo like any other view |

---

### 4. Landing page before login

Table stakes for any public-facing portal that currently falls back to the Laravel default welcome page — the default page actively undermines the "Online Examination Portal for Yayasan Peneraju Technical Assessment" branding the user explicitly wants (v3.md: official name + subtitle).

**Conventional table-stakes content for this class of product** (a small institutional portal, not a marketed SaaS):
- Product name + subtitle (as specified), a short one-line description of what the portal does.
- A clear primary call-to-action: **Login** (and, since public registration is intentionally locked to Student-only per existing Out-of-Scope decisions, a secondary **Register** CTA framed for students).
- Enough visual identity to not look like a scaffold default — a hero section, brand color/gradient (can reuse the same gradient treatment as the dashboard welcome banner for visual consistency), maybe 2-3 short feature/benefit blurbs (e.g., "Take exams," "Track results," "Manage classes").
- No login/registration form embedded on the landing page itself — that stays behind the existing Breeze routes; the landing page's job is purely to route the visitor to Login/Register.

**What would be over-engineering here:** marketing-site patterns (testimonials, pricing, animated feature carousels, video hero) — this is an internal assessment tool for one institution, not a product being sold; a clean single-scroll hero + CTA is sufficient and matches the "minimal, correct implementation" constraint already in CLAUDE.md.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Branded hero (name, subtitle, one-liner) | Replaces Laravel default; explicit ask | LOW | Static Blade view, new root `/` route (guest-only) |
| Login/Register CTAs | Only real job of a pre-auth landing page | LOW | Link to existing Breeze routes, no new auth logic |
| Light "what this does" content (3ish blurbs) | Standard for any product landing page, sets expectations before login | LOW | Static content, no backend |

**Anti-feature:**

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|------------------|-------------|
| Marketing-site richness (testimonials, animated carousels, pricing) | "Make it look professional" | Disproportionate for a single-institution internal tool; the assessment brief rewards minimal-correct, not marketing polish | Clean single hero section reusing the same design language as the rest of the app |

---

### 5. Take-exam UX

The v3.md spec (vertical stepper w/ checkmarks, sticky top bar with title/timer/progress, 10-minute toaster + red timer, instructions popup, fixed answer order) is a well-established pattern in exam/quiz-taking software (large-scale testing platforms, Google Forms quizzes with timers, corporate assessment tools) — none of it is exotic.

- **Vertical stepper with answered checkmarks** is conventional for longer, multi-question sequential flows, particularly in dashboard-style (non-marketing) products — exactly this app's context. A vertical layout (as opposed to horizontal) is the right call for exams with more than a handful of questions since it scales down the left rail without wrapping, and it's explicitly the recommended orientation for longer/complex workflows.
- **Sticky top bar with title/timer/progress** is standard for anything time-boxed — the timer must always be visible without scrolling; this is close to non-negotiable for a *time-limited* exam (the app's stated Core Value hinges on the clock being enforced and visible).
- **10-minute warning + red timer color escalation** matches the existing v2.0 pattern (already shipped: 300s/60s color escalation on countdown) — v3 adds a toaster notification at the 10-minute mark as an *additional* escalation step layered on the existing color-based one, not a replacement. This is a straightforward addition to the already-built Alpine countdown component.
- **Instructions popup reachable from the top bar** is a reasonable convenience — separates "what is this exam" (rules/instructions) from "what am I answering" (the questions), keeping the working view uncluttered while keeping instructions one click away. Common in formal test-taking software.
- **No answer randomization on the take side** (v3.md: "follow arrangement") is explicitly the *opposite* of a common anti-cheating pattern (shuffling options per student to reduce copying) — but the user has requested authored order be preserved and shuffling instead be an *authoring-time* toggle (see §6) that, if enabled, presumably still needs to shuffle consistently. This needs to be read carefully: v3.md's "shuffle/randomize" toggle lives in the **exam editor** (§6, applies to how the lecturer arranges options while authoring), while the **take-exam** instruction "don't randomize, follow arrangement" governs runtime behavior. These aren't contradictory once separated: the shuffle toggle (if the user means "shuffle per student at attempt time") would need to override "follow arrangement" — this ambiguity should be resolved as a single requirement, not two independently-interpreted ones. Recommend treating the editor's "shuffle" as **authoring-time reordering** (lecturer randomizes the option order once, then it's fixed) unless the user clarifies they want true per-student runtime shuffling — that reading is consistent with "take exam: follow arrangement" and is far simpler to implement (no per-attempt option-order snapshot needed).

**What would be over-engineering:** per-question individual timers (not requested, and conflicts with the existing single-attempt-level `expires_at` server timer architecture already built in v2.0/v1.0); auto-save-per-keystroke on essay answers (existing autosave is already per-answer on blur/interval, sufficient); a horizontal stepper (wrong orientation for exams with more than ~6 questions); animated question transitions (adds JS complexity with no functional value).

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Sticky top bar (title, timer, progress) | Time-boxed exam; timer must always be visible — core to the app's stated Core Value | LOW-MEDIUM | Extends existing Alpine countdown component + a small progress computation (answered/total) |
| Vertical stepper with answered checkmarks | Conventional for longer sequential flows in dashboard-style products | MEDIUM | New nav component; must read live answered-state from the same autosave data already tracked per question |
| 10-minute toaster + red timer | Additional escalation layered on the existing 300s/60s color-escalation countdown | LOW | One more threshold branch in the already-shipped Alpine timer; reuse the toast system being standardized app-wide (§ UI system) |
| Instructions popup from top bar | Keeps working view uncluttered; instructions one click away | LOW | Modal reusing exam's existing instructions/description field; same modal pattern used elsewhere for the "one popup/alert style" requirement |
| Fixed answer order at take-time | Explicit; simpler and cheaper than per-attempt shuffling | LOW | No schema change — read options in their stored `sort_order` (see §6) |

**Anti-features:**

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|------------------|-------------|
| Per-question individual timers | "More rigorous testing feel" | Conflicts with the existing single-attempt-level server-authoritative timer (`expires_at`); would require a parallel timing model | Keep the single whole-attempt timer; per-question pacing is a UX nicety at most (e.g. a soft suggested-time indicator), not a hard timer |
| True per-student runtime answer shuffling | "More secure against copying" | Directly contradicted by v3.md's explicit "don't randomize, follow arrangement" instruction for take-exam; would also require snapshotting per-attempt option order, adding real complexity | Authoring-time shuffle toggle only (lecturer randomizes the fixed order once) |
| Auto-advance to next question on answer | Common in some consumer quiz apps | Removes the user's ability to review/change answers within the same visit before navigating; conflicts with "vertical stepper for navigation" (implies user-driven navigation, not forced advance) | Stepper stays user-driven; answering doesn't force navigation |

---

### 6. Exam editor

**"Merge details and questions into one page but as two tabs"** — a standard pattern for object-with-children editors (the "settings" vs. "content" split seen in most CMS/quiz-builder editors) — avoids the extra round-trip of "save details, then navigate to manage questions" that the existing v2.0 editor likely has today. Complexity LOW-MEDIUM: mostly an Alpine tab-switch UI over the two existing forms, no new backend routes required beyond what already exists for exam-details-update and question CRUD.

**Arrangeable answers with a shuffle toggle:**
- "Arrangeable" (drag-to-reorder or up/down buttons) requires a persisted `sort_order` (or similar) column on `options` — currently options are almost certainly unordered/insertion-order. This is a real, if small, schema change.
- "Shuffle/randomize" as an authoring convenience (a button that randomizes the stored order once, distinct from the runtime "don't randomize" instruction in §5) is the simpler and more consistent reading, per the §5 discussion — flag as the assumed interpretation, confirm with user.
- Complexity: LOW-MEDIUM (one migration + a reorder UI, likely drag handles or up/down buttons — v3.md explicitly specifies up/down buttons for *questions*, so reusing the same up/down-button pattern for *options* rather than introducing a drag library is consistent and avoids adding SortableJS/similar as a new frontend dependency).

**Question reorder (up/down buttons, question number on the left):** same pattern, same `sort_order`-on-`questions` need if not already present. Explicitly specified as up/down buttons, not drag-and-drop — keep it that simple; no drag library needed.

**"Saving cancels prior attempts, warn if any student has attempted"** — this is where real quiz-builder products (Moodle chief among them) converge on the same rule for the same reason: **editing a quiz that has live attempts changes the fairness contract mid-flight** — a student who already saw the old version could be advantaged or disadvantaged relative to one who sees the new version. Moodle's actual behavior is more granular (it restricts structural edits like add/remove/reorder questions once *any* attempt exists, and separately warns about editing question content that's "in use"), but the user's request here — a blunter, simpler rule: **any save invalidates all attempts on that exam, with a confirmation warning if attempts exist** — is a defensible, *simpler* choice for this project's scale, consistent with "exam versioning omitted, too complex" already stated in v3.md. It trades some nuance (you can't tweak a typo without wiping attempts) for a rule that's trivial to reason about and impossible to get subtly wrong.

Concretely this needs:
1. A pre-save check: does this exam have any non-empty `Attempt` rows?
2. If yes, block the save behind a confirmation step (the "one popup/alert style" modal, not a native `confirm()`) that names the consequence explicitly ("N students have attempted this exam — saving will cancel and delete their attempts").
3. On confirmed save: the existing attempts need a defined disposition — the user's wording ("cancelled") suggests soft-invalidation (mark attempts as void/cancelled, e.g. a status value or a `cancelled_at` timestamp) rather than hard-deleting attempt/answer rows, so grading history/audit isn't silently destroyed. This is a decision worth flagging precisely because v3.md says "cancelled," which is closer to "voided" than "deleted" — recommend a soft status transition, not a `DELETE`.

| Feature | Why Expected/Valuable | Complexity | Notes |
|---------|------------------------|------------|-------|
| Details+questions as two tabs, one page | Standard object-editor pattern; removes an extra save/navigate round-trip | LOW-MEDIUM | Alpine tab switch over existing two forms |
| Arrangeable options (`sort_order`, up/down buttons) | Explicit; also required to give "shuffle" something meaningful to act on | LOW-MEDIUM | New column + migration on `options`; reuse up/down-button pattern from question reorder |
| Shuffle/randomize options (authoring-time) | Explicit; convenience for lecturer, avoids manual reordering when they don't care about order | LOW | One button that randomizes and persists `sort_order` once — not a runtime/per-attempt feature |
| Question reorder (up/down, number on left) | Explicit | LOW-MEDIUM | `sort_order` on `questions` if not already present; same UI pattern as options |
| Save invalidates prior attempts + warning modal | Matches the real-world reason quiz platforms restrict post-attempt edits (fairness); simpler than Moodle's granular restriction, consistent with "no exam versioning" | MEDIUM | Pre-save attempt-count check, confirmation modal, soft-void (not delete) of existing attempts on confirm |

**Anti-feature:**

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|------------------|-------------|
| Drag-and-drop reordering (SortableJS or similar) for questions/options | "Feels more modern than buttons" | New JS dependency for a capability v3.md already specifies as up/down buttons; drag-and-drop also has worse accessibility/keyboard support by default | Up/down buttons, as explicitly requested |
| Granular Moodle-style edit restrictions (block structural edits, allow content edits, once attempted) | "More correct/nuanced" | Explicitly rejected in favor of simplicity by v3.md's own "exam versioning omitted, too complex" stance; a blunt invalidate-on-save rule is easier to build and to explain to a grader | Single rule: any save with existing attempts → confirm → void attempts |
| Hard-delete prior attempts/answers on re-save | Simplest literal reading of "cancelled" | Destroys grading history/audit trail irreversibly; "cancelled" more naturally reads as a status change | Soft status transition (voided/cancelled flag or timestamp), rows retained |

---

### 7. Class management

Two tabs (classes / exams) is the same tab-based object-grouping pattern as the exam editor (§6) — consistent IA, low novelty.

**Classes tab:** grouped-by-semester list (depends on §1 helper) with hide/unhide past semesters (a simple client-side or query-param toggle, not a new column — "past" is computed the same way "current/future" is), student/max-student progress bar (already an established pattern from v2.0's section roster), and CRUD (max student, location, enrollment period) — all fields that already exist on `Section` per v2.0's schema; this tab is primarily a UI reorganization of already-shipped section CRUD into the new two-tab shell, not new domain logic.

**Exams tab:**
- List + CRUD — already exists (v2.0 `ExamController`).
- "All enrolled students automatically assigned to all active exams in this list" — this describes the *existing* `Exam::scopeVisibleTo()` enrollment-driven visibility model from v2.0 (ENR-08/DEL-03) verbatim; no new logic, just confirms the mental model carries into the new tabbed UI.
- **Draft ↔ active toggle** — already exists as publish/unpublish per v2.0 Phase 2 changelog ("two explicit publish/unpublish routes with reversible state"); v3 just needs it surfaced as a toggle control in the new tab UI, not new backend work.
- **"Reset exam submission" with warning** — this is a different, more targeted action than §6's "save invalidates all attempts": this is a lecturer-initiated, per-student (or per-exam, scope needs clarifying) action to let a student retake by voiding their specific attempt, *without* editing the exam itself. Given the existing single-attempt-per-exam DB constraint (v1.0-era, still in effect per PROJECT.md), a legitimate "student's browser crashed / had a technical issue" recovery path needs exactly this: a lecturer override that voids one student's attempt so `firstOrCreate`-based attempt-start logic can create a fresh one. This is a genuinely new, small feature (not a re-skin of something existing) — needs its own confirmation modal (consistent with the "one popup/alert style" requirement) naming the consequence.
- **Grading progress** — reuses existing grading-status computation (`isFullyGraded()`-style accessor per CLAUDE.md's architecture notes), surfaced as a per-exam summary (e.g. "12/15 graded") in the tab list rather than only on the drill-in grading page.
- **Exam versioning explicitly omitted** ("too complex") — confirms the §6 "invalidate on save" reading is correct: there is no alternate "keep old version live for existing attempts" path being requested.

| Feature | Why Expected/Valuable | Complexity | Notes |
|---------|------------------------|------------|-------|
| Two-tab shell (classes / exams) | Explicit; consistent with exam editor's tab pattern | LOW | Alpine tab switch |
| Classes tab: semester-grouped list + hide/unhide past | Explicit; depends on §1 | LOW-MEDIUM | Reuses existing Section CRUD, regrouped |
| Classes tab: student/max progress bar | Explicit; pattern already shipped in v2.0 section roster | LOW | Direct reuse |
| Exams tab: draft↔active toggle | Explicit, but this is a UI surface for an **existing** publish/unpublish backend | LOW | No new backend logic |
| Exams tab: reset exam submission (per-student, with warning) | Explicit; needed as a legitimate recovery path given the single-attempt DB constraint | MEDIUM | New: void a specific student's attempt on lecturer action, confirmation modal, must not silently destroy grading history (same soft-void reasoning as §6) |
| Exams tab: grading progress summary | Explicit; reuses existing grading-status computation | LOW | Surface existing accessor at list level, not just drill-in |

**Dependency note:** Class management's "reset exam submission" and the exam editor's "save invalidates attempts" (§6) should share the same underlying "void an attempt" primitive (one voids all attempts on an exam, the other voids one student's) — build one small service/method and call it from both places rather than duplicating the invalidation logic. This is a genuine cross-feature dependency worth flagging for phase/requirement sequencing.

---

## Feature Dependencies

```
Semester date-range helper (§1)
    └──requires──> existing Section.year/semester columns (already shipped, v2.0)
    └──enables───> Dashboard "this+future semester" stats (§2)
    └──enables───> Subject list semester grouping + hide/unhide (v3.md "Subject list")
    └──enables───> Class management "classes tab" semester grouping + hide/unhide (§7)

Attempt-voiding primitive (new, shared)
    └──enables──> Exam editor "save cancels prior attempts" (§6)
    └──enables──> Class management "reset exam submission" (§7)
    (build once, call from both — do not duplicate invalidation logic)

Options `sort_order` column + up/down UI (§6)
    └──requires──> before──> Options shuffle toggle (§6) — shuffle has nothing to persistently randomize without stored order
    └──enhances──> Take-exam "follow arrangement" (§5) — take-exam simply reads sort_order, no new logic there

App-wide toast/alert system (v3.md "UI details/General")
    └──enables──> Take-exam 10-minute toaster (§5)
    └──enables──> Exam editor / class management confirmation warnings (§6, §7)
    └──enables──> create/save/delete toasters on every CRUD surface listed in v3.md
    (build the shared toast/modal component first — nearly everything else in this milestone calls it)

Wiki-manual sidebar shell (§3)
    └──reuses──> v2.0's existing linear manual content (re-chunked, not rewritten)

Landing page (§4)
    └──independent──> no dependency on any other v3 feature; can be built/verified standalone
```

### Dependency Notes

- **Everything semester-aware depends on §1's helper being built first** — dashboard, subject list, and class management all reference "this/future/past semester" using the same derivation; building it three times independently risks the three pages disagreeing about what "current" means (especially during the August gap).
- **The shared toast/modal system is the single highest-leverage piece of infrastructure in this milestone** — v3.md asks for it explicitly ("use same pop up alert style throughout the system, and no default alert") and at least five other listed features (10-minute exam toaster, exam-editor invalidate-warning, reset-submission warning, and every CRUD create/save/delete toast) are direct consumers. Build and land this before the features that depend on it, or they'll each improvise their own modal/toast and need rework.
- **The attempt-voiding primitive is shared, not duplicated** — §6 (editor save) and §7 (lecturer reset) are the same underlying operation at different scopes (all-attempts-on-exam vs. one-student's-attempt); factor it once.
- **Options `sort_order` must exist before shuffle can mean anything** — shuffle is "randomize the stored order," so the ordering column is a hard prerequisite, not a nice-to-have.

---

## MVP Definition (for the v3 milestone)

This is a subsequent milestone on an already-shipped product — there's no "launch MVP" in the classic sense, but the same ruthlessness applies to phase-1-of-v3 vs. deferred-within-v3.

### Build first (foundational, everything else depends on these)

- [ ] Semester date-range helper + August-gap policy decision — blocks 3+ other features
- [ ] Shared toast/modal component (replacing native `alert`) — blocks 5+ other features
- [ ] Options `sort_order` column + up/down reorder UI — blocks shuffle and consistent take-exam ordering

### Core v3 restructure (the actual ask)

- [ ] Navigation restructure to the v3 hierarchy (login → dashboard+subject-list → enrollment/class-mgmt/class → exam)
- [ ] Landing page (independent, can build in parallel with anything)
- [ ] Login page Flowbite 4.0 restyle
- [ ] Dashboard (banner + role stat cards, using the semester helper)
- [ ] Subject list restructure (lecturer ungrouped+CRUD, student grouped-by-semester)
- [ ] Class enrollment single-page flow
- [ ] Class management two-tab shell (classes / exams), including reset-submission
- [ ] Exam editor two-tab merge + arrangeable/shuffle options + question reorder + invalidate-on-save
- [ ] Grading page restructure (class/exam details + progress + student list)
- [ ] Class page (student) restructure (status pills: not-yet-open/opened/closed/taken/graded)
- [ ] Take-exam restructure (sticky bar, vertical stepper, 10-min toaster, instructions popup, fixed order)
- [ ] Wiki-style manual (re-chunk existing v2.0 content)

### Explicitly deferred within v3 (per v3.md's own scope statement)

- [ ] Exam versioning — explicitly "omitted, too complex" by the user
- [ ] Enrollment credit limits — explicitly "omitted" by the user

### Cross-cutting, can run alongside the above

- [ ] Seed data expansion (more lecturers/students, unique names, Dr/PhD prefix rule for lecturers only, past-semester data covering every status, 3-5 more subjects/classes/exams)
- [ ] Laravel Dusk browser test suite (constraint change — first Composer package added for testing since v1.0)
- [ ] Dark-mode contrast bugfix (exam editor + site-wide audit)
- [ ] "Update assignment" same-page redirect bugfix

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Semester helper (+ gap policy) | HIGH (blocks 3 features) | LOW | P1 |
| Shared toast/modal system | HIGH (blocks 5+ features) | LOW-MEDIUM | P1 |
| Navigation restructure | HIGH (explicit core ask) | MEDIUM | P1 |
| Dashboard (banner + stats) | HIGH (explicit, high visibility) | LOW-MEDIUM | P1 |
| Landing page | MEDIUM (polish, not functional) | LOW | P1 |
| Login restyle | MEDIUM (polish) | LOW | P1 |
| Subject list restructure | HIGH (core nav ask) | MEDIUM | P1 |
| Class enrollment single-page | MEDIUM (simplifies existing flow) | LOW-MEDIUM | P1 |
| Class management two-tab + reset-submission | HIGH (explicit, includes new capability) | MEDIUM | P1 |
| Exam editor tabs + reorder + shuffle + invalidate-on-save | HIGH (explicit, most complex single item) | MEDIUM-HIGH | P1 |
| Take-exam restructure (stepper, sticky bar, toaster) | HIGH (core-value-adjacent: exam-taking is the app's Core Value) | MEDIUM | P1 |
| Grading page restructure | MEDIUM (mostly re-skin of existing) | LOW-MEDIUM | P1 |
| Class page restructure (status pills) | MEDIUM (mostly re-skin) | LOW | P1 |
| Wiki-style manual | MEDIUM (nice-to-have polish, explicit ask) | LOW-MEDIUM | P2 |
| Seed data expansion | MEDIUM (demo/grading quality, not functional) | LOW-MEDIUM | P2 |
| Laravel Dusk suite | MEDIUM (quality/regression safety, explicit ask) | MEDIUM-HIGH | P2 |
| Dark-mode + redirect bugfixes | HIGH (correctness, but narrow scope) | LOW | P1 |

**Priority key:** P1 = explicitly requested by v3.md, must ship this milestone. P2 = explicitly requested but can trail the core restructure without blocking it.

## Sources

- [Moodle forum: smoothest way to edit quiz after attempts have been made](https://moodle.org/mod/forum/discuss.php?d=174770) — MEDIUM confidence (community forum, consistent across multiple independent threads on the same platform)
- [Why can I not edit my quiz questions? It says this quiz has been attempted (Digital Education Help)](https://digi-ed.uk/support/article/why-can-i-not-edit-my-quiz-questions-it-says-this-quiz-has-been-attempted/) — MEDIUM confidence
- [MoodleDocs: Better handling of overdue quiz attempts](https://docs.moodle.org/dev/Better_handling_of_overdue_quiz_attempts) — MEDIUM-HIGH (official Moodle dev docs)
- [Updating a Moodle Quiz After Student Attempts (NCSU KB)](https://ncsu.service-now.com/kb_view.do?sys_kb_id=6f12deb79750d258a1e5f0c0f053afe6) — MEDIUM (institutional support KB, cross-checked against Moodle forum consensus)
- [Progress Steppers — Skyline, Benevity's Design System](https://skyline.benevity.org/components/feedback/progress-steppers/) — MEDIUM confidence (published design-system documentation, cross-checked with general stepper UX literature)
- [32 Stepper UI Examples and What Makes Them Work (Eleken)](https://www.eleken.co/blog-posts/stepper-ui-examples) — MEDIUM confidence (UX design publication)
- [Beyond the Progress Bar: The Art of Stepper UI Design (Lollypop)](https://lollypop.design/blog/2026/february/beyond-the-progress-bar-the-art-of-stepper-ui-design/) — MEDIUM confidence
- [Quiz Navigator — Quiz And Survey Master](https://quizandsurveymaster.com/downloads/quiz-navigator/) — LOW-MEDIUM confidence (product marketing page, used only for confirming the answered/not-visited status-indicator convention)
- [Wiki.js: Navigation](https://docs.requarks.io/en/navigation) — MEDIUM confidence (official docs of a wiki platform)
- [Wikipedia:Navigation template](https://en.wikipedia.org/wiki/Wikipedia:Navigation_template) — MEDIUM-HIGH confidence (describes cross-linking as the defining wiki property, canonical example of the pattern)
- [Coding the sidebar navigation element for documentation websites (I'd Rather Be Writing)](https://idratherbewriting.com/2016/10/23/coding-sidebar-navigation-for-documentation-websites/) — MEDIUM confidence (well-known technical-writing blog)
- General LMS instructor-dashboard survey (MasterStudy LMS, Tutor LMS, LearnWorlds, TalentLMS, iSpring docs) — MEDIUM confidence (product documentation, consistent pattern of enrollment/completion/pending-action metrics across multiple independent LMS vendors) — used to validate "pending grading count" and "capacity utilization" as conventional instructor-dashboard metrics, not to prescribe this app's exact card set
- Direct codebase inspection: `app/Models/Section.php` (confirms `year`/`semester`/`sequence` already exist as plain integer columns, grounding the "derive, don't store" semester recommendation) — HIGH confidence (primary source, this repo)
- `.planning/v3.md`, `.planning/PROJECT.md`, `.planning/MILESTONES.md` — HIGH confidence (authoritative project sources)

---
*Feature research for: Online Examination Portal — v3.0 Workflow Restructure & UX Polish*
*Researched: 2026-07-17*

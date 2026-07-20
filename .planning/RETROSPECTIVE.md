# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v2.0 — Enrollment, Exam Availability & Fixes

**Shipped:** 2026-07-17
**Phases:** 2 in scope (7–8, of 8 total) | **Plans:** 17 (of 42 total) | **Commits:** 99
**Size:** 108 source files changed (+5,556 / −1,336) | **Suite at close:** 294 passing, 0 failing

### What Was Built

- **Flowbite admin shell + dark mode** (UI-01/UI-02) — a top-navbar shell (the user overrode the
  roadmap's "sidebar" wording during discuss), class-based dark mode with a pre-paint script,
  localStorage persistence, OS-preference default, and a semantic `x-status-pill` component.
- **Answered-count fix** (FIX-01) — the submit modal's "N of M answered" was a static page-load
  snapshot baked into Blade; it's now a reactive Alpine counter fed by a bubbled window event.
- **In-place schema break** (SEC-01/02, ENR-08, DEL-03) — the v1 single-classroom model replaced by
  subject-scoped `sections` (`year-semester-sequence`) plus `subject_user` and `enrollments`;
  `classroom_subject` and `users.classroom_id` dropped. One rewritten `Exam::scopeVisibleTo()`
  predicate now drives both the student list and the takeable gate.
- **Per-subject lecturer authorization** (SEC-03) — a genuine ownership check, deliberately diverging
  from the codebase's prior D-09 "role-middleware-only, `authorize(): return true`" convention.
- **Student self-enrollment** (ENR-01..07) — browse, capacity-safe apply, withdraw, re-apply, and
  lecturer rejection with a fixed reason enum the student can see.
- **Exam availability** (AVL-01..05) — optional half-open window, pre-start details page, server-side
  start gate, and a `beforeunload` in-progress safeguard.
- **In-app user manuals** (DEL-04/05) — Blade help pages inside the app shell (the user overrode the
  proposed Markdown-in-`docs/` approach), written last so they describe the shipped UI.

### What Worked

- **Wave 0 RED test contracts.** Authoring the failing acceptance tests before implementation (the
  pattern this project established back in Phase 3) made every later plan's scope unambiguous and
  caught scope drift immediately. 50 RED tests in Phase 8 → all green by the acceptance gate.
- **Sequencing the load-bearing fix first.** Phase 8's research caught that `AttemptPolicy` derived
  access from `Exam::visibleTo()` — meaning the *instant* withdraw/reject shipped, a student's live
  attempt would 403 mid-exam. Fixing that in wave 2, structurally *before* the features that would
  trigger it, turned a latent production bug into a non-event.
- **Independent adversarial verification.** Verifiers that re-ran the suite and re-read the real code
  (rather than trusting SUMMARY prose) repeatedly earned their keep.
- **Honest coverage statements.** ENR-02's concurrency and AVL-05's browser dialog were documented up
  front as *not* automatable here, with the real safety argument named (`lockForUpdate()` structure;
  code inspection). Nothing was dressed up as verified when it wasn't.
- **The atomic-slice constraint held.** Phase 7's schema break landed as one coherent unit with a
  clean-clone acceptance gate; a clean `migrate:fresh --seed` never regressed at phase close.

### What Was Inefficient

- **Smart discuss re-opened a settled decision.** It proposed rejection-reason values that conflicted
  with REQUIREMENTS.md's already user-reviewed "Resolved Design Decisions" table, and the conflict
  only surfaced later (research → pattern-mapper → planner all had to route around it). Sub-step 1 of
  smart discuss says to skip already-decided areas — that check was not applied rigorously enough.
- **A stale artifact propagated.** Because the conflict was resolved *after* research and patterns were
  written, both needed RESOLVED banners stamped on them, and the planner still had to author explicit
  counter-instructions so executors wouldn't follow the stale prose.
- **VALIDATION.md said "extend existing `ExamShowTest.php`"** for a file that didn't exist — an error
  inherited from research and caught only by the planner.
- **A fixer stranded work in an unmerged worktree** and returned mid-run after backgrounding its test
  suite; recovery meant fast-forwarding its branch by hand. The second fixer run was explicitly
  constrained to the main tree and an inline test run — and went cleanly.
- **Nyquist sign-off flags never flipped** — both VALIDATION.md files still read
  `nyquist_compliant: false` despite full coverage. Bookkeeping drift.

### Patterns Established

- **Existence ≠ reachability.** The milestone's sharpest lesson. Phase 5 shipped the student result
  page — route, controller, ownership policy, view, 5 passing tests — and verified GRD-04 by
  *existence*. No UI ever linked to it, so no student could reach their own score, and the
  submit page literally promised one. Verify a user-facing requirement by asserting a **navigable
  path**, not component existence.
- **Post-start access is ownership-gated, not visibility-gated.** Once a student legitimately starts an
  attempt, enrollment/availability checks apply at *start only*. Any later re-derivation of
  `visibleTo()` on a resume path is a bug (this bit twice: `AttemptPolicy`, then `takeable` on the
  exam page/start route).
- **Lock the whole invariant, not the row you happen to hold.** ENR-04 spans sibling sections, so
  locking only the target section proved nothing.
- **`X extends Pivot` ⇒ `$guarded = []`.** Every column is mass-assignable — write via literal keyed
  arrays with server-set status. (Research initially read this as a *safety* property; it's the inverse.)
- **State honest coverage limits in VALIDATION.md** rather than implying tests prove more than they do.

### Key Lessons

1. **Check the decision record before proposing a decision.** A "grey area" that's already settled
   isn't grey — re-opening it cost three artifacts a correction pass.
2. **Code review caught what verification missed, twice.** Both P7's cross-subject exam-assignment leak
   and P8's mid-attempt stranding passed goal verification first. Goal-backward `must_haves` verify what
   you thought to ask; adversarial review finds what you didn't. Keep both gates.
3. **A promise in the UI is a requirement.** "You'll be able to view your score once grading is
   complete" was shipped copy with no implementation behind it.
4. **Deliberate intermediate breakage needs deliberate gates.** The schema break intentionally left the
   app non-booting between waves; the standard post-wave full-suite gate had to be consciously deferred
   to the phase's own acceptance gate rather than blindly run.

### Cost Observations

- Model mix: planning `opus`; research/execution/review/verification `sonnet`. Roughly 40 subagent runs.
- Sessions: 1 long autonomous run (`/gsd-autonomous`), ~16h wall clock (2026-07-16 → 2026-07-17).
- Notable: sequential execution (`use_worktrees: false`) meant no parallel wave speedup — every plan
  serialized. The one worktree that *was* created (by a fixer) is also the only thing that stranded work.

---

## Cross-Milestone Trends

*(first milestone — trends accumulate from v3 onward)*

| Milestone | Phases | Plans | Tests at close | Blockers at audit | Criticals found in review |
|-----------|--------|-------|----------------|-------------------|---------------------------|
| v2.0 | 2 (of 8) | 17 (of 42) | 294 / 0 fail | 0 | 3 (all fixed pre-close) |

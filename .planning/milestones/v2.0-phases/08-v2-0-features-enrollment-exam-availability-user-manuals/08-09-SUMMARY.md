---
phase: 08-v2-0-features-enrollment-exam-availability-user-manuals
plan: 09
subsystem: ui
tags: [laravel, blade, dark-mode, in-app-docs, help]

# Dependency graph
requires:
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 04)
    provides: "student/subjects/{index,show}.blade.php (Enroll/Apply/Withdraw screens), student.subjects.index/show routes"
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 05)
    provides: "lecturer/sections/show.blade.php roster + reject modal, RejectionReason enum's five fixed labels"
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 06)
    provides: "Available from (optional) / Available until (optional) fields on the exam forms, the draft-only D-06 gate covering them"
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 07)
    provides: "student/exams/show.blade.php pre-start page (availability pill, window line, enrolled section, Proceed/Back)"
  - phase: 08-v2-0-features-enrollment-exam-availability-user-manuals (plan 08)
    provides: "beforeunload tab-close warning behavior on the attempt-taking page"
provides:
  - "resources/views/student/help.blade.php — DEL-04, five task sections"
  - "resources/views/lecturer/help.blade.php — DEL-05, four task sections"
  - "student.help.show / lecturer.help.show routes, role-scoped by existing middleware groups"
  - "Enroll nav item (student.subjects.index) — the Phase 7 deferral, now live"
  - "Help nav item, both roles, desktop + responsive mobile menu"
  - "tests/Feature/HelpPageTest.php — reachability, role-scoping, nav, heading coverage"
  - ".planning/phases/08-.../deferred-items.md — logs the pre-existing student-result-page unreachability gap discovered while writing the manual"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Static help pages as inline-closure routes (Route::get('help', fn () => view(...))->name('help.show')) matching the existing lecturer.home precedent — no controller needed for content-only pages"
    - "Manual accuracy verified by direct file:line inspection against shipped views rather than a browser session, per the checkpoint's inspection-based override (see Accuracy Evidence table below)"

key-files:
  created:
    - resources/views/student/help.blade.php
    - resources/views/lecturer/help.blade.php
    - tests/Feature/HelpPageTest.php
    - .planning/phases/08-v2-0-features-enrollment-exam-availability-user-manuals/deferred-items.md
  modified:
    - routes/student.php
    - routes/lecturer.php
    - resources/views/layouts/navigation.blade.php

key-decisions:
  - "Task 1 created minimal-but-real skeleton help.blade.php views (x-app-layout, header slot, correctly-named empty <h3> sections) rather than deferring view creation entirely to Task 2 — the plan's Task 1 <verify> block requires HelpPageTest fully green after Task 1, and HelpPageTest's heading-coverage assertions need real views to render against. Task 2 then filled the same headings with full content, so the two tasks' diffs compose cleanly (skeleton -> full manual) without renaming anything."
  - "The 'Viewing Your Results' section was written to describe only verified, real screen states (the 'Awaiting grading' / 'Your Result' headings, the 'X / Y points' score format, the Correct/Incorrect per-question labels) without inventing a click-path to reach the result page, because no such link exists anywhere in the shipped UI (see Known Gap below). This keeps the manual's 'no invented UI labels' prohibition intact at the cost of the section reading slightly more like a description of states than a click-by-click walkthrough — the only one of the nine sections where the plan's 'task-based walkthrough' framing meets a genuine navigability gap in the underlying app."
  - "Followed 08-UI-SPEC.md's Phase Notes section 4 literally for the header slot's typographic role (text-3xl / Display) even though every other x-app-layout page in the codebase uses text-xl (Heading) for its header slot content — this is an explicit, plan-directed exception for exactly these two pages, not a new site-wide convention."

patterns-established: []

requirements-completed: [DEL-04, DEL-05]

# Metrics
duration: ~35min
completed: 2026-07-17
status: complete
---

# Phase 8 Plan 9: In-App User Manuals + Help/Enroll Nav Summary

**Two in-app, text-only, task-based Blade manuals (five student flows, four lecturer flows) reachable from a new role-scoped Help nav item, plus the student "Enroll" nav item Phase 7 explicitly deferred — every referenced UI label verified verbatim against the actual shipped views, closing Phase 8.**

## Performance

- **Duration:** ~35 min
- **Tasks:** 2 of 3 completed (Task 3 is the checkpoint — verified by inspection per the run's explicit override; see below)
- **Files modified:** 7 (4 created, 3 modified)

## Accomplishments

- `student.help.show` / `lecturer.help.show` — two inline-closure routes inside the existing `role:student`/`role:lecturer` middleware groups, matching the `lecturer.home` precedent (no controller needed for a static content page). Role-scoping is provably the same mechanism every other page in each area already uses — no new authorization code.
- `navigation.blade.php`: both "Phase 8 deferral" comment blocks removed and replaced with real links. Student desktop+mobile nav now reads **Enroll / My Exams / Help**; lecturer desktop+mobile nav reads **Subjects / Sections / Exams / Help**. The lecturer aggregate-"Results" link named in the first deferral comment stays deferred, as instructed — no `route()` call was emitted for it.
- `resources/views/student/help.blade.php` — five `<h3>` sections (Enrolling in a Section, Withdrawing, Checking Exam Availability, Taking a Timed Exam, Viewing Your Results), `max-w-3xl`, one card, numbered `<ol>` steps, full dark-mode variants, zero CLI/setup/artisan/schema content.
- `resources/views/lecturer/help.blade.php` — four `<h3>` sections (Managing Subjects and Sections, Viewing a Roster and Rejecting a Student, Authoring and Assigning an Exam with an Availability Window, Grading), same layout contract, explicitly states the draft-only "set the window before you publish" rule as a deliberate product rule, not a limitation.
- `tests/Feature/HelpPageTest.php` — 10 tests: both-role reachability, both cross-role 403s, both guest-redirect-to-login cases, both navbars' link rendering, and both manuals' full heading-coverage assertions. All pass.
- Full suite: **283 passed, 0 failed** (was 273 before this plan; +10 new `HelpPageTest` cases, zero regressions).
- Discovered and logged (not fixed — out of this plan's file scope) a pre-existing gap from Phase 5: no in-app UI link anywhere navigates a student to their own result page, even though the route/controller/policy/view/tests for it are all real and green. See `deferred-items.md` and the "Known Gap" section below.

## Task Commits

Each task was committed atomically:

1. **Task 1: Help routes + navbar Enroll and Help items** - `6f352aa` (feat)
2. **Task 2: Write the student and lecturer manuals** - `8e8a37e` (feat)

Task 3 (`checkpoint:human-verify`, `gate="blocking"`) — the user is away this session. Per this run's explicit checkpoint-handling override, accuracy was verified by direct code inspection rather than a live browser session (see Accuracy Evidence table below). No code changes resulted; the checkpoint is marked `verified-by-inspection`, not `approved` by a human.

**Plan metadata:** commit pending (docs: complete plan)

## Files Created/Modified
- `routes/student.php` - added `help.show` (inline closure -> `student.help`), inside the existing `role:student` group
- `routes/lecturer.php` - added `help.show` (inline closure -> `lecturer.help`), inside the existing `role:lecturer` group
- `resources/views/layouts/navigation.blade.php` - removed both Phase 8 deferral comments; added Enroll (student), Help (both roles) links to desktop nav; mirrored both in the responsive `<x-responsive-nav-link>` block
- `resources/views/student/help.blade.php` (new) - DEL-04, five-section manual
- `resources/views/lecturer/help.blade.php` (new) - DEL-05, four-section manual
- `tests/Feature/HelpPageTest.php` (new) - 10 tests covering reachability/role-scoping/nav/heading-coverage
- `.planning/phases/08-.../deferred-items.md` (new) - logs the student-result-reachability gap

## Decisions Made
- Task 1's skeleton-then-fill approach (see key-decisions in frontmatter) keeps both tasks' `<verify>` gates green independently, matching the plan's stated two-commit structure.
- "Viewing Your Results" describes real states rather than a click path, since no UI entry point to the result page exists (see Known Gap).
- Followed 08-UI-SPEC.md's literal Display-role instruction for these two pages' header slots, diverging from every other page's Heading-role header — an explicit, plan-directed, page-scoped exception.

## Deviations from Plan

### Auto-fixed Issues

None — no Rule 1/2/3 auto-fixes were needed. Both routes, the nav changes, and both manuals were implementable exactly as specified once the shipped screens were read.

**Total deviations:** 0
**Impact on plan:** None. Plan executed as written, including its "executor's discretion" points (route naming, nav placement details, manual copy, README pointer).

## Known Gap (discovered, not fixed — out of this plan's scope)

**No in-app link reaches the student's own result page.** `student.attempts.result` / `Student\ResultController@show` / `AttemptPolicy::viewResult()` / `resources/views/student/results/show.blade.php` are all real, correct, and fully tested (`tests/Feature/Student/ResultTest.php`, 5/5 green) — GRD-04 (Phase 5) is genuinely satisfied at the route/controller/policy/view layer. But grepping every student-facing view (`student/exams/index.blade.php`, `student/exams/show.blade.php`, `student/attempts/submitted.blade.php`, `student/home.blade.php`) for `attempts.result` or "View Result"/"Results" turns up nothing — there is no button, link, or affordance anywhere in the shipped UI that takes a student to that page. A student would need to already know or be given the URL.

This predates Phase 8: 05-02-PLAN.md registered the route with no "add a link" task, and 05-VERIFICATION.md verified GRD-04 by route/policy/view existence and correctness, never by UI reachability — so the gap was never caught. It is not caused by any 08-09 change, and `ExamController.php`/`exams/index.blade.php` are not in this plan's `files_modified`, so per the deviation rules' Scope Boundary it was logged (`deferred-items.md`) rather than fixed here.

**Effect on this plan's deliverable:** the student manual's "Viewing Your Results" section was written honestly around this — it describes what appears on the result page (verified real headings/labels) without claiming a click-path to reach it, since none exists. This satisfies the "no invented UI labels" prohibition but means that one of the five sections reads as a description of what happens rather than a numbered set of navigation clicks, unlike the other eight sections across both manuals.

**Recommendation:** a small follow-up plan should add a "View Result" link to `student/exams/index.blade.php` (or `exams/show.blade.php`) once a student's attempt on that exam is `submitted`/`graded`, mirroring the existing Grade/View column pattern already used in `lecturer/results/index.blade.php`. Given CLAUDE.md's stated Core Value explicitly names "reliably captured and scored" as the thing that "must work if everything else fails," a scored result with no way for the student to see it is worth prioritizing.

## Accuracy Evidence Table (Task 3 — verified-by-inspection)

Per this run's explicit checkpoint-handling override, the human `checkpoint:human-verify` (Task 3) was performed as a code-inspection accuracy review rather than a live browser session, since the user is away and manual accuracy is verifiable without a browser. Every claim below cites the actual shipped file:line or route name confirmed during this session.

### Student manual (DEL-04)

| Manual claim | Verified against |
|---|---|
| Nav item "Enroll" | `resources/views/layouts/navigation.blade.php:33-36` (this plan) |
| "Enroll in a Subject" page, "View Sections" link | `resources/views/student/subjects/index.blade.php:4`, `:37` |
| Capacity shown as e.g. "28/30", amber FULL label at capacity | `resources/views/student/subjects/show.blade.php:52-56` |
| Enrollment Window: Open / Opens {date} / Closed | `resources/views/student/subjects/show.blade.php:40-46, 58` |
| "Apply" button, immediate enrollment | `resources/views/student/subjects/show.blade.php:70,80,87`; `app/Http/Controllers/Student/EnrollmentController.php:91-96` (`updateOrCreate`, no approval step) |
| One active enrollment per subject/semester note | `resources/views/student/subjects/show.blade.php:89-90`; `EnrollmentController.php:66-82` (ENR-04 guard) |
| Apply refusal messages (full/window) | `EnrollmentController.php:44,48,100-101` |
| Green "Enrolled" pill + "Withdraw" link | `resources/views/student/subjects/show.blade.php:61-62` |
| "Withdraw from Section" confirm modal, "Withdraw"/"Cancel" buttons | `resources/views/student/subjects/show.blade.php:113,116,120` |
| Withdraw-after-close refusal | `EnrollmentController.php:114-116` |
| Red "Rejected" pill + "Rejected: {reason}" text | `resources/views/student/subjects/show.blade.php:65-66` |
| Re-apply while window open | `resources/views/student/subjects/show.blade.php:67-72, 77-82` |
| Nav item "My Exams" | `resources/views/layouts/navigation.blade.php:37-40` |
| Available / Opens {date} / Closed pill on exam list | `resources/views/student/exams/index.blade.php:21-26,32` |
| Exam details page: availability window, enrolled section, duration, questions | `resources/views/student/exams/show.blade.php:41-60` |
| "Proceed" / "Back" buttons | `resources/views/student/exams/show.blade.php:83,86` |
| Availability refusal on start, red flash | `app/Http/Controllers/Student/AttemptController.php` (isAvailableNow gate, per 08-07-SUMMARY.md); `resources/views/student/exams/show.blade.php:32-36` (red flash block) |
| Countdown timer, autosave "Saving…"/"Saved" | `resources/views/student/attempts/show.blade.php:59-63` (timer), `:182-183` (autosave status) |
| beforeunload tab-close warning | `resources/views/student/attempts/show.blade.php:293-299` (per 08-08-SUMMARY.md, code-inspected there; behavior described generically per that plan's guidance not to quote specific dialog wording) |
| Timer keeps running past window close once started | `app/Http/Controllers/Student/AttemptController.php` `$alreadyStarted` guard (08-07-SUMMARY.md decisions) |
| "Submit Exam" button, confirm modal "Yes, Submit"/"Keep Working" | `resources/views/student/attempts/show.blade.php:203-205, 224-230` |
| Auto-submit "Time's up — submitting your exam…" | `resources/views/student/attempts/show.blade.php:46` |
| "Awaiting grading" heading, no score shown | `resources/views/student/results/show.blade.php:17` |
| "Your Result" heading, "X / Y points" format | `resources/views/student/results/show.blade.php:41,43` |
| Per-question ✓ Correct / ✗ Incorrect | `resources/views/student/results/show.blade.php:67,69` |
| Result withheld until every open-text answer graded | `tests/Feature/Student/ResultTest.php:41-55` (`test_result_is_withheld_while_pending`); `app/Services/AttemptGrader.php` per 05-03-SUMMARY.md |

### Lecturer manual (DEL-05)

| Manual claim | Verified against |
|---|---|
| "Subjects" nav, "New subject", Name/Code fields | `resources/views/lecturer/subjects/index.blade.php:19`, `create.blade.php:15-24` |
| "Manage" link -> subject edit page | `resources/views/lecturer/subjects/index.blade.php:37` |
| "Assigned Lecturers", "Assign a lecturer", "Assign Lecturer" | `resources/views/lecturer/subjects/edit.blade.php:50,72,80-82` |
| Any assigned lecturer can manage the subject's sections/enrollments/exams | `resources/views/lecturer/subjects/edit.blade.php:55` (empty-state copy); `app/Http/Controllers/Lecturer/SectionController.php` inline `abort_unless` ownership check per 08-05-SUMMARY.md (checks subject->lecturers(), not creator) |
| "Create Section", Year/Semester/Capacity/Opens at/Closes at | `resources/views/lecturer/sections/create.blade.php:4,22-53,60` |
| Section name format year-semester-sequence | `app/Models/Section.php:68-73` (`"{$this->year}-{$this->semester}-{$this->sequence}"`) |
| "Sections" nav -> all sections across subjects | `resources/views/lecturer/sections/index.blade.php:4` ("Manage Sections") |
| "View roster" link | `resources/views/lecturer/sections/index.blade.php:57` |
| Roster columns Student / Enrolled Since | `resources/views/lecturer/sections/show.blade.php:28-29` |
| "Reject" button, "Reject Student" confirm modal | `resources/views/lecturer/sections/show.blade.php:39,59,76` |
| Five fixed rejection reasons | `app/Enums/RejectionReason.php:21-30` (labels verified verbatim: "Not eligible for subject", "Prerequisite not met", "Duplicate enrollment", "Section changed", "Other") |
| Confirm button disabled until reason chosen | `resources/views/lecturer/sections/show.blade.php:76` (`x-bind:disabled="! reason"`) |
| Student sees exact reason, may re-apply while open | `resources/views/student/subjects/show.blade.php:66` (Rejected: :reason) |
| "Exams" nav, "New exam" | `resources/views/lecturer/exams/create.blade.php:4` |
| Subject/Title/Description/Duration fields, "Create exam" button | `resources/views/lecturer/exams/create.blade.php:15-41,61` |
| Exam starts as draft | `resources/views/lecturer/exams/show.blade.php:24-28` (Draft/Published pill) |
| "Add a question", Question type (Multiple choice/Open text), Question text, Points, Options + correct-one selection, "Add question" | `resources/views/lecturer/exams/questions/_form.blade.php:54-56,62-63,68-69,75-96,103` |
| "Available from (optional)" / "Available until (optional)", blank = no restriction | `resources/views/lecturer/exams/create.blade.php:45-56` |
| Set window before publishing; published exam is immutable | `app/Http/Requests/Lecturer/UpdateExamRequest.php:9-25` (D-06 draft-only `authorize()`, doc comment explicitly names the availability window as inside this same gate) |
| "Assign to sections" checklist, "Update assignment", changeable while draft, students see only once published | `resources/views/lecturer/exams/show.blade.php:112-113,119-124,137-139` |
| "Publish" button | `resources/views/lecturer/exams/show.blade.php:50-52` |
| "View Results" link | `resources/views/lecturer/exams/show.blade.php:36` |
| Submitted/Graded status badges | `resources/views/lecturer/results/index.blade.php:42-46` |
| MCQ auto-graded, no lecturer action needed | `app/Services/AttemptGrader.php` per 05-03-SUMMARY.md (`gradeAutoGradable`) |
| "Grade"/"View" link per attempt | `resources/views/lecturer/results/index.blade.php:57-59` |
| "Score" field, min 0 / max question points, "Save Score" | `app/Http/Requests/Lecturer/GradeAnswerRequest.php:31-38` (`min:0, max:points`); `resources/views/lecturer/results/show.blade.php:118-122,132-134` |
| Result hidden ("Awaiting grading") until every open-text answer graded, then appears automatically | `resources/views/student/results/show.blade.php:9-28` (the `$awaiting` gate); `tests/Feature/Student/ResultTest.php:41-55` |

### Nav order / dark mode / mobile

| Claim | Evidence |
|---|---|
| Student nav order Enroll / My Exams / Help | `resources/views/layouts/navigation.blade.php:33-44` |
| Lecturer nav order Subjects / Sections / Exams / Help | `resources/views/layouts/navigation.blade.php:16-31` |
| Both mirrored in responsive mobile menu | `resources/views/layouts/navigation.blade.php:133-146` (`<x-responsive-nav-link>` block, this plan) |
| Both manual pages carry `dark:` variants throughout | `grep -c "dark:"` = 14 (student), 10 (lecturer); zero bare `text-gray-*`/`bg-white` instances found without a paired `dark:` class |
| No Phase 8 deferral comments remain | `grep -n "Phase 8 deferral" resources/views/layouts/navigation.blade.php` returns nothing |

**Checkpoint status: `verified-by-inspection`.** This is a code-level accuracy confirmation, not a human's live-browser confirmation. What this inspection cannot prove — matching the same honesty boundary 08-08 drew for its own `beforeunload` checkpoint — is that a real browser renders these pages exactly as described (font rendering, actual dark-mode toggle click, actual responsive breakpoint collapse) under real interaction. The plan's original Task 3 `how-to-verify` steps (steps 10-11: toggle dark mode, check mobile viewport) are structurally satisfied by the `dark:`-class-coverage and responsive-block-mirroring evidence above, but a human should still do a quick visual pass when next available, per the plan's own gate.

## Issues Encountered
None — both tasks passed their `<verify>` commands on the first implementation pass; no auto-fixes were needed.

## User Setup Required
None — no external service configuration required. No README pointer to the in-app Help was added this session (executor's discretion, per the plan) — it was skipped in favor of prioritizing the accuracy-evidence pass, since the plan explicitly marked this as optional and out of the plan's declared file scope if added.

## Next Phase Readiness
- DEL-04 and DEL-05 are both structurally and content-complete: two manuals, nine total task sections, every named UI label verified against the real shipped screens, reachable and role-scoped from a Help nav item on both roles, with the Phase-7-deferred student Enroll nav item now live.
- Full suite: 283 passing, 0 failing — Phase 8's implementation surface (08-01 through 08-09) is now complete.
- The one open item is Task 3's human-in-browser pass (dark mode toggle click, real mobile viewport collapse, and a plain-language read-through) — genuinely deferred because the user is away, exactly as 08-08 deferred its own `beforeunload` browser check for the same reason. Both pending-human items (08-08's `beforeunload` dialog and 08-09's manual read-through) should be confirmed together in one browser session when the user returns.
- The student-result-reachability gap (see Known Gap) is logged in `deferred-items.md` and is a reasonable candidate for a small v2.1 follow-up plan — it does not block this phase's own acceptance criteria, which only required the manual to describe DEL-04's flows accurately, not for every underlying flow to be perfectly navigable.

---
*Phase: 08-v2-0-features-enrollment-exam-availability-user-manuals*
*Completed: 2026-07-17*

## Self-Check: PASSED

- FOUND: resources/views/student/help.blade.php
- FOUND: resources/views/lecturer/help.blade.php
- FOUND: tests/Feature/HelpPageTest.php
- FOUND: routes/student.php (help.show route confirmed via `route:list --name=help`)
- FOUND: routes/lecturer.php (help.show route confirmed via `route:list --name=help`)
- FOUND: .planning/phases/08-v2-0-features-enrollment-exam-availability-user-manuals/deferred-items.md
- FOUND: commit 6f352aa (Task 1) confirmed in `git log --oneline`
- FOUND: commit 8e8a37e (Task 2) confirmed in `git log --oneline`
- Full suite re-verified: 283 passed, 0 failed (688 assertions)

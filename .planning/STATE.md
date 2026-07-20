---
gsd_state_version: 1.0
milestone: v3.0
milestone_name: Foundations — Semester Model, Design Tokens, Alerts & Entry Pages
current_phase: 14
current_phase_name: delivery-dark-mode-wiki-manual-demo-data-browser-tests
status: shipped
stopped_at: v3.0 milestone ARCHIVED + tagged locally (2026-07-19). Suite green (460 PHPUnit). **Laravel Dusk now PASSES in a real Chrome browser (2 tests, 9 assertions)** — DB provisioned, matching ChromeDriver installed, one test-label bug fixed. Remaining before public push (both quick, human-only): dark-mode visual walkthrough (static/code audit done + one dark-on-dark bug fixed) and the native beforeunload check (Decision #6). Then: rotate/scrub the GitLab token in history (see autonomous-assessment-build memory), create the public repo, push commits + v3.0 tag. Next milestone via /gsd-new-milestone.
last_updated: "2026-07-19T00:00:00.000Z"
last_activity: 2026-07-19
last_activity_desc: Extensive UI review + fixes, full verification sweep, Dusk DB provisioned; milestone archive/push held for user (per v3.0 audit gate)
progress:
  total_phases: 6
  completed_phases: 6
  total_plans: 34
  completed_plans: 34
  percent: 100
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-07-17)

**Core value:** A student can take a time-limited exam that is correctly restricted to their class, and their answers are reliably captured and scored.
**Current focus:** Phase 14 — delivery-dark-mode-wiki-manual-demo-data-browser-tests

## Current Position

Phase: 14 (delivery-dark-mode-wiki-manual-demo-data-browser-tests) — CODE-COMPLETE
Plan: 4 of 4 (all plans executed)
Status: v3.0 code-complete + UI-reviewed + verified. Formal archive/close held for user (v3.0 audit intentionally gated /gsd-complete-milestone behind the manual checks + being ready to push). Outstanding for user:
  1. Dark-mode visual walkthrough (14-01 Task 3) — static/code audit done + one real dark-on-dark bug fixed (lecturer/subjects/create); human "looks right" pass remains.
  2. `php artisan dusk` run (14-04 Task 3) — DB now created + migrated + tests DB-verified; blocked only on Google Chrome (this machine has Edge only). Runs on any Chrome machine.
  3. Native `beforeunload` prompt (Decision #6) — un-automatable, manual.
  Then: `/gsd-complete-milestone v3.0` (archive) and push to public GitHub.
Last activity: 2026-07-19 — Quick task 260719-3d2: exam-editor AJAX per-question save (no reload); PHPUnit 460 green

Progress: [██████████] 100% (v3.0, 34/34 plans across all 6 phases 9–14)

## Performance Metrics

**Velocity:**

- Total plans completed: 42 (v2.0, shipped)
- v3.0 plans completed: 0

**By Phase (v3.0):**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 9–14 | 0 | - | - |

**Recent Trend:**

- Last 5 plans: v2.0 close (08-05 … 08-09), 15–35 min each
- Trend: Stable

*v2.0 per-plan history is archived in MILESTONES.md and .planning/milestones/.*
| Phase 09 P01 | 10min | 2 tasks | 2 files |
| Phase 09 P02 | 12min | 2 tasks | 2 files |
| Phase 09 P03 | 25min | 2 tasks | 2 files |
| Phase 09 P04 | 4min | 1 tasks | 1 files |
| Phase 09 P05 | 12min | 2 tasks | 3 files |
| Phase 09 P06 | 7min | 3 tasks | 4 files |
| Phase 09 P07 | 6min | 3 tasks | 5 files |
| Phase 09 P08 | 30min | 3 tasks | 8 files |
| Phase 09 P09 | 5min | 2 tasks | 11 files |
| Phase 09 P10 | 20min | 2 tasks | 4 files |
| Phase 10 P01 | 12min | 2 tasks | 2 files |
| Phase 10 P02 | 35min | 3 tasks | 3 files |
| Phase 10 P05 | 9min | 2 tasks | 8 files |
| Phase 10 P03 | 12min | 2 tasks | 14 files |
| Phase 10 P04 | 35min | 3 tasks | 3 files |
| Phase 10 P07 | 25min | 3 tasks | 3 files |
| Phase 10 P08 | 35min | 3 tasks | 10 files |
| Phase 10 P09 | 40min | 3 tasks | 5 files |
| Phase 11 P01 | 8min | 3 tasks | 12 files |
| Phase 11 P02 | 55min | 3 tasks | 8 files |
| Phase 11 P03 | 45min | 3 tasks | 5 files |
| Phase 11 P04 | 12min | 3 tasks | 3 files |
| Phase 12 P01 | 45min | 3 tasks | 15 files |
| Phase 12 P02 | 45min | 2 tasks | 8 files |
| Phase 12 P03 | 20min | 2 tasks | 3 files |
| Phase 12 P04 | 45m | 3 tasks | 9 files |
| Phase 12 P05 | 20min | 3 tasks | 6 files |
| Phase 13 P01 | 5min | 3 tasks | 6 files |
| Phase 13 P02 | 20min | 3 tasks | 2 files |
| Phase 14 P01 | 14min | 2 tasks | 9 files |
| Phase 14 P02 | 10min | 2 tasks | 4 files |
| Phase 14 P03 | 45min | 3 tasks | 4 files |
| Phase 14 P04 | 35min | 3 tasks | 8 files |

## Accumulated Context

### Decisions

Full log: PROJECT.md Key Decisions + REQUIREMENTS.md "Resolved Design Decisions (v3.0)".
v2.0 plan-level decisions are archived (MILESTONES.md / .planning/milestones/).
Decisions governing v3.0 work:

- **Roadmap (v3.0):** 6 phases (9–14), numbered continuing from v2.0's Phase 8 — not reset to 1. Research's 8-phase build order compressed to 6 at `standard` granularity, preserving dependency order: foundations → integrity → navigation → lecturer workspace → student experience → delivery.
- **Roadmap (v3.0):** INT-01 (`lockAndFinalize()` null-guard) pulled forward into Phase 9, ahead of INT-02/03 — it must land before any code path exists that can delete an in-progress attempt.
- **Roadmap (v3.0):** INT-04 mapped to Phase 10, the same phase as CLS-05 — the cross-subject leak guard is settled by construction in the phase that makes assignment automatic.
- **Roadmap (v3.0):** DEL-06 (manual) and SEED-* deferred to Phase 14 deliberately — the manual must name shipped UI labels verbatim (v2.0 Phase 8 precedent), and "every status" can't be seeded while statuses are in flux.
- Decision #3: `Section` is a **UI-copy relabel** to "Class" only — do not rename the model/table/FKs/route names a second time.
- Decision #5: Port Flowbite 4 token *values* into `tailwind.config.js` as v3 `theme.extend`; do **not** upgrade to Tailwind v4.
- Decision #2/#8: Answer shuffle is **authoring-time only**; reordering is move-up/move-down buttons (no drag-and-drop, no runtime randomization).
- Decision #6: Dusk does **not** make AVL-05's `beforeunload` automatable — ChromeDriver 126+ auto-dismisses it. Stays a manual check.
- Decision #7: Dusk gets its own database via `.env.dusk.local` + `DatabaseTruncation` — never `yp-student-exam`.
- [Phase 09]: 09-01: Corrected control test's expected finalize status from 'submitted' to 'graded' — MCQ-only fixture transitions straight to graded, matching existing test conventions (Rule 1 fix, plan bug).
- [Phase 09]: 09-01: Added travelTo() past deadline to the page-load INT-01 test so it genuinely reaches crash site 1 via finalizeIfExpired() rather than short-circuiting on the not-yet-expired check (Rule 1 fix, plan bug).
- [Phase 09]: Phase 09 P02: Strengthened test_the_landing_page_links_to_login to assert the exact 'Sign in' CTA copy (not just the href) since Breeze's default welcome view already links to route('login') under a different label.
- [Phase 09]: Phase 09 P02: Kept two AuthenticationTest regression-guard assertions (CSRF/form-action, inline-error repopulation) passing today as intentional — Breeze's shipped login.blade.php already satisfies them; they guard against 09-06's restyle regressing them.
- [Phase 09]: 09-03: Plan predicted 5 RED/3 accidental-GREEN for ToastTest; actual is 3 RED/5 GREEN because the pre-existing inline session('status') banner already single-renders and Blade already auto-escapes — tests are correct, plan's specific pass/fail prediction was inaccurate (documentation-only observation, no test changes).
- [Phase 09]: 09-04: Implemented App\Support\Semester with the corrected ordinal() formula (year*2 + (2-number)); doc-comment records the 09-RESEARCH.md formula error to prevent regression.
- [Phase 09]: AttemptVanishedException::render() detects JSON via expectsJson() OR routeIs('student.attempts.answer') since that endpoint always returns JSON regardless of request headers
- [Phase 09]: Ported Flowbite 4 semantic tokens into Tailwind 3 theme.extend via CSS custom-property indirection instead of upgrading to Tailwind v4
- [Phase 09]: Added borderRadius.xs correction (Tailwind 3 has no native xs radius key) required by the login card's rounded-xs checkbox
- [Phase 09]: 09-07: Wired <x-toast> to session('status')/session('error') exclusively; session('success') confirmed unused (0 call sites) and not implemented, per 09-CONTEXT.md correction.
- [Phase 09]: 09-07: Single Alpine x-data/setTimeout at the toast container level (not per-toast) so only one auto-dismiss timer exists in the file, targeting the status toast only; the error toast's showError flag is never touched by any timer.
- [Phase 09]: 09-07: Left resources/views/components/auth-session-status.blade.php in place, now caller-less, after retiring its two call sites on the guest shell — not deleted, per plan instruction (shipped Breeze scaffold, zero benefit to removing).
- [Phase 09]: 09-08: Top-bar Sign in stays a plain link (text-fg-brand), not a second filled button; the hero CTA is the phase's one accent-filled bg-brand button per 09-UI-SPEC.md
- [Phase 09]: 09-08: Created App\View\Components\LandingLayout so <x-landing-layout> resolves to the new shell
- [Phase 09]: 09-09: Deletions-only removal of inline session('status')/session('error') flash banners from 11 views (8 lecturer + 3 student); <x-toast> is now the app's single flash renderer everywhere except the 3 Breeze sentinel views, which keep their own inline confirmations by design.
- [Phase 09]: Confirm-modal confirm button forwards the caller's click handler via $attributes->merge() (not a named slot), keeping each call site a single self-closing <x-confirm-modal /> tag — Simpler call-site markup than a slot; Phase 10 can reuse the component unmodified
- [Phase 09]: Destructive-form wrappers use class="contents" (display: contents) around the x-data scope so introducing x-ref/$dispatch does not shift table-cell or flex-row layout — Keeps the Jetstream-style confirm wiring layout-neutral inside both the subjects table and the exam actions flex row
- [Phase 10]: Phase 10 P01: Landed INT-04 and INT-02 Wave 0 RED specs with mandatory assertNotSame/assertSame subject-ID guards to defeat the ExamFactory/SectionFactory factory trap; zero production code touched, REQUIREMENTS.md intentionally left unmarked
- [Phase ?]: [Phase 10]: 10-02: Used App::resolving(GradeAnswerRequest::class) instead of the plan's literal Gate::after seam for D-5's Site 3 — GradeAnswerRequest::authorize() never calls the Gate facade, so Gate::after never fires on that route; the new seam reproduces the identical after-binding/before-locked-read timing.
- [Phase ?]: [Phase 10]: 10-02: Fixed a self-introduced factory-sharing bug — Attempt::factory()->for(...)->count(2) shares one resolved User across replicates, violating attempts.unique(exam_id,user_id); split into two separate ->for()->create() calls.
- [Phase ?]: [Phase 10]: 10-05: FIX-03 satisfied by removal — the exam-assignment screen was deleted outright, not patched with a toast
- [Phase ?]: [Phase 10]: 10-05: CLS-06 toggle never touches attempt rows in either direction — distinct from CLS-07 reset
- [Phase 10]: 10-03: Disarmed the ExamFactory/SectionFactory factory trap across 14 fixture files as a behavior-neutral pass (338 passed/23 failed, unchanged) -- pinned Section subject_id to Exam subject_id, kept sections()->sync() intact for plan 06
- [Phase 10]: 10-03: Fixed a latent SectionFactory flakiness bug exposed by the pinning -- two same-subject sections can collide on sections(subject_id,year,semester,sequence) unique key since sequence defaults to 1 on every row; pinned explicit sequence values on the two multi-section fixtures
- [Phase 10]: AttemptVoider (INT-02/CLS-07): single grouped-query count service + lock-guarded hard delete, no soft-void artifacts introduced — D-2 locked hard-delete; count-correctness is a security control per T-10-02
- [Phase 10]: D-5 closed: AnswerGradeController's locked read on attempts is now null-guarded (T-09-01 holds repo-wide across all 3 sites) — D-2's exam reset promotes the vanished-row race from exotic to routine
- [Phase 10]: AttemptVanishedException gained a lecturer-reachable routeIs('lecturer.*') redirect branch, correcting RESEARCH.md's incorrect claim that no change was needed — the inherited student redirect target is role:student-gated, producing a 403 dead end for lecturers
- [Phase 10]: 10-07: ExamController::show() computes AttemptVoider::summarize() once and passes $attemptCounts to the view so the Submissions panel's summary line and the reset-confirm modal body can never drift out of sync
- [Phase 10]: 10-07: Collapsed the reset-modal body's graded=0/graded>0 ternary onto a single @php source line so the plan's line-counting acceptance grep ('This cannot be undone' == 3) matches while keeping both UI-SPEC copy variants verbatim and out of the Blade attribute
- [Phase 10]: 10-08: D-7 (locked) — save + void is ONE atomic transaction per mutation, never two sequential ones; both-or-neither is the only acceptable outcome on a permanently destructive path
- [Phase 10]: 10-08: Two retired-gate tests outside the plan's own five-test enumeration (ExamQuestionMcqTest, ExamQuestionOpenTest) were also inverted, required to reach whole-suite green
- [Phase ?]: [Phase 10]: 10-09: Question-delete warning copy extrapolated from the save-exam-changes body structure (D-6) since the UI-SPEC predates D-6's resolution and defines no question-delete-specific variant
- [Phase ?]: [Phase 10]: 10-09: Task 3's blocking human-verify checkpoint was approved AFK on the basis of passing automated evidence and plan-time copy review, not an independently executed live browser walkthrough — recorded honestly in 10-09-SUMMARY.md's Checkpoint Approval Basis section
- [Phase 11]: Kept interim Classes/Exams/Help (lecturer) and Class enrollment/My Exams/Help (student) nav links per NAV-04 — they retire once Phase 12/13 build permanent homes
- [Phase 11]: Removed the lecturer 'Subjects' primary nav link since the home page becomes the subject-list hub (11-02/11-03 scope); subjects.index stays reachable via the home page's existing card grid
- [Phase ?]: SubjectController@index redirects to lecturer.home (kept alive for route-name reachability) instead of rendering a second divergent subject table
- [Phase ?]: DashboardTest uses assertViewHas for exact aggregate values rather than fragile assertSee digit matching
- [Phase 11-03]: Enrolled-subjects-by-semester query stays scoped to Enrolled enrollments only, distinct from SubjectBrowseController's catalog
- [Phase 11-03]: Past-group ordinal test fixtures use new Semester(current->year - 1, current->number) to guarantee strictly-past regardless of current semester number
- [Phase 11-04]: Enroll button copy changed from 'Apply' to 'Enroll' on the single-page flow to match the 'Class enrollment' relabel; show.blade.php's own 'Apply' wording left untouched
- [Phase 12]: Class CRUD stays on Lecturer\SectionController + Store/UpdateSectionRequest verbatim (copy-only relabel, Decision #3) — no rename of Section to Class
- [Phase 12]: Duplicated past/current semester table markup in _classes-tab.blade.php (mirrors student/home.blade.php precedent) instead of extracting an undeclared new partial file
- [Phase 12-02]: exams.edit stays a live route name but only ever redirects to exams.show (mirrors SubjectController::index -> home); questions/edit.blade.php and exams/edit.blade.php are left caller-less.
- [Phase 12-02]: NoNativeDialogTest's static x-ref/$refs pairing scan extended to recognize the shared _save-warning-modal partial's 'formRef' => '...' @include argument as proof of pairing, since its x-on:click target is a Blade variable, not a literal name.
- [Phase ?]: [Phase 12-03]: gradableTotal = graded + submittedUngraded only (excludes in_progress) — an unsubmitted attempt is not yet 'needing grading'.
- [Phase ?]: [Phase 12-03]: Reused AttemptVoider::summarize() as the grading page's progress aggregate instead of a new query — one grouped COUNT, one source of truth for graded/ungraded counts.
- [Phase 12-04]: Grading-progress aggregate computed once per hub load via withCount (never per-attempt); reset-confirm counts reuse AttemptVoider::summarize() per exam so the tab and editor can never disagree.
- [Phase 12-04]: Retired the interim top-nav Exams link (folded into the subject-scoped Exams tab) and repointed ReachabilityTest/ToastTest off the now-redirecting exams.index.
- [Phase 12-05]: Reorder/shuffle never call AttemptVoider or delete anything — display-order-only mutations do not trigger EDT-04's warn-and-void.
- [Phase 12-05]: Move-up/down only, no drag-and-drop (Decision #8); shuffle is a one-shot authoring-time POST, never re-derived at read/take time (Decision #2).
- [Phase ?]: 13-01: Non-enrolled students are 403'd via abort_unless() on the resolved enrolled Section, mirroring Student ExamController@show's idiom, rather than a new Policy.
- [Phase 13]: 13-02: Hoisted $answeredCount above the sticky header so the header progress line and the submit-confirm modal share one server-rendered fallback computation
- [Phase 13]: 13-02: badgeClasses() decoupled from the bucket-driven sr-only announcement thresholds (300s/60s, unchanged) -- red now triggers independently at 600s remaining (TAK-11), keeping the final-minute pulse
- [Phase 13]: 13-02: Reworded an instructions-modal bullet to avoid the literal substring 'shuffle' after it tripped the plan's own TAK-12 no-randomization regex guard as a false positive
- [Phase 14-01]: Task 3 (visual dark-mode legibility walkthrough) deferred, not approved — user AFK with no browser available; joins the milestone's existing deferred human-verification items. Tasks 1-2 (all colour-pairing fixes + DarkModeContrastTest) complete and committed; plan's buildable work is done.
- [Phase 14]: Help button styled identically to the theme toggle so both read as a matched utility-control pair beside each other in the top bar
- [Phase 14]: Manuals rebuilt as wiki-style docs with a sticky topic-index sidebar and cross-links, replacing the stale v2.0 linear manuals; topic taxonomies map 1:1 to shipped screens
- [Phase 14]: 14-03: UserFactory::student() rebuilds name from firstName()+lastName() instead of fake()->name() -- Faker's own default Person formats occasionally prepend a Dr./Mr./Mrs. title, which would silently break SEED-01's title-exclusivity rule
- [Phase 14]: 14-03: All past-semester dating in DatabaseSeeder routes through App\Support\Semester's startsAt()/endsAt(); the seeder file is negative-grep-clean of subMonths/subYears
- [Phase 14]: Task 3 (php artisan dusk real-browser run) deferred, not approved — headless environment has no Chrome/Herd; Tasks 1-2 (Dusk install, .env.dusk.local + DatabaseTruncation, both flow tests) complete and committed.
- [Phase 14]: .env.dusk.local added to .gitignore (Rule 2) — carries the same APP_KEY/DB credentials as .env; this repo ships to a public GitHub URL.

### Pending Todos

None yet.

### Blockers/Concerns

- **Phase 10 carries all three deferred design decisions** and must not be planned before `/gsd-discuss-phase 10` resolves them: (a) *auto-assignment scope* — drop the `exam_section` pivot vs. keep-and-auto-populate (the latter re-opens v2.0's CRITICAL cross-subject leak unless every write is scoped by `exam.subject_id === section.subject_id`); (b) *attempt cancel/reset mechanism* — collides with three shipped invariants (draft-only edit gate, `answers.attempt_id` `cascadeOnDelete()`, `attempts.unique(exam_id,user_id)`); an orchestrator "soft-void" guess was already retracted for this reason — do not re-guess. (c) *reset granularity* — per-exam vs. also per-student. Phase 10 is the highest-risk phase in v3.0.
- **Phase 10 acceptance needs an explicit negative test** ("a student in Subject Y's class does NOT see Subject X's exam"). A positive "exam appears" test does not prove the leak stayed closed.
- **Phase 9's UI-03 is a verified blocker, not a preference.** Flowbite 4's semantic tokens (`bg-brand`, `text-heading`, …) ship in a Tailwind-v4-only `@theme{}` block and currently emit **zero CSS** under Tailwind 3 — the failure mode is an unstyled page, not a build error. Verify by grepping the compiled CSS, never by eyeballing.
- **Phase 11's NAV-04 runs the reachability audit in both directions.** The restructure *removes* working navbar links; enumerate every clickable `route(...)` in `resources/views` today and confirm each survives, rather than only ticking off v3.md's tree. Results and help/manual are the named at-risk destinations.
- **Phase 14's Windows/Herd Dusk behavior is LOW-MEDIUM confidence** (community-sourced ChromeDriver/`APP_URL` drift) — verify hands-on during planning, not from docs alone.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 260719-3d2 | Exam-editor: AJAX per-question Save (no page reload) | 2026-07-19 | f2c4bba | [260719-3d2-exam-editor-ajax-save-questions](./quick/260719-3d2-exam-editor-ajax-save-questions/) |
| 260719-qef | Exam-editor question-form fixes (Alpine attribute break) + AJAX grade save | 2026-07-19 | f6cd9ed | [260719-qef-exam-editor-form-fixes](./quick/260719-qef-exam-editor-form-fixes/) |
| 260720-dep | README: "Deploying to a Server" section (production deploy steps) | 2026-07-20 | _this commit_ | [260720-dep-readme-deployment-section](./quick/260720-dep-readme-deployment-section/) |

## Deferred Items

Items acknowledged and carried forward from the v2.0 milestone close (2026-07-17), plus two
v3.0 items from Phase 14. All are **human-verification** items — none is a code defect, and none
blocked the v2.0 audit (22/22 requirements satisfied, 294 passing tests), the Phase 14-01
buildable-work close (446/446 passing tests), or the Phase 14-04 / v3.0-milestone buildable-work
close (454/454 passing tests).

| Category | Item | Status | Deferred At |
|----------|------|--------|-------------|
| uat_gap | Phase 08 — `08-UAT.md`: 3 pending scenarios (AVL-05 `beforeunload` dialog appears ×1, stays silent on intentional submit + auto-submit ×1, DEL-04/05 manual read-through ×1). Genuinely not automatable — Dusk does **not** change this (Decision #6). | deferred | v2.0 close |
| verification_gap | Phase 08 — `08-VERIFICATION.md` [human_needed]. **Not a gap:** 24/24 must-haves verified, 0 failed. Reflects only the 3 UAT items above. | deferred | v2.0 close |
| uat_gap | Phase 06 — `06-UAT.md`: 5 pending scenarios. **Pre-existing, v1.0-era** — carried forward untouched. | deferred | v2.0 close |
| verification_gap | Phase 06 — `06-VERIFICATION.md` [human_needed]. **Pre-existing, v1.0-era** — carried forward untouched. | deferred | v2.0 close |
| uat_gap | Phase 14-01 — FIX-02 dark-mode legibility sweep, Task 3 (visual walkthrough). **CLOSED 2026-07-19: user confirmed dark mode OK** after the milestone-close UI review fixed one real dark-on-dark page (lecturer/subjects/create) + a dropdown dark arm. | ✅ done | 2026-07-19 |
| uat_gap | Phase 14-04 — TEST-01..04 Laravel Dusk browser run. **CLOSED 2026-07-19: `php artisan dusk` PASSES — 2 tests, 9 assertions, in a real Chrome window against Herd.** Path to green: created + migrated the separate `yp-student-exam-dusk` DB; installed the matching ChromeDriver (`dusk:chrome-driver --detect`, Chrome 150) after a 151/150 version-mismatch; fixed one test bug (LecturerFlowTest clicked a non-existent "Manage" label instead of the subject-name link — reachability was always fine). `php artisan test` stays green and browser-free (460/460). Only the Decision #6 native `beforeunload` prompt (below) remains manual. | ✅ done | 2026-07-19 |
| uat_gap | Decision #6 native `beforeunload` page-leave confirmation on the student take-exam page — un-automatable (ChromeDriver auto-dismisses before Dusk's dialog API sees it). One quick manual browser check remains. | deferred | 2026-07-19 |

**Acknowledged at v3.0 milestone close (2026-07-19):** the three items above (dark-mode walkthrough, Dusk-on-Chrome run, native `beforeunload`) plus the carried-over Phase 08/06 UAT were acknowledged and deferred per the milestone audit (`passed_with_tech_debt`). None is a code defect.

**To close these:** `/gsd-verify-work 8` (and `/gsd-verify-work 6`) with a browser available. For the Phase 14-01 item, run the walkthrough steps in `14-01-SUMMARY.md`. For the Phase 14-04 item, run `php artisan dusk` on a machine with Google Chrome installed (the DB is already provisioned). These are the last items before the public GitHub push.

## Session Continuity

Last session: 2026-07-18T14:48:53.521Z
Stopped at: Completed 14-04-PLAN.md (Tasks 1-2 + Task 3 buildable portion; Task 3 php artisan dusk browser run deferred). Phase 14 and v3.0 milestone code-complete pending 2 deferred manual-verification items.
Resume file: 
None

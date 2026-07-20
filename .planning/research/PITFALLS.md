# Pitfalls Research

**Domain:** v3.0 Workflow Restructure & UX Polish — adding features to a shipped Laravel 11 + Breeze exam portal (294 passing tests, `migrate:fresh --seed` clean)
**Researched:** 2026-07-17
**Confidence:** HIGH (grounded directly in this codebase's models/policies/migrations/seeder — not generic Laravel advice)

This file mines `.planning/RETROSPECTIVE.md`'s v2.0 lessons hard and re-applies them to v3's specific
feature list in `.planning/v3.md`. Every pitfall below cites the actual file/line/invariant it threatens.

## Critical Pitfalls

### Pitfall 1: "Saving cancels attempts" / "reset submission" breaks three load-bearing invariants at once

**What goes wrong:**
v3 wants two destructive actions — (a) saving the exam editor cancels all previous student attempts,
(b) an explicit "reset exam submission" action — added on top of a system that was **built to make this
impossible**. Today: `UpdateExamRequest::authorize()` returns `! $exam->is_published` (D-06,
`app/Http/Requests/Lecturer/UpdateExamRequest.php:22-25`) — a published exam literally cannot be edited.
`ExamController::unpublish()` refuses to unpublish once `$exam->attempts()->exists()` (`app/Http/Controllers/Lecturer/ExamController.php:135-137`), with an explicit comment that an attempted exam is
"locked" so a lecturer can't "change correct answers / points / delete questions after grading —
desyncing the computed scores from the results breakdown (review HIGH-02)". v3 wants to lift exactly
this lock. Naively doing so reopens HIGH-02 (a real, previously-caught defect class) plus three new ones:
- **Destroying graded work with no distinction from destroying unstarted attempts.** `answers.attempt_id`
  is `cascadeOnDelete()` (`database/migrations/2026_07_15_100010_create_answers_table.php:16`) — a hard
  delete of an `Attempt` row silently deletes every `Answer` row with it, including `score`/`graded_by`/
  `graded_at` on already-graded essay answers. "if any student attempted previously, warning will pop up"
  must not use the same generic copy for "0 in-progress attempts, safe" vs "12 graded results, permanently
  destroying real grading work."
- **The single-attempt unique index blocks re-attempt after "reset."** `attempts` has
  `unique(['exam_id', 'user_id'])` (`database/migrations/2026_07_15_100009_create_attempts_table.php:25`).
  If "reset" soft-deletes/marks-cancelled instead of hard-deleting (to preserve an audit trail), the
  student's new attempt still collides with the old row unless the unique index is redesigned — MySQL has
  no partial/filtered unique index, so `SoftDeletes` alone does **not** free the slot; `deleted_at` must be
  folded into the composite unique key, or the constraint dropped in favor of an app-level "at most one
  active attempt" invariant enforced under a lock (see RETROSPECTIVE's "lock the whole invariant" lesson).
- **Racing a live attempt's timer.** `Attempt::finalizeIfExpired()`/`finalize()` already solve exactly this
  class of race via `lockForUpdate()` inside `DB::transaction()` (`app/Models/Attempt.php:104-175`) — a
  lecturer's "reset" or editor-save cancellation must route through the **same** lock-then-check-then-write
  primitive, not a separate ad hoc `Attempt::where(...)->delete()`. Without it: a student mid-exam
  (`status = in_progress`, timer still running client-side) gets their attempt deleted or reset out from
  under them while `answer()`/`finalize()` calls are in flight — the next autosave hits a missing row and
  either 500s or, worse, silently creates a fresh row that bypasses `started_at`, orphaning the student on
  a broken timer.

**Why it happens:**
The feature request ("cancel previous attempts on save," "reset submission") reads as a simple destructive
action in isolation. It's only dangerous because it collides with three *already-shipped, deliberately
defensive* mechanisms (draft-only edit gate, cascade-delete FK, single-attempt unique index) that were
built under the opposite assumption: once attempted, an exam and its attempts are immutable.

**How to avoid:**
- Build one shared service (mirroring `AttemptGrader`'s "one place that ever writes `status=submitted`"
  precedent) — e.g. `AttemptCanceller`/`ExamRevision` — that both the editor-save path and the explicit
  "reset submission" action call. Never duplicate the cancellation write in two controllers.
- Inside that service, acquire the same `lockForUpdate()` pattern `Attempt::lockAndFinalize()` uses before
  touching any attempt row, so a racing student submit/autosave and a lecturer reset can't corrupt each
  other.
- Decide explicitly (flag for `/gsd-new-milestone` discuss, do not silently assume): does "cancel" mean
  hard-delete (simple, but destroys graded history and needs the unique-index rework above) or a new
  `status = 'cancelled'` value excluded from `syncStatus()`'s status checks and from the "already attempted"
  count, with the unique index redesigned to allow one new active attempt per (exam, user)? Given this
  project's schema-break precedent (v2.0 edited migrations in place rather than adding alter migrations),
  a clean redesign of the unique constraint is more in-character than a soft-delete hack.
- Differentiate warning copy by what's actually at stake: "N students have started but not finished — their
  progress will be lost" vs. "N students have been graded — their scores will be permanently deleted."
- Explicitly forbid: editor-save silently cancelling attempts with no confirmation step; a "reset" that
  bypasses the lock and directly deletes/updates rows a live request might be mid-transaction with.

**Warning signs:**
A PLAN.md or code diff that (a) adds a `$exam->attempts()->delete()` call without touching
`UpdateExamRequest::authorize()`'s draft-only gate at all (meaning it's dead code — edits still can't
reach a published exam), (b) deletes/updates `Attempt` rows without `lockForUpdate()`, or (c) doesn't
account for the `answers` cascade delete when describing what "reset" preserves.

**Phase to address:**
Early — before the exam editor UI work, since the editor's save button behavior depends on this decision.
This is schema/invariant-level work, same category as v2.0's Phase 7 schema break, not a UI phase.

---

### Pitfall 2: Implicit "auto-assign all enrolled students to all active exams" reopens the cross-subject leak v2.0 fixed

**What goes wrong:**
v2.0's Phase 7 shipped a CRITICAL fix (`ExamController::show()`, `app/Http/Controllers/Lecturer/ExamController.php:60-69`): the section picker offered for exam assignment is explicitly filtered to
`Section::where('subject_id', $exam->subject_id)`, with a comment that not doing so lets "an exam ... be
assignable to a section belonging to a different subject, since that would make it visible/takeable by
students enrolled in that foreign-subject section." `Exam::scopeVisibleTo()` (`app/Models/Exam.php:97-105`) is the **single** predicate this depends on — it walks `sections.enrollments` with no subject
filter of its own, trusting that the `exam_section` pivot only ever contains same-subject pairs.
v3.md's exams tab says "all student enrolled automatically assigned to all active exam in this list" —
if "this list" means *all exams in the system* (not scoped to the section's subject) and "all student
enrolled" means *all students with any active enrollment* (not scoped to that exam's subject), the
auto-assignment write would populate `exam_section` (or an equivalent bulk-enroll step) with cross-subject
pairs — silently reopening the exact leak the `Section::where('subject_id', ...)` filter exists to close,
because that filter lives in the *lecturer's manual picker UI*, not as a DB constraint. There is no
`CHECK`/trigger anywhere enforcing `exam.subject_id === section.subject_id` for `exam_section` rows — the
invariant is enforced entirely by "only ever write same-subject pairs," which auto-assignment must not
violate.

**Why it happens:**
"Auto-assign all enrolled students to all active exams" is easy to read as a system-wide join
(`Exam::where('is_published', true)` × `all enrolled students`) rather than the subject-scoped join the
domain actually requires (`exam.subject_id === section.subject_id`, then that section's enrolled students).
The word "all" in both halves of the sentence invites exactly the mistake v2.0 fixed once already.

**How to avoid:**
- The auto-assignment write must go through `exam.subject_id` → sections of that subject → those
  sections' `Enrolled` students, never a flat "all active exams" × "all enrolled students" cross join.
  Concretely: for each published/active exam, attach it only to sections where
  `section.subject_id === exam.subject_id` (this may just mean: an exam assigned to a class's exams tab is
  implicitly assigned to *that class's own subject's currently-active sections*, not globally).
- Re-run (or extend) the equivalent of v2.0's cross-subject regression test against the new
  auto-assignment path specifically — don't assume the old manual-assignment test coverage protects a new
  code path that never calls `ExamAssignmentController::update()`.
- If "all active exam" literally means every exam without an explicit assignment step at all (auto-assign
  entirely replaces the manual `exam_section` sync UI), then `Exam::scopeVisibleTo()`'s
  `whereHas('sections.enrollments', ...)` traversal needs to be re-examined for whether `exam_section` is
  even still the right join table, or whether visibility collapses to "same subject" directly — a design
  decision to flag explicitly rather than silently keep the pivot and half-populate it.

**Warning signs:**
A migration/service that does `Exam::where('is_published', true)->get()` and `User::role('student')` (or
`Enrollment::where('status', Enrolled)`) without ever joining through `subject_id`, or a feature test that
only asserts "a student sees exams for their own section" without a same-project-precedent negative
assertion ("a student in Section A of Subject X does NOT see an exam only assigned to Subject Y").

**Phase to address:**
Same phase as the exams-tab CRUD rework (Class Management → exams tab). Must ship with an explicit
negative regression test mirroring v2.0's Phase 7 fix, not just a positive "exam appears" test.

---

### Pitfall 3: Semester date math — year rollover, the August gap, and "current semester" at the boundary

**What goes wrong:**
v3.md's semester rule is unambiguous on paper but has several concrete edge cases this codebase doesn't
yet handle anywhere (there is currently no semester-boundary logic at all — `Section` only stores
`year`/`semester`/`sequence` as plain integers with no derived "is this the current semester" concept):
- **S1 crosses a calendar year boundary** (Sep of year Y → Feb of year Y+1). A section seeded/created as
  `year = 2026, semester = 1` must resolve to the window **2026-09-01 through 2027-02-28/29** — if the
  date range is computed as `Carbon::create($year, 9, 1)` through `Carbon::create($year, 2, ...)` without
  incrementing the year for the end date, the window inverts (end before start) or silently truncates to
  a few months.
- **February's last day varies by leap year** — 2027-02-28 vs., e.g., 2028-02-29. A hardcoded
  `->day(28)` instead of `->endOfMonth()` silently drops a day in leap years.
- **August is in neither semester.** S2 ends July 31; S1 begins September 1. If "current semester" logic
  is written as `if (now < s1_end) return s1; else return s2;` (a naive two-branch check) it will
  incorrectly resolve August into one of the two rather than returning "no current semester" / "between
  semesters" / defaulting to the *next upcoming* one. v3.md's dashboard cards ("total classes assigned for
  this semester and future semester", "total subjects enrolled this semester") need an explicit answer for
  what "this semester" means when today is in August — silently picking S2 (just-ended) vs. S1
  (not-yet-started) changes what a lecturer/student sees on first login in that month, and nothing in
  v3.md specifies which.
- **Timezone.** `now()` uses the app's configured timezone (check `config/app.php`); if semester
  boundaries are computed with UTC dates but compared against a `now()` in a different offset, a section
  can appear to open/close up to a day early or late right at the boundary — same class of bug the
  existing `Section::windowStatus()`/`Exam::availabilityState()` half-open-interval methods already had to
  get right for `opens_at`/`closes_at` (`app/Models/Section.php:85-97`, `app/Models/Exam.php:129-141`).
  Follow that established half-open `[start, end)` pattern for semester windows too, rather than
  reinventing date-inclusive/exclusive logic.

**How to avoid:**
- Add one canonical helper (e.g. `Semester::forDate(Carbon $date): array{year:int, semester:int}` or a
  `Semester` value object/enum-adjacent class) that is the **single** place semester↔date-range math
  happens — every dashboard card, "current semester" filter, and "hide past semesters" toggle must call
  through it, exactly like `Exam::scopeVisibleTo()` is the single predicate for exam visibility. Do not let
  each view independently compute `now()->month >= 9`.
- Represent S1's end explicitly as `{year: Y+1, month: 2, day: lastOfFeb(Y+1)}` — never `{year: Y}` for
  the S1 end date.
- Write the August case as an explicit, named test: "date is 2026-08-15 → current semester is [defined
  behavior]" — pick one deliberately (most natural for a dashboard: treat August as belonging to the
  *upcoming* semester, i.e., S1 of the current year, since enrollment/prep for the new term is the
  meaningful "current" context) and assert it, rather than leaving it to fall out of whichever branch
  order a naive if/else happens to use.
- Test both a leap-year S1 (Feb 29 end) and a non-leap-year S1 (Feb 28 end) explicitly.

**Warning signs:**
Any date arithmetic using `->month(2)->day(28)` literally instead of `->lastOfMonth()`/`->endOfMonth()`;
any semester-range helper that takes only `$semester` (1 or 2) without a `$year`, since S1's start and end
years differ; a "current semester" function with no test for a date in August.

**Phase to address:**
Foundational — before the dashboard cards, subject-list semester grouping, and section CRUD forms that
all depend on this. Should land as its own small phase or the first slice of the semester-model phase,
since nearly every other v3 UI feature (dashboard stats, subject grouping, hide/unhide past semesters)
reads through it.

---

### Pitfall 4: The navigation restructure repeats "existence ≠ reachability" — this time by *removal*, not omission

**What goes wrong:**
RETROSPECTIVE.md's sharpest v2.0 lesson was a feature (student result page) that existed — route, policy,
view, tests — but was unreachable because nothing linked to it. v3's navigation restructure inverts the
failure mode: it **removes/restructures already-reachable, already-working links** (today's top navbar in
`resources/views/layouts/navigation.blade.php:14-45` links directly to `lecturer.subjects.index`,
`lecturer.sections.index`, `lecturer.exams.index`, `lecturer.help.show` / the student equivalents) in favor
of the new nested hierarchy (dashboard+subject-list landing page → class/subject management → exam
management → exam editor/grading; class → exam list → take exam). Every one of those currently-linked
routes must reappear reachable somewhere in the new hierarchy, or v3 recreates the exact bug class it's
supposedly distinct from: a working feature (results page, help/manual page, sections CRUD) becomes
orphaned because the old navbar link was deleted and no new entry point was added in its place. The
explicit risk items called out in v3.md itself: **results** (`student.results.show`,
`lecturer.results.index/show` — currently linked from the navbar's Help/Exams areas indirectly) and
**manuals** (`lecturer.help.show`, `student.help.show`, moving to a "wiki style" manual, explicitly asked
to sit "nearby those light dark toggle") are named as features that must survive the restructure, not
just be rewritten.

**Why it happens:**
A navigation rewrite is naturally scoped as "build the new hierarchy" — verifying *that* is complete is
easy (walk the tree in v3.md, tick off each node). It's easy to forget the audit runs the other direction
too: enumerate every route currently reachable from `navigation.blade.php` and *every* other place a link
exists (e.g., "N of M answered" submit modal, dashboard cards, result-page cross-links), and confirm each
has a path in the new structure — not just that the new structure's own listed nodes are present.

**How to avoid:**
- Before removing `navigation.blade.php`'s old link set, grep the whole `resources/views` tree for every
  `route('...')` call that currently renders a link/button a user can click (not just the navbar) and
  build a checklist of destinations. Cross-check the new hierarchy against that checklist, not just
  against v3.md's tree diagram.
- Specifically verify: the grading-progress view stays reachable from the exams tab (v3.md says so
  explicitly); the user manual stays reachable via the help button beside the dark-mode toggle (v3.md says
  so explicitly, and this is a *relocation*, not a new feature — the old `lecturer.help.show`/
  `student.help.show` routes/content need a new link, not a new implementation); the result page stays
  reachable from wherever "class → exam list → take exam" shows "has been taken/graded" status (v3.md's
  Class section says exactly this markers requirement — make sure the marker is also a *link*, per the
  existing-and-relearned lesson, not just a status label).
- Use the same verification discipline RETROSPECTIVE.md prescribes: verify a **navigable path** (click from
  the landing page to the destination in a real browser/Dusk test), not "the route exists and the view
  renders."

**Warning signs:**
A PLAN.md/SUMMARY.md that lists new routes added but never lists old routes it makes unreachable; a Dusk
suite that visits pages directly by URL (`$browser->visit(route('...'))`) instead of clicking through the
actual navigation the restructured UI provides — the latter is the only thing that actually proves
reachability, and this project now has Dusk specifically to prove exactly this class of thing.

**Phase to address:**
The navigation-restructure phase itself needs an explicit "old destinations still reachable" checklist as
part of its own acceptance gate — not deferred to a later audit phase, since by then the old navbar code
is gone and the gap is easy to miss without a diff to compare against.

---

### Pitfall 5: Laravel Dusk on Windows/Herd — driver drift, the dev DB, and no CI safety net

**What goes wrong:**
This is a genuinely new tool for this project (no `laravel/dusk` in `composer.json`/`composer.lock`, no
`.env.dusk.local`, no `tests/Browser` directory exist yet) being added on Windows via Herd, which has
several well-known failure modes:
- **ChromeDriver/Chrome version drift.** `php artisan dusk:chrome-driver` pins a driver version at
  install time; any subsequent Chrome auto-update on the dev machine desyncs it, and Dusk fails with a
  version-mismatch error unrelated to any actual test bug. A stale ChromeDriver process left running in
  the background is used by new test runs even after reinstalling the driver, producing confusing
  "it still fails after I reinstalled" reports.
- **`APP_URL` mismatch.** Dusk's browser literally navigates to `config('app.url')`; on Herd this must
  match the actual `*.test` (or configured) hostname/port the site is served on, not `localhost` — a
  mismatch doesn't error clearly, it just times out waiting for elements that never load because the
  wrong page loaded.
- **A separate Dusk environment/database is mandatory, not optional, here.** This project's dev DB is a
  named, real MySQL database (`yp-student-exam` via Herd) carrying the demo seed the README documents
  (fixed lecturer/student credentials). `DuskTestCase`'s default `RefreshDatabase`/`DatabaseMigrations`
  traits will migrate-fresh **whatever database the running app's `.env` points at** unless a dedicated
  `.env.dusk.local` (pointing at a separate `*_dusk` database) is created — without it, running `php
  artisan dusk` wipes the developer's manually-seeded dev database mid-session. Given this project's own
  seeder comment explicitly protects against "a re-seed must not clobber a reviewer's manual edits"
  (`database/seeders/DatabaseSeeder.php:32`), an unconfigured Dusk run undoing that same protection via a
  different code path is a real regression risk specific to this project.
  For local dev this is best kept minimal-but-explicit: an `.env.dusk.local` with its own `DB_DATABASE` is
  enough — no CI infrastructure is required for a single-developer graded deliverable.
- **Flaky waits.** Dusk tests that assert on Alpine-driven UI (the countdown timer, the toaster, the
  stepper) without explicit `->waitFor(...)`/`->waitUntil(...)` calls race Alpine's `x-init`/reactive
  updates and intermittently fail — especially the take-exam timer and the 10-minute toaster trigger,
  which are the two Alpine-heaviest new UI pieces v3 adds.
- **No CI here** (this is a local-clone graded deliverable, not a hosted CI pipeline) — Dusk failures are
  invisible until someone runs the suite locally; there is no safety net catching a broken Dusk test in
  the same way the 294-strong PHPUnit suite currently is (presumably) run before each commit/push. This
  means Dusk suite health is easy to silently regress unless it's run as part of the same verification
  gate as the rest of the suite before each phase closes.

**How to avoid:**
- Pin the Dusk install steps in the README (parallel to the existing DB-setup documentation) —
  `composer require --dev laravel/dusk`, `php artisan dusk:install`, `php artisan dusk:chrome-driver
  --detect`, and the `.env.dusk.local` setup with a distinct database name.
- Explicitly create `.env.dusk.local` pointing at a dedicated `yp-student-exam-dusk` (or similar) database
  before writing the first Dusk test, and document `php artisan dusk:chrome-driver --detect` as a
  standard troubleshooting step for version drift.
- Prefer `->waitFor()`/`->waitForText()` over fixed `->pause()` sleeps for every Alpine-dependent assertion
  (timer display, toaster appearance/dismissal, stepper checkmarks).
- Keep the Dusk suite small and targeted at the browser-only behaviors PHPUnit genuinely cannot cover
  (the `beforeunload` warning AVL-05 already flagged as deferred in v2.0, the 10-minute red-timer toaster,
  drag/shuffle interactions) rather than duplicating everything PHPUnit Feature tests already assert —
  this project's existing 294 tests are the correctness net; Dusk's job here is the browser-only gap.

**Warning signs:**
A Dusk test suite that shares `.env`/`DB_DATABASE` with the main dev environment; any Dusk test using
`sleep()`/`->pause(n)` instead of `->waitFor`; a README with no Dusk setup section despite Dusk being a
graded-deliverable dependency now.

**Phase to address:**
Set up the Dusk scaffold (`.env.dusk.local`, chrome-driver, README section) as its own small early step,
before any phase that plans to *write* Dusk tests — the dedicated-database mistake is exactly the kind of
thing that's cheap to prevent up front and expensive (a wiped dev DB) to recover from after the fact.

---

### Pitfall 6: Flowbite 4 semantic token classes (`bg-brand`, `text-heading`, `bg-neutral-primary-soft`, `border-default`, `rounded-base`) don't resolve in this Tailwind 3 setup — and fail silently

**What goes wrong:**
v3.md's login-page snippet uses Flowbite's newer semantic design-token utility classes:
`bg-neutral-primary-soft`, `text-heading`, `bg-neutral-secondary-medium`, `border-default`,
`border-default-medium`, `focus:ring-brand`, `text-body`, `bg-brand`, `bg-brand-strong`,
`focus:ring-brand-medium`, `rounded-base`, `shadow-xs`, `text-fg-brand`, `focus:ring-brand-soft`. This
project's `package.json` does have `"flowbite": "^4.0.2"` installed, but `tailwind.config.js`
(`tailwind.config.js:1-25`) only registers `flowbitePlugin` and `forms` with **no theme token
extension/CSS variables defined anywhere** — `resources/css/app.css` is the three bare `@tailwind` directives
with no custom properties. Flowbite 4's token classes (`bg-brand`, `text-heading`, etc.) are part of its
newer semantic-token system, which requires either a specific preset/CSS-variable layer wired into
Tailwind's `theme.extend.colors` (mapping `brand`/`heading`/`neutral-*`/`default` to actual color values)
or importing Flowbite's token CSS — neither is present. **Every one of those classes will simply not match
any Tailwind utility and be silently dropped** — Tailwind doesn't error on an unrecognized class name, it
just emits no CSS for it. The result: the login page renders with default browser form styling (no
background, no border, no button color) rather than throwing any build error or console warning. This is
the single most likely-to-be-missed bug in the whole milestone precisely *because* it produces a page that
"looks bad" rather than "is broken" — easy to misdiagnose as "needs more Tailwind classes" rather than
"these classes don't exist in this build."

**How to avoid:**
- Before writing any view using these token classes, run a build (`npm run build` or `npm run dev`) and
  grep the compiled CSS output for one of the distinctive class names (e.g. `bg-brand`) to confirm it was
  actually generated — don't trust visual inspection alone, since a *close-looking* fallback (browser
  default button styling, etc.) can be mistaken for "mostly working."
- Decide explicitly: either (a) define the missing tokens in `tailwind.config.js`'s `theme.extend` (map
  `brand`/`heading`/`body`/`neutral-primary-soft`/`default`/etc. to concrete color values matching this
  project's actual palette), or (b) rewrite the v3.md snippet's classes to this project's existing
  Tailwind-native equivalents (`bg-white`/`bg-gray-800`, `text-gray-900`/`dark:text-white`, etc., matching
  the pattern already used throughout `resources/views`, e.g. `navigation.blade.php`). Given the rest of
  the codebase (416 occurrences across 28 files) already uses plain Tailwind grayscale + `dark:` variants
  consistently, translating the snippet to that existing vocabulary is more in-character than introducing
  a second, partially-wired design-token system — but this is a design decision to surface explicitly, not
  silently pick.
- If tokens are defined, centralize them once in `tailwind.config.js`/a CSS `:root` block — not per-view —
  so `dark:` variants of each token are defined exactly once.

**Warning signs:**
A screenshot/manual QA pass of the login page showing plain, unstyled form fields; `grep -r "bg-brand\|
text-heading\|neutral-primary-soft" resources/views` returning hits with no matching definition anywhere
in `tailwind.config.js` or `resources/css/app.css`.

**Phase to address:**
Resolve before or at the very start of the UI-system-unification phase (login/landing page restyle) — every
other v3 UI phase (dashboard, exam editor, take-exam) will be tempted to reuse whatever token vocabulary
this phase establishes, so getting it wired (or explicitly rejected in favor of the existing gray-scale
system) early prevents the same silent-failure class from spreading.

---

### Pitfall 7: Dark-mode contrast bugs recur because `dark:` variants are still added view-by-view, not systematically

**What goes wrong:**
v3.md's bugfix note is explicit: "on exam editor, some text remain dark on dark mode, also check
throughout site for dark mode compatibility." RETROSPECTIVE.md context (surfaced in the user's own hint)
says v2.0 shipped `dark:` variants view-by-view, and a blanket find/replace once wrongly added
`dark:text-gray-200` to a light-tinted badge — i.e., the *mechanism* that causes this bug class is already
known: dark-mode support here is not a single themeable layer, it's ~416 individually hand-placed `dark:`
utility occurrences across 28 Blade files (confirmed: `grep -rn "dark:" resources/views` → 416 matches,
28 files). Any new view added in v3 (exam editor's merged two-tab layout, the wiki-style manual, the
stepper, the toaster, the dashboard cards) that copies an existing view's classes without also copying its
`dark:` counterpart reproduces the identical bug. Components like `x-status-pill`
(`resources/views/components/status-pill.blade.php`) that encode a *semantic* color (e.g., a "light"
warning tint) are exactly the kind of place a rote find-and-replace of `text-gray-*` → `dark:text-gray-200`
breaks, because the light-mode color wasn't gray to begin with — the replace target pattern doesn't
generalize.

**Why it happens:**
There's no single source of truth for "what does this semantic color look like in light vs dark mode" —
every Blade file independently pairs a light utility with a hand-picked `dark:` utility. A component with
a non-gray light-mode background (status pills, badges, any colored card) needs a *different* dark-mode
pairing than a plain white/gray page background does, so a single global find-replace across the whole
`resources/views` tree cannot be correct for all of them simultaneously.

**How to avoid:**
- Don't repeat the blanket find/replace approach. Instead: (1) enumerate every reusable Blade *component*
  (`resources/views/components/*`) and give each one a definitively correct light+dark pairing once, since
  components are reused everywhere and get the highest leverage; (2) for one-off page markup, audit by
  *view*, not by *class name* — visually toggle dark mode on every distinct page (not just the exam editor
  the bug report named) rather than trusting a text search to find every offender, since the actual bug is
  semantic (wrong pairing) not syntactic (missing `dark:` prefix entirely — some occurrences may have a
  `dark:` class that's simply the wrong color, which no grep for "missing dark:" will catch).
  A Dusk test (or even a manual pass) that toggles the dark-mode button and screenshots every route in the
  new navigation hierarchy is the systematic check the "check throughout site" instruction is asking for
  and that no single find/replace can substitute for.
- Specifically re-verify `x-status-pill` and any other component that encodes non-neutral semantic color
  (success/warning/danger states) — those are precisely where a generic gray-mapped fix breaks.

**Warning signs:**
Any commit that does a repo-wide `sed`/find-replace on `dark:` classes without per-file review; a
component with a colored (non-gray) light-mode background whose dark-mode variant is still
`dark:bg-gray-*`/`dark:text-gray-*` (a generic gray substitute for what should be a color-appropriate dark
variant).

**Phase to address:**
Pair with whichever phase does the broadest new-view surface (exam editor rework, take-exam page,
dashboard) since that's where new dark-mode debt is most likely to be introduced fresh; run a final
dedicated dark-mode pass as an explicit acceptance-gate item near the end of the milestone covering the
*entire* new navigation tree, not just the exam editor the bug report named.

---

### Pitfall 8: Toaster/alert unification misses the write paths that don't render through a normal Blade page load

**What goes wrong:**
Today, flash messages use `session('status')` with `dark:` classes hand-applied per view (confirmed: 28
files independently reference `dark:` alongside ad hoc status-message markup; no shared toast/alert
component currently exists in `resources/views/components`). No native `alert()`/`confirm()` calls were
found in `resources/views`, but the request explicitly calls for their app-wide removal, implying some
exist elsewhere (client-side JS, or are anticipated for the new confirm-before-destroy flows this
milestone adds — e.g. "reset exam submission... with warning," "saving cancels attempts... warning will
pop up"). Unifying flashes into one toaster component is straightforward for the common case (a controller
redirects with `->with('status', '...')`) but easy to leave gaps in:
- **Validation errors** (`$errors` bag from a failed Form Request) render inline by convention in this
  Breeze-based app, not as a flash — if the new toaster only listens for `session('status')`/similar keys,
  validation failures won't route through the unified system at all, leaving two different alert styles
  coexisting (exactly what v3.md says to avoid: "use same pop up alert style through out the system").
- **The two new destructive-confirm flows** (attempt-cancellation warning, reset-submission warning) are
  *pre-action* confirmations, not post-action flashes — these need a modal/dialog component, not a toast
  (a toast that auto-dismisses is the wrong UI for "are you sure," which needs the user to make a decision
  before anything happens). Conflating "toaster for success/error notices" with "confirmation dialog for
  destructive actions" in a single component will produce a toast that fires *after* a destructive action
  already happened, defeating the point of warning the lecturer first.
- **Alpine-only client-side events** (e.g., the reactive "N of M answered" counter from FIX-01, the
  10-minute-remaining timer warning) need to trigger a toast without a page reload/redirect at all — if
  the toaster is wired only to `session()` flash data, these client-only triggers (which by definition
  never hit a controller redirect) can't reuse it unless the component also listens for a
  window/Alpine-dispatched event, mirroring the pattern FIX-01 already established for the answered-count
  bubble.

**How to avoid:**
- Build one toast component that accepts triggers from **both** a server-rendered flash (`session('status')`
  or similar, read once on page load) **and** a client-dispatched Alpine/window event — so
  Alpine-originated notices (10-minute timer warning) and server-originated notices (created/saved/deleted)
  share the exact same visual component, which is what "same pop up alert style throughout" actually
  requires.
- Route validation-error display through the same visual language (even if triggered differently) so a
  failed form submission doesn't look like a different alert system than a successful one.
- Keep destructive-action confirmation as a distinct modal/dialog component, separate from the toast —
  it's a different interaction pattern (blocking, requires a decision) from a toast (non-blocking,
  informational, auto-dismissing). Both "warning will pop up" requirements in v3.md (editor-save
  cancelling attempts; reset-submission) are confirmation dialogs, not toasts.
- Grep for every current `session('status')`/`session(...)`-style flash and every place a redirect carries
  a message, to build a checklist of call sites the new toaster must cover — then re-check nothing was
  missed after the unification lands, the same "existence ≠ reachability" discipline applied to messaging
  coverage instead of navigation coverage.

**Warning signs:**
A toaster component wired only to a single session key with no handling for `$errors`; a "warning" for a
destructive action implemented as a toast that appears after the delete already ran rather than a
confirm-first dialog; two visually different notice styles still present after the "unification" phase
closes (one old inline flash `<div>`, one new toast).

**Phase to address:**
Its own focused phase (or a clearly-scoped slice of the UI-system phase) with an explicit inventory of
every current flash/alert call site as its acceptance checklist — "toaster added" is not the same
acceptance bar as "every existing alert now uses the toaster and no native alert/confirm remains."

---

### Pitfall 9: Richer seed data breaks idempotency, `migrate:fresh --seed`'s speed, or both

**What goes wrong:**
The current `DatabaseSeeder` (`database/seeders/DatabaseSeeder.php`) is carefully idempotent —
`firstOrCreate` on natural keys throughout, `syncWithoutDetaching`/`sync` for pivots, and an explicit
comment warning "never `updateOrCreate` — a re-seed must not clobber a reviewer's manual edits." v3 wants
substantially more volume: many more lecturers/students with **unique names**, **past semesters** with
**graded exams**, classes filled to **every status** combination, 3-5 more subjects/classes/exams, and a
rule that only lecturers get "Dr"/"PhD" name prefixes. Scaling this up risks breaking exactly the
properties the current seeder protects:
- **Uniqueness of names becomes a generator problem, not a literal-array problem.** The current seeder
  hardcodes 4 named users. "A lot more" lecturers/students with **unique** names either needs a curated
  name list large enough to avoid collisions, or `fakerphp/faker`'s name generator — which does **not**
  guarantee uniqueness by default (`fake()->name()` can repeat, especially at moderate volumes) and needs
  either `fake()->unique()->name()` or a de-dup check; naive use will intermittently produce a duplicate
  name (fine for `name`, since it's not a unique DB column, but breaks the "unique name" *product*
  requirement silently and non-deterministically — the same seed run can pass or fail this informal
  requirement depending on random state, unless `fake()` is seeded deterministically or `unique()` is used
  explicitly).
- **"Only lecturers can have Dr/PhD prefix"** is an easy rule to violate accidentally in a factory `state()`
  callback shared between lecturer/student factories if the prefix logic isn't gated strictly on the role
  argument at the call site (e.g., a shared "random honorific" helper applied before the role is known).
- **Idempotency under volume.** `firstOrCreate` scales fine for a handful of named accounts keyed on
  email, but for bulk "3-5 more subjects/classes/exams" with *graded exam* history (multiple students,
  multiple attempts, multiple graded answers per exam), the natural-key `firstOrCreate` pattern needs a
  key that's actually stable across re-seeds — e.g. subject `code`, section `(subject_id, year, semester,
  sequence)` as already used — but a **generated bulk graded-attempt graph** (many students × many exams ×
  scored answers) needs the same discipline the existing `seedDemoAttempt()` shows (guard child creation
  on `wasRecentlyCreated`, per the comment on `seedExam()` about "no unique index" on questions/options) or
  a re-seed either duplicates rows or throws unique-constraint errors on the second run.
- **Speed.** `migrate:fresh --seed` is the documented clean-clone path (README, PROJECT.md). Every
  additional attempt+answer row created via `AttemptGrader` calls (as the existing pattern already does,
  correctly avoiding simulated HTTP) is still N database round-trips; a seeder generating "past semesters"
  worth of graded exams across many students, done row-by-row instead of batched, can turn a
  sub-few-seconds clean-clone step into something noticeably slower — worth watching, not necessarily
  worth micro-optimizing pre-emptively, but a seeder that takes tens of seconds on a fresh clone is a bad
  first impression for a graded deliverable evaluated by exactly that clean-clone step.
- **"All available statuses"** (draft/active exams, enrolled/withdrawn/rejected enrollments, submitted/
  graded attempts, section open/opening/closed windows) is a combinatorial requirement — it's easy to seed
  volume without actually covering every distinct status value the UI needs to demonstrate, silently
  leaving one status un-demoed (e.g., no example of a *rejected* enrollment in the richer dataset, even
  though volume goes up) unless the seeder explicitly asserts (or is written against) a checklist of every
  enum value needing at least one seeded example.

**How to avoid:**
- Use `fake()->unique()->name()` (resetting Faker's unique-tracking at the start of the run, since it's
  process-global) rather than plain `fake()->name()`, and gate Dr/PhD prefixing at the exact point the
  factory already knows the role (a `lecturer()` factory state, never a shared "maybe add a title" helper
  called before role is fixed).
- Keep every new bulk-seeded entity on the same natural-key `firstOrCreate`/`wasRecentlyCreated`-guarded
  pattern the current seeder already establishes — do not introduce `updateOrCreate` anywhere, per the
  existing explicit comment.
- Write the "cover every status" requirement as an explicit checklist in the seeder's own doc comments
  (mirroring the existing style) enumerating each `EnrollmentStatus`/attempt-status/exam-published/window
  state that must have at least one seeded example — and consider a lightweight assertion/console output
  at the end of `run()` confirming each is present, so a future edit that accidentally removes the one
  "rejected" example is caught immediately rather than silently.
- Batch-insert where the existing "call the real service, don't simulate HTTP" precedent doesn't force
  row-by-row Eloquent creates (e.g., bulk `Option`/`Answer` inserts via `insert()` where no model event or
  relationship-dependent ID is needed at creation time) — but keep using the real `AttemptGrader` calls for
  anything that must reflect actual grading logic, per the existing precedent, rather than trading
  correctness for seed speed.
- Time `migrate:fresh --seed` before and after the volume increase; if it becomes noticeably slow, that's
  a signal to batch inserts, not to abandon idempotency for `insert()`-and-forget bulk writes that skip
  `firstOrCreate` checks entirely on a repeat run.

**Warning signs:**
Running `php artisan migrate:fresh --seed` twice in a row and getting a different result, a unique
constraint violation, or duplicate visible names, the second time; a lecturer or student in the seeded
data with a name prefix that violates the "lecturers only" rule; a "past semester" section whose computed
date window (Pitfall 3's helper) doesn't actually fall in the past because it was built with the same
year-rollover mistake described there.

**Phase to address:**
The seeding-and-demo-data phase, sequenced **after** the semester-model phase (Pitfall 3) — bulk "past
semester" seed data is meaningless/wrong if the semester date-math it depends on isn't already correct and
tested. Should also land after (or alongside) the attempt-cancellation work (Pitfall 1) if seeded data is
meant to demonstrate the reset-submission/cancelled-attempt states.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|-----------------|------------------|
| Hard-delete `Attempt` rows for "reset submission" instead of a `cancelled` status | Simplest code, no unique-index redesign | Destroys graded history permanently, no audit trail, no way to show "this was reset" in a UI later | Never for graded attempts; only defensible for un-started/never-touched attempts, and even then flag it explicitly |
| Reusing the existing gray-scale `dark:` vocabulary instead of wiring Flowbite 4's semantic tokens | Zero new config, consistent with 416 existing occurrences | The v3.md login snippet's classes stop matching the rest of the app's actual rendered look unless someone manually re-authors it | Acceptable and arguably preferable given this codebase's existing convention — but must be a stated decision, not silent divergence from the user's snippet |
| Skipping a dedicated `.env.dusk.local` and pointing Dusk at the main dev DB "just to get started" | Faster first Dusk test | One `dusk` run can wipe the documented demo seed data mid-development | Never — this project's dev DB is a named, seed-documented database; the blast radius is real |
| Seeding "past semesters" by literally reusing today's date math with a `->subMonths(N)` fudge instead of the canonical semester helper | Fast to write | Reintroduces exactly the year-rollover/leap-year bugs Pitfall 3 catalogs, just in seed data instead of live code | Never — route seed dates through the same `Semester` helper live code uses |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|--------------|-----------------|-------------------|
| Laravel Dusk + ChromeDriver on Windows/Herd | Assuming an installed driver stays valid after a Chrome auto-update; leaving a stale driver process running | `dusk:chrome-driver --detect` as a documented troubleshooting step; kill stray `chromedriver.exe` processes before re-running |
| Flowbite 4 (npm package) vs. Tailwind 3 (`flowbite/plugin`) | Assuming installing the npm package alone makes its newer semantic token classes (`bg-brand`, etc.) available | Confirm generated CSS actually contains the class (grep the built `app.css`) before relying on it; define missing tokens in `tailwind.config.js` explicitly or don't use them |
| Herd's `APP_URL`/local domain vs. Dusk's browser navigation target | Leaving `APP_URL` as `http://localhost` when Herd serves the site on a `*.test` domain | Set `APP_URL` (and `.env.dusk.local`'s copy of it) to the actual Herd-served hostname before writing the first Dusk test |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|-----------------|
| Row-by-row Eloquent seeding of bulk "past semester" graded-attempt data | `migrate:fresh --seed` visibly slows down on a clean clone | Batch-insert `Option`/`Answer` rows where no model event is needed; keep `AttemptGrader` calls only where grading logic must actually run | Noticeable once past-semester volume covers many students × many exams; watch it, don't pre-optimize before it's actually slow |
| Dashboard stat cards (classes assigned, enrolled-vs-seats, subjects-enrolled) computed as live N+1 queries per card per page load | Dashboard feels slow as seeded volume grows | Eager-load/aggregate with `withCount`/`selectRaw` sums rather than looping relationships in Blade; this project's existing precedent is "live accessor is fine until it's a measured problem" — apply the same judgment, don't pre-cache | Only worth revisiting if the dashboard is measurably slow with the new higher seed volume, not before |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| A new bulk-write path (auto-assignment, reset-submission, attempt-cancellation) written against a `Pivot`-extending or mass-assignable model without literal keyed arrays | Same class of bug this codebase already documents on `Enrollment` (`$guarded = []` on every `Pivot` subclass) — a forwarded `$request->all()` becomes fully mass-assignable | Any new model extending `Pivot` (or reusing `exam_section`) must get the same explicit "$guarded = [] — always pass literal keyed arrays" doc comment `Enrollment` has, and code review should specifically check every new pivot-touching write for `$request->all()`/`fill($request->input())` |
| "Reset exam submission" / attempt-cancellation actions not re-checked against `ExamPolicy`/per-subject lecturer ownership | A lecturer assigned to Subject A could reset/cancel attempts on an exam belonging to Subject B if the new controller action skips the existing per-subject ownership authorization (SEC-03) that gates other exam-management actions | Route the new destructive actions through the same Form Request `authorize()`/Policy pattern every other exam-mutation endpoint already uses — do not add a new ad hoc route with inline `Gate`-free logic |
| Auto-assignment write path bypassing `AssignExamRequest`'s validation/authorization entirely (since it's now implicit, not a lecturer-submitted form) | An implicit/background assignment step run without going through the same authorization chain could attach exams to sections a lecturer isn't even the owner of, since there's no per-request "acting user" to authorize against | If auto-assignment is triggered by a lecturer action (e.g. flipping exam status draft→active), authorize it against that lecturer's subject ownership at that trigger point, exactly as the explicit `AssignExamRequest` does today — don't treat "automatic" as "unauthenticated/unauthorized" |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|------------------|
| A destructive-action toast (not a confirm dialog) for "saving cancels attempts"/"reset submission" | The lecturer only learns attempts were destroyed *after* it already happened, with no chance to back out | A blocking confirmation modal before the write, distinct from the toaster (see Pitfall 8) |
| Vertical stepper (take-exam) with no persisted "answered" state surviving a page reload/browser crash mid-exam | A student who refreshes mid-exam sees every checkmark reset even though answers were autosaved, causing panic/re-answering | Derive the stepper's checkmark state from the same server-persisted answers the autosave already writes, not from client-only Alpine state that resets on reload |
| "Back" buttons with generic labels despite v3.md explicitly asking for "clear text where will it send you" | Users can't predict where "Back" goes in the new nested hierarchy (dashboard → subject → class → exam is 4 levels deep) | Every back button's label states the actual destination ("Back to Mathematics — Section 2026-2-1"), not a bare "Back" |
| 10-minute-remaining toaster firing every render/poll tick instead of exactly once | Toast spam right when a student most needs a calm, clear warning | Fire it from a one-shot Alpine watcher/flag (mirroring the FIX-01 reactive-counter precedent) that flips once and never re-fires for the same attempt |

## "Looks Done But Isn't" Checklist

- [ ] **Attempt-cancellation/reset:** Verify it doesn't just delete rows and stop — check that a student with a live `in_progress` attempt at the moment of cancellation gets a graceful, correctly-worded outcome (not a raw 404/500 on their next autosave), and that graded answers are never silently destroyed without the warning explicitly saying so.
- [ ] **Auto-assignment:** Verify with a same-subject-only *and* a cross-subject-negative test — "exam visible" alone doesn't prove the leak from v2.0's Phase 7 didn't return.
- [ ] **Semester-scoped dashboard cards:** Verify the August boundary and a leap-year S1 explicitly — "shows correct numbers most months" is not the same as "correct."
- [ ] **Navigation restructure:** Verify every route reachable from the *old* navbar is reachable from the *new* hierarchy by actually clicking through it (or a Dusk test doing so), not just checking the new tree matches v3.md's diagram.
- [ ] **Flowbite login restyle:** Verify by inspecting the *compiled* CSS for the token classes used, not by eyeballing the rendered page — a close-enough browser-default look can pass a casual glance.
- [ ] **Dark mode "site-wide":** Verify by toggling dark mode on every route in the new hierarchy, not just the exam editor the bug report named — the bug is semantic (wrong color pairing), not a simple missing-prefix search.
- [ ] **Toaster unification:** Verify validation-error display and Alpine-only client-side events (timer warning, answered-count) both route through the same visual component as server-flash-driven toasts — "toaster added to the create/save/delete paths" is not full coverage.
- [ ] **Seed data "all statuses":** Verify by enumerating every enum value (`EnrollmentStatus`, attempt status, exam published/window state, section window state) against the seeder's actual output, not by eyeballing seeded volume.
- [ ] **Dusk suite:** Verify it runs clean on a *fresh clone* following only README steps (including `.env.dusk.local` setup) — not just on the machine that originally wrote it.

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|----------------|------------------|
| Cross-subject leak reintroduced via auto-assignment | LOW | Same fix shape as v2.0's Phase 7: scope the assignment write to `exam.subject_id === section.subject_id`; add the negative regression test; re-run `migrate:fresh --seed` to confirm no leaked pivot rows exist in seed data |
| Graded attempts destroyed by an unguarded "reset" before the warning-copy distinction was added | HIGH (data is genuinely gone once cascade-deleted) | If caught before merge: add the distinction and re-test. If caught after real use: no code recovery restores destroyed scores — this is why Pitfall 1's guardrails must land *before* the feature ships, not be retrofitted after |
| Semester date math wrong at the year-boundary/leap-year edge | LOW | Centralize into the single `Semester` helper (if not already done) and fix once; re-seed and re-check every dependent view (dashboard cards, subject grouping) since they all read through the one helper |
| A route orphaned by the navigation restructure | LOW | Add the missing link/entry point in the new hierarchy; this is a Blade-only fix once identified, no data or schema involved |
| Dusk accidentally wiped the shared dev database | MEDIUM | Re-run `migrate:fresh --seed` against the correct database; going forward, set up `.env.dusk.local` before running Dusk again |

## Pitfall-to-Phase Mapping

| Pitfall | Suggested Phase Theme | Verification |
|---------|------------------------|---------------|
| 1. Attempt-cancellation/reset destroys data or races the timer | Exam editor + attempt lifecycle rework (early, schema/invariant-level) | Feature tests: reset while an `in_progress` attempt is live (lock contention), reset on a `graded` attempt (warning copy + no silent data loss), re-attempt after reset doesn't violate the unique constraint |
| 2. Auto-assignment reopens the cross-subject leak | Class Management → exams tab (CRUD + auto-assign) | Positive test (student sees exam via correct subject/section) + explicit negative test (student in a different subject's section does NOT see it), mirroring v2.0 Phase 7's fix |
| 3. Semester date math edge cases | Semester model (foundational, before dashboard/subject-list phases) | Named tests: S1 year-rollover, leap-year Feb 29, August "no current semester" gap, timezone boundary |
| 4. Navigation restructure orphans working features | Navigation restructure phase itself | Checklist audit of every pre-restructure route + a Dusk click-through (not direct-URL-visit) test per major destination |
| 5. Dusk on Windows/Herd setup pitfalls | Dusk scaffold setup (before any phase writes Dusk tests) | `.env.dusk.local` exists and points at a distinct DB; README documents setup; a trivial smoke Dusk test passes on a fresh clone |
| 6. Flowbite 4 token classes don't resolve | Login/landing page restyle (UI-system phase, early) | Compiled CSS grep for the token classes actually used; explicit decision logged (define tokens vs. translate to existing gray-scale vocabulary) |
| 7. Dark-mode contrast recurs | Paired with the largest new-view phase (exam editor/take-exam/dashboard) + a final dedicated pass | Manual/Dusk dark-mode toggle sweep across the entire new navigation tree, not just the exam editor |
| 8. Toaster/alert unification gaps | Dedicated UI-system slice with an explicit call-site inventory | Checklist of every pre-existing flash/alert call site confirmed migrated; validation errors and Alpine-only events confirmed routed through the same component |
| 9. Seeder realism vs. idempotency/speed | Seeding phase, sequenced after the semester-model phase | `migrate:fresh --seed` run twice back-to-back with identical results; enum-coverage checklist confirmed present in output; timed against the pre-v3 baseline |

## Sources

- `.planning/RETROSPECTIVE.md` — v2.0's "existence ≠ reachability," "post-start access is
  ownership-gated," "lock the whole invariant," and "`Pivot` ⇒ `$guarded = []`" lessons, applied directly
  to v3's new destructive/implicit features above (HIGH — this project's own documented history).
- `.planning/v3.md` — the authoritative feature request analyzed pitfall-by-pitfall (HIGH — primary
  source).
- `app/Models/Exam.php`, `app/Models/Attempt.php`, `app/Models/Section.php`, `app/Models/Enrollment.php`,
  `app/Policies/ExamPolicy.php`, `app/Policies/AttemptPolicy.php`, `app/Http/Controllers/Lecturer/
  ExamController.php`, `app/Http/Controllers/Lecturer/ExamAssignmentController.php`, `app/Http/Requests/
  Lecturer/UpdateExamRequest.php`, `app/Services/AttemptGrader.php`, `database/migrations/*`,
  `database/seeders/DatabaseSeeder.php`, `tailwind.config.js`, `resources/views/layouts/
  navigation.blade.php` — read directly to ground every pitfall in this codebase's actual, current
  invariants rather than generic advice (HIGH — primary source, current code).
- [Laravel Dusk | Laravel 11.x docs](https://laravel.com/docs/11.x/dusk) — ChromeDriver management,
  `.env.dusk.local` environment separation (HIGH — official docs).
- General web search cross-referencing Dusk-on-Windows ChromeDriver version-mismatch and `APP_URL`
  configuration issues (MEDIUM — community troubleshooting posts, consistent across multiple independent
  sources, no single authoritative doc for the Windows/Herd-specific driver-drift symptom).

---
*Pitfalls research for: Online Examination Portal v3.0 (Workflow Restructure & UX Polish)*
*Researched: 2026-07-17*

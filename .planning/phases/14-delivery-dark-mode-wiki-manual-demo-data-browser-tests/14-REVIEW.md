---
phase: 14-delivery-dark-mode-wiki-manual-demo-data-browser-tests
reviewed: 2026-07-18T00:00:00Z
depth: standard
files_reviewed: 23
files_reviewed_list:
  - database/seeders/DatabaseSeeder.php
  - database/factories/UserFactory.php
  - tests/DuskTestCase.php
  - tests/Browser/LecturerFlowTest.php
  - tests/Browser/StudentFlowTest.php
  - tests/Browser/console/.gitignore
  - tests/Browser/screenshots/.gitignore
  - tests/Browser/source/.gitignore
  - tests/Feature/DatabaseSeederTest.php
  - tests/Feature/HelpPageTest.php
  - tests/Feature/DarkModeContrastTest.php
  - resources/views/components/secondary-button.blade.php
  - resources/views/layouts/navigation.blade.php
  - resources/views/lecturer/exams/questions/_form.blade.php
  - resources/views/lecturer/help.blade.php
  - resources/views/lecturer/sections/edit.blade.php
  - resources/views/lecturer/sections/index.blade.php
  - resources/views/lecturer/sections/show.blade.php
  - resources/views/lecturer/subjects/edit.blade.php
  - resources/views/student/attempts/show.blade.php
  - resources/views/student/help.blade.php
  - resources/views/student/subjects/index.blade.php
  - resources/views/student/subjects/show.blade.php
findings:
  critical: 0
  warning: 2
  info: 2
  total: 4
status: fixed
resolved: 2026-07-18
resolution: "WR-02 fixed — added orderBy('id') to the bulk-lecturer/student pool queries so the index-slice partitioning in seedBulkSubjects()/seedPastSemester() is deterministic across seed runs (reproducible demo data for a grader). No blockers/critical found: seeder correctness (6/6 DatabaseSeederTest — idempotency, Dr/PhD exclusivity, Semester-derived past dating, full status matrix, no cross-subject FK), Dusk security (separate DB, DatabaseTruncation, gitignored secrets, tests/Browser out of phpunit.xml), and browser-test flows all verified sound. WR-01 (exact-fit pool-size coupling) accepted — not a current bug (30 students, needs 27, margin holds) and the orderBy fix reduces its fragility surface; adding a runtime assertion would be gold-plating on a tested, working seeder. IN-01 (duplicated help ternary) / IN-02 (magic numbers) accepted as harmless. Full suite: 454 passing."
---

# Phase 14: Code Review Report

**Reviewed:** 2026-07-18
**Depth:** standard
**Files Reviewed:** 23
**Status:** issues_found (no blockers)

## Summary

Reviewed the diff from `2da0038` (phase-14 planning commit) to `HEAD`, covering the dark-mode sweep, the two wiki-style manuals, the demo-data seeder expansion, and the new Dusk browser-test harness.

- `database/seeders/DatabaseSeeder.php` + `database/factories/UserFactory.php`: ran `Tests\Feature\DatabaseSeederTest` (6/6 pass, 68 assertions) which independently verifies idempotency-on-repeat-run, title exclusivity (lecturers only), subject-scoping via `firstOrCreate` natural keys, `App\Support\Semester`-derived past dating (no `subMonths`/`subYears`), and full status-matrix coverage (every `EnrollmentStatus`, every `Attempt` status, every `Exam::availabilityState()`). Traced the logic by hand as well; found two non-blocking robustness issues below (fragile index-slicing math), not correctness bugs — everything the tests assert is genuinely true today.
- Dusk config: confirmed `DatabaseTruncation` (not `RefreshDatabase`) in `tests/DuskTestCase.php`; `.env.dusk.local` is listed in `.gitignore` and is **not tracked** (`git ls-files` confirms only `tests/DuskTestCase.php` matches `dusk`); `tests/Browser` is **not** referenced anywhere in `phpunit.xml`, so PHPUnit's default suite stays independent of Dusk. `laravel/dusk` is correctly placed under `require-dev`, not `require`. No secrets committed.
- `tests/Browser/*.php`: both flows click through nav (`clickLink`/`press`/`radio`), use `waitForText(...)` for every state transition, never call `visit(route(...))` mid-flow, and never script the browser's native beforeunload dialog (Decision #6, documented and consistent with the README's "Not automated" callout). All factory states referenced (`->lecturer()`, `->student()`, `->published()->available()`, `->open()`, `->mcq()`, `->submitted()`, `->openText()`) exist in their respective factories.
- Dark-mode view edits: diffed each changed view with the pure-class-churn lines filtered out — confirmed **zero** non-`dark:` line changes in `sections/edit`, `sections/index`, `sections/show`, `subjects/edit`, `student/subjects/index`, `student/subjects/show`. The two views with logic-adjacent changes (`_form.blade.php`, `student/attempts/show.blade.php`) were read in full — no unclosed directives, no variable renames, no broken Alpine bindings.
- No hardcoded secrets, `eval`, unescaped `{!! !!}` output, or debug artifacts (`console.log`/`dd(`/`dump(`/`TODO`/`FIXME`) introduced anywhere in the reviewed diff.

Two warnings below concern seeder robustness (magic-number coupling between two private methods, and an unordered query relied on for disjoint index-based slicing) — both work correctly today (proven by the passing idempotency/status-matrix tests) but are latent footguns for whoever edits the bulk-sizing constants next.

## Warnings

### WR-01: Past-semester student pool sizing is coupled to `seedBulkStudents`' target by an unstated exact-fit invariant

**File:** `database/seeders/DatabaseSeeder.php:359` (target) and `:513-517` (consumption)

**Issue:** `seedBulkStudents()` sets `$target = 30` (27 bulk students after the 3 named accounts). `seedBulkSubjects()` consumes bulk-student indices `0..19` (5 subjects × 4 each). `seedPastSemester()` then does `$pastPool = $bulkStudents->slice(20)` and needs `$capacity + 2 = 7` students from it (`enrolledStudents` = 5, `withdrawnStudent`, `rejectedStudent`). `27 - 20 = 7` — the fit is exact, with zero margin, and nothing in the code documents or asserts this relationship. If a future edit bumps `seedBulkStudents`'s target down, or `seedBulkSubjects` starts consuming a 6th subject/more students per class, `$pastPool` silently shrinks below 7. The two `get($capacity)` / `get($capacity + 1)` calls degrade gracefully (return `null`, guarded by `if ($x)`), so nothing crashes — but the seed would then silently stop exercising `EnrollmentStatus::Withdrawn`/`Rejected` on the past section, or under-fill the past section's capacity, defeating `test_seeder_holds_past_semester_graded_and_filled_data`'s and `test_seeder_exercises_every_status`'s guarantees without any obvious signal at the call site that changed.

**Fix:** Either assert the invariant explicitly, or make the past pool independent of the bulk-subjects pool (e.g., reserve a fixed count up front rather than relying on `slice(20)` matching the leftover):
```php
// at the top of seedPastSemester():
$pastPool = $bulkStudents->slice(20)->values();
if ($pastPool->count() < $capacity + 2) {
    throw new \RuntimeException(
        'seedPastSemester() needs at least '.($capacity + 2).' unused bulk students; '
        .'check seedBulkStudents()\'s target against seedBulkSubjects()\'s consumption.'
    );
}
```

### WR-02: Disjoint index-based partitioning of `$bulkStudents`/`$bulkLecturers` relies on unordered query results

**File:** `database/seeders/DatabaseSeeder.php:65-67` (`$bulkStudents` query), `:412` (`slice($index * 4, 4)`), `:513` (`slice(20)`)

**Issue:** `run()` builds `$bulkStudents` via `User::where('role', Role::Student)->whereNotIn('email', [...])->get()` with no `->orderBy(...)`. That single `Collection` is then split into disjoint index ranges by two different private methods (`seedBulkSubjects()` takes `0..19`, `seedPastSemester()` takes `20..`) under the assumption that MySQL returns rows in a stable, consistent order across the two `slice()` calls. Within one seeder run this happens to be safe (it's the same `Collection` object, sliced twice, so PHP-side order is fixed once fetched) — but the *correctness* of "these two slices never overlap" depends entirely on that one `->get()` call returning every row exactly once in a stable order, which is not guaranteed by the query as written (no primary-key predicate, no `ORDER BY`). A query-planner change (e.g., MySQL choosing a different index for the `role`/`email` predicates) could theoretically alter row order without any code change, which — combined with WR-01 — would be a difficult regression to diagnose.

**Fix:** Add an explicit, deterministic order to make the "disjoint slices" invariant hold for a documented reason, not incidentally:
```php
$bulkStudents = User::where('role', Role::Student)
    ->whereNotIn('email', ['student@example.com', 'student2@example.com', 'student3@example.com'])
    ->orderBy('id')
    ->get();
```

## Info

### IN-01: Role-branch markup duplicated across desktop/mobile nav for the new Help button

**File:** `resources/views/layouts/navigation.blade.php:68` and `:137` (both `auth()->user()->isLecturer() ? route('lecturer.help.show') : route('student.help.show'))`

**Issue:** The same ternary is repeated verbatim in the desktop and mobile nav blocks (UX-05). Harmless today, but any future third role or renamed route name has to be updated in two places.

**Fix:** Hoist to a single `@php $helpRoute = auth()->user()->isLecturer() ? route('lecturer.help.show') : route('student.help.show'); @endphp` near the top of the file, or a small view-composer/accessor, and reference `$helpRoute` in both places.

### IN-02: Bulk-seed sizing constants are unnamed magic numbers scattered across multiple methods

**File:** `database/seeders/DatabaseSeeder.php:340` (`$target = 12`), `:359` (`$target = 30`), `:412` (`* 4, 4`), `:499` (`$capacity = 5`), `:513` (`slice(20)`)

**Issue:** The numbers that make WR-01/WR-02's exact-fit invariant hold (`30`, `20` = `5 subjects × 4 students`, `5 + 2` past-pool need) are not co-located or named, making the coupling invisible to a reader of any single method.

**Fix:** Pull the shared numbers into named private constants (e.g., `BULK_STUDENT_TARGET`, `STUDENTS_PER_BULK_SECTION`) and derive the "students consumed by seedBulkSubjects" figure (`count($subjectsData) * STUDENTS_PER_BULK_SECTION`) instead of the implicit `20`, so the relationship WR-01 flags is enforced by arithmetic rather than by two independently-hardcoded literals.

---

_Reviewed: 2026-07-18_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_

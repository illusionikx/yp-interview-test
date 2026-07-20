# Phase 14 — UI Review

**Audited:** 2026-07-19
**Baseline:** No UI-SPEC.md exists — audited against abstract 6-pillar standards + 14-CONTEXT.md / 14-01/14-02 PLAN+SUMMARY intent (dark-mode legibility sweep, wiki manual).
**Screenshots:** Not captured — no dev server detected on :80/:8000 (Herd not serving at audit time). Code-only audit.

---

## Pillar Scores

| Pillar | Score | Key Finding |
|--------|-------|-------------|
| 1. Copywriting | 4/4 | Wiki manual quotes shipped labels verbatim; no generic "Submit/OK/Cancel" labels found anywhere. |
| 2. Visuals | 3/4 | `<x-dropdown>`'s own default `contentClasses` prop has zero `dark:` arm (call-site override saves it in practice, but the component's own default is broken). |
| 3. Color | 2/4 | `lecturer/subjects/create.blade.php` — a page reachable from the dashboard's primary "New subject" CTA — has **zero** `dark:` classes anywhere: dark-on-dark page header AND a stark white form card left over in dark mode. |
| 4. Typography | 3/4 | 7 distinct font sizes / 3 weights in use app-wide (exceeds the ≤4-size guideline), but usage is disciplined and hierarchical (xs labels → base body → xl/3xl headers). |
| 5. Spacing | 4/4 | Only 5 arbitrary bracket-value spacing occurrences across the entire `resources/views` tree — spacing scale is followed almost everywhere. |
| 6. Experience Design | 2/4 | The sweep's own methodology (`grep -rl 'dark:' resources/views`) structurally cannot find a page with **zero** `dark:` occurrences that should have them — exactly the class of bug this phase existed to close, and it survived. |

**Overall: 18/24**

---

## Top 3 Priority Fixes

1. **`resources/views/lecturer/subjects/create.blade.php` — entire page missed by the FIX-02 sweep, dark-on-dark header, reachable from the dashboard's #1 lecturer CTA.**
   - **File:lines:** `resources/views/lecturer/subjects/create.blade.php:3` (`<h2 class="font-semibold text-xl text-gray-800 leading-tight">` — no `dark:text-*`), `:10` (`<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">` — no `dark:bg-gray-800`).
   - **User impact:** A lecturer who toggles dark mode, then clicks "New subject" from the dashboard (`resources/views/lecturer/home.blade.php:32`, `resources/views/lecturer/subjects/index.blade.php:12`), lands on a page where the header title (`text-gray-800`, ~#1f2937) sits directly on the layout's `dark:bg-gray-800` header surface (`resources/views/layouts/app.blade.php:37`) — the title is functionally invisible. Below it, the form card stays a flat white box in an otherwise all-dark UI, and its labels render `dark:text-gray-300` (light gray) directly on that white card — a low-contrast, visually broken patchwork.
   - **Concrete fix:** Add `dark:text-white` to the `<h2>` (line 3) and `dark:bg-gray-800` to the card div (line 10), matching every other `x-slot name="header"`/card pattern already used correctly on 24 of 26 other header-slot pages in the app.
   - **Why the phase 14-01 sweep missed it:** 14-01-SUMMARY.md states the sweep covered "all 36 files matching `grep -rl 'dark:' resources/views`" — a file with **zero** `dark:` occurrences is by construction excluded from that grep. The sweep's method can only find *wrong* pairings, not *absent* ones. This is a real methodology gap, not a one-off oversight, and should be re-run as `grep -rL 'dark:' resources/views --include="*.blade.php"` cross-referenced against every reachable route to close it out.

2. **`resources/views/components/dropdown.blade.php:1` — default `contentClasses` prop (`'py-1 bg-white'`) carries no `dark:` arm.**
   - **User impact:** Currently masked because the only call site (`resources/views/layouts/navigation.blade.php:79`) overrides it with `content-classes="py-1 bg-white dark:bg-gray-700"`. But the component's *documented default* is broken — any future dropdown usage that doesn't override this prop (e.g. a new feature added post-milestone) will silently ship a white-only dropdown panel in dark mode, and nothing in `tests/Feature/DarkModeContrastTest.php` guards this component's default (only `<x-status-pill>` is covered).
   - **Concrete fix:** Change the prop default to `'py-1 bg-white dark:bg-gray-700'` in `dropdown.blade.php:1` so the component is dark-safe out of the box, independent of call-site diligence.

3. **Typography scale is wider than necessary (7 sizes, 3 weights) with no documented scale to audit against.**
   - **File:lines:** aggregate across `resources/views/**/*.blade.php` — `text-3xl` (4 occurrences, page-level headers like `student/help.blade.php:14`), `text-2xl` (3), `text-4xl` (1) — three large sizes doing overlapping "big heading" work with no stated rule for when to use which.
   - **User impact:** Low on its own (usage is hierarchical, not random), but with no UI-SPEC and no documented type scale, the next contributor has no way to know whether `text-2xl` or `text-3xl` is "correct" for a new page header — this is the kind of drift that produces inconsistent heading sizes across pages a year from now.
   - **Concrete fix:** Document the intended scale (e.g., "page headers → `text-3xl`; card/section headers → `text-xl`; body → `text-base`; helper/caption text → `text-xs`") in CLAUDE.md's Conventions section (currently empty — "Conventions not yet established") and reconcile the 4 `text-2xl`/1 `text-4xl` outliers against it.

---

## Detailed Findings

### Pillar 1: Copywriting (4/4)
- `grep -n ">Submit<\|>OK<\|>Cancel<"` across all views returns zero generic hits outside of legitimately-specific strings ("Submit Exam", "Submit this exam?").
- The wiki manual (`resources/views/student/help.blade.php`, `resources/views/lecturer/help.blade.php`) quotes shipped UI copy verbatim per the 14-02-SUMMARY.md accuracy table (spot-checked 3 entries against source: "Submit Exam" at `student/attempts/show.blade.php:270` ✓, "Move question up"/"Move question down" at `lecturer/exams/show.blade.php:248,255` ✓, "Assigned Lecturers" at `lecturer/subjects/edit.blade.php:47` ✓).
- Empty/error-state copy is descriptive where present ("Awaiting grading" with explanatory subtext, `student/help.blade.php:121` quoting the actual results page) rather than generic "No data".
- No blocking findings.

### Pillar 2: Visuals (3/4)
- Help button correctly carries `aria-label="Help"` + `sr-only` text per 14-02-SUMMARY.md, giving the icon-only control an accessible name — matches the established pattern of the adjacent theme toggle.
- Clear focal points: dashboard summary tiles, sticky topic-index sidebar in the manual, sticky exam-taking header — consistent visual hierarchy via size/weight/color.
- **Finding:** `resources/views/components/dropdown.blade.php:1` ships a broken default (`bg-white` only, no `dark:` arm) for its `contentClasses` prop. It happens to be safe today because the sole call site overrides it, but the component itself is not dark-mode-correct in isolation — a latent defect for future reuse. See Fix #2 above.

### Pillar 3: Color (2/4)
- `<x-status-pill>` (the component named in 14-CONTEXT.md as the highest-risk regression point from the prior blanket find/replace) is correctly paired in all four palettes: `bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300` etc. — light bg/dark text in light mode, dark bg/light text in dark mode. `tests/Feature/DarkModeContrastTest.php` guards this. Verified correct — no regression found here.
- Other non-gray semantic colors checked and correctly paired: `lecturer/results/index.blade.php:79,81`, `lecturer/results/show.blade.php:31`, `student/attempts/show.blade.php:141,581,587,588` (countdown badge buckets) — all carry matched light/dark arms.
- **BLOCKER-tier finding:** `lecturer/subjects/create.blade.php` (reachable from the dashboard's primary "New subject" CTA at `lecturer/home.blade.php:32` and `lecturer/subjects/index.blade.php:12`) has **zero `dark:` classes** on its own markup — see Fix #1. This directly breaks the "no text left dark-on-dark" acceptance criterion FIX-02 exists to satisfy, on a live, primary-CTA-reachable page.
- `resources/views/lecturer/exams/questions/edit.blade.php` has the same zero-dark:-classes pattern but is confirmed dead code — retired in favor of inline editing per its own header comment ("both create (exams/show.blade.php) and edit (questions/edit.blade.php)" superseded language) and no view links to `exams.questions.edit` anywhere in the codebase. Not user-reachable; lower priority than Finding #1 but worth deleting as dead code during cleanup.
- `resources/views/layouts/guest.blade.php` (pre-auth login/register) is light-only by design — no theme toggle exists before login, so this is not a defect.

### Pillar 4: Typography (3/4)
- Distribution: `text-sm` (301), `text-xl` (64), `text-base` (28), `text-xs` (21), `text-lg` (17), `text-3xl` (4), `text-2xl` (3), `text-4xl` (1) — 7 distinct sizes app-wide, 3 weights (`font-semibold` 183, `font-medium` 60, `font-normal` 4).
- Usage is hierarchical and legible in every file read (page headers `text-3xl`/`text-xl`, card headers `text-xl`, body `text-base`, meta/caption `text-xs`), but the 3 large sizes (`2xl`/`3xl`/`4xl`) overlapping in purpose with no documented scale is a discipline gap for future contributors. See Fix #3.

### Pillar 5: Spacing (4/4)
- Only 5 arbitrary bracket-value spacing/sizing occurrences (`[Npx]`/`[N.Nrem]`) found across the entire `resources/views` tree — the app is disciplined about using Tailwind's default spacing scale (`p-6`, `gap-4`, `space-y-3`, `mt-4`, etc.) rather than one-off pixel values.
- Manual pages follow the established `lg:sticky lg:top-6` sidebar pattern consistently with the take-exam page's precedent (`lg:sticky lg:top-40`), per 14-02-SUMMARY.md.
- No findings requiring a fix.

### Pillar 6: Experience Design (2/4)
- Wiki manual delivers real navigation value: topic index + cross-links verified (each topic anchor referenced 2+ times per 14-02-SUMMARY.md's grep evidence), help button correctly paired beside the theme toggle in both desktop and mobile bars, both roles (`grep -c "help.show"` confirmed 2+ hits in `navigation.blade.php`, not present in the interim links block).
- Six reachability/role-scoping tests (guest→login, cross-role 403) remain green per 14-REVIEW.md's code review — a student cannot load the lecturer manual and vice versa.
- **BLOCKER-tier finding, same root cause as Pillar 3:** the phase's own audit method (`grep -rl 'dark:' resources/views`, stated explicitly in 14-01-SUMMARY.md) is structurally incapable of discovering a page that has **zero** `dark:` classes but needs them — such a file never appears in that grep's output. This isn't a one-off miss, it's a blind spot baked into the verification method itself, meaning other files (beyond the two found here) could carry the same class of bug undetected. Recommend re-running the sweep as `grep -rL 'dark:' resources/views --include="*.blade.php"` filtered to reachable routes, to close the blind spot rather than trusting the positive-match sweep as exhaustive.
- Loading/error-state coverage is thin by grep signal (`isLoading`/`x-show="loading`/`role="alert"` — only 2 hits app-wide), though the take-exam page's autosave Saving…/Saved/Retry states (verified present at `student/attempts/show.blade.php:255-259` per the manual's own citation) show the pattern exists where it matters most; the low grep count reflects naming conventions (e.g. Alpine `saving`/`autosaveState` rather than literal `isLoading`) more than an actual absence of state handling — not scored as a standalone defect, but not independently verified beyond the take-exam page either.

---

## Registry Safety
Not applicable — no `components.json` / shadcn registry present in this project (Blade + Tailwind + Alpine only, no component registry).

---

## Files Audited
- `.planning/phases/14-delivery-dark-mode-wiki-manual-demo-data-browser-tests/14-CONTEXT.md`
- `.planning/phases/14-delivery-dark-mode-wiki-manual-demo-data-browser-tests/14-01-SUMMARY.md`, `14-02-SUMMARY.md`, `14-02-PLAN.md`, `14-REVIEW.md`
- `resources/views/components/status-pill.blade.php`
- `resources/views/components/dropdown.blade.php`, `dropdown-link.blade.php`
- `resources/views/components/input-label.blade.php`, `text-input.blade.php`
- `resources/views/layouts/app.blade.php`, `navigation.blade.php`
- `resources/views/lecturer/subjects/create.blade.php`, `lecturer/exams/questions/edit.blade.php`
- `resources/views/lecturer/results/index.blade.php`, `results/show.blade.php`
- `resources/views/student/attempts/show.blade.php`
- `resources/views/student/help.blade.php`, `lecturer/help.blade.php`
- Full-tree greps across all `resources/views/**/*.blade.php` for `dark:` presence/absence, color-class pairing, font-size/weight distribution, arbitrary spacing values, and generic-copy patterns.

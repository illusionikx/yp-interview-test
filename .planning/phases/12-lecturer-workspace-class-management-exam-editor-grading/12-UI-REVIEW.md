# Phase 12 — UI Review

**Audited:** 2026-07-19
**Baseline:** No UI-SPEC.md for this phase — audited against abstract 6-pillar standards + 12-CONTEXT.md locked decisions (Decision #8 move-up/down, Decision #2 authoring-time shuffle, EDT-03/EDT-05 "persisted immediately") and PLAN/SUMMARY intent.
**Screenshots:** Not captured. Herd dev server (`https://yp-test.test`) responds 200, but all target surfaces (subject hub, exam editor, grading page) require an authenticated lecturer session; scripting login via the CLI screenshot approach was out of scope for the time budget, so this is a code-only audit of the actual Blade/Alpine/controller source.

---

## Pillar Scores

| Pillar | Score | Key Finding |
|--------|-------|-------------|
| 1. Copywriting | 3/4 | Confirm-modal copy is specific and count-accurate; a few generic table-action labels (View/Edit/Delete/Grade) are acceptable in-context but "Grade" never changes to "View" once an exam is fully graded on the Exams tab row (unlike the per-student row in the grading page, which does). |
| 2. Visuals | 3/4 | Clear card/tab hierarchy and icon-button aria-labels throughout; the option-level move-up/down glyphs are shrunk to `text-[10px]`, well below the rest of the UI's icon sizing, weakening their visual weight and touch target. |
| 3. Color | 2/4 | The exam editor's own question-authoring form (`questions/_form.blade.php`) uses `indigo-500/600` for focus rings and the "Add option"/"Shuffle options" links, while every other interactive/accent element on the exact same page (tabs, Publish/Save buttons, all other links) uses `blue-600`. Two accent hues on one screen breaks the single-accent discipline the rest of the phase follows correctly. |
| 4. Typography | 3/4 | Consistent, small font-size palette (`text-xs/sm/lg/xl`) and only two weights (`font-medium`, `font-semibold`) across all six audited files — within best-practice bounds. |
| 5. Spacing | 3/4 | Spacing is drawn entirely from the standard Tailwind scale (`p-6`, `px-4 py-2`, `mt-1/2/4/6`, `gap-2/3/4`) with no arbitrary spacing values found; the only arbitrary-bracket values in scope are font-size (`text-[10px]`), not spacing. |
| 6. Experience Design | 2/4 | A real functional gap: the option move-up/down and shuffle controls inside the question form are **pure client-side Alpine state** — they do not call the `exams.questions.options.move` / `exams.questions.options.shuffle` routes that Plan 12-05 built specifically for this purpose. Those two routes are wired, tested (`QuestionReorderTest`), and exist in `routes/lecturer.php`, but are never invoked by the actual UI. Option reordering only persists if/when the surrounding question form's unrelated "Save question" button is later clicked — contradicting EDT-03/EDT-05's "persisted immediately" design and behaving inconsistently with question-level reorder (which *does* persist instantly via AJAX in `show.blade.php`). Additionally, the async question-reorder request (`questionReorder()` in `show.blade.php`) gives zero visual feedback while in flight and, on failure, silently reverts and does a hard `window.location.reload()` with no toast/error message shown first. |

**Overall: 16/24**

---

## Top 3 Priority Fixes

1. **Option move-up/down and shuffle silently don't persist immediately, contradicting EDT-03** — a lecturer who reorders/shuffles MCQ options and then navigates away (or refreshes) without noticing the form is now "dirty" loses the reorder entirely, believing it saved (question-level reorder next to it *does* save instantly, setting a false expectation). **Fix:** either (a) wire `moveOption()`/`shuffleOptions()` in `resources/views/lecturer/exams/questions/_form.blade.php:57-85` to POST/PATCH to the already-built `exams.questions.options.move` / `exams.questions.options.shuffle` routes (`routes/lecturer.php:74-77`) the same way `show.blade.php`'s `questionReorder()` does for questions, or (b) if deferred-save-on-option-reorder is actually intentional, delete the now-dead `options.move`/`options.shuffle` routes+controller actions and their tests, and make the "unsaved changes" state visually obvious (e.g. a persistent "unsaved option order" banner) rather than a bare dirty-flag Save button.

2. **Two competing accent colors on the same exam-editor page** — `resources/views/lecturer/exams/questions/_form.blade.php` lines 105, 114, 138, 143 (`focus:border-indigo-500 focus:ring-indigo-500`, `text-indigo-600`) and lines 152-153 ("Add option"/"Shuffle options" links) use indigo, while the rest of `exams/show.blade.php` (tabs, Publish/Save/View Results, all other links) uses `blue-600`/`focus:ring-blue-*`. **Fix:** replace every `indigo-*` occurrence in `_form.blade.php` with the equivalent `blue-*` shade to match the page's established single accent.

3. **No in-flight/error feedback on the AJAX question reorder** — `questionReorder()` in `resources/views/lecturer/exams/show.blade.php:323-361` moves the DOM node optimistically, then on a failed background POST silently reverts and calls `window.location.reload()` with no toast or inline message shown to the lecturer first, so a slow network makes the reorder look instant-but-then-inexplicably-reset. **Fix:** show a brief `x-show`-gated inline "Saving…" state on the row during the request, and surface a toast (reusing the existing `<x-toast>` component) before the reload on failure so the revert isn't unexplained.

---

## Detailed Findings

### Pillar 1: Copywriting (3/4)
- Confirm-modal bodies are exemplary: they interpolate exact counts and named entities rather than generic "Are you sure?" text (`exams/show.blade.php:59`, `:184`, `:230-231`; `_exams-tab.blade.php:36-37`).
- `_exams-tab.blade.php:79` renders a static "Grade" link regardless of grading state, whereas the dedicated grading page (`results/index.blade.php:94`) correctly swaps the label to "View" once `status === 'graded'`. Minor inconsistency between the two surfaces that show the same underlying state.
- Table action labels (View/Edit/Delete) are generic per the grep sweep, but are contextually unambiguous inside a row-scoped table — acceptable, not scored as a defect.
- No "went wrong"/raw-exception-style copy found in any Phase 12 view.

### Pillar 2: Visuals (3/4)
- Clear focal hierarchy: each tab/page opens with a card carrying a `text-xl font-semibold` heading, consistent with the rest of the app (`exams/show.blade.php:165`, `_classes-tab.blade.php:12`, `_exams-tab.blade.php:13`).
- Icon-only buttons (question/option move arrows, delete-exam trash-adjacent text links) all carry `aria-label` (`exams/show.blade.php:251,258`; `_form.blade.php:135-136`).
- The option-level move arrows are shrunk to `text-[10px]` (`_form.blade.php:135-136`) — noticeably smaller than the question-level move arrows in the same page (`show.blade.php:251,258`, unscaled), producing a visual-weight mismatch between two controls that do conceptually the same job at two different nesting levels.

### Pillar 3: Color (2/4)
- 28 files in the app use `blue-6*` as the established primary accent vs. only 2 files using `indigo-*` — and one of those 2 is `questions/_form.blade.php`, part of the very exam-editor page named in the dark-mode bug report.
- Every dark-mode pairing checked (`text-gray-*`/`dark:text-gray-*`, `bg-white`/`dark:bg-gray-800`, status-pill palettes, back-button, confirm-modal) is fully paired — no unpaired light-only class was found in any of the six audited files. This reflects the prior "Flowbite theme pass + dark-mode gaps" fix (`90de168`) holding up under this second look; the dark-mode bug report's likely remaining surface is the indigo/blue accent clash rather than missing dark variants.
- `<x-status-pill>` (`components/status-pill.blade.php:11-15`) correctly restricts semantic color to a fixed `match()` allowlist rather than interpolating caller-supplied status strings — good discipline, prevents accidental color sprawl.

### Pillar 4: Typography (3/4)
- Font-size distribution across the six files: `text-sm` (65 uses), `text-xs` (8), `text-xl` (7), `text-lg` (1) — four sizes, well under the 4-size caution threshold, and used consistently by role (xs = badges/tiny labels, sm = body/table, xl = section headers).
- Font-weight distribution: `font-semibold` (38), `font-medium` (4) — exactly two weights, matching best practice.

### Pillar 5: Spacing (3/4)
- All spacing classes found (`p-6`, `px-4`, `py-2`, `mt-1/2/4/6`, `mb-2/4/6`, `gap-2/3/4`) map onto the standard Tailwind 4px scale; no arbitrary spacing bracket values (`[Npx]`/`[Nrem]`) were found in any of the six audited files.
- The only bracket-arbitrary values present are font-size (`text-[10px]`, `_form.blade.php:135-136`), already flagged under Visuals — not a spacing defect.

### Pillar 6: Experience Design (2/4)
- Destructive actions are consistently gated behind `<x-confirm-modal>` with exam/class-specific copy (delete exam, delete class, delete question, reset submissions) — no native `confirm()`/`alert()` found, matching the app's `NoNativeDialogTest` convention.
- Disabled-state handling is present and correct: boundary move buttons (`@disabled($loop->first/last)`), zero-attempt reset button (`disabled` + `opacity-50 cursor-not-allowed`), draft-only delete gating.
- **Spec-mismatch defect:** `QuestionReorderController::moveOption`/`shuffleOptions` (built and tested in Plan 12-05, `routes/lecturer.php:74-77`) are never called from `_form.blade.php`'s actual "Move answer up/down"/"Shuffle options" buttons (`_form.blade.php:57-85`, `:135-136`, `:153`) — those buttons only mutate local Alpine array state (`moveOption()`, `shuffleOptions()` at `_form.blade.php:57-85`), deferring persistence to whenever the question's own Save button is next clicked. This means EDT-03's "reordering is persisted immediately" requirement is met for questions but **not** for options, despite both controls sitting side-by-side in the same UI and both routes existing server-side.
- No in-flight ("saving…") or error-toast feedback during the async question-level reorder (`questionReorder()`, `show.blade.php:323-361`) — failure handling is a silent DOM revert followed by a hard page reload, giving the lecturer no explanation for the flicker.

---

## Files Audited
- resources/views/lecturer/subjects/manage.blade.php
- resources/views/lecturer/subjects/partials/_classes-tab.blade.php
- resources/views/lecturer/subjects/partials/_exams-tab.blade.php
- resources/views/lecturer/exams/show.blade.php
- resources/views/lecturer/exams/questions/_form.blade.php
- resources/views/lecturer/results/index.blade.php
- resources/views/components/status-pill.blade.php
- resources/views/components/back-button.blade.php
- resources/views/components/confirm-modal.blade.php
- routes/lecturer.php (reorder route registration cross-check)
- .planning/phases/12-lecturer-workspace-class-management-exam-editor-grading/12-CONTEXT.md
- .planning/phases/12-lecturer-workspace-class-management-exam-editor-grading/12-{01..05}-SUMMARY.md
- .planning/POST-V3.0-UI-FIXES.md (prior manual dark-mode fix record, cross-referenced)

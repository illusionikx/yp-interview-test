# Phase 13 — UI Review

**Audited:** 2026-07-19
**Baseline:** No UI-SPEC.md for this phase — audited against abstract 6-pillar standards, 13-CONTEXT.md locked decisions, and the app's own established Blade component conventions (x-primary-button, x-text-input, x-toast, x-modal) as the de facto design system.
**Screenshots:** Not captured — no dev server running at localhost:3000/5173/8080 detected; code-only audit of `resources/views/student/subjects/class.blade.php` and `resources/views/student/attempts/show.blade.php`.

---

## Pillar Scores

| Pillar | Score | Key Finding |
|--------|-------|-------------|
| 1. Copywriting | 3/4 | Contextual, non-generic CTAs throughout; no material defects |
| 2. Visuals | 3/4 | Clear hierarchy and focal points; minor redundant numbering |
| 3. Color | 2/4 | Take-exam page uses off-brand indigo accent against the app's established blue design system |
| 4. Typography | 3/4 | 6 distinct font sizes across the two files, at the edge of acceptable but role-justified |
| 5. Spacing | 4/4 | Fully on the standard Tailwind scale, no arbitrary values |
| 6. Experience Design | 2/4 | Two competing modal implementations (Flowbite data-modal vs Alpine x-modal) shipped in the same phase |

**Overall: 17/24**

---

## Top 3 Priority Fixes

1. **Off-brand indigo accent on the take-exam page** — Every other shared component in the app (`x-primary-button`, `x-text-input`, `nav-link`, `responsive-nav-link`, `confirm-modal`) uses `blue-500/600/700` as the one accent color, confirmed by a repo-wide grep. `resources/views/student/attempts/show.blade.php:98` (Instructions link, `text-indigo-600`), `:141` (stepper checkmark accent bleed), `:215` (question-number badge, `bg-indigo-50 text-indigo-700`), `:238` (MCQ radio `focus:ring-indigo-500`), `:253` (textarea `focus:border-indigo-500 focus:ring-indigo-500`), and `:587` (timer badge "normal" bucket, `bg-indigo-50 dark:bg-indigo-900`) all diverge from the app's blue convention on the single highest-stakes page in the product. **Fix:** replace every `indigo-*` class in this file with the matching `blue-*` shade already used by `x-primary-button`/`x-text-input` (e.g. `focus:ring-indigo-500` → `focus:ring-blue-500`, `bg-indigo-50 text-indigo-700` → `bg-blue-50 text-blue-700`).

2. **Two live modal systems in one app** — `resources/views/student/subjects/class.blade.php:104` and `:137-159` render the "Awaiting grading" popup using raw Flowbite `data-modal-target`/`data-modal-toggle` markup. A repo-wide grep (`data-modal-target`) shows this is the *only* place in the entire codebase using that pattern — every other modal (`confirm-submit`, `instructions` in the same phase's take-exam page, `confirm-modal.blade.php`) uses the app's Alpine `<x-modal>` component, which provides focus-trap/`Escape`-to-close/backdrop-click behavior this Flowbite instance does not replicate. This directly contradicts 13-CONTEXT.md's explicit instruction to "reuse the app's single modal style… never a native alert" and introduces an unnecessary second popup implementation and a live dependency on the `flowbite` JS package for exactly one component. **Fix:** convert the "Awaiting grading" popup to `<x-modal name="awaiting-grading" :show="false">`, dispatched via `@click="$dispatch('open-modal', 'awaiting-grading')"`, matching the pattern already used two files over in `show.blade.php`.

3. **Duplicated toast markup instead of the shared `<x-toast>` component** — `resources/views/student/attempts/show.blade.php:364-387` hand-rolls the 10-minute warning toast (icon, dismiss button, `bg-neutral-primary-soft border-l-4` shell) by copy-pasting the internals of `resources/views/components/toast.blade.php` rather than extending that component with an optional slot/prop for custom copy. The two are already drifting (the shared component's dismiss button has no `x-cloak`/`x-transition` combination identical to this one, and any future change to the toast's visual language — e.g., a border-radius or shadow tweak — now has to be applied in two places to stay consistent). **Fix:** add a `message`/slot prop to `<x-toast>` (or a sibling `<x-inline-toast :show="...">` component) and have both the flash-message toast and this 10-minute warning render through the same Blade file.

---

## Detailed Findings

### Pillar 1: Copywriting (3/4)
- Strong, task-specific CTA labels throughout: `Start` / `Resume` / `View result` / `Awaiting grading` / `Not open yet` / `Closed` (`class.blade.php:84,89,94,98,105`) — none of the generic `Submit`/`OK`/`Click Here` anti-patterns grepped for.
- Take-exam instructions copy is specific and accurate ("Answer options always stay in the same fixed order shown on this page" — `show.blade.php:342`), correctly reworded per the SUMMARY to avoid the false-positive "shuffle" collision.
- Autosave status copy is well-scoped per state: `Saving…` / `Saved` / `Save failed — Retry` (`show.blade.php:260-265`) — clear, no jargon.
- Minor deduction: the "Awaiting grading" button and its popup both restate the same idea ("You have submitted this exam…") with no differentiation between the button label and the modal body — slightly redundant but not incorrect.

### Pillar 2: Visuals (3/4)
- Clear focal point on the take-exam page: sticky header keeps subject/exam name + timer + progress always in view (`show.blade.php:71-104`); on the class page the exam-list card is visually separated from the subject-detail card per TAK-07 (`class.blade.php:12` vs `:30`).
- Icon-only buttons (toast dismiss `×`, modal close `×`) all carry `aria-label`/`sr-only` text (`show.blade.php:380`, `class.blade.php:146`).
- Minor redundancy: each question card renders its own number badge (`show.blade.php:215`, `bg-indigo-50` circle) immediately next to the vertical stepper, which already shows the same number in its own circle (`show.blade.php:139-145`). Two number badges for the same question, in two different accent colors (indigo card badge vs green/gray stepper badge), is duplicated information that also compounds the color-inconsistency finding above.

### Pillar 3: Color (2/4)
- The app's real, established accent is blue — confirmed via `grep` across `components/primary-button.blade.php:1`, `components/text-input.blade.php:3`, `components/nav-link.blade.php:5-6`, `components/responsive-nav-link.blade.php:5-6`, and `components/confirm-modal.blade.php:30,42` — all use `blue-500/600/700/800`, none use indigo.
- `class.blade.php` correctly follows this: its Start/Resume/View-result buttons are `bg-blue-700 hover:bg-blue-800` (`:83,93,98`), matching `x-primary-button` exactly.
- `show.blade.php`, the single most important page in the app (the actual exam-taking surface), instead uses `indigo-*` for the question-number badge (`:215`), the MCQ radio focus ring (`:238`), the textarea focus ring/border (`:253`), the Instructions link (`:98`), and the timer badge's default "normal" state (`:587`) — five separate, unrelated introductions of a second accent hue never used anywhere else in the codebase. This is not a 60/30/10-distribution nitpick; it is two different brand colors competing for the same semantic role (primary/focus accent) on the same page, one of which (`x-primary-button` on the Submit Exam button, still blue) sits directly below the indigo-accented question cards.
- Status-pill semantics (`components/status-pill.blade.php:11-16`) are otherwise sound: green/available, red/closed, amber/full, gray/opening — a real 3-4 hue distinction with adequate WCAG-plausible contrast pairs (`bg-green-100 text-green-800` etc.), no findings there.

### Pillar 4: Typography (3/4)
- Distinct sizes present across the two files: `text-xs`, `text-sm`, `text-base`, `text-lg`, `text-xl`, `text-3xl` — 6 sizes, over the "flag if >4" abstract threshold, but each maps to a distinct, justified role: `3xl` timer digits (`show.blade.php:83`), `xl` page/exam title (`:78`), `lg` modal headings (`:286,315`), `base` instructions sub-heading (`:334`), `sm` body copy, `xs` metadata labels (`class.blade.php:67,75,140`). No arbitrary/one-off sizes found; the hierarchy is legible and consistent with the rest of the app's Blade components.
- Weights are disciplined: only `font-medium` and `font-semibold` appear — no unnecessary bold/light variants.
- Deduction is for scale sprawl (6 sizes) rather than any single misuse — a smaller team style guide would likely collapse `base`/`lg` into one.

### Pillar 5: Spacing (4/4)
- `grep` for arbitrary bracket values (`\[.*px\]`, `\[.*rem\]`) across both files returned zero hits — every spacing utility (`p-3/4/6`, `py-12`, `gap-2/4/6`, `mt-1/3/4/6/12`, `px-2.5 py-0.5` on pills) sits on the standard Tailwind scale.
- Layout spacing is consistent between the two files (card padding `p-6` throughout, `space-y-6` for card stacks, `gap-4` for header rows) — no evidence of one-off spacing choices introduced by this phase.

### Pillar 6: Experience Design (2/4)
- Loading/save states are well covered: per-question autosave states (`idle`/`saving`/`saved`/`failed`/`expired`, `show.blade.php:175-209,260-265`), a dedicated retry action on failure, and an empty state for a zero-question exam (`:116-119`, "Nothing to answer").
- Reload-surviving stepper checkmarks are correctly server-seeded (`$answeredQuestionIds` → `answered` map, `show.blade.php:21-25,438`) rather than client-only state — matches the TAK-10 requirement and is verifiable in the SUMMARY's two-GET regression test.
- One-shot 10-minute toaster and red timer are implemented as a genuine fired-once guard (`tenMinuteWarned` set before `showTenMinuteToast` is revealed, `show.blade.php:566-571`), not a per-tick re-trigger — sound pattern, consistent with the FIX-01 precedent cited in the SUMMARY.
- **Regression:** the "Awaiting grading" modal on the class page (`class.blade.php:104,137-159`) is the only modal in the entire codebase built on raw Flowbite `data-modal-*` attributes instead of the app's own `<x-modal>` Alpine component used by every other modal shipped, including two in this same phase (`show.blade.php:282,313`). This means the app now ships two independent, behaviorally-inconsistent modal systems (no shared focus-trap/backdrop/Escape behavior guarantee across all popups) and a live runtime dependency on the `flowbite` npm package (`resources/js/app.js:4`) for exactly one dialog. This is an experience-design regression for a v3.0 milestone close: a user closing this popup does not get the same interaction guarantees (keyboard trap, backdrop click-to-dismiss) as every other dialog in the product.
- Toast markup duplication (see Fix #3 above) is a smaller instance of the same "second implementation of an existing single-source component" pattern.

---

## Registry Safety

No `components.json` present (this is a Blade/Tailwind/Alpine project, not shadcn) — registry audit skipped per the audit's own gating rule.

---

## Files Audited
- `resources/views/student/subjects/class.blade.php`
- `resources/views/student/attempts/show.blade.php`
- `resources/views/student/exams/show.blade.php` (reference/consistency check)
- `resources/views/components/toast.blade.php` (reference/consistency check)
- `resources/views/components/status-pill.blade.php` (reference/consistency check)
- `resources/views/components/primary-button.blade.php`, `text-input.blade.php`, `nav-link.blade.php`, `responsive-nav-link.blade.php`, `confirm-modal.blade.php`, `secondary-button.blade.php`, `danger-button.blade.php` (brand-color baseline)
- `.planning/phases/13-student-exam-experience-class-page-take-exam/13-CONTEXT.md`
- `.planning/phases/13-student-exam-experience-class-page-take-exam/13-01-SUMMARY.md`
- `.planning/phases/13-student-exam-experience-class-page-take-exam/13-02-SUMMARY.md`

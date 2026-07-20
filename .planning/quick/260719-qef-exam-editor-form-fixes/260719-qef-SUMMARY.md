---
quick_id: 260719-qef
title: Exam-editor question form fixes + student exam-start card padding
date: 2026-07-19
status: complete
tests: not re-run (view-only Blade/Alpine/CSS changes; no PHP logic touched)
build: npm run build required — new Tailwind classes were added (see "Asset rebuild" below)
---

# Quick Task 260719-qef — Exam-editor question form fixes

## Symptom
On the lecturer exam editor (Questions tab), Q1 rendered the **raw Alpine
component source** as its visible text ("first-message map populated on an ajax
422. errors: {}, onSubmit(form) { ... "). The DB question body was clean
(`What is 2 + 2?`) — this was a render leak, not corrupt data.

## Root cause
`resources/views/lecturer/exams/questions/_form.blade.php` defines a large Alpine
component inline in `x-data="{ ... }"`. Two JS `//` comments inside that attribute
contained characters that terminate the HTML attribute early:

1. **Line 52** `// Drives the transient "Saved" indicator ...` — the literal
   double-quotes around `"Saved"` closed the `x-data="` attribute. The browser
   then hit the `>` in a later `field->first-message` comment, ended the tag, and
   dumped the rest of the component onto the page as text. **This was the actual
   trigger.**
2. **Line 68** `// FormData carries the @csrf _token and @method('PUT') _method`
   — Blade expands `@csrf`/`@method()` *everywhere*, including inside an
   attribute, injecting `<input ... value="...">` (more quotes). A latent second
   break that never got reached because #1 fired first.

## What changed
All edits are in `resources/views/lecturer/exams/questions/_form.blade.php` unless
noted.

- **Attribute-break fix** — reworded both comments to contain no literal `"` and
  no live Blade directives (`"Saved"` → `'Saved'`; `@csrf`/`@method('PUT')` →
  plain prose "CSRF _token and PUT-spoof _method").
- **Unsaved-changes warning** — added a `submitting: false` flag and
  `x-on:beforeunload.window="if (dirty && ! submitting) { preventDefault; returnValue='' }"`.
  Leaving the page (link / back / tab-close) with a dirty question prompts the
  browser's native confirm. Stays quiet on intentional navigation:
  - AJAX save (no-attempt exams) never navigates.
  - Native fallback `form.submit()` in `saveViaAjax` sets `submitting = true`.
  - Discard button sets `submitting = true` before reload.
  - Attempted-exam confirm modal (`_save-warning-modal.blade.php`) sets
    `submitting = true` before `$refs.<form>.submit()`. Flag is set only at the
    confirm click, so cancelling the modal leaves the guard armed. Harmless on the
    details-form caller (Alpine sets the prop; only the question form reads it).
- **Options block spacing/UX** —
  - row spacing `space-y-2` → `space-y-2.5`, item gap `gap-2` → `gap-3`;
  - each row gets padding + hover highlight; the row of the **correct** option is
    tinted blue (`:class="correct === index ? 'bg-blue-50/60 ...'"`) so the answer
    is scannable;
  - reorder arrows bumped `text-[10px]` → `text-xs` with a small gap;
  - correct-answer radio sized `h-4 w-4`, `cursor-pointer`, `title="Mark as the
    correct answer"`;
  - actions row more top spacing; "Add option" → "+ Add option", bolded.

## Student exam-start card padding (`resources/views/student/exams/show.blade.php`)
Reported: the exam-start card "feels cramped, missing padding." Bumped each card
section's internal padding `px-6`→`px-8` and vertical `py-4/py-5`→`py-5/py-6`
(header, the four stat-grid cells, warning, footer). No dividers added (explicitly
not wanted).

## Asset rebuild — the "nothing changed" cause
The app serves **JIT-purged built assets** from `public/build` (no Vite dev
server / no `public/hot`). `px-8` and `py-5` were **not used anywhere before**, so
they didn't exist in the compiled CSS — the class swaps silently no-op'd and the
card actually *lost* its `px-6` padding. Same reason the exam-editor
`bg-blue-50/60` correct-row tint didn't appear. Fixed with `npm run build`
(new CSS `app-bZtbkMBz.css`); verified `px-8`, `py-5`, `bg-blue-50/60`,
`space-y-2.5` all present post-build. Recorded as a persistent gotcha in
auto-memory (`built-assets-need-npm-build`). **Any future Blade edit that
introduces a fresh Tailwind class must be followed by `npm run build`.**

## Notes / follow-ups
- **Regression caught in-session:** the Remove button was first given
  `opacity-0 group-hover:opacity-100` (hover-reveal), which read as "delete button
  missing." Reverted to always-visible red. Remove is still intentionally hidden
  at exactly 2 options (MCQ minimum).
- **Deferred (ponytail):** the durable cure for this recurring
  prose-in-attribute fragility is extracting the component to `Alpine.data()` in a
  script. Not done — three quote/directive-free comments hold. Add if a quote
  sneaks into a comment a fourth time.
- No automated test added — changes are Blade/Alpine view-only; verified by
  rendering the partial for a real question (x-data closes at its real `}`, full
  component intact, beforeunload + options markup present).

# Phase 11 — UI Review

**Audited:** 2026-07-19
**Baseline:** No UI-SPEC.md exists for this phase — audited against 11-CONTEXT.md's explicit decisions (which repeatedly call for reusing Phase 9 Flowbite semantic tokens) plus the abstract 6-pillar standards.
**Screenshots:** Not captured (code-only audit — no dev server check performed; audit based on static Blade/Tailwind analysis of the actually-committed views).

---

## Pillar Scores

| Pillar | Score | Key Finding |
|--------|-------|-------------|
| 1. Copywriting | 4/4 | Destination-naming back buttons, specific CTAs ("Enroll", "Choose a subject"), solid empty states — no generic "Submit"/"Click Here" violations found. |
| 2. Visuals | 3/4 | Clear banner→cards→table hierarchy, but the toggle flash bug (see Pillar 6) and hardcoded gradient undercut the intended "brand pizzazz" focal point. |
| 3. Color | 2/4 | 11-CONTEXT.md explicitly mandates Phase 9 token reuse (`bg-neutral-primary-soft`, `border-default`, brand tokens) for this exact surface — the actual views hardcode `blue-600/700/800/900` and `gray-200/700/800` instead, producing two parallel color systems in the same phase. |
| 4. Typography | 2/4 | 6 distinct font sizes in a single small dashboard surface (`text-xs/sm/lg/xl/2xl/3xl`) — exceeds the abstract ≤4-size guideline with no UI-SPEC override to justify it. |
| 5. Spacing | 4/4 | Consistent Tailwind spacing scale throughout (`p-6`, `px-4 py-2`, `gap-6`, `mb-4/6`, `space-y-6/8`); zero arbitrary bracket values found. |
| 6. Experience Design | 3/4 | Good state coverage (empty states, window-gated enroll, withdraw confirm modal) but `x-cloak` is used on 10 files app-wide (including this phase's `student/home.blade.php` toggle) with **no** `[x-cloak]{display:none}` CSS rule anywhere in the codebase — the cloak is inert. |

**Overall: 18/24**

---

## Top 3 Priority Fixes

1. **`[x-cloak]` has no effect anywhere in the app — every `x-cloak` element flashes visibly on page load.** — User impact: on `student/home.blade.php`, both "Show past semesters" and "Hide past semesters" spans (and the light/dark toggle icons in `navigation.blade.php`/`landing.blade.php`) are visible simultaneously for a frame before Alpine hydrates, on every single page load, for every user. — Concrete fix: add `[x-cloak] { display: none !important; }` to the `@layer base` block in `D:\Herd\yp-test\resources\css\app.css` (currently absent — confirmed via `grep -rn "cloak" resources/css` returning nothing).

2. **Phase 9 semantic color tokens exist and were explicitly mandated for this phase but are not used in the new surfaces.** — User impact: two parallel, drifting color systems now exist in the same codebase (tokens `bg-brand`/`text-heading`/`text-body`/`border-default` vs. hardcoded `blue-600`/`gray-200`/`gray-800`), which is exactly the maintenance/consistency risk the Phase 9 token layer was built to eliminate — future dark-mode or brand-color edits will need to be made in two places and will silently diverge. — Concrete fix: in `D:\Herd\yp-test\resources\views\components\welcome-banner.blade.php:11`, replace `bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-800 dark:to-blue-900` with `bg-gradient-to-r from-brand to-brand-strong` (both tokens already ship correct light/dark values in `tailwind.config.js`/`app.css`); in `D:\Herd\yp-test\resources\views\components\dashboard-card.blade.php:19`, replace `border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800` with `border-default bg-neutral-primary-soft`; sweep the same hardcoded-`blue-*`/`gray-*` pattern out of `lecturer/home.blade.php`, `student/home.blade.php`, and `student/subjects/index.blade.php` (3, 4, and 2 hardcoded-blue hits respectively, confirmed via grep).

3. **6 distinct font sizes on a single small dashboard page (`text-xs/sm/lg/xl/2xl/3xl`) with no declared scale to justify the spread.** — User impact: visual hierarchy reads as slightly noisy/inconsistent rather than deliberately tiered — e.g. `lecturer/home.blade.php`'s page header (`text-xl`) and its "Your subjects" card heading (`text-xl`) are the same size as `student/subjects/index.blade.php`'s "No subjects available" empty-state heading (`text-xl`), while step headings there use `text-lg` — the size choices don't map cleanly onto a level of importance. — Concrete fix: collapse to 4 tiers — page header `text-xl font-semibold`, section/card heading `text-lg font-semibold`, body/table `text-sm`, micro (status pills) `text-xs` — and drop the redundant `text-2xl` banner size down to `text-xl` or promote it deliberately as the one "hero" exception with a documented rationale.

---

## Detailed Findings

### Pillar 1: Copywriting (4/4)
- Back buttons name their destination everywhere audited: `x-back-button` slot text is always destination-specific ("Back to classes", "Back to exams", "Back to home" — `resources/views/student/subjects/index.blade.php:10`), never a bare "Back". Matches UX-04 exactly.
- CTAs are specific: "Enroll" (not "Submit"), "New subject", "Choose a subject", "Enroll in a class", "Open class page" — all name the action/destination.
- Empty states are informative, not generic: "You're not enrolled in any subjects yet." (`student/home.blade.php:32`), "There are no subjects open for enrollment right now." (`student/subjects/index.blade.php:20`), "No subjects assigned to you yet." (`lecturer/home.blade.php:64`) — each names the actual condition rather than a bare "No data".
- Only one "Cancel" found app-wide in the audited scope (`student/subjects/index.blade.php:151`), and it's inside a modal titled "Withdraw from Class" with explicit surrounding copy — acceptable, standard modal-dismiss convention, not a violation of the CTA-labeling intent.
- ENR-10's "no credit limit" rule is surfaced as user-facing copy, not just enforced silently: `student/subjects/index.blade.php:15` — "You may enroll in as many subjects as you like — there is no limit on how many classes you can hold at once."

### Pillar 2: Visuals (3/4)
- Strong compositional hierarchy on both home pages: full-width gradient banner (focal point) → responsive stat-card grid → subject table, matching the phase's specified layout order exactly (`lecturer/home.blade.php`, `student/home.blade.php`).
- No icon-only buttons without accompanying text were found in the phase-11 surfaces — `x-back-button`'s chevron icon is always paired with slot text; the dark/light toggle (pre-existing, `navigation.blade.php`) also pairs icon-only affordance with `x-cloak`-gated sun/moon swap — undermined by Finding #1 above (flash on load).
- Dashboard cards (`x-dashboard-card`) establish size/weight hierarchy correctly: `text-3xl font-semibold` value over `text-sm font-semibold` label — good use of size+weight together for hierarchy, this part of the pillar is executed well.
- Deduction: the welcome banner's "pizzazz" gradient is a hardcoded two-stop `blue-600→blue-700` (light) / `blue-800→blue-900` (dark) rather than a tokenized brand gradient — visually fine in isolation but a missed opportunity for the "subtle brand gradient over Phase 9 tokens" the context decision called for, and it will not track any future brand-token repaint.

### Pillar 3: Color (2/4)
- `tailwind.config.js` and `resources/css/app.css` define a complete, dark-mode-safe semantic token layer specifically for this purpose (`--color-brand`, `--color-heading`, `--color-body`, `--color-border-default`, `--color-neutral-primary-soft`, all with distinct light/dark values) — built in Phase 9 exactly so surfaces like this one wouldn't need to hand-roll `dark:` variants per hardcoded color.
- `dashboard-card.blade.php` partially adopts the tokens (`text-heading`, `text-body`, `bg-brand` on the progress fill — lines 20-21, 26) but its own container uses hardcoded `border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800` (line 19) instead of `border-default bg-neutral-primary-soft` — inconsistent even within the same 32-line file.
- `welcome-banner.blade.php:11` hardcodes the entire gradient in raw Tailwind blue-scale classes rather than referencing `brand`/`brand-strong` — directly contradicts 11-CONTEXT.md's explicit instruction ("subtle brand gradient... over Phase 9 brand tokens").
- Grep confirms the scope of the drift: `grep -c "blue-6/7/8/500/400"` returns 3 hits in `lecturer/home.blade.php`, 4 in `student/home.blade.php`, 2 in `student/subjects/index.blade.php` — versus 0 uses of `bg-brand|text-heading|text-body|border-default|neutral-primary-soft` in any of those three page-level views (only the shared component partially uses them).
- Accent (blue) usage count across the three new pages is low enough to not trip a 60/30/10 overuse flag on its own, but the underlying problem is architectural, not volumetric: the phase built a token system specifically to solve this and then didn't route the phase's own new surfaces through it.

### Pillar 4: Typography (2/4)
- Distinct font sizes present across the four audited files: `text-xs` (1), `text-sm` (43), `text-lg` (3), `text-xl` (7), `text-2xl` (1), `text-3xl` (1) — 6 distinct sizes, confirmed via grep.
- Font weights are well-constrained: only `font-semibold` (27) and `font-medium` (2) — 2 weights, within the abstract ≤2-weight guideline. This half of the pillar is clean.
- The 6-size spread isn't obviously tiered to importance: `text-xl` is reused for the page-level `<h2>` header slot, the "Your subjects" card heading, and the "No subjects available"/"No classes yet" empty-state headings — three different semantic levels sharing one size, while `text-lg` is used one level down for step headings in the enrollment flow. A cleaner scale (page header → section heading → body → micro) would use 4 sizes, not 6.
- No UI-SPEC.md exists to declare an intentional wider scale, so this is scored against the abstract standard, which flags >4 sizes.

### Pillar 5: Spacing (4/4)
- All spacing observed is on the standard Tailwind scale: `p-6`, `px-4 py-2`, `px-6 py-8`, `gap-6`, `mb-4`, `mb-6`, `space-y-6`, `space-y-8`, `mt-1`, `mt-3` — no arbitrary bracket values (`grep -rn "\[.*px\]\|\[.*rem\]"` returned zero matches across all six audited files).
- Card grid spacing (`gap-6` in both `grid sm:grid-cols-2 lg:grid-cols-3` and `grid sm:grid-cols-2`) is consistent between the lecturer (3-card) and student (2-card) layouts, matching the context decision's specified grid pattern.
- Table cell padding (`px-4 py-2`) is uniform across every table in all three views (lecturer subjects, student current/past semester groups, enrollment classes table) — good consistency at the component level.

### Pillar 6: Experience Design (3/4)
- Empty states are handled for every meaningful zero-state: no assigned subjects (lecturer), no enrolled subjects (student), no subjects open for enrollment, no classes for a chosen subject — all covered with specific copy (see Pillar 1).
- ENR-11's enrollment-window gate is enforced both in copy and action-visibility: `student/subjects/index.blade.php:83` computes `$canApply` and only renders the enroll form when true, with status pills ("Opens :date", "Closed", "FULL") explaining why the action is absent — good non-blocking feedback pattern.
- Destructive action (withdraw) is behind a named confirm modal (`Withdraw from Class` — `student/subjects/index.blade.php:146-159`), not a bare confirm() or instant action — matches the app's established `x-confirm-modal`/`x-modal` convention.
- Deduction: `x-cloak` is used in 10 files across the app (`grep -rn "x-cloak" resources/views`), including this phase's own `student/home.blade.php:63` (past-semester toggle) and the navigation dark/light icon toggle rendered on every phase-11 page — but there is no `[x-cloak] { display: none }` rule anywhere in `resources/css/app.css` or any other stylesheet (confirmed by grep across the repo). Functionally this means `x-cloak` is a no-op: on every page load, before Alpine attaches, cloaked elements are visible in their default (uncontrolled) state, producing a visible flash — most noticeably both toggle-label spans on `student/home.blade.php` appearing together for a frame. This degrades polish without breaking the underlying flow, hence a Warning rather than a Blocker.
- No loading-state affordances were found, but none are needed here — every audited view is a classic server-rendered Blade page-load/redirect flow (no async fetch, no SPA-style partial updates), so the absence of spinners/skeletons is not a defect in this architecture.

---

## Files Audited
- `resources/views/lecturer/home.blade.php`
- `resources/views/student/home.blade.php`
- `resources/views/student/subjects/index.blade.php`
- `resources/views/components/welcome-banner.blade.php`
- `resources/views/components/dashboard-card.blade.php`
- `resources/views/components/back-button.blade.php`
- `resources/views/components/status-pill.blade.php`
- `resources/views/components/primary-button.blade.php`
- `resources/views/components/secondary-button.blade.php`
- `tailwind.config.js`
- `resources/css/app.css`
- `resources/js/app.js`
- `.planning/phases/11-navigation-restructure-landing-hierarchy-dashboard-subjects-/11-CONTEXT.md`
- `.planning/phases/11-navigation-restructure-landing-hierarchy-dashboard-subjects-/11-01-SUMMARY.md` through `11-04-SUMMARY.md`

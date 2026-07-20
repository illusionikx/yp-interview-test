# Phase 9 — UI Review

**Audited:** 2026-07-19 (re-audit — supersedes the 2026-07-17 review)
**Baseline:** 09-UI-SPEC.md (approved, 6/6 dimensions)
**Screenshots:** not captured (no dev server running at :3000/:5173/:8080; code-only audit performed directly against Blade sources, `tailwind.config.js`, and `resources/css/app.css`)

**Note on the prior review:** the 2026-07-17 review's top fix (login page rendering on an un-themed `bg-gray-100` shell with no dark bootstrap) has since been **resolved** — `layouts/guest.blade.php` now carries the pre-paint bootstrap script and a token-based background. This re-audit finds that fix landed correctly, but surfaces new, previously-unflagged defects in the same two components the prior review already called out for token inconsistency (`<x-toast>`, `<x-confirm-modal>`) — this time in Typography, not just Color.

---

## Pillar Scores

| Pillar | Score | Key Finding |
|--------|-------|-------------|
| 1. Copywriting | 4/4 | All contract strings reproduced verbatim; INT-01 error message matches exactly, byte-for-byte. |
| 2. Visuals | 3/4 | Landing/login/toast hierarchy is clean; confirm-modal introduces an undeclared fifth type size and several decorative SVG icons across the phase lack `aria-hidden`. |
| 3. Color | 2/4 | `<x-confirm-modal>` bypasses the UI-03 token port entirely on both its buttons (hardcoded `blue-300`/`gray-300`/`gray-700`); the login/guest page's outer shell uses the 30%-role secondary token as its 60%-role page background. |
| 4. Typography | 2/4 | Toast body text and confirm-modal body text both ship at `text-sm` (14px) where the contract explicitly names them under the 16px Body role ("toast body text, modal body text"); confirm-modal's title uses an undeclared `text-lg` (18px) instead of the contracted 14px Label role. |
| 5. Spacing | 3/4 | 8-point scale followed almost everywhere; the landing hero's `sm:py-24` (96px) is off the seven declared token values. |
| 6. Experience Design | 4/4 | Auto-dismiss/persist-until-dismissed/sentinel-exclusion/aria-label/focus-trap/escape-to-close all correctly wired; all 3 native `confirm()` call sites fully migrated; INT-01 null-guard wired end-to-end through the error-toast path. |

**Overall: 18/24**

---

## Top 3 Priority Fixes

1. **Toast and confirm-modal body text ship undersized — `text-sm` (14px) where the contract specifies `text-base` (16px)** — `resources/views/components/toast.blade.php:47,78` and `resources/views/components/confirm-modal.blade.php:24`. The UI-SPEC's Typography table explicitly names both surfaces under the Body role: "16px (`text-base`) | 400 regular | ... toast body text, modal body text." Both ship one size smaller. User impact: the two highest-traffic new surfaces in this phase — every flash message the app shows, and every destructive-action confirmation sentence — read smaller than the rest of the app's body copy, and a future audit/reader has no way to tell this is deliberate vs. drift. Fix: change `text-sm` → `text-base` on `toast.blade.php:47,78` and on the modal's `<p>` at `confirm-modal.blade.php:24` (keep `font-normal`/`text-body` on all three).

2. **`<x-confirm-modal>` hardcodes non-token colors on both its buttons instead of using the UI-03 port** — `resources/views/components/confirm-modal.blade.php:30,42` use `bg-white dark:bg-gray-700`, `border-gray-300 dark:border-gray-600`, `text-gray-700 dark:text-gray-200`, and `focus:ring-blue-300 dark:focus:ring-blue-800` on the Cancel button, and the same hardcoded `focus:ring-blue-300 dark:focus:ring-blue-800` on the Confirm button. This is the newest component this phase's token port was built to serve consistently, and it's the one surface that routes around it. User impact: it's the component every later destructive-action warning (Phase 10's INT-02/CLS-07) will inherit unchanged, so the token-bypass propagates forward; the focus ring also doesn't match `ring-brand-medium` used everywhere else the contract governs focus states. Fix: swap to `border-default`/`border-default-medium`, `text-heading`/`text-body`, `bg-neutral-primary-soft`, and `focus:ring-brand-medium` on the Cancel button; swap the Confirm button's focus ring to `focus:ring-brand-medium` (keep its `bg-red-600 hover:bg-red-700` destructive fill — that's correctly scoped per the Color contract and not part of this defect).

3. **Confirm-modal title uses an undeclared `text-lg` (18px) size** — `resources/views/components/confirm-modal.blade.php:23`. The contract's Typography table has exactly 4 declared sizes (Body 16 / Label 14 / Heading 20 / Display 36) and explicitly assigns "modal title" to the 14px/600 Label row. `text-lg` is a fifth, undeclared size found nowhere else in the phase's new-build markup — exactly the "more than N sizes in use" drift the contract exists to prevent, just contained to one component so far. Fix: change `text-lg` → `text-sm` on the `<h2>` (its `font-semibold` weight is already correct).

**Additional findings not in the top 3 (real, lower severity):**
- `resources/views/layouts/guest.blade.php:34` sets the outer page wrapper to `bg-neutral-secondary-medium` — the Color contract reserves that token for "input fields, top bar background, toast container background" (the 30% role), not the page background (the 60% `neutral-primary-soft` role, which the login *card* correctly sits on top of using the right token). Visual effect is subtle in light mode (light gray vs. white) but it is a literal token-role misuse, and it now propagates to 4 other guest pages (`register`, `forgot-password`, `confirm-password`, `reset-password`) whose card treatment was moved inline onto this same wrapper during the refactor. One-line fix: `bg-neutral-secondary-medium` → `bg-neutral-primary-soft`.
- `resources/views/landing.blade.php:2` — `py-16 sm:py-24`; `py-24` (96px at the `sm:` breakpoint) is not one of the seven declared spacing tokens (4/8/16/24/32/48/64px). The spec states new-build markup, landing hero explicitly named, follows the 8-point scale "with no further exceptions" beyond the login card. Low severity (a multiple of 8, just not a named token) but worth normalizing to `sm:py-16` or a declared `3xl` progression.
- Decorative SVG icons across the phase's new components lack `aria-hidden="true"`: all four toast icons (`toast.blade.php:41,55,73,86`) and the landing/guest top-bar dark-mode toggle's two icons (`layouts/landing.blade.php:71,74`). The checker's one explicit sign-off flag (icon-only close button needs `aria-label`) was correctly implemented, but the adjacent purely-decorative icons were not given the complementary `aria-hidden` treatment, which is inconsistent screen-reader hygiene on components built in the same pass.

---

## Detailed Findings

### Pillar 1: Copywriting (4/4)
- Landing hero copy matches the contract verbatim: `resources/views/landing.blade.php:4,8,12,19` — "Online Examination Portal" / "for Yayasan Peneraju Technical Assessment" / one-line description / "Sign in".
- Login card copy matches the verbatim Flowbite reproduction requirement exactly: `resources/views/auth/login.blade.php:10,14,23,35,38,42,44` — "Sign in to our platform", "Your email", "Your password", "Remember me", "Lost Password?", "Login to your account", "Not registered? Create account".
- INT-01's error string is byte-exact against the contract: `app/Exceptions/AttemptVanishedException.php:53` — `"This exam attempt is no longer available. Please return to your exam list."`
- `<x-confirm-modal>` correctly keeps `title`/`body` as caller-supplied props with no hardcoded messages (`confirm-modal.blade.php:1-8`), matching the reuse contract for Phase 10's dynamic warnings.
- No generic "Submit"/"Click Here"/"OK" placeholders found in any audited new-build file.

### Pillar 2: Visuals (3/4)
- Landing page has a single, clear focal point (centered hero, one CTA) — no competing sections, correctly scoped per NAV-01/UX-01, matching the "no features/how-it-works, no role-picker" decision.
- Icon-only toast close buttons carry `aria-label="Dismiss"` on both variants (`toast.blade.php:52,83`) — the one flagged recommendation from the checker sign-off was implemented correctly.
- Dark-mode toggle button has an `aria-label` (`layouts/landing.blade.php:68`), but neither of its two SVGs, nor any of the toast's four icon SVGs, carry `aria-hidden="true"` — a minor but real a11y-consistency gap (see additional findings above).
- Confirm-modal introduces a fifth, undeclared type size (`text-lg`, see Typography) which is a visual-hierarchy regression specific to that component — it reads as belonging to a different scale than the toast's equivalent title-role text, even though nothing looks visibly "broken."

### Pillar 3: Color (2/4)
- Token port itself is complete and correctly bidirectional: `tailwind.config.js:23-49` and `resources/css/app.css:6-30` define both light and dark values for all 11 custom properties the login card and toast use, with `.dark` flipping every one of them (brand/brand-strong/brand-medium/brand-soft, fg-brand, heading, body, border-default, border-default-medium, neutral-primary-soft, neutral-secondary-medium).
- Accent (`bg-brand`) usage is correctly scoped to the contract's exhaustive reserved-for list: login submit button (`auth/login.blade.php:42`), landing "Sign in" CTA (`landing.blade.php:17`), focus rings (`ring-brand`/`ring-brand-soft` on inputs/checkbox), the checked-checkbox state, and `text-fg-brand` links (subtitle, "Lost Password?", "Create account") — no leakage onto secondary buttons, nav items, or the toast close button.
- **Violation:** `confirm-modal.blade.php:30,42` — Cancel and Confirm buttons hardcode `bg-white dark:bg-gray-700`, `border-gray-300 dark:border-gray-600`, `text-gray-700 dark:text-gray-200`, `focus:ring-blue-300 dark:focus:ring-blue-800` instead of the ported tokens used everywhere else this phase touches. This is the exact component the UI-03 port exists to make consistent, and it's the one surface routing around it.
- **Violation:** `layouts/guest.blade.php:34` — page-level background is `bg-neutral-secondary-medium`, the declared 30%-role token, used as the 60%-role dominant background. Propagates to `register.blade.php`, `forgot-password.blade.php`, `confirm-password.blade.php`, `reset-password.blade.php`, `verify-email.blade.php`, all of which now inherit this wrapper's card-treatment-moved-inline pattern.
- Destructive red is correctly confined to the confirm-modal's danger button and the toast's error accent only (`confirm-modal.blade.php:16`, `toast.blade.php:71,73`) — no red bleeding into warning/informational states, matching the contract's "never for a general warning" rule.

### Pillar 4: Typography (2/4)
- Landing/login sizes are contract-compliant: `text-4xl font-semibold` for the Display line (`landing.blade.php:3`), `text-xl font-semibold` for the subtitle and the login card's `<h5>` heading (`landing.blade.php:7`, `login.blade.php:10`), `text-base` for the landing body description (`landing.blade.php:11`).
- The two-weight discipline (400/600) holds for all new-build markup outside the scoped login-card exception; the login card's `font-medium` (500) instances are correctly confined to the verbatim snippet (labels, checkbox label, links, button) and were confirmed via grep to appear nowhere else in the audited files.
- **Violation:** `toast.blade.php:47,78` — toast message text is `text-sm font-normal` (14px) on both the success/info and error variants. The contract's Typography table explicitly lists "toast body text" under the Body role at 16px (`text-base`).
- **Violation:** `confirm-modal.blade.php:24` — modal body `<p>` is `text-sm text-body` (14px). Same table row explicitly lists "modal body text" under Body/16px — this is the actual delete-confirmation sentence a user reads before an irreversible action, undersized relative to contract.
- **Violation:** `confirm-modal.blade.php:23` — modal `<h2>` title is `text-lg font-semibold` (18px), a size entirely absent from the contract's 4-row table (16/14/20/36). The table explicitly assigns "modal title" to the Label row (14px/600).

### Pillar 5: Spacing (3/4)
- Login card and its field spacing hold the documented exceptions exactly (`py-2.5`/`mb-2.5` verbatim at `auth/login.blade.php:16-17,24-25,38,42`) and nothing else in the card drifts further off-scale.
- Toast (`gap-2` = 8px between stacked toasts, `p-4` = 16px internal padding, `top-20 right-4` fixed placement) matches the declared scale exactly.
- Confirm-modal (`p-6` = 24px body padding, `mt-6` = 24px, `gap-3` = 12px) is close to the declared scale — `gap-3` (12px) is a common Tailwind default rather than one of the seven named tokens, but low severity given how minor and standard the value is.
- **Minor violation:** `landing.blade.php:2` — `py-16 sm:py-24`. `py-24` = 96px is not one of the seven declared tokens (4/8/16/24/32/48/64px); the spec states new-build markup — the landing hero explicitly named — follows the 8-point scale "with no further exceptions."
- `mt-2`, `mt-6`, `mt-8` on the landing hero (8px/24px/32px) are all declared-scale values (sm/lg/xl), correctly used for "hero vertical rhythm."

### Pillar 6: Experience Design (4/4)
- Landing redirect logic is correctly implemented: `routes/web.php:11-13` — `auth()->check() ? redirect()->route('dashboard') : view('landing')` — satisfies NAV-01's guest/auth split without gating the guest view behind `auth` middleware.
- Toast dismiss behavior matches the contract precisely: success/info auto-dismisses via `x-init="setTimeout(() => { showStatus = false }, 4000)"` (`toast.blade.php:29`); the error variant has no corresponding timer and only closes via the manual button (`toast.blade.php:62-91`) — the exact FIX-03-preventing behavior the contract calls for.
- The sentinel exclusion list is implemented with strict, exact-equality matching (`in_array(..., true)`), not a substring/pattern match (`toast.blade.php:10,22`) — correctly defuses the landmine the spec flagged (Breeze's `verification-link-sent`/`password-updated`/`profile-updated` sentinels).
- All 3 native `confirm()` call sites are fully migrated to `@submit.prevent` + `<x-confirm-modal>` + `x-ref` form binding (`lecturer/exams/show.blade.php:50,188,265`, `lecturer/subjects/index.blade.php:33`) — grep confirms zero remaining `confirm(` calls in either file.
- `<x-confirm-modal>` correctly builds on the existing `<x-modal>` primitive (focus trap, Escape-to-close, backdrop click) rather than reimplementing it (`confirm-modal.blade.php:21`), and the wrapped `<x-modal>` primitive already carries `dark:bg-gray-800` on its panel (`modal.blade.php:68`), satisfying the spec's dark-mode requirement for a component the primitive didn't originally have it for.
- INT-01's null-guard is wired end-to-end: thrown at all 3 required sites (`app/Models/Attempt.php:161`, `app/Http/Controllers/Student/AttemptController.php:192`, `app/Http/Controllers/Lecturer/AnswerGradeController.php:50`) and self-renders through the error-toast path via `AttemptVanishedException::render()` (`app/Exceptions/AttemptVanishedException.php:65`) rather than surfacing a raw exception page.
- The prior review's dark-mode-toggle gap on the login page is confirmed fixed: `layouts/guest.blade.php:14-28` now carries the same pre-paint bootstrap script as `layouts/app.blade.php` and `layouts/landing.blade.php`, keyed to the same `theme` localStorage value.

---

## Files Audited

- `resources/views/landing.blade.php`
- `resources/views/layouts/landing.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/auth/register.blade.php`, `forgot-password.blade.php`, `verify-email.blade.php`, `confirm-password.blade.php`, `reset-password.blade.php`
- `resources/views/components/toast.blade.php`
- `resources/views/components/confirm-modal.blade.php`
- `resources/views/components/modal.blade.php`
- `resources/views/lecturer/exams/show.blade.php`
- `resources/views/lecturer/subjects/index.blade.php`
- `tailwind.config.js`
- `resources/css/app.css`
- `routes/web.php`
- `app/Exceptions/AttemptVanishedException.php`
- `app/Models/Attempt.php`
- `app/Http/Controllers/Student/AttemptController.php`
- `app/Http/Controllers/Lecturer/AnswerGradeController.php`
- `09-UI-SPEC.md`, `09-CONTEXT.md`
- All 10 `09-*-SUMMARY.md` plan summaries (indexed; implementation verified directly against source rather than trusting summary claims)

**Registry audit:** N/A — no `components.json`, no shadcn, no component registries in play for this Blade/Tailwind/Alpine stack (confirmed by the UI-SPEC's own Registry Safety section).

---
phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p
plan: 06
subsystem: ui
tags: [tailwind, css-custom-properties, flowbite, breeze, blade, design-tokens]

# Dependency graph
requires:
  - phase: 09-02
    provides: The four failing NAV-02 tests in tests/Feature/Auth/AuthenticationTest.php
      that specify the required login card copy, links, token classes, and form wiring.
provides:
  - "theme.extend.colors/borderRadius/boxShadow in tailwind.config.js — the Flowbite 4 token
    vocabulary (brand, fg-brand, heading, body, default, default-medium, neutral) ported to Tailwind 3"
  - "CSS custom properties (--color-brand, --color-heading, etc.) in resources/css/app.css's
    @layer base, defined on both :root and .dark"
  - "resources/views/auth/login.blade.php reskinned to the v3.md Flowbite card, wired to Breeze"
  - "scripts/ui-03-token-gate.sh — committed, re-runnable compiled-CSS acceptance gate"
affects: [09-07, 09-08, 09-10, phase-14-dark-mode-sweep]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Semantic color tokens via CSS custom-property indirection: 'rgb(var(--color-X) / <alpha-value>)'
      in theme.extend.colors, with the actual RGB triplets defined in @layer base :root / .dark. This
      is how a Tailwind 3 project reproduces Tailwind-4-style @theme{} tokens without upgrading."
    - "Compiled-CSS acceptance gate script (scripts/ui-03-token-gate.sh) for verifying utilities that
      Tailwind's JIT only emits when a content file references them — a class of bug PHPUnit and
      browser inspection cannot catch."

key-files:
  created:
    - scripts/ui-03-token-gate.sh
  modified:
    - tailwind.config.js
    - resources/css/app.css
    - resources/views/auth/login.blade.php

key-decisions:
  - "Ported Flowbite 4's @theme{} token values into tailwind.config.js theme.extend rather than
    upgrading to Tailwind v4 (v3.0 Decision #5) — avoids rippling through 28 files of dark: classes."
  - "Added borderRadius.xs: '0.125rem' as a correction to 09-UI-SPEC.md/09-RESEARCH.md, which listed
    only borderRadius.base — Tailwind 3 has no native xs radius key and the login card's remember-me
    checkbox uses rounded-xs."
  - "No separate borderColor/ringColor block added — Tailwind 3's stock preset defines both as
    functions of theme('colors'), so extending colors alone resolves border-default, ring-brand,
    text-fg-brand, bg-brand-strong, and placeholder:text-body."

patterns-established:
  - "Compiled-CSS grep gate as a committed script, not a one-off shell paste, for any future
    Tailwind-JIT-dependent verification (Phase 14's dark-mode sweep will reuse this pattern)."

requirements-completed: [UI-03, NAV-02]

# Metrics
duration: ~7min (three atomic task commits, 12:53–12:58 local time)
completed: 2026-07-17
status: complete
---

# Phase 9 Plan 06: Design Token Port & Login Reskin Summary

**Ported Flowbite 4's semantic color/radius/shadow tokens into Tailwind 3's `theme.extend` via CSS
custom-property indirection, then reskinned the Breeze login page to the exact v3.md Flowbite card —
proven against the compiled CSS bundle by a committed, fail-closed-demonstrated gate script, not a
screenshot.**

## Performance

- **Duration:** ~7 min (task commits 12:53:44 → 12:58:33 local time)
- **Started:** 2026-07-17T04:53Z (approx, first task commit)
- **Completed:** 2026-07-17T04:58:33Z
- **Tasks:** 3/3 completed
- **Files modified:** 3 modified (`tailwind.config.js`, `resources/css/app.css`,
  `resources/views/auth/login.blade.php`), 1 created (`scripts/ui-03-token-gate.sh`)

## Accomplishments
- `tailwind.config.js` `theme.extend` now carries all 11 token colors (brand DEFAULT/strong/medium/soft,
  fg-brand, heading, body, default, default-medium, neutral.primary-soft/secondary-medium), plus
  `borderRadius.base`/`borderRadius.xs` and `boxShadow.xs` — with the `xs` radius correction called
  out in the plan explicitly preserved as a code comment.
- `resources/css/app.css` defines all 11 `--color-*` custom properties on both `:root` and `.dark`
  (22 total lines), flipped by the existing Phase 7 `.dark`-class bootstrap — no `dark:` prefix needed
  on the card.
- `resources/views/auth/login.blade.php` reproduces the `v3.md` Flowbite card structurally and
  class-for-class, wired to real Breeze routes/CSRF/`old()`/inline errors — the 4 previously-failing
  NAV-02 tests plus all 6 pre-existing Breeze auth tests now pass (10/10 in `AuthenticationTest`).
- `scripts/ui-03-token-gate.sh` proves all 18 token checks (11 base-utility rule openings, 5 variant
  substrings, 2 custom-property light/dark values) resolve to real CSS in the compiled bundle, and was
  deliberately demonstrated to fail closed (non-zero exit) before being confirmed to pass again.

## Task Commits

Each task was committed atomically:

1. **Task 1: Port the Flowbite 4 token vocabulary into tailwind.config.js and resources/css/app.css** - `e04dd37` (feat)
2. **Task 2: Reskin resources/views/auth/login.blade.php to the v3.md Flowbite card** - `e96898a` (feat)
3. **Task 3: The UI-03 acceptance gate — prove every login-card token emits real CSS** - `a869131` (test)

**Plan metadata:** (pending — this SUMMARY's own commit)

## Files Created/Modified
- `tailwind.config.js` - Added `colors`, `borderRadius` (incl. corrective `xs` key), `boxShadow` under
  the existing `theme.extend` block; `fontFamily` untouched.
- `resources/css/app.css` - Added a `@layer base` block with `:root`/`.dark` rules defining 11
  `--color-*` custom properties each, after the three existing `@tailwind` directives.
- `resources/views/auth/login.blade.php` - Replaced Breeze's default markup with the v3.md Flowbite
  card, keeping `<x-guest-layout>` and `<x-auth-session-status>`, wired to real routes/CSRF/errors.
- `scripts/ui-03-token-gate.sh` - New committed, re-runnable acceptance gate script (73 lines).

## Decisions Made
- Kept the raw snippet's `<input>` markup instead of the app's `<x-text-input>` component, since the
  acceptance bar (NAV-02, 09-UI-SPEC.md) is pixel-exact reproduction of the v3.md classes — introducing
  the component wrapper would have diverged from the supplied class list.
- Used a single `value="{{ old('email') }}"` attribute (not the Blade-component `:value` binding syntax)
  since the email field is now a raw HTML `<input>`, not `<x-text-input>`.

## Deviations from Plan

None — plan executed exactly as written, including the mandated `borderRadius.xs` correction (which
the plan itself specifies as required, not a deviation from it).

## Verification Evidence

### 1. `scripts/ui-03-token-gate.sh` — PASS (all 18 tokens)

```
Running npm run build...
Build OK.

PASS  bg-brand rule                                 .bg-brand{                          matches=1
PASS  bg-neutral-primary-soft rule                  .bg-neutral-primary-soft{           matches=1
PASS  bg-neutral-secondary-medium rule              .bg-neutral-secondary-medium{       matches=1
PASS  text-heading rule                             .text-heading{                      matches=1
PASS  text-body rule                                .text-body{                         matches=1
PASS  text-fg-brand rule                            .text-fg-brand{                     matches=1
PASS  border-default rule                           .border-default{                    matches=1
PASS  border-default-medium rule                    .border-default-medium{             matches=1
PASS  rounded-base rule                             .rounded-base{                      matches=1
PASS  rounded-xs rule                               .rounded-xs{                        matches=1
PASS  shadow-xs rule                                .shadow-xs{                         matches=1
PASS  bg-brand-strong (hover: variant)              bg-brand-strong                     matches=1
PASS  ring-brand (focus: variant)                   ring-brand                          matches=3
PASS  ring-brand-medium (focus: variant)            ring-brand-medium                   matches=1
PASS  ring-brand-soft (focus: variant)              ring-brand-soft                     matches=1
PASS  border-brand (focus: variant)                 border-brand                        matches=2
PASS  --color-brand light value (:root)             --color-brand: 37 99 235            matches=1
PASS  --color-brand dark value (.dark)              --color-brand: 59 130 246           matches=1

UI-03 TOKEN GATE: PASS — all 18 tokens emit real CSS rules.
EXIT CODE: 0
```

### 2. Fail-closed demonstration (required acceptance criterion)

Deliberately renamed the `brand` color key to `brandx` in `tailwind.config.js` (verified diff before
running), re-ran the gate:

```
Running npm run build...
Build OK.

FAIL  bg-brand rule                                 .bg-brand{                          matches=0
PASS  bg-neutral-primary-soft rule                  .bg-neutral-primary-soft{           matches=1
PASS  bg-neutral-secondary-medium rule              .bg-neutral-secondary-medium{       matches=1
PASS  text-heading rule                             .text-heading{                      matches=1
PASS  text-body rule                                .text-body{                         matches=1
PASS  text-fg-brand rule                            .text-fg-brand{                     matches=1
PASS  border-default rule                           .border-default{                    matches=1
PASS  border-default-medium rule                    .border-default-medium{             matches=1
PASS  rounded-base rule                             .rounded-base{                      matches=1
PASS  rounded-xs rule                               .rounded-xs{                        matches=1
PASS  shadow-xs rule                                .shadow-xs{                         matches=1
FAIL  bg-brand-strong (hover: variant)              bg-brand-strong                     matches=0
FAIL  ring-brand (focus: variant)                   ring-brand                          matches=0
FAIL  ring-brand-medium (focus: variant)            ring-brand-medium                   matches=0
FAIL  ring-brand-soft (focus: variant)              ring-brand-soft                     matches=0
FAIL  border-brand (focus: variant)                 border-brand                        matches=0
PASS  --color-brand light value (:root)             --color-brand: 37 99 235            matches=1
PASS  --color-brand dark value (.dark)              --color-brand: 59 130 246           matches=1

UI-03 TOKEN GATE: FAIL — one or more tokens emitted zero CSS rules.
EXIT CODE: 1
```

Reverted the rename (restored from a pre-edit backup copy), re-ran the gate: exits 0, all 18 PASS
(identical output to section 1 above). `git diff --stat tailwind.config.js` after revert showed zero
diff, confirming the working tree returned exactly to the committed Task 1 state before Task 3's
commit was made.

### 3. `AuthenticationTest` — 10/10 pass

```
✓ login screen can be rendered
✓ users can authenticate using the login screen
✓ users can not authenticate with invalid password
✓ users can logout
✓ the login screen renders the flowbite card
✓ the login card links to the register route
✓ the login card links to the password reset route
✓ the login card uses the ported design tokens
✓ the login form still posts to the login route with csrf
✓ a failed login repopulates the email and shows an inline error

Tests: 10 passed (21 assertions)
```

All 4 previously-failing NAV-02 tests now pass; all 6 pre-existing Breeze auth tests still pass.

### 4. No new dependencies

`git diff package.json composer.json` — empty (confirmed via `git diff --name-only package.json
package-lock.json composer.json composer.lock` returning no output, per Task 1's acceptance criteria).
`tailwindcss` remains pinned at `^3.1.0` (`grep -c '"tailwindcss": "\^3' package.json` returns `1`).

### 5. Full suite totals

```
Tests: 11 failed, 328 passed (813 assertions)
```

Failures (all pre-existing, expected, owned by later plans in this phase — not regressions):
- `LandingPageTest` — 6 failures (→ plan 09-08)
- `ToastTest` — 3 failures (→ plan 09-07)
- `NoNativeDialogTest` — 2 failures (→ plan 09-10)

This exactly matches the plan's stated expected-remaining-RED set. No other test file regressed.

## Issues Encountered
None.

## User Setup Required
None — no external service configuration required.

## Next Phase Readiness
- UI-03's central technical risk is retired: the token port is proven against compiled CSS, not
  eyeballed, and the gate script is committed for reuse by Phase 14's dark-mode sweep (FIX-02, UX-05).
- NAV-02 is complete; the login page is the first real consumer of the ported tokens.
- Plans 09-07 (toast), 09-08 (landing page), and 09-10 (confirm modal) can now build on the same
  `theme.extend` token vocabulary and `scripts/ui-03-token-gate.sh` pattern without re-deriving it.
- No blockers.

---
*Phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p*
*Completed: 2026-07-17*

## Self-Check: PASSED

All created/modified files found on disk: `tailwind.config.js`, `resources/css/app.css`,
`resources/views/auth/login.blade.php`, `scripts/ui-03-token-gate.sh`, and this SUMMARY.md.
All three task commits (`e04dd37`, `e96898a`, `a869131`) verified present in `git log`.

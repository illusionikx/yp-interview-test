# Stack Research

**Domain:** Online examination & student management portal — exam domain built on an existing Laravel 11 + Breeze scaffold
**Latest milestone researched:** v3.0 — Workflow Restructure & UX Polish (2026-07-17)
**Confidence:** HIGH on the Flowbite-4/Tailwind-3 token conflict (verified directly against installed `node_modules`) and on package version compatibility (verified directly against Packagist/npm registries); LOW-MEDIUM on Windows/Herd-specific Dusk behavior and on Flowbite's toast/stepper doc claims (community/search-assisted sources, not hands-on reproduced). v2.0 and v1.0 addenda below remain HIGH / MEDIUM-HIGH as previously assessed.

This document accumulates stack research across milestones. **Read the v3.0 addendum first** — it is what the current roadmap/requirements work should use. The v2.0 and v1.0 research is preserved below it, unedited, for historical reference (v3.0 builds directly on the Blade/Tailwind3/Flowbite4/Alpine stack v1.0 and v2.0 established).

---

## v3.0 Addendum: Workflow Restructure & UX Polish (researched 2026-07-17)

**Domain:** Browser/E2E testing (Laravel Dusk), Flowbite 4.0 design-token UI restyle, app-wide toasts, vertical stepper navigation, arrangeable answer options
**Confidence:** MEDIUM overall (HIGH on the two findings verified against installed code/registries; LOW-MEDIUM on Windows/Herd specifics and Flowbite component docs, sourced via web search rather than hands-on reproduction)

### Summary

v3.0 introduces exactly one new Composer package (`laravel/dusk`, explicitly requested by the user, reversing the project's prior "no new Composer packages" rule for the testing layer only) and zero mandatory new npm packages — every UI requirement (toasts, vertical stepper, arrangeable answers) is achievable with the already-installed Flowbite 4.0.2 + Alpine 3.15.12 + Tailwind 3 stack. The one genuinely hard finding this milestone surfaced: **the exact Flowbite 4.0 semantic-token classes in the user's pasted login markup (`bg-neutral-primary-soft`, `text-heading`, `rounded-base`, etc.) do not and cannot work under the project's installed Tailwind v3** — verified by reading `node_modules/flowbite/src/themes/default.css` directly, not just Flowbite's marketing docs. The recommended fix is not "upgrade to Tailwind v4" (too much blast radius for a UX-polish milestone) but to port the specific token *values* into the existing `tailwind.config.js` as v3 `theme.extend` entries, which reproduces the exact class names from the pasted markup with zero framework change. See full detail below.

### Recommended Stack (v3.0 additions)

#### Core Additions

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| `laravel/dusk` | `^8.6` (latest, v8.6.0, released 2026-04-15 on Packagist) | Browser/E2E testing | `php ^8.1`, `laravel/framework`/`illuminate/support` `^10\|^11\|^12\|^13` — drop-in compatible with the installed Laravel 11.55 / PHP 8.2. Official first-party Laravel browser-testing package; explicitly requested by the user, reversing v2.0's no-new-Composer-packages rule for the testing layer only. |
| ChromeDriver (managed by Dusk, not a Composer/npm dependency) | auto-matched via `php artisan dusk:chrome-driver --detect` | Drives a real Chrome instance for Dusk | Ships as a binary in `vendor/laravel/dusk/bin/`; `--detect` matches it to whatever Chrome is actually installed, avoiding version-skew failures — the single most common cause of Dusk breakage on any OS. |

#### Supporting Libraries — none required; one optional

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| *(none required)* | — | Toasts, vertical stepper, and arrangeable answers are all achievable with the already-installed Flowbite 4.0.2 + Alpine 3.15.12 markup/directives — see "Detailed Findings" below. | — |
| `@alpinejs/sort` (optional, not default) | `3.15.12` (exact match to installed `alpinejs@3.15.12`) | True pointer/touch drag-and-drop reordering | Only if move-up/move-down buttons prove too slow in real usage for reordering answer options. Official first-party Alpine plugin (wraps SortableJS). Not the v3.0 default — see rationale under Q5 below (Dusk-testability). |

### Detailed Findings

#### 1. Laravel Dusk on Windows + Laravel Herd — Confidence: MEDIUM (version/compat verified against Packagist; Windows/Herd specifics are community-sourced)

**Version:** `laravel/dusk ^8.6` is current and fully compatible with this project (`php ^8.1` vs. installed `8.2`; `laravel/framework ^10–^13` vs. installed `11.55`; `php-webdriver/webdriver ^1.15.2`; `guzzlehttp/guzzle ^7.5`).

**Minimal correct setup:**
1. `composer require laravel/dusk --dev` → `php artisan dusk:install` (creates `tests/Browser/`, `Tests\DuskTestCase`, drops a ChromeDriver binary into `vendor/laravel/dusk/bin/`).
2. `php artisan dusk:chrome-driver --detect` — matches ChromeDriver to the locally installed Chrome; re-run after every Chrome auto-update.
3. Create `.env.dusk.local` (the `.local` suffix matters — it's the convention for a locally-run, non-CI environment) with its own `APP_URL` and, critically, its own `DB_DATABASE`. Dusk backs up the real `.env`, swaps this file in as `.env` for the run, and restores the original afterward.
4. **Herd-specific:** this project's directory is already continuously served by Herd at `http://yp-test.test` (or `https://yp-test.test` if `herd secure` has been run) — there is no need to also run `php artisan serve` in parallel. Set `APP_URL` in `.env.dusk.local` to that Herd domain and Dusk's browser hits the already-running site directly. This is a Herd-specific shortcut, simpler than the generic Laravel docs' assumption of manually starting `php artisan serve` on port 8000 — but note it does NOT apply if this project is later run under CI (no Herd there); CI needs the standard `php artisan serve` approach instead (see "Stack Patterns by Variant" below).
5. **Database — this is the part that breaks the project's existing test approach.** The existing PHPUnit suite (294 tests) uses `RefreshDatabase`, which wraps each test in a DB transaction rolled back at the end — this only works because PHPUnit and the app share one PHP process. Dusk's browser is a **separate OS process** making real HTTP requests to the real Herd-served app; there is no shared transaction to roll back, so `RefreshDatabase` cannot be reused for Dusk tests (Laravel's own guidance is explicit on this point). Use `Illuminate\Foundation\Testing\DatabaseTruncation` (migrate once, truncate between tests — faster, available since Laravel 9.51, present and maintained in 11.55) or `DatabaseMigrations` (full migrate every test — slower, simpler to reason about) on `Tests\Browser\*` test classes extending `DuskTestCase`. Point `.env.dusk.local`'s `DB_DATABASE` at a **separate** database (e.g. `yp-student-exam-dusk`), never at `yp-student-exam` itself — truncating/migrating that would destroy the curated demo seed data (the named lecturer/student accounts the README documents as the graded deliverable's demo credentials).
6. In-memory SQLite is explicitly unusable with Dusk (the separate browser process can't see another process's in-memory DB) — moot here since the project is already on MySQL, but confirms MySQL is the correct choice for the Dusk database too, just a second one.

**What breaks on Windows specifically:** two recurring community-reported failure modes (GitHub `laravel/dusk` issues #1044, #215, and independent Windows write-ups), both non-fatal:
- `chromedriver.exe` path/rename handling has regressed on Windows in some Dusk point releases — if `dusk:chrome-driver` can't find the binary it just installed, re-run with `--detect` rather than hand-editing a path.
- Windows Defender / antivirus intermittently quarantines or blocks execution of the freshly-downloaded `chromedriver.exe`, surfacing as a generic "Chrome failed to start" or permission error — add `vendor/laravel/dusk/bin/` to an AV exclusion if this occurs.

**Can Dusk automate the native `beforeunload` dialog (the thing v2.0 deferred as AVL-05)? No — and adopting Dusk does not change this.** Dusk's dialog API (`waitForDialog()`, `assertDialogOpened()`, `acceptDialog()`, `dismissDialog()`) works correctly for JS `alert()`/`confirm()`/`prompt()`. The native `beforeunload` confirmation is different: modern ChromeDriver (126+ — effectively every ChromeDriver Dusk installs today) auto-dismisses `beforeunload` prompts on classic HTTP WebDriver sessions per a W3C spec change, *before* Dusk's dialog API ever sees the prompt. The only documented workaround is WebDriver **BiDi** mode (`webSocketUrl` capability + `unhandledPromptBehavior: 'ignore'`) — Dusk does not configure this, and the broader WebDriver ecosystem advises against enabling BiDi globally due to side effects on other tests. **Recommendation: keep AVL-05's `beforeunload` check as a manual/human-verification checklist item even after Dusk is adopted.** Don't scope a Dusk test for it — it would be flaky-to-permanently-failing depending on the installed ChromeDriver version, not a real regression signal. Everything *else* v2.0 deferred for needing a live browser (modal timing, JS `confirm()` dialogs, countdown/auto-submit visual behavior) **is** now genuinely automatable with Dusk.

#### 2. Flowbite 4.0 semantic design tokens under Tailwind v3 — Confidence: HIGH (verified directly against the installed `node_modules/flowbite` source code, not just documentation)

**Definitive answer: these tokens do NOT work under Tailwind v3, and no configuration change can make them work — they require Tailwind v4's `@theme` engine.** This is not a hedge; it was verified two ways: reading Flowbite's own theming docs, and reading the actual installed package source.

Read directly from the installed `node_modules/flowbite/src/themes/default.css`: every token in the user's pasted markup (`--color-neutral-primary-soft`, `--color-heading`, `--color-brand`, `--radius-base: 12px`, `--color-default`, `--color-fg-brand`, `--color-brand-medium`, etc.) is defined inside a `@theme { ... }` CSS at-rule — a **Tailwind-v4-only** CSS-first configuration construct — with dark-mode overrides in a parallel `.dark { ... }` block. Tailwind v3's JIT engine (driven by `tailwind.config.js`, the format this project uses) does not parse `@theme` at all; it will not generate `bg-neutral-primary-soft`, `text-heading`, `bg-brand`, `rounded-base`, `border-default`, `text-fg-brand`, `focus:ring-brand-medium`, etc. as utility classes under any configuration. They simply won't exist in the compiled CSS.

This also explains an otherwise-surprising detail in the dependency tree: `node_modules/flowbite/package.json` (the installed `flowbite@4.0.2`) itself declares `"tailwindcss": "^4.1.12"` as a plain (non-peer) `dependency` — Flowbite 4.0.2 vendors its own Tailwind v4 internally. Meanwhile this project's top-level `tailwindcss@^3.1.0` + `tailwind.config.js` + the `flowbite/plugin` CJS export (registered as a plugin in `tailwind.config.js`) is Flowbite's **legacy, pre-4.0-theme, v3-compatible integration path** — it supplies component base styles (tooltip/popover positioning, form-control resets) but explicitly does not pull in the new token/theme layer. Per Flowbite's own changelog, this v4 jump happened back at Flowbite 3.0.0 (Jan 2025) — the project has been consuming Flowbite's *components* via the v3-compatible track while sitting on a *later* Flowbite release than that track was designed against.

**Options, decisively:**

| Option | What it takes | Verdict for v3.0 |
|--------|---------------|-------------------|
| **A — Upgrade to Tailwind v4** | Migrate `tailwind.config.js` → CSS-first `@theme` in `resources/css/app.css`; swap the PostCSS pipeline for `@tailwindcss/vite` or the v4 PostCSS plugin; re-verify `@tailwindcss/forms` v4 compatibility; re-audit every existing Blade view for v3→v4 utility renames (e.g. `shadow-sm`→`shadow-xs`, opacity-modifier syntax changes). | **Not recommended for this milestone.** Real regression risk across the *entire* existing app for a milestone scoped as UX polish, not a framework upgrade. |
| **B — Port the token *values* into `tailwind.config.js` as v3 `theme.extend`, reusing the same class names** | Copy the light/dark values straight out of `node_modules/flowbite/src/themes/default.css` into `theme.extend.colors` (as CSS-variable-backed colors, so `.dark` keeps working with the project's existing `darkMode: 'class'` setting) and `theme.extend.borderRadius.base = '12px'`. | **Recommended.** Produces the exact class names from the user's pasted markup working verbatim under Tailwind v3, zero framework upgrade, zero new dependency, correct dark-mode behavior out of the box (the source file's `.dark {}` overrides map 1:1 onto the project's `darkMode: 'class'` convention). |
| **C — Translate to the existing gray palette (v2.0's style: `bg-white dark:bg-gray-800`)** | Keep the plain-gray classes v2.0 already used; ignore the semantic names entirely. | Fallback only, if Option B turns out costlier mid-implementation than expected — unlikely, since the concrete config below is a same-day change. |

**Concrete implementation for Option B** (values read directly from `node_modules/flowbite/src/themes/default.css`, light + `.dark` variants):

Add CSS custom properties to `resources/css/app.css` (light under `:root`, dark overrides under `.dark`, matching the project's existing `darkMode: 'class'` mechanism):

```css
:root {
  --color-body: theme(colors.gray.600);
  --color-heading: theme(colors.gray.900);
  --color-brand: theme(colors.blue.700);
  --color-brand-strong: theme(colors.blue.800);
  --color-brand-medium: theme(colors.blue.200);
  --color-brand-soft: theme(colors.blue.100);
  --color-fg-brand: theme(colors.blue.700);
  --color-neutral-primary-soft: theme(colors.white);
  --color-neutral-secondary-medium: theme(colors.gray.50);
  --color-default: theme(colors.gray.200);
  --color-default-medium: theme(colors.gray.200);
}
.dark {
  --color-body: theme(colors.gray.400);
  --color-heading: theme(colors.white);
  --color-brand: theme(colors.blue.600);
  --color-brand-strong: theme(colors.blue.700);
  --color-brand-medium: theme(colors.blue.900);
  --color-brand-soft: theme(colors.blue.900);
  --color-fg-brand: theme(colors.blue.500);
  --color-neutral-primary-soft: theme(colors.gray.900);
  --color-neutral-secondary-medium: theme(colors.gray.800);
  --color-default: theme(colors.gray.800);
  --color-default-medium: theme(colors.gray.700);
}
```

Then register them as regular v3 theme colors/radii in `tailwind.config.js`:

```js
theme: {
  extend: {
    colors: {
      body: 'var(--color-body)',
      heading: 'var(--color-heading)',
      brand: { DEFAULT: 'var(--color-brand)', strong: 'var(--color-brand-strong)', medium: 'var(--color-brand-medium)', soft: 'var(--color-brand-soft)' },
      'fg-brand': 'var(--color-fg-brand)',
      'neutral-primary-soft': 'var(--color-neutral-primary-soft)',
      'neutral-secondary-medium': 'var(--color-neutral-secondary-medium)',
      default: { DEFAULT: 'var(--color-default)', medium: 'var(--color-default-medium)' },
    },
    borderRadius: { base: '12px' },
  },
},
```

This yields working Tailwind v3 utilities for `bg-brand`, `text-heading`, `border-default`, `rounded-base`, `focus:ring-brand-medium`, `bg-neutral-primary-soft`, etc. — the pasted login markup can be used close to verbatim. Extend this table with more tokens (success/danger/warning families) only as later screens actually need them; don't port the entire ~90-variable source file speculatively.

#### 3. Toasts — Confidence: LOW-MEDIUM (Flowbite docs verified via fetch; the composition pattern is a common community idiom, not an official Flowbite recipe)

Flowbite ships a **static** dismissible toast markup (`bg-neutral-primary-soft rounded-base shadow-xs border border-default` + a close `<button data-dismiss-target="#toast-id">`), wired to Flowbite's `Dismiss` JS object for the close interaction only. **Flowbite has no JS API to programmatically create/show a toast on demand** — that orchestration must be hand-built, which is exactly what "no new JS framework" already expects.

**Recommended minimal Blade+Alpine pattern (zero new dependency):**
1. One toast-container partial, included once in the app layout, with `x-data="toastStack()"` — a small Alpine component holding an array of `{id, type, message}`, a `push()` method, `x-show`/`x-transition` per toast, and a `setTimeout` auto-dismiss (~4s).
2. **Session-flash trigger** (server-driven, e.g. a normal form POST + redirect on create/save/delete): in the layout, `@if(session('status')) <script>window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: @json(session('status')) } }))</script> @endif` (mirrored for `session('error')`); the toast container listens with `x-on:toast.window="push($event.detail)"`.
3. **Client-driven trigger** (any future AJAX/fetch call): dispatch the same `toast` window event from the JS success/error handler — no server round-trip, same listener handles both paths.
4. Style each pushed toast with Flowbite's documented toast markup/classes (using the Option-B ported tokens from Q2, or the plain gray-palette fallback) so it looks identical to Flowbite's own toast — only the show/hide/queue orchestration is custom.

This satisfies "toasters on create/save/delete" and "no default alert" without adding a toast library (`notyf`, `toastify-js`, etc. — see What NOT to Use).

#### 4. Vertical stepper for question navigation — Confidence: LOW-MEDIUM (Flowbite docs verified via fetch, not hands-on)

Flowbite ships a dedicated **Stepper** component (distinct from Flowbite's Timeline component, which is a chronological activity-feed pattern and not the right fit here) with a vertical variant built for exactly this: an `<ol>` of `<li>` steps, each carrying state-specific classes (completed/active/upcoming, distinguished by background/border/text color) and an icon swap — a checkmark SVG for completed steps vs. a number/arrow otherwise. This maps directly onto "vertical stepper for question navigation with a checkmark when answered": drive per-step state from an Alpine `x-data` object tracking which question indices already have a saved answer (already known client-side from the existing autosave flow), and swap classes/icons reactively with `x-bind:class` / `x-show` — no new library. Tailwind-v3-safe as long as the step color classes come from either the ported tokens (Q2, Option B) or the existing gray palette.

#### 5. Sortable/arrangeable answer options — Confidence: LOW-MEDIUM

**Recommended: plain move-up/move-down buttons, zero new dependency.** The same v3.md request already specifies exactly this button pattern for question reordering ("button to move up and move down on the left of the question") — reusing it for answer options keeps the interaction language consistent across the exam editor and needs nothing beyond a small Alpine method that swaps two array indices, plus stable `order` hidden inputs submitted on save. It is also trivially and reliably Dusk-testable — `$browser->click('@move-up-2')` — where drag-and-drop is not.

**If true drag-and-drop is wanted later:** Alpine's own first-party **`@alpinejs/sort`** plugin (npm `3.15.12`, an exact match to the already-installed `alpinejs@3.15.12` — same version line, zero compatibility risk) wraps SortableJS and exposes an `x-sort` directive with minimal boilerplate. This is the "one tiny well-adopted lib" fallback if buttons prove too slow in practice. **Caution:** drag-and-drop is one of the most notoriously flaky interactions to automate in Selenium/Dusk (requires synthesized low-level mouse-down/move/up sequences, timing-sensitive) — given v3.0 is explicitly adding Dusk coverage, prefer buttons specifically so the new suite can assert reordering deterministically.

### What NOT to Use (v3.0)

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| Selenium/Cypress/Playwright as a replacement for Dusk | User explicitly requested Dusk; it's also the only option with zero added language/runtime for a PHP/Laravel-only project. | `laravel/dusk` |
| `RefreshDatabase` in any Dusk test | Relies on DB transactions scoped to the PHPUnit process; Dusk's browser talks to a separate real HTTP process, so the transaction never rolls back what the browser wrote — silent data leakage between tests. | `DatabaseTruncation` (preferred, faster) or `DatabaseMigrations` in `Tests\Browser\*` classes extending `DuskTestCase`, pointed at a dedicated Dusk MySQL database. |
| Running Dusk against `yp-student-exam` (the real dev/demo database) | Truncation/migration between Dusk tests would wipe the curated demo seed data (named lecturer/student accounts documented in the README) the graded deliverable depends on. | A separate `DB_DATABASE` (e.g. `yp-student-exam-dusk`) set in `.env.dusk.local`. |
| A Dusk test asserting the native `beforeunload` confirmation dialog | Modern ChromeDriver (126+) auto-dismisses `beforeunload` prompts on classic WebDriver sessions before Dusk's dialog API can see them; only unsupported BiDi-mode capability overrides can change this. Such a test would be flaky-to-permanently-failing depending on installed ChromeDriver version. | Keep as a manual/human verification checklist item (as v2.0 already scoped it), not a Dusk assertion. |
| Upgrading to Tailwind v4 just to get Flowbite 4's semantic class names | Real migration risk (CSS-first config rewrite, PostCSS/Vite pipeline swap, utility-class renames across every existing Blade view) for a milestone scoped as UX polish, not a framework upgrade. | Port the specific token *values* Flowbite 4 defines into `tailwind.config.js` `theme.extend` under Tailwind v3 (Option B, Q2). |
| A toast library (`notyf`, `toastify-js`, `vue-toastification`, etc.) | Adds a dependency for something a ~30-line Alpine component + existing Flowbite toast markup already covers; contradicts the "no new JS framework" instruction in spirit. | Session-flash + `window` CustomEvent + Alpine `x-data` toast stack (Q3 above). |
| Hand-wiring raw SortableJS, or a jQuery-era drag-and-drop plugin | Either use Alpine's own first-party wrapper (`@alpinejs/sort`) if drag-and-drop is truly wanted, or skip it entirely with buttons — hand-wiring raw SortableJS duplicates the official plugin, and jQuery plugins don't belong in an Alpine/no-jQuery stack. | Move-up/move-down buttons (default), or `@alpinejs/sort` (opt-in only). |

### Stack Patterns by Variant (v3.0)

**If a later milestone explicitly scopes a Tailwind v4 upgrade:**
- Drop the ported `theme.extend` tokens from `tailwind.config.js` and instead import Flowbite's actual theme file (`@import "flowbite/theme"`, or one of its 5 preset themes: modern/minimal/playful/enterprise/mono) directly in `app.css` under `@theme` — the project then gets the *real* token layer (and any other v4-only Flowbite components) for free, instead of the hand-ported subset.
- Because Flowbite's own theme file already ships `.dark {}` overrides, `darkMode: 'class'` keeps working unchanged post-upgrade — no dark-mode logic rewrite needed either way.

**If CI (not just local Windows/Herd) needs to run Dusk:**
- The Herd-specific "no `php artisan serve` needed" shortcut (Q1) does not apply in CI — CI must explicitly boot `php artisan serve` (or Laravel's `Testing\ServeCommand`) and set `APP_URL` to that CI-local server, per the standard non-Herd Dusk docs. Document the local (Herd) and CI setup as two distinct paths, not one.

### Version Compatibility (v3.0)

| Package A | Compatible With | Notes |
|-----------|------------------|-------|
| `laravel/dusk ^8.6` | `laravel/framework ^11.31` (installed 11.55), `php ^8.2` | Verified directly against Packagist's `require` block for v8.6.0 — no conflict. |
| `laravel/dusk` DB testing traits | MySQL only (not SQLite in-memory) | In-memory SQLite is unusable across Dusk's separate browser process; project is already MySQL via Herd, so this only means "use a second database name," not a driver change. |
| Flowbite `^4.0.2` semantic tokens (`bg-brand`, `text-heading`, `rounded-base`, …) | Tailwind CSS **v4 only** | Confirmed by reading `node_modules/flowbite/src/themes/*.css` directly — every token lives in a v4-only `@theme {}` block. Project's `tailwindcss ^3.1.0` + `tailwind.config.js` cannot resolve these classes without porting the values manually (Option B, Q2). |
| `flowbite/plugin` (the CJS export already registered in `tailwind.config.js`) | Tailwind v3 | This is the correct, already-working integration for v3 — component base styles only (tooltips, form resets), not the v4 theme/token layer. No change needed to keep existing Flowbite components (modals, dropdowns, etc.) working. |
| `@alpinejs/sort ^3.15` | `alpinejs ^3.15` (installed: exactly `3.15.12`) | Same version line as the already-resolved `alpinejs` in `node_modules` — safe to add later with zero version conflict, if drag-and-drop is ever adopted. |

### Sources (v3.0)

- https://packagist.org/packages/laravel/dusk.json — fetched directly, version/require verification (HIGH — registry data)
- https://laravel.com/docs/11.x/dusk — official docs, install steps, `.env.dusk.local` semantics, DB trait guidance, dialog API (HIGH — official docs, fetched directly)
- https://github.com/laravel/dusk/issues/1044, #215 — Windows-specific ChromeDriver path/permission reports (MEDIUM — first-party issue tracker, community-reported not officially documented)
- https://makandracards.com/makandra/622849-allow-testing-beforeunload-confirmation-dialogs-modern — ChromeDriver 126+ `beforeunload` auto-dismiss behavior and BiDi requirement (MEDIUM — reputable community dev-notes source, cross-checked against W3C webdriver spec issue w3c/webdriver#1294)
- `node_modules/flowbite/src/themes/default.css` (installed package, read directly) — authoritative source for the exact token definitions and Tailwind-v4 `@theme`/`.dark` structure (HIGH — primary source, the actual installed code)
- `node_modules/flowbite/package.json` (installed package, read directly) — confirms nested `tailwindcss: ^4.1.12` dependency (HIGH — primary source)
- https://flowbite.com/docs/customize/theming/ — official theming docs, confirms Tailwind v4 requirement for the token system (HIGH — official docs, fetched directly)
- https://flowbite.com/docs/getting-started/changelog/ — confirms Flowbite 3.0.0 (Jan 2025) already moved to Tailwind v4 (MEDIUM — official docs via search-assisted fetch)
- https://flowbite.com/docs/components/toast/ , https://github.com/themesberg/flowbite/blob/main/content/components/toast.md — toast markup and `Dismiss` JS object scope (MEDIUM — official docs source, fetched via raw GitHub)
- https://flowbite.com/docs/components/stepper/ , https://github.com/themesberg/flowbite/blob/main/content/components/stepper.md — vertical stepper markup and state-class pattern (MEDIUM — official docs source, fetched via raw GitHub)
- https://alpinejs.dev/plugins/sort — official Alpine.js Sort plugin docs (MEDIUM — official docs, referenced via search)
- https://registry.npmjs.org/@alpinejs/sort , https://registry.npmjs.org/flowbite — fetched directly, version verification (HIGH — registry data)
- https://herd.laravel.com/docs/macos/getting-started/sites — Herd's `.test` parked-directory serving model (MEDIUM — official Herd docs, referenced via search; project is on Windows not macOS, but the parking/serving mechanism is documented as OS-agnostic)
- `D:\Herd\yp-test\composer.json`, `D:\Herd\yp-test\package.json`, `D:\Herd\yp-test\tailwind.config.js` — read directly to confirm installed versions before recommending anything new.

---

## v2.0 Addendum: Enrollment, Exam Availability & Fixes (researched 2026-07-16)

**Domain:** Student self-enrollment, subject-scoped class-sections, exam availability windows, pre-start exam details page, subject↔lecturer assignment, lecturer nav
**Confidence:** HIGH

### Summary

**No new Composer or npm package is justified for v2.0.** Every feature in this milestone — enrollment status, fixed-dropdown rejection reasons, subject-scoped section capacity/windows, exam availability windows, the derived `year-semester-count` section label, and "live" capacity display — is implementable with primitives already present in `composer.json`/`package.json` and already in active use in this exact codebase (v1 already uses the `casts()` method, native backed enums, and named `belongsToMany` pivots — see `app/Models/Exam.php`, `app/Models/User.php`, `app/Models/Classroom.php`). This milestone is pure "more of the same," not a stack change. The one non-obvious idiom worth calling out precisely (see "Laravel 11 Idioms" §3 below) is that pivot columns are **not** automatically cast unless the relationship uses a custom Pivot model — `withPivot()` alone returns raw strings, not enum instances. That single detail is the only place a naive implementation of "enrollment status on a pivot" would silently misbehave.

### Recommended Stack (No Changes)

#### Core Technologies (unchanged from v1 — confirmed still current for 11.x)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Laravel | 11.x (project has 11.31) | Application framework | Confirmed against the live `laravel.com/docs/11.x` pages (fetched 2026-07-16) — `protected function casts(): array`, native enum casting, and `belongsToMany`/pivot APIs used below are all current 11.x syntax, unchanged from what v1 already uses. |
| Breeze / MySQL / Blade+Tailwind+Alpine | as scaffolded | Auth, persistence, views | No feature in this milestone requires anything outside these — confirmed by elimination during requirements review, not just assumption. |

#### Supporting Libraries — none added

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| *(none)* | — | — | See "Native vs. Package Rationale" below for each temptation and why it's unnecessary here. |

### Native vs. Package Rationale

| Feature need | Native primitive | Package alternative | Why native wins here |
|---------------|-------------------|----------------------|------------------------|
| Enrollment status (`enrolled`/`withdrawn`/`rejected`) | Native PHP backed enum + `casts()` (`App\Enums\EnrollmentStatus`) | `spatie/laravel-model-states` | Three linear states with no per-transition side effects/guards beyond a capacity check and a date-window check — same reasoning the v1 STACK already applied to `Attempt::status`. A state-machine package earns its keep when transitions carry hooks/notifications; this doesn't. |
| Rejection reason (fixed dropdown, not free text) | Native PHP backed enum + `casts()` (`App\Enums\RejectionReason`) | Free-text column, or a `reasons` lookup table + FK | A fixed, small, closed set of reasons is exactly what a backed enum models — no runtime editability requirement was stated, so a DB-editable lookup table is unjustified indirection. |
| "Any lecturer of a subject can reject" | `belongsToMany` pivot `subject_user` (Eloquent's alphabetical-singular convention — same pattern already used for `classroom_subject`) | `spatie/laravel-permission` | This is data ("which lecturers teach which subjects"), not a permission system — the check is `$subject->lecturers->contains($user)` or a policy method, not a role/permission grant. Matches the v1 decision to avoid `spatie/laravel-permission` for exactly two fixed roles; this doesn't introduce a third role, so the same reasoning holds. |
| Per-section capacity + enrollment open/close window | Two nullable/typed columns on `classrooms` (`capacity`, `enrollment_opens_at`, `enrollment_closes_at`) cast via `casts()`, checked in a Form Request/Policy at write-time | A scheduling/feature-flag package (e.g. Laravel Pennant) | This is exactly the same "server checks `now()` against a stored timestamp before accepting a write" shape v1 already used for the exam timer (`Attempt::isExpired()`) — no new mechanism needed, just the same pattern applied to enrollment instead of attempt-taking. |
| Exam availability window (`available_from`/`available_until`) | Two nullable `datetime` columns on `exams`, cast via `casts()`, checked in the same place `AttemptPolicy`/`StartAttemptRequest` already gates attempt start | Feature-flag/scheduling package | Same rationale as above — one more `now()` comparison alongside the existing expiry check, not a new capability. |
| `year-semester-count` section label (e.g. `2026-2-1`) | Eloquent accessor via `Attribute::make(get: ...)` reading `year`/`semester`/`count` columns | Denormalized stored `label` column, or a package for slug/label generation | The label is 100% derivable from three small integer columns — storing it denormalized invites drift (the exact anti-pattern v1's own STACK.md flagged for `total_score`: "don't denormalize until recompute is a measured problem"). |
| Live capacity display | Blade renders `$classroom->enrollments()->where('status', 'enrolled')->count()` on each page load; Alpine only for button-disable state and (optionally) a `setInterval` + `fetch()` refresh | Laravel Echo / Reverb / Pusher (broadcasting) | Capacity changing "live" across simultaneous viewers is not a stated requirement, and v1's own STACK.md already explicitly rules out Echo/Reverb/Pusher for this project ("no cross-client real-time sync requirement"). A page-load-accurate count, refreshed by the same Alpine `setInterval` pattern already used for the exam countdown, is the same-shape solution the project has already chosen once. |
| Datetime input for windows | Native HTML `<input type="datetime-local">` + Laravel's `datetime` cast (Carbon parses the `YYYY-MM-DDTHH:MM` value the browser submits with no special handling) | A JS datepicker library (flatpickr, etc.) | No stated requirement for a fancier picker UX; the native browser control needs zero JS and zero npm install, consistent with the "no SPA, minimal JS" constraint. |

**Bottom line: zero new dependencies.** Every "supporting library" temptation in this table is either already-avoided-once (Echo/Reverb, spatie/permission, a state-machine package) or introduces DB/runtime flexibility (editable reasons, feature flags) the requirements don't ask for.

### Laravel 11 Idioms to Use

#### 1. Native backed enums via `casts()` (matches existing v1 convention exactly)

```php
// app/Enums/EnrollmentStatus.php
enum EnrollmentStatus: string
{
    case Enrolled = 'enrolled';
    case Withdrawn = 'withdrawn';
    case Rejected = 'rejected';
}

// app/Enums/RejectionReason.php — exact case list is a REQUIREMENTS.md decision,
// not a stack decision; shape only, illustrative cases below.
enum RejectionReason: string
{
    case CapacityExceeded = 'capacity_exceeded';
    case IneligibleForSubject = 'ineligible_for_subject';
    case Other = 'other';
}
```

```php
// app/Models/Enrollment.php (or the custom Pivot model — see §3)
protected function casts(): array
{
    return [
        'status' => EnrollmentStatus::class,
        'rejection_reason' => RejectionReason::class,
        'enrolled_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];
}
```

This is the identical `protected function casts(): array` shape already used in `User::casts()` (for `role => Role::class`), `Exam::casts()`, and `Attempt::casts()` — no new convention, just two more enums added to the same pattern. Confirmed current for Laravel 11.x directly against `laravel.com/docs/11.x/eloquent-mutators` (Enum Casting section).

#### 2. Datetime windows: cast + native `datetime-local` input, no library

```php
// Classroom / Exam models
protected function casts(): array
{
    return [
        'enrollment_opens_at' => 'datetime',
        'enrollment_closes_at' => 'datetime',
        'available_from' => 'datetime',
        'available_until' => 'datetime',
    ];
}
```

```blade
<input type="datetime-local" name="available_from"
       value="{{ old('available_from', optional($exam->available_from)->format('Y-m-d\TH:i')) }}">
```

Validation stays a plain Form Request rule, no custom cast needed:

```php
'available_from' => ['nullable', 'date'],
'available_until' => ['nullable', 'date', 'after:available_from'],
```

The browser submits `YYYY-MM-DDTHH:MM` (no seconds, no timezone) for `datetime-local`; Carbon (used internally by the `datetime` cast) parses this format natively — no adapter or custom cast class required. Server-side gating follows the exact `now() >= X` pattern already established for `Attempt::isExpired()` — apply the same shape to `available_from`/`available_until` and to `enrollment_opens_at`/`enrollment_closes_at`.

#### 3. Pivot with a status column — the one non-obvious detail

For `subject_user` (lecturer↔subject), Eloquent's alphabetical-singular naming convention applies automatically (same as the existing `classroom_subject` pivot) — no extra columns needed beyond the two FKs, so plain `belongsToMany` is sufficient:

```php
// Subject.php
public function lecturers(): BelongsToMany
{
    return $this->belongsToMany(User::class);
}
```

For enrollments, **`withPivot('status')` alone is not enough** — pivot attributes returned via the default lightweight `Pivot` object are plain scalars; the enum cast only applies if the relationship uses a custom Pivot model via `->using()`:

```php
// app/Models/Enrollment.php — a real Eloquent model, not just a bag of pivot columns,
// because it needs its own casts() and (per Laravel's documented pattern for pivot
// models that need their own primary key) may set $incrementing = true if `enrollments`
// has its own auto-increment id rather than a composite (user_id, classroom_id) key.
class Enrollment extends Pivot
{
    public $incrementing = true; // only if `enrollments.id` is its own PK

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'rejection_reason' => RejectionReason::class,
            'enrolled_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }
}
```

```php
// Classroom.php
public function students(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'enrollments')
        ->using(Enrollment::class)
        ->withPivot('status', 'rejection_reason', 'enrolled_at', 'withdrawn_at')
        ->withTimestamps();
}
```

This also allows direct queries (`Enrollment::where('status', EnrollmentStatus::Enrolled)->count()`) alongside the relationship access (`$classroom->students`), which is exactly what "live capacity" (a `count()` of enrolled rows) and the lecturer rejection action both need. Confirmed current for Laravel 11.x directly against `laravel.com/docs/11.x/eloquent-relationships` (`withPivot`, `wherePivot`/`wherePivotIn`, `withPivotValue`, `->as()`, `->using()` with a custom Pivot model).

#### 4. Derived label via accessor, not a stored column

```php
// Classroom.php (the subject-scoped "section")
protected function label(): Attribute
{
    return Attribute::make(
        get: fn (mixed $value, array $attributes) => sprintf(
            '%d-%d-%d',
            $attributes['year'],
            $attributes['semester'],
            $attributes['count'],
        ),
    );
}
```

`$classroom->label` then always reflects `year`/`semester`/`count` with zero drift risk — confirmed current 11.x accessor syntax (`Attribute::make(get: fn (mixed $value, array $attributes) => ...)`) against the same official docs page.

#### 5. Alpine — same role it already has, no addition

Alpine is already installed (`alpinejs ^3.4.2`) and already used for the exam countdown (`setInterval` reading a server-supplied deadline). For live capacity, the recommended default is simplest: render the count server-side on each page load/redirect (a plain Blade interpolation), with Alpine only handling client-side button state (disable "Apply" once `remaining <= 0`, matching the same `x-data`/`x-init` shape as the countdown). If a genuinely live (no-reload) refresh is wanted, extend the same Alpine component with a `setInterval` + `fetch()` poll against a small JSON endpoint — still zero new dependencies, and explicitly not Echo/Reverb/Pusher (already ruled out in the v1 stack for lack of a real cross-client sync requirement, and nothing in this milestone changes that).

### What NOT to Use (reaffirmed for v2.0)

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| `spatie/laravel-permission` | Still no dynamic/runtime-editable roles or permissions in this milestone — subject↔lecturer is a data relationship (`subject_user`), not a permission grant | `belongsToMany` pivot + a policy method (`$subject->lecturers->contains($user)`) |
| `spatie/laravel-model-states` (or similar) | Enrollment has 3 linear states, same shape as `Attempt::status` in v1, which the v1 stack deliberately kept as a plain enum-cast column | Native backed enum + `casts()`, guard checks in the controller/policy |
| Laravel Echo / Reverb / Pusher (broadcasting) | No stated cross-client real-time requirement for capacity counts; matches the v1 exclusion verbatim | Server-rendered count on page load + optional Alpine polling |
| A JS datepicker library (flatpickr, Pikaday, etc.) | No stated UX requirement beyond a datetime input; native `datetime-local` needs no JS | `<input type="datetime-local">` |
| A free-text `rejection_reason` string or a DB-editable lookup table | Requirement explicitly says "fixed dropdown," not admin-editable | Native backed enum (`RejectionReason`) |
| Denormalized stored `label`/`capacity_remaining` columns | Both are cheaply derivable (`Attribute::make` accessor / a `count()` query) at this project's scale (a handful of sections) | Accessor for the label; live `count()` query for remaining capacity |

### Integration Points with Existing v1 Conventions

- **Enum location & casting convention**: new enums go in `app/Enums/` alongside `Role.php` and `QuestionType.php`; every model casts them via `protected function casts(): array`, never the legacy `$casts` property — matches `User`, `Exam`, `Attempt` exactly.
- **Pivot naming convention**: `subject_user` needs no explicit pivot-table override (alphabetical-singular convention, same as `classroom_subject`); only pivots that break that convention need an explicit name argument, as `exam_classroom` already demonstrates.
- **Window-gating pattern**: `enrollment_opens_at`/`enrollment_closes_at` and `available_from`/`available_until` should reuse the exact `now() >= $deadline` chokepoint pattern `Attempt::isExpired()`/`finalizeIfExpired()` established — a single method on the model (or a small policy check), called at the top of every write-path controller action, not duplicated ad hoc.
- **Authorization convention**: "any lecturer of a subject can reject" extends the existing Policy-per-model pattern (`ExamPolicy`, `AttemptPolicy`) — add an `EnrollmentPolicy::reject()` that checks subject-teacher membership via the `subject_user` pivot, called through `$this->authorize()`, not a new Gate.
- **User model change**: this milestone drops `users.classroom_id` and the direct `User::classroom()` relationship (per the PROJECT.md schema note) in favor of the `enrollments` pivot — any code currently reading `$user->classroom` (there is exam-visibility logic in `Exam::scopeVisibleTo()` that depends on `$user->classroom_id`) must be migrated to derive "the student's current section(s)" from `Enrollment::where('status', EnrollmentStatus::Enrolled)` instead. This is a requirements/roadmap-level migration concern, not a new stack dependency, but it is the highest-blast-radius integration point in this milestone and should be flagged to the roadmapper as needing its own phase-ordering care (Exam visibility must be re-derived before/alongside the enrollment feature ships, or a student could transiently see zero exams).
- **Frontend stack**: no change — Blade + Tailwind 3 + Alpine + Vite, same as v1.

### Version Compatibility (v2.0)

| Package A | Compatible With | Notes |
|-----------|------------------|-------|
| Laravel 11.31 | PHP 8.2+ | Unchanged from v1; no version bump needed for any idiom above. |
| `protected function casts(): array` enum/datetime casting | Laravel 11.x (also works 10.x+, but this is the 11-current convention) | Directly confirmed against the live `laravel.com/docs/11.x/eloquent-mutators` page (fetched 2026-07-16); page banner confirms 11.x is a still-published, if superseded, docs version — syntax unchanged from what v1 already ships. |
| `belongsToMany(...)->using(CustomPivot::class)` | Laravel 11.x | Directly confirmed against the live `laravel.com/docs/11.x/eloquent-relationships` page (fetched 2026-07-16) — `withPivot`, `wherePivot`/`wherePivotIn`/`wherePivotNull`, `withPivotValue`, `->as()`, `->using()` all present and unchanged. |
| HTML `<input type="datetime-local">` | All evergreen browsers; Carbon (bundled with Laravel) | No Laravel-version dependency — plain web platform feature. |
| Alpine.js 3.4.2 | Already installed, no upgrade needed | Same version already used for the exam countdown; no new API surface required for capacity display or button-state toggling. |

### Sources (v2.0)

- https://laravel.com/docs/11.x/eloquent-mutators — Enum Casting, Date Casting, and accessor (`Attribute::make`) sections fetched and quoted directly (2026-07-16). Confidence: HIGH — official first-party docs, and the exact `casts()` shape shown is already in production use in this codebase's `Exam`, `Attempt`, and `User` models, giving a direct internal cross-check with zero drift.
- https://laravel.com/docs/11.x/eloquent-relationships — `withPivot`, `wherePivot*`, `withPivotValue`, `->as()`, `->using()` with custom Pivot models fetched and quoted directly (2026-07-16). Confidence: HIGH — same official-docs + internal-cross-check reasoning (the existing `exam_classroom` explicit-pivot-name pattern in `Exam.php`/`Classroom.php` already demonstrates the base `belongsToMany` API working as documented).
- Community cross-check (Laracasts, Medium, dev.to — aggregated via web search, 2026-07-16): confirms no third-party package (`spatie/enum`, `spatie/laravel-permission`, etc.) appears in current community guidance for "fixed-choice status on a pivot" — treated universally as a native backed-enum + custom-Pivot-model problem. Confidence: MEDIUM (community sources, used only to corroborate the official-docs finding above, not as a standalone claim).
- `D:\Herd\yp-test\composer.json`, `D:\Herd\yp-test\package.json` — read directly to confirm no relevant package is already present that would change the "zero new dependencies" conclusion.
- `D:\Herd\yp-test\app\Models\{Exam,Attempt,User,Classroom,Subject}.php` — read directly to confirm the exact conventions this milestone must extend (casts() shape, pivot naming, policy-per-model pattern).

**Note on automated confidence tiering:** this project's `classify-confidence` seam defaults generic `webfetch`/`websearch` fetches to LOW because it cannot distinguish a fetch of `laravel.com/docs` from a fetch of an arbitrary blog. The HIGH ratings above are a manual override justified by (a) the source being Laravel's own first-party documentation, directly quoted, and (b) every syntax claim being independently cross-checked against this project's own already-shipped v1 code, which already uses the identical `casts()`/pivot patterns without issue.

---

## v1.0 Original Research (2026-07-15, preserved unchanged)

**Domain:** Online examination & student management portal — exam domain built on an existing Laravel 11 + Breeze scaffold
**Researched:** 2026-07-15
**Confidence:** MEDIUM-HIGH (Laravel-native mechanisms verified against official laravel.com/docs/11.x pages and cross-referenced community sources; architectural judgment calls — e.g. lazy vs. scheduled auto-submit — are opinionated recommendations, not hard API facts)

This document assumes the fixed stack from PROJECT.md: **Laravel 11, Breeze (already scaffolded), MySQL, Blade + Tailwind 3 + Alpine.js**. It does not propose replacing any of these. It recommends the Laravel-native mechanisms and minimal supporting libraries for the exam domain (roles, timed attempts, grading, seeding) on top of that scaffold.

### Recommended Stack

#### Core Technologies (already fixed — confirmed compatible, no action needed)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Laravel | 11.x (project has 11.31) | Application framework | Mandated. Laravel 11 slimmed the app skeleton: no `app/Http/Kernel.php` or `app/Console/Kernel.php` — middleware aliases are registered in `bootstrap/app.php` via `->withMiddleware()`, and scheduled tasks are defined in `routes/console.php` via the `Schedule` facade. Every recommendation below targets this Laravel-11-shaped skeleton, not the Laravel-10 Kernel-based one. |
| Breeze | 2.4 (installed) | Auth scaffolding | Already provides register/login/logout/password-reset/email-verification/profile and the base Blade layout. Do not touch. |
| MySQL | 8.x (via Herd, `yp-student-exam`) | Persistence | Already configured in `.env`. All domain tables below are plain relational tables — no MySQL-specific features (JSON columns, etc.) are required. |
| Blade + Tailwind 3 + Alpine.js | as scaffolded | Views/interactivity | Sufficient for a countdown timer and grading forms — see Supporting Libraries. No SPA/Livewire/Inertia needed. |

#### Supporting Libraries (what to actually add)

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| *(none — no new Composer packages required for RBAC, timer, or grading)* | — | — | The entire exam domain (roles, timed attempts, auto-grade, manual grade, seeding) is implementable with Laravel-native primitives already in `composer.json`. See "What NOT to Use" for the packages you'll be tempted to add and why they're unnecessary. |
| `fakerphp/faker` | already present (transitive via `laravel/framework`'s factory support, or dev dependency in a fresh Breeze install) | Realistic fake data in factories (student names, subject titles, etc.) | Already available — do not `composer require` it separately; just use `fake()`/`$this->faker` inside factories. |

Nothing else needs to be installed. The two Laravel-native mechanisms that carry this whole feature set are **Policies + custom role middleware** (access control) and **a server-checked `expires_at` timestamp** (timer enforcement) — both are core framework features, not add-ons.

#### Development Tools (already present — no changes)

| Tool | Purpose | Notes |
|------|---------|-------|
| PHPUnit | Automated tests | Use Feature tests for role gating, one-attempt-per-exam enforcement, and expiry behavior — these are exactly the areas most likely to regress silently. |
| Pint | Code style | Run before commits; no config changes needed. |
| Pail / Telescope | Log tailing / debugging | Useful while building the timer-expiry edge cases; not required by any recommendation below. |

### Detailed Recommendations

#### 1. Role-based access control (Lecturer, Student) — Confidence: HIGH

**Do this:**
- Add a `role` column to `users` (migration: `$table->string('role')->default('student');`), backed by a PHP native enum: `app/Enums/Role.php` → `enum Role: string { case Lecturer = 'lecturer'; case Student = 'student'; }`.
- Cast it on the `User` model using Laravel 11's `casts()` method (the newer convention that replaces/complements the `$casts` property): `protected function casts(): array { return ['role' => Role::class]; }`. This gives `$user->role === Role::Lecturer` type safety and IDE autocomplete with zero extra dependency — Laravel has supported native backed-enum casting since Laravel 8+.
- Add two trivial helper methods on `User`: `isLecturer(): bool` and `isStudent(): bool`. Every other role check in the app (Blade `@if`, Form Request `authorize()`, Policies) reads through these, not raw string comparisons.
- **Route-group protection via custom middleware, not Gates.** Create `app/Http/Middleware/EnsureUserHasRole.php` (single parameterized middleware: `middleware('role:lecturer')` / `middleware('role:student')`), register the alias in `bootstrap/app.php`:
  ```php
  ->withMiddleware(function (Middleware $middleware) {
      $middleware->alias(['role' => \App\Http\Middleware\EnsureUserHasRole::class]);
  })
  ```
  Then group routes: `Route::middleware(['auth','role:lecturer'])->prefix('lecturer')->group(...)` and the equivalent for students. This is the Laravel-11-idiomatic replacement for the old `Kernel::$routeMiddleware` array.
- **Model-scoped authorization via Policies, not Gates.** Anything tied to a specific record — "can this lecturer edit *this* exam", "can this student view *this* attempt" — belongs in a Policy (`ExamPolicy`, `AttemptPolicy`, `AnswerPolicy`), auto-discovered by Laravel's `app/Models` ↔ `app/Policies` naming convention (no manual registration needed). Call via `$this->authorize('update', $exam)` in controllers or the `can:` middleware/Blade directive.
- Reserve **Gates** (`Gate::define` in `AppServiceProvider::boot()`) only for the rare check that isn't tied to a model at all (e.g., "can view the lecturer dashboard shell"). For this domain there are very few of these — most access decisions are either route-group-level (middleware) or record-level (policy).

**What NOT to use:** `spatie/laravel-permission`. It is the dominant Laravel RBAC package (100M+ installs, actively maintained, current release 8.3.0 requiring Laravel 12/13 — but its ^6.x line supports Laravel 11 fine via Composer's normal resolution) and is the right tool when roles/permissions are **dynamic and admin-editable** at runtime (arbitrary many-to-many role↔permission assignment, permission caching layer, teams/guards). This project has exactly two roles, fixed at build time, assigned once at account creation and never reassigned through the UI. Installing it here buys: two extra migrations, a permission cache to reason about, a `HasRoles` trait, and a mental model (permissions, not just roles) the project doesn't need — for a distinction (`lecturer` vs `student`) that a single enum column and two Policy classes express just as correctly and far more legibly for a reviewer reading the code. Add it later only if a third role or per-user granular permissions genuinely materialize — not preemptively.

#### 2. Server-enforced exam timer with client countdown + auto-submit — Confidence: MEDIUM-HIGH

**Schema (on `exam_attempts`):**
- `started_at` (timestamp) — set once, when the student begins the attempt.
- `duration_minutes` (unsigned int) — **copied from the exam at start time**, not read live from `exams.duration_minutes` on every check. This is important: if a lecturer edits an in-progress exam's duration, already-started attempts must not shift underneath the student.
- `expires_at` (timestamp) — computed and **stored** at start time (`started_at->addMinutes($duration)`), not recomputed on every request. Storing it once avoids drift and makes every subsequent check a single indexed comparison (`now() > expires_at`).
- `submitted_at` (nullable timestamp) — null while in progress, set on finalization (student-initiated or auto-submit).

**Server is the sole authority — client timer is cosmetic only:**
- Every write-path route that touches an attempt (save an answer, finalize/submit) must check `now() >= $attempt->expires_at` (or `submitted_at !== null`) **before** accepting the write, and short-circuit into "auto-finalize and redirect to results" if expired. Do this once, centrally — either a Form Request's `authorize()` (e.g. `SubmitAnswerRequest`) or a small `EnsureAttemptIsActive` middleware applied to the attempt-taking route group — not duplicated ad hoc in each controller method.
- **Recommended enforcement mechanism for this project: lazy/on-touch finalization, not a cron sweep.** Any request that touches an expired-but-not-yet-submitted attempt (the exam-taking page itself, or an answer-save call) triggers finalization inline, synchronously, in that request. This requires zero background infrastructure — no queue worker, no `schedule:run` cron entry — which matters because this is a small graded deliverable evaluated by cloning the repo and clicking through it; a queue worker that isn't running is a common and confusing demo failure mode, and this project doesn't need attempts to close themselves the instant they expire if nobody is looking at them.
- **Optional, not required for MVP:** a scheduled sweep (`routes/console.php`: `Schedule::command('exams:auto-submit')->everyMinute();`, backed by an Artisan command that finds `expires_at < now() AND submitted_at IS NULL` and finalizes them) is the "textbook" complete answer and is easy to add later if the requirement grows (e.g., a lecturer needs to see an attempt flip to "closed" in real time without the student revisiting the tab). Don't build it up front — it's additional infrastructure (a cron entry, or `schedule:work` running continuously) for a scenario the current requirements don't call for. Note this is a Laravel-11-specific location: since Laravel 11 removed `Console/Kernel.php`, scheduled commands are defined directly in `routes/console.php` (or a closure in `bootstrap/app.php`'s `withSchedule()`), not in a Kernel `schedule()` method.

**Client countdown (Alpine.js, Blade):**
- Pass the attempt's `expires_at` to the view as an ISO-8601 string or epoch-ms integer (`{{ $attempt->expires_at->timestamp * 1000 }}`), not a pre-computed "seconds remaining" — computing remaining time client-side from an absolute timestamp avoids drift from page-load delay.
- A single Alpine component handles display and the auto-submit trigger:
  ```html
  <div x-data="{
        expiresAt: {{ $attempt->expires_at->valueOf() }},
        remaining: 0,
        tick() {
          this.remaining = Math.max(0, this.expiresAt - Date.now());
          if (this.remaining <= 0) { this.$refs.examForm.requestSubmit(); }
        }
      }"
      x-init="tick(); setInterval(() => tick(), 1000)">
    <span x-text="new Date(remaining).toISOString().substr(11,8)"></span>
  </div>
  ```
- This is purely UX — the actual submit endpoint still re-checks `now()` against the stored `expires_at` server-side, so a student pausing JS or editing the DOM cannot extend their time.

**What NOT to use:** Laravel Echo / Pusher / Reverb (broadcasting) for the timer — this is a single-user countdown with no cross-client sync requirement; WebSockets solve a problem this feature doesn't have. Also avoid making a queued/delayed job (`AutoSubmitAttempt::dispatch($attempt)->delay($expiresAt)`) the *only* enforcement mechanism — it's fragile (depends on a queue worker being alive at exactly the right moment, and delayed jobs can be pushed back by a busy queue) and doesn't remove the need for the on-write check anyway, so it would be pure added complexity with no security benefit.

#### 3. Auto-grading MCQ, manual grading open-text — Confidence: HIGH

**Schema:**
- `questions`: `exam_id`, `type` (native enum: `App\Enums\QuestionType::{Mcq, Essay}`, cast via `casts()`), `body`, `max_score`.
- `options`: `question_id`, `body`, `is_correct` (bool) — only populated for `mcq` questions.
- `answers`: `attempt_id`, `question_id`, nullable `option_id` (the student's MCQ choice), nullable `text_answer` (essay response), nullable `is_correct` (bool, MCQ only), nullable `score_awarded` (decimal), nullable `graded_by` (FK to `users`), nullable `graded_at`.

**Grading logic — a plain service class, not model observers:**
- Put grading logic in `app/Services/AttemptGrader.php` with a method like `gradeMcqAnswers(Attempt $attempt): void` that, for each MCQ answer, compares `answer->option_id` to the question's correct option and sets `is_correct` + `score_awarded` accordingly. Invoke this explicitly at finalization time (student submits, or auto-submit fires) — **not** via an Eloquent model `saving`/`created` event/observer on `Answer`. Model events make grading a hidden side effect of "saving a row," which is surprising to a reader and easy to accidentally re-trigger (e.g., re-grading on every autosave of a draft answer). An explicit service method called exactly once, at exactly the "this attempt is now finished" transition, is easier to reason about and to test.
- Manual grading (essay questions) is a simple lecturer-facing form: a `GradeAnswerRequest` Form Request validates `score` is numeric and within `[0, $question->max_score]`, the controller sets `score_awarded`, `graded_by = auth()->id()`, `graded_at = now()`.
- Track whether an attempt is "fully graded" (all essay answers have `graded_at`) — expose as an accessor on `Attempt` (e.g. `isFullyGraded(): bool`) computed from its answers relationship. Only compute/cache a denormalized `total_score` on `attempts` if the results-listing page shows scores for many attempts at once and recomputing per-row on every page load becomes noticeably expensive — for this project's scale (a handful of classes/students) a live accessor is simpler and avoids a stale-cache bug class; don't add the denormalized column pre-emptively.

**What NOT to use:** no quiz/exam Composer package (e.g. random GitHub "laravel-quiz" packages) — these are low-adoption, don't match this project's specific Subject/Class-scoped assignment model, and building ~6 focused domain tables directly gives full control for less total complexity than adapting a generic package's schema.

#### 4. Eloquent relationships / domain schema — Confidence: HIGH

```
User (role: lecturer|student)
  ├─ hasMany Exam            (as author_id, lecturer only)
  ├─ belongsTo SchoolClass    (as class_id, student only, nullable)
  └─ hasMany Attempt          (as student_id, student only)

SchoolClass                   (do NOT name the model `Class` — reserved word in PHP)
  ├─ hasMany User             (students)
  └─ belongsToMany Subject    (pivot: class_subject)

Subject
  ├─ belongsToMany SchoolClass
  └─ hasMany Exam

Exam
  ├─ belongsTo Subject
  ├─ belongsTo User           (author, lecturer)
  ├─ hasMany Question
  ├─ belongsToMany SchoolClass (pivot: exam_class — "assigned to")
  └─ hasMany Attempt

Question
  ├─ belongsTo Exam
  └─ hasMany Option            (mcq only)

Attempt
  ├─ belongsTo Exam
  ├─ belongsTo User            (student)
  └─ hasMany Answer

Answer
  ├─ belongsTo Attempt
  ├─ belongsTo Question
  └─ belongsTo Option (nullable)
```

**Key integrity rule:** "one attempt per student per exam" must be enforced with a **database-level unique composite index** on `attempts (exam_id, user_id)`, not just an application-level check-then-create. Wrap attempt creation in `DB::transaction()` using `firstOrCreate()` (which relies on that unique index to be race-safe) so a student double-clicking "Start Exam" — or two tabs — cannot create two attempts. An app-only check (`if (! Attempt::where(...)->exists()) { create... }`) has a race window between the check and the write; the unique index is what actually guarantees the invariant.

**Naming pitfall to flag explicitly:** `Class` is a reserved word in PHP and cannot be used as a model class name (`App\Models\Class` will not compile). Use `SchoolClass`, `ClassGroup`, or `Cohort` instead — decide the name in the roadmap/phase plan, not here, but do not let anyone reach for `Class`.

#### 5. Factories & seeders (demo data) — Confidence: HIGH

- Write a factory per model: `UserFactory` (add `lecturer()` and `student()` state methods setting the `role` enum and, for students, an associated class), `SchoolClassFactory`, `SubjectFactory`, `ExamFactory`, `QuestionFactory` (with an `mcq()`/`essay()` state, and an `hasOptions()` callback that attaches 3-4 `Option` rows with exactly one `is_correct`), `AttemptFactory`, `AnswerFactory`.
- `DatabaseSeeder` should produce a **reviewable demo scenario**, not just random noise: one lecturer and a handful of students with **known, fixed credentials documented in the README** (e.g. `lecturer@example.com` / `student1@example.com`, a shared demo password), 1-2 classes, 2-3 subjects, and at least one sample exam per subject containing both an MCQ and an essay question, assigned to a class. Use explicit `User::factory()->lecturer()->create(['email' => 'lecturer@example.com', ...])` (or `firstOrCreate`) for these named accounts so `php artisan db:seed` is safely re-runnable without unique-constraint errors; use plain `factory()->count(n)->create()` for filler/bulk data where exact identity doesn't matter.
- No seeding package is needed — `fakerphp/faker` (already available through Laravel's factory support) covers realistic names/text, and Laravel's built-in `Model::factory()` states/sequences cover everything else (roles, question types, correct-option selection).

#### 6. Form Requests — Confidence: HIGH

- One Form Request per meaningful write action: `StoreExamRequest`, `UpdateExamRequest`, `AssignExamToClassesRequest`, `StartAttemptRequest`, `SubmitAnswerRequest`, `FinalizeAttemptRequest`, `GradeAnswerRequest`.
- Put **ownership/role checks** in `authorize()` (e.g. `return $this->user()->isLecturer() && $this->route('exam')->author_id === $this->user()->id;`) so invalid requests are rejected before validation runs. For anything already covered by a Policy, call `$this->user()->can('update', $this->route('exam'))` inside `authorize()` instead of re-deriving the check inline — keep the ownership rule defined once, in the Policy, and reused everywhere (Form Request, Blade `@can`, controller `authorize()` calls).
- Put **shape/format validation** (required fields, `max_score` bounds, timer duration ranges, etc.) in `rules()` as usual.

### Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|--------------------------|
| Role enum column + Gates/Policies/middleware | `spatie/laravel-permission` | Roles/permissions become dynamic (admin can create new roles or assign fine-grained permissions at runtime) or the number of roles grows past a handful with overlapping permission sets. |
| Lazy/on-touch attempt finalization | Scheduled sweep (`Schedule::command(...)->everyMinute()`) closing expired attempts | You need an attempt to visibly flip to "closed"/"submitted" the instant it expires even if the student never returns to the tab (e.g., a lecturer live-monitoring dashboard). |
| Plain Eloquent enum-cast `status` column on `Attempt` (`in_progress`/`submitted`/`graded`) | `spatie/laravel-model-states` (or similar state-machine package) | The attempt lifecycle grows real branching transitions with side effects per transition (e.g., notifications, multi-step review workflow) — three linear states don't warrant it. |
| Live accessor for attempt total score | Denormalized `total_score` column recomputed on grade save | The results-listing view needs to sort/filter by score across hundreds of attempts and recomputing per request becomes a measured performance problem. |
| Custom `EnsureUserHasRole` middleware | Laravel's built-in `can:` middleware exclusively | If every access rule is naturally model-scoped (no route-group-level "lecturer area" vs "student area" split), you could skip the custom role middleware and rely purely on Policies + `can:` — this project has enough route-group-level splitting (whole "lecturer" vs "whole "student" areas) that the extra middleware pays for itself. |

### What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| `spatie/laravel-permission` | Built for dynamic, database-editable roles/permissions with a permission cache layer and pivot tables; for exactly two fixed roles it adds migrations, a caching model to reason about, and indirection with no correctness benefit. | `role` enum column on `users` + native Gates/Policies/middleware. |
| Livewire / Inertia / any SPA layer | Breeze's Blade+Tailwind+Alpine stack is already scaffolded and mandated; introducing a reactive component framework mid-project for a handful of forms and a timer is unjustified surface area. | Blade forms + the Alpine countdown component above. |
| Laravel Echo / Pusher / Reverb (broadcasting) | The timer is single-user; there's no cross-client real-time sync requirement. | Alpine `setInterval` client display + server-side `expires_at` check on write. |
| Queued/delayed job as the *sole* auto-submit mechanism | Depends on a queue worker being alive at the precise moment; delayed jobs can slip under load; still requires the on-write server check regardless, so it adds infrastructure without removing any risk. | On-write `now() >= expires_at` check (lazy finalization), optionally supplemented later by a `Schedule::command(...)->everyMinute()` sweep. |
| Third-party "Laravel quiz/exam" packages found on GitHub/Packagist | Low adoption, generic schemas that won't match this project's Subject/Class-scoped assignment model; adapting one costs more than building the ~6 focused domain tables directly. | The custom schema in section 4 above. |
| A state-machine package for attempt status | Three linear states (`in_progress` → `submitted` → `graded`) don't need transition guards/side-effect hooks. | Plain enum-cast `status` column with a couple of guard checks in the controller/service. |
| Model observers/events for grading | Turns grading into a hidden side effect of "saving a row"; easy to accidentally re-trigger on autosave. | Explicit `AttemptGrader` service method called once, at finalization. |

### Stack Patterns by Variant

**If the project later needs a third role (e.g., "Admin" for provisioning lecturers):**
- Extend the `Role` enum with one more case; add one more middleware alias/route group. Still no package needed at 3 fixed roles.
- Only reach for `spatie/laravel-permission` if that Admin role starts needing to grant/revoke fine-grained permissions per-user rather than "is an admin or not."

**If a lecturer live-monitoring dashboard is added later (see students' attempts in real time):**
- That's when the scheduled sweep (optional pattern above) starts earning its keep, and potentially a lightweight polling refresh (`wire:poll`-equivalent via a simple `setInterval` + fetch in Alpine) rather than full broadcasting — still no need for Echo/Pusher/Reverb unless true push updates across many simultaneous viewers become a requirement.

### Version Compatibility

| Package A | Compatible With | Notes |
|-----------|------------------|-------|
| Laravel 11.x | PHP 8.2+ | Matches the existing scaffold (PROJECT.md notes PHP 8.2+ already). |
| Native backed enums + `casts()` model method | Laravel 8+ (casting), `casts()` method style specifically Laravel 11+ | Either the `$casts` property or the `casts()` method works in Laravel 11; the method form is the more current convention and is what new Laravel 11 code should use. |
| `Schedule::command(...)` in `routes/console.php` | Laravel 11.x only (this location is new) | Laravel 10 and earlier defined the schedule in `app/Console/Kernel.php::schedule()`, which no longer exists in the Laravel 11 skeleton — do not follow Laravel-10-era tutorials that reference `Kernel.php` for this. |
| `spatie/laravel-permission` (if ever adopted) | Composer resolves an ^6.x-line release against Laravel 11; the current 8.3.0 release requires Laravel ^12\|^13 | Not relevant to this project's recommendation (avoid it), but flagged so nobody accidentally `composer require`s the latest major and breaks on a Laravel-version conflict. |

### Sources

- https://laravel.com/docs/11.x/authorization — Gates vs Policies, policy auto-discovery, `can` middleware (confirmed directly)
- https://laravel.com/docs/11.x/scheduling — Laravel 11 scheduling location (`routes/console.php`, no `Console/Kernel.php`) (confirmed directly)
- https://laravel.com/docs/12.x/queues — delayed dispatch semantics, queue-vs-scheduler tradeoffs, cross-checked against 11.x behavior (MEDIUM — 12.x doc used to corroborate 11.x-era mechanics, framework version noted)
- https://packagist.org/packages/spatie/laravel-permission — current version (8.3.0, requires Laravel ^12|^13), install base, license (MEDIUM — community package registry, not official Laravel docs)
- https://laravel-news.com/laravel-gates-policies-guards-explained — cross-reference on Gates/Policies/Guards distinction (MEDIUM — reputable Laravel community publication)
- https://laraveldaily.com/lesson/alpine-js/countdown-timer-x-init — Alpine.js `x-init`/`setInterval` countdown pattern (MEDIUM — established Laravel-focused training resource, cross-checked against multiple similar examples)
- General web search cross-referencing Laravel quiz/exam implementations (Laracasts forum threads, Medium walkthroughs) for the "store `started_at`/`duration`, check server-side on every request" timer-enforcement pattern (MEDIUM — pattern consistent across multiple independent community sources, no single authoritative official doc for this exact scenario since it's application-level architecture, not a framework API)

---
*Stack research for: Online examination & student management portal (exam domain)*
*v3.0 addendum researched: 2026-07-17*
*v2.0 addendum researched: 2026-07-16*
*v1.0 original researched: 2026-07-15*

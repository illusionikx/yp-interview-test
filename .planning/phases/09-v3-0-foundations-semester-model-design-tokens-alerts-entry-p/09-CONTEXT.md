# Phase 9: v3.0 Foundations — Semester Model, Design Tokens, Alerts & Entry Pages - Context

**Gathered:** 2026-07-17
**Status:** Ready for planning

<domain>
## Phase Boundary

This phase delivers the four primitives every later v3.0 phase reuses, and nothing that consumes
them:

1. **One semester vocabulary** — a derived `App\Support\Semester` value object (SEM-01..03) that
   Phases 11/12 read for subject/class grouping.
2. **One design-token vocabulary** — Flowbite 4's semantic tokens made to emit real CSS under the
   project's Tailwind 3 build (UI-03), so the login card and every later screen can use
   `bg-brand`/`text-heading` and have them resolve.
3. **One alert style** — a `<x-toast>` and a `<x-confirm-modal>` component (UX-02, UX-03), with
   every native `confirm()` call site in the app migrated onto the modal.
4. **The pre-login path** — a branded landing page and the Flowbite login card (NAV-01, NAV-02,
   UX-01).

Plus **INT-01**, a null-guard on `Attempt::lockAndFinalize()` pulled ahead of Phase 10 because it
must exist *before* Phase 10 creates the first code path that can delete an in-progress attempt.

**Out of this phase:** the dashboard, the subject list, navigation restructure (Phase 11); anything
that *consumes* the semester rule beyond its own tests; the dark-mode correctness sweep across
existing views (Phase 14).

</domain>

<decisions>
## Implementation Decisions

### Landing Page & Entry (NAV-01, NAV-02, UX-01)

- **Landing content:** a single hero — "Online Examination Portal" with the subtitle "for Yayasan
  Peneraju Technical Assessment", a one-line description, and a "Sign in" primary CTA. No
  features/how-it-works section and no role-picker — this is an assessment deliverable, not a
  marketing site.
- **Top bar on landing:** yes, minimal — title + dark-mode toggle + Sign in. Consistent with
  Decision #9 (the slim bar survives to host the toggle and the later help button).
- **Routing:** guests see the landing page at `/`; authenticated users are redirected to
  `/dashboard`. This replaces Breeze's default `welcome` view.
- **Login card auth links:** "Create account" and "Lost Password?" wire to the existing Breeze
  routes (`register`, `password.request`) — both already ship and work. They are NOT left as the
  inert `#` hrefs shown in the raw v3.md snippet.

### Alerts & Toasts (UX-02, UX-03)

- **Toast placement:** fixed top-right, below the navbar, stacked.
- **Dismiss behavior:** success/info auto-dismiss after ~4s; **errors persist until dismissed**. An
  error the user misses is exactly the FIX-03 bug. Both variants carry a manual close button.
- **Trigger mechanism:** keep the app's existing flash convention. One `<x-toast>` in the layout
  reads the flash and renders it. This touches **zero controllers** — no `toast()` helper, no JS
  event bus, no controller migration.
- **CORRECTION (found by the UI researcher, verified by grep):** the actual convention is
  **`session('status')` / `session('error')`** — **not** `session('success')`, which appears at
  **zero** call sites. `with('status', …)` / `session('status')` appears at **57**. Wire the toast
  to `status` + `error`.
- **Landmine — `session('status')` is overloaded.** Three shipped Breeze views test it against
  *literal sentinel values* and render their own inline confirmation text:
  `auth/verify-email.blade.php:6` (`'verification-link-sent'`),
  `profile/partials/update-password-form.blade.php:37` (`'password-updated'`),
  `profile/partials/update-profile-information-form.blade.php:41,53`
  (`'verification-link-sent'`, `'profile-updated'`).
  A naive `<x-toast>` would render those raw sentinel strings as garbled toasts. The toast must
  **exclude** these sentinel values (or those routes), leaving the existing inline confirmations
  intact. See `09-UI-SPEC.md` for the exclusion list.
- **Native `confirm()` migration:** the 3 existing call sites become a blocking `<x-confirm-modal>`
  Alpine component. This satisfies UX-02's "native `alert()` is never used" and hands Phase 10 the
  component its destructive-action warnings (INT-02) consume. Toasts and confirmation modals are
  **two distinct components** — non-blocking/informational vs. blocking/decision-required.

### Design Token Port (UI-03)

- **Token scope:** port only the tokens the v3.md login snippet actually uses, plus their dark
  variants — `bg-brand`, `bg-brand-strong`, `bg-neutral-primary-soft`, `bg-neutral-secondary-medium`,
  `border-default`, `border-default-medium`, `text-heading`, `text-body`, `text-fg-brand`,
  `ring-brand` / `ring-brand-medium` / `ring-brand-soft`, `rounded-base`, `shadow-xs`,
  `placeholder:text-body`. Later phases extend `theme.extend` as they need more.
- **Brand color:** Flowbite's default blue — reproduces the supplied design exactly, no invention.
- **Dark mode mechanism:** tokens resolve through **CSS custom properties flipped by the existing
  `.dark` class**. This is Flowbite 4's actual model, so `bg-brand` is correct in both themes with
  no `dark:` prefix. Builds on the `darkMode: 'class'` config and pre-paint no-flash bootstrap
  already shipped in Phase 7.
- **Existing views:** leave the ~28 files of existing `dark:` classes alone. Tokens are additive;
  those views keep working untouched. Phase 14 (FIX-02, UX-05) owns the dark-mode correctness sweep.

### Claude's Discretion

- **Semester (SEM-01..03)** — settled by v3.0 Decisions #1 and #4, no open questions: a derived
  `App\Support\Semester` value object (not a table), reading `Section.year` / `Section.semester` as
  the source of truth; S1 = September → February of the following year, S2 = March → July; a
  semester spans the 1st of its first month to the last day of its last month; an `ordinal()` total
  order that sorts correctly across the S1 year rollover; and the August gap **rolls forward** to
  the upcoming semester (a null would blank every dashboard for a month). Implementation shape,
  method names, and test structure are Claude's call.
- **INT-01** — the `Attempt::lockAndFinalize()` null-guard. Pure defensive infrastructure; approach
  is Claude's call. It must fail safely, not crash, when the locked row has vanished underneath a
  racing timer/autosave request.

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets

- `tailwind.config.js` — already has `darkMode: 'class'`, the `flowbitePlugin`, `@tailwindcss/forms`,
  and a `theme.extend` block with only `fontFamily`. The token port lands here.
- Phase 7 shipped a **pre-paint no-flash dark-mode bootstrap** and an Alpine dark-mode toggle in the
  navbar — the token layer plugs into the same `.dark` class these already drive.
- `Section` model already carries `year` and `semester` columns, plus a computed `name` accessor
  (`"{year}-{semester}-{sequence}"`). `Semester` reads these; it does not add a table.
- An `x-status-pill` Blade component exists from Phase 7 — the reference for how this codebase
  writes a reusable component.

### Established Patterns

- Blade + Tailwind 3 + Alpine, no SPA layer (mandated by CLAUDE.md; no new Composer/npm deps).
- Flash messaging is already uniform on **`session('status')` / `session('error')`** — 57 call sites
  across controllers and views. `session('success')` is **never** used (0 hits). The toast component
  reads this convention rather than replacing it. Three Breeze views overload `status` with sentinel
  values — see the landmine note under Alerts & Toasts.
- `resources/views/components/modal.blade.php` — an **existing `<x-modal>` primitive** with overlay,
  focus trap, and transitions. The `<x-confirm-modal>` should wrap this, not rebuild it.
- Native `confirm()` appears at exactly **3 call sites**, all lecturer-side destructive actions:
  - `resources/views/lecturer/exams/show.blade.php:54` — delete exam
  - `resources/views/lecturer/exams/show.blade.php:79` — delete question
  - `resources/views/lecturer/subjects/index.blade.php:38` — delete subject
  All three are `onsubmit="return confirm(...)"` on a POST form — the modal must preserve the
  form-submit semantics.
- No `app/Support/` directory exists yet — `Semester` creates it.
- Test command is `php artisan test` (PHPUnit); build is `npm run build`.

### Integration Points

- `routes/web.php` — `/` currently returns Breeze's `welcome` view; becomes the landing page with an
  auth redirect to `/dashboard`.
- `resources/views/auth/login.blade.php` — reskinned to the v3.md Flowbite card.
- The main layout — hosts `<x-toast>` so every flash renders without per-view wiring.
- `app/Models/Attempt.php::lockAndFinalize()` — INT-01's null-guard.

</code_context>

<specifics>
## Specific Ideas

- The login card markup is supplied **verbatim** in `.planning/v3.md` (lines 3–26) — a Flowbite 4.0
  example. Reproduce its structure and token classes: card, "Sign in to our platform" heading, email
  field, password field, remember-me checkbox, "Lost Password?" link, full-width submit button
  reading "Login to your account", and a "Not registered? Create account" footer line.
- UI-03 is a **verified blocker, not a preference**: those tokens ship in a Tailwind-v4-only
  `@theme{}` block and currently emit **no CSS at all** under this project's Tailwind 3 build. The
  failure mode is an unstyled page, not a build error — which is why Phase 9's success criterion
  demands confirming the token classes appear **in the compiled CSS**, not eyeballing a browser.

</specifics>

<deferred>
## Deferred Ideas

- Migrating the ~28 existing views onto semantic tokens — Phase 14 (FIX-02, UX-05).
- The gradient welcome banner (DASH-02) — Phase 11 owns the dashboard; the landing hero here does
  not need to share its treatment.
- Extending the token set beyond the login card's needs — later phases extend `theme.extend`
  as required.

</deferred>

# Phase 9: v3.0 Foundations — Semester Model, Design Tokens, Alerts & Entry Pages - Research

**Researched:** 2026-07-17
**Domain:** Laravel 11 domain value objects (semester date math), Tailwind 3 design-token porting, Blade/Alpine component patterns (toast + confirm modal), a defensive concurrency null-guard
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Landing Page & Entry (NAV-01, NAV-02, UX-01)**
- Landing content: a single hero — "Online Examination Portal" with the subtitle "for Yayasan
  Peneraju Technical Assessment", a one-line description, and a "Sign in" primary CTA. No
  features/how-it-works section and no role-picker — this is an assessment deliverable, not a
  marketing site.
- Top bar on landing: yes, minimal — title + dark-mode toggle + Sign in.
- Routing: guests see the landing page at `/`; authenticated users are redirected to
  `/dashboard`. This replaces Breeze's default `welcome` view.
- Login card auth links: "Create account" and "Lost Password?" wire to the existing Breeze
  routes (`register`, `password.request`) — both already ship and work. They are NOT left as the
  inert `#` hrefs shown in the raw v3.md snippet.

**Alerts & Toasts (UX-02, UX-03)**
- Toast placement: fixed top-right, below the navbar, stacked.
- Dismiss behavior: success/info auto-dismiss after ~4s; errors persist until dismissed. Both
  variants carry a manual close button.
- Trigger mechanism: keep the app's existing flash convention. One `<x-toast>` in the layout
  reads the flash and renders it. Touches zero controllers.
- CORRECTION (verified by grep): actual convention is `session('status')` / `session('error')` —
  NOT `session('success')` (0 call sites). `session('status')` appears at 57 sites. Wire the
  toast to `status` + `error`.
- Landmine — `session('status')` is overloaded. Three shipped Breeze views test it against
  literal sentinel values and render their own inline confirmation text:
  `auth/verify-email.blade.php:6` (`'verification-link-sent'`),
  `profile/partials/update-password-form.blade.php:37` (`'password-updated'`),
  `profile/partials/update-profile-information-form.blade.php:41,53`
  (`'verification-link-sent'`, `'profile-updated'`). The toast must exclude these three sentinel
  values, leaving the existing inline confirmations intact.
- Native `confirm()` migration: the 3 existing call sites become a blocking `<x-confirm-modal>`
  Alpine component. Toasts and confirmation modals are two distinct components —
  non-blocking/informational vs. blocking/decision-required.

**Design Token Port (UI-03)**
- Token scope: port only the tokens the v3.md login snippet actually uses, plus their dark
  variants — `bg-brand`, `bg-brand-strong`, `bg-neutral-primary-soft`, `bg-neutral-secondary-medium`,
  `border-default`, `border-default-medium`, `text-heading`, `text-body`, `text-fg-brand`,
  `ring-brand` / `ring-brand-medium` / `ring-brand-soft`, `rounded-base`, `shadow-xs`,
  `placeholder:text-body`. Later phases extend `theme.extend` as they need more.
- Brand color: Flowbite's default blue — reproduces the supplied design exactly, no invention.
- Dark mode mechanism: tokens resolve through CSS custom properties flipped by the existing
  `.dark` class. Builds on `darkMode: 'class'` and the pre-paint no-flash bootstrap from Phase 7.
- Existing views: leave the ~28 files of existing `dark:` classes alone. Tokens are additive.

### Claude's Discretion

- **Semester (SEM-01..03)** — settled by v3.0 Decisions #1 and #4, no open questions: a derived
  `App\Support\Semester` value object (not a table), reading `Section.year` / `Section.semester` as
  the source of truth; S1 = September → February of the following year, S2 = March → July; a
  semester spans the 1st of its first month to the last day of its last month; an `ordinal()` total
  order that sorts correctly across the S1 year rollover; and the August gap rolls forward to the
  upcoming semester. Implementation shape, method names, and test structure are Claude's call.
- **INT-01** — the `Attempt::lockAndFinalize()` null-guard. Pure defensive infrastructure;
  approach is Claude's call. It must fail safely, not crash, when the locked row has vanished
  underneath a racing timer/autosave request.

### Deferred Ideas (OUT OF SCOPE)

- Migrating the ~28 existing views onto semantic tokens — Phase 14 (FIX-02, UX-05).
- The gradient welcome banner (DASH-02) — Phase 11 owns the dashboard; the landing hero here does
  not need to share its treatment.
- Extending the token set beyond the login card's needs — later phases extend `theme.extend`
  as required.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| SEM-01 | Semester derived from a date, not a table; S1 = Sep→Feb(next yr), S2 = Mar→Jul, full-month bounds | `App\Support\Semester` value object design below; `Section::windowStatus()` half-open-interval precedent |
| SEM-02 | Total ordering correct across S1 year rollover | `ordinal()` composite `year*2+(number-1)` scheme below; `WindowSemanticsTest.php` precedent for boundary-instant test style |
| SEM-03 | August gap has a defined, tested "current semester" policy | Roll-forward policy (Decision #1) implemented as `Semester::current()`'s branch order below |
| INT-01 | `Attempt::lockAndFinalize()` null-guards its locked row | `app/Models/Attempt.php:137-175` read directly; exact crash line identified; guard shape + caller-notification design below |
| UI-03 | Flowbite 4 tokens resolve to real CSS under Tailwind 3 | `tailwind.config.js`/`resources/css/app.css` read directly; CSS-custom-property + `theme.extend.colors` mechanism verified against Tailwind's own default preset source; compiled-CSS verification command |
| NAV-01 | Landing page renders before login | `routes/web.php` current `/` handler read directly; replacement shape below |
| NAV-02 | Login page follows the Flowbite design from v3.md | `resources/views/auth/login.blade.php` current state read directly; exact required deviations enumerated |
| UX-01 | App titled "Online Examination Portal" / subtitle | `layouts/app.blade.php` `<title>`, `navigation.blade.php` wordmark, landing hero — all identified as edit points |
| UX-02 | One popup/alert style; native `alert()`/`confirm()` never used | 3 `confirm()` call sites verified by grep; `<x-modal>` primitive read in full; wrapping pattern below |
| UX-03 | Toaster on create/save/delete | 36+ `session('status'/'error')` controller call sites verified by grep; toast component design below |
</phase_requirements>

## Summary

This phase is almost entirely Laravel-native/Tailwind-native work — **zero new Composer or npm
packages** are introduced. The four deliverables are: (1) a small, stateless PHP value object
(`App\Support\Semester`) wrapping date arithmetic already partially proven-out by this codebase's
own `Section::windowStatus()`/`Exam::availabilityState()` half-open-interval pattern; (2) a
`tailwind.config.js` `theme.extend` + `resources/css/app.css` `@layer base` addition that maps
Flowbite 4's semantic token *names* onto CSS custom properties, verified end-to-end against
Tailwind v3's actual default preset source (`borderColor`/`ringColor` are themselves functions of
`theme('colors')`, so extending `colors` alone is sufficient — confirmed directly against
`tailwindlabs/tailwindcss`'s `stubs/config.full.js`); (3) two Blade+Alpine components (`<x-toast>`,
`<x-confirm-modal>`) that read the app's *existing* flash keys and wrap the *existing* `<x-modal>`
primitive rather than building new overlay/focus-trap/transition logic; (4) a one-line-scoped but
architecturally subtle null-guard in `Attempt::lockAndFinalize()`, whose exact crash mechanics were
traced directly in the current code.

**Primary recommendation:** Build all four pieces as small, single-purpose additions layered on top
of already-shipped conventions (`Section`'s computed-accessor pattern, the existing `<x-modal>`
primitive, the existing `session('status')`/`session('error')` flash convention) — do not introduce
a semesters table, a second modal system, a new flash-key convention, or a Tailwind v4 upgrade. The
UI-03 token port is the phase's one genuine technical risk (silent-failure mode, not a build error);
verify it by grepping compiled CSS, never by eyeballing a browser tab.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Semester date-range/ordering math (SEM-01..03) | API / Backend (`app/Support/Semester.php`) | — | Pure PHP value object; no view or DB dependency. Every consumer (future dashboard/subject-list phases) calls through it — single predicate, same discipline as `Exam::scopeVisibleTo()`. |
| Design token resolution (UI-03) | CDN / Static (build-time CSS generation) | Browser (CSS custom property flip via `.dark` class) | Tailwind's JIT compiler resolves `theme.extend` into static utility CSS at `npm run build` time; the `.dark`/`:root` custom-property flip is a pure browser-CSS behavior with zero JS runtime cost. |
| Toast rendering (`<x-toast>`, UX-02/UX-03) | Frontend Server (SSR) reads flash | Browser (Alpine auto-dismiss timer) | Blade renders the flash into HTML server-side on page load (no controller/API change); Alpine only owns the client-side dismiss countdown and manual-close interaction. |
| Confirm modal (`<x-confirm-modal>`, UX-02) | Browser / Client (Alpine intercepts submit) | Frontend Server (existing `<x-modal>` markup) | The blocking decision (open/close/confirm) is pure client-side Alpine state; the actual destructive POST still goes through the existing form-submit-to-controller path unchanged. |
| Landing page + login card (NAV-01, NAV-02, UX-01) | Frontend Server (SSR) | Browser (dark-mode toggle, Alpine) | Static Blade views with no new data dependency; only the dark-mode toggle and modal-adjacent interactivity are client-side. |
| INT-01 null-guard | API / Backend (`app/Models/Attempt.php`) | — | Pure server-side concurrency defense inside an existing `DB::transaction()`; no UI surface of its own beyond the one copywriting exception routed through the toast. |

## Standard Stack

### Core

No new core technology. This phase works entirely within the already-fixed stack (Laravel 11.55,
Breeze 2.4, Blade + Tailwind 3.1 + Alpine 3.4, MySQL via Herd) per CLAUDE.md.

### Supporting

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| *(none)* | — | — | Every deliverable in this phase (Semester value object, token CSS, toast, confirm modal, null-guard) is implementable with what's already in `composer.json`/`package.json`. |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Derived `Semester` value object | A `semesters` DB table | Only earns its keep if boundaries become admin-editable per year or "current semester" needs a manual override — neither is in scope (v3.md states the rule as permanently fixed). |
| CSS-custom-property token port in `theme.extend` | Upgrade to Tailwind v4 (native `@theme{}` support) | Explicitly rejected by v3.0 Decision #5 — would ripple through the 416 existing `dark:` occurrences across 28 files for zero functional gain in this phase. |
| Wrapping existing `<x-modal>` for `<x-confirm-modal>` | A second, standalone modal component | Duplicates overlay/focus-trap/transition logic that's already shipped, tested, and working — pure waste. |
| Blade-server-rendered toast reading `session()` | A new `toast()` helper + controller migration | CONTEXT.md explicitly rules this out — "touches zero controllers." |

**Installation:** none required. No `npm install` / `composer require` for this phase.

**Version verification:**
```bash
npm view tailwindcss version   # confirms latest 3.x if ever bumped — this phase pins to installed ^3.1.0, already in package.json
npm view flowbite version      # confirms latest 4.x — already installed at ^4.0.2 in package.json (dependencies, not devDependencies)
```
Both packages are **already installed** (verified by reading `package.json` directly) — no
registry lookup is needed to add anything new. `tailwindcss: ^3.1.0`, `flowbite: ^4.0.2` confirmed
present in this repo's `package.json` at research time [VERIFIED: package.json read directly].

## Package Legitimacy Audit

**Not applicable — this phase installs no new packages.** Every dependency it touches
(`tailwindcss`, `flowbite`, `@tailwindcss/forms`, `alpinejs`) is already present in `package.json`
and was vetted when originally installed in earlier phases. No `npm install`/`composer require`
command appears anywhere in this phase's planned work.

**Packages removed due to [SLOP] verdict:** none — not applicable.
**Packages flagged as suspicious [SUS]:** none — not applicable.

## Architecture Patterns

### System Architecture Diagram

```
                     ┌─────────────────────────────────────────┐
                     │              Guest visitor                │
                     └───────────────────┬─────────────────────┘
                                          │ GET /
                                          ▼
                     ┌─────────────────────────────────────────┐
                     │ routes/web.php: '/'                      │
                     │  auth()?  ──yes──▶ redirect /dashboard   │
                     │  no  ──▶ render resources/views/landing  │
                     └───────────────────┬─────────────────────┘
                                          │ clicks "Sign in"
                                          ▼
                     ┌─────────────────────────────────────────┐
                     │ GET /login (Breeze, unchanged route)     │
                     │  resources/views/auth/login.blade.php    │
                     │  ── reads: token classes (bg-brand, ...) │
                     │  ── resolved by: tailwind.config.js      │
                     │     theme.extend.colors + CSS custom     │
                     │     properties in resources/css/app.css  │
                     └───────────────────┬─────────────────────┘
                                          │ POST /login (Breeze, unchanged controller)
                                          ▼
                     ┌─────────────────────────────────────────┐
                     │ any authenticated controller action      │
                     │  redirect()->with('status'|'error', ...) │  ◀── 57 existing call sites, untouched
                     └───────────────────┬─────────────────────┘
                                          │ next page load
                                          ▼
                     ┌─────────────────────────────────────────┐
                     │ layouts/app.blade.php + layouts/guest    │
                     │  includes <x-toast /> once               │
                     │  reads session('status') excluding the   │
                     │  3 sentinel values, session('error')     │
                     │  ── Alpine: auto-dismiss timer (status)  │
                     │  ── Alpine: no auto-dismiss (error)      │
                     └───────────────────────────────────────────┘

  Separate, independent flow — destructive action:
                     ┌─────────────────────────────────────────┐
                     │ delete-exam / delete-question /          │
                     │ delete-subject <form method=POST>        │
                     │  Alpine @submit.prevent → opens           │
                     │  <x-confirm-modal> (wraps <x-modal>)      │
                     │  user clicks "Delete" → $refs.form.submit()│
                     │  (form now actually POSTs, unchanged      │
                     │   controller/route)                       │
                     └─────────────────────────────────────────┘

  Separate, independent flow — attempt race (INT-01):
                     ┌─────────────────────────────────────────┐
                     │ Student autosave/timer request           │
                     │  → Attempt::finalizeIfExpired()/finalize()│
                     │  → lockAndFinalize(): SELECT ... FOR UPDATE│
                     │     row vanished? (concurrent delete)     │
                     │     ── null-guard: return false, no crash │
                     │     ── caller detects "no longer exists"  │
                     │        and redirects with session('error')│
                     │        = "attempt no longer available" →  │
                     │        rendered by the SAME <x-toast>      │
                     └─────────────────────────────────────────┘
```

### Recommended Project Structure

```
app/
├── Support/
│   └── Semester.php              # new — the only file in a new directory
├── Models/
│   └── Attempt.php                # edited — null-guard in lockAndFinalize()
├── Http/Controllers/Student/
│   └── AttemptController.php      # edited — null-guard in answer()'s direct lockForUpdate() read
resources/
├── views/
│   ├── landing.blade.php          # new — replaces welcome.blade.php as guest '/'
│   ├── auth/login.blade.php       # edited — Flowbite card markup
│   ├── layouts/
│   │   ├── app.blade.php          # edited — add <x-toast />
│   │   ├── guest.blade.php        # edited — add <x-toast />, may need top-bar slot for landing
│   │   └── navigation.blade.php   # edited — wordmark copy only, this phase
│   └── components/
│       ├── toast.blade.php        # new
│       └── confirm-modal.blade.php # new — wraps existing modal.blade.php
tailwind.config.js                 # edited — theme.extend.colors/borderRadius/boxShadow
resources/css/app.css              # edited — @layer base custom properties
routes/web.php                     # edited — '/' becomes landing + auth redirect
tests/
├── Unit/
│   └── SemesterTest.php           # new
└── Feature/
    ├── LandingPageTest.php        # new
    ├── ToastTest.php              # new
    └── AttemptNullGuardTest.php   # new (or folded into existing attempt test file)
```

### Pattern 1: Semester value object (SEM-01/02/03)

**What:** A stateless, DB-free PHP class wrapping S1/S2 date-range and ordering math, mirroring the
established "computed, not stored" precedent already in this codebase (`Section::windowStatus()`,
`Exam::availabilityState()`).

**When to use:** Any code that needs "which semester does this date fall in," "is this the current
semester," or "sort these semesters."

**Example:**
```php
<?php
// Source: derived from Section.year/Section.semester (source of truth), following
// the half-open-interval + "single predicate" conventions already established by
// Section::windowStatus() (app/Models/Section.php:85-97) and
// Exam::availabilityState() (app/Models/Exam.php:129-141).

namespace App\Support;

use Illuminate\Support\Carbon;

final class Semester
{
    public function __construct(
        public readonly int $year,
        public readonly int $number, // 1 or 2
    ) {}

    /** SEM-03: August rolls FORWARD to the upcoming semester (Decision #1). */
    public static function forDate(Carbon $date): self
    {
        $month = (int) $date->format('n');

        return match (true) {
            $month >= 9 => new self($date->year, 1),               // Sep-Dec -> S1 this year
            $month <= 2 => new self($date->year - 1, 1),           // Jan-Feb -> S1 started last year
            $month <= 7 => new self($date->year, 2),                // Mar-Jul -> S2 this year
            default => new self($date->year, 1),                    // August (8) -> roll forward to S1 this year
        };
    }

    public static function current(): self
    {
        return self::forDate(now());
    }

    /** SEM-01: 1st of the first month; never a stored/cached value. */
    public function startsAt(): Carbon
    {
        return $this->number === 1
            ? Carbon::create($this->year, 9, 1)->startOfDay()
            : Carbon::create($this->year, 3, 1)->startOfDay();
    }

    /**
     * SEM-01: last day of the last month — S1's end year is $year+1 and
     * MUST use endOfMonth()/lastOfMonth(), never a literal ->day(28), so
     * leap-year Feb 29 is handled automatically.
     */
    public function endsAt(): Carbon
    {
        return $this->number === 1
            ? Carbon::create($this->year + 1, 2, 1)->endOfMonth()->endOfDay()
            : Carbon::create($this->year, 7, 31)->endOfDay();
    }

    /**
     * SEM-02: total order across the S1 year rollover. year*2+(number-1)
     * makes S2-2026 (2026*2+1=4053) < S1-2027 (2027*2+0=4054) — the
     * naive "compare year then semester" comparison breaks here because
     * semester 1 < semester 2 even though the year advanced.
     */
    public function ordinal(): int
    {
        return $this->year * 2 + ($this->number - 1);
    }

    public function isCurrent(): bool { return $this->ordinal() === self::current()->ordinal(); }
    public function isFuture(): bool  { return $this->ordinal() >  self::current()->ordinal(); }
    public function isPast(): bool    { return $this->ordinal() <  self::current()->ordinal(); }

    public function label(): string { return "{$this->year} Semester {$this->number}"; }
}
```

Note the `forDate()` branch order above deliberately checks `$month >= 9` before `$month <= 2` —
January/February of year Y belong to the S1 that *started* in September of year Y-1, so their
year attribution must subtract 1. This is the exact "S1 crosses a calendar year boundary" case
PITFALLS.md Pitfall 3 warns about; get the branch order (and the `-1`) wrong and January/February
dates attribute to the wrong `Semester::$year`.

### Pattern 2: Design token port under Tailwind 3 (UI-03)

**What:** Map Flowbite 4's semantic token *names* onto Tailwind v3 utilities via
`theme.extend.colors` + CSS custom properties, exactly as specified in `09-UI-SPEC.md`'s "Design
Token Port" section (the concrete `tailwind.config.js`/`app.css` diffs are already fully specified
there — reproduced here only to confirm the underlying Tailwind mechanics are sound).

**When to use:** Any new class using `bg-brand`, `text-heading`, `border-default`, `ring-brand*`,
`rounded-base`, `shadow-xs` (the exact set the v3.md login snippet uses).

**Why this works (verified, not assumed):**
- Tailwind v3's own default preset (`tailwindlabs/tailwindcss`, `stubs/config.full.js`) defines
  `borderColor` and `ringColor` as **functions of `theme('colors')`**:
  `borderColor: ({theme}) => ({...theme('colors'), DEFAULT: theme('colors.gray.200', 'currentColor')})`
  and `ringColor: ({theme}) => ({DEFAULT: theme('colors.blue.500', '#3b82f6'), ...theme('colors')})`
  [CITED: github.com/tailwindlabs/tailwindcss/blob/v3/stubs/config.full.js]. Because both spread
  `theme('colors')`, extending `theme.extend.colors` with new keys (`brand`, `default`,
  `'default-medium'`, `heading`, `body`, `'fg-brand'`, neutral variants) **automatically** produces
  matching `border-*`, `ring-*`, `text-*`, `bg-*`, `placeholder:*` utilities — no separate
  `borderColor`/`ringColor` block is needed, confirming 09-UI-SPEC.md's claim.
- The `rgb(var(--color-x) / <alpha-value>)` pattern is Tailwind's documented technique for
  CSS-custom-property-backed colors that still support opacity modifiers: define
  `--color-x: 37 99 235` (space-separated RGB channels, no `rgb()` wrapper) in a CSS layer, then
  reference it in `theme.extend.colors` as `'rgb(var(--color-x) / <alpha-value>)'` — Tailwind
  substitutes `<alpha-value>` at build time [CITED: web search cross-referencing Tailwind's
  documented CSS-variable color pattern, consistent across multiple sources]. This is exactly the
  pattern 09-UI-SPEC.md's `tailwind.config.js`/`app.css` diffs already use.
- `borderRadius: { base: '0.5rem' }` and `boxShadow: { xs: '...' }` are plain `theme.extend`
  key additions — no special mechanism needed; Tailwind generates `rounded-base` and `shadow-xs`
  directly from these keys.

**Example (see 09-UI-SPEC.md for the full, already-specified diff):**
```js
// tailwind.config.js — theme.extend addition
colors: {
  brand: { DEFAULT: 'rgb(var(--color-brand) / <alpha-value>)', strong: '...', medium: '...', soft: '...' },
  'fg-brand': 'rgb(var(--color-fg-brand) / <alpha-value>)',
  heading: 'rgb(var(--color-heading) / <alpha-value>)',
  body: 'rgb(var(--color-body) / <alpha-value>)',
  default: 'rgb(var(--color-border-default) / <alpha-value>)',
  'default-medium': 'rgb(var(--color-border-default-medium) / <alpha-value>)',
  neutral: {
    'primary-soft': 'rgb(var(--color-neutral-primary-soft) / <alpha-value>)',
    'secondary-medium': 'rgb(var(--color-neutral-secondary-medium) / <alpha-value>)',
  },
},
```
```css
/* resources/css/app.css — after the three @tailwind directives */
@layer base {
  :root { --color-brand: 37 99 235; /* ... */ }
  .dark { --color-brand: 59 130 246; /* ... */ }
}
```

**Verification (do not skip, do not eyeball):**
```bash
npm run build
grep -o "\.bg-brand{" public/build/assets/*.css
grep -o "\.text-heading{" public/build/assets/*.css
grep -o "\.rounded-base{" public/build/assets/*.css
```
All three must return a match. This project's `package.json` confirms `"build": "vite build"` is
the correct command [VERIFIED: package.json read directly].

### Pattern 3: `<x-toast>` reading the existing flash convention (UX-02/UX-03)

**What:** A single Blade component, included once per layout, reading `session('status')` /
`session('error')` — zero controller changes.

**When to use:** Included in `layouts/app.blade.php` and `layouts/guest.blade.php` (and the new
landing/login shell) exactly once each.

**Example:**
```blade
{{-- resources/views/components/toast.blade.php --}}
@php
    $sentinels = ['verification-link-sent', 'password-updated', 'profile-updated'];
    $status = session('status');
    $showStatus = $status && ! in_array($status, $sentinels, true);
    $error = session('error');
@endphp

@if ($showStatus || $error)
<div
    x-data="{ statusVisible: @js((bool) $showStatus), errorVisible: @js((bool) $error) }"
    x-init="statusVisible && setTimeout(() => statusVisible = false, 4000)"
    class="fixed top-20 right-4 z-50 flex flex-col gap-2"
>
    @if ($showStatus)
        <div x-show="statusVisible" x-transition
             class="flex items-center gap-2 rounded-base border border-default bg-neutral-primary-soft p-4 shadow-xs">
            {{-- check-circle icon --}}
            <span class="text-body">{{ $status }}</span>
            <button type="button" @click="statusVisible = false" aria-label="Dismiss" class="ms-auto">
                {{-- x icon --}}
            </button>
        </div>
    @endif
    @if ($error)
        <div x-show="errorVisible" x-transition
             class="flex items-center gap-2 rounded-base border border-red-300 bg-neutral-primary-soft p-4 shadow-xs">
            {{-- exclamation-circle icon --}}
            <span class="text-body">{{ $error }}</span>
            <button type="button" @click="errorVisible = false" aria-label="Dismiss" class="ms-auto">
                {{-- x icon --}}
            </button>
        </div>
    @endif
</div>
@endif
```
This reproduces 09-UI-SPEC.md's contract exactly (placement, sentinel exclusion, differential
dismiss behavior, `aria-label="Dismiss"` on the close button per the checker's non-blocking
recommendation).

### Pattern 4: `<x-confirm-modal>` wrapping the existing `<x-modal>` primitive (UX-02)

**What:** `resources/views/components/modal.blade.php` already provides a complete overlay +
Escape-to-close + focus-trap + enter/leave transition primitive, driven by
`window.dispatchEvent(new CustomEvent('open-modal', {detail: name}))` /
`'close-modal'` [VERIFIED: `resources/views/components/modal.blade.php` read in full]. A thin
wrapper adds title/body/confirm/cancel slots without re-implementing any of that.

**Example (one of the 3 migrated call sites):**
```blade
{{-- Before (resources/views/lecturer/exams/show.blade.php:54) --}}
<form method="POST" action="{{ route('lecturer.exams.destroy', $exam) }}"
      onsubmit="return confirm('{{ __('Delete this exam?') }}');">
    @csrf @method('DELETE')
    <button type="submit">Delete</button>
</form>

{{-- After --}}
<form method="POST" action="{{ route('lecturer.exams.destroy', $exam) }}"
      x-ref="deleteExamForm" @submit.prevent="$dispatch('open-modal', 'delete-exam')">
    @csrf @method('DELETE')
    <button type="submit">Delete</button>
</form>

<x-confirm-modal name="delete-exam" title="Delete exam?"
    :body="'This permanently removes “'.$exam->name.'” and all its questions. This cannot be undone.'"
    confirm-label="Delete" danger
    on-confirm="document.querySelector('[x-ref=deleteExamForm]').submit()" />
```
(Exact wiring — whether via a shared Alpine store keyed by form ref, or a scoped
`x-ref`/`$refs` lookup — is the executor's call per 09-UI-SPEC.md; the important constraint is that
the underlying `<form method="POST">` submission is preserved unchanged, no fetch/AJAX introduced.)

### Pattern 5: `Attempt::lockAndFinalize()` null-guard (INT-01)

**What:** `app/Models/Attempt.php:137-175`'s `lockAndFinalize()` re-reads the row with
`lockForUpdate()->first()` and then **unconditionally** does `$locked->setRelation(...)` on the
very next line with no null check [VERIFIED: `app/Models/Attempt.php` read in full, line 140-141].
Today this is safe because nothing deletes an `in_progress` attempt out from under a running
request — Phase 10 is the first phase that will add such a delete path (attempt-reset/editor-save
cancellation), but the guard must land *now*, before that code exists, per the phase's explicit
mandate.

**A second, independent crash site exists**, not just the one in `lockAndFinalize()`:
`app/Http/Controllers/Student/AttemptController.php:172` (`answer()` method) does its own **direct**
`Attempt::whereKey($attempt->id)->lockForUpdate()->first()` read, completely bypassing
`lockAndFinalize()`, and then reads `$locked->status` on the next line with no null check
[VERIFIED: `AttemptController.php` read in full]. Both sites need the guard — fixing only
`lockAndFinalize()` leaves `answer()`'s autosave path still crashing on a vanished row.

**Recommended shape:**
```php
// app/Models/Attempt.php — inside lockAndFinalize(), immediately after the locked re-read
$locked = self::whereKey($this->id)->lockForUpdate()->first();

if (! $locked) {
    // The row vanished under us (concurrent delete/reset). Treat exactly
    // like "already finalized by a racing request" — an idempotent no-op,
    // not a crash. Distinguish it from a normal false via return value or
    // exception if callers need to react differently (see below).
    return false;
}

$locked->setRelation('exam', $this->exam);
// ... rest unchanged
```
```php
// app/Http/Controllers/Student/AttemptController.php — inside answer()'s DB::transaction closure
$locked = Attempt::whereKey($attempt->id)->lockForUpdate()->first();

if (! $locked || $locked->status !== 'in_progress') {
    return false;
}
```

**Open design question this phase must resolve (not fully specified by CONTEXT.md/UI-SPEC.md —
flagged for the planner):** a plain `return false` makes the null case *indistinguishable* from the
ordinary "already finalized, nothing to do" case at the call site. But 09-UI-SPEC.md's copywriting
contract requires a *specific, user-facing* message ("This exam attempt is no longer available.
Please return to your exam list.") when the row has genuinely vanished — which is a different
situation from "already submitted, here's your result." Two viable shapes:
1. **Exception-based:** `lockAndFinalize()`/`answer()`'s direct read throw a small
   `AttemptVanishedException` on `$locked === null`, caught once (e.g. in a Form Request's
   `authorize()` boundary, a small middleware, or the exception handler in `bootstrap/app.php`'s
   `->withExceptions()`) and converted uniformly into a redirect with
   `session('error', 'This exam attempt is no longer available. Please return to your exam list.')`
   for page-load contexts (`show()`, `submit()`), and a `422 {'vanished': true, 'message': '...'}`
   JSON shape for the AJAX `answer()` autosave endpoint (mirroring the existing `{'expired': true}`
   convention already used for ordinary expiry at `AttemptController.php:191`).
2. **Return-value-based:** each caller (`show()`, `submit()`, `answer()`) explicitly re-checks
   `Attempt::find($attempt->id) === null` after a `false` return from `finalize()`/
   `finalizeIfExpired()` and branches on that, duplicating the "does this attempt still exist"
   check at 2-3 call sites instead of one.

Given this codebase's stated preference for single-chokepoint logic (`AttemptGrader`, the "one
place that ever writes `status=submitted`" comment on `lockAndFinalize()` itself), **option 1
(exception + one handler) is the better fit** and should be the default the planner adopts unless
it proves awkward for the JSON autosave path specifically (which cannot "redirect" — it must stay
a JSON 422 response the client-side JS interprets, most simply by reusing the toast for a
subsequent page load rather than trying to inject a toast into an AJAX response). This is flagged
`[ASSUMED]` — CONTEXT.md leaves "implementation shape... Claude's call" deliberately open; the
plan should make this decision explicit rather than leaving it ambiguous across three call sites.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Modal overlay/backdrop/focus-trap/Escape-to-close | A second modal system for `<x-confirm-modal>` | Wrap the existing `resources/views/components/modal.blade.php` | It already ships a complete, working, tested primitive (`x-on:open-modal.window`, focus-trap Tab-cycling, backdrop click-close, transitions) — rebuilding it is pure duplication and a second, divergent interaction pattern. |
| Semester date arithmetic per view | Ad hoc `now()->month >= 9` checks scattered across dashboard/subject-list views | One `App\Support\Semester` class, called through everywhere | Same "single predicate" discipline this codebase already enforces via `Exam::scopeVisibleTo()` — duplicated derivation logic is the exact bug class PITFALLS.md Pitfall 3 catalogs (year-rollover, leap-year, August-gap all silently reintroduced per duplicate). |
| Design token CSS | A parallel Flowbite CSS import / Tailwind v4 upgrade | `theme.extend` + CSS custom properties in the existing Tailwind 3 pipeline | v3.0 Decision #5 explicitly rules out the v4 upgrade; a parallel CSS import risks specificity conflicts with the 416 existing `dark:` utility occurrences. |
| Flash/notification delivery | A new `toast()` helper, event bus, or controller changes | The existing `session('status')`/`session('error')` convention, read once by `<x-toast>` | CONTEXT.md is explicit: "This touches zero controllers." Anything else is unrequested scope. |

**Key insight:** every "don't hand-roll" item above is really the same lesson restated: this
codebase has already solved each of these four problems once, informally or partially — the job in
this phase is to make each solution single-sourced and complete, not to invent a new one.

## Common Pitfalls

### Pitfall 1: S1's year-rollover attribution gets the wrong year for Jan/Feb dates

**What goes wrong:** A date in January or February 2027 belongs to the S1 semester that *started*
in September 2026 — so `Semester::forDate()` must return `year: 2026`, not `year: 2027`, for those
two months. A naive `if (month >= 9) year else year - 1`-less branch order silently attributes
Jan/Feb dates to the wrong year, which then breaks every downstream `ordinal()` comparison and
`startsAt()`/`endsAt()` computation for that semester.

**Why it happens:** It's tempting to write `$date->year` directly for the S1 branch without
noticing Jan/Feb needs `$date->year - 1` specifically, since "S1 of year Y" spans two different
calendar years (Y and Y+1) depending which month of it you're looking at.

**How to avoid:** Test both edges of the branch explicitly: a September date (attributes to that
same year) and a February date (attributes to the *previous* year) — see the branch order in
Pattern 1 above.

**Warning signs:** A test asserting `Semester::forDate(Carbon::parse('2027-02-15'))->year === 2027`
(wrong) instead of `2026` (correct).

### Pitfall 2: Hardcoded `->day(28)` for Feb instead of `->endOfMonth()`

**What goes wrong:** 2028 is a leap year (Feb has 29 days); a literal `->day(28)` silently drops
Feb 29 from S1-2027's (i.e., the S1 that ends Feb 2028) date range.

**How to avoid:** Always `->endOfMonth()`/`->lastOfMonth()`, never a literal day number, as shown
in Pattern 1's `endsAt()`.

**Warning signs:** Any `Carbon::create($y, 2, 28)` literal in the codebase.

### Pitfall 3: UI-03 fails silently — unstyled page, not a build error

**What goes wrong:** Tailwind does not error on an unrecognized/unresolved utility class name — it
simply emits no CSS for it. If the `theme.extend`/`app.css` wiring has any typo (a mismatched CSS
custom property name, a missing `.dark` block entry), the affected class silently produces zero
styling, and the page renders with default browser form styling — which can be mistaken for
"needs more classes" rather than "the token pipeline is broken."

**How to avoid:** Always run the compiled-CSS grep (Pattern 2's verification block) after any
change to `tailwind.config.js`/`resources/css/app.css`, before considering the work done. Never
accept a visual/screenshot check alone as verification for this specific requirement.

**Warning signs:** A login page that "looks mostly right" in a quick glance but has plain white
inputs with no border/background — the classic silent-failure look this pitfall produces.

### Pitfall 4: Toast sentinel exclusion is easy to get subtly wrong

**What goes wrong:** If `<x-toast>` checks `$status !== null` instead of also excluding the three
literal sentinel strings, the profile-update/verify-email pages will show a garbled duplicate toast
("password-updated" as raw toast text) *alongside* their own existing inline confirmation — the
exact landmine CONTEXT.md flags.

**How to avoid:** Use a literal `in_array($status, $sentinels, true)` check (strict comparison,
exact three-string allowlist) as shown in Pattern 3, not a substring/pattern match.

**Warning signs:** Visiting `/email/verify` or updating a profile/password after this phase ships
and seeing two confirmation messages instead of one.

### Pitfall 5: Fixing only one of the two `lockAndFinalize()`-adjacent crash sites

**What goes wrong:** Adding the null-guard inside the private `lockAndFinalize()` method alone
leaves `AttemptController::answer()`'s **separate, direct** `lockForUpdate()->first()` read (line
172) still capable of a null-pointer crash on the very next line — this is a distinct code path,
not a caller of `lockAndFinalize()`.

**How to avoid:** Guard both sites explicitly (see Pattern 5's two code blocks). A test asserting
"deleting the attempt row then calling `finalize()`/`finalizeIfExpired()` doesn't crash" will NOT
catch the `answer()` bug, since that method never calls those two methods for its lock read — write
a **separate** test that deletes the row then POSTs to the `answer()` autosave route.

**Warning signs:** A PLAN.md/diff that only touches `app/Models/Attempt.php` and claims INT-01 is
fully resolved — the fix is incomplete without also touching `AttemptController::answer()`.

## Code Examples

### Semester unit test style (mirrors this codebase's existing precedent exactly)

```php
<?php
// Source: mirrors tests/Unit/WindowSemanticsTest.php's exact style — plain object
// construction, no RefreshDatabase, Carbon::parse/travelTo for boundary instants.

namespace Tests\Unit;

use App\Support\Semester;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SemesterTest extends TestCase
{
    public function test_september_date_resolves_to_s1_same_year(): void
    {
        $s = Semester::forDate(Carbon::parse('2026-09-01'));
        $this->assertSame(2026, $s->year);
        $this->assertSame(1, $s->number);
    }

    public function test_february_date_resolves_to_s1_of_the_previous_year(): void
    {
        $s = Semester::forDate(Carbon::parse('2027-02-15'));
        $this->assertSame(2026, $s->year); // S1 that STARTED in Sep 2026
        $this->assertSame(1, $s->number);
    }

    public function test_s1_end_date_is_leap_year_correct(): void
    {
        $s = new Semester(2027, 1); // ends Feb 2028 — a leap year
        $this->assertSame('2028-02-29', $s->endsAt()->format('Y-m-d'));
    }

    public function test_s1_end_date_is_non_leap_year_correct(): void
    {
        $s = new Semester(2026, 1); // ends Feb 2027 — not a leap year
        $this->assertSame('2027-02-28', $s->endsAt()->format('Y-m-d'));
    }

    public function test_august_rolls_forward_to_upcoming_s1(): void
    {
        $s = Semester::forDate(Carbon::parse('2026-08-15'));
        $this->assertSame(2026, $s->year);
        $this->assertSame(1, $s->number);
    }

    public function test_ordinal_sorts_correctly_across_the_s1_year_rollover(): void
    {
        $s2_2026 = new Semester(2026, 2);
        $s1_2027 = new Semester(2027, 1);
        $this->assertLessThan($s1_2027->ordinal(), $s2_2026->ordinal());
    }
}
```

### Compiled-CSS token verification (UI-03, run as part of the phase's own acceptance gate)

```bash
npm run build
grep -o "\.bg-brand{" public/build/assets/*.css && \
grep -o "\.text-heading{" public/build/assets/*.css && \
grep -o "\.rounded-base{" public/build/assets/*.css && \
echo "UI-03 tokens confirmed emitting CSS" || echo "UI-03 FAILED: tokens not found in compiled CSS"
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| Breeze default `welcome.blade.php` at `/` | Branded landing page, guest-only, auth-redirects to `/dashboard` | This phase | Existing `resources/views/welcome.blade.php` is replaced entirely, not extended. |
| Ad hoc per-view flash `<div>` (28 files, hand-placed `dark:`) | One `<x-toast>` reading `session('status')`/`session('error')` | This phase | Fixes the "update assignment looks like nothing happened" perceived bug (ARCHITECTURE.md's root-cause finding) as a side effect — no controller change needed, since the flash was already being set correctly; only its rendering was invisible. |
| 3 native `onsubmit="return confirm(...)"` sites | `<x-confirm-modal>` wrapping `<x-modal>` | This phase | Removes the last native browser dialogs from the app, satisfying UX-02's "never used" bar. |

**Deprecated/outdated:** none — this phase introduces no deprecated pattern; it retires the 3
`confirm()` call sites and the unstyled-Flowbite-class risk, both of which are this milestone's own
prior-state issues, not upstream deprecations.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | INT-01's null-guard should use an exception-based single-chokepoint fix (option 1 in Pattern 5) rather than per-caller return-value re-checks | Architecture Patterns → Pattern 5 | If the planner instead picks return-value duplication across 3 call sites, it's still correct but less consistent with this codebase's established single-chokepoint style — low risk, a style preference not a correctness issue. |
| A2 | The AJAX `answer()` autosave endpoint's response to a vanished-attempt null-guard trip should reuse the existing `{'expired': true}` JSON shape rather than a new `{'vanished': true}` key | Architecture Patterns → Pattern 5 | If the client-side JS branches specifically on `expired` to trigger a distinct UI treatment from "vanished," reusing the same key could conflate two different situations in the client. Low risk since both currently drive the same "attempt over, redirect" client behavior. |
| A3 | `rgb(var(--color-x) / <alpha-value>)` CSS-custom-property color pattern works identically for `boxShadow`/`borderRadius` extend keys (i.e., no CSS-variable indirection needed for those, since they're not colors) | Architecture Patterns → Pattern 2 | Very low risk — `borderRadius`/`boxShadow` are plain string values in Tailwind, not color functions; this is standard, uncontroversial Tailwind config usage, cross-checked against the same official source as the color pattern. |

**If this table is empty:** N/A — see entries above. All three are low-risk implementation-shape
choices, not open questions about *what* to build (CONTEXT.md/UI-SPEC.md settle the "what" for
every requirement in this phase); they concern *how* to wire the one genuinely open mechanism
(INT-01's guard shape) and a minor detail of the token CSS technique.

## Open Questions

1. **INT-01's exact guard mechanism (exception vs. return-value) and how it surfaces to the 3
   different call sites (`show()`, `submit()`, `answer()`'s AJAX autosave)**
   - What we know: the crash mechanics (both sites), the required user-facing copy for the
     page-load case, and that the existing `{'expired': true}` JSON convention is a plausible
     template for the AJAX case.
   - What's unclear: whether to introduce a small custom exception type + a single handler, or
     duplicate a `null` re-check at each of the 3 call sites. CONTEXT.md explicitly leaves this to
     the planner/executor.
   - Recommendation: adopt the exception + single-handler shape (Pattern 5, option 1) for
     consistency with this codebase's established single-chokepoint conventions; the planner should
     make this an explicit task rather than leaving it implicit.

2. **Whether the landing page's guest shell reuses `layouts/guest.blade.php` or needs its own
   dedicated layout** (the login card keeps `layouts.guest`, but the landing page's spec calls for
   a full-bleed page with a top bar — `layouts.guest`'s current markup centers a narrow card, which
   doesn't fit a full-bleed hero).
   - What we know: 09-UI-SPEC.md describes the landing page's layout requirements (top bar, full-
     bleed hero, no card) in detail; `layouts/guest.blade.php` as it exists today is a centered-card
     shell unsuitable for that without modification.
   - What's unclear: whether to add a landing-specific layout/blade file or heavily conditionally
     modify `layouts.guest` to support both shapes.
   - Recommendation: a new, small dedicated layout (or the landing view assembling its own
     `<html>` shell reusing only the shared `<head>` partial) is simpler and lower-risk than
     branching `layouts.guest`'s existing centered-card behavior, which the login page still needs
     unchanged.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Node.js | `npm run build` (UI-03 verification) | Yes | v20.14.0 | — |
| npm | build tooling | Yes | 10.7.0 | — |
| PHP | Laravel runtime, `php artisan test` | Yes | 8.2.32 | — |
| Laravel | framework | Yes | 11.55.0 | — |
| MySQL (Herd, `yp-student-exam`) | test DB, Feature tests | Assumed available via Herd (not re-probed this session; `.env` access is restricted in this environment) | — | Planner/executor should confirm `php artisan migrate:status` succeeds before starting work |

**Missing dependencies with no fallback:** none identified.
**Missing dependencies with fallback:** none identified — all required tooling is present.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit (via `php artisan test`), Laravel 11.55 [VERIFIED: `phpunit.xml` read directly] |
| Config file | `phpunit.xml` — `tests/Unit` + `tests/Feature` suites, `app/` covered by `<source>` |
| Quick run command | `php artisan test tests/Unit/SemesterTest.php` (or `--filter=Semester`/`--filter=Toast`/etc.) |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SEM-01 | S1=Sep→Feb(next yr), S2=Mar→Jul, full-month bounds, leap-year Feb correct | unit | `php artisan test --filter=SemesterTest` | ❌ Wave 0 |
| SEM-02 | `ordinal()` sorts correctly across the S1 year rollover | unit | `php artisan test --filter=test_ordinal_sorts_correctly_across_the_s1_year_rollover` | ❌ Wave 0 |
| SEM-03 | August date resolves to the upcoming (roll-forward) semester | unit | `php artisan test --filter=test_august_rolls_forward_to_upcoming_s1` | ❌ Wave 0 |
| INT-01 | Deleting an attempt row mid-lock doesn't crash `finalize()`/`finalizeIfExpired()`/`answer()` | feature | `php artisan test --filter=AttemptNullGuardTest` | ❌ Wave 0 |
| UI-03 | Token classes emit real CSS after `npm run build` | build (not PHPUnit) | `npm run build && grep -o "\.bg-brand{" public/build/assets/*.css` (see Code Examples block for the full 3-token check) | ❌ Wave 0 — no existing build-verification script |
| NAV-01 | Guest sees landing page at `/`; authenticated user redirected to `/dashboard` | feature | `php artisan test --filter=LandingPageTest` | ❌ Wave 0 |
| NAV-02 | Login form posts to `route('login')` with CSRF, `old('email')`, inline `x-input-error`, working `register`/`password.request` links | feature | `php artisan test --filter=AuthenticationTest` (extend existing `tests/Feature/Auth/AuthenticationTest.php`) | ✅ existing file, needs new assertions |
| UX-01 | Page `<title>` and landing hero read "Online Examination Portal" / subtitle | feature/smoke | `php artisan test --filter=LandingPageTest` | ❌ Wave 0 (same file as NAV-01) |
| UX-02 | Zero native `confirm()`/`alert()` remain in `resources/views`; 3 sites use `<x-confirm-modal>` | static/lint | a small PHPUnit test that greps `resources/views` for `onsubmit="return confirm` / `alert(` and asserts zero matches | ❌ Wave 0 |
| UX-03 | Create/save/delete actions render via `<x-toast>`; sentinel exclusion holds | feature | `php artisan test --filter=ToastTest` (spot-check 2-3 existing flash-producing routes + the 3 sentinel routes) | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** the quick run command for whichever file the task touched (e.g.
  `php artisan test --filter=SemesterTest` after a `Semester` edit).
- **Per wave merge:** `php artisan test` (full suite) plus, if any UI-03-adjacent file changed,
  `npm run build` + the 3-token grep.
- **Phase gate:** full suite green, plus the UI-03 compiled-CSS grep passing, before
  `/gsd-verify-work`.

### Wave 0 Gaps

- [ ] `tests/Unit/SemesterTest.php` — covers SEM-01, SEM-02, SEM-03
- [ ] `tests/Feature/AttemptNullGuardTest.php` — covers INT-01 (both crash sites: `lockAndFinalize()`
      via `finalize()`/`finalizeIfExpired()`, and `AttemptController::answer()`'s direct read)
- [ ] `tests/Feature/LandingPageTest.php` — covers NAV-01, UX-01
- [ ] Extend `tests/Feature/Auth/AuthenticationTest.php` — covers NAV-02 (existing file, needs new
      assertions for token classes / `register`/`password.request` link targets)
- [ ] A static "no native confirm()/alert()" scan test — covers UX-02
- [ ] `tests/Feature/ToastTest.php` — covers UX-03 (and the sentinel-exclusion landmine)
- [ ] No PHPUnit test for UI-03 — this requirement is verified by a `npm run build` + compiled-CSS
      grep, which should be documented as a manual/scripted acceptance-gate step, not a PHPUnit file

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-------------------|
| V2 Authentication | Indirectly (login page restyle only) | Breeze's existing auth controllers/routes — unchanged this phase; only the view markup changes |
| V3 Session Management | No | Not touched this phase |
| V4 Access Control | No | INT-01's guard is a data-integrity fix, not an authorization change; existing `AttemptPolicy`/Form Request authorization is untouched |
| V5 Input Validation | No new surface | The login form's `name`/`autocomplete` attributes and `@csrf` are added per NAV-02, using Laravel's existing CSRF/validation pipeline — no new validation rules introduced |
| V6 Cryptography | No | Not touched |
| V11 Business Logic (informal, race-condition class) | Yes | INT-01's null-guard directly addresses a TOCTOU (time-of-check-to-time-of-use) race between a concurrent delete and a `lockForUpdate()` read — the standard mitigation (already partially applied elsewhere in this codebase) is exactly what's being extended: re-check state under the same row lock, never trust a pre-lock read |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|----------------------|
| TOCTOU race: attempt deleted between an application's decision to act on it and the locked read that acts | Tampering / Denial of Service (crash) | `lockForUpdate()` inside `DB::transaction()` (already used) + explicit null-check after the locked read (this phase's fix) — never assume a row exists just because a route-model-bound instance was resolved earlier in the request |
| XSS via unescaped flash/error message rendered in `<x-toast>` | Tampering (stored/reflected XSS) | Blade's `{{ }}` (not `{!! !!}`) auto-escapes `session('status')`/`session('error')` content — confirm the toast component uses `{{ }}` exclusively, never raw HTML output, for the flash text (both are currently plain `__()`-wrapped strings, never user-supplied HTML) |
| Native `confirm()`/`alert()` retained as a fallback "just in case" | Tampering (inconsistent UX, not a security bug per se, but flagged since UX-02 is a hard requirement) | The static-scan test (UX-02's Wave-0 gap) enforces zero native dialogs remain — treat any reintroduction as a regression, not a style nit |

## Sources

### Primary (HIGH confidence)
- `app/Models/Attempt.php` — read directly, in full; exact null-guard crash sites identified at
  lines 140-141 and (separately) `AttemptController.php:172`.
- `app/Models/Section.php` — read directly; `windowStatus()`'s half-open-interval pattern is the
  established precedent `Semester` should mirror.
- `resources/views/components/modal.blade.php` — read directly, in full; the primitive
  `<x-confirm-modal>` wraps.
- `resources/views/auth/login.blade.php`, `routes/web.php`, `tailwind.config.js`,
  `resources/css/app.css`, `package.json`, `phpunit.xml`, `.planning/config.json` — all read
  directly to ground every claim in this document in the actual current repo state.
- `.planning/phases/09-.../09-CONTEXT.md`, `09-UI-SPEC.md`, `.planning/REQUIREMENTS.md`,
  `.planning/STATE.md`, `.planning/research/PITFALLS.md`, `.planning/research/ARCHITECTURE.md`,
  `.planning/v3.md` — all read directly; this document treats their locked decisions as
  non-negotiable inputs, not re-litigated.
- https://github.com/tailwindlabs/tailwindcss/blob/v3/stubs/config.full.js — fetched directly;
  confirms `borderColor`/`ringColor` are `theme('colors')`-derived functions in Tailwind v3's
  default preset, the load-bearing fact behind UI-03's token-port mechanism.

### Secondary (MEDIUM confidence)
- Web search cross-referencing Tailwind's documented `rgb(var(--x) / <alpha-value>)`
  CSS-custom-property color-opacity pattern — consistent across multiple sources (Tailwind's own
  docs on `opacity`, GitHub Discussions #7407/#8987), verified against the mechanism actually
  specified in 09-UI-SPEC.md.

### Tertiary (LOW confidence)
- None used unverified in this document — every claim above was either read directly from this
  repo or cross-checked against an official/semi-official source before being marked CITED.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new dependencies; every existing package version confirmed by reading
  `package.json`/`composer.json`-equivalent directly.
- Architecture: HIGH — all four deliverables' mechanics (Semester math, token CSS, toast/modal
  wiring, null-guard crash sites) verified against actual current code, not generic advice.
- Pitfalls: HIGH — all five pitfalls are grounded in either this codebase's own read code or a
  directly-verified Tailwind mechanism, not speculative.

**Research date:** 2026-07-17
**Valid until:** 30 days (stable Laravel/Tailwind 3 stack; no fast-moving dependency in this phase)

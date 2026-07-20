---
phase: 9
slug: v3-0-foundations-semester-model-design-tokens-alerts-entry-pages
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-07-17
---

# Phase 9 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Derived from `09-RESEARCH.md` § Validation Architecture.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit via `php artisan test` (Laravel 11.55) — verified by reading `phpunit.xml` |
| **Config file** | `phpunit.xml` — `tests/Unit` + `tests/Feature` suites, `app/` under `<source>` |
| **Quick run command** | `php artisan test --filter={SemesterTest\|ToastTest\|…}` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~60–90s full suite (294 tests at v2.0 close) |

**Non-PHPUnit gate (UI-03):** `npm run build` + a compiled-CSS grep. See "Manual-Only
Verifications" — this one cannot be a PHPUnit test.

---

## Sampling Rate

- **After every task commit:** the quick run command for the file the task touched
  (e.g. `php artisan test --filter=SemesterTest` after a `Semester` edit).
- **After every plan wave:** `php artisan test` (full suite). Plus, if any UI-03-adjacent file
  changed (`tailwind.config.js`, `app.css`, any Blade using the new tokens): `npm run build` +
  the 3-token grep.
- **Before `/gsd-verify-work`:** full suite green **and** the UI-03 compiled-CSS grep passing.
- **Max feedback latency:** ~90 seconds.

---

## Per-Task Verification Map

| Req ID | Behavior | Threat Ref | Test Type | Automated Command | File Exists | Status |
|--------|----------|------------|-----------|-------------------|-------------|--------|
| SEM-01 | S1 = Sep→Feb (next yr), S2 = Mar→Jul; full-month bounds; leap-year Feb end correct | — | unit | `php artisan test --filter=SemesterTest` | ❌ W0 | ⬜ pending |
| SEM-02 | `ordinal()` sorts correctly across the S1 year rollover (Sep 2026 → Feb 2027 before Mar 2027) | — | unit | `php artisan test --filter=SemesterTest` | ❌ W0 | ⬜ pending |
| SEM-03 | An August date resolves to the upcoming semester (roll-forward policy) | — | unit | `php artisan test --filter=SemesterTest` | ❌ W0 | ⬜ pending |
| INT-01 | A vanished attempt row does not crash `finalize()` / `finalizeIfExpired()` **or** `AttemptController::answer()` | T-09-01 (TOCTOU) | feature | `php artisan test --filter=AttemptNullGuardTest` | ❌ W0 | ⬜ pending |
| UI-03 | Token classes emit real CSS after `npm run build` | — | **build (not PHPUnit)** | `npm run build` + compiled-CSS grep | ❌ W0 | ⬜ pending |
| NAV-01 | Guest sees landing at `/`; authenticated user redirected to `/dashboard` | — | feature | `php artisan test --filter=LandingPageTest` | ❌ W0 | ⬜ pending |
| NAV-02 | Login posts to `route('login')` w/ CSRF, `old('email')`, inline `x-input-error`, working `register` / `password.request` links | — | feature | `php artisan test --filter=AuthenticationTest` | ✅ exists, needs new assertions | ⬜ pending |
| UX-01 | Page `<title>` and landing hero read "Online Examination Portal" / "for Yayasan Peneraju Technical Assessment" | — | feature | `php artisan test --filter=LandingPageTest` | ❌ W0 | ⬜ pending |
| UX-02 | Zero native `confirm()` / `alert()` remain in `resources/views`; the 3 sites use `<x-confirm-modal>` | T-09-03 | static scan | `php artisan test --filter=NoNativeDialogTest` | ❌ W0 | ⬜ pending |
| UX-03 | Create/save/delete render via `<x-toast>`; **sentinel exclusion holds** | T-09-02 (XSS) | feature | `php artisan test --filter=ToastTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/SemesterTest.php` — SEM-01, SEM-02, SEM-03. **Must include:** the Sep 2026 → Feb
      2027 rollover ordering case, a leap-year February end (Feb 2028 → 29th), and an August-gap
      date asserting roll-forward.
- [ ] `tests/Feature/AttemptNullGuardTest.php` — INT-01, **covering BOTH crash sites**:
      `lockAndFinalize()` (reached via `finalize()` / `finalizeIfExpired()`) at
      `app/Models/Attempt.php:141`, **and** `AttemptController::answer()`'s separate direct
      `lockForUpdate()->first()` at `app/Http/Controllers/Student/AttemptController.php:172`.
      Exercise the vanished-row path directly (delete the row inside the test) — do not attempt to
      reproduce a real race.
- [ ] `tests/Feature/LandingPageTest.php` — NAV-01, UX-01.
- [ ] Extend `tests/Feature/Auth/AuthenticationTest.php` — NAV-02 assertions (token classes present,
      `register` / `password.request` link targets resolve).
- [ ] `tests/Feature/NoNativeDialogTest.php` — UX-02 static scan: grep `resources/views` for
      `onsubmit="return confirm` and `alert(`, assert zero matches.
- [ ] `tests/Feature/ToastTest.php` — UX-03, including the **sentinel-exclusion landmine**: assert
      `verification-link-sent`, `password-updated`, and `profile-updated` do NOT render as toasts and
      their existing inline confirmations still render.

*No framework install needed — PHPUnit is configured and 294 tests already pass.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Flowbite token classes emit real CSS | UI-03 | **PHPUnit cannot see the compiled CSS bundle.** The failure mode is silently-unstyled markup, not an error — a passing PHPUnit suite proves nothing here. This is the phase's central technical risk and needs its own gate. | `npm run build`, then grep the built asset in `public/build/assets/*.css` for the emitted token rules (e.g. `.bg-brand{`, `.text-heading{`, `.rounded-base{`). All must be present. Scripted, not eyeballed — do **not** substitute "the page looks right in a browser". |

---

## Threat Model Refs

| ID | Threat | STRIDE | Mitigation (this phase) |
|----|--------|--------|--------------------------|
| T-09-01 | TOCTOU: an attempt row is deleted between resolving the route-model-bound instance and the locked read that acts on it | Tampering / DoS (crash) | Explicit null-check after **every** `lockForUpdate()->first()` on `attempts` — both sites. Never assume the row exists because it resolved earlier in the request. |
| T-09-02 | XSS via unescaped flash text rendered in `<x-toast>` | Tampering | The toast must render flash text with Blade `{{ }}` (auto-escaping) exclusively — never `{!! !!}`. |
| T-09-03 | A native `confirm()` / `alert()` retained "just in case" | — (hard requirement, not a security bug) | The UX-02 static-scan test enforces zero native dialogs; any reintroduction is a regression, not a style nit. |

**ASVS L1 note:** V2/V3/V4/V5/V6 are not materially touched — Breeze's auth controllers and routes
are unchanged (login is a *view* restyle only), and INT-01 is a data-integrity fix, not an
authorization change. V11 (business logic / race conditions) is the live category, covered by T-09-01.

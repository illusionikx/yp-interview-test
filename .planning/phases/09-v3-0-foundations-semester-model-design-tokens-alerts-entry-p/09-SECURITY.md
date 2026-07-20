---
phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-p
audited: 2026-07-17
asvs_level: 1
block_on: high
threats_open: 0
threats_total: 13
known_deferred_items: 1
status: SECURED
---

# Phase 9 — Security Audit

Verified against the STRIDE threat registers declared in each of the 10 plans'
`<threat_model>` blocks (T-09-01 through T-09-13 — 09-VALIDATION.md summarizes only
T-09-01/02/03; the full register lives distributed across `09-01-PLAN.md` … `09-10-PLAN.md`
and was reconstructed from all ten). Every threat below was verified against the
implemented code, not against plan/summary narrative. Implementation files were read-only;
no code was modified during this audit.

## Threat Verification

| Threat ID | Category | Disposition | Component | Evidence | Status |
|-----------|----------|-------------|------------|----------|--------|
| T-09-01 | Tampering / DoS (crash) — TOCTOU on `attempts` | mitigate | `Attempt::lockAndFinalize()`, `AttemptController::answer()` | `app/Models/Attempt.php:160-162` — `if (! $locked) { throw new AttemptVanishedException; }` immediately after `self::whereKey($this->id)->lockForUpdate()->first()` (line 141). `app/Http/Controllers/Student/AttemptController.php:191-193` — identical guard immediately after its own independent `Attempt::whereKey($attempt->id)->lockForUpdate()->first()` (line 178). Both are the two sites every plan (09-01, 09-05) declared in scope. `tests/Feature/AttemptNullGuardTest.php` — 5 tests, all pass (hard-deletes the row via `DB::table('attempts')->delete()` mid-request via `Gate::after`, then asserts the typed exception / 422 / redirect-with-error, plus a control test that a surviving attempt still finalizes normally). | CLOSED (declared scope) — see Known Deferred Item below |
| T-09-02 | Tampering (XSS) | mitigate | `resources/views/components/toast.blade.php`, `resources/views/components/confirm-modal.blade.php` | `toast.blade.php:47,78` — `{{ $status }}` / `{{ $error }}`, escaping echo only. `confirm-modal.blade.php:23-24` — `{{ $title }}` / `{{ $body }}`, escaping echo only. Repo-wide: `grep -rn '{!!' resources/views` → **zero matches**. `tests/Feature/ToastTest.php::test_the_toast_escapes_html_in_flash_text` seeds `<b>bold</b>` and asserts the escaped entity renders, raw tag does not — passes. | CLOSED |
| T-09-03 | Tampering (native dialog retained; hard requirement) | mitigate | `resources/views/**` (all Blade views), specifically the 3 migrated call sites in `lecturer/exams/show.blade.php` (×2) and `lecturer/subjects/index.blade.php` (×1) | Repo-wide: `grep -rn 'confirm(\|alert(' resources/views` → **zero matches**. All 3 destructive-delete sites now dispatch `open-modal` to a paired `<x-confirm-modal>`. `tests/Feature/NoNativeDialogTest.php` — 3 tests, all pass: (1) zero-native-dialog scan, (2) `<x-confirm-modal` presence at both files, (3) `test_each_destructive_forms_x_ref_matches_its_confirm_modals_refs_submit_call` — added after 09-REVIEW.md's WR-01 finding, pins the `x-ref` ↔ `$refs.<name>.submit()` wiring itself plus one `@csrf`/`@method('DELETE')` per form, closing the "modal opens but submits nothing" regression class WR-01 flagged as untested. | CLOSED |
| T-09-04 | Information Disclosure — authenticated navbar leaking onto guest landing | mitigate | `resources/views/landing.blade.php`, `resources/views/layouts/landing.blade.php` | `layouts/landing.blade.php` renders no `@include('layouts.navigation')`, no `route('logout')`, no user dropdown — verified by direct read of the full file (lines 1-98); the only auth-adjacent link is `route('login')`. `tests/Feature/LandingPageTest.php` covers this (`test_the_landing_page_does_not_render_the_authenticated_navbar`, part of the passing suite). | CLOSED |
| T-09-05 | Spoofing / CSRF — inert `<form action="#">` shipping from the v3.md static snippet | mitigate | `resources/views/auth/login.blade.php` | Line 7: `<form method="POST" action="{{ route('login') }}">` with `@csrf` at line 8. No `action="#"` anywhere in the file. `AuthenticationTest` extended per 09-02-PLAN.md; Breeze's `VerifyCsrfToken` middleware and `AuthenticatedSessionController`/`LoginRequest` confirmed unmodified (`git diff 170cb93 HEAD -- app/Http/Controllers/Auth/ app/Http/Requests/Auth/` — empty). | CLOSED |
| T-09-06 | Tampering (logic error) — `Semester::ordinal()` mis-ordering could mis-scope a future cohort query | mitigate | `app/Support/Semester.php` | Line 125: `return $this->year * 2 + (2 - $this->number);` — the corrected formula (not research's inverted `year*2+(number-1)`). `tests/Unit/SemesterTest.php::test_ordinal_is_monotonic_with_start_date` sorts a 4-semester set two ways (by `ordinal()`, by `startsAt()`) and asserts identical order — passes, part of the 340-green baseline. | CLOSED |
| T-09-07 | Information Disclosure — `AttemptVanishedException` message could leak attempt/owner identity | accept | `app/Exceptions/AttemptVanishedException.php` | `MESSAGE` constant (line 40): `'This exam attempt is no longer available. Please return to your exam list.'` — no attempt id, owner, or exam name interpolated; identical text whether the row never existed for this caller or was just deleted. Route-model binding + `AttemptPolicy` already gate access before this exception is reachable. Accepted-risk entry logged below. | CLOSED (accepted) |
| T-09-08 | Information Disclosure — login form's per-field errors could enable user enumeration | accept | `resources/views/auth/login.blade.php` | Messages are Laravel/Breeze's stock validation strings, unchanged — `LoginRequest`/`AuthenticatedSessionController` confirmed untouched this phase (see T-09-05 diff evidence). No new enumeration surface introduced by the view restyle. Accepted-risk entry logged below. | CLOSED (accepted) |
| T-09-09 | Tampering (supply chain) — a new/compromised package slipping in with the design-token work | mitigate | `tailwind.config.js`, `resources/css/app.css` | `git diff 170cb93 HEAD -- package.json package-lock.json composer.json composer.lock` → **empty**. No new dependency installed this phase; `tailwindcss`/`flowbite`/`@tailwindcss/forms`/`alpinejs` all pre-existing. | CLOSED |
| T-09-10 | Information Disclosure — toast now renders flash data on unauthenticated pages | accept | `<x-toast />` in `resources/views/layouts/guest.blade.php:30` and `resources/views/layouts/landing.blade.php:96` | Confirmed both layouts render `<x-toast />`. Flash data is per-session, set only by this app's own controllers — a guest can only ever see a message this app flashed into that same guest's own session; no cross-session or authenticated-user data path exists into `session('status')`/`session('error')`. Accepted-risk entry logged below. | CLOSED (accepted) |
| T-09-11 | Elevation of Privilege — `/` intentionally unauthenticated | accept | `routes/web.php:11-13` | `Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('landing'));` — reads no request input, touches no database. `route('dashboard')` retains its pre-existing `['auth', 'verified']` middleware (line 15-17), confirmed unchanged — the redirect is a convenience, not the security boundary. Accepted-risk entry logged below. | CLOSED (accepted) |
| T-09-12 | Spoofing / CSRF — migrated delete forms losing `@csrf`/method-spoofing in the modal rewrite | mitigate | `lecturer/exams/show.blade.php`, `lecturer/subjects/index.blade.php` (3 forms) | Direct read confirms `@csrf` + `@method('DELETE')` present in all 3 forms. `NoNativeDialogTest::test_each_destructive_forms_x_ref_matches_its_confirm_modals_refs_submit_call` additionally asserts exactly one `@method('DELETE')` and at least one `@csrf` per destructive form, tied to the x-ref count — passes. | CLOSED |
| T-09-13 | Tampering — client-side confirm-modal is bypassable (JS disabled) | accept | `<x-confirm-modal>` | Confirmed real gate is server-side: `routes/lecturer.php:14-17` wraps the entire lecturer route group (including `exams.destroy`, `subjects.destroy`, `exams.questions.destroy`) in `['auth', 'verified', 'role:lecturer']` middleware, unchanged by this phase. The modal grants nothing; bypassing it still routes through the same authorization. Accepted-risk entry logged below. | CLOSED (accepted) |

**13/13 threats declared in Phase 9's plans are CLOSED.** `threats_open: 0`.

## Accepted Risks Log

The following dispositions were declared `accept` in the source plans and are logged here as
the accepted-risk record required to resolve them to CLOSED:

1. **T-09-07** — `AttemptVanishedException` renders a generic, non-identifying message
   regardless of whether the attempt never existed for this caller or was just deleted.
   Accepted because route-model binding + `AttemptPolicy` gate access before the exception is
   reachable at all.
2. **T-09-08** — Login form surfaces Laravel/Breeze's stock per-field validation messages
   (unchanged this phase). Accepted as pre-existing Breeze behavior, not a new surface.
3. **T-09-10** — `<x-toast />` now renders on the two unauthenticated shells (`guest`,
   `landing`). Accepted because flash data is strictly per-session and set only by this app's
   own controllers.
4. **T-09-11** — `/` is intentionally public. Accepted — it is NAV-01 itself, reads no input,
   and the actual authorization boundary (`route('dashboard')`'s `['auth','verified']`) is
   unchanged.
5. **T-09-13** — The confirm-modal is a UX safeguard only; a JS-disabled client bypasses it
   straight to the form POST. Accepted because the real gates (route middleware + policy) are
   server-side, unchanged, and untouched by this phase.

## Known Deferred Item (Not a Phase 9 Threat — Reported Per Orchestrator Instruction)

**Site:** `app/Http/Controllers/Lecturer/AnswerGradeController.php:29`
```php
$locked = Attempt::whereKey($attempt->id)->lockForUpdate()->first();  // unguarded
...
app(AttemptGrader::class)->syncStatus($locked);                        // TypeError if null
```
`AttemptGrader::syncStatus(Attempt $attempt): void` (`app/Services/AttemptGrader.php:69`) has a
non-nullable parameter — a vanished row TypeErrors into a 500 inside the `DB::transaction()`,
which rolls back cleanly (no data corruption).

This is the same TOCTOU class as T-09-01, but **none of Phase 9's ten plans declared this call
site in scope** — 09-01/09-05 explicitly scope T-09-01 to exactly the two guarded sites, and
every other plan states "T-09-01 does not apply." It is already filed at
`.planning/todos/pending/int-01-third-crash-site-grading-path.md` with `resolves_phase: 10`,
`severity: medium`. Per instruction, this is reported as a known open item, not re-filed.

**Correction to the filed todo's reachability claim.** The todo states: *"The bug is latent
today — nothing currently deletes an attempt row today."* This audit found that claim is
**factually incorrect**. `database/migrations/2026_07_15_100009_create_attempts_table.php:17`
declares `attempts.user_id` with `cascadeOnDelete()`, and Breeze's stock, unmodified
`ProfileController::destroy()` (`DELETE /profile`, gated only by `auth`) calls `$user->delete()`
— any authenticated user, including a student, can delete their own account today via the
standard "delete account" self-service flow, and MySQL will cascade-delete every `attempts` row
(and, transitively, `answers` rows) that user owns. This is reachable in the **currently shipped
code**, not only after Phase 10 ships a reset/cancel feature.

**My severity assessment: MEDIUM, not HIGH — the phase gate is not affected.**
- Confidentiality impact: none — the rendered 500 leaks no attempt/user data.
- Integrity impact: none — `DB::transaction()` rolls back the grade write; the row deletion
  that triggered the crash already committed independently and correctly via cascade.
- Availability impact: narrow — one lecturer HTTP request returns a 500 instead of a graceful
  "this attempt no longer exists" message. Not a persistent or repeatable outage; the next
  request against the same (now-deleted) `answer` 404s normally via route-model binding.
- Reachability: requires a genuine but ordinary concurrent-request race — route-model binding
  for `Attempt`/`Answer` must resolve before the student's account-deletion commits the cascade,
  and the lecturer's `lockForUpdate()` read must land after. No special tooling or privilege
  is needed to trigger the deleting side (self-account-deletion is a routine action), but hitting
  the exact race window is not guaranteed on any single attempt.
- No privilege escalation, no authorization bypass — `GradeAnswerRequest::authorize()` and the
  route's `role:lecturer` middleware are unaffected and still gate the request correctly.

Recommendation: update the filed todo's reachability rationale (the disposition — defer the fix
to Phase 10 alongside the attempt-lifecycle work — can reasonably stand given the medium, not
high, impact ceiling, but the todo currently under-states risk by claiming zero reachability).
This is a recommendation for the todo's owner; per this audit's read-only constraint on
implementation files, no code or todo file was modified.

## Unregistered Flags

None. No `## Threat Flags` section (or equivalent) was found in any of the 10 `09-*-SUMMARY.md`
files. Grepping all summaries for `T-09-\d+` surfaces only references back to threats already
declared in the plans (T-09-02 in 09-03/09-07, T-09-04 in 09-08) — no new threat IDs, no
undeclared attack surface flagged by the executor during implementation.

## ASVS L1 Scope (cross-checked against implementation, not accepted from plan text)

- **V2 (Authentication):** touched only via view restyle. `app/Http/Controllers/Auth/` and
  `app/Http/Requests/Auth/` confirmed byte-identical to pre-phase (`git diff` empty).
- **V3 (Session Management):** not touched — toast reads flash via Laravel's existing session
  abstraction; writes none.
- **V4 (Access Control):** not touched — every route/policy/middleware gate cited above
  (`role:lecturer`, `AttemptPolicy`, `route('dashboard')`'s `['auth','verified']`) verified
  unchanged.
- **V5 (Input Validation / Output Encoding):** the live category — output encoding via Blade's
  default escaping is the mitigation for T-09-02/T-09-12's interpolated modal body, verified by
  direct grep (zero `{!!`) rather than accepted from plan narrative.
- **V6 (Cryptography):** not touched.
- **V11 (Business Logic / Race Conditions):** the live category for T-09-01, fully verified at
  both declared call sites; see Known Deferred Item above for the one site outside this phase's
  declared scope.

## Files Verified (read-only)

`app/Models/Attempt.php`, `app/Http/Controllers/Student/AttemptController.php`,
`app/Exceptions/AttemptVanishedException.php`, `app/Http/Controllers/Lecturer/AnswerGradeController.php`,
`app/Services/AttemptGrader.php`, `app/Http/Requests/Lecturer/GradeAnswerRequest.php`,
`app/Support/Semester.php`, `resources/views/components/toast.blade.php`,
`resources/views/components/confirm-modal.blade.php`, `resources/views/auth/login.blade.php`,
`resources/views/auth/forgot-password.blade.php`, `resources/views/layouts/guest.blade.php`,
`resources/views/layouts/landing.blade.php`, `resources/views/landing.blade.php`,
`resources/views/lecturer/exams/show.blade.php`, `resources/views/lecturer/subjects/index.blade.php`,
`routes/web.php`, `routes/lecturer.php`, `database/migrations/2026_07_15_100009_create_attempts_table.php`,
`tests/Feature/AttemptNullGuardTest.php`, `tests/Feature/ToastTest.php`,
`tests/Feature/NoNativeDialogTest.php`, `tests/Unit/SemesterTest.php`, plus all 10
`09-*-PLAN.md` `<threat_model>` blocks, `09-VALIDATION.md`, `09-REVIEW.md`, and the
`int-01-third-crash-site-grading-path.md` todo.

## Baseline (orchestrator-verified, re-cited here)

`php artisan test` → 340 passed, 0 failed. `bash scripts/ui-03-token-gate.sh` → 18/18 PASS.

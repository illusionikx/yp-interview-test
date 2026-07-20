---
phase: 09-v3-0-foundations-semester-model-design-tokens-alerts-entry-pages
reviewed: 2026-07-17T06:29:56Z
depth: standard
files_reviewed: 27
files_reviewed_list:
  - app/Support/Semester.php
  - app/Exceptions/AttemptVanishedException.php
  - app/Models/Attempt.php
  - app/Http/Controllers/Student/AttemptController.php
  - app/View/Components/LandingLayout.php
  - resources/views/components/toast.blade.php
  - resources/views/components/confirm-modal.blade.php
  - resources/views/components/modal.blade.php
  - resources/views/auth/login.blade.php
  - resources/views/landing.blade.php
  - resources/views/layouts/landing.blade.php
  - resources/views/layouts/app.blade.php
  - resources/views/layouts/guest.blade.php
  - resources/views/layouts/navigation.blade.php
  - routes/web.php
  - tailwind.config.js
  - resources/css/app.css
  - config/app.php
  - phpunit.xml
  - scripts/ui-03-token-gate.sh
  - resources/views/lecturer/exams/show.blade.php
  - resources/views/lecturer/subjects/index.blade.php
  - resources/views/auth/forgot-password.blade.php
  - tests/Unit/SemesterTest.php
  - tests/Feature/AttemptNullGuardTest.php
  - tests/Feature/ToastTest.php
  - tests/Feature/NoNativeDialogTest.php
  - tests/Feature/LandingPageTest.php
  - tests/Feature/Auth/AuthenticationTest.php
  - tests/Feature/NavigationTest.php
findings:
  critical: 0
  warning: 1
  info: 1
  total: 2
status: findings
---

# Phase 9: Code Review Report

**Reviewed:** 2026-07-17T06:29:56Z
**Depth:** standard
**Files Reviewed:** 27 production/test files (plus 9 deletion-only view diffs spot-checked)
**Status:** findings (1 Warning, 1 Info — no Critical/Blocker findings)

## Summary

This phase is unusually tight. I traced every focus area named in the brief against the actual
code rather than the surrounding commentary, and each one held up:

- **T-09-01 (TOCTOU):** grepped every `Attempt::...lockForUpdate()->first()` call site in `app/`.
  There are exactly three: `Attempt::lockAndFinalize()` (guarded this phase), the second read in
  `AttemptController::answer()` (guarded this phase), and `AnswerGradeController.php:29` (the
  known, already-filed Phase 10 item — confirmed unguarded, correctly left alone). No other site
  exists. Both new guards throw `AttemptVanishedException` before touching `$this`/using `$locked`,
  and `Attempt::lockAndFinalize()` deliberately skips the in-memory sync on the vanished branch
  (correct — there is nothing authoritative to sync from).
- **T-09-02 (XSS):** `toast.blade.php` renders `{{ $status }}` and `{{ $error }}` exclusively —
  no `{!! !!}` anywhere in the component or elsewhere in the reviewed views (`grep -rl "{!!"
  resources/views` returns nothing). `ToastTest::test_the_toast_escapes_html_in_flash_text`
  actually asserts the escaped output, not just presence.
- **T-09-03 (native dialogs):** zero `confirm(`/`alert(` remain in `resources/views`; all three
  known call sites (`exams/show.blade.php` ×2, `subjects/index.blade.php` ×1) now dispatch
  `open-modal` and the paired `<x-confirm-modal>` calls `$refs.<name>Form.submit()`, which bypasses
  the `@submit.prevent` listener (native `.submit()` doesn't fire a `submit` event) — this is the
  correct, standard Breeze-style pattern, and `@csrf`/`@method('DELETE')` are still present inside
  every one of the three forms.
- **Semester math:** verified by hand against `forDate()`'s branch order, `ordinal()`'s formula
  (`year*2 + (2-number)`, confirmed NOT the inverted `year*2 + (number-1)` research flagged as
  wrong), the Sep/Feb rollover, the leap-year `endOfMonth()` use, and the August roll-forward. All
  correct; `SemesterTest` covers the rollover, the leap-year boundary, and the monotonic-with-
  `startsAt()` property test that would catch a formula regression regardless of which specific
  case is checked.
- **`AttemptVanishedException::render()`:** correctly branches JSON (autosave `answer()` path, and
  any `axios`-driven `submit()` call, since `bootstrap.js` sets `X-Requested-With` →
  `expectsJson()` true) vs. redirect+flash (real browser GET/POST page loads). Traced the client JS
  (`student/attempts/show.blade.php`) to confirm both `axios.post` call sites (autosave and
  timeout-triggered auto-submit) get the JSON branch.
- **Blade-directive-in-comment gotcha:** searched every HTML comment block across the reviewed
  views for a literal `@directive(` inside `<!-- -->`; found none. The one prior incident
  (`01c5ebb`, landing shell dark-toggle comment) was already fixed before this file set landed.
- **`dark:` on new tokens:** zero hits (`grep -rE "dark:(bg-brand|text-heading|...)" resources/views`
  returns nothing) — correct, tokens resolve via the `.dark`-flipped CSS custom properties.
- **Token coverage:** every semantic token class used in the reviewed markup (`bg-brand`,
  `bg-brand-strong`, `bg-neutral-primary-soft`, `bg-neutral-secondary-medium`, `border-brand`,
  `border-default`, `border-default-medium`, `placeholder:text-body`, `ring-brand(-medium|-soft)`,
  `text-body`, `text-fg-brand`, `text-heading`, `rounded-base`, `rounded-xs`, `shadow-xs`) is
  present in `tailwind.config.js`'s `theme.extend` and in the gate script's `TOKENS` list. No
  silent-no-CSS class found.
- **No new dependencies:** `git diff HEAD~30 -- package.json composer.json` is empty.
- **Deletion-only view diffs (9 lecturer/student views + `forgot-password.blade.php`):** spot-
  checked all of them; each is a clean removal of an inline `@if (session('status'/'error'))`
  banner (or, for `forgot-password.blade.php`, the `<x-auth-session-status>` include) with no
  dangling `@endif`, no orphaned markup, and no view left with zero flash surface — the layout-
  level `<x-toast>` now covers all of them. `grep` confirms `session('status')`/`session('error')`
  only remain in the 3 intentional Breeze sentinel views plus `toast.blade.php` itself.

The one substantive gap is a test-coverage hole around the confirm-modal's functional wiring (see
Warning below) — the implementation itself is correct (verified by reading), but nothing pins it
down against regression.

## Warnings

### WR-01: No automated test exercises the confirm-modal's actual submit wiring

**File:** `tests/Feature/NoNativeDialogTest.php:57-64`
**Issue:** `test_the_destructive_lecturer_forms_use_the_confirm_modal_component` only asserts the
literal substring `<x-confirm-modal` is present in `exams/show.blade.php` and
`subjects/index.blade.php`. It does not exercise the actual delete flow (a real HTTP DELETE
request reaching the controller) or assert that the paired form still carries `@csrf` and
`@method('DELETE')`, or that the modal's `x-on:click="$refs.<name>Form.submit()"` attribute is
wired to the correct `x-ref`. This phase's own stated risk is: "A modal that opens but never
submits silently breaks delete" — today that risk is verified correct by reading the source
(all three sites do preserve CSRF/method-spoofing and correct `$refs` targeting), but a future
edit that breaks the `x-ref`/`x-on:click` pairing (e.g. a copy-paste typo renaming
`deleteExamForm` in one place but not the other) would still pass every existing test:
`NoNativeDialogTest` only checks for the component tag's presence, and no Feature test posts to
`lecturer.exams.destroy` / `lecturer.exams.questions.destroy` / `lecturer.subjects.destroy` at
all (checked: no `assertDelete`/`->delete(route(...))`/`->post(route('lecturer.exams.destroy'...`
call exists anywhere in `tests/Feature` for these three routes).
**Fix:** Add a Feature test per destructive route that posts (with `_method=DELETE` + a valid
CSRF token, as the real form does) and asserts the record is actually removed — this is a
one-time investment that also verifies `AttemptPolicy`/ownership gating on the delete routes
themselves, which appears equally untested. Example:
```php
public function test_a_lecturer_can_delete_their_own_unpublished_exam(): void
{
    $lecturer = User::factory()->lecturer()->create();
    $exam = Exam::factory()->create(['author_id' => $lecturer->id, 'is_published' => false]);

    $this->actingAs($lecturer)
        ->delete(route('lecturer.exams.destroy', $exam))
        ->assertRedirect();

    $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
}
```

## Info

### IN-01: `<x-confirm-modal>` wiring is duplicated three times with no shared helper

**File:** `resources/views/lecturer/exams/show.blade.php:48-62,83-97`,
`resources/views/lecturer/subjects/index.blade.php:32-46`
**Issue:** The `<div x-data class="contents"><form x-ref="..." @submit.prevent="$dispatch(...)">
...</form><x-confirm-modal ... x-on:click="$refs.....submit()" /></div>` pattern is repeated
verbatim three times with only the ref name, modal name, and copy varying. Not a bug — the
Breeze-native pattern this mirrors (the profile "delete account" modal) also inlines this — but
worth flagging since Phase 10's INT-02/CLS-07 warnings are documented as reusing this same
component, which will make it four-plus call sites.
**Fix:** No action required now; consider a small Blade partial or a `<x-confirm-delete-form>`
wrapper if a 4th/5th call site lands in Phase 10, to avoid the ref-name/dispatch-name pairing
being retyped (and potentially mismatched) each time.

---

_Reviewed: 2026-07-17T06:29:56Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_

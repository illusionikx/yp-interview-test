---
status: testing
phase: 08-v2-0-features-enrollment-exam-availability-user-manuals
source: [08-VERIFICATION.md]
started: 2026-07-17T00:00:00Z
updated: 2026-07-17T00:00:00Z
note: >
  Deferred by the autonomous run at the user's explicit instruction ("handle decisions
  by yourself since I'll be away"). Automated verification is COMPLETE: 24/24 must-haves
  verified, 0 gaps, 294 tests passing, migrate:fresh --seed exit 0. The three items below
  are the only checks that genuinely require a human at a browser — they were NOT marked
  passed and were NOT dropped.
---

## Current Test

number: 1
name: AVL-05 — the beforeunload warning appears when leaving an in-progress attempt
expected: The browser's own native "Leave site?" confirmation dialog appears
awaiting: user response

## Tests

### 1. AVL-05 — warning appears on tab-close / navigate-away

expected: Start an exam attempt, click once anywhere on the page (browsers require a
"sticky activation" gesture before they honour beforeunload), then try to close the tab
or navigate away → the browser's native "Leave site?" confirmation appears.
why_human: PHPUnit executes no browser JS and cannot observe a native dialog. Laravel Dusk
is deliberately not installed (CLAUDE.md forbids new Composer packages for this). Code
inspection is complete and correct — named handler attached in `init()`
(`student/attempts/show.blade.php:298`), exactly one add/remove pair on the same reference.
result: [pending]

### 2. AVL-05 — NO warning on intentional submit or auto-submit

expected: Submit the exam via the confirm modal → no dialog on the redirect. Separately,
let the timer run out and auto-submit → no dialog on that redirect either.
why_human: Same limitation. `detachBeforeUnload()` is called before both navigations per
code inspection (`show.blade.php:211` `x-on:submit`, and `:364` inside `autoSubmit()`), but
only a real browser can confirm the dialog is actually suppressed.
result: [pending]

### 3. DEL-04 / DEL-05 — manual read-through as a non-technical reader

expected: Read the in-app student and lecturer manuals end-to-end while clicking the real
screens. Every instruction and quoted UI label matches what actually renders; no step
describes a screen state or click-path that doesn't exist.
why_human: The verifier grep-checked every UI label quoted in both manuals against the
shipped Blade views (Enroll, View Sections, Apply, Withdraw, Reject Student, View roster,
Create Section, Assign Lecturer, View result, …) — all matched verbatim — and confirmed the
"Viewing Your Results" click-path now exists (commit `78eb271`). Strong evidence, but
08-VALIDATION.md designates a human content review as the method for DEL-04/DEL-05.
result: [pending]

## Summary

total: 3
passed: 0
issues: 0
pending: 3
skipped: 0
blocked: 0

## Gaps

*(none — 0 gaps found; 24/24 must-haves verified against real code)*

---

**To run these:** `/gsd-verify-work 8` — it walks each item and closes the phase's UAT when they pass.

**Demo setup:** `php artisan migrate:fresh --seed`, then sign in as the seeded demo accounts
(password `password`). Item 3 needs both a student and a lecturer account.

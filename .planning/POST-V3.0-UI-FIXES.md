---
type: post-milestone-changelog
milestone: v3.0 (code-complete)
scope: UI/UX polish + bugfixes done during manual review
started: 2026-07-19
status: ongoing
note: >
  These changes were made as direct fixes while the user manually tested the
  v3.0 build, OUTSIDE the GSD phase workflow (no PLAN/SUMMARY artifacts). This
  file is the planning-side record. Each entry maps to a git commit; the full
  rationale is in the commit body. The full test suite (457 passing) is green at
  every commit.
---

# Post-v3.0 UI/UX Fixes

Recorded after the fact — the v3.0 milestone was code-complete (see
`v3.0-MILESTONE-AUDIT.md`) and these are polish/bugfixes surfaced during the
user's hands-on review. Listed newest-first.

## `fa6b8cc` — Tabbed class page
- Class page (`sections.show`) split into **Students** (roster) + **Settings**
  (edit form + delete) tabs; edit form extracted to `sections/_settings` partial,
  reused by the standalone edit page.
- Class lists show a single **View** into the class page (was "View roster" + "Edit").
- Student name → details modal (email, enrolled-since) carrying the reject action.
- Added the missing "Back to classes" button on the class page.

## `11c7ea3` — Exam-editor polish
- Per-question **Save** button appears only when that question is dirty (not on every question); "Discard" reloads.
- **Answer (option) move up/down** restored (client-side, saved on submit); Shuffle kept.
- **Two-column** question layout (number/reorder gutter + content), applied to the take-exam page too for consistency.

## `f903e8a` — All questions editable inline + one-click add
- Removed the per-question Edit toggle; every question renders its form open.
- One-click **Add question** (`quickStore`) appends a blank starter MCQ at the end (warn-and-void if attempted) and scrolls/focuses it.
- Option ordering moved into the form; `_form` field ids made unique per question.
- New test: `QuickAddQuestionTest`.

## `9481189` — Class creation via the subject page
- Global "Manage Sections" list: subject names link to the subject page; per-subject "Create Section" replaced by "Manage classes" → the subject page's Classes tab.

## `b8b554a` — Auth dark mode
- Dark-mode variants for forgot/reset/confirm-password + verify-email. (Password reset is Breeze built-in — kept, not removed.)

## `aab792f` — Exam subject lock + 419 + large seed
- Exam subject fixed at creation (create form locks it; editor shows it read-only; `UpdateExamRequest` drops `subject_id`).
- 419 page-expiry (CSRF mismatch) → friendly redirect with a "session expired" message.
- Seeder: large 15-question unpublished Mathematics exam for editor stress-testing.

## `cf08c20` — Subject Manage/Delete into the subject page
- Home subject name links to its page; Delete moved onto the subject page header; home row keeps a quick Edit.

## `90de168` — Flowbite theme pass + dark-mode gaps + assorted UX/behaviour
- Flowbite theming (buttons, nav, cards: border + padding, mobile edge padding).
- Dark-mode gaps: register, profile editor, inputs/labels, welcome-banner contrast.
- Removed redundant "Class enrollment" nav item.
- Student exam panel: two columns, disabled button for unavailable exams, default-hide coming-soon/closed behind a toggle, duration + deadline.
- Awaiting-grading → popup instead of navigating to an empty result page.
- Question/option reorder happens in place (optimistic PATCH), no full-page refresh.
- Exam timer derives from an absolute deadline (no freeze/desync on backgrounded tab).
- `beforeunload` "leave site" dialog no longer fires on an intentional submit.
- Login card horizontally centered.
- Seeder: broadened demo lecturer/student across 6 subjects; added coming-soon + closed Mathematics exams.

---

## Still outstanding (unchanged from v3.0 audit)
- The two deferred manual browser verifications (dark-mode walkthrough, `php artisan dusk`).
- The 🔴 pre-push blocker: GitLab PAT in old git history — rotate/revoke + scrub before any public push.
- The orphaned standalone `sections.edit` route (kept alive only for `BackButtonTest`; editing now lives in the class page's Settings tab).

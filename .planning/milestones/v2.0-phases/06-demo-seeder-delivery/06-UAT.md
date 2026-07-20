---
status: testing
phase: 6-demo-seeder-delivery
source: [06-VERIFICATION.md]
started: 2026-07-16
updated: 2026-07-16
---

## Current Test

number: 1
name: Student takes the seeded exam under the live timer
expected: |
  Logged in as student@example.com (password: password), the "Mathematics Midterm"
  exam is visible; starting it shows a live countdown that ticks down; answering the
  MCQ + open-text shows a "Saved" autosave indicator; Submit → confirmation page (no score).
awaiting: user response

## Tests

> All of these behaviors are already covered by the automated suite (176 tests) at the
> server level; this UAT is the visual/interaction confirmation of the client-side Alpine
> pieces (live countdown, autosave indicator, grading form) that a browser is needed to see.
> Run `php artisan migrate:fresh --seed` first, then `php artisan serve` (or Herd), and use the
> demo credentials from the README.

### 1. Student takes the exam (live countdown + autosave + submit)
expected: student@example.com sees "Mathematics Midterm", starts it, the countdown ticks, MCQ/open answers autosave ("Saved"), Submit → confirmation (no score shown).
result: [pending]

### 2. Class-scoped visibility (no IDOR)
expected: student3@example.com (Advanced Classroom) does NOT see "Mathematics Midterm" in their list, and opening its URL directly is denied (403). student@example.com (Demo Classroom) does see it.
result: [pending]

### 3. Lecturer grades open-text and result flips to graded
expected: lecturer@example.com opens the exam's results, sees student2@example.com's attempt as "submitted" (MCQ auto-graded, open-text pending), grades the open-text answer (0..points), the attempt flips to "graded", and the total score appears.
result: [pending]

### 4. Student sees their graded result (no answer key)
expected: after grading, student2@example.com sees their result — total score + per-question breakdown (their answer + ✓/✗ + points), and the breakdown does NOT reveal the correct option for a wrong MCQ.
result: [pending]

### 5. README clean-clone accuracy (optional)
expected: following README.md from a fresh clone (create the `yp-student-exam` MySQL db, install, `migrate:fresh --seed`, run) stands the app up and the documented demo credentials log in.
result: [pending]

## Summary

total: 5
passed: 0
issues: 0
pending: 5
skipped: 0
blocked: 0

## Gaps

(none — all automated must-haves passed; these are human/browser confirmations of already-tested behavior)

---
phase: 13-student-exam-experience-class-page-take-exam
plan: 01
subsystem: ui
tags: [laravel-blade, tailwind, alpine, student-portal, class-page]

# Dependency graph
requires:
  - phase: 11-student-navigation-subject-browse-enrollment
    provides: "student.home's per-subject 'Open class page' link and its enrolled-subjects-by-semester query shape"
  - phase: 12-lecturer-workspace-class-management-exam-editor-grading
    provides: "Exam::availabilityState()/x-status-pill status-marker pattern, reused verbatim on the student side"
provides:
  - "Student\\ClassPageController@show(Subject) — subject-scoped, enrollment-guarded class page"
  - "student.subjects.class named route"
  - "resources/views/student/subjects/class.blade.php — subject detail card + separate exam-list card"
  - "TAK-07 fix: taken/graded marker is a real link to the result (v2.0's unreachable-result defect closed)"
  - "TAK-08: Start control disabled once the student holds an attempt for that exam"
affects: [13-02-take-exam-flow]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Class page reuses the exams/index status-pill + result-link idiom verbatim rather than introducing a second implementation"
    - "Enrollment-derived 403 gate computed the same way as Student\\ExamController@show (Section::whereHas('enrollments', enrolled-by-this-user)->first())"

key-files:
  created:
    - app/Http/Controllers/Student/ClassPageController.php
    - resources/views/student/subjects/class.blade.php
    - tests/Feature/Student/ClassPageTest.php
  modified:
    - routes/student.php
    - resources/views/student/home.blade.php
    - tests/Feature/Student/SubjectListTest.php

key-decisions:
  - "Non-enrolled students are 403'd via abort_unless() on the resolved enrolled Section, exactly mirroring Student\\ExamController@show's idiom, rather than a new Policy."
  - "Exam list is read exclusively through Exam::visibleTo() — no availability filtering, no re-derivation of is_published/enrollment conditions in the controller or view."

patterns-established:
  - "Subject-scoped student pages resolve the acting student's own enrolled Section via the same Section::where(subject_id)->whereHas('enrollments', enrolled) query used by ExamController@show, rather than inventing a new query shape per controller."

requirements-completed: [TAK-07, TAK-08]

# Metrics
duration: 5min
completed: 2026-07-18
status: complete
---

# Phase 13 Plan 01: Student class page Summary

**Student `ClassPageController` + `student.subjects.class` route reusing the exams/index status-pill and result-link idiom, with a real link (not a bare label) for taken/graded exams and a disabled Start once an attempt exists — closing v2.0's unreachable-result defect.**

## Performance

- **Duration:** 5 min
- **Started:** 2026-07-18T12:16:02Z
- **Completed:** 2026-07-18T12:20:28Z
- **Tasks:** 3
- **Files modified:** 6 (3 created, 3 modified)

## Accomplishments
- New subject-scoped class page (`student.subjects.class`) reached from the Phase 11 "Open class page" link, showing subject detail plus a separate exam-list card.
- TAK-07: exams already taken or graded show a real anchor to `student.attempts.result`, never a bare "Taken"/"Graded" label.
- TAK-08: the Start control is rendered disabled once the student holds any attempt for that exam, while the server-side single-attempt constraint (`attempts.unique(exam_id,user_id)` + `AttemptController@store`'s `firstOrCreate`) stays untouched and authoritative.
- Non-enrolled access is 403'd at the controller (T-13-01), and the exam list is read exclusively through `Exam::visibleTo()` with no re-derivation of visibility conditions.

## Task Commits

Each task was committed atomically:

1. **Task 1: ClassPageController + route** - `0f4a1af` (feat)
2. **Task 2: Class page view + retarget home links** - `a3f2c29` (feat)
3. **Task 3: Feature tests (ClassPageTest + SubjectListTest retarget)** - `49b286d` (test)

**Plan metadata:** committed separately below.

## Files Created/Modified
- `app/Http/Controllers/Student/ClassPageController.php` - New controller: 403-gates non-enrolled students, builds the subject-scoped exam list via `Exam::visibleTo()` with the acting student's own attempt eager-loaded, loads `subject.lecturers`.
- `routes/student.php` - Registers `GET subjects/{subject}/class` as `student.subjects.class`.
- `resources/views/student/subjects/class.blade.php` - Subject detail card (code/name, lecturer(s), enrolled class name) + a separate exam-list card (status pill per exam, disabled-once-attempted Start, taken/graded result link, empty state).
- `resources/views/student/home.blade.php` - Both "Open class page" anchors retargeted from the interim `student.subjects.show` to `student.subjects.class`; stale "Interim target" comments removed.
- `tests/Feature/Student/ClassPageTest.php` - New: subject detail + exam list render, availability status labels, taken/graded result links, disabled-vs-enabled Start, non-enrolled 403, single-attempt regression guard via a crafted second `attempts.store` POST.
- `tests/Feature/Student/SubjectListTest.php` - Both home-page link assertions retargeted from `student.subjects.show` to `student.subjects.class`.

## Decisions Made
- Non-enrolled students are 403'd via `abort_unless()` on the resolved enrolled `Section`, mirroring `Student\ExamController@show`'s existing idiom, rather than introducing a new Policy for a single-controller check.
- The exam list is read exclusively through `Exam::visibleTo()` — deliberately no availability filtering in the query (ENR-08: listed with a status label, never hidden) and no re-derivation of `is_published`/enrollment conditions anywhere else in the controller or view.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The class page's "Start" link/Resume/result-link scaffolding is now the live entry point into the take-exam flow (13-02), which can build on `student.exams.show` and `student.attempts.show`/`student.attempts.result` unchanged.
- Full test suite: 434 passed (428 baseline + 6 new `ClassPageTest` cases), 0 failures.

---
*Phase: 13-student-exam-experience-class-page-take-exam*
*Completed: 2026-07-18*

## Self-Check: PASSED

All created files verified on disk; all task commit hashes (0f4a1af, a3f2c29, 49b286d) verified in git log.

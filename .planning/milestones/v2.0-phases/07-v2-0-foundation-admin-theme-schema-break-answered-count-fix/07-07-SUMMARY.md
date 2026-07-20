---
phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix
plan: 07
subsystem: database
tags: [laravel, eloquent, seeder, phpunit, enum-cast-pivot, enrollment]

# Dependency graph
requires:
  - phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix (plan 03)
    provides: Section/Enrollment models, EnrollmentStatus enum, Exam::scopeVisibleTo() enrollment-driven rewrite
  - phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix (plan 04)
    provides: SectionController/SubjectLecturerController, AssignExamRequest section_ids contract, exam_section route wiring
provides:
  - DatabaseSeeder rewritten for the section/enrollment demo graph (subject_user, sections, enrollments, exam_section)
  - DatabaseSeederTest GREEN against the v2.0 schema (DEL-03)
  - Lecturer/grading test suite swept off the dropped Classroom shape onto Section::factory() + enrollments()->attach()
affects: [07-08 (student-side test sweep + full-suite phase gate)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Seeder-time enrollment write via syncWithoutDetaching([$id => ['status' => EnrollmentStatus::Enrolled]]) — pivot enum instances bind directly through Laravel's BackedEnum query-binding support, no ->value needed"
    - "Test fixture idiom: Section::factory()->create() + exam->sections()->sync([...]) + section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]) replaces the old Classroom::factory()+classroom_id fixture shape"

key-files:
  created: []
  modified:
    - database/seeders/DatabaseSeeder.php
    - tests/Feature/Grading/AttemptGraderTest.php
    - tests/Feature/Lecturer/GradeAnswerTest.php
    - tests/Feature/Lecturer/Phase5ReviewFixesTest.php
    - tests/Feature/Lecturer/ResultTest.php
    - tests/Feature/Lecturer/ExamAssignmentTest.php
  deleted:
    - tests/Feature/Lecturer/ClassroomControllerTest.php
    - tests/Feature/Lecturer/ClassroomRosterTest.php
    - tests/Feature/Lecturer/ClassroomSubjectLinkageTest.php

key-decisions:
  - "Seeded a second Mathematics section (sequence 2) with a withdrawn student3 enrollment, beyond the plan's minimum ask, so the demo graph shows more than one enrollment state (enrolled + withdrawn) per the wave note's 'sample enrollments spanning states' framing."
  - "ExamAssignmentTest fully rewritten (not patched) since every method's fixture and assertions used the old classroom_ids/exam_classroom shape end to end."

patterns-established: []

requirements-completed: [DEL-03, ENR-08]

# Metrics
duration: ~15min
completed: 2026-07-16
status: complete
---

# Phase 7 Plan 7: Demo Seeder Rewrite & Lecturer/Root Test Sweep Summary

**DatabaseSeeder rewritten for the section/enrollment demo graph (subject_user + 2 sections + enrollment-state-spanning enrollments + exam_section), and the lecturer/grading test suite swept off the dropped Classroom shape onto Section::factory() + enrollments()->attach() fixtures.**

## Performance

- **Duration:** ~15 min
- **Completed:** 2026-07-16T15:31:19+08:00
- **Tasks:** 2
- **Files modified:** 9 (1 seeder rewrite, 5 tests swept, 3 tests deleted)

## Accomplishments
- `DatabaseSeeder::run()` fully rewritten: lecturer assigned to Mathematics via `subject_user`, two `2026-2-{1,2}` Mathematics sections seeded, student/student2 enrolled in the first section, student3 left un-enrolled there (ENR-08 denial demo) and given a withdrawn enrollment on the second section for state variety, and the demo exam assigned to the first section only via `exam_section`.
- `php artisan migrate:fresh --seed` boots clean end to end (exit 0) and `DatabaseSeederTest` (2 tests, 25 assertions) is GREEN — DEL-03 satisfied.
- Deleted `ClassroomControllerTest`, `ClassroomRosterTest`, `ClassroomSubjectLinkageTest` (dropped-feature tests, superseded by 07-04's `SectionControllerTest`/`SubjectLecturerTest`).
- Swept `AttemptGraderTest`, `GradeAnswerTest`, `Phase5ReviewFixesTest`, `Lecturer/ResultTest`, `ExamAssignmentTest` onto the section/enrollment fixture idiom — no remaining `Classroom`/`classroom_id`/`exam_classroom` references in any of the plan's declared `files_modified`.
- `php artisan test --filter=Grading --filter=Lecturer` GREEN (97 passed, 1 failed) — the single failure is `Student\ExamAccessTest::test_a_lecturer_is_forbidden_from_the_student_exam_routes`, which the shared `--filter` regex incidentally matched via the substring "lecturer" in its test method name; the file itself is untouched, still references `Classroom` (7 occurrences), and is explicitly out of this plan's scope per the wave note ("STUDENT-side test sweep is 07-08's scope — leave those").

## Task Commits

Each task was committed atomically:

1. **Task 1: Rewrite DatabaseSeeder for the section/enrollment demo graph** - `8cf2c2f` (feat)
2. **Task 2: Sweep lecturer/root tests to the section/enrollment shape; delete dropped-feature tests** - `65e9c4b` (test)

## Files Created/Modified
- `database/seeders/DatabaseSeeder.php` - full rewrite: subject_user assignment, seedSections() (2 Mathematics sections), enrollment writes spanning Enrolled/Withdrawn, seedExam() takes a Section and syncs exam_section, seedDemoAttempt() unchanged aside from classroom decoupling
- `tests/Feature/Grading/AttemptGraderTest.php` - fixture() swapped Classroom+classroom_id for Section::factory()+enrollments()->attach()
- `tests/Feature/Lecturer/GradeAnswerTest.php` - openTextFixture() swept to Section/enrollments
- `tests/Feature/Lecturer/Phase5ReviewFixesTest.php` - test_an_attempted_exam_cannot_be_unpublished() swept to Section/enrollments
- `tests/Feature/Lecturer/ResultTest.php` - both test methods swept to Section/enrollments (syncWithoutDetaching for two students in the index test)
- `tests/Feature/Lecturer/ExamAssignmentTest.php` - full rewrite: classroom_ids -> section_ids, exam_classroom -> exam_section assertions, Classroom::factory() -> Section::factory()
- `tests/Feature/Lecturer/ClassroomControllerTest.php` - deleted (superseded by SectionControllerTest)
- `tests/Feature/Lecturer/ClassroomRosterTest.php` - deleted (roster mechanism dropped, Pitfall 4)
- `tests/Feature/Lecturer/ClassroomSubjectLinkageTest.php` - deleted (classroom_subject pivot dropped; subject_user covered by SubjectLecturerTest)

## Decisions Made
- Added a withdrawn enrollment sample (student3 on the second section) beyond the plan's stated minimum, so the seeded demo graph demonstrates more than the single "Enrolled" state — matches the wave note's "sample enrollments spanning states" framing without adding any new seeded user/section.
- Kept `ExamAssignmentTest` as a full-file rewrite rather than incremental edits, since every one of its 5 methods needed both fixture and assertion changes (classroom_ids/exam_classroom throughout).

## Deviations from Plan

None - plan executed exactly as written. The one test-suite discrepancy (`Student\ExamAccessTest` appearing in the `--filter=Grading --filter=Lecturer` run) is not a deviation from this plan's work — that file was never in this plan's `files_modified` list, is unmodified, and its appearance is solely an artifact of PHPUnit's `--filter` regex matching the word "lecturer" inside an unrelated Student-side test method name. It is explicitly deferred to 07-08 per the wave note.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- The demo graph and lecturer/grading/assignment test suites are fully green against the v2.0 section/enrollment schema.
- 07-08 can now sweep the remaining Student-side tests (`AttemptAnswerTest`, `AttemptPolicyTest`, `AttemptShowTest`, `AttemptStartTest`, `AttemptSubmitTest`, `ExamAccessTest`, `ExamIndexTest`, `Phase4ReviewFixesTest`, `Student/ResultTest`) and run the full-suite phase gate.
- No blockers.

---
*Phase: 07-v2-0-foundation-admin-theme-schema-break-answered-count-fix*
*Completed: 2026-07-16*

## Self-Check: PASSED

All modified/created files verified present on disk (`database/seeders/DatabaseSeeder.php`, the 5 swept test files, this SUMMARY.md); all 3 deleted test files verified absent. Both task commits (`8cf2c2f`, `65e9c4b`) verified present in git log.

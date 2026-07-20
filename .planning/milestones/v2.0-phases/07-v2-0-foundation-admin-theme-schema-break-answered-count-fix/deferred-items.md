# Deferred Items

Out-of-scope discoveries logged during execution, per the executor's scope-boundary
rule (only auto-fix issues directly caused by the current task's changes).

## From 07-04 (Lecturer backend for the schema slice)

The following pre-existing test files already referenced `App\Models\Classroom`
(deleted in 07-03) and were already RED before 07-04 started. They are explicitly
out of scope for 07-04 — 07-RESEARCH.md's Runtime State Inventory lists them under
the phase-wide 26-file rename sweep, and the phase plan notes "the remaining test
sweep land[s] in 07-07":

- `tests/Feature/Lecturer/ClassroomControllerTest.php` — superseded by
  `SectionControllerTest.php` (this plan); should be deleted, not rewritten, in
  07-07's test sweep.
- `tests/Feature/Lecturer/ClassroomRosterTest.php` — superseded by nothing this
  phase (the v1 roster mechanism was deleted outright per Pitfall 4); should be
  deleted in 07-07.
- `tests/Feature/Lecturer/ClassroomSubjectLinkageTest.php` — superseded by
  `SubjectLecturerTest.php` (this plan); should be deleted in 07-07.
- `tests/Feature/Lecturer/ExamAssignmentTest.php` — still references
  `App\Models\Classroom` and `classroom_ids`; needs a rewrite to `Section`/
  `section_ids` to match the `AssignExamRequest`/`ExamAssignmentController`
  changes landed in this plan's Task 3.

Also still pending (07-05/07-06/07-07 scope, not touched by 07-04):
- `resources/views/lecturer/exams/show.blade.php` references `$classrooms` and
  `$exam->classrooms` — both already broken since 07-03 renamed `Exam::classrooms()`
  to `Exam::sections()`; `ExamController::show()` (this plan) now passes `$sections`
  instead of `$classrooms`, so the view still needs its own reskin/rename pass
  (07-05 scope) before `lecturer.exams.show` renders again.
- `resources/views/lecturer/classrooms/{create,edit,index}.blade.php` — not yet
  renamed to `resources/views/lecturer/sections/*` (07-05 scope); `SectionController`
  in this plan already points at the new `lecturer.sections.*` view names, so those
  views do not exist yet and `index()/create()/edit()` will 500 until 07-05 lands.
- `database/seeders/DatabaseSeeder.php` — still seeds the v1 `Classroom`/
  `classroom_id`/`exam_classroom` shape (07-07 scope per the plan's explicit
  wave-sequencing note: "DatabaseSeederTest remains RED until 07-07").

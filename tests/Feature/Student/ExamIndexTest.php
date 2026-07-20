<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// CR-02 tests below live in this class rather than a new file: they
// exercise the SAME ExamController@index endpoint as every other test
// here, just asserting the added "In-progress attempts" (Resume exam)
// section instead of the main visible-exams list.

class ExamIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a published exam assigned to a section the given student is
     * actively enrolled in — the standard visible-exam fixture.
     *
     * Same-subject fixture discipline (10-03, factory trap): ExamFactory and
     * SectionFactory each mint their own Subject independently, so the exam
     * is created first and the section's subject_id is pinned to it — never
     * relies on two independent Subject::factory() calls agreeing by
     * accident.
     */
    private function visibleExamFor(User $student): Exam
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        return $exam;
    }

    public function test_a_submitted_attempt_gets_a_result_link_on_the_exam_list(): void
    {
        $student = User::factory()->student()->create();
        $exam = $this->visibleExamFor($student);
        $attempt = Attempt::factory()->for($exam)->for($student)->create(['status' => 'submitted']);

        $response = $this->actingAs($student)->get(route('student.exams.index'));

        $response->assertOk();
        $response->assertSee(route('student.attempts.result', $attempt));
    }

    public function test_an_exam_with_no_attempt_has_no_result_link(): void
    {
        $student = User::factory()->student()->create();
        $this->visibleExamFor($student);

        $response = $this->actingAs($student)->get(route('student.exams.index'));

        $response->assertOk();
        $response->assertDontSee('View result');
    }

    public function test_an_in_progress_attempt_gets_no_result_link(): void
    {
        $student = User::factory()->student()->create();
        $exam = $this->visibleExamFor($student);
        Attempt::factory()->for($exam)->for($student)->create(['status' => 'in_progress']);

        $response = $this->actingAs($student)->get(route('student.exams.index'));

        $response->assertOk();
        $response->assertDontSee('View result');
    }

    public function test_the_list_never_links_another_students_attempt(): void
    {
        $student = User::factory()->student()->create();
        $exam = $this->visibleExamFor($student);

        $other = User::factory()->student()->create();
        $otherAttempt = Attempt::factory()->for($exam)->for($other)->create(['status' => 'graded']);

        $response = $this->actingAs($student)->get(route('student.exams.index'));

        $response->assertOk();
        $response->assertDontSee(route('student.attempts.result', $otherAttempt));
        $response->assertDontSee('View result');
    }

    public function test_a_student_sees_a_published_exam_assigned_to_their_enrolled_section(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $response = $this->actingAs($student)->get(route('student.exams.index'));

        $response->assertOk();
        $response->assertSee($exam->title);
    }

    public function test_index_excludes_an_unpublished_but_assigned_exam(): void
    {
        $exam = Exam::factory()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $response = $this->actingAs($student)->get(route('student.exams.index'));

        $response->assertOk();
        $response->assertDontSee($exam->title);
    }

    /**
     * CLS-05's same-subject entitlement (Phase 10, D-1), list surface —
     * the twin of ExamAccessTest's inverted direct-access method. Once
     * assignment is subject-derived, a student in ANY section of the
     * exam's own subject sees the exam on the list; "a different section
     * is excluded" was precisely the per-exam-assignment behavior D-1
     * removes. Inverted here, not deleted, so CLS-05 keeps real list
     * coverage instead of silently collapsing into a second copy of
     * CrossSubjectVisibilityTest's cross-SUBJECT denial (INT-04). The
     * `assertSame` guard is load-bearing — see ExamAccessTest's twin.
     */
    public function test_index_includes_a_published_exam_for_any_section_of_the_same_subject(): void
    {
        $exam = Exam::factory()->published()->create();
        // sequence pinned explicitly (not left to factory default 1 on both):
        // two sections sharing a subject_id can otherwise collide on the
        // sections(subject_id, year, semester, sequence) unique constraint
        // when the random year/semester happen to match — flaky, not
        // behavior-neutral. This is fixture hygiene only; it does not
        // change the test's expected outcome.
        $sectionA = Section::factory()->create(['subject_id' => $exam->subject_id, 'sequence' => 1]);
        $sectionB = Section::factory()->create(['subject_id' => $exam->subject_id, 'sequence' => 2]);

        $this->assertSame(
            $sectionA->subject_id,
            $sectionB->subject_id,
            'Both sections must share the exam\'s subject — this test is CLS-05\'s same-subject entitlement, not INT-04\'s cross-subject denial.'
        );

        $student = User::factory()->student()->create();
        $sectionB->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $response = $this->actingAs($student)->get(route('student.exams.index'));

        $response->assertOk();
        $response->assertSee($exam->title);
    }

    public function test_a_student_with_no_enrollment_sees_an_empty_index(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.exams.index'));

        $response->assertOk();
        $response->assertDontSee($exam->title);
    }

    /**
     * CR-02 — once a mid-attempt withdrawal drops the exam out of
     * Exam::visibleTo(), the exam disappears from the main list, but the
     * student's own in-progress attempt must still be reachable via the
     * "Resume exam" entry (ownership-driven, not visibility-driven).
     */
    public function test_resume_exam_link_appears_for_an_orphaned_in_progress_attempt_after_withdrawal(): void
    {
        $student = User::factory()->student()->create();
        $exam = $this->visibleExamFor($student);
        $attempt = Attempt::factory()->for($exam)->for($student)->create(['status' => 'in_progress']);

        $exam->subject->sections()->first()->enrollments()->updateExistingPivot(
            $student->id,
            ['status' => EnrollmentStatus::Withdrawn]
        );

        $response = $this->actingAs($student)->get(route('student.exams.index'));

        $response->assertOk();
        // No longer reachable via the main visible-exams list link (the
        // exam title still legitimately appears inside the "Resume exam:"
        // text below, so assert on the specific route instead of the title).
        $response->assertDontSee(route('student.exams.show', $exam));
        $response->assertSee(route('student.attempts.show', $attempt));
        $response->assertSee('Resume exam');
    }

    /**
     * CR-02 negative control — the same orphaned-attempt surfacing must
     * stay strictly ownership-scoped: another student's in-progress
     * attempt (even one similarly orphaned by rejection) must never
     * appear on this student's index.
     */
    public function test_resume_exam_link_never_surfaces_another_students_in_progress_attempt(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $exam = $this->visibleExamFor($other);
        $otherAttempt = Attempt::factory()->for($exam)->for($other)->create(['status' => 'in_progress']);

        $exam->subject->sections()->first()->enrollments()->updateExistingPivot(
            $other->id,
            ['status' => EnrollmentStatus::Rejected]
        );

        $response = $this->actingAs($student)->get(route('student.exams.index'));

        $response->assertOk();
        $response->assertDontSee(route('student.attempts.show', $otherAttempt));
        $response->assertDontSee('Resume exam');
    }
}

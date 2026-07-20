<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Exam;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamAccessTest extends TestCase
{
    use RefreshDatabase;

    // Same-subject fixture discipline (10-03, factory trap): ExamFactory and
    // SectionFactory each mint their own Subject independently, so every
    // fixture below builds the exam first, then pins the section's
    // subject_id to the exam's — never relies on two independent
    // Subject::factory() calls agreeing by accident.

    public function test_a_student_enrolled_in_the_assigned_section_can_view_the_published_exam(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertOk();
    }

    /**
     * CLS-05's same-subject entitlement (Phase 10, D-1): once assignment
     * is subject-derived, a student in ANY section of the exam's own
     * subject is entitled to the exam — "a different section is denied"
     * was precisely the per-exam-assignment behavior D-1 removes. This
     * method used to assert the opposite (`assertForbidden`); it is
     * inverted here, not deleted, so CLS-05 keeps real coverage instead
     * of silently collapsing into a second copy of
     * CrossSubjectVisibilityTest's cross-SUBJECT denial (INT-04). The
     * `assertSame` guard below is load-bearing: it is what stops this
     * method from being satisfiable by two sections landing in different
     * subjects by accident.
     */
    public function test_a_student_enrolled_in_a_different_section_of_the_same_subject_can_view_the_exam(): void
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

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertOk();
    }

    public function test_an_unpublished_but_assigned_exam_is_forbidden(): void
    {
        $exam = Exam::factory()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertForbidden();
    }

    public function test_a_student_with_a_withdrawn_enrollment_is_forbidden_direct_access(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Withdrawn]);

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertForbidden();
    }

    public function test_a_student_with_a_rejected_enrollment_is_forbidden_direct_access(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Rejected]);

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertForbidden();
    }

    public function test_a_student_with_no_enrollment_is_forbidden_direct_access(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertForbidden();
    }

    public function test_a_lecturer_is_forbidden_from_the_student_exam_routes(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $lecturer = User::factory()->lecturer()->create();

        $indexResponse = $this->actingAs($lecturer)->get(route('student.exams.index'));
        $showResponse = $this->actingAs($lecturer)->get(route('student.exams.show', $exam));

        $indexResponse->assertForbidden();
        $showResponse->assertForbidden();
    }
}

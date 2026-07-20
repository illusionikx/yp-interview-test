<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Exam;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 8, Wave 0) — AVL-02: the pre-start page must show duration,
 * description, the availability window, the availability state pill, the
 * student's own enrolled section, and a Proceed/Back action pair; it must
 * remain reachable while the exam is out of its availability window (only
 * the START ACTION is gated by AVL-03, never this page).
 *
 * This file is genuinely NEW. 08-VALIDATION.md's Wave 0 Requirements table
 * said "extend existing", but no tests/Feature/Student/ExamShowTest.php
 * existed before this plan — that document should be corrected (see
 * 08-02-SUMMARY.md).
 *
 * Expected RED until student/exams/show.blade.php gains these elements
 * (08-06/08-07).
 */
class ExamShowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Section}
     */
    private function enrolledStudentFor(Exam $exam): array
    {
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        return [$student, $section];
    }

    public function test_the_page_is_reachable_for_an_exam_not_yet_available(): void
    {
        $exam = Exam::factory()->published()->opening()->create();
        [$student] = $this->enrolledStudentFor($exam);

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertOk();
    }

    public function test_the_page_is_reachable_for_an_exam_that_has_closed(): void
    {
        $exam = Exam::factory()->published()->closed()->create();
        [$student] = $this->enrolledStudentFor($exam);

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertOk();
    }

    public function test_the_page_shows_the_exams_duration(): void
    {
        $exam = Exam::factory()->published()->available()->create(['duration_minutes' => 45]);
        [$student] = $this->enrolledStudentFor($exam);

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertSee('45');
    }

    public function test_the_page_shows_the_exams_description_when_present(): void
    {
        $exam = Exam::factory()->published()->available()->create(['description' => 'Covers chapters 1 through 5.']);
        [$student] = $this->enrolledStudentFor($exam);

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertSee('Covers chapters 1 through 5.');
    }

    public function test_the_page_shows_the_availability_window(): void
    {
        $exam = Exam::factory()->published()->create([
            'available_from' => now()->subDay(),
            'available_until' => now()->addDays(7),
        ]);
        [$student] = $this->enrolledStudentFor($exam);

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertSee($exam->available_from->format('M j, Y g:ia'));
        $response->assertSee($exam->available_until->format('M j, Y g:ia'));
    }

    /**
     * Asserts the pill's color-class arm for the 'opening' keyword rather
     * than exact label text (the date-bearing label wording is not locked
     * by 08-UI-SPEC.md), matching the extended x-status-pill match() table.
     */
    public function test_the_page_shows_the_availability_state_pill_for_an_opening_exam(): void
    {
        $exam = Exam::factory()->published()->opening()->create();
        [$student] = $this->enrolledStudentFor($exam);

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertSee('bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300', false);
    }

    public function test_the_page_shows_the_students_enrolled_section_name(): void
    {
        $exam = Exam::factory()->published()->available()->create();
        [$student, $section] = $this->enrolledStudentFor($exam);

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertSee($section->name);
    }

    public function test_the_page_offers_a_proceed_action_and_a_back_action(): void
    {
        $exam = Exam::factory()->published()->available()->create();
        [$student] = $this->enrolledStudentFor($exam);

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertSee('Proceed');
        $response->assertSee(route('student.exams.index'), false);
    }

    public function test_a_non_enrolled_student_still_gets_403(): void
    {
        $exam = Exam::factory()->published()->available()->create();
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.exams.show', $exam));

        $response->assertForbidden();
    }

    public function test_the_page_renders_the_red_refusal_flash_when_session_error_is_set(): void
    {
        $exam = Exam::factory()->published()->available()->create();
        [$student] = $this->enrolledStudentFor($exam);

        $response = $this->actingAs($student)
            ->withSession(['error' => 'This exam is not available yet. It opens Jan 1, 2027 12:00pm.'])
            ->get(route('student.exams.show', $exam));

        $response->assertSee('This exam is not available yet. It opens Jan 1, 2027 12:00pm.');
    }
}

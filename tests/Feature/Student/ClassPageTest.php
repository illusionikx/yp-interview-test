<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TAK-07/TAK-08: the student class page — subject detail + a separate
 * exam-list card, status markers, a taken/graded marker that is a real
 * link to the result (v2.0's unreachable-result defect), and a Start
 * button disabled once the student already has an attempt. The final
 * test proves the disabled button is UX only — the server-side
 * single-attempt constraint stays authoritative.
 */
class ClassPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Enroll $student in a section of $subject (creating the section if
     * not supplied) and return the enrolled section.
     */
    private function enroll(User $student, Subject $subject, array $sectionAttributes = []): Section
    {
        $section = Section::factory()->create(array_merge(['subject_id' => $subject->id], $sectionAttributes));
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        return $section;
    }

    public function test_class_page_shows_subject_detail_and_lists_a_visible_exam(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create(['name' => 'Discrete Mathematics']);
        $this->enroll($student, $subject);
        $exam = Exam::factory()->published()->available()->create(['subject_id' => $subject->id, 'title' => 'Midterm Exam']);

        $response = $this->actingAs($student)->get(route('student.subjects.class', $subject));

        $response->assertOk();
        $response->assertSee('Discrete Mathematics');
        $response->assertSee('Midterm Exam');
    }

    public function test_class_page_marks_availability_state_per_exam(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();
        $this->enroll($student, $subject);

        Exam::factory()->published()->opening()->create(['subject_id' => $subject->id, 'title' => 'Not Yet Open Exam']);
        Exam::factory()->published()->closed()->create(['subject_id' => $subject->id, 'title' => 'Closed Exam']);

        $response = $this->actingAs($student)->get(route('student.subjects.class', $subject));

        $response->assertOk();
        $response->assertSeeText('Closed');
        $response->assertSee('Opens', false);
    }

    public function test_submitted_exam_opens_awaiting_grading_popup_and_graded_exam_links_to_result(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();
        $this->enroll($student, $subject);

        $submittedExam = Exam::factory()->published()->available()->create(['subject_id' => $subject->id]);
        $submittedAttempt = Attempt::factory()->for($submittedExam)->for($student)->submitted()->create();

        $gradedExam = Exam::factory()->published()->available()->create(['subject_id' => $subject->id]);
        $gradedAttempt = Attempt::factory()->for($gradedExam)->for($student)->graded()->create();

        $response = $this->actingAs($student)->get(route('student.subjects.class', $subject));

        $response->assertOk();
        // Graded — still a link to the result (there's a score to see).
        $response->assertSee(route('student.attempts.result', $gradedAttempt), false);
        $response->assertSeeText('View result');
        // Submitted but not yet graded — a popup explains the state instead of
        // navigating to an empty result page (issue #6). No result link is rendered.
        $response->assertSeeText('Awaiting grading');
        $response->assertSee('data-modal-target="awaiting-grading-modal"', false);
        $response->assertDontSee(route('student.attempts.result', $submittedAttempt), false);
    }

    public function test_an_attempted_exam_that_is_later_unpublished_keeps_its_result_link(): void
    {
        // 13-REVIEW WR-01: CLS-06 lets a lecturer unpublish an exam even after
        // students have attempted it. Such an exam drops out of Exam::visibleTo(),
        // but the student's own result must stay reachable from the class page
        // (this page is now home.blade.php's primary nav target) — TAK-07's whole
        // point. The ownership-driven fallback surfaces it.
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();
        $this->enroll($student, $subject);

        $exam = Exam::factory()->available()->create([
            'subject_id' => $subject->id,
            'is_published' => false,
            'title' => 'Unpublished Attempted Exam',
        ]);
        $attempt = Attempt::factory()->for($exam)->for($student)->graded()->create();

        $response = $this->actingAs($student)->get(route('student.subjects.class', $subject));

        $response->assertOk();
        $response->assertSee('Unpublished Attempted Exam');
        $response->assertSee(route('student.attempts.result', $attempt), false);
    }

    public function test_start_is_disabled_once_attempted_but_enabled_for_an_unattempted_exam(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();
        $this->enroll($student, $subject);

        $attemptedExam = Exam::factory()->published()->available()->create(['subject_id' => $subject->id]);
        Attempt::factory()->for($attemptedExam)->for($student)->create(['status' => 'in_progress']);

        $freshExam = Exam::factory()->published()->available()->create(['subject_id' => $subject->id]);

        $response = $this->actingAs($student)->get(route('student.subjects.class', $subject));

        $response->assertOk();
        // The unattempted exam's Start is an enabled link to the exam page.
        $response->assertSee(route('student.exams.show', $freshExam), false);
        // The attempted exam's Start is rendered disabled and offers Resume
        // instead of a fresh start link.
        $response->assertDontSee(route('student.exams.show', $attemptedExam), false);
        $response->assertSeeText('Resume');
    }

    public function test_a_non_enrolled_student_cannot_open_the_class_page(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();

        $response = $this->actingAs($student)->get(route('student.subjects.class', $subject));

        $response->assertForbidden();
    }

    /**
     * Core-value regression guard: the disabled Start button is UX only.
     * A crafted second POST to attempts.store for an exam the student
     * already has an attempt for must not create a second attempt row —
     * the server-side attempts.unique(exam_id,user_id) constraint (and
     * AttemptController@store's firstOrCreate) stays authoritative
     * regardless of what this page renders.
     */
    public function test_a_second_attempts_store_post_does_not_create_a_second_attempt(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();
        $this->enroll($student, $subject);
        $exam = Exam::factory()->published()->available()->create(['subject_id' => $subject->id]);
        Attempt::factory()->for($exam)->for($student)->create(['status' => 'in_progress']);

        $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertSame(1, Attempt::where('exam_id', $exam->id)->where('user_id', $student->id)->count());
    }
}

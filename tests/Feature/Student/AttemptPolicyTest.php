<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AVL-04 critical regression suite.
 *
 * AttemptPolicy::view()/update() used to delegate to Exam::visibleTo(),
 * which is enrollment-status-driven. Phase 8 is the first milestone in
 * which enrollment status (and exam publication/assignment/availability)
 * can change AFTER an attempt has already started — withdraw (08-04),
 * reject (08-05), the availability window (08-01/08-06), unpublish, and
 * un-assignment are all mutable underneath an in-progress attempt.
 *
 * REQUIREMENTS.md Resolved Design Decision #7 is explicit: post-start
 * attempt access is ownership-gated, not visibility-gated. The
 * enrollment/availability check applies at START only (ExamPolicy::
 * takeable(), AttemptController@store) — never again after the attempt
 * exists. Re-coupling view()/update() to Exam::visibleTo() to "restore
 * symmetry" with the pre-start gate would 403 a student's own
 * in-progress attempt the moment any of the above changes underneath
 * them, silently discarding unsaved work. This file is the guard rail
 * against that regression — see also the doc comment on
 * AttemptPolicy::view().
 */
class AttemptPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build an in-progress attempt for an Enrolled student on a
     * published, available, section-assigned exam with one MCQ
     * question — the baseline fixture every mutation test starts from
     * before mutating the world underneath it.
     *
     * @return array{0: User, 1: Attempt, 2: Section, 3: Exam, 4: Question, 5: Option}
     */
    private function attemptFixture(): array
    {
        $exam = Exam::factory()->published()->available()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);

        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $question = Question::factory()->mcq()->create(['exam_id' => $exam->id]);
        $option = $question->options()->first();

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now(),
        ]);

        return [$student, $attempt, $section, $exam, $question, $option];
    }

    /**
     * Assert all attempt-touching actions still work for the attempt's
     * owner: the exam landing page renders, the "resume" POST redirects
     * straight back to the attempt (CR-02 — neither goes through
     * ExamPolicy::takeable()'s Exam::visibleTo() gate once an attempt
     * already exists), the take page renders, autosave is accepted, and
     * submit finalizes successfully. Called AFTER the world has been
     * mutated underneath the attempt.
     */
    private function assertAttemptFullyUsable(User $student, Attempt $attempt, Question $question, Option $option): void
    {
        $examShowResponse = $this->actingAs($student)->get(route('student.exams.show', $attempt->exam_id));
        $examShowResponse->assertOk();

        $resumeResponse = $this->actingAs($student)->post(route('student.attempts.store', $attempt->exam_id));
        $resumeResponse->assertRedirect(route('student.attempts.show', $attempt));

        $showResponse = $this->actingAs($student)->get(route('student.attempts.show', $attempt));
        $showResponse->assertOk();

        $answerResponse = $this->actingAs($student)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);
        $answerResponse->assertOk();

        $submitResponse = $this->actingAs($student)->post(route('student.attempts.submit', $attempt));
        $submitResponse->assertRedirect(route('student.attempts.submitted', $attempt));
    }

    // -- AVL-04 mid-attempt mutation cases -----------------------------

    public function test_attempt_survives_enrollment_withdrawn_mid_attempt(): void
    {
        [$student, $attempt, $section, , $question, $option] = $this->attemptFixture();

        $section->enrollments()->updateExistingPivot($student->id, ['status' => EnrollmentStatus::Withdrawn]);

        $this->assertAttemptFullyUsable($student, $attempt, $question, $option);
    }

    public function test_attempt_survives_enrollment_rejected_mid_attempt(): void
    {
        [$student, $attempt, $section, , $question, $option] = $this->attemptFixture();

        $section->enrollments()->updateExistingPivot($student->id, ['status' => EnrollmentStatus::Rejected]);

        $this->assertAttemptFullyUsable($student, $attempt, $question, $option);
    }

    public function test_attempt_survives_availability_window_closing_mid_attempt(): void
    {
        [$student, $attempt, , $exam, $question, $option] = $this->attemptFixture();

        // Move the window, not the clock (08-03-PLAN.md) — travelTo() here
        // would also expire the attempt's own timer and finalizeIfExpired()
        // would legitimately close it, masking what this test proves.
        $exam->update(['available_until' => now()->subMinute()]);

        $this->assertAttemptFullyUsable($student, $attempt, $question, $option);
    }

    public function test_attempt_survives_enrollment_row_deleted_mid_attempt(): void
    {
        [$student, $attempt, $section, , $question, $option] = $this->attemptFixture();

        $section->enrollments()->detach($student->id);

        $this->assertAttemptFullyUsable($student, $attempt, $question, $option);
    }

    public function test_attempt_survives_exam_unpublished_mid_attempt(): void
    {
        [$student, $attempt, , $exam, $question, $option] = $this->attemptFixture();

        $exam->update(['is_published' => false]);

        $this->assertAttemptFullyUsable($student, $attempt, $question, $option);
    }

    // -- Retained/strengthened IDOR cases -------------------------------

    public function test_a_student_cannot_view_another_students_attempt(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);

        $studentA = User::factory()->student()->create();
        $studentB = User::factory()->student()->create();
        $section->enrollments()->attach($studentA->id, ['status' => EnrollmentStatus::Enrolled]);
        $section->enrollments()->attach($studentB->id, ['status' => EnrollmentStatus::Enrolled]);

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $studentA->id,
        ]);

        $response = $this->actingAs($studentB)->get(route('student.attempts.show', $attempt));

        $response->assertForbidden();
    }

    public function test_another_student_cannot_autosave_into_this_students_attempt(): void
    {
        [, $attempt, , , $question, $option] = $this->attemptFixture();
        $intruder = User::factory()->student()->create();

        $response = $this->actingAs($intruder)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $response->assertForbidden();
    }

    public function test_another_student_cannot_submit_this_students_attempt(): void
    {
        [, $attempt] = $this->attemptFixture();
        $intruder = User::factory()->student()->create();

        $response = $this->actingAs($intruder)->post(route('student.attempts.submit', $attempt));

        $response->assertForbidden();
    }

    public function test_another_student_enrolled_in_the_same_section_cannot_view_this_students_attempt(): void
    {
        [, $attempt, $section] = $this->attemptFixture();
        $intruder = User::factory()->student()->create();
        $section->enrollments()->attach($intruder->id, ['status' => EnrollmentStatus::Enrolled]);

        $response = $this->actingAs($intruder)->get(route('student.attempts.show', $attempt));

        $response->assertForbidden();
    }
}

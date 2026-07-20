<?php

namespace Tests\Feature\Lecturer;

use App\Enums\EnrollmentStatus;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 10, Wave 0) — CLS-07 + INT-03's acceptance. Pins the
 * `lecturer.exams.submissions.reset` route-name contract (DELETE
 * `lecturer/exams/{exam}/submissions` -> `ExamController@resetSubmissions`,
 * see this plan's `<route_name_contract>` block) that plan 07 must conform
 * to, plus the INT-03 retake guarantee that D-2's hard delete releases
 * `attempts.unique(exam_id, user_id)` so a reset student can start again.
 *
 * Every method except the retake one calls
 * `route('lecturer.exams.submissions.reset', $exam)` before any assertion
 * that could otherwise matter, so those methods are expected to ERROR with
 * a RouteNotFoundException until plan 07 defines the route — the file's
 * predominant RED signal. The retake method (see its own method-level doc
 * comment below) additionally exercises the retake path BEFORE reaching
 * that undefined route, and is expected to fail there instead, for a
 * second and independent pre-implementation reason: `Exam::scopeVisibleTo()`
 * still walks the `exam_section` pivot (plan 06 has not yet made visibility
 * subject-derived), so the enrolled student's first attempt-start 403s.
 * Both are legitimate RED reasons for this wave, not fixture bugs — see
 * 10-02-SUMMARY.md for the recorded actual output.
 */
class ResetSubmissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Same-subject fixture template copied from
     * `AttemptAvailabilityTest::enrolledStudentFor()` — WITHOUT the pivot
     * sync call that helper makes on `$exam->sections()`. Plan 06 drops the
     * exam_section pivot entirely; once `scopeVisibleTo()` is rewritten to
     * be subject-derived, pinning `subject_id` + the enrollment below is
     * all that is needed for the student to see/start the exam. Adding
     * that sync call here would break this file at wave 3.
     */
    private function enrolledStudentFor(Exam $exam): User
    {
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        return $student;
    }

    public function test_reset_deletes_every_attempt_on_the_exam(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();

        Attempt::factory()->for($exam)->for(User::factory()->student())->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->submitted()->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->graded(5)->create();

        $response = $this->actingAs($lecturer)->delete(route('lecturer.exams.submissions.reset', $exam));

        $response->assertRedirect();
        $this->assertDatabaseCount('attempts', 0);
    }

    /**
     * D-2's locked, deliberate behavior — the test documents the
     * destruction as intended, not as a bug (INT-02's stated consequence).
     */
    public function test_reset_permanently_deletes_graded_scores(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();
        $question = Question::factory()->for($exam)->create();

        $attempt = Attempt::factory()->for($exam)->for(User::factory()->student())->graded(5)->create();
        $answer = Answer::factory()->for($attempt)->for($question)->create(['score' => 5]);

        $this->actingAs($lecturer)->delete(route('lecturer.exams.submissions.reset', $exam));

        $this->assertDatabaseMissing('answers', ['attempt_id' => $attempt->id]);
        $this->assertDatabaseMissing('answers', ['id' => $answer->id]);
    }

    /**
     * INT-03's actual acceptance — the method that proves the
     * unique(exam_id, user_id) index no longer locks the student out once
     * D-2's hard delete has run.
     */
    public function test_a_student_whose_attempt_was_reset_can_start_the_exam_again(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->available()->create();
        $student = $this->enrolledStudentFor($exam);

        $this->actingAs($student)->post(route('student.attempts.store', $exam));
        $this->assertDatabaseCount('attempts', 1);
        $originalAttemptId = Attempt::firstOrFail()->id;

        $this->actingAs($lecturer)->delete(route('lecturer.exams.submissions.reset', $exam));

        $response = $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(409, $response->status());
        $this->assertNotEquals(500, $response->status());
        $this->assertDatabaseHas('attempts', ['exam_id' => $exam->id, 'user_id' => $student->id]);

        $newAttempt = Attempt::where('exam_id', $exam->id)->where('user_id', $student->id)->firstOrFail();
        $this->assertNotSame($originalAttemptId, $newAttempt->id);
    }

    /**
     * Without an exam-scoped delete, a `void()` missing its
     * `where('exam_id', ...)` would wipe the whole attempts table and still
     * pass every method above.
     */
    public function test_reset_only_affects_the_target_exam(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();
        $otherExam = Exam::factory()->published()->create();

        Attempt::factory()->for($exam)->for(User::factory()->student())->create();
        $otherAttempt = Attempt::factory()->for($otherExam)->for(User::factory()->student())->create();

        $this->actingAs($lecturer)->delete(route('lecturer.exams.submissions.reset', $exam));

        $this->assertDatabaseCount('attempts', 1);
        $this->assertDatabaseHas('attempts', ['id' => $otherAttempt->id]);
    }

    /**
     * Success criterion 5 — the flash key is `status`, NOT `success`
     * (`session('success')` has zero call sites in this codebase and
     * `<x-toast>` does not read it).
     */
    public function test_reset_reports_the_outcome_to_the_lecturer(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create(['title' => 'Midterm Exam']);

        Attempt::factory()->for($exam)->for(User::factory()->student())->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->submitted()->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->graded(5)->create();

        $response = $this->actingAs($lecturer)->delete(route('lecturer.exams.submissions.reset', $exam));

        $response->assertSessionHas('status');
        $this->assertStringContainsString(
            'Reset 3 submission(s) for "Midterm Exam".',
            (string) session('status')
        );
    }

    public function test_a_student_is_forbidden_from_resetting_submissions(): void
    {
        $student = User::factory()->student()->create();
        $exam = Exam::factory()->published()->create();

        Attempt::factory()->for($exam)->for(User::factory()->student())->create();

        $response = $this->actingAs($student)->delete(route('lecturer.exams.submissions.reset', $exam));

        $response->assertForbidden();
        $this->assertDatabaseCount('attempts', 1);
    }
}

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
 * RED (Phase 5, Wave 0) — pins the lecturer open-text grading contract
 * (GRD-02, D-04). The grade route/controller/FormRequest do not exist
 * yet: every method here is expected to fail on a missing-route error
 * (RouteNotFoundException) or a 404/500, never a parse error.
 *
 * Fixed route contract this plan pins for Waves 2-4:
 *   PATCH lecturer/attempts/{attempt}/answers/{answer}/grade -> lecturer.attempts.answers.grade
 */
class GradeAnswerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: User, 2: Attempt, 3: Question, 4: Answer} */
    private function openTextFixture(int $points = 5): array
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);
        $lecturer = User::factory()->lecturer()->create();

        $question = Question::factory()->open()->create(['exam_id' => $exam->id, 'points' => $points]);

        $attempt = Attempt::factory()->submitted()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        $answer = Answer::factory()->openText()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
        ]);

        return [$lecturer, $student, $attempt, $question, $answer];
    }

    public function test_lecturer_can_grade_an_open_text_answer(): void
    {
        [$lecturer, , $attempt, , $answer] = $this->openTextFixture(points: 5);

        $response = $this->actingAs($lecturer)->patch(
            route('lecturer.attempts.answers.grade', [$attempt, $answer]),
            ['score' => 3]
        );

        $response->assertRedirect();
        $this->assertEquals(3, $answer->fresh()->score);
    }

    public function test_over_points_score_is_rejected(): void
    {
        [$lecturer, , $attempt, , $answer] = $this->openTextFixture(points: 5);

        $response = $this->actingAs($lecturer)->patchJson(
            route('lecturer.attempts.answers.grade', [$attempt, $answer]),
            ['score' => 6]
        );

        $response->assertStatus(422);
        $this->assertNull($answer->fresh()->score);
    }

    public function test_negative_score_is_rejected(): void
    {
        [$lecturer, , $attempt, , $answer] = $this->openTextFixture(points: 5);

        $response = $this->actingAs($lecturer)->patchJson(
            route('lecturer.attempts.answers.grade', [$attempt, $answer]),
            ['score' => -1]
        );

        $response->assertStatus(422);
        $this->assertNull($answer->fresh()->score);
    }

    public function test_non_lecturer_cannot_grade(): void
    {
        [, $student, $attempt, , $answer] = $this->openTextFixture();

        $studentResponse = $this->actingAs($student)->patch(
            route('lecturer.attempts.answers.grade', [$attempt, $answer]),
            ['score' => 1]
        );
        $studentResponse->assertForbidden();

        // actingAs() sets the auth guard's user directly (no real session
        // login), so it persists across subsequent calls in this same test
        // method unless the guard is explicitly forgotten — otherwise this
        // "guest" request would still be authenticated as $student.
        $this->app['auth']->forgetGuards();

        $guestResponse = $this->patch(
            route('lecturer.attempts.answers.grade', [$attempt, $answer]),
            ['score' => 1]
        );
        $guestResponse->assertRedirect(route('login'));

        $this->assertNull($answer->fresh()->score);
    }

    public function test_grading_an_mcq_answer_is_rejected(): void
    {
        [$lecturer, , $attempt] = $this->openTextFixture();

        $mcqQuestion = Question::factory()->mcq()->create(['exam_id' => $attempt->exam_id, 'points' => 2]);
        $mcqAnswer = Answer::factory()->mcqCorrect($mcqQuestion)->create(['attempt_id' => $attempt->id]);

        $response = $this->actingAs($lecturer)->patch(
            route('lecturer.attempts.answers.grade', [$attempt, $mcqAnswer]),
            ['score' => 2]
        );

        $response->assertForbidden();
        $this->assertNull($mcqAnswer->fresh()->score);
    }
}

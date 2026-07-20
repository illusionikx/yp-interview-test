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

class AttemptAnswerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Attempt, 2: Question, 3: Option}
     */
    private function mcqAttemptFixture(int $durationMinutes = 30): array
    {
        $exam = Exam::factory()->published()->create(['duration_minutes' => $durationMinutes]);
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

        return [$student, $attempt, $question, $option];
    }

    public function test_an_answer_saved_before_the_deadline_is_persisted(): void
    {
        [$student, $attempt, $question, $option] = $this->mcqAttemptFixture();

        $response = $this->actingAs($student)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('answers', 1);
    }

    public function test_an_answer_after_the_deadline_is_rejected(): void
    {
        [$student, $attempt, $question, $option] = $this->mcqAttemptFixture(10);

        $this->travelTo($attempt->started_at->copy()->addMinutes(10)->addMinute());

        $response = $this->actingAs($student)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('answers', 0);
    }

    public function test_autosave_persists_and_survives_reload(): void
    {
        [$student, $attempt, $question, $option] = $this->mcqAttemptFixture();

        $this->actingAs($student)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $response = $this->actingAs($student)->get(route('student.attempts.show', $attempt));

        $savedAnswers = $response->viewData('savedAnswers');
        $this->assertNotNull($savedAnswers);
        $this->assertSame($option->id, $savedAnswers[$question->id]->selected_option_id);
    }

    public function test_repeated_autosave_upserts_the_same_answer_row(): void
    {
        [$student, $attempt, $question, $option] = $this->mcqAttemptFixture();
        $otherOption = Option::factory()->create(['question_id' => $question->id, 'is_correct' => false]);

        $this->actingAs($student)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $this->actingAs($student)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $otherOption->id,
        ]);

        $this->assertDatabaseCount('answers', 1);
        $this->assertDatabaseHas('answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_option_id' => $otherOption->id,
        ]);
    }

    public function test_an_expired_attempt_rejects_answer_writes(): void
    {
        [$student, $attempt, $question, $option] = $this->mcqAttemptFixture(10);

        $this->travelTo($attempt->started_at->copy()->addMinutes(10)->addMinute());

        $response = $this->actingAs($student)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $response->assertStatus(422);

        // Phase 5 (D-02/D-03): this fixture's exam has a single MCQ
        // question the student never answered — no open-text remains
        // pending, so the finalize-on-expiry hook grades it straight to
        // 'graded' in the same call that rejects the late answer write.
        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
    }
}

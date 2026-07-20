<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the Phase 4 code-review findings:
 *  - Blocker 1: an autosave racing the attempt's finalization must NOT write to
 *    an already-submitted attempt (stale in-memory status after lockAndFinalize).
 *  - Blocker 2: only a genuine deadline rejection carries `expired: true`; an
 *    ordinary validation 422 must NOT (so the client doesn't force auto-submit),
 *    and must not finalize the attempt.
 *  - Medium: the confirmation URL finalizes an expired in_progress attempt.
 *  - Low: a non-owner is 403'd before validation runs.
 */
class Phase4ReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Attempt, 2: Question} */
    private function fixture(int $durationMinutes = 30): array
    {
        $exam = Exam::factory()->published()->create(['duration_minutes' => $durationMinutes]);
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);
        $question = Question::factory()->mcq()->create(['exam_id' => $exam->id]);

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now(),
        ]);

        return [$student, $attempt, $question];
    }

    /** Blocker 1 — an answer to an already-finalized attempt writes nothing. */
    public function test_answering_an_already_finalized_attempt_writes_nothing(): void
    {
        [$student, $attempt, $question] = $this->fixture();
        $option = $question->options()->first();

        // Simulate the racing auto-submit having already finalized the attempt.
        // Phase 5: the single unanswered MCQ question isn't "pending" (only
        // open-text blocks completeness), so finalize grades it straight to
        // 'graded' — the important assertion here is just "not in_progress".
        $attempt->loadMissing('exam')->finalize();
        $this->assertSame('graded', $attempt->fresh()->status);

        $response = $this->actingAs($student)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('answers', 0);
    }

    /** Blocker 2 — a deadline rejection carries the `expired` flag. */
    public function test_a_deadline_rejection_carries_the_expired_flag(): void
    {
        [$student, $attempt, $question] = $this->fixture(10);
        $option = $question->options()->first();

        $this->travelTo($attempt->started_at->copy()->addMinutes(11));

        $response = $this->actingAs($student)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        // Phase 5: see note above — an unanswered MCQ finalizes straight to 'graded'.
        $response->assertStatus(422)->assertJson(['expired' => true]);
        $this->assertSame('graded', $attempt->fresh()->status);
    }

    /** Blocker 2 — an ordinary validation 422 does NOT carry `expired` and does NOT finalize. */
    public function test_a_validation_error_does_not_carry_the_expired_flag_or_finalize(): void
    {
        [$student, $attempt, $question] = $this->fixture();

        $response = $this->actingAs($student)->postJson(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => 999999, // does not exist → validation 422, not an expiry
        ]);

        $response->assertStatus(422);
        $this->assertNotSame(true, $response->json('expired'));   // no expiry flag
        $this->assertSame('in_progress', $attempt->fresh()->status); // still valid — NOT force-finalized
    }

    /** Low — a non-owner is 403'd (authorize runs before validation). */
    public function test_a_non_owner_cannot_autosave_an_answer(): void
    {
        [$owner, $attempt, $question] = $this->fixture();
        $intruder = User::factory()->student()->create();

        // Even with a structurally-invalid payload, authorization wins first → 403, not 422.
        $response = $this->actingAs($intruder)->postJson(route('student.attempts.answer', $attempt), [
            'question_id' => 999999,
        ]);

        $response->assertForbidden();
    }

    /** Medium — the confirmation URL finalizes an expired in_progress attempt. */
    public function test_the_confirmation_page_finalizes_an_expired_in_progress_attempt(): void
    {
        [$student, $attempt, $question] = $this->fixture(10);

        $this->travelTo($attempt->started_at->copy()->addMinutes(11));

        $this->actingAs($student)->get(route('student.attempts.submitted', $attempt))->assertOk();

        // Phase 5: see note above — an unanswered MCQ finalizes straight to 'graded'.
        $this->assertSame('graded', $attempt->fresh()->status);
    }
}

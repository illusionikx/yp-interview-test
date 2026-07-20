<?php

namespace Tests\Feature\Lecturer;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the ExamQuestionController::update() response contract after quick
 * task 3d2 added the 204-on-ajax branch (progressive enhancement of the
 * per-question Save). The FORMAT of the response depends on whether the
 * request carries X-Requested-With; authorization, validation, and the
 * DB write are identical on both branches.
 *
 * The attempted-exam warn-and-void redirect + void behavior is NOT this
 * file's concern — ExamUpdateVoidsAttemptsTest guards that (it never sends
 * the ajax header, so it always hits the redirect branch).
 */
class ExamQuestionAjaxSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_ajax_update_returns_204_no_content(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $question = Question::factory()->open()->create(['exam_id' => $exam->id]);

        $response = $this->actingAs($lecturer)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put(route('lecturer.exams.questions.update', [$exam, $question]), [
                'type' => 'open',
                'body' => 'Updated body',
                'points' => 2,
            ]);

        $response->assertNoContent();
        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'body' => 'Updated body',
            'points' => 2,
        ]);
    }

    public function test_a_non_ajax_update_still_redirects(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $question = Question::factory()->open()->create(['exam_id' => $exam->id]);

        $response = $this->actingAs($lecturer)
            ->put(route('lecturer.exams.questions.update', [$exam, $question]), [
                'type' => 'open',
                'body' => 'Updated body',
                'points' => 2,
            ]);

        $response->assertRedirect(route('lecturer.exams.show', $exam));
    }

    public function test_an_ajax_update_with_invalid_data_returns_422_json(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $question = Question::factory()->open()->create(['exam_id' => $exam->id]);

        $response = $this->actingAs($lecturer)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->put(route('lecturer.exams.questions.update', [$exam, $question]), [
                'type' => 'open',
                'body' => '',
                'points' => 2,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('body');
    }
}

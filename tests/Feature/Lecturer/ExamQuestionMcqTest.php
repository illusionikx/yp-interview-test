<?php

namespace Tests\Feature\Lecturer;

use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamQuestionMcqTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lecturer_can_add_an_mcq_question_with_exactly_one_correct_option(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'mcq',
            'body' => 'What is 2 + 2?',
            'points' => 2,
            'options' => [
                ['body' => '3'],
                ['body' => '4'],
                ['body' => '5'],
            ],
            'correct_option' => 1,
        ]);

        $response->assertRedirect(route('lecturer.exams.show', $exam));

        $question = Question::firstWhere('body', 'What is 2 + 2?');
        $this->assertNotNull($question);
        $this->assertSame(QuestionType::Mcq, $question->type);
        $this->assertSame(2, $question->points);
        $this->assertSame(3, $question->options()->count());
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
        $this->assertDatabaseHas('options', [
            'question_id' => $question->id,
            'body' => '4',
            'is_correct' => true,
        ]);
        $this->assertDatabaseHas('options', [
            'question_id' => $question->id,
            'body' => '3',
            'is_correct' => false,
        ]);
    }

    public function test_adding_an_mcq_question_without_a_correct_option_is_rejected(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'mcq',
            'body' => 'No correct option supplied',
            'options' => [
                ['body' => 'A'],
                ['body' => 'B'],
            ],
        ]);

        $response->assertSessionHasErrors('correct_option');
        $this->assertSame(0, Question::count());
    }

    public function test_adding_an_mcq_question_with_an_out_of_range_correct_option_is_rejected(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'mcq',
            'body' => 'Correct option out of range',
            'options' => [
                ['body' => 'A'],
                ['body' => 'B'],
            ],
            'correct_option' => 5,
        ]);

        $response->assertSessionHasErrors('correct_option');
        $this->assertSame(0, Question::count());
    }

    public function test_adding_an_mcq_question_with_fewer_than_two_options_is_rejected(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'mcq',
            'body' => 'Only one option',
            'options' => [
                ['body' => 'Only option'],
            ],
            'correct_option' => 0,
        ]);

        $response->assertSessionHasErrors('options');
        $this->assertSame(0, Question::count());
    }

    public function test_adding_an_mcq_question_with_a_blank_option_body_is_rejected(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'mcq',
            'body' => 'Has a blank option',
            'options' => [
                ['body' => 'A'],
                ['body' => ''],
            ],
            'correct_option' => 0,
        ]);

        $response->assertSessionHasErrors('options.1.body');
        $this->assertSame(0, Question::count());
    }

    public function test_mcq_points_default_to_one_when_omitted(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $this->actingAs($lecturer)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'mcq',
            'body' => 'Points omitted',
            'options' => [
                ['body' => 'A'],
                ['body' => 'B'],
            ],
            'correct_option' => 0,
        ]);

        $question = Question::firstWhere('body', 'Points omitted');
        $this->assertNotNull($question);
        $this->assertSame(1, $question->points);
    }

    public function test_a_custom_points_value_persists(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $this->actingAs($lecturer)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'mcq',
            'body' => 'Custom points',
            'points' => 5,
            'options' => [
                ['body' => 'A'],
                ['body' => 'B'],
            ],
            'correct_option' => 0,
        ]);

        $question = Question::firstWhere('body', 'Custom points');
        $this->assertNotNull($question);
        $this->assertSame(5, $question->points);
    }

    public function test_points_below_one_is_rejected(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'mcq',
            'body' => 'Bad points',
            'points' => 0,
            'options' => [
                ['body' => 'A'],
                ['body' => 'B'],
            ],
            'correct_option' => 0,
        ]);

        $response->assertSessionHasErrors('points');
        $this->assertSame(0, Question::count());
    }

    public function test_a_student_is_forbidden_from_adding_an_mcq_question(): void
    {
        $student = User::factory()->student()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($student)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'mcq',
            'body' => 'Student attempt',
            'options' => [
                ['body' => 'A'],
                ['body' => 'B'],
            ],
            'correct_option' => 0,
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Question::count());
    }

    /**
     * D-4/D-6 (Phase 10, plan 08): the draft-only published-edit gate is
     * retired. Adding a question to a published exam is now allowed — see
     * tests\Feature\Lecturer\ExamPublishedEditGateTest's class doc comment
     * and tests\Feature\Lecturer\ExamUpdateVoidsAttemptsTest for the
     * warn-and-void consequence when the exam has attempts.
     */
    public function test_adding_a_question_to_a_published_exam_is_allowed(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'mcq',
            'body' => 'Published exam attempt',
            'options' => [
                ['body' => 'A'],
                ['body' => 'B'],
            ],
            'correct_option' => 0,
        ]);

        $response->assertRedirect(route('lecturer.exams.show', $exam));
        $this->assertDatabaseHas('questions', ['exam_id' => $exam->id, 'body' => 'Published exam attempt']);
    }
}

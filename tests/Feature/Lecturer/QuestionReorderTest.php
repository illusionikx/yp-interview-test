<?php

namespace Tests\Feature\Lecturer;

use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EDT-03/EDT-05 (12-05) — authoring-time move-up/move-down reordering for
 * questions and their MCQ options, plus a one-time option shuffle. All
 * three actions are display-order only (is_correct is never touched), so
 * — unlike ExamQuestionController's store/update/destroy — none of them
 * run AttemptVoider or void attempts. The "reorder does not void attempts"
 * case below is the load-bearing guard that distinguishes reorder from an
 * EDT-04 content edit.
 */
class QuestionReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_a_question_up_swaps_its_position_with_the_prior_question(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $first = Question::factory()->create(['exam_id' => $exam->id, 'position' => 0]);
        $second = Question::factory()->create(['exam_id' => $exam->id, 'position' => 1]);
        $third = Question::factory()->create(['exam_id' => $exam->id, 'position' => 2]);

        $response = $this->actingAs($lecturer)->patch(route('lecturer.exams.questions.move', [$exam, $second]), [
            'direction' => 'up',
        ]);

        $response->assertRedirect();
        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(2, $third->fresh()->position);

        $ordered = $exam->fresh()->questions->pluck('id')->all();
        $this->assertSame([$second->id, $first->id, $third->id], $ordered);
    }

    public function test_moving_a_question_up_swaps_with_the_nearest_sibling_not_the_topmost(): void
    {
        // 12-REVIEW CR-01: with 2+ questions above it, "up" must swap with the
        // NEAREST higher question (position n-1), not the topmost (position 0).
        // The relation's default orderBy('position') asc previously dominated
        // the appended orderByDesc, silently returning the topmost sibling.
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $first = Question::factory()->create(['exam_id' => $exam->id, 'position' => 0]);
        $second = Question::factory()->create(['exam_id' => $exam->id, 'position' => 1]);
        $third = Question::factory()->create(['exam_id' => $exam->id, 'position' => 2]);

        $response = $this->actingAs($lecturer)->patch(route('lecturer.exams.questions.move', [$exam, $third]), [
            'direction' => 'up',
        ]);

        $response->assertRedirect();
        $this->assertSame(0, $first->fresh()->position, 'the topmost question must NOT move');
        $this->assertSame(2, $second->fresh()->position);
        $this->assertSame(1, $third->fresh()->position);
        $this->assertSame([$first->id, $third->id, $second->id], $exam->fresh()->questions->pluck('id')->all());
    }

    public function test_moving_an_option_up_swaps_with_the_nearest_sibling_not_the_topmost(): void
    {
        // 12-REVIEW CR-01, option variant.
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $question = Question::factory()->create(['exam_id' => $exam->id]);
        $option0 = Option::factory()->create(['question_id' => $question->id, 'position' => 0]);
        $option1 = Option::factory()->create(['question_id' => $question->id, 'position' => 1]);
        $option2 = Option::factory()->create(['question_id' => $question->id, 'position' => 2]);

        $response = $this->actingAs($lecturer)->patch(route('lecturer.exams.questions.options.move', [$exam, $question, $option2]), [
            'direction' => 'up',
        ]);

        $response->assertRedirect();
        $this->assertSame(0, $option0->fresh()->position, 'the topmost option must NOT move');
        $this->assertSame(2, $option1->fresh()->position);
        $this->assertSame(1, $option2->fresh()->position);
    }

    public function test_moving_the_first_question_up_is_a_no_op(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $first = Question::factory()->create(['exam_id' => $exam->id, 'position' => 0]);
        $second = Question::factory()->create(['exam_id' => $exam->id, 'position' => 1]);

        $response = $this->actingAs($lecturer)->patch(route('lecturer.exams.questions.move', [$exam, $first]), [
            'direction' => 'up',
        ]);

        $response->assertRedirect();
        $this->assertSame(0, $first->fresh()->position);
        $this->assertSame(1, $second->fresh()->position);
    }

    public function test_moving_the_last_question_down_is_a_no_op(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $first = Question::factory()->create(['exam_id' => $exam->id, 'position' => 0]);
        $second = Question::factory()->create(['exam_id' => $exam->id, 'position' => 1]);

        $response = $this->actingAs($lecturer)->patch(route('lecturer.exams.questions.move', [$exam, $second]), [
            'direction' => 'down',
        ]);

        $response->assertRedirect();
        $this->assertSame(0, $first->fresh()->position);
        $this->assertSame(1, $second->fresh()->position);
    }

    public function test_moving_an_option_down_swaps_its_position_with_the_next_option(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $question = Question::factory()->create(['exam_id' => $exam->id]);
        $option0 = Option::factory()->create(['question_id' => $question->id, 'position' => 0]);
        $option1 = Option::factory()->create(['question_id' => $question->id, 'position' => 1]);
        $option2 = Option::factory()->create(['question_id' => $question->id, 'position' => 2]);
        $option3 = Option::factory()->create(['question_id' => $question->id, 'position' => 3]);

        $response = $this->actingAs($lecturer)->patch(route('lecturer.exams.questions.options.move', [$exam, $question, $option0]), [
            'direction' => 'down',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, $option0->fresh()->position);
        $this->assertSame(0, $option1->fresh()->position);
        $this->assertSame(2, $option2->fresh()->position);
        $this->assertSame(3, $option3->fresh()->position);
    }

    public function test_shuffling_options_produces_a_valid_permutation_and_preserves_correctness(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $question = Question::factory()->create(['exam_id' => $exam->id]);
        $option0 = Option::factory()->create(['question_id' => $question->id, 'position' => 0, 'is_correct' => true]);
        $option1 = Option::factory()->create(['question_id' => $question->id, 'position' => 1, 'is_correct' => false]);
        $option2 = Option::factory()->create(['question_id' => $question->id, 'position' => 2, 'is_correct' => false]);
        $option3 = Option::factory()->create(['question_id' => $question->id, 'position' => 3, 'is_correct' => false]);

        $response = $this->actingAs($lecturer)->patch(route('lecturer.exams.questions.options.shuffle', [$exam, $question]));

        $response->assertRedirect();

        $positions = $question->options()->pluck('position')->sort()->values()->all();
        $this->assertSame([0, 1, 2, 3], $positions);

        $this->assertTrue($option0->fresh()->is_correct);
        $this->assertFalse($option1->fresh()->is_correct);
        $this->assertFalse($option2->fresh()->is_correct);
        $this->assertFalse($option3->fresh()->is_correct);
    }

    public function test_reordering_and_shuffling_do_not_void_an_existing_attempt(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();
        $first = Question::factory()->create(['exam_id' => $exam->id, 'position' => 0]);
        $second = Question::factory()->create(['exam_id' => $exam->id, 'position' => 1]);
        $option0 = Option::factory()->create(['question_id' => $second->id, 'position' => 0]);
        Option::factory()->create(['question_id' => $second->id, 'position' => 1]);
        $attempt = Attempt::factory()->for($exam)->for(User::factory()->student())->create();

        $this->actingAs($lecturer)->patch(route('lecturer.exams.questions.move', [$exam, $second]), [
            'direction' => 'up',
        ]);
        $this->actingAs($lecturer)->patch(route('lecturer.exams.questions.options.shuffle', [$exam, $second]));

        $this->assertDatabaseHas('attempts', ['id' => $attempt->id]);
        unset($first, $option0);
    }

    public function test_a_question_move_with_a_mismatched_exam_404s(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $otherExam = Exam::factory()->create();
        $question = Question::factory()->create(['exam_id' => $otherExam->id]);

        $response = $this->actingAs($lecturer)->patch(route('lecturer.exams.questions.move', [$exam, $question]), [
            'direction' => 'up',
        ]);

        $response->assertNotFound();
    }

    public function test_an_option_move_with_a_mismatched_question_404s(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $question = Question::factory()->create(['exam_id' => $exam->id]);
        $otherQuestion = Question::factory()->create(['exam_id' => $exam->id]);
        $option = Option::factory()->create(['question_id' => $otherQuestion->id]);

        $response = $this->actingAs($lecturer)->patch(route('lecturer.exams.questions.options.move', [$exam, $question, $option]), [
            'direction' => 'up',
        ]);

        $response->assertNotFound();
    }

    public function test_questions_render_in_ascending_position_order_after_a_reorder(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $first = Question::factory()->create(['exam_id' => $exam->id, 'position' => 0, 'body' => 'First Question']);
        $second = Question::factory()->create(['exam_id' => $exam->id, 'position' => 1, 'body' => 'Second Question']);

        $this->actingAs($lecturer)->patch(route('lecturer.exams.questions.move', [$exam, $second]), [
            'direction' => 'up',
        ]);

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.show', $exam).'?tab=questions');
        $response->assertOk();

        $content = $response->getContent();
        $secondPos = strpos($content, 'Second Question');
        $firstPos = strpos($content, 'First Question');

        $this->assertNotFalse($secondPos);
        $this->assertNotFalse($firstPos);
        $this->assertTrue($secondPos < $firstPos, 'Second Question should now render before First Question.');
    }

    public function test_options_render_in_ascending_position_order_after_a_move(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $question = Question::factory()->create(['exam_id' => $exam->id, 'type' => \App\Enums\QuestionType::Mcq]);
        $optionA = Option::factory()->create(['question_id' => $question->id, 'position' => 0, 'body' => 'Option Alpha']);
        $optionB = Option::factory()->create(['question_id' => $question->id, 'position' => 1, 'body' => 'Option Beta']);

        $this->actingAs($lecturer)->patch(route('lecturer.exams.questions.options.move', [$exam, $question, $optionA]), [
            'direction' => 'down',
        ]);

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.show', $exam).'?tab=questions');
        $response->assertOk();

        $content = $response->getContent();
        $betaPos = strpos($content, 'Option Beta');
        $alphaPos = strpos($content, 'Option Alpha');

        $this->assertNotFalse($betaPos);
        $this->assertNotFalse($alphaPos);
        $this->assertTrue($betaPos < $alphaPos, 'Option Beta should now render before Option Alpha.');
    }

    public function test_a_student_is_forbidden_from_moving_a_question(): void
    {
        $student = User::factory()->student()->create();
        $exam = Exam::factory()->create();
        $question = Question::factory()->create(['exam_id' => $exam->id, 'position' => 0]);
        Question::factory()->create(['exam_id' => $exam->id, 'position' => 1]);

        $response = $this->actingAs($student)->patch(route('lecturer.exams.questions.move', [$exam, $question]), [
            'direction' => 'down',
        ]);

        $response->assertForbidden();
    }

    public function test_a_student_is_forbidden_from_moving_an_option(): void
    {
        $student = User::factory()->student()->create();
        $exam = Exam::factory()->create();
        $question = Question::factory()->create(['exam_id' => $exam->id]);
        $option = Option::factory()->create(['question_id' => $question->id, 'position' => 0]);
        Option::factory()->create(['question_id' => $question->id, 'position' => 1]);

        $response = $this->actingAs($student)->patch(route('lecturer.exams.questions.options.move', [$exam, $question, $option]), [
            'direction' => 'down',
        ]);

        $response->assertForbidden();
    }

    public function test_a_student_is_forbidden_from_shuffling_options(): void
    {
        $student = User::factory()->student()->create();
        $exam = Exam::factory()->create();
        $question = Question::factory()->create(['exam_id' => $exam->id]);

        $response = $this->actingAs($student)->patch(route('lecturer.exams.questions.options.shuffle', [$exam, $question]));

        $response->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Lecturer;

use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_factory_persists_a_subject_with_a_unique_name(): void
    {
        $subject = Subject::factory()->create();

        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
        $this->assertNotNull($subject->name);
    }

    public function test_option_factory_defaults_is_correct_to_false(): void
    {
        $option = Option::factory()->create();

        $this->assertFalse($option->is_correct);
        $this->assertNotNull($option->question_id);
    }

    public function test_exam_factory_defaults_to_unpublished_and_published_state_flips_it(): void
    {
        $draft = Exam::factory()->create();
        $published = Exam::factory()->published()->create();

        $this->assertFalse($draft->is_published);
        $this->assertTrue($published->is_published);
    }

    public function test_exam_factory_created_by_resolves_to_a_lecturer(): void
    {
        $exam = Exam::factory()->create();

        $this->assertTrue($exam->creator->isLecturer());
    }

    public function test_question_factory_mcq_state_yields_exactly_one_correct_option(): void
    {
        $question = Question::factory()->mcq()->create();

        $this->assertSame(QuestionType::Mcq, $question->type);
        $this->assertGreaterThanOrEqual(2, $question->options()->count());
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
    }

    public function test_question_factory_open_state_attaches_no_options(): void
    {
        $question = Question::factory()->open()->create();

        $this->assertSame(QuestionType::Open, $question->type);
        $this->assertSame(0, $question->options()->count());
    }

    public function test_user_factory_lecturer_and_student_states_set_the_correct_role(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->student()->create();

        $this->assertTrue($lecturer->isLecturer());
        $this->assertTrue($student->isStudent());
    }
}

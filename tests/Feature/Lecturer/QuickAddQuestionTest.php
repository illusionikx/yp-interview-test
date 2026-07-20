<?php

namespace Tests\Feature\Lecturer;

use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one-click "Add question" flow (issue #3): a blank default question is
 * created at the end with no form payload, and the lecturer fills it in inline.
 * Like every other question mutation it runs the EDT-04/D-6 warn-and-void.
 */
class QuickAddQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_add_appends_a_blank_mcq_question_at_the_last_position(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $exam->questions()->create(['type' => QuestionType::Open, 'body' => 'Q1', 'points' => 1, 'position' => 0]);
        $exam->questions()->create(['type' => QuestionType::Open, 'body' => 'Q2', 'points' => 1, 'position' => 1]);

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.questions.quick', $exam));

        // reorder() clears the questions() relation's default orderBy('position') asc.
        $new = $exam->questions()->reorder('position', 'desc')->first();

        $response->assertRedirect(route('lecturer.exams.show', $exam).'?tab=questions#question-'.$new->id);
        $this->assertSame(3, $exam->questions()->count());
        $this->assertSame(2, $new->position);
        $this->assertSame(QuestionType::Mcq, $new->type);
        // A usable starter MCQ: two options, exactly one correct.
        $this->assertSame(2, $new->options()->count());
        $this->assertSame(1, $new->options()->where('is_correct', true)->count());
    }

    public function test_quick_add_on_an_attempted_exam_voids_its_attempts(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->student()->create();
        $exam = Exam::factory()->published()->create();
        Attempt::factory()->for($exam)->for($student)->submitted()->create();

        $this->assertSame(1, $exam->attempts()->count());

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.questions.quick', $exam));

        $response->assertRedirect();
        $this->assertSame(0, $exam->attempts()->count());
        $this->assertSame(1, $exam->questions()->count());
    }

    public function test_a_student_cannot_quick_add_a_question(): void
    {
        $student = User::factory()->student()->create();
        $exam = Exam::factory()->create();

        $this->actingAs($student)
            ->post(route('lecturer.exams.questions.quick', $exam))
            ->assertForbidden();
    }
}

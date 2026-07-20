<?php

namespace Tests\Feature\Lecturer;

use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The name of this class is now misleading, deliberately kept unchanged
 * to avoid file-rename churn (CLAUDE.md's minimal-diff constraint). The
 * "published edit gate" it was originally built to pin was RETIRED by
 * D-4/D-6 in Phase 10 plan 08: editing a published exam — details,
 * question add, question edit, and question delete — IS now allowed.
 *
 * The `..._is_allowed` methods below pin that. The surviving
 * `test_a_student_is_forbidden_from_*` methods are a DIFFERENT gate — the
 * `role:lecturer` route-group middleware, unrelated to publish state —
 * and must stay green as proof `authorize(): return true;` opened no
 * hole for students.
 *
 * The destructive consequence of editing an ATTEMPTED (not just
 * published) exam — that the edit voids its attempts, after a warning —
 * is NOT this file's concern. That is EDT-04's warn-and-void flow,
 * covered exhaustively by ExamUpdateVoidsAttemptsTest. Do not "restore" a
 * gate here that was removed deliberately.
 */
class ExamPublishedEditGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lecturer_can_edit_a_question_on_a_draft_exam(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create(['is_published' => false]);
        $question = Question::factory()->open()->create([
            'exam_id' => $exam->id,
            'body' => 'Original body',
            'points' => 1,
        ]);

        $response = $this->actingAs($lecturer)->put(route('lecturer.exams.questions.update', [$exam, $question]), [
            'type' => 'open',
            'body' => 'Updated body',
            'points' => 3,
        ]);

        $response->assertRedirect(route('lecturer.exams.show', $exam));
        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'body' => 'Updated body',
            'points' => 3,
        ]);
    }

    public function test_editing_an_mcq_question_replaces_the_option_set_atomically(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create(['is_published' => false]);
        $question = Question::factory()->mcq()->create(['exam_id' => $exam->id]);
        $originalOptionIds = $question->options()->pluck('id')->all();

        $response = $this->actingAs($lecturer)->put(route('lecturer.exams.questions.update', [$exam, $question]), [
            'type' => 'mcq',
            'body' => $question->body,
            'points' => $question->points,
            'options' => [
                ['body' => 'Alpha'],
                ['body' => 'Beta'],
                ['body' => 'Gamma'],
            ],
            'correct_option' => 2,
        ]);

        $response->assertRedirect(route('lecturer.exams.show', $exam));

        $question->refresh();
        $this->assertSame(3, $question->options()->count());
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
        $this->assertDatabaseHas('options', [
            'question_id' => $question->id,
            'body' => 'Gamma',
            'is_correct' => true,
        ]);

        foreach ($originalOptionIds as $originalOptionId) {
            $this->assertDatabaseMissing('options', ['id' => $originalOptionId]);
        }
    }

    public function test_switching_a_question_from_mcq_to_open_drops_all_options(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create(['is_published' => false]);
        $question = Question::factory()->mcq()->create(['exam_id' => $exam->id]);

        $response = $this->actingAs($lecturer)->put(route('lecturer.exams.questions.update', [$exam, $question]), [
            'type' => 'open',
            'body' => 'Now an open question',
            'points' => 1,
        ]);

        $response->assertRedirect(route('lecturer.exams.show', $exam));

        $question->refresh();
        $this->assertSame(QuestionType::Open, $question->type);
        $this->assertSame(0, $question->options()->count());
    }

    public function test_a_lecturer_can_delete_a_question_on_a_draft_exam(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create(['is_published' => false]);
        $question = Question::factory()->mcq()->create(['exam_id' => $exam->id]);
        $optionIds = $question->options()->pluck('id')->all();

        $response = $this->actingAs($lecturer)->delete(route('lecturer.exams.questions.destroy', [$exam, $question]));

        $response->assertRedirect(route('lecturer.exams.show', $exam));
        $this->assertDatabaseMissing('questions', ['id' => $question->id]);

        foreach ($optionIds as $optionId) {
            $this->assertDatabaseMissing('options', ['id' => $optionId]);
        }
    }

    /**
     * D-4 retires the draft-only question-authoring gate. This exam has
     * no attempts, so no voiding fires — the question simply lands (see
     * ExamUpdateVoidsAttemptsTest for the voiding behavior on an
     * ATTEMPTED published exam).
     */
    public function test_adding_a_question_to_a_published_exam_is_allowed(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'open',
            'body' => 'Should now be created',
            'points' => 1,
        ]);

        $response->assertRedirect(route('lecturer.exams.show', $exam));
        $this->assertDatabaseHas('questions', ['exam_id' => $exam->id, 'body' => 'Should now be created']);
    }

    /**
     * D-4 retires the draft-only question-editing gate. This exam has no
     * attempts, so no voiding fires.
     */
    public function test_updating_a_question_on_a_published_exam_is_allowed(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();
        $question = Question::factory()->open()->create([
            'exam_id' => $exam->id,
            'body' => 'Original body',
        ]);

        $response = $this->actingAs($lecturer)->put(route('lecturer.exams.questions.update', [$exam, $question]), [
            'type' => 'open',
            'body' => 'Attempted change',
            'points' => 1,
        ]);

        $response->assertRedirect(route('lecturer.exams.show', $exam));
        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'body' => 'Attempted change',
        ]);
    }

    /**
     * D-6's fourth site — deleting a question on a published exam is now
     * allowed too. This exam has no attempts, so no voiding fires.
     */
    public function test_deleting_a_question_on_a_published_exam_is_allowed(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();
        $question = Question::factory()->open()->create(['exam_id' => $exam->id]);

        $response = $this->actingAs($lecturer)->delete(route('lecturer.exams.questions.destroy', [$exam, $question]));

        $response->assertRedirect(route('lecturer.exams.show', $exam));
        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
    }

    public function test_a_student_is_forbidden_from_editing_a_question(): void
    {
        $student = User::factory()->student()->create();
        $exam = Exam::factory()->create(['is_published' => false]);
        $question = Question::factory()->open()->create(['exam_id' => $exam->id]);

        $response = $this->actingAs($student)->get(route('lecturer.exams.questions.edit', [$exam, $question]));

        $response->assertForbidden();
    }

    public function test_a_student_is_forbidden_from_updating_a_question(): void
    {
        $student = User::factory()->student()->create();
        $exam = Exam::factory()->create(['is_published' => false]);
        $question = Question::factory()->open()->create([
            'exam_id' => $exam->id,
            'body' => 'Original body',
        ]);

        $response = $this->actingAs($student)->put(route('lecturer.exams.questions.update', [$exam, $question]), [
            'type' => 'open',
            'body' => 'Student change',
            'points' => 1,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'body' => 'Original body',
        ]);
    }

    public function test_a_student_is_forbidden_from_deleting_a_question(): void
    {
        $student = User::factory()->student()->create();
        $exam = Exam::factory()->create(['is_published' => false]);
        $question = Question::factory()->open()->create(['exam_id' => $exam->id]);

        $response = $this->actingAs($student)->delete(route('lecturer.exams.questions.destroy', [$exam, $question]));

        $response->assertForbidden();
        $this->assertDatabaseHas('questions', ['id' => $question->id]);
    }
}

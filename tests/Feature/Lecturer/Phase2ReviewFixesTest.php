<?php

namespace Tests\Feature\Lecturer;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the Phase 2 code-review findings:
 *  - Critical 1: nested {exam}/{question} binding integrity (published-gate bypass)
 *  - Critical 2: subject delete cascading into (published) exams
 *  - High: sparse MCQ option keys saving zero correct options
 */
class Phase2ReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    /** Critical 1 — a question may not be updated through a different exam's URL. */
    public function test_a_question_cannot_be_updated_via_a_mismatched_exam_url(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $publishedExam = Exam::factory()->published()->create();
        $question = Question::factory()->open()->create([
            'exam_id' => $publishedExam->id,
            'body' => 'Locked body',
        ]);
        $draftExam = Exam::factory()->create(['is_published' => false]);

        // Pair the published exam's question with a DRAFT exam URL to try to
        // slip past the published-exam gate (which inspects the URL exam).
        $response = $this->actingAs($lecturer)->put(
            route('lecturer.exams.questions.update', [$draftExam, $question]),
            ['type' => 'open', 'body' => 'Hacked body', 'points' => 1]
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'body' => 'Locked body']);
    }

    /** Critical 1 — a question may not be deleted through a different exam's URL. */
    public function test_a_question_cannot_be_deleted_via_a_mismatched_exam_url(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $publishedExam = Exam::factory()->published()->create();
        $question = Question::factory()->open()->create(['exam_id' => $publishedExam->id]);
        $draftExam = Exam::factory()->create(['is_published' => false]);

        $response = $this->actingAs($lecturer)->delete(
            route('lecturer.exams.questions.destroy', [$draftExam, $question])
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('questions', ['id' => $question->id]);
    }

    /** Critical 2 — deleting a subject that has exams is refused; the exams survive. */
    public function test_deleting_a_subject_with_exams_is_blocked_and_preserves_exams(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $exam = Exam::factory()->published()->create(['subject_id' => $subject->id]);

        $response = $this->actingAs($lecturer)->delete(route('lecturer.subjects.destroy', $subject));

        $response->assertRedirect(route('lecturer.home'));
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
        $this->assertDatabaseHas('exams', ['id' => $exam->id]);
    }

    /** High — a sparse-keyed options payload cannot save an MCQ with zero correct options. */
    public function test_sparse_option_keys_cannot_save_an_mcq_with_zero_correct_options(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create(['is_published' => false]);

        // Non-sequential keys: indices 0 and 2, with correct_option=2. Before the
        // fix, validation's array_key_exists(2, ...) passed but the controller's
        // ->values() reindex ([0,1]) left index 2 unmatched → zero correct saved.
        $response = $this->actingAs($lecturer)->post(
            route('lecturer.exams.questions.store', $exam),
            [
                'type' => 'mcq',
                'body' => 'Sparse MCQ',
                'points' => 1,
                'options' => [0 => ['body' => 'A'], 2 => ['body' => 'B']],
                'correct_option' => 2,
            ]
        );

        $response->assertSessionHasErrors('correct_option');
        $this->assertSame(0, Question::count());
        $this->assertSame(0, \App\Models\Option::where('is_correct', true)->count());
    }

    /** High — the same sparse payload with a valid reindexed correct_option saves exactly one correct. */
    public function test_reindexed_options_persist_exactly_one_correct(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create(['is_published' => false]);

        $this->actingAs($lecturer)->post(
            route('lecturer.exams.questions.store', $exam),
            [
                'type' => 'mcq',
                'body' => 'Reindexed MCQ',
                'points' => 1,
                'options' => [0 => ['body' => 'A'], 2 => ['body' => 'B']],
                'correct_option' => 1, // valid after array_values reindex → 'B'
            ]
        )->assertRedirect(route('lecturer.exams.show', $exam));

        $question = Question::firstOrFail();
        $this->assertSame(2, $question->options()->count());
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
        $this->assertDatabaseHas('options', [
            'question_id' => $question->id,
            'body' => 'B',
            'is_correct' => true,
        ]);
    }
}

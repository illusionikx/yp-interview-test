<?php

namespace Tests\Feature\Lecturer;

use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EDT-01/EDT-02 (plan 12-02) — the exam's Details and Questions surfaces
 * are merged into ONE page at `lecturer.exams.show`, presented as two
 * Alpine tabs (Details default). `lecturer.exams.edit` and the standalone
 * questions/edit page are absorbed into it (edit() now 302s to show()).
 * EDT-04's warn-and-void machinery, publish/unpublish, View Results, and
 * the CLS-07 Submissions panel all stay reachable — this file proves the
 * reorganization didn't drop any of them.
 */
class ExamEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_editor_renders_the_details_name_input_and_a_questions_tab_region(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.show', $exam));

        $response->assertOk();
        // EDT-01 — the exam/test name field, labeled and validated.
        $response->assertSee('name="title"', false);
        $response->assertSee(__('Questions'));
    }

    public function test_the_editor_defaults_to_the_details_tab_with_no_query_param(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.show', $exam));

        $response->assertOk();
        $response->assertSee("tab: 'details'", false);
    }

    public function test_tab_equals_questions_deep_links_straight_to_the_questions_tab(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.show', $exam).'?tab=questions');

        $response->assertOk();
        $response->assertSee("tab: 'questions'", false);
    }

    public function test_exams_edit_redirects_to_the_editor(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.edit', $exam));

        $response->assertRedirect(route('lecturer.exams.show', $exam));
    }

    public function test_saving_the_details_tab_with_a_blank_name_is_rejected(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->put(route('lecturer.exams.update', $exam), [
            'subject_id' => $exam->subject_id,
            'title' => '',
            'duration_minutes' => $exam->duration_minutes,
        ]);

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseMissing('exams', ['id' => $exam->id, 'title' => '']);
    }

    public function test_saving_the_details_tab_with_a_valid_name_persists_it(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create(['title' => 'Old Name']);

        $response = $this->actingAs($lecturer)->put(route('lecturer.exams.update', $exam), [
            'subject_id' => $exam->subject_id,
            'title' => 'Midterm Exam',
            'duration_minutes' => $exam->duration_minutes,
        ]);

        $response->assertRedirect(route('lecturer.exams.show', $exam));
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'title' => 'Midterm Exam']);
    }

    /**
     * Options are created out of position order — the response must still
     * render them ascending by the stored `position` column, not insertion
     * order (12-05's reorder controls depend on this rendering contract).
     */
    public function test_question_options_render_in_stored_position_order(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $question = Question::factory()->create(['exam_id' => $exam->id, 'type' => QuestionType::Mcq]);

        Option::factory()->create(['question_id' => $question->id, 'body' => 'Third Option', 'position' => 2]);
        Option::factory()->create(['question_id' => $question->id, 'body' => 'First Option', 'position' => 0]);
        Option::factory()->create(['question_id' => $question->id, 'body' => 'Second Option', 'position' => 1]);

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.show', $exam).'?tab=questions');
        $response->assertOk();

        $content = $response->getContent();
        $firstPos = strpos($content, 'First Option');
        $secondPos = strpos($content, 'Second Option');
        $thirdPos = strpos($content, 'Third Option');

        $this->assertNotFalse($firstPos);
        $this->assertNotFalse($secondPos);
        $this->assertNotFalse($thirdPos);
        $this->assertTrue($firstPos < $secondPos, 'First Option should render before Second Option.');
        $this->assertTrue($secondPos < $thirdPos, 'Second Option should render before Third Option.');
    }

    /**
     * NAV-04 reachability — publish, View Results, and the CLS-07
     * Submissions reset form must all still be present on the merged
     * editor. The reset form only renders once an attempt exists, so this
     * exam is seeded with one.
     */
    public function test_the_editor_keeps_publish_results_and_reset_submissions_reachable(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.show', $exam));

        $response->assertOk();
        $response->assertSee(route('lecturer.exams.publish', $exam), false);
        $response->assertSee(route('lecturer.results.index', $exam), false);
        $response->assertSee(route('lecturer.exams.submissions.reset', $exam), false);
    }

    /**
     * The unpublish action is the counterpart affordance to publish
     * (CLS-06) — proved separately since it only renders for a published
     * exam.
     */
    public function test_the_editor_keeps_unpublish_reachable_for_a_published_exam(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.show', $exam));

        $response->assertOk();
        $response->assertSee(route('lecturer.exams.unpublish', $exam), false);
    }

    public function test_a_student_is_forbidden_from_the_editor(): void
    {
        $student = User::factory()->student()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($student)->get(route('lecturer.exams.show', $exam));

        $response->assertForbidden();
    }
}

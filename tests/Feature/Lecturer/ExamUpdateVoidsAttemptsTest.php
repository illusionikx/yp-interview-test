<?php

namespace Tests\Feature\Lecturer;

use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 10, Wave 0) — EDT-04's acceptance across all FOUR editor
 * mutations (D-4's three Form Requests plus D-6's fourth site, the inline
 * `abort_if` in `ExamQuestionController::destroy()`), plus INT-02's
 * warning-copy-variance requirement as it applies to the edit page.
 * Relaxing only some of the four gates ships a half-EDT-04 that passes a
 * narrow test — this file exercises all four.
 *
 * Every gate-relaxation method currently 403s (the published-edit gate is
 * still closed — plan 08 relaxes all four sites), so this file is expected
 * to be RED for that reason. The validation-safety method (see its own
 * method-level doc comment below) and the two edit-page copy-variance
 * methods are RED for the same closed gate, since none of them can reach
 * past-gate behavior yet either.
 *
 * Deliberately NOT covered here: `ExamController::destroy()` (whole-exam
 * deletion). Its `abort_if($exam->is_published, 403)` is explicitly
 * UNCHANGED this phase per 10-UI-SPEC.md §1.B — only *editing* relaxes,
 * never whole-exam deletion of a published exam. Do not "fix" this
 * omission; it is intentional.
 */
class ExamUpdateVoidsAttemptsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published exam with one open-text question and three attempts in
     * the three states (in_progress / submitted / graded, distinct
     * students). No pivot use — this fixture never syncs the exam_section
     * pivot — so this file survives plan 06 untouched.
     *
     * @return array{0: Exam, 1: Question, 2: array<int, Attempt>}
     */
    private function attemptedPublishedExam(): array
    {
        $exam = Exam::factory()->published()->create();
        $question = Question::factory()->open()->create(['exam_id' => $exam->id]);

        $attempts = [
            Attempt::factory()->for($exam)->for(User::factory()->student())->create(),
            Attempt::factory()->for($exam)->for(User::factory()->student())->submitted()->create(),
            Attempt::factory()->for($exam)->for(User::factory()->student())->graded(5)->create(),
        ];

        return [$exam, $question, $attempts];
    }

    /**
     * D-7 makes save + void ONE atomic transaction — both halves of this
     * assertion matter: the edit must land AND the attempts must be gone.
     */
    public function test_updating_an_attempted_exams_details_voids_its_attempts(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        [$exam] = $this->attemptedPublishedExam();

        $this->actingAs($lecturer)->put(route('lecturer.exams.update', $exam), [
            'subject_id' => $exam->subject_id,
            'title' => 'Changed',
            'duration_minutes' => $exam->duration_minutes,
        ]);

        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'title' => 'Changed']);
        $this->assertDatabaseCount('attempts', 0);
    }

    public function test_adding_a_question_to_an_attempted_exam_voids_its_attempts(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        [$exam] = $this->attemptedPublishedExam();

        $this->actingAs($lecturer)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'open',
            'body' => 'New question',
            'points' => 1,
        ]);

        $this->assertDatabaseHas('questions', ['exam_id' => $exam->id, 'body' => 'New question']);
        $this->assertDatabaseCount('attempts', 0);
    }

    public function test_updating_a_question_on_an_attempted_exam_voids_its_attempts(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        [$exam, $question] = $this->attemptedPublishedExam();

        $this->actingAs($lecturer)->put(route('lecturer.exams.questions.update', [$exam, $question]), [
            'type' => 'open',
            'body' => 'Updated body',
            'points' => 3,
        ]);

        $this->assertDatabaseHas('questions', ['id' => $question->id, 'body' => 'Updated body']);
        $this->assertDatabaseCount('attempts', 0);
    }

    /**
     * D-6's fourth gate site — question deletion routes through the same
     * warn-and-void flow as the other three editor mutations.
     */
    public function test_deleting_a_question_on_an_attempted_exam_voids_its_attempts(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        [$exam, $question] = $this->attemptedPublishedExam();

        $this->actingAs($lecturer)->delete(route('lecturer.exams.questions.destroy', [$exam, $question]));

        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
        $this->assertDatabaseCount('attempts', 0);
    }

    /**
     * The zero-count branch — friction only exists where risk exists.
     * A published exam with NO attempts saves as a plain edit, with the
     * existing, unchanged "Exam updated." flash.
     */
    public function test_editing_an_exam_with_no_attempts_does_not_touch_anything_and_flashes_the_plain_message(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();

        $response = $this->actingAs($lecturer)->put(route('lecturer.exams.update', $exam), [
            'subject_id' => $exam->subject_id,
            'title' => 'No attempts yet',
            'duration_minutes' => $exam->duration_minutes,
        ]);

        $response->assertSessionHas('status', 'Exam updated.');
    }

    /**
     * Success criterion 5 — the lecturer must see the side effect
     * happened, not just that the save succeeded.
     */
    public function test_a_save_that_voids_attempts_reports_the_side_effect_in_its_flash(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        [$exam] = $this->attemptedPublishedExam();

        $response = $this->actingAs($lecturer)->put(route('lecturer.exams.update', $exam), [
            'subject_id' => $exam->subject_id,
            'title' => 'Changed again',
            'duration_minutes' => $exam->duration_minutes,
        ]);

        $response->assertSessionHas('status', 'Exam updated. 3 affected attempt(s) were reset.');
    }

    /**
     * THE critical safety method. A void that ran before validation would
     * destroy every student's work for a save that never landed — the
     * worst possible outcome. This method is what forbids that ordering.
     */
    public function test_a_failed_validation_on_an_attempted_exam_destroys_no_attempts(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        [$exam] = $this->attemptedPublishedExam();

        $response = $this->actingAs($lecturer)->put(route('lecturer.exams.update', $exam), [
            'subject_id' => $exam->subject_id,
            'title' => '',
            'duration_minutes' => 0,
        ]);

        $response->assertSessionHasErrors(['title', 'duration_minutes']);
        $this->assertDatabaseCount('attempts', 3);
    }

    /**
     * EDT-02 (plan 12-02) folded the standalone edit page into the
     * `exams.show` Details tab — the warning copy this pins now lives
     * there (the Submissions panel), not on a page `exams.edit` renders
     * (it 302s to `exams.show` now).
     */
    public function test_the_editor_warning_names_only_the_ungraded_population_when_none_are_graded(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();
        // Two SEPARATE for()->create() calls, not ->count(2) — for() resolves
        // its related User::factory() once and would otherwise share a
        // single user_id across both replicates, violating
        // attempts.unique(exam_id, user_id).
        Attempt::factory()->for($exam)->for(User::factory()->student())->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.show', $exam));

        $response->assertSee('have started this exam but have not been graded', false);
        $response->assertDontSee('have already been graded', false);
    }

    public function test_the_editor_warning_names_the_graded_population_when_scores_are_at_stake(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        [$exam] = $this->attemptedPublishedExam();

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.show', $exam));

        $response->assertSee('have already been graded', false);
    }
}

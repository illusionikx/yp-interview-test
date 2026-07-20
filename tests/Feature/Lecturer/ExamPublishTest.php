<?php

namespace Tests\Feature\Lecturer;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lecturer_can_publish_a_draft_exam(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create(['is_published' => false]);

        $response = $this->actingAs($lecturer)->patch(route('lecturer.exams.publish', $exam));

        $response->assertRedirect();
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'is_published' => true]);
    }

    /** Success criterion 5 — the publish toast reports the outcome. */
    public function test_publishing_reports_the_outcome_to_the_lecturer(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create(['is_published' => false]);

        $response = $this->actingAs($lecturer)->patch(route('lecturer.exams.publish', $exam));

        $response->assertSessionHas('status', 'Exam published. Students can now see and start it.');
    }

    /** Success criterion 5 — the unpublish toast states attempts are unaffected. */
    public function test_unpublishing_reports_that_existing_attempts_are_unaffected(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();

        $response = $this->actingAs($lecturer)->patch(route('lecturer.exams.unpublish', $exam));

        $response->assertSessionHas('status', 'Exam moved back to draft. Students can no longer start it, but existing attempts are unaffected.');
    }

    public function test_a_lecturer_can_unpublish_a_published_exam(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();

        $response = $this->actingAs($lecturer)->patch(route('lecturer.exams.unpublish', $exam));

        $response->assertRedirect();
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'is_published' => false]);
    }

    public function test_unpublishing_an_exam_makes_it_editable_again(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create(['title' => 'Original Title']);

        $this->actingAs($lecturer)->patch(route('lecturer.exams.unpublish', $exam));
        $exam->refresh();

        $response = $this->actingAs($lecturer)->put(route('lecturer.exams.update', $exam), [
            'subject_id' => $exam->subject_id,
            'title' => 'Updated After Unpublish',
            'duration_minutes' => $exam->duration_minutes,
        ]);

        $response->assertRedirect(route('lecturer.exams.show', $exam));
        $this->assertDatabaseHas('exams', [
            'id' => $exam->id,
            'title' => 'Updated After Unpublish',
        ]);
    }

    public function test_a_student_is_forbidden_from_publishing_an_exam(): void
    {
        $student = User::factory()->student()->create();
        $exam = Exam::factory()->create(['is_published' => false]);

        $response = $this->actingAs($student)->patch(route('lecturer.exams.publish', $exam));

        $response->assertForbidden();
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'is_published' => false]);
    }

    public function test_a_student_is_forbidden_from_unpublishing_an_exam(): void
    {
        $student = User::factory()->student()->create();
        $exam = Exam::factory()->published()->create();

        $response = $this->actingAs($student)->patch(route('lecturer.exams.unpublish', $exam));

        $response->assertForbidden();
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'is_published' => true]);
    }
}

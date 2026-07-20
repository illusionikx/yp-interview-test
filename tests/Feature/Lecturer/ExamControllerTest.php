<?php

namespace Tests\Feature\Lecturer;

use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lecturer_can_create_an_exam(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.store'), [
            'subject_id' => $subject->id,
            'title' => 'Midterm',
            'description' => 'Covers chapters 1-5.',
            'duration_minutes' => 60,
        ]);

        $exam = Exam::firstWhere('title', 'Midterm');

        $response->assertRedirect(route('lecturer.exams.show', $exam));
        $this->assertDatabaseHas('exams', [
            'title' => 'Midterm',
            'subject_id' => $subject->id,
            'duration_minutes' => 60,
            'is_published' => false,
            'created_by' => $lecturer->id,
        ]);
    }

    public function test_a_forged_created_by_field_is_ignored_on_create(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherLecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();

        $this->actingAs($lecturer)->post(route('lecturer.exams.store'), [
            'subject_id' => $subject->id,
            'title' => 'Forged Exam',
            'duration_minutes' => 30,
            'created_by' => $otherLecturer->id,
        ]);

        $this->assertDatabaseHas('exams', [
            'title' => 'Forged Exam',
            'created_by' => $lecturer->id,
        ]);
        $this->assertDatabaseMissing('exams', [
            'title' => 'Forged Exam',
            'created_by' => $otherLecturer->id,
        ]);
    }

    public function test_creating_an_exam_requires_a_title_and_valid_subject(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.store'), [
            'subject_id' => 999999,
            'title' => '',
            'duration_minutes' => 30,
        ]);

        $response->assertSessionHasErrors(['subject_id', 'title']);
        $this->assertSame(0, Exam::count());
    }

    public function test_creating_an_exam_rejects_a_non_positive_duration(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.store'), [
            'subject_id' => $subject->id,
            'title' => 'Bad Duration',
            'duration_minutes' => 0,
        ]);

        $response->assertSessionHasErrors('duration_minutes');
        $this->assertSame(0, Exam::count());
    }

    public function test_a_lecturer_can_edit_a_draft_exam(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create(['title' => 'Old Title']);

        $response = $this->actingAs($lecturer)->put(route('lecturer.exams.update', $exam), [
            'subject_id' => $exam->subject_id,
            'title' => 'New Title',
            'duration_minutes' => $exam->duration_minutes,
        ]);

        $response->assertRedirect(route('lecturer.exams.show', $exam));
        $this->assertDatabaseHas('exams', [
            'id' => $exam->id,
            'title' => 'New Title',
        ]);
    }

    public function test_a_lecturer_can_delete_a_draft_exam(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->delete(route('lecturer.exams.destroy', $exam));

        $response->assertRedirect(route('lecturer.exams.index'));
        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
    }

    /**
     * D-4 retires the draft-only edit gate: editing a published exam is
     * now allowed. This exam has no attempts, so no voiding fires — the
     * edit simply lands (see ExamUpdateVoidsAttemptsTest for the voiding
     * behavior on an ATTEMPTED published exam).
     */
    public function test_editing_a_published_exam_is_allowed(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create(['title' => 'Locked Exam']);

        $response = $this->actingAs($lecturer)->put(route('lecturer.exams.update', $exam), [
            'subject_id' => $exam->subject_id,
            'title' => 'Attempted Change',
            'duration_minutes' => $exam->duration_minutes,
        ]);

        $response->assertRedirect(route('lecturer.exams.show', $exam));
        $this->assertDatabaseHas('exams', [
            'id' => $exam->id,
            'title' => 'Attempted Change',
        ]);
    }

    public function test_deleting_a_published_exam_is_forbidden(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();

        $response = $this->actingAs($lecturer)->delete(route('lecturer.exams.destroy', $exam));

        $response->assertForbidden();
        $this->assertDatabaseHas('exams', ['id' => $exam->id]);
    }

    /**
     * CLS-04 (12-04): the unscoped all-exams listing is folded into the
     * subject hub's Exams tab — this route now redirects home, mirroring
     * SubjectController::index -> home.
     */
    public function test_a_lecturer_visiting_the_exams_index_is_redirected_home(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.index'));

        $response->assertRedirect(route('lecturer.home'));
    }

    public function test_a_student_is_forbidden_from_the_exams_index(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('lecturer.exams.index'));

        $response->assertForbidden();
    }

    public function test_a_student_is_forbidden_from_storing_an_exam(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();

        $response = $this->actingAs($student)->post(route('lecturer.exams.store'), [
            'subject_id' => $subject->id,
            'title' => 'Student Exam',
            'duration_minutes' => 30,
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Exam::count());
    }
}

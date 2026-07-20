<?php

namespace Tests\Feature\Lecturer;

use App\Models\Exam;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lecturer_can_create_a_subject(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.subjects.store'), [
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subjects', [
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);
    }

    public function test_a_lecturer_can_update_a_subjects_name(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($lecturer)->put(route('lecturer.subjects.update', $subject), [
            'name' => 'New Name',
            'code' => $subject->code,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'name' => 'New Name',
        ]);
    }

    public function test_a_lecturer_can_delete_a_subject(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();

        $response = $this->actingAs($lecturer)->delete(route('lecturer.subjects.destroy', $subject));

        $response->assertRedirect();
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    }

    public function test_a_subject_with_classes_cannot_be_deleted(): void
    {
        // CR-01: sections.subject_id and enrollments.section_id both
        // cascadeOnDelete — deleting a subject with classes would silently
        // wipe every class and every student's enrollment. The guard refuses.
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $section = Section::factory()->for($subject)->create();

        $response = $this->actingAs($lecturer)->delete(route('lecturer.subjects.destroy', $subject));

        $response->assertRedirect();
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
        $this->assertDatabaseHas('sections', ['id' => $section->id]);
    }

    public function test_a_subject_with_exams_cannot_be_deleted(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $exam = Exam::factory()->for($subject)->create();

        $response = $this->actingAs($lecturer)->delete(route('lecturer.subjects.destroy', $subject));

        $response->assertRedirect();
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
        $this->assertDatabaseHas('exams', ['id' => $exam->id]);
    }

    public function test_creating_a_subject_with_a_blank_name_fails_validation(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.subjects.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertSame(0, Subject::count());
    }

    public function test_a_student_is_forbidden_from_the_subjects_index(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('lecturer.subjects.index'));

        $response->assertForbidden();
    }

    public function test_a_student_is_forbidden_from_the_subject_create_form(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('lecturer.subjects.create'));

        $response->assertForbidden();
    }

    public function test_a_student_is_forbidden_from_storing_a_subject(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->post(route('lecturer.subjects.store'), [
            'name' => 'Physics',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Subject::count());
    }

    public function test_a_subject_created_by_a_lecturer_appears_on_their_home_list(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $this->actingAs($lecturer)->post(route('lecturer.subjects.store'), [
            'name' => 'Organic Chemistry',
            'code' => 'CHEM201',
        ]);

        $subject = Subject::where('name', 'Organic Chemistry')->firstOrFail();
        $this->assertTrue($lecturer->subjects()->whereKey($subject->id)->exists());

        $response = $this->actingAs($lecturer)->get(route('lecturer.home'));

        $response->assertOk();
        $response->assertSee('Organic Chemistry');
    }
}

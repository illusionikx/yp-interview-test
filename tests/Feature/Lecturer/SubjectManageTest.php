<?php

namespace Tests\Feature\Lecturer;

use App\Enums\EnrollmentStatus;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Support\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CLS-01/CLS-02/CLS-03 — the per-subject two-tab hub: Classes tab default,
 * ?tab=exams deep-link, SEC-03 ownership gate (T-12-01), the enrolled_total
 * progress-bar aggregate, and current-vs-past semester grouping.
 */
class SubjectManageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_assigned_lecturer_sees_the_hub_defaulting_to_the_classes_tab(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);

        $response = $this->actingAs($lecturer)->get(route('lecturer.subjects.manage', $subject));

        $response->assertOk();
        $response->assertSee('Classes');
        $response->assertSee('Exams');
        $response->assertSee("tab: 'classes'", false);
    }

    public function test_tab_query_param_deep_links_to_the_exams_tab(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);

        $response = $this->actingAs($lecturer)->get(route('lecturer.subjects.manage', $subject, absolute: false).'?tab=exams');

        $response->assertOk();
        $response->assertSee("tab: 'exams'", false);
    }

    public function test_a_lecturer_not_assigned_to_the_subject_cannot_open_the_hub(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.subjects.manage', $subject));

        $response->assertForbidden();
    }

    public function test_a_student_cannot_open_the_hub(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();

        $response = $this->actingAs($student)->get(route('lecturer.subjects.manage', $subject));

        $response->assertForbidden();
    }

    public function test_the_students_progress_label_reflects_enrolled_total_and_capacity(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);

        $current = Semester::current();
        $section = Section::factory()->for($subject)->create([
            'year' => $current->year,
            'semester' => $current->number,
            'capacity' => 5,
        ]);

        $studentOne = User::factory()->student()->create();
        $studentTwo = User::factory()->student()->create();
        $section->enrollments()->attach($studentOne->id, ['status' => EnrollmentStatus::Enrolled]);
        $section->enrollments()->attach($studentTwo->id, ['status' => EnrollmentStatus::Enrolled]);

        $response = $this->actingAs($lecturer)->get(route('lecturer.subjects.manage', $subject));

        $response->assertOk();
        $response->assertSee('2 / 5');
    }

    public function test_classes_are_grouped_current_versus_past_with_past_behind_the_toggle(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);

        $current = Semester::current();
        $currentSection = Section::factory()->for($subject)->create([
            'year' => $current->year,
            'semester' => $current->number,
            'capacity' => 10,
        ]);

        $pastSection = Section::factory()->for($subject)->create([
            'year' => $current->year - 5,
            'semester' => 1,
            'capacity' => 10,
        ]);

        $response = $this->actingAs($lecturer)->get(route('lecturer.subjects.manage', $subject));

        $response->assertOk();
        $response->assertSee($currentSection->name);
        $response->assertSee($pastSection->name);
        $response->assertSee('Show past semesters');

        // The current section's row must render before the "Show past
        // semesters" toggle; the past section's row must render after it —
        // proving the past group lives inside the collapsed region.
        $content = $response->getContent();
        $togglePosition = strpos($content, 'Show past semesters');
        $currentPosition = strpos($content, $currentSection->name);
        $pastPosition = strpos($content, $pastSection->name);

        $this->assertLessThan($togglePosition, $currentPosition);
        $this->assertGreaterThan($togglePosition, $pastPosition);
    }

    public function test_create_class_link_and_delete_form_target_the_expected_routes(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);

        $current = Semester::current();
        $section = Section::factory()->for($subject)->create([
            'year' => $current->year,
            'semester' => $current->number,
        ]);

        $response = $this->actingAs($lecturer)->get(route('lecturer.subjects.manage', $subject));

        $response->assertOk();
        $response->assertSee(route('lecturer.subjects.sections.create', $subject), false);
        $response->assertSee(route('lecturer.subjects.sections.destroy', [$subject, $section]), false);
    }
}

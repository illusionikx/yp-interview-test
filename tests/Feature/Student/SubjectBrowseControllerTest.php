<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 8, Wave 0) — ENR-01 (live capacity display) and ENR-06 (never
 * hide out-of-window sections, only disable the Apply action). Expected RED
 * until App\Http\Controllers\Student\SubjectBrowseController and the
 * student.subjects.index/show routes land (08-04) — every method here
 * currently errors on route() resolution (RouteNotFoundException), the
 * correct RED reason: nothing on the student side of enrollment exists yet.
 */
class SubjectBrowseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_sees_a_sections_live_capacity(): void
    {
        $subject = Subject::factory()->create();
        $section = Section::factory()->create(['subject_id' => $subject->id, 'capacity' => 30]);
        $section->enrollments()->attach(User::factory()->student()->create()->id, ['status' => EnrollmentStatus::Enrolled]);
        $section->enrollments()->attach(User::factory()->student()->create()->id, ['status' => EnrollmentStatus::Enrolled]);
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.subjects.show', $subject));

        $response->assertOk();
        $response->assertSee('2/30');
    }

    public function test_a_section_at_capacity_shows_the_full_pill_and_offers_no_apply_action(): void
    {
        $subject = Subject::factory()->create();
        $section = Section::factory()->create(['subject_id' => $subject->id, 'capacity' => 1]);
        $section->enrollments()->attach(User::factory()->student()->create()->id, ['status' => EnrollmentStatus::Enrolled]);
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.subjects.show', $subject));

        $response->assertOk();
        $response->assertSee('FULL');
        // Assert against the section's own POST url rather than the word
        // "Apply" — a second, eligible section's button on the same page
        // must not be able to mask this assertion.
        $response->assertDontSee(route('student.sections.enroll', $section), false);
    }

    public function test_a_section_whose_window_has_not_opened_is_listed_but_offers_no_apply_action(): void
    {
        $subject = Subject::factory()->create();
        $section = Section::factory()->create([
            'subject_id' => $subject->id,
            'opens_at' => now()->addDay(),
            'closes_at' => now()->addDays(14),
        ]);
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.subjects.show', $subject));

        $response->assertOk();
        // ENR-06/Decision #5 — never hidden, only the Apply action is withheld.
        $response->assertSee($section->name);
        $response->assertDontSee(route('student.sections.enroll', $section), false);
    }

    public function test_a_section_whose_window_has_closed_is_listed_but_offers_no_apply_action(): void
    {
        $subject = Subject::factory()->create();
        $section = Section::factory()->create([
            'subject_id' => $subject->id,
            'opens_at' => now()->subDays(14),
            'closes_at' => now()->subDay(),
        ]);
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.subjects.show', $subject));

        $response->assertOk();
        $response->assertSee($section->name);
        $response->assertDontSee(route('student.sections.enroll', $section), false);
    }

    public function test_an_open_non_full_section_offers_an_apply_action(): void
    {
        $subject = Subject::factory()->create();
        $section = Section::factory()->create(['subject_id' => $subject->id, 'capacity' => 30]);
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.subjects.show', $subject));

        $response->assertOk();
        $response->assertSee(route('student.sections.enroll', $section), false);
    }

    public function test_the_subject_index_lists_subjects_that_have_sections(): void
    {
        $subjectWithSection = Subject::factory()->create();
        Section::factory()->create(['subject_id' => $subjectWithSection->id]);
        Subject::factory()->create(); // a subject with no sections at all
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.subjects.index'));

        $response->assertOk();
        $response->assertSee($subjectWithSection->name);
    }
}

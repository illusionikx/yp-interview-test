<?php

namespace Tests\Feature\Lecturer;

use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 7, Wave 0) — SEC-01/SEC-02 section CRUD contract, nested under
 * a subject. Mirrors ClassroomControllerTest 1:1 (Classroom -> Section),
 * plus SEC-01's computed year-semester-sequence name and per-(subject,
 * year, semester) auto-incrementing sequence.
 *
 * Expected RED until Section/SectionController and the sections schema
 * land (07-03/07-04) — App\Models\Section does not exist yet, and the
 * lecturer.subjects.sections.* routes are not registered yet.
 */
class SectionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lecturer_assigned_to_the_subject_can_create_a_section(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);

        $response = $this->actingAs($lecturer)->post(route('lecturer.subjects.sections.store', $subject), [
            'year' => 2026,
            'semester' => 2,
            'capacity' => 30,
            'opens_at' => now()->toDateString(),
            'closes_at' => now()->addWeek()->toDateString(),
        ]);

        // 12-01: store() now redirects to the per-subject hub, not the
        // top-level lecturer.sections.index listing.
        $response->assertRedirect(route('lecturer.subjects.manage', $subject));
        $this->assertDatabaseHas('sections', [
            'subject_id' => $subject->id,
            'year' => 2026,
            'semester' => 2,
            'capacity' => 30,
        ]);
    }

    /**
     * CLS-03: a location value supplied on create persists; omitting it
     * still succeeds with a null location (nullable column, T-12-02).
     */
    public function test_a_stored_location_value_persists(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);

        $this->actingAs($lecturer)->post(route('lecturer.subjects.sections.store', $subject), [
            'year' => 2026,
            'semester' => 2,
            'capacity' => 30,
            'location' => 'Room 204',
            'opens_at' => now()->toDateString(),
            'closes_at' => now()->addWeek()->toDateString(),
        ]);

        $this->assertDatabaseHas('sections', [
            'subject_id' => $subject->id,
            'location' => 'Room 204',
        ]);
    }

    public function test_omitting_location_on_create_succeeds_with_a_null_location(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);

        $response = $this->actingAs($lecturer)->post(route('lecturer.subjects.sections.store', $subject), [
            'year' => 2026,
            'semester' => 2,
            'capacity' => 30,
            'opens_at' => now()->toDateString(),
            'closes_at' => now()->addWeek()->toDateString(),
        ]);

        $response->assertRedirect(route('lecturer.subjects.manage', $subject));
        $this->assertDatabaseHas('sections', [
            'subject_id' => $subject->id,
            'location' => null,
        ]);
    }

    /**
     * WR-01 regression: create()/edit() GET routes must be ownership-gated
     * the same as the write paths, not merely a hidden UI affordance.
     */
    public function test_a_lecturer_not_assigned_to_the_subject_cannot_view_the_create_form(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.subjects.sections.create', $subject));

        $response->assertForbidden();
    }

    public function test_a_lecturer_not_assigned_to_the_subject_cannot_view_the_edit_form(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $section = Section::factory()->create(['subject_id' => $subject->id]);

        $response = $this->actingAs($lecturer)->get(route('lecturer.subjects.sections.edit', [$subject, $section]));

        $response->assertForbidden();
    }

    public function test_a_lecturer_assigned_to_the_subject_can_update_a_sections_capacity(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);
        $section = Section::factory()->create(['subject_id' => $subject->id, 'capacity' => 20]);

        $response = $this->actingAs($lecturer)->put(route('lecturer.subjects.sections.update', [$subject, $section]), [
            'year' => $section->year,
            'semester' => $section->semester,
            'capacity' => 40,
            'opens_at' => $section->opens_at,
            'closes_at' => $section->closes_at,
        ]);

        // 12-01: update() now redirects to the per-subject hub, not the
        // top-level lecturer.sections.index listing.
        $response->assertRedirect(route('lecturer.subjects.manage', $subject));
        $this->assertDatabaseHas('sections', [
            'id' => $section->id,
            'capacity' => 40,
        ]);
    }

    /**
     * WR-02 regression: editing year/semester into a combination that
     * collides with another section's (subject_id, year, semester,
     * sequence) must return a friendly 422, not an unhandled 500.
     */
    public function test_editing_a_section_into_a_colliding_year_semester_returns_a_validation_error(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);
        $sectionA = Section::factory()->create([
            'subject_id' => $subject->id,
            'year' => 2026,
            'semester' => 1,
            'sequence' => 1,
        ]);
        $sectionB = Section::factory()->create([
            'subject_id' => $subject->id,
            'year' => 2027,
            'semester' => 1,
            'sequence' => 1,
        ]);

        $response = $this->actingAs($lecturer)->put(route('lecturer.subjects.sections.update', [$subject, $sectionB]), [
            'year' => $sectionA->year,
            'semester' => $sectionA->semester,
            'capacity' => $sectionB->capacity,
            'opens_at' => $sectionB->opens_at,
            'closes_at' => $sectionB->closes_at,
        ]);

        $response->assertSessionHasErrors('year');
        $this->assertDatabaseHas('sections', ['id' => $sectionB->id, 'year' => 2027]);
    }

    public function test_a_lecturer_assigned_to_the_subject_can_delete_a_section(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);
        $section = Section::factory()->create(['subject_id' => $subject->id]);

        $response = $this->actingAs($lecturer)->delete(route('lecturer.subjects.sections.destroy', [$subject, $section]));

        // 12-01: destroy() now redirects to the per-subject hub, not the
        // top-level lecturer.sections.index listing.
        $response->assertRedirect(route('lecturer.subjects.manage', $subject));
        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
    }

    public function test_creating_a_section_with_a_blank_capacity_fails_validation(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);

        $response = $this->actingAs($lecturer)->post(route('lecturer.subjects.sections.store', $subject), [
            'year' => 2026,
            'semester' => 2,
            'capacity' => '',
            'opens_at' => now()->toDateString(),
            'closes_at' => now()->addWeek()->toDateString(),
        ]);

        $response->assertSessionHasErrors('capacity');
        $this->assertSame(0, Section::where('subject_id', $subject->id)->count());
    }

    public function test_creating_a_section_with_closes_at_before_opens_at_fails_validation(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);

        $response = $this->actingAs($lecturer)->post(route('lecturer.subjects.sections.store', $subject), [
            'year' => 2026,
            'semester' => 2,
            'capacity' => 30,
            'opens_at' => now()->addWeek()->toDateString(),
            'closes_at' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('closes_at');
        $this->assertSame(0, Section::where('subject_id', $subject->id)->count());
    }

    public function test_a_created_sections_computed_name_is_year_semester_sequence(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);

        $this->actingAs($lecturer)->post(route('lecturer.subjects.sections.store', $subject), [
            'year' => 2026,
            'semester' => 2,
            'capacity' => 30,
            'opens_at' => now()->toDateString(),
            'closes_at' => now()->addWeek()->toDateString(),
        ]);

        $section = Section::where('subject_id', $subject->id)->firstOrFail();

        $this->assertSame('2026-2-1', $section->name);
    }

    public function test_sequence_auto_increments_per_subject_year_and_semester(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);

        $payload = [
            'year' => 2026,
            'semester' => 2,
            'capacity' => 30,
            'opens_at' => now()->toDateString(),
            'closes_at' => now()->addWeek()->toDateString(),
        ];

        $this->actingAs($lecturer)->post(route('lecturer.subjects.sections.store', $subject), $payload);
        $this->actingAs($lecturer)->post(route('lecturer.subjects.sections.store', $subject), $payload);

        $sections = Section::where('subject_id', $subject->id)
            ->where('year', 2026)
            ->where('semester', 2)
            ->orderBy('sequence')
            ->get();

        $this->assertSame(2, $sections->count());
        $this->assertSame(1, $sections[0]->sequence);
        $this->assertSame(2, $sections[1]->sequence);
    }
}

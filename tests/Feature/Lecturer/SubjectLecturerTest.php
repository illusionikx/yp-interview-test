<?php

namespace Tests\Feature\Lecturer;

use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 7, Wave 0) — SEC-03 per-subject ownership contract: a subject
 * can be assigned to multiple lecturers, any assigned lecturer can manage
 * its sections, and a lecturer NOT assigned to the subject is denied. This
 * is a genuine divergence from the existing D-09 "role-middleware-only, no
 * per-record ownership" convention (07-RESEARCH.md Pattern 2 / Assumption
 * A4) — StoreSectionRequest/UpdateSectionRequest/AssignLecturerRequest
 * must NOT copy authorize(): bool { return true; } from StoreSubjectRequest/
 * StoreClassroomRequest.
 *
 * Expected RED until the subject_user schema, SubjectLecturerController,
 * and Section/SectionController land (07-03/07-04) — App\Models\Section
 * does not exist yet and Subject::lecturers() is not defined yet.
 */
class SubjectLecturerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lecturer_can_be_assigned_to_a_subject(): void
    {
        $owner = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($owner);
        $newLecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($owner)->post(route('lecturer.subjects.lecturers.store', $subject), [
            'user_id' => $newLecturer->id,
        ]);

        $response->assertRedirect();
        $this->assertTrue($subject->lecturers()->whereKey($newLecturer->id)->exists());
    }

    public function test_a_subject_can_hold_multiple_lecturers(): void
    {
        $subject = Subject::factory()->create();
        $lecturerA = User::factory()->lecturer()->create();
        $lecturerB = User::factory()->lecturer()->create();

        $subject->lecturers()->attach([$lecturerA->id, $lecturerB->id]);

        $this->assertSame(2, $subject->lecturers()->count());
        $this->assertTrue($subject->lecturers()->whereKey($lecturerA->id)->exists());
        $this->assertTrue($subject->lecturers()->whereKey($lecturerB->id)->exists());
    }

    public function test_any_assigned_lecturer_can_manage_the_subjects_sections(): void
    {
        $subject = Subject::factory()->create();
        $lecturerA = User::factory()->lecturer()->create();
        $lecturerB = User::factory()->lecturer()->create();
        $subject->lecturers()->attach([$lecturerA->id, $lecturerB->id]);

        $response = $this->actingAs($lecturerB)->post(route('lecturer.subjects.sections.store', $subject), [
            'year' => 2026,
            'semester' => 1,
            'capacity' => 25,
            'opens_at' => now()->toDateString(),
            'closes_at' => now()->addWeek()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sections', ['subject_id' => $subject->id, 'capacity' => 25]);
    }

    public function test_a_lecturer_not_assigned_to_the_subject_is_forbidden_from_creating_a_section(): void
    {
        $subject = Subject::factory()->create();
        $outsider = User::factory()->lecturer()->create();

        $response = $this->actingAs($outsider)->post(route('lecturer.subjects.sections.store', $subject), [
            'year' => 2026,
            'semester' => 1,
            'capacity' => 25,
            'opens_at' => now()->toDateString(),
            'closes_at' => now()->addWeek()->toDateString(),
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Section::where('subject_id', $subject->id)->count());
    }

    public function test_a_lecturer_not_assigned_to_the_subject_is_forbidden_from_editing_a_section(): void
    {
        $subject = Subject::factory()->create();
        $owner = User::factory()->lecturer()->create();
        $subject->lecturers()->attach($owner);
        $section = Section::factory()->create(['subject_id' => $subject->id, 'capacity' => 20]);
        $outsider = User::factory()->lecturer()->create();

        $response = $this->actingAs($outsider)->put(route('lecturer.subjects.sections.update', [$subject, $section]), [
            'year' => $section->year,
            'semester' => $section->semester,
            'capacity' => 99,
            'opens_at' => $section->opens_at,
            'closes_at' => $section->closes_at,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('sections', ['id' => $section->id, 'capacity' => 99]);
    }

    public function test_a_lecturer_not_assigned_to_the_subject_is_forbidden_from_assigning_lecturers(): void
    {
        $subject = Subject::factory()->create();
        $outsider = User::factory()->lecturer()->create();
        $target = User::factory()->lecturer()->create();

        $response = $this->actingAs($outsider)->post(route('lecturer.subjects.lecturers.store', $subject), [
            'user_id' => $target->id,
        ]);

        $response->assertForbidden();
        $this->assertFalse($subject->lecturers()->whereKey($target->id)->exists());
    }

    public function test_a_lecturer_not_assigned_to_the_subject_is_forbidden_from_unassigning_lecturers(): void
    {
        $subject = Subject::factory()->create();
        $owner = User::factory()->lecturer()->create();
        $subject->lecturers()->attach($owner);
        $outsider = User::factory()->lecturer()->create();

        $response = $this->actingAs($outsider)->delete(route('lecturer.subjects.lecturers.destroy', [$subject, $owner]));

        $response->assertForbidden();
        $this->assertTrue($subject->lecturers()->whereKey($owner->id)->exists());
    }

    public function test_unassigning_a_lecturer_detaches_the_subject_user_pivot_row(): void
    {
        $subject = Subject::factory()->create();
        $owner = User::factory()->lecturer()->create();
        $other = User::factory()->lecturer()->create();
        $subject->lecturers()->attach([$owner->id, $other->id]);

        $response = $this->actingAs($owner)->delete(route('lecturer.subjects.lecturers.destroy', [$subject, $other]));

        $response->assertRedirect();
        $this->assertFalse($subject->lecturers()->whereKey($other->id)->exists());
    }
}

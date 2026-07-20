<?php

namespace Tests\Feature\Navigation;

use App\Models\Exam;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 11, Plan 01 (UX-04) — proves x-back-button renders on the
 * retrofitted lecturer create/edit views as a styled button-anchor whose
 * visible text names its destination, not a bare "Cancel"/"Back" link.
 * Doubles as the component's render smoke test (referenced by Task 2's
 * verify step).
 *
 * Phase 12, Plan 02 (EDT-02) folded the standalone exams.edit page into
 * the exams.show two-tab editor, so the exams back-button assertion moved
 * from exams.edit to exams.show.
 */
class BackButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_exams_create_shows_a_back_to_exams_button(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Subject::factory()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.create'));

        $response->assertOk();
        $response->assertSee('Back to exams');
        // Button styling token asserted, not just the copy — proves this is
        // a styled button, not a bare underlined link.
        $response->assertSee('rounded-lg', false);
    }

    /**
     * EDT-02 (plan 12-02) folded the standalone exams.edit page into the
     * `exams.show` two-tab editor — `exams.edit` now 302s there instead of
     * rendering its own back button, so the button (retargeted to the
     * subject's Exams tab, "Back to exams") is asserted on `exams.show`.
     */
    public function test_exams_show_shows_a_back_to_exams_button(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.show', $exam));

        $response->assertOk();
        $response->assertSee('Back to exams');
        // Button styling token asserted, not just the copy — proves this is
        // a styled button, not a bare underlined link.
        $response->assertSee('rounded-lg', false);
    }

    public function test_sections_create_shows_a_back_to_classes_button(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);

        $response = $this->actingAs($lecturer)->get(route('lecturer.subjects.sections.create', $subject));

        $response->assertOk();
        $response->assertSee('Back to classes');
    }

    public function test_sections_edit_shows_a_back_to_classes_button(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);
        $section = Section::factory()->for($subject)->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.subjects.sections.edit', [$subject, $section]));

        $response->assertOk();
        $response->assertSee('Back to classes');
    }
}

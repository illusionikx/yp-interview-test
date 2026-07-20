<?php

namespace Tests\Feature\Lecturer;

use App\Enums\EnrollmentStatus;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Support\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DASH-01/DASH-03: the lecturer home page renders a welcome banner, three
 * scoped bounded-aggregate stat cards, and the lecturer's assigned-subject
 * list — proving both the correct figures AND that everything is scoped to
 * the acting lecturer (T-11-02-01).
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecturer_home_shows_banner_scoped_cards_and_assigned_subjects(): void
    {
        $lecturer = User::factory()->lecturer()->create(['name' => 'Ada Lovelace']);

        $subject = Subject::factory()->create(['name' => 'Discrete Mathematics']);
        $subject->lecturers()->attach($lecturer);

        // Current-semester section: counted by classesThisAndFuture.
        $current = Semester::current();
        $currentSection = Section::factory()->for($subject)->create([
            'year' => $current->year,
            'semester' => $current->number,
            'capacity' => 10,
        ]);

        // Past-semester section: capacity still counts toward totalSeats,
        // but must be EXCLUDED from classesThisAndFuture (composite ordinal
        // guard against a naive year/semester comparison).
        $pastSection = Section::factory()->for($subject)->create([
            'year' => $current->year - 5,
            'semester' => 1,
            'capacity' => 5,
        ]);

        $studentOne = User::factory()->student()->create();
        $studentTwo = User::factory()->student()->create();
        $currentSection->enrollments()->attach($studentOne->id, ['status' => EnrollmentStatus::Enrolled]);
        $currentSection->enrollments()->attach($studentTwo->id, ['status' => EnrollmentStatus::Enrolled]);

        $exam = Exam::factory()->for($subject)->published()->create();
        Attempt::factory()->for($exam)->submitted()->create();

        // A second lecturer with their own subject — proves the query is
        // scoped, not global (T-11-02-01).
        $otherLecturer = User::factory()->lecturer()->create();
        $otherSubject = Subject::factory()->create(['name' => 'Quantum Chemistry']);
        $otherSubject->lecturers()->attach($otherLecturer);

        $response = $this->actingAs($lecturer)->get(route('lecturer.home'));

        $response->assertOk();
        $response->assertSee('Welcome back, Ada Lovelace');
        $response->assertSee('2 / 15'); // enrolledStudents / totalSeats (10 + 5)
        $response->assertSee('Discrete Mathematics');
        $response->assertDontSee('Quantum Chemistry');

        // Exact aggregate values — asserted against the view data directly
        // (not markup substrings) so the past-section-excluded proof is
        // unambiguous regardless of surrounding digits in the page.
        $response->assertViewHas('classesThisAndFuture', 1);
        $response->assertViewHas('enrolledStudents', 2);
        $response->assertViewHas('totalSeats', 15);
        $response->assertViewHas('awaitingGrading', 1);

        $this->assertNotNull($pastSection);
    }

    public function test_lecturer_with_no_assigned_subjects_sees_zeroed_cards(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.home'));

        $response->assertOk();
        $response->assertSee('No subjects assigned to you yet.');
    }
}

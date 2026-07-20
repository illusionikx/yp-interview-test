<?php

namespace Tests\Feature\Student;

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
 * DASH-04: the student home page renders a welcome banner and two scoped
 * bounded-aggregate stat cards — proving both the correct figures AND that
 * the "this semester" aggregate is scoped by the composite ordinal (a past
 * enrollment must not count) and that "exams available" excludes exams the
 * student has already attempted (T-11-03-01/T-11-03-02).
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_current_semester_count_excluding_past_and_available_exams(): void
    {
        $student = User::factory()->student()->create(['name' => 'Grace Hopper']);
        $lecturer = User::factory()->lecturer()->create();

        $currentSubject = Subject::factory()->create();
        $currentSubject->lecturers()->attach($lecturer);

        $current = Semester::current();
        $currentSection = Section::factory()->for($currentSubject)->create([
            'year' => $current->year,
            'semester' => $current->number,
        ]);
        $currentSection->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        // Past-semester enrollment (a different subject) — MUST be excluded
        // from subjectsThisSemester (composite ordinal guard against a
        // naive year/semester comparison).
        $pastSubject = Subject::factory()->create();
        $pastSection = Section::factory()->for($pastSubject)->create([
            'year' => $current->year - 1,
            'semester' => $current->number,
        ]);
        $pastSection->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $exam = Exam::factory()->for($currentSubject)->published()->available()->create();

        $response = $this->actingAs($student)->get(route('student.home'));

        $response->assertOk();
        $response->assertSee('Welcome back, Grace Hopper');
        $response->assertViewHas('subjectsThisSemester', 1);
        $response->assertViewHas('examsAvailable', 1);

        // Take the exam — the available count must drop.
        Attempt::factory()->for($exam)->for($student)->create();

        $response = $this->actingAs($student)->get(route('student.home'));

        $response->assertOk();
        $response->assertViewHas('examsAvailable', 0);
    }

    public function test_student_with_no_enrollments_sees_zeroed_cards(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.home'));

        $response->assertOk();
        $response->assertViewHas('subjectsThisSemester', 0);
        $response->assertViewHas('examsAvailable', 0);
        $response->assertSee("You're not enrolled in any subjects yet.");
    }
}

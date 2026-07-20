<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Support\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUBJ-03/SUBJ-04/SUBJ-05: the student home page's NEW enrolled-subjects
 * list (distinct from SubjectBrowseController's catalog) — proving the
 * lecturer name renders per row, past semesters are collapsed behind a
 * showPast Alpine toggle, the enroll button targets the Class enrollment
 * page, and each row's action targets the (interim) class page.
 */
class SubjectListTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_current_subject_lecturer_and_past_subject_behind_toggle(): void
    {
        $student = User::factory()->student()->create();
        $lecturer = User::factory()->lecturer()->create(['name' => 'Ada Lovelace']);

        $currentSubject = Subject::factory()->create(['name' => 'Discrete Mathematics']);
        $currentSubject->lecturers()->attach($lecturer);

        $current = Semester::current();
        $currentSection = Section::factory()->for($currentSubject)->create([
            'year' => $current->year,
            'semester' => $current->number,
        ]);
        $currentSection->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $pastSubject = Subject::factory()->create(['name' => 'Quantum Chemistry']);
        $pastSection = Section::factory()->for($pastSubject)->create([
            'year' => $current->year - 1,
            'semester' => $current->number,
        ]);
        $pastSection->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $response = $this->actingAs($student)->get(route('student.home'));

        $response->assertOk();

        // Current subject and its lecturer's name are in the always-visible
        // region.
        $response->assertSee('Discrete Mathematics');
        $response->assertSee('Ada Lovelace');

        // The past subject is present in the response (it lives in the
        // collapsed-by-default region), and the page carries the toggle
        // control that gates it.
        $response->assertSee('Quantum Chemistry');
        $response->assertSee('showPast', false);
        $response->assertSee('Show past semesters');

        // The enroll button and each row's class-page action link to the
        // correct routes.
        $response->assertSee(route('student.subjects.index'), false);
        $response->assertSee(route('student.subjects.class', $currentSubject), false);
        $response->assertSee(route('student.subjects.class', $pastSubject), false);
    }

    public function test_home_renders_with_multiple_enrolled_subjects_without_error(): void
    {
        $student = User::factory()->student()->create();
        $current = Semester::current();

        foreach (range(1, 4) as $i) {
            $subject = Subject::factory()->create();
            $lecturer = User::factory()->lecturer()->create();
            $subject->lecturers()->attach($lecturer);

            $section = Section::factory()->for($subject)->create([
                'year' => $current->year,
                'semester' => $current->number,
            ]);
            $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);
        }

        $response = $this->actingAs($student)->get(route('student.home'));

        $response->assertOk();
    }

    public function test_subject_with_no_assigned_lecturer_shows_unassigned(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();

        $current = Semester::current();
        $section = Section::factory()->for($subject)->create([
            'year' => $current->year,
            'semester' => $current->number,
        ]);
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $response = $this->actingAs($student)->get(route('student.home'));

        $response->assertOk();
        $response->assertSee('Unassigned');
    }
}

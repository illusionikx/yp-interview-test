<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ENR-09/ENR-10/ENR-11 — the single-page "Class enrollment" flow:
 * select subject -> select class -> enroll, with no across-subject credit
 * limit and enroll offered only for currently-open enrollment windows.
 * SubjectBrowseController@index and @show, and EnrollmentController@store,
 * are unchanged backends (Decision #3) — this proves only the presentation
 * layer built on top of them.
 */
class EnrollmentPageTest extends TestCase
{
    use RefreshDatabase;

    private function openSection(?int $subjectId = null, int $capacity = 30, int $year = 2026, int $semester = 1): Section
    {
        $subjectId ??= Subject::factory()->create()->id;

        $sequence = Section::where('subject_id', $subjectId)
            ->where('year', $year)
            ->where('semester', $semester)
            ->max('sequence') + 1;

        return Section::factory()->create([
            'subject_id' => $subjectId,
            'capacity' => $capacity,
            'year' => $year,
            'semester' => $semester,
            'sequence' => $sequence,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(14),
        ]);
    }

    public function test_single_page_lists_subjects_and_reveals_classes(): void
    {
        $subject = Subject::factory()->create();
        $section = $this->openSection($subject->id);
        $student = User::factory()->student()->create();

        $indexResponse = $this->actingAs($student)->get(route('student.subjects.index'));

        $indexResponse->assertOk();
        $indexResponse->assertSee('Class enrollment');
        $indexResponse->assertSee($subject->name);

        $selectedResponse = $this->actingAs($student)->get(route('student.subjects.index', ['subject' => $subject->id]));

        $selectedResponse->assertOk();
        $selectedResponse->assertSee($section->name);
        $selectedResponse->assertSee(route('student.sections.enroll', $section), false);
    }

    public function test_only_open_window_classes_are_enrollable(): void
    {
        $subject = Subject::factory()->create();

        $notYetOpen = Section::factory()->create([
            'subject_id' => $subject->id,
            'year' => 2026,
            'semester' => 1,
            'sequence' => 1,
            'opens_at' => now()->addDay(),
            'closes_at' => now()->addDays(14),
        ]);

        $closed = Section::factory()->create([
            'subject_id' => $subject->id,
            'year' => 2026,
            'semester' => 1,
            'sequence' => 2,
            'opens_at' => now()->subDays(14),
            'closes_at' => now()->subDay(),
        ]);

        $open = Section::factory()->create([
            'subject_id' => $subject->id,
            'year' => 2026,
            'semester' => 1,
            'sequence' => 3,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(14),
        ]);

        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.subjects.index', ['subject' => $subject->id]));

        $response->assertOk();

        // ENR-11/ENR-06 — never hidden, only the enroll action is withheld.
        $response->assertSee($notYetOpen->name);
        $response->assertDontSee(route('student.sections.enroll', $notYetOpen), false);

        $response->assertSee($closed->name);
        $response->assertDontSee(route('student.sections.enroll', $closed), false);

        $response->assertSee($open->name);
        $response->assertSee(route('student.sections.enroll', $open), false);
    }

    public function test_no_credit_limit_across_subjects(): void
    {
        $subjectA = Subject::factory()->create();
        $subjectB = Subject::factory()->create();
        $sectionA = $this->openSection($subjectA->id);
        $sectionB = $this->openSection($subjectB->id);
        $student = User::factory()->student()->create();

        $this->actingAs($student)->post(route('student.sections.enroll', $sectionA));

        $this->assertDatabaseHas('enrollments', [
            'section_id' => $sectionA->id,
            'user_id' => $student->id,
            'status' => 'enrolled',
        ]);

        // Still offered on the page for a different subject...
        $pageResponse = $this->actingAs($student)->get(route('student.subjects.index', ['subject' => $subjectB->id]));
        $pageResponse->assertOk();
        $pageResponse->assertSee(route('student.sections.enroll', $sectionB), false);

        // ...and the second enroll, in a different subject, succeeds.
        $this->actingAs($student)->post(route('student.sections.enroll', $sectionB));

        $this->assertDatabaseHas('enrollments', [
            'section_id' => $sectionB->id,
            'user_id' => $student->id,
            'status' => 'enrolled',
        ]);

        $this->assertSame(2, \App\Models\Enrollment::where('user_id', $student->id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->count());
    }
}

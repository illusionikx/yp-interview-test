<?php

namespace Tests\Feature\Lecturer;

use App\Enums\EnrollmentStatus;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GRD-06 — the grading page (lecturer.results.index) is the "what is this
 * exam, how far is grading, and who still needs it" entry point: an exam-
 * details + grading-progress header above the full student attempt list.
 * The progress figure must be a single bounded aggregate
 * (AttemptVoider::summarize()), never a per-attempt loop — asserted here via
 * assertViewHas('progress', ...) on the exact computed array, not just
 * assertSee of the rendered numbers.
 */
class GradingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_grading_page_shows_exam_details_progress_and_full_student_list(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);

        $gradedStudent = User::factory()->student()->create();
        $submittedStudentA = User::factory()->student()->create();
        $submittedStudentB = User::factory()->student()->create();

        $section->enrollments()->syncWithoutDetaching([
            $gradedStudent->id => ['status' => EnrollmentStatus::Enrolled],
            $submittedStudentA->id => ['status' => EnrollmentStatus::Enrolled],
            $submittedStudentB->id => ['status' => EnrollmentStatus::Enrolled],
        ]);

        // 1 graded, 2 submitted-ungraded -> gradable total of 3, "1 / 3" graded.
        Attempt::factory()->graded(score: 4)->create([
            'exam_id' => $exam->id,
            'user_id' => $gradedStudent->id,
        ]);
        Attempt::factory()->submitted()->create([
            'exam_id' => $exam->id,
            'user_id' => $submittedStudentA->id,
        ]);
        $submittedAttemptB = Attempt::factory()->submitted()->create([
            'exam_id' => $exam->id,
            'user_id' => $submittedStudentB->id,
        ]);

        $response = $this->actingAs($lecturer)->get(route('lecturer.results.index', $exam));

        $response->assertOk();

        // Exam details.
        $response->assertSee($exam->title);
        $response->assertSee($exam->subject->name);

        // Grading progress — bounded aggregate, asserted on the exact
        // computed array (not just the rendered text) so this test would
        // catch a divergent/looped recomputation.
        $response->assertViewHas('progress', [
            'graded' => 1,
            'needingGrading' => 2,
            'gradableTotal' => 3,
        ]);
        $response->assertSee('1 / 3');

        // Full list — every seeded attempt's student, each with a Grade/View
        // action into results.show.
        $response->assertSee($gradedStudent->name);
        $response->assertSee($submittedStudentA->name);
        $response->assertSee($submittedStudentB->name);
        $response->assertSee(route('lecturer.results.show', [$exam, $submittedAttemptB]), false);
    }

    public function test_grading_page_shows_no_submissions_message_when_nothing_gradable(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.results.index', $exam));

        $response->assertOk();
        $response->assertViewHas('progress', [
            'graded' => 0,
            'needingGrading' => 0,
            'gradableTotal' => 0,
        ]);
        $response->assertSee('No submissions to grade yet.');
    }
}

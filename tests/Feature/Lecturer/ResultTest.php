<?php

namespace Tests\Feature\Lecturer;

use App\Enums\EnrollmentStatus;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 5, Wave 0) — pins the lecturer per-exam results contract
 * (GRD-05, D-06). The results routes/controller/views do not exist yet:
 * every method here is expected to fail on a missing-route error
 * (RouteNotFoundException) or a 404/500, never a parse error.
 *
 * Fixed route contract this plan pins for Waves 2-4:
 *   GET lecturer/exams/{exam}/results          -> lecturer.results.index
 *   GET lecturer/exams/{exam}/results/{attempt} -> lecturer.results.show
 */
class ResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_attempts_per_exam(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);

        $studentA = User::factory()->student()->create();
        $studentB = User::factory()->student()->create();
        $section->enrollments()->syncWithoutDetaching([
            $studentA->id => ['status' => EnrollmentStatus::Enrolled],
            $studentB->id => ['status' => EnrollmentStatus::Enrolled],
        ]);

        Attempt::factory()->submitted()->create([
            'exam_id' => $exam->id,
            'user_id' => $studentA->id,
        ]);
        Attempt::factory()->graded(score: 4)->create([
            'exam_id' => $exam->id,
            'user_id' => $studentB->id,
        ]);

        $response = $this->actingAs($lecturer)->get(route('lecturer.results.index', $exam));

        $response->assertOk();
        $response->assertSee($studentA->name);
        $response->assertSee($studentB->name);
    }

    public function test_show_renders_breakdown(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $question = Question::factory()->mcq()->create(['exam_id' => $exam->id, 'points' => 3]);

        $attempt = Attempt::factory()->graded(score: 3)->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        Answer::factory()->mcqCorrect($question)->create([
            'attempt_id' => $attempt->id,
            'is_correct' => true,
            'score' => 3,
        ]);

        $response = $this->actingAs($lecturer)->get(route('lecturer.results.show', [$exam, $attempt]));

        $response->assertOk();
        $response->assertSee($question->body);
        $response->assertSee($student->name);
    }
}

<?php

namespace Tests\Feature\Student;

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
 * RED (Phase 5, Wave 0) — pins the gated student result contract (GRD-04,
 * D-05/D-07, 05-RESEARCH.md Pitfall 1 & 3). The result route/controller/
 * view do not exist yet: every method here is expected to fail on a
 * missing-route error (RouteNotFoundException) or a 404/500, never a
 * parse error.
 *
 * Fixed route contract this plan pins for Waves 2-4:
 *   GET student/attempts/{attempt}/result -> student.attempts.result
 */
class ResultTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Section, 1: Exam, 2: User} */
    private function fixture(): array
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        return [$section, $exam, $student];
    }

    public function test_result_is_withheld_while_pending(): void
    {
        [, $exam, $student] = $this->fixture();

        $attempt = Attempt::factory()->submitted()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($student)->get(route('student.attempts.result', $attempt));

        $response->assertOk();
        $response->assertSee('Awaiting grading');
        $response->assertDontSee('points'); // no "{score} / {total} points" score line rendered
    }

    public function test_result_shown_when_graded(): void
    {
        [, $exam, $student] = $this->fixture();

        $question = Question::factory()->mcq()->create(['exam_id' => $exam->id, 'points' => 2]);

        $attempt = Attempt::factory()->graded(score: 2)->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        Answer::factory()->mcqCorrect($question)->create([
            'attempt_id' => $attempt->id,
            'is_correct' => true,
            'score' => 2,
        ]);

        $response = $this->actingAs($student)->get(route('student.attempts.result', $attempt));

        $response->assertOk();
        $response->assertSee('Your Result');
        $response->assertSee('2 / 2'); // "{score} / {total} points" per 05-UI-SPEC.md
    }

    public function test_cannot_view_another_students_result(): void
    {
        [, $exam, $studentA] = $this->fixture();
        $studentB = User::factory()->student()->create();

        $attempt = Attempt::factory()->graded(score: 0)->create([
            'exam_id' => $exam->id,
            'user_id' => $studentA->id,
        ]);

        $response = $this->actingAs($studentB)->get(route('student.attempts.result', $attempt));

        $response->assertForbidden();
    }

    public function test_result_visible_after_exam_unpublished(): void
    {
        [, $exam, $student] = $this->fixture();

        $attempt = Attempt::factory()->graded(score: 0)->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        // Ownership-only viewResult() must NOT depend on Exam::visibleTo()
        // (05-RESEARCH.md Pitfall 1) — unpublishing after grading must not
        // hide the student's own already-graded result.
        $exam->update(['is_published' => false]);

        $response = $this->actingAs($student)->get(route('student.attempts.result', $attempt));

        $response->assertOk();
    }

    public function test_breakdown_never_exposes_the_correct_option(): void
    {
        [, $exam, $student] = $this->fixture();

        $question = Question::factory()->mcq()->create(['exam_id' => $exam->id, 'points' => 2]);
        $correctOptionBody = $question->options()->where('is_correct', true)->value('body');

        $attempt = Attempt::factory()->graded(score: 0)->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        Answer::factory()->mcqIncorrect($question)->create([
            'attempt_id' => $attempt->id,
            'is_correct' => false,
            'score' => 0,
        ]);

        $response = $this->actingAs($student)->get(route('student.attempts.result', $attempt));

        $response->assertOk();
        $response->assertDontSee($correctOptionBody);
    }
}

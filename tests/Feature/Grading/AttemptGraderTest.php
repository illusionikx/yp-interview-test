<?php

namespace Tests\Feature\Grading;

use App\Enums\EnrollmentStatus;
use App\Enums\QuestionType;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\Section;
use App\Models\User;
use App\Services\AttemptGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 5, Wave 0) — pins the auto-grade edge-case matrix (D-01,
 * 05-RESEARCH.md Pitfall 2) and the submitted->graded completeness gate
 * (D-03) BEFORE App\Services\AttemptGrader exists and BEFORE
 * Attempt::lockAndFinalize() calls it. Every method here is expected to
 * fail for a missing-class/behavior reason, never a parse error.
 */
class AttemptGraderTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Exam, 1: User} */
    private function fixture(int $durationMinutes = 30): array
    {
        $exam = Exam::factory()->published()->create(['duration_minutes' => $durationMinutes]);
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        return [$exam, $student];
    }

    public function test_submitting_auto_grades_every_mcq_answer(): void
    {
        [$exam, $student] = $this->fixture();

        $correctQuestion = Question::factory()->mcq()->create(['exam_id' => $exam->id, 'points' => 2]);
        $wrongQuestion = Question::factory()->mcq()->create(['exam_id' => $exam->id, 'points' => 3]);
        $untouchedQuestion = Question::factory()->mcq()->create(['exam_id' => $exam->id, 'points' => 5]);

        // A question with NO option flagged is_correct — grade defensively
        // (Pitfall 2 row 5): never crash on firstWhere(...)?->id === null.
        $noCorrectOptionQuestion = Question::factory()->create([
            'exam_id' => $exam->id,
            'type' => QuestionType::Mcq,
            'points' => 4,
        ]);
        Option::factory()->count(4)->create([
            'question_id' => $noCorrectOptionQuestion->id,
            'is_correct' => false,
        ]);

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now(),
        ]);

        Answer::factory()->mcqCorrect($correctQuestion)->create(['attempt_id' => $attempt->id]);
        Answer::factory()->mcqIncorrect($wrongQuestion)->create(['attempt_id' => $attempt->id]);
        Answer::factory()->mcqIncorrect($noCorrectOptionQuestion)->create(['attempt_id' => $attempt->id]);
        // $untouchedQuestion deliberately gets no Answer row — never touched.

        $this->actingAs($student)->post(route('student.attempts.submit', $attempt));

        $correctAnswer = Answer::query()
            ->where('attempt_id', $attempt->id)->where('question_id', $correctQuestion->id)->first();
        $wrongAnswer = Answer::query()
            ->where('attempt_id', $attempt->id)->where('question_id', $wrongQuestion->id)->first();
        $noCorrectOptionAnswer = Answer::query()
            ->where('attempt_id', $attempt->id)->where('question_id', $noCorrectOptionQuestion->id)->first();

        $this->assertTrue($correctAnswer->is_correct);
        $this->assertEquals(2, $correctAnswer->score);

        $this->assertFalse($wrongAnswer->is_correct);
        $this->assertEquals(0, $wrongAnswer->score);

        $this->assertFalse($noCorrectOptionAnswer->is_correct);
        $this->assertEquals(0, $noCorrectOptionAnswer->score);

        // Untouched question: no row written, contributes 0 implicitly, no crash.
        $this->assertDatabaseMissing('answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $untouchedQuestion->id,
        ]);
    }

    public function test_auto_grading_fires_on_lazy_expiry(): void
    {
        [$exam, $student] = $this->fixture(10);

        $question = Question::factory()->mcq()->create(['exam_id' => $exam->id, 'points' => 1]);

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now(),
        ]);

        Answer::factory()->mcqCorrect($question)->create(['attempt_id' => $attempt->id]);

        $this->travelTo($attempt->started_at->copy()->addMinutes(11));

        // Any attempt-touching route lazily finalizes an expired attempt
        // (Phase 4's single lockAndFinalize() chokepoint) — proving the
        // Phase 5 hook covers BOTH the manual-submit AND lazy-expiry path.
        $this->actingAs($student)->get(route('student.attempts.show', $attempt));

        // All-MCQ exam, single question fully answered -> D-03's
        // completeness gate fires on this same hook, so the attempt
        // reaches 'graded' immediately (matches
        // test_all_mcq_exam_grades_immediately's manual-submit assertion;
        // D-02's whole point is that both paths share one chokepoint).
        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);

        $answer = Answer::query()
            ->where('attempt_id', $attempt->id)->where('question_id', $question->id)->first();

        $this->assertTrue($answer->is_correct);
        $this->assertEquals(1, $answer->score);
    }

    public function test_all_mcq_exam_grades_immediately(): void
    {
        [$exam, $student] = $this->fixture();

        $correctQuestion = Question::factory()->mcq()->create(['exam_id' => $exam->id, 'points' => 2]);
        $wrongQuestion = Question::factory()->mcq()->create(['exam_id' => $exam->id, 'points' => 3]);

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now(),
        ]);

        Answer::factory()->mcqCorrect($correctQuestion)->create(['attempt_id' => $attempt->id]);
        Answer::factory()->mcqIncorrect($wrongQuestion)->create(['attempt_id' => $attempt->id]);

        $this->actingAs($student)->post(route('student.attempts.submit', $attempt));

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertEquals(2, $attempt->score); // Sigma answers.score = 2 (correct) + 0 (wrong)
    }

    public function test_open_text_exam_stays_submitted_until_graded(): void
    {
        [$exam, $student] = $this->fixture();

        $mcqQuestion = Question::factory()->mcq()->create(['exam_id' => $exam->id, 'points' => 2]);
        $openQuestion = Question::factory()->open()->create(['exam_id' => $exam->id, 'points' => 5]);

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now(),
        ]);

        Answer::factory()->mcqCorrect($mcqQuestion)->create(['attempt_id' => $attempt->id]);
        $openAnswer = Answer::factory()->openText()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $openQuestion->id,
        ]);

        $this->actingAs($student)->post(route('student.attempts.submit', $attempt));

        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertNull($attempt->score);

        // Lecturer grades the last pending open-text answer, then completeness
        // is re-evaluated (D-03/D-04) — this is the second syncStatus() call
        // site (grade-save), independent of the finalize hook above.
        $openAnswer->update(['score' => 4]);
        app(AttemptGrader::class)->syncStatus($attempt->fresh());

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertEquals(6, $attempt->score); // 2 (mcq) + 4 (open-text)
    }
}

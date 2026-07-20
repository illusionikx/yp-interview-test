<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use App\Services\AttemptVoider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 10, Wave 0) — INT-02's acceptance, guarding threat T-10-02.
 *
 * This is the phase's single most safety-critical test. D-2's hard delete
 * has no undo and no audit trail, so the warning built from
 * AttemptVoider::summarize()'s counts is the ONLY protection between a
 * lecturer and permanent destruction of graded student work.
 * Count-correctness is therefore a SECURITY control, not a UX detail — a
 * modal reporting "0 graded" while 3 are graded is the catastrophic
 * failure this test exists to prevent. Every assertion below pins an
 * exact number, never merely "a modal appears".
 *
 * `App\Services\AttemptVoider` does not exist yet (plan 04 creates it) —
 * this file is expected to ERROR (class not found), which is the correct
 * RED for a class that does not exist. Do not stub the class to make this
 * a clean failure instead of an error.
 */
class AttemptVoiderTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_reports_all_five_counts_exactly(): void
    {
        $exam = Exam::factory()->published()->create();

        Attempt::factory()->for($exam)->for(User::factory()->student())->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->submitted()->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->graded(5)->create();

        $counts = app(AttemptVoider::class)->summarize($exam);

        $this->assertSame(1, $counts['inProgress']);
        $this->assertSame(1, $counts['submittedUngraded']);
        $this->assertSame(1, $counts['graded']);
        $this->assertSame(2, $counts['notYetGraded']);
        $this->assertSame(3, $counts['total']);
    }

    public function test_summarize_reports_zeroes_for_an_exam_with_no_attempts(): void
    {
        $exam = Exam::factory()->published()->create();

        $counts = app(AttemptVoider::class)->summarize($exam);

        $this->assertSame(0, $counts['inProgress']);
        $this->assertSame(0, $counts['submittedUngraded']);
        $this->assertSame(0, $counts['graded']);
        $this->assertSame(0, $counts['notYetGraded']);
        $this->assertSame(0, $counts['total']);
    }

    /**
     * Without an exam_id scope, a summarize() call could report another
     * exam's graded work as being at risk on THIS exam's modal — a wrong
     * count is a wrong consent, per D-2.
     */
    public function test_summarize_counts_only_the_given_exams_attempts(): void
    {
        $exam = Exam::factory()->published()->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->submitted()->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->graded(5)->create();

        $otherExam = Exam::factory()->published()->create();
        Attempt::factory()->for($otherExam)->for(User::factory()->student())->create();
        Attempt::factory()->for($otherExam)->for(User::factory()->student())->submitted()->create();
        Attempt::factory()->for($otherExam)->for(User::factory()->student())->graded(9)->create();

        $counts = app(AttemptVoider::class)->summarize($exam);

        $this->assertSame(1, $counts['inProgress']);
        $this->assertSame(1, $counts['submittedUngraded']);
        $this->assertSame(1, $counts['graded']);
        $this->assertSame(2, $counts['notYetGraded']);
        $this->assertSame(3, $counts['total']);
    }

    /**
     * Pins D-2's stated consequence: the delete cascades to answers,
     * permanently destroying graded scores. If a future change made
     * answers.attempt_id restrict instead of cascade, this must fail
     * loudly, not silently leave orphaned rows behind.
     */
    public function test_void_hard_deletes_every_attempt_and_cascades_to_answers(): void
    {
        $exam = Exam::factory()->published()->create();
        $question = Question::factory()->for($exam)->create();
        $attempt = Attempt::factory()->for($exam)->for(User::factory()->student())->graded(5)->create();
        $answer = Answer::factory()->for($attempt)->for($question)->create();

        $deletedCount = app(AttemptVoider::class)->void($exam);

        $this->assertSame(1, $deletedCount);
        $this->assertDatabaseMissing('attempts', ['id' => $attempt->id]);
        $this->assertDatabaseMissing('answers', ['attempt_id' => $attempt->id]);
        $this->assertDatabaseMissing('answers', ['id' => $answer->id]);
    }

    public function test_void_on_an_exam_with_no_attempts_returns_zero_and_throws_nothing(): void
    {
        $exam = Exam::factory()->published()->create();

        $deletedCount = app(AttemptVoider::class)->void($exam);

        $this->assertSame(0, $deletedCount);
    }
}

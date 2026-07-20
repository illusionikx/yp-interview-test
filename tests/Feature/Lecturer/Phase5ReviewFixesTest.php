<?php

namespace Tests\Feature\Lecturer;

use App\Models\Attempt;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the Phase 5 code-review findings:
 *  - HIGH-01: question points are capped so a single answer's score can't
 *    overflow answers.score decimal(5,2) and crash the grader mid-finalize.
 *  - HIGH-02 (SUPERSEDED by Phase 10/CLS-06): originally, an exam that
 *    students had attempted could not be unpublished, to stop a lecturer
 *    re-opening the draft-only edit gate and desyncing grades. CLS-06
 *    requires the draft/published toggle to work in BOTH directions,
 *    including post-attempt, so that lock is now removed. HIGH-02's
 *    protection is superseded, not abandoned: D-4/D-6 retire the
 *    draft-only edit gate outright, and EDT-04's warn-and-void flow
 *    (plan 08) replaces it with a stronger, explicit guarantee — editing
 *    an attempted exam voids its attempts, after a warning. Unpublishing
 *    itself still touches no attempt data in either direction; that is
 *    what distinguishes CLS-06's toggle from CLS-07's reset.
 */
class Phase5ReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    /** HIGH-01 — a question worth more than the cap is rejected. */
    public function test_a_question_over_the_points_cap_is_rejected(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create(['is_published' => false]);

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'open',
            'body' => 'A very heavy question',
            'points' => 1000, // > answers.score decimal(5,2) capacity → must be rejected
        ]);

        $response->assertSessionHasErrors('points');
        $this->assertDatabaseCount('questions', 0);
    }

    /** HIGH-01 — a question at the cap is accepted (boundary). */
    public function test_a_question_at_the_points_cap_is_accepted(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create(['is_published' => false]);

        $this->actingAs($lecturer)->post(route('lecturer.exams.questions.store', $exam), [
            'type' => 'open',
            'body' => 'Max-weight question',
            'points' => 100,
        ])->assertRedirect();

        $this->assertDatabaseHas('questions', ['exam_id' => $exam->id, 'points' => 100]);
    }

    /**
     * HIGH-02 SUPERSEDED (CLS-06) — an attempted exam CAN now be
     * unpublished. This inverts the old lock: the toggle must work in
     * both directions including post-attempt, and toggling must not
     * touch attempt data — the attempt survives, which is exactly what
     * distinguishes this from CLS-07's reset.
     */
    public function test_an_attempted_exam_can_now_be_unpublished(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();
        $student = User::factory()->student()->create();
        Attempt::factory()->create(['exam_id' => $exam->id, 'user_id' => $student->id]);

        $this->actingAs($lecturer)->patch(route('lecturer.exams.unpublish', $exam));

        $this->assertFalse($exam->fresh()->is_published, 'CLS-06: an attempted exam must be unpublishable.');
        $this->assertDatabaseCount('attempts', 1);
    }

    /** HIGH-02 — an exam with no attempts can still be unpublished (regression guard). */
    public function test_an_unattempted_exam_can_still_be_unpublished(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->create();

        $this->actingAs($lecturer)->patch(route('lecturer.exams.unpublish', $exam));

        $this->assertFalse($exam->fresh()->is_published);
    }
}

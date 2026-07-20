<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Enums\QuestionType;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 13-02: presentation-layer enhancements over the already-shipped take page
 * (TAK-09/TAK-10/TAK-11/TAK-12). These tests deliberately re-prove the
 * server-authoritative deadline stayed untouched (REGRESSION case) — this
 * plan is cosmetic/navigational only, never an enforcement change.
 */
class TakeExamPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Attempt, 2: Question}
     */
    private function scrambledMcqFixture(int $durationMinutes = 30): array
    {
        $exam = Exam::factory()->published()->create([
            'duration_minutes' => $durationMinutes,
            'description' => 'Bring nothing but yourself.',
        ]);
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $question = Question::factory()->create([
            'exam_id' => $exam->id,
            'type' => QuestionType::Mcq,
            'position' => 0,
        ]);

        // Inserted out of position order (Charlie, Alpha, Bravo) so a
        // rendered-order assertion is only ever satisfied by reading
        // `position`, never insertion/creation order (TAK-12).
        Option::factory()->create(['question_id' => $question->id, 'body' => 'Charlie', 'position' => 2, 'is_correct' => false]);
        Option::factory()->create(['question_id' => $question->id, 'body' => 'Alpha', 'position' => 0, 'is_correct' => true]);
        Option::factory()->create(['question_id' => $question->id, 'body' => 'Bravo', 'position' => 1, 'is_correct' => false]);

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now(),
        ]);

        return [$student, $attempt, $question];
    }

    public function test_the_sticky_top_bar_shows_subject_exam_name_and_an_instructions_popup_trigger(): void
    {
        [$student, $attempt] = $this->scrambledMcqFixture();
        $attempt->loadMissing('exam.subject');

        $response = $this->actingAs($student)->get(route('student.attempts.show', $attempt));

        $response->assertOk();
        $response->assertSee($attempt->exam->subject->name);
        $response->assertSee($attempt->exam->title);
        // The instructions popup trigger + the x-modal it opens — never a
        // native alert(). <x-modal name="instructions"> compiles down to the
        // component's own x-on:open-modal.window listener gated on the exact
        // 'instructions' name, which is what actually reaches the HTML.
        $response->assertSee("open-modal', 'instructions'", false);
        $response->assertSee("\$event.detail == 'instructions'", false);
    }

    public function test_checkmarks_derive_from_the_server_persisted_answered_map_and_survive_a_reload(): void
    {
        [$student, $attempt, $question] = $this->scrambledMcqFixture();

        Answer::factory()->mcqCorrect($question)->create(['attempt_id' => $attempt->id]);

        $response = $this->actingAs($student)->get(route('student.attempts.show', $attempt));

        // The question id is present in the server-seeded @js($answeredQuestionIds)
        // array Blade renders into the attemptTimer(...) call.
        $response->assertSee("JSON.parse('[{$question->id}]')", false);
        // The stepper checkmark binds the SAME reactive `answered` map —
        // never a new client-only per-card answered flag.
        $response->assertSee("answered[{$question->id}]", false);

        // Simulate a mid-exam page reload: a second GET must still seed the
        // same answered id from the persisted Answer row, not from any
        // client-side state (which a real reload would have discarded).
        $reloaded = $this->actingAs($student)->get(route('student.attempts.show', $attempt));

        $reloaded->assertSee("JSON.parse('[{$question->id}]')", false);
    }

    public function test_mcq_options_render_in_authored_position_order_never_insertion_order(): void
    {
        [$student, $attempt] = $this->scrambledMcqFixture();

        $response = $this->actingAs($student)->get(route('student.attempts.show', $attempt));

        // Inserted as Charlie, Alpha, Bravo; positions 2, 0, 1 — a rendered
        // order of Alpha, Bravo, Charlie is only possible by honoring
        // `position`, proving no runtime randomization/insertion-order leak.
        $response->assertSeeInOrder(['Alpha', 'Bravo', 'Charlie']);
    }

    public function test_the_ten_minute_warning_is_a_guarded_fired_once_flag_at_the_600_second_threshold(): void
    {
        // TAK-11 is partly a runtime-visual behavior (the badge visibly
        // turning red, the toast visibly appearing) that only a real
        // browser/Dusk run can observe — deferred to Phase 14 per
        // 13-02-PLAN.md. This test proves the LOGIC: a single fired-once
        // flag, guarded (not a per-tick action), at the 600-second (10
        // minute) threshold.
        [$student, $attempt] = $this->scrambledMcqFixture();

        $response = $this->actingAs($student)->get(route('student.attempts.show', $attempt));
        $html = $response->getContent();

        $this->assertStringContainsString('tenMinuteWarned: false', $html);
        $this->assertStringContainsString('showTenMinuteToast: false', $html);
        $this->assertStringContainsString('checkTenMinuteWarning()', $html);
        // The guard: the flag must be checked (not this.remaining <= 600
        // alone) before the toast is revealed — proves it cannot re-fire.
        $this->assertMatchesRegularExpression(
            '/if \(this\.remaining <= 600 && ! this\.tenMinuteWarned\) \{\s*this\.tenMinuteWarned = true;\s*this\.showTenMinuteToast = true;/',
            $html
        );
    }

    public function test_visiting_an_expired_attempt_still_finalizes_it_server_side(): void
    {
        // REGRESSION (core value, T-13-03): this plan is presentation-only.
        // Mirrors AttemptShowTest's expired->finalize case to prove the
        // deadline enforcement did not move client-side.
        [$student, $attempt] = $this->scrambledMcqFixture(20);

        $this->travelTo($attempt->started_at->copy()->addMinutes(20)->addMinute());

        $response = $this->actingAs($student)->get(route('student.attempts.show', $attempt));

        $attempt->refresh();
        $this->assertContains($attempt->status, ['submitted', 'graded']);
        $response->assertRedirect(route('student.attempts.submitted', $attempt));
    }

    public function test_a_write_past_the_deadline_is_still_rejected_server_side(): void
    {
        // REGRESSION (core value, T-13-03): the autosave endpoint must keep
        // rejecting a late write regardless of any client-side countdown
        // state — the client badge/toast are cosmetic only.
        [$student, $attempt, $question] = $this->scrambledMcqFixture(10);
        $option = $question->options()->first();

        $this->travelTo($attempt->started_at->copy()->addMinutes(10)->addMinute());

        $response = $this->actingAs($student)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('answers', 0);
    }
}

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

class AttemptShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_seconds_reflects_elapsed_time(): void
    {
        $exam = Exam::factory()->published()->create(['duration_minutes' => 30]);
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $this->freezeTime();

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now(),
        ]);

        $this->travel(10)->minutes();

        $response = $this->actingAs($student)->get(route('student.attempts.show', $attempt));

        $response->assertViewHas('remainingSeconds', (30 - 10) * 60);
    }

    public function test_visiting_an_expired_attempt_finalizes_it_to_submitted(): void
    {
        $exam = Exam::factory()->published()->create(['duration_minutes' => 20]);
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $this->freezeTime();
        $startedAt = now();

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => $startedAt,
        ]);

        $deadline = $startedAt->copy()->addMinutes(20);
        $this->travelTo($deadline->copy()->addMinute());

        $response = $this->actingAs($student)->get(route('student.attempts.show', $attempt));

        // Phase 5 (D-02/D-03): the finalize-on-expiry hook grades and
        // evaluates completeness in the same call. The single MCQ question
        // here was never answered (no Answer row), which is not "pending"
        // (only open-text rows block completeness) — the attempt reaches
        // 'graded' immediately, not just 'submitted'.
        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        // Second-precision comparison: `attempts.started_at`/`submitted_at` are
        // plain `timestamp` columns (Phase-1 fixed schema, no fractional-seconds
        // precision) — Eloquent's default MySQL date format ('Y-m-d H:i:s') drops
        // microseconds on every write, so a DB-round-tripped deadline can never
        // equal an in-memory `now()` down to the microsecond. Comparing to the
        // second preserves the assertion's real intent (submitted_at == deadline,
        // never past it) without depending on precision the schema doesn't have.
        $this->assertSame($deadline->format('Y-m-d H:i:s'), $attempt->submitted_at->format('Y-m-d H:i:s'));
        $response->assertRedirect(route('student.attempts.submitted', $attempt));
    }

    public function test_submit_confirmation_modal_seeds_the_answered_count_from_saved_answers(): void
    {
        // FIX-01: the modal's answered count must reflect answers already
        // saved server-side at the moment the page renders, not remain
        // stuck at zero — this covers the server-rendered/initial-seed
        // portion of the fix (the live post-autosave update is JS-only,
        // covered by manual verification per 07-VALIDATION).
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $answeredQuestion = Question::factory()->mcq()->create(['exam_id' => $exam->id, 'position' => 0]);
        Question::factory()->open()->create(['exam_id' => $exam->id, 'position' => 1]);

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        Answer::factory()->mcqCorrect($answeredQuestion)->create([
            'attempt_id' => $attempt->id,
        ]);

        $response = $this->actingAs($student)->get(route('student.attempts.show', $attempt));

        $response->assertSee('1 of 2 questions answered.');
    }

    public function test_the_take_page_never_exposes_is_correct(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        // mcq() attaches 1 correct + 3 incorrect options (mix of both).
        Question::factory()->mcq()->create(['exam_id' => $exam->id]);

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($student)->get(route('student.attempts.show', $attempt));

        // Whole raw response body, not just visible/rendered text (TAK-06).
        $response->assertDontSee('is_correct');
    }
}

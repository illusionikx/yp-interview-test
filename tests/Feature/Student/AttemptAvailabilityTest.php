<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 8, Wave 0) — AVL-03: attempt start refused outside the exam's
 * optional availability window ([available_from, available_until), half-
 * open per REQUIREMENTS.md #6), with the existing-attempt-stays-resumable
 * guard (the AVL-04 boundary) proven by a dedicated composition case.
 * Expected RED until App\Http\Controllers\Student\AttemptController@store
 * gains the isAvailableNow() branch described in 08-PATTERNS.md (08-06).
 * Every window fixture is built from 08-01's ExamFactory
 * available()/opening()/closed() states rather than hand-rolled datetimes.
 */
class AttemptAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudentFor(Exam $exam): User
    {
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        return $student;
    }

    public function test_an_enrolled_student_can_start_an_attempt_inside_the_availability_window(): void
    {
        $exam = Exam::factory()->published()->available()->create();
        $student = $this->enrolledStudentFor($exam);

        $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertDatabaseCount('attempts', 1);
    }

    public function test_starting_before_available_from_is_refused_with_no_attempt_created(): void
    {
        $exam = Exam::factory()->published()->opening()->create();
        $student = $this->enrolledStudentFor($exam);

        $response = $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertDatabaseCount('attempts', 0);
        $response->assertRedirect(route('student.exams.show', $exam));
        $response->assertSessionHas('error', __('This exam is not available yet. It opens :date.', [
            'date' => $exam->available_from->format('M j, Y g:ia'),
        ]));
    }

    public function test_starting_after_available_until_is_refused_with_no_attempt_created(): void
    {
        $exam = Exam::factory()->published()->closed()->create();
        $student = $this->enrolledStudentFor($exam);

        $response = $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertDatabaseCount('attempts', 0);
        $response->assertRedirect(route('student.exams.show', $exam));
        $response->assertSessionHas('error', __('This exam is no longer available. It closed :date.', [
            'date' => $exam->available_until->format('M j, Y g:ia'),
        ]));
    }

    public function test_an_exam_with_both_bounds_null_is_startable(): void
    {
        // definition() emits null on both columns by default — the
        // unbounded case that every pre-Phase-8 exam falls into.
        $exam = Exam::factory()->published()->create();
        $student = $this->enrolledStudentFor($exam);

        $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertDatabaseCount('attempts', 1);
    }

    public function test_an_exam_with_only_available_from_set_in_the_past_is_startable(): void
    {
        $exam = Exam::factory()->published()->create([
            'available_from' => now()->subDay(),
            'available_until' => null,
        ]);
        $student = $this->enrolledStudentFor($exam);

        $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertDatabaseCount('attempts', 1);
    }

    public function test_an_exam_with_only_available_until_set_in_the_future_is_startable(): void
    {
        $exam = Exam::factory()->published()->create([
            'available_from' => null,
            'available_until' => now()->addDay(),
        ]);
        $student = $this->enrolledStudentFor($exam);

        $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertDatabaseCount('attempts', 1);
    }

    /**
     * EXACT-BOUNDARY (REQUIREMENTS.md #6) — starting exactly at
     * available_from must SUCCEED (inclusive lower bound).
     */
    public function test_starting_exactly_at_available_from_succeeds(): void
    {
        $exam = Exam::factory()->published()->create([
            'available_from' => now()->addHour(),
            'available_until' => now()->addDays(7),
        ]);
        $student = $this->enrolledStudentFor($exam);

        $this->travelTo($exam->available_from);
        $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertDatabaseCount('attempts', 1);
    }

    /**
     * EXACT-BOUNDARY — starting exactly at available_until must be
     * REFUSED (exclusive upper bound).
     */
    public function test_starting_exactly_at_available_until_is_refused(): void
    {
        $exam = Exam::factory()->published()->create([
            'available_from' => now()->subDay(),
            'available_until' => now()->addHour(),
        ]);
        $student = $this->enrolledStudentFor($exam);

        $this->travelTo($exam->available_until);
        $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertDatabaseCount('attempts', 0);
    }

    /**
     * THE CRITICAL COMPOSITION CASE (the AVL-04 boundary) — protects
     * against the availability gate being written in the wrong place. A
     * student with an ALREADY-STARTED in-progress attempt must still be
     * able to POST the start route after available_until has passed and
     * be routed to their attempt, NOT refused. The availability check
     * applies to the NEW-attempt branch only; an existing attempt is
     * always resumable.
     */
    public function test_an_already_started_attempt_is_resumable_even_after_the_window_has_closed(): void
    {
        $exam = Exam::factory()->published()->create([
            'available_from' => now()->subDays(2),
            'available_until' => now()->addHour(),
        ]);
        $student = $this->enrolledStudentFor($exam);

        $this->actingAs($student)->post(route('student.attempts.store', $exam));
        $attempt = Attempt::firstOrFail();

        $this->travelTo($exam->available_until->clone()->addMinute());

        $response = $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $response->assertRedirect(route('student.attempts.show', $attempt));
        $this->assertDatabaseCount('attempts', 1);
    }

    /**
     * The enrollment gate stays first — availability never widens access.
     * A NON-enrolled student outside the window still gets the existing
     * 403 from authorize('takeable'), not the availability flash.
     */
    public function test_a_non_enrolled_student_outside_the_window_still_gets_the_existing_403_not_the_availability_flash(): void
    {
        $exam = Exam::factory()->published()->closed()->create();
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $response->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttemptSubmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_an_in_progress_attempt_finalizes_it_to_submitted(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($student)->post(route('student.attempts.submit', $attempt));

        // Phase 5 (D-02/D-03): the finalize hook grades and evaluates
        // completeness in the same transaction. This exam has zero
        // questions, so there is trivially nothing pending — the attempt
        // reaches 'graded' immediately, not just 'submitted'.
        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertNotNull($attempt->submitted_at);
        $response->assertRedirect(route('student.attempts.submitted', $attempt));
    }

    public function test_a_double_submit_is_idempotent(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        $this->actingAs($student)->post(route('student.attempts.submit', $attempt));
        $firstSubmittedAt = $attempt->refresh()->submitted_at;

        $this->travel(1)->minutes();

        $response = $this->actingAs($student)->post(route('student.attempts.submit', $attempt));

        // Phase 5: see note above — zero-question exam grades immediately.
        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertTrue($firstSubmittedAt->equalTo($attempt->submitted_at));
        $this->assertLessThan(500, $response->status());
    }
}

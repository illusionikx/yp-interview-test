<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttemptStartTest extends TestCase
{
    use RefreshDatabase;

    // Same-subject fixture discipline (10-03, factory trap): ExamFactory and
    // SectionFactory each mint their own Subject independently, so every
    // fixture below builds the exam first and pins the section's
    // subject_id to it explicitly.

    public function test_starting_an_assigned_exam_creates_an_in_progress_attempt(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $response = $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertDatabaseCount('attempts', 1);
        $attempt = Attempt::first();
        $this->assertSame('in_progress', $attempt->status);
        $this->assertNotNull($attempt->started_at);
        $response->assertRedirect(route('student.attempts.show', $attempt));
    }

    public function test_starting_the_same_exam_twice_resumes_the_existing_attempt(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $this->actingAs($student)->post(route('student.attempts.store', $exam));
        $firstStartedAt = Attempt::first()->started_at;

        $this->travel(2)->minutes();

        $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertSame(1, Attempt::count());
        $this->assertTrue($firstStartedAt->equalTo(Attempt::first()->started_at));
    }

    public function test_a_concurrent_double_start_does_not_create_a_duplicate_attempt(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        // Simulate the race winner: a competing in_progress Attempt already
        // exists for this (exam, user) pair before our request lands.
        Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertSame(1, Attempt::count());
        $this->assertLessThan(500, $response->status());
    }

    public function test_a_student_cannot_start_a_second_attempt_after_submitting(): void
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        Attempt::factory()->submitted()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($student)->post(route('student.attempts.store', $exam));

        $this->assertSame(1, Attempt::count());
        $response->assertRedirect(route('student.exams.show', $exam));
        $response->assertSessionHas('status');
    }
}

<?php

namespace Tests\Unit;

use App\Enums\RejectionReason;
use App\Models\Exam;
use App\Models\Section;
use Tests\TestCase;

/**
 * Pins the exact half-open boundary semantics ([from, until)) for both
 * Exam::isAvailableNow()/availabilityState() and Section::windowStatus()
 * (REQUIREMENTS.md #6). No RefreshDatabase — every case constructs the
 * model directly via mass assignment, so no DB round-trip is needed.
 */
class WindowSemanticsTest extends TestCase
{
    public function test_exam_with_null_bounds_is_always_available(): void
    {
        $exam = new Exam(['available_from' => null, 'available_until' => null]);

        $this->assertTrue($exam->isAvailableNow());
        $this->assertSame('available', $exam->availabilityState());
    }

    public function test_exam_is_available_at_exact_available_from_instant(): void
    {
        $from = now();
        $this->travelTo($from);

        $exam = new Exam(['available_from' => $from, 'available_until' => null]);

        $this->assertTrue($exam->isAvailableNow());
    }

    public function test_exam_is_not_available_one_second_before_available_from(): void
    {
        $from = now()->addMinute();
        $this->travelTo($from->copy()->subSecond());

        $exam = new Exam(['available_from' => $from, 'available_until' => null]);

        $this->assertFalse($exam->isAvailableNow());
        $this->assertSame('opening', $exam->availabilityState());
    }

    public function test_exam_is_not_available_at_exact_available_until_instant(): void
    {
        $until = now();
        $this->travelTo($until);

        $exam = new Exam(['available_from' => null, 'available_until' => $until]);

        $this->assertFalse($exam->isAvailableNow());
        $this->assertSame('closed', $exam->availabilityState());
    }

    public function test_exam_is_available_one_second_before_available_until(): void
    {
        $until = now()->addMinute();
        $this->travelTo($until->copy()->subSecond());

        $exam = new Exam(['available_from' => null, 'available_until' => $until]);

        $this->assertTrue($exam->isAvailableNow());
        $this->assertSame('available', $exam->availabilityState());
    }

    public function test_exam_with_only_available_from_set_is_available_after_it(): void
    {
        $from = now()->subDay();
        $exam = new Exam(['available_from' => $from, 'available_until' => null]);

        $this->assertTrue($exam->isAvailableNow());
    }

    public function test_exam_with_only_available_until_set_in_the_past_is_closed(): void
    {
        $until = now()->subDay();
        $exam = new Exam(['available_from' => null, 'available_until' => $until]);

        $this->assertFalse($exam->isAvailableNow());
        $this->assertSame('closed', $exam->availabilityState());
    }

    public function test_section_window_status_is_open_at_exact_opens_at_instant(): void
    {
        $opensAt = now();
        $this->travelTo($opensAt);

        $section = new Section(['opens_at' => $opensAt, 'closes_at' => $opensAt->copy()->addDay()]);

        $this->assertSame('open', $section->windowStatus());
    }

    public function test_section_window_status_is_opens_one_second_before_opens_at(): void
    {
        $opensAt = now()->addMinute();
        $this->travelTo($opensAt->copy()->subSecond());

        $section = new Section(['opens_at' => $opensAt, 'closes_at' => $opensAt->copy()->addDay()]);

        $this->assertSame('opens', $section->windowStatus());
    }

    public function test_section_window_status_is_closed_at_exact_closes_at_instant(): void
    {
        $closesAt = now();
        $this->travelTo($closesAt);

        $section = new Section(['opens_at' => $closesAt->copy()->subDay(), 'closes_at' => $closesAt]);

        $this->assertSame('closed', $section->windowStatus());
    }

    public function test_section_window_status_is_open_one_second_before_closes_at(): void
    {
        $closesAt = now()->addMinute();
        $this->travelTo($closesAt->copy()->subSecond());

        $section = new Section(['opens_at' => $closesAt->copy()->subDay(), 'closes_at' => $closesAt]);

        $this->assertSame('open', $section->windowStatus());
    }

    public function test_rejection_reason_has_exactly_five_cases(): void
    {
        $this->assertCount(5, RejectionReason::cases());
    }

    public function test_rejection_reason_labels_are_the_locked_human_strings(): void
    {
        $this->assertSame('Not eligible for subject', RejectionReason::NotEligibleForSubject->label());
        $this->assertSame('Prerequisite not met', RejectionReason::PrerequisiteNotMet->label());
        $this->assertSame('Duplicate enrollment', RejectionReason::DuplicateEnrollment->label());
        $this->assertSame('Section changed', RejectionReason::SectionChanged->label());
        $this->assertSame('Other', RejectionReason::Other->label());
    }
}

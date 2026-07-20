<?php

namespace Tests\Feature\Lecturer;

use App\Enums\EnrollmentStatus;
use App\Enums\RejectionReason;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * RED (Phase 8, Wave 0) — ENR-07: any lecturer assigned to a section's
 * subject can reject an enrolled student with a reason from the fixed
 * 5-value RejectionReason enum, and the reason is visible to the rejected
 * student as its human label. Also pins the ENR-07/SEC-03 negative case —
 * a lecturer NOT assigned to the subject must be refused, never
 * `return true;` (mirrors SectionControllerTest's existing precedent).
 * Expected RED until App\Http\Controllers\Lecturer\RejectEnrollmentController
 * and the lecturer.sections.show / lecturer.sections.enrollments.reject
 * routes land (08-05).
 */
class RejectEnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Section, 1: User}
     */
    private function enrolledStudentInSectionOf(Subject $subject): array
    {
        $section = Section::factory()->create(['subject_id' => $subject->id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        return [$section, $student];
    }

    /**
     * Iterate every enum case rather than hardcoding one, so all 5 fixed
     * reasons are proven accepted, not just a representative sample.
     */
    #[DataProvider('rejectionReasons')]
    public function test_an_assigned_lecturer_can_reject_an_enrolled_student_with_a_fixed_reason(RejectionReason $reason): void
    {
        $subject = Subject::factory()->create();
        $lecturer = User::factory()->lecturer()->create();
        $subject->lecturers()->attach($lecturer);
        [$section, $student] = $this->enrolledStudentInSectionOf($subject);

        $response = $this->actingAs($lecturer)->patch(
            route('lecturer.sections.enrollments.reject', [$section, $student]),
            ['reason' => $reason->value]
        );

        $response->assertRedirect();
        // Persisted as the enum's BACKING VALUE, not its label.
        $this->assertDatabaseHas('enrollments', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'status' => 'rejected',
            'rejection_reason' => $reason->value,
        ]);
    }

    /**
     * @return array<string, array{0: RejectionReason}>
     */
    public static function rejectionReasons(): array
    {
        return collect(RejectionReason::cases())
            ->mapWithKeys(fn (RejectionReason $case) => [$case->value => [$case]])
            ->all();
    }

    public function test_the_flash_is_the_exact_rejection_copy(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = User::factory()->lecturer()->create();
        $subject->lecturers()->attach($lecturer);
        [$section, $student] = $this->enrolledStudentInSectionOf($subject);

        $response = $this->actingAs($lecturer)->patch(
            route('lecturer.sections.enrollments.reject', [$section, $student]),
            ['reason' => RejectionReason::Other->value]
        );

        $response->assertSessionHas('status', "{$student->name} has been rejected from this section.");
    }

    /**
     * The "student can see the reason" half of ENR-07 — asserted against
     * the student-facing page, not just the database, rendered as the
     * human LABEL rather than the raw enum backing value.
     */
    public function test_the_rejected_student_sees_the_reason_label_on_their_sections_page(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = User::factory()->lecturer()->create();
        $subject->lecturers()->attach($lecturer);
        [$section, $student] = $this->enrolledStudentInSectionOf($subject);

        $this->actingAs($lecturer)->patch(
            route('lecturer.sections.enrollments.reject', [$section, $student]),
            ['reason' => RejectionReason::PrerequisiteNotMet->value]
        );

        $response = $this->actingAs($student)->get(route('student.subjects.show', $subject));

        $response->assertSee('Rejected: Prerequisite not met');
    }

    /**
     * ENR-07/SEC-03 — the important negative case. A lecturer NOT assigned
     * to the section's subject must be refused. This is the per-subject
     * ownership boundary; the controller must never `return true;`.
     */
    public function test_a_lecturer_not_assigned_to_the_subject_is_forbidden(): void
    {
        $subject = Subject::factory()->create();
        $outsider = User::factory()->lecturer()->create();
        [$section, $student] = $this->enrolledStudentInSectionOf($subject);

        $response = $this->actingAs($outsider)->patch(
            route('lecturer.sections.enrollments.reject', [$section, $student]),
            ['reason' => RejectionReason::Other->value]
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('enrollments', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'status' => 'enrolled',
        ]);
    }

    /**
     * "Any lecturer of the subject" (ENR-07) — a second lecturer, also
     * assigned but not the section's creator, must also be able to reject.
     */
    public function test_a_second_lecturer_also_assigned_to_the_subject_can_reject(): void
    {
        $subject = Subject::factory()->create();
        $ownerLecturer = User::factory()->lecturer()->create();
        $secondLecturer = User::factory()->lecturer()->create();
        $subject->lecturers()->attach([$ownerLecturer->id, $secondLecturer->id]);
        [$section, $student] = $this->enrolledStudentInSectionOf($subject);

        $response = $this->actingAs($secondLecturer)->patch(
            route('lecturer.sections.enrollments.reject', [$section, $student]),
            ['reason' => RejectionReason::Other->value]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('enrollments', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'status' => 'rejected',
        ]);
    }

    public function test_a_reason_outside_the_fixed_enum_is_a_422_and_the_enrollment_is_unchanged(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = User::factory()->lecturer()->create();
        $subject->lecturers()->attach($lecturer);
        [$section, $student] = $this->enrolledStudentInSectionOf($subject);

        $response = $this->actingAs($lecturer)->patch(
            route('lecturer.sections.enrollments.reject', [$section, $student]),
            ['reason' => 'not_a_real_reason']
        );

        $response->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('enrollments', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_a_missing_reason_is_a_422(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = User::factory()->lecturer()->create();
        $subject->lecturers()->attach($lecturer);
        [$section, $student] = $this->enrolledStudentInSectionOf($subject);

        $response = $this->actingAs($lecturer)->patch(
            route('lecturer.sections.enrollments.reject', [$section, $student]),
            []
        );

        $response->assertSessionHasErrors('reason');
    }

    /**
     * WR-01 — the enrollments()->whereKey($student->id)->exists() check
     * proves a pivot row exists for this student/section, but not that its
     * CURRENT status is Enrolled. A student who already withdrew must not
     * be silently flipped to Rejected by a stale/late reject request.
     */
    public function test_rejecting_a_student_who_has_already_withdrawn_is_a_404_and_the_row_is_unchanged(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = User::factory()->lecturer()->create();
        $subject->lecturers()->attach($lecturer);
        $section = Section::factory()->create(['subject_id' => $subject->id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Withdrawn]);

        $response = $this->actingAs($lecturer)->patch(
            route('lecturer.sections.enrollments.reject', [$section, $student]),
            ['reason' => RejectionReason::Other->value]
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('enrollments', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'status' => 'withdrawn',
            'rejection_reason' => null,
        ]);
    }

    /**
     * WR-01 — same guard, applied to a student who was already rejected
     * (a second reject must not silently overwrite the first reason).
     */
    public function test_rejecting_a_student_who_is_already_rejected_is_a_404_and_the_row_is_unchanged(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = User::factory()->lecturer()->create();
        $subject->lecturers()->attach($lecturer);
        $section = Section::factory()->create(['subject_id' => $subject->id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, [
            'status' => EnrollmentStatus::Rejected,
            'rejection_reason' => RejectionReason::PrerequisiteNotMet->value,
        ]);

        $response = $this->actingAs($lecturer)->patch(
            route('lecturer.sections.enrollments.reject', [$section, $student]),
            ['reason' => RejectionReason::Other->value]
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('enrollments', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'status' => 'rejected',
            'rejection_reason' => RejectionReason::PrerequisiteNotMet->value,
        ]);
    }

    public function test_a_student_hitting_the_reject_route_is_refused_by_role_middleware(): void
    {
        $subject = Subject::factory()->create();
        [$section, $student] = $this->enrolledStudentInSectionOf($subject);

        $response = $this->actingAs($student)->patch(
            route('lecturer.sections.enrollments.reject', [$section, $student]),
            ['reason' => RejectionReason::Other->value]
        );

        $response->assertForbidden();
    }
}

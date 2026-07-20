<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Enums\RejectionReason;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 8, Wave 0) — ENR-02..ENR-05: capacity-safe apply/withdraw, the
 * one-active-enrollment-per-subject-per-semester rule, and re-apply
 * semantics (UPDATE never INSERT — the unique(section_id,user_id) index
 * must never be violated by a second apply). Expected RED until
 * App\Http\Controllers\Student\EnrollmentController and the
 * student.sections.enroll/withdraw routes land (08-04) — every method here
 * currently errors on route() resolution (RouteNotFoundException).
 */
class EnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function openSection(int $capacity = 30, ?int $subjectId = null, int $year = 2026, int $semester = 1): Section
    {
        $subjectId ??= Subject::factory()->create()->id;

        // Auto-increment sequence per (subject, year, semester), mirroring
        // SectionController@store's own idiom — the factory's default
        // sequence is a fixed 1, which collides against the unique
        // (subject_id, year, semester, sequence) index whenever a test
        // (e.g. the ENR-04 same-subject-same-semester case) needs two
        // sections sharing all three of those values.
        $sequence = Section::where('subject_id', $subjectId)
            ->where('year', $year)
            ->where('semester', $semester)
            ->max('sequence') + 1;

        return Section::factory()->create([
            'subject_id' => $subjectId,
            'capacity' => $capacity,
            'year' => $year,
            'semester' => $semester,
            'sequence' => $sequence,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(14),
        ]);
    }

    public function test_applying_to_an_open_non_full_section_enrolls_immediately(): void
    {
        $section = $this->openSection();
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->post(route('student.sections.enroll', $section));

        $response->assertRedirect();
        $response->assertSessionHas('status', "You're enrolled in {$section->name}.");
        $this->assertDatabaseHas('enrollments', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_applying_to_a_full_section_is_refused_and_does_not_increase_the_count(): void
    {
        $section = $this->openSection(capacity: 1);
        $section->enrollments()->attach(User::factory()->student()->create()->id, ['status' => EnrollmentStatus::Enrolled]);
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->post(route('student.sections.enroll', $section));

        $response->assertSessionHas('error', 'This section just reached capacity — choose another section.');
        $this->assertSame(1, $section->enrollments()->wherePivot('status', 'enrolled')->count());
    }

    /**
     * ENR-02 — SEQUENTIAL SIMULATION ONLY. PHPUnit's runner is
     * single-threaded and cannot fire two truly simultaneous requests. This
     * test proves the count check and that the lock introduces no
     * deadlock/logic error when two applies land back-to-back; it does NOT
     * prove true concurrency safety. That argument rests on the structural
     * presence of lockForUpdate() inside the transaction, verifiable by
     * code review (08-VALIDATION.md "Stated Coverage Limitations"). Do not
     * read this test as proof of race-condition coverage.
     */
    public function test_enr02_sequential_simulation_of_two_back_to_back_applies_never_exceeds_capacity(): void
    {
        $section = $this->openSection(capacity: 3);
        for ($i = 0; $i < 2; $i++) {
            $section->enrollments()->attach(User::factory()->student()->create()->id, ['status' => EnrollmentStatus::Enrolled]);
        }
        $studentA = User::factory()->student()->create();
        $studentB = User::factory()->student()->create();

        $this->actingAs($studentA)->post(route('student.sections.enroll', $section));
        $aSucceeded = session()->has('status');
        $aRefused = session()->has('error');

        $this->actingAs($studentB)->post(route('student.sections.enroll', $section));
        $bSucceeded = session()->has('status');
        $bRefused = session()->has('error');

        $this->assertSame(1, collect([$aSucceeded, $bSucceeded])->filter()->count(), 'exactly one apply must succeed');
        $this->assertSame(1, collect([$aRefused, $bRefused])->filter()->count(), 'exactly one apply must be refused');
        $this->assertSame(3, $section->enrollments()->wherePivot('status', 'enrolled')->count(), 'final enrolled count must equal capacity, never exceed it');
    }

    public function test_applying_before_the_window_opens_is_refused(): void
    {
        $section = Section::factory()->create([
            'opens_at' => now()->addDay(),
            'closes_at' => now()->addDays(14),
        ]);
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->post(route('student.sections.enroll', $section));

        $response->assertSessionHas('error', "Enrollment for this section hasn't opened yet.");
        $this->assertDatabaseMissing('enrollments', ['section_id' => $section->id, 'user_id' => $student->id]);
    }

    public function test_applying_after_the_window_closes_is_refused(): void
    {
        $section = Section::factory()->create([
            'opens_at' => now()->subDays(14),
            'closes_at' => now()->subDay(),
        ]);
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->post(route('student.sections.enroll', $section));

        $response->assertSessionHas('error', 'Enrollment for this section is closed.');
        $this->assertDatabaseMissing('enrollments', ['section_id' => $section->id, 'user_id' => $student->id]);
    }

    /**
     * EXACT-BOUNDARY (REQUIREMENTS.md #6) — half-open [opens_at, closes_at):
     * applying exactly at opens_at must SUCCEED.
     */
    public function test_applying_exactly_at_opens_at_succeeds(): void
    {
        $section = Section::factory()->create([
            'opens_at' => now()->addHour(),
            'closes_at' => now()->addDays(14),
        ]);
        $student = User::factory()->student()->create();

        $this->travelTo($section->opens_at);
        $this->actingAs($student)->post(route('student.sections.enroll', $section));

        $this->assertDatabaseHas('enrollments', ['section_id' => $section->id, 'user_id' => $student->id, 'status' => 'enrolled']);
    }

    /**
     * EXACT-BOUNDARY (REQUIREMENTS.md #6) — applying exactly at closes_at
     * must be REFUSED (upper bound is exclusive).
     */
    public function test_applying_exactly_at_closes_at_is_refused(): void
    {
        $section = Section::factory()->create([
            'opens_at' => now()->subDays(14),
            'closes_at' => now()->addHour(),
        ]);
        $student = User::factory()->student()->create();

        $this->travelTo($section->closes_at);
        $this->actingAs($student)->post(route('student.sections.enroll', $section));

        $this->assertDatabaseMissing('enrollments', ['section_id' => $section->id, 'user_id' => $student->id]);
    }

    public function test_withdrawing_before_close_succeeds(): void
    {
        $section = $this->openSection();
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $response = $this->actingAs($student)->delete(route('student.sections.withdraw', $section));

        $response->assertSessionHas('status', "You've withdrawn from {$section->name}.");
        $this->assertDatabaseHas('enrollments', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'status' => 'withdrawn',
        ]);
    }

    public function test_withdrawing_after_close_is_refused(): void
    {
        $section = Section::factory()->create([
            'opens_at' => now()->subDays(14),
            'closes_at' => now()->subDay(),
        ]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $response = $this->actingAs($student)->delete(route('student.sections.withdraw', $section));

        $response->assertSessionHas('error', "You can no longer withdraw — this section's enrollment window has closed.");
        $this->assertDatabaseHas('enrollments', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'status' => 'enrolled',
        ]);
    }

    /**
     * EXACT-BOUNDARY — withdrawing exactly at closes_at must be REFUSED.
     */
    public function test_withdrawing_exactly_at_closes_at_is_refused(): void
    {
        $section = Section::factory()->create([
            'opens_at' => now()->subDays(14),
            'closes_at' => now()->addHour(),
        ]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $this->travelTo($section->closes_at);
        $response = $this->actingAs($student)->delete(route('student.sections.withdraw', $section));

        $response->assertSessionHas('error', "You can no longer withdraw — this section's enrollment window has closed.");
    }

    /**
     * WR-02 — withdrawing when never enrolled must be refused with an
     * explicit error flash, not the false "withdrawn" success (0 rows
     * would otherwise be silently affected).
     */
    public function test_withdrawing_when_never_enrolled_is_refused_with_an_error_flash(): void
    {
        $section = $this->openSection();
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->delete(route('student.sections.withdraw', $section));

        $response->assertSessionHas('error', "You're not currently enrolled in this section.");
        $this->assertDatabaseMissing('enrollments', ['section_id' => $section->id, 'user_id' => $student->id]);
    }

    /**
     * WR-02 — a student who was already Rejected must not be able to
     * self-service-flip their own row to Withdrawn via this endpoint,
     * silently erasing the lecturer's rejection outcome.
     */
    public function test_withdrawing_a_rejected_enrollment_is_refused_and_the_rejection_is_preserved(): void
    {
        $section = $this->openSection();
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, [
            'status' => EnrollmentStatus::Rejected,
            'rejection_reason' => RejectionReason::Other->value,
        ]);

        $response = $this->actingAs($student)->delete(route('student.sections.withdraw', $section));

        $response->assertSessionHas('error', "You're not currently enrolled in this section.");
        $this->assertDatabaseHas('enrollments', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'status' => 'rejected',
            'rejection_reason' => RejectionReason::Other->value,
        ]);
    }

    public function test_a_student_with_an_active_enrollment_cannot_apply_to_a_different_section_of_the_same_subject_and_semester(): void
    {
        $subject = Subject::factory()->create();
        $sectionA = $this->openSection(subjectId: $subject->id, year: 2026, semester: 1);
        $sectionB = $this->openSection(subjectId: $subject->id, year: 2026, semester: 1);
        $student = User::factory()->student()->create();
        $sectionA->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $response = $this->actingAs($student)->post(route('student.sections.enroll', $sectionB));

        $response->assertSessionHas('error', 'You already have an active enrollment in this subject for this semester. Withdraw first if you want to switch sections.');
        $this->assertDatabaseMissing('enrollments', ['section_id' => $sectionB->id, 'user_id' => $student->id]);
    }

    /**
     * CR-01 regression — a student already actively enrolled must be
     * refused for EVERY other sibling section of the same subject/term,
     * not just the one checked by the pre-existing single-sibling test
     * above. This exercises the fixed sibling-section lockForUpdate()
     * (EnrollmentController@store) across three sections sharing the same
     * (subject_id, year, semester), proving the added lock query does not
     * itself break the normal cross-section refusal path. True concurrent
     * interleaving is still not achievable in PHPUnit's single-threaded
     * runner — that argument rests on the structural presence of the
     * sibling-section lockForUpdate() call, verifiable by code review.
     */
    public function test_a_second_active_enrollment_in_any_sibling_section_of_the_same_subject_and_term_is_refused(): void
    {
        $subject = Subject::factory()->create();
        $sectionA = $this->openSection(subjectId: $subject->id, year: 2026, semester: 1);
        $sectionB = $this->openSection(subjectId: $subject->id, year: 2026, semester: 1);
        $sectionC = $this->openSection(subjectId: $subject->id, year: 2026, semester: 1);
        $student = User::factory()->student()->create();

        $this->actingAs($student)->post(route('student.sections.enroll', $sectionA));
        $this->assertDatabaseHas('enrollments', ['section_id' => $sectionA->id, 'user_id' => $student->id, 'status' => 'enrolled']);

        $responseB = $this->actingAs($student)->post(route('student.sections.enroll', $sectionB));
        $responseB->assertSessionHas('error', 'You already have an active enrollment in this subject for this semester. Withdraw first if you want to switch sections.');

        $responseC = $this->actingAs($student)->post(route('student.sections.enroll', $sectionC));
        $responseC->assertSessionHas('error', 'You already have an active enrollment in this subject for this semester. Withdraw first if you want to switch sections.');

        $this->assertSame(1, Enrollment::where('user_id', $student->id)->where('status', 'enrolled')->count());
    }

    /**
     * ENR-04 negative control — a different semester of the same subject
     * must NOT be blocked by the one-active-enrollment rule.
     */
    public function test_a_student_can_apply_to_the_same_subject_in_a_different_semester(): void
    {
        $subject = Subject::factory()->create();
        $sectionA = $this->openSection(subjectId: $subject->id, year: 2026, semester: 1);
        $sectionB = $this->openSection(subjectId: $subject->id, year: 2026, semester: 2);
        $student = User::factory()->student()->create();
        $sectionA->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $this->actingAs($student)->post(route('student.sections.enroll', $sectionB));

        $this->assertDatabaseHas('enrollments', ['section_id' => $sectionB->id, 'user_id' => $student->id, 'status' => 'enrolled']);
    }

    /**
     * ENR-04 negative control — a different subject entirely must NOT be
     * blocked by the one-active-enrollment rule.
     */
    public function test_a_student_can_apply_to_a_different_subject(): void
    {
        $sectionA = $this->openSection();
        $sectionB = $this->openSection();
        $student = User::factory()->student()->create();
        $sectionA->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $this->actingAs($student)->post(route('student.sections.enroll', $sectionB));

        $this->assertDatabaseHas('enrollments', ['section_id' => $sectionB->id, 'user_id' => $student->id, 'status' => 'enrolled']);
    }

    /**
     * ENR-05 — re-apply after withdraw must UPDATE the existing row, never
     * INSERT a second one. This is the assertion that catches an accidental
     * create() against the unique(section_id,user_id) index.
     */
    public function test_reapplying_after_withdrawing_updates_the_existing_row_not_a_duplicate(): void
    {
        $section = $this->openSection();
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Withdrawn]);

        $this->actingAs($student)->post(route('student.sections.enroll', $section));

        $this->assertSame(1, Enrollment::where('section_id', $section->id)->where('user_id', $student->id)->count());
        $this->assertDatabaseHas('enrollments', ['section_id' => $section->id, 'user_id' => $student->id, 'status' => 'enrolled']);
    }

    /**
     * ENR-05 — re-apply after rejection must succeed, flip status back to
     * enrolled, and CLEAR rejection_reason — a stale reason must never
     * linger on a fresh enrolled row.
     */
    public function test_reapplying_after_rejection_succeeds_and_clears_the_rejection_reason(): void
    {
        $section = $this->openSection();
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, [
            'status' => EnrollmentStatus::Rejected,
            'rejection_reason' => RejectionReason::Other->value,
        ]);

        $this->actingAs($student)->post(route('student.sections.enroll', $section));

        $this->assertSame(1, Enrollment::where('section_id', $section->id)->where('user_id', $student->id)->count());
        $this->assertDatabaseHas('enrollments', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'status' => 'enrolled',
            'rejection_reason' => null,
        ]);
    }

    /**
     * T-08-02-MA — Enrollment extends Pivot ($guarded = []), so every
     * column is mass-assignable. This is a live risk, not theoretical: a
     * forged status/rejection_reason in the POST body must never reach the
     * write.
     */
    public function test_a_forged_status_and_rejection_reason_in_the_request_are_ignored(): void
    {
        $section = $this->openSection();
        $student = User::factory()->student()->create();

        $this->actingAs($student)->post(route('student.sections.enroll', $section), [
            'status' => 'rejected',
            'rejection_reason' => RejectionReason::Other->value,
        ]);

        $this->assertDatabaseHas('enrollments', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'status' => 'enrolled',
            'rejection_reason' => null,
        ]);
    }

    /**
     * T-08-02-IDOR — a forged user_id in the POST body must never enroll
     * anyone other than the authenticated student.
     */
    public function test_a_forged_user_id_in_the_request_is_ignored_and_the_authenticated_student_is_enrolled(): void
    {
        $section = $this->openSection();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $this->actingAs($student)->post(route('student.sections.enroll', $section), [
            'user_id' => $otherStudent->id,
        ]);

        $this->assertDatabaseHas('enrollments', ['section_id' => $section->id, 'user_id' => $student->id]);
        $this->assertDatabaseMissing('enrollments', ['section_id' => $section->id, 'user_id' => $otherStudent->id]);
    }

    public function test_a_lecturer_hitting_the_student_enroll_route_is_refused_by_role_middleware(): void
    {
        $section = $this->openSection();
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->post(route('student.sections.enroll', $section));

        $response->assertForbidden();
    }
}

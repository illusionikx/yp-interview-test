<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Exam;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 10, Wave 0) — INT-04's acceptance, guarding threat T-10-01
 * (v2.0's CRITICAL cross-subject exam leak).
 *
 * This file is INT-04's negative regression AND its positive control. The
 * `assertNotSame()`/`assertSame()` subject-ID guards in each method below
 * are LOAD-BEARING, not decorative — see "the factory trap" in
 * 10-CONTEXT.md: ExamFactory and SectionFactory EACH independently call
 * Subject::factory(), so a bare Exam::factory()->create() plus a bare
 * Section::factory()->create() land in different subjects BY ACCIDENT.
 * Without the guard assertions, this test could pass while proving
 * nothing — green, while guarding an open CRITICAL leak. The negative
 * (method 1) and positive (method 2) methods must always be maintained
 * together: the positive control is what stops the negative method from
 * being satisfiable by a scopeVisibleTo() that simply denies everyone.
 */
class CrossSubjectVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_enrolled_only_in_a_different_subject_cannot_see_open_or_start_the_exam(): void
    {
        $subjectA = Subject::factory()->create();
        $subjectB = Subject::factory()->create();

        $this->assertNotSame(
            $subjectA->id,
            $subjectB->id,
            'Fixture is not exercising the cross-subject case — the negative assertions below would pass vacuously.'
        );

        $examOnA = Exam::factory()->published()->for($subjectA)->create();
        $sectionOnB = Section::factory()->for($subjectB)->create();
        $student = User::factory()->student()->create();
        $sectionOnB->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        // 1. LIST — the exam must not appear in the visible-to-this-student set.
        $this->assertFalse(
            Exam::visibleTo($student)->whereKey($examOnA->id)->exists(),
            'A student enrolled only in a different subject must not see the exam in the visible-to set.'
        );

        // 2. DIRECT ACCESS — the show route must forbid it, not merely hide it.
        $this->actingAs($student)
            ->get(route('student.exams.show', $examOnA))
            ->assertForbidden();

        // 3. START — the write path. INT-04 says "see OR start"; the pre-existing
        // regression coverage only ever exercised list+takeable, never the
        // store() write path.
        $this->actingAs($student)
            ->post(route('student.attempts.store', $examOnA))
            ->assertForbidden();

        $this->assertDatabaseMissing('attempts', [
            'exam_id' => $examOnA->id,
            'user_id' => $student->id,
        ]);
    }

    /**
     * The positive counterpart (CLS-05's control). Proves method 1 fails
     * for the RIGHT reason: a scopeVisibleTo() that denies everyone would
     * satisfy the negative test above while failing this one.
     */
    public function test_a_student_enrolled_in_the_exams_own_subject_can_see_open_and_start_it(): void
    {
        $subject = Subject::factory()->create();

        $exam = Exam::factory()->published()->for($subject)->create();
        $section = Section::factory()->for($subject)->create();

        $this->assertSame(
            $exam->subject_id,
            $section->subject_id,
            'Fixture must pin exam and section to the same subject — see the factory trap.'
        );

        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        // 1. LIST
        $this->assertTrue(
            Exam::visibleTo($student)->whereKey($exam->id)->exists(),
            'A student enrolled in the exam\'s own subject must see it in the visible-to set.'
        );

        // 2. DIRECT ACCESS
        $this->actingAs($student)
            ->get(route('student.exams.show', $exam))
            ->assertOk();

        // 3. START
        $this->actingAs($student)
            ->post(route('student.attempts.store', $exam))
            ->assertRedirect();
    }
}

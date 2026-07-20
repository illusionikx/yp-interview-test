<?php

namespace Tests\Feature\Student;

use App\Models\Exam;
use App\Models\Section;
use App\Models\User;
use App\Policies\ExamPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * RED (Phase 7, Wave 0) — the ENR-08 hard acceptance gate: the exam LIST
 * (Exam::visibleTo()) and the direct-access GATE (ExamPolicy::takeable())
 * must always agree, across every enrollment state. This is the exact
 * "hidden but reachable" divergence class this phase exists to prevent
 * (07-RESEARCH.md Pattern 1 "Regression test contract (hard acceptance
 * gate)", STATE.md blocker note).
 *
 * Expected RED until the sections/enrollments schema (07-03) and the
 * Exam::scopeVisibleTo() rewrite (07-04) land — App\Models\Section does
 * not exist yet, so every test method here errors on class resolution.
 */
class ExamVisibilityRegressionTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('enrollmentStates')]
    public function test_exam_index_and_direct_access_gate_agree(string $status, bool $expectedVisible): void
    {
        // Same-subject fixture discipline (10-03, factory trap): ExamFactory
        // and SectionFactory each mint their own Subject independently, so
        // the exam is created first and the section's subject_id is pinned
        // to it explicitly.
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();

        if ($status !== 'never_applied') {
            $section->enrollments()->attach($student->id, ['status' => $status]);
        }

        $listVisible = Exam::visibleTo($student)->whereKey($exam->id)->exists();
        $gateVisible = app(ExamPolicy::class)->takeable($student, $exam);

        $this->assertSame($expectedVisible, $listVisible);
        $this->assertSame($listVisible, $gateVisible, 'List and gate must always agree.');
    }

    public static function enrollmentStates(): array
    {
        return [
            'enrolled' => ['enrolled', true],
            'withdrawn' => ['withdrawn', false],
            'rejected' => ['rejected', false],
            'never_applied' => ['never_applied', false],
        ];
    }
}

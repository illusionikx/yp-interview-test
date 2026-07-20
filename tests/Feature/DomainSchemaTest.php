<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * RED (Phase 7, Wave 0) — rewritten for the v2.0 schema break: sections/
 * subject_user/enrollments replace classrooms/classroom_subject/
 * exam_classroom, and users.classroom_id is dropped entirely (SEC-01,
 * DEL-03). Expected RED until the schema slice lands (07-03).
 *
 * Phase 10, D-1/CLS-05: `exam_section` (the per-exam assignment pivot) is
 * ALSO gone as of `2026_07_17_100001_drop_exam_section_table.php` — exam
 * visibility/assignment is derived entirely from subject enrollment now,
 * so no assignment table exists at all. See Exam::scopeVisibleTo().
 */
class DomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Success criterion #1: every domain table exists after a fresh migration.
     */
    public function test_all_domain_tables_exist(): void
    {
        $tables = [
            'sections',
            'subjects',
            'subject_user',
            'enrollments',
            'exams',
            'questions',
            'options',
            'attempts',
            'answers',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }
    }

    /**
     * v2.0 schema break: users keeps role, but classroom_id is dropped —
     * section membership is expressed exclusively through enrollments.
     */
    public function test_users_table_has_role_and_no_longer_has_classroom_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'role'));
        $this->assertFalse(Schema::hasColumn('users', 'classroom_id'));
    }

    /**
     * D-02: single-attempt integrity guarantee, baked into the create migration.
     */
    public function test_attempts_table_has_composite_unique_index_on_exam_id_and_user_id(): void
    {
        $indexes = Schema::getIndexes('attempts');

        $hasUniqueCompositeIndex = collect($indexes)->contains(
            fn (array $index) => $index['unique'] === true
                && $index['columns'] === ['exam_id', 'user_id']
        );

        $this->assertTrue($hasUniqueCompositeIndex, 'Expected attempts to have a unique composite index on (exam_id, user_id).');
    }

    /**
     * D-02: one answer row per question per attempt, baked into the create migration.
     */
    public function test_answers_table_has_composite_unique_index_on_attempt_id_and_question_id(): void
    {
        $indexes = Schema::getIndexes('answers');

        $hasUniqueCompositeIndex = collect($indexes)->contains(
            fn (array $index) => $index['unique'] === true
                && $index['columns'] === ['attempt_id', 'question_id']
        );

        $this->assertTrue($hasUniqueCompositeIndex, 'Expected answers to have a unique composite index on (attempt_id, question_id).');
    }

    /**
     * ENR-08 prerequisite: a student can hold at most one enrollment row per
     * section, enforced at the schema layer (not just application logic).
     */
    public function test_enrollments_table_has_composite_unique_index_on_section_id_and_user_id(): void
    {
        $indexes = Schema::getIndexes('enrollments');

        $hasUniqueCompositeIndex = collect($indexes)->contains(
            fn (array $index) => $index['unique'] === true
                && $index['columns'] === ['section_id', 'user_id']
        );

        $this->assertTrue($hasUniqueCompositeIndex, 'Expected enrollments to have a unique composite index on (section_id, user_id).');
    }
}

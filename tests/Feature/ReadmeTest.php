<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * RED (Phase 6, Wave 0) — pins the delivered README content contract
 * (DEL-02, D-04). The repo currently ships the Laravel-default README,
 * so this test is expected to FAIL until 06-03 replaces it.
 *
 * No database access — this test only reads the README.md file at the
 * project root.
 */
class ReadmeTest extends TestCase
{
    public function test_readme_documents_setup_and_credentials(): void
    {
        $this->assertFileExists(base_path('README.md'));

        $contents = file_get_contents(base_path('README.md'));

        $this->assertStringContainsString('Online Examination Portal', $contents);
        $this->assertStringContainsString('composer install', $contents);
        $this->assertStringContainsString('npm install', $contents);
        $this->assertStringContainsString('yp-student-exam', $contents);
        $this->assertStringContainsString('migrate:fresh --seed', $contents);
        $this->assertStringContainsString('lecturer@example.com', $contents);
        $this->assertStringContainsString('student@example.com', $contents);
    }
}

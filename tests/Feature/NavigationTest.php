<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7, Plan 05 (UI-01/UI-02) — proves the Flowbite top-navbar shell
 * renders for both roles: the app wordmark plus role-scoped links that
 * only point at routes that already exist in Phase 7. This is the
 * acceptance gate for the rebuilt navigation.blade.php shell.
 *
 * Wordmark copy updated in Phase 9 plan 09-08 (UX-01): "Exam Portal" ->
 * "Online Examination Portal".
 *
 * Phase 11, Plan 01 (NAV-03) — the navbar trim relabels the lecturer's
 * "Sections" interim link to "Classes" and drops the redundant "Subjects"
 * primary link entirely (the home page becomes the subject-list hub).
 * These are intentional relabels, not regressions.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecturer_sees_the_navbar_wordmark_and_role_scoped_links(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.home'));

        $response->assertOk();
        $response->assertSee('Online Examination Portal');
        $response->assertSee('Classes');
        $response->assertSee('Exams');
    }

    public function test_student_sees_the_navbar_wordmark_and_role_scoped_links(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.home'));

        $response->assertOk();
        $response->assertSee('Online Examination Portal');
        $response->assertSee('My Exams');
    }
}

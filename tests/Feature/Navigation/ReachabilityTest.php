<?php

namespace Tests\Feature\Navigation;

use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 11, Plan 01 (NAV-04) — the two-direction reachability audit.
 *
 * Before the NAV-03 trim removed any nav link, every destination reachable
 * before the restructure had to be proven to still have a path in the new
 * hierarchy. This test is that proof, not a pixel check: every assertion is
 * a route-reachability / link-presence check against the interim links kept
 * in navigation.blade.php (retired only once Phase 12/13 build their
 * permanent homes) plus the guest landing entry point.
 */
class ReachabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecturer_home_links_to_classes_and_help(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.home'));

        $response->assertOk();
        $response->assertSee(route('lecturer.sections.index'), false);
        $response->assertSee(route('lecturer.help.show'), false);
    }

    /**
     * CLS-04 (12-04): the interim "Exams" nav link is retired — the
     * unscoped exams.index it pointed to now redirects home
     * (ExamControllerTest covers that redirect). Exam reachability lives in
     * the drill-down path this asserts: lecturer.home -> a subject's
     * Manage page -> its Exams tab -> the exam editor (exams.show).
     */
    public function test_lecturer_exams_index_redirects_home_and_exams_stay_reachable_via_the_subject_hub(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $subject->lecturers()->attach($lecturer);
        $exam = Exam::factory()->for($subject)->create();

        $indexResponse = $this->actingAs($lecturer)->get(route('lecturer.exams.index'));
        $indexResponse->assertRedirect(route('lecturer.home'));

        $hubResponse = $this->actingAs($lecturer)->get(route('lecturer.subjects.manage', $subject).'?tab=exams');
        $hubResponse->assertOk();
        $hubResponse->assertSee(route('lecturer.exams.show', $exam), false);
    }

    public function test_lecturer_results_index_stays_reachable_for_a_seeded_exam(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.results.index', $exam));

        $response->assertOk();
    }

    public function test_student_home_links_to_class_enrollment_my_exams_and_help(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.home'));

        $response->assertOk();
        $response->assertSee(route('student.subjects.index'), false);
        $response->assertSee(route('student.exams.index'), false);
        $response->assertSee(route('student.help.show'), false);
    }

    public function test_guest_landing_renders_the_branded_landing_and_links_to_login_and_register(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Online Examination Portal');
        $response->assertSee(route('login'), false);

        // Second hop: login itself links onward to register, so the full
        // guest path (landing -> login -> register) stays reachable.
        $loginResponse = $this->get(route('login'));

        $loginResponse->assertOk();
        $loginResponse->assertSee(route('register'), false);
    }
}

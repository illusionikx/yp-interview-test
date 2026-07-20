<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8, Plan 09 (DEL-04/DEL-05) — reachability, role-scoping, nav
 * rendering, and heading-coverage for the two in-app manuals. This is the
 * drift guard: it can prove the manuals exist, are reachable only by their
 * own role, and carry every required section heading — it cannot verify
 * the PROSE is accurate, which is Task 3's human content review.
 *
 * Phase 11, Plan 01 (NAV-03) — navbar labels relabelled: lecturer
 * "Sections" -> "Classes" (and "Subjects" primary link removed), student
 * "Enroll" -> "Class enrollment". Intentional relabels, not regressions.
 *
 * Phase 14, Plan 02 (UX-05/DEL-06) — the manuals were rebuilt wiki-style
 * (a topic index + cross-links between topics) with copy quoting the
 * shipped phases-11–13 screens verbatim; the old linear v2.0 heading
 * assertions below were replaced accordingly. See 14-02-SUMMARY.md for
 * the topic taxonomy and the verbatim-label accuracy evidence table.
 */
class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_can_load_the_student_manual(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.help.show'));

        $response->assertOk();
    }

    public function test_a_lecturer_can_load_the_lecturer_manual(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.help.show'));

        $response->assertOk();
    }

    public function test_a_student_cannot_load_the_lecturer_manual(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('lecturer.help.show'));

        $response->assertForbidden();
    }

    public function test_a_lecturer_cannot_load_the_student_manual(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->get(route('student.help.show'));

        $response->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login_from_the_student_manual(): void
    {
        $response = $this->get(route('student.help.show'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_guest_is_redirected_to_login_from_the_lecturer_manual(): void
    {
        $response = $this->get(route('lecturer.help.show'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_student_navbar_renders_links_to_class_enrollment_my_exams_and_help(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.home'));

        $response->assertOk();
        $response->assertSee('Class enrollment');
        $response->assertSee('My Exams');
        // UX-05: Help is now the icon button beside the theme toggle, not a
        // text nav link — assert the button's href resolves to the manual
        // and it carries an accessible "Help" name (aria-label + sr-only).
        $response->assertSee(route('student.help.show'), false);
        $response->assertSee('aria-label="Help"', false);
    }

    public function test_the_lecturer_navbar_renders_links_to_classes_exams_and_help(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.home'));

        $response->assertOk();
        $response->assertSee('Classes');
        $response->assertSee('Exams');
        // UX-05: Help is now the icon button beside the theme toggle, not a
        // text nav link — assert the button's href resolves to the manual
        // and it carries an accessible "Help" name (aria-label + sr-only).
        $response->assertSee(route('lecturer.help.show'), false);
        $response->assertSee('aria-label="Help"', false);
    }

    public function test_the_student_manual_contains_all_five_topic_headings(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.help.show'));

        $response->assertOk();
        $response->assertSee('Home &amp; dashboard', false);
        $response->assertSee('Enrolling in a class');
        $response->assertSee('Your subjects &amp; class page', false);
        $response->assertSee('Taking a timed exam');
        $response->assertSee('Viewing your results');
    }

    public function test_the_student_manual_renders_a_topic_index_with_cross_links(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.help.show'));

        $response->assertOk();
        // Topic index anchors (one per topic).
        $response->assertSee('href="#topic-home"', false);
        $response->assertSee('href="#topic-enrolling"', false);
        $response->assertSee('href="#topic-class-page"', false);
        $response->assertSee('href="#topic-taking-exam"', false);
        $response->assertSee('href="#topic-results"', false);
        // Cross-links between topic sections — the anchor targets above
        // each appear at least a second time as an in-prose cross-link, so
        // the manual is wiki-style and not just a table of contents.
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($response->getContent(), 'href="#topic-class-page"'),
            'expected "Your subjects & class page" to be linked from both the topic index and a cross-link'
        );
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($response->getContent(), 'href="#topic-results"'),
            'expected "Viewing your results" to be linked from both the topic index and a cross-link'
        );
    }

    public function test_the_student_manual_quotes_shipped_labels_verbatim(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.help.show'));

        $response->assertOk();
        // student/subjects/index.blade.php header + enroll action.
        $response->assertSee('Class enrollment');
        $response->assertSee('Enroll');
        // student/subjects/class.blade.php availability pill words.
        $response->assertSee('Available');
        $response->assertSee('Closed');
        // student/attempts/show.blade.php submit action.
        $response->assertSee('Submit Exam');
        // student/results/show.blade.php result labels.
        $response->assertSee('Your Result');
        $response->assertSee('Awaiting grading');
    }

    public function test_the_lecturer_manual_contains_all_six_topic_headings(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.help.show'));

        $response->assertOk();
        $response->assertSee('Home &amp; dashboard', false);
        $response->assertSee('Managing subjects');
        $response->assertSee('Class management (Classes tab)');
        $response->assertSee('Managing exams (Exams tab)');
        $response->assertSee('The exam editor');
        $response->assertSee('Grading');
    }

    public function test_the_lecturer_manual_renders_a_topic_index_with_cross_links(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.help.show'));

        $response->assertOk();
        // Topic index anchors (one per topic).
        $response->assertSee('href="#topic-home"', false);
        $response->assertSee('href="#topic-subjects"', false);
        $response->assertSee('href="#topic-classes-tab"', false);
        $response->assertSee('href="#topic-exams-tab"', false);
        $response->assertSee('href="#topic-exam-editor"', false);
        $response->assertSee('href="#topic-grading"', false);
        // Cross-links between topic sections.
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($response->getContent(), 'href="#topic-exam-editor"'),
            'expected "The exam editor" to be linked from both the topic index and a cross-link'
        );
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($response->getContent(), 'href="#topic-grading"'),
            'expected "Grading" to be linked from both the topic index and a cross-link'
        );
    }

    public function test_the_lecturer_manual_quotes_shipped_labels_verbatim(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.help.show'));

        $response->assertOk();
        // lecturer/exams/questions/_form.blade.php + questions tab reorder controls.
        $response->assertSee('Move question up');
        $response->assertSee('Move question down');
        $response->assertSee('Shuffle options');
        // lecturer/subjects/partials/_exams-tab.blade.php reset action.
        $response->assertSee('Reset submissions');
        // lecturer/subjects/partials/_exams-tab.blade.php draft/active toggle labels.
        $response->assertSee('Publish');
        $response->assertSee('Unpublish');
        // lecturer/results/show.blade.php grading action.
        $response->assertSee('Save Score');
    }
}

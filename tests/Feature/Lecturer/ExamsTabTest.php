<?php

namespace Tests\Feature\Lecturer;

use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CLS-04/CLS-08 (12-04) — the Exams tab: subject-scoped listing with
 * create/edit/delete, per-exam grading progress as a bounded aggregate,
 * the shipped CLS-06 toggle and CLS-07 reset surfaced inline, and the
 * folded (redirect-home) `lecturer.exams.index`.
 */
class ExamsTabTest extends TestCase
{
    use RefreshDatabase;

    private function assignedLecturerFor(Subject $subject): User
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject->lecturers()->attach($lecturer);

        return $lecturer;
    }

    public function test_the_exams_tab_lists_the_subjects_exams_with_a_new_exam_link(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = $this->assignedLecturerFor($subject);

        $examOne = Exam::factory()->for($subject)->create(['title' => 'Midterm Exam']);
        $examTwo = Exam::factory()->for($subject)->create(['title' => 'Final Exam']);

        $response = $this->actingAs($lecturer)->get(
            route('lecturer.subjects.manage', $subject).'?tab=exams'
        );

        $response->assertOk();
        $response->assertSee($examOne->title);
        $response->assertSee($examTwo->title);
        $response->assertSee(route('lecturer.exams.create', ['subject' => $subject]), false);
    }

    public function test_grading_progress_shows_the_correct_graded_over_total_count(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = $this->assignedLecturerFor($subject);
        $exam = Exam::factory()->for($subject)->create();

        Attempt::factory()->for($exam)->for(User::factory()->student())->graded(5)->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->submitted()->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->create();

        $response = $this->actingAs($lecturer)->get(
            route('lecturer.subjects.manage', $subject).'?tab=exams'
        );

        $response->assertOk();
        $response->assertViewHas('exams', function ($exams) use ($exam) {
            $found = $exams->firstWhere('id', $exam->id);

            return $found->attempts_count === 3 && $found->graded_attempts_count === 1;
        });
        $response->assertSee('1 / 3 graded');
    }

    public function test_an_exam_with_no_attempts_shows_no_attempts_yet(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = $this->assignedLecturerFor($subject);
        Exam::factory()->for($subject)->create();

        $response = $this->actingAs($lecturer)->get(
            route('lecturer.subjects.manage', $subject).'?tab=exams'
        );

        $response->assertOk();
        $response->assertSee('No attempts yet');
    }

    public function test_a_published_row_exposes_the_unpublish_form_and_a_draft_row_the_publish_form(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = $this->assignedLecturerFor($subject);

        $published = Exam::factory()->for($subject)->published()->create();
        $draft = Exam::factory()->for($subject)->create(['is_published' => false]);

        $response = $this->actingAs($lecturer)->get(
            route('lecturer.subjects.manage', $subject).'?tab=exams'
        );

        $response->assertOk();
        $response->assertSee(route('lecturer.exams.unpublish', $published), false);
        $response->assertSee(route('lecturer.exams.publish', $draft), false);
    }

    public function test_an_exam_with_attempts_exposes_a_reset_control_with_the_counts(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = $this->assignedLecturerFor($subject);
        $exam = Exam::factory()->for($subject)->published()->create();

        Attempt::factory()->for($exam)->for(User::factory()->student())->submitted()->create();
        Attempt::factory()->for($exam)->for(User::factory()->student())->graded(5)->create();

        $response = $this->actingAs($lecturer)->get(
            route('lecturer.subjects.manage', $subject).'?tab=exams'
        );

        $response->assertOk();
        $response->assertSee(route('lecturer.exams.submissions.reset', $exam), false);
        // INT-02 — the modal body states the notYetGraded vs graded split exactly.
        $response->assertSee('1 student(s) have started this exam but have not been graded, and', false);
        $response->assertSee('1 student(s) have already been graded.', false);
    }

    public function test_an_exam_with_zero_attempts_shows_the_reset_control_disabled(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = $this->assignedLecturerFor($subject);
        $exam = Exam::factory()->for($subject)->published()->create();

        $response = $this->actingAs($lecturer)->get(
            route('lecturer.subjects.manage', $subject).'?tab=exams'
        );

        $response->assertOk();
        $content = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/<button type="button" disabled[^>]*>\s*Reset submissions/',
            $content
        );
    }

    public function test_each_row_links_to_the_grading_page(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = $this->assignedLecturerFor($subject);
        $exam = Exam::factory()->for($subject)->create();

        $response = $this->actingAs($lecturer)->get(
            route('lecturer.subjects.manage', $subject).'?tab=exams'
        );

        $response->assertOk();
        $response->assertSee(route('lecturer.results.index', $exam), false);
    }

    public function test_a_lecturer_visiting_the_exams_index_is_redirected_home(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)->get(route('lecturer.exams.index'));

        $response->assertRedirect(route('lecturer.home'));
    }

    public function test_the_create_form_pre_selects_the_subject_and_the_created_exam_belongs_to_it(): void
    {
        $subject = Subject::factory()->create();
        $lecturer = $this->assignedLecturerFor($subject);

        $createResponse = $this->actingAs($lecturer)->get(
            route('lecturer.exams.create', ['subject' => $subject])
        );

        $createResponse->assertOk();
        // The subject is locked to the page it was added from: a hidden
        // subject_id plus a read-only display of the subject name.
        $createResponse->assertSee('name="subject_id" value="'.$subject->id.'"', false);
        $createResponse->assertSee($subject->name);

        $storeResponse = $this->actingAs($lecturer)->post(route('lecturer.exams.store'), [
            'subject_id' => $subject->id,
            'title' => 'Scoped Exam',
            'duration_minutes' => 45,
        ]);

        $exam = Exam::firstWhere('title', 'Scoped Exam');

        $storeResponse->assertRedirect(route('lecturer.exams.show', $exam));
        $this->assertSame($subject->id, $exam->subject_id);
    }
}

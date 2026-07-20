<?php

namespace Tests\Browser;

use App\Enums\EnrollmentStatus;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Support\Semester;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * TEST-03: the primary lecturer journey — open a subject's management hub,
 * switch between its Classes and Exams tabs, open the exam editor, and
 * reach the grading page — driven entirely by CLICKING through the real
 * navigation (nav links, buttons, tabs) as a real user would, never by
 * visiting routes directly. This is what proves NAV-04 reachability through
 * the actual rendered UI, not just that the routes exist.
 *
 * Fixtures are seeded directly into THIS test's own database via factories
 * (never the demo DatabaseSeeder — see tests/DuskTestCase.php's
 * DatabaseTruncation, Decision #7: Dusk's DB is separate from, and reset
 * independently of, the documented yp-student-exam demo database).
 *
 * Decision #6 (LOCKED): the browser's native page-leave confirmation dialog
 * (v2.0 AVL-05) is deliberately NOT scripted anywhere in this file — it is
 * only reachable from the student take-exam page, not the lecturer area,
 * and stays a manual verification step regardless (14-04-PLAN.md Task 3).
 */
class LecturerFlowTest extends DuskTestCase
{
    public function test_a_lecturer_reaches_the_exam_editor_and_grading_page_by_clicking_through_the_nav(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $subject = Subject::factory()->create(['name' => 'Linear Algebra']);
        $subject->lecturers()->attach($lecturer->id);

        // The Classes tab only lists a class in the NOT-behind-a-toggle
        // "current or future" group when the section's (year, semester)
        // resolves to the current Semester (SubjectManageController::show())
        // — pin it explicitly rather than trusting the factory's random
        // year/semester.
        $current = Semester::current();
        $section = Section::factory()->create([
            'subject_id' => $subject->id,
            'year' => $current->year,
            'semester' => $current->number,
        ]);

        $exam = Exam::factory()->published()->available()->create([
            'subject_id' => $subject->id,
            'title' => 'Dusk Linear Algebra Final',
        ]);

        $question = Question::factory()->open()->create([
            'exam_id' => $exam->id,
            'body' => 'Explain the rank-nullity theorem.',
            'points' => 5,
            'position' => 0,
        ]);

        // A submitted-but-ungraded attempt so the results index shows a
        // real "Grade" link (not "No submissions yet") and the grading page
        // has an actual open-text answer to display.
        $student = User::factory()->student()->create(['name' => 'Dusk Test Student']);
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);
        $attempt = Attempt::factory()->for($exam)->for($student)->submitted()->create();
        Answer::factory()->openText()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answer_text' => 'Dusk essay answer: rank plus nullity equals the domain dimension.',
        ]);

        $this->browse(function (Browser $browser) use ($lecturer, $subject, $exam, $question, $student) {
            $browser->loginAs($lecturer)
                ->visit('/dashboard')
                ->waitForText('Your subjects')
                // NAV-04: click through the nav to the subject's two-tab hub
                // — never visit(route(...)) directly. On the dashboard the
                // subject NAME is the link to the management hub
                // (lecturer.subjects.manage); there is no separate "Manage"
                // label on this row (that's "Edit" for the subject form).
                ->clickLink($subject->name)
                ->waitForText($subject->name)
                ->assertSee('Classes')
                // Tab switch (Alpine x-show, not a route navigation).
                ->press('Exams')
                ->waitForText($exam->title)
                ->clickLink($exam->title)
                // Lands on the exam editor (lecturer.exams.show).
                ->waitForText('Details')
                ->assertSee('Questions')
                ->press('Questions')
                ->waitForText($question->body)
                ->clickLink('View Results')
                ->waitForText('Grading progress')
                ->clickLink('Grade')
                // Lands on the per-attempt grading page (lecturer.results.show).
                ->waitForText($student->name)
                ->assertSee($question->body)
                ->assertSee('Dusk essay answer');
        });
    }
}

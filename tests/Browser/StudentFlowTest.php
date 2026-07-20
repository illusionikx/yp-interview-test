<?php

namespace Tests\Browser;

use App\Enums\EnrollmentStatus;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Support\Semester;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * TEST-03: the primary student journey — reach an exam from the class page,
 * start it, answer a question, submit, and land on the confirmation — driven
 * entirely by CLICKING through the real navigation (nav links, buttons) as a
 * real user would, never by visiting routes directly. This is what proves
 * NAV-04 reachability through the actual rendered UI, not just that the
 * routes exist.
 *
 * Fixtures are seeded directly into THIS test's own database via factories
 * (never the demo DatabaseSeeder — see tests/DuskTestCase.php's
 * DatabaseTruncation, Decision #7: Dusk's DB is separate from, and reset
 * independently of, the documented yp-student-exam demo database).
 *
 * Decision #6 (LOCKED): the browser's native page-leave confirmation dialog
 * (v2.0 AVL-05) is deliberately NOT scripted anywhere in this file —
 * ChromeDriver 126+ auto-dismisses that dialog before Dusk's dialog API ever
 * observes it, so it stays a manual verification step (14-04-PLAN.md Task 3).
 */
class StudentFlowTest extends DuskTestCase
{
    public function test_a_student_reaches_and_submits_an_exam_by_clicking_through_the_nav(): void
    {
        $student = User::factory()->student()->create();

        $subject = Subject::factory()->create(['name' => 'Discrete Mathematics']);

        // Student::home only lists a subject in the NOT-behind-a-toggle
        // "current or future" group when the section's (year, semester)
        // resolves to the current Semester (App\Http\Controllers\Student\
        // HomeController) — pin it explicitly rather than trusting the
        // section factory's random year/semester, which would otherwise
        // intermittently hide the exam behind "Show past semesters".
        $current = Semester::current();
        $section = Section::factory()->create([
            'subject_id' => $subject->id,
            'year' => $current->year,
            'semester' => $current->number,
        ]);
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $exam = Exam::factory()->published()->available()->create([
            'subject_id' => $subject->id,
            'title' => 'Dusk Midterm Exam',
        ]);

        $question = Question::factory()->mcq()->create([
            'exam_id' => $exam->id,
            'position' => 0,
        ]);
        $correctOption = $question->options()->where('is_correct', true)->firstOrFail();

        $this->browse(function (Browser $browser) use ($student, $subject, $exam, $question, $correctOption) {
            $browser->loginAs($student)
                ->visit('/dashboard')
                ->waitForText('Your subjects')
                // NAV-04: click through the nav to the class page — never
                // visit(route(...)) directly.
                ->clickLink('Open class page')
                ->waitForText($subject->name)
                ->assertSee($exam->title)
                ->clickLink('Start')
                ->waitForText('Proceed')
                ->assertSee($exam->title)
                ->press('Proceed')
                // Lands on the take-exam page (student.attempts.show).
                ->waitForText($exam->title)
                ->radio('question_'.$question->id, (string) $correctOption->id)
                // The MCQ selection fires an autosave POST (@change="save(...)")
                // — an Alpine-driven status flip, so it's waited on rather than
                // assumed instantaneous.
                ->waitForText('Saved')
                ->press('Submit Exam')
                // Opens the confirm-submit x-modal (Alpine x-show, not a
                // fixed-duration reveal).
                ->waitForText('Submit this exam?')
                ->press('Yes, Submit')
                ->waitForText('Exam submitted')
                ->assertSee('Your answers have been recorded')
                // A further nav click proving the confirmation page's own
                // link back into the reachable nav graph (NAV-04).
                ->clickLink('Back to my exams')
                ->waitForText('My exams')
                ->assertSee($exam->title)
                ->assertSee('View result');
        });
    }
}

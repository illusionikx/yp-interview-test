<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Support\Semester;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 7, Wave 0) — rewritten for the v2.0 section/enrollment demo
 * graph (SEC-01/02/03, ENR-08, DEL-03). The seeder is still Phase 6's
 * classroom-shaped graph, so every assertion below is expected to FAIL/
 * ERROR until 07-07 rewrites DatabaseSeeder::run() against the new
 * sections/subject_user/enrollments schema.
 *
 * Entities are looked up by natural/business key (email, section name,
 * subject code, exam title) — NEVER by numeric id (06-RESEARCH.md
 * Pitfall 3: MySQL auto-increment does not reset between seeder runs).
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_builds_full_demo_graph(): void
    {
        $this->seed(DatabaseSeeder::class);

        // --- Users -----------------------------------------------------
        $lecturer = User::where('email', 'lecturer@example.com')->firstOrFail();
        $this->assertSame(Role::Lecturer, $lecturer->role);
        $this->assertNotNull($lecturer->email_verified_at);

        $student1 = User::where('email', 'student@example.com')->firstOrFail();
        $this->assertSame(Role::Student, $student1->role);
        $this->assertNotNull($student1->email_verified_at);

        $student2 = User::where('email', 'student2@example.com')->firstOrFail();
        $this->assertSame(Role::Student, $student2->role);
        $this->assertNotNull($student2->email_verified_at);

        // --- Subjects + subject_user pivot (SEC-03) -------------------------
        $mathematics = Subject::where('code', 'MATH101')->firstOrFail();
        $this->assertSame('Mathematics', $mathematics->name);

        $science = Subject::where('code', 'SCI101')->firstOrFail();
        $this->assertSame('Science', $science->name);

        $this->assertTrue(
            $mathematics->lecturers()->whereKey($lecturer->id)->exists(),
            'Expected at least one subject_user row assigning the demo lecturer to Mathematics.'
        );

        // --- Sections (SEC-01) ----------------------------------------------
        $section = Section::where('subject_id', $mathematics->id)->firstOrFail();
        $this->assertMatchesRegularExpression(
            '/^\d+-\d+-\d+$/',
            $section->name,
            'Expected the seeded section name to resolve to the year-semester-sequence form.'
        );

        // --- Enrollments (ENR-08 seed-time consumer) -------------------------
        $enrolledPivot = $section->enrollments()
            ->wherePivot('user_id', $student1->id)
            ->wherePivot('status', EnrollmentStatus::Enrolled)
            ->first();
        $this->assertNotNull($enrolledPivot, 'Expected at least one enrolled enrollments row for the demo student.');

        // --- Exam ----------------------------------------------------------
        $exam = Exam::where('title', 'Mathematics Midterm')->firstOrFail();
        $this->assertSame($mathematics->id, $exam->subject_id);
        $this->assertTrue($exam->is_published);
        $this->assertSame(30, $exam->duration_minutes);

        $mcqQuestion = $exam->questions()->where('type', QuestionType::Mcq)->firstOrFail();
        $openQuestion = $exam->questions()->where('type', QuestionType::Open)->firstOrFail();
        $this->assertSame(1, $exam->questions()->where('type', QuestionType::Mcq)->count());
        $this->assertSame(1, $exam->questions()->where('type', QuestionType::Open)->count());

        $mcqOptions = Option::where('question_id', $mcqQuestion->id)->get();
        $this->assertGreaterThanOrEqual(2, $mcqOptions->count());
        $this->assertSame(1, $mcqOptions->where('is_correct', true)->count());

        // --- Subject-derived visibility (Phase 10, D-1/CLS-05) ---------------
        // No exam_section pivot exists anymore — assert the actual CLS-05
        // outcome through the real predicate: the seeded demo student
        // (Enrolled in the first Mathematics section) can see the exam.
        $this->assertTrue(Exam::visibleTo($student1)->whereKey($exam->id)->exists());

        // --- Pre-graded demo attempt (student2) -------------------------------
        $attempt = Attempt::where('exam_id', $exam->id)->where('user_id', $student2->id)->firstOrFail();
        $this->assertSame('submitted', $attempt->status);

        $mcqAnswer = Answer::where('attempt_id', $attempt->id)->where('question_id', $mcqQuestion->id)->firstOrFail();
        $this->assertTrue($mcqAnswer->is_correct);
        $this->assertEquals($mcqQuestion->points, $mcqAnswer->score);

        $openAnswer = Answer::where('attempt_id', $attempt->id)->where('question_id', $openQuestion->id)->firstOrFail();
        $this->assertNull($openAnswer->score);
    }

    public function test_seeder_is_idempotent_on_repeat_runs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $countsAfterFirstRun = [
            'users' => User::count(),
            'sections' => Section::count(),
            'subjects' => Subject::count(),
            'exams' => Exam::count(),
            'questions' => Question::count(),
            'options' => Option::count(),
            'attempts' => Attempt::count(),
            'answers' => Answer::count(),
        ];

        $this->seed(DatabaseSeeder::class);

        $countsAfterSecondRun = [
            'users' => User::count(),
            'sections' => Section::count(),
            'subjects' => Subject::count(),
            'exams' => Exam::count(),
            'questions' => Question::count(),
            'options' => Option::count(),
            'attempts' => Attempt::count(),
            'answers' => Answer::count(),
        ];

        $this->assertSame($countsAfterFirstRun, $countsAfterSecondRun);
    }

    /**
     * SEED-01: many uniquely-named, titled lecturers; many uniquely-named,
     * untitled students; and title exclusivity (zero students carry a
     * Dr/Prof/PhD title).
     */
    public function test_seeder_creates_many_titled_lecturers_and_untitled_students(): void
    {
        $this->seed(DatabaseSeeder::class);

        // No trailing \b: "Dr." / "Prof." are followed by a space, and \b
        // never matches between two non-word characters (".", " "), so a
        // trailing boundary after the period would silently never match.
        $titleRegex = '/\b(Dr\.|Prof\.|PhD)/';

        $this->assertGreaterThanOrEqual(8, User::where('role', Role::Lecturer)->count());
        $this->assertGreaterThanOrEqual(25, User::where('role', Role::Student)->count());

        $titledLecturers = User::where('role', Role::Lecturer)->get()
            ->filter(fn (User $u) => preg_match($titleRegex, $u->name) === 1);
        $this->assertGreaterThanOrEqual(6, $titledLecturers->count());

        $titledStudents = User::where('role', Role::Student)->get()
            ->filter(fn (User $u) => preg_match($titleRegex, $u->name) === 1);
        $this->assertCount(0, $titledStudents, 'No student should carry a Dr/Prof/PhD title.');
    }

    /**
     * SEED-03: at least 3 more subjects beyond the original Mathematics/
     * Science pair, each with at least one Section and one Exam.
     */
    public function test_seeder_adds_further_subjects_with_sections_and_exams(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(5, Subject::count());

        foreach (['ENG101', 'HIST101', 'PHYS101', 'CHEM101', 'CS101'] as $code) {
            $subject = Subject::where('code', $code)->firstOrFail();
            $this->assertGreaterThanOrEqual(1, Section::where('subject_id', $subject->id)->count());
            $this->assertGreaterThanOrEqual(1, Exam::where('subject_id', $subject->id)->count());
        }
    }

    /**
     * SEED-02: past-semester data holding a filled (capacity-reached),
     * graded exam, dated through App\Support\Semester.
     */
    public function test_seeder_holds_past_semester_graded_and_filled_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $pastSubject = Subject::where('code', 'HIST201')->firstOrFail();
        $pastSection = Section::where('subject_id', $pastSubject->id)->firstOrFail();

        $pastSemester = Semester::forDate($pastSection->opens_at);
        $this->assertTrue($pastSemester->isPast(), 'Expected the seeded past section to resolve to a past Semester.');

        $enrolledCount = $pastSection->enrollments()
            ->wherePivot('status', EnrollmentStatus::Enrolled)
            ->count();
        $this->assertSame($pastSection->capacity, $enrolledCount, 'Expected the past section to be filled to capacity.');

        $pastExam = Exam::where('subject_id', $pastSubject->id)->firstOrFail();
        $this->assertSame('closed', $pastExam->availabilityState());

        $gradedAttempts = Attempt::where('exam_id', $pastExam->id)->where('status', 'graded')->count();
        $this->assertGreaterThanOrEqual(1, $gradedAttempts, 'Expected at least one graded attempt on the past exam.');
    }

    /**
     * SEED-02: every EnrollmentStatus, every Attempt status, and every
     * Exam::availabilityState() value is exercised somewhere in the seed.
     */
    public function test_seeder_exercises_every_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (EnrollmentStatus::cases() as $status) {
            $this->assertTrue(
                Enrollment::where('status', $status)->exists(),
                "Expected at least one enrollment with status '{$status->value}'."
            );
        }

        foreach (['in_progress', 'submitted', 'graded'] as $status) {
            $this->assertTrue(
                Attempt::where('status', $status)->exists(),
                "Expected at least one attempt with status '{$status}'."
            );
        }

        $states = Exam::all()->map(fn (Exam $exam) => $exam->availabilityState());
        foreach (['opening', 'available', 'closed'] as $state) {
            $this->assertTrue(
                $states->contains($state),
                "Expected at least one exam with availabilityState '{$state}'."
            );
        }
    }
}

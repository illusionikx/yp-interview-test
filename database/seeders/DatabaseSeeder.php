<?php

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Enums\QuestionType;
use App\Enums\RejectionReason;
use App\Enums\Role;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Services\AttemptGrader;
use App\Support\Semester;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Builds the full, idempotent DEL-03 v2.0 demo graph: 1 Lecturer + 3
     * verified Students, 2 Subjects (the demo lecturer assigned to
     * Mathematics via subject_user), 2 Sections under Mathematics, sample
     * enrollments spanning states, and 1 published time-limited Exam
     * (MCQ + open-text question) under Mathematics. Visibility is
     * subject-derived (Phase 10, D-1/CLS-05) — there is no per-exam
     * section assignment, so any student Enrolled in either Mathematics
     * section automatically sees the exam. Every named/demo row uses
     * firstOrCreate on a natural key (never updateOrCreate — a re-seed
     * must not clobber a reviewer's manual edits, 06-RESEARCH.md
     * Pattern 1).
     */
    public function run(): void
    {
        $lecturer = $this->seedLecturer();
        $students = $this->seedStudents();

        [$mathematics, $science] = $this->seedSubjects();

        // subject_user pivot (SEC-03) — sync() is inherently idempotent
        // (Pattern 2). The demo lecturer manages Mathematics; Science has
        // no assigned lecturer yet in this demo graph.
        $mathematics->lecturers()->syncWithoutDetaching([$lecturer->id]);

        [$section, $secondSection] = $this->seedSections($mathematics);

        // SEED-01/SEED-03 — bulk titled lecturers, bulk untitled students,
        // and 3-5 further subjects/sections/exams. Runs after the original
        // named demo graph above so the count-guards below always see the
        // named accounts already present.
        $this->seedBulkLecturers();
        $this->seedBulkStudents();

        // orderBy('id') (14-REVIEW WR-02): the two pools below are partitioned
        // by index slice() in seedBulkSubjects() and seedPastSemester(). A bare
        // ->get() leaves ordering implementation-defined, so which student lands
        // in which partition would drift between seed runs. Pinning the order
        // keeps a re-run of `migrate:fresh --seed` reproducible for a grader.
        $bulkLecturers = User::where('role', Role::Lecturer)
            ->where('email', '!=', 'lecturer@example.com')
            ->orderBy('id')
            ->get();

        $bulkStudents = User::where('role', Role::Student)
            ->whereNotIn('email', ['student@example.com', 'student2@example.com', 'student3@example.com'])
            ->orderBy('id')
            ->get();

        $bulkExams = $this->seedBulkSubjects($bulkLecturers, $bulkStudents);

        // Enrollments (ENR-08 seed-time consumer): student and student2
        // are actively enrolled in the first section, so the live
        // class-scoping demo has content. student3 is deliberately left
        // un-enrolled in this section (the ENR-08 denial demo). A
        // withdrawn sample on the second section rounds out the demo
        // graph with more than one enrollment state.
        $section->enrollments()->syncWithoutDetaching([
            $students['student']->id => ['status' => EnrollmentStatus::Enrolled],
            $students['student2']->id => ['status' => EnrollmentStatus::Enrolled],
        ]);
        $secondSection->enrollments()->syncWithoutDetaching([
            $students['student3']->id => ['status' => EnrollmentStatus::Withdrawn],
        ]);

        $exam = $this->seedExam($mathematics, $lecturer);

        // student (Demo Student) stays enrolled but un-attempted so a
        // reviewer can take the exam fresh; student3 holds only a
        // Withdrawn enrollment on the second Mathematics section (never
        // Enrolled), so it still can't see this exam even though
        // visibility is now subject- rather than section-scoped.
        $this->seedDemoAttempt($exam, $students['student2']);

        // Extra Mathematics exams so the demo STUDENT (enrolled in Mathematics)
        // sees all three availability states on their own class page — a
        // coming-soon 'opening' exam and a past-deadline 'closed' one, alongside
        // the always-available Midterm above.
        $this->seedStudentAvailabilityExams($mathematics, $lecturer);

        // Broaden the demo accounts across several more subjects so the lecturer
        // manages a real portfolio and the student is enrolled in more than one
        // class.
        $this->seedDemoPortfolio($lecturer, $students);

        // A deliberately large, unpublished exam so the lecturer's editor can be
        // exercised with many questions at once.
        $this->seedLargeExam($mathematics, $lecturer);

        // SEED-02 — past-semester graded/filled data, and the remaining
        // corners of the status matrix (in_progress attempt, rejected
        // enrollment, a not-yet-'opening' exam) that nothing above
        // exercises yet. Dated through App\Support\Semester — never a
        // relative calendar-offset fudge.
        $this->seedPastSemester($bulkLecturers, $bulkStudents);

        $firstBulk = $bulkExams->first();
        $this->seedInProgressAttempt($firstBulk['exam'], $firstBulk['students']->last());

        $this->seedFutureOpeningExam($science, $lecturer);
    }

    /**
     * Demo Lecturer — authors/assigns/grades everything below.
     */
    private function seedLecturer(): User
    {
        return User::firstOrCreate(
            ['email' => 'lecturer@example.com'],
            [
                'name' => 'Demo Lecturer',
                'password' => Hash::make('password'),
                'email_verified_at' => now(), // required — Breeze's `verified` middleware otherwise blocks this account
                'role' => Role::Lecturer,
            ]
        );
    }

    /**
     * The three fixed-credential demo students (D-03). email_verified_at
     * MUST be set on every one of these, not just the first pair
     * (06-RESEARCH.md Pitfall 5).
     *
     * @return array{student: User, student2: User, student3: User}
     */
    private function seedStudents(): array
    {
        $student = User::firstOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Demo Student',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => Role::Student,
            ]
        );

        $student2 = User::firstOrCreate(
            ['email' => 'student2@example.com'],
            [
                'name' => 'Demo Student Two',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => Role::Student,
            ]
        );

        $student3 = User::firstOrCreate(
            ['email' => 'student3@example.com'],
            [
                'name' => 'Demo Student Three',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => Role::Student,
            ]
        );

        return compact('student', 'student2', 'student3');
    }

    /**
     * @return array{0: Subject, 1: Subject}
     */
    private function seedSubjects(): array
    {
        $mathematics = Subject::firstOrCreate(
            ['code' => 'MATH101'],
            ['name' => 'Mathematics']
        );

        $science = Subject::firstOrCreate(
            ['code' => 'SCI101'],
            ['name' => 'Science']
        );

        return [$mathematics, $science];
    }

    /**
     * Two Sections under Mathematics (SEC-01), named via the computed
     * year-semester-sequence accessor, both with an open enrollment
     * window so the demo enrollments below are immediately valid.
     *
     * @return array{0: Section, 1: Section}
     */
    private function seedSections(Subject $mathematics): array
    {
        $section = Section::firstOrCreate(
            ['subject_id' => $mathematics->id, 'year' => 2026, 'semester' => 2, 'sequence' => 1],
            [
                'capacity' => 30,
                'opens_at' => now()->subDay(),
                'closes_at' => now()->addDays(14),
            ]
        );

        $secondSection = Section::firstOrCreate(
            ['subject_id' => $mathematics->id, 'year' => 2026, 'semester' => 2, 'sequence' => 2],
            [
                'capacity' => 30,
                'opens_at' => now()->subDay(),
                'closes_at' => now()->addDays(14),
            ]
        );

        return [$section, $secondSection];
    }

    /**
     * The published, time-limited demo exam (D-01) — one MCQ (>=2 options,
     * exactly one correct) + one open-text question. Visibility is
     * subject-derived (Phase 10, D-1/CLS-05): every student enrolled in
     * ANY Mathematics section automatically sees this exam — there is no
     * per-exam assignment step, so both Mathematics sections seeded above
     * see it; only enrollment status gates access (ENR-08 — student3's
     * withdrawn enrollment on the second section is what demonstrates
     * denial, not a section-scoping decision).
     *
     * Questions/options have no unique index (Pitfall 1), so child
     * creation is guarded by $exam->wasRecentlyCreated (Pattern 3) rather
     * than a brittle firstOrCreate on body text.
     *
     * available_from/available_until (AVL-01, closes the DEL-03 seeder
     * gap — the columns did not exist until 08-01) are set to a
     * generously open window (a day in the past through a month out) so
     * `migrate:fresh --seed` always demonstrates a currently-available
     * exam a reviewer can start immediately, never a window that has
     * already closed.
     */
    private function seedExam(Subject $mathematics, User $lecturer): Exam
    {
        $exam = Exam::firstOrCreate(
            ['subject_id' => $mathematics->id, 'title' => 'Mathematics Midterm'],
            [
                'created_by' => $lecturer->id,
                'description' => 'Demo exam covering basic arithmetic.',
                'duration_minutes' => 30,
                'is_published' => true,
                'available_from' => now()->subDay(),
                'available_until' => now()->addMonth(),
            ]
        );

        if ($exam->wasRecentlyCreated) {
            $mcq = Question::create([
                'exam_id' => $exam->id,
                'type' => QuestionType::Mcq,
                'body' => 'What is 2 + 2?',
                'points' => 1,
                'position' => 0,
            ]);

            $mcq->options()->createMany([
                ['body' => '4', 'is_correct' => true, 'position' => 0],
                ['body' => '3', 'is_correct' => false, 'position' => 1],
                ['body' => '5', 'is_correct' => false, 'position' => 2],
                ['body' => '22', 'is_correct' => false, 'position' => 3],
            ]);

            Question::create([
                'exam_id' => $exam->id,
                'type' => QuestionType::Open,
                'body' => 'Explain how you arrived at your answer.',
                'points' => 5,
                'position' => 1,
            ]);
        }

        return $exam;
    }

    /**
     * One pre-submitted, auto-graded demo attempt for student2 (D-02):
     * the MCQ answer is graded correct, the open-text answer stays
     * ungraded, and the attempt correctly stays 'submitted' (not
     * 'graded') — this gives the lecturer's grading queue live content
     * on first load. Built directly via Eloquent, never a simulated
     * HTTP submit (06-RESEARCH.md Pattern 4 / Anti-Patterns).
     */
    private function seedDemoAttempt(Exam $exam, User $student2): void
    {
        $exam->loadMissing('questions.options');

        $mcqQuestion = $exam->questions->firstWhere('type', QuestionType::Mcq);
        $openQuestion = $exam->questions->firstWhere('type', QuestionType::Open);

        $attempt = Attempt::firstOrCreate(
            ['exam_id' => $exam->id, 'user_id' => $student2->id],
            [
                'started_at' => now()->subMinutes(20),
                'submitted_at' => now(),
                // MUST be 'submitted' before grading — syncStatus() no-ops
                // on 'in_progress' (Pitfall 2).
                'status' => 'submitted',
                'score' => null,
            ]
        );

        Answer::firstOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $mcqQuestion->id],
            [
                'selected_option_id' => $mcqQuestion->options->firstWhere('is_correct', true)?->id,
            ]
        );

        Answer::firstOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $openQuestion->id],
            [
                // Left ungraded (score null) — this is what keeps the
                // attempt at 'submitted' (Pitfall 4), giving the lecturer
                // grading queue live content and GRD-03's gating something
                // to demonstrate.
                'answer_text' => 'Because 2 apples plus 2 apples makes 4 apples.',
                'score' => null,
            ]
        );

        // Invoke the real grading service exactly as production finalize
        // does, but without HTTP (Pattern 4 / Anti-Patterns).
        app(AttemptGrader::class)->gradeAutoGradable($attempt);
        app(AttemptGrader::class)->syncStatus($attempt);
    }

    /**
     * SEED-01: many uniquely-named, TITLED lecturers. Count-guarded
     * (read-existing-then-create-the-shortfall) rather than an
     * unconditional factory()->count(n)->create() — a second seed run
     * reads the same count it just wrote and creates nothing, which is
     * what keeps DatabaseSeederTest::test_seeder_is_idempotent_on_repeat_runs
     * (strict row-count equality) green. Target of 12 total (1 named demo
     * lecturer + 11 bulk-titled) comfortably clears both the >=8-total and
     * >=6-titled documented thresholds.
     */
    private function seedBulkLecturers(): void
    {
        $target = 12;
        $existing = User::where('role', Role::Lecturer)->count();

        if ($existing < $target) {
            User::factory()
                ->count($target - $existing)
                ->lecturer()
                ->titled()
                ->create();
        }
    }

    /**
     * SEED-01: many uniquely-named, UNTITLED students — see seedBulkLecturers()
     * for the count-guard rationale. Target of 30 total (3 named demo
     * students + 27 bulk) clears the documented >=25 threshold.
     */
    private function seedBulkStudents(): void
    {
        $target = 30;
        $existing = User::where('role', Role::Student)->count();

        if ($existing < $target) {
            User::factory()
                ->count($target - $existing)
                ->student()
                ->create();
        }
    }

    /**
     * SEED-03: 5 further subjects beyond the original Mathematics/Science
     * pair, each firstOrCreate'd on its natural `code` key, assigned to a
     * bulk lecturer via subject_user, given one open current-semester
     * Section filled with a spread of bulk students, and one published
     * available exam. Every write here is firstOrCreate/syncWithoutDetaching
     * -guarded, so a re-seed neither duplicates rows nor errors.
     *
     * @return Collection<int, array{subject: Subject, section: Section, exam: Exam, lecturer: User, students: Collection<int, User>}>
     */
    private function seedBulkSubjects(Collection $bulkLecturers, Collection $bulkStudents): Collection
    {
        $subjectsData = [
            ['code' => 'ENG101', 'name' => 'English Literature'],
            ['code' => 'HIST101', 'name' => 'History'],
            ['code' => 'PHYS101', 'name' => 'Physics'],
            ['code' => 'CHEM101', 'name' => 'Chemistry'],
            ['code' => 'CS101', 'name' => 'Computer Science'],
        ];

        $result = collect();

        foreach ($subjectsData as $index => $data) {
            $subject = Subject::firstOrCreate(
                ['code' => $data['code']],
                ['name' => $data['name']]
            );

            $lecturer = $bulkLecturers[$index % $bulkLecturers->count()];
            $subject->lecturers()->syncWithoutDetaching([$lecturer->id]);

            $section = Section::firstOrCreate(
                ['subject_id' => $subject->id, 'year' => 2026, 'semester' => 2, 'sequence' => 1],
                [
                    'capacity' => 30,
                    'opens_at' => now()->subDay(),
                    'closes_at' => now()->addDays(14),
                ]
            );

            // Enroll a spread of bulk students so the new class is
            // visibly filled, not just structurally present.
            $sectionStudents = $bulkStudents->slice($index * 4, 4)->values();
            $section->enrollments()->syncWithoutDetaching(
                $sectionStudents->mapWithKeys(fn (User $s) => [$s->id => ['status' => EnrollmentStatus::Enrolled]])->all()
            );

            $exam = Exam::firstOrCreate(
                ['subject_id' => $subject->id, 'title' => "{$data['name']} Fundamentals"],
                [
                    'created_by' => $lecturer->id,
                    'description' => "Demo exam covering {$data['name']}.",
                    'duration_minutes' => 30,
                    'is_published' => true,
                    'available_from' => now()->subDay(),
                    'available_until' => now()->addMonth(),
                ]
            );

            if ($exam->wasRecentlyCreated) {
                $this->seedGenericQuestions($exam);
            }

            $result->push([
                'subject' => $subject,
                'section' => $section,
                'exam' => $exam,
                'lecturer' => $lecturer,
                'students' => $sectionStudents,
            ]);
        }

        return $result;
    }

    /**
     * One MCQ (3 options, exactly one correct) + one open-text question —
     * the same shape as seedExam()'s original pair, factored out so the
     * bulk/past/future exams below don't each hand-roll question creation.
     * Guarded by the caller's $exam->wasRecentlyCreated check (Questions
     * have no unique index — Pitfall 1 — so this must never run twice
     * against the same exam).
     */
    private function seedGenericQuestions(Exam $exam): void
    {
        $mcq = Question::create([
            'exam_id' => $exam->id,
            'type' => QuestionType::Mcq,
            'body' => 'What is 2 + 2?',
            'points' => 1,
            'position' => 0,
        ]);

        $mcq->options()->createMany([
            ['body' => '4', 'is_correct' => true, 'position' => 0],
            ['body' => '3', 'is_correct' => false, 'position' => 1],
            ['body' => '5', 'is_correct' => false, 'position' => 2],
        ]);

        Question::create([
            'exam_id' => $exam->id,
            'type' => QuestionType::Open,
            'body' => 'Explain your reasoning.',
            'points' => 5,
            'position' => 1,
        ]);
    }

    /**
     * A deliberately large, UNPUBLISHED Mathematics exam (15 questions, mixed
     * MCQ/open) so the lecturer's editor can be stress-tested with many
     * questions. Unpublished + no attempts, so editing is unconstrained (no
     * save-warning / attempt-voiding gets in the way). Guarded by
     * wasRecentlyCreated (Questions have no unique index — Pitfall 1).
     */
    private function seedLargeExam(Subject $mathematics, User $lecturer): void
    {
        $exam = Exam::firstOrCreate(
            ['subject_id' => $mathematics->id, 'title' => 'Mathematics Practice Set (Large)'],
            [
                'created_by' => $lecturer->id,
                'description' => 'A larger exam to exercise the editor with many questions.',
                'duration_minutes' => 60,
                'is_published' => false,
                'available_from' => now()->subDay(),
                'available_until' => now()->addMonth(),
            ]
        );

        if (! $exam->wasRecentlyCreated) {
            return;
        }

        for ($i = 0; $i < 15; $i++) {
            // Every third question is open-text; the rest are MCQ.
            if ($i % 3 === 2) {
                Question::create([
                    'exam_id' => $exam->id,
                    'type' => QuestionType::Open,
                    'body' => 'Question '.($i + 1).' (open): explain your working in full.',
                    'points' => 5,
                    'position' => $i,
                ]);

                continue;
            }

            $n = $i + 1;
            $correct = $n * 2;

            $question = Question::create([
                'exam_id' => $exam->id,
                'type' => QuestionType::Mcq,
                'body' => "Question {$n} (MCQ): what is {$n} + {$n}?",
                'points' => 1,
                'position' => $i,
            ]);

            $question->options()->createMany([
                ['body' => (string) $correct, 'is_correct' => true, 'position' => 0],
                ['body' => (string) ($correct + 1), 'is_correct' => false, 'position' => 1],
                ['body' => (string) ($correct - 1), 'is_correct' => false, 'position' => 2],
                ['body' => (string) ($correct + 3), 'is_correct' => false, 'position' => 3],
            ]);
        }
    }

    /**
     * SEED-02: a past-semester Subject/Section/Exam holding graded, filled
     * data, plus the enrollment-status corners (withdrawn, rejected) not
     * already covered by the original demo graph's section2 withdrawal.
     * ALL past dating is derived from a Semester instance's startsAt()/
     * endsAt() — never a relative calendar-offset fudge (SEED-02's
     * load-bearing rule).
     */
    private function seedPastSemester(Collection $bulkLecturers, Collection $bulkStudents): void
    {
        $current = Semester::current();
        $past = new Semester($current->year - 1, $current->number);

        $pastLecturer = $bulkLecturers->first();

        $pastSubject = Subject::firstOrCreate(
            ['code' => 'HIST201'],
            ['name' => 'Advanced History']
        );
        $pastSubject->lecturers()->syncWithoutDetaching([$pastLecturer->id]);

        $capacity = 5;

        $pastSection = Section::firstOrCreate(
            ['subject_id' => $pastSubject->id, 'year' => $past->year, 'semester' => $past->number, 'sequence' => 1],
            [
                'capacity' => $capacity,
                'opens_at' => $past->startsAt(),
                'closes_at' => $past->endsAt(),
            ]
        );

        // Reserve a slice of the bulk student pool exclusively for the
        // past semester so it never collides with seedBulkSubjects()'s
        // slices (which consume indices 0..19 across its 5 sections).
        $pastPool = $bulkStudents->slice(20)->values();

        $enrolledStudents = $pastPool->slice(0, $capacity)->values();
        $withdrawnStudent = $pastPool->get($capacity);
        $rejectedStudent = $pastPool->get($capacity + 1);

        $pastSection->enrollments()->syncWithoutDetaching(
            $enrolledStudents->mapWithKeys(fn (User $s) => [$s->id => ['status' => EnrollmentStatus::Enrolled]])->all()
        );

        if ($withdrawnStudent) {
            $pastSection->enrollments()->syncWithoutDetaching([
                $withdrawnStudent->id => ['status' => EnrollmentStatus::Withdrawn],
            ]);
        }

        if ($rejectedStudent) {
            $pastSection->enrollments()->syncWithoutDetaching([
                $rejectedStudent->id => [
                    'status' => EnrollmentStatus::Rejected,
                    'rejection_reason' => RejectionReason::NotEligibleForSubject,
                ],
            ]);
        }

        // available_until falls inside the past window, so
        // Exam::availabilityState() reads 'closed' for every reviewer
        // looking at this exam today (SEED-02's "past 'closed' exam").
        $pastExam = Exam::firstOrCreate(
            ['subject_id' => $pastSubject->id, 'title' => 'Advanced History Final'],
            [
                'created_by' => $pastLecturer->id,
                'description' => 'Past-semester demo exam — closed and fully graded.',
                'duration_minutes' => 45,
                'is_published' => true,
                'available_from' => $past->startsAt(),
                'available_until' => $past->endsAt(),
            ]
        );

        if ($pastExam->wasRecentlyCreated) {
            $this->seedGenericQuestions($pastExam);
        }

        $this->seedPastGradedAttempts($pastExam, $enrolledStudents, $past);
    }

    /**
     * Builds a fully-graded Attempt per enrolled past-semester student —
     * directly via Eloquent (never a simulated HTTP submit, per
     * seedDemoAttempt()'s established pattern), invoking AttemptGrader
     * for both the MCQ auto-grade AND the open-text score, so every one
     * of these reaches 'graded' (the status the original demo graph never
     * exercised on its own).
     */
    private function seedPastGradedAttempts(Exam $pastExam, Collection $students, Semester $past): void
    {
        $pastExam->loadMissing('questions.options');

        $mcqQuestion = $pastExam->questions->firstWhere('type', QuestionType::Mcq);
        $openQuestion = $pastExam->questions->firstWhere('type', QuestionType::Open);

        $attemptedAt = $past->endsAt()->copy()->subDays(5);

        foreach ($students as $student) {
            $attempt = Attempt::firstOrCreate(
                ['exam_id' => $pastExam->id, 'user_id' => $student->id],
                [
                    'started_at' => $attemptedAt->copy()->subMinutes(40),
                    'submitted_at' => $attemptedAt,
                    'status' => 'submitted',
                    'score' => null,
                ]
            );

            Answer::firstOrCreate(
                ['attempt_id' => $attempt->id, 'question_id' => $mcqQuestion->id],
                [
                    'selected_option_id' => $mcqQuestion->options->firstWhere('is_correct', true)?->id,
                ]
            );

            $openAnswer = Answer::firstOrCreate(
                ['attempt_id' => $attempt->id, 'question_id' => $openQuestion->id],
                [
                    'answer_text' => 'This is the past-semester response.',
                    'score' => null,
                ]
            );

            app(AttemptGrader::class)->gradeAutoGradable($attempt);

            // Manually grade the open-text answer, mirroring
            // AnswerGradeController@update's single-key write, so
            // syncStatus() below can complete the submitted -> graded
            // transition (this is the seed's only 'graded' attempt).
            if ($openAnswer->score === null) {
                $openAnswer->update(['score' => $openQuestion->points]);
            }

            app(AttemptGrader::class)->syncStatus($attempt);
        }
    }

    /**
     * SEED-02's 'in_progress' corner: a current, un-submitted attempt on a
     * bulk-subject exam. Built directly via Eloquent with no submitted_at,
     * so Attempt::status stays 'in_progress' — nothing else in the seeder
     * produces this status.
     */
    private function seedInProgressAttempt(Exam $exam, User $student): void
    {
        Attempt::firstOrCreate(
            ['exam_id' => $exam->id, 'user_id' => $student->id],
            [
                'started_at' => now()->subMinutes(5),
                'submitted_at' => null,
                'status' => 'in_progress',
                'score' => null,
            ]
        );
    }

    /**
     * Two extra Mathematics exams for the demo student's class page: one
     * 'opening' (coming soon — available_from a few days out) and one 'closed'
     * (deadline already passed). Both are published and belong to Mathematics,
     * so the demo student — enrolled in a Mathematics section — sees them via
     * subject-derived visibility (ENR-08: listed with a status label, never
     * hidden). Each carries an explicit available_from/until so the class
     * page's new duration/deadline detail line has real data to render.
     */
    private function seedStudentAvailabilityExams(Subject $mathematics, User $lecturer): void
    {
        $comingSoon = Exam::firstOrCreate(
            ['subject_id' => $mathematics->id, 'title' => 'Mathematics Quiz 2 (Coming Soon)'],
            [
                'created_by' => $lecturer->id,
                'description' => 'Not open yet — opens in a few days. Demonstrates the upcoming exam state.',
                'duration_minutes' => 20,
                'is_published' => true,
                'available_from' => now()->addDays(3),
                'available_until' => now()->addDays(10),
            ]
        );

        if ($comingSoon->wasRecentlyCreated) {
            $this->seedGenericQuestions($comingSoon);
        }

        $expired = Exam::firstOrCreate(
            ['subject_id' => $mathematics->id, 'title' => 'Mathematics Diagnostic (Closed)'],
            [
                'created_by' => $lecturer->id,
                'description' => 'Its deadline has passed. Demonstrates the closed/expired exam state.',
                'duration_minutes' => 25,
                'is_published' => true,
                'available_from' => now()->subDays(14),
                'available_until' => now()->subDays(2),
            ]
        );

        if ($expired->wasRecentlyCreated) {
            $this->seedGenericQuestions($expired);
        }
    }

    /**
     * Broadens the DEMO accounts' footprint across several more subjects so
     * logging in as lecturer@example.com shows a real portfolio of managed
     * subjects (each with a class + published exam) and student@example.com is
     * enrolled across several classes — not just Mathematics. Science (already
     * created, previously lecturer-less and section-less) is wired up here too.
     * Every write is firstOrCreate/syncWithoutDetaching, so a re-seed neither
     * duplicates rows nor errors.
     *
     * @param array{student: User, student2: User, student3: User} $students
     */
    private function seedDemoPortfolio(User $lecturer, array $students): void
    {
        $subjects = [
            ['code' => 'SCI101', 'name' => 'Science'],
            ['code' => 'BIO101', 'name' => 'Biology'],
            ['code' => 'GEO101', 'name' => 'Geography'],
            ['code' => 'ART101', 'name' => 'Art & Design'],
            ['code' => 'MUS101', 'name' => 'Music Theory'],
        ];

        foreach ($subjects as $data) {
            $subject = Subject::firstOrCreate(
                ['code' => $data['code']],
                ['name' => $data['name']]
            );
            $subject->lecturers()->syncWithoutDetaching([$lecturer->id]);

            $section = Section::firstOrCreate(
                ['subject_id' => $subject->id, 'year' => 2026, 'semester' => 2, 'sequence' => 1],
                [
                    'capacity' => 30,
                    'opens_at' => now()->subDay(),
                    'closes_at' => now()->addDays(14),
                ]
            );

            $section->enrollments()->syncWithoutDetaching([
                $students['student']->id => ['status' => EnrollmentStatus::Enrolled],
                $students['student2']->id => ['status' => EnrollmentStatus::Enrolled],
            ]);

            $exam = Exam::firstOrCreate(
                ['subject_id' => $subject->id, 'title' => "{$data['name']} Assessment"],
                [
                    'created_by' => $lecturer->id,
                    'description' => "Demo exam for {$data['name']}.",
                    'duration_minutes' => 30,
                    'is_published' => true,
                    'available_from' => now()->subDay(),
                    'available_until' => now()->addDays(20),
                ]
            );

            if ($exam->wasRecentlyCreated) {
                $this->seedGenericQuestions($exam);
            }
        }
    }

    /**
     * SEED-02's 'opening' corner: a published exam whose available_from is
     * still in the future, so Exam::availabilityState() reads 'opening'.
     * available_from is derived from the current Semester's endsAt() —
     * guaranteed to be later than now() while the semester is current —
     * rather than an arbitrary now()->addWeek() fudge.
     */
    private function seedFutureOpeningExam(Subject $subject, User $lecturer): void
    {
        $exam = Exam::firstOrCreate(
            ['subject_id' => $subject->id, 'title' => 'Science Orientation Quiz'],
            [
                'created_by' => $lecturer->id,
                'description' => 'Not yet open — demonstrates the opening exam state.',
                'duration_minutes' => 20,
                'is_published' => true,
                'available_from' => Semester::current()->endsAt(),
                'available_until' => Semester::current()->endsAt()->copy()->addMonth(),
            ]
        );

        if ($exam->wasRecentlyCreated) {
            $this->seedGenericQuestions($exam);
        }
    }
}

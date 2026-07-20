<?php

use App\Http\Controllers\Lecturer\AnswerGradeController;
use App\Http\Controllers\Lecturer\ExamController;
use App\Http\Controllers\Lecturer\ExamQuestionController;
use App\Http\Controllers\Lecturer\HomeController;
use App\Http\Controllers\Lecturer\QuestionReorderController;
use App\Http\Controllers\Lecturer\RejectEnrollmentController;
use App\Http\Controllers\Lecturer\ResultController;
use App\Http\Controllers\Lecturer\SectionController;
use App\Http\Controllers\Lecturer\SubjectController;
use App\Http\Controllers\Lecturer\SubjectLecturerController;
use App\Http\Controllers\Lecturer\SubjectManageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:lecturer'])
    ->prefix('lecturer')
    ->name('lecturer.')
    ->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');

        Route::get('help', fn () => view('lecturer.help'))->name('help.show');

        Route::resource('subjects', SubjectController::class)->except(['show']);
        Route::get('sections', [SectionController::class, 'index'])->name('sections.index');
        // ENR-07: top-level siblings of sections.index per the locked
        // 08-02 route-name contract — placed AFTER the literal 'sections'
        // route so the literal wins.
        Route::get('sections/{section}', [SectionController::class, 'show'])->name('sections.show');
        Route::patch('sections/{section}/enrollments/{student}/reject', [RejectEnrollmentController::class, 'reject'])
            ->name('sections.enrollments.reject');
        Route::prefix('subjects/{subject}')
            ->name('subjects.')
            ->group(function () {
                // CLS-01: the per-subject two-tab hub — final name lecturer.subjects.manage.
                Route::get('manage', [SubjectManageController::class, 'show'])->name('manage');
                Route::get('sections/create', [SectionController::class, 'create'])->name('sections.create');
                Route::post('sections', [SectionController::class, 'store'])->name('sections.store');
                Route::get('sections/{section}/edit', [SectionController::class, 'edit'])->name('sections.edit');
                Route::put('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
                Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');

                Route::post('lecturers', [SubjectLecturerController::class, 'store'])->name('lecturers.store');
                Route::delete('lecturers/{lecturer}', [SubjectLecturerController::class, 'destroy'])->name('lecturers.destroy');
            });
        Route::resource('exams', ExamController::class);
        Route::patch('exams/{exam}/publish', [ExamController::class, 'publish'])->name('exams.publish');
        Route::patch('exams/{exam}/unpublish', [ExamController::class, 'unpublish'])->name('exams.unpublish');
        // Fixed route-name contract locked by 10-02-SUMMARY.md's ResetSubmissionsTest
        // (executable RED spec) — DELETE states the semantics honestly: D-2's
        // AttemptVoider::void() is a permanent hard delete, not a status toggle.
        Route::delete('exams/{exam}/submissions', [ExamController::class, 'resetSubmissions'])
            ->name('exams.submissions.reset');

        Route::post('exams/{exam}/questions', [ExamQuestionController::class, 'store'])
            ->name('exams.questions.store');
        // One-click add: creates a blank question at the end (no form payload),
        // then redirects to it in the editor where it's filled in inline.
        Route::post('exams/{exam}/questions/quick', [ExamQuestionController::class, 'quickStore'])
            ->name('exams.questions.quick');
        Route::get('exams/{exam}/questions/{question}/edit', [ExamQuestionController::class, 'edit'])
            ->name('exams.questions.edit');
        Route::put('exams/{exam}/questions/{question}', [ExamQuestionController::class, 'update'])
            ->name('exams.questions.update');
        Route::patch('exams/{exam}/questions/{question}', [ExamQuestionController::class, 'update']);
        Route::delete('exams/{exam}/questions/{question}', [ExamQuestionController::class, 'destroy'])
            ->name('exams.questions.destroy');

        // 12-05 (EDT-03/EDT-05): non-destructive position-swap reordering —
        // see QuestionReorderController's class doc comment for why these
        // never run AttemptVoider.
        Route::patch('exams/{exam}/questions/{question}/move', [QuestionReorderController::class, 'moveQuestion'])
            ->name('exams.questions.move');
        Route::patch('exams/{exam}/questions/{question}/options/{option}/move', [QuestionReorderController::class, 'moveOption'])
            ->name('exams.questions.options.move');
        Route::patch('exams/{exam}/questions/{question}/options/shuffle', [QuestionReorderController::class, 'shuffleOptions'])
            ->name('exams.questions.options.shuffle');

        // Fixed route-name contract locked by 05-01-SUMMARY.md (executable
        // RED test contract) — do not rename without updating the pinned
        // GradeAnswerTest/ResultTest route() calls.
        Route::get('exams/{exam}/results', [ResultController::class, 'index'])
            ->name('results.index');
        Route::get('exams/{exam}/results/{attempt}', [ResultController::class, 'show'])
            ->name('results.show');
        Route::patch('attempts/{attempt}/answers/{answer}/grade', [AnswerGradeController::class, 'update'])
            ->name('attempts.answers.grade');
    });

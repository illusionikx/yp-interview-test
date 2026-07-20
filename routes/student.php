<?php

use App\Http\Controllers\Student\AttemptController;
use App\Http\Controllers\Student\ClassPageController;
use App\Http\Controllers\Student\EnrollmentController;
use App\Http\Controllers\Student\ExamController;
use App\Http\Controllers\Student\HomeController;
use App\Http\Controllers\Student\ResultController;
use App\Http\Controllers\Student\SubjectBrowseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:student'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');

        Route::get('help', fn () => view('student.help'))->name('help.show');

        Route::get('exams', [ExamController::class, 'index'])->name('exams.index');
        Route::get('exams/{exam}', [ExamController::class, 'show'])->name('exams.show');

        Route::get('subjects', [SubjectBrowseController::class, 'index'])->name('subjects.index');
        Route::get('subjects/{subject}', [SubjectBrowseController::class, 'show'])->name('subjects.show');
        Route::get('subjects/{subject}/class', [ClassPageController::class, 'show'])->name('subjects.class');

        Route::post('sections/{section}/enroll', [EnrollmentController::class, 'store'])->name('sections.enroll');
        Route::delete('sections/{section}/enroll', [EnrollmentController::class, 'destroy'])->name('sections.withdraw');

        Route::post('exams/{exam}/attempts', [AttemptController::class, 'store'])->name('attempts.store');
        Route::get('attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
        Route::post('attempts/{attempt}/answers', [AttemptController::class, 'answer'])->name('attempts.answer');
        Route::post('attempts/{attempt}/submit', [AttemptController::class, 'submit'])->name('attempts.submit');
        Route::get('attempts/{attempt}/submitted', [AttemptController::class, 'submitted'])->name('attempts.submitted');
        Route::get('attempts/{attempt}/result', [ResultController::class, 'show'])->name('attempts.result');
    });

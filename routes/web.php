<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Guests see the branded landing page (NAV-01); authenticated users never see
// it and go straight to their dashboard, which carries its own ['auth',
// 'verified'] gate below — the redirect here is a convenience, not a
// security boundary.
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('landing');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/lecturer.php';
require __DIR__.'/student.php';

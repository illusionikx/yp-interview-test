<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Dispatch the shared Breeze /dashboard route by role (D-08).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return $request->user()->isLecturer()
            ? redirect()->route('lecturer.home')
            : redirect()->route('student.home');
    }
}

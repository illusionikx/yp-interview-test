<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\AssignLecturerRequest;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class SubjectLecturerController extends Controller
{
    /**
     * Assign a lecturer to the subject (SEC-03).
     *
     * syncWithoutDetaching keeps this idempotent — assigning an
     * already-assigned lecturer a second time is a no-op, not an error.
     */
    public function store(AssignLecturerRequest $request, Subject $subject): RedirectResponse
    {
        $subject->lecturers()->syncWithoutDetaching([$request->validated('user_id')]);

        return back()->with('status', 'Lecturer assigned.');
    }

    /**
     * Unassign a lecturer from the subject.
     *
     * No Form Request backs this action (route-model binding only), so
     * the same per-subject ownership check AssignLecturerRequest performs
     * for store() is applied inline here — SEC-03 requires unassignment
     * to be ownership-gated too, not only assignment.
     */
    public function destroy(Subject $subject, User $lecturer): RedirectResponse
    {
        abort_unless($subject->lecturers()->whereKey(auth()->id())->exists(), 403);

        $subject->lecturers()->detach($lecturer->id);

        return back()->with('status', 'Lecturer unassigned.');
    }
}

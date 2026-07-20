<?php

namespace App\Http\Controllers\Lecturer;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\RejectEnrollmentRequest;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class RejectEnrollmentController extends Controller
{
    /**
     * Reject an enrolled student from a section (ENR-07).
     *
     * Ownership is already settled by RejectEnrollmentRequest::authorize()
     * (SEC-03), which runs before this action. Route-model binding only
     * proves {section} and {student} both exist — it does not prove the
     * student is actually enrolled in this section, so the nested-binding
     * integrity is checked explicitly (T-08-05-BIND), mirroring
     * GradeAnswerRequest's Phase 5 precedent: abort 404 rather than
     * silently updating zero rows and flashing a false success.
     *
     * WR-01: the row must also be CURRENTLY Enrolled, not merely present
     * — a stale pivot row for a student who already withdrew or was
     * already rejected must never be silently overwritten by a fresh
     * reject (e.g. flipping a Withdrawn row to Rejected with a reason the
     * student never actually triggered). The roster
     * (SectionController@show) only lists Enrolled students, so the
     * normal UI path never surfaces this, but the endpoint itself must
     * still enforce it server-side.
     *
     * The write is a literal, explicitly-keyed array (T-08-05-MA) —
     * Enrollment extends Pivot ($guarded = []), so the validated reason is
     * never spread wholesale. No observer/event on Enrollment is
     * registered for this transition (CLAUDE.md's explicit-call-not-
     * hidden-hook discipline).
     *
     * WR-03: 'status' is written as the EnrollmentStatus enum instance,
     * matching EnrollmentController@store/destroy's consistent convention
     * — never a raw string literal, even one that happens to match the
     * enum's current backing value today.
     */
    public function reject(RejectEnrollmentRequest $request, Section $section, User $student): RedirectResponse
    {
        $enrolled = $section->enrollments()
            ->wherePivot('status', EnrollmentStatus::Enrolled->value)
            ->whereKey($student->id)
            ->exists();

        abort_unless($enrolled, 404);

        $section->enrollments()->updateExistingPivot($student->id, [
            'status' => EnrollmentStatus::Rejected,
            'rejection_reason' => $request->validated('reason'),
        ]);

        return redirect()
            ->route('lecturer.sections.show', $section)
            ->with('status', "{$student->name} has been rejected from this section.");
    }
}

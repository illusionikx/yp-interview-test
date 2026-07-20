<?php

namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamController extends Controller
{
    /**
     * Display the exams visible to the acting student (ASN-02).
     *
     * The predicate lives entirely in Exam::visibleTo() — never filter
     * an unfiltered collection in PHP after loading.
     */
    public function index(Request $request): View
    {
        $exams = Exam::visibleTo($request->user())
            ->with('subject')
            // The acting student's OWN attempt per exam, so the list can
            // offer a "View result" link once it is submitted. Scoped to
            // this user (never another student's attempt) and eager-loaded
            // so the list stays a bounded 2-query read, not an N+1.
            // The single-attempt DB constraint guarantees at most one row.
            ->with(['attempts' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->orderBy('title')
            ->get();

        // CR-02: a student's own in-progress attempt must stay reachable
        // even after its exam drops out of Exam::visibleTo() (withdrawal,
        // rejection, unpublish, un-assignment) — deliberately an
        // OWNERSHIP-driven query, not visibleTo(), so it can never be
        // hidden by the same conditions that hid the exam itself.
        // Excludes exams already present in $exams above: those stay
        // reachable via the normal "exam page -> Proceed" flow, so this
        // section only needs to surface the otherwise-orphaned ones.
        $resumableAttempts = Attempt::where('user_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->whereNotIn('exam_id', $exams->pluck('id'))
            ->with('exam')
            ->get();

        return view('student.exams.index', compact('exams', 'resumableAttempts'));
    }

    /**
     * Show the read-only exam landing page.
     *
     * Route-model binding above only confirmed the exam ROW exists.
     * $this->authorize() is the actual class-scoping gate (RBAC-05/D-04)
     * — never rely on route-model binding or list-omission alone.
     */
    public function show(Request $request, Exam $exam): View
    {
        /**
         * CR-02: takeable() delegates to Exam::visibleTo(), which is
         * enrollment-status-driven. Skip it once the student already has
         * an attempt on this exam — otherwise a mid-attempt withdrawal or
         * lecturer rejection 403s the student's own landing page the
         * instant it happens, cutting off the normal navigation path back
         * to their in-progress attempt. Starting a NEW attempt still goes
         * through AttemptController@store's takeable() gate; viewing this
         * landing page for an attempt that already exists does not.
         */
        $hasAttempt = Attempt::where('exam_id', $exam->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if (! $hasAttempt) {
            $this->authorize('takeable', $exam);
        }

        $exam->load('subject')->loadCount('questions');

        // AVL-02: the section the acting student is enrolled in for the
        // exam's subject (Phase 10, D-1 — visibility/assignment is
        // subject-derived now, there is no per-exam section assignment to
        // navigate). Resolved here (a bounded query) rather than in the
        // view. Genuinely nullable as of CR-02: when $hasAttempt exempted
        // the takeable() gate above, the student may no longer hold an
        // Enrolled enrollment at all (withdrawn/rejected mid-attempt) —
        // the view already renders this null-safely
        // (`@if ($enrolledSection)`).
        $enrolledSection = Section::where('subject_id', $exam->subject_id)
            ->whereHas('enrollments', fn ($q) => $q
                ->where('user_id', $request->user()->id)
                ->where('status', EnrollmentStatus::Enrolled)
            )
            ->first();

        return view('student.exams.show', compact('exam', 'enrolledSection'));
    }
}

<?php

namespace App\Http\Controllers\Lecturer;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Subject;
use App\Support\Semester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * DASH-01/DASH-03: the lecturer landing page — a gradient welcome banner
     * plus three bounded-aggregate stat cards, followed by the ungrouped
     * assigned-subject CRUD table (SUBJ-01). Every figure below is scoped to
     * $ids (the acting lecturer's assigned subject_user pivot rows) and is a
     * single COUNT/SUM/withCount query — never a PHP loop over a relation.
     */
    public function index(Request $request): View
    {
        $ids = $request->user()->subjects()->pluck('subjects.id');

        // "This and future semesters" MUST compare the composite ordinal
        // (year * 2 + (2 - semester)) — mirroring Semester::ordinal() —
        // never a naive year>=Y AND semester>=S, which mis-orders the
        // Sep->Feb S1 rollover against S2 (see Semester::ordinal() docblock).
        $classesThisAndFuture = Section::whereIn('subject_id', $ids)
            ->whereRaw('(year * 2 + (2 - semester)) >= ?', [Semester::current()->ordinal()])
            ->count();

        $totalSeats = (int) Section::whereIn('subject_id', $ids)->sum('capacity');

        $enrolledStudents = Enrollment::where('status', EnrollmentStatus::Enrolled)
            ->whereHas('section', fn (Builder $q) => $q->whereIn('subject_id', $ids))
            ->count();

        $awaitingGrading = Attempt::where('status', 'submitted')
            ->whereHas('exam', fn (Builder $q) => $q->whereIn('subject_id', $ids))
            ->count();

        $subjects = Subject::whereIn('id', $ids)
            ->withCount(['sections', 'exams'])
            ->orderBy('name')
            ->get();

        return view('lecturer.home', [
            'classesThisAndFuture' => $classesThisAndFuture,
            'enrolledStudents' => $enrolledStudents,
            'totalSeats' => $totalSeats,
            'awaitingGrading' => $awaitingGrading,
            'subjects' => $subjects,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassPageController extends Controller
{
    /**
     * TAK-07/TAK-08: the student class page — subject detail plus the
     * subject's exam list, each exam status-marked and, once attempted,
     * pointing at a real result link (never a bare label).
     *
     * The enrollment check below IS the access gate (T-13-01): a student
     * who does not hold an Enrolled enrollment in any section of this
     * subject must not open this page at all, mirroring
     * Student\ExamController@show's enrolled-section resolution idiom.
     * The exam list is then read exclusively through the single Exam
     * visibility scope below — never re-derive is_published/enrollment
     * conditions here, or the list and the gate can silently diverge.
     */
    public function show(Request $request, Subject $subject): View
    {
        $enrolledSection = Section::where('subject_id', $subject->id)
            ->whereHas('enrollments', fn ($q) => $q
                ->where('user_id', $request->user()->id)
                ->where('status', EnrollmentStatus::Enrolled)
            )
            ->first();

        abort_unless($enrolledSection !== null, 403);

        // ENR-08: listed with a status label, never hidden — no
        // availability filter here. The acting student's OWN attempt is
        // eager-loaded (mirrors ExamController@index) so the view can
        // offer a result link/disabled Start without an N+1; the
        // single-attempt DB constraint guarantees at most one row.
        $exams = Exam::visibleTo($request->user())
            ->where('subject_id', $subject->id)
            ->with(['attempts' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->orderBy('title')
            ->get();

        // 13-REVIEW WR-01: an exam this student already ATTEMPTED must keep its
        // result link on the class page even after it drops out of visibleTo()
        // (e.g. the lecturer unpublishes it — CLS-06 allows that after attempts).
        // Ownership-driven, NOT visibleTo(), so it can't be hidden by the same
        // condition that hid the exam. Excludes exams already listed above.
        // This is TAK-07's whole point — a taken/graded result must stay
        // reachable from the page home.blade.php now treats as primary navigation.
        $attemptedButHidden = Exam::where('subject_id', $subject->id)
            ->whereNotIn('id', $exams->pluck('id'))
            ->whereHas('attempts', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['attempts' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->orderBy('title')
            ->get();

        $exams = $exams->concat($attemptedButHidden);

        $subject->load('lecturers');

        return view('student.subjects.class', compact('subject', 'enrolledSection', 'exams'));
    }
}

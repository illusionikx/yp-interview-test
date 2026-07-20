<?php

namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Section;
use App\Support\Semester;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * DASH-04/SUBJ-03/SUBJ-04/SUBJ-05: the student landing page — a gradient
     * welcome banner plus two bounded-aggregate stat cards, followed by a
     * NEW enrolled-subjects-by-semester list (distinct from
     * SubjectBrowseController's enrollment catalog, which stays 11-04's
     * concern). Every figure below is scoped to the acting student ($student)
     * and is a single COUNT query or an eager-loaded, already-materialized
     * collection transform — never a PHP loop issuing per-row queries.
     */
    public function index(Request $request): View
    {
        $student = $request->user();

        // "This semester" MUST compare the composite ordinal
        // (year * 2 + (2 - semester)) — mirroring Semester::ordinal() —
        // never a naive year=Y AND semester=S, which mis-orders the
        // Sep->Feb S1 rollover against S2 (see Semester::ordinal() docblock).
        $subjectsThisSemester = Section::whereRaw('(year * 2 + (2 - semester)) = ?', [Semester::current()->ordinal()])
            ->whereHas('enrollments', fn ($q) => $q
                ->where('user_id', $student->id)
                ->where('status', EnrollmentStatus::Enrolled)
            )
            ->distinct()
            ->count('subject_id');

        $examsAvailable = Exam::visibleTo($student)
            ->whereDoesntHave('attempts', fn ($q) => $q->where('user_id', $student->id))
            ->availableNow()
            ->count();

        // The NEW enrolled-subjects-by-semester query (SUBJ-03) — one query,
        // eager-loading section.subject.lecturers, then grouped in memory
        // over the already-loaded collection (not an N+1).
        $enrollments = Enrollment::where('user_id', $student->id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->with(['section.subject.lecturers'])
            ->get();

        $groups = [];

        foreach ($enrollments as $enrollment) {
            $section = $enrollment->section;
            $subject = $section->subject;
            $semester = new Semester($section->year, $section->semester);
            $ordinal = $semester->ordinal();

            $groups[$ordinal] ??= [
                'semester' => $semester,
                'isPast' => $semester->isPast(),
                'subjects' => [],
            ];

            $groups[$ordinal]['subjects'][$subject->id] ??= [
                'subject' => $subject,
                'lecturerNames' => $subject->lecturers->pluck('name')->join(', ') ?: __('Unassigned'),
            ];
        }

        krsort($groups);

        $currentOrFutureGroups = array_filter($groups, fn ($group) => ! $group['isPast']);
        $pastGroups = array_filter($groups, fn ($group) => $group['isPast']);

        return view('student.home', [
            'subjectsThisSemester' => $subjectsThisSemester,
            'examsAvailable' => $examsAvailable,
            'currentOrFutureGroups' => $currentOrFutureGroups,
            'pastGroups' => $pastGroups,
        ]);
    }
}

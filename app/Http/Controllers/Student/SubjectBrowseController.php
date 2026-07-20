<?php

namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SubjectBrowseController extends Controller
{
    /**
     * ENR-09: single-page "Class enrollment" flow — step 1 always lists
     * every subject reachable for enrollment (those with at least one
     * section); when a valid `subject` query param is present, step 2/3
     * (that subject's classes + enroll action) render inline on the same
     * page via the same bounded query show() uses, so there is no N+1 and
     * no divergent second implementation. No authorize() call — this is
     * read-only browse and the role:student route-group middleware is the
     * sole gate, the same tier Student\ExamController@index occupies.
     */
    public function index(Request $request): View
    {
        $subjects = Subject::has('sections')->orderBy('name')->get();

        $selectedSubject = null;
        $sections = collect();
        $ownEnrollments = collect();
        $activeElsewhere = collect();

        $subjectId = $request->query('subject');

        if ($subjectId) {
            $selectedSubject = $subjects->firstWhere('id', (int) $subjectId);

            if ($selectedSubject) {
                [$sections, $ownEnrollments, $activeElsewhere] = $this->loadSections($selectedSubject);
            }
        }

        return view('student.subjects.index', [
            'subjects' => $subjects,
            'selectedSubject' => $selectedSubject,
            'sections' => $sections,
            'ownEnrollments' => $ownEnrollments,
            'activeElsewhere' => $activeElsewhere,
        ]);
    }

    /**
     * Every section of this subject with live capacity, window status, and
     * the acting student's own enrollment state — all resolved here in
     * bounded queries so the Blade template never runs a per-row query.
     * Only the student's OWN enrollment data reaches the view; the page
     * needs aggregate counts, never other students' identities.
     */
    public function show(Subject $subject): View
    {
        [$sections, $ownEnrollments, $activeElsewhere] = $this->loadSections($subject);

        return view('student.subjects.show', compact('subject', 'sections', 'ownEnrollments', 'activeElsewhere'));
    }

    /**
     * Shared section-loading helper behind both index() (ENR-09 single-page
     * flow) and show() (still reachable directly) — one implementation of
     * the bounded sections/ownEnrollments/activeElsewhere query so the two
     * entry points can never drift apart.
     *
     * @return array{0: Collection<int, Section>, 1: Collection<int, mixed>, 2: Collection<int, bool>}
     */
    private function loadSections(Subject $subject): array
    {
        $studentId = auth()->id();

        $sections = Section::where('subject_id', $subject->id)
            ->orderBy('year')
            ->orderBy('semester')
            ->orderBy('sequence')
            // enrolled_total is a live count computed fresh on every
            // request via withCount() — there is no denormalized counter
            // column on `sections` and there must never be one.
            ->withCount(['enrollments as enrolled_total' => fn ($query) => $query
                // withCount()'s closure receives a plain query builder (the
                // relation-existence subquery), not the BelongsToMany
                // relation instance itself — wherePivot() is undefined on
                // it (and silently mis-resolves via dynamic-where magic).
                // Reference the pivot table's column explicitly instead.
                ->where('enrollments.status', EnrollmentStatus::Enrolled->value)])
            ->with(['enrollments' => fn ($query) => $query
                ->wherePivot('user_id', $studentId)])
            ->get();

        // The acting student's own enrollment (the Enrollment pivot, or
        // null) keyed by section id — resolved here from the filtered
        // eager load above so the view never reaches into
        // $section->enrollments->first()->pivot itself.
        $ownEnrollments = $sections->mapWithKeys(
            fn ($section) => [$section->id => $section->enrollments->first()?->pivot]
        );

        // ENR-04 display consequence: every (year, semester) term where the
        // student already holds an ACTIVE enrollment in one of this
        // subject's sections — used below to suppress the Apply action on
        // every OTHER section of that same term. This is a display
        // consequence of the server-side one-active-enrollment rule
        // (enforced in EnrollmentController@store); it is not itself the
        // enforcement mechanism.
        $activeTerms = $sections
            ->filter(fn ($section) => $ownEnrollments[$section->id]?->status === EnrollmentStatus::Enrolled)
            ->map(fn ($section) => "{$section->year}-{$section->semester}")
            ->all();

        $activeElsewhere = $sections->mapWithKeys(function ($section) use ($ownEnrollments, $activeTerms) {
            $isOwnActiveHere = $ownEnrollments[$section->id]?->status === EnrollmentStatus::Enrolled;

            return [$section->id => ! $isOwnActiveHere && in_array("{$section->year}-{$section->semester}", $activeTerms, true)];
        });

        return [$sections, $ownEnrollments, $activeElsewhere];
    }
}

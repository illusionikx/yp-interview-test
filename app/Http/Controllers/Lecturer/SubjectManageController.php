<?php

namespace App\Http\Controllers\Lecturer;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Subject;
use App\Services\AttemptVoider;
use App\Support\Semester;
use Illuminate\View\View;

class SubjectManageController extends Controller
{
    /**
     * CLS-01: the per-subject, two-tab (Classes default / Exams) hub —
     * subject-scoped and ownership-gated (SEC-03), reusing SectionController's
     * shipped abort_unless idiom rather than inventing a new check (T-12-01).
     *
     * Classes are grouped current-or-future vs past via App\Support\Semester
     * (never a naive year/semester comparison — mirrors HomeController's
     * composite-ordinal discipline), each carrying a bounded enrolled_total
     * aggregate (withCount) for the progress bar.
     *
     * CLS-04/CLS-08 (12-04): the Exams tab is now the real management
     * surface, so exams carry a bounded withCount aggregate — one query for
     * the whole set, never a per-attempt loop — for the grading-progress
     * indicator (graded_attempts_count / attempts_count). The CLS-07 reset
     * confirm-modal copy per exam reuses the shipped AttemptVoider::summarize()
     * (the single voiding authority — see show.blade.php's Submissions
     * panel) rather than re-deriving those counts, one small grouped query
     * per exam (a handful of exams per subject, not a scan of attempt rows).
     */
    public function show(Subject $subject): View
    {
        abort_unless($subject->lecturers()->whereKey(auth()->id())->exists(), 403);

        $sections = Section::where('subject_id', $subject->id)
            ->orderBy('year')
            ->orderBy('semester')
            ->orderBy('sequence')
            ->withCount(['enrollments as enrolled_total' => fn ($query) => $query
                ->where('enrollments.status', EnrollmentStatus::Enrolled->value)])
            ->get();

        $currentOrFutureSections = $sections->reject(
            fn ($section) => (new Semester($section->year, $section->semester))->isPast()
        );
        $pastSections = $sections->filter(
            fn ($section) => (new Semester($section->year, $section->semester))->isPast()
        );

        $currentOrFutureGroups = $this->groupBySemester($currentOrFutureSections);
        $pastGroups = $this->groupBySemester($pastSections);

        $exams = $subject->exams()
            ->withCount([
                'attempts',
                'attempts as graded_attempts_count' => fn ($query) => $query->where('status', 'graded'),
            ])
            ->orderBy('title')
            ->get();

        $attemptCountsByExam = $exams->mapWithKeys(
            fn ($exam) => [$exam->id => app(AttemptVoider::class)->summarize($exam)]
        );

        return view('lecturer.subjects.manage', [
            'subject' => $subject,
            'currentOrFutureGroups' => $currentOrFutureGroups,
            'pastGroups' => $pastGroups,
            'exams' => $exams,
            'attemptCountsByExam' => $attemptCountsByExam,
        ]);
    }

    /**
     * Groups an already-loaded sections collection by (year, semester),
     * ordered oldest-to-newest within the given collection (callers already
     * orderBy year/semester/sequence) — mirrors the student HomeController's
     * in-memory grouping-over-loaded-collection idiom (no N+1).
     *
     * @param  \Illuminate\Support\Collection<int, Section>  $sections
     * @return array<int, array{semester: Semester, sections: \Illuminate\Support\Collection<int, Section>}>
     */
    private function groupBySemester($sections): array
    {
        $groups = [];

        foreach ($sections as $section) {
            $semester = new Semester($section->year, $section->semester);
            $ordinal = $semester->ordinal();

            $groups[$ordinal] ??= [
                'semester' => $semester,
                'sections' => collect(),
            ];

            $groups[$ordinal]['sections']->push($section);
        }

        return $groups;
    }
}

<?php

namespace App\Http\Controllers\Lecturer;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\StoreSectionRequest;
use App\Http\Requests\Lecturer\UpdateSectionRequest;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SectionController extends Controller
{
    /**
     * Display a listing of the sections the acting lecturer can manage
     * (top-level, navbar target) — every section whose subject the
     * lecturer is assigned to via the subject_user pivot (SEC-03).
     */
    public function index(): View
    {
        $sections = Section::whereHas(
            'subject.lecturers',
            fn ($query) => $query->whereKey(auth()->id())
        )->with('subject')->get();

        return view('lecturer.sections.index', compact('sections'));
    }

    /**
     * Show the form for creating a new section, nested under a subject.
     *
     * Ownership-gated (WR-01) for consistency with store() — a lecturer
     * not assigned to this subject must get 403, not merely a hidden UI
     * affordance, matching the SEC-03 intent already enforced on the
     * write paths.
     */
    public function create(Subject $subject): View
    {
        abort_unless($subject->lecturers()->whereKey(auth()->id())->exists(), 403);

        return view('lecturer.sections.create', compact('subject'));
    }

    /**
     * Store a newly created section in storage.
     *
     * The sequence auto-increments per (subject, year, semester) so the
     * computed year-semester-sequence name never collides within a term
     * (SEC-01) — mirrors the Question::position idiom. Wrapped in a
     * transaction with a locking read (WR-03) so two concurrent requests
     * for the same (subject, year, semester) cannot both read the same
     * max(sequence) and attempt to insert the same next value.
     */
    public function store(StoreSectionRequest $request, Subject $subject): RedirectResponse
    {
        DB::transaction(function () use ($request, $subject) {
            $sequence = Section::where('subject_id', $subject->id)
                ->where('year', $request->validated('year'))
                ->where('semester', $request->validated('semester'))
                ->lockForUpdate()
                ->max('sequence') + 1;

            Section::create([
                ...$request->validated(),
                'subject_id' => $subject->id,
                'sequence' => $sequence,
            ]);
        });

        return redirect()->route('lecturer.subjects.manage', $subject)->with('status', 'Section created.');
    }

    /**
     * Show a section's roster (ENR-07) — the enrolled students a lecturer
     * can act on (reject). TOP-LEVEL route per the locked 08-02 route-name
     * contract (lecturer.sections.show), so there is no {subject} route
     * parameter and therefore no subject/section consistency abort_unless
     * to perform here, unlike edit()/destroy(). No Form Request backs a
     * read action, so the same inline SEC-03 ownership check destroy()
     * uses is applied here.
     *
     * Only ENROLLED students are listed — a rejected or withdrawn student
     * is not on the roster.
     */
    public function show(Section $section): View
    {
        abort_unless($section->subject->lecturers()->whereKey(auth()->id())->exists(), 403);

        $section->load('subject');
        $students = $section->enrollments()
            ->wherePivot('status', EnrollmentStatus::Enrolled->value)
            ->get();

        return view('lecturer.sections.show', compact('section', 'students'));
    }

    /**
     * Show the form for editing the specified section.
     *
     * Ownership-gated (WR-01) for consistency with update() — a lecturer
     * not assigned to this subject must get 403, not merely a hidden UI
     * affordance, matching the SEC-03 intent already enforced on the
     * write paths.
     */
    public function edit(Subject $subject, Section $section): View
    {
        abort_unless($section->subject_id === $subject->id, 404);
        abort_unless($subject->lecturers()->whereKey(auth()->id())->exists(), 403);

        return view('lecturer.sections.edit', compact('subject', 'section'));
    }

    /**
     * Update the specified section in storage.
     */
    public function update(UpdateSectionRequest $request, Subject $subject, Section $section): RedirectResponse
    {
        abort_unless($section->subject_id === $subject->id, 404);

        $section->update($request->validated());

        return redirect()->route('lecturer.subjects.manage', $subject)->with('status', 'Section updated.');
    }

    /**
     * Remove the specified section from storage.
     *
     * No Form Request backs this action, so the same per-subject
     * ownership check StoreSectionRequest/UpdateSectionRequest perform
     * is applied inline here — SEC-03 requires deletion to be
     * ownership-gated too, not only create/edit.
     */
    public function destroy(Subject $subject, Section $section): RedirectResponse
    {
        abort_unless($section->subject_id === $subject->id, 404);
        abort_unless($subject->lecturers()->whereKey(auth()->id())->exists(), 403);

        $section->delete();

        return redirect()->route('lecturer.subjects.manage', $subject)->with('status', 'Section deleted.');
    }
}

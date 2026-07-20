<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\StoreSubjectRequest;
use App\Http\Requests\Lecturer\UpdateSubjectRequest;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubjectController extends Controller
{
    /**
     * The home page (lecturer.home) is now the canonical subject list
     * (DASH-01/SUBJ-01) — this route stays reachable (for existing
     * references) but no longer renders a second, divergent table.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('lecturer.home');
    }

    /**
     * Show the form for creating a new subject.
     */
    public function create(): View
    {
        return view('lecturer.subjects.create');
    }

    /**
     * Store a newly created subject in storage.
     */
    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        $subject = Subject::create($request->validated());

        // SUBJ-01: without this, the home list scopes to assigned subjects
        // only, so a subject the lecturer just created would not appear on
        // their own list.
        $subject->lecturers()->syncWithoutDetaching([$request->user()->id]);

        return redirect()->route('lecturer.home')->with('status', 'Subject created.');
    }

    /**
     * Show the form for editing the specified subject.
     */
    public function edit(Subject $subject): View
    {
        return view('lecturer.subjects.edit', compact('subject'));
    }

    /**
     * Update the specified subject in storage.
     */
    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        return redirect()->route('lecturer.home')->with('status', 'Subject updated.');
    }

    /**
     * Remove the specified subject from storage.
     */
    public function destroy(Subject $subject): RedirectResponse
    {
        // exams.subject_id is cascadeOnDelete: deleting a subject would silently
        // wipe every exam under it (published ones included) plus their
        // questions/options — bypassing the "cannot delete a published exam"
        // rule. Refuse to delete a subject that still has any exams.
        if ($subject->exams()->exists()) {
            return redirect()->route('lecturer.home')
                ->with('status', 'Cannot delete a subject that still has exams. Delete or reassign its exams first.');
        }

        // sections.subject_id is cascadeOnDelete, and enrollments.section_id
        // cascades in turn: deleting a subject with classes would silently
        // destroy every class and every student's enrollment record under it.
        // Refuse — the lecturer must remove the classes deliberately first.
        if ($subject->sections()->exists()) {
            return redirect()->route('lecturer.home')
                ->with('status', 'Cannot delete a subject that still has classes. Delete its classes (and their enrollments) first.');
        }

        $subject->delete();

        return redirect()->route('lecturer.home')->with('status', 'Subject deleted.');
    }
}

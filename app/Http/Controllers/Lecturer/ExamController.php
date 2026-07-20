<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\StoreExamRequest;
use App\Http\Requests\Lecturer\UpdateExamRequest;
use App\Models\Exam;
use App\Models\Subject;
use App\Services\AttemptVoider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExamController extends Controller
{
    /**
     * CLS-04 (12-04): the unscoped all-exams listing is folded into the
     * subject hub's Exams tab (SubjectManageController::show()), mirroring
     * `SubjectController::index -> home`. This route stays reachable (the
     * `role:lecturer` gate still 403s a student before the redirect fires)
     * but no longer renders a divergent, lecturer-unscoped table.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('lecturer.home');
    }

    /**
     * Show the form for creating a new exam.
     *
     * CLS-04 (12-04): an optional `?subject=` id pre-selects the subject
     * dropdown so "New exam" from a subject's Exams tab lands pre-scoped.
     * This is a UI convenience only — a forged/mismatched value never
     * bypasses store()'s server-side `subject_id` validation
     * (StoreExamRequest), so it carries no authorization weight.
     */
    public function create(Request $request): View
    {
        $subjects = Subject::orderBy('name')->get();
        $selectedSubjectId = $request->query('subject');

        return view('lecturer.exams.create', compact('subjects', 'selectedSubjectId'));
    }

    /**
     * Store a newly created exam in storage.
     */
    public function store(StoreExamRequest $request): RedirectResponse
    {
        // created_by is always the acting lecturer, stamped server-side —
        // never trust a request `created_by` field (T-02-MA). is_published
        // defaults to false at the DB layer, so a new exam always lands as
        // a draft (D-05/D-06).
        $exam = Exam::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('lecturer.exams.show', $exam)->with('status', 'Exam created.');
    }

    /**
     * Display the specified exam.
     *
     * EDT-02: this is now the two-tab exam editor (Details + Questions),
     * absorbing the standalone edit()/questions.edit forms — so it needs
     * everything both of those used to load: the ordered questions/options
     * (questions/options always render position-ordered, a precondition
     * plan 12-05's reorder controls depend on) plus the subject list the
     * Details tab's form needs.
     */
    public function show(Exam $exam): View
    {
        $exam->load([
            'subject',
            'questions' => fn ($query) => $query->orderBy('position'),
            'questions.options' => fn ($query) => $query->orderBy('position'),
        ]);

        $subjects = Subject::orderBy('name')->get();

        // Computed once, here, so the Submissions panel's summary line and
        // the reset-confirm modal's body can never drift out of sync by
        // being derived twice (UI-SPEC "Shared summary source").
        $attemptCounts = app(AttemptVoider::class)->summarize($exam);

        return view('lecturer.exams.show', compact('exam', 'subjects', 'attemptCounts'));
    }

    /**
     * EDT-02: the standalone details-edit form is absorbed into the
     * `exams.show` two-tab editor's Details tab — this route name stays
     * alive purely as a redirect, mirroring Phase 11's
     * `SubjectController::index -> home` precedent.
     */
    public function edit(Exam $exam): RedirectResponse
    {
        return redirect()->route('lecturer.exams.show', $exam);
    }

    /**
     * Update the specified exam in storage.
     *
     * D-4/D-6 retired the draft-only edit gate (UpdateExamRequest::authorize()
     * now returns true unconditionally) — this method is reachable on a
     * published, attempted exam. EDT-04/D-7 replaces the old immutability
     * with an atomic warn-and-void: the pre-write attempt count decides
     * both whether void() fires and what the toast reports, the write and
     * the conditional void happen inside ONE atomic transaction (both or
     * neither), and voiding never runs before the write — a validation
     * failure never reaches this method at all (it 422s in the Form
     * Request), so it destroys nothing.
     */
    public function update(UpdateExamRequest $request, Exam $exam): RedirectResponse
    {
        $voider = app(AttemptVoider::class);

        // Computed BEFORE the write: this is the count the lecturer saw
        // (or would have seen) on the warning modal, and it must match
        // what the toast reports — not a post-write recount.
        $attemptCounts = $voider->summarize($exam);

        DB::transaction(function () use ($request, $exam, $voider, $attemptCounts) {
            $exam->update($request->validated());

            if ($attemptCounts['total'] > 0) {
                $voider->void($exam);
            }
        });

        $status = $attemptCounts['total'] > 0
            ? "Exam updated. {$attemptCounts['total']} affected attempt(s) were reset."
            : 'Exam updated.';

        return redirect()->route('lecturer.exams.show', $exam)->with('status', $status);
    }

    /**
     * Remove the specified exam from storage.
     *
     * destroy() has no Form Request, so the draft-only gate (D-06/EXM-05)
     * is enforced inline here — a published exam may not be deleted.
     */
    public function destroy(Exam $exam): RedirectResponse
    {
        abort_if($exam->is_published, 403);

        $exam->delete();

        return redirect()->route('lecturer.exams.index')->with('status', 'Exam deleted.');
    }

    /**
     * Publish the specified exam, making it visible to every student
     * enrolled in the exam's subject (CLS-05/D-1 — visibility is derived
     * from subject enrollment, not a separate assignment step).
     */
    public function publish(Exam $exam): RedirectResponse
    {
        $exam->update(['is_published' => true]);

        return back()->with('status', __('Exam published. Students can now see and start it.'));
    }

    /**
     * Unpublish the specified exam back to draft.
     *
     * CLS-06: reversible in BOTH directions, including after students have
     * already attempted it. Toggling touches only `is_published` — it never
     * reads or writes `attempts`, so existing attempts are unaffected either
     * way.
     *
     * This retires the Phase-5 review finding HIGH-02, which previously
     * locked an attempted exam as published-and-immutable to stop a
     * lecturer from re-opening the draft-only edit gate and desyncing
     * computed scores after grading. HIGH-02's protection is SUPERSEDED,
     * not abandoned: D-4/D-6 retire the draft-only edit gate outright, and
     * EDT-04's warn-and-void flow (plan 08) replaces it with a stronger,
     * explicit guarantee — editing an attempted exam voids its attempts,
     * after a warning, rather than silently allowing edits under a still-
     * published label. The old lock was "you may not touch it"; the new
     * protection is "touching it costs you the attempts, and you will be
     * told first."
     */
    public function unpublish(Exam $exam): RedirectResponse
    {
        $exam->update(['is_published' => false]);

        return back()->with('status', __('Exam moved back to draft. Students can no longer start it, but existing attempts are unaffected.'));
    }

    /**
     * CLS-07: reset an exam's submissions. This is a PERMANENT hard delete —
     * `answers.attempt_id` cascades, so every graded score on this exam is
     * destroyed with it (D-2, locked in App\Services\AttemptVoider). The
     * Submissions panel's confirm-modal (INT-02) is the only protection
     * between a lecturer and that loss; this action re-derives no counts of
     * its own, because the modal already gated the click before the
     * request ever arrived here — show() computed the counts once.
     *
     * The delete itself lives in AttemptVoider::void() — shared with EDT-04
     * (plan 08) — never inline here, never a second delete path.
     *
     * INT-03 falls out for free: deleting the rows releases
     * attempts.unique(exam_id, user_id), so a reset student's next
     * AttemptController::store() call simply creates a fresh attempt.
     */
    public function resetSubmissions(Exam $exam): RedirectResponse
    {
        $deleted = app(AttemptVoider::class)->void($exam);

        return back()->with('status', __('Reset :count submission(s) for ":title".', [
            'count' => $deleted,
            'title' => $exam->title,
        ]));
    }
}
